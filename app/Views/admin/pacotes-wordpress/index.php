<?php ob_start(); ?>
<div class="container-fluid">
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="page-title">Pacotes WordPress</h1>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <button class="btn btn-sm btn-success" onclick="sincronizarPacotes()" id="btnSync">
                <i class="fas fa-sync-alt me-1"></i>Sincronizar
            </button>
            <a class="btn btn-sm btn-outline-primary" href="/admin/pacotes-wordpress?action=containers">Containers</a>
            <a class="btn btn-sm btn-outline-primary" href="/admin/pacotes-wordpress?action=faturas">Faturas (CN38)</a>
        </div>
    </div>

    <?php if (!empty($lastSync)): ?>
        <div class="text-muted small mb-3">
            <i class="fas fa-clock me-1"></i>Última sincronização: <?= date('d/m/Y H:i', strtotime((string) $lastSync)) ?>
        </div>
    <?php endif; ?>

    <div class="alert alert-success" id="syncSuccess" style="display:none;"></div>
    <div class="alert alert-danger" id="syncError" style="display:none;"></div>

    <!-- Filtros -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body py-2">
            <form method="GET" action="/admin/pacotes-wordpress" class="row g-2 align-items-end">
                <div class="col-auto">
                    <label class="form-label small mb-0">Origem</label>
                    <select name="origem" class="form-select form-select-sm">
                        <option value="">Todas</option>
                        <option value="br" <?= ($filtroOrigem ?? '') === 'br' ? 'selected' : '' ?>>BR</option>
                        <option value="red" <?= ($filtroOrigem ?? '') === 'red' ? 'selected' : '' ?>>RED</option>
                        <option value="us" <?= ($filtroOrigem ?? '') === 'us' ? 'selected' : '' ?>>US</option>
                    </select>
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-0">Nº Pedido</label>
                    <input type="text" name="pedido" class="form-control form-control-sm" placeholder="Ex: 68849" value="<?= htmlspecialchars((string) ($filtroPedido ?? '')) ?>">
                </div>
                <div class="col-auto">
                    <label class="form-label small mb-0">Tracking</label>
                    <input type="text" name="tracking" class="form-control form-control-sm" placeholder="Código rastreio..." value="<?= htmlspecialchars((string) ($filtroTracking ?? '')) ?>">
                </div>
                <div class="col-auto">
                    <button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-search me-1"></i>Filtrar</button>
                    <a href="/admin/pacotes-wordpress" class="btn btn-sm btn-outline-secondary">Limpar</a>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabela de Etiquetas -->
    <div class="card border-0 shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center">
            <strong>Etiquetas (<?= (int) ($total ?? 0) ?> total)</strong>
        </div>
        <div class="card-body">
            <div class="table-responsive d-none d-md-block">
                <table class="table table-sm align-middle">
                    <thead>
                        <tr>
                            <th>Origem</th>
                            <th>Pedido</th>
                            <th>Cliente</th>
                            <th>Rastreio</th>
                            <th>Container</th>
                            <th>Data</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $etiquetas = isset($etiquetas) && is_array($etiquetas) ? $etiquetas : []; ?>
                        <?php if (empty($etiquetas)): ?>
                            <tr><td colspan="7" class="text-muted">Nenhuma etiqueta encontrada. Clique em "Sincronizar" para puxar do WordPress.</td></tr>
                        <?php else: ?>
                            <?php foreach ($etiquetas as $e): ?>
                                <?php
                                    $eid = (int) ($e['id'] ?? 0);
                                    $origem = strtoupper((string) ($e['origem'] ?? ''));
                                    $pedidoId = (int) ($e['pedido_id'] ?? 0);
                                    $trk = (string) ($e['tracking_number'] ?? '');
                                    $contId = (int) ($e['container_id'] ?? 0);
                                ?>
                                <tr>
                                    <td><span class="badge bg-<?= $origem === 'BR' ? 'success' : ($origem === 'RED' ? 'warning' : 'info') ?>"><?= $origem ?></span></td>
                                    <td>
                                        <?php if ($pedidoId > 0): ?>
                                            #<?= $pedidoId ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars((string) ($e['cliente_nome'] ?? '-')) ?></td>
                                    <td>
                                        <?php if ($trk !== ''): ?>
                                            <code class="small"><?= htmlspecialchars($trk) ?></code>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($contId > 0): ?>
                                            <a href="/admin/pacotes-wordpress?action=container-detalhes&id=<?= $contId ?>">#<?= $contId ?></a>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= !empty($e['created_at']) ? date('d/m/Y H:i', strtotime((string) $e['created_at'])) : '-' ?></td>
                                    <td>
                                        <?php if ($trk !== ''): ?>
                                            <?php
                                                $meta = json_decode((string) ($e['meta_json'] ?? '{}'), true) ?: [];
                                                $pdfData = json_encode([
                                                    'tracking' => $trk,
                                                    'orderId' => $pedidoId,
                                                    'nome' => (string) ($e['cliente_nome'] ?? ''),
                                                    'endereco' => (string) ($meta['_order_shipping_address_1'] ?? ($meta['_shipping_address_1'] ?? '')),
                                                    'numero' => (string) ($meta['_order_shipping_number'] ?? ($meta['_shipping_number'] ?? '')),
                                                    'complemento' => (string) ($meta['_order_shipping_address_2'] ?? ($meta['_shipping_address_2'] ?? '')),
                                                    'cidade' => (string) ($meta['_order_shipping_city'] ?? ($meta['_shipping_city'] ?? '')),
                                                    'estado' => (string) ($meta['_order_shipping_state'] ?? ($meta['_shipping_state'] ?? '')),
                                                    'cep' => preg_replace('/\D/', '', (string) ($meta['_order_shipping_postcode'] ?? ($meta['_shipping_postcode'] ?? ''))),
                                                    'documento' => (string) ($meta['_order_billing_cpf'] ?? ($meta['_billing_cpf'] ?? '')),
                                                    'origem' => strtoupper((string) ($e['origem'] ?? '')),
                                                ], JSON_UNESCAPED_UNICODE);
                                            ?>
                                            <button class="btn btn-sm btn-outline-primary" onclick='gerarEtiquetaPdf(<?= htmlspecialchars($pdfData, ENT_QUOTES) ?>)' title="Gerar PDF"><i class="fas fa-file-pdf"></i></button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Mobile: Cards -->
            <div class="d-md-none">
                <?php if (empty($etiquetas)): ?>
                    <div class="text-muted small py-3">Nenhuma etiqueta encontrada.</div>
                <?php else: ?>
                    <?php foreach ($etiquetas as $e): ?>
                        <?php
                            $eid = (int) ($e['id'] ?? 0);
                            $origem = strtoupper((string) ($e['origem'] ?? ''));
                            $pedidoId = (int) ($e['pedido_id'] ?? 0);
                            $trk = (string) ($e['tracking_number'] ?? '');
                        ?>
                        <div class="border-bottom py-2">
                            <div class="d-flex justify-content-between align-items-center">
                                <div style="min-width:0;flex:1;">
                                    <span class="badge bg-<?= $origem === 'BR' ? 'success' : ($origem === 'RED' ? 'warning' : 'info') ?> me-1"><?= $origem ?></span>
                                    <?php if ($pedidoId > 0): ?>
                                        <span class="fw-bold small">#<?= $pedidoId ?></span>
                                    <?php endif; ?>
                                    <span class="small ms-1"><?= htmlspecialchars((string) ($e['cliente_nome'] ?? '-')) ?></span>
                                    <?php if ($trk !== ''): ?>
                                        <div class="text-muted small" style="word-break:break-all;"><?= htmlspecialchars($trk) ?></div>
                                    <?php endif; ?>
                                </div>
                                <?php if ($trk !== ''): ?>
                                    <?php
                                        $meta2 = json_decode((string) ($e['meta_json'] ?? '{}'), true) ?: [];
                                        $pdfData2 = json_encode([
                                            'tracking' => $trk,
                                            'orderId' => $pedidoId,
                                            'nome' => (string) ($e['cliente_nome'] ?? ''),
                                            'endereco' => (string) ($meta2['_order_shipping_address_1'] ?? ($meta2['_shipping_address_1'] ?? '')),
                                            'numero' => (string) ($meta2['_order_shipping_number'] ?? ($meta2['_shipping_number'] ?? '')),
                                            'complemento' => (string) ($meta2['_order_shipping_address_2'] ?? ($meta2['_shipping_address_2'] ?? '')),
                                            'cidade' => (string) ($meta2['_order_shipping_city'] ?? ($meta2['_shipping_city'] ?? '')),
                                            'estado' => (string) ($meta2['_order_shipping_state'] ?? ($meta2['_shipping_state'] ?? '')),
                                            'cep' => preg_replace('/\D/', '', (string) ($meta2['_order_shipping_postcode'] ?? ($meta2['_shipping_postcode'] ?? ''))),
                                            'documento' => (string) ($meta2['_order_billing_cpf'] ?? ($meta2['_billing_cpf'] ?? '')),
                                            'origem' => strtoupper((string) ($e['origem'] ?? '')),
                                        ], JSON_UNESCAPED_UNICODE);
                                    ?>
                                    <button class="btn btn-sm btn-outline-primary py-0 px-2 ms-2" onclick='gerarEtiquetaPdf(<?= htmlspecialchars($pdfData2, ENT_QUOTES) ?>)'><i class="fas fa-file-pdf"></i></button>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Paginação -->
            <?php
                $totalPages = max(1, (int) ceil(($total ?? 0) / ($perPage ?? 50)));
                $currentPage = (int) ($page ?? 1);
            ?>
            <?php if ($totalPages > 1): ?>
                <nav class="mt-3">
                <?php
                $paginacao_atual = (int)$currentPage;
                $paginacao_total = (int)$totalPages;
                $paginacao_url_fn = function($p) use ($filtroOrigem, $filtroPedido, $filtroTracking) {
                    $queryParams = array_filter([
                        'origem' => $filtroOrigem ?? '',
                        'pedido' => $filtroPedido ?? '',
                        'tracking' => $filtroTracking ?? '',
                        'page' => $p,
                    ]);
                    return '/admin/pacotes-wordpress?' . http_build_query($queryParams);
                };
                include __DIR__ . '/../../partials/pagination.php';
                ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
<script>
function gerarEtiquetaPdf(d) {
    const { jsPDF } = window.jspdf;
    // Etiqueta 100mm x 150mm
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

    // Header
    doc.setFontSize(8);
    doc.text('PACKET STANDARD', 60, 8);
    doc.setFontSize(7);
    doc.text('Order #: ' + orderId, 5, 8);
    doc.text('DDU', 5, 12);

    // Tracking number
    doc.setFontSize(14);
    doc.setFont(undefined, 'bold');
    doc.text(tracking, 50, 25, { align: 'center' });
    doc.setFont(undefined, 'normal');

    // Barcode (simulated with text)
    doc.setFontSize(8);
    doc.text('||||| ' + tracking + ' |||||', 50, 32, { align: 'center' });

    // Origin country
    doc.setFontSize(18);
    doc.setFont(undefined, 'bold');
    doc.text(origem, 90, 30);
    doc.setFont(undefined, 'normal');

    // Separator
    doc.setDrawColor(0);
    doc.line(5, 38, 95, 38);

    // Recebedor / Assinatura
    doc.setFontSize(7);
    doc.text('Recebedor: ___________________________________', 5, 43);
    doc.text('Assinatura: ___________________________________', 5, 48);
    doc.text('Documento: ____________', 65, 48);

    // Destinatário box
    doc.setFillColor(0, 0, 0);
    doc.rect(5, 52, 90, 5, 'F');
    doc.setTextColor(255, 255, 255);
    doc.setFontSize(8);
    doc.setFont(undefined, 'bold');
    doc.text('DESTINATÁRIO', 7, 56);
    doc.setTextColor(0, 0, 0);
    doc.setFont(undefined, 'normal');

    doc.setFontSize(9);
    doc.text(nome, 7, 62);
    doc.setFontSize(8);
    const enderecoFull = endereco + (numero ? ', ' + numero : '');
    doc.text(enderecoFull, 7, 67);
    if (complemento) doc.text(complemento, 7, 72);
    doc.text(cidade + '/' + estado, 7, complemento ? 77 : 72);

    // CEP grande
    doc.setFontSize(14);
    doc.setFont(undefined, 'bold');
    const cepFormatted = cep.length === 8 ? cep.substring(0,5) + '-' + cep.substring(5) : cep;
    doc.text(cepFormatted, 75, 67);
    doc.setFont(undefined, 'normal');

    // CEP barcode (simulated)
    doc.setFontSize(7);
    doc.text('||||| ' + cep + ' |||||', 80, 73, { align: 'center' });

    // Separator
    doc.line(5, 80, 95, 80);

    // Instrução de não nacionalização
    doc.setFontSize(7);
    doc.text('Instrução do Remetente no caso de não nacionalização:', 5, 85);
    doc.rect(5, 87, 3, 3);
    doc.text('X', 5.8, 89.5);
    doc.text('Retorno à origem', 10, 89.5);

    // Remetente
    doc.setFontSize(8);
    doc.setFont(undefined, 'bold');
    doc.text('Remetente:', 60, 85);
    doc.setFont(undefined, 'normal');
    doc.setFontSize(7);
    doc.text('Braziliana LLC', 60, 89);
    doc.text('United States', 60, 93);

    // Devolução
    doc.setFontSize(6);
    doc.text('--- DEVOLUÇÃO ---', 5, 97);
    doc.text('(Em caso de não entrega ao remetente, entregar para:)', 5, 101);
    doc.text('Braziliana', 5, 105);
    doc.text('Rua Votuporanga 2276 / Eldorado', 5, 109);
    doc.text('15043-040 - São José do Rio Preto/SP', 5, 113);

    // Separator
    doc.line(5, 116, 95, 116);

    // Declaração para Alfândega
    doc.setFontSize(7);
    doc.setFont(undefined, 'bold');
    doc.text('Declaração para Alfândega', 5, 120);
    doc.text('Pode ser aberto Ex Officio', 55, 120);
    doc.setFont(undefined, 'normal');

    // Table header
    doc.setFontSize(6);
    doc.text('Cod SH', 5, 125);
    doc.text('Qtde', 25, 125);
    doc.text('Descrição', 35, 125);
    doc.text('Peso KG', 60, 125);
    doc.text('Unit USD', 75, 125);
    doc.text('Valor USD', 88, 125);
    doc.line(5, 126, 95, 126);

    // CPF/Documento
    if (documento) {
        doc.setFontSize(6);
        doc.text('CPF: ' + documento, 5, 145);
    }

    // Save
    doc.save('etiqueta-' + tracking + '.pdf');
}
</script>

<script>
async function sincronizarPacotes() {
    const btn = document.getElementById('btnSync');
    const successEl = document.getElementById('syncSuccess');
    const errorEl = document.getElementById('syncError');
    successEl.style.display = 'none';
    errorEl.style.display = 'none';
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Sincronizando...';

    try {
        const r = await fetch('/admin/pacotes-wordpress?action=sincronizar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' }
        });
        const data = await r.json();
        if (data.success) {
            successEl.textContent = 'Sincronização concluída! ' + (data.synced || 0) + ' pacotes sincronizados.';
            successEl.style.display = '';
            setTimeout(() => location.reload(), 1500);
        } else {
            errorEl.textContent = 'Erros: ' + (data.errors || []).join('; ');
            errorEl.style.display = '';
        }
    } catch (e) {
        errorEl.textContent = 'Falha na sincronização: ' + e.message;
        errorEl.style.display = '';
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-sync-alt me-1"></i>Sincronizar';
    }
}
</script>

<?php
$content = ob_get_clean();
$title = 'Pacotes WordPress - Admin';
$activePage = 'pacotes-wordpress';
include __DIR__ . '/../../layouts/admin.php';
?>
