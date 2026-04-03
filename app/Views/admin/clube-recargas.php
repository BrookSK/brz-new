<?php ob_start(); ?>
<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Recargas Clube (Checkout rápido)</h1>
    </div>

    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Registros (listados)</div>
                    <div class="h4 mb-0"><?= number_format((int) ($stats['total_registros'] ?? 0), 0, ',', '.') ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Total (USD) listado</div>
                    <div class="h4 mb-0">$ <?= number_format((float) ($stats['total_usd'] ?? 0), 2, ',', '.') ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Pagos/creditados (listados)</div>
                    <div class="h4 mb-0"><?= number_format((int) ($stats['total_pago_registros'] ?? 0), 0, ',', '.') ?></div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Total pago (USD) listado</div>
                    <div class="h4 mb-0">$ <?= number_format((float) ($stats['total_pago_usd'] ?? 0), 2, ',', '.') ?></div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6 mb-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Teto de captação (BRL)</div>
                    <div class="h4 mb-0">R$ <?= number_format((float) ($stats['cap_brl'] ?? 150000), 2, ',', '.') ?></div>
                    <div class="small text-muted mt-1">Captado pago: R$ <?= number_format((float) ($stats['total_pago_brl'] ?? 0), 2, ',', '.') ?></div>
                    <?php if (!empty($stats['cap_reached'])): ?>
                        <div class="small fw-semibold mt-1" style="color:#b42318;">Limite atingido</div>
                    <?php else: ?>
                        <div class="small fw-semibold mt-1" style="color:#027a48;">Aberto</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Usuário</th>
                            <th>Pagador</th>
                            <th>Documento</th>
                            <th>Tipo</th>
                            <th>Método</th>
                            <th>Valor USD</th>
                            <th>Valor BRL</th>
                            <th>Gateway</th>
                            <th>Status</th>
                            <th>Criado</th>
                            <th>Pago</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $recargas = isset($recargas) && is_array($recargas) ? $recargas : []; ?>
                        <?php if (empty($recargas)): ?>
                            <tr>
                                <td colspan="12" class="text-muted">Nenhuma recarga encontrada.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recargas as $r): ?>
                                <?php
                                $status = strtolower(trim((string) ($r['status'] ?? 'pending')));
                                $badge = 'secondary';
                                if (in_array($status, ['paid', 'approved', 'credited'], true)) $badge = 'success';
                                elseif (in_array($status, ['rejected', 'failed', 'canceled'], true)) $badge = 'danger';
                                elseif (in_array($status, ['pending'], true)) $badge = 'warning';
                                ?>
                                <tr>
                                    <td>#<?= (int) ($r['id'] ?? 0) ?></td>
                                    <td><?= (int) ($r['usuario_id'] ?? 0) ?></td>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars((string) ($r['pagador_nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                        <div class="text-muted small"><?= htmlspecialchars((string) ($r['pagador_email'] ?? ''), ENT_QUOTES, 'UTF-8') ?></div>
                                    </td>
                                    <td class="text-muted small"><?= htmlspecialchars((string) ($r['pagador_documento'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>
                                        <?php
                                        $tipoRec = strtolower(trim((string) ($r['tipo_recarga'] ?? 'normal')));
                                        $tipoBadge = ($tipoRec === 'turbo') ? 'warning' : 'info';
                                        $tipoLabel = ($tipoRec === 'turbo') ? 'Turbo' : 'Normal';
                                        ?>
                                        <span class="badge bg-<?= $tipoBadge ?>"><?= $tipoLabel ?></span>
                                    </td>
                                    <td><?= htmlspecialchars((string) ($r['metodo'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td>$ <?= number_format((float) ($r['valor'] ?? 0), 2, ',', '.') ?></td>
                                    <td>R$ <?= number_format((float) ($r['valor_brl'] ?? 0), 2, ',', '.') ?></td>
                                    <td class="text-muted small" style="word-break: break-all;">
                                        <?= htmlspecialchars((string) ($r['gateway'] ?? ''), ENT_QUOTES, 'UTF-8') ?><br>
                                        <?= htmlspecialchars((string) ($r['payment_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                                    </td>
                                    <td><span class="badge bg-<?= $badge ?>"><?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?></span></td>
                                    <td class="text-muted small"><?= htmlspecialchars((string) ($r['created_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td class="text-muted small"><?= htmlspecialchars((string) ($r['paid_at'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/admin.php'; ?>
