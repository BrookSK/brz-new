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
// Mobile: drag para redimensionar + auto-expand quando BRI responde
(function() {
  if (window.innerWidth >= 768) return;

  var container = document.getElementById('bri-fullscreen-mode');
  var sidebar = document.getElementById('bri-sidebar');
  var header = document.getElementById('bri-sidebar-header');
  var msgsEl = document.getElementById('bri-mensagens');
  if (!container || !sidebar || !header) return;

  var minH = 100; // será recalculado
  var maxH = Math.round(window.innerHeight * 0.8);

  // Setar altura do container = viewport visual real
  function setRealHeight() {
    var vh = window.innerHeight;
    container.style.height = vh + 'px';
    maxH = Math.round(vh * 0.8);
    // Recalcular min
    var inputArea = document.getElementById('bri-input-area');
    var hH = header.offsetHeight || 48;
    var iH = inputArea ? inputArea.offsetHeight : 48;
    minH = hH + iH + 10; // +10 para handle
  }

  setRealHeight();
  window.addEventListener('resize', setRealHeight);

  // Setar altura inicial = 30%
  setTimeout(function() {
    setRealHeight();
    sidebar.style.height = Math.round(window.innerHeight * 0.3) + 'px';
  }, 50);

  // === DRAG ===
  var isDragging = false;
  var startY = 0;
  var startH = 0;

  header.addEventListener('touchstart', function(e) {
    if (e.target.closest('button, a')) return;
    isDragging = true;
    startY = e.touches[0].clientY;
    startH = sidebar.offsetHeight;
    sidebar.style.transition = 'none';
  }, { passive: true });

  document.addEventListener('touchmove', function(e) {
    if (!isDragging) return;
    e.preventDefault();
    var y = e.touches[0].clientY;
    var diff = startY - y; // positivo = dedo subiu = chat maior
    var newH = startH + diff;
    if (newH < minH) newH = minH;
    if (newH > maxH) newH = maxH;
    sidebar.style.height = newH + 'px';
  }, { passive: false });

  document.addEventListener('touchend', function() {
    if (!isDragging) return;
    isDragging = false;
    sidebar.style.transition = '';
  });

  // === AUTO-EXPAND quando BRI responde ===
  // Observar mudanças no container de mensagens
  if (msgsEl && window.MutationObserver) {
    var observer = new MutationObserver(function() {
      // Quando uma nova mensagem aparece, expandir para 50% se está menor
      var currentH = sidebar.offsetHeight;
      var targetH = Math.round(window.innerHeight * 0.5);
      if (currentH < targetH) {
        sidebar.style.transition = 'height 0.3s ease';
        sidebar.style.height = targetH + 'px';
        setTimeout(function() { sidebar.style.transition = ''; }, 350);
      }
      // Scroll para baixo
      setTimeout(function() {
        msgsEl.scrollTop = msgsEl.scrollHeight;
      }, 100);
    });
    observer.observe(msgsEl, { childList: true, subtree: true });
  }
})();
</script>

</body>
</html>
