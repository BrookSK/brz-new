<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Debug - Assessoria de Compras</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .json-viewer {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 15px;
            border-radius: 5px;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            max-height: 500px;
            overflow-y: auto;
        }
        .log-entry {
            margin-bottom: 20px;
            border: 1px solid #dee2e6;
            border-radius: 8px;
        }
        .log-header {
            background: #f8f9fa;
            padding: 10px 15px;
            border-bottom: 1px solid #dee2e6;
            font-weight: 600;
        }
        .log-body {
            padding: 15px;
        }
    </style>
</head>
<body>
    <div class="container-fluid mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h1><i class="fas fa-bug"></i> Debug - Assessoria de Compras</h1>
                    <div>
                        <a href="/assessoria" class="btn btn-outline-primary">
                            <i class="fas fa-arrow-left"></i> Voltar para Assessoria
                        </a>
                    </div>
                </div>

                <!-- Status da Configuração -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-cog"></i> Status da Configuração</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="form-label">API Key Configurada:</label>
                                        <div>
                                            <?php if ($currentConfig['api_key_configured']): ?>
                                                <span class="badge bg-success">Sim</span>
                                                <code class="ms-2"><?php echo $currentConfig['api_key_preview']; ?></code>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Não</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-vial"></i> Testar URL</h5>
                            </div>
                            <div class="card-body">
                                <form id="debugForm">
                                    <div class="input-group">
                                        <input type="url" id="testUrl" class="form-control" placeholder="https://www.amazon.com/..." required>
                                        <button type="submit" class="btn btn-primary">
                                            <i class="fas fa-play"></i> Testar
                                        </button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Resultado do Teste -->
                <div id="testResult" style="display: none;">
                    <div class="card">
                        <div class="card-header">
                            <h5><i class="fas fa-flask"></i> Resultado do Teste</h5>
                        </div>
                        <div class="card-body">
                            <div id="resultContent"></div>
                        </div>
                    </div>
                </div>

                <!-- Logs de Debug -->
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-history"></i> Logs de Debug</h5>
                        <button class="btn btn-sm btn-outline-danger" onclick="clearLogs()">
                            <i class="fas fa-trash"></i> Limpar Logs
                        </button>
                    </div>
                    <div class="card-body">
                        <?php if (empty($debugLogs)): ?>
                            <div class="text-center text-muted py-5">
                                <i class="fas fa-info-circle fa-3x mb-3"></i>
                                <p>Nenhum log de debug encontrado. Teste uma URL para começar.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach (array_reverse($debugLogs) as $log): ?>
                                <div class="log-entry">
                                    <div class="log-header">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <i class="fas <?php echo $log['result']['success'] ? 'fa-check-circle text-success' : 'fa-exclamation-circle text-danger'; ?>"></i>
                                                <span class="ms-2"><?php echo $log['timestamp']; ?></span>
                                            </div>
                                            <div>
                                                <small class="text-muted"><?php echo substr($log['url'], 0, 60); ?>...</small>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="log-body">
                                        <!-- URL Testada -->
                                        <div class="mb-3">
                                            <strong>URL:</strong>
                                            <a href="<?php echo $log['url']; ?>" target="_blank" class="text-break"><?php echo $log['url']; ?></a>
                                        </div>

                                        <!-- Resultado -->
                                        <div class="mb-3">
                                            <strong>Resultado:</strong>
                                            <?php if ($log['result']['success']): ?>
                                                <span class="badge bg-success">Sucesso</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Erro</span>
                                                <div class="alert alert-danger mt-2">
                                                    <?php echo htmlspecialchars($log['result']['error']); ?>
                                                </div>
                                            <?php endif; ?>
                                        </div>

                                        <!-- Debug Information -->
                                        <?php if (isset($log['result']['debug'])): ?>
                                            <div class="mb-3">
                                                <strong>Informações de Debug:</strong>
                                                <div class="row mt-2">
                                                    <div class="col-md-6">
                                                        <small><strong>HTTP Code:</strong> <?php echo $log['result']['debug']['http_code'] ?? 'N/A'; ?></small>
                                                    </div>
                                                    <div class="col-md-6">
                                                        <small><strong>Response Length:</strong> <?php echo $log['result']['debug']['response_length'] ?? 'N/A'; ?> bytes</small>
                                                    </div>
                                                </div>
                                                
                                                <?php if (!empty($log['result']['debug']['curl_error'])): ?>
                                                    <div class="alert alert-warning mt-2">
                                                        <strong>cURL Error:</strong> <?php echo htmlspecialchars($log['result']['debug']['curl_error']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        <?php endif; ?>

                                        <!-- Response Raw (colapsável) -->
                                        <?php if (isset($log['result']['debug']['response_raw'])): ?>
                                            <div class="mb-3">
                                                <button class="btn btn-sm btn-outline-secondary" type="button" data-bs-toggle="collapse" data-bs-target="#raw-<?php echo md5($log['timestamp']); ?>">
                                                    <i class="fas fa-code"></i> Ver Response Raw
                                                </button>
                                                <div class="collapse mt-2" id="raw-<?php echo md5($log['timestamp']); ?>">
                                                    <div class="json-viewer">
                                                        <pre><?php echo htmlspecialchars(substr($log['result']['debug']['response_raw'], 0, 5000)); ?><?php echo strlen($log['result']['debug']['response_raw']) > 5000 ? '...' : ''; ?></pre>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Loading Overlay -->
    <div id="loadingOverlay" class="position-fixed top-0 start-0 w-100 h-100 d-flex align-items-center justify-content-center" style="background: rgba(0,0,0,0.5); z-index: 9999; display: none;">
        <div class="text-center text-white">
            <div class="spinner-border mb-3" role="status">
                <span class="visually-hidden">Carregando...</span>
            </div>
            <h5>Processando requisição...</h5>
            <p>Aguarde enquanto analisamos a URL</p>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.getElementById('debugForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            const url = document.getElementById('testUrl').value;
            const loadingOverlay = document.getElementById('loadingOverlay');
            const testResult = document.getElementById('testResult');
            const resultContent = document.getElementById('resultContent');
            
            loadingOverlay.style.display = 'flex';
            testResult.style.display = 'none';
            
            fetch('/assessoria/debug/test', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ url: url })
            })
            .then(response => response.json())
            .then(data => {
                loadingOverlay.style.display = 'none';
                
                // Mostrar resultado
                resultContent.innerHTML = `
                    <div class="alert ${data.success ? 'alert-success' : 'alert-danger'}">
                        <h6>Resultado: ${data.success ? 'Sucesso' : 'Erro'}</h6>
                        ${data.success ? '' : '<p><strong>Erro:</strong> ' + data.error + '</p>'}
                    </div>
                    
                    ${data.debug ? `
                        <div class="mt-3">
                            <h6>Informações de Debug:</h6>
                            <div class="row">
                                <div class="col-md-6"><small><strong>HTTP Code:</strong> ${data.debug.http_code || 'N/A'}</small></div>
                                <div class="col-md-6"><small><strong>Response Length:</strong> ${data.debug.response_length || 'N/A'} bytes</small></div>
                            </div>
                            
                            ${data.debug.response_raw ? `
                                <div class="mt-3">
                                    <h6>Response Raw:</h6>
                                    <div class="json-viewer">
                                        <pre>${data.debug.response_raw.substring(0, 2000)}${data.debug.response_raw.length > 2000 ? '...' : ''}</pre>
                                    </div>
                                </div>
                            ` : ''}
                        </div>
                    ` : ''}
                `;
                
                testResult.style.display = 'block';
                
                // Recarregar após 2 segundos para mostrar no log
                setTimeout(() => {
                    window.location.reload();
                }, 2000);
            })
            .catch(error => {
                loadingOverlay.style.display = 'none';
                resultContent.innerHTML = `
                    <div class="alert alert-danger">
                        <h6>Erro na Requisição</h6>
                        <p>${error.message}</p>
                    </div>
                `;
                testResult.style.display = 'block';
            });
        });
        
        function clearLogs() {
            if (confirm('Tem certeza que deseja limpar todos os logs?')) {
                // Limpar logs da sessão via AJAX
                fetch('/assessoria/debug/clear', { method: 'POST' })
                .then(() => window.location.reload())
                .catch(() => window.location.reload());
            }
        }
        
        // Auto-scroll para o resultado
        window.addEventListener('load', function() {
            const testResult = document.getElementById('testResult');
            if (testResult && testResult.style.display !== 'none') {
                testResult.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        });
    </script>
</body>
</html>
