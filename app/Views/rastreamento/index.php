<?php ob_start(); ?>
<div class="container py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-start align-items-md-center gap-2 mb-4">
        <h1 class="h4 mb-0"><i class="fas fa-search-location"></i> Rastreamento de Pedido</h1>
        <a href="/rastreamento" class="btn btn-outline-primary">
            <i class="fas fa-search"></i> Buscar Outro
        </a>
    </div>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-box"></i> Dados do Pedido #<?= $pedido['id'] ?></h5>
                </div>
                <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <p><strong>Cliente:</strong> <?= htmlspecialchars($pedido['cliente_nome']) ?></p>
                        <p><strong>E-mail:</strong> <?= htmlspecialchars($pedido['cliente_email']) ?></p>
                        <p><strong>Status:</strong> 
                            <?php 
                            $statusLabels = [
                                'selecao' => 'Seleção de Produtos',
                                'cobranca' => 'Cobrança',
                                'despacho' => 'Despacho para MIA',
                                'transito' => 'Trânsito',
                                'aduana' => 'Processamento Aduaneiro',
                                'entrega' => 'Entrega',
                                'concluido' => 'Concluído'
                            ];
                            $statusColors = [
                                'selecao' => ['bg' => 'rgba(148, 163, 184, 0.18)', 'border' => 'rgba(148, 163, 184, 0.35)', 'color' => 'rgba(15, 23, 42, 0.82)'],
                                'cobranca' => ['bg' => 'rgba(56, 189, 248, 0.12)', 'border' => 'rgba(56, 189, 248, 0.22)', 'color' => 'rgba(11, 31, 58, 1)'],
                                'despacho' => ['bg' => 'rgba(245, 158, 11, 0.14)', 'border' => 'rgba(245, 158, 11, 0.35)', 'color' => 'rgba(124, 45, 18, 1)'],
                                'transito' => ['bg' => 'rgba(11, 31, 58, 0.08)', 'border' => 'rgba(11, 31, 58, 0.14)', 'color' => 'rgba(11, 31, 58, 1)'],
                                'aduana' => ['bg' => 'rgba(245, 158, 11, 0.14)', 'border' => 'rgba(245, 158, 11, 0.35)', 'color' => 'rgba(124, 45, 18, 1)'],
                                'entrega' => ['bg' => 'rgba(56, 189, 248, 0.12)', 'border' => 'rgba(56, 189, 248, 0.22)', 'color' => 'rgba(11, 31, 58, 1)'],
                                'concluido' => ['bg' => 'rgba(16, 185, 129, 0.10)', 'border' => 'rgba(16, 185, 129, 0.18)', 'color' => 'rgba(6, 78, 59, 1)']
                            ];
                            ?>
                            <?php $statusBadge = $statusColors[$pedido['status']] ?? $statusColors['selecao']; ?>
                            <span class="badge" style="background: <?= $statusBadge['bg'] ?>; border: 1px solid <?= $statusBadge['border'] ?>; color: <?= $statusBadge['color'] ?>;">
                                <?= $statusLabels[$pedido['status']] ?? $pedido['status'] ?>
                            </span>
                        </p>
                    </div>
                    <div class="col-md-6">
                        <p><strong>Subtotal:</strong> R$ <?= number_format($pedido['subtotal'], 2, ',', '.') ?></p>
                        <p><strong>Serviços:</strong> R$ <?= number_format($pedido['servicos'], 2, ',', '.') ?></p>
                        <p><strong>Impostos:</strong> R$ <?= number_format($pedido['impostos'], 2, ',', '.') ?></p>
                        <p><strong>Total:</strong> R$ <?= number_format($pedido['total'], 2, ',', '.') ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header">
                <h5><i class="fas fa-list"></i> Itens do Pedido</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Produto</th>
                                <th>Quantidade</th>
                                <th>Valor Unit.</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($pedido['items'] as $item): ?>
                                <tr>
                                    <td><?= htmlspecialchars($item['produto_nome']) ?></td>
                                    <td><?= $item['quantidade'] ?></td>
                                    <td>R$ <?= number_format($item['preco_unitario'], 2, ',', '.') ?></td>
                                    <td>R$ <?= number_format($item['subtotal'], 2, ',', '.') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

        </div>

        <div class="col-lg-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header">
                    <h5 class="mb-0"><i class="fas fa-route"></i> Fluxo do Processo</h5>
                </div>
                <div class="card-body">
                <div class="timeline">
                    <?php 
                    $etapas = [
                        'selecao' => ['Selecão de Produtos', 'fa-shopping-cart', 'secondary'],
                        'cobranca' => ['Cobrança', 'fa-calculator', 'info'],
                        'despacho' => ['Despacho MIA', 'fa-plane-departure', 'warning'],
                        'transito' => ['Voo para BR', 'fa-plane', 'primary'],
                        'aduana' => ['Processamento Aduaneiro', 'fa-gavel', 'warning'],
                        'entrega' => ['Translado e Armazenamento', 'fa-warehouse', 'info'],
                        'concluido' => ['Envio Correios', 'fa-truck', 'success']
                    ];
                    
                    $currentStatusIndex = array_search($pedido['status'], array_keys($etapas));
                    ?>
                    
                    <?php foreach ($etapas as $key => $etapa): ?>
                        <?php 
                        $etapaIndex = array_search($key, array_keys($etapas));
                        $isCompleted = $etapaIndex <= $currentStatusIndex;
                        $isCurrent = $key === $pedido['status'];
                        ?>
                        
                        <div class="timeline-item mb-3">
                            <div class="d-flex align-items-center">
                                <div class="timeline-icon me-3">
                                    <i class="fas <?= $etapa[1] ?> <?= $isCompleted ? 'text-success' : 'text-muted' ?>"></i>
                                </div>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="<?= $isCurrent ? 'fw-bold' : '' ?>">
                                            <?= $etapa[0] ?>
                                        </span>
                                        <?php if ($isCompleted): ?>
                                            <i class="fas fa-check-circle text-success"></i>
                                        <?php endif; ?>
                                    </div>
                                    <?php if ($isCurrent): ?>
                                        <small class="text-primary">Em andamento</small>
                                    <?php elseif ($isCompleted): ?>
                                        <small class="text-success">Concluído</small>
                                    <?php else: ?>
                                        <small class="text-muted">Aguardando</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header">
                <h5><i class="fas fa-history"></i> Histórico de Rastreamento</h5>
            </div>
            <div class="card-body">
                <?php if (empty($rastreamento)): ?>
                    <p class="text-muted">Nenhuma atualização de rastreamento encontrada.</p>
                <?php else: ?>
                    <div class="timeline-vertical">
                        <?php foreach ($rastreamento as $item): ?>
                            <div class="timeline-vertical-item">
                                <div class="timeline-vertical-marker"></div>
                                <div class="timeline-vertical-content">
                                    <h6><?= htmlspecialchars($item['etapa']) ?></h6>
                                    <p class="mb-1"><?= htmlspecialchars($item['descricao']) ?></p>
                                    <?php if ($item['local']): ?>
                                        <small class="text-muted"><i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($item['local']) ?></small><br>
                                    <?php endif; ?>
                                    <small class="text-muted"><i class="fas fa-clock"></i> <?= date('d/m/Y H:i', strtotime($item['data_hora'])) ?></small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<style>
.timeline-item {
    position: relative;
}

.timeline-icon {
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background-color: #f8f9fa;
}

.timeline-vertical {
    position: relative;
    padding-left: 30px;
}

.timeline-vertical::before {
    content: '';
    position: absolute;
    left: 15px;
    top: 0;
    bottom: 0;
    width: 2px;
    background-color: rgba(148, 163, 184, 0.35);
}

.timeline-vertical-item {
    position: relative;
    margin-bottom: 20px;
}

.timeline-vertical-marker {
    position: absolute;
    left: -22px;
    top: 5px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background-color: rgba(11, 31, 58, 0.85);
    border: 2px solid #fff;
    box-shadow: 0 0 0 2px rgba(148, 163, 184, 0.35);
}

.timeline-vertical-content {
    background-color: rgba(248, 250, 252, 0.85);
    padding: 15px;
    border-radius: 5px;
    border-left: 3px solid rgba(11, 31, 58, 0.85);
}
</style>
</div>
<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
