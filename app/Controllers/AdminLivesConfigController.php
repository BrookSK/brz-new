<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\LiveStreamService;
use App\Services\LiveShoppingService;

class AdminLivesConfigController {
    private $auth;
    private $streamService;
    private $shoppingService;

    public function __construct() {
        $this->auth = new \App\Services\AuthService();
        $this->auth->requerPerfis(['admin']);
        $this->streamService = new LiveStreamService();
        $this->shoppingService = new LiveShoppingService();
    }

    /**
     * Página de configurações do módulo Lives
     */
    public function index(Request $request) {
        $config = $this->shoppingService->getConfig();
        $credentials = $this->streamService->getCredentials();
        $quota = $this->shoppingService->checkQuota();
        $activePage = 'lives-config';
        $title = __('admin.lives_config.page_title', 'Configurações - Lives');
        $sidebarActive = 'lives-config';

        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';
        ob_start();
        include __DIR__ . '/../Views/admin/lives/config.php';
        $content = ob_get_clean();
        include __DIR__ . '/../Views/layouts/admin.php';
    }

    /**
     * Salvar configurações
     */
    public function store(Request $request) {
        // Salvar credenciais CF (criptografadas)
        $accountId = trim($request->getParam('cf_account_id') ?? '');
        $apiToken = trim($request->getParam('cf_api_token') ?? '');
        $subdomain = trim($request->getParam('cf_stream_subdomain') ?? '');

        if (!empty($accountId) && !empty($apiToken)) {
            $this->streamService->saveCredentials($accountId, $apiToken, $subdomain);
        }

        // Salvar configurações gerais
        $config = [
            'modo_operacao' => $request->getParam('modo_operacao') ?? 'desligado',
            'minutos_inclusos' => (int) ($request->getParam('minutos_inclusos') ?? 300),
            'modo_excedente' => $request->getParam('modo_excedente') ?? 'block',
            'preco_minuto_excedente' => (float) ($request->getParam('preco_minuto_excedente') ?? 0),
        ];

        // Validar modo_operacao
        if (!in_array($config['modo_operacao'], ['online', 'teste', 'desligado'])) {
            $config['modo_operacao'] = 'desligado';
        }

        $this->shoppingService->saveConfig($config);

        // Testar conexão se credenciais foram fornecidas
        $testResult = null;
        if (!empty($accountId) && !empty($apiToken)) {
            $testResult = $this->streamService->testConnection();
        }

        if ($request->getParam('ajax')) {
            http_response_code(200);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => true,
                'connection_test' => $testResult,
            ]);
            return;
        }

        header('Location: /admin/configuracoes/lives?saved=1');
        exit;
    }
}
