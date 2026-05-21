<!DOCTYPE html>
<?php
// Ler configuração de conversão de moeda (necessário para o navbar)
$__conversaoMoedaAtiva = false;
try {
    $__pdo = \Config\Database::getConnection();
    $__queries = [
        "SELECT valor FROM configuracoes_sistema WHERE categoria = 'loja' AND chave = 'conversao_moeda_ativa' LIMIT 1",
        "SELECT value FROM configuracoes_sistema WHERE categoria = 'loja' AND chave = 'conversao_moeda_ativa' LIMIT 1",
    ];
    foreach ($__queries as $__sql) {
        try {
            $__st = $__pdo->query($__sql);
            if ($__st) {
                $__v = $__st->fetchColumn();
                if ($__v !== false) {
                    $__conversaoMoedaAtiva = ((string) $__v === '1');
                    break;
                }
            }
        } catch (\Exception $__e) { continue; }
    }
} catch (\Exception $e) {
    $__conversaoMoedaAtiva = false;
}
$__isCheckoutPage = false;
$__mostrarConversao = $__conversaoMoedaAtiva;
?>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
  <title>BRI IA — Braziliana Shop</title>
  <?php
  // Favicon
  $siteFavicon = '';
  try {
      $pdoFav = \Config\Database::getConnection();
      $stmtFav = $pdoFav->prepare("SELECT valor FROM configuracoes_sistema WHERE categoria = 'layout' AND chave = 'favicon' LIMIT 1");
      $stmtFav->execute();
      $siteFavicon = trim((string) ($stmtFav->fetchColumn() ?: ''));
      if ($siteFavicon !== '' && preg_match('#^/uploads/#', $siteFavicon)) {
          $siteFavicon = '/public' . $siteFavicon;
      }
  } catch (\Exception $e) {}
  if ($siteFavicon !== ''):
  ?>
  <link rel="icon" href="<?= htmlspecialchars($siteFavicon, ENT_QUOTES, 'UTF-8') ?>">
  <?php endif; ?>

  <!-- CSS do site (mesmo do main layout) -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

  <!-- CSS do BRI sidebar -->
  <link rel="stylesheet" href="/public/assets/css/bri-sidebar.css">

  <meta name="bri-user-id" content="<?= (int) ($jsUserId ?? 0) ?>">

  <style>
    /* ── Variáveis do site (copiadas do main layout) ── */
    :root {
      --primary-color: #0b1f3a;
      --primary-color-2: #1d4ed8;
      --secondary-color: #94a3b8;
      --accent-color: #38bdf8;
      --success-color: #10b981;
      --danger-color: #ef4444;
      --bg-gradient: #f6f8fb;
      --surface-gradient: #ffffff;
      --radius-sm: 10px;
      --radius-md: 14px;
      --radius-lg: 18px;
      --navbar-height: 76px;
      --shadow-sm: 0 6px 18px rgba(15, 23, 42, 0.08);
      --shadow-md: 0 10px 28px rgba(15, 23, 42, 0.10);
      --header-surface: rgba(255, 255, 255, 0.86);
      --header-border: rgba(148, 163, 184, 0.35);
      --footer-bg: #0b1f3a;
      --footer-border: rgba(255, 255, 255, 0.10);
    }

    /* ── Navbar fixo ── */
    .navbar {
      position: fixed !important;
      top: 0 !important;
      left: 0 !important;
      right: 0 !important;
      z-index: 10000 !important;
      box-shadow: var(--shadow-sm) !important;
      background: var(--header-surface) !important;
      border-bottom: 1px solid var(--header-border) !important;
      backdrop-filter: blur(10px);
      -webkit-backdrop-filter: blur(10px);
      padding: 0 !important;
      margin: 0 !important;
      width: 100% !important;
    }

    .navbar-brand {
      font-weight: 700;
      font-size: 1.5rem;
      display: inline-flex;
      align-items: center;
      gap: 10px;
    }

    .navbar-brand .site-logo {
      max-height: 38px;
      width: auto;
      object-fit: contain;
    }

    .navbar .nav-link {
      color: rgba(15, 23, 42, 0.78) !important;
      font-weight: 600;
      padding: 0.55rem 0.75rem;
      border-radius: 999px;
      white-space: nowrap;
      transition: background-color 0.2s ease, color 0.2s ease;
    }

    .navbar .nav-link:hover {
      color: rgba(15, 23, 42, 0.92) !important;
      background: rgba(29, 78, 216, 0.10);
    }

    .navbar .dropdown-menu {
      border: 1px solid rgba(148, 163, 184, 0.35);
      box-shadow: var(--shadow-md);
    }

    .navbar .btn.btn-primary {
      background: var(--primary-color);
      border: 1px solid rgba(11, 31, 58, 0.22);
    }

    .navbar .cart-badge {
      background: var(--danger-color) !important;
      color: #fff;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      min-width: 20px;
      height: 20px;
      padding: 0 6px;
      border-radius: 999px;
      font-size: 11px;
      font-weight: 700;
    }

    .navbar .nav-link.position-relative .cart-badge {
      position: absolute;
      top: -6px;
      right: -10px;
    }

    .navbar .user-menu .user-avatar {
      width: 35px;
      height: 35px;
      border-radius: 50%;
      background: var(--primary-color);
      color: white;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: bold;
      cursor: pointer;
      overflow: hidden;
      font-size: 0.85rem;
    }

    .navbar .user-menu .user-avatar img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    /* ── Override do BRI para funcionar com navbar ── */
    body {
      margin: 0 !important;
      padding: 0 !important;
      overflow: hidden;
      font-family: system-ui, -apple-system, sans-serif;
      background: var(--bg-gradient);
    }

    #bri-fullscreen-mode {
      position: fixed;
      top: var(--navbar-height);
      left: 0;
      right: 0;
      bottom: 0;
      height: auto;
      width: 100%;
      display: flex;
      z-index: 9999;
    }

    /* ── Footer ── */
    .site-footer {
      background: var(--footer-bg);
      border-top: 1px solid var(--footer-border);
    }

    .site-footer .text-muted {
      color: rgba(255, 255, 255, 0.72) !important;
    }

    .site-footer a.text-muted {
      color: rgba(255, 255, 255, 0.75) !important;
    }

    .site-footer .footer-link {
      color: rgba(255, 255, 255, 0.78);
      text-decoration: none;
    }

    .site-footer .footer-link:hover {
      color: rgba(255, 255, 255, 0.98);
      text-decoration: underline;
    }

    .site-footer .social-link {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      text-decoration: none;
      width: 44px;
      height: 44px;
      border-radius: 999px;
      background: rgba(255, 255, 255, 0.08);
      border: 1px solid rgba(255, 255, 255, 0.14);
      color: rgba(255, 255, 255, 0.92);
      transition: background-color 0.2s ease;
    }

    .site-footer .social-link:hover {
      background: rgba(255, 255, 255, 0.16);
      text-decoration: none;
    }

    .site-footer .site-logo {
      max-height: 32px;
      width: auto;
      object-fit: contain;
      filter: brightness(1.08);
    }

    /* ── Mobile ── */
    @media (max-width: 991.98px) {
      :root {
        --navbar-height: 64px;
      }

      .navbar .container-fluid {
        padding-left: 12px;
        padding-right: 12px;
      }

      .navbar-brand {
        font-size: 1.2rem;
      }

      .navbar-toggler {
        border-radius: 8px;
        padding: 6px 8px;
        border: 1px solid #18253D;
        box-shadow: none;
      }

      .navbar-toggler:focus {
        box-shadow: none;
        outline: none;
      }

      .navbar-collapse {
        margin-top: 10px;
        padding: 14px;
        border-radius: var(--radius-md);
        background: rgba(255,255,255,0.98);
        border: 1px solid var(--header-border);
        box-shadow: var(--shadow-md);
        max-height: calc(100vh - var(--navbar-height) - 16px);
        overflow-y: auto;
      }

      .navbar-collapse .navbar-nav.mx-auto .nav-link {
        display: flex;
        align-items: center;
        gap: 10px;
        padding: 0.6rem 0.85rem;
        border-radius: 10px;
        font-size: 14px;
      }
    }

    @media (max-width: 767px) {
      #bri-fullscreen-mode {
        top: var(--navbar-height);
      }
    }
  </style>
</head>
<body>

<!-- ═══════════════ NAVBAR (mesmo do site) ═══════════════ -->
<nav class="navbar navbar-expand-lg navbar-light">
  <div class="container-fluid px-3">
    <?php
    $siteLogo = '';
    try {
        $pdo = \Config\Database::getConnection();
        $stmtLogo = $pdo->prepare("SELECT valor FROM configuracoes_sistema WHERE categoria = 'layout' AND chave = 'logo' LIMIT 1");
        $stmtLogo->execute();
        $siteLogo = trim((string) ($stmtLogo->fetchColumn() ?: ''));
        if ($siteLogo !== '' && preg_match('#^/uploads/#', $siteLogo)) {
            $siteLogo = '/public' . $siteLogo;
        }
    } catch (\Exception $e) {}
    ?>
    <a class="navbar-brand fw-bold" href="/">
      <?php if (!empty($siteLogo)): ?>
        <img src="<?= htmlspecialchars($siteLogo, ENT_QUOTES, 'UTF-8') ?>" alt="Braziliana" class="site-logo">
      <?php else: ?>
        <i class="fas fa-globe-americas text-primary"></i> Braziliana
      <?php endif; ?>
    </a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav mx-auto gap-lg-1">
        <li class="nav-item"><a class="nav-link" href="/"><i class="fas fa-home"></i> Início</a></li>
        <li class="nav-item"><a class="nav-link" href="/produtos"><i class="fas fa-box"></i> Produtos</a></li>
        <li class="nav-item"><a class="nav-link" href="/grupos-compras"><i class="fas fa-store"></i> Grupos de Compras</a></li>
        <li class="nav-item"><a class="nav-link" href="/faq"><i class="fas fa-comments"></i> FAQ</a></li>
        <li class="nav-item"><a class="nav-link" href="/contato"><i class="fas fa-envelope"></i> Contato</a></li>
        <li class="nav-item"><a class="nav-link" href="/assessoria"><i class="fas fa-magic"></i> Redirecionamento</a></li>
      </ul>

      <ul class="navbar-nav align-items-center gap-1">
        <?php
        $isLoggedIn = isset($_SESSION['logado']) && $_SESSION['logado'] === true;
        $usuarioLogado = $isLoggedIn ? ($_SESSION['usuario_nome'] ?? '') : null;
        $usuarioAvatar = $isLoggedIn ? ($_SESSION['usuario_avatar'] ?? null) : null;
        $usuarioPerfil = $isLoggedIn ? ($_SESSION['usuario_perfil'] ?? 'cliente') : 'cliente';

        if ($isLoggedIn && (empty($usuarioLogado) || !is_string($usuarioLogado))) {
            try {
                $uModel = new \App\Models\Usuario();
                $u = $uModel->find($_SESSION['usuario_id'] ?? 0);
                if (is_array($u)) {
                    foreach (['nome', 'name', 'full_name'] as $c) {
                        if (!empty($u[$c]) && is_string($u[$c])) {
                            $usuarioLogado = $u[$c];
                            $_SESSION['usuario_nome'] = $usuarioLogado;
                            break;
                        }
                    }
                }
            } catch (\Exception $e) {}
        }

        $totalItens = 0;
        if ($isLoggedIn) {
            try {
                $cModel = new \App\Models\Carrinho();
                $cart = $cModel->getOrCreateCarrinho($_SESSION['usuario_id'] ?? 0, null, 'BRL');
                $cartId = is_array($cart) ? (int) ($cart['id'] ?? 0) : (int) $cart;
                if ($cartId > 0) {
                    $db = $cModel->getConnection();
                    $st = $db->prepare('SELECT COALESCE(SUM(quantidade),0) FROM carrinho_items WHERE carrinho_id = ?');
                    $st->execute([$cartId]);
                    $totalItens = (int) ($st->fetchColumn() ?: 0);
                }
            } catch (\Throwable $e) { $totalItens = 0; }
        }
        ?>

        <?php if ($isLoggedIn): ?>
          <li class="nav-item dropdown user-menu">
            <a class="nav-link dropdown-toggle d-flex align-items-center" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
              <div class="user-avatar me-2">
                <?php if (!empty($usuarioAvatar) && is_string($usuarioAvatar)): ?>
                  <img src="<?= htmlspecialchars($usuarioAvatar) ?>" alt="<?= htmlspecialchars($usuarioLogado) ?>">
                <?php else: ?>
                  <?= strtoupper(substr($usuarioLogado, 0, 2)) ?>
                <?php endif; ?>
              </div>
              <span class="d-none d-md-inline"><?= htmlspecialchars($usuarioLogado) ?></span>
            </a>
            <ul class="dropdown-menu dropdown-menu-end">
              <li><a class="dropdown-item" href="/minha-conta"><i class="fas fa-tachometer-alt"></i> Minha Conta</a></li>
              <?php if ($usuarioPerfil === 'representante'): ?>
                <li><a class="dropdown-item" href="/meu-painel"><i class="fas fa-chart-line"></i> Meu Painel</a></li>
              <?php endif; ?>
              <li><a class="dropdown-item" href="/meus-pedidos"><i class="fas fa-shopping-bag"></i> Meus Pedidos</a></li>
              <li><a class="dropdown-item" href="/meus-dados"><i class="fas fa-user"></i> Meus Dados</a></li>
              <?php if (in_array($usuarioPerfil, ['admin', 'vendedor', 'suporte', 'redirecionador', 'conferente'], true)): ?>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item text-primary" href="/admin/dashboard"><i class="fas fa-cog"></i> Painel Admin</a></li>
              <?php endif; ?>
              <li><hr class="dropdown-divider"></li>
              <li><a class="dropdown-item" href="/logout"><i class="fas fa-sign-out-alt"></i> Sair</a></li>
            </ul>
          </li>
        <?php else: ?>
          <li class="nav-item"><a class="nav-link" href="/login"><i class="fas fa-sign-in-alt"></i> Entrar</a></li>
          <li class="nav-item"><a class="btn btn-primary btn-sm ms-2" href="/register">Cadastrar</a></li>
        <?php endif; ?>

        <!-- Carrinho -->
        <li class="nav-item">
          <a class="nav-link position-relative" href="/carrinho">
            <i class="fas fa-shopping-cart"></i>
            <?php if ($totalItens > 0): ?>
              <span class="cart-badge"><?= $totalItens ?></span>
            <?php endif; ?>
          </a>
        </li>
      </ul>
    </div>
  </div>
</nav>

<!-- ═══════════════ CONTEÚDO BRI ═══════════════ -->
<div id="bri-fullscreen-mode">

  <!-- SIDEBAR ESQUERDA — Chat BRI -->
  <aside id="bri-sidebar">

    <header id="bri-sidebar-header">
      <div class="bri-avatar">
        <?php
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
        ?>
        <div class="bri-avatar-icon">
          <?php if (str_ends_with($briAvatarSrc, '.webm')): ?>
            <video src="<?= htmlspecialchars($briAvatarSrc, ENT_QUOTES, 'UTF-8') ?>" autoplay loop muted playsinline style="width:32px;height:32px;border-radius:50%;object-fit:cover;"></video>
          <?php else: ?>
            <img src="<?= htmlspecialchars($briAvatarSrc, ENT_QUOTES, 'UTF-8') ?>" alt="BRI" style="width:32px;height:32px;border-radius:50%;object-fit:cover;">
          <?php endif; ?>
        </div>
      </div>
      <div class="bri-header-info">
        <span class="bri-nome">BRI</span>
        <span class="bri-status">
          <i class="bi bi-circle-fill"></i> Online
        </span>
      </div>
      <a href="/" class="bri-icon-btn" title="Voltar ao site">
        <i class="bi bi-box-arrow-left"></i>
      </a>
      <button class="bri-icon-btn" title="Limpar conversa" onclick="BriSidebar.limparChat()">
        <i class="bi bi-trash3"></i>
      </button>
    </header>

    <div id="bri-mensagens">
      <!-- Mensagens inseridas via JS -->
    </div>

    <footer id="bri-input-area">
      <textarea
        id="bri-input"
        placeholder="Digite sua mensagem..."
        rows="1"
        maxlength="2000"
      ></textarea>
      <button id="bri-send-btn" class="bri-btn-primary" title="Enviar">
        <i class="bi bi-send-fill"></i>
      </button>
    </footer>

  </aside>

  <!-- Divisor visual -->
  <div id="bri-divider"></div>

  <!-- PAINEL DINÂMICO DIREITA — iframe -->
  <main id="bri-painel">

    <button id="bri-back-btn" onclick="BriSidebar.fecharPainel()">
      <i class="bi bi-chevron-left"></i> Chat
    </button>

    <div id="bri-painel-loader">
      <div class="bri-spinner"></div>
    </div>

    <iframe
      id="bri-frame"
      src="/bri/inicio?embed=1"
      title="Conteúdo BRI"
    ></iframe>

  </main>

</div>

<!-- Scripts do site -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<!-- Script do BRI -->
<script src="/public/assets/js/bri-sidebar-mode.js"></script>
<script>
// Mobile: bottom-sheet com 3 estados (minimizado ↔ 35% ↔ 60%)
(function() {
  if (window.innerWidth >= 768) return;

  var container = document.getElementById('bri-fullscreen-mode');
  var sidebar = document.getElementById('bri-sidebar');
  var header = document.getElementById('bri-sidebar-header');
  var msgsEl = document.getElementById('bri-mensagens');
  if (!container || !sidebar || !header) return;

  // 0=minimizado, 1=35%, 2=60%
  var state = 1;
  var unreadCount = 0;

  function applyState() {
    var winH = window.innerHeight;
    var navH = parseInt(getComputedStyle(document.documentElement).getPropertyValue('--navbar-height')) || 76;
    var availH = winH - navH;
    var inputArea = document.getElementById('bri-input-area');
    var minH = (header.offsetHeight || 52) + (inputArea ? inputArea.offsetHeight : 48);
    var h;
    if (state === 0) h = minH;
    else if (state === 1) h = Math.max(Math.round(availH * 0.35), minH);
    else h = Math.max(Math.round(availH * 0.60), minH);
    sidebar.style.height = h + 'px';
    sidebar.classList.toggle('bri-minimized', state === 0);
    if (state === 2) {
      toggleBtn.innerHTML = '<i class="bi bi-chevron-down"></i>';
    } else {
      toggleBtn.innerHTML = '<i class="bi bi-chevron-up"></i>';
    }
    if (badge) toggleBtn.appendChild(badge);
    updateToggleStyle();
    if (msgsEl && state > 0) setTimeout(function() { msgsEl.scrollTop = msgsEl.scrollHeight; }, 200);
    if (state > 0) { unreadCount = 0; updateBadge(); }
  }

  container.style.height = (window.innerHeight - (parseInt(getComputedStyle(document.documentElement).getPropertyValue('--navbar-height')) || 64)) + 'px';
  window.addEventListener('resize', function() {
    var navH = parseInt(getComputedStyle(document.documentElement).getPropertyValue('--navbar-height')) || 64;
    container.style.height = (window.innerHeight - navH) + 'px';
    applyState();
  });

  var toggleBtn = document.createElement('button');
  toggleBtn.className = 'bri-icon-btn bri-toggle-btn';
  toggleBtn.title = 'Expandir';
  toggleBtn.innerHTML = '<i class="bi bi-chevron-up"></i>';
  function updateToggleStyle() {
    if (state === 0) {
      toggleBtn.style.cssText = 'background:#18253D;color:#fff;border:2px solid #18253D;border-radius:50%;width:28px;height:28px;position:relative;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;flex-shrink:0;';
    } else {
      toggleBtn.style.cssText = 'background:#fff;color:#18253D;border:2px solid #18253D;border-radius:50%;width:28px;height:28px;position:relative;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;flex-shrink:0;';
    }
  }
  updateToggleStyle();

  var badge = null;
  function updateBadge() {
    if (unreadCount > 0 && state === 0) {
      if (!badge) {
        badge = document.createElement('span');
        badge.className = 'bri-badge';
        toggleBtn.appendChild(badge);
      }
      badge.textContent = unreadCount > 9 ? '9+' : unreadCount;
      badge.style.display = 'flex';
      toggleBtn.classList.add('has-notification');
    } else {
      if (badge) badge.style.display = 'none';
      toggleBtn.classList.remove('has-notification');
    }
  }

  toggleBtn.addEventListener('click', function(e) {
    e.stopPropagation();
    e.preventDefault();
    if (state < 2) { state++; } else { state--; }
    applyState();
  });
  header.appendChild(toggleBtn);

  var minBtn = document.createElement('button');
  minBtn.className = 'bri-icon-btn bri-min-btn';
  minBtn.title = 'Minimizar';
  minBtn.innerHTML = '<span style="width:12px;height:2px;background:#DC2626;border-radius:1px;display:block;"></span>';
  minBtn.style.cssText = 'background:#FEE2E2;border:1.5px solid #FECACA;border-radius:50%;width:24px;height:24px;display:inline-flex;align-items:center;justify-content:center;cursor:pointer;flex-shrink:0;margin-left:4px;';
  minBtn.addEventListener('click', function(e) {
    e.stopPropagation();
    e.preventDefault();
    state = 0;
    applyState();
  });
  header.appendChild(minBtn);

  header.addEventListener('click', function(e) {
    if (e.target.closest('button') || e.target.closest('a')) return;
    if (state === 0) { state = 1; applyState(); }
  });

  applyState();

  if (msgsEl && window.MutationObserver) {
    var debounce = null;
    new MutationObserver(function() {
      if (debounce) clearTimeout(debounce);
      debounce = setTimeout(function() {
        if (state === 0) { unreadCount++; updateBadge(); }
        else { if (msgsEl) msgsEl.scrollTop = msgsEl.scrollHeight; }
      }, 400);
    }).observe(msgsEl, { childList: true });
  }
})();
</script>

</body>
</html>
