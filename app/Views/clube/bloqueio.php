<?php ob_start(); ?>
<?php
$minUsd = isset($min_usd) ? (float) $min_usd : 39.0;
$wallet = isset($wallet) && is_array($wallet) ? $wallet : [];
$saldoUsd = (float) ($wallet['saldo_usd'] ?? 0);
$saldoBrl = (float) ($wallet['saldo_brl'] ?? 0);
$rate = (float) ($wallet['usd_brl_rate'] ?? 0);
$equivUsd = (float) ($wallet['saldo_usd_equiv'] ?? 0);
$missingUsd = $minUsd - $equivUsd;
if ($missingUsd < 0) $missingUsd = 0;
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4 p-md-5">
                    <div class="d-flex align-items-center gap-3 mb-3">
                        <div class="rounded-circle d-flex align-items-center justify-content-center" style="width:56px;height:56px;background: rgba(11,31,58,0.08);">
                            <i class="fas fa-lock" style="font-size: 1.3rem; color: #0b1f3a;"></i>
                        </div>
                        <div>
                            <h1 class="h4 mb-1" style="color:#0b1f3a; font-weight: 800;">Área exclusiva do Clube Brasiliana</h1>
                            <div class="text-muted">Para acessar, é necessário manter um saldo mínimo de créditos.</div>
                        </div>
                    </div>

                    <div class="alert alert-warning" style="border-radius: 14px;">
                        <div class="fw-bold">Ativação obrigatória</div>
                        <div class="small">Deposite e mantenha pelo menos <strong>$ <?= number_format($minUsd, 2, ',', '.') ?></strong> em créditos na sua carteira para liberar o acesso.</div>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="border rounded p-3 bg-light" style="border-radius:14px !important;">
                                <div class="text-muted small">Saldo USD</div>
                                <div class="h5 mb-0">$ <?= number_format($saldoUsd, 2, ',', '.') ?></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-3 bg-light" style="border-radius:14px !important;">
                                <div class="text-muted small">Saldo BRL</div>
                                <div class="h5 mb-0">R$ <?= number_format($saldoBrl, 2, ',', '.') ?></div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-3 bg-light" style="border-radius:14px !important;">
                                <div class="text-muted small">Equivalente USD</div>
                                <div class="h5 mb-0">$ <?= number_format($equivUsd, 2, ',', '.') ?></div>
                                <?php if ($rate > 0): ?>
                                    <div class="text-muted small mt-1">Taxa USD→BRL: <?= number_format($rate, 4, ',', '.') ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="mt-4 d-flex flex-wrap gap-2">
                        <a href="/minha-conta" class="btn btn-primary">
                            <i class="fas fa-wallet me-2"></i>Ir para Minha Conta / Carteira
                        </a>
                        <a href="/clube-brasiliana" class="btn btn-outline-primary">
                            <i class="fas fa-circle-info me-2"></i>Entender como funciona
                        </a>
                        <a href="/produtos" class="btn btn-outline-secondary">
                            <i class="fas fa-box me-2"></i>Ver produtos comuns
                        </a>
                    </div>

                    <?php if ($missingUsd > 0.00001): ?>
                        <div class="text-muted small mt-3">Faltam aproximadamente <strong>$ <?= number_format($missingUsd, 2, ',', '.') ?></strong> em créditos (equivalente USD) para ativar.</div>
                    <?php endif; ?>

                    <hr class="my-4">

                    <div class="text-muted small">
                        Os termos “rendimento”, “ganho” ou “retorno” são sempre tratados como <strong>créditos internos do Clube</strong> (não são dinheiro, não são saqueáveis e não representam investimento).
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php $content = ob_get_clean(); ?>
<?php $title = 'Clube Brasiliana - Ativação'; ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
