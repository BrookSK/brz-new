<?php
$pedido = is_array($pedido ?? null) ? $pedido : null;
$meta = is_array($meta ?? null) ? $meta : [];
$itens = is_array($itens ?? null) ? $itens : [];
$erro = (string) ($erro ?? '');

function wpVal($meta, $key) {
    return isset($meta[$key]) ? (string) $meta[$key] : '';
}

function wpFormatMoney2($v, $currency) {
    $currency = strtoupper(trim((string) $currency));
    if ($currency === '') $currency = 'BRL';
    $v = is_numeric($v) ? (float) $v : 0.0;
    $fmt = number_format($v, 2, ',', '.');
    if ($currency === 'BRL') return 'R$ ' . $fmt;
    if ($currency === 'USD') return 'US$ ' . $fmt;
    return $currency . ' ' . $fmt;
}

$currency = wpVal($meta, '_order_currency');
$total = wpVal($meta, '_order_total');

$billingName = trim(wpVal($meta, '_billing_first_name') . ' ' . wpVal($meta, '_billing_last_name'));
$billingEmail = wpVal($meta, '_billing_email');
$billingCpf = wpVal($meta, '_billing_cpf');
$billingPhone = wpVal($meta, '_billing_phone');

$shipAddress1 = wpVal($meta, '_shipping_address_1');
$shipAddress2 = wpVal($meta, '_shipping_address_2');
$shipCity = wpVal($meta, '_shipping_city');
$shipState = wpVal($meta, '_shipping_state');
$shipPostcode = wpVal($meta, '_shipping_postcode');
$shipCountry = wpVal($meta, '_shipping_country');
$shipSuite = wpVal($meta, 'suite');
if ($shipSuite === '') $shipSuite = wpVal($meta, '_shipping_suite');
if ($shipSuite === '') $shipSuite = wpVal($meta, 'shipping_suite');

$tracking = wpVal($meta, '_tracking_code');
if ($tracking === '') $tracking = wpVal($meta, 'tracking_code');

$paymentMethod = wpVal($meta, '_payment_method');
$transactionId = wpVal($meta, '_transaction_id');

$source = strtolower(trim((string) ($source ?? ($_GET['source'] ?? 'br'))));
if (!in_array($source, ['br','red','us'], true)) $source = 'br';

?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <div>
        <h1 class="h2 mb-0">Pedido WP #<?= htmlspecialchars((string) (($pedido['ID'] ?? $pedido['id'] ?? '') ?: '')) ?></h1>
        <div class="text-muted small"><?= htmlspecialchars((string) (($pedido['post_title'] ?? $pedido['numero_pedido'] ?? '') ?: '')) ?></div>
    </div>
    <div class="d-flex gap-2">
        <a href="/admin/pedidos-wp?<?= http_build_query(['source' => $source]) ?>" class="btn btn-outline-secondary">Voltar</a>
        <button type="button" class="btn btn-primary" onclick="gerarEtiquetaWexpressWp(<?= (int) (($pedido['ID'] ?? $pedido['id'] ?? 0) ?: 0) ?>, '<?= htmlspecialchars($source, ENT_QUOTES, 'UTF-8') ?>')">Gerar etiqueta W-Express</button>
    </div>
</div>

<?php if ($erro !== ''): ?>
    <div class="alert alert-danger">Erro ao carregar detalhes do pedido: <?= htmlspecialchars($erro) ?></div>
<?php endif; ?>

<script>
function gerarEtiquetaWexpressWp(orderId, source) {
    if (!orderId) {
        alert('Pedido inválido');
        return;
    }
    source = (source || 'br').toString().toLowerCase();
    if (!confirm('Deseja gerar a etiqueta da W-Express para este pedido?')) return;

    fetch('/admin/pedidos-wp/wexpress/gerar/' + orderId + '?source=' + encodeURIComponent(source), {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({})
    })
    .then(async (r) => {
        const data = await r.json().catch(() => ({}));
        if (!r.ok || !data || !data.success) {
            throw new Error((data && (data.error || data.message)) ? (data.error || data.message) : 'Erro ao gerar etiqueta');
        }
        return data;
    })
    .then((data) => {
        const labelUrl = data.label_url || '';
        alert('Etiqueta gerada com sucesso!');
        if (labelUrl) {
            window.open(labelUrl, '_blank');
        } else {
            location.reload();
        }
    })
    .catch((e) => {
        alert('Erro: ' + (e && e.message ? e.message : String(e)));
    });
}
</script>

<?php if (!$pedido): ?>
    <div class="alert alert-warning">Pedido não encontrado.</div>
<?php else: ?>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><strong>Cliente</strong></div>
            <div class="card-body">
                <div><strong>Nome:</strong> <?= htmlspecialchars($billingName ?: '-') ?></div>
                <div><strong>Email:</strong> <?= htmlspecialchars($billingEmail ?: '-') ?></div>
                <?php if ($billingCpf !== ''): ?><div><strong>CPF:</strong> <?= htmlspecialchars($billingCpf) ?></div><?php endif; ?>
                <?php if ($billingPhone !== ''): ?><div><strong>Telefone:</strong> <?= htmlspecialchars($billingPhone) ?></div><?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><strong>Pedido</strong></div>
            <div class="card-body">
                <div><strong>Status:</strong> <?= htmlspecialchars((string) ($pedido['post_status'] ?? $pedido['status'] ?? '')) ?></div>
                <div><strong>Data:</strong> <?= htmlspecialchars(date('d/m/Y H:i', strtotime((string) ($pedido['post_date'] ?? $pedido['created_at'] ?? 'now')))) ?></div>
                <div><strong>Total:</strong> <?= htmlspecialchars(wpFormatMoney2($total, $currency)) ?></div>
                <div><strong>Moeda:</strong> <?= htmlspecialchars($currency ?: '-') ?></div>
            </div>
        </div>
    </div>

    <div class="col-lg-12">
        <div class="card">
            <div class="card-header"><strong>Entrega</strong></div>
            <div class="card-body">
                <div><strong>Endereço:</strong> <?= htmlspecialchars(trim($shipAddress1 . ' ' . $shipAddress2) ?: '-') ?></div>
                <div><strong>Cidade/Estado:</strong> <?= htmlspecialchars(trim($shipCity . ' / ' . $shipState) ?: '-') ?></div>
                <div><strong>CEP:</strong> <?= htmlspecialchars($shipPostcode ?: '-') ?></div>
                <div><strong>País:</strong> <?= htmlspecialchars($shipCountry ?: '-') ?></div>
                <?php if ($shipSuite !== ''): ?><div><strong>Suite:</strong> <?= htmlspecialchars($shipSuite) ?></div><?php endif; ?>
                <?php if ($tracking !== ''): ?><div><strong>Rastreio:</strong> <?= htmlspecialchars($tracking) ?></div><?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-12">
        <div class="card">
            <div class="card-header"><strong>Pagamento</strong></div>
            <div class="card-body">
                <div><strong>Método:</strong> <?= htmlspecialchars($paymentMethod ?: '-') ?></div>
                <?php if ($transactionId !== ''): ?><div><strong>Transaction ID:</strong> <?= htmlspecialchars($transactionId) ?></div><?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-header"><strong>Itens</strong></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th>Produto</th>
                                <th>SKU</th>
                                <th>NCM</th>
                                <th>Qtd</th>
                                <th>Unitário</th>
                                <th>Subtotal</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($itens)): ?>
                                <tr><td colspan="7" class="text-center text-muted">Sem itens encontrados.</td></tr>
                            <?php else: ?>
                                <?php foreach ($itens as $it): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?= htmlspecialchars((string) ($it['nome'] ?? '')) ?></div>
                                            <div class="text-muted small">Produto ID: <?= (int) ($it['produto_id'] ?? 0) ?> | Variação ID: <?= (int) ($it['variacao_id'] ?? 0) ?></div>
                                        </td>
                                        <td><?= htmlspecialchars((string) ($it['sku'] ?? '')) ?></td>
                                        <td><?= htmlspecialchars((string) ($it['ncm'] ?? '')) ?></td>
                                        <td><?= (int) ($it['quantidade'] ?? 0) ?></td>
                                        <td><?= htmlspecialchars(wpFormatMoney2((float) ($it['preco_unitario'] ?? 0), $currency)) ?></td>
                                        <td><?= htmlspecialchars(wpFormatMoney2((float) ($it['subtotal'] ?? 0), $currency)) ?></td>
                                        <td><?= htmlspecialchars(wpFormatMoney2((float) ($it['total'] ?? 0), $currency)) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php endif; ?>
