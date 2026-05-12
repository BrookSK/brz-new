<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;
use App\Services\BackupService;

class AdminBackupController extends Controller {

    private function fmtBytes(int $bytes): string {
        if ($bytes < 1024) return $bytes . ' B';
        $kb = $bytes / 1024;
        if ($kb < 1024) return number_format($kb, 1, ',', '.') . ' KB';
        $mb = $kb / 1024;
        if ($mb < 1024) return number_format($mb, 1, ',', '.') . ' MB';
        $gb = $mb / 1024;
        return number_format($gb, 2, ',', '.') . ' GB';
    }

    private function isPathInside(string $baseDir, string $path): bool {
        $baseReal = realpath($baseDir);
        $pathReal = realpath($path);
        if ($baseReal === false || $pathReal === false) {
            return false;
        }
        $baseReal = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $baseReal), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        $pathReal = rtrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $pathReal), DIRECTORY_SEPARATOR);
        return strncmp($pathReal, $baseReal, strlen($baseReal)) === 0;
    }

    public function download(Request $request, $id, $tipo) {
        $auth = new AuthService();
        $auth->requerPerfil('admin');

        $service = new BackupService();
        $runId = (int) $id;
        $tipo = strtolower(trim((string) $tipo));
        if ($tipo !== 'db' && $tipo !== 'files') {
            http_response_code(400);
            echo 'Tipo inválido';
            exit;
        }

        try {
            $run = $service->getBackupRun($runId);
            $backupDir = $service->getBackupDir();

            $path = $tipo === 'db'
                ? (string) ($run['db_sql_path'] ?? '')
                : (string) ($run['files_zip_path'] ?? '');

            if ($path === '' || !file_exists($path) || !is_file($path)) {
                http_response_code(404);
                echo 'Arquivo não encontrado';
                exit;
            }

            if (!$this->isPathInside($backupDir, $path)) {
                http_response_code(403);
                echo 'Acesso negado';
                exit;
            }

            $filename = basename($path);
            $size = (int) (@filesize($path) ?: 0);

            while (ob_get_level() > 0) {
                @ob_end_clean();
            }

            header('Content-Description: File Transfer');
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . str_replace('"', '', $filename) . '"');
            header('Content-Transfer-Encoding: binary');
            header('Expires: 0');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');
            if ($size > 0) {
                header('Content-Length: ' . $size);
            }

            $fh = fopen($path, 'rb');
            if ($fh === false) {
                http_response_code(500);
                echo 'Erro ao abrir arquivo';
                exit;
            }
            fpassthru($fh);
            fclose($fh);
            exit;
        } catch (\Throwable $e) {
            http_response_code(500);
            echo 'Erro ao preparar download: ' . $e->getMessage();
            exit;
        }
    }

    public function index(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfil('admin');

        $service = new BackupService();
        $cfg = $service->getConfig();
        $cronUrl = '';
        try {
            $cronUrl = $service->getCronUrl();
        } catch (\Throwable $e) {
            $cronUrl = '';
        }

        $runs = [];
        try {
            $runs = $service->listBackups(200);
        } catch (\Throwable $e) {
            $runs = [];
        }

        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Backup - Braziliana Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';

        renderAdminSidebarStyles();

        echo '</head>
<body>
    <div class="container-fluid">
        <div class="row">';

        renderAdminSidebar('backup');

        $pasta = htmlspecialchars((string) ($cfg['pasta_backup'] ?? ''), ENT_QUOTES, 'UTF-8');
        $token = htmlspecialchars((string) ($cfg['cron_token'] ?? ''), ENT_QUOTES, 'UTF-8');
        $cronUrlEsc = htmlspecialchars((string) $cronUrl, ENT_QUOTES, 'UTF-8');
        $horario = htmlspecialchars((string) ($cfg['horario'] ?? '02:00:00'), ENT_QUOTES, 'UTF-8');
        $reter = (int) ($cfg['reter_quantidade'] ?? 10);
        $rec = (string) ($cfg['recorrencia'] ?? 'diaria');

        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="page-title">Backup do Sistema</h1>
            </div>';

        echo '<div class="alert alert-info">
            <div class="fw-semibold mb-1">Restauração de arquivos do sistema</div>
            <div>Os arquivos do sistema são versionados via GitHub/Git. Para restaurar arquivos, utilize o fluxo de revert/rollback no repositório. Este módulo restaura apenas o banco de dados.</div>
        </div>';

        echo '<div class="row g-4">
            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <div class="fw-semibold"><i class="fas fa-sliders-h me-2"></i>Configurações</div>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="/admin/backup/salvar">
                            <div class="mb-3">
                                <label class="form-label">Recorrência</label>
                                <select class="form-select" name="recorrencia">
                                    <option value="diaria" ' . ($rec === 'diaria' ? 'selected' : '') . '>Diária</option>
                                    <option value="semanal" ' . ($rec === 'semanal' ? 'selected' : '') . '>Semanal</option>
                                    <option value="mensal" ' . ($rec === 'mensal' ? 'selected' : '') . '>Mensal</option>
                                </select>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Horário do backup</label>
                                <input class="form-control" type="time" name="horario" value="' . htmlspecialchars(substr($horario, 0, 5), ENT_QUOTES, 'UTF-8') . '">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Quantos backups reter</label>
                                <input class="form-control" type="number" min="1" max="365" name="reter_quantidade" value="' . (int) $reter . '">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Token do CRON</label>
                                <input class="form-control" type="text" name="cron_token" value="' . $token . '">
                                <div class="form-text">Use esse token no agendamento para chamar a URL do cron com segurança.</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Pasta onde salva</label>
                                <input class="form-control" type="text" name="pasta_backup" value="' . $pasta . '">
                                <div class="form-text">A pasta será criada automaticamente (se possível).</div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label">URL do CRON</label>
                                <input class="form-control" type="text" readonly value="' . $cronUrlEsc . '">
                            </div>

                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Salvar</button>
                                <a class="btn btn-outline-secondary" href="/admin/backup"><i class="fas fa-rotate me-1"></i>Recarregar</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <div class="fw-semibold"><i class="fas fa-bolt me-2"></i>Ações</div>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="/admin/backup/agora" class="mb-3" id="backupNowForm">
                            <button type="submit" class="btn btn-success" id="backupNowBtn">
                                <span class="backup-now-idle"><i class="fas fa-play me-1"></i>Fazer backup agora</span>
                                <span class="backup-now-loading" style="display:none;"><span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Fazendo backup...</span>
                            </button>
                        </form>
                        <div class="text-muted small">O backup cria um dump do banco (.sql) e um .zip dos arquivos (somente referência). A restauração atua apenas no banco.</div>
                    </div>
                </div>
            </div>
        </div>';

        echo '<div class="card mt-4">
            <div class="card-header d-flex align-items-center justify-content-between">
                <div class="fw-semibold"><i class="fas fa-clock-rotate-left me-2"></i>Backups realizados</div>
            </div>
            <div class="card-body">';

        if (empty($runs)) {
            echo '<div class="text-muted">Nenhum backup registrado.</div>';
        } else {
            echo '<div class="table-responsive">
                <table class="table table-striped align-middle">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Data/Hora</th>
                            <th>DB</th>
                            <th>Arquivos (referência)</th>
                            <th>Status</th>
                            <th class="text-end">Ações</th>
                        </tr>
                    </thead>
                    <tbody>';

            foreach ($runs as $r) {
                $id = (int) ($r['id'] ?? 0);
                $created = (string) ($r['created_at'] ?? '');
                $status = (string) ($r['status'] ?? 'ok');
                $dbPath = (string) ($r['db_sql_path'] ?? '');
                $zipPath = (string) ($r['files_zip_path'] ?? '');
                $dbSize = (int) ($r['db_size_bytes'] ?? 0);
                $zipSize = (int) ($r['files_size_bytes'] ?? 0);

                $dbName = htmlspecialchars(basename($dbPath), ENT_QUOTES, 'UTF-8');
                $zipName = htmlspecialchars(basename($zipPath), ENT_QUOTES, 'UTF-8');

                $createdFmt = $created !== '' ? date('d/m/Y H:i', strtotime($created)) : '-';
                $statusBadge = $status === 'ok'
                    ? '<span class="badge bg-success">OK</span>'
                    : '<span class="badge bg-danger">Erro</span>';

                $dbDownload = '';
                if ($dbPath !== '' && file_exists($dbPath) && is_file($dbPath)) {
                    $dbDownload = '<a class="ms-2 text-decoration-none" href="/admin/backup/download/' . $id . '/db" title="Download DB"><i class="fas fa-download"></i></a>';
                }
                $zipDownload = '';
                if ($zipPath !== '' && file_exists($zipPath) && is_file($zipPath)) {
                    $zipDownload = '<a class="ms-2 text-decoration-none" href="/admin/backup/download/' . $id . '/files" title="Download arquivos"><i class="fas fa-download"></i></a>';
                }

                echo '<tr>
                    <td>#' . $id . '</td>
                    <td>' . htmlspecialchars($createdFmt, ENT_QUOTES, 'UTF-8') . '</td>
                    <td>
                        <div class="fw-semibold">' . $dbName . $dbDownload . '</div>
                        <div class="text-muted small">' . htmlspecialchars($this->fmtBytes($dbSize), ENT_QUOTES, 'UTF-8') . '</div>
                    </td>
                    <td>
                        <div class="fw-semibold">' . $zipName . $zipDownload . '</div>
                        <div class="text-muted small">' . htmlspecialchars($this->fmtBytes($zipSize), ENT_QUOTES, 'UTF-8') . '</div>
                    </td>
                    <td>' . $statusBadge . '</td>
                    <td class="text-end">
                        <form method="POST" action="/admin/backup/restaurar/' . $id . '" style="display:inline-block" class="restoreForm" data-backup-id="' . $id . '">
                            <input type="hidden" name="tipo" value="db">
                            <div class="input-group input-group-sm" style="width: 260px; display:inline-flex;">
                                <select class="form-select" name="tipo_select" aria-label="Tipo de restauração">
                                    <option value="db" selected>Restaurar banco</option>
                                    <option value="files">Restaurar arquivos</option>
                                </select>
                                <button type="submit" class="btn btn-warning"><i class="fas fa-rotate-left"></i></button>
                            </div>
                        </form>
                        <form method="POST" action="/admin/backup/excluir/' . $id . '" style="display:inline-block" onsubmit="return confirm(\'Excluir backup #' . $id . '?\')">
                            <button type="submit" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i> Excluir</button>
                        </form>
                    </td>
                </tr>';
            }

            echo '</tbody></table></div>';
        }

        echo '</div>
        </div>';

        echo '</main>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        (function(){
            var form = document.getElementById("backupNowForm");
            var btn = document.getElementById("backupNowBtn");
            if (form && btn) {
                form.addEventListener("submit", function(){
                    try {
                        btn.disabled = true;
                        var idle = btn.querySelector(".backup-now-idle");
                        var loading = btn.querySelector(".backup-now-loading");
                        if (idle) idle.style.display = "none";
                        if (loading) loading.style.display = "inline";
                    } catch(e) {}
                });
            }

            var restoreForms = document.querySelectorAll("form.restoreForm");
            restoreForms.forEach(function(f){
                f.addEventListener("submit", function(e){
                    try {
                        var sel = f.querySelector("select[name=\"tipo_select\"]");
                        var hidden = f.querySelector("input[name=\"tipo\"]");
                        var v = sel ? String(sel.value || "db") : "db";
                        if (hidden) hidden.value = v;

                        if (v === "files") {
                            e.preventDefault();
                            alert("Restauração de arquivos do sistema\n\nOs arquivos são versionados via GitHub/Git. Para restaurar/rollback de arquivos, use revert/rollback no repositório (Git). Este módulo restaura apenas o banco de dados.");
                            return false;
                        }

                        var bid = f.getAttribute("data-backup-id") || "";
                        return confirm("Confirmar restauração do banco a partir do backup #" + bid + "?");
                    } catch(err) {
                        return true;
                    }
                });
            });
        })();
    </script>
</body>
</html>';
        exit;
    }

    public function salvar(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfil('admin');

        $service = new BackupService();

        try {
            $service->saveConfig([
                'recorrencia' => (string) $request->getParam('recorrencia', 'diaria'),
                'horario' => (string) $request->getParam('horario', '02:00'),
                'reter_quantidade' => (int) $request->getParam('reter_quantidade', 10),
                'cron_token' => (string) $request->getParam('cron_token', ''),
                'pasta_backup' => (string) $request->getParam('pasta_backup', ''),
            ]);

            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['message'] = 'Configurações de backup salvas.';
            $_SESSION['message_type'] = 'success';
        } catch (\Throwable $e) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['message'] = 'Erro ao salvar configurações: ' . $e->getMessage();
            $_SESSION['message_type'] = 'danger';
        }

        header('Location: /admin/backup');
        exit;
    }

    public function agora(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfil('admin');

        $service = new BackupService();
        try {
            $id = $service->createBackupNow('manual');
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['message'] = 'Backup criado com sucesso (#' . $id . ').';
            $_SESSION['message_type'] = 'success';
        } catch (\Throwable $e) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['message'] = 'Erro ao criar backup: ' . $e->getMessage();
            $_SESSION['message_type'] = 'danger';
        }

        header('Location: /admin/backup');
        exit;
    }

    public function excluir(Request $request, $id) {
        $auth = new AuthService();
        $auth->requerPerfil('admin');

        $service = new BackupService();
        try {
            $service->deleteBackup((int) $id);
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['message'] = 'Backup excluído.';
            $_SESSION['message_type'] = 'success';
        } catch (\Throwable $e) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['message'] = 'Erro ao excluir: ' . $e->getMessage();
            $_SESSION['message_type'] = 'danger';
        }

        header('Location: /admin/backup');
        exit;
    }

    public function restaurar(Request $request, $id) {
        $auth = new AuthService();
        $auth->requerPerfil('admin');

        $service = new BackupService();
        try {
            $tipo = strtoupper(trim((string) $request->getParam('tipo', 'DB')));
            if ($tipo === 'FILES' || $tipo === 'ARQUIVOS') {
                if (session_status() === PHP_SESSION_NONE) {
                    session_start();
                }
                $_SESSION['message'] = 'Restauração de arquivos: utilize rollback/revert via GitHub/Git. Este módulo restaura apenas o banco de dados.';
                $_SESSION['message_type'] = 'info';
                header('Location: /admin/backup');
                exit;
            }
            $service->restoreDatabase((int) $id);
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['message'] = 'Banco restaurado a partir do backup #' . (int) $id . '.';
            $_SESSION['message_type'] = 'success';
        } catch (\Throwable $e) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $_SESSION['message'] = 'Erro ao restaurar: ' . $e->getMessage();
            $_SESSION['message_type'] = 'danger';
        }

        header('Location: /admin/backup');
        exit;
    }
}
