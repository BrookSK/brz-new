<?php
namespace App\Controllers;

use App\Core\Request;
use App\Models\Carne;
use App\Services\CarneService;
use Config\Database;

class CarneController extends Controller {
    private $carneModel;
    private $carneService;

    public function __construct() {
        $this->carneModel = new Carne();
        $this->carneService = new CarneService();
    }

    /**
     * Página de termos do Carnê Braziliana
     */
    public function termos(Request $request) {
        require __DIR__ . '/../Views/carne/termos.php';
    }

    /**
     * Tela de conclusão do pedido com Carnê
     */
    public function conclusao(Request $request, $id) {
        if (empty($_SESSION['usuario_id'])) {
            $this->redirect('/login');
        }

        $carne = $this->carneModel->getCompleto($id);
        if (!$carne || $carne['cliente_id'] != $_SESSION['usuario_id']) {
            $_SESSION['message'] = 'Carnê não encontrado.';
            $_SESSION['message_type'] = 'danger';
            $this->redirect('/minha-conta');
        }

        $primeiraParcela = !empty($carne['parcelas']) ? $carne['parcelas'][0] : null;
        require __DIR__ . '/../Views/carne/conclusao.php';
    }

    /**
     * Área "Meus Carnês" do cliente
     */
    public function meusCarnes(Request $request) {
        if (empty($_SESSION['usuario_id'])) {
            $this->redirect('/login');
        }

        $carnes = $this->carneModel->getByCliente($_SESSION['usuario_id']);
        require __DIR__ . '/../Views/carne/meus-carnes.php';
    }

    /**
     * Detalhe de um carnê do cliente
     */
    public function detalhe(Request $request, $id) {
        if (empty($_SESSION['usuario_id'])) {
            $this->redirect('/login');
        }

        $carne = $this->carneModel->getCompleto($id);
        if (!$carne || $carne['cliente_id'] != $_SESSION['usuario_id']) {
            $_SESSION['message'] = 'Carnê não encontrado.';
            $_SESSION['message_type'] = 'danger';
            $this->redirect('/meus-carnes');
        }

        require __DIR__ . '/../Views/carne/detalhe.php';
    }

    /**
     * Solicitar segunda via de boleto
     */
    public function segundaVia(Request $request, $parcelaId) {
        if (empty($_SESSION['usuario_id'])) {
            $this->json(['success' => false, 'message' => 'Não autenticado'], 401);
        }

        $parcela = $this->carneModel->getParcela($parcelaId);
        if (!$parcela) {
            $this->json(['success' => false, 'message' => 'Parcela não encontrada'], 404);
        }

        $carne = $this->carneModel->find($parcela['carne_id']);
        if (!$carne || $carne['cliente_id'] != $_SESSION['usuario_id']) {
            $this->json(['success' => false, 'message' => 'Acesso negado'], 403);
        }

        $this->carneModel->atualizarParcela($parcelaId, [
            'reemissao_count' => $parcela['reemissao_count'] + 1,
            'status' => 'reemitida'
        ]);

        $this->carneModel->registrarHistorico($carne['id'], $parcelaId, 'boleto_reemitido',
            "Segunda via solicitada para parcela {$parcela['numero_parcela']}");

        $this->json(['success' => true, 'message' => 'Segunda via gerada com sucesso']);
    }

    /**
     * Antecipar pagamento de uma parcela futura (gerar boletos/PIX antes do vencimento)
     */
    public function antecipar(Request $request, $parcelaId) {
        if (empty($_SESSION['usuario_id'])) {
            $this->json(['success' => false, 'message' => 'Não autenticado'], 401);
            return;
        }

        $parcela = $this->carneModel->getParcela($parcelaId);
        if (!$parcela) {
            $this->json(['success' => false, 'message' => 'Parcela não encontrada'], 404);
            return;
        }

        $carne = $this->carneModel->find($parcela['carne_id']);
        if (!$carne || (int) $carne['cliente_id'] !== (int) $_SESSION['usuario_id']) {
            $this->json(['success' => false, 'message' => 'Acesso negado'], 403);
            return;
        }

        if ($parcela['status'] === 'paga') {
            $this->json(['success' => false, 'message' => 'Esta parcela já está paga'], 400);
            return;
        }

        if (!in_array($parcela['status'], ['pendente'], true)) {
            $this->json(['success' => false, 'message' => 'Esta parcela já está em andamento. Use "Regerar PIX" ou "2ª Via Boleto".'], 400);
            return;
        }

        // Verificar se a parcela anterior está paga (não pode pular parcelas)
        $numeroParcela = (int) ($parcela['numero_parcela'] ?? 0);
        if ($numeroParcela > 1) {
            $parcelas = $this->carneModel->getParcelas($carne['id']);
            foreach ($parcelas as $p) {
                if ((int) $p['numero_parcela'] === $numeroParcela - 1) {
                    if ($p['status'] !== 'paga') {
                        $this->json(['success' => false, 'message' => 'A parcela anterior (#' . ($numeroParcela - 1) . ') ainda não foi paga. Pague na ordem.'], 400);
                        return;
                    }
                    break;
                }
            }
        }

        try {
            // Ativar a parcela
            $this->carneModel->atualizarParcela($parcelaId, ['status' => 'aguardando_pagamento']);

            // Gerar boletos/PIX
            $clientData = $this->carneService->buildClientData([], $carne['id']);
            $descBase = "Carnê Braziliana - Pedido #{$carne['pedido_id']} - Parcela {$parcela['numero_parcela']}";
            $this->carneService->gerarBoletosParcela($parcela, $carne['pedido_id'], $clientData);

            $this->carneModel->registrarHistorico($carne['id'], $parcelaId, 'parcela_antecipada',
                "Parcela {$parcela['numero_parcela']} antecipada pelo cliente");

            $this->json(['success' => true, 'message' => 'Parcela antecipada! Os boletos foram gerados.']);
        } catch (\Exception $e) {
            error_log('[CARNE] Erro ao antecipar parcela: ' . $e->getMessage());
            $this->json(['success' => false, 'message' => 'Erro ao gerar boletos: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Regerar PIX de uma parcela (quando expirou)
     */
    public function regerarPix(Request $request, $parcelaId) {
        if (empty($_SESSION['usuario_id'])) {
            $this->json(['success' => false, 'message' => 'Não autenticado'], 401);
        }

        $parcela = $this->carneModel->getParcela($parcelaId);
        if (!$parcela) {
            $this->json(['success' => false, 'message' => 'Parcela não encontrada'], 404);
        }

        $carne = $this->carneModel->find($parcela['carne_id']);
        if (!$carne || $carne['cliente_id'] != $_SESSION['usuario_id']) {
            $this->json(['success' => false, 'message' => 'Acesso negado'], 403);
        }

        if ($parcela['status'] === 'paga') {
            $this->json(['success' => false, 'message' => 'Parcela já está paga'], 400);
        }

        try {
            $clientData = $this->carneService->buildClientData([], $carne['id']);
            $descBase = "Carnê Braziliana - Pedido #{$carne['pedido_id']} - Parcela {$parcela['numero_parcela']}";
            $this->carneService->gerarPixParcela($parcela, $carne['pedido_id'], $clientData, $descBase);

            $this->carneModel->registrarHistorico($carne['id'], $parcelaId, 'pix_regerado',
                "PIX regerado para parcela {$parcela['numero_parcela']}");

            // Recarregar parcela com dados atualizados
            $parcelaAtualizada = $this->carneModel->getParcela($parcelaId);

            $this->json([
                'success' => true,
                'message' => 'PIX regerado com sucesso',
                'parcela' => $parcelaAtualizada
            ]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'message' => 'Erro ao regerar PIX: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Endpoint AJAX para calcular parcelas no checkout
     */
    public function calcularParcelas(Request $request) {
        $totalProdutos = floatval($request->getParam('total_produtos', 0));
        $totalTaxas = floatval($request->getParam('total_taxas', 0));

        $opcoes = $this->carneService->calcularParcelas($totalProdutos, $totalTaxas);

        // Buscar valor mínimo configurado pelo admin
        $valorMinimo = 0.0;
        try {
            $db = \Config\Database::getConnection();
            $st = $db->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = 'carne_valor_minimo' LIMIT 1");
            $st->execute();
            $valorMinimo = (float) ($st->fetchColumn() ?: 0);
        } catch (\Exception $e) {}

        // Mínimo técnico para parcelar (R$20 por boleto × 2 parcelas)
        $minimoParcelamento = $this->carneService->calcularMinimoParcelamento($totalProdutos, $totalTaxas);

        $this->json([
            'success'            => true,
            'parcelas'           => $opcoes,
            'valor_minimo'       => $valorMinimo,
            'minimo_parcelamento'=> $minimoParcelamento,
            'total'              => $totalProdutos + $totalTaxas,
        ]);
    }

    /**
     * Cron endpoint — protegido por token
     * URL: /cron/carne/processar?token=SEU_TOKEN
     */
    public function cron(Request $request) {
        // 1. Tentar pegar o secret do env
        $secret = null;
        if (isset($_ENV['CRON_SECRET'])) {
            $secret = (string) $_ENV['CRON_SECRET'];
        } elseif (isset($_SERVER['CRON_SECRET'])) {
            $secret = (string) $_SERVER['CRON_SECRET'];
        }

        // 2. Se não tem no env, tentar pegar do banco (configuracoes_sistema)
        if ($secret === null || trim($secret) === '') {
            try {
                $db = Database::getConnection();
                $stmt = $db->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = 'cron_secret' LIMIT 1");
                $stmt->execute();
                $val = $stmt->fetchColumn();
                if ($val) $secret = (string) $val;
            } catch (\Exception $e) {
                // ignora
            }
        }

        // 3. Se existe um secret configurado, validar o token
        if ($secret !== null && trim($secret) !== '') {
            $token = (string) $request->getParam('token', '');
            if ($token === '' && isset($_SERVER['HTTP_X_CRON_TOKEN'])) {
                $token = (string) $_SERVER['HTTP_X_CRON_TOKEN'];
            }
            if (!hash_equals(trim($secret), trim($token))) {
                $this->json(['success' => false, 'error' => 'Unauthorized'], 403);
            }
        }

        // 4. Executar o processamento
        try {
            $resultados = $this->carneService->processarCron();
            $this->json(['success' => true, 'resultados' => $resultados]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
