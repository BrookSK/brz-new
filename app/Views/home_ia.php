<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no, viewport-fit=cover">
  <title>BRI IA — Braziliana Shop</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="/public/assets/css/bri-sidebar.css">
  <meta name="bri-user-id" content="<?= (int) ($jsUserId ?? 0) ?>">
</head>
<body>

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

<script src="/public/assets/js/bri-sidebar-mode.js"></script>
<script>
// Mobile: bottom-sheet com 3 estados fixos (minimizado, 20%, 35%)
(function() {
  if (window.innerWidth >= 768) return;

  var container = document.getElementById('bri-fullscreen-mode');
  var sidebar = document.getElementById('bri-sidebar');
  var header = document.getElementById('bri-sidebar-header');
  var msgsEl = document.getElementById('bri-mensagens');
  if (!container || !sidebar || !header) return;

  // Estados: 0=minimizado, 1=20%, 2=35%
  var state = 1; // inicia em 20%
  var STATES = [0, 0.20, 0.35];

  function getHeaderInputHeight() {
    var inputArea = document.getElementById('bri-input-area');
    var hH = header.offsetHeight || 52;
    var iH = inputArea ? inputArea.offsetHeight : 48;
    return hH + iH;
  }

  function getHeightForState(s) {
    if (s === 0) return getHeaderInputHeight();
    return Math.round(window.innerHeight * STATES[s]);
  }

  function applyState() {
    var h = getHeightForState(state);
    sidebar.style.height = h + 'px';
    // Scroll mensagens para baixo
    if (msgsEl) setTimeout(function() { msgsEl.scrollTop = msgsEl.scrollHeight; }, 100);
    // Toggle classe minimizado
    sidebar.classList.toggle('bri-minimized', state === 0);
  }

  // Setar container height
  function updateContainer() {
    container.style.height = window.innerHeight + 'px';
  }
  updateContainer();
  window.addEventListener('resize', function() {
    updateContainer();
    applyState();
  });

  // Iniciar em state 1 (20%)
  setTimeout(applyState, 50);

  // === Clique no header para expandir (ciclo: min→20%→35%) ===
  header.addEventListener('click', function(e) {
    if (e.target.closest('.bri-minimize-btn, button, a')) return;
    if (state < 2) {
      state++;
    } else {
      state = 0;
    }
    applyState();
  });

  // === Botão minimizar ===
  var minBtn = document.createElement('button');
  minBtn.className = 'bri-icon-btn bri-minimize-btn';
  minBtn.title = 'Minimizar';
  minBtn.innerHTML = '<i class="bi bi-chevron-down"></i>';
  minBtn.style.cssText = 'margin-left:auto;';
  minBtn.addEventListener('click', function(e) {
    e.stopPropagation();
    state = 0;
    applyState();
  });
  header.appendChild(minBtn);

  // === Gesto magnético: swipe up/down encaixa no estado mais próximo ===
  var touchStartY = 0;
  var touchStartH = 0;
  var swiping = false;

  header.addEventListener('touchstart', function(e) {
    if (e.target.closest('.bri-minimize-btn, button, a')) return;
    swiping = true;
    touchStartY = e.touches[0].clientY;
    touchStartH = sidebar.offsetHeight;
  }, { passive: true });

  document.addEventListener('touchmove', function(e) {
    if (!swiping) return;
    // Não mover livremente — apenas detectar direção no touchend
  }, { passive: true });

  document.addEventListener('touchend', function(e) {
    if (!swiping) return;
    swiping = false;
    var endY = e.changedTouches[0].clientY;
    var diff = touchStartY - endY; // positivo = swipe up

    if (Math.abs(diff) < 20) return; // tap, não swipe — ignorar (click handler cuida)

    if (diff > 0) {
      // Swipe up → expandir
      if (state < 2) state++;
    } else {
      // Swipe down → minimizar
      if (state > 0) state--;
    }
    applyState();
  });

  // === Auto-expand quando BRI responde ===
  if (msgsEl && window.MutationObserver) {
    var expandTimeout = null;
    var observer = new MutationObserver(function() {
      if (expandTimeout) clearTimeout(expandTimeout);
      expandTimeout = setTimeout(function() {
        // Se minimizado, expandir para 20%
        if (state === 0) {
          state = 1;
          applyState();
        }
        // Scroll para baixo
        if (msgsEl) msgsEl.scrollTop = msgsEl.scrollHeight;
      }, 300);
    });
    observer.observe(msgsEl, { childList: true });
  }
})();
</script>

</body>
</html>
