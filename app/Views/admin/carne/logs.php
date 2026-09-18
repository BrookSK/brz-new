<?php $title = __('admin.carne_logs.page_title', 'Logs do Carnê - Admin'); ?>
<?php ob_start(); ?>
<?php
$tipoBadges = [
    'carne_criado' => 'success',
    'carne_erro' => 'danger',
    'pix_gerado' => 'primary',
    'pix_erro' => 'danger',
    'boleto_gerado' => 'primary',
    'boleto_erro' => 'danger',
    'pagamento_confirmado' => 'success',
    'pagamento_nao_encontrado' => 'warning',
    'pagamento_nao_confirmado' => 'warning',
    'pagamento_expirado' => 'warning',
    'pagamento_ignorado' => 'secondary',
    'webhook_recebido' => 'info',
    'info' => 'secondary',
];

// Traduz o código do tipo de log (ex.: "webhook_recebido") para um rótulo legível no idioma atual.
$traduzirTipoLog = function (string $tipo): string {
    $mapa = [
        'carne_criado'             => __('admin.carne_logs.type.plan_created', 'Carnê criado'),
        'carne_erro'               => __('admin.carne_logs.type.plan_error', 'Erro no carnê'),
        'pix_gerado'               => __('admin.carne_logs.type.pix_generated', 'PIX gerado'),
        'pix_erro'                 => __('admin.carne_logs.type.pix_error', 'Erro no PIX'),
        'boleto_gerado'            => __('admin.carne_logs.type.slip_generated', 'Boleto gerado'),
        'boleto_erro'              => __('admin.carne_logs.type.slip_error', 'Erro no boleto'),
        'pagamento_confirmado'     => __('admin.carne_logs.type.payment_confirmed', 'Pagamento confirmado'),
        'pagamento_nao_encontrado' => __('admin.carne_logs.type.payment_not_found', 'Pagamento não encontrado'),
        'pagamento_nao_confirmado' => __('admin.carne_logs.type.payment_not_confirmed', 'Pagamento não confirmado'),
        'pagamento_expirado'       => __('admin.carne_logs.type.payment_expired', 'Pagamento expirado'),
        'pagamento_ignorado'       => __('admin.carne_logs.type.payment_ignored', 'Pagamento ignorado'),
        'webhook_recebido'         => __('admin.carne_logs.type.webhook_received', 'Webhook recebido'),
        'info'                     => __('admin.carne_logs.type.info', 'Informação'),
    ];
    return $mapa[$tipo] ?? ucfirst(str_replace('_', ' ', $tipo));
};

// Traduz mensagens de log (gravadas em pt-BR no banco) por padrões, preservando as partes dinâmicas.
$traduzirMensagemLog = function (string $msg): string {
    $msg = trim($msg);
    if ($msg === '') return '';

    $regras = [
        // Webhook recebido mas pagamento não confirmado no Câmbio Real. Status: XXX
        ['/^Webhook recebido mas pagamento não confirmado no Câmbio Real\. Status: (.+)$/u',
            fn($m) => __('admin.carne_logs.msg.webhook_not_confirmed', 'Webhook received but payment not confirmed on Câmbio Real. Status: {status}', ['status' => $m[1]])],
        // Webhook recebido para campo=valor
        ['/^Webhook recebido para (.+)$/u',
            fn($m) => __('admin.carne_logs.msg.webhook_received', 'Webhook received for {ref}', ['ref' => $m[1]])],
        // Parcela não encontrada para campo=valor
        ['/^Parcela não encontrada para (.+)$/u',
            fn($m) => __('admin.carne_logs.msg.installment_not_found', 'Installment not found for {ref}', ['ref' => $m[1]])],
        // Pagamento confirmado para parcela #N (tipo)
        ['/^Pagamento confirmado para parcela #(\d+) \((.+)\)$/u',
            fn($m) => __('admin.carne_logs.msg.payment_confirmed', 'Payment confirmed for installment #{id} ({type})', ['id' => $m[1], 'type' => $m[2]])],
        // Pagamento ignorado - parcela expirada em X
        ['/^Pagamento ignorado - parcela expirada em (.+)$/u',
            fn($m) => __('admin.carne_logs.msg.payment_ignored_expired', 'Payment ignored - installment expired on {when}', ['when' => $m[1]])],
        // Pagamento ignorado - pedido cancelado/lixeira
        ['/^Pagamento ignorado - pedido cancelado\/lixeira$/u',
            fn($m) => __('admin.carne_logs.msg.payment_ignored_cancelled', 'Payment ignored - order cancelled/trashed')],
        // Iniciando criação de carnê para pedido #N
        ['/^Iniciando criação de carnê para pedido #(\d+)$/u',
            fn($m) => __('admin.carne_logs.msg.plan_creating', 'Starting installment plan creation for order #{id}', ['id' => $m[1]])],
        // Carnê #N criado com sucesso para pedido #M
        ['/^Carnê #(\d+) criado com sucesso para pedido #(\d+)$/u',
            fn($m) => __('admin.carne_logs.msg.plan_created', 'Installment plan #{plan} created successfully for order #{order}', ['plan' => $m[1], 'order' => $m[2]])],
        // Erro ao gerar boletos da 1ª parcela
        ['/^Erro ao gerar boletos da 1ª parcela$/u',
            fn($m) => __('admin.carne_logs.msg.first_slip_error', 'Error generating first installment slips')],
        // PIX Produtos gerado para parcela #N
        ['/^PIX Produtos gerado para parcela #(\d+)$/u',
            fn($m) => __('admin.carne_logs.msg.pix_products_generated', 'Product PIX generated for installment #{id}', ['id' => $m[1]])],
        // PIX Taxas gerado para parcela #N
        ['/^PIX Taxas gerado para parcela #(\d+)$/u',
            fn($m) => __('admin.carne_logs.msg.pix_fees_generated', 'Fees PIX generated for installment #{id}', ['id' => $m[1]])],
        // Erro ao gerar PIX Produtos para parcela #N
        ['/^Erro ao gerar PIX Produtos para parcela #(\d+)$/u',
            fn($m) => __('admin.carne_logs.msg.pix_products_error', 'Error generating product PIX for installment #{id}', ['id' => $m[1]])],
        // Erro ao gerar PIX Taxas para parcela #N
        ['/^Erro ao gerar PIX Taxas para parcela #(\d+)$/u',
            fn($m) => __('admin.carne_logs.msg.pix_fees_error', 'Error generating fees PIX for installment #{id}', ['id' => $m[1]])],
        // Boleto Produtos gerado para parcela #N
        ['/^Boleto Produtos gerado para parcela #(\d+)$/u',
            fn($m) => __('admin.carne_logs.msg.slip_products_generated', 'Product slip generated for installment #{id}', ['id' => $m[1]])],
        // Boleto Taxas gerado para parcela #N
        ['/^Boleto Taxas gerado para parcela #(\d+)$/u',
            fn($m) => __('admin.carne_logs.msg.slip_fees_generated', 'Fees slip generated for installment #{id}', ['id' => $m[1]])],
        // Erro ao gerar Boleto Produtos para parcela #N
        ['/^Erro ao gerar Boleto Produtos para parcela #(\d+)$/u',
            fn($m) => __('admin.carne_logs.msg.slip_products_error', 'Error generating product slip for installment #{id}', ['id' => $m[1]])],
        // Erro ao gerar Boleto Taxas para parcela #N
        ['/^Erro ao gerar Boleto Taxas para parcela #(\d+)$/u',
            fn($m) => __('admin.carne_logs.msg.slip_fees_error', 'Error generating fees slip for installment #{id}', ['id' => $m[1]])],
    ];

    foreach ($regras as [$pattern, $fn]) {
        if (preg_match($pattern, $msg, $m)) {
            return $fn($m);
        }
    }
    return $msg;
};
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="page-title"><?= htmlspecialchars(__('admin.carne_logs.title', 'Logs do Carnê'), ENT_QUOTES, 'UTF-8') ?></h1>
        <div>
            <a href="/admin/carnes" class="btn btn-outline-primary"><i class="fas fa-file-invoice-dollar me-1"></i> <?= htmlspecialchars(__('admin.carne_logs.plans', 'Carnês'), ENT_QUOTES, 'UTF-8') ?></a>
            <a href="/admin" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i> <?= htmlspecialchars(__('common.back', 'Voltar'), ENT_QUOTES, 'UTF-8') ?></a>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" action="/admin/carnes/logs" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label small"><?= htmlspecialchars(__('admin.carne_logs.filter.plan_id', 'Carnê ID'), ENT_QUOTES, 'UTF-8') ?></label>
                    <input type="number" name="carne_id" class="form-control form-control-sm" value="<?= htmlspecialchars($filtros['carne_id'] ?? '') ?>" placeholder="<?= htmlspecialchars(__('admin.carne_logs.filter.plan_id_placeholder', 'ID do carnê'), ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small"><?= htmlspecialchars(__('admin.carne_logs.filter.order_id', 'Pedido ID'), ENT_QUOTES, 'UTF-8') ?></label>
                    <input type="number" name="pedido_id" class="form-control form-control-sm" value="<?= htmlspecialchars($filtros['pedido_id'] ?? '') ?>" placeholder="<?= htmlspecialchars(__('admin.carne_logs.filter.order_id_placeholder', 'ID do pedido'), ENT_QUOTES, 'UTF-8') ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label small"><?= htmlspecialchars(__('admin.carne_logs.filter.type', 'Tipo'), ENT_QUOTES, 'UTF-8') ?></label>
                    <select name="tipo" class="form-select form-select-sm">
                        <option value=""><?= htmlspecialchars(__('common.all', 'Todos'), ENT_QUOTES, 'UTF-8') ?></option>
                        <?php foreach ($tipoBadges as $tipo => $cor): ?>
                            <option value="<?= $tipo ?>" <?= ($filtros['tipo'] ?? '') === $tipo ? 'selected' : '' ?>><?= htmlspecialchars($traduzirTipoLog($tipo)) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-primary btn-sm"><i class="fas fa-search me-1"></i> <?= htmlspecialchars(__('common.filter', 'Filtrar'), ENT_QUOTES, 'UTF-8') ?></button>
                    <a href="/admin/carnes/logs" class="btn btn-outline-secondary btn-sm"><i class="fas fa-times me-1"></i> <?= htmlspecialchars(__('common.clear', 'Limpar'), ENT_QUOTES, 'UTF-8') ?></a>
                </div>
            </form>
        </div>
    </div>

    <!-- Tabela de Logs -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="fas fa-list me-1"></i> <?= htmlspecialchars(__('admin.carne_logs.logs_count', 'Logs'), ENT_QUOTES, 'UTF-8') ?> (<?= count($logs) ?>)</span>
        </div>
        <div class="card-body p-0">
            <?php if (empty($logs)): ?>
                <div class="text-center text-muted py-5">
                    <i class="fas fa-inbox fa-3x mb-3 d-block"></i>
                    <?= htmlspecialchars(__('admin.carne_logs.empty', 'Nenhum log encontrado.'), ENT_QUOTES, 'UTF-8') ?>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-sm table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 150px;"><?= htmlspecialchars(__('admin.carne_logs.col.date', 'Data'), ENT_QUOTES, 'UTF-8') ?></th>
                                <th style="width: 160px;"><?= htmlspecialchars(__('admin.carne_logs.col.type', 'Tipo'), ENT_QUOTES, 'UTF-8') ?></th>
                                <th style="width: 80px;"><?= htmlspecialchars(__('admin.carne_logs.col.plan', 'Carnê'), ENT_QUOTES, 'UTF-8') ?></th>
                                <th style="width: 80px;"><?= htmlspecialchars(__('admin.carne_logs.col.order', 'Pedido'), ENT_QUOTES, 'UTF-8') ?></th>
                                <th style="width: 80px;"><?= htmlspecialchars(__('admin.carne_logs.col.installment', 'Parcela'), ENT_QUOTES, 'UTF-8') ?></th>
                                <th><?= htmlspecialchars(__('admin.carne_logs.col.message', 'Mensagem'), ENT_QUOTES, 'UTF-8') ?></th>
                                <th style="width: 200px;"><?= htmlspecialchars(__('admin.carne_logs.col.details', 'Detalhes'), ENT_QUOTES, 'UTF-8') ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td class="small text-nowrap"><?= date('d/m/Y H:i:s', strtotime($log['created_at'])) ?></td>
                                    <td>
                                        <?php $badgeCor = $tipoBadges[$log['tipo']] ?? 'secondary'; ?>
                                        <span class="badge bg-<?= $badgeCor ?>"><?= htmlspecialchars($traduzirTipoLog((string) $log['tipo'])) ?></span>
                                    </td>
                                    <td class="small">
                                        <?php if (!empty($log['carne_id'])): ?>
                                            <a href="/admin/carnes/detalhes/<?= (int) $log['carne_id'] ?>">#<?= (int) $log['carne_id'] ?></a>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small">
                                        <?php if (!empty($log['pedido_id'])): ?>
                                            #<?= (int) $log['pedido_id'] ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small">
                                        <?php if (!empty($log['parcela_id'])): ?>
                                            #<?= (int) $log['parcela_id'] ?>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="small"><?= htmlspecialchars($traduzirMensagemLog((string) ($log['mensagem'] ?? ''))) ?></td>
                                    <td class="small">
                                        <?php if (!empty($log['detalhes'])): ?>
                                            <?php $detalheTruncado = mb_strlen($log['detalhes']) > 80 ? mb_substr($log['detalhes'], 0, 80) . '...' : $log['detalhes']; ?>
                                            <span class="log-detalhe-truncado" title="<?= htmlspecialchars(__('admin.carne_logs.expand_hint', 'Clique para expandir'), ENT_QUOTES, 'UTF-8') ?>" style="cursor: pointer;" onclick="this.style.display='none'; this.nextElementSibling.style.display='inline';">
                                                <?= htmlspecialchars($detalheTruncado) ?>
                                            </span>
                                            <span class="log-detalhe-completo" style="display: none; word-break: break-all;">
                                                <?= htmlspecialchars($log['detalhes']) ?>
                                                <a href="#" class="text-muted small" onclick="event.preventDefault(); this.parentElement.style.display='none'; this.parentElement.previousElementSibling.style.display='inline';">[<?= htmlspecialchars(__('admin.carne_logs.collapse', 'recolher'), ENT_QUOTES, 'UTF-8') ?>]</a>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php $content = ob_get_clean(); ?>
<?php require __DIR__ . '/../../layouts/admin.php'; ?>
