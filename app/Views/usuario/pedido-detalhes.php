<?php
ob_start();
use App\Core\Url;

function getStatusColor($status) {
    $colors = [
        'pendente' => ['bg' => 'rgba(245, 158, 11, 0.14)', 'border' => 'rgba(245, 158, 11, 0.35)', 'color' => 'rgba(124, 45, 18, 1)'],
        'processando' => ['bg' => 'rgba(56, 189, 248, 0.12)', 'border' => 'rgba(56, 189, 248, 0.22)', 'color' => 'rgba(11, 31, 58, 1)'],
        'enviado' => ['bg' => 'rgba(56, 189, 248, 0.12)', 'border' => 'rgba(56, 189, 248, 0.22)', 'color' => 'rgba(11, 31, 58, 1)'],
        'entregue' => ['bg' => 'rgba(16, 185, 129, 0.10)', 'border' => 'rgba(16, 185, 129, 0.18)', 'color' => 'rgba(6, 78, 59, 1)'],
        'cancelado' => ['bg' => 'rgba(239, 68, 68, 0.10)', 'border' => 'rgba(239, 68, 68, 0.18)', 'color' => 'rgba(185, 28, 28, 1)'],
        'pago' => ['bg' => 'rgba(16, 185, 129, 0.10)', 'border' => 'rgba(16, 185, 129, 0.18)', 'color' => 'rgba(6, 78, 59, 1)']
    ];

    $statusKey = is_string($status) ? strtolower($status) : '';
    return $colors[$statusKey] ?? ['bg' => 'rgba(148, 163, 184, 0.18)', 'border' => 'rgba(148, 163, 184, 0.35)', 'color' => 'rgba(15, 23, 42, 0.82)'];
}

function getStatusText($status) {
    $statusKey = is_string($status) ? strtolower($status) : '';
    $map = [
        'pendente'                       => 'order_status.pending',
        'processando'                    => 'order_status.processing',
        'pago'                           => 'order_status.paid',
        'produto_consolidado'            => 'order_status.product_consolidated',
        'itens_comprados'                => 'order_status.items_purchased',
        'itens_parcialmente_comprados'   => 'order_status.items_partially_purchased',
        'etiqueta_gerada'                => 'order_status.label_generated',
        'enviado'                        => 'order_status.label_generated',
        'em_transporte'                  => 'order_status.in_transit',
        'aguardando_liberacao_aduaneira' => 'order_status.awaiting_customs_release',
        'enviado_ao_destinatario'        => 'order_status.sent_to_recipient',
        'entregue'                       => 'order_status.delivered',
        'cancelado'                      => 'order_status.cancelled',

        // legado/aliases
        'pedido_criado'                  => 'order_status.created',
        'aguardando_processamento'       => 'order_status.awaiting_processing',
        'consolidado'                    => 'order_status.consolidated',
        'rascunho_etiqueta'              => 'order_status.label_draft',
        'etiqueta_efetivada'             => 'order_status.label_effective',
        'aguardando_lib_alfandegaria'    => 'order_status.awaiting_customs_release',
        'finalizacao_embalagem'          => 'order_status.packaging_finalization',
        'entrega_finalizada'             => 'order_status.delivery_completed',
    ];

    if (isset($map[$statusKey])) {
        $fallback = is_string($status) ? ucfirst($status) : '';
        return __($map[$statusKey], $fallback);
    }

    return is_string($status) ? ucfirst($status) : '';
}

function formatStatusLabel($status) {
    $raw = is_string($status) ? trim($status) : '';
    if ($raw === '') {
        return '';
    }

    $txt = getStatusText($raw);
    if (is_string($txt) && $txt !== '' && $txt !== $raw && $txt !== ucfirst($raw)) {
        return $txt;
    }

    $pretty = str_replace(['_', '-'], ' ', strtolower($raw));
    $pretty = preg_replace('/\s+/', ' ', $pretty);
    $pretty = trim((string) $pretty);
    if ($pretty === '') {
        return $raw;
    }
    return ucwords($pretty);
}

$badgePedido = getStatusColor($pedido['status'] ?? '');
$badgePedidoLabel = formatStatusLabel((string) ($pedido['status'] ?? ''));
?>

<div class="container py-4">
    <script>
        window.ORDER_DETAILS_I18N = {
            copy: <?= json_encode(__('common.copy', 'Copiar'), JSON_UNESCAPED_UNICODE) ?>,
            copied: <?= json_encode(__('common.copied', 'Copiado'), JSON_UNESCAPED_UNICODE) ?>
        };
    </script>

    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2 mb-4">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <a href="/meus-pedidos" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-arrow-left"></i>
                    <?= __('common.back', 'Voltar') ?>
                </a>
                <h1 class="h4 mb-0"><?= __('user_order_details.order_title', 'Pedido #{id}', ['id' => htmlspecialchars($pedido['codigo_pedido'] ?? $pedido['id'])]) ?></h1>
            </div>
            <div class="text-muted small"><?= __('user_order_details.date', 'Data:') ?> <?= !empty($pedido['created_at']) ? date('d/m/Y \à\s H:i', strtotime($pedido['created_at'])) : '-' ?></div>
            <?php if (!empty($pedido['origem_pedido'])): ?>
                <div class="text-muted small">
                    <?= __('user_order_details.origin', 'Origem:') ?> <strong><?= htmlspecialchars((string) $pedido['origem_pedido']) ?></strong>
                    <?php if (!empty($pedido['admin_criador_nome']) || !empty($pedido['admin_criador_email'])): ?>
                        <span class="ms-1">(<?= __('user_order_details.admin', 'Admin:') ?> <?= htmlspecialchars((string) ($pedido['admin_criador_nome'] ?? '')) ?><?= !empty($pedido['admin_criador_email']) ? ' &lt;' . htmlspecialchars((string) $pedido['admin_criador_email']) . '&gt;' : '' ?>)</span>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </div>
        <div class="d-flex align-items-center gap-2">
            <a href="/pedido/detalhes/<?= (int) ($pedido['id'] ?? 0) ?>/pdf" class="btn btn-sm btn-outline-dark" target="_blank" rel="noopener">
                <i class="fas fa-file-pdf"></i>
                <?= __('user_order_details.export_pdf', 'Exportar PDF') ?>
            </a>
            <span class="badge" style="background: <?= $badgePedido['bg'] ?>; border: 1px solid <?= $badgePedido['border'] ?>; color: <?= $badgePedido['color'] ?>;">
                <?= htmlspecialchars($badgePedidoLabel !== '' ? $badgePedidoLabel : getStatusText($pedido['status'] ?? '')) ?>
            </span>
        </div>
    </div>

        <!-- Main Content -->
    <div class="row g-4">
        <?php $activePage = 'pedidos'; include __DIR__ . '/../partials/usuario_sidebar.php'; ?>
                
                <!-- Main Content -->
        <div class="col-lg-9">
            <div class="d-flex flex-column gap-4">
                    <!-- Status Timeline -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-truck me-2"></i> <?= __('user_order_details.status_timeline', 'Status do Pedido') ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php
                            $trk = trim((string) ($pedido['tracking_code'] ?? ''));
                            $trkFonte = trim((string) ($pedido['tracking_source'] ?? ''));
                            $trkUrl = trim((string) ($pedido['tracking_label_url'] ?? ''));
                            ?>
                            <?php if ($trk !== ''): ?>
                                <div class="alert alert-light border mb-3">
                                    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-2">
                                        <div>
                                            <strong><?= __('user_order_details.tracking_code', 'Código de rastreio:') ?></strong> <?= htmlspecialchars($trk) ?>
                                        </div>
                                        <div class="d-flex gap-2">
                                            <a class="btn btn-sm btn-primary" href="/rastreamento?codigo=<?= rawurlencode($trk) ?>" target="_blank" rel="noopener">
                                                <i class="fas fa-route me-1"></i>
                                                <?= __('user_order_details.track_order', 'Acompanhar rastreio') ?>
                                            </a>
                                        </div>
                                    </div>
                                    <?php if ($trkFonte !== ''): ?>
                                        <div class="small text-muted"><?= __('user_order_details.tracking_source', 'Fonte:') ?> <?= htmlspecialchars($trkFonte) ?></div>
                                    <?php endif; ?>
                                    <?php if ($trkUrl !== ''): ?>
                                        <div class="small mt-1"><a href="<?= htmlspecialchars($trkUrl) ?>" target="_blank" rel="noopener"><?= __('user_order_details.view_label', 'Ver etiqueta') ?></a></div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            <div class="timeline">
                                <?php if (!empty($historico)): ?>
                                    <?php foreach ($historico as $index => $item): ?>
                                        <div class="timeline-item">
                                            <div class="timeline-marker">
                                                <i class="fas fa-check-circle text-success"></i>
                                            </div>
                                            <div class="timeline-content">
                                                <?php
                                                $rawSt = $item['novo_status'] ?? ($item['status_novo'] ?? ($item['etapa'] ?? ($item['status'] ?? '')));
                                                $labelSt = formatStatusLabel((string) $rawSt);
                                                if ($labelSt === '') {
                                                    $labelSt = __('user_order_details.status_updated', 'Status atualizado');
                                                }

                                                $dtRaw = (string) ($item['created_at'] ?? '');
                                                $dtOut = '-';
                                                if (trim($dtRaw) !== '') {
                                                    $ts = strtotime($dtRaw);
                                                    if ($ts !== false) {
                                                        $dtOut = date('d/m/Y H:i', $ts);
                                                    }
                                                }
                                                ?>
                                                <h6><?= htmlspecialchars($labelSt) ?></h6>
                                                <p class="mb-0"><?= htmlspecialchars($item['observacao'] ?? __('user_order_details.no_notes', 'Sem observação')) ?></p>
                                                <small><?= htmlspecialchars($dtOut) ?> - <?= __('user_order_details.by', 'Por:') ?> <?= htmlspecialchars($item['usuario_alterou'] ?? __('user_order_details.system', 'Sistema')) ?></small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <?= __('user_order_details.no_status_history', 'Nenhum histórico de status disponível para este pedido.') ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Order Items -->
                    <div class="card border-0 shadow-sm">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-box me-2"></i> <?= __('user_order_details.items_title', 'Itens do Pedido') ?>
                                <span class="badge ms-2" style="background: rgba(11, 31, 58, 0.08); border: 1px solid rgba(11, 31, 58, 0.14); color: rgba(11, 31, 58, 1);">
                                    <?= __('user_order_details.items_count', '{count} itens', ['count' => count($pedido['items'])]) ?>
                                </span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php
                            $moedaPedido = strtoupper((string) ($pedido['moeda'] ?? 'BRL'));
                            if ($moedaPedido === '') $moedaPedido = 'BRL';
                            $simboloMoeda = ($moedaPedido === 'BRL') ? 'R$' : 'US$';
                            // Taxa de conversão para converter itens USD→BRL quando pedido é BRL
                            $taxaConvView = (float) ($pedido['taxa_conversao'] ?? 0);
                            if ($taxaConvView <= 1.01) $taxaConvView = 5.85;
                            // Detectar se é carnê (itens podem estar em USD mesmo com pedido BRL)
                            $isCarnePedido = (strtolower(trim((string) ($pedido['forma_pagamento'] ?? ''))) === 'carne_braziliana'
                                || in_array(strtolower(trim((string) ($pedido['status'] ?? ''))), ['carne_pagando', 'carne_aguardando'], true));
                            $itensConvertidos = !empty($pedido['__converted_items_to_brl']);

                            // Verificar flag no pedido_meta (só carnês novos que usaram preço cheio)
                            $precisaConverterView = false;
                            $mostrarAvisoCarne = false;
                            if ($isCarnePedido && $moedaPedido === 'BRL') {
                                try {
                                    $dbMeta = \Config\Database::getConnection();
                                    $stMeta = $dbMeta->prepare("SELECT meta_value FROM pedido_meta WHERE pedido_id = ? AND meta_key = 'carne_usou_preco_original' LIMIT 1");
                                    $stMeta->execute([(int) ($pedido['id'] ?? 0)]);
                                    $flagOriginal = ((string) $stMeta->fetchColumn() === '1');
                                    if ($flagOriginal) {
                                        $mostrarAvisoCarne = true;
                                    }
                                } catch (\Exception $e) {}
                            }
                            ?>
                            <?php if ($mostrarAvisoCarne): ?>
                                <div class="alert alert-info small mb-3">
                                    <i class="fas fa-info-circle me-1"></i>
                                    <strong><?= __('order_detail.carne_full_price_title', 'Carnê Braziliana — Valor original') ?>:</strong>
                                    <?= __('order_detail.carne_full_price_msg', 'Os produtos neste pedido foram cobrados pelo valor original (sem promoção), pois promoções podem não estar vigentes durante todo o período de parcelamento.') ?>
                                </div>
                            <?php endif; ?>
                            <?php if (!empty($pedido['items'])): ?>
                                <?php foreach ($pedido['items'] as $item): ?>
                                    <div class="product-item">
                                        <?php
                                            $imgRaw = (string) ($item['imagem'] ?? '');
                                            if ($imgRaw === '') {
                                                $imgRaw = 'placeholder.jpg';
                                            }
                                            $imgSrc = '';
                                            if (preg_match('#^https?://#i', $imgRaw) || strpos($imgRaw, '//') === 0) {
                                                $imgSrc = (strpos($imgRaw, '//') === 0) ? ('https:' . $imgRaw) : $imgRaw;
                                            } else {
                                                $imgFile = trim($imgRaw);
                                                if ($imgFile === '' || $imgFile === 'placeholder.jpg') {
                                                    $imgSrc = Url::absolute('/uploads/produtos/placeholder.jpg');
                                                } else {
                                                    if (strpos($imgFile, '/uploads/') === 0) {
                                                        $imgSrc = Url::absolute($imgFile);
                                                    } else {
                                                        $imgFile = ltrim($imgFile, '/');
                                                        if (strpos($imgFile, 'uploads/') === 0) {
                                                            $imgSrc = Url::absolute('/' . $imgFile);
                                                        } else {
                                                            $imgSrc = Url::absolute('/uploads/produtos/' . $imgFile);
                                                        }
                                                    }
                                                }
                                            }
                                        ?>
                                        <img src="<?= htmlspecialchars($imgSrc) ?>" 
                                             alt="<?= htmlspecialchars($item['nome_produto'] ?? __('user_order_details.product', 'Produto')) ?>" 
                                             class="product-image" onerror="this.onerror=null;this.src='<?= htmlspecialchars(Url::absolute('/uploads/produtos/placeholder.jpg')) ?>';">
                                        <div class="product-info flex-grow-1">
                                            <h6><?= htmlspecialchars($item['nome_produto'] ?? __('user_order_details.product_without_name', 'Produto sem nome')) ?></h6>
                                            <small class="text-muted"><?= htmlspecialchars($item['referencia'] ?? '') ?></small>
                                            <?php if (!empty($item['variacao_descricao'])): ?>
                                                <div class="small text-muted"><?= htmlspecialchars((string) $item['variacao_descricao'], ENT_QUOTES, 'UTF-8') ?></div>
                                            <?php endif; ?>
                                            <?php
                                            // Mostrar status de compra do item (se pedido está em compra parcial/total)
                                            $statusPedido = strtolower(trim((string) ($pedido['status'] ?? '')));
                                            if (in_array($statusPedido, ['itens_parcialmente_comprados', 'itens_comprados'])) {
                                                $itemCompraStatus = $item['compra_status'] ?? null;
                                                if ($itemCompraStatus === 'comprado') {
                                                    echo '<span class="badge bg-success mt-1"><i class="fas fa-check me-1"></i>' . __('order_item.purchased', 'Comprado') . '</span>';
                                                } elseif ($itemCompraStatus === 'pendente') {
                                                    echo '<span class="badge bg-warning text-dark mt-1"><i class="fas fa-clock me-1"></i>' . __('order_item.pending_purchase', 'Aguardando compra') . '</span>';
                                                }
                                            }
                                            ?>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="quantity-badge"><?= $item['quantidade'] ?? 1 ?></span>
                                            <span class="text-muted">x</span>
                                            <?php
                                            $pu = (float) ($item['preco_unitario'] ?? 0);
                                            ?>
                                            <span class="fw-bold"><?= $simboloMoeda ?> <?= number_format($pu, 2, ',', '.') ?></span>
                                        </div>
                                        <div class="text-end">
                                            <?php
                                            $st = (float) ($item['subtotal'] ?? 0);
                                            ?>
                                            <small class="text-muted"><?= __('checkout.subtotal', 'Subtotal') ?>: <?= $simboloMoeda ?> <?= number_format($st, 2, ',', '.') ?></small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    <?= __('user_order_details.no_items', 'Nenhum item encontrado neste pedido.') ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Order Summary -->
                    <div class="row g-4">
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-map-marker-alt me-2"></i> <?= __('checkout.shipping_address', 'Endereço de Entrega') ?>
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <?php
                                    $destNome = (string) ($pedido['destinatario_nome'] ?? '');
                                    $destDoc = (string) ($pedido['destinatario_documento'] ?? '');
                                    $destTel = (string) ($pedido['destinatario_telefone'] ?? '');
                                    ?>
                                    <?php if ($destNome !== '' || $destDoc !== '' || $destTel !== ''): ?>
                                        <div class="mb-3">
                                            <div class="fw-semibold"><?= __('checkout.recipient', 'Destinatário') ?></div>
                                            <div class="text-muted small">
                                                <?= htmlspecialchars($destNome !== '' ? $destNome : __('common.not_informed', 'Não informado')) ?>
                                                <?php if ($destDoc !== ''): ?>
                                                    <span class="ms-1">(<?= htmlspecialchars($destDoc) ?>)</span>
                                                <?php endif; ?>
                                                <?php if ($destTel !== ''): ?>
                                                    <span class="ms-1">• <?= htmlspecialchars($destTel) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endif; ?>

                                    <?php
                                    $linha1 = trim((string) ($pedido['endereco_entrega'] ?? ''));
                                    $num = trim((string) ($pedido['numero_entrega'] ?? ''));
                                    $comp = trim((string) ($pedido['complemento_entrega'] ?? ''));
                                    $bairro = trim((string) ($pedido['bairro_entrega'] ?? ''));
                                    $cidade = trim((string) ($pedido['cidade_entrega'] ?? ''));
                                    $estado = trim((string) ($pedido['estado_entrega'] ?? ''));
                                    $cep = trim((string) ($pedido['cep_entrega'] ?? ''));
                                    $linha1Fmt = $linha1;
                                    if ($linha1Fmt !== '' && $num !== '') {
                                        $linha1Fmt .= ', ' . $num;
                                    }
                                    ?>
                                    <address class="mb-0">
                                        <?= htmlspecialchars($linha1Fmt !== '' ? $linha1Fmt : __('common.not_informed', 'Não informado')) ?><br>
                                        <?php if ($comp !== ''): ?><?= htmlspecialchars($comp) ?><br><?php endif; ?>
                                        <?php if ($bairro !== ''): ?><?= htmlspecialchars($bairro) ?><br><?php endif; ?>
                                        <?= htmlspecialchars($cidade) ?><?= ($estado !== '' ? (' - ' . htmlspecialchars($estado)) : '') ?><br>
                                        <?= ($cep !== '' ? (__('user_order_details.cep_zip', 'CEP/ZIP:') . ' ' . htmlspecialchars($cep)) : '') ?>
                                    </address>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card border-0 shadow-sm">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-calculator me-2"></i> <?= __('user_order_details.summary', 'Resumo do Pedido') ?>
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="price-row">
                                        <span><?= __('checkout.subtotal', 'Subtotal') ?>:</span>
                                        <?php
                                        $sub = (float) ($pedido['subtotal_produtos'] ?? 0);
                                        ?>
                                        <span><?= $simboloMoeda ?> <?= number_format($sub, 2, ',', '.') ?></span>
                                    </div>
                                    <div class="price-row">
                                        <span><?= __('checkout.shipping', 'Frete') ?>:</span>
                                        <?php $freteVal = (float) ($pedido['valor_frete'] ?? 0); ?>
                                        <?php
                                        $freteUsd = $freteVal;
                                        ?>
                                        <span><?= ($freteVal <= 0 ? __('checkout.free_shipping', 'Frete grátis') : ($simboloMoeda . ' ' . number_format($freteUsd, 2, ',', '.'))) ?></span>
                                    </div>
                                    <div class="price-row">
                                        <span><?= __('checkout.service_fee', 'Taxa de Serviço') ?>:</span>
                                        <?php if (((float) ($pedido['taxa_servico_desconto_aplicado'] ?? 0)) > 0): ?>
                                            <span class="text-decoration-line-through text-muted"><?= $simboloMoeda ?> <?= number_format($pedido['taxa_servico_original'] ?? 0, 2, ',', '.') ?></span>
                                        <?php else: ?>
                                            <span><?= $simboloMoeda ?> <?= number_format($pedido['taxa_servico'] ?? 0, 2, ',', '.') ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (((float) ($pedido['taxa_servico_desconto_aplicado'] ?? 0)) > 0): ?>
                                    <div class="price-row small">
                                        <span class="text-success"><i class="fas fa-tags me-1"></i><?= __('cart.promo_discount', 'Desconto promocional') ?></span>
                                        <span class="text-success">-<?= $simboloMoeda ?> <?= number_format($pedido['taxa_servico_desconto_aplicado'] ?? 0, 2, ',', '.') ?></span>
                                    </div>
                                    <div class="price-row small">
                                        <span class="fw-semibold"><?= __('cart.service_fee_final', 'Taxa de serviço final') ?></span>
                                        <span class="fw-semibold"><?= $simboloMoeda ?> <?= number_format($pedido['taxa_servico'] ?? 0, 2, ',', '.') ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (((float) ($pedido['valor_impostos'] ?? 0)) > 0): ?>
                                    <div class="price-row">
                                        <span><?= __('checkout.brazil_taxes', 'Impostos do Brasil') ?>:</span>
                                        <span><?= $simboloMoeda ?> <?= number_format($pedido['valor_impostos'] ?? 0, 2, ',', '.') ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (((float) ($pedido['imposto_local'] ?? 0)) > 0): ?>
                                    <div class="price-row">
                                        <span><?= __('checkout.local_tax', 'Imposto local') ?>:</span>
                                        <span><?= $simboloMoeda ?> <?= number_format((float) $pedido['imposto_local'], 2, ',', '.') ?></span>
                                    </div>
                                    <?php endif; ?>
                                    <hr>
                                    <div class="price-row">
                                        <span><?= __('checkout.total', 'Total') ?>:</span>
                                        <span class="text-primary"><?= $simboloMoeda ?> <?= number_format($pedido['valor_total'] ?? 0, 2, ',', '.') ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($paymentDetails) || !empty($pedido['pagamento_gateway']) || !empty($pedido['payment_gateway'])): ?>
                    <div class="card border-0 shadow-sm">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-credit-card me-2"></i> <?= __('checkout.payment', 'Pagamento') ?>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php
                            $statusPagamento = $pedido['pagamento_status'] ?? ($pedido['payment_status'] ?? ($paymentDetails['status'] ?? null));
                            if (is_string($statusPagamento)) {
                                $statusPagamento = strtoupper($statusPagamento);
                            }

                            $badgeClass = 'bg-warning text-dark';
                            $statusLabel = __('checkout.payment_status.awaiting', 'Aguardando');
                            $isPago = false;
                            if (!empty($statusPagamento)) {
                                if (in_array($statusPagamento, ['APPROVED', 'CONFIRMED', 'RECEIVED', 'PAID', 'SUCCEEDED', 'SUCCESS'], true)) {
                                    $badgeClass = 'bg-success';
                                    $statusLabel = __('checkout.payment_status.paid', 'Pago');
                                    $isPago = true;
                                } elseif (in_array($statusPagamento, ['REJECTED', 'CANCELED', 'CANCELLED', 'DELETED'], true)) {
                                    $badgeClass = 'bg-danger';
                                    $statusLabel = __('checkout.payment_status.cancelled', 'Cancelado');
                                } elseif (in_array($statusPagamento, ['REFUNDED'], true)) {
                                    $badgeClass = 'bg-secondary';
                                    $statusLabel = __('checkout.payment_status.refunded', 'Estornado');
                                }
                            }

                            $billingType = strtoupper((string) ($paymentDetails['billingType'] ?? ''));
                            $invoiceUrl = $paymentDetails['invoiceUrl'] ?? null;
                            $bankSlipUrl = $paymentDetails['bankSlipUrl'] ?? null;
                            $digitableLine = $paymentDetails['digitableLine'] ?? null;
                            ?>

                            <p class="mb-2 text-muted">
                                <small><?= __('checkout.payment_status.label', 'Status do pagamento:') ?> <span class="badge" style="background: rgba(148, 163, 184, 0.18); border: 1px solid rgba(148, 163, 184, 0.35); color: rgba(15, 23, 42, 0.82);"><?= htmlspecialchars($statusLabel) ?></span></small>
                            </p>

                            <?php if (!$isPago && $billingType === 'PIX' && !empty($pixQrCode)): ?>
                                <?php $pixImage = $pixQrCode['encodedImage'] ?? null; ?>
                                <?php $pixPayload = $pixQrCode['payload'] ?? null; ?>
                                <?php if (!empty($pixImage)): ?>
                                    <div class="text-center my-3">
                                        <img src="data:image/png;base64,<?= $pixImage ?>" alt="<?= htmlspecialchars(__('checkout_done.pix_qr_code', 'QR Code PIX'), ENT_QUOTES, 'UTF-8') ?>" style="max-width: 240px; width: 100%; height: auto;" />
                                    </div>
                                <?php endif; ?>
                                <?php if (!empty($pixPayload)): ?>
                                    <div class="mb-2">
                                        <strong><?= __('checkout_done.copy_and_paste', 'Copia e cola:') ?></strong>
                                        <div class="input-group">
                                            <input id="pix-payload" type="text" class="form-control" value="<?= htmlspecialchars($pixPayload) ?>" readonly onclick="this.select();" />
                                            <button type="button" class="btn btn-outline-dark" onclick="copiarPixPayload('pix-payload','pix-copied', this)"><?= __('common.copy', 'Copiar') ?></button>
                                        </div>
                                        <div id="pix-copied" class="small text-success mt-1" style="display:none;"><?= __('common.copied_success', 'Copiado!') ?></div>
                                    </div>
                                <?php endif; ?>
                            <?php elseif ($billingType === 'BOLETO'): ?>
                                <?php if (!empty($bankSlipUrl) || !empty($invoiceUrl)): ?>
                                    <p class="mb-2">
                                        <a class="btn btn-outline-primary" href="<?= htmlspecialchars($bankSlipUrl ?: $invoiceUrl) ?>" target="_blank" rel="noopener"><?= __('checkout_done.open_boleto', 'Abrir boleto') ?></a>
                                    </p>
                                <?php endif; ?>
                                <?php if (!empty($digitableLine)): ?>
                                    <div class="mb-2">
                                        <strong><?= __('checkout_done.digitable_line', 'Linha digitável:') ?></strong>
                                        <input type="text" class="form-control" value="<?= htmlspecialchars($digitableLine) ?>" readonly onclick="this.select();" />
                                    </div>
                                <?php endif; ?>
                            <?php elseif (!$isPago && $billingType === 'CREDIT_CARD'): ?>
                                <?php if (!empty($invoiceUrl)): ?>
                                    <p class="mb-2">
                                        <a class="btn btn-outline-primary" href="<?= htmlspecialchars($invoiceUrl) ?>" target="_blank" rel="noopener"><?= __('checkout_done.pay_by_card', 'Pagar com cartão') ?></a>
                                    </p>
                                    <div class="mb-2">
                                        <strong><?= __('checkout_done.payment_link', 'Link de pagamento:') ?></strong>
                                        <div class="input-group">
                                            <input id="stripe-link" type="text" class="form-control" value="<?= htmlspecialchars($invoiceUrl) ?>" readonly onclick="this.select();" />
                                            <button type="button" class="btn btn-outline-dark" onclick="copiarPixPayload('stripe-link','stripe-copied', this)"><?= __('common.copy', 'Copiar') ?></button>
                                        </div>
                                        <div id="stripe-copied" class="small text-success mt-1" style="display:none;"><?= __('common.copied_success', 'Copiado!') ?></div>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>

                            <?php
                            $statusInterno = (string) ($pedido['pagamento_status'] ?? ($pedido['payment_status'] ?? ''));
                            $gatewayPedido = (string) ($pedido['pagamento_gateway'] ?? ($pedido['payment_gateway'] ?? ''));
                            $podeReemitir = ($gatewayPedido === 'asaas') && in_array(strtoupper($statusInterno), ['PENDING', 'PENDENTE', 'OVERDUE'], true);
                            ?>

                            <?php if ($podeReemitir && in_array($billingType, ['PIX', 'BOLETO'], true) && !empty($pedido['id'])): ?>
                                <form method="POST" action="/pedido/reemitir-pagamento/<?= (int) $pedido['id'] ?>" class="mt-3">
                                    <button type="submit" class="btn btn-outline-secondary">
                                        <?= __('checkout_done.regenerate_billing', 'Gerar nova cobrança') ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>

            </div>
            
            <!-- Action Buttons -->
            <div class="d-flex gap-2 flex-wrap mt-4">
                <a href="/meus-pedidos" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i> <?= __('common.back', 'Voltar') ?>
                </a>
                <a href="/meu-ticket/abrir/pedido/<?= (int) ($pedido['id'] ?? 0) ?>" class="btn btn-outline-dark">
                    <i class="fas fa-life-ring me-2"></i> <?= __('user_orders.actions.open_ticket', 'Abrir Ticket para este Pedido') ?>
                </a>
                <button class="btn btn-primary" onclick="window.print()">
                    <i class="fas fa-print me-2"></i> <?= __('common.print', 'Imprimir') ?>
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.user-avatar img {
    width: 80px;
    height: 80px;
    object-fit: cover;
}

.timeline {
    position: relative;
    padding-left: 2rem;
}

.timeline::before {
    content: '';
    position: absolute;
    left: 0.75rem;
    top: 0;
    bottom: 0;
    width: 2px;
    background: rgba(148, 163, 184, 0.35);
}

.timeline-item {
    position: relative;
    margin-bottom: 1.25rem;
}

.timeline-marker {
    position: absolute;
    left: -2rem;
    top: 0;
    width: 2rem;
    height: 2rem;
    background: #fff;
    border-radius: 999px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid rgba(148, 163, 184, 0.35);
}

.timeline-content {
    background: rgba(255, 255, 255, 0.9);
    padding: 0.9rem 1rem;
    border-radius: 12px;
    border: 1px solid rgba(148, 163, 184, 0.22);
    margin-left: 1rem;
}

.product-item {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.75rem;
    padding: 1rem;
    border-bottom: 1px solid rgba(148, 163, 184, 0.22);
}

.product-item:last-child {
    border-bottom: none;
}

.product-image {
    width: 60px;
    height: 60px;
    object-fit: cover;
    border-radius: 12px;
    margin-right: 0;
}

.product-info {
    min-width: 0;
}

.quantity-badge {
    background: rgba(11, 31, 58, 0.08);
    border: 1px solid rgba(11, 31, 58, 0.14);
    color: rgba(11, 31, 58, 1);
    padding: 0.25rem 0.5rem;
    border-radius: 999px;
    font-size: 0.75rem;
    font-weight: 700;
}

@media print {
    .btn,
    .nav,
    .navbar,
    footer {
        display: none !important;
    }
}
</style>

<script>
function copiarPixPayload(inputId, msgId, btn) {
    const el = document.getElementById(inputId);
    const msg = document.getElementById(msgId);
    if (!el) return;
    const txt = el.value || el.textContent || "";
    if (!txt) return;
    const old = btn ? btn.innerText : "";

    const ok = () => {
        if (msg) {
            msg.style.display = "block";
            setTimeout(() => { msg.style.display = "none"; }, 1800);
        }
        if (btn) {
            const copiedTxt = (window.ORDER_DETAILS_I18N && window.ORDER_DETAILS_I18N.copied) ? window.ORDER_DETAILS_I18N.copied : 'Copiado';
            const copyTxt = (window.ORDER_DETAILS_I18N && window.ORDER_DETAILS_I18N.copy) ? window.ORDER_DETAILS_I18N.copy : 'Copiar';
            btn.innerText = copiedTxt;
            setTimeout(() => { btn.innerText = old || copyTxt; }, 1800);
        }
    };

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(txt).then(ok).catch(() => {
            el.focus();
            el.select();
            try { document.execCommand("copy"); ok(); } catch (e) {}
        });
        return;
    }
    el.focus();
    el.select();
    try { document.execCommand("copy"); ok(); } catch (e) {}
}
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/../layouts/main.php';
?>
