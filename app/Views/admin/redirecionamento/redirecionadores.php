<?php
$sidebarActive = 'redirecionamento-redirecionadores';
$title = 'Redirecionadores';
$redirecionadores = is_array($redirecionadores ?? null) ? $redirecionadores : [];
$busca = htmlspecialchars($busca ?? '', ENT_QUOTES, 'UTF-8');
?>
<?php ob_start(); ?>
<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h2 mb-1">Redirecionadores</h1>
            <div class="text-muted small"><?= count($redirecionadores) ?> cadastrado(s)</div>
        </div>
        <a class="btn btn-primary btn-sm" href="/admin/redirecionamento/redirecionadores/novo"><i class="fas fa-plus me-1"></i>Novo redirecionador</a>
    </div>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="get" class="row g-3 align-items-end">
                <div class="col-md-8"><input class="form-control form-control-sm" type="text" name="busca" value="<?= $busca ?>" placeholder="Buscar por nome ou e-mail..."></div>
                <div class="col-md-4 d-flex gap-2">
                    <button class="btn btn-primary btn-sm flex-fill" type="submit"><i class="fas fa-search me-1"></i>Buscar</button>
                    <a class="btn btn-outline-secondary btn-sm" href="/admin/redirecionamento/redirecionadores">Limpar</a>
                </div>
            </form>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-sm table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="ps-3">#</th>
                            <th>Nome</th>
                            <th>E-mail</th>
                            <th>Telefone</th>
                            <th>Suite</th>
                            <th>Status</th>
                            <th class="pe-3 text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($redirecionadores)): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">Nenhum redirecionador cadastrado.</td></tr>
                        <?php else: foreach ($redirecionadores as $r): ?>
                        <tr>
                            <td class="ps-3"><?= (int)$r['id'] ?></td>
                            <td><?= htmlspecialchars($r['nome'],ENT_QUOTES,'UTF-8') ?></td>
                            <td><?= htmlspecialchars($r['email'],ENT_QUOTES,'UTF-8') ?></td>
                            <td><?= htmlspecialchars($r['telefone']??'',ENT_QUOTES,'UTF-8') ?></td>
                            <td><code><?= htmlspecialchars($r['suite']??'',ENT_QUOTES,'UTF-8') ?></code></td>
                            <td>
                                <span class="badge bg-<?= $r['status']==='ativo'?'success':'danger' ?> bg-opacity-10 text-<?= $r['status']==='ativo'?'success':'danger' ?> border border-<?= $r['status']==='ativo'?'success':'danger' ?> border-opacity-25">
                                    <?= $r['status']==='ativo'?'Ativo':'Bloqueado' ?>
                                </span>
                            </td>
                            <td class="pe-3 text-end">
                                <a class="btn btn-xs btn-outline-primary" href="/admin/redirecionamento/redirecionadores/editar/<?= (int)$r['id'] ?>" style="font-size:.75rem;padding:2px 8px">Editar</a>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php $content = ob_get_clean(); include __DIR__ . '/../../layouts/admin.php'; ?>
