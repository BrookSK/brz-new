<?php ob_start(); ?>
<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0"><i class="fas fa-ticket-alt me-2"></i>Cupons de Desconto</h1>
        <div>
            <a href="/admin" class="btn btn-secondary"><i class="fas fa-arrow-left me-2"></i>Voltar</a>
            <a href="/admin/cupons/novo" class="btn btn-primary ms-2"><i class="fas fa-plus me-2"></i>Novo Cupom</a>
        </div>
    </div>

    <?php if (!empty($_SESSION['message'])): ?>
        <div class="alert alert-<?= $_SESSION['message_type'] ?? 'info' ?> alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
    <?php endif; ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-2">
                <div class="col-md-6">
                    <input type="text" name="busca" class="form-control" value="<?= htmlspecialchars($busca ?? '') ?>" placeholder="Buscar por código ou descrição">
                </div>
                <div class="col-md-3 d-flex gap-2">
                    <button class="btn btn-primary" type="submit"><i class="fas fa-search me-1"></i>Buscar</button>
                    <a class="btn btn-outline-secondary" href="/admin/cupons">Limpar</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">Código</th>
                            <th>Descrição</th>
                            <th>Desconto</th>
                            <th>Validade</th>
                            <th>Usos</th>
                            <th>Mín. produtos</th>
                            <th>Status</th>
                            <th class="pe-3 text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($cupons)): ?>
                            <tr><td colspan="8" class="text-center text-muted py-4">Nenhum cupom cadastrado.</td></tr>
                        <?php else: foreach ($cupons as $c): ?>
                            <?php
                                $desc = ($c['tipo'] === 'percentual')
                                    ? (rtrim(rtrim(number_format((float)$c['valor'], 2, '.', ''), '0'), '.') . '%')
                                    : ('US$ ' . number_format((float)$c['valor'], 2, '.', ','));
                                $validade = '';
                                if (!empty($c['data_inicio']) || !empty($c['data_expiracao'])) {
                                    $ini = !empty($c['data_inicio']) ? date('d/m/Y', strtotime($c['data_inicio'])) : '—';
                                    $fim = !empty($c['data_expiracao']) ? date('d/m/Y', strtotime($c['data_expiracao'])) : '∞';
                                    $validade = $ini . ' → ' . $fim;
                                } else {
                                    $validade = 'Sem prazo';
                                }
                                $limiteTotal = ($c['limite_uso_total'] !== null && $c['limite_uso_total'] !== '') ? (int)$c['limite_uso_total'] : null;
                                $usos = (int)($c['usos_realizados'] ?? 0) . ($limiteTotal !== null ? ' / ' . $limiteTotal : ' / ∞');
                            ?>
                            <tr>
                                <td class="ps-3"><span class="badge bg-dark"><?= htmlspecialchars($c['codigo']) ?></span></td>
                                <td class="text-muted small"><?= htmlspecialchars((string)($c['descricao'] ?? '—')) ?></td>
                                <td class="fw-semibold"><?= $desc ?></td>
                                <td class="small"><?= htmlspecialchars($validade) ?></td>
                                <td class="small"><?= $usos ?></td>
                                <td class="small"><?= ($c['valor_minimo_pedido'] !== null && $c['valor_minimo_pedido'] !== '') ? 'US$ ' . number_format((float)$c['valor_minimo_pedido'], 2, '.', ',') : '—' ?></td>
                                <td>
                                    <?php if ((int)$c['ativo'] === 1): ?>
                                        <span class="badge bg-success">Ativo</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inativo</span>
                                    <?php endif; ?>
                                </td>
                                <td class="pe-3 text-end">
                                    <a class="btn btn-sm btn-outline-primary" href="/admin/cupons/editar/<?= (int)$c['id'] ?>"><i class="fas fa-edit"></i></a>
                                    <form method="POST" action="/admin/cupons/toggle/<?= (int)$c['id'] ?>" class="d-inline">
                                        <button class="btn btn-sm btn-outline-secondary" type="submit" title="<?= (int)$c['ativo'] === 1 ? 'Desativar' : 'Ativar' ?>">
                                            <i class="fas fa-power-off"></i>
                                        </button>
                                    </form>
                                    <button class="btn btn-sm btn-outline-danger" type="button" onclick="excluirCupom(<?= (int)$c['id'] ?>)"><i class="fas fa-trash"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <form id="formExcluirCupom" method="POST" style="display:none;"></form>
</div>

<script>
function excluirCupom(id) {
    if (!confirm('Tem certeza que deseja excluir este cupom?')) return;
    var form = document.getElementById('formExcluirCupom');
    form.action = '/admin/cupons/excluir/' + id;
    form.submit();
}
</script>
<?php
$content = ob_get_clean();
$title = 'Cupons de Desconto - Admin';
include __DIR__ . '/../../layouts/admin.php';
?>
