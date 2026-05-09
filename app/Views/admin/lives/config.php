<?php
/** @var array $config */
/** @var array $credentials */
/** @var array $quota */
/** @var string $activePage */
/** @var string $title */
$modo = $config['modo_operacao'] ?? 'desligado';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <?php renderAdminSidebar($activePage); ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3"><i class="fas fa-cog me-2"></i>Configurações - Lives</h1>
                <a href="/admin/lives" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-1"></i> Voltar
                </a>
            </div>

            <?php if (isset($_GET['saved'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check me-2"></i>Configurações salvas com sucesso!
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <form method="POST" action="/admin/configuracoes/lives">
                <div class="row">
                    <div class="col-lg-6">
                        <!-- Modo de Operação -->
                        <div class="card mb-4">
                            <div class="card-header"><i class="fas fa-power-off me-2"></i>Modo de Operação</div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="radio" name="modo_operacao" value="online" id="modoOnline"
                                               <?= $modo === 'online' ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="modoOnline">
                                            <strong class="text-success">Online</strong> — Disponível para todos os clientes
                                        </label>
                                    </div>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="radio" name="modo_operacao" value="teste" id="modoTeste"
                                               <?= $modo === 'teste' ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="modoTeste">
                                            <strong class="text-warning">Teste</strong> — Somente admins podem ver e usar
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="modo_operacao" value="desligado" id="modoOff"
                                               <?= $modo === 'desligado' ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="modoOff">
                                            <strong class="text-danger">Desligado</strong> — Ninguém consegue acessar
                                        </label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Cloudflare Stream -->
                        <div class="card mb-4">
                            <div class="card-header"><i class="fas fa-cloud me-2"></i>Cloudflare Stream</div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Account ID</label>
                                    <input type="text" name="cf_account_id" class="form-control" 
                                           value="<?= htmlspecialchars($credentials['account_id'] ?? '') ?>"
                                           placeholder="Ex: a1b2c3d4e5f6...">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">API Token</label>
                                    <input type="password" name="cf_api_token" class="form-control"
                                           value="<?= htmlspecialchars($credentials['api_token'] ?? '') ?>"
                                           placeholder="Token com permissão Stream:Edit">
                                    <small class="text-muted">Gere em: Cloudflare Dashboard → API Tokens → Create Token → Stream:Edit</small>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Stream Subdomain (opcional)</label>
                                    <input type="text" name="cf_stream_subdomain" class="form-control"
                                           value="<?= htmlspecialchars($credentials['subdomain'] ?? '') ?>"
                                           placeholder="Ex: customer-abc123.cloudflarestream.com">
                                </div>
                                <button type="button" class="btn btn-outline-info btn-sm" onclick="testConnection()">
                                    <i class="fas fa-plug me-1"></i> Testar Conexão
                                </button>
                                <span id="testResult" class="ms-2"></span>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-6">
                        <!-- Cota Mensal -->
                        <div class="card mb-4">
                            <div class="card-header"><i class="fas fa-clock me-2"></i>Cota Mensal de Streaming</div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Minutos inclusos por mês</label>
                                    <input type="number" name="minutos_inclusos" class="form-control" min="0"
                                           value="<?= (int)($config['minutos_inclusos'] ?? 300) ?>">
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Ao exceder a cota</label>
                                    <select name="modo_excedente" class="form-select">
                                        <option value="block" <?= ($config['modo_excedente'] ?? 'block') === 'block' ? 'selected' : '' ?>>
                                            Bloquear novas lives
                                        </option>
                                        <option value="charge" <?= ($config['modo_excedente'] ?? '') === 'charge' ? 'selected' : '' ?>>
                                            Cobrar por minuto excedente
                                        </option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Preço por minuto excedente (R$)</label>
                                    <input type="number" name="preco_minuto_excedente" class="form-control" min="0" step="0.01"
                                           value="<?= number_format((float)($config['preco_minuto_excedente'] ?? 0), 2, '.', '') ?>">
                                </div>

                                <!-- Uso atual -->
                                <div class="alert alert-light">
                                    <strong>Uso este mês:</strong> <?= $quota['minutes_used'] ?> / <?= $quota['minutes_included'] ?> min
                                    <?php if ($quota['exceeded']): ?>
                                        <span class="badge bg-danger ms-2">Cota excedida</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary btn-lg w-100">
                            <i class="fas fa-save me-2"></i> Salvar Configurações
                        </button>
                    </div>
                </div>
            </form>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
async function testConnection() {
    const result = document.getElementById('testResult');
    result.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testando...';
    
    // Salvar primeiro via AJAX
    const form = document.querySelector('form');
    const formData = new FormData(form);
    formData.append('ajax', '1');

    try {
        const res = await fetch('/admin/configuracoes/lives', { method: 'POST', body: formData });
        const data = await res.json();
        
        if (data.connection_test) {
            if (data.connection_test.success) {
                result.innerHTML = '<span class="text-success"><i class="fas fa-check"></i> ' + data.connection_test.message + '</span>';
            } else {
                result.innerHTML = '<span class="text-danger"><i class="fas fa-times"></i> ' + data.connection_test.message + '</span>';
            }
        }
    } catch (e) {
        result.innerHTML = '<span class="text-danger">Erro de conexão</span>';
    }
}
</script>
</body>
</html>
