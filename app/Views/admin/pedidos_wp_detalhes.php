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
    if ($currency === 'BRL') return __('admin.orders.js.currency_brl', 'R$') . ' ' . $fmt;
    if ($currency === 'USD') return __('admin.orders.js.currency_usd', 'US$') . ' ' . $fmt;
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
$shipNeighborhood = wpVal($meta, '_shipping_neighborhood');
if ($shipNeighborhood === '') $shipNeighborhood = wpVal($meta, '_shipping_bairro');
if ($shipNeighborhood === '') $shipNeighborhood = wpVal($meta, 'shipping_bairro');
$shipSuite = wpVal($meta, 'suite');
if ($shipSuite === '') $shipSuite = wpVal($meta, '_shipping_suite');
if ($shipSuite === '') $shipSuite = wpVal($meta, 'shipping_suite');

$tracking = wpVal($meta, '_tracking_code');
if ($tracking === '') $tracking = wpVal($meta, 'tracking_code');

$paymentMethod = wpVal($meta, '_payment_method');
$transactionId = wpVal($meta, '_transaction_id');

?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h1 class="h4 mb-0">#<?= (int) (($pedido['ID'] ?? $pedido['id'] ?? 0) ?: 0) ?></h1>
        <div class="text-muted small"><?= htmlspecialchars((string) (($pedido['post_title'] ?? $pedido['numero_pedido'] ?? '') ?: '')) ?></div>
    </div>
    <div class="d-flex gap-2">
        <a href="/admin/pedidos-wp" class="btn btn-outline-secondary"><?= __('common.back', 'Voltar') ?></a>
        <button type="button" class="btn btn-primary" onclick="gerarEtiquetaWexpressWp(<?= (int) (($pedido['ID'] ?? $pedido['id'] ?? 0) ?: 0) ?>)"><?= __('admin.orders_wp.details.generate_wexpress_label', 'Gerar etiqueta W-Express') ?></button>
    </div>
</div>

<?php if ($erro !== ''): ?>
    <div class="alert alert-danger"><?= __('admin.orders_wp.details.error_load_details_prefix', 'Erro ao carregar detalhes do pedido:') ?> <?= htmlspecialchars($erro) ?></div>
<?php endif; ?>

<script>
window.ADMIN_ORDERS_WP_DETAILS_I18N = {
    invalid_order: <?= json_encode(__('admin.orders_wp.details.js.invalid_order', 'Pedido inválido'), JSON_UNESCAPED_UNICODE) ?>,
    confirm_generate_label: <?= json_encode(__('admin.orders_wp.details.js.confirm_generate_label', 'Deseja gerar a etiqueta da W-Express para este pedido?'), JSON_UNESCAPED_UNICODE) ?>,
    error_generate_label: <?= json_encode(__('admin.orders_wp.details.js.error_generate_label', 'Erro ao gerar etiqueta'), JSON_UNESCAPED_UNICODE) ?>,
    label_generated_success: <?= json_encode(__('admin.orders_wp.details.js.label_generated_success', 'Etiqueta gerada com sucesso!'), JSON_UNESCAPED_UNICODE) ?>,
    error_prefix: <?= json_encode(__('admin.orders_wp.details.js.error_prefix', 'Erro:'), JSON_UNESCAPED_UNICODE) ?>
};

function gerarEtiquetaWexpressWp(orderId) {
    if (!orderId) {
        alert((window.ADMIN_ORDERS_WP_DETAILS_I18N && window.ADMIN_ORDERS_WP_DETAILS_I18N.invalid_order) ? window.ADMIN_ORDERS_WP_DETAILS_I18N.invalid_order : 'Pedido inválido');
        return;
    }
    if (!confirm((window.ADMIN_ORDERS_WP_DETAILS_I18N && window.ADMIN_ORDERS_WP_DETAILS_I18N.confirm_generate_label) ? window.ADMIN_ORDERS_WP_DETAILS_I18N.confirm_generate_label : 'Deseja gerar a etiqueta da W-Express para este pedido?')) return;

    fetch('/admin/pedidos-wp/wexpress/gerar/' + orderId, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({})
    })
    .then(async (r) => {
        const data = await r.json().catch(() => ({}));
        if (!r.ok || !data || !data.success) {
            throw new Error((data && (data.error || data.message)) ? (data.error || data.message) : ((window.ADMIN_ORDERS_WP_DETAILS_I18N && window.ADMIN_ORDERS_WP_DETAILS_I18N.error_generate_label) ? window.ADMIN_ORDERS_WP_DETAILS_I18N.error_generate_label : 'Erro ao gerar etiqueta'));
        }
        return data;
    })
    .then((data) => {
        const labelUrl = data.label_url || '';
        alert((window.ADMIN_ORDERS_WP_DETAILS_I18N && window.ADMIN_ORDERS_WP_DETAILS_I18N.label_generated_success) ? window.ADMIN_ORDERS_WP_DETAILS_I18N.label_generated_success : 'Etiqueta gerada com sucesso!');
        if (labelUrl) {
            window.open(labelUrl, '_blank');
        } else {
            location.reload();
        }
    })
    .catch((e) => {
        const errPrefix = (window.ADMIN_ORDERS_WP_DETAILS_I18N && window.ADMIN_ORDERS_WP_DETAILS_I18N.error_prefix) ? window.ADMIN_ORDERS_WP_DETAILS_I18N.error_prefix : 'Erro:';
        alert(errPrefix + ' ' + (e && e.message ? e.message : String(e)));
    });
}
</script>

<?php if (!$pedido): ?>
    <div class="alert alert-warning"><?= __('admin.orders_wp.details.order_not_found', 'Pedido não encontrado.') ?></div>
<?php else: ?>

<div class="row g-3">
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><strong><?= __('admin.orders_wp.details.customer', 'Cliente') ?></strong></div>
            <div class="card-body">
                <div><strong><?= __('common.name', 'Nome') ?>:</strong> <?= htmlspecialchars($billingName ?: '-') ?></div>
                <div><strong><?= __('common.email', 'E-mail') ?>:</strong> <?= htmlspecialchars($billingEmail ?: '-') ?></div>
                <?php if ($billingCpf !== ''): ?><div><strong><?= __('checkout.cpf_cnpj', 'CPF/CNPJ') ?>:</strong> <?= htmlspecialchars($billingCpf) ?></div><?php endif; ?>
                <?php if ($billingPhone !== ''): ?><div><strong><?= __('common.phone', 'Telefone') ?>:</strong> <?= htmlspecialchars($billingPhone) ?></div><?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-6">
        <div class="card">
            <div class="card-header"><strong><?= __('admin.orders_wp.details.order', 'Pedido') ?></strong></div>
            <div class="card-body">
                <div><strong><?= __('common.status', 'Status') ?>:</strong> <?= htmlspecialchars((string) ($pedido['post_status'] ?? $pedido['status'] ?? '')) ?></div>
                <div><strong><?= __('common.date', 'Data') ?>:</strong> <?= htmlspecialchars(date('d/m/Y H:i', strtotime((string) ($pedido['post_date'] ?? $pedido['created_at'] ?? 'now')))) ?></div>
                <div><strong><?= __('common.total', 'Total') ?>:</strong> <?= htmlspecialchars(wpFormatMoney2($total, $currency)) ?></div>
                <div><strong><?= __('admin.orders_wp.details.currency', 'Moeda') ?>:</strong> <?= htmlspecialchars($currency ?: '-') ?></div>
            </div>
        </div>
    </div>

    <div class="col-lg-12">
        <div class="card">
            <div class="card-header"><strong><?= __('admin.orders_wp.details.delivery', 'Entrega') ?></strong></div>
            <div class="card-body">
                <div><strong><?= __('admin.orders_wp.details.address', 'Endereço') ?> 1:</strong> <?= htmlspecialchars($shipAddress1 ?: '-') ?></div>
                <div><strong><?= __('admin.orders_wp.details.address', 'Endereço') ?> 2:</strong> <?= htmlspecialchars($shipAddress2 ?: '-') ?></div>
                <?php if ($shipNeighborhood !== ''): ?><div><strong><?= __('checkout.neighborhood', 'Bairro') ?>:</strong> <?= htmlspecialchars($shipNeighborhood) ?></div><?php endif; ?>
                <div><strong><?= __('admin.orders_wp.details.city_state', 'Cidade/Estado') ?>:</strong> <?= htmlspecialchars(trim($shipCity . ' / ' . $shipState) ?: '-') ?></div>
                <div><strong><?= __('checkout.zip_code', 'CEP') ?>:</strong> <?= htmlspecialchars($shipPostcode ?: '-') ?></div>
                <div><strong><?= __('admin.orders_wp.details.country', 'País') ?>:</strong> <?= htmlspecialchars($shipCountry ?: '-') ?></div>
                <?php if ($shipSuite !== ''): ?><div><strong><?= __('admin.orders_wp.details.suite', 'Suite') ?>:</strong> <?= htmlspecialchars($shipSuite) ?></div><?php endif; ?>
                <?php if ($tracking !== ''): ?><div><strong><?= __('admin.orders_wp.details.tracking', 'Rastreio') ?>:</strong> <?= htmlspecialchars($tracking) ?></div><?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-12">
        <div class="card">
            <div class="card-header"><strong><?= __('admin.orders_wp.details.payment', 'Pagamento') ?></strong></div>
            <div class="card-body">
                <div><strong><?= __('admin.orders_wp.details.payment_method', 'Método') ?>:</strong> <?= htmlspecialchars($paymentMethod ?: '-') ?></div>
                <?php if ($transactionId !== ''): ?><div><strong><?= __('admin.orders_wp.details.transaction_id', 'Transaction ID') ?>:</strong> <?= htmlspecialchars($transactionId) ?></div><?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card">
            <div class="card-header"><strong><?= __('admin.orders_wp.details.items', 'Itens') ?></strong></div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover align-middle">
                        <thead>
                            <tr>
                                <th><?= __('admin.orders_wp.details.table.product', 'Produto') ?></th>
                                <th><?= __('admin.orders_wp.details.table.sku', 'SKU') ?></th>
                                <th><?= __('admin.orders_wp.details.table.ncm', 'NCM') ?></th>
                                <th><?= __('admin.orders_wp.details.table.qty', 'Qtd') ?></th>
                                <th><?= __('admin.orders_wp.details.table.unit_price', 'Unitário') ?></th>
                                <th><?= __('checkout.subtotal', 'Subtotal') ?></th>
                                <th><?= __('common.total', 'Total') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($itens)): ?>
                                <tr><td colspan="7" class="text-center text-muted"><?= __('admin.orders_wp.details.items_empty', 'Sem itens encontrados.') ?></td></tr>
                            <?php else: ?>
                                <?php foreach ($itens as $it): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?= htmlspecialchars((string) ($it['nome'] ?? '')) ?></div>
                                            <div class="text-muted small"><?= __('admin.orders_wp.details.product_id', 'Produto ID') ?>: <?= (int) ($it['produto_id'] ?? 0) ?> | <?= __('admin.orders_wp.details.variation_id', 'Variação ID') ?>: <?= (int) ($it['variacao_id'] ?? 0) ?></div>
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
