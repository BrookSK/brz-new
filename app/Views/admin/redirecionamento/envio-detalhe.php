<?php
$sidebarActive = 'redirecionamento-envios';
$title = __('admin.redirect.shipment_word', 'Envio') . ' #' . (int)($envio['id'] ?? 0);
$envio = $envio ?? [];
$produtos = is_array($produtos ?? null) ? $produtos : [];
$pagamentos = is_array($pagamentos ?? null) ? $pagamentos : [];
$stripePublicKey = $stripePublicKey ?? '';

$statusLabels = ['rascunho'=>[__('admin.redirect.status_draft','Rascunho'),'secondary'],'aguardando_pagamento'=>[__('admin.redirect.status_awaiting_payment','Aguard. pagamento'),'warning'],'pago'=>[__('admin.redirect.status_paid','Pago'),'success'],'etiqueta_gerada'=>[__('admin.redirect.status_label_generated','Etiqueta gerada'),'info'],'coletado'=>[__('admin.redirect.status_collected','Coletado'),'primary'],'entregue'=>[__('admin.redirect.status_delivered','Entregue'),'dark'],'divergencia'=>[__('admin.redirect.status_divergence','Divergência'),'danger'],'cancelado'=>[__('admin.redirect.status_cancelled','Cancelado'),'secondary']];
[$sl,$sc] = $statusLabels[$envio['status']??'rascunho'] ?? ['?','secondary'];
?>
<?php ob_start(); ?>
<div class="container-fluid p-4">
    <div class="d-flex align-items-center gap-3 mb-4 flex-wrap">
        <a href="/admin/redirecionamento/envios" class="btn btn-sm btn-outline-secondary"><i class="fas fa-arrow-left"></i></a>
        <div class="flex-fill">
            <h1 class="h2 mb-0"><?= __('admin.redirect.shipment_word', 'Envio') ?> #<?= (int)$envio['id'] ?></h1>
            <div class="text-muted small"><?= htmlspecialchars($envio['redirecionador_nome']??'',ENT_QUOTES,'UTF-8') ?> — <?= date('d/m/Y H:i', strtotime($envio['created_at']??'now')) ?></div>
        </div>
        <span class="badge bg-<?= $sc ?> bg-opacity-10 text-<?= $sc ?> border border-<?= $sc ?> border-opacity-25 fs-6"><?= $sl ?></span>
    </div>

    <div class="row g-4">
        <!-- Destinatário -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h5 class="mb-3"><i class="fas fa-user me-2 text-primary"></i><?= __('admin.redirect.recipient', 'Destinatário') ?></h5>
                    <dl class="row mb-0 small">
                        <dt class="col-5 text-muted"><?= __('admin.redirect.name', 'Nome') ?></dt><dd class="col-7"><?= htmlspecialchars($envio['destinatario_nome']??'',ENT_QUOTES,'UTF-8') ?></dd>
                        <dt class="col-5 text-muted">CPF</dt><dd class="col-7"><?= htmlspecialchars($envio['destinatario_cpf']??'',ENT_QUOTES,'UTF-8') ?></dd>
                        <dt class="col-5 text-muted"><?= __('admin.redirect.email', 'E-mail') ?></dt><dd class="col-7"><?= htmlspecialchars($envio['destinatario_email']??'',ENT_QUOTES,'UTF-8') ?></dd>
                        <dt class="col-5 text-muted"><?= __('admin.redirect.phone', 'Telefone') ?></dt><dd class="col-7"><?= htmlspecialchars($envio['destinatario_telefone']??'',ENT_QUOTES,'UTF-8') ?></dd>
                        <dt class="col-5 text-muted"><?= __('admin.redirect.address', 'Endereço') ?></dt>
                        <dd class="col-7"><?= htmlspecialchars(implode(', ', array_filter([$envio['dest_logradouro']??'',$envio['dest_numero']??'',$envio['dest_complemento']??'',$envio['dest_bairro']??'',$envio['dest_cidade']??'',$envio['dest_estado']??'',$envio['dest_cep']??''])),ENT_QUOTES,'UTF-8') ?></dd>
                    </dl>
                </div>
            </div>
        </div>

        <!-- Envio -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <h5 class="mb-3"><i class="fas fa-box me-2 text-primary"></i><?= __('admin.redirect.shipment_data', 'Dados do envio') ?></h5>
                    <dl class="row mb-0 small">
                        <dt class="col-5 text-muted"><?= __('admin.redirect.client_order_id', 'ID pedido cliente') ?></dt><dd class="col-7"><?= htmlspecialchars($envio['id_pedido_cliente']??'',ENT_QUOTES,'UTF-8') ?></dd>
                        <dt class="col-5 text-muted"><?= __('admin.redirect.declared_weight', 'Peso informado') ?></dt><dd class="col-7"><?= number_format((float)($envio['peso_kg']??0),3,',','.') ?> kg</dd>
                        <dt class="col-5 text-muted"><?= __('admin.redirect.real_weight', 'Peso real') ?></dt><dd class="col-7"><?= $envio['peso_real_kg'] ? number_format((float)$envio['peso_real_kg'],3,',','.').' kg' : '—' ?></dd>
                        <dt class="col-5 text-muted"><?= __('admin.redirect.dimensions_wxhxl', 'Dimensões (L×A×C)') ?></dt><dd class="col-7"><?= number_format((float)($envio['largura_cm']??0),1,',','.') ?> × <?= number_format((float)($envio['altura_cm']??0),1,',','.') ?> × <?= number_format((float)($envio['comprimento_cm']??0),1,',','.') ?> cm</dd>
                        <dt class="col-5 text-muted"><?= __('admin.redirect.charged_amount', 'Valor cobrado') ?></dt><dd class="col-7 fw-bold">US$ <?= number_format((float)($envio['valor_cobrado_usd']??0),2,',','.') ?></dd>
                        <dt class="col-5 text-muted"><?= __('admin.redirect.correct_amount', 'Valor correto') ?></dt><dd class="col-7"><?= $envio['valor_correto_usd'] ? 'US$ '.number_format((float)$envio['valor_correto_usd'],2,',','.') : '—' ?></dd>
                        <dt class="col-5 text-muted"><?= __('admin.redirect.tracking', 'Rastreio') ?></dt><dd class="col-7"><?= htmlspecialchars($envio['tracking_code']??'',ENT_QUOTES,'UTF-8') ?: '—' ?></dd>
                    </dl>
                </div>
            </div>
        </div>

        <!-- Produtos -->
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="mb-3"><i class="fas fa-list me-2 text-primary"></i><?= __('admin.redirect.products', 'Produtos') ?></h5>
                    <?php if (empty($produtos)): ?>
                    <p class="text-muted small mb-0"><?= __('admin.redirect.no_products_registered', 'Nenhum produto cadastrado.') ?></p>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light"><tr><th>NCM</th><th><?= __('admin.redirect.description', 'Descrição') ?></th><th><?= __('admin.redirect.price_usd', 'Preço (USD)') ?></th><th><?= __('admin.redirect.weight_kg', 'Peso (kg)') ?></th><th><?= __('admin.redirect.qty', 'Qtd') ?></th></tr></thead>
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
                    <h5 class="mb-3"><i class="fas fa-weight me-2 text-primary"></i><?= __('admin.redirect.update_real_weight_dimensions', 'Atualizar peso/dimensões reais') ?></h5>
                    <div class="row g-2">
                        <div class="col-6"><label class="form-label small"><?= __('admin.redirect.real_weight_kg', 'Peso real (kg)') ?></label><input class="form-control form-control-sm" type="number" step="0.001" id="pesoReal" value="<?= htmlspecialchars($envio['peso_real_kg']??'',ENT_QUOTES,'UTF-8') ?>"></div>
                        <div class="col-6"><label class="form-label small"><?= __('admin.redirect.real_width_cm', 'Largura real (cm)') ?></label><input class="form-control form-control-sm" type="number" step="0.1" id="largReal" value="<?= htmlspecialchars($envio['largura_real_cm']??'',ENT_QUOTES,'UTF-8') ?>"></div>
                        <div class="col-6"><label class="form-label small"><?= __('admin.redirect.real_height_cm', 'Altura real (cm)') ?></label><input class="form-control form-control-sm" type="number" step="0.1" id="altReal" value="<?= htmlspecialchars($envio['altura_real_cm']??'',ENT_QUOTES,'UTF-8') ?>"></div>
                        <div class="col-6"><label class="form-label small"><?= __('admin.redirect.real_length_cm', 'Comprimento real (cm)') ?></label><input class="form-control form-control-sm" type="number" step="0.1" id="compReal" value="<?= htmlspecialchars($envio['comprimento_real_cm']??'',ENT_QUOTES,'UTF-8') ?>"></div>
                        <div class="col-12"><button type="button" class="btn btn-primary btn-sm w-100" id="btnSalvarPeso"><?= __('admin.redirect.save_and_check_divergence', 'Salvar e verificar divergência') ?></button></div>
                        <div id="msgPeso" class="col-12"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Etiqueta / tracking -->
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <h5 class="mb-3"><i class="fas fa-tag me-2 text-primary"></i><?= __('admin.redirect.label_and_tracking', 'Etiqueta e rastreio') ?></h5>

                    <?php $__pagamentoConfirmado = (strtolower($envio['status_pagamento'] ?? '') === 'pago'); ?>
                    <?php if ($__pagamentoConfirmado && empty($envio['tracking_code'])): ?>
                    <!-- Botão gerar etiqueta (disponível após pagamento) -->
                    <div class="mb-3">
                        <button type="button" class="btn btn-success w-100" id="btnGerarEtiqueta">
                            <i class="fas fa-shipping-fast me-2"></i><?= __('admin.redirect.generate_label', 'Gerar Etiqueta') ?>
                        </button>
                        <div id="msgGerarEtiqueta" class="mt-2"></div>
                    </div>
                    <hr>
                    <?php elseif ($__pagamentoConfirmado && !empty($envio['tracking_code'])): ?>
                    <!-- Botão regerar etiqueta -->
                    <div class="mb-3">
                        <button type="button" class="btn btn-outline-warning btn-sm" id="btnGerarEtiqueta">
                            <i class="fas fa-redo me-2"></i><?= __('admin.redirect.regenerate_label_corrected', 'Regerar Etiqueta (dados corrigidos)') ?>
                        </button>
                        <div id="msgGerarEtiqueta" class="mt-2"></div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($envio['tracking_code'])): ?>
                    <div class="alert alert-success py-2 mb-3">
                        <i class="fas fa-check-circle me-2"></i>
                        <strong><?= __('admin.redirect.tracking_colon', 'Rastreio:') ?></strong> <?= htmlspecialchars($envio['tracking_code'], ENT_QUOTES, 'UTF-8') ?>
                        <?php if (!empty($envio['etiqueta_provedor'])): ?>
                        <span class="badge bg-info ms-2"><?= strtoupper($envio['etiqueta_provedor']) ?></span>
                        <?php endif; ?>
                        <?php
                        // Detectar tipo de serviço (SEDEX/PAC) pelo código usado na etiqueta
                        $__servicoLabel = '';
                        $__servicoBadge = 'secondary';
                        if (!empty($envio['etiqueta_request_json'])) {
                            $__reqData = json_decode($envio['etiqueta_request_json'], true);
                            $__codSvc = (string) ($__reqData['codigoServico'] ?? ($__reqData['service_code'] ?? ''));
                            if ($__codSvc !== '') {
                                $__mapServicos = [
                                    '03220' => 'SEDEX', '04162' => 'SEDEX', '04014' => 'SEDEX',
                                    '03298' => 'PAC', '04510' => 'PAC', '41106' => 'PAC',
                                    '03158' => 'SEDEX 10', '03140' => 'SEDEX 12', '03204' => 'SEDEX Hoje',
                                ];
                                $__servicoLabel = $__mapServicos[$__codSvc] ?? (__('admin.redirect.code_prefix', 'Cód:') . ' ' . $__codSvc);
                                $__servicoBadge = (stripos($__servicoLabel, 'SEDEX') !== false) ? 'danger' : 'primary';
                            }
                        }
                        ?>
                        <?php if ($__servicoLabel !== ''): ?>
                        <span class="badge bg-<?= $__servicoBadge ?> ms-1"><?= $__servicoLabel ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($envio['etiqueta_url']) || !empty($envio['wexpress_label_url'])): ?>
                    <div class="mb-3">
                        <?php if (($envio['etiqueta_provedor'] ?? '') === 'correios_wordpress' && !empty($envio['wp_post_id_etiqueta'])): ?>
                        <a href="/admin/redirecionamento/envios/baixar-etiqueta?envio_id=<?= (int)$envio['id'] ?>" target="_blank" class="btn btn-outline-primary w-100">
                            <i class="fas fa-print me-2"></i><?= __('admin.redirect.print_download_label', 'Imprimir / Baixar Etiqueta') ?> (PACKET)
                        </a>
                        <?php else: ?>
                        <a href="<?= htmlspecialchars($envio['wexpress_label_url'] ?? $envio['etiqueta_url'], ENT_QUOTES, 'UTF-8') ?>" target="_blank" class="btn btn-outline-primary w-100">
                            <i class="fas fa-print me-2"></i><?= __('admin.redirect.print_download_label', 'Imprimir / Baixar Etiqueta') ?>
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>

                    <?php
                    $__perfilLogado = strtolower(trim((string)($_SESSION['usuario_perfil'] ?? $_SESSION['usuario_role'] ?? '')));
                    $__isAdminEtiqueta = in_array($__perfilLogado, ['admin','suporte'], true);
                    ?>
                    <?php if ($__isAdminEtiqueta): ?>
                    <hr class="my-3">
                    <div class="small text-muted mb-2"><?= __('admin.redirect.manual_fill_admin', 'Preenchimento manual (admin)') ?></div>
                    <div class="row g-2">
                        <div class="col-12"><label class="form-label small"><?= __('admin.redirect.tracking_code', 'Código de rastreio') ?></label><input class="form-control form-control-sm" type="text" id="trackingCode" value="<?= htmlspecialchars($envio['tracking_code']??'',ENT_QUOTES,'UTF-8') ?>"></div>
                        <div class="col-12">
                            <label class="form-label small"><?= __('admin.redirect.label_upload_pdf_image', 'Etiqueta (upload PDF/imagem)') ?></label>
                            <input class="form-control form-control-sm" type="file" id="inputEtiquetaUpload" accept=".pdf,.jpg,.jpeg,.png">
                            <?php if (!empty($envio['etiqueta_url'])): ?>
                            <div class="form-text"><?= __('admin.redirect.current_colon', 'Atual:') ?> <a href="<?= htmlspecialchars($envio['etiqueta_url'],ENT_QUOTES,'UTF-8') ?>" target="_blank"><?= __('admin.redirect.view_label', 'Ver etiqueta') ?></a></div>
                            <?php endif; ?>
                        </div>
                        <div class="col-12"><button type="button" class="btn btn-info btn-sm w-100" id="btnSalvarTracking"><?= __('admin.redirect.save_and_notify', 'Salvar e notificar') ?></button></div>
                        <div id="msgTracking" class="col-12"></div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Status / ações rápidas -->
        <div class="col-12">
            <div class="card border-0 shadow-sm">
                <div class="card-body d-flex gap-2 flex-wrap">
                    <button type="button" class="btn btn-outline-primary btn-sm" id="btnMarcarColetado"><?= __('admin.redirect.mark_as_collected', 'Marcar como coletado') ?></button>
                    <button type="button" class="btn btn-outline-success btn-sm" id="btnMarcarEntregue"><?= __('admin.redirect.mark_as_delivered', 'Marcar como entregue') ?></button>
                    <a href="/admin/redirecionamento/coletas" class="btn btn-outline-secondary btn-sm"><i class="fas fa-calendar me-1"></i><?= __('admin.redirect.view_collections', 'Ver coletas') ?></a>
                </div>
            </div>
        </div>

        <!-- Pagamentos -->
        <?php if (strtolower($envio['status_pagamento'] ?? '') === 'pendente' && strtolower($envio['status'] ?? '') === 'aguardando_pagamento'): ?>
        <div class="col-12">
            <div class="card border-0 shadow-sm border-warning">
                <div class="card-body">
                    <h5 class="mb-3"><i class="fas fa-credit-card me-2 text-warning"></i><?= __('admin.redirect.payment_pending', 'Pagamento Pendente') ?></h5>
                    <div class="alert alert-warning py-2 mb-3">
                        <i class="fas fa-exclamation-triangle me-2"></i><?= __('admin.redirect.shipment_awaits_payment_of', 'Este envio aguarda pagamento de') ?> <strong>US$ <?= number_format((float)($envio['valor_cobrado_usd']??0),2,',','.') ?></strong>
                    </div>

                    <?php
                    $perfilLogado = strtolower(trim((string)($_SESSION['usuario_perfil'] ?? $_SESSION['usuario_role'] ?? '')));
                    $isAdmin = in_array($perfilLogado, ['admin','suporte'], true);
                    ?>
                    <?php if ($isAdmin): ?>
                    <div class="mb-3">
                        <button type="button" class="btn btn-outline-success btn-sm" id="btnMarcarPagoAdmin">
                            <i class="fas fa-check-circle me-2"></i><?= __('admin.redirect.mark_as_paid_admin', 'Marcar como Pago (admin)') ?>
                        </button>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($stripePublicKey)): ?>
                    <div id="stripePayContainer">
                        <div id="cardElementDetalhe" class="form-control p-3 mb-3"></div>
                        <button type="button" class="btn btn-success w-100" id="btnPagarDetalhe">
                            <i class="fas fa-lock me-2"></i><?= __('admin.redirect.pay', 'Pagar') ?> US$ <?= number_format((float)($envio['valor_cobrado_usd']??0),2,',','.') ?>
                        </button>
                        <div id="msgPagDetalhe" class="mt-2"></div>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-info py-2"><?= __('admin.redirect.stripe_not_configured_send_receipt', 'Pagamento via Stripe não configurado. Envie o comprovante abaixo.') ?></div>
                    <?php endif; ?>
                    <div class="mt-3">
                        <label class="form-label small"><?= __('admin.redirect.upload_payment_receipt', 'Upload do comprovante de pagamento') ?></label>
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
                    <h5 class="mb-3"><i class="fas fa-credit-card me-2 text-primary"></i><?= __('admin.redirect.payments', 'Pagamentos') ?></h5>
                    <div class="table-responsive">
                        <table class="table table-sm align-middle mb-0">
                            <thead class="table-light"><tr><th><?= __('admin.redirect.type', 'Tipo') ?></th><th><?= __('admin.redirect.value_usd', 'Valor (USD)') ?></th><th><?= __('admin.redirect.status', 'Status') ?></th><th><?= __('admin.redirect.paid_on', 'Pago em') ?></th><th><?= __('admin.redirect.receipt', 'Comprovante') ?></th></tr></thead>
                            <tbody>
                                <?php foreach ($pagamentos as $p):
                                    $psc = ['pendente'=>'warning','pago'=>'success','falhou'=>'danger'][$p['status']??'pendente']??'secondary';
                                ?>
                                <tr>
                                    <td><?= ucfirst($p['tipo']??'') ?></td>
                                    <td>US$ <?= number_format((float)($p['valor_usd']??0),2,',','.') ?></td>
                                    <td><span class="badge bg-<?= $psc ?> bg-opacity-10 text-<?= $psc ?> border border-<?= $psc ?> border-opacity-25"><?= ucfirst($p['status']??'') ?></span></td>
                                    <td><?= $p['pago_em'] ? date('d/m/Y H:i', strtotime($p['pago_em'])) : '—' ?></td>
                                    <td><?= !empty($p['comprovante_url']) ? '<a href="'.htmlspecialchars($p['comprovante_url'],ENT_QUOTES,'UTF-8').'" target="_blank">' . __('admin.redirect.view', 'Ver') . '</a>' : '—' ?></td>
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
        const tipo = dif > 0 ? '<?= htmlspecialchars(__('admin.redirect.charge_lc', 'cobrança'), ENT_QUOTES, 'UTF-8') ?>' : (dif < 0 ? '<?= htmlspecialchars(__('admin.redirect.refund_lc', 'reembolso'), ENT_QUOTES, 'UTF-8') ?>' : '');
        msg.innerHTML = `<div class="alert alert-${Math.abs(dif)>0.01?'warning':'success'} py-1 small mt-2"><?= htmlspecialchars(__('admin.redirect.correct_amount_colon', 'Valor correto:'), ENT_QUOTES, 'UTF-8') ?> US$ ${j.valor_correto.toFixed(2)}. ${Math.abs(dif)>0.01?'<?= htmlspecialchars(__('admin.redirect.divergence_of', 'Divergência de'), ENT_QUOTES, 'UTF-8') ?> US$ '+Math.abs(dif).toFixed(2)+' ('+tipo+') <?= htmlspecialchars(__('admin.redirect.generated_fem', 'gerada.'), ENT_QUOTES, 'UTF-8') ?>':'<?= htmlspecialchars(__('admin.redirect.no_divergence', 'Sem divergência.'), ENT_QUOTES, 'UTF-8') ?>'}</div>`;
        if (Math.abs(dif) > 0.01) setTimeout(()=>location.reload(), 1500);
    } else { msg.innerHTML = `<div class="alert alert-danger py-1 small mt-2">${j.msg||'<?= htmlspecialchars(__('admin.redirect.error', 'Erro'), ENT_QUOTES, 'UTF-8') ?>'}</div>`; }
});

document.getElementById('btnSalvarTracking')?.addEventListener('click', async () => {
    const fd = new FormData();
    fd.append('tracking_code', document.getElementById('trackingCode').value);
    const fileInput = document.getElementById('inputEtiquetaUpload');
    if (fileInput && fileInput.files[0]) {
        fd.append('etiqueta_file', fileInput.files[0]);
    }
    const r = await fetch(`/admin/redirecionamento/envios/${ENVIO_ID}/tracking`,{method:'POST',body:fd});
    const j = await r.json();
    document.getElementById('msgTracking').innerHTML = j.ok ? '<div class="alert alert-success py-1 small mt-2"><?= htmlspecialchars(__('admin.redirect.saved_and_notifications_sent', 'Salvo e notificações enviadas.'), ENT_QUOTES, 'UTF-8') ?></div>' : '<div class="alert alert-danger py-1 small mt-2">'+(j.msg||'<?= htmlspecialchars(__('admin.redirect.error', 'Erro'), ENT_QUOTES, 'UTF-8') ?>')+'</div>';
    if (j.ok) setTimeout(()=>location.reload(),1500);
});

// Gerar etiqueta via API
document.getElementById('btnGerarEtiqueta')?.addEventListener('click', async () => {
    const btn = document.getElementById('btnGerarEtiqueta');
    const msg = document.getElementById('msgGerarEtiqueta');
    const isRegen = <?= !empty($envio['tracking_code']) ? 'true' : 'false' ?>;
    const confirmMsg = isRegen
        ? '<?= htmlspecialchars(__('admin.redirect.confirm_regenerate_label', 'Regerar etiqueta? A etiqueta anterior será substituída por uma nova com os dados atuais.'), ENT_QUOTES, 'UTF-8') ?>'
        : '<?= htmlspecialchars(__('admin.redirect.confirm_generate_label', 'Gerar etiqueta para este envio? Após gerar, imprima e cole na caixa.'), ENT_QUOTES, 'UTF-8') ?>';
    if (!confirm(confirmMsg)) return;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i><?= htmlspecialchars(__('admin.redirect.generating_label', 'Gerando etiqueta...'), ENT_QUOTES, 'UTF-8') ?>';
    msg.innerHTML = '';
    try {
        const fd = new FormData();
        fd.append('envio_id', ENVIO_ID);
        const r = await fetch('/admin/redirecionamento/envios/gerar-etiqueta', {method:'POST', body:fd});
        const j = await r.json();
        if (j.ok) {
            msg.innerHTML = '<div class="alert alert-success py-2"><i class="fas fa-check-circle me-2"></i>' + (j.msg||'<?= htmlspecialchars(__('admin.redirect.label_generated', 'Etiqueta gerada!'), ENT_QUOTES, 'UTF-8') ?>') + (j.tracking ? '<br><strong><?= htmlspecialchars(__('admin.redirect.tracking_colon', 'Rastreio:'), ENT_QUOTES, 'UTF-8') ?></strong> '+j.tracking : '') + '</div>';
            setTimeout(() => location.reload(), 2000);
        } else {
            msg.innerHTML = '<div class="alert alert-danger py-2"><i class="fas fa-exclamation-circle me-2"></i>' + (j.msg||'<?= htmlspecialchars(__('admin.redirect.error_generating_label', 'Erro ao gerar etiqueta'), ENT_QUOTES, 'UTF-8') ?>') + '</div>';
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-shipping-fast me-2"></i><?= htmlspecialchars(__('admin.redirect.generate_label', 'Gerar Etiqueta'), ENT_QUOTES, 'UTF-8') ?>';
        }
    } catch (e) {
        msg.innerHTML = '<div class="alert alert-danger py-2"><?= htmlspecialchars(__('admin.redirect.network_error_try_again', 'Erro de rede. Tente novamente.'), ENT_QUOTES, 'UTF-8') ?></div>';
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-shipping-fast me-2"></i><?= htmlspecialchars(__('admin.redirect.generate_label', 'Gerar Etiqueta'), ENT_QUOTES, 'UTF-8') ?>';
    }
});

document.getElementById('btnMarcarColetado')?.addEventListener('click', async () => {
    if (!confirm('<?= htmlspecialchars(__('admin.redirect.confirm_mark_collected', 'Marcar como coletado?'), ENT_QUOTES, 'UTF-8') ?>')) return;
    const fd = new FormData();
    await fetch(`/admin/redirecionamento/envios/${ENVIO_ID}/coletado`,{method:'POST',body:fd});
    location.reload();
});

document.getElementById('btnMarcarEntregue')?.addEventListener('click', async () => {
    if (!confirm('<?= htmlspecialchars(__('admin.redirect.confirm_mark_delivered', 'Marcar como entregue?'), ENT_QUOTES, 'UTF-8') ?>')) return;
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
        btn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i><?= htmlspecialchars(__('admin.redirect.processing', 'Processando...'), ENT_QUOTES, 'UTF-8') ?>';
        msg.innerHTML = '';

        // Criar payment intent
        const fd = new FormData();
        fd.append('envio_id', ENVIO_ID);
        const r = await fetch('/admin/redirecionamento/pagamento/criar-intent', {method:'POST', body:fd});
        const j = await r.json();
        if (!j.ok) {
            msg.innerHTML = '<div class="alert alert-danger py-2">' + (j.msg||'<?= htmlspecialchars(__('admin.redirect.error', 'Erro'), ENT_QUOTES, 'UTF-8') ?>') + '</div>';
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-lock me-2"></i><?= htmlspecialchars(__('admin.redirect.pay', 'Pagar'), ENT_QUOTES, 'UTF-8') ?>';
            return;
        }

        // Confirmar pagamento
        const {paymentIntent, error} = await stripeDet.confirmCardPayment(j.client_secret, {payment_method:{card:cardDet}});
        if (error) {
            msg.innerHTML = '<div class="alert alert-danger py-2">' + error.message + '</div>';
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-lock me-2"></i><?= htmlspecialchars(__('admin.redirect.pay', 'Pagar'), ENT_QUOTES, 'UTF-8') ?>';
            return;
        }

        // Confirmar no backend
        const fd2 = new FormData();
        fd2.append('envio_id', ENVIO_ID);
        fd2.append('payment_intent_id', paymentIntent.id);
        const r2 = await fetch('/admin/redirecionamento/pagamento/confirmar', {method:'POST', body:fd2});
        const j2 = await r2.json();
        msg.innerHTML = j2.ok
            ? '<div class="alert alert-success py-2"><i class="fas fa-check me-2"></i><?= htmlspecialchars(__('admin.redirect.payment_confirmed', 'Pagamento confirmado!'), ENT_QUOTES, 'UTF-8') ?></div>'
            : '<div class="alert alert-warning py-2"><?= htmlspecialchars(__('admin.redirect.payment_processed_awaiting', 'Pagamento processado, aguardando confirmação.'), ENT_QUOTES, 'UTF-8') ?></div>';
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
        el.innerHTML = '<i class="fas fa-check me-2"></i><?= htmlspecialchars(__('admin.redirect.receipt_sent', 'Comprovante enviado.'), ENT_QUOTES, 'UTF-8') ?>';
        this.parentNode.appendChild(el);
    }
});

// Marcar como pago (admin)
document.getElementById('btnMarcarPagoAdmin')?.addEventListener('click', async () => {
    if (!confirm('<?= htmlspecialchars(__('admin.redirect.confirm_mark_paid_manually', 'Marcar este envio como PAGO manualmente? Use apenas se o pagamento foi confirmado por outro meio.'), ENT_QUOTES, 'UTF-8') ?>')) return;
    const fd = new FormData();
    fd.append('envio_id', ENVIO_ID);
    const r = await fetch('/admin/redirecionamento/envios/marcar-pago', {method:'POST', body:fd});
    const j = await r.json();
    if (j.ok) { location.reload(); }
    else { alert(j.msg || '<?= htmlspecialchars(__('admin.redirect.error', 'Erro'), ENT_QUOTES, 'UTF-8') ?>'); }
});
</script>
<?php $content = ob_get_clean(); include __DIR__ . '/../../layouts/admin.php'; ?>
