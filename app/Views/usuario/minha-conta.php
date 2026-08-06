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

            <?php
            $perfil = strtolower(trim((string) ($_SESSION['usuario_perfil'] ?? ($usuario['perfil'] ?? ''))));
            ?>
            <?php if ($perfil === 'representante'): ?>
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-body">
                        <div class="d-flex flex-wrap gap-2">
                            <a class="btn btn-outline-primary" href="/admin/representante/produtos">
                                <i class="fas fa-box me-1"></i> Produtos
                            </a>
                            <a class="btn btn-primary" href="/admin/representante/comissoes">
                                <i class="fas fa-percentage me-1"></i> Comissões
                            </a>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <?php if (!empty($usuario['precisa_recadastro'])): ?>
                <div class="alert alert-warning" role="alert" style="background: #fff3cd; border-color: #ffecb5; color: #664d03;">
                    <?= __('user.recadastro_warning', 'Como este é um site novo, precisamos que você atualize seus dados cadastrais. ') ?>
                    <a href="/meus-dados" class="alert-link"><?= __('user.recadastro_cta', 'Clique aqui para atualizar agora.') ?></a>
                </div>
            <?php endif; ?>
            
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

                <div class="col-md-3 mb-3">
                    <div class="card shadow-sm border-0">
                        <div class="card-body">
                            <div class="d-flex align-items-start justify-content-between">
                                <div>
                                    <small class="text-muted d-block mb-1">Carteira</small>
                                    <h6 class="mb-0 text-dark" style="line-height: 1.2;">
                                        <?php
                                        $cu = floatval($carteira_saldo_usd ?? 0);
                                        echo 'US$ ' . number_format($cu, 2, ',', '.');
                                        ?>
                                    </h6>
                                    <a href="/clube/recarga" class="btn btn-sm btn-outline-primary mt-2">Adicionar saldo</a>
                                </div>
                                <div class="rounded-circle d-flex align-items-center justify-content-center" style="width: 42px; height: 42px; background: rgba(99, 102, 241, 0.12); border: 1px solid rgba(99, 102, 241, 0.18); color: rgba(49, 46, 129, 1);">
                                    <i class="fas fa-wallet"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Endereço de Redirecionamento -->
            <div class="card shadow-sm mt-4">
                <div class="card-header d-flex justify-content-between align-items-center" role="button" data-bs-toggle="collapse" data-bs-target="#collapseEnderecoRed" aria-expanded="false" aria-controls="collapseEnderecoRed">
                    <h5 class="mb-0"><i class="fas fa-warehouse me-2"></i>Endereço de Entrega (Redirecionamento)</h5>
                    <i class="fas fa-chevron-down small"></i>
                </div>
                <div class="collapse show" id="collapseEnderecoRed">
                    <div class="card-body">
                        <p class="text-muted mb-3 small">Use este endereço ao comprar em lojas americanas. Seus produtos serão recebidos no nosso armazém e enviados para o Brasil.</p>
                        <table class="table table-sm table-borderless mb-0">
                            <tbody>
                                <tr>
                                    <td class="text-muted" style="width:130px;">Endereço 1</td>
                                    <td class="fw-bold">1227 W Broad St</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Endereço 2</td>
                                    <td class="fw-bold">Suite: <?= htmlspecialchars((string) ($usuario['suite'] ?? '-')) ?></td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Cidade</td>
                                    <td class="fw-bold">Saint Pauls</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">Estado</td>
                                    <td class="fw-bold">NC</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">CEP</td>
                                    <td class="fw-bold">28384</td>
                                </tr>
                                <tr>
                                    <td class="text-muted">País</td>
                                    <td class="fw-bold">Estados Unidos da América (EUA)</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Wallet Balance Breakdown -->
            <?php
            $normalDisp = floatval($carteira_normal_disponivel ?? 0);
            $bloqueado = floatval($carteira_bloqueado_usd ?? 0);
            $turboRecargas = $carteira_turbo_recargas ?? [];
            ?>
            <div class="card shadow-sm mt-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-wallet"></i> Saldos da Carteira</h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <div class="border rounded p-3" style="background: rgba(16, 185, 129, 0.06); border-color: rgba(16, 185, 129, 0.18) !important;">
                                <div class="small text-muted">Saldo disponível para uso</div>
                                <div class="h5 mb-0 fw-bold">US$ <?= number_format($normalDisp, 2, ',', '.') ?></div>
                                <div class="small text-muted mt-1">Clube Normal + Turbo liberado</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-3" style="background: rgba(245, 158, 11, 0.06); border-color: rgba(245, 158, 11, 0.18) !important;">
                                <div class="small text-muted">Saldo Turbo bloqueado</div>
                                <div class="h5 mb-0 fw-bold" style="color:#b45309;">US$ <?= number_format($bloqueado, 2, ',', '.') ?></div>
                                <div class="small text-muted mt-1">Em permanência mínima</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="border rounded p-3" style="background: rgba(99, 102, 241, 0.06); border-color: rgba(99, 102, 241, 0.18) !important;">
                                <div class="small text-muted">Saldo total da carteira</div>
                                <div class="h5 mb-0 fw-bold">US$ <?= number_format(floatval($carteira_saldo_usd ?? 0), 2, ',', '.') ?></div>
                            </div>
                        </div>
                    </div>

                    <?php if (!empty($turboRecargas)): ?>
                    <div class="mt-4">
                        <div class="fw-semibold mb-2"><i class="fas fa-bolt" style="color:#b45309;"></i> Recargas Turbo</div>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle">
                                <thead><tr><th>Valor</th><th>Data da recarga</th><th>Permanência até</th><th>Dias restantes</th><th>Status</th></tr></thead>
                                <tbody>
                                    <?php foreach ($turboRecargas as $tr):
                                        $trValor = (float) ($tr['valor'] ?? 0);
                                        $trCreated = $tr['created_at'] ?? '';
                                        $trLockedUntil = $tr['locked_until'] ?? null;
                                        $trUnlockedAt = $tr['unlocked_at'] ?? null;
                                        $trIsLocked = ($trLockedUntil && strtotime($trLockedUntil) > time() && !$trUnlockedAt);
                                        $trDiasRestantes = $trIsLocked ? max(0, (int) ceil((strtotime($trLockedUntil) - time()) / 86400)) : 0;
                                        $trStatus = $trIsLocked ? 'Turbo ativo em permanência' : 'Turbo liberado para uso';
                                        $trBadge = $trIsLocked ? 'warning' : 'success';
                                    ?>
                                    <tr>
                                        <td>US$ <?= number_format($trValor, 2, ',', '.') ?></td>
                                        <td><?= $trCreated ? date('d/m/Y', strtotime($trCreated)) : '-' ?></td>
                                        <td><?= $trLockedUntil ? date('d/m/Y', strtotime($trLockedUntil)) : '-' ?></td>
                                        <td><?= $trIsLocked ? $trDiasRestantes . ' dias' : '-' ?></td>
                                        <td><span class="badge bg-<?= $trBadge ?>"><?= $trStatus ?></span></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if ($bloqueado > 0): ?>
                    <div class="alert alert-warning mt-3 mb-0" style="border-radius:10px;">
                        <i class="fas fa-lock me-1"></i>
                        Seu saldo Turbo de US$ <?= number_format($bloqueado, 2, ',', '.') ?> está em permanência mínima. O saldo e os rendimentos gerados por ele ficarão disponíveis após o término do prazo.
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card shadow-sm mt-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-chart-line"></i> Rendimentos da Carteira (Clube)</h5>
                </div>
                <div class="card-body">
                    <?php $rr = $carteira_rendimento_resumo ?? ['credito_usd' => 0, 'credito_brl' => 0, 'debito_usd' => 0, 'debito_brl' => 0]; ?>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="border rounded p-3" style="background: rgba(16, 185, 129, 0.06); border-color: rgba(16, 185, 129, 0.18) !important;">
                                <div class="small text-muted">Total creditado</div>
                                <div class="fw-bold">
                                    <?php
                                        $cUsd = (float) ($rr['credito_usd'] ?? 0);
                                        $cBrl = (float) ($rr['credito_brl'] ?? 0);
                                        $parts = [];
                                        if ($cBrl > 0) $parts[] = 'R$ ' . number_format($cBrl, 2, ',', '.');
                                        if ($cUsd > 0) $parts[] = 'US$ ' . number_format($cUsd, 2, ',', '.');
                                        echo !empty($parts) ? implode(' / ', $parts) : 'R$ 0,00';
                                    ?>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded p-3" style="background: rgba(239, 68, 68, 0.06); border-color: rgba(239, 68, 68, 0.18) !important;">
                                <div class="small text-muted">Total estornado</div>
                                <div class="fw-bold">
                                    <?php
                                        $dUsd = (float) ($rr['debito_usd'] ?? 0);
                                        $dBrl = (float) ($rr['debito_brl'] ?? 0);
                                        $parts = [];
                                        if ($dBrl > 0) $parts[] = 'R$ ' . number_format($dBrl, 2, ',', '.');
                                        if ($dUsd > 0) $parts[] = 'US$ ' . number_format($dUsd, 2, ',', '.');
                                        echo !empty($parts) ? implode(' / ', $parts) : 'R$ 0,00';
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive mt-3">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Descrição</th>
                                    <th class="text-end">Valor</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $txs = $carteira_transacoes ?? []; ?>
                                <?php if (empty($txs)): ?>
                                    <tr>
                                        <td colspan="3" class="text-center text-muted py-4">Nenhuma movimentação encontrada.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($txs as $t): ?>
                                        <?php
                                            $desc = (string) ($t['descricao'] ?? '');
                                            $tipo = strtolower(trim((string) ($t['tipo'] ?? '')));
                                            $modalidade = strtolower(trim((string) ($t['modalidade'] ?? '')));
                                            $isRend = (stripos($desc, 'Rendimento Clube') !== false);
                                            $isRecarga = (stripos($desc, 'Recarga Clube') !== false || stripos($desc, 'Recarga Carteira') !== false);
                                            $isTurbo = ($modalidade === 'turbo' || stripos($desc, 'Turbo') !== false);
                                            $vUsd = (float) ($t['valor_usd'] ?? 0);
                                            $vBrl = (float) ($t['valor_brl'] ?? 0);
                                            $valorStr = '-';
                                            if (abs($vBrl) > 0.00001) {
                                                $valorStr = 'R$ ' . number_format(abs($vBrl), 2, ',', '.');
                                            } elseif (abs($vUsd) > 0.00001) {
                                                $valorStr = 'US$ ' . number_format(abs($vUsd), 2, ',', '.');
                                            }
                                            $valorClass = ($tipo === 'debito') ? 'text-danger' : 'text-success';
                                        ?>
                                        <tr class="<?= ($isRend || $isRecarga) ? '' : 'text-muted' ?>">
                                            <td style="white-space: nowrap;">
                                                <?= !empty($t['created_at']) ? date('d/m/Y H:i', strtotime((string) $t['created_at'])) : '-' ?>
                                            </td>
                                            <td>
                                                <?php if ($isRend || $isRecarga): ?>
                                                    <span class="badge" style="background: rgba(11, 31, 58, 0.08); border: 1px solid rgba(11, 31, 58, 0.14); color: rgba(11, 31, 58, 1);">Clube</span>
                                                <?php endif; ?>
                                                <?php if ($isTurbo): ?>
                                                    <span class="badge" style="background: rgba(245, 158, 11, 0.12); border: 1px solid rgba(245, 158, 11, 0.25); color: #b45309;">Turbo</span>
                                                <?php elseif ($modalidade === 'normal' || $isRend || $isRecarga): ?>
                                                    <span class="badge" style="background: rgba(16, 185, 129, 0.10); border: 1px solid rgba(16, 185, 129, 0.25); color: #065f46;">Normal</span>
                                                <?php endif; ?>
                                                <?= htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') ?>
                                            </td>
                                            <td class="text-end <?= $valorClass ?>" style="white-space: nowrap;">
                                                <?= ($tipo === 'debito' ? '-' : '+') . ' ' . $valorStr ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        <div class="small text-muted">Mostrando as últimas 50 movimentações.</div>
                    </div>
                </div>
            </div>

            <div class="modal fade" id="modalRecargaCarteira" tabindex="-1" aria-hidden="true">
                <div class="modal-dialog modal-lg modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Adicionar saldo na carteira</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label">Moeda</label>
                                    <select class="form-select" id="recargaMoeda" onchange="onRecargaMoedaChange()">
                                        <option value="BRL" selected>BRL (Real)</option>
                                        <option value="USD">USD (Dólar)</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Valor</label>
                                    <input type="number" min="0.01" step="0.01" class="form-control" id="recargaValor" placeholder="0,00">
                                </div>
                                <div class="col-md-4" id="recargaMetodoWrap">
                                    <label class="form-label">Método (BRL)</label>
                                    <select class="form-select" id="recargaMetodo">
                                        <option value="pix" selected>PIX</option>
                                        <option value="boleto">Boleto</option>
                                        <option value="cartao_credito">Cartão de crédito</option>
                                    </select>
                                </div>
                            </div>

                            <div class="row g-2 mt-2" id="recargaCartaoWrap" style="display:none;">
                                <div class="col-md-6">
                                    <label class="form-label">Nome no cartão</label>
                                    <input type="text" class="form-control" id="recargaCardHolder" placeholder="Nome como está no cartão">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label">Número do cartão</label>
                                    <input type="text" class="form-control" id="recargaCardNumber" placeholder="0000 0000 0000 0000" maxlength="19" inputmode="numeric">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Validade (MM)</label>
                                    <input type="text" class="form-control" id="recargaCardExpMonth" placeholder="MM" maxlength="2" inputmode="numeric">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Validade (AAAA)</label>
                                    <input type="text" class="form-control" id="recargaCardExpYear" placeholder="AAAA" maxlength="4" inputmode="numeric">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">CVV</label>
                                    <input type="text" class="form-control" id="recargaCardCvv" placeholder="123" maxlength="4" inputmode="numeric">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label">Parcelas</label>
                                    <input type="number" class="form-control" id="recargaInstallments" value="1" min="1" max="12">
                                </div>
                            </div>

                            <div class="alert alert-info mt-3 mb-0" id="recargaInfo" style="display:none;"></div>

                            <div class="mt-3" id="recargaStripeWrap" style="display:none;">
                                <div class="mb-2"><strong>Pagamento (Stripe)</strong></div>
                                <div id="recargaStripeCard" class="form-control" style="padding: 12px; background: #fff;"></div>
                                <div id="recargaStripeErrors" class="text-danger small mt-2" style="display:none;"></div>
                            </div>

                            <div class="mt-3" id="recargaResultado" style="display:none;"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
                            <button type="button" class="btn btn-primary" id="btnConfirmarRecarga" onclick="confirmarRecargaCarteira()">Gerar pagamento</button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- OCULTO TEMPORARIAMENTE - Orçamentos do Redirecionamento
            <div class="card shadow-sm mt-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-clipboard-list"></i> Orçamentos do Redirecionamento</h5>
                    <a href="/assessoria" class="btn btn-sm btn-outline-primary">Novo Orçamento</a>
                </div>
                <div class="card-body">
                    <?php $orcamentosAssessoria = $orcamentos_assessoria ?? []; ?>
                    <?php if (empty($orcamentosAssessoria)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-file-invoice-dollar fa-3x text-muted mb-3"></i>
                            <h6>Nenhum orçamento ainda</h6>
                            <p class="text-muted">Gere um orçamento pelo Redirecionamento para aparecer aqui.</p>
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
            -->

            <script src="https://js.stripe.com/v3/"></script>

            <script>
            let recargaModalInstance = null;
            let recargaStripeClient = null;
            let recargaStripeElements = null;
            let recargaStripeCard = null;
            let recargaStripeCardMounted = false;

            function abrirModalRecargaCarteira() {
                const el = document.getElementById('modalRecargaCarteira');
                if (!el) return;
                if (!recargaModalInstance) {
                    recargaModalInstance = new bootstrap.Modal(el);
                }
                document.getElementById('recargaValor').value = '';
                document.getElementById('recargaMoeda').value = 'BRL';
                document.getElementById('recargaMetodo').value = 'pix';
                const info = document.getElementById('recargaInfo');
                const res = document.getElementById('recargaResultado');
                const err = document.getElementById('recargaStripeErrors');
                if (info) { info.style.display = 'none'; info.innerHTML = ''; }
                if (res) { res.style.display = 'none'; res.innerHTML = ''; }
                if (err) { err.style.display = 'none'; err.textContent = ''; }
                onRecargaMoedaChange();
                recargaModalInstance.show();
            }

            function onRecargaMoedaChange() {
                const moeda = (document.getElementById('recargaMoeda')?.value || 'BRL').toString().toUpperCase();
                const metodoWrap = document.getElementById('recargaMetodoWrap');
                const stripeWrap = document.getElementById('recargaStripeWrap');
                const cartaoWrap = document.getElementById('recargaCartaoWrap');
                const btn = document.getElementById('btnConfirmarRecarga');

                if (moeda === 'USD') {
                    if (metodoWrap) metodoWrap.style.display = 'none';
                    if (cartaoWrap) cartaoWrap.style.display = 'none';
                    if (stripeWrap) stripeWrap.style.display = 'block';
                    if (btn) btn.textContent = 'Pagar com Stripe';
                    ensureRecargaStripeInit();
                    mountRecargaStripeCard();
                    return;
                }

                if (metodoWrap) metodoWrap.style.display = '';
                if (stripeWrap) stripeWrap.style.display = 'none';
                if (btn) btn.textContent = 'Gerar pagamento';

                const metodo = (document.getElementById('recargaMetodo')?.value || 'pix').toString();
                if (cartaoWrap) {
                    cartaoWrap.style.display = (metodo === 'cartao_credito') ? '' : 'none';
                }
            }

            document.getElementById('recargaMetodo')?.addEventListener('change', function() {
                onRecargaMoedaChange();
            });

            function ensureRecargaStripeInit() {
                const stripeEnabled = <?php echo json_encode((bool) ($stripe_enabled ?? false)); ?>;
                const publishableKey = <?php echo json_encode((string) ($stripe_publishable_key ?? '')); ?>;
                if (!stripeEnabled || !publishableKey) {
                    return false;
                }
                if (typeof Stripe !== 'function') {
                    return false;
                }
                if (!recargaStripeClient) {
                    recargaStripeClient = Stripe(publishableKey);
                    recargaStripeElements = recargaStripeClient.elements();
                    recargaStripeCard = recargaStripeElements.create('card');
                    recargaStripeCardMounted = false;
                }
                return true;
            }

            function mountRecargaStripeCard() {
                const wrap = document.getElementById('recargaStripeWrap');
                const target = document.getElementById('recargaStripeCard');
                if (!wrap || wrap.style.display === 'none' || !target) {
                    return;
                }
                if (!ensureRecargaStripeInit()) {
                    return;
                }
                if (recargaStripeCard && !recargaStripeCardMounted) {
                    recargaStripeCard.mount('#recargaStripeCard');
                    recargaStripeCardMounted = true;
                }
            }

            function setRecargaInfo(html) {
                const info = document.getElementById('recargaInfo');
                if (!info) return;
                info.innerHTML = html;
                info.style.display = html ? '' : 'none';
            }

            function setRecargaResultado(html) {
                const res = document.getElementById('recargaResultado');
                if (!res) return;
                res.innerHTML = html;
                res.style.display = html ? '' : 'none';
            }

            function setRecargaStripeError(msg) {
                const el = document.getElementById('recargaStripeErrors');
                if (!el) return;
                el.textContent = msg || '';
                el.style.display = msg ? '' : 'none';
            }

            async function confirmarRecargaCarteira() {
                setRecargaInfo('');
                setRecargaResultado('');
                setRecargaStripeError('');

                const moeda = (document.getElementById('recargaMoeda')?.value || 'BRL').toString().toUpperCase();
                const valor = parseFloat((document.getElementById('recargaValor')?.value || '0').toString().replace(',', '.'));
                if (!valor || valor <= 0) {
                    setRecargaInfo('Informe um valor válido.');
                    return;
                }

                if (moeda === 'USD') {
                    if (!ensureRecargaStripeInit()) {
                        setRecargaInfo('Stripe não está configurado.');
                        return;
                    }

                    const r = await fetch('/carteira/recarga/criar', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ moeda: 'USD', valor: valor })
                    });
                    const data = await r.json();
                    if (!data || !data.success || !data.client_secret) {
                        setRecargaInfo('Falha ao iniciar recarga: ' + (data && (data.error || data.message) ? (data.error || data.message) : 'erro')); 
                        return;
                    }

                    mountRecargaStripeCard();
                    const confirmRes = await recargaStripeClient.confirmCardPayment(data.client_secret, {
                        payment_method: { card: recargaStripeCard }
                    });
                    if (confirmRes.error) {
                        setRecargaStripeError(confirmRes.error.message || 'Pagamento não autorizado');
                        return;
                    }

                    const pi = confirmRes.paymentIntent;
                    const piId = (pi && pi.id) ? pi.id : (data.payment_intent_id || '');
                    if (!piId) {
                        setRecargaStripeError('PaymentIntent inválido');
                        return;
                    }

                    const f = new URLSearchParams({ recarga_id: String(data.recarga_id || ''), payment_intent_id: String(piId) }).toString();
                    const respFin = await fetch('/carteira/recarga/stripe/finalizar', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                        body: f
                    });
                    const fin = await respFin.json();
                    if (!fin || !fin.success) {
                        setRecargaStripeError(fin && (fin.error || fin.message) ? (fin.error || fin.message) : 'Falha ao finalizar');
                        return;
                    }

                    if (recargaModalInstance) {
                        recargaModalInstance.hide();
                    }
                    setTimeout(function() {
                        window.location.reload();
                    }, 300);
                    return;
                }

                const metodo = (document.getElementById('recargaMetodo')?.value || 'pix').toString();
                const payload = { moeda: 'BRL', valor: valor, metodo: metodo };

                if (metodo === 'cartao_credito') {
                    payload.card_holder_name = (document.getElementById('recargaCardHolder')?.value || '').toString();
                    payload.card_number = (document.getElementById('recargaCardNumber')?.value || '').toString();
                    payload.card_expiry_month = (document.getElementById('recargaCardExpMonth')?.value || '').toString();
                    payload.card_expiry_year = (document.getElementById('recargaCardExpYear')?.value || '').toString();
                    payload.card_cvv = (document.getElementById('recargaCardCvv')?.value || '').toString();
                    payload.installments = parseInt((document.getElementById('recargaInstallments')?.value || '1').toString(), 10) || 1;
                }

                const r = await fetch('/carteira/recarga/criar', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(payload)
                });
                const data = await r.json();
                if (!data || !data.success) {
                    setRecargaInfo('Falha ao gerar pagamento: ' + (data && (data.error || data.message) ? (data.error || data.message) : 'erro'));
                    return;
                }

                let html = '';
                if (data.pix && (data.pix.encodedImage || data.pix.payload)) {
                    if (data.pix.encodedImage) {
                        html += '<div class="mb-2"><strong>PIX QR Code</strong></div>';
                        html += '<img alt="PIX" style="max-width:220px" class="img-fluid border rounded" src="data:image/png;base64,' + String(data.pix.encodedImage) + '">';
                    }
                    if (data.pix.payload) {
                        html += '<div class="mt-2"><small class="text-muted">Copia e cola:</small><div class="border rounded p-2" style="word-break:break-all;">' + String(data.pix.payload) + '</div></div>';
                    }
                }
                if (data.bankSlipUrl) {
                    html += '<div class="mt-2"><a class="btn btn-sm btn-outline-secondary" target="_blank" href="' + String(data.bankSlipUrl) + '">Abrir boleto</a></div>';
                }
                if (data.invoiceUrl) {
                    html += '<div class="mt-2"><a class="btn btn-sm btn-outline-primary" target="_blank" href="' + String(data.invoiceUrl) + '">Abrir link de pagamento</a></div>';
                }
                if (data.digitableLine) {
                    html += '<div class="mt-2"><small class="text-muted">Linha digitável:</small><div class="border rounded p-2" style="word-break:break-all;">' + String(data.digitableLine) + '</div></div>';
                }

                if (!html) {
                    html = '<div class="alert alert-success mb-0">Pagamento gerado. Aguarde a confirmação para crédito automático na carteira.</div>';
                }
                setRecargaResultado(html);
            }
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
