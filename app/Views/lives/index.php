<?php
/** @var array|null $liveAtiva */
/** @var array $agendadas */
/** @var array $encerradas */
/** @var string $title */
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --pink: #ff2d55; }
        body { background: #0a0a0a; color: #fff; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; }
        a { color: inherit; text-decoration: none; }
        .page-header { padding: 40px 0 20px; text-align: center; }
        .page-header h1 { font-size: 28px; font-weight: 700; }
        .page-header p { color: rgba(255,255,255,0.6); }

        /* Live ativa - destaque */
        .live-now-card {
            position: relative;
            border-radius: 16px;
            overflow: hidden;
            background: #1a1a1a;
            margin-bottom: 40px;
            cursor: pointer;
            transition: transform 0.2s;
        }
        .live-now-card:hover { transform: scale(1.01); }
        .live-now-cover {
            width: 100%;
            height: 300px;
            object-fit: cover;
            opacity: 0.7;
        }
        .live-now-overlay {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 30px 24px 24px;
            background: linear-gradient(to top, rgba(0,0,0,0.9) 0%, transparent 100%);
        }
        .live-now-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: var(--pink);
            padding: 4px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 700;
            margin-bottom: 10px;
            animation: pulse 2s infinite;
        }
        @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:.7} }
        .live-now-title { font-size: 22px; font-weight: 700; margin: 0; }
        .live-now-meta { color: rgba(255,255,255,0.6); font-size: 14px; margin-top: 6px; }

        /* Cards de lives */
        .section-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 16px;
            padding-left: 4px;
        }
        .live-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); gap: 16px; margin-bottom: 40px; }
        .live-card {
            background: #1a1a1a;
            border-radius: 12px;
            overflow: hidden;
            transition: transform 0.2s;
            cursor: pointer;
        }
        .live-card:hover { transform: translateY(-3px); }
        .live-card-img {
            width: 100%;
            height: 160px;
            object-fit: cover;
            background: #2a2a2a;
        }
        .live-card-body { padding: 14px; }
        .live-card-title { font-size: 15px; font-weight: 600; margin: 0 0 6px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .live-card-meta { font-size: 13px; color: rgba(255,255,255,0.5); }
        .live-card-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            margin-bottom: 6px;
        }
        .badge-scheduled { background: rgba(56,189,248,0.15); color: #38bdf8; }
        .badge-ended { background: rgba(255,255,255,0.1); color: rgba(255,255,255,0.6); }

        .empty-state { text-align: center; padding: 60px 20px; color: rgba(255,255,255,0.4); }
        .empty-state i { font-size: 48px; margin-bottom: 16px; }

        .back-link { display: inline-flex; align-items: center; gap: 8px; color: rgba(255,255,255,0.6); margin-bottom: 20px; font-size: 14px; }
        .back-link:hover { color: #fff; }
    </style>
</head>
<body>
<div class="container" style="max-width:900px">
    <a href="/" class="back-link"><i class="fas fa-arrow-left"></i> Voltar ao site</a>

    <div class="page-header">
        <h1><i class="fas fa-video me-2"></i>Lives</h1>
        <p>Assista ao vivo e compre com 1 clique</p>
    </div>

    <!-- Live ativa -->
    <?php if ($liveAtiva): ?>
        <a href="/lives/<?= $liveAtiva['id'] ?>" class="live-now-card d-block">
            <?php if (!empty($liveAtiva['cover_url'])): ?>
                <img src="<?= htmlspecialchars($liveAtiva['cover_url']) ?>" class="live-now-cover" alt="">
            <?php else: ?>
                <div class="live-now-cover" style="background:linear-gradient(135deg,#1a1a2e,#16213e)"></div>
            <?php endif; ?>
            <div class="live-now-overlay">
                <div class="live-now-badge"><span style="width:6px;height:6px;background:#fff;border-radius:50%"></span> AO VIVO AGORA</div>
                <h2 class="live-now-title"><?= htmlspecialchars($liveAtiva['title']) ?></h2>
                <div class="live-now-meta">
                    <i class="fas fa-eye"></i> <?= (int)($liveAtiva['viewers_current'] ?? 0) ?> assistindo
                    · <i class="fas fa-heart"></i> <?= (int)($liveAtiva['likes_count'] ?? 0) ?>
                </div>
            </div>
        </a>
    <?php endif; ?>

    <!-- Agendadas -->
    <?php if (!empty($agendadas)): ?>
        <h3 class="section-title"><i class="fas fa-calendar me-2"></i>Próximas lives</h3>
        <div class="live-grid">
            <?php foreach ($agendadas as $live): ?>
                <div class="live-card" onclick="location.href='/lives/<?= $live['id'] ?>'">
                    <?php if (!empty($live['cover_url'])): ?>
                        <img src="<?= htmlspecialchars($live['cover_url']) ?>" class="live-card-img" alt="">
                    <?php else: ?>
                        <div class="live-card-img" style="display:flex;align-items:center;justify-content:center"><i class="fas fa-video fa-2x" style="color:#333"></i></div>
                    <?php endif; ?>
                    <div class="live-card-body">
                        <span class="live-card-badge badge-scheduled"><i class="fas fa-clock me-1"></i>Agendada</span>
                        <h4 class="live-card-title"><?= htmlspecialchars($live['title']) ?></h4>
                        <div class="live-card-meta">
                            <?php if ($live['scheduled_at']): ?>
                                <i class="fas fa-calendar-alt"></i> <?= date('d/m/Y', strtotime($live['scheduled_at'])) ?>
                                às <?= date('H:i', strtotime($live['scheduled_at'])) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Encerradas (com gravação) -->
    <?php if (!empty($encerradas)): ?>
        <h3 class="section-title"><i class="fas fa-play-circle me-2"></i>Lives anteriores</h3>
        <div class="live-grid">
            <?php foreach ($encerradas as $live): ?>
                <div class="live-card" onclick="location.href='/lives/<?= $live['id'] ?>'">
                    <?php if (!empty($live['cover_url'])): ?>
                        <img src="<?= htmlspecialchars($live['cover_url']) ?>" class="live-card-img" alt="">
                    <?php else: ?>
                        <div class="live-card-img" style="display:flex;align-items:center;justify-content:center"><i class="fas fa-play fa-2x" style="color:#333"></i></div>
                    <?php endif; ?>
                    <div class="live-card-body">
                        <span class="live-card-badge badge-ended"><i class="fas fa-check me-1"></i>Gravação</span>
                        <h4 class="live-card-title"><?= htmlspecialchars($live['title']) ?></h4>
                        <div class="live-card-meta">
                            <?php if ($live['live_ended_at']): ?>
                                <i class="fas fa-calendar-alt"></i> <?= date('d/m/Y', strtotime($live['live_ended_at'])) ?>
                            <?php endif; ?>
                            · <i class="fas fa-eye"></i> <?= (int)($live['viewers_peak'] ?? 0) ?> viewers
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Vazio -->
    <?php if (!$liveAtiva && empty($agendadas) && empty($encerradas)): ?>
        <div class="empty-state">
            <i class="fas fa-video"></i>
            <p>Nenhuma live programada no momento.<br>Volte em breve!</p>
        </div>
    <?php endif; ?>
</div>
</body>
</html>
