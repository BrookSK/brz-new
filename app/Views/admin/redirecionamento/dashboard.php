<?php
$sidebarActive = 'redirecionamento-dashboard';
$title = 'Redirecionamento - Dashboard';
$kpis = is_array($kpis ?? null) ? $kpis : [];
?>
<?php ob_start(); ?>
<div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">
        <div>
            <h1 class="h2 mb-1">Dashboard de Redirecionamento</h1>
            <div class="text-muted small">Visão geral operacional</div>
        </div>
        <div class="btn-group">
            <a class="btn btn-sm btn-outline-primary" href="/admin/redirecionamento/envios">Ver envios</a>
            <a class="btn btn-sm btn-outline-success" href="/admin/redirecionamento/divergencias">Ver divergências</a>
        </div>
    </div>

    <div class="row g-4 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Total de envios</div>
                    <div class="h4 mb-0"><?= (int) ($kpis['total_envios'] ?? 0) ?></div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Pendentes de pagamento</div>
                    <div class="h4 mb-0"><?= (int) ($kpis['pendentes_pagamento'] ?? 0) ?></div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Aguardando coleta</div>
                    <div class="h4 mb-0"><?= (int) ($kpis['aguardando_coleta'] ?? 0) ?></div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Divergências de peso</div>
                    <div class="h4 mb-0"><?= (int) ($kpis['divergencias_peso'] ?? 0) ?></div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Valores a receber</div>
                    <div class="h4 mb-0">US$ <?= number_format((float) ($kpis['valores_a_receber'] ?? 0), 2, ',', '.') ?></div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="text-muted small">Valores a devolver</div>
                    <div class="h4 mb-0">US$ <?= number_format((float) ($kpis['valores_a_devolver'] ?? 0), 2, ',', '.') ?></div>
                </div>
            </div>
        </div>
    </div>

    <div class="alert alert-info mb-0">
        Módulo de Redirecionamento em construção. Esta tela exibe a estrutura inicial (rotas + controllers + views).
    </div>
</div>
<?php $content = ob_get_clean(); include __DIR__ . '/../../layouts/admin.php'; ?>

