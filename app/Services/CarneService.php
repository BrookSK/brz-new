<?php
namespace App\Services;

use App\Models\Carne;
use Config\Database;

class CarneService {
    private $carneModel;
    private $db;

    public function __construct() {
        $this->carneModel = new Carne();
        $this->db = Database::getConnection();
    }

    /**
     * Calcula parcelas para exibição no checkout
     */
    public function calcularParcelas($totalProdutos, $totalTaxas, $maxParcelas = 12) {
        $total = $totalProdutos + $totalTaxas;
        $opcoes = [];

        for ($i = 1; $i <= $maxParcelas; $i++) {
            $vProd = round($totalProdutos / $i, 2);
            $vTaxa = round($totalTaxas / $i, 2);
            $vTotal = round($total / $i, 2);

            $opcoes[] = [
                'parcelas' => $i,
                'valor_parcela_produtos' => $vProd,
                'valor_parcela_taxas' => $vTaxa,
                'valor_parcela_total' => $vTotal,
                'total' => $total
            ];
        }
        return $opcoes;
    }

    /**
     * Cria carnê a partir do checkout
     */
    public function criarCarne($pedidoId, $clienteId, $totalProdutos, $totalTaxas, $qtdParcelas) {
        $dados = [
            'pedido_id' => $pedidoId,
            'cliente_id' => $clienteId,
            'total_produtos' => $totalProdutos,
            'total_taxas' => $totalTaxas,
            'total_geral' => round($totalProdutos + $totalTaxas, 2),
            'quantidade_parcelas' => $qtdParcelas,
            'status' => 'aguardando_primeira_parcela',
            'termos_aceitos' => 1,
            'termos_aceitos_em' => date('Y-m-d H:i:s')
        ];

        $carneId = $this->carneModel->criarComParcelas($dados, $qtdParcelas);
        $this->dispararNotificacao($carneId, null, 'carne_criado');
        return $carneId;
    }

    /**
     * Processa pagamento de boleto via webhook
     */
    public function processarPagamentoBoleto($idExterno, $tipo) {
        // tipo: 'produtos' (Câmbio Real) ou 'taxas' (Appmax)
        $campo = "boleto_{$tipo}_id_externo";
        $stmt = $this->db->prepare("SELECT * FROM carne_parcelas WHERE {$campo} = :ext");
        $stmt->execute([':ext' => $idExterno]);
        $parcela = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$parcela) return false;

        $this->carneModel->registrarPagamentoBoleto($parcela['id'], $tipo);
        $this->dispararNotificacao($parcela['carne_id'], $parcela['id'], 'pagamento_confirmado');
        return true;
    }

    /**
     * Rotina do cron - processa parcelas
     */
    public function processarCron() {
        $hoje = date('Y-m-d');
        $resultados = ['vencidas' => 0, 'geradas' => 0, 'notificadas' => 0, 'quitados' => 0];

        // 1. Marcar parcelas vencidas
        $stmt = $this->db->prepare("
            UPDATE carne_parcelas SET status = 'vencida'
            WHERE status = 'aguardando_pagamento' AND vencimento < :hoje
        ");
        $stmt->execute([':hoje' => $hoje]);
        $resultados['vencidas'] = $stmt->rowCount();

        // 2. Marcar em atraso (vencidas há mais de 7 dias)
        $stmt = $this->db->prepare("
            UPDATE carne_parcelas SET status = 'em_atraso'
            WHERE status = 'vencida' AND vencimento < DATE_SUB(:hoje, INTERVAL 7 DAY)
        ");
        $stmt->execute([':hoje' => $hoje]);

        // 3. Gerar próximas parcelas (ativar pendentes cujo vencimento se aproxima)
        $proximoVenc = date('Y-m-d', strtotime('+7 days'));
        $stmt = $this->db->prepare("
            UPDATE carne_parcelas SET status = 'aguardando_pagamento'
            WHERE status = 'pendente' AND vencimento <= :prox
        ");
        $stmt->execute([':prox' => $proximoVenc]);
        $resultados['geradas'] = $stmt->rowCount();

        // 4. Atualizar status dos carnês
        $stmt = $this->db->query("SELECT DISTINCT carne_id FROM carne_parcelas WHERE status IN ('vencida','em_atraso')");
        $carnesComAtraso = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($carnesComAtraso as $cid) {
            $this->carneModel->update($cid, ['status' => 'com_atraso']);
        }

        // 5. Verificar carnês quitados
        $stmt = $this->db->query("
            SELECT c.id FROM carnes c
            WHERE c.status NOT IN ('quitado','encerrado','liberado_envio')
            AND NOT EXISTS (
                SELECT 1 FROM carne_parcelas cp WHERE cp.carne_id = c.id AND cp.status != 'paga'
            )
        ");
        $carnesQuitados = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        foreach ($carnesQuitados as $cid) {
            $this->carneModel->update($cid, ['status' => 'quitado', 'envio_liberado' => 1]);
            $this->carneModel->registrarHistorico($cid, null, 'carne_quitado', 'Carnê quitado via cron');
            $this->dispararNotificacao($cid, null, 'carne_quitado');
            $resultados['quitados']++;
        }

        // 6. Notificar parcelas próximas do vencimento (3 dias)
        $tresDias = date('Y-m-d', strtotime('+3 days'));
        $stmt = $this->db->prepare("
            SELECT cp.*, c.cliente_id FROM carne_parcelas cp
            JOIN carnes c ON cp.carne_id = c.id
            WHERE cp.status = 'aguardando_pagamento' AND cp.vencimento = :data
        ");
        $stmt->execute([':data' => $tresDias]);
        $proximasVencer = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($proximasVencer as $p) {
            $this->dispararNotificacao($p['carne_id'], $p['id'], 'parcela_proxima_vencimento');
            $resultados['notificadas']++;
        }

        return $resultados;
    }

    /**
     * Dispara notificação (email + webhook)
     */
    public function dispararNotificacao($carneId, $parcelaId, $evento) {
        $carne = $this->carneModel->getCompleto($carneId);
        if (!$carne) return;

        $parcela = $parcelaId ? $this->carneModel->getParcela($parcelaId) : null;

        $payload = [
            'evento' => $evento,
            'carne_id' => $carneId,
            'pedido_id' => $carne['pedido_id'],
            'cliente' => $carne['cliente_nome'],
            'status' => $carne['status'],
            'parcela' => $parcela
        ];

        // Webhook
        $this->enviarWebhook($carneId, $parcelaId, $evento, $payload);

        // Email (registrar para envio)
        $this->registrarNotificacaoEmail($carneId, $parcelaId, $evento, $carne['cliente_email'], $payload);
    }

    private function enviarWebhook($carneId, $parcelaId, $evento, $payload) {
        $stmt = $this->db->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = 'carne_webhook_ativo'");
        $stmt->execute();
        $ativo = $stmt->fetchColumn();
        if (!$ativo) return;

        $stmt = $this->db->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = 'carne_webhook_url'");
        $stmt->execute();
        $url = $stmt->fetchColumn();
        if (empty($url)) return;

        $stmt = $this->db->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = 'carne_eventos_webhook'");
        $stmt->execute();
        $eventos = explode(',', $stmt->fetchColumn() ?: '');
        if (!in_array($evento, $eventos)) return;

        $status = 'pendente';
        $erro = null;

        try {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 10
            ]);
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            $status = ($httpCode >= 200 && $httpCode < 300) ? 'enviado' : 'erro';
            if ($status === 'erro') $erro = "HTTP {$httpCode}: {$response}";
        } catch (\Exception $e) {
            $status = 'erro';
            $erro = $e->getMessage();
        }

        $stmt = $this->db->prepare("
            INSERT INTO carne_notificacoes (carne_id, parcela_id, evento, canal, payload, status, erro_mensagem)
            VALUES (:cid, :pid, :ev, 'webhook', :pay, :st, :err)
        ");
        $stmt->execute([
            ':cid' => $carneId, ':pid' => $parcelaId, ':ev' => $evento,
            ':pay' => json_encode($payload), ':st' => $status, ':err' => $erro
        ]);
    }

    private function registrarNotificacaoEmail($carneId, $parcelaId, $evento, $email, $payload) {
        $stmt = $this->db->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = 'carne_email_ativo'");
        $stmt->execute();
        if (!$stmt->fetchColumn()) return;

        $stmt = $this->db->prepare("
            INSERT INTO carne_notificacoes (carne_id, parcela_id, evento, canal, destinatario, payload, status)
            VALUES (:cid, :pid, :ev, 'email', :dest, :pay, 'pendente')
        ");
        $stmt->execute([
            ':cid' => $carneId, ':pid' => $parcelaId, ':ev' => $evento,
            ':dest' => $email, ':pay' => json_encode($payload)
        ]);
    }

    /**
     * Gera crédito em carteira para produto indisponível
     */
    public function gerarCreditoCarteira($carneId, $valor, $observacao, $adminId) {
        $carne = $this->carneModel->find($carneId);
        if (!$carne) return false;

        // Atualizar compra interna
        $stmt = $this->db->prepare("
            UPDATE carne_compras_internas SET 
                status = 'produto_indisponivel', produto_indisponivel = 1,
                acao_indisponibilidade = 'credito_carteira', valor_credito = :val, observacoes = :obs
            WHERE carne_id = :cid
        ");
        $stmt->execute([':val' => $valor, ':obs' => $observacao, ':cid' => $carneId]);

        $this->carneModel->registrarHistorico($carneId, null, 'credito_carteira',
            "Crédito de R$ " . number_format($valor, 2, ',', '.') . " gerado na carteira", null, $adminId);

        return true;
    }

    /**
     * Verifica se carnê está disponível para o contexto do checkout
     */
    public function isCarneDisponivel($moeda = 'BRL', $paisEnvio = 'BR') {
        try {
            $stmt = $this->db->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = 'carne_ativo' LIMIT 1");
            $stmt->execute();
            $ativo = $stmt->fetchColumn();
            // Se não encontrou o registro, considerar desativado
            if ($ativo === false || $ativo === null) return false;
            return ((string) $ativo === '1') && $moeda === 'BRL' && $paisEnvio === 'BR';
        } catch (\Exception $e) {
            return false;
        }
    }
}
