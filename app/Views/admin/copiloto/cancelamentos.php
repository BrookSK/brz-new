<?php include __DIR__ . '/../../layouts/admin.php'; ?>

<div class="container-fluid admin-shell">
    <div class="row">
        <?php renderAdminSidebar($activePage ?? 'copiloto-cancelamentos'); ?>
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">

            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h2 class="mb-1"><i class="fas fa-times-circle me-2"></i>Cancelamentos via Co-Piloto</h2>
                    <p class="text-muted mb-0">Solicitações de cancelamento feitas pelo copiloto</p>
                </div>
                <a href="/admin/copiloto" class="btn btn-outline-secondary btn-sm">
                    <i class="fas fa-arrow-left me-1"></i>Voltar
                </a>
            </div>

            <?php if (!empty($_SESSION['flash_success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?= htmlspecialchars($_SESSION['flash_success']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['flash_success']); ?>
            <?php endif; ?>

            <!-- Filtros -->
            <div class="d-flex gap-2 mb-4">
                <?php
                $filtros = [
                    'aguardando_revisao' => ['label' => 'Aguardando', 'color' => 'warning'],
                    'autorizado' => ['label' => 'Autorizados', 'color' => 'success'],
                    'recusado' => ['label' => 'Recusados', 'color' => 'danger'],
                    'processado' => ['label' => 'Processados', 'color' => 'info'],
                    'todos' => ['label' => 'Todos', 'color' => 'secondary'],
                ];
                foreach ($filtros as $key => $f):
                    $active = ($status ?? 'aguardando_revisao') === $key ? 'btn-primary' : 'btn-outline-secondary';
                    $count = $key === 'todos' ? array_sum($contadores) : ($contadores[$key] ?? 0);
                ?>
                    <a href="/admin/copiloto/cancelamentos?status=<?= $key ?>" class="btn <?= $active ?> btn-sm">
                        <?= $f['label'] ?> <span class="badge bg-<?= $f['color'] ?> ms-1"><?= $count ?></span>
                    </a>
                <?php endforeach; ?>
            </div>

            <?php if (empty($cancelamentos)): ?>
                <div class="card p-5 text-center text-muted">
                    <i class="fas fa-check-circle fa-3x mb-3"></i>
                    <p>Nenhum cancelamento neste filtro.</p>
                </div>
            <?php else: ?>
                <?php foreach ($cancelamentos as $c): ?>
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <strong>Pedido: #<?= htmlspecialchars($c['numero_pedido']) ?></strong>
                                    <span class="badge bg-<?= $c['status'] === 'aguardando_revisao' ? 'warning' : ($c['status'] === 'autorizado' ? 'success' : ($c['status'] === 'recusado' ? 'danger' : 'info')) ?> ms-2">
                                        <?= ucfirst(str_replace('_', ' ', $c['status'])) ?>
                                    </span>
                                    <?php if (!empty($c['numero_solicitacao'])): ?>
                                        <small class="text-muted ms-2"><?= htmlspecialchars($c['numero_solicitacao']) ?></small>
                                    <?php endif; ?>
                                </div>
                                <small class="text-muted"><?= date('d/m/Y H:i', strtotime($c['solicitado_em'])) ?></small>
                            </div>

                            <div class="row g-3 mb-3">
                                <div class="col-md-3">
                                    <small class="text-muted d-block">Valor pago</small>
                                    <strong>US$ <?= number_format($c['valor_pago_usd'], 2) ?></strong>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted d-block">Taxa cancelamento</small>
                                    <strong class="text-danger">US$ <?= number_format($c['taxa_cancelamento_usd'], 2) ?></strong>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted d-block">Reembolso</small>
                                    <strong class="text-success">US$ <?= number_format($c['valor_reembolso_usd'], 2) ?></strong>
                                    <?php if (!empty($c['valor_reembolso_brl'])): ?>
                                        <br><small class="text-muted">≈ R$ <?= number_format($c['valor_reembolso_brl'], 2, ',', '.') ?></small>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted d-block">Método</small>
                                    <strong><?= htmlspecialchars($c['metodo_reembolso'] ?? '—') ?></strong>
                                </div>
                            </div>

                            <?php if ($c['status'] === 'aguardando_revisao'): ?>
                                <div class="d-flex gap-2">
                                    <form method="POST" action="/admin/copiloto/cancelamentos/autorizar/<?= $c['id'] ?>">
                                        <button type="submit" class="btn btn-success btn-sm"
                                            onclick="return confirm('Autorizar cancelamento e processar reembolso de US$ <?= number_format($c['valor_reembolso_usd'], 2) ?>?')">
                                            <i class="fas fa-check me-1"></i>Autorizar e processar reembolso
                                        </button>
                                    </form>
                                    <form method="POST" action="/admin/copiloto/cancelamentos/recusar/<?= $c['id'] ?>">
                                        <input type="text" name="motivo" placeholder="Motivo (opcional)" class="form-control form-control-sm d-inline-block" style="width:250px">
                                        <button type="submit" class="btn btn-outline-danger btn-sm">
                                            <i class="fas fa-times me-1"></i>Recusar
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($c['motivo_recusa'])): ?>
                                <div class="alert alert-danger mt-2 mb-0 py-1 px-2">
                                    <small><strong>Motivo da recusa:</strong> <?= htmlspecialchars($c['motivo_recusa']) ?></small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

        </main>
    </div>
</div>
