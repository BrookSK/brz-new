<?php ob_start(); ?>
<?php
$logo = (string) ($logo ?? '');
$next = (string) ($next ?? '/');
$err = (string) ($_GET['err'] ?? '');
?>

<div class="container" style="min-height: 100vh; display:flex; align-items:center; justify-content:center; padding: 32px 0;">
    <div class="w-100" style="max-width: 520px;">
        <div class="text-center mb-4">
            <?php if ($logo !== ''): ?>
                <img src="<?= htmlspecialchars($logo, ENT_QUOTES, 'UTF-8') ?>" alt="Logo" style="max-height: 56px; max-width: 100%; object-fit: contain;">
            <?php else: ?>
                <div class="fw-bold" style="font-size: 22px; color:#0b1f3a;">Braziliana</div>
            <?php endif; ?>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h1 class="h5 mb-2" style="color:#0b1f3a; font-weight: 800;">Acesso protegido</h1>
                <div class="text-muted mb-3">Digite a senha para acessar o site.</div>

                <?php if ($err === '1'): ?>
                    <div class="alert alert-danger">Senha inválida. Tente novamente.</div>
                <?php endif; ?>

                <form method="POST" action="/site-lock/unlock" autocomplete="off">
                    <input type="hidden" name="next" value="<?= htmlspecialchars($next, ENT_QUOTES, 'UTF-8') ?>">
                    <div class="mb-3">
                        <label class="form-label">Senha</label>
                        <input type="password" class="form-control" name="password" required>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Entrar</button>
                </form>

                <div class="text-muted small mt-3">Se você não tem a senha, peça ao administrador.</div>
            </div>
        </div>
    </div>
</div>

<?php $content = ob_get_clean(); ?>
<?php $title = 'Acesso protegido'; ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
