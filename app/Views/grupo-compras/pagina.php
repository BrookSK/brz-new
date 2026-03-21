<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($grupo['nome'], ENT_QUOTES, 'UTF-8') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root { --primary: #0b1f3a; }
        body { background: #f6f8fb; }
        .grupo-header { background: var(--primary); color: #fff; padding: 2rem 0 1.5rem; }
        .produto-card { border-radius: 16px; overflow: hidden; transition: transform .15s; }
        .produto-card:hover { transform: translateY(-3px); }
        .produto-card img { width: 100%; height: 200px; object-fit: cover; }
        .badge-imposto { background: rgba(245,158,11,.15); color: #92400e; border: 1px solid rgba(245,158,11,.3); }
    </style>
</head>
<body>
<div class="grupo-header">
    <div class="container">
        <h1 class="h3 mb-1"><?= htmlspecialchars($grupo['nome'], ENT_QUOTES, 'UTF-8') ?></h1>
        <?php if (!empty($grupo['descricao'])): ?>
        <p class="mb-2 opacity-75 small"><?= htmlspecialchars($grupo['descricao'], ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <?php if ($grupo['cobra_imposto_eua']): ?>
        <span class="badge badge-imposto"><i class="fas fa-percent me-1"></i>Inclui imposto EUA (10%)</span>
        <?php endif; ?>
    </div>
</div>

<div class="container py-4">
    <?php if (empty($produtos)): ?>
    <div class="text-center text-muted py-5">
        <i class="fas fa-box-open fa-3x mb-3 d-block opacity-25"></i>
        Nenhum produto disponível neste grupo no momento.
    </div>
    <?php else: ?>
    <div class="row g-3">
        <?php foreach ($produtos as $p): ?>
        <?php
            $foto = $p['foto_principal'] ?: '/uploads/produtos/placeholder.jpg';
            $nome = htmlspecialchars($p['name'] ?? '', ENT_QUOTES, 'UTF-8');
            $preco = number_format((float)($p['price'] ?? 0), 2, ',', '.');
            $link = '/produto/detalhes/' . (int)$p['id'];
        ?>
        <div class="col-6 col-md-4 col-lg-3">
            <a href="<?= $link ?>" class="text-decoration-none text-dark">
                <div class="card border-0 shadow-sm produto-card">
                    <img src="<?= htmlspecialchars($foto, ENT_QUOTES, 'UTF-8') ?>" alt="<?= $nome ?>">
                    <div class="card-body p-3">
                        <div class="fw-semibold small" style="line-height:1.3"><?= $nome ?></div>
                        <div class="mt-1 fw-bold" style="color:var(--primary)">US$ <?= $preco ?></div>
                    </div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div>
</body>
</html>
