<div class="py-4">

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="mb-1"><i class="fas fa-brain me-2"></i>Aprendizado da IA</h2>
            <p class="text-muted mb-0">Pendências geradas automaticamente a partir de interações do copiloto</p>
        </div>
        <a href="/admin/copiloto" class="btn btn-outline-secondary btn-sm">
            <i class="fas fa-arrow-left me-1"></i>Voltar
        </a>
    </div>

            <?php if (!empty($_SESSION['flash_success'])): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <?= htmlspecialchars($_SESSION['flash_success']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['flash_success']); ?>
            <?php endif; ?>

            <!-- Filtros -->
            <div class="d-flex gap-2 mb-4">
                <?php
                $filtros = [
                    'pendente' => ['label' => 'Pendentes', 'badge' => $contadores['pendente'] ?? 0, 'color' => 'warning'],
                    'aceita' => ['label' => 'Aceitas', 'badge' => $contadores['aceita'] ?? 0, 'color' => 'success'],
                    'recusada' => ['label' => 'Recusadas', 'badge' => $contadores['recusada'] ?? 0, 'color' => 'danger'],
                    'todos' => ['label' => 'Todos', 'badge' => array_sum($contadores), 'color' => 'secondary'],
                ];
                foreach ($filtros as $key => $f):
                    $active = ($status ?? 'pendente') === $key ? 'btn-primary' : 'btn-outline-secondary';
                ?>
                    <a href="/admin/copiloto/aprendizado?status=<?= $key ?>" class="btn <?= $active ?> btn-sm">
                        <?= $f['label'] ?>
                        <span class="badge bg-<?= $f['color'] ?> ms-1"><?= $f['badge'] ?></span>
                    </a>
                <?php endforeach; ?>
            </div>

            <!-- Lista de Pendências -->
            <?php if (empty($pendencias)): ?>
                <div class="card p-5 text-center text-muted">
                    <i class="fas fa-check-circle fa-3x mb-3"></i>
                    <p>Nenhuma pendência encontrada neste filtro.</p>
                </div>
            <?php else: ?>
                <?php foreach ($pendencias as $p): ?>
                    <div class="card mb-3">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <div>
                                    <?php
                                    $tipos = json_decode($p['tipos'] ?? '[]', true) ?: [];
                                    foreach ($tipos as $tipo):
                                        $cor = $tipo === 'lacuna_documento' ? 'info' : 'warning';
                                        $label = $tipo === 'lacuna_documento' ? 'Lacuna de Documento' : 'Falha de Processo';
                                    ?>
                                        <span class="badge bg-<?= $cor ?> me-1"><?= $label ?></span>
                                    <?php endforeach; ?>
                                    <span class="badge bg-<?= $p['impacto_estimado'] === 'alto' ? 'danger' : ($p['impacto_estimado'] === 'medio' ? 'warning' : 'secondary') ?>">
                                        <?= ucfirst($p['impacto_estimado']) ?> impacto
                                    </span>
                                    <?php if ($p['frequencia'] > 1): ?>
                                        <span class="badge bg-dark"><?= $p['frequencia'] ?>× relatado</span>
                                    <?php endif; ?>
                                </div>
                                <small class="text-muted"><?= date('d/m/Y H:i', strtotime($p['criado_em'])) ?></small>
                            </div>

                            <h6 class="mb-2"><?= htmlspecialchars($p['resumo_problema']) ?></h6>

                            <?php if (!empty($p['mensagem_usuario'])): ?>
                                <div class="bg-light p-2 rounded mb-2">
                                    <small class="text-muted">Cliente disse:</small><br>
                                    <em>"<?= htmlspecialchars(mb_substr($p['mensagem_usuario'], 0, 300)) ?>"</em>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($p['texto_sugerido'])): ?>
                                <div class="border-start border-3 border-info ps-3 mb-2">
                                    <small class="text-muted">Sugestão para <?= htmlspecialchars($p['documento_afetado'] ?? 'documento') ?>:</small>
                                    <p class="mb-1"><?= nl2br(htmlspecialchars($p['texto_sugerido'])) ?></p>
                                    <?php if (!empty($p['justificativa'])): ?>
                                        <small class="text-muted"><strong>Justificativa:</strong> <?= htmlspecialchars($p['justificativa']) ?></small>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($p['sugestao_melhoria'])): ?>
                                <div class="border-start border-3 border-warning ps-3 mb-2">
                                    <small class="text-muted">Sugestão de processo (<?= htmlspecialchars($p['area_responsavel'] ?? '') ?>):</small>
                                    <p class="mb-1"><?= nl2br(htmlspecialchars($p['sugestao_melhoria'])) ?></p>
                                </div>
                            <?php endif; ?>

                            <?php if ($p['status'] === 'pendente'): ?>
                                <div class="d-flex gap-2 mt-3">
                                    <form method="POST" action="/admin/copiloto/aprendizado/aceitar/<?= $p['id'] ?>" class="d-inline">
                                        <button type="submit" class="btn btn-success btn-sm"><i class="fas fa-check me-1"></i>Aceitar</button>
                                    </form>
                                    <form method="POST" action="/admin/copiloto/aprendizado/recusar/<?= $p['id'] ?>" class="d-inline">
                                        <button type="submit" class="btn btn-outline-danger btn-sm"><i class="fas fa-times me-1"></i>Recusar</button>
                                    </form>
                                </div>
                            <?php else: ?>
                                <span class="badge bg-<?= $p['status'] === 'aceita' ? 'success' : 'danger' ?>">
                                    <?= ucfirst($p['status']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>

                <!-- Paginação simples -->
                <?php if ($total > $porPagina): ?>
                    <nav>
                        <ul class="pagination justify-content-center">
                            <?php for ($i = 1; $i <= ceil($total / $porPagina); $i++): ?>
                                <li class="page-item <?= $i === $pagina ? 'active' : '' ?>">
                                    <a class="page-link" href="/admin/copiloto/aprendizado?status=<?= $status ?>&page=<?= $i ?>"><?= $i ?></a>
                                </li>
                            <?php endfor; ?>
                        </ul>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>

</div>
