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
// Mobile: bottom-sheet com estados fixos (sem drag)
(function() {
  if (window.innerWidth >= 768) return;

  var container = document.getElementById('bri-fullscreen-mode');
  var sidebar = document.getElementById('bri-sidebar');
  var header = document.getElementById('bri-sidebar-header');
  var msgsEl = document.getElementById('bri-mensagens');
  if (!container || !sidebar || !header) return;

  // 0=minimizado, 1=35%
  var state = 1;

  function applyState() {
    var winH = window.innerHeight;
    var inputArea = document.getElementById('bri-input-area');
    var minH = (header.offsetHeight || 52) + (inputArea ? inputArea.offsetHeight : 48);
    var h;
    if (state === 0) h = minH;
    else h = Math.max(Math.round(winH * 0.35), minH);
    sidebar.style.height = h + 'px';
    sidebar.classList.toggle('bri-minimized', state === 0);
    if (msgsEl && state > 0) setTimeout(function() { msgsEl.scrollTop = msgsEl.scrollHeight; }, 150);
  }

  // Container = viewport real
  container.style.height = window.innerHeight + 'px';
  window.addEventListener('resize', function() {
    container.style.height = window.innerHeight + 'px';
    applyState();
  });

  applyState();

  // Botão expandir/minimizar — adicionado ao header
  var toggleBtn = document.createElement('button');
  toggleBtn.className = 'bri-icon-btn bri-toggle-btn';
  toggleBtn.title = 'Expandir/Minimizar';
  toggleBtn.innerHTML = '<i class="bi bi-chevron-down"></i>';
  // Estado visual: aberto (state=1) = fundo branco, borda navy, seta navy
  // minimizado (state=0) = fundo navy, seta branca
  function updateToggleStyle() {
    if (state === 0) {
      toggleBtn.style.cssText = 'background:#18253D;color:#fff;border:2px solid #18253D;border-radius:50%;width:28px;height:28px;';
    } else {
      toggleBtn.style.cssText = 'background:#fff;color:#18253D;border:2px solid #18253D;border-radius:50%;width:28px;height:28px;';
    }
  }
  updateToggleStyle();
  toggleBtn.addEventListener('click', function(e) {
    e.stopPropagation();
    e.preventDefault();
    state = state === 0 ? 1 : 0;
    toggleBtn.innerHTML = state === 0 ? '<i class="bi bi-chevron-up"></i>' : '<i class="bi bi-chevron-down"></i>';
    updateToggleStyle();
    applyState();
  });
  header.appendChild(toggleBtn);

  // Tap no header também toggle
  header.addEventListener('click', function(e) {
    if (e.target.closest('button') || e.target.closest('a')) return;
    state = state === 0 ? 1 : 0;
    toggleBtn.innerHTML = state === 0 ? '<i class="bi bi-chevron-up"></i>' : '<i class="bi bi-chevron-down"></i>';
    updateToggleStyle();
    applyState();
  });

  // Auto-expand quando BRI responde
  if (msgsEl && window.MutationObserver) {
    var debounce = null;
    new MutationObserver(function() {
      if (debounce) clearTimeout(debounce);
      debounce = setTimeout(function() {
        if (state === 0) {
          state = 1;
          toggleBtn.innerHTML = '<i class="bi bi-chevron-down"></i>';
          updateToggleStyle();
          applyState();
        }
        if (msgsEl) msgsEl.scrollTop = msgsEl.scrollHeight;
      }, 400);
    }).observe(msgsEl, { childList: true });
  }
})();
</script>

</body>
</html>
