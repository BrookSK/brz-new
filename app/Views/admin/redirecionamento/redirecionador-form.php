<?php
$sidebarActive = 'redirecionamento-redirecionadores';
$modo = $modo ?? 'novo';
$r = $redirecionador ?? null;
$title = $modo === 'novo' ? __('admin.redirect.new_redirector_title', 'Novo Redirecionador') : __('admin.redirect.edit_redirector_title', 'Editar Redirecionador');
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
                    <div class="col-12"><label class="form-label"><?= __('admin.redirect.name', 'Nome') ?> <span class="text-danger">*</span></label><input class="form-control" type="text" name="nome" value="<?= htmlspecialchars($r['nome']??'',ENT_QUOTES,'UTF-8') ?>" required></div>
                    <div class="col-12"><label class="form-label"><?= __('admin.redirect.email', 'E-mail') ?> <span class="text-danger">*</span></label><input class="form-control" type="email" name="email" value="<?= htmlspecialchars($r['email']??'',ENT_QUOTES,'UTF-8') ?>" required></div>
                    <div class="col-md-6"><label class="form-label"><?= __('admin.redirect.phone', 'Telefone') ?></label><input class="form-control" type="text" name="telefone" value="<?= htmlspecialchars($r['telefone']??'',ENT_QUOTES,'UTF-8') ?>"></div>
                    <div class="col-md-6"><label class="form-label"><?= __('admin.redirect.suite', 'Suite') ?></label><input class="form-control" type="text" name="suite" value="<?= htmlspecialchars($r['suite']??'',ENT_QUOTES,'UTF-8') ?>" placeholder="<?= htmlspecialchars(__('admin.redirect.generated_if_empty', 'Gerada automaticamente se vazio'), ENT_QUOTES, 'UTF-8') ?>"></div>
                    <div class="col-12"><label class="form-label"><?= __('admin.redirect.status', 'Status') ?></label>
                        <select class="form-select" name="status">
                            <option value="ativo" <?= ($r['status']??'ativo')==='ativo'?'selected':'' ?>><?= __('admin.redirect.active', 'Ativo') ?></option>
                            <option value="bloqueado" <?= ($r['status']??'')==='bloqueado'?'selected':'' ?>><?= __('admin.redirect.blocked', 'Bloqueado') ?></option>
                        </select>
                    </div>
                    <div class="col-12 d-flex gap-2 justify-content-end">
                        <a href="/admin/redirecionamento/redirecionadores" class="btn btn-outline-secondary"><?= __('admin.redirect.cancel', 'Cancelar') ?></a>
                        <button type="submit" class="btn btn-primary"><?= __('admin.redirect.save', 'Salvar') ?></button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
<?php $content = ob_get_clean(); include __DIR__ . '/../../layouts/admin.php'; ?>
