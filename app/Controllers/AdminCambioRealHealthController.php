<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;
use App\Services\PaymentService;

class AdminCambioRealHealthController extends Controller
{
    public function index(Request $request)
    {
        $auth = new AuthService();
        $auth->requerPerfil('admin');

        $svc = new PaymentService();
        $results = $svc->testCambioRealConnectivity();

        $title = __('admin.cambioreal_health.page_title', 'Health Check — Câmbio Real');

        // Render inline (self-contained admin page)
        ?>
<!DOCTYPE html>
<html lang="<?= \App\Core\I18n::getLocaleHtml() ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title) ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f8f9fa; }
        .health-card { border-radius: 12px; transition: box-shadow 0.2s; }
        .health-card:hover { box-shadow: 0 4px 20px rgba(0,0,0,0.08); }
        .status-ok { border-left: 5px solid #198754; }
        .status-error, .status-auth_failed { border-left: 5px solid #dc3545; }
        .status-disabled { border-left: 5px solid #6c757d; }
        .status-not_configured { border-left: 5px solid #ffc107; }
        .status-unknown { border-left: 5px solid #0dcaf0; }
        .badge-latency { font-size: 0.75rem; }
    </style>
</head>
<body>
<div class="container py-4" style="max-width: 900px;">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="mb-0"><i class="fas fa-heartbeat text-danger me-2"></i><?= htmlspecialchars($title) ?></h4>
            <small class="text-muted"><?= __('admin.cambioreal_health.subtitle', 'Verificação de conectividade com as contas do gateway') ?></small>
        </div>
        <div>
            <a href="/admin/configuracoes" class="btn btn-outline-secondary btn-sm me-2">
                <i class="fas fa-cog me-1"></i><?= __('admin.cambioreal_health.settings', 'Configurações') ?>
            </a>
            <a href="/admin/cambioreal-health" class="btn btn-outline-primary btn-sm">
                <i class="fas fa-sync-alt me-1"></i><?= __('admin.cambioreal_health.test_again', 'Testar novamente') ?>
            </a>
        </div>
    </div>

    <div class="row g-3">
        <?php foreach ($results as $key => $account): ?>
        <div class="col-md-6">
            <div class="card health-card status-<?= htmlspecialchars($account['status']) ?>">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between mb-3">
                        <h6 class="mb-0">
                            <?php if ($key === 'produtos'): ?>
                                <i class="fas fa-box text-primary me-1"></i>
                            <?php else: ?>
                                <i class="fas fa-receipt text-warning me-1"></i>
                            <?php endif; ?>
                            <?= htmlspecialchars($account['label']) ?>
                        </h6>
                        <?php
                        $badgeClass = 'bg-secondary';
                        $badgeText = __('admin.cambioreal_health.status_unknown', 'Desconhecido');
                        $badgeIcon = 'fas fa-question-circle';
                        switch ($account['status']) {
                            case 'ok':
                                $badgeClass = 'bg-success';
                                $badgeText = __('admin.cambioreal_health.status_connected', 'Conectado');
                                $badgeIcon = 'fas fa-check-circle';
                                break;
                            case 'auth_failed':
                                $badgeClass = 'bg-danger';
                                $badgeText = __('admin.cambioreal_health.status_auth_failed', 'Falha Auth');
                                $badgeIcon = 'fas fa-times-circle';
                                break;
                            case 'error':
                                $badgeClass = 'bg-danger';
                                $badgeText = __('admin.cambioreal_health.status_error', 'Erro');
                                $badgeIcon = 'fas fa-exclamation-triangle';
                                break;
                            case 'disabled':
                                $badgeClass = 'bg-secondary';
                                $badgeText = __('admin.cambioreal_health.status_disabled', 'Desabilitado');
                                $badgeIcon = 'fas fa-power-off';
                                break;
                            case 'not_configured':
                                $badgeClass = 'bg-warning text-dark';
                                $badgeText = __('admin.cambioreal_health.status_not_configured', 'Não Configurado');
                                $badgeIcon = 'fas fa-exclamation-circle';
                                break;
                        }
                        ?>
                        <span class="badge <?= $badgeClass ?>">
                            <i class="<?= $badgeIcon ?> me-1"></i><?= $badgeText ?>
                        </span>
                    </div>

                    <table class="table table-sm table-borderless mb-0" style="font-size: 0.85rem;">
                        <tr>
                            <td class="text-muted" style="width: 110px;"><?= __('admin.cambioreal_health.base_url', 'Base URL') ?></td>
                            <td><code><?= htmlspecialchars($account['base_url']) ?></code></td>
                        </tr>
                        <tr>
                            <td class="text-muted">APP ID</td>
                            <td><code><?= htmlspecialchars($account['app_id_masked']) ?></code></td>
                        </tr>
                        <?php if ($account['http_code'] !== null): ?>
                        <tr>
                            <td class="text-muted">HTTP Code</td>
                            <td>
                                <span class="badge <?= ($account['http_code'] >= 200 && $account['http_code'] < 300) ? 'bg-success' : 'bg-danger' ?>">
                                    <?= $account['http_code'] ?>
                                </span>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php if ($account['latency_ms'] !== null): ?>
                        <tr>
                            <td class="text-muted"><?= __('admin.cambioreal_health.latency', 'Latência') ?></td>
                            <td>
                                <span class="badge badge-latency <?= $account['latency_ms'] < 1000 ? 'bg-success' : ($account['latency_ms'] < 3000 ? 'bg-warning text-dark' : 'bg-danger') ?>">
                                    <?= $account['latency_ms'] ?> ms
                                </span>
                            </td>
                        </tr>
                        <?php endif; ?>
                        <?php if (!empty($account['exchange_rate'])): ?>
                        <tr>
                            <td class="text-muted"><?= __('admin.cambioreal_health.exchange_rate', 'Câmbio (USD→BRL)') ?></td>
                            <td><strong>R$ <?= number_format($account['exchange_rate'], 4, ',', '.') ?></strong></td>
                        </tr>
                        <?php endif; ?>
                        <?php if (!empty($account['error'])): ?>
                        <tr>
                            <td class="text-muted"><?= __('admin.cambioreal_health.detail', 'Detalhe') ?></td>
                            <td><span class="text-danger"><?= htmlspecialchars($account['error']) ?></span></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="mt-4 text-center text-muted" style="font-size: 0.8rem;">
<?= __('admin.cambioreal_health.verified_at', 'Verificado em {date}', ['date' => date('d/m/Y H:i:s')]) ?> &bull; 
        <?= __('admin.cambioreal_health.footer_note', 'O teste usa o endpoint {endpoint} para validar autenticação e retornar o câmbio atual.', ['endpoint' => '<code>/service/v1/checkout/simulator</code>']) ?>
    </div>
</div>
</body>
</html>
        <?php
    }
}
