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
     * Endpoint AJAX para calcular parcelas no checkout
     */
    public function calcularParcelas(Request $request) {
        $totalProdutos = floatval($request->getParam('total_produtos', 0));
        $totalTaxas = floatval($request->getParam('total_taxas', 0));

        $opcoes = $this->carneService->calcularParcelas($totalProdutos, $totalTaxas);
        $this->json(['success' => true, 'parcelas' => $opcoes]);
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
