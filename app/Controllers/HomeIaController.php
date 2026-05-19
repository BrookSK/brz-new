<?php
namespace App\Controllers;

use App\Core\Request;

class HomeIaController extends Controller {
    public function index(Request $request) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        // Verificar se está logado (mesma lógica do AuthService)
        $logado = (isset($_SESSION['logado']) && $_SESSION['logado'] === true && !empty($_SESSION['usuario_id']));
        
        // Se não está logado, tentar via remember_token
        if (!$logado && !empty($_COOKIE['remember_token'])) {
            try {
                $pdo = \Config\Database::getConnection();
                $st = $pdo->prepare("SELECT * FROM usuarios WHERE remember_token = ? LIMIT 1");
                $st->execute([$_COOKIE['remember_token']]);
                $usuario = $st->fetch(\PDO::FETCH_ASSOC);
                if ($usuario && !empty($usuario['id'])) {
                    // Recriar sessão completa (mesma lógica do AuthService::criarSessao)
                    $_SESSION['usuario_id'] = (int) $usuario['id'];
                    $_SESSION['usuario_nome'] = $usuario['nome'] ?? $usuario['name'] ?? '';
                    $_SESSION['usuario_email'] = $usuario['email'] ?? '';
                    $_SESSION['usuario_perfil'] = $usuario['perfil'] ?? $usuario['role'] ?? 'cliente';
                    $_SESSION['usuario_role'] = $usuario['role'] ?? $usuario['perfil'] ?? 'cliente';
                    $_SESSION['logado'] = true;
                    $logado = true;
                }
            } catch (\Throwable $e) {}
        }
        
        // Se ainda não está logado, redirecionar para login
        if (!$logado) {
            header('Location: /login?redirect=/home-ia');
            exit;
        }
        
        $jsUserId = (int) ($_SESSION['usuario_id'] ?? 0);
        include __DIR__ . '/../Views/home_ia.php';
    }

    /**
     * Página inicial do painel (boas-vindas)
     */
    public function inicio(Request $request) {
        // Buscar avatar BRI dinâmico
        $briAvatarSrc = '/public/assets/img/bri-avatar.gif';
        try {
            $pdoBri = \Config\Database::getConnection();
            $stmtBri = $pdoBri->prepare("SELECT valor FROM configuracoes_sistema WHERE categoria = 'layout' AND chave = 'bri_avatar' LIMIT 1");
            $stmtBri->execute();
            $briAvatarDb = $stmtBri->fetchColumn();
            if ($briAvatarDb && trim($briAvatarDb) !== '') {
                $briAvatarSrc = trim($briAvatarDb);
            }
        } catch (\Exception $e) {}
        $briAvatarEsc = htmlspecialchars($briAvatarSrc, ENT_QUOTES, 'UTF-8');
        $isWebm = str_ends_with($briAvatarSrc, '.webm');

        echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>BRI — Início</title>
<style>
body{margin:0;font-family:system-ui,-apple-system,sans-serif;background:#F5F7FA;display:flex;align-items:center;justify-content:center;min-height:100vh;color:#1F2937;}
.welcome{text-align:center;padding:40px;max-width:480px;}
.welcome-icon{width:72px;height:72px;margin:0 auto 20px;border-radius:50%;overflow:hidden;display:flex;align-items:center;justify-content:center;}
.welcome-icon img,.welcome-icon video{width:72px;height:72px;object-fit:cover;border-radius:50%;}
.welcome h1{font-size:22px;font-weight:700;color:#18253D;margin:0 0 8px;}
.welcome p{font-size:14px;color:#64748B;line-height:1.6;margin:0 0 24px;}
.tips{display:grid;gap:10px;text-align:left;}
.tip{background:#fff;border:1px solid #EBF0F6;border-radius:10px;padding:12px 16px;font-size:13px;color:#374151;display:flex;align-items:center;gap:10px;}
.tip i{color:#18253D;font-size:16px;flex-shrink:0;}
</style>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head><body>
<div class="welcome">
<div class="welcome-icon">' . ($isWebm ? '<video src="' . $briAvatarEsc . '" autoplay loop muted playsinline></video>' : '<img src="' . $briAvatarEsc . '" alt="BRI">') . '</div>
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
