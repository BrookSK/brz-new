<?php
$sidebarActive = 'redirecionamento-dashboard';
$title = 'Dashboard de Redirecionamento';
$kpis = is_array($kpis ?? null) ? $kpis : [];
?>
<?php ob_start(); ?>
<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h2 mb-1">Dashboard de Redirecionamento</h1>
            <div class="text-muted small">Visão geral operacional — atualizado em <?= date('d/m/Y H:i') ?></div>
        </div>
        <div class="btn-group">
            <a class="btn btn-sm btn-outline-primary" href="/admin/redirecionamento/envios/novo"><i class="fas fa-plus me-1"></i>Novo envio</a>
            <a class="btn btn-sm btn-outline-secondary" href="/admin/redirecionamento/coletas"><i class="fas fa-calendar me-1"></i>Coletas</a>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <?php
        $cards = [
            ['label'=>'Total de envios',        'val'=>(int)($kpis['total_envios']??0),          'icon'=>'fa-box',          'color'=>'#0b1f3a', 'link'=>'/admin/redirecionamento/envios'],
            ['label'=>'Pendentes de pagamento', 'val'=>(int)($kpis['pendentes_pagamento']??0),    'icon'=>'fa-clock',        'color'=>'#d97706', 'link'=>'/admin/redirecionamento/pagamentos'],
            ['label'=>'Aguardando coleta',      'val'=>(int)($kpis['aguardando_coleta']??0),      'icon'=>'fa-truck',        'color'=>'#2563eb', 'link'=>'/admin/redirecionamento/coletas'],
            ['label'=>'Divergências de peso',   'val'=>(int)($kpis['divergencias_peso']??0),      'icon'=>'fa-scale-balanced','color'=>'#dc2626','link'=>'/admin/redirecionamento/divergencias'],
            ['label'=>'A receber (USD)',         'val'=>'US$ '.number_format((float)($kpis['valores_a_receber']??0),2,',','.'), 'icon'=>'fa-arrow-up-right-dots','color'=>'#059669','link'=>'/admin/redirecionamento/divergencias'],
            ['label'=>'A devolver (USD)',        'val'=>'US$ '.number_format((float)($kpis['valores_a_devolver']??0),2,',','.'), 'icon'=>'fa-rotate-left','color'=>'#7c3aed','link'=>'/admin/redirecionamento/divergencias'],
        ];
        foreach ($cards as $c): ?>
        <div class="col-xl-2 col-md-4 col-6">
            <a href="<?= $c['link'] ?>" class="text-decoration-none">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div style="width:40px;height:40px;border-radius:10px;background:<?= $c['color'] ?>18;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                            <i class="fas <?= $c['icon'] ?>" style="color:<?= $c['color'] ?>"></i>
                        </div>
                        <div>
                            <div class="text-muted" style="font-size:.72rem"><?= $c['label'] ?></div>
                            <div class="fw-bold" style="font-size:1.1rem;color:<?= $c['color'] ?>"><?= $c['val'] ?></div>
                        </div>
                    </div>
                </div>
            </a>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="row g-3">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="fw-semibold mb-3">Ações rápidas</div>
                    <div class="d-grid gap-2">
                        <a href="/admin/redirecionamento/envios/novo" class="btn btn-primary"><i class="fas fa-plus me-2"></i>Novo envio</a>
                        <a href="/admin/redirecionamento/redirecionadores/novo" class="btn btn-outline-primary"><i class="fas fa-user-plus me-2"></i>Novo redirecionador</a>
                        <a href="/admin/redirecionamento/divergencias" class="btn btn-outline-danger"><i class="fas fa-scale-balanced me-2"></i>Ver divergências</a>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm">
                <div class="card-body">
                    <div class="fw-semibold mb-3">Navegação rápida</div>
                    <div class="list-group list-group-flush">
                        <?php
                        $links = [
                            ['/admin/redirecionamento/redirecionadores','fa-users','Redirecionadores'],
                            ['/admin/redirecionamento/envios','fa-truck-fast','Envios'],
                            ['/admin/redirecionamento/clientes','fa-address-book','Clientes'],
                            ['/admin/redirecionamento/tabela-pesos','fa-table','Tabela de pesos'],
                            ['/admin/redirecionamento/pagamentos','fa-credit-card','Pagamentos'],
                            ['/admin/redirecionamento/comprovantes','fa-file-upload','Comprovantes'],
                            ['/admin/redirecionamento/coletas','fa-calendar-check','Coletas'],
                        ];
                        foreach ($links as [$url,$icon,$label]): ?>
                        <a href="<?= $url ?>" class="list-group-item list-group-item-action border-0 px-0 py-2">
                            <i class="fas <?= $icon ?> me-2 text-muted" style="width:18px"></i><?= $label ?>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $content = ob_get_clean(); include __DIR__ . '/../../layouts/admin.php'; ?>
