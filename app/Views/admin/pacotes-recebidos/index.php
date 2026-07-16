<?php ob_start(); ?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="fas fa-box me-2"></i>Pacotes Recebidos
        </h1>
        <div>
            <a href="/admin" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Voltar
            </a>
            <a href="/admin/pacotes-recebidos/configuracoes" class="btn btn-outline-primary ms-2">
                <i class="fas fa-cog me-2"></i>Configurações
            </a>
            <a href="/admin/pacotes-recebidos/novo" class="btn btn-primary ms-2">
                <i class="fas fa-plus me-2"></i>Novo Pacote
            </a>
        </div>
    </div>

    <!-- Mensagem Flash -->
    <?php if (!empty($_SESSION['message'])): ?>
        <div class="alert alert-<?= $_SESSION['message_type'] ?? 'info' ?> alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
    <?php endif; ?>

    <!-- Filtros -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-2">
                    <label class="form-label">Suite</label>
                    <input type="number" name="suite" class="form-control" value="<?= htmlspecialchars($suite) ?>" placeholder="Nº Suite">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach ($statusList as $val => $label): ?>
                        <option value="<?= htmlspecialchars($val) ?>"<?= ($status == $val) ? ' selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">Data Inicial</label>
                    <input type="date" name="data_inicio" class="form-control" value="<?= htmlspecialchars($data_inicio) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Data Final</label>
                    <input type="date" name="data_fim" class="form-control" value="<?= htmlspecialchars($data_fim) ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">Buscar</label>
                    <input type="text" name="busca" class="form-control" placeholder="Nome, fornecedor..." value="<?= htmlspecialchars($busca) ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end gap-2">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search"></i>
                    </button>
                    <a href="/admin/pacotes-recebidos" class="btn btn-secondary">
                        <i class="fas fa-times"></i>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabela -->
    <div class="card border-0 shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <span><strong><?= $total ?></strong> pacote(s) encontrado(s)</span>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>ID</th>
                            <th>Suite</th>
                            <th>Cliente</th>
                            <th>Produto</th>
                            <th>Fornecedor</th>
                            <th>Peso (kg)</th>
                            <th>Qtd</th>
                            <th>Recebido</th>
                            <th>Status</th>
                            <th>Dias</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pacotes)): ?>
                            <tr><td colspan="11" class="text-center text-muted py-4">Nenhum pacote encontrado.</td></tr>
                        <?php else: ?>
                            <?php foreach ($pacotes as $p): ?>
                                <tr>
                                    <td><strong>#<?= $p['id'] ?></strong></td>
                                    <td><span class="badge bg-info"><?= $p['numero_suite'] ?></span></td>
                                    <td>
                                        <small><?= htmlspecialchars($p['usuario_nome'] ?? '-') ?></small><br>
                                        <small class="text-muted"><?= htmlspecialchars($p['usuario_email'] ?? '') ?></small>
                                    </td>
                                    <td>
                                        <?php if (!empty($p['foto_url'])): ?>
                                            <img src="<?= htmlspecialchars($p['foto_url']) ?>" alt="" style="width:30px;height:30px;object-fit:cover;border-radius:4px;" class="me-1">
                                        <?php endif; ?>
                                        <?= htmlspecialchars($p['nome']) ?>
                                    </td>
                                    <td><?= htmlspecialchars($p['fornecedor']) ?></td>
                                    <td><?= number_format((float)$p['peso_kg'], 3, ',', '.') ?></td>
                                    <td><?= $p['quantidade'] ?></td>
                                    <td><?= date('d/m/Y', strtotime($p['data_recebimento'])) ?></td>
                                    <td>
                                        <?php
                                        $statusColors = [
                                            'pendente' => 'warning',
                                            'pedido_criado' => 'primary',
                                            'invoice_liberado' => 'info',
                                            'invoice_confirmado' => 'success',
                                            'invoice_contestado' => 'danger',
                                            'enviado' => 'success',
                                            'fatura_pendente' => 'warning',
                                            'fatura_paga' => 'success',
                                            'descartado' => 'dark',
                                        ];
                                        $cor = $statusColors[$p['status']] ?? 'secondary';
                                        ?>
                                        <span class="badge bg-<?= $cor ?>"><?= $statusList[$p['status']] ?? $p['status'] ?></span>
                                    </td>
                                    <td><?= (int)$p['dias_armazenamento'] ?></td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="/admin/pacotes-recebidos/<?= $p['id'] ?>" class="btn btn-outline-primary" title="Editar">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php if ($p['status'] === 'pendente'): ?>
                                            <button type="button" class="btn btn-outline-danger" title="Excluir" onclick="excluirPacote(<?= $p['id'] ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Paginação -->
    <?php if ($totalPaginas > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                    <li class="page-item <?= ($i == $pagina) ? 'active' : '' ?>">
                        <a class="page-link" href="?pagina=<?= $i ?>&suite=<?= urlencode($suite) ?>&status=<?= urlencode($status) ?>&data_inicio=<?= urlencode($data_inicio) ?>&data_fim=<?= urlencode($data_fim) ?>&busca=<?= urlencode($busca) ?>">
                            <?= $i ?>
                        </a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<!-- Form oculto para exclusão -->
<form id="formExcluir" method="POST" action="" style="display:none;"></form>

<script>
function excluirPacote(id) {
    if (!confirm('Tem certeza que deseja excluir este pacote?')) return;
    const form = document.getElementById('formExcluir');
    form.action = '/admin/pacotes-recebidos/' + id + '/excluir';
    form.submit();
}
</script>

<?php
$content = ob_get_clean();
$title = 'Pacotes Recebidos - Admin';
include __DIR__ . '/../../layouts/admin.php';
?>
