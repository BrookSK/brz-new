<?php require_once __DIR__.'/../../partials/admin_sidebar.php'; renderAdminSidebarStyles(); ?>
<!DOCTYPE html>
<html lang="pt-BR">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>QuickBooks - Braziliana Admin</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
<div class="container-fluid"><div class="row">
<?php renderAdminSidebar('quickbooks'); ?>
<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">

<div class="d-flex justify-content-between align-items-center mb-4">
    <h2><i class="fas fa-calculator me-2"></i>QuickBooks Online</h2>
</div>

<?php if (!empty($_GET['sucesso'])): ?>
<div class="alert alert-success"><?= htmlspecialchars($_GET['sucesso']) ?></div>
<?php endif; ?>
<?php if (!empty($_GET['erro'])): ?>
<div class="alert alert-danger"><?= htmlspecialchars($_GET['erro']) ?></div>
<?php endif; ?>

<div class="row mb-4">
    <!-- Status da Conexão -->
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-plug me-2"></i>Status da Conexão</h5>
                <?php if ($conectado): ?>
                    <span class="badge bg-success fs-6">Conectado</span>
                    <?php if ($companyInfo): ?>
                        <p class="mt-2 mb-1"><strong>Empresa:</strong> <?= htmlspecialchars($companyInfo['CompanyInfo']['CompanyName'] ?? 'N/A') ?></p>
                        <p class="mb-1 text-muted small">Realm ID: <?= htmlspecialchars($token['realm_id'] ?? '') ?></p>
                        <p class="mb-2 text-muted small">Ambiente: <?= htmlspecialchars($token['ambiente'] ?? '') ?></p>
                    <?php endif; ?>
                    <form method="POST" action="/admin/quickbooks/desconectar" class="mt-2">
                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Desconectar do QuickBooks?')">
                            <i class="fas fa-unlink me-1"></i>Desconectar
                        </button>
                    </form>
                <?php else: ?>
                    <span class="badge bg-secondary fs-6">Desconectado</span>
                    <?php if ($configurado): ?>
                        <div class="mt-3">
                            <a href="/admin/quickbooks/conectar" class="btn btn-primary">
                                <i class="fas fa-link me-1"></i>Conectar ao QuickBooks
                            </a>
                        </div>
                    <?php else: ?>
                        <p class="text-muted mt-2">Configure as credenciais abaixo para conectar.</p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Ações Rápidas -->
    <?php if ($conectado): ?>
    <div class="col-md-4">
        <div class="card h-100">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-bolt me-2"></i>Ações Rápidas</h5>
                <div class="d-grid gap-2">
                    <a href="/admin/quickbooks/invoices" class="btn btn-outline-primary">
                        <i class="fas fa-file-invoice-dollar me-1"></i>Ver Invoices
                    </a>
                    <button type="button" class="btn btn-outline-warning" onclick="document.getElementById('syncLoteCard').style.display=document.getElementById('syncLoteCard').style.display==='none'?'':'none'">
                        <i class="fas fa-sync me-1"></i>Sincronizar em Lote
                    </button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<!-- Configurações -->
<div class="card mb-4">
    <div class="card-header"><i class="fas fa-cog me-2"></i>Configurações QuickBooks</div>
    <div class="card-body">
        <form method="POST" action="/admin/quickbooks/config">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label class="form-label">Client ID <span class="text-danger">*</span></label>
                    <input type="text" name="qb_client_id" class="form-control"
                           value="<?= htmlspecialchars($config['qb_client_id'] ?? '') ?>"
                           placeholder="Client ID do app QuickBooks">
                    <div class="form-text">Obtido em <a href="https://developer.intuit.com" target="_blank">developer.intuit.com</a></div>
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Client Secret <span class="text-danger">*</span></label>
                    <input type="password" name="qb_client_secret" class="form-control"
                           placeholder="<?= !empty($config['qb_client_secret']) ? '(salvo)' : 'Client Secret' ?>">
                </div>
                <div class="col-md-6 mb-3">
                    <label class="form-label">Redirect URI <span class="text-danger">*</span></label>
                    <input type="text" name="qb_redirect_uri" class="form-control"
                           value="<?= htmlspecialchars($config['qb_redirect_uri'] ?? '') ?>"
                           placeholder="https://seusite.com/admin/quickbooks/callback">
                    <div class="form-text">Deve ser cadastrado no app QuickBooks exatamente igual.</div>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Ambiente</label>
                    <select name="qb_ambiente" class="form-select">
                        <option value="sandbox" <?= ($config['qb_ambiente'] ?? 'sandbox') === 'sandbox' ? 'selected' : '' ?>>Sandbox (Testes)</option>
                        <option value="production" <?= ($config['qb_ambiente'] ?? 'sandbox') === 'production' ? 'selected' : '' ?>>Produção</option>
                    </select>
                </div>
                <div class="col-md-3 mb-3">
                    <label class="form-label">Ativo</label>
                    <select name="qb_ativo" class="form-select">
                        <option value="1" <?= ($config['qb_ativo'] ?? '0') === '1' ? 'selected' : '' ?>>Sim</option>
                        <option value="0" <?= ($config['qb_ativo'] ?? '0') === '0' ? 'selected' : '' ?>>Não</option>
                    </select>
                </div>
                <div class="col-12 mb-3">
                    <label class="form-label">Verifier Token (Webhook)</label>
                    <input type="text" name="qb_verifier_token" class="form-control font-monospace"
                           value="<?= htmlspecialchars($config['qb_verifier_token'] ?? '') ?>"
                           placeholder="Token gerado no painel QuickBooks → Webhooks">
                    <div class="form-text">
                        Obtido em <strong>developer.intuit.com → seu app → Webhooks</strong>.
                        URL do webhook a cadastrar:
                        <code><?= htmlspecialchars(($_SERVER['REQUEST_SCHEME'] ?? 'https') . '://' . ($_SERVER['HTTP_HOST'] ?? 'seusite.com') . '/webhook/quickbooks') ?></code>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save me-1"></i>Salvar Configurações
            </button>
        </form>
    </div>
</div>

<!-- Sincronização em Lote (oculto - admin only) -->
<?php if ($conectado): ?>
<div class="card mb-4" id="syncLoteCard" style="display:none;">
    <div class="card-header bg-warning text-dark"><i class="fas fa-sync me-2"></i>Sincronização em Lote</div>
    <div class="card-body">
        <p class="small text-muted">Sincroniza todos os pedidos no período que ainda não foram enviados ao QuickBooks. Pedidos já sincronizados são ignorados automaticamente.</p>
        <div class="row g-2 align-items-end">
            <div class="col-md-3">
                <label class="form-label small">Data Início</label>
                <input type="date" class="form-control form-control-sm" id="syncLoteInicio" value="2026-04-29">
            </div>
            <div class="col-md-3">
                <label class="form-label small">Data Fim</label>
                <input type="date" class="form-control form-control-sm" id="syncLoteFim" value="<?= date('Y-m-d') ?>">
            </div>
            <div class="col-md-3">
                <button type="button" class="btn btn-warning btn-sm" id="btnSyncLote" onclick="executarSyncLote()">
                    <i class="fas fa-play me-1"></i>Executar Sincronização
                </button>
            </div>
        </div>
        <div id="syncLoteResultado" class="mt-3" style="display:none;"></div>
    </div>
</div>
<script>
// Ctrl+Shift+Q para mostrar o painel de sync em lote
document.addEventListener('keydown', function(e) {
    if (e.ctrlKey && e.shiftKey && e.key === 'Q') {
        var card = document.getElementById('syncLoteCard');
        card.style.display = card.style.display === 'none' ? '' : 'none';
    }
});
function executarSyncLote() {
    var btn = document.getElementById('btnSyncLote');
    var res = document.getElementById('syncLoteResultado');
    var inicio = document.getElementById('syncLoteInicio').value;
    var fim = document.getElementById('syncLoteFim').value;
    if (!inicio) { alert('Informe a data de início'); return; }
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Processando...';
    res.style.display = '';
    res.innerHTML = '<div class="alert alert-info">Sincronizando pedidos... Isso pode levar alguns minutos.</div>';

    var fd = new FormData();
    fd.append('data_inicio', inicio);
    if (fim) fd.append('data_fim', fim);

    fetch('/admin/quickbooks/sincronizar-lote', { method: 'POST', body: fd })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.ok && data.resultados) {
                var r = data.resultados;
                var html = '<div class="alert alert-success">'
                    + '<strong>Concluído!</strong> ' + r.sucesso + '/' + r.total + ' pedidos sincronizados.'
                    + '</div>';
                if (r.erros && r.erros.length > 0) {
                    html += '<div class="alert alert-warning"><strong>Erros (' + r.erros.length + '):</strong><ul class="mb-0 small">';
                    r.erros.forEach(function(e) { html += '<li>' + e + '</li>'; });
                    html += '</ul></div>';
                }
                res.innerHTML = html;
            } else {
                res.innerHTML = '<div class="alert alert-danger">Erro: ' + (data.erro || 'Falha desconhecida') + '</div>';
            }
        })
        .catch(function(e) {
            res.innerHTML = '<div class="alert alert-danger">Erro de conexão: ' + e.message + '</div>';
        })
        .finally(function() {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-play me-1"></i>Executar Sincronização';
        });
}
</script>
<?php endif; ?>

<!-- Logs de Sincronização -->
<?php if ($conectado && !empty($logs)): ?>
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><i class="fas fa-history me-2"></i>Logs de Sincronização Recentes</span>
        <a href="/admin/quickbooks/invoices" class="btn btn-sm btn-outline-primary">Ver Invoices</a>
    </div>
    <div class="card-body p-0">
        <table class="table table-sm table-hover mb-0">
            <thead class="table-light">
                <tr>
                    <th>Data</th><th>Entidade</th><th>ID Local</th><th>QB ID</th><th>Ação</th><th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($logs as $log): ?>
                <tr>
                    <td class="text-muted small"><?= htmlspecialchars($log['criado_em']) ?></td>
                    <td><span class="badge bg-light text-dark"><?= htmlspecialchars($log['entidade']) ?></span></td>
                    <td><?= htmlspecialchars((string)($log['entidade_id'] ?? '')) ?></td>
                    <td class="font-monospace small"><?= htmlspecialchars($log['qb_id'] ?? '') ?></td>
                    <td><?= htmlspecialchars($log['acao']) ?></td>
                    <td>
                        <?php if ($log['status'] === 'success'): ?>
                            <span class="badge bg-success">OK</span>
                        <?php else: ?>
                            <span class="badge bg-danger" title="<?= htmlspecialchars($log['erro'] ?? '') ?>">Erro</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

</main>
</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>