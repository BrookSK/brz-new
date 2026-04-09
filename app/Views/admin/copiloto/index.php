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
