<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h1 class="page-title"><?= __('admin.promos_audit.title', 'Auditoria de Promoções') ?></h1>
        <p class="page-subtitle"><?= __('admin.promos_audit.subtitle', 'Histórico e controle de preços promocionais') ?></p>
    </div>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center p-3">
            <div class="fs-3 fw-bold text-success"><?= $totalAtivas ?></div>
            <div class="text-muted small"><?= __('admin.promos_audit.active_promos', 'Promoções Ativas') ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center p-3">
            <div class="fs-3 fw-bold text-danger"><?= $totalExpiradas ?></div>
            <div class="text-muted small"><?= __('admin.promos_audit.expired_promos', 'Promoções Expiradas') ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center p-3">
            <div class="fs-3 fw-bold text-primary"><?= count($promocoes) ?></div>
            <div class="text-muted small"><?= __('admin.promos_audit.showing', 'Exibindo') ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card border-0 shadow-sm text-center p-3">
            <div class="fs-3 fw-bold text-info"><?= count($historico) ?></div>
            <div class="text-muted small"><?= __('admin.promos_audit.history', 'Histórico') ?></div>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body py-3">
        <form class="row g-2" method="GET" action="/admin/promocoes-auditoria" id="promoAuditoriaForm">
            <div class="col-6 col-md-3">
                <select class="form-select form-select-sm" name="status" onchange="this.form.submit()">
                    <option value="todas" <?= ($filtroStatus === 'todas') ? 'selected' : '' ?>><?= __('admin.promos_audit.all_with_promo', 'Todas com promoção') ?></option>
                    <option value="ativas" <?= ($filtroStatus === 'ativas') ? 'selected' : '' ?>><?= __('admin.promos_audit.active_now', 'Ativas agora') ?></option>
                    <option value="expiradas" <?= ($filtroStatus === 'expiradas') ? 'selected' : '' ?>><?= __('admin.promos_audit.expired', 'Expiradas') ?></option>
                </select>
            </div>
            <div class="col-6 col-md-5">
                <input type="text" class="form-control form-control-sm" name="busca" value="<?= htmlspecialchars($filtroBusca) ?>" placeholder="<?= htmlspecialchars(__('admin.promos_audit.search_product', 'Buscar produto...'), ENT_QUOTES, 'UTF-8') ?>" oninput="clearTimeout(this._t);this._t=setTimeout(()=>this.form.submit(),400)">
            </div>
            <div class="col-6 col-md-2">
                <a class="btn btn-sm btn-outline-secondary w-100" href="/admin/promocoes-auditoria"><?= __('admin.promos_audit.clear', 'Limpar') ?></a>
            </div>
        </form>
    </div>
</div>

<!-- Promoções Atuais -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white fw-semibold"><?= __('admin.promos_audit.promos_on_products', 'Promoções nos Produtos (estado atual)') ?></div>
    <div class="card-body">
        <?php if (empty($promocoes)): ?>
            <div class="text-muted"><?= __('admin.promos_audit.none_found_filters', 'Nenhuma promoção encontrada com os filtros selecionados.') ?></div>
        <?php else: ?>
            <!-- Desktop: Table -->
            <div class="table-responsive d-none d-md-block">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th><?= __('admin.promos_audit.col_id', 'ID') ?></th>
                            <th><?= __('admin.promos_audit.col_product', 'Produto') ?></th>
                            <th class="text-end"><?= __('admin.promos_audit.col_regular', 'Regular') ?></th>
                            <th class="text-end"><?= __('admin.promos_audit.col_promo', 'Promo') ?></th>
                            <th class="text-end"><?= __('admin.promos_audit.col_disc', 'Desc.') ?></th>
                            <th><?= __('admin.promos_audit.col_expires', 'Expira') ?></th>
                            <th><?= __('admin.promos_audit.col_status', 'Status') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($promocoes as $p):
                            $regular = (float) ($p['preco_regular'] ?? 0);
                            $promo = (float) ($p['sale_price'] ?? 0);
                            $expires = $p['sale_price_expires'] ?? null;
                            $expirada = ($expires && strtotime($expires) < time());
                            $descPct = ($regular > 0 && $promo > 0) ? round((1 - $promo / $regular) * 100, 1) : 0;
                        ?>
                        <tr class="<?= $expirada ? 'table-warning' : '' ?>">
                            <td class="fw-semibold">#<?= (int) $p['id'] ?></td>
                            <td><a href="/admin/produtos/editar/<?= (int) $p['id'] ?>" class="text-decoration-none"><?= htmlspecialchars((string) ($p['nome'] ?? '')) ?></a></td>
                            <td class="text-end">US$ <?= number_format($regular, 2) ?></td>
                            <td class="text-end fw-bold text-success">US$ <?= number_format($promo, 2) ?></td>
                            <td class="text-end"><?php if ($descPct > 0): ?><span class="badge bg-danger">-<?= $descPct ?>%</span><?php endif; ?></td>
                            <td class="small <?= $expirada ? 'text-danger' : 'text-muted' ?>"><?= $expires ? date('d/m/Y H:i', strtotime($expires)) : __('admin.promos_audit.no_deadline', 'Sem prazo') ?></td>
                            <td><?= $expirada ? '<span class="badge bg-warning text-dark">' . __('admin.promos_audit.expired_badge', 'Expirada') . '</span>' : ($promo > 0 ? '<span class="badge bg-success">' . __('admin.promos_audit.active_badge', 'Ativa') . '</span>' : '') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <!-- Mobile: Cards -->
            <div class="d-md-none">
                <?php foreach ($promocoes as $p):
                    $regular = (float) ($p['preco_regular'] ?? 0);
                    $promo = (float) ($p['sale_price'] ?? 0);
                    $expires = $p['sale_price_expires'] ?? null;
                    $expirada = ($expires && strtotime($expires) < time());
                    $descPct = ($regular > 0 && $promo > 0) ? round((1 - $promo / $regular) * 100, 1) : 0;
                ?>
                <div class="border-bottom py-2">
                    <div class="d-flex justify-content-between align-items-start">
                        <div style="min-width:0;flex:1;">
                            <div class="fw-semibold small" style="word-break:break-word;">
                                <a href="/admin/produtos/editar/<?= (int) $p['id'] ?>" class="text-decoration-none">#<?= (int) $p['id'] ?> <?= htmlspecialchars((string) ($p['nome'] ?? '')) ?></a>
                            </div>
                            <div class="d-flex flex-wrap gap-2 mt-1 small">
                                <span class="text-muted text-decoration-line-through">US$ <?= number_format($regular, 2) ?></span>
                                <span class="fw-bold text-success">US$ <?= number_format($promo, 2) ?></span>
                                <?php if ($descPct > 0): ?><span class="badge bg-danger">-<?= $descPct ?>%</span><?php endif; ?>
                            </div>
                            <div class="text-muted small mt-1"><?= $expires ? ($expirada ? '<span class="text-danger">' . __('admin.promos_audit.expired_on', 'Expirou') . ' ' : __('admin.promos_audit.expires_on', 'Expira') . ' ') . date('d/m/Y', strtotime($expires)) . ($expirada ? '</span>' : '') : __('admin.promos_audit.no_deadline', 'Sem prazo') ?></div>
                        </div>
                        <div class="ms-2">
                            <?= $expirada ? '<span class="badge bg-warning text-dark">' . __('admin.promos_audit.expired_badge', 'Expirada') . '</span>' : ($promo > 0 ? '<span class="badge bg-success">' . __('admin.promos_audit.active_badge', 'Ativa') . '</span>' : '') ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Histórico de Alterações -->
<?php if (!empty($historico)): ?>
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-semibold"><?= __('admin.promos_audit.changes_history', 'Histórico de Alterações') ?></div>
    <div class="card-body">
        <!-- Desktop: Table -->
        <div class="table-responsive d-none d-md-block">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th><?= __('admin.promos_audit.col_date', 'Data') ?></th>
                        <th><?= __('admin.promos_audit.col_product', 'Produto') ?></th>
                        <th><?= __('admin.promos_audit.col_action', 'Ação') ?></th>
                        <th class="text-end"><?= __('admin.promos_audit.col_previous', 'Anterior') ?></th>
                        <th class="text-end"><?= __('admin.promos_audit.col_new', 'Nova') ?></th>
                        <th><?= __('admin.promos_audit.col_expires', 'Expira') ?></th>
                        <th><?= __('admin.promos_audit.col_who', 'Quem') ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($historico as $h):
                        $acaoCor = match($h['acao'] ?? '') {
                            'criada' => 'success', 'alterada' => 'info',
                            'removida' => 'danger', 'expirada' => 'warning',
                            default => 'secondary'
                        };
                    ?>
                    <tr>
                        <td class="text-muted small"><?= date('d/m/Y H:i', strtotime($h['created_at'])) ?></td>
                        <td><a href="/admin/produtos/editar/<?= (int) $h['produto_id'] ?>" class="text-decoration-none"><?= htmlspecialchars((string) ($h['produto_nome'] ?? __('admin.promos_audit.product_hash', 'Produto #{id}', ['id' => $h['produto_id']]))) ?></a></td>
                        <td><span class="badge bg-<?= $acaoCor ?>"><?= ucfirst($h['acao']) ?></span></td>
                        <td class="text-end"><?= $h['sale_price_anterior'] ? 'US$ ' . number_format((float) $h['sale_price_anterior'], 2) : '-' ?></td>
                        <td class="text-end fw-bold"><?= $h['sale_price'] ? 'US$ ' . number_format((float) $h['sale_price'], 2) : '-' ?></td>
                        <td class="text-muted small"><?= $h['sale_price_expires'] ? date('d/m/Y', strtotime($h['sale_price_expires'])) : '-' ?></td>
                        <td class="small"><?= htmlspecialchars((string) ($h['usuario_nome'] ?? '-')) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <!-- Mobile: Cards -->
        <div class="d-md-none">
            <?php foreach ($historico as $h):
                $acaoCor = match($h['acao'] ?? '') {
                    'criada' => 'success', 'alterada' => 'info',
                    'removida' => 'danger', 'expirada' => 'warning',
                    default => 'secondary'
                };
            ?>
            <div class="border-bottom py-2">
                <div class="d-flex justify-content-between align-items-center">
                    <span class="badge bg-<?= $acaoCor ?>"><?= ucfirst($h['acao']) ?></span>
                    <span class="text-muted small"><?= date('d/m/Y H:i', strtotime($h['created_at'])) ?></span>
                </div>
                <div class="fw-semibold small mt-1" style="word-break:break-word;"><?= htmlspecialchars((string) ($h['produto_nome'] ?? __('admin.promos_audit.product_hash', 'Produto #{id}', ['id' => $h['produto_id']]))) ?></div>
                <div class="d-flex gap-2 small mt-1">
                    <?php if ($h['sale_price_anterior']): ?><span class="text-muted text-decoration-line-through">US$ <?= number_format((float) $h['sale_price_anterior'], 2) ?></span><?php endif; ?>
                    <?php if ($h['sale_price']): ?><span class="fw-bold">→ US$ <?= number_format((float) $h['sale_price'], 2) ?></span><?php endif; ?>
                </div>
                <div class="text-muted small"><?= htmlspecialchars((string) ($h['usuario_nome'] ?? '-')) ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php else: ?>
<div class="card border-0 shadow-sm">
    <div class="card-body text-center text-muted py-4">
        <p><?= __('admin.promos_audit.history_will_be_recorded', 'O histórico de alterações será registrado a partir de agora.') ?></p>
        <small><?= __('admin.promos_audit.run_migration_prefix', 'Rode a migration') ?> <code>144_create_promocoes_historico.sql</code> <?= __('admin.promos_audit.run_migration_suffix', 'para ativar.') ?></small>
    </div>
</div>
<?php endif; ?>
