<div class="py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="page-title"><?= __('admin.copilot_cancel.title', 'Cancelamentos via Co-Piloto') ?></h1>
            <p class="page-subtitle"><?= __('admin.copilot_cancel.subtitle', 'Solicitações de cancelamento feitas pelo copiloto') ?></p>
        </div>
        <a href="/admin/copiloto" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i><?= __('common.back', 'Voltar') ?>
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
                    'aguardando_revisao' => ['label' => __('admin.copilot_cancel.filter_awaiting', 'Aguardando'), 'color' => 'warning'],
                    'autorizado' => ['label' => __('admin.copilot_cancel.filter_authorized', 'Autorizados'), 'color' => 'success'],
                    'recusado' => ['label' => __('admin.copilot_cancel.filter_refused', 'Recusados'), 'color' => 'danger'],
                    'processado' => ['label' => __('admin.copilot_cancel.filter_processed', 'Processados'), 'color' => 'info'],
                    'todos' => ['label' => __('admin.copilot_cancel.filter_all', 'Todos'), 'color' => 'secondary'],
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
                    <p><?= __('admin.copilot_cancel.empty', 'Nenhum cancelamento neste filtro.') ?></p>
                </div>
            <?php else: ?>
                <?php foreach ($cancelamentos as $c): ?>
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <strong><?= __('admin.copilot_cancel.order', 'Pedido') ?>: #<?= htmlspecialchars($c['numero_pedido']) ?></strong>
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
                                    <small class="text-muted d-block"><?= __('admin.copilot_cancel.amount_paid', 'Valor pago') ?></small>
                                    <strong>US$ <?= number_format($c['valor_pago_usd'], 2) ?></strong>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted d-block"><?= __('admin.copilot_cancel.cancellation_fee', 'Taxa cancelamento') ?></small>
                                    <strong class="text-danger">US$ <?= number_format($c['taxa_cancelamento_usd'], 2) ?></strong>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted d-block"><?= __('admin.copilot_cancel.refund', 'Reembolso') ?></small>
                                    <strong class="text-success">US$ <?= number_format($c['valor_reembolso_usd'], 2) ?></strong>
                                    <?php if (!empty($c['valor_reembolso_brl'])): ?>
                                        <br><small class="text-muted">≈ R$ <?= number_format($c['valor_reembolso_brl'], 2, ',', '.') ?></small>
                                    <?php endif; ?>
                                </div>
                                <div class="col-md-3">
                                    <small class="text-muted d-block"><?= __('admin.copilot_cancel.method', 'Método') ?></small>
                                    <strong><?= htmlspecialchars($c['metodo_reembolso'] ?? '—') ?></strong>
                                </div>
                            </div>

                            <?php if ($c['status'] === 'aguardando_revisao'): ?>
                                <div class="d-flex gap-2">
                                    <form method="POST" action="/admin/copiloto/cancelamentos/autorizar/<?= $c['id'] ?>">
                                        <button type="submit" class="btn btn-success btn-sm"
                                            onclick="return confirm('<?= htmlspecialchars(__('admin.copilot_cancel.confirm_authorize', 'Autorizar cancelamento e processar reembolso de US$ {amount}?', ['amount' => number_format($c['valor_reembolso_usd'], 2)]), ENT_QUOTES, 'UTF-8') ?>')">
                                            <i class="fas fa-check me-1"></i><?= __('admin.copilot_cancel.authorize_process', 'Autorizar e processar reembolso') ?>
                                        </button>
                                    </form>
                                    <form method="POST" action="/admin/copiloto/cancelamentos/recusar/<?= $c['id'] ?>">
                                        <input type="text" name="motivo" placeholder="<?= htmlspecialchars(__('admin.copilot_cancel.reason_placeholder', 'Motivo (opcional)'), ENT_QUOTES, 'UTF-8') ?>" class="form-control form-control-sm d-inline-block" style="width:250px">
                                        <button type="submit" class="btn btn-outline-danger btn-sm">
                                            <i class="fas fa-times me-1"></i><?= __('admin.copilot_cancel.refuse', 'Recusar') ?>
                                        </button>
                                    </form>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($c['motivo_recusa'])): ?>
                                <div class="alert alert-danger mt-2 mb-0 py-1 px-2">
                                    <small><strong><?= __('admin.copilot_cancel.refusal_reason', 'Motivo da recusa:') ?></strong> <?= htmlspecialchars($c['motivo_recusa']) ?></small>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

</div>
