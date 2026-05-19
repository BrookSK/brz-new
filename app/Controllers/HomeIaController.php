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
.welcome{text-align:center;padding:40px;max-width:520px;}
.welcome-icon{width:72px;height:72px;margin:0 auto 20px;border-radius:50%;overflow:hidden;display:flex;align-items:center;justify-content:center;}
.welcome-icon img,.welcome-icon video{width:72px;height:72px;object-fit:cover;border-radius:50%;}
.welcome h1{font-size:22px;font-weight:700;color:#18253D;margin:0 0 8px;}
.welcome p{font-size:14px;color:#64748B;line-height:1.6;margin:0 0 24px;}
.tips{display:grid;gap:10px;text-align:left;}
.tip{background:#fff;border:1px solid #EBF0F6;border-radius:10px;padding:12px 16px;font-size:13px;color:#374151;display:flex;align-items:center;gap:10px;cursor:pointer;transition:background .15s,border-color .15s,transform .1s;}
.tip:hover{background:#EEF2FF;border-color:#C7D2FE;transform:translateY(-1px);}
.tip:active{transform:translateY(0);}
.tip i{color:#18253D;font-size:16px;flex-shrink:0;}
</style>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head><body>
<div class="welcome">
<div class="welcome-icon">' . ($isWebm ? '<video src="' . $briAvatarEsc . '" autoplay loop muted playsinline></video>' : '<img src="' . $briAvatarEsc . '" alt="BRI">') . '</div>
<h1>Olá! Eu sou a BRI</h1>
<p>Sua assistente inteligente da Braziliana Shop. Posso te ajudar a encontrar produtos, calcular fretes, acompanhar pedidos e muito mais.</p>
<div class="tips">
<div class="tip" data-msg="Como funciona a BRI? Como usar o chat?"><i class="bi bi-info-circle"></i>Como funciona / Como usar o chat</div>
<div class="tip" data-msg="Como pesquisar um produto?"><i class="bi bi-search"></i>Como pesquisar um produto</div>
<div class="tip" data-msg="Como calcular o valor do frete e total do pedido?"><i class="bi bi-calculator"></i>Como calcular valores e frete</div>
<div class="tip" data-msg="Como acompanhar o status do meu pedido?"><i class="bi bi-box-seam"></i>Acompanhar status de pedidos</div>
<div class="tip" data-msg="Como adicionar itens ao carrinho?"><i class="bi bi-cart-plus"></i>Adicionar itens ao carrinho</div>
<div class="tip" data-msg="Quais são as perguntas frequentes? FAQ"><i class="bi bi-question-circle"></i>Perguntas frequentes (FAQ)</div>
<div class="tip" data-msg="Quero abrir um ticket de suporte"><i class="bi bi-ticket-perforated"></i>Abrir um ticket</div>
</div>
</div>
<script>
document.querySelectorAll(".tip[data-msg]").forEach(function(el) {
  el.addEventListener("click", function() {
    var msg = el.getAttribute("data-msg");
    if (!msg) return;
    try {
      var parentDoc = window.parent.document;
      var input = parentDoc.getElementById("bri-input");
      var sendBtn = parentDoc.getElementById("bri-send-btn");
      if (input) {
        input.value = msg;
        input.dispatchEvent(new Event("input", {bubbles:true}));
        if (sendBtn) sendBtn.click();
      }
    } catch(e) { console.warn("Não foi possível enviar ao chat:", e); }
  });
});
</script>
</body></html>';
        exit;
    }
}
