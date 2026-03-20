<?php
$sidebarActive = 'redirecionamento-tabela-pesos';
$title = 'Redirecionamento - Tabela de Pesos';
$tabela = is_array($tabela ?? null) ? $tabela : [];
?>
<?php ob_start(); ?>
<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h2 mb-1">Tabela de Pesos e Preços</h1>
            <div class="text-muted small">Controla toda a cobrança do redirecionamento (placeholder)</div>
        </div>
        <a class="btn btn-sm btn-outline-primary" href="/admin/redirecionamento/tabela-pesos">CRUD (em breve)</a>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <h3 class="h5 mb-3">Simulador</h3>
            <div class="row g-3 align-items-end">
                <div class="col-md-4">
                    <label class="form-label">Peso total (kg)</label>
                    <input class="form-control" type="number" step="0.01" min="0" value="1.00" disabled />
                </div>
                <div class="col-md-4">
                    <label class="form-label">Faixa / valor (USD)</label>
                    <input class="form-control" type="text" value="(simulação em breve)" disabled />
                </div>
                <div class="col-md-4 text-end">
                    <button class="btn btn-primary" type="button" disabled>Calcular</button>
                </div>
            </div>
            <div class="alert alert-info mt-3 mb-0">
                O simulador real vai ler a tabela do banco e calcular automaticamente por faixa.
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <h3 class="h5 mb-3">Tabela</h3>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle">
                    <thead>
                        <tr>
                            <th>Peso mínimo (kg)</th>
                            <th>Peso máximo (kg)</th>
                            <th>Valor (USD)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($tabela)): ?>
                            <tr>
                                <td colspan="3" class="text-muted text-center">Tabela vazia ainda.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($tabela as $row): ?>
                                <tr>
                                    <td><?= htmlspecialchars((string) ($row['peso_min_kg'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars((string) ($row['peso_max_kg'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>US$ <?= number_format((float) ($row['valor_usd'] ?? 0), 2, ',', '.') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="alert alert-warning mt-4 mb-0">
        Este commit adiciona apenas a estrutura/skin. A lógica de CRUD e cálculo será ligada em seguida.
    </div>
</div>
<?php $content = ob_get_clean(); include __DIR__ . '/../../layouts/admin.php'; ?>

