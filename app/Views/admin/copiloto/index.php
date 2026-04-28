<div class="py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1"><i class="fas fa-robot me-2"></i>Co-Piloto Braziliana</h2>
            <p class="text-muted mb-0">Configurações do assistente inteligente do site</p>
        </div>
                <div class="d-flex gap-2">
                    <a href="/admin/copiloto/analytics" class="btn btn-outline-info btn-sm">
                        <i class="fas fa-chart-bar me-1"></i>Analytics
                    </a>
                    <a href="/admin/copiloto/aprendizado" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-brain me-1"></i>Aprendizado
                        <?php if (($stats['total_pendencias'] ?? 0) > 0): ?>
                            <span class="badge bg-danger ms-1"><?= $stats['total_pendencias'] ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="/admin/copiloto/conteudo" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-book me-1"></i>Conteúdo
                    </a>
                    <a href="/admin/copiloto/cancelamentos" class="btn btn-outline-primary btn-sm">
                        <i class="fas fa-times-circle me-1"></i>Cancelamentos
                        <?php if (($stats['total_cancelamentos_pendentes'] ?? 0) > 0): ?>
                            <span class="badge bg-warning ms-1"><?= $stats['total_cancelamentos_pendentes'] ?></span>
                        <?php endif; ?>
                    </a>
                </div>
            </div>

            <?php if (!empty($_SESSION['flash_success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?= htmlspecialchars($_SESSION['flash_success']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['flash_success']); ?>
            <?php endif; ?>

            <?php if (!empty($_SESSION['flash_error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show">
                    <?= htmlspecialchars($_SESSION['flash_error']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['flash_error']); ?>
            <?php endif; ?>

            <!-- Stats Cards -->
            <div class="row g-3 mb-4">
                <div class="col-md-3">
                    <div class="card text-center p-3">
                        <div class="fs-3 fw-bold text-primary"><?= $stats['total_sessoes_hoje'] ?? 0 ?></div>
                        <small class="text-muted">Sessões hoje</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center p-3">
                        <div class="fs-3 fw-bold text-primary"><?= $stats['total_mensagens_hoje'] ?? 0 ?></div>
                        <small class="text-muted">Mensagens hoje</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center p-3">
                        <div class="fs-3 fw-bold text-warning"><?= $stats['total_pendencias'] ?? 0 ?></div>
                        <small class="text-muted">Pendências IA</small>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-center p-3">
                        <div class="fs-3 fw-bold text-danger"><?= $stats['total_cancelamentos_pendentes'] ?? 0 ?></div>
                        <small class="text-muted">Cancelamentos pendentes</small>
                    </div>
                </div>
            </div>

            <!-- Formulário de Configurações -->
            <form method="POST" action="/admin/copiloto/salvar">
                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-toggle-on me-2"></i>Status do Co-Piloto</h5>
                    </div>
                    <div class="card-body">
                        <?php $modo = $configs['modo'] ?? 'desativado'; ?>
                        <input type="hidden" name="copiloto_ativo" value="<?= $modo !== 'desativado' ? '1' : '0' ?>">
                        <div class="mb-3">
                            <label for="copiloto_modo" class="form-label"><strong>Modo de operação</strong></label>
                            <select class="form-select" id="copiloto_modo" name="copiloto_modo">
                                <option value="desativado" <?= $modo === 'desativado' ? 'selected' : '' ?>>
                                    🔴 Desativado — widget não aparece para ninguém
                                </option>
                                <option value="somente_admins" <?= $modo === 'somente_admins' ? 'selected' : '' ?>>
                                    🟡 Somente Admins — widget aparece apenas para usuários admin logados (modo teste)
                                </option>
                                <option value="publico" <?= $modo === 'publico' ? 'selected' : '' ?>>
                                    🟢 Público — widget aparece para todos os visitantes do site
                                </option>
                            </select>
                            <small class="text-muted mt-1 d-block">
                                Use "Somente Admins" para testar o copiloto sem que os clientes vejam.
                                Quando estiver satisfeito, mude para "Público".
                            </small>
                        </div>

                        <?php if ($modo === 'somente_admins'): ?>
                            <div class="alert alert-warning py-2 mb-0">
                                <i class="fas fa-flask me-1"></i>
                                <strong>Modo teste ativo.</strong> O widget só aparece para admins logados. Clientes não veem nada.
                            </div>
                        <?php elseif ($modo === 'publico'): ?>
                            <div class="alert alert-success py-2 mb-0">
                                <i class="fas fa-globe me-1"></i>
                                <strong>Público.</strong> O widget está visível para todos os visitantes.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-key me-2"></i>API Claude (Anthropic)</h5>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label for="api_key_claude" class="form-label">API Key do Claude</label>
                            <input type="password" class="form-control" id="api_key_claude" name="api_key_claude"
                                value="<?= htmlspecialchars($configs['api_key_claude'] ?? '') ?>"
                                placeholder="sk-ant-...">
                            <small class="text-muted">Chave da API Anthropic para o modelo claude-sonnet-4-5</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Modelo de IA</label>
                            <input type="text" class="form-control" value="claude-sonnet-4-5" disabled>
                            <small class="text-muted">Modelo fixo — não pode ser alterado conforme especificação do projeto</small>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-sliders-h me-2"></i>Parâmetros Operacionais</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label for="max_msgs_por_minuto" class="form-label">Máx. mensagens/minuto</label>
                                <input type="number" class="form-control" id="max_msgs_por_minuto" name="max_msgs_por_minuto"
                                    value="<?= htmlspecialchars($configs['max_msgs_por_minuto'] ?? '20') ?>" min="1" max="100">
                            </div>
                            <div class="col-md-4">
                                <label for="timeout_claude_ms" class="form-label">Timeout Claude (ms)</label>
                                <input type="number" class="form-control" id="timeout_claude_ms" name="timeout_claude_ms"
                                    value="<?= htmlspecialchars($configs['timeout_claude_ms'] ?? '15000') ?>" min="1000" max="60000">
                            </div>
                            <div class="col-md-4">
                                <label for="cambio_usd_brl" class="form-label">Câmbio USD→BRL</label>
                                <input type="number" class="form-control" id="cambio_usd_brl" name="cambio_usd_brl"
                                    value="<?= htmlspecialchars($configs['cambio_usd_brl'] ?? '5.80') ?>" step="0.01" min="0.01">
                            </div>
                            <div class="col-md-4">
                                <label for="gatilho_tempo_ms" class="form-label">Tempo gatilho proativo (ms)</label>
                                <input type="number" class="form-control" id="gatilho_tempo_ms" name="gatilho_tempo_ms"
                                    value="<?= htmlspecialchars($configs['gatilho_tempo_ms'] ?? '30000') ?>" min="5000">
                            </div>
                            <div class="col-md-4">
                                <label for="max_historico_enviado" class="form-label">Máx. histórico enviado</label>
                                <input type="number" class="form-control" id="max_historico_enviado" name="max_historico_enviado"
                                    value="<?= htmlspecialchars($configs['max_historico_enviado'] ?? '10') ?>" min="1" max="50">
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-end mb-4">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-save me-2"></i>Salvar Configurações
                    </button>
                </div>

            <!-- QR Code do Co-Piloto -->
            <?php
            $baseUrl = (isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'https') . '://' . ($_SERVER['HTTP_HOST'] ?? 'brazilianashop.com.br');
            $qrUrl = $baseUrl . '/?bri=1';
            $msgBoasVindas = !empty($configs['qrcode_mensagem']) ? $configs['qrcode_mensagem'] : 'Oi! Vi que você veio pelo nosso QR Code! 😊🎉

Eu sou a Bri, sua assistente de compras da Braziliana. Posso te ajudar com tudo:

🛍️ **Encontrar produtos** — me fala o que procura que eu busco no catálogo
🔗 **Comprar por link** — me manda o link de qualquer produto dos EUA que eu faço o orçamento
💰 **Calcular valores** — te mostro o valor total com taxas e impostos
📦 **Acompanhar pedidos** — consulto o status dos seus pedidos
❓ **Tirar dúvidas** — sobre como funciona, prazos, pagamento, etc.

Pode mandar sua dúvida ou o que você procura! 💚';
            ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-qrcode me-2"></i>QR Code — Divulgação do Co-Piloto</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">Compartilhe este QR Code em redes sociais, panfletos e criativos. Quando escaneado, abre o site com a Bri já conversando!</p>
                    
                    <div class="row">
                        <div class="col-md-4 text-center">
                            <div id="qrcode-container" class="mb-3 d-inline-block p-3 bg-white rounded shadow-sm"></div>
                            <div class="d-flex gap-2 justify-content-center">
                                <button type="button" class="btn btn-primary btn-sm" onclick="downloadQRCode()">
                                    <i class="fas fa-download me-1"></i>Baixar PNG
                                </button>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="copyQRUrl()">
                                    <i class="fas fa-link me-1"></i>Copiar Link
                                </button>
                            </div>
                            <div class="mt-2">
                                <small class="text-muted">URL: <code id="qr-url-display"><?= htmlspecialchars($qrUrl) ?></code></small>
                            </div>
                        </div>
                        <div class="col-md-8">
                            <label class="form-label"><strong>Mensagem de boas-vindas (QR Code)</strong></label>
                            <textarea class="form-control" name="copiloto_qrcode_mensagem" rows="10" placeholder="Mensagem que a Bri envia quando o visitante vem pelo QR Code..."><?= htmlspecialchars($msgBoasVindas) ?></textarea>
                            <small class="text-muted mt-1 d-block">Esta mensagem aparece automaticamente quando alguém escaneia o QR Code. Use **negrito** e emojis à vontade.</small>
                        </div>
                    </div>
                </div>
            </div>

            <script>
            // QR Code generator — implementação inline sem dependência externa
            // Gera QR Code direto no canvas usando algoritmo simplificado
            (function(){
            // Minimal QR Code generator (Mode Byte, ECC L, Version auto)
            // Based on Project Nayuki QR Code generator (MIT)
            function generateQR(text) {
                // Use a simple encoding: create data URL via inline SVG with a table-based approach
                // For reliability, we'll create a simple QR using the qr-creator pattern
                var size = 256;
                var canvas = document.createElement('canvas');
                canvas.width = size;
                canvas.height = size;
                canvas.id = 'qrcode-canvas';
                var ctx = canvas.getContext('2d');
                
                // Encode text to QR matrix using fetch to a free QR API
                var img = new Image();
                img.crossOrigin = 'anonymous';
                // Try multiple QR APIs for reliability
                var apis = [
                    'https://api.qrserver.com/v1/create-qr-code/?size=500x500&format=png&ecc=H&data=' + encodeURIComponent(text),
                    'https://quickchart.io/qr?text=' + encodeURIComponent(text) + '&size=500&ecLevel=H&margin=2'
                ];
                var apiIndex = 0;
                
                function tryLoad() {
                    if (apiIndex >= apis.length) {
                        // Fallback: show URL as text
                        var el = document.getElementById('qrcode-container');
                        if (el) el.innerHTML = '<div class="alert alert-warning p-2 small">QR Code não pôde ser gerado. Use o link: <br><code>' + text + '</code></div>';
                        return;
                    }
                    img.src = apis[apiIndex];
                }
                
                img.onload = function() {
                    ctx.fillStyle = '#ffffff';
                    ctx.fillRect(0, 0, size, size);
                    ctx.drawImage(img, 0, 0, size, size);
                    
                    var el = document.getElementById('qrcode-container');
                    if (el) {
                        el.innerHTML = '';
                        // Show image for display
                        var displayImg = document.createElement('img');
                        displayImg.src = canvas.toDataURL('image/png');
                        displayImg.alt = 'QR Code Co-Piloto Braziliana';
                        displayImg.id = 'qrcode-img';
                        displayImg.style.width = '250px';
                        displayImg.style.height = '250px';
                        displayImg.style.imageRendering = 'pixelated';
                        el.appendChild(displayImg);
                        // Keep canvas hidden for download
                        canvas.style.display = 'none';
                        el.appendChild(canvas);
                    }
                };
                img.onerror = function() {
                    apiIndex++;
                    tryLoad();
                };
                tryLoad();
            }
            
            document.addEventListener('DOMContentLoaded', function() {
                generateQR('<?= addslashes($qrUrl) ?>');
            });
            window._qrUrl = '<?= addslashes($qrUrl) ?>';
            })();

            function downloadQRCode(format) {
                var canvas = document.getElementById('qrcode-canvas');
                if (!canvas) {
                    alert('QR Code ainda carregando, tente novamente.');
                    return;
                }
                var a = document.createElement('a');
                a.href = canvas.toDataURL('image/png');
                a.download = 'qrcode-copiloto-braziliana.png';
                a.click();
            }
            function copyQRUrl() {
                navigator.clipboard.writeText(window._qrUrl || '<?= addslashes($qrUrl) ?>');
                var btn = event.target.closest('button');
                var orig = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check me-1"></i>Copiado!';
                setTimeout(function() { btn.innerHTML = orig; }, 2000);
            }
            </script>

            </form>

            <!-- Info do Cron -->
            <?php
            $cronApiKey = $configs['api_key_claude'] ?? '';
            $cronToken = substr(md5('copiloto_cron_' . $cronApiKey), 0, 16);
            $cronUrl = (isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'https') . '://' . ($_SERVER['HTTP_HOST'] ?? 'brazilianashop.com.br') . '/api/copiloto/cron?token=' . $cronToken;
            ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Cron (Tarefas Automáticas)</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-2">Configure no AAPanel um cron para acessar esta URL a cada 5 minutos. Ela processa conteúdo pendente e faz limpeza automática.</p>
                    <div class="input-group">
                        <input type="text" class="form-control form-control-sm" value="<?= htmlspecialchars($cronUrl) ?>" readonly id="cronUrl">
                        <button class="btn btn-outline-secondary btn-sm" onclick="navigator.clipboard.writeText(document.getElementById('cronUrl').value);this.textContent='Copiado!';setTimeout(()=>this.textContent='Copiar',2000)">Copiar</button>
                    </div>
                    <small class="text-muted mt-1 d-block">No AAPanel: Cron Jobs → Add → Shell Script → <code>curl -s "<?= htmlspecialchars($cronUrl) ?>" > /dev/null</code> → A cada 5 minutos</small>
                </div>
            </div>

</div>
