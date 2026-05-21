<?php
/**
 * Conteúdo da página BRI IA — renderizado dentro do layout main.php
 * Variáveis disponíveis: $jsUserId
 */
?>
<style>
  /* Override: nesta página o main não precisa de padding/margin */
  body > main,
  main {
    padding: 0 !important;
    margin: 0 !important;
    overflow: hidden !important;
    flex: 1 1 auto !important;
  }

  /* Container BRI ocupa toda a área disponível abaixo do navbar */
  #bri-fullscreen-mode {
    display: flex;
    width: 100%;
    height: calc(100vh - var(--navbar-height));
    position: relative;
    background: var(--color-bg, #F5F7FA);
  }

  /* Esconder footer nesta página (fullscreen chat) */
  footer.site-footer,
  .site-footer {
    display: none !important;
  }
  .floating-cart {
    display: none !important;
  }

  /* Esconder alertas de sessão nesta página */
  main > .alert {
    display: none !important;
  }
</style>

<meta name="bri-user-id" content="<?= (int) ($jsUserId ?? 0) ?>">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
<link rel="stylesheet" href="/public/assets/css/bri-sidebar.css">

<!-- Override do CSS do bri-sidebar para funcionar dentro do layout -->
<style>
  /* Remover o position:fixed do container — já está dentro do layout main */
  #bri-fullscreen-mode {
    position: relative !important;
    top: auto !important;
    left: auto !important;
    right: auto !important;
    bottom: auto !important;
    z-index: 1 !important;
    height: calc(100vh - var(--navbar-height)) !important;
    width: 100% !important;
  }

  /* Body overflow hidden para evitar scroll */
  body {
    overflow: hidden !important;
  }

  /* No mobile, o bri-sidebar.css usa media query para reposicionar — manter compatível */
  @media (max-width: 767px) {
    #bri-fullscreen-mode {
      height: calc(100vh - var(--navbar-height)) !important;
      flex-direction: column !important;
    }
  }
</style>

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
  <section id="bri-painel">

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

  </section>

</div>

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

  var state = 1;
  var unreadCount = 0;

  function getNavH() {
    return parseInt(getComputedStyle(document.documentElement).getPropertyValue('--navbar-height')) || 64;
  }

  function applyState() {
    var availH = window.innerHeight - getNavH();
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

  container.style.height = (window.innerHeight - getNavH()) + 'px';
  window.addEventListener('resize', function() {
    container.style.height = (window.innerHeight - getNavH()) + 'px';
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
