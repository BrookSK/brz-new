<?php
namespace App\Services;

use App\Models\Carne;
use App\Services\PaymentService;
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
    public function criarCarne($pedidoId, $clienteId, $totalProdutos, $totalTaxas, $qtdParcelas, $dadosCliente = []) {
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

        // Gerar boletos da primeira parcela automaticamente
        try {
            $parcelas = $this->carneModel->getParcelas($carneId);
            if (!empty($parcelas[0])) {
                $this->gerarBoletosParcela($parcelas[0], $pedidoId, $dadosCliente);
            }
        } catch (\Exception $e) {
            // Log do erro mas não falha a criação do carnê
            error_log('[CARNE] Erro ao gerar boletos da 1ª parcela: ' . $e->getMessage());
        }

        $this->dispararNotificacao($carneId, null, 'carne_criado');
        return $carneId;
    }

    /**
     * Gera os dois boletos de uma parcela (Câmbio Real + Appmax)
     */
    public function gerarBoletosParcela($parcela, $pedidoId, $dadosCliente = []) {
        $paymentService = new PaymentService();
        $parcelaId = $parcela['id'];

        // Dados do cliente para os gateways
        $clientData = [
            'name' => $dadosCliente['nome'] ?? '',
            'email' => $dadosCliente['email'] ?? '',
            'document' => $dadosCliente['documento'] ?? '',
            'birth_date' => $dadosCliente['data_nascimento'] ?? '',
            'phone' => $dadosCliente['telefone'] ?? '',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1',
            'address' => [
                'state' => $dadosCliente['estado'] ?? '',
                'city' => $dadosCliente['cidade'] ?? '',
                'zip_code' => $dadosCliente['cep'] ?? '',
                'district' => $dadosCliente['bairro'] ?? '',
                'street' => $dadosCliente['endereco'] ?? '',
                'number' => $dadosCliente['numero'] ?? '',
            ],
        ];

        // Se não temos dados do cliente, buscar do banco
        if (empty($clientData['name'])) {
            try {
                $stmt = $this->db->prepare("
                    SELECT u.nome, u.email, u.documento, u.data_nascimento, u.telefone, u.celular,
                           e.estado, e.cidade, e.cep, e.bairro, e.endereco, e.numero
                    FROM carnes c
                    JOIN usuarios u ON c.cliente_id = u.id
                    LEFT JOIN enderecos e ON e.usuario_id = u.id AND e.principal = 1
                    WHERE c.id = :cid LIMIT 1
                ");
                $stmt->execute([':cid' => $parcela['carne_id']]);
                $row = $stmt->fetch(\PDO::FETCH_ASSOC);
                if ($row) {
                    $clientData['name'] = $row['nome'] ?? '';
                    $clientData['email'] = $row['email'] ?? '';
                    $clientData['document'] = $row['documento'] ?? '';
                    $clientData['birth_date'] = $row['data_nascimento'] ?? '';
                    $clientData['phone'] = $row['telefone'] ?? ($row['celular'] ?? '');
                    $clientData['address'] = [
                        'state' => $row['estado'] ?? '',
                        'city' => $row['cidade'] ?? '',
                        'zip_code' => $row['cep'] ?? '',
                        'district' => $row['bairro'] ?? '',
                        'street' => $row['endereco'] ?? '',
                        'number' => $row['numero'] ?? '',
                    ];
                }
            } catch (\Exception $e) {}
        }

        $descBase = "Carnê Braziliana - Pedido #{$pedidoId} - Parcela {$parcela['numero_parcela']}";

        // 1. Boleto Produtos via Câmbio Real (mínimo USD 1.00 na API)
        if ($parcela['valor_produtos'] > 0) {
            // Câmbio Real exige mínimo de USD 1.00 - verificar antes de chamar
            $minBrl = 6.0; // ~USD 1.00 com margem
            if ($parcela['valor_produtos'] < $minBrl) {
                error_log("[CARNE] Valor produtos R$ {$parcela['valor_produtos']} abaixo do mínimo Câmbio Real (R$ {$minBrl}). Boleto não gerado.");
                $this->carneModel->atualizarParcela($parcelaId, [
                    'boleto_produtos_url' => '',
                    'boleto_produtos_codigo' => 'Valor abaixo do mínimo para geração de boleto',
                ]);
            } else {
            try {
                $crResult = $paymentService->createCambioRealDirectPaymentProdutoBoleto(
                    (int) $pedidoId,
                    (float) $parcela['valor_produtos'],
                    $descBase . ' - Produtos',
                    $clientData
                );

                if (!empty($crResult['success'])) {
                    // Extrair URL do boleto - tentar múltiplos campos da resposta
                    $crUrl = $crResult['bank_slip_url'] ?? ($crResult['invoice_url'] ?? '');
                    $crDigitable = $crResult['digitable_line'] ?? '';
                    $crPaymentId = $crResult['payment_id'] ?? '';

                    // Se não encontrou URL nos campos padrão, buscar no raw da resposta
                    if (empty($crUrl) && !empty($crResult['raw'])) {
                        $raw = $crResult['raw'];
                        $data = is_array($raw['data'] ?? null) ? $raw['data'] : [];
                        $tx = is_array($data['transaction'] ?? null) ? $data['transaction'] : [];

                        // Campos possíveis para URL do boleto
                        $urlCandidates = [
                            $tx['ticket_url'] ?? '',
                            $tx['url'] ?? '',
                            $tx['boleto_url'] ?? '',
                            $tx['bank_slip_url'] ?? '',
                            $data['ticket_url'] ?? '',
                            $data['url'] ?? '',
                            $data['checkout_url'] ?? '',
                            $data['boleto_url'] ?? '',
                            $raw['ticket_url'] ?? '',
                            $raw['url'] ?? '',
                        ];
                        foreach ($urlCandidates as $candidate) {
                            if (!empty($candidate) && is_string($candidate) && strpos($candidate, 'http') === 0) {
                                $crUrl = $candidate;
                                break;
                            }
                        }

                        // Campos possíveis para linha digitável
                        if (empty($crDigitable)) {
                            $lineCandidates = [
                                $tx['digitable_line'] ?? '',
                                $tx['linha_digitavel'] ?? '',
                                $tx['barcode_number'] ?? '',
                                $tx['barcode'] ?? '',
                                $data['digitable_line'] ?? '',
                                $data['linha_digitavel'] ?? '',
                                $data['barcode'] ?? '',
                            ];
                            foreach ($lineCandidates as $candidate) {
                                if (!empty($candidate) && is_string($candidate) && strlen($candidate) > 10) {
                                    $crDigitable = $candidate;
                                    break;
                                }
                            }
                        }
                    }

                    $this->carneModel->atualizarParcela($parcelaId, [
                        'boleto_produtos_url' => $crUrl,
                        'boleto_produtos_codigo' => $crDigitable,
                        'boleto_produtos_id_externo' => $crPaymentId,
                    ]);

                    // Log para debug
                    error_log('[CARNE] Câmbio Real boleto gerado: url=' . $crUrl . ' line=' . substr($crDigitable, 0, 20) . '... id=' . $crPaymentId);
                } else {
                    error_log('[CARNE] Erro Câmbio Real boleto: ' . ($crResult['error'] ?? 'desconhecido'));
                    if (!empty($crResult['raw'])) {
                        error_log('[CARNE] Câmbio Real raw: ' . json_encode($crResult['raw']));
                    }
                }
            } catch (\Exception $e) {
                error_log('[CARNE] Exception Câmbio Real: ' . $e->getMessage());
            }
            } // fecha else do mínimo
        }

        // 2. Boleto Taxas via Appmax
        if ($parcela['valor_taxas'] > 0) {
            try {
                $appmaxDados = [
                    'billingType' => 'BOLETO',
                    'customer_name' => $clientData['name'],
                    'customer_email' => $clientData['email'],
                    'customer_document' => $clientData['document'],
                    'customer_phone' => $clientData['phone'],
                    'customer_zipcode' => $clientData['address']['zip_code'] ?? '',
                    'customer_address' => $clientData['address']['street'] ?? '',
                    'customer_address_number' => $clientData['address']['number'] ?? '',
                    'customer_province' => $clientData['address']['district'] ?? '',
                    'customer_city' => $clientData['address']['city'] ?? '',
                    'customer_state' => $clientData['address']['state'] ?? '',
                    'products' => [[
                        'sku' => 'CARNE_TAXA_' . $pedidoId . '_' . $parcela['numero_parcela'],
                        'name' => $descBase . ' - Taxas',
                        'quantity' => 1,
                        'unit_value' => (int) round($parcela['valor_taxas'] * 100),
                        'type' => 'service',
                    ]],
                    'products_value_cents' => (int) round($parcela['valor_taxas'] * 100),
                    'shipping_value_cents' => 0,
                    'discount_value_cents' => 0,
                ];

                $appmaxResult = $paymentService->processarPagamento(
                    $appmaxDados,
                    (float) $parcela['valor_taxas'],
                    'BRL',
                    $descBase . ' - Taxas'
                );

                if (!empty($appmaxResult['success'])) {
                    $boletoUrl = $appmaxResult['bankSlipUrl'] ?? ($appmaxResult['invoiceUrl'] ?? '');
                    $digitableLine = $appmaxResult['digitableLine'] ?? '';
                    $paymentId = $appmaxResult['payment_id'] ?? '';

                    $this->carneModel->atualizarParcela($parcelaId, [
                        'boleto_taxas_url' => $boletoUrl,
                        'boleto_taxas_codigo' => $digitableLine,
                        'boleto_taxas_id_externo' => $paymentId,
                    ]);
                } else {
                    error_log('[CARNE] Erro Appmax boleto: ' . ($appmaxResult['error'] ?? 'desconhecido'));
                }
            } catch (\Exception $e) {
                error_log('[CARNE] Exception Appmax: ' . $e->getMessage());
            }
        }
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
