<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
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
// Mobile: drag-to-resize chat panel
(function() {
  if (window.innerWidth >= 768) return;

  var sidebar = document.getElementById('bri-sidebar');
  var handle = document.getElementById('bri-sidebar-header');
  if (!sidebar || !handle) return;

  // Calcular altura mínima: header + input
  var headerEl = document.getElementById('bri-sidebar-header');
  var inputEl = document.getElementById('bri-input-area');
  var MIN_H = 100;

  function calcMinH() {
    var hH = headerEl ? headerEl.offsetHeight : 48;
    var iH = inputEl ? inputEl.offsetHeight : 48;
    MIN_H = hH + iH + 2;
  }
  setTimeout(calcMinH, 200);

  var MAX_H_RATIO = 0.8;
  var isDragging = false;
  var touchStartY = 0;
  var heightAtStart = 0;

  handle.addEventListener('touchstart', function(e) {
    // Ignorar se tocou em botão/link
    if (e.target.closest('button, a')) return;
    isDragging = true;
    touchStartY = e.touches[0].clientY;
    heightAtStart = sidebar.offsetHeight;
    e.preventDefault();
  }, { passive: false });

  document.addEventListener('touchmove', function(e) {
    if (!isDragging) return;
    e.preventDefault();
    var currentY = e.touches[0].clientY;
    var diff = touchStartY - currentY; // positivo = dedo subiu = aumentar
    var maxH = Math.floor(window.innerHeight * MAX_H_RATIO);
    var newH = heightAtStart + diff;
    // Clampar entre min e max
    if (newH < MIN_H) newH = MIN_H;
    if (newH > maxH) newH = maxH;
    sidebar.style.height = newH + 'px';
  }, { passive: false });

  document.addEventListener('touchend', function() {
    isDragging = false;
  });
})();
</script>

</body>
</html>
