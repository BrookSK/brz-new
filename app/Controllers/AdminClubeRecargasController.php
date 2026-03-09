<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;

class AdminClubeRecargasController extends Controller {
    public function index(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte', 'redirecionador']);

        $rows = [];
        $stats = [
            'total_registros' => 0,
            'total_usd' => 0.0,
            'total_brl' => 0.0,
            'total_pago_registros' => 0,
            'total_pago_usd' => 0.0,
            'total_pago_brl' => 0.0,
        ];

        try {
            $db = \Config\Database::getConnection();

            // Garantir colunas necessárias (compat com ambientes existentes)
            try {
                $cols = [];
                try {
                    $st = $db->query('DESCRIBE carteira_recargas');
                    $cols = $st ? ($st->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
                } catch (\Exception $e) {
                    $cols = [];
                }

                if (is_array($cols) && !empty($cols)) {
                    $toAdd = [
                        'origem' => "ALTER TABLE carteira_recargas ADD COLUMN origem varchar(40) DEFAULT NULL",
                        'public_token' => "ALTER TABLE carteira_recargas ADD COLUMN public_token varchar(64) DEFAULT NULL",
                        'pagador_nome' => "ALTER TABLE carteira_recargas ADD COLUMN pagador_nome varchar(191) DEFAULT NULL",
                        'pagador_email' => "ALTER TABLE carteira_recargas ADD COLUMN pagador_email varchar(191) DEFAULT NULL",
                        'pagador_documento' => "ALTER TABLE carteira_recargas ADD COLUMN pagador_documento varchar(30) DEFAULT NULL",
                        'metodo' => "ALTER TABLE carteira_recargas ADD COLUMN metodo varchar(20) DEFAULT NULL",
                        'usd_brl_rate' => "ALTER TABLE carteira_recargas ADD COLUMN usd_brl_rate decimal(10,6) DEFAULT NULL",
                        'valor_brl' => "ALTER TABLE carteira_recargas ADD COLUMN valor_brl decimal(10,2) DEFAULT NULL",
                    ];
                    foreach ($toAdd as $c => $sql) {
                        if (!in_array($c, $cols, true)) {
                            try { $db->exec($sql); } catch (\Exception $e) {}
                        }
                    }
                }
            } catch (\Exception $e) {
            }

            $limit = (int) ($request->getParam('limit') ?? 200);
            if ($limit <= 0) $limit = 200;
            if ($limit > 1000) $limit = 1000;

            $stmt = $db->prepare("SELECT id, usuario_id, pagador_nome, pagador_email, pagador_documento, metodo, moeda, valor, usd_brl_rate, valor_brl, gateway, payment_id, status, created_at, paid_at
                FROM carteira_recargas
                WHERE origem = 'clube_quick_checkout'
                ORDER BY created_at DESC, id DESC
                LIMIT {$limit}");
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            $stats['total_registros'] = count($rows);
            foreach ($rows as $r) {
                $stats['total_usd'] += (float) ($r['valor'] ?? 0);
                $stats['total_brl'] += (float) ($r['valor_brl'] ?? 0);

                $st = strtolower(trim((string) ($r['status'] ?? 'pending')));
                if (in_array($st, ['paid', 'approved', 'credited'], true)) {
                    $stats['total_pago_registros']++;
                    $stats['total_pago_usd'] += (float) ($r['valor'] ?? 0);
                    $stats['total_pago_brl'] += (float) ($r['valor_brl'] ?? 0);
                }
            }
        } catch (\Exception $e) {
            $rows = [];
        }

        $this->view('admin/clube-recargas', [
            'recargas' => $rows,
            'stats' => $stats,
        ]);
    }
}
