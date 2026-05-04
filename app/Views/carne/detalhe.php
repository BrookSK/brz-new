<?php $title = 'Carnê #' . $carne['id'] . ' - Detalhes'; ?>
<?php ob_start(); ?>
<?php
$pagas = 0;
foreach ($carne['parcelas'] as $pp) { if ($pp['status'] === 'paga') $pagas++; }
$total = (int) $carne['quantidade_parcelas'];
$progresso = $total > 0 ? round(($pagas / $total) * 100) : 0;
?>
<div class="container py-5">
    <div class="row g-4">
        <?php $activePage = 'carnes'; include __DIR__ . '/../partials/usuario_sidebar.php'; ?>

        <div class="col-lg-9">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <a href="/meus-carnes" class="text-muted text-decoration-none small"><i class="fas fa-arrow-left me-1"></i>Voltar aos Carnês</a>
                    <h2 class="mb-0 mt-1">Carnê — Pedido #<?= $carne['pedido_id'] ?></h2>
                </div>
                <span class="badge bg-<?= $carne['status'] === 'quitado' ? 'success' : ($carne['status'] === 'com_atraso' ? 'danger' : 'primary') ?> fs-6">
                    <?= ucfirst(str_replace('_', ' ', $carne['status'])) ?>
                </span>
            </div>

            <!-- Resumo -->
            <div class="card border-0 shadow-sm mb-4">
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-4">
                            <small class="text-muted d-block">Total</small>
                            <span class="fs-5 fw-bold">R$ <?= number_format($carne['total_geral'], 2, ',', '.') ?></span>
                        </div>
                        <div class="col-4">
                            <small class="text-muted d-block">Parcelas</small>
                            <span class="fs-5 fw-bold"><?= $pagas ?> / <?= $total ?></span>
                        </div>
                        <div class="col-4">
                            <small class="text-muted d-block">Valor/Parcela</small>
                            <span class="fs-5 fw-bold">R$ <?= number_format($carne['total_geral'] / max($total, 1), 2, ',', '.') ?></span>
                        </div>
                    </div>
                    <div class="progress mt-3" style="height: 8px;">
                        <div class="progress-bar bg-<?= $progresso >= 100 ? 'success' : 'primary' ?>" style="width: <?= $progresso ?>%"></div>
                    </div>
                    <?php if ($carne['status'] !== 'quitado' && $carne['status'] !== 'liberado_envio'): ?>
                    <div class="alert alert-warning small mt-3 mb-0 py-2">
                        <i class="fas fa-info-circle me-1"></i>O envio ocorre somente após a quitação total de todas as parcelas.
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Parcelas -->
            <?php foreach ($carne['parcelas'] as $idx => $p): ?>
            <?php
                $isPix = (($p['metodo_pagamento'] ?? '') === 'pix');
                $paga = ($p['status'] === 'paga');
                $corStatus = match($p['status']) {
                    'paga' => 'success', 'parcialmente_paga' => 'warning',
                    'aguardando_pagamento' => 'info', 'vencida','em_atraso' => 'danger',
                    default => 'secondary'
                };
                $aberta = (!$paga && in_array($p['status'], ['aguardando_pagamento','parcialmente_paga','reemitida']));
            ?>
            <div class="card border-0 shadow-sm mb-3 <?= $paga ? 'opacity-75' : '' ?>">
                <div class="card-body py-3">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <div>
                            <span class="fw-bold">Parcela <?= $p['numero_parcela'] ?></span>
                            <?php if ($isPix && !$paga): ?><span class="badge bg-success ms-1"><i class="fas fa-qrcode me-1"></i>PIX</span><?php endif; ?>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <small class="text-muted">Venc: <?= date('d/m/Y', strtotime($p['vencimento'])) ?></small>
                            <span class="badge bg-<?= $corStatus ?>"><?= ucfirst(str_replace('_', ' ', $p['status'])) ?></span>
                        </div>
                    </div>

                    <?php if ($paga): ?>
                        <!-- Parcela paga: resumo simples -->
                        <div class="row text-center">
                            <div class="col-6">
                                <small class="text-muted">Produtos</small><br>
                                <span class="text-success"><i class="fas fa-check me-1"></i>R$ <?= number_format($p['valor_produtos'], 2, ',', '.') ?></span>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">Taxas</small><br>
                                <span class="text-success"><i class="fas fa-check me-1"></i>R$ <?= number_format($p['valor_taxas'], 2, ',', '.') ?></span>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Parcela pendente: mostrar pagamento -->
                        <div class="row">
                            <!-- Produtos (Câmbio Real) -->
                            <div class="col-md-6 mb-2">
                                <div class="border rounded p-3 text-center <?= $p['boleto_produtos_pago'] ? 'bg-light' : '' ?>">
                                    <small class="text-muted d-block mb-1">Produtos (Câmbio Real)</small>
                                    <span class="fs-5 fw-bold">R$ <?= number_format($p['valor_produtos'], 2, ',', '.') ?></span>
                                    <?php if ($p['boleto_produtos_pago']): ?>
                                        <div class="mt-1"><span class="badge bg-success"><i class="fas fa-check"></i> Pago</span></div>
                                    <?php elseif ($isPix && !empty($p['pix_produtos_payload'])): ?>
                                        <?php if (!empty($p['pix_produtos_qrcode'])): ?>
                                            <?php $qrSrc = (strpos(base64_decode(substr($p['pix_produtos_qrcode'],0,100)),'<svg')!==false) ? 'data:image/svg+xml;base64,' : 'data:image/png;base64,'; ?>
                                            <div class="my-2"><img src="<?= $qrSrc . htmlspecialchars($p['pix_produtos_qrcode']) ?>" alt="QR" style="width:180px;height:180px;image-rendering:pixelated;" class="img-fluid"></div>
                                        <?php endif; ?>
                                        <div class="input-group input-group-sm mt-1">
                                            <input type="text" class="form-control bg-light small" readonly value="<?= htmlspecialchars($p['pix_produtos_payload']) ?>" id="pix-p-<?= $p['id'] ?>">
                                            <button class="btn btn-outline-success" onclick="navigator.clipboard.writeText(document.getElementById('pix-p-<?= $p['id'] ?>').value);this.innerHTML='✓'"><i class="fas fa-copy"></i></button>
                                        </div>
                                    <?php elseif (!empty($p['boleto_produtos_url'])): ?>
                                        <div class="mt-2"><a href="<?= htmlspecialchars($p['boleto_produtos_url']) ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-barcode me-1"></i>Ver Boleto</a></div>
                                    <?php else: ?>
                                        <div class="mt-1"><span class="badge bg-warning text-dark">⏳ Pendente</span></div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Taxas (Câmbio Real Taxas) -->
                            <div class="col-md-6 mb-2">
                                <div class="border rounded p-3 text-center <?= $p['boleto_taxas_pago'] ? 'bg-light' : '' ?>">
                                    <small class="text-muted d-block mb-1">Taxas (Câmbio Real Taxas)</small>
                                    <span class="fs-5 fw-bold">R$ <?= number_format($p['valor_taxas'], 2, ',', '.') ?></span>
                                    <?php if ($p['boleto_taxas_pago']): ?>
                                        <div class="mt-1"><span class="badge bg-success"><i class="fas fa-check"></i> Pago</span></div>
                                    <?php elseif ($isPix && !empty($p['pix_taxas_payload'])): ?>
                                        <?php if (!empty($p['pix_taxas_qrcode'])): ?>
                                            <?php
                                            $qrRawT = $p['pix_taxas_qrcode'];
                                            if (stripos($qrRawT, 'data:image') === 0) {
                                                $qrImgSrcT = $qrRawT;
                                            } else {
                                                $qrSrcT = (strpos(base64_decode(substr($qrRawT,0,100)),'<svg')!==false) ? 'data:image/svg+xml;base64,' : 'data:image/png;base64,';
                                                $qrImgSrcT = $qrSrcT . $qrRawT;
                                            }
                                            ?>
                                            <div class="my-2"><img src="<?= htmlspecialchars($qrImgSrcT) ?>" alt="QR" style="width:180px;height:180px;image-rendering:pixelated;" class="img-fluid"></div>
                                        <?php else: ?>
                                            <div class="my-2" id="qr-taxas-<?= $p['numero'] ?? 0 ?>"></div>
                                            <script>
                                            (function(){
                                                var el = document.getElementById('qr-taxas-<?= $p['numero'] ?? 0 ?>');
                                                var payload = <?= json_encode($p['pix_taxas_payload'] ?? '') ?>;
                                                if (el && payload) {
                                                    var img = new Image(); img.alt='QR'; img.style.cssText='width:180px;height:180px';
                                                    img.src='https://api.qrserver.com/v1/create-qr-code/?size=180x180&data='+encodeURIComponent(payload);
                                                    el.appendChild(img);
                                                }
                                            })();
                                            </script>
                                        <?php endif; ?>
                                        <div class="input-group input-group-sm mt-1">
                                            <input type="text" class="form-control bg-light small" readonly value="<?= htmlspecialchars($p['pix_taxas_payload']) ?>" id="pix-t-<?= $p['id'] ?>">
                                            <button class="btn btn-outline-success" onclick="navigator.clipboard.writeText(document.getElementById('pix-t-<?= $p['id'] ?>').value);this.innerHTML='✓'"><i class="fas fa-copy"></i></button>
                                        </div>
                                    <?php elseif (!empty($p['boleto_taxas_url'])): ?>
                                        <div class="mt-2"><a href="<?= htmlspecialchars($p['boleto_taxas_url']) ?>" target="_blank" class="btn btn-sm btn-outline-primary"><i class="fas fa-barcode me-1"></i>Ver Boleto</a></div>
                                    <?php elseif ($p['valor_taxas'] > 0): ?>
                                        <div class="mt-1"><span class="badge bg-warning text-dark">⏳ Pendente</span></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <?php if ($p['status'] === 'pendente' && !$paga): ?>
                        <!-- Parcela futura: botão para antecipar pagamento -->
                        <div class="text-center mt-2">
                            <button class="btn btn-sm btn-success" onclick="anteciparParcela(<?= $p['id'] ?>, this)">
                                <i class="fas fa-forward me-1"></i>Antecipar pagamento desta parcela
                            </button>
                            <div class="small text-muted mt-1">Gera os boletos/PIX para pagar antes do vencimento</div>
                        </div>
                        <?php elseif ($isPix && $aberta): ?>
                        <div class="text-center mt-2">
                            <button class="btn btn-sm btn-outline-success" onclick="regerarPix(<?= $p['id'] ?>, this)">
                                <i class="fas fa-redo me-1"></i>Regerar QR Code PIX
                            </button>
                        </div>
                        <?php elseif (!$isPix && in_array($p['status'], ['aguardando_pagamento','vencida','em_atraso','reemitida'])): ?>
                        <div class="text-center mt-2">
                            <form method="POST" action="/carne/segunda-via/<?= $p['id'] ?>" class="d-inline">
                                <button type="submit" class="btn btn-sm btn-outline-warning"><i class="fas fa-redo me-1"></i>2ª Via Boleto</button>
                            </form>
                        </div>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<script>
function anteciparParcela(parcelaId, btn) {
    if (!confirm('Deseja antecipar o pagamento desta parcela? Os boletos/PIX serão gerados agora.')) return;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Gerando...';
    fetch('/carne/antecipar/' + parcelaId, { method: 'POST' })
        .then(r => r.json())
        .then(data => {
            if (data.success) { location.reload(); }
            else { alert(data.message || 'Erro ao antecipar parcela'); btn.disabled = false; btn.innerHTML = '<i class="fas fa-forward me-1"></i>Antecipar pagamento desta parcela'; }
        })
        .catch(() => { alert('Erro de conexão'); btn.disabled = false; btn.innerHTML = '<i class="fas fa-forward me-1"></i>Antecipar pagamento desta parcela'; });
}

function regerarPix(parcelaId, btn) {
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Gerando...';
    fetch('/carne/regerar-pix/' + parcelaId, { method: 'POST' })
        .then(r => r.json())
        .then(data => {
            if (data.success) { location.reload(); }
            else { alert(data.message || 'Erro'); btn.disabled = false; btn.innerHTML = '<i class="fas fa-redo me-1"></i>Regerar QR Code PIX'; }
        })
        .catch(() => { alert('Erro de conexão'); btn.disabled = false; btn.innerHTML = '<i class="fas fa-redo me-1"></i>Regerar QR Code PIX'; });
}
</script>

<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
