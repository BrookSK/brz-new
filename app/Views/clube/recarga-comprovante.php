<?php ob_start(); ?>
<?php
$rec = isset($recarga) && is_array($recarga) ? $recarga : [];
$isPaid = !empty($is_paid);

$fmtUsd = function($v) {
    $v = (float) ($v ?? 0);
    return '$ ' . number_format($v, 2, ',', '.');
};
$fmtBrl = function($v) {
    $v = (float) ($v ?? 0);
    return 'R$ ' . number_format($v, 2, ',', '.');
};

$docMasked = (string) ($rec['pagador_documento'] ?? '');
$docDigits = preg_replace('/\D+/', '', $docMasked);
if (strlen($docDigits) === 11) {
    $docMasked = substr($docDigits, 0, 3) . '.' . substr($docDigits, 3, 3) . '.' . substr($docDigits, 6, 3) . '-' . substr($docDigits, 9, 2);
} elseif (strlen($docDigits) === 14) {
    $docMasked = substr($docDigits, 0, 2) . '.' . substr($docDigits, 2, 3) . '.' . substr($docDigits, 5, 3) . '/' . substr($docDigits, 8, 4) . '-' . substr($docDigits, 12, 2);
}

$token = (string) ($token ?? '');
$rid = (int) ($rec['id'] ?? 0);
$status = strtolower(trim((string) ($rec['status'] ?? 'pending')));
$paidAt = (string) ($rec['paid_at'] ?? '');
$createdAt = (string) ($rec['created_at'] ?? '');
$gateway = (string) ($rec['gateway'] ?? '');
$paymentId = (string) ($rec['payment_id'] ?? '');
$metodo = (string) ($rec['metodo'] ?? '');
$rate = isset($rec['usd_brl_rate']) ? (float) $rec['usd_brl_rate'] : null;
$valorUsd = (float) ($rec['valor'] ?? 0);
$valorBrl = isset($rec['valor_brl']) ? (float) $rec['valor_brl'] : null;
?>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3">
                <div>
                    <h1 class="h4 mb-1" style="color:#0b1f3a; font-weight: 800;">Comprovante da Recarga</h1>
                    <div class="text-muted">Clube Brasiliana</div>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-primary" id="btnPrint">Imprimir</button>
                    <button type="button" class="btn btn-outline-secondary" id="btnShare">Compartilhar</button>
                </div>
            </div>

            <?php if (!$isPaid): ?>
                <div class="alert alert-warning" style="border-radius:14px;">
                    <div class="fw-bold">Pagamento ainda não confirmado</div>
                    <div class="small">Status atual: <strong><?= htmlspecialchars($status) ?></strong>. Esta página será atualizada automaticamente.</div>
                </div>
            <?php else: ?>
                <div class="alert alert-success" style="border-radius:14px;">
                    <div class="fw-bold">Pagamento confirmado</div>
                    <div class="small">Obrigado! Sua recarga foi creditada na carteira.</div>
                </div>
            <?php endif; ?>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="text-muted small">Recarga</div>
                            <div class="fw-bold">#<?= (int) $rid ?></div>

                            <div class="text-muted small mt-3">Valor (USD)</div>
                            <div class="h5 mb-0"><?= htmlspecialchars($fmtUsd($valorUsd)) ?></div>

                            <div class="text-muted small mt-3">Conversão</div>
                            <div class="small text-muted">Taxa: <?= $rate ? htmlspecialchars('1 USD = R$ ' . number_format((float) $rate, 4, ',', '.')) : '-' ?></div>
                            <div class="small text-muted">Valor em BRL (Stripe): <?= $valorBrl !== null ? htmlspecialchars($fmtBrl($valorBrl)) : '-' ?></div>
                        </div>

                        <div class="col-md-6">
                            <div class="text-muted small">Pagador</div>
                            <div class="fw-bold"><?= htmlspecialchars((string) ($rec['pagador_nome'] ?? '')) ?></div>
                            <div class="text-muted"><?= htmlspecialchars((string) ($rec['pagador_email'] ?? '')) ?></div>
                            <div class="text-muted"><?= htmlspecialchars($docMasked) ?></div>

                            <div class="text-muted small mt-3">Pagamento</div>
                            <div class="small text-muted">Método: <?= htmlspecialchars(strtoupper($metodo !== '' ? $metodo : 'stripe')) ?></div>
                            <div class="small text-muted">Gateway: <?= htmlspecialchars($gateway !== '' ? $gateway : 'stripe') ?></div>
                            <div class="small text-muted" style="word-break: break-all;">Payment ID: <?= htmlspecialchars($paymentId) ?></div>

                            <div class="text-muted small mt-3">Datas</div>
                            <div class="small text-muted">Criado em: <?= htmlspecialchars($createdAt !== '' ? $createdAt : '-') ?></div>
                            <div class="small text-muted">Pago em: <?= htmlspecialchars($paidAt !== '' ? $paidAt : '-') ?></div>
                        </div>
                    </div>

                    <hr class="my-4" />

                    <div class="d-flex flex-wrap gap-2">
                        <a class="btn btn-primary" href="/clube/recarga">Nova recarga</a>
                        <a class="btn btn-outline-secondary" href="/minha-conta">Minha conta</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function(){
    const recargaId = <?= json_encode((int) $rid) ?>;
    const token = <?= json_encode((string) $token) ?>;
    const isPaid = <?= json_encode((bool) $isPaid) ?>;

    async function poll(){
        try{
            const r = await fetch('/clube/recarga/status?recarga_id=' + encodeURIComponent(recargaId) + '&token=' + encodeURIComponent(token));
            const data = await r.json();
            if(data && data.success && data.is_paid){
                // reload to show paid status
                window.location.reload();
                return;
            }
        }catch(e){}
    }

    document.getElementById('btnPrint')?.addEventListener('click', function(){
        window.print();
    });

    document.getElementById('btnShare')?.addEventListener('click', async function(){
        const text = 'Comprovante Recarga Clube #' + recargaId;
        const url = window.location.href;
        try{
            if(navigator.share){
                await navigator.share({ title: text, text, url });
                return;
            }
        }catch(e){}

        try{
            if(navigator.clipboard){
                await navigator.clipboard.writeText(url);
                alert('Link copiado para a área de transferência.');
                return;
            }
        }catch(e){}

        prompt('Copie o link do comprovante:', url);
    });

    if(!isPaid){
        setInterval(poll, 5000);
    }
})();
</script>

<?php $content = ob_get_clean(); ?>
<?php $title = 'Comprovante da Recarga'; ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
