<?php ob_start(); ?>
<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="page-title">Container #<?= (int) ($container['id'] ?? 0) ?></h1>
        <a class="btn btn-sm btn-outline-secondary" href="/admin/pacotes-wordpress?action=containers"><i class="fas fa-arrow-left me-1"></i>Voltar</a>
    </div>

    <?php $container = isset($container) && is_array($container) ? $container : []; ?>

    <div class="row mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Nome</div>
                    <div class="fw-bold"><?= htmlspecialchars((string) ($container['nome'] ?? '-')) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Dispatch Number</div>
                    <div class="fw-bold"><?= (int) ($container['dispatch_number'] ?? 0) ?></div>
                </div>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Status</div>
                    <?php $status = (string) ($container['status'] ?? 'created'); ?>
                    <?php if ($status === 'billed'): ?>
                        <span class="badge bg-success">Faturado</span>
                    <?php else: ?>
                        <span class="badge bg-warning text-dark">Aberto</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="card border-0 shadow-sm">
        <div class="card-header"><strong>Etiquetas neste container</strong></div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>Origem</th>
                            <th>Pedido</th>
                            <th>Cliente</th>
                            <th>Tracking</th>
                            <th>Data</th>
                            <th>PDF</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $etiquetas = isset($etiquetas) && is_array($etiquetas) ? $etiquetas : []; ?>
                        <?php if (empty($etiquetas)): ?>
                            <tr><td colspan="6" class="text-muted">Nenhuma etiqueta neste container.</td></tr>
                        <?php else: ?>
                            <?php foreach ($etiquetas as $e): ?>
                                <?php
                                    $eid = (int) ($e['id'] ?? 0);
                                    $origem = strtoupper((string) ($e['origem'] ?? ''));
                                    $pedidoId = (int) ($e['pedido_id'] ?? 0);
                                    $trk = (string) ($e['tracking_number'] ?? '');
                                ?>
                                <tr>
                                    <td><span class="badge bg-<?= $origem === 'BR' ? 'success' : ($origem === 'RED' ? 'warning' : 'info') ?>"><?= $origem ?></span></td>
                                    <td><?= $pedidoId > 0 ? '#' . $pedidoId : '-' ?></td>
                                    <td><?= htmlspecialchars((string) ($e['cliente_nome'] ?? '-')) ?></td>
                                    <td><code class="small"><?= htmlspecialchars($trk) ?></code></td>
                                    <td><?= !empty($e['created_at']) ? date('d/m/Y H:i', strtotime((string) $e['created_at'])) : '-' ?></td>
                                    <td>
                                        <?php if ($trk !== ''): ?>
                                            <?php
                                                $meta3 = json_decode((string) ($e['meta_json'] ?? '{}'), true) ?: [];
                                                $pdfData3 = json_encode([
                                                    'tracking' => $trk,
                                                    'orderId' => (int) ($e['pedido_id'] ?? 0),
                                                    'nome' => (string) ($e['cliente_nome'] ?? ''),
                                                    'endereco' => (string) ($meta3['_recipient_address'] ?? ''),
                                                    'numero' => (string) ($meta3['_recipient_address_number'] ?? ''),
                                                    'complemento' => (string) ($meta3['_recipient_address_complement'] ?? ''),
                                                    'cidade' => (string) ($meta3['_recipient_city_name'] ?? ''),
                                                    'estado' => (string) ($meta3['_recipient_state'] ?? ''),
                                                    'cep' => (string) ($meta3['_recipient_zip_code'] ?? ''),
                                                    'documento' => (string) ($meta3['_recipient_document_number'] ?? ''),
                                                    'origem' => strtoupper((string) ($e['origem'] ?? '')),
                                                ], JSON_UNESCAPED_UNICODE);
                                            ?>
                                            <button class="btn btn-sm btn-outline-primary" onclick='gerarEtiquetaPdf(<?= htmlspecialchars($pdfData3, ENT_QUOTES) ?>)'><i class="fas fa-file-pdf"></i></button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <?php
        $trackingsJson = $container['tracking_numbers_json'] ?? '[]';
        $trackings = json_decode((string) $trackingsJson, true) ?: [];
    ?>
    <?php if (!empty($trackings)): ?>
        <div class="card border-0 shadow-sm mt-4">
            <div class="card-header"><strong>Tracking Numbers (<?= count($trackings) ?>)</strong></div>
            <div class="card-body">
                <div class="small" style="max-height:200px;overflow-y:auto;">
                    <?php foreach ($trackings as $t): ?>
                        <div><code><?= htmlspecialchars((string) $t) ?></code></div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
function gerarEtiquetaPdf(d) {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF({ orientation: 'portrait', unit: 'mm', format: [100, 150] });
    const tracking = d.tracking || '';
    const orderId = d.orderId || '';
    const nome = d.nome || '';
    const endereco = d.endereco || '';
    const numero = d.numero || '';
    const complemento = d.complemento || '';
    const cidade = d.cidade || '';
    const estado = d.estado || '';
    const cep = d.cep || '';
    const documento = d.documento || '';
    const origem = d.origem || 'US';
    doc.setFontSize(8); doc.text('PACKET STANDARD', 60, 8);
    doc.setFontSize(7); doc.text('Order #: ' + orderId, 5, 8); doc.text('DDU', 5, 12);
    doc.setFontSize(14); doc.setFont(undefined, 'bold'); doc.text(tracking, 50, 25, { align: 'center' }); doc.setFont(undefined, 'normal');
    doc.setFontSize(8); doc.text('||||| ' + tracking + ' |||||', 50, 32, { align: 'center' });
    doc.setFontSize(18); doc.setFont(undefined, 'bold'); doc.text(origem, 90, 30); doc.setFont(undefined, 'normal');
    doc.line(5, 38, 95, 38);
    doc.setFontSize(7); doc.text('Recebedor: ___________________________________', 5, 43); doc.text('Assinatura: ___________________________________', 5, 48);
    doc.setFillColor(0, 0, 0); doc.rect(5, 52, 90, 5, 'F');
    doc.setTextColor(255, 255, 255); doc.setFontSize(8); doc.setFont(undefined, 'bold'); doc.text('DESTINATÁRIO', 7, 56);
    doc.setTextColor(0, 0, 0); doc.setFont(undefined, 'normal');
    doc.setFontSize(9); doc.text(nome, 7, 62);
    doc.setFontSize(8); doc.text(endereco + (numero ? ', ' + numero : ''), 7, 67);
    if (complemento) doc.text(complemento, 7, 72);
    doc.text(cidade + '/' + estado, 7, complemento ? 77 : 72);
    doc.setFontSize(14); doc.setFont(undefined, 'bold');
    const cepF = cep.length === 8 ? cep.substring(0,5) + '-' + cep.substring(5) : cep;
    doc.text(cepF, 75, 67); doc.setFont(undefined, 'normal');
    doc.line(5, 80, 95, 80);
    doc.setFontSize(7); doc.text('Instrução: Retorno à origem', 5, 85);
    doc.setFontSize(8); doc.setFont(undefined, 'bold'); doc.text('Remetente:', 60, 85); doc.setFont(undefined, 'normal');
    doc.setFontSize(7); doc.text('Braziliana LLC', 60, 89); doc.text('United States', 60, 93);
    if (documento) { doc.setFontSize(6); doc.text('CPF: ' + documento, 5, 145); }
    doc.save('etiqueta-' + tracking + '.pdf');
}
</script>

<?php
$content = ob_get_clean();
$title = 'Container #' . (int) ($container['id'] ?? 0) . ' - Pacotes WordPress';
$activePage = 'pacotes-wordpress';
include __DIR__ . '/../../layouts/admin.php';
?>
