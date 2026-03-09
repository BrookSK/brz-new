<?php
$rec = isset($recarga) && is_array($recarga) ? $recarga : [];
$isPaid = !empty($is_paid);

$siteLogo = '';
try {
    $raw = '';
    $tablesToTry = ['configuracoes_sistema', 'configuracoes', 'settings', 'config'];
    foreach ($tablesToTry as $t) {
        if ($raw !== '') break;
        try {
            $pdo = \Config\Database::getConnection();
            $stmtT = $pdo->prepare('SHOW TABLES LIKE ?');
            $stmtT->execute([$t]);
            if (!$stmtT->fetchColumn()) {
                continue;
            }
            $stmtCols = $pdo->query('DESCRIBE ' . $t);
            $cols = $stmtCols ? ($stmtCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            if (!is_array($cols)) {
                $cols = [];
            }
            if (in_array('categoria', $cols, true) && in_array('chave', $cols, true)) {
                $valCol = in_array('valor', $cols, true) ? 'valor' : (in_array('value', $cols, true) ? 'value' : '');
                if ($valCol !== '') {
                    $stmt = $pdo->prepare('SELECT ' . $valCol . ' FROM ' . $t . ' WHERE categoria = ? AND chave = ? LIMIT 1');
                    $stmt->execute(['layout', 'logo']);
                    $raw = (string) ($stmt->fetchColumn() ?: '');
                    if ($raw !== '') break;
                }
            }
            $keyCol = '';
            if (in_array('chave', $cols, true)) $keyCol = 'chave';
            elseif (in_array('key', $cols, true)) $keyCol = 'key';
            elseif (in_array('nome', $cols, true)) $keyCol = 'nome';
            elseif (in_array('config_key', $cols, true)) $keyCol = 'config_key';
            $valCol = '';
            if (in_array('valor', $cols, true)) $valCol = 'valor';
            elseif (in_array('value', $cols, true)) $valCol = 'value';
            elseif (in_array('conteudo', $cols, true)) $valCol = 'conteudo';
            if ($keyCol !== '' && $valCol !== '') {
                $stmt = $pdo->prepare('SELECT ' . $valCol . ' FROM ' . $t . ' WHERE ' . $keyCol . ' = ? LIMIT 1');
                $stmt->execute(['layout_logo']);
                $raw = (string) ($stmt->fetchColumn() ?: '');
                if ($raw !== '') break;
            }
            if (in_array('layout_logo', $cols, true)) {
                $idCol = in_array('id', $cols, true) ? 'id' : (in_array('ID', $cols, true) ? 'ID' : 'id');
                $stmt2 = $pdo->query('SELECT layout_logo AS valor FROM ' . $t . ' ORDER BY ' . $idCol . ' ASC LIMIT 1');
                $raw = (string) ($stmt2 ? ($stmt2->fetchColumn() ?: '') : '');
                if ($raw !== '') break;
            }
        } catch (\Exception $e) {
        }
    }
    $siteLogo = is_string($raw) ? trim($raw) : '';
} catch (\Exception $e) {
    $siteLogo = '';
}

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

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprovante da Recarga</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f6f8fb; }
    </style>
</head>
<body>

<style>
@media print {
    body * {
        visibility: hidden !important;
    }
    #printArea, #printArea * {
        visibility: visible !important;
    }
    #printArea {
        position: fixed;
        left: 0;
        top: 0;
        width: 100%;
        padding: 0;
        margin: 0;
        background: #fff;
    }
    .no-print {
        display: none !important;
    }
    .print-only {
        display: block !important;
    }
}

.print-only {
    display: none;
}
</style>

<div class="container" style="padding: 22px 0 0;">
    <div class="text-center mb-3 no-print">
        <?php if (!empty($siteLogo)): ?>
            <img src="<?= htmlspecialchars($siteLogo, ENT_QUOTES, 'UTF-8') ?>" alt="Braziliana" style="max-height: 52px; max-width: 100%; object-fit: contain;">
        <?php else: ?>
            <div style="font-weight:800; color:#0b1f3a; font-size: 20px;">Braziliana</div>
        <?php endif; ?>
    </div>
</div>

<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">
            <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-3 no-print">
                <div>
                    <h1 class="h4 mb-1" style="color:#0b1f3a; font-weight: 800;">Comprovante da Recarga</h1>
                    <div class="text-muted">Clube Brasiliana</div>
                </div>
                <div class="d-flex gap-2">
                    <button type="button" class="btn btn-outline-primary" id="btnPrint">Imprimir</button>
                    <button type="button" class="btn btn-outline-secondary" id="btnShare">Compartilhar</button>
                </div>
            </div>

            <div id="printArea">
                <div class="text-center mb-3 print-only" style="padding-top: 12px;">
                    <?php if (!empty($siteLogo)): ?>
                        <img src="<?= htmlspecialchars($siteLogo, ENT_QUOTES, 'UTF-8') ?>" alt="Braziliana" style="max-height: 42px; max-width: 100%; object-fit: contain;">
                    <?php else: ?>
                        <div style="font-weight:800; color:#0b1f3a; font-size: 18px;">Braziliana</div>
                    <?php endif; ?>
                </div>

                <?php if (!$isPaid): ?>
                    <div class="alert alert-warning no-print" style="border-radius:14px;">
                        <div class="fw-bold">Pagamento ainda não confirmado</div>
                        <div class="small">Status atual: <strong><?= htmlspecialchars($status) ?></strong>. Esta página será atualizada automaticamente.</div>
                    </div>
                <?php else: ?>
                    <div class="alert alert-success no-print" style="border-radius:14px;">
                        <div class="fw-bold">Pagamento confirmado</div>
                        <div class="small">Obrigado!<br><br>Sua recarga foi creditada na carteira  e já faz parte do nosso programa de bonificações<br><br>Em breve você poderá acompanhar os seu crédito e bonificações na sua página de informações da conta.</div>
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

                        <hr class="my-4 no-print" />

                        <div class="d-flex flex-wrap gap-2 no-print">
                            <a class="btn btn-primary" href="/clube/recarga">Nova recarga</a>
                        </div>
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

</body>
</html>
