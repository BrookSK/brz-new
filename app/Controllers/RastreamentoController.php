<?php
namespace App\Controllers;

use App\Core\Request;
use App\Models\Pedido;
use App\Services\CorreiosTrackingService;
use Config\Database;

class RastreamentoController extends Controller {
    private $pedidoModel;
    private $trackingService;

    public function __construct() {
        $this->pedidoModel = new Pedido();
        $this->trackingService = new CorreiosTrackingService();
    }

    public function index(Request $request) {
        $codigo = trim((string) $request->getParam('codigo'));
        $pedidoId = $request->getParam('id');

        if ($codigo !== '') {
            // se vier número, permite cair no fluxo de pedido
            if (preg_match('/^\d+$/', $codigo)) {
                $pedidoId = $codigo;
            } else {
                $cfg = $this->loadCorreiosTrackingConfig();
                $resp = $this->trackingService->rastrear($codigo, $cfg);

                $rastreamento = [];
                if (!empty($resp['success'])) {
                    $rastreamento = $resp['eventos'] ?? [];
                }

                $this->view('rastreamento/index', [
                    'pedido' => null,
                    'rastreamento' => $rastreamento,
                    'codigo_rastreio' => strtoupper($codigo),
                    'tracking_error' => empty($resp['success']) ? ($resp['error'] ?? 'Erro ao rastrear') : null,
                    'tracking_raw' => $resp['raw'] ?? null,
                ]);
                return;
            }
        }

        if (!$pedidoId) {
            $this->view('rastreamento/search');
            return;
        }

        $pedido = $this->pedidoModel->getComItems($pedidoId);

        if (!$pedido) {
            $this->view('rastreamento/not-found');
            return;
        }

        $rastreamento = $this->pedidoModel->getRastreamento($pedidoId);

        $this->view('rastreamento/index', [
            'pedido' => $pedido,
            'rastreamento' => $rastreamento,
            'codigo_rastreio' => null,
            'tracking_error' => null,
            'tracking_raw' => null,
        ]);
    }

    private function loadCorreiosTrackingConfig(): array {
        $pdo = Database::getConnection();

        $tableInfo = $this->getConfigTableInfo($pdo);
        $table = $tableInfo['table'];

        $get = function(string $cat, string $key, string $default = '') use ($pdo, $tableInfo, $table): string {
            try {
                $mode = (string) ($tableInfo['mode'] ?? '');
                if ($mode === 'single_row') {
                    $cols = [];
                    try {
                        $st = $pdo->query('DESCRIBE ' . $table);
                        $cols = $st->fetchAll(\PDO::FETCH_COLUMN) ?: [];
                    } catch (\Exception $e) {
                        $cols = [];
                    }

                    $col = null;
                    $map = $tableInfo['columnMap'] ?? [];
                    if (isset($map[$cat]) && isset($map[$cat][$key])) {
                        $col = $map[$cat][$key];
                    } else {
                        if (in_array($key, $cols, true)) {
                            $col = $key;
                        }
                        // compat: migrations antigas que prefixaram entrega_
                        $alt = 'entrega_' . $key;
                        if (!$col && in_array($alt, $cols, true)) {
                            $col = $alt;
                        }
                    }

                    if (!$col || !preg_match('/^[a-zA-Z0-9_]+$/', (string) $col)) {
                        return $default;
                    }

                    $idCol = (string) ($tableInfo['idCol'] ?? 'id');
                    $stmt = $pdo->query('SELECT ' . $col . ' FROM ' . $table . ' ORDER BY ' . $idCol . ' ASC LIMIT 1');
                    $v = $stmt->fetchColumn();
                    return ($v === false || $v === null) ? $default : (string) $v;
                }

                if ($mode === 'categoria_chave') {
                    $catCol = $tableInfo['categoriaCol'];
                    $keyCol = $tableInfo['chaveCol'];
                    $valCol = $tableInfo['valueCol'];

                    $stmt = $pdo->prepare('SELECT ' . $valCol . ' FROM ' . $table . ' WHERE ' . $catCol . ' = ? AND ' . $keyCol . ' = ? LIMIT 1');
                    $stmt->execute([$cat, $key]);
                    $v = $stmt->fetchColumn();
                    if ($v !== false && $v !== null) return (string) $v;

                    // compat: chave antiga entrega_...
                    $stmt = $pdo->prepare('SELECT ' . $valCol . ' FROM ' . $table . ' WHERE ' . $catCol . ' = ? AND ' . $keyCol . ' = ? LIMIT 1');
                    $stmt->execute([$cat, 'entrega_' . $key]);
                    $v = $stmt->fetchColumn();
                    return ($v === false || $v === null) ? $default : (string) $v;
                }

                $keyCol = $tableInfo['keyCol'];
                $valCol = $tableInfo['valueCol'];

                $fullKey = $cat . '_' . $key;
                $stmt = $pdo->prepare('SELECT ' . $valCol . ' FROM ' . $table . ' WHERE ' . $keyCol . ' = ? LIMIT 1');
                $stmt->execute([$fullKey]);
                $v = $stmt->fetchColumn();
                if ($v !== false && $v !== null) return (string) $v;

                // compat: chave antiga entrega_...
                $fullKey2 = $cat . '_entrega_' . $key;
                $stmt = $pdo->prepare('SELECT ' . $valCol . ' FROM ' . $table . ' WHERE ' . $keyCol . ' = ? LIMIT 1');
                $stmt->execute([$fullKey2]);
                $v = $stmt->fetchColumn();
                return ($v === false || $v === null) ? $default : (string) $v;
            } catch (\Exception $e) {
                return $default;
            }
        };

        $enabled = $get('entrega', 'correios_tracking_enabled', '0');
        $token = $get('entrega', 'correios_tracking_token', '');

        $configuredBaseUrl = trim($get('entrega', 'correios_tracking_base_url', ''));

        // Automático: URL do SRO Rastro v3 baseado no ambiente (reaproveita sigep_ambiente)
        // Permite override por configuracao (entrega_correios_tracking_base_url)
        $amb = $get('entrega', 'sigep_ambiente', 'homologacao');
        $baseUrl = $configuredBaseUrl;
        if ($baseUrl === '') {
            $baseUrl = ($amb === 'producao')
                ? 'https://api.correios.com.br/srorastro/v1/objetos/{codigo}'
                : 'https://apihom.correios.com.br/srorastro/v1/objetos/{codigo}';
        }

        // Automático: header padrão
        $header = trim($get('entrega', 'correios_tracking_header', 'Authorization'));
        if ($header === '') {
            $header = 'Authorization';
        }

        return [
            'enabled' => $enabled,
            'base_url' => $baseUrl,
            'token' => $token,
            'header' => $header,
        ];
    }

    private function getConfigTableInfo(\PDO $pdo): array {
        $tableCandidates = ['configuracoes_sistema', 'configuracoes', 'settings', 'config'];
        $table = null;
        foreach ($tableCandidates as $t) {
            try {
                $st = $pdo->query("SHOW TABLES LIKE '" . $t . "'");
                if ($st && $st->fetchColumn()) {
                    $table = $t;
                    break;
                }
            } catch (\Exception $e) {
            }
        }

        if (!$table) {
            return ['table' => 'configuracoes_sistema', 'mode' => 'single_row', 'idCol' => 'id'];
        }

        // Detect single row schema
        $cols = [];
        try {
            $st = $pdo->query('DESCRIBE ' . $table);
            $cols = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            $cols = [];
        }

        $colNames = array_map(fn($c) => (string) ($c['Field'] ?? ''), $cols);
        $hasCategoria = in_array('categoria', $colNames, true);
        $hasChave = in_array('chave', $colNames, true);
        $hasValor = in_array('valor', $colNames, true);

        if ($hasCategoria && $hasChave && $hasValor) {
            return [
                'table' => $table,
                'mode' => 'categoria_chave',
                'categoriaCol' => 'categoria',
                'chaveCol' => 'chave',
                'valueCol' => 'valor',
                'updatedAtCol' => in_array('updated_at', $colNames, true) ? 'updated_at' : null,
            ];
        }

        if (in_array('chave', $colNames, true) && $hasValor) {
            return [
                'table' => $table,
                'mode' => 'chave_valor',
                'keyCol' => 'chave',
                'valueCol' => 'valor',
                'updatedAtCol' => in_array('updated_at', $colNames, true) ? 'updated_at' : null,
            ];
        }

        $idCol = in_array('id', $colNames, true) ? 'id' : ($colNames[0] ?? 'id');
        return [
            'table' => $table,
            'mode' => 'single_row',
            'idCol' => $idCol,
            'valueCol' => 'valor',
            'updatedAtCol' => in_array('updated_at', $colNames, true) ? 'updated_at' : null,
            'columnMap' => [],
        ];
    }
}
