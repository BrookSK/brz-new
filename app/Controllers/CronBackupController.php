<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\BackupService;

class CronBackupController extends Controller {

    public function run(Request $request) {
        // Cron não tem limite de tempo do browser
        @set_time_limit(0);
        @ini_set('max_execution_time', '0');
        ignore_user_abort(true);

        $service = new BackupService();
        $cfg = $service->getConfig();

        $token = (string) $request->getParam('token', '');
        $expected = (string) ($cfg['cron_token'] ?? '');

        if ($expected === '' || $token === '' || !hash_equals($expected, $token)) {
            http_response_code(403);
            header('Content-Type: text/plain; charset=UTF-8');
            echo 'forbidden';
            exit;
        }

        $now = new \DateTime('now');
        $lastRun = null;
        try {
            if (!empty($cfg['last_run_at'])) {
                $lastRun = new \DateTime((string) $cfg['last_run_at']);
            }
        } catch (\Throwable $e) {
            $lastRun = null;
        }

        $due = false;
        $horario = (string) ($cfg['horario'] ?? '02:00:00');
        if ($horario === '') $horario = '02:00:00';
        if (strlen($horario) === 5) $horario .= ':00';
        [$hh, $mm, $ss] = array_pad(explode(':', $horario), 3, '00');

        $scheduledToday = (clone $now)->setTime((int) $hh, (int) $mm, (int) $ss);
        if ($now >= $scheduledToday) {
            if (!$lastRun) {
                $due = true;
            } else {
                $rec = (string) ($cfg['recorrencia'] ?? 'diaria');
                if ($rec === 'semanal') {
                    $diffDays = (int) $lastRun->diff($now)->days;
                    $due = ($diffDays >= 7);
                } elseif ($rec === 'mensal') {
                    $due = ($lastRun->format('Y-m') !== $now->format('Y-m'));
                } else {
                    $due = ($lastRun->format('Y-m-d') !== $now->format('Y-m-d'));
                }
            }
        }

        header('Content-Type: text/plain; charset=UTF-8');

        if (!$due) {
            echo "skip\n";
            exit;
        }

        try {
            $id = $service->createBackupNow('cron');
            echo 'ok #' . (int) $id . "\n";
            exit;
        } catch (\Throwable $e) {
            http_response_code(500);
            echo 'error: ' . $e->getMessage() . "\n";
            exit;
        }
    }
}
