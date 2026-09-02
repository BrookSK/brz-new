<?php ob_start();

$cmPerfil = '';
try {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $cmPerfil = (string) ($_SESSION['usuario_perfil'] ?? ($_SESSION['usuario_role'] ?? ''));
} catch (\Exception $e) {
    $cmPerfil = '';
}
$cmPerfil = strtolower(trim($cmPerfil));
$cmIsRedirecionador = ($cmPerfil === 'redirecionador');

function cm_mask_cpf_cnpj(string $digits): string {
    $d = preg_replace('/\D+/', '', $digits);
    if (strlen($d) === 11) {
        return substr($d, 0, 3) . '.' . substr($d, 3, 3) . '.' . substr($d, 6, 3) . '-' . substr($d, 9, 2);
    }
    if (strlen($d) === 14) {
        return substr($d, 0, 2) . '.' . substr($d, 2, 3) . '.' . substr($d, 5, 3) . '/' . substr($d, 8, 4) . '-' . substr($d, 12, 2);
    }
    return $digits;
}

function cm_mask_cep(string $digits): string {
    $d = preg_replace('/\D+/', '', $digits);
    if (strlen($d) === 8) {
        return substr($d, 0, 5) . '-' . substr($d, 5, 3);
    }
    return $digits;
}

function cm_mask_phone_br(string $digits): string {
    $d = preg_replace('/\D+/', '', $digits);
    if (strlen($d) === 10) {
        return '(' . substr($d, 0, 2) . ') ' . substr($d, 2, 4) . '-' . substr($d, 6, 4);
    }
    if (strlen($d) === 11) {
        return '(' . substr($d, 0, 2) . ') ' . substr($d, 2, 5) . '-' . substr($d, 7, 4);
    }
    return $digits;
}

$docType = strtoupper(trim((string) ($destinatario['recipientDocumentType'] ?? 'CPF')));
$docDigits = (string) ($destinatario['recipientDocumentNumber'] ?? '');
$docLabel = $docType;
$docPretty = ($docType === 'CPF' || $docType === 'CNPJ') ? cm_mask_cpf_cnpj($docDigits) : $docDigits;

$cepPretty = cm_mask_cep((string) ($destinatario['recipientZipCode'] ?? ''));
$phonePretty = cm_mask_phone_br((string) ($destinatario['recipientPhoneNumber'] ?? ''));

$currencyCode = 'USD';
$modalityCode = '33162';
$taxCode = 'DDU';
$nonNatCode = 'RETURNTOORIGIN';

$currencyLabel = [
    'USD' => __('admin.correios_mundial.currency_usd','USD - Dólar Americano'),
    'BRL' => __('admin.correios_mundial.currency_brl','BRL - Real Brasileiro'),
][$currencyCode] ?? $currencyCode;

$modalityLabel = [
    '33162' => 'PACKET STANDARD',
    '33170' => 'PACKET EXPRESS',
][$modalityCode] ?? $modalityCode;

$taxLabel = [
    'DDU' => __('admin.correios_mundial.tax_ddu','DDU - Pagamento Posterior'),
    'DDP' => __('admin.correios_mundial.tax_ddp','DDP - Antecipação de Tributos'),
    'PRC' => __('admin.correios_mundial.tax_prc','PRC - Programa Remessa Conforme'),
][$taxCode] ?? $taxCode;

$nonNatLabel = [
    'RETURNTOORIGIN' => __('admin.correios_mundial.nonnat_return','Devolver à Origem'),
    'TREATASABANDONED' => __('admin.correios_mundial.nonnat_abandoned','Tratar como Abandonado'),
][$nonNatCode] ?? $nonNatCode;
?>
<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="page-title"><?= __('admin.correios_mundial.order_page_title','Correios Mundial (PACKET) - Pedido #{n}', ['n' => str_pad((string) (int) ($pedido['id'] ?? 0), 6, '0', STR_PAD_LEFT)]) ?></h1>
        <div class="d-flex gap-2">
            <a href="/admin/correios-mundial" class="btn btn-outline-secondary"><?= __('admin.correios_mundial.back','Voltar') ?></a>
            <?php if (!$cmIsRedirecionador): ?>
                <a href="/admin/pedidos/detalhes/<?= (int) ($pedido['id'] ?? 0) ?>" target="_blank" class="btn btn-outline-secondary"><?= __('admin.correios_mundial.view_order','Ver pedido') ?></a>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($existingEtiqueta) && is_array($existingEtiqueta) && !empty($existingEtiqueta['tracking_number'])): ?>
        <div class="alert alert-success">
            <?= __('admin.correios_mundial.label_already_generated','Etiqueta já gerada. Rastreio:') ?> <strong><?= htmlspecialchars((string) $existingEtiqueta['tracking_number']) ?></strong>
            <div class="mt-2 d-flex gap-2 flex-wrap">
                <a class="btn btn-sm btn-outline-primary" href="/admin/correios-mundial/etiqueta/<?= rawurlencode((string) $existingEtiqueta['tracking_number']) ?>.pdf" target="_blank"><?= __('admin.correios_mundial.download_label_pdf','Baixar etiqueta (PDF)') ?></a>
                <button class="btn btn-sm btn-outline-warning" type="button" onclick="regerarEtiquetaPacket()"><i class="fas fa-redo me-1"></i><?= __('admin.correios_mundial.regenerate_label_current','Regerar etiqueta (com medidas atuais)') ?></button>
            </div>
        </div>
    <?php endif; ?>

    <?php if (!empty($pageError)): ?>
        <div class="alert alert-warning">
            <?= htmlspecialchars((string) $pageError) ?>
        </div>
    <?php endif; ?>

    <div class="alert alert-danger" id="cm_pedido_error" style="display:none;"></div>
    <div class="alert alert-success" id="cm_pedido_success" style="display:none;"></div>

    <div class="row">
        <div class="col-lg-6 mb-3">
            <div class="card border-0 shadow-sm">
                <div class="card-header"><strong><?= __('admin.correios_mundial.recipient_info','Informações do Destinatário') ?></strong></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><?= __('admin.correios_mundial.recipient_name','Nome do Destinatário') ?></label>
                            <input class="form-control" value="<?= htmlspecialchars((string) ($destinatario['recipientName'] ?? '')) ?>" disabled>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label"><?= __('admin.correios_mundial.document_type','Tipo de Documento') ?></label>
                            <input class="form-control" value="<?= htmlspecialchars((string) $docLabel) ?>" disabled>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label"><?= __('admin.correios_mundial.document_number','Número do Documento') ?></label>
                            <input class="form-control" value="<?= htmlspecialchars((string) $docPretty) ?>" disabled>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label"><?= __('admin.correios_mundial.recipient_address','Endereço do Destinatário') ?></label>
                            <input class="form-control" value="<?= htmlspecialchars((string) ($destinatario['recipientAddress'] ?? '')) ?>" disabled>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label"><?= __('admin.correios_mundial.address_number','Número do Endereço') ?></label>
                            <input class="form-control" value="<?= htmlspecialchars((string) ($destinatario['recipientAddressNumber'] ?? '')) ?>" disabled>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><?= __('admin.correios_mundial.address_complement','Complemento do Endereço') ?></label>
                            <input class="form-control" value="<?= htmlspecialchars((string) ($destinatario['recipientAddressComplement'] ?? '')) ?>" disabled>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><?= __('admin.correios_mundial.recipient_city','Cidade do Destinatário') ?></label>
                            <input class="form-control" value="<?= htmlspecialchars((string) ($destinatario['recipientCityName'] ?? '')) ?>" disabled>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-2 mb-3">
                            <label class="form-label"><?= __('admin.correios_mundial.state','Estado') ?></label>
                            <input class="form-control" value="<?= htmlspecialchars((string) ($destinatario['recipientState'] ?? '')) ?>" disabled>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label"><?= __('admin.correios_mundial.recipient_zip','CEP do Destinatário') ?></label>
                            <input class="form-control" value="<?= htmlspecialchars((string) $cepPretty) ?>" disabled>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><?= __('admin.correios_mundial.recipient_email','E-mail do Destinatário') ?></label>
                            <input class="form-control" value="<?= htmlspecialchars((string) ($destinatario['recipientEmail'] ?? '')) ?>" disabled>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-0">
                            <label class="form-label"><?= __('admin.correios_mundial.recipient_phone','Telefone do Destinatário') ?></label>
                            <input class="form-control" value="<?= htmlspecialchars((string) $phonePretty) ?>" disabled>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <div class="col-lg-6 mb-3">
            <div class="card border-0 shadow-sm">
                <div class="card-header"><strong><?= __('admin.correios_mundial.shipping_info','Informações de Envio') ?></strong></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label"><?= __('admin.correios_mundial.currency','Moeda') ?></label>
                            <input class="form-control" id="currency" value="<?= htmlspecialchars((string) $currencyLabel) ?>" disabled>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label"><?= __('admin.correios_mundial.modality','Modalidade') ?></label>
                            <input class="form-control" id="distributionModality" value="<?= htmlspecialchars((string) $modalityLabel) ?>" disabled>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label"><?= __('admin.correios_mundial.tax','Imposto') ?></label>
                            <input class="form-control" id="taxPaymentMethod" value="<?= htmlspecialchars((string) $taxLabel) ?>" disabled>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><?= __('admin.correios_mundial.non_nationalization','Não nacionalização') ?></label>
                            <input class="form-control" id="nonNationalizationInstruction" value="<?= htmlspecialchars((string) $nonNatLabel) ?>" disabled>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><?= __('admin.correios_mundial.rfid','RFID') ?></label>
                            <input class="form-control" id="packageRfidCode" value="" placeholder="<?= htmlspecialchars(__('admin.correios_mundial.empty','(vazio)'), ENT_QUOTES, 'UTF-8') ?>" disabled>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-3 mb-3">
                            <label class="form-label"><?= __('admin.correios_mundial.total_weight_g','Peso total (g)') ?></label>
                            <input class="form-control" id="totalWeight" type="number" min="1" max="30000" step="1" value="<?= htmlspecialchars((string) ($defaults['totalWeight'] ?? '')) ?>">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label"><?= __('admin.correios_mundial.width_cm','Largura (cm)') ?></label>
                            <input class="form-control" id="packagingWidth" type="number" min="11" max="100" step="0.01" value="<?= htmlspecialchars((string) ($defaults['packagingWidth'] ?? '')) ?>">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label"><?= __('admin.correios_mundial.height_cm','Altura (cm)') ?></label>
                            <input class="form-control" id="packagingHeight" type="number" min="2" max="100" step="0.01" value="<?= htmlspecialchars((string) ($defaults['packagingHeight'] ?? '')) ?>">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label"><?= __('admin.correios_mundial.length_cm','Comprimento (cm)') ?></label>
                            <input class="form-control" id="packagingLength" type="number" min="16" max="100" step="0.01" value="<?= htmlspecialchars((string) ($defaults['packagingLength'] ?? '')) ?>">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><?= __('admin.correios_mundial.freight_paid_usd','Frete pago (USD)') ?></label>
                            <input class="form-control" id="freightPaidValue" type="number" min="0.01" max="999999" step="0.01" value="0.01">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label"><?= __('admin.correios_mundial.insurance_paid_usd','Seguro pago (USD)') ?></label>
                            <input class="form-control" id="insurancePaidValue" type="number" min="0.01" max="999999" step="0.01" value="" placeholder="<?= htmlspecialchars(__('admin.correios_mundial.empty','(vazio)'), ENT_QUOTES, 'UTF-8') ?>">
                        </div>
                    </div>

                    <div class="d-flex gap-2 flex-wrap">
                        <button class="btn btn-primary" type="button" onclick="gerarEtiqueta()" <?= (!empty($existingEtiqueta) && !empty($existingEtiqueta['tracking_number'])) || !empty($pageError) ? 'disabled' : '' ?>><?= __('admin.correios_mundial.generate_label_btn','Gerar etiqueta') ?></button>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header"><strong><?= __('admin.correios_mundial.products_items','Produtos (Items)') ?></strong></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th><?= __('admin.correios_mundial.col_ncm','NCM') ?></th>
                            <th><?= __('admin.correios_mundial.col_description','Descrição') ?></th>
                            <th><?= __('admin.correios_mundial.col_price_usd','Preço (USD)') ?></th>
                            <th><?= __('admin.correios_mundial.col_weight_kg2','Peso (kg)') ?></th>
                            <th><?= __('admin.correios_mundial.col_qty','Qtd') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $items = isset($items) && is_array($items) ? $items : []; ?>
                        <?php if (empty($items)): ?>
                            <tr><td colspan="5" class="text-muted"><?= __('admin.correios_mundial.order_no_items','Pedido sem itens.') ?></td></tr>
                        <?php else: ?>
                            <?php foreach ($items as $it): ?>
                                <tr>
                                    <td><?= htmlspecialchars((string) ($it['ncm'] ?? '')) ?></td>
                                    <td><?= htmlspecialchars((string) ($it['nome_produto'] ?? ($it['nome'] ?? ''))) ?></td>
                                    <td>$ <?= number_format((float) ($it['preco_unitario'] ?? 0), 2, '.', ',') ?></td>
                                    <td><?= htmlspecialchars((string) ($it['peso_kg'] ?? '')) ?></td>
                                    <td><?= (int) ($it['quantidade'] ?? 0) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function setErr(msg){
    const el = document.getElementById('cm_pedido_error');
    if(!el) return;
    el.textContent = msg || '';
    el.style.display = msg ? '' : 'none';
}
function setOk(msg){
    const el = document.getElementById('cm_pedido_success');
    if(!el) return;
    el.textContent = msg || '';
    el.style.display = msg ? '' : 'none';
}

function gerarEtiqueta(){
    setErr('');
    setOk('');

    const body = {
        totalWeight: document.getElementById('totalWeight').value,
        packagingLength: document.getElementById('packagingLength').value,
        packagingWidth: document.getElementById('packagingWidth').value,
        packagingHeight: document.getElementById('packagingHeight').value,
        freightPaidValue: document.getElementById('freightPaidValue').value,
        insurancePaidValue: document.getElementById('insurancePaidValue').value,
    };

    if(!confirm(<?= json_encode(__('admin.correios_mundial.confirm_generate_order','Gerar etiqueta PACKET para este pedido?')) ?>)) return;

    fetch('/admin/correios-mundial/pedido/<?= (int) ($pedido["id"] ?? 0) ?>/gerar-etiqueta', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(body)
    })
    .then(r => r.json().catch(() => ({})).then(data => ({ok: r.ok, data})))
    .then(({ok, data}) => {
        if(ok && data && data.success){
            setOk(<?= json_encode(__('admin.correios_mundial.label_generated_tracking','Etiqueta gerada. Rastreio: {n}')) ?>.replace('{n}', (data.tracking_number || '')));
            setTimeout(() => location.reload(), 600);
            return;
        }
        const msg = (data && (data.error || data.message)) ? (data.error || data.message) : <?= json_encode(__('admin.correios_mundial.generate_label_failed','Falha ao gerar etiqueta')) ?>;
        setErr(msg);
    })
    .catch(err => setErr(err.message || <?= json_encode(__('admin.correios_mundial.generate_label_failed','Falha ao gerar etiqueta')) ?>));
}

function regerarEtiquetaPacket(){
    if(!confirm(<?= json_encode(__('admin.correios_mundial.confirm_regenerate_current','Isso vai DELETAR a etiqueta atual e gerar uma nova com as medidas atuais do formulário. Continuar?')) ?>)) return;

    setErr('');
    setOk('');

    const body = {
        totalWeight: document.getElementById('totalWeight').value,
        packagingLength: document.getElementById('packagingLength').value,
        packagingWidth: document.getElementById('packagingWidth').value,
        packagingHeight: document.getElementById('packagingHeight').value,
        freightPaidValue: document.getElementById('freightPaidValue').value,
        insurancePaidValue: document.getElementById('insurancePaidValue').value,
    };

    fetch('/admin/correios-mundial/pedido/<?= (int) ($pedido["id"] ?? 0) ?>/regerar-etiqueta', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
        body: JSON.stringify(body)
    })
    .then(r => r.json().catch(() => ({})).then(data => ({ok: r.ok, data})))
    .then(({ok, data}) => {
        if(ok && data && data.success){
            setOk(<?= json_encode(__('admin.correios_mundial.label_regenerated_tracking','Etiqueta regerada. Novo rastreio: {n}')) ?>.replace('{n}', (data.tracking_number || '')));
            setTimeout(() => location.reload(), 600);
            return;
        }
        const msg = (data && (data.error || data.message)) ? (data.error || data.message) : <?= json_encode(__('admin.correios_mundial.regenerate_label_failed','Falha ao regerar etiqueta')) ?>;
        setErr(msg);
    })
    .catch(err => setErr(err.message || <?= json_encode(__('admin.correios_mundial.regenerate_label_failed','Falha ao regerar etiqueta')) ?>));
}
</script>

<?php $content = ob_get_clean(); include __DIR__ . '/../layouts/admin.php'; ?>
