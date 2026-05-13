<?php
$demandas = $demandas ?? [];
$colunas = ['pendente'=>'Pendente','em_analise'=>'Em Análise','em_execucao'=>'Em Execução','em_teste'=>'Em Teste','recusado'=>'Recusado','concluido'=>'Concluído'];
$cores = ['pendente'=>'#94a3b8','em_analise'=>'#3b82f6','em_execucao'=>'#f59e0b','em_teste'=>'#8b5cf6','recusado'=>'#ef4444','concluido'=>'#10b981'];
$porStatus = [];
foreach ($colunas as $k => $v) $porStatus[$k] = [];
foreach ($demandas as $d) { $s = $d['status'] ?? 'pendente'; if (isset($porStatus[$s])) $porStatus[$s][] = $d; }
?>
<div class="container-fluid py-3">
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
        <h1 class="page-title">Painel de Demandas</h1>
        <a href="/admin/demandas/nova" class="btn btn-dark btn-sm rounded-pill px-3"><i class="fas fa-plus me-1"></i>Nova Solicitação</a>
    </div>
    <?php if (!empty($_SESSION['message'])): ?><div class="alert alert-<?= $_SESSION['message_type'] ?? 'info' ?> alert-dismissible fade show"><?= htmlspecialchars($_SESSION['message']) ?><button type="button" class="btn-close" data-bs-dismiss="alert"></button></div><?php unset($_SESSION['message'], $_SESSION['message_type']); endif; ?>

    <div class="d-flex gap-3 overflow-auto pb-3" style="min-height:70vh;-webkit-overflow-scrolling:touch;">
        <?php foreach ($colunas as $statusKey => $statusLabel): $cards = $porStatus[$statusKey]; ?>
        <div class="flex-shrink-0" style="width:260px;min-width:260px;">
            <div class="card border-0 shadow-sm h-100" style="border-top:3px solid <?= $cores[$statusKey] ?>;">
                <div class="card-header bg-white border-0 py-2 d-flex align-items-center justify-content-between">
                    <span class="fw-bold small"><?= $statusLabel ?></span>
                    <span class="badge bg-secondary"><?= count($cards) ?></span>
                </div>
                <div class="card-body p-2" style="overflow-y:auto;max-height:calc(70vh - 60px);">
                    <?php if (empty($cards)): ?>
                    <div class="text-center text-muted small py-4"><i class="fas fa-inbox d-block mb-1"></i>Vazio</div>
                    <?php else: foreach ($cards as $card):
                        $prazo = $card['prazo_entrega'] ?? '';
                        $teste = $card['inicio_teste'] ?? '';
                        $testeExpired = false;
                        if ($teste && $statusKey === 'em_teste') {
                            $inicioT = new \DateTime($teste);
                            $agoraT = new \DateTime('now', new \DateTimeZone('America/Sao_Paulo'));
                            $huT = 0; $curT = clone $inicioT;
                            while ($curT < $agoraT) { $dsT = (int)$curT->format('N'); $hT = (int)$curT->format('G'); if ($dsT <= 5 && $hT >= 8 && $hT < 18) $huT++; $curT->modify('+1 hour'); }
                            $testeExpired = ($huT >= 24);
                        }
                    ?>
                    <a href="/admin/demandas/detalhe/<?= $card['id'] ?>" class="card mb-2 border-0 shadow-sm text-decoration-none <?= $testeExpired ? 'border-danger border-2' : '' ?>" style="<?= $testeExpired ? 'border:2px solid #ef4444!important;' : '' ?>">
                        <div class="card-body p-2">
                            <div class="fw-semibold small text-dark text-truncate"><?= htmlspecialchars($card['titulo'] ?? $card['bloco1_titulo'] ?? '') ?></div>
                            <div class="text-muted" style="font-size:10px;"><?= htmlspecialchars($card['solicitante'] ?? '') ?> · <?= date('d/m', strtotime($card['created_at'])) ?></div>
                            <?php if ($prazo && $statusKey === 'em_execucao'): ?><div class="mt-1"><span class="badge bg-warning text-dark" style="font-size:9px;"><i class="fas fa-clock me-1"></i>Prazo: <?= date('d/m/Y', strtotime($prazo)) ?></span></div><?php endif; ?>
                            <?php if ($teste && $statusKey === 'em_teste'):
                                // Calcular horas úteis restantes (seg-sex, 8h-18h)
                                $inicioTeste = new \DateTime($teste);
                                $agora = new \DateTime('now', new \DateTimeZone('America/Sao_Paulo'));
                                $horasUteis = 0;
                                $cursor = clone $inicioTeste;
                                while ($cursor < $agora) {
                                    $ds = (int)$cursor->format('N');
                                    $h = (int)$cursor->format('G');
                                    if ($ds <= 5 && $h >= 8 && $h < 18) $horasUteis++;
                                    $cursor->modify('+1 hour');
                                }
                                $restanteHoras = max(0, 24 - $horasUteis);
                                $testeExpiredUtil = ($restanteHoras <= 0);
                            ?><div class="mt-1"><span class="badge <?= $testeExpiredUtil ? 'bg-danger' : 'bg-purple' ?>" style="font-size:9px;"><i class="fas fa-stopwatch me-1"></i><?= $testeExpiredUtil ? 'EXPIRADO' : $restanteHoras.'h úteis restantes' ?></span></div><?php endif; ?>
                        </div>
                    </a>
                    <?php endforeach; endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
