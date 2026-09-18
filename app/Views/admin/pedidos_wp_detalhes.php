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
$shipNumber = wpVal($meta, '_shipping_number');
if ($shipNumber === '') $shipNumber = wpVal($meta, 'shipping_number');
if ($shipNumber === '') $shipNumber = wpVal($meta, '_shipping_numero');
if ($shipNumber === '') $shipNumber = wpVal($meta, 'shipping_numero');
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

$pesoTotalItensKg = is_numeric($pesoTotalItensKg ?? null) ? (float) $pesoTotalItensKg : 0.0;
$declaracaoTotal = is_numeric($declaracaoTotal ?? null) ? (float) $declaracaoTotal : 0.0;
$qtdTotalItens = is_numeric($qtdTotalItens ?? null) ? (int) $qtdTotalItens : 0;

$tracking = wpVal($meta, '_tracking_code');
if ($tracking === '') $tracking = wpVal($meta, 'tracking_code');

$paymentMethod = wpVal($meta, '_payment_method');
$transactionId = wpVal($meta, '_transaction_id');

$wexpressLabelUrl = wpVal($meta, 'wexpress_label_url');
if ($wexpressLabelUrl === '') $wexpressLabelUrl = wpVal($meta, '_wexpress_label_url');
if ($wexpressLabelUrl === '') $wexpressLabelUrl = wpVal($meta, 'wp_wexpress_label_url');

$source = strtolower(trim((string) ($source ?? ($_GET['source'] ?? 'br'))));
if (!in_array($source, ['br','red','us'], true)) $source = 'br';

?>

<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
        <h1 class="h4 mb-0">#<?= (int) (($pedido['ID'] ?? $pedido['id'] ?? 0) ?: 0) ?></h1>
        <div class="text-muted small"><?= htmlspecialchars((string) (($pedido['post_title'] ?? $pedido['numero_pedido'] ?? '') ?: '')) ?></div>
    </div>
    <div class="d-flex gap-2">
        <a href="/admin/pedidos-wp?<?= http_build_query(['source' => $source]) ?>" class="btn btn-outline-secondary"><?= __('common.back', 'Voltar') ?></a>
        <?php if ($wexpressLabelUrl !== ''): ?>
            <a class="btn btn-success" href="<?= htmlspecialchars($wexpressLabelUrl) ?>" target="_blank" rel="noopener">
                <?= __('admin.orders_wp.details.download_wexpress_label', 'Baixar etiqueta W-Express') ?>
            </a>
            <button type="button" class="btn btn-outline-danger" id="btnRegerarEtiquetaWexpress" onclick="regerarEtiquetaWexpressWp(<?= (int) (($pedido['ID'] ?? $pedido['id'] ?? 0) ?: 0) ?>)">
                <span class="spinner-border spinner-border-sm me-2 d-none" role="status" aria-hidden="true" id="wxReSpinner"></span>
                <span id="wxReBtnText"><?= __('admin.orders_wp.details.regenerate_label', 'Regerar etiqueta') ?></span>
            </button>
        <?php else: ?>
            <button type="button" class="btn btn-primary" id="btnGerarEtiquetaWexpress" onclick="gerarEtiquetaWexpressWp(<?= (int) (($pedido['ID'] ?? $pedido['id'] ?? 0) ?: 0) ?>)">
                <span class="spinner-border spinner-border-sm me-2 d-none" role="status" aria-hidden="true" id="wxSpinner"></span>
                <span id="wxBtnText"><?= __('admin.orders_wp.details.generate_wexpress_label', 'Gerar etiqueta W-Express') ?></span>
            </button>
        <?php endif; ?>
    </div>
</div>

<?php if ($erro !== ''): ?>
    <div class="alert alert-danger"><?= __('admin.orders_wp.details.error_load_details_prefix', 'Erro ao carregar detalhes do pedido:') ?> <?= htmlspecialchars($erro) ?></div>
<?php endif; ?>

<div class="card mb-3">
    <div class="card-header"><strong><?= __('admin.orders_wp.details.general_items', 'Itens gerais do pedido') ?></strong></div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <div class="text-muted small"><?= __('admin.orders_wp.details.total_items_weight', 'Peso total dos itens (kg)') ?></div>
                <div class="fw-semibold"><?= $pesoTotalItensKg > 0 ? htmlspecialchars(number_format((float) $pesoTotalItensKg, 3, ',', '.')) : '-' ?></div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small"><?= __('admin.orders_wp.details.total_declaration', 'Declaração total') ?></div>
                <div class="fw-semibold"><?= $declaracaoTotal > 0 ? htmlspecialchars(wpFormatMoney2((float) $declaracaoTotal, 'USD')) : '-' ?></div>
            </div>
            <div class="col-md-4">
                <div class="text-muted small"><?= __('admin.orders_wp.details.total_qty_items', 'Quantidade total de itens') ?></div>
                <div class="fw-semibold"><?= (int) $qtdTotalItens ?></div>
            </div>
        </div>
    </div>
</div>

<script>
window.ADMIN_ORDERS_WP_DETAILS_I18N = {
    invalid_order: <?= json_encode(__('admin.orders_wp.details.js.invalid_order', 'Pedido inválido'), JSON_UNESCAPED_UNICODE) ?>,
    confirm_generate_label: <?= json_encode(__('admin.orders_wp.details.js.confirm_generate_label', 'Deseja gerar a etiqueta da W-Express para este pedido?'), JSON_UNESCAPED_UNICODE) ?>,
    error_generate_label: <?= json_encode(__('admin.orders_wp.details.js.error_generate_label', 'Erro ao gerar etiqueta'), JSON_UNESCAPED_UNICODE) ?>,
    label_generated_success: <?= json_encode(__('admin.orders_wp.details.js.label_generated_success', 'Etiqueta gerada com sucesso!'), JSON_UNESCAPED_UNICODE) ?>,
    error_prefix: <?= json_encode(__('admin.orders_wp.details.js.error_prefix', 'Erro:'), JSON_UNESCAPED_UNICODE) ?>
};

window.ADMIN_ORDERS_WP_DETAILS_SOURCE = <?= json_encode($source, JSON_UNESCAPED_UNICODE) ?>;
window.ADMIN_ORDERS_WP_DETAILS_DEFAULT_BTN_TEXT = <?= json_encode(__('admin.orders_wp.details.generate_wexpress_label', 'Gerar etiqueta W-Express'), JSON_UNESCAPED_UNICODE) ?>;
window.ADMIN_ORDERS_WP_DETAILS_LOADING_BTN_TEXT = <?= json_encode(__('admin.orders_wp.details.generating_wexpress_label', 'Gerando...'), JSON_UNESCAPED_UNICODE) ?>;
window.ADMIN_ORDERS_WP_DETAILS_RE_DEFAULT_BTN_TEXT = <?= json_encode(__('admin.orders_wp.details.regenerate_label', 'Regerar etiqueta'), JSON_UNESCAPED_UNICODE) ?>;
window.ADMIN_ORDERS_WP_DETAILS_RE_LOADING_BTN_TEXT = <?= json_encode(__('admin.orders_wp.details.js.regenerating', 'Regerando...'), JSON_UNESCAPED_UNICODE) ?>;

function gerarEtiquetaWexpressWp(orderId, source) {
    if (!orderId) {
        alert((window.ADMIN_ORDERS_WP_DETAILS_I18N && window.ADMIN_ORDERS_WP_DETAILS_I18N.invalid_order) ? window.ADMIN_ORDERS_WP_DETAILS_I18N.invalid_order : 'Pedido inválido');
        return;
    }
    source = (source || window.ADMIN_ORDERS_WP_DETAILS_SOURCE || 'br').toString().toLowerCase();
    if (!confirm((window.ADMIN_ORDERS_WP_DETAILS_I18N && window.ADMIN_ORDERS_WP_DETAILS_I18N.confirm_generate_label) ? window.ADMIN_ORDERS_WP_DETAILS_I18N.confirm_generate_label : 'Deseja gerar a etiqueta da W-Express para este pedido?')) return;

    var btn = document.getElementById('btnGerarEtiquetaWexpress');
    var sp = document.getElementById('wxSpinner');
    var tx = document.getElementById('wxBtnText');
    if (btn) btn.disabled = true;
    if (sp) sp.classList.remove('d-none');
    if (tx) tx.textContent = (window.ADMIN_ORDERS_WP_DETAILS_LOADING_BTN_TEXT || 'Gerando...');

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
            throw new Error((data && (data.error || data.message)) ? (data.error || data.message) : ((window.ADMIN_ORDERS_WP_DETAILS_I18N && window.ADMIN_ORDERS_WP_DETAILS_I18N.error_generate_label) ? window.ADMIN_ORDERS_WP_DETAILS_I18N.error_generate_label : 'Erro ao gerar etiqueta'));
        }
        return data;
    })
    .then((data) => {
        const labelUrl = data.label_url || '';
        alert((window.ADMIN_ORDERS_WP_DETAILS_I18N && window.ADMIN_ORDERS_WP_DETAILS_I18N.label_generated_success) ? window.ADMIN_ORDERS_WP_DETAILS_I18N.label_generated_success : 'Etiqueta gerada com sucesso!');
        if (labelUrl) {
            const w = window.open(labelUrl, '_blank');
            if (!w) {
                // Popup bloqueado: não navega para fora do admin. Mostra a URL para abrir manualmente.
                var msg = '<?= htmlspecialchars(__('admin.orders_wp.details.js.popup_blocked', 'Popup bloqueado. Copie e abra a etiqueta em uma nova aba:'), ENT_QUOTES, 'UTF-8') ?>\n' + labelUrl;
                try {
                    window.prompt(msg, labelUrl);
                } catch (e) {
                    alert(msg);
                }
            }
            // Atualiza a tela para refletir a etiqueta salva no WooCommerce
            setTimeout(() => location.reload(), 800);
        } else {
            location.reload();
        }
    })
    .catch((e) => {
        const errPrefix = (window.ADMIN_ORDERS_WP_DETAILS_I18N && window.ADMIN_ORDERS_WP_DETAILS_I18N.error_prefix) ? window.ADMIN_ORDERS_WP_DETAILS_I18N.error_prefix : 'Erro:';
        alert(errPrefix + ' ' + (e && e.message ? e.message : String(e)));
    })
    .finally(() => {
        if (btn) btn.disabled = false;
        if (sp) sp.classList.add('d-none');
        if (tx) tx.textContent = (window.ADMIN_ORDERS_WP_DETAILS_DEFAULT_BTN_TEXT || 'Gerar etiqueta W-Express');
    });
}

function regerarEtiquetaWexpressWp(orderId, source) {
    if (!orderId) {
        alert((window.ADMIN_ORDERS_WP_DETAILS_I18N && window.ADMIN_ORDERS_WP_DETAILS_I18N.invalid_order) ? window.ADMIN_ORDERS_WP_DETAILS_I18N.invalid_order : 'Pedido inválido');
        return;
    }
    source = (source || window.ADMIN_ORDERS_WP_DETAILS_SOURCE || 'br').toString().toLowerCase();
    if (!confirm('<?= htmlspecialchars(__('admin.orders_wp.details.js.confirm_regenerate_label', 'Deseja regerar a etiqueta da W-Express para este pedido? Isso irá substituir a etiqueta atual.'), ENT_QUOTES, 'UTF-8') ?>')) return;

    var btn = document.getElementById('btnRegerarEtiquetaWexpress');
    var sp = document.getElementById('wxReSpinner');
    var tx = document.getElementById('wxReBtnText');
    if (btn) btn.disabled = true;
    if (sp) sp.classList.remove('d-none');
    if (tx) tx.textContent = (window.ADMIN_ORDERS_WP_DETAILS_RE_LOADING_BTN_TEXT || 'Regerando...');

    fetch('/admin/pedidos-wp/wexpress/regerar/' + orderId + '?source=' + encodeURIComponent(source), {
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
            const w = window.open(labelUrl, '_blank');
            if (!w) {
                var msg = '<?= htmlspecialchars(__('admin.orders_wp.details.js.popup_blocked', 'Popup bloqueado. Copie e abra a etiqueta em uma nova aba:'), ENT_QUOTES, 'UTF-8') ?>\n' + labelUrl;
                try {
                    window.prompt(msg, labelUrl);
                } catch (e) {
                    alert(msg);
                }
            }
            setTimeout(() => location.reload(), 800);
        } else {
            location.reload();
        }
    })
    .catch((e) => {
        const errPrefix = (window.ADMIN_ORDERS_WP_DETAILS_I18N && window.ADMIN_ORDERS_WP_DETAILS_I18N.error_prefix) ? window.ADMIN_ORDERS_WP_DETAILS_I18N.error_prefix : 'Erro:';
        alert(errPrefix + ' ' + (e && e.message ? e.message : String(e)));
    })
    .finally(() => {
        if (btn) btn.disabled = false;
        if (sp) sp.classList.add('d-none');
        if (tx) tx.textContent = (window.ADMIN_ORDERS_WP_DETAILS_RE_DEFAULT_BTN_TEXT || 'Regerar etiqueta');
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
                <?php if ($source === 'red' && isset($declaracaoTotal) && is_numeric($declaracaoTotal) && (float) $declaracaoTotal > 0): ?>
                    <div><strong><?= __('admin.orders_wp.details.declaration', 'Declaração') ?>:</strong> <?= htmlspecialchars(wpFormatMoney2((float) $declaracaoTotal, 'USD')) ?></div>
                <?php endif; ?>
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
                <?php if ($shipNumber !== ''): ?><div><strong><?= __('checkout.number', 'Número') ?>:</strong> <?= htmlspecialchars($shipNumber) ?></div><?php endif; ?>
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
                                <th><?= __('admin.orders_wp.details.table.weight_kg', 'Peso (kg)') ?></th>
                                <th><?= __('admin.orders_wp.details.table.qty', 'Qtd') ?></th>
                                <th><?= __('admin.orders_wp.details.table.unit_price', 'Unitário') ?></th>
                                <?php if ($source === 'red'): ?>
                                    <th>Declaração (unit.)</th>
                                <?php endif; ?>
                                <th><?= __('checkout.subtotal', 'Subtotal') ?></th>
                                <th><?= __('common.total', 'Total') ?></th>
                                <?php if ($source === 'red'): ?>
                                    <th>Declaração (total)</th>
                                <?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($itens)): ?>
                                <tr><td colspan="<?= $source === 'red' ? '10' : '8' ?>" class="text-center text-muted"><?= __('admin.orders_wp.details.items_empty', 'Sem itens encontrados.') ?></td></tr>
                            <?php else: ?>
                                <?php foreach ($itens as $it): ?>
                                    <tr>
                                        <td>
                                            <div class="fw-semibold"><?= htmlspecialchars((string) ($it['nome'] ?? '')) ?></div>
                                            <div class="text-muted small"><?= __('admin.orders_wp.details.product_id', 'Produto ID') ?>: <?= (int) ($it['produto_id'] ?? 0) ?> | <?= __('admin.orders_wp.details.variation_id', 'Variação ID') ?>: <?= (int) ($it['variacao_id'] ?? 0) ?></div>
                                        </td>
                                        <td><?= htmlspecialchars((string) ($it['sku'] ?? '')) ?></td>
                                        <td><?= htmlspecialchars((string) ($it['ncm'] ?? '')) ?></td>
                                        <td><?= ($it['peso_kg'] ?? null) !== null ? htmlspecialchars(number_format((float) ($it['peso_kg'] ?? 0), 3, ',', '.')) : '-' ?></td>
                                        <td><?= (int) ($it['quantidade'] ?? 0) ?></td>
                                        <td><?= htmlspecialchars(wpFormatMoney2((float) ($it['preco_unitario'] ?? 0), $currency)) ?></td>
                                        <?php if ($source === 'red'): ?>
                                            <td><?= ($it['declaracao_unitario'] ?? null) !== null ? htmlspecialchars(wpFormatMoney2((float) ($it['declaracao_unitario'] ?? 0), 'USD')) : '-' ?></td>
                                        <?php endif; ?>
                                        <td><?= htmlspecialchars(wpFormatMoney2((float) ($it['subtotal'] ?? 0), $currency)) ?></td>
                                        <td><?= htmlspecialchars(wpFormatMoney2((float) ($it['total'] ?? 0), $currency)) ?></td>
                                        <?php if ($source === 'red'): ?>
                                            <td><?= ($it['declaracao_total'] ?? null) !== null ? htmlspecialchars(wpFormatMoney2((float) ($it['declaracao_total'] ?? 0), 'USD')) : '-' ?></td>
                                        <?php endif; ?>
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
