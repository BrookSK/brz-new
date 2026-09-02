<?php ob_start(); ?>
<?php
    $pedido = isset($pedido) && is_array($pedido) ? $pedido : [];
    $itens = isset($itens) && is_array($itens) ? $itens : [];
    $etiqueta = isset($etiqueta) && is_array($etiqueta) ? $etiqueta : null;

    $pid = (int) ($pedido['id'] ?? 0);
    $clienteNome = (string) ($pedido['cliente_nome'] ?? '-');
    $clienteEmail = (string) ($pedido['cliente_email'] ?? '-');
    $clienteTelefone = (string) ($pedido['cliente_telefone'] ?? '-');

    $endereco = (string) ($pedido['_endereco'] ?? '');
    $complemento = (string) ($pedido['_complemento'] ?? '');
    $cidade = (string) ($pedido['_cidade'] ?? '');
    $estado = (string) ($pedido['_estado'] ?? '');
    $cep = (string) ($pedido['_cep'] ?? '');
    $pais = strtoupper(trim((string) ($pedido['_pais'] ?? '')));

    $peso = (float) ($pedido['peso_total'] ?? 0);
    $altura = (float) ($pedido['altura'] ?? 0);
    $largura = (float) ($pedido['largura'] ?? 0);
    $comprimento = (float) ($pedido['comprimento'] ?? 0);
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="page-title">
            <?= __('admin.shippo.order_title','Shippo - Pedido #{n}', ['n' => str_pad((string) $pid, 6, '0', STR_PAD_LEFT)]) ?>
        </h1>
        <div class="d-flex gap-2">
            <a class="btn btn-sm btn-outline-secondary" href="/admin/shippo"><i class="fas fa-arrow-left me-1"></i><?= __('admin.shippo.back','Voltar') ?></a>
            <a class="btn btn-sm btn-outline-primary" href="/admin/pedidos/detalhes/<?= $pid ?>" target="_blank"><i class="fas fa-external-link-alt me-1"></i><?= __('admin.shippo.view_order','Ver Pedido') ?></a>
        </div>
    </div>

    <div class="alert alert-danger" id="shippo_error" style="display:none;"></div>
    <div class="alert alert-success" id="shippo_success" style="display:none;"></div>

    <div class="row">
        <!-- Dados do Pedido -->
        <div class="col-lg-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header"><strong><?= __('admin.shippo.recipient_data','Dados do Destinatário') ?></strong></div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr><th style="width:130px;"><?= __('admin.shippo.customer','Cliente') ?></th><td><?= htmlspecialchars($clienteNome) ?></td></tr>
                        <tr><th><?= __('admin.shippo.email','Email') ?></th><td><?= htmlspecialchars($clienteEmail) ?></td></tr>
                        <tr><th><?= __('admin.shippo.phone','Telefone') ?></th><td><?= htmlspecialchars($clienteTelefone) ?></td></tr>
                        <tr><th><?= __('admin.shippo.address','Endereço') ?></th><td><?= htmlspecialchars($endereco) ?><?= $complemento ? ', ' . htmlspecialchars($complemento) : '' ?></td></tr>
                        <tr><th><?= __('admin.shippo.city','Cidade') ?></th><td><?= htmlspecialchars($cidade) ?></td></tr>
                        <tr><th><?= __('admin.shippo.state','Estado') ?></th><td><?= htmlspecialchars($estado) ?></td></tr>
                        <tr><th><?= __('admin.shippo.zip','CEP / ZIP') ?></th><td><?= htmlspecialchars($cep) ?></td></tr>
                        <tr><th><?= __('admin.shippo.country','País') ?></th><td><span class="badge bg-primary"><?= htmlspecialchars($pais ?: '?') ?></span></td></tr>
                    </table>
                </div>
            </div>
        </div>

        <!-- Dimensões e Peso -->
        <div class="col-lg-6 mb-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header"><strong><?= __('admin.shippo.weight_dimensions','Peso e Medidas') ?></strong></div>
                <div class="card-body">
                    <table class="table table-sm mb-0">
                        <tr>
                            <th style="width:130px;"><?= __('admin.shippo.total_weight','Peso Total') ?></th>
                            <td>
                                <?php if ($peso > 0): ?>
                                    <span class="fw-bold"><?= number_format($peso, 2, ',', '.') ?> kg</span>
                                <?php else: ?>
                                    <span class="text-danger fw-bold"><?= __('admin.shippo.not_informed','Não informado') ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th><?= __('admin.shippo.width','Largura') ?></th>
                            <td><?= $largura > 0 ? number_format($largura, 1) . ' cm' : '<span class="text-danger">--</span>' ?></td>
                        </tr>
                        <tr>
                            <th><?= __('admin.shippo.height','Altura') ?></th>
                            <td><?= $altura > 0 ? number_format($altura, 1) . ' cm' : '<span class="text-danger">--</span>' ?></td>
                        </tr>
                        <tr>
                            <th><?= __('admin.shippo.length','Comprimento') ?></th>
                            <td><?= $comprimento > 0 ? number_format($comprimento, 1) . ' cm' : '<span class="text-danger">--</span>' ?></td>
                        </tr>
                        <tr>
                            <th><?= __('admin.shippo.dimensions','Medidas') ?></th>
                            <td>
                                <?php if ($largura > 0 && $altura > 0 && $comprimento > 0): ?>
                                    <?= number_format($largura, 2, '.', '') ?>×<?= number_format($altura, 2, '.', '') ?>×<?= number_format($comprimento, 2, '.', '') ?> cm
                                <?php else: ?>
                                    <span class="text-danger"><?= __('admin.shippo.incomplete','Incompletas') ?></span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    </table>

                    <?php if (!empty($itens)): ?>
                        <hr>
                        <h6 class="small text-muted mb-2"><?= __('admin.shippo.order_items_customs','Itens do Pedido (Declaração Aduaneira)') ?></h6>
                        <table class="table table-sm table-bordered" style="font-size:.8rem;">
                            <thead class="table-light">
                                <tr><th><?= __('admin.shippo.col_description','Descrição') ?></th><th><?= __('admin.shippo.col_qty','Qtd') ?></th><th><?= __('admin.shippo.col_weight','Peso') ?></th><th><?= __('admin.shippo.col_value','Valor') ?></th></tr>
                            </thead>
                            <tbody>
                                <?php foreach ($itens as $item): ?>
                                    <tr>
                                        <td><?= htmlspecialchars((string) ($item['description'] ?? '-')) ?></td>
                                        <td><?= (int) ($item['quantity'] ?? 1) ?></td>
                                        <td><?= htmlspecialchars((string) ($item['net_weight'] ?? '0')) ?> kg</td>
                                        <td>USD <?= number_format((float) ($item['value_amount'] ?? 0), 2) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Etiqueta existente ou ação para gerar -->
    <div class="row">
        <div class="col-12">
            <?php if ($etiqueta): ?>
                <!-- Etiqueta já gerada -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header bg-success text-white d-flex justify-content-between align-items-center">
                        <strong><i class="fas fa-check-circle me-1"></i><?= __('admin.shippo.label_generated_title','Etiqueta Gerada') ?></strong>
                        <button class="btn btn-sm btn-light" onclick="regerarEtiquetaShippo(<?= $pid ?>)"><i class="fas fa-redo me-1"></i><?= __('admin.shippo.regenerate','Regerar') ?></button>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <table class="table table-sm mb-0">
                                    <tr><th style="width:130px;"><?= __('admin.shippo.tracking','Tracking') ?></th><td>
                                        <?php if (!empty($etiqueta['tracking_url'])): ?>
                                            <a href="<?= htmlspecialchars((string) $etiqueta['tracking_url']) ?>" target="_blank"><?= htmlspecialchars((string) ($etiqueta['tracking_number'] ?? '-')) ?></a>
                                        <?php else: ?>
                                            <?= htmlspecialchars((string) ($etiqueta['tracking_number'] ?? '-')) ?>
                                        <?php endif; ?>
                                    </td></tr>
                                    <tr><th><?= __('admin.shippo.carrier','Carrier') ?></th><td><?= htmlspecialchars((string) ($etiqueta['carrier'] ?? '-')) ?></td></tr>
                                    <tr><th><?= __('admin.shippo.service','Serviço') ?></th><td><?= htmlspecialchars((string) ($etiqueta['service_level'] ?? '-')) ?></td></tr>
                                    <tr><th><?= __('admin.shippo.value','Valor') ?></th><td><?= htmlspecialchars((string) ($etiqueta['rate_currency'] ?? 'USD')) ?> <?= number_format((float) ($etiqueta['rate_amount'] ?? 0), 2) ?></td></tr>
                                    <tr><th><?= __('admin.shippo.created_at','Criada em') ?></th><td><?= !empty($etiqueta['created_at']) ? date('d/m/Y H:i', strtotime((string) $etiqueta['created_at'])) : '-' ?></td></tr>
                                </table>
                            </div>
                            <div class="col-md-6 d-flex align-items-center justify-content-center">
                                <?php if (!empty($etiqueta['label_url'])): ?>
                                    <a class="btn btn-lg btn-primary" href="<?= htmlspecialchars((string) $etiqueta['label_url']) ?>" target="_blank">
                                        <i class="fas fa-file-pdf me-2"></i><?= __('admin.shippo.download_label_pdf','Baixar Etiqueta (PDF)') ?>
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted"><?= __('admin.shippo.pdf_unavailable','PDF não disponível') ?></span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <!-- Gerar etiqueta -->
                <div class="card border-0 shadow-sm mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <strong><i class="fas fa-tags me-1"></i><?= __('admin.shippo.generate_label','Gerar Etiqueta Shippo') ?></strong>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small mb-3">
                            <?= __('admin.shippo.generate_help','Clique no botão abaixo para consultar as opções de frete disponíveis via Shippo. Após receber as cotações, escolha a que preferir para gerar a etiqueta.') ?>
                        </p>

                        <button class="btn btn-primary" id="btnBuscarRates" onclick="buscarRatesShippo(<?= $pid ?>)">
                            <i class="fas fa-search me-1"></i><?= __('admin.shippo.search_rates','Buscar Opções de Frete') ?>
                        </button>

                        <!-- Container para exibir rates -->
                        <div id="ratesContainer" style="display:none;" class="mt-4">
                            <h6><?= __('admin.shippo.available_rates','Opções de frete disponíveis:') ?></h6>
                            <div id="ratesList" class="row"></div>
                        </div>

                        <!-- Loading -->
                        <div id="ratesLoading" style="display:none;" class="mt-3">
                            <div class="spinner-border spinner-border-sm text-primary me-2" role="status"></div>
                            <span class="text-muted"><?= __('admin.shippo.querying_rates','Consultando opções na Shippo...') ?></span>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
let currentShipmentId = '';

function setError(msg) {
    const el = document.getElementById('shippo_error');
    if (!el) return;
    el.textContent = msg || '';
    el.style.display = msg ? '' : 'none';
}

function setSuccess(msg) {
    const el = document.getElementById('shippo_success');
    if (!el) return;
    el.innerHTML = msg || '';
    el.style.display = msg ? '' : 'none';
}

async function buscarRatesShippo(pedidoId) {
    setError('');
    setSuccess('');
    const btn = document.getElementById('btnBuscarRates');
    const loading = document.getElementById('ratesLoading');
    const container = document.getElementById('ratesContainer');
    const list = document.getElementById('ratesList');

    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>' + <?= json_encode(__('admin.shippo.querying','Consultando...')) ?>; }
    if (loading) loading.style.display = '';
    if (container) container.style.display = 'none';

    try {
        const r = await fetch('/admin/shippo/pedido/' + pedidoId + '/gerar-etiqueta', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        });
        const data = await r.json();

        if (loading) loading.style.display = 'none';

        if (!data || !data.success) {
            setError(data.error || <?= json_encode(__('admin.shippo.fetch_rates_failed','Falha ao buscar rates.')) ?>);
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-search me-1"></i>' + <?= json_encode(__('admin.shippo.search_rates','Buscar Opções de Frete')) ?>; }
            return;
        }

        currentShipmentId = data.shipment_id || '';
        const rates = data.rates || [];

        if (rates.length === 0) {
            setError(<?= json_encode(__('admin.shippo.no_rates','Nenhuma opção de frete disponível para este destino. Verifique os dados.')) ?>);
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-search me-1"></i>' + <?= json_encode(__('admin.shippo.search_rates','Buscar Opções de Frete')) ?>; }
            return;
        }

        // Ordenar por preço
        rates.sort((a, b) => parseFloat(a.amount || 999) - parseFloat(b.amount || 999));

        // Renderizar rates
        list.innerHTML = '';
        rates.forEach(function(rate) {
            const provider = rate.provider || '?';
            const serviceName = (rate.servicelevel && rate.servicelevel.name) || rate.servicelevel_name || '';
            const amount = parseFloat(rate.amount || 0).toFixed(2);
            const currency = rate.currency || 'USD';
            const days = rate.estimated_days || '?';
            const rateId = rate.object_id || '';
            const providerImg = rate.provider_image_75 || '';

            const card = document.createElement('div');
            card.className = 'col-md-6 col-lg-4 mb-3';
            card.innerHTML = `
                <div class="card h-100 border rate-card" style="cursor:pointer;" onclick="confirmarRate('${rateId}', '${provider}', '${serviceName}', '${amount}', '${currency}')">
                    <div class="card-body text-center p-3">
                        ${providerImg ? '<img src="' + providerImg + '" alt="' + provider + '" style="height:30px;margin-bottom:8px;">' : ''}
                        <div class="fw-bold">${provider}</div>
                        <div class="small text-muted">${serviceName}</div>
                        <div class="h5 mt-2 mb-1 text-primary">${currency} ${amount}</div>
                        <div class="small text-muted">${days !== '?' ? <?= json_encode(__('admin.shippo.estimated_days','{n} dia(s) estimado(s)')) ?>.replace('{n}', days) : <?= json_encode(__('admin.shippo.no_deadline','Prazo não informado')) ?>}</div>
                    </div>
                </div>
            `;
            list.appendChild(card);
        });

        if (container) container.style.display = '';
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-search me-1"></i>' + <?= json_encode(__('admin.shippo.search_again','Buscar Novamente')) ?>; }
    } catch (e) {
        if (loading) loading.style.display = 'none';
        setError(<?= json_encode(__('admin.shippo.network_error','Erro de rede:')) ?> + ' ' + e.message);
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-search me-1"></i>' + <?= json_encode(__('admin.shippo.search_rates','Buscar Opções de Frete')) ?>; }
    }
}

async function confirmarRate(rateId, provider, service, amount, currency) {
    if (!confirm(<?= json_encode(__('admin.shippo.confirm_purchase','Confirmar compra da etiqueta?\n\nCarrier: {carrier}\nServiço: {service}\nValor: {currency} {amount}\n\nEsta ação irá cobrar do seu saldo Shippo.')) ?>.replace('{carrier}', provider).replace('{service}', service).replace('{currency}', currency).replace('{amount}', amount))) return;

    setError('');
    setSuccess('');
    const container = document.getElementById('ratesContainer');
    if (container) container.innerHTML = '<div class="text-center py-3"><div class="spinner-border text-primary" role="status"></div><div class="mt-2 text-muted">' + <?= json_encode(__('admin.shippo.generating_label','Gerando etiqueta...')) ?> + '</div></div>';

    try {
        const pedidoId = <?= $pid ?>;
        const r = await fetch('/admin/shippo/pedido/' + pedidoId + '/confirmar-etiqueta', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ rate_id: rateId, shipment_id: currentShipmentId })
        });
        const data = await r.json();

        if (!data || !data.success) {
            setError(data.error || <?= json_encode(__('admin.shippo.generate_label_failed','Falha ao gerar etiqueta.')) ?>);
            if (container) container.style.display = 'none';
            return;
        }

        setSuccess(`
            <strong><?= __('admin.shippo.label_generated_success_html','Etiqueta gerada com sucesso!') ?></strong><br>
            <?= __('admin.shippo.tracking','Tracking') ?>: <strong>${data.tracking_number || '-'}</strong><br>
            ${data.label_url ? '<a href="' + data.label_url + '" target="_blank" class="btn btn-sm btn-primary mt-2"><i class="fas fa-file-pdf me-1"></i><?= __('admin.shippo.download_pdf','Baixar PDF') ?></a>' : ''}
        `);
        if (container) container.style.display = 'none';

        // Recarregar após 2 segundos
        setTimeout(function() { location.reload(); }, 2500);
    } catch (e) {
        setError(<?= json_encode(__('admin.shippo.network_error','Erro de rede:')) ?> + ' ' + e.message);
    }
}

async function regerarEtiquetaShippo(pedidoId) {
    if (!confirm(<?= json_encode(__('admin.shippo.confirm_remove_current_label','Remover etiqueta atual do pedido #{n}?\nVocê poderá gerar uma nova em seguida.')) ?>.replace('{n}', pedidoId))) return;

    try {
        const r = await fetch('/admin/shippo/pedido/' + pedidoId + '/regerar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        });
        const data = await r.json();
        if (data && data.success) {
            alert(<?= json_encode(__('admin.shippo.label_removed','Etiqueta removida. Recarregando...')) ?>);
            location.reload();
        } else {
            alert(<?= json_encode(__('admin.shippo.error_prefix','Erro:')) ?> + ' ' + (data.error || <?= json_encode(__('admin.shippo.failure','Falha')) ?>));
        }
    } catch (e) {
        alert(<?= json_encode(__('admin.shippo.network_error','Erro de rede:')) ?> + ' ' + e.message);
    }
}
</script>

<style>
.rate-card:hover {
    border-color: #0d6efd !important;
    box-shadow: 0 4px 12px rgba(13, 110, 253, 0.15);
    transform: translateY(-2px);
    transition: all 0.2s ease;
}
</style>

<?php
$content = ob_get_clean();
$title = __('admin.shippo.order_title','Shippo - Pedido #{n}', ['n' => str_pad((string) $pid, 6, '0', STR_PAD_LEFT)]);
$sidebarActive = $sidebarActive ?? 'shippo';
require __DIR__ . '/../layouts/admin.php';
?>
