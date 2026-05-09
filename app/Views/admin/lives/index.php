<?php
/** @var array $lives */
/** @var array $quota */
/** @var string $activePage */
/** @var string $title */
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?> - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .live-badge { animation: pulse 2s infinite; }
        @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.6} }
        .live-card { transition: transform .2s; }
        .live-card:hover { transform: translateY(-2px); }
        .quota-bar { height: 8px; border-radius: 4px; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <?php renderAdminSidebar($activePage); ?>
        
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-4">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1 class="h3"><i class="fas fa-video me-2"></i>Lives</h1>
                <a href="/admin/lives/nova" class="btn btn-primary">
                    <i class="fas fa-plus me-1"></i> Nova Live
                </a>
            </div>

            <!-- Cota de streaming -->
            <div class="card mb-4">
                <div class="card-body py-2">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <small class="text-muted">
                            Cota mensal: <?= $quota['minutes_used'] ?> / <?= $quota['minutes_included'] ?> min
                        </small>
                        <a href="/admin/configuracoes/lives" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-cog"></i> Config
                        </a>
                    </div>
                    <?php $pct = $quota['minutes_included'] > 0 ? min(100, ($quota['minutes_used'] / $quota['minutes_included']) * 100) : 0; ?>
                    <div class="progress quota-bar">
                        <div class="progress-bar <?= $pct > 90 ? 'bg-danger' : ($pct > 70 ? 'bg-warning' : 'bg-success') ?>" 
                             style="width: <?= $pct ?>%"></div>
                    </div>
                </div>
            </div>

            <!-- Tabs de status -->
            <ul class="nav nav-tabs mb-3">
                <li class="nav-item">
                    <a class="nav-link <?= empty($_GET['status'] ?? '') ? 'active' : '' ?>" href="/admin/lives">Todas</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($_GET['status'] ?? '') === 'live' ? 'active' : '' ?>" href="/admin/lives?status=live">
                        <span class="badge bg-danger live-badge me-1">●</span> Ao Vivo
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($_GET['status'] ?? '') === 'scheduled' ? 'active' : '' ?>" href="/admin/lives?status=scheduled">Agendadas</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= ($_GET['status'] ?? '') === 'ended' ? 'active' : '' ?>" href="/admin/lives?status=ended">Encerradas</a>
                </li>
            </ul>

            <!-- Lista de lives -->
            <?php if (empty($lives)): ?>
                <div class="text-center py-5 text-muted">
                    <i class="fas fa-video fa-3x mb-3"></i>
                    <p>Nenhuma live encontrada</p>
                    <a href="/admin/lives/nova" class="btn btn-primary">Criar primeira live</a>
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($lives as $live): ?>
                        <div class="col-12 col-md-6 col-lg-4">
                            <div class="card live-card h-100">
                                <?php if (!empty($live['cover_url'])): ?>
                                    <img src="<?= htmlspecialchars($live['cover_url']) ?>" class="card-img-top" style="height:150px;object-fit:cover" alt="">
                                <?php endif; ?>
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 class="card-title mb-0"><?= htmlspecialchars($live['title']) ?></h6>
                                        <?php if ($live['status'] === 'live'): ?>
                                            <span class="badge bg-danger live-badge">AO VIVO</span>
                                        <?php elseif ($live['status'] === 'scheduled'): ?>
                                            <span class="badge bg-info">Agendada</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Encerrada</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <?php if ($live['status'] === 'live'): ?>
                                        <small class="text-muted">
                                            <i class="fas fa-eye"></i> <?= $live['viewers_current'] ?> viewers
                                            · <i class="fas fa-heart"></i> <?= $live['likes_count'] ?>
                                        </small>
                                    <?php elseif ($live['scheduled_at']): ?>
                                        <small class="text-muted">
                                            <i class="fas fa-calendar"></i> <?= date('d/m/Y H:i', strtotime($live['scheduled_at'])) ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                                <div class="card-footer bg-transparent">
                                    <div class="btn-group btn-group-sm w-100">
                                        <?php if ($live['status'] === 'live'): ?>
                                            <a href="/admin/lives/<?= $live['id'] ?>/studio" class="btn btn-danger">
                                                <i class="fas fa-broadcast-tower"></i> Estúdio
                                            </a>
                                        <?php elseif ($live['status'] === 'scheduled'): ?>
                                            <a href="/admin/lives/<?= $live['id'] ?>/studio" class="btn btn-success">
                                                <i class="fas fa-play"></i> Iniciar
                                            </a>
                                        <?php endif; ?>
                                        <a href="/admin/lives/<?= $live['id'] ?>/editar" class="btn btn-outline-primary">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <?php if ($live['status'] === 'ended'): ?>
                                            <a href="/admin/lives/<?= $live['id'] ?>/report" class="btn btn-outline-info">
                                                <i class="fas fa-chart-bar"></i>
                                            </a>
                                        <?php endif; ?>
                                        <?php if ($live['status'] !== 'live'): ?>
                                            <button class="btn btn-outline-danger btn-delete-live" data-id="<?= $live['id'] ?>">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.querySelectorAll('.btn-delete-live').forEach(btn => {
    btn.addEventListener('click', async function() {
        if (!confirm('Excluir esta live?')) return;
        const id = this.dataset.id;
        const res = await fetch('/admin/lives/' + id, { method: 'DELETE' });
        if (res.ok) location.reload();
        else alert('Erro ao excluir');
    });
});
</script>
</body>
</html>
