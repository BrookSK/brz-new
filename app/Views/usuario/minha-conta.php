<?php ob_start(); ?>
<div class="container py-5">
    <div class="row">
        <!-- Sidebar -->
        <?php $activePage = 'dashboard'; include __DIR__ . '/../partials/usuario_sidebar.php'; ?>
        
        <!-- Main Content -->
        <div class="col-lg-9">
            <div class="d-flex justify-content-between align-items-center mb-4 user-page-header">
                <h2><i class="fas fa-tachometer-alt"></i> Minha Conta</h2>
                <span class="text-muted">
                    Bem-vindo, <strong><?= htmlspecialchars($usuario['nome']) ?></strong>!
                </span>
            </div>
            
            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-md-3 mb-3">
                    <div class="card shadow-sm border-0">
                        <div class="card-body">
                            <div class="d-flex align-items-start justify-content-between">
                                <div>
                                    <small class="text-muted d-block mb-1">Total de Pedidos</small>
                                    <h4 class="mb-0 text-dark"><?= $total_pedidos ?></h4>
                                </div>
                                <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 42px; height: 42px; background: rgba(11, 31, 58, 0.08); border: 1px solid rgba(11, 31, 58, 0.14); color: #0b1f3a;">
                                    <i class="fas fa-shopping-bag"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-3">
                    <div class="card shadow-sm border-0">
                        <div class="card-body">
                            <div class="d-flex align-items-start justify-content-between">
                                <div>
                                    <small class="text-muted d-block mb-1">Pedidos Ativos</small>
                                    <h4 class="mb-0 text-dark">
                                        <?php 
                                        echo (int) ($pedidos_ativos ?? 0);
                                        ?>
                                    </h4>
                                </div>
                                <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 42px; height: 42px; background: rgba(16, 185, 129, 0.10); border: 1px solid rgba(16, 185, 129, 0.18); color: rgba(6, 78, 59, 1);">
                                    <i class="fas fa-truck"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-3">
                    <div class="card shadow-sm border-0">
                        <div class="card-body">
                            <div class="d-flex align-items-start justify-content-between">
                                <div>
                                    <small class="text-muted d-block mb-1">Total Gasto</small>
                                    <h6 class="mb-0 text-dark" style="line-height: 1.2;">
                                        <?php 
                                        $tgBRL = floatval($total_gasto_brl ?? 0);
                                        $tgUSD = floatval($total_gasto_usd ?? 0);
                                        if ($tgUSD > 0 && $tgBRL > 0) {
                                            echo 'R$ ' . number_format($tgBRL, 2, ',', '.') . '<br><span class="text-muted" style="font-size: 0.85rem;">US$ ' . number_format($tgUSD, 2, ',', '.') . '</span>';
                                        } elseif ($tgUSD > 0) {
                                            echo 'US$ ' . number_format($tgUSD, 2, ',', '.');
                                        } else {
                                            echo 'R$ ' . number_format($tgBRL, 2, ',', '.');
                                        }
                                        ?>
                                    </h6>
                                </div>
                                <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 42px; height: 42px; background: rgba(245, 158, 11, 0.12); border: 1px solid rgba(245, 158, 11, 0.18); color: rgba(124, 45, 18, 1);">
                                    <i class="fas fa-dollar-sign"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-3 mb-3">
                    <div class="card shadow-sm border-0">
                        <div class="card-body">
                            <div class="d-flex align-items-start justify-content-between">
                                <div>
                                    <small class="text-muted d-block mb-1">Endereços</small>
                                    <h4 class="mb-0 text-dark"><?= count($enderecos) ?></h4>
                                </div>
                                <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 42px; height: 42px; background: rgba(56, 189, 248, 0.12); border: 1px solid rgba(56, 189, 248, 0.18); color: rgba(11, 31, 58, 1);">
                                    <i class="fas fa-map-marker-alt"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Orçamentos da Assessoria -->
            <div class="card shadow-sm mt-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-clipboard-list"></i> Orçamentos da Assessoria</h5>
                    <a href="/assessoria" class="btn btn-sm btn-outline-primary">Novo Orçamento</a>
                </div>
                <div class="card-body">
                    <?php $orcamentosAssessoria = $orcamentos_assessoria ?? []; ?>
                    <?php if (empty($orcamentosAssessoria)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-file-invoice-dollar fa-3x text-muted mb-3"></i>
                            <h6>Nenhum orçamento ainda</h6>
                            <p class="text-muted">Gere um orçamento pela Assessoria para aparecer aqui.</p>
                            <a href="/assessoria" class="btn btn-primary">
                                <i class="fas fa-plus me-2"></i> Criar Orçamento
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Data</th>
                                        <th>Status</th>
                                        <th>Tempo</th>
                                        <th>Pedido</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($orcamentosAssessoria as $o): ?>
                                        <?php
                                            $status = (string) ($o['status'] ?? 'rascunho');
                                            $isPago = ($status === 'pago');
                                            $pedidoId = (int) ($o['pedido_id'] ?? 0);
                                            $createdAt = !empty($o['created_at']) ? strtotime((string) $o['created_at']) : null;
                                            $expiresAt = ($createdAt !== null) ? ($createdAt + (15 * 60)) : null;
                                            $remaining = ($expiresAt !== null) ? max(0, $expiresAt - time()) : 0;
                                            $isExpired = (!$isPago) && ($expiresAt !== null) && ($remaining <= 0);
                                        ?>
                                        <tr>
                                            <td>#<?= (int) ($o['id'] ?? 0) ?></td>
                                            <td><?= !empty($o['created_at']) ? date('d/m/Y H:i', strtotime($o['created_at'])) : '-' ?></td>
                                            <td>
                                                <?php if ($isPago): ?>
                                                    <span class="badge" style="background: rgba(16, 185, 129, 0.10); border: 1px solid rgba(16, 185, 129, 0.18); color: rgba(6, 78, 59, 1);">Pago</span>
                                                <?php else: ?>
                                                    <span class="badge" style="background: rgba(148, 163, 184, 0.18); border: 1px solid rgba(148, 163, 184, 0.35); color: rgba(15, 23, 42, 0.82);">Rascunho</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($isPago): ?>
                                                    -
                                                <?php else: ?>
                                                    <span class="assessoria-timer" data-remaining="<?= (int) $remaining ?>" style="color: #dc3545; font-weight: 700;">
                                                        <?= $isExpired ? '00:00' : gmdate('i:s', $remaining) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($pedidoId > 0): ?>
                                                    <a href="/pedido/detalhes/<?= $pedidoId ?>" class="text-decoration-none">#<?= $pedidoId ?></a>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <?php if ($isPago): ?>
                                                    <a href="/assessoria/orcamento?orcamento_id=<?= (int) ($o['id'] ?? 0) ?>" class="btn btn-sm btn-outline-primary">
                                                        Abrir
                                                    </a>
                                                <?php else: ?>
                                                    <?php if ($isExpired): ?>
                                                        <a href="/assessoria/orcamento?orcamento_id=<?= (int) ($o['id'] ?? 0) ?>" class="btn btn-sm btn-outline-primary">
                                                            Ver orçamento
                                                        </a>
                                                        <a href="/assessoria/reprocessar?orcamento_id=<?= (int) ($o['id'] ?? 0) ?>" class="btn btn-sm btn-primary">
                                                            Reprocessar
                                                        </a>
                                                    <?php else: ?>
                                                        <a href="/assessoria/orcamento?orcamento_id=<?= (int) ($o['id'] ?? 0) ?>" class="btn btn-sm btn-primary">
                                                            Finalizar orçamento
                                                        </a>
                                                    <?php endif; ?>
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

            <script>
            (function() {
                function pad2(n) {
                    return (n < 10 ? '0' : '') + n;
                }

                function tick() {
                    document.querySelectorAll('.assessoria-timer').forEach(function(el) {
                        var r = parseInt(el.getAttribute('data-remaining') || '0', 10);
                        if (isNaN(r) || r <= 0) {
                            el.textContent = '00:00';
                            el.setAttribute('data-remaining', '0');
                            return;
                        }
                        r = r - 1;
                        el.setAttribute('data-remaining', String(r));
                        var m = Math.floor(r / 60);
                        var s = r % 60;
                        el.textContent = pad2(m) + ':' + pad2(s);
                    });
                }

                setInterval(tick, 1000);
            })();
            </script>
        </div>
        </div>
    </div>
</div>

<style>
.account-sidebar .user-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: rgba(11, 31, 58, 0.10);
    border: 1px solid rgba(11, 31, 58, 0.14);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 2rem;
    font-weight: bold;
    overflow: hidden;
}

.account-sidebar .user-avatar img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    display: block;
}

.nav-link {
    border-radius: 8px;
    margin-bottom: 5px;
    transition: all 0.3s ease;
}

.nav-link:hover {
    background-color: #f8f9fa;
    transform: none;
}

.nav-link.active {
    background: rgba(11, 31, 58, 0.08);
    border: 1px solid rgba(11, 31, 58, 0.14);
    color: rgba(11, 31, 58, 1) !important;
}

.table th {
    border-top: none;
    font-weight: 600;
    color: #6c757d;
    font-size: 0.875rem;
    text-transform: uppercase;
}

@media (max-width: 767.98px) {
    .user-page-header {
        flex-direction: column;
        align-items: flex-start !important;
        gap: 0.35rem;
    }

    .user-page-header h2 {
        font-size: 1.5rem;
        margin-bottom: 0;
    }
}
</style>
<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
