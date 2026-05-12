<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
    <div>
        <h1 class="page-title">Auditoria de Promoções</h1>
        <p class="page-subtitle">Histórico e controle de preços promocionais</p>
    </div>
</div>

<!-- Stats -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm text-center p-3">
            <div class="fs-3 fw-bold text-success"><?= $totalAtivas ?></div>
            <div class="text-muted small">Promoções Ativas</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm text-center p-3">
            <div class="fs-3 fw-bold text-danger"><?= $totalExpiradas ?></div>
            <div class="text-muted small">Promoções Expiradas</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm text-center p-3">
            <div class="fs-3 fw-bold text-primary"><?= count($promocoes) ?></div>
            <div class="text-muted small">Exibindo</div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card border-0 shadow-sm text-center p-3">
            <div class="fs-3 fw-bold text-info"><?= count($historico) ?></div>
            <div class="text-muted small">Registros no Histórico</div>
        </div>
    </div>
</div>

<!-- Filtros -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <form class="row g-2 align-items-end" method="GET" action="/admin/promocoes-auditoria">
            <div class="col-md-3">
                <label class="form-label mb-1">Status</label>
                <select class="form-select" name="status">
                    <option value="todas" <?= ($filtroStatus === 'todas') ? 'selected' : '' ?>>Todas com promoção</option>
                    <option value="ativas" <?= ($filtroStatus === 'ativas') ? 'selected' : '' ?>>Ativas agora</option>
                    <option value="expiradas" <?= ($filtroStatus === 'expiradas') ? 'selected' : '' ?>>Expiradas</option>
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label mb-1">Buscar produto</label>
                <input type="text" class="form-control" name="busca" value="<?= htmlspecialchars($filtroBusca) ?>" placeholder="Nome ou ID do produto...">
            </div>
            <div class="col-md-4 d-flex gap-2">
                <button class="btn btn-primary w-100" type="submit"><i class="fas fa-filter me-1"></i>Filtrar</button>
                <a class="btn btn-outline-secondary w-100" href="/admin/promocoes-auditoria"><i class="fas fa-eraser me-1"></i>Limpar</a>
            </div>
        </form>
    </div>
</div>

<!-- Promoções Atuais -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-header bg-white fw-semibold">
        <i class="fas fa-tags me-2"></i>Promoções nos Produtos (estado atual)
    </div>
    <div class="card-body">
        <?php if (empty($promocoes)): ?>
            <div class="text-muted">Nenhuma promoção encontrada com os filtros selecionados.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Produto</th>
                            <th class="text-end">Preço Regular</th>
                            <th class="text-end">Preço Promo</th>
                            <th class="text-end">Desconto</th>
                            <th>Expira em</th>
                            <th>Status</th>
                            <th>Atualizado</th>
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
                            <td>
                                <a href="/admin/produtos/editar/<?= (int) $p['id'] ?>" class="text-decoration-none">
                                    <?= htmlspecialchars((string) ($p['nome'] ?? '')) ?>
                                </a>
                            </td>
                            <td class="text-end">US$ <?= number_format($regular, 2) ?></td>
                            <td class="text-end fw-bold text-success">US$ <?= number_format($promo, 2) ?></td>
                            <td class="text-end">
                                <?php if ($descPct > 0): ?>
                                    <span class="badge bg-danger">-<?= $descPct ?>%</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($expires): ?>
                                    <span class="<?= $expirada ? 'text-danger' : 'text-muted' ?> small">
                                        <?= date('d/m/Y H:i', strtotime($expires)) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="text-muted small">Sem prazo</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($expirada): ?>
                                    <span class="badge bg-warning text-dark">Expirada</span>
                                <?php elseif ($promo > 0): ?>
                                    <span class="badge bg-success">Ativa</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-muted small"><?= !empty($p['updated_at']) ? date('d/m/Y H:i', strtotime($p['updated_at'])) : '-' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Histórico de Alterações -->
<?php if (!empty($historico)): ?>
<div class="card border-0 shadow-sm">
    <div class="card-header bg-white fw-semibold">
        <i class="fas fa-history me-2"></i>Histórico de Alterações
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-sm table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Produto</th>
                        <th>Ação</th>
                        <th class="text-end">Promo Anterior</th>
                        <th class="text-end">Promo Nova</th>
                        <th>Expira</th>
                        <th>Quem</th>
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
                        <td>
                            <a href="/admin/produtos/editar/<?= (int) $h['produto_id'] ?>" class="text-decoration-none">
                                <?= htmlspecialchars((string) ($h['produto_nome'] ?? 'Produto #' . $h['produto_id'])) ?>
                            </a>
                        </td>
                        <td><span class="badge bg-<?= $acaoCor ?>"><?= ucfirst($h['acao']) ?></span></td>
                        <td class="text-end"><?= $h['sale_price_anterior'] ? 'US$ ' . number_format((float) $h['sale_price_anterior'], 2) : '-' ?></td>
                        <td class="text-end fw-bold"><?= $h['sale_price'] ? 'US$ ' . number_format((float) $h['sale_price'], 2) : '-' ?></td>
                        <td class="text-muted small"><?= $h['sale_price_expires'] ? date('d/m/Y H:i', strtotime($h['sale_price_expires'])) : 'Sem prazo' ?></td>
                        <td class="small"><?= htmlspecialchars((string) ($h['usuario_nome'] ?? '-')) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php else: ?>
<div class="card border-0 shadow-sm">
    <div class="card-body text-center text-muted py-4">
        <i class="fas fa-history fs-2 mb-2 d-block"></i>
        <p>O histórico de alterações será registrado a partir de agora.</p>
        <small>Rode a migration <code>144_create_promocoes_historico.sql</code> para ativar.</small>
    </div>
</div>
<?php endif; ?>
