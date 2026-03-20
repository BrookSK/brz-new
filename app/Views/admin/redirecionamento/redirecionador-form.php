<?php
$sidebarActive = 'redirecionamento-redirecionadores';
$modo = $modo ?? 'novo';
$r = $redirecionador ?? null;
$title = $modo === 'novo' ? 'Novo Redirecionador' : 'Editar Redirecionador';
$action = $modo === 'novo' ? '/admin/redirecionamento/redirecionadores/salvar' : '/admin/redirecionamento/redirecionadores/atualizar/'.((int)($r['id']??0));
?>
<?php ob_start(); ?>
<div class="container-fluid p-4">
    <div class="d-flex align-items-center gap-3 mb-4">
        <a href="/admin/redirecionamento/redirecionadores" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left"></i></a>
        <h1 class="h2 mb-0"><?= $title ?></h1>
    </div>
    <div class="card border-0 shadow-sm" style="max-width:600px">
        <div class="card-body">
            <form method="post" action="<?= $action ?>">
                <div class="row g-3">
                    <div class="col-12"><label class="form-label">Nome <span class="text-danger">*</span></label><input class="form-control" type="text" name="nome" value="<?= htmlspecialchars($r['nome']??'',ENT_QUOTES,'UTF-8') ?>" required></div>
                    <div class="col-12"><label class="form-label">E-mail <span class="text-danger">*</span></label><input class="form-control" type="email" name="email" value="<?= htmlspecialchars($r['email']??'',ENT_QUOTES,'UTF-8') ?>" required></div>
                    <div class="col-md-6"><label class="form-label">Telefone</label><input class="form-control" type="text" name="telefone" value="<?= htmlspecialchars($r['telefone']??'',ENT_QUOTES,'UTF-8') ?>"></div>
                    <div class="col-md-6"><label class="form-label">Suite</label><input class="form-control" type="text" name="suite" value="<?= htmlspecialchars($r['suite']??'',ENT_QUOTES,'UTF-8') ?>" placeholder="Gerada automaticamente se vazio"></div>
                    <div class="col-12"><label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="ativo" <?= ($r['status']??'ativo')==='ativo'?'selected':'' ?>>Ativo</option>
                            <option value="bloqueado" <?= ($r['status']??'')==='bloqueado'?'selected':'' ?>>Bloqueado</option>
                        </select>
                    </div>
                    <div class="col-12 d-flex gap-2 justify-content-end">
                        <a href="/admin/redirecionamento/redirecionadores" class="btn btn-outline-secondary">Cancelar</a>
                        <button type="submit" class="btn btn-primary">Salvar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<?php $content = ob_get_clean(); include __DIR__ . '/../../layouts/admin.php'; ?>
