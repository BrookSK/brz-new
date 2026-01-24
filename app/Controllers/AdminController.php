<?php
namespace App\Controllers;

use App\Core\Request;

class AdminController extends Controller {
    
    public function dashboard(Request $request) {
        // Estatísticas básicas
        $stats = [
            'total_pedidos' => 0,
            'pedidos_por_status' => [],
            'financeiro' => ['faturamento_usd' => 0],
            'total_produtos' => 0,
            'total_usuarios' => 0
        ];
        
        // Iniciar buffer manualmente
        ob_start();
        include __DIR__ . '/../Views/admin/dashboard.php';
        $content = ob_get_clean();
        
        // Incluir layout
        include __DIR__ . '/../Views/layouts/admin.php';
    }
}