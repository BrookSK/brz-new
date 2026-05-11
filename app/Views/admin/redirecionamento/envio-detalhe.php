<?php
$sidebarActive = 'redirecionamento-envios';
$title = 'Envio #' . (int)($envio['id'] ?? 0);
$envio = $envio ?? [];
$produtos = is_array($produtos ?? null) ? $produtos : [];
$pagamentos = is_array($pagamentos ?? null) ? $pagamentos : [];
$stripePublicKey = $stripePublicKey ?? '';

$statusLabels = ['rascunho'=>['Rascunho','secondary'],'aguardando_pagamento'=>['Aguard. pagamento','warning'],'pago'=>['Pago','success'],'etiqueta_gerada'=>['Etiqueta gerada','info'],'coletado'=>['Coletado','primary'],'entregue'=>['Entregue','dark'],'divergencia'=>['Divergência','danger'],'cancelado'=>['Cancelado','secondary']];
[$sl,$sc] = $statusLabels[$envio['status']??'rascunho'] ?? ['?','secondary'];
?>
<?php ob_start(); ?>
<div class="container-fluid p-4">
    <div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
        <a href="/admin/redirecionamento/envios" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left"></i></a>
        <div class="flex-fill">
            <h1 class="h2 mb-0">Envio #<?= (int)$envio['id'] ?></h1>
            <div class="text-muted small"><?= htmlspecialchars($envio['redirecionador_nome']??'',ENT_QUOTES,'UTF-8') ?> — <?= date('d/m/Y H:i', strtotime($envio['created_at']??'now')) ?></div>
        </div>
        <span class="badge bg-<?= $sc ?> bg-opacity-10 text-<?= $sc ?> border border-<?= $sc ?> border-opacity-25 fs-6"><?= $sl ?></span>
    </div>

    <div class="row g-4">
        <!-- Destinatário -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h5 class="mb-3"><i class="fas fa-user me-2 text-primary"></i>Destinatário</h5>
                    <dl class="row mb-0 small">
                        <dt class="col-5 text-muted">Nome</dt><dd class="col-7"><?= htmlspecialchars($envio['destinatario_nome']??'',ENT_QUOTES,'UTF-8') ?></dd>
                        <dt class="col-5 text-muted">CPF</dt><dd class="col-7"><?= htmlspecialchars($envio['destinatario_cpf']??'',ENT_QUOTES,'UTF-8') ?></dd>
                        <dt class="col-5 text-muted">E-mail</dt><dd class="col-7"><?= htmlspecialchars($envio['destinatario_email']??'',ENT_QUOTES,'UTF-8') ?></dd>
                        <dt class="col-5 text-muted">Telefone</dt><dd class="col-7"><?= htmlspecialchars($envio['destinatario_telefone']??'',ENT_QUOTES,'UTF-8') ?></dd>
                        <dt class="col-5 text-muted">Endereço</dt>
                        <dd class="col-7"><?= htmlspecialchars(implode(', ', array_filter([$envio['dest_logradouro']??'',$envio['dest_numero']??'',$envio['dest_complemento']??'',$envio['dest_bairro']??'',$envio['dest_cidade']??'',$envio['dest_estado']??'',$envio['dest_cep']??''])),ENT_QUOTES,'UTF-8') ?></dd>
                    </dl>
                </div>
            </div>
        </div>

        <!-- Envio -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h5 class="mb-3"><i class="fas fa-box me-2 text-primary"></i>Dados do envio</h5>
                    <dl class="row mb-0 small">
                        <dt class="col-5 text-muted">ID pedido cliente</dt><dd class="col-7"><?= htmlspecialchars($envio['id_pedido_cliente']??'',ENT_QUOTES,'UTF-8') ?></dd>
                        <dt class="col-5 text-muted">Peso informado</dt><dd class="col-7"><?= number_format((float)($envio['peso_kg']??0),3,',','.') ?> kg</dd>
                        <dt class="col-5 text-muted">Peso real</dt><dd class="col-7"><?= $envio['peso_real_kg'] ? number_format((float)$envio['peso_real_kg'],3,',','.').' kg' : '—' ?></dd>
                        <dt class="col-5 text-muted">Dimensões (L×A×C)</dt><dd class="col-7"><?= number_format((float)($envio['largura_cm']??0),1,',','.') ?> × <?= number_format((float)($envio['altura_cm']??0),1,',','.') ?> × <?= number_format((float)($envio['comprimento_cm']??0),1,',','.') ?> cm</dd>
                        <dt class="col-5 text-muted">Valor cobrado</dt><dd class="col-7 fw-bold">US$ <?= number_format((float)($envio['valor_cobrado_usd']??0),2,',','.') ?></dd>
                        <dt class="col-5 text-muted">Valor correto</dt><dd class="col-7"><?= $envio['valor_correto_usd'] ? 'US$ '.number_format((float)$envio['valor_correto_usd'],2,',','.') : '—' ?></dd>
                        <dt class="col-5 text-muted">Rastreio</dt><dd class="col-7"><?= htmlspecialchars($envio['tracking_code']??'',ENT_QUOTES,'UTF-8') ?: '—' ?></dd>
                    </dl>
                </div>
            </div>
        </div>

        <!-- Produtos -->
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="mb-3"><i class="fas fa-list me-2 text-primary"></i>Produtos</h5>
                    <?php if (empty($produtos)): ?>
                    <p class="text-muted small mb-0">Nenhum produto cadastrado.</p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light"><tr><th>NCM</th><th>Descrição</th><th>Preço (USD)</th><th>Peso (kg)</th><th>Qtd</th></tr></thead>
                            <tbody>
                                <?php foreach ($produtos as $p): ?>
                                <tr>
                                    <td><?= htmlspecialchars($p['ncm']??'',ENT_QUOTES,'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($p['descricao']??'',ENT_QUOTES,'UTF-8') ?></td>
                                    <td>US$ <?= number_format((float)($p['preco_usd']??0),2,',','.') ?></td>
                                    <td><?= number_format((float)($p['peso_kg']??0),3,',','.') ?></td>
                                    <td><?= (int)($p['quantidade']??1) ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Ações admin -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="mb-3"><i class="fas fa-weight me-2 text-primary"></i>Atualizar peso/dimensões reais</h5>
                    <div class="row g-2">
                        <div class="col-6"><label class="form-label small">Peso real (kg)</label><input class="form-control form-control-sm" type="number" step="0.001" id="pesoReal" value="<?= htmlspecialchars($envio['peso_real_kg']??'',ENT_QUOTES,'UTF-8') ?>"></div>
                        <div class="col-6"><label class="form-label small">Largura real (cm)</label><input class="form-control form-control-sm" type="number" step="0.1" id="largReal" value="<?= htmlspecialchars($envio['largura_real_cm']??'',ENT_QUOTES,'UTF-8') ?>"></div>
                        <div class="col-6"><label class="form-label small">Altura real (cm)</label><input class="form-control form-control-sm" type="number" step="0.1" id="altReal" value="<?= htmlspecialchars($envio['altura_real_cm']??'',ENT_QUOTES,'UTF-8') ?>"></div>
                        <div class="col-6"><label class="form-label small">Comprimento real (cm)</label><input class="form-control form-control-sm" type="number" step="0.1" id="compReal" value="<?= htmlspecialchars($envio['comprimento_real_cm']??'',ENT_QUOTES,'UTF-8') ?>"></div>
                        <div class="col-12"><button type="button" class="btn btn-primary btn-sm w-100" id="btnSalvarPeso">Salvar e verificar divergência</button></div>
                        <div id="msgPeso" class="col-12"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Etiqueta / tracking -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="mb-3"><i class="fas fa-tag me-2 text-primary"></i>Etiqueta e rastreio</h5>

                    <?php if (empty($envio['tracking_code']) && strtolower($envio['status_pagamento'] ?? '') === 'pago'): ?>
                    <!-- Botão gerar etiqueta (disponível após pagamento) -->
                    <div class="mb-3">
                        <button type="button" class="btn btn-success w-100" id="btnGerarEtiqueta">
                            <i class="fas fa-shipping-fast me-2"></i>Gerar Etiqueta
                        </button>
                        <div id="msgGerarEtiqueta" class="mt-2"></div>
                    </div>
                    <hr>
                    <?php endif; ?>

                    <?php if (!empty($envio['tracking_code'])): ?>
                    <div class="alert alert-success py-2 mb-3">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong>Rastreio:</strong> <?= htmlspecialchars($envio['tracking_code'], ENT_QUOTES, 'UTF-8') ?>
                        <?php if (!empty($envio['etiqueta_provedor'])): ?>
                        <span class="badge bg-info ms-2"><?= strtoupper($envio['etiqueta_provedor']) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($envio['etiqueta_url']) || !empty($envio['wexpress_label_url'])): ?>
                    <div class="mb-3">
                        <a href="<?= htmlspecialchars($envio['wexpress_label_url'] ?? $envio['etiqueta_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" class="btn btn-outline-primary w-100">
                            <i class="fas fa-print me-2"></i>Imprimir / Baixar Etiqueta
                        </a>
                    </div>
                    <?php endif; ?>

                    <div class="row g-2">
                        <div class="col-12"><label class="form-label small">Código de rastreio</label><input class="form-control form-control-sm" type="text" id="trackingCode" value="<?= htmlspecialchars($envio['tracking_code']??'',ENT_QUOTES,'UTF-8') ?>"></div>
                        <div class="col-12"><label class="form-label small">URL da etiqueta</label><input class="form-control form-control-sm" type="text" id="etiquetaUrl" value="<?= htmlspecialchars($envio['etiqueta_url']??'',ENT_QUOTES,'UTF-8') ?>"></div>
                        <div class="col-12"><button type="button" class="btn btn-info btn-sm w-100" id="btnSalvarTracking">Salvar e notificar</button></div>
                        <div id="msgTracking" class="col-12"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Status / ações rápidas -->
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex gap-2 flex-wrap">
                    <button type="button" class="btn btn-outline-primary btn-sm" id="btnMarcarColetado">Marcar como coletado</button>
                    <button type="button" class="btn btn-outline-success btn-sm" id="btnMarcarEntregue">Marcar como entregue</button>
                    <a href="/admin/redirecionamento/coletas" class="btn btn-outline-secondary btn-sm"><i class="fas fa-calendar me-1"></i>Ver coletas</a>
                </div>
            </div>
        </div>

        <!-- Pagamentos -->
        <?php if (strtolower($envio['status_pagamento'] ?? '') === 'pendente' && strtolower($envio['status'] ?? '') === 'aguardando_pagamento'): ?>
        <div class="col-12">
            <div class="card border-0 shadow-sm border-warning">
                <div class="card-body">
                    <h5 class="mb-3"><i class="fas fa-credit-card me-2 text-warning"></i>Pagamento Pendente</h5>
                    <div class="alert alert-warning py-2 mb-3">
                        <i class="fas fa-exclamation-triangle me-2"></i>Este envio aguarda pagamento de <strong>US$ <?= number_format((float)($envio['valor_cobrado_usd']??0),2,',','.') ?></strong>
                    </div>
                    <?php if (!empty($stripePublicKey)): ?>
                    <div id="stripePayContainer">
                        <div id="cardElementDetalhe" class="form-control p-3 mb-3"></div>
                        <button type="button" class="btn btn-success w-100" id="btnPagarDetalhe">
                            <i class="fas fa-lock me-2"></i>Pagar US$ <?= number_format((float)($envio['valor_cobrado_usd']??0),2,',','.') ?>
                        </button>
                        <div id="msgPagDetalhe" class="mt-2"></div>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info py-2">Pagamento via Stripe não configurado. Envie o comprovante abaixo.</div>
                    <?php endif; ?>
                    <div class="mt-3">
                        <label class="form-label small">Upload do comprovante de pagamento</label>
                        <input type="file" class="form-control form-control-sm" id="inputComprovanteDetalhe" accept=".jpg,.jpeg,.png,.pdf">
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!empty($pagamentos)): ?>
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="mb-3"><i class="fas fa-credit-card me-2 text-primary"></i>Pagamentos</h5>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light"><tr><th>Tipo</th><th>Valor (USD)</th><th>Status</th><th>Pago em</th><th>Comprovante</th></tr></thead>
                            <tbody>
                                <?php foreach ($pagamentos as $p):
                                    $psc = ['pendente'=>'warning','pago'=>'success','falhou'=>'danger'][$p['status']??'pendente']??'secondary';
                                ?>
                                <tr>
                                    <td><?= ucfirst($p['tipo']??'') ?></td>
                                    <td>US$ <?= number_format((float)($p['valor_usd']??0),2,',','.') ?></td>
                                    <td><span class="badge bg-<?= $psc ?> bg-opacity-10 text-<?= $psc ?> border border-<?= $psc ?> border-opacity-25"><?= ucfirst($p['status']??'') ?></span></td>
                                    <td><?= $p['pago_em'] ? date('d/m/Y H:i', strtotime($p['pago_em'])) : '—' ?></td>
                                    <td><?= !empty($p['comprovante_url']) ? '<a href="'.htmlspecialchars($p['comprovante_url'],ENT_QUOTES,'UTF-8').'" target="_blank">Ver</a>' : '—' ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($stripePublicKey) && strtolower($envio['status_pagamento'] ?? '') === 'pendente'): ?>
<script src="https://js.stripe.com/v3/"></script>
<?php endif; ?>

<script>
const ENVIO_ID = <?= (int)($envio['id']??0) ?>;

document.getElementById('btnSalvarPeso')?.addEventListener('click', async () => {
    const fd = new FormData();
    fd.append('peso_real_kg', document.getElementById('pesoReal').value);
    fd.append('largura_real_cm', document.getElementById('largReal').value);
    fd.append('altura_real_cm', document.getElementById('altReal').value);
    fd.append('comprimento_real_cm', document.getElementById('compReal').value);
    const r = await fetch(`/admin/redirecionamento/envios/${ENVIO_ID}/peso-real`,{method:'POST',body:fd});
    const j = await r.json();
    const msg = document.getElementById('msgPeso');
    if (j.ok) {
        const dif = j.diferenca;
        const tipo = dif > 0 ? 'cobrança' : (dif < 0 ? 'reembolso' : '');
        msg.innerHTML = `<div class="alert alert-${Math.abs(dif)>0.01?'warning':'success'} py-1 small mt-2">Valor correto: US$ ${j.valor_correto.toFixed(2)}. ${Math.abs(dif)>0.01?'Divergência de US$ '+Math.abs(dif).toFixed(2)+' ('+tipo+') gerada.':'Sem divergência.'}</div>`;
        if (Math.abs(dif) > 0.01) setTimeout(()=>location.reload(), 1500);
    } else { msg.innerHTML = `<div class="alert alert-danger py-1 small mt-2">${j.msg||'Erro'}</div>`; }
});

document.getElementById('btnSalvarTracking')?.addEventListener('click', async () => {
    const fd = new FormData();
    fd.append('tracking_code', document.getElementById('trackingCode').value);
    fd.append('etiqueta_url', document.getElementById('etiquetaUrl').value);
    const r = await fetch(`/admin/redirecionamento/envios/${ENVIO_ID}/tracking`,{method:'POST',body:fd});
    const j = await r.json();
    document.getElementById('msgTracking').innerHTML = j.ok ? '<div class="alert alert-success py-1 small mt-2">Salvo e notificações enviadas.</div>' : '<div class="alert alert-danger py-1 small mt-2">'+(j.msg||'Erro')+'</div>';
    if (j.ok) setTimeout(()=>location.reload(),1500);
});

// Gerar etiqueta via API
document.getElementById('btnGerarEtiqueta')?.addEventListener('click', async () => {
    const btn = document.getElementById('btnGerarEtiqueta');
    const msg = document.getElementById('msgGerarEtiqueta');
    if (!confirm('Gerar etiqueta para este envio? Após gerar, imprima e cole na caixa.')) return;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Gerando etiqueta...';
    msg.innerHTML = '';
    try {
        const fd = new FormData();
        fd.append('envio_id', ENVIO_ID);
        const r = await fetch('/admin/redirecionamento/envios/gerar-etiqueta', {method:'POST', body:fd});
        const j = await r.json();
        if (j.ok) {
            msg.innerHTML = '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-2"></i>' + (j.msg||'Etiqueta gerada!') + (j.tracking ? '<br><strong>Rastreio:</strong> '+j.tracking : '') + '</div>';
            setTimeout(() => location.reload(), 2000);
        } else {
            msg.innerHTML = '<div class="alert alert-danger py-2"><i class="fas fa-exclamation-circle me-2"></i>' + (j.msg||'Erro ao gerar etiqueta') + '</div>';
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-shipping-fast me-2"></i>Gerar Etiqueta';
        }
    } catch (e) {
        msg.innerHTML = '<div class="alert alert-danger py-2">Erro de rede. Tente novamente.</div>';
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-shipping-fast me-2"></i>Gerar Etiqueta';
    }
});

document.getElementById('btnMarcarColetado')?.addEventListener('click', async () => {
    if (!confirm('Marcar como coletado?')) return;
    const fd = new FormData();
    await fetch(`/admin/redirecionamento/envios/${ENVIO_ID}/coletado`,{method:'POST',body:fd});
    location.reload();
});

document.getElementById('btnMarcarEntregue')?.addEventListener('click', async () => {
    if (!confirm('Marcar como entregue?')) return;
    const fd = new FormData();
    await fetch(`/admin/redirecionamento/envios/${ENVIO_ID}/entregue`,{method:'POST',body:fd});
    location.reload();
});

// ── Pagamento Stripe na tela de detalhe ──
<?php if (!empty($stripePublicKey) && strtolower($envio['status_pagamento'] ?? '') === 'pendente'): ?>
(function(){
    const stripeDet = Stripe(<?= json_encode($stripePublicKey) ?>);
    const elementsDet = stripeDet.elements();
    const cardDet = elementsDet.create('card', {style:{base:{fontSize:'16px'}}});
    const container = document.getElementById('cardElementDetalhe');
    if (container) cardDet.mount('#cardElementDetalhe');

    document.getElementById('btnPagarDetalhe')?.addEventListener('click', async () => {
        const btn = document.getElementById('btnPagarDetalhe');
        const msg = document.getElementById('msgPagDetalhe');
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Processando...';
        msg.innerHTML = '';

        // Criar payment intent
        const fd = new FormData();
        fd.append('envio_id', ENVIO_ID);
        const r = await fetch('/admin/redirecionamento/pagamento/criar-intent', {method:'POST', body:fd});
        const j = await r.json();
        if (!j.ok) {
            msg.innerHTML = '<div class="alert alert-danger py-2">' + (j.msg||'Erro') + '</div>';
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-lock me-2"></i>Pagar';
            return;
        }

        // Confirmar pagamento
        const {paymentIntent, error} = await stripeDet.confirmCardPayment(j.client_secret, {payment_method:{card:cardDet}});
        if (error) {
            msg.innerHTML = '<div class="alert alert-danger py-2">' + error.message + '</div>';
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-lock me-2"></i>Pagar';
            return;
        }

        // Confirmar no backend
        const fd2 = new FormData();
        fd2.append('envio_id', ENVIO_ID);
        fd2.append('payment_intent_id', paymentIntent.id);
        const r2 = await fetch('/admin/redirecionamento/pagamento/confirmar', {method:'POST', body:fd2});
        const j2 = await r2.json();
        msg.innerHTML = j2.ok
            ? '<div class="alert alert-success py-2"><i class="fas fa-check me-2"></i>Pagamento confirmado!</div>'
            : '<div class="alert alert-warning py-2">Pagamento processado, aguardando confirmação.</div>';
        btn.style.display = 'none';
        setTimeout(() => location.reload(), 2000);
    });
})();
<?php endif; ?>

// Upload comprovante na tela de detalhe
document.getElementById('inputComprovanteDetalhe')?.addEventListener('change', async function() {
    if (!this.files[0]) return;
    const fd = new FormData();
    fd.append('comprovante', this.files[0]);
    fd.append('envio_id', ENVIO_ID);
    fd.append('tipo', 'envio');
    const r = await fetch('/admin/redirecionamento/comprovantes/upload', {method:'POST', body:fd});
    const j = await r.json();
    if (j.ok) {
        const el = document.createElement('div');
        el.className = 'alert alert-success mt-2 py-1 small';
        el.innerHTML = '<i class="fas fa-check me-2"></i>Comprovante enviado.';
        this.parentNode.appendChild(el);
    }
});
</script>
<?php $content = ob_get_clean(); include __DIR__ . '/../../layouts/admin.php'; ?>
