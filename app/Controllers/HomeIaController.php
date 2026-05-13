<?php
namespace App\Controllers;

use App\Core\Request;

class HomeIaController extends Controller {
    public function index(Request $request) {
        // Garantir sessão ativa e usuário identificado
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        // Se usuario_id não está na sessão, tentar identificar
        if (empty($_SESSION['usuario_id'])) {
            try {
                $auth = new \App\Services\AuthService();
                $u = $auth->getUsuarioLogado();
                if (!empty($u['id'])) {
                    $_SESSION['usuario_id'] = (int) $u['id'];
                }
            } catch (\Throwable $e) {}
        }
        // Fallback: remember_token
        if (empty($_SESSION['usuario_id']) && !empty($_COOKIE['remember_token'])) {
            try {
                $pdo = \Config\Database::getConnection();
                $st = $pdo->prepare("SELECT id FROM usuarios WHERE remember_token = ? LIMIT 1");
                $st->execute([$_COOKIE['remember_token']]);
                $uid = (int) ($st->fetchColumn() ?: 0);
                if ($uid > 0) $_SESSION['usuario_id'] = $uid;
            } catch (\Throwable $e) {}
        }
        include __DIR__ . '/../Views/home_ia.php';
    }

    /**
     * Página inicial do painel (boas-vindas)
     */
    public function inicio(Request $request) {
        echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>BRI — Início</title>
<style>
body{margin:0;font-family:system-ui,-apple-system,sans-serif;background:#F5F7FA;display:flex;align-items:center;justify-content:center;min-height:100vh;color:#1F2937;}
.welcome{text-align:center;padding:40px;max-width:480px;}
.welcome-icon{width:64px;height:64px;margin:0 auto 20px;background:#18253D;border-radius:50%;display:flex;align-items:center;justify-content:center;color:#fff;font-size:28px;}
.welcome h1{font-size:22px;font-weight:700;color:#18253D;margin:0 0 8px;}
.welcome p{font-size:14px;color:#64748B;line-height:1.6;margin:0 0 24px;}
.tips{display:grid;gap:10px;text-align:left;}
.tip{background:#fff;border:1px solid #EBF0F6;border-radius:10px;padding:12px 16px;font-size:13px;color:#374151;display:flex;align-items:center;gap:10px;}
.tip i{color:#18253D;font-size:16px;flex-shrink:0;}
</style>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head><body>
<div class="welcome">
<div class="welcome-icon"><i class="bi bi-stars"></i></div>
<h1>Olá! Eu sou a BRI</h1>
<p>Sua assistente inteligente da Braziliana Shop. Posso te ajudar a encontrar produtos, calcular fretes, acompanhar pedidos e muito mais.</p>
<div class="tips">
<div class="tip"><i class="bi bi-search"></i>Buscar produtos por nome ou categoria</div>
<div class="tip"><i class="bi bi-calculator"></i>Calcular custo total de envio</div>
<div class="tip"><i class="bi bi-box-seam"></i>Acompanhar status de pedidos</div>
<div class="tip"><i class="bi bi-cart-plus"></i>Adicionar itens ao carrinho</div>
<div class="tip"><i class="bi bi-chat-dots"></i>Tirar dúvidas sobre o processo</div>
</div>
</div>
</body></html>';
        exit;
    }
}
