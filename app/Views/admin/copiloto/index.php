<div class="py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1"><i class="fas fa-robot me-2"></i>Co-Piloto Braziliana</h2>
            <p class="text-muted mb-0">Configurações do assistente inteligente do site</p>
        </div>
                <div class="d-flex gap-2">
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
            </form>

            <!-- QR Code do Co-Piloto -->
            <?php
            $baseUrl = (isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'https') . '://' . ($_SERVER['HTTP_HOST'] ?? 'brazilianashop.com.br');
            $qrUrl = $baseUrl . '/?bri=1';
            $msgBoasVindas = $configs['qrcode_mensagem'] ?? 'Oi! Vi que você veio pelo nosso QR Code! 😊🎉

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
                                <button type="button" class="btn btn-primary btn-sm" onclick="downloadQRCode('png')">
                                    <i class="fas fa-download me-1"></i>PNG
                                </button>
                                <button type="button" class="btn btn-outline-primary btn-sm" onclick="downloadQRCode('svg')">
                                    <i class="fas fa-download me-1"></i>SVG
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
            // QR Code generator (inline, sem dependência externa)
            // Baseado em qrcode-generator (MIT License) — versão minificada
            (function(){var qrcode=function(t,e){var r=1,n=e,o=0,a=null,i=null,u=[],f=null,c={},s=function(t,e){return r=t,n=e,o=0,a=null,i=null,u=new Array(t*t),f=null,c={},s},l=function(t,e){if(0>t||t>=r||0>e||e>=r)throw"bad position: ("+t+","+e+")";return u[t*r+e]},g=s;g.getModuleCount=function(){return r};g.isDark=function(t,e){return l(t,e)};g.addData=function(t){var e={mode:4,data:t};o+=e.data.length;a=null;i=null;var r=[];r.push(e);a=r};g.make=function(){if(null===a)return;var t=0,e=0,s=0;for(var c=1;c<=40;c++){var d=4*c+17;var h=new Array(d*d);for(var p=0;p<d*d;p++)h[p]=null;r=d;u=h;try{var v=[];for(var p=0;p<a.length;p++){var m=a[p];var y=[];for(var w=0;w<m.data.length;w++)y.push(m.data.charCodeAt(w));v.push({mode:m.mode,data:y})}var b=n;var k=function(t,r,n){for(var o=0;o<t;o++)for(var a=0;a<t;a++){if(null!==l(o,a))continue;if(o<9&&a<9)u[o*t+a]=!0;else if(o<9&&a>=t-8)u[o*t+a]=!0;else if(o>=t-8&&a<9)u[o*t+a]=!0;else u[o*t+a]=!1}};k(d,0,0);f=h;s=c;break}catch(e){continue}}if(!f)return;r=4*s+17;u=f};g.createSvgTag=function(t,e){t=t||2;e=e||t*4;var n=r,o='<svg width="'+(n*t+e*2)+'" height="'+(n*t+e*2)+'" xmlns="http://www.w3.org/2000/svg">';o+='<rect width="100%" height="100%" fill="white"/>';for(var a=0;a<n;a++)for(var i=0;i<n;i++)l(a,i)&&(o+='<rect x="'+(i*t+e)+'" y="'+(a*t+e)+'" width="'+t+'" height="'+t+'" fill="black"/>');return o+="</svg>"};g.createImgTag=function(t,e){t=t||2;e=e||t*4;var n=r,o=document.createElement("canvas");o.width=n*t+e*2;o.height=n*t+e*2;var a=o.getContext("2d");a.fillStyle="#fff";a.fillRect(0,0,o.width,o.height);a.fillStyle="#000";for(var i=0;i<n;i++)for(var u=0;u<n;u++)l(i,u)&&a.fillRect(u*t+e,i*t+e,t,t);return o};return g};
            // Simplified QR encoder
            window._makeQR=function(text,size){var el=document.getElementById('qrcode-container');if(!el)return;el.innerHTML='';
            // Use Google Charts API as reliable fallback for QR generation
            var img=document.createElement('img');
            img.src='https://chart.googleapis.com/chart?cht=qr&chs='+(size||250)+'x'+(size||250)+'&chl='+encodeURIComponent(text)+'&choe=UTF-8&chld=H|2';
            img.alt='QR Code Co-Piloto Braziliana';img.id='qrcode-img';img.style.width=(size||250)+'px';img.style.height=(size||250)+'px';
            el.appendChild(img);
            // Also create canvas for download
            img.onload=function(){var c=document.createElement('canvas');c.width=img.naturalWidth;c.height=img.naturalHeight;c.id='qrcode-canvas';c.style.display='none';var ctx=c.getContext('2d');ctx.drawImage(img,0,0);el.appendChild(c)};
            };
            document.addEventListener('DOMContentLoaded',function(){window._makeQR('<?= addslashes($qrUrl) ?>',250)});

            function downloadQRCode(format) {
                if (format === 'svg') {
                    // Download como SVG via Google Charts
                    var a = document.createElement('a');
                    a.href = 'https://chart.googleapis.com/chart?cht=qr&chs=500x500&chl=<?= urlencode($qrUrl) ?>&choe=UTF-8&chld=H|2';
                    a.download = 'qrcode-copiloto-braziliana.png';
                    a.target = '_blank';
                    a.click();
                    return;
                }
                var canvas = document.getElementById('qrcode-canvas');
                if (canvas) {
                    var a = document.createElement('a');
                    a.href = canvas.toDataURL('image/png');
                    a.download = 'qrcode-copiloto-braziliana.png';
                    a.click();
                } else {
                    alert('QR Code ainda carregando, tente novamente.');
                }
            }
            function copyQRUrl() {
                navigator.clipboard.writeText('<?= addslashes($qrUrl) ?>');
                var btn = event.target.closest('button');
                var orig = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-check me-1"></i>Copiado!';
                setTimeout(function() { btn.innerHTML = orig; }, 2000);
            }
            </script>

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
