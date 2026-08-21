<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;

/**
 * Controller para página de Documentação de Envios.
 * Permite baixar PDFs de containers e faturas por embarque.
 */
class AdminDocumentacaoEnviosController extends Controller {

    public function index(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);

        $this->view('admin/documentacao-envios', [
            'sidebarActive' => 'documentacao-envios',
        ]);
    }
}
