<?php
namespace App\Controllers;

use Config\Database;
use App\Services\WExpressService;
use App\Models\PedidoEcommerce;
use App\Services\AuthService;

class AdminRemessaInternacionalController extends Controller {
    private $connection;

    public function __construct() {
        $this->connection = Database::getConnection();
    }

    private function tableExists(string $table): bool {
        try {
            $stmt = $this->connection->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
            $stmt->execute([$table]);
            return ((int) $stmt->fetchColumn()) > 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function requireAdmin(): void {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor']);
    }

    private function isPedidoCaixaFechada(array $pedido): bool {
        $status = strtolower(trim((string) ($pedido['status'] ?? '')));
        return in_array($status, ['produto_consolidado', 'consolidado'], true);
    }

    private function isValidCpf(string $cpfDigits): bool {
        $cpf = preg_replace('/\D+/', '', $cpfDigits);
        if ($cpf === null) $cpf = '';
        if (strlen($cpf) !== 11) return false;
        if (preg_match('/^(\d)\1{10}$/', $cpf)) return false;

        $sum = 0;
        for ($i = 0, $w = 10; $i < 9; $i++, $w--) {
            $sum += ((int) $cpf[$i]) * $w;
        }
        $d1 = 11 - ($sum % 11);
        if ($d1 >= 10) $d1 = 0;

        $sum = 0;
        for ($i = 0, $w = 11; $i < 10; $i++, $w--) {
            $sum += ((int) $cpf[$i]) * $w;
        }
        $d2 = 11 - ($sum % 11);
        if ($d2 >= 10) $d2 = 0;

        return ((int) $cpf[9] === $d1) && ((int) $cpf[10] === $d2);
    }

    private function isValidCnpj(string $cnpjDigits): bool {
        $cnpj = preg_replace('/\D+/', '', $cnpjDigits);
        if ($cnpj === null) $cnpj = '';
        if (strlen($cnpj) !== 14) return false;
        if (preg_match('/^(\d)\1{13}$/', $cnpj)) return false;

        $calc = function (string $base, array $weights): int {
            $sum = 0;
            foreach ($weights as $i => $w) {
                $sum += ((int) $base[$i]) * $w;
            }
            $r = $sum % 11;
            return ($r < 2) ? 0 : (11 - $r);
        };

        $d1 = $calc(substr($cnpj, 0, 12), [5,4,3,2,9,8,7,6,5,4,3,2]);
        $d2 = $calc(substr($cnpj, 0, 13), [6,5,4,3,2,9,8,7,6,5,4,3,2]);
        return ((int) $cnpj[12] === $d1) && ((int) $cnpj[13] === $d2);
    }

    private function ensureTables(): void {
        // Migrar coluna status de ENUM para VARCHAR nas tabelas de remessa
        try {
            foreach (['remessa_janelas', 'pedidos'] as $tbl) {
                try {
                    $stDesc = $this->connection->query("DESCRIBE {$tbl}");
                    $rows = $stDesc ? $stDesc->fetchAll(\PDO::FETCH_ASSOC) : [];
                    foreach ($rows as $row) {
                        if (strtolower((string)($row['Field'] ?? '')) === 'status'
                            && strpos(strtolower((string)($row['Type'] ?? '')), 'enum') !== false) {
                            $default = ($tbl === 'pedidos') ? 'pendente' : 'aberta';
                            $this->connection->exec("ALTER TABLE {$tbl} MODIFY COLUMN status VARCHAR(60) NOT NULL DEFAULT '{$default}'");
                            break;
                        }
                    }
                } catch (\Exception $e) {}
            }
        } catch (\Exception $e) {}
    }

    private function now(): \DateTime {
        return new \DateTime('now');
    }

    private function ensureJanelaAtual(): array {
        $now = $this->now();

        // Atualizar janelas antigas (aberta -> finalizada) e marcar atraso quando aplicável
        $stAll = $this->connection->query('SELECT id, data_fim, status FROM remessa_janelas ORDER BY data_inicio ASC');
        $all = $stAll->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        foreach ($all as $j) {
            $dataFim = new \DateTime((string) $j['data_fim']);
            $status = (string) ($j['status'] ?? 'aberta');
            if ($status === 'aberta' && $dataFim < $now) {
                $stUp = $this->connection->prepare("UPDATE remessa_janelas SET status = 'finalizada', updated_at = NOW() WHERE id = ?");
                $stUp->execute([(int) $j['id']]);
            }
        }

        // Garantir que existe uma janela que contém o dia atual
        $st = $this->connection->prepare('SELECT * FROM remessa_janelas WHERE data_inicio <= ? AND data_fim >= ? ORDER BY id ASC');
        $nowStr = $now->format('Y-m-d H:i:s');
        $st->execute([$nowStr, $nowStr]);
        $currents = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        $current = $currents[0] ?? null;

        // Se existirem janelas duplicadas cobrindo o mesmo período, consolidar automaticamente
        if ($current && count($currents) > 1) {
            $primaryId = (int) ($current['id'] ?? 0);
            foreach ($currents as $idx => $dup) {
                if ($idx === 0) continue;
                $dupId = (int) ($dup['id'] ?? 0);
                if ($dupId <= 0 || $primaryId <= 0) continue;

                // mover pedidos para a janela principal
                $stMove = $this->connection->prepare('INSERT IGNORE INTO remessa_janela_pedidos (janela_id, pedido_id, etiqueta_gerada, etiqueta_gerada_em, created_at) SELECT ?, pedido_id, etiqueta_gerada, etiqueta_gerada_em, created_at FROM remessa_janela_pedidos WHERE janela_id = ?');
                $stMove->execute([$primaryId, $dupId]);

                $stDelLinks = $this->connection->prepare('DELETE FROM remessa_janela_pedidos WHERE janela_id = ?');
                $stDelLinks->execute([$dupId]);

                $stDelJanela = $this->connection->prepare('DELETE FROM remessa_janelas WHERE id = ?');
                $stDelJanela->execute([$dupId]);
            }
        }

        if (!$current) {
            // Se não existir janela cobrindo hoje, criar sequencial a partir da última janela
            $stLast = $this->connection->query('SELECT * FROM remessa_janelas ORDER BY data_inicio DESC LIMIT 1');
            $last = $stLast->fetch(\PDO::FETCH_ASSOC);

            if ($last) {
                $start = new \DateTime((string) $last['data_fim']);
                $start->modify('+1 second');
            } else {
                $start = new \DateTime($now->format('Y-m-d 00:00:00'));
            }

            while (true) {
                $end = (clone $start);
                $end->modify('+12 days');
                $end->setTime(23, 59, 59);

                $status = ($end < $now) ? 'finalizada' : 'aberta';

                // Evitar criar janelas duplicadas para o mesmo período
                $stExists = $this->connection->prepare('SELECT * FROM remessa_janelas WHERE data_inicio = ? AND data_fim = ? ORDER BY id ASC LIMIT 1');
                $stExists->execute([
                    $start->format('Y-m-d H:i:s'),
                    $end->format('Y-m-d H:i:s'),
                ]);
                $existing = $stExists->fetch(\PDO::FETCH_ASSOC);

                if (!$existing) {
                    $stIns = $this->connection->prepare('INSERT INTO remessa_janelas (data_inicio, data_fim, status, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())');
                    $stIns->execute([
                        $start->format('Y-m-d H:i:s'),
                        $end->format('Y-m-d H:i:s'),
                        $status,
                    ]);
                }

                if ($start <= $now && $end >= $now) {
                    break;
                }

                $start = (clone $end);
                $start->modify('+1 second');
            }

            $st->execute([$nowStr, $nowStr]);
            $current = $st->fetch(\PDO::FETCH_ASSOC);
        }

        // Sincronizar pedidos dentro da janela atual
        if ($current && !empty($current['id'])) {
            $this->syncPedidosParaJanela((int) $current['id']);
        }

        // Marcar janelas em atraso (finalizadas há mais de 15 dias e ainda não remessa_gerada)
        $stAtraso = $this->connection->query("SELECT id, data_fim, status FROM remessa_janelas WHERE status IN ('aberta','finalizada')");
        $cands = $stAtraso->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        foreach ($cands as $j) {
            $dataFim = new \DateTime((string) $j['data_fim']);
            $limite = (clone $dataFim);
            $limite->modify('+15 days');
            if ($limite < $now) {
                // Só marca atraso se ainda houver pedidos sem etiqueta
                $janelaId = (int) $j['id'];
                $this->syncPedidosParaJanela($janelaId);
                $stCount = $this->connection->prepare('SELECT COUNT(*) FROM remessa_janela_pedidos WHERE janela_id = ? AND etiqueta_gerada = 0');
                $stCount->execute([$janelaId]);
                $pend = (int) $stCount->fetchColumn();
                if ($pend > 0) {
                    $stUp = $this->connection->prepare("UPDATE remessa_janelas SET status = 'atraso', updated_at = NOW() WHERE id = ? AND status <> 'remessa_gerada'");
                    $stUp->execute([$janelaId]);
                }
            }
        }

        // Fechar janelas que já têm todas as etiquetas
        $stClose = $this->connection->query("SELECT id FROM remessa_janelas WHERE status IN ('aberta','finalizada','atraso')");
        $ids = $stClose->fetchAll(\PDO::FETCH_COLUMN) ?: [];
        foreach ($ids as $janelaId) {
            $this->tryAutoCloseJanela((int) $janelaId);
        }

        return $current ?: [];
    }

    private function syncPedidosParaJanela(int $janelaId): void {
        $stJ = $this->connection->prepare('SELECT id, data_inicio, data_fim FROM remessa_janelas WHERE id = ? LIMIT 1');
        $stJ->execute([$janelaId]);
        $j = $stJ->fetch(\PDO::FETCH_ASSOC);
        if (!$j) {
            return;
        }

        $inicio = (string) $j['data_inicio'];
        $fim    = (string) $j['data_fim'];

        $cols = [];
        try {
            $stCols = $this->connection->query('DESCRIBE pedidos');
            $cols = $stCols ? ($stCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
        } catch (\Exception $e) {
            $cols = [];
        }

        // Critério de status: qualquer pedido pago (não cancelado, não deletado)
        // A janela é determinada pela data de CRIAÇÃO do pedido (created_at)
        $paidStatusWhere = "(LOWER(COALESCE(p.status,'')) IN ("
            . "'pago','paid','approved','aprovado',"
            . "'produto_consolidado','consolidado',"
            . "'processando','processing',"
            . "'em_transporte','enviado','entregue'"
            . "))";

        $canceladoWhere = " AND LOWER(COALESCE(p.status,'')) NOT IN ('cancelado','cancelled','lixeira','deleted')";
        $hasDeletedAt = is_array($cols) && in_array('deleted_at', $cols, true);
        if ($hasDeletedAt) {
            $canceladoWhere .= ' AND p.deleted_at IS NULL';
        }

        // Sempre usar created_at para determinar a janela
        $dateCol = 'id'; // fallback sem filtro
        if (is_array($cols) && in_array('created_at', $cols, true)) {
            $dateCol = 'p.created_at';
        }
        $dateWhere = ($dateCol === 'id') ? '1=1' : ("{$dateCol} >= ? AND {$dateCol} <= ?");

        $joinEndereco = '';
        $wherePais = '1=1';
        if (is_array($cols) && in_array('endereco_entrega_id', $cols, true) && $this->tableExists('enderecos')) {
            try {
                $colsEnd = [];
                try {
                    $stColsE = $this->connection->query('DESCRIBE enderecos');
                    $colsEnd = $stColsE ? ($stColsE->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
                } catch (\Exception $e) {
                    $colsEnd = [];
                }
                if (is_array($colsEnd) && in_array('pais', $colsEnd, true)) {
                    $joinEndereco = ' LEFT JOIN enderecos e ON e.id = p.endereco_entrega_id ';
                    $wherePais = "UPPER(COALESCE(e.pais,'BR')) IN ('BR','BRASIL','BRAZIL')";
                }
            } catch (\Exception $e) {
            }
        }

        // Excluir pedidos já em outra janela e pedidos cancelados/deletados
        $sqlBase = "SELECT p.id FROM pedidos p {$joinEndereco}
                WHERE {$paidStatusWhere}
                  AND {$dateWhere}
                  AND {$wherePais}
                  {$canceladoWhere}
                  AND p.id NOT IN (
                      SELECT pedido_id FROM remessa_janela_pedidos
                      WHERE janela_id != ?
                  )";

        $execParams = ($dateCol === 'id') ? [$janelaId] : [$inicio, $fim, $janelaId];

        // Tentar com deleted_at; fallback sem ele se coluna não existir
        $sqlWithDel = $sqlBase . ($hasDeletedAt ? '' : ' AND p.deleted_at IS NULL');
        try {
            $stP = $this->connection->prepare($sqlWithDel);
            $stP->execute($execParams);
        } catch (\Exception $e) {
            $stP = $this->connection->prepare($sqlBase);
            $stP->execute($execParams);
        }
        $pedidoIds = $stP->fetchAll(\PDO::FETCH_COLUMN) ?: [];

        if (!$pedidoIds) {
            return;
        }

        $stIns = $this->connection->prepare('INSERT IGNORE INTO remessa_janela_pedidos (janela_id, pedido_id, created_at) VALUES (?, ?, NOW())');
        foreach ($pedidoIds as $pid) {
            $stIns->execute([$janelaId, (int) $pid]);
        }

        // Remover da janela pedidos que foram cancelados/deletados depois de entrar
        $this->removeInvalidPedidosFromJanela($janelaId, $cols);
    }

    private function removeInvalidPedidosFromJanela(int $janelaId, array $cols): void {
        // Buscar todos os pedido_ids desta janela sem etiqueta gerada
        $stIds = $this->connection->prepare('SELECT pedido_id FROM remessa_janela_pedidos WHERE janela_id = ? AND etiqueta_gerada = 0');
        $stIds->execute([$janelaId]);
        $ids = $stIds->fetchAll(\PDO::FETCH_COLUMN) ?: [];
        if (!$ids) return;

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $toRemove = [];

        // 1. Pedidos hard-deleted (não existem mais na tabela pedidos)
        $sqlMissing = "SELECT rjp.pedido_id FROM remessa_janela_pedidos rjp
                       LEFT JOIN pedidos p ON p.id = rjp.pedido_id
                       WHERE rjp.janela_id = ? AND rjp.etiqueta_gerada = 0 AND p.id IS NULL";
        $stMissing = $this->connection->prepare($sqlMissing);
        $stMissing->execute([$janelaId]);
        $toRemove = array_merge($toRemove, array_map('intval', $stMissing->fetchAll(\PDO::FETCH_COLUMN) ?: []));

        // 2. Pedidos soft-deleted — tenta com deleted_at, ignora erro se coluna não existir
        try {
            $sqlDel = "SELECT p.id FROM pedidos p WHERE p.id IN ({$placeholders}) AND p.deleted_at IS NOT NULL";
            $stDel = $this->connection->prepare($sqlDel);
            $stDel->execute($ids);
            $toRemove = array_merge($toRemove, array_map('intval', $stDel->fetchAll(\PDO::FETCH_COLUMN) ?: []));
        } catch (\Exception $e) {
            // coluna deleted_at não existe — ignorar
        }

        // 3. Pedidos cancelados por status
        try {
            $sqlCan = "SELECT p.id FROM pedidos p WHERE p.id IN ({$placeholders})
                       AND LOWER(COALESCE(p.status,'')) IN ('cancelado','cancelled','lixeira','deleted')";
            $stCan = $this->connection->prepare($sqlCan);
            $stCan->execute($ids);
            $toRemove = array_merge($toRemove, array_map('intval', $stCan->fetchAll(\PDO::FETCH_COLUMN) ?: []));
        } catch (\Exception $e) {}

        $toRemove = array_unique($toRemove);
        if (!$toRemove) return;

        $phRem = implode(',', array_fill(0, count($toRemove), '?'));
        $stDel = $this->connection->prepare("DELETE FROM remessa_janela_pedidos WHERE janela_id = ? AND pedido_id IN ({$phRem}) AND etiqueta_gerada = 0");
        $stDel->execute(array_merge([$janelaId], $toRemove));
    }

    private function tryAutoCloseJanela(int $janelaId): void {
        $this->syncPedidosParaJanela($janelaId);

        $stTotal = $this->connection->prepare('SELECT COUNT(*) FROM remessa_janela_pedidos WHERE janela_id = ?');
        $stTotal->execute([$janelaId]);
        $total = (int) $stTotal->fetchColumn();
        if ($total <= 0) {
            return;
        }

        $stPend = $this->connection->prepare('SELECT COUNT(*) FROM remessa_janela_pedidos WHERE janela_id = ? AND etiqueta_gerada = 0');
        $stPend->execute([$janelaId]);
        $pend = (int) $stPend->fetchColumn();

        // Só fecha automaticamente se TODOS os pedidos têm etiqueta gerada
        // e a janela já passou do período (não fechar janelas ainda ativas)
        if ($pend === 0) {
            $stJ = $this->connection->prepare('SELECT data_fim, status FROM remessa_janelas WHERE id = ? LIMIT 1');
            $stJ->execute([$janelaId]);
            $jRow = $stJ->fetch(\PDO::FETCH_ASSOC);
            if (!$jRow) return;

            $dataFim = new \DateTime((string) $jRow['data_fim']);
            $now = $this->now();

            // Só auto-fecha se a janela já expirou (data_fim < agora)
            if ($dataFim < $now) {
                $stUp = $this->connection->prepare("UPDATE remessa_janelas SET status = 'remessa_gerada', closed_at = COALESCE(closed_at, NOW()), updated_at = NOW() WHERE id = ?");
                $stUp->execute([$janelaId]);
            }
        }
        // Se ainda tem etiquetas pendentes, NÃO fecha — mantém o status atual
        // O dashboard mostrará o aviso de etiquetas pendentes
    }

    public function index($request) {
        $this->requireAdmin();
        $errorMsg = null;
        try {
            $this->ensureTables();
            $janelaAtual = $this->ensureJanelaAtual();

            $janelasAbertas = $this->getJanelasByStatus(['aberta']);
            $janelasFinalizadas = $this->getJanelasByStatus(['finalizada']);
            $janelasAtraso = $this->getJanelasByStatus(['atraso']);
            $janelasGeradas = $this->getJanelasByStatus(['remessa_gerada']);

            // Mantido por compatibilidade com blocos antigos no HTML (até remover/refatorar)
            $pedidosPendentes = [];
            $pedidosAtraso = [];
            $remessasGeradas = $this->getRemessasGeradas();

            $stats = [
                'abertas' => count($janelasAbertas),
                'finalizadas' => count($janelasFinalizadas),
                'atraso' => count($janelasAtraso),
                'geradas' => count($janelasGeradas),
            ];

        } catch (\Exception $e) {
            $errorMsg = $e->getMessage();
            $janelaAtual = [];
            $janelasAbertas = [];
            $janelasFinalizadas = [];
            $janelasAtraso = [];
            $janelasGeradas = [];
            $pedidosPendentes = [];
            $pedidosAtraso = [];
            $remessasGeradas = [];
            $stats = ['abertas' => 0, 'finalizadas' => 0, 'atraso' => 0, 'geradas' => 0];
        }

        // Incluir o partial do menu lateral
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Remessa Internacional - Braziliana Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';
        
        // Renderizar estilos do menu
        renderAdminSidebarStyles();
        
        echo '<style>
        .status-pendente { background-color: #ffc107; }
        .status-atraso { background-color: #dc3545; }
        .status-gerada { background-color: #17a2b8; }
        .status-enviado { background-color: #28a745; }
        .janela-card { 
            transition: transform 0.2s; 
            border-left: 4px solid #007bff;
        }
        .janela-card:hover { 
            transform: translateY(-3px); 
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
        .pedido-card { 
            transition: all 0.3s; 
            border-left: 4px solid #dee2e6;
        }
        .pedido-card:hover { 
            transform: translateX(5px); 
            box-shadow: 0 4px 12px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">';
        
        // Renderizar menu lateral usando o partial
        renderAdminSidebar('remessa-internacional');
        
        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="page-title">Remessa Internacional</h1>
                    <div class="d-flex gap-2 align-items-center">
                        <div class="input-group input-group-sm" style="width:220px">
                            <input type="text" class="form-control" id="buscarPedidoNum" placeholder="Nº pedido..." onkeydown="if(event.key===\'Enter\'){irParaPedido();event.preventDefault();}">
                            <button class="btn btn-outline-primary" type="button" onclick="irParaPedido()"><i class="fas fa-search"></i></button>
                        </div>
                        <button type="button" class="btn btn-info btn-sm" onclick="location.reload()">
                            <i class="fas fa-sync me-1"></i>Atualizar
                        </button>
                    </div>
                </div>
                <script>
                function irParaPedido(){
                    var v = document.getElementById("buscarPedidoNum").value.replace(/\D/g,"");
                    if(v===""){alert("Digite o número do pedido");return;}
                    fetch("/admin/remessa-internacional/buscar-pedido/"+parseInt(v,10))
                        .then(r=>r.json())
                        .then(data=>{
                            if(data.success && data.url){
                                window.location.href=data.url;
                            } else {
                                alert(data.error || "Pedido não encontrado em nenhuma janela");
                            }
                        })
                        .catch(()=>alert("Erro ao buscar pedido"));
                }
                </script>';

        if (!empty($errorMsg)) {
            echo '<div class="alert alert-danger">'
                . '<strong>Erro ao carregar Remessa Internacional:</strong> '
                . htmlspecialchars((string) $errorMsg)
                . '<br><small class="text-muted">Verifique se você rodou as migrations de remessa (ex: database/migrations/016_create_remessa_janelas.sql) e se a tabela/colunas existem.</small>'
                . '</div>';
        }

        echo '
                <!-- Estatísticas -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card card-stats bg-primary text-white">
                            <div class="card-body">
                                <h5 class="card-title">Abertas</h5>
                                <h3>' . (int) ($stats['abertas'] ?? 0) . '</h3>
                                <small>Janelas ativas</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-stats bg-danger text-white">
                            <div class="card-body">
                                <h5 class="card-title">Atraso</h5>
                                <h3>' . (int) ($stats['atraso'] ?? 0) . '</h3>
                                <small>Janelas em atraso</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-stats bg-info text-white">
                            <div class="card-body">
                                <h5 class="card-title">Remessa Gerada</h5>
                                <h3>' . (int) ($stats['geradas'] ?? 0) . '</h3>
                                <small>Janelas fechadas</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-stats bg-secondary text-white">
                            <div class="card-body">
                                <h5 class="card-title">Finalizadas</h5>
                                <h3>' . (int) ($stats['finalizadas'] ?? 0) . '</h3>
                                <small>Esperando etiquetas</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Janelas Abertas -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0"><i class="fas fa-calendar-alt me-2"></i>Janelas Abertas (13 dias)</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">';
                                
                                foreach ($janelasAbertas as $janela) {
                                    $statusClass = $janela['status'] == 'aberta' ? 'success' : 'secondary';
                                    $pendentes = (int) ($janela['etiquetas_pendentes'] ?? 0);
                                    $totalPed  = (int) ($janela['total_pedidos'] ?? 0);
                                    echo '<div class="col-md-4 mb-3">
                                        <div class="card janela-card">
                                            <div class="card-body">
                                                <h6 class="card-title">Janela #' . $janela['id'] . '</h6>
                                                <p class="card-text">
                                                    <small class="text-muted">
                                                        <i class="fas fa-calendar"></i> ' . date('d/m/Y', strtotime($janela['data_inicio'])) . ' a ' . date('d/m/Y', strtotime($janela['data_fim'])) . '
                                                    </small><br>
                                                    <span class="badge bg-' . $statusClass . '">' . ucfirst($janela['status']) . '</span>
                                                    <span class="badge bg-light text-dark ms-1">' . $totalPed . ' pedido(s)</span>
                                                </p>'
                                                . ($pendentes > 0 ? '<div class="alert alert-warning py-1 px-2 mb-2 small"><i class="fas fa-tag me-1"></i><strong>' . $pendentes . ' etiqueta(s) pendente(s)</strong></div>' : '')
                                                . '<button class="btn btn-sm btn-outline-primary" onclick="verJanela(' . $janela['id'] . ')">
                                                    <i class="fas fa-eye"></i> Ver Pedidos
                                                </button>
                                            </div>
                                        </div>
                                    </div>';
                                }
                                
                                if (empty($janelasAbertas)) {
                                    echo '<div class="col-12 text-center py-4">
                                        <i class="fas fa-calendar-times fa-3x text-muted mb-3"></i>
                                        <p class="text-muted">Nenhuma janela de remessa encontrada</p>
                                    </div>';
                                }
                                
                                echo '</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Janelas Finalizadas -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-secondary text-white">
                                <h5 class="mb-0"><i class="fas fa-check-circle me-2"></i>Janelas Finalizadas</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">';

                                foreach ($janelasFinalizadas as $janela) {
                                    $pendentes = (int) ($janela['etiquetas_pendentes'] ?? 0);
                                    $totalPed  = (int) ($janela['total_pedidos'] ?? 0);
                                    echo '<div class="col-md-4 mb-3">
                                        <div class="card janela-card">
                                            <div class="card-body">
                                                <h6 class="card-title">Janela #' . $janela['id'] . '</h6>
                                                <p class="card-text">
                                                    <small class="text-muted">
                                                        <i class="fas fa-calendar"></i> ' . date('d/m/Y', strtotime($janela['data_inicio'])) . ' a ' . date('d/m/Y', strtotime($janela['data_fim'])) . '
                                                    </small><br>
                                                    <span class="badge bg-secondary">Finalizada</span>
                                                    <span class="badge bg-light text-dark ms-1">' . $totalPed . ' pedido(s)</span>
                                                </p>'
                                                . ($pendentes > 0 ? '<div class="alert alert-warning py-1 px-2 mb-2 small"><i class="fas fa-tag me-1"></i><strong>' . $pendentes . ' etiqueta(s) pendente(s)</strong> — não pode ser fechada</div>' : '')
                                                . '<button class="btn btn-sm btn-outline-primary" onclick="verJanela(' . $janela['id'] . ')">
                                                    <i class="fas fa-eye"></i> Ver Pedidos
                                                </button>
                                            </div>
                                        </div>
                                    </div>';
                                }

                                if (empty($janelasFinalizadas)) {
                                    echo '<div class="col-12 text-center py-2">
                                        <p class="text-muted mb-0">Nenhuma janela finalizada</p>
                                    </div>';
                                }

                                echo '</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Janelas em Atraso -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-danger text-white">
                                <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Janelas em Atraso (15+ dias)</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">';

                                foreach ($janelasAtraso as $janela) {
                                    $pendentes = (int) ($janela['etiquetas_pendentes'] ?? 0);
                                    $totalPed  = (int) ($janela['total_pedidos'] ?? 0);
                                    echo '<div class="col-md-4 mb-3">
                                        <div class="card janela-card">
                                            <div class="card-body">
                                                <h6 class="card-title">Janela #' . $janela['id'] . '</h6>
                                                <p class="card-text">
                                                    <small class="text-muted">
                                                        <i class="fas fa-calendar"></i> ' . date('d/m/Y', strtotime($janela['data_inicio'])) . ' a ' . date('d/m/Y', strtotime($janela['data_fim'])) . '
                                                    </small><br>
                                                    <span class="badge bg-danger">Atraso</span>
                                                    <span class="badge bg-light text-dark ms-1">' . $totalPed . ' pedido(s)</span>
                                                </p>'
                                                . ($pendentes > 0 ? '<div class="alert alert-danger py-1 px-2 mb-2 small"><i class="fas fa-exclamation-triangle me-1"></i><strong>' . $pendentes . ' etiqueta(s) pendente(s)</strong> — gere as etiquetas para fechar</div>' : '')
                                                . '<button class="btn btn-sm btn-outline-primary" onclick="verJanela(' . $janela['id'] . ')">
                                                    <i class="fas fa-eye"></i> Ver Pedidos
                                                </button>
                                            </div>
                                        </div>
                                    </div>';
                                }

                                if (empty($janelasAtraso)) {
                                    echo '<div class="col-12 text-center py-2">
                                        <p class="text-muted mb-0">Nenhuma janela em atraso</p>
                                    </div>';
                                }

                                echo '</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Remessas Geradas (Janelas Fechadas) -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0"><i class="fas fa-paper-plane me-2"></i>Remessas Geradas</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">';

                                foreach ($janelasGeradas as $janela) {
                                    echo '<div class="col-md-4 mb-3">
                                        <div class="card janela-card">
                                            <div class="card-body">
                                                <h6 class="card-title">Janela #' . $janela['id'] . '</h6>
                                                <p class="card-text">
                                                    <small class="text-muted">
                                                        <i class="fas fa-calendar"></i> ' . date('d/m/Y', strtotime($janela['data_inicio'])) . ' a ' . date('d/m/Y', strtotime($janela['data_fim'])) . '
                                                    </small><br>
                                                    <span class="badge bg-info">Remessa Gerada</span>
                                                </p>
                                                <button class="btn btn-sm btn-outline-primary" onclick="verJanela(' . $janela['id'] . ')">
                                                    <i class="fas fa-eye"></i> Ver Pedidos
                                                </button>
                                            </div>
                                        </div>
                                    </div>';
                                }

                                if (empty($janelasGeradas)) {
                                    echo '<div class="col-12 text-center py-2">
                                        <p class="text-muted mb-0">Nenhuma remessa gerada</p>
                                    </div>';
                                }

                                echo '</div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pedidos Pendentes -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-warning text-dark">
                                <h5 class="mb-0"><i class="fas fa-clock me-2"></i>Pedidos Pendentes de Envio</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Pedido</th>
                                                <th>Cliente</th>
                                                <th>Data</th>
                                                <th>Dias</th>
                                                <th>Total</th>
                                                <th>Status</th>
                                                <th>Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>';
                                        
                                        foreach ($pedidosPendentes as $pedido) {
                                            $dias = $this->calcularDias($pedido['created_at']);
                                            $statusBadge = $dias >= 15 ? 'atraso' : 'pendente';
                                            echo '<tr>
                                                <td><strong>#' . str_pad($pedido['id'], 6, '0', STR_PAD_LEFT) . '</strong></td>
                                                <td>' . htmlspecialchars($pedido['cliente_nome'] ?? 'N/A') . '</td>
                                                <td>' . date('d/m/Y', strtotime($pedido['created_at'])) . '</td>
                                                <td><span class="badge bg-' . ($dias >= 15 ? 'danger' : 'warning') . '">' . $dias . ' dias</span></td>
                                                <td>R$ ' . number_format($pedido['total'], 2, ',', '.') . '</td>
                                                <td><span class="badge bg-' . $statusBadge . '">' . ucfirst($statusBadge) . '</span></td>
                                                <td>
                                                    <button class="btn btn-sm btn-success" onclick="gerarRemessa(' . $pedido['id'] . ')">
                                                        <i class="fas fa-globe"></i> Gerar Remessa
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-primary" onclick="verDetalhes(' . $pedido['id'] . ')">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </td>
                                            </tr>';
                                        }
                                        
                                        if (empty($pedidosPendentes)) {
                                            echo '<tr><td colspan="7" class="text-center text-muted">Nenhum pedido pendente encontrado</td></tr>';
                                        }
                                        
                                        echo '</tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Pedidos em Atraso -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-danger text-white">
                                <h5 class="mb-0"><i class="fas fa-exclamation-triangle me-2"></i>Pedidos em Atraso (15+ dias)</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Pedido</th>
                                                <th>Cliente</th>
                                                <th>Data</th>
                                                <th>Dias</th>
                                                <th>Total</th>
                                                <th>Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>';
                                        
                                        foreach ($pedidosAtraso as $pedido) {
                                            $dias = $this->calcularDias($pedido['created_at']);
                                            echo '<tr class="table-danger">
                                                <td><strong>#' . str_pad($pedido['id'], 6, '0', STR_PAD_LEFT) . '</strong></td>
                                                <td>' . htmlspecialchars($pedido['cliente_nome'] ?? 'N/A') . '</td>
                                                <td>' . date('d/m/Y', strtotime($pedido['created_at'])) . '</td>
                                                <td><span class="badge bg-danger">' . $dias . ' dias</span></td>
                                                <td>R$ ' . number_format($pedido['total'], 2, ',', '.') . '</td>
                                                <td>
                                                    <button class="btn btn-sm btn-danger" onclick="gerarRemessaUrgente(' . $pedido['id'] . ')">
                                                        <i class="fas fa-exclamation"></i> Gerar Urgente
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-primary" onclick="verDetalhes(' . $pedido['id'] . ')">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </td>
                                            </tr>';
                                        }
                                        
                                        if (empty($pedidosAtraso)) {
                                            echo '<tr><td colspan="6" class="text-center text-muted">Nenhum pedido em atraso encontrado</td></tr>';
                                        }
                                        
                                        echo '</tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Remessas Geradas -->
                <div class="row">
                    <div class="col-12">
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0"><i class="fas fa-paper-plane me-2"></i>Remessas Geradas</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Remessa</th>
                                                <th>Pedido</th>
                                                <th>Cliente</th>
                                                <th>Data Geração</th>
                                                <th>Status</th>
                                                <th>Webhook</th>
                                                <th>Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>';
                                        
                                        foreach ($remessasGeradas as $remessa) {
                                            $webhookStatus = $remessa['webhook_enviado'] ? 'success' : 'warning';
                                            $trk = '';
                                            if (!empty($remessa['courier_tracking_number'])) {
                                                $trk = (string) $remessa['courier_tracking_number'];
                                            } elseif (!empty($remessa['wexpress_tracking_number'])) {
                                                $trk = (string) $remessa['wexpress_tracking_number'];
                                            }
                                            $wxStatus = (string) ($remessa['wexpress_status'] ?? '');
                                            echo '<tr>
                                                <td><strong>#' . str_pad($remessa['id'], 6, '0', STR_PAD_LEFT) . '</strong></td>
                                                <td>#' . str_pad($remessa['pedido_id'], 6, '0', STR_PAD_LEFT) . '</td>
                                                <td>' . htmlspecialchars($remessa['cliente_nome'] ?? 'N/A') . '</td>
                                                <td>' . date('d/m/Y H:i', strtotime($remessa['created_at'])) . '</td>
                                                <td><span class="badge bg-info">Remessa Gerada</span>'
                                                    . ($wxStatus !== '' ? ('<div class="small text-muted">W-Express: ' . htmlspecialchars($wxStatus) . '</div>') : '')
                                                    . ($trk !== '' ? ('<div class="small text-muted">Tracking: ' . htmlspecialchars($trk) . '</div>') : '')
                                                . '</td>
                                                <td><span class="badge bg-' . $webhookStatus . '">' . ($remessa['webhook_enviado'] ? 'Enviado' : 'Pendente') . '</span></td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-secondary" onclick="reenviarWebhook(' . $remessa['id'] . ')">
                                                        <i class="fas fa-redo"></i> Reenviar
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-primary" onclick="verDetalhesRemessa(' . $remessa['id'] . ')">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </td>
                                            </tr>';
                                        }
                                        
                                        if (empty($remessasGeradas)) {
                                            echo '<tr><td colspan="7" class="text-center text-muted">Nenhuma remessa gerada encontrada</td></tr>';
                                        }
                                        
                                        echo '</tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>';

    // Renderizar scripts
    renderAdminScripts();
    
    echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function gerarRemessa(pedidoId) {
            if (confirm("Deseja gerar a remessa internacional para este pedido?")) {
                fetch("/admin/remessa-internacional/gerar/" + pedidoId, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert("Remessa gerada com sucesso!");
                        location.reload();
                    } else {
                        alert("Erro ao gerar remessa: " + data.message);
                    }
                })
                .catch(error => {
                    console.error("Error:", error);
                    alert("Erro ao gerar remessa");
                });
            }
        }

        function gerarRemessaUrgente(pedidoId) {
            if (confirm("ESTE PEDIDO ESTÁ EM ATRASO! Deseja gerar a remessa internacional urgentemente?")) {
                gerarRemessa(pedidoId);
            }
        }

        function verDetalhes(pedidoId) {
            window.open("/admin/pedidos/detalhes/" + pedidoId, "_blank");
        }

        function reenviarWebhook(remessaId) {
            if (confirm("Deseja reenviar o webhook para esta remessa?")) {
                fetch("/admin/remessa-internacional/reenviar-webhook/" + remessaId, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert("Webhook reenviado com sucesso!");
                        location.reload();
                    } else {
                        alert("Erro ao reenviar webhook: " + data.message);
                    }
                })
                .catch(error => {
                    console.error("Error:", error);
                    alert("Erro ao reenviar webhook");
                });
            }
        }

        function verJanela(janelaId) {
            window.location.href = "/admin/remessa-internacional/janela/" + janelaId;
        }

        function verDetalhesRemessa(remessaId) {
            alert("Funcionalidade em desenvolvimento");
        }
    </script>
</body>
</html>';
        exit;
    }

    public function baixarEtiquetaWexpress($request, $janelaId, $pedidoId) {
        $this->requireAdmin();

        $jid = (int) $janelaId;
        $pid = (int) $pedidoId;
        if ($jid <= 0 || $pid <= 0) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'Parâmetros inválidos']);
            exit;
        }

        $st = $this->connection->prepare('SELECT wexpress_shipping_id, wexpress_last_response_json FROM remessa_janela_pedidos WHERE janela_id = ? AND pedido_id = ? LIMIT 1');
        $st->execute([$jid, $pid]);
        $row = $st->fetch(\PDO::FETCH_ASSOC) ?: [];
        $shipId = trim((string) ($row['wexpress_shipping_id'] ?? ''));
        if ($shipId === '') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'error' => 'Shipping ID não encontrado para este pedido']);
            exit;
        }

        $svc = new \App\Services\WExpressService();
        $data = null;
        try {
            $data = $svc->getShipping($shipId);
        } catch (\Exception $e) {
            // fallback para a última resposta salva
            $raw = (string) ($row['wexpress_last_response_json'] ?? '');
            $decoded = $raw !== '' ? json_decode($raw, true) : null;
            if (is_array($decoded)) {
                $data = $decoded;
            } else {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
                exit;
            }
        }

        // Alguns ambientes não devolvem o PDF/URL da etiqueta no GET /shipping/{id};
        // então tentamos endpoints comuns para download do label.
        $sid = rawurlencode($shipId);
        $labelPaths = [
            '/shipping/' . $sid . '/label/',
            '/shipping/' . $sid . '/label',
            '/shipping/' . $sid . '/label/?format=pdf',
            '/shipping/' . $sid . '/label?format=pdf',
            '/shipping/' . $sid . '/labels/',
            '/shipping/' . $sid . '/labels',
            '/shipping/' . $sid . '/labels/?format=pdf',
            '/shipping/' . $sid . '/labels?format=pdf',
            '/shipping/' . $sid . '/documents/label/',
            '/shipping/' . $sid . '/documents/label',
            '/shipping/' . $sid . '/documents/label?format=pdf',
            '/shipping/' . $sid . '/document/label/',
            '/shipping/' . $sid . '/document/label',
            '/shipping/' . $sid . '/pdf/',
            '/shipping/' . $sid . '/pdf',
            // variações alternativas
            '/shipping/label/' . $sid,
            '/shipping/label/' . $sid . '/',
            '/shipping/labels/' . $sid,
            '/shipping/labels/' . $sid . '/',
            '/labels/' . $sid,
            '/label/' . $sid,
            '/shipping/' . $sid . '/print/',
            '/shipping/' . $sid . '/print',
        ];

        $attempts = [];
        foreach ($labelPaths as $p) {
            try {
                $raw = $svc->requestRaw('GET', $p);
                $ct = strtolower((string) ($raw['content_type'] ?? ''));
                $body = $raw['body'] ?? '';
                $attempts[] = [
                    'path' => $p,
                    'http_code' => (int) ($raw['http_code'] ?? 0),
                    'content_type' => (string) ($raw['content_type'] ?? ''),
                    'len' => is_string($body) ? strlen($body) : null,
                    'first4' => is_string($body) ? substr($body, 0, 4) : null,
                    'url' => (string) ($raw['url'] ?? ''),
                ];

                $isPdf = (strpos($ct, 'application/pdf') !== false);
                if (!$isPdf && is_string($body)) {
                    $isPdf = (strncmp($body, '%PDF', 4) === 0);
                }

                if ($isPdf) {
                    header('Content-Type: application/pdf');
                    header('Content-Disposition: attachment; filename="etiqueta_' . preg_replace('/[^a-zA-Z0-9_-]+/', '_', $shipId) . '.pdf"');
                    echo $body;
                    exit;
                }

                // Se devolver JSON com link
                $decoded = null;
                if (is_string($body)) {
                    $decoded = json_decode($body, true);
                }
                if (is_array($decoded)) {
                    $found2 = $this->findWexpressLabelResource($decoded);
                    if (is_array($found2) && ($found2['type'] ?? '') === 'url' && !empty($found2['value'])) {
                        header('Location: ' . $found2['value']);
                        exit;
                    }
                    if (is_array($found2) && ($found2['type'] ?? '') === 'base64_pdf' && !empty($found2['value'])) {
                        $bin = base64_decode((string) $found2['value']);
                        if ($bin !== false) {
                            header('Content-Type: application/pdf');
                            header('Content-Disposition: attachment; filename="etiqueta_' . preg_replace('/[^a-zA-Z0-9_-]+/', '_', $shipId) . '.pdf"');
                            echo $bin;
                            exit;
                        }
                    }
                }
            } catch (\Exception $e) {
                // ignora e tenta o próximo
                $attempts[] = [
                    'path' => $p,
                    'error' => $e->getMessage(),
                ];
            }
        }

        $found = $this->findWexpressLabelResource($data);
        if (is_array($found) && ($found['type'] ?? '') === 'url' && !empty($found['value'])) {
            header('Location: ' . $found['value']);
            exit;
        }
        if (is_array($found) && ($found['type'] ?? '') === 'base64_pdf' && !empty($found['value'])) {
            $bin = base64_decode((string) $found['value']);
            if ($bin === false) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['success' => false, 'error' => 'Falha ao decodificar PDF (base64)']);
                exit;
            }
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="etiqueta_' . preg_replace('/[^a-zA-Z0-9_-]+/', '_', $shipId) . '.pdf"');
            echo $bin;
            exit;
        }

        $printUrl = 'https://label.wexpress.me/wexpress-premium/?shipping_id=' . rawurlencode($shipId);
        header('Location: ' . $printUrl);
        exit;

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => 'Etiqueta não encontrada no retorno da W-Express. Use a URL de impressão para visualizar a label.',
            'shipping_id' => $shipId,
            'data' => $data,
            'label_attempts' => $attempts,
            'print_url' => $printUrl,
        ]);
        exit;
    }

    private function findWexpressLabelResource($data): ?array {
        if (!is_array($data)) {
            return null;
        }

        $queue = [$data];
        while (!empty($queue)) {
            $cur = array_shift($queue);
            if (!is_array($cur)) {
                continue;
            }
            foreach ($cur as $k => $v) {
                $key = strtolower((string) $k);

                if (is_string($v)) {
                    $val = trim($v);
                    // URL
                    if (preg_match('#^https?://#i', $val) && (strpos($key, 'label') !== false || strpos($key, 'etiqueta') !== false || strpos($key, 'pdf') !== false)) {
                        return ['type' => 'url', 'value' => $val, 'key' => (string) $k];
                    }
                    // base64 pdf
                    if ((strpos($key, 'label') !== false || strpos($key, 'etiqueta') !== false || strpos($key, 'pdf') !== false) && strlen($val) > 200 && preg_match('#^[A-Za-z0-9+/=\r\n]+$#', $val)) {
                        return ['type' => 'base64_pdf', 'value' => preg_replace('/\s+/', '', $val), 'key' => (string) $k];
                    }
                }
                if (is_array($v)) {
                    $queue[] = $v;
                }
            }
        }

        return null;
    }

    private function getJanelasByStatus(array $statuses): array {
        $statuses = array_values(array_filter(array_map('strval', $statuses)));
        if (!$statuses) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
        $sql = "SELECT id, data_inicio, data_fim, status, closed_at, created_at, updated_at FROM remessa_janelas WHERE status IN ({$placeholders}) ORDER BY data_inicio DESC LIMIT 50";
        $st = $this->connection->prepare($sql);
        $st->execute($statuses);
        $janelas = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        // Enriquecer com contagem de pedidos e etiquetas pendentes
        foreach ($janelas as &$janela) {
            $jid = (int) $janela['id'];
            try {
                $stCount = $this->connection->prepare('SELECT COUNT(*) as total, SUM(CASE WHEN etiqueta_gerada = 0 THEN 1 ELSE 0 END) as pendentes FROM remessa_janela_pedidos WHERE janela_id = ?');
                $stCount->execute([$jid]);
                $row = $stCount->fetch(\PDO::FETCH_ASSOC);
                $janela['total_pedidos']      = (int) ($row['total'] ?? 0);
                $janela['etiquetas_pendentes'] = (int) ($row['pendentes'] ?? 0);
            } catch (\Exception $e) {
                $janela['total_pedidos']      = 0;
                $janela['etiquetas_pendentes'] = 0;
            }
        }
        unset($janela);

        return $janelas;
    }

    public function buscarPedido($request) {
        $this->requireAdmin();
        $pedidoId = (int) ($request->getParam('id') ?? 0);
        if ($pedidoId <= 0) {
            echo json_encode(['success' => false, 'error' => 'ID inválido']);
            exit;
        }

        try {
            // Buscar em qual janela o pedido está
            $st = $this->connection->prepare('SELECT janela_id FROM remessa_janela_pedidos WHERE pedido_id = ? ORDER BY id DESC LIMIT 1');
            $st->execute([$pedidoId]);
            $janelaId = (int) ($st->fetchColumn() ?: 0);

            if ($janelaId > 0) {
                echo json_encode([
                    'success' => true,
                    'url' => '/admin/remessa-internacional/janela/' . $janelaId . '/pedido/' . $pedidoId,
                ]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Pedido #' . $pedidoId . ' não encontrado em nenhuma janela de remessa']);
            }
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Erro: ' . $e->getMessage()]);
        }
        exit;
    }

    public function verJanela($request, $id) {
        $this->requireAdmin();

        $janelaId = (int) $id;
        if ($janelaId <= 0) {
            header('Location: /admin/remessa-internacional');
            exit;
        }

        $this->syncPedidosParaJanela($janelaId);

        $stJ = $this->connection->prepare('SELECT * FROM remessa_janelas WHERE id = ? LIMIT 1');
        $stJ->execute([$janelaId]);
        $janela = $stJ->fetch(\PDO::FETCH_ASSOC);
        if (!$janela) {
            header('Location: /admin/remessa-internacional');
            exit;
        }

        $colsPedidos = [];
        try {
            $stCols = $this->connection->query('DESCRIBE pedidos');
            $colsPedidos = $stCols ? ($stCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
        } catch (\Exception $e) {
            $colsPedidos = [];
        }

        $hasMoeda = is_array($colsPedidos) && in_array('moeda', $colsPedidos, true);
        $hasCurrency = is_array($colsPedidos) && in_array('currency', $colsPedidos, true);

        // Pedidos da janela
        $sql = "
            SELECT 
                rjp.pedido_id,
                rjp.etiqueta_gerada,
                rjp.etiqueta_gerada_em,
                rjp.wexpress_shipping_id,
                rjp.wexpress_tracking_number,
                rjp.courier_tracking_number,
                rjp.wexpress_status,
                rjp.created_at AS entrou_janela_em,
                p.created_at AS pedido_criado_em,
                p.total,
                " . ($hasMoeda ? 'p.moeda,' : "'' AS moeda,") . "
                " . ($hasCurrency ? 'p.currency,' : "'' AS currency,") . "
                p.status,
                u.nome AS cliente_nome,
                u.email AS cliente_email
            FROM remessa_janela_pedidos rjp
            LEFT JOIN pedidos p ON p.id = rjp.pedido_id
            LEFT JOIN usuarios u ON u.id = p.usuario_id
            WHERE rjp.janela_id = ?
            ORDER BY (rjp.created_at IS NULL) ASC, rjp.created_at DESC, rjp.pedido_id DESC
        ";
        $st = $this->connection->prepare($sql);
        $st->execute([$janelaId]);
        $pedidos = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        // Totais
        $total = count($pedidos);
        $geradas = 0;
        foreach ($pedidos as $p) {
            if ((int) ($p['etiqueta_gerada'] ?? 0) === 1) {
                $geradas++;
            }
        }
        $pendentes = $total - $geradas;

        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Janela de Remessa #' . (int) $janelaId . '</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';

        renderAdminSidebarStyles();

        echo '</head>
<body>
<div class="container-fluid">
  <div class="row">';

        renderAdminSidebar('remessa-internacional');

        $badge = 'secondary';
        if (($janela['status'] ?? '') === 'aberta') $badge = 'success';
        if (($janela['status'] ?? '') === 'atraso') $badge = 'danger';
        if (($janela['status'] ?? '') === 'remessa_gerada') $badge = 'info';

        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1 class="h3 mb-0">Janela #' . (int) $janelaId . ' <span class="badge bg-' . $badge . '">' . htmlspecialchars((string) ($janela['status'] ?? '')) . '</span></h1>
                    <div class="text-muted small">' . date('d/m/Y', strtotime((string) $janela['data_inicio'])) . ' a ' . date('d/m/Y', strtotime((string) $janela['data_fim'])) . '</div>
                </div>
                <div class="d-flex gap-2">
                    <a class="btn btn-outline-secondary" href="/admin/remessa-internacional"><i class="fas fa-arrow-left"></i> Voltar</a>
                    <button class="btn btn-outline-primary" type="button" onclick="location.reload()"><i class="fas fa-sync"></i> Atualizar</button>
                    <button class="btn btn-success" type="button" onclick="fecharJanela()" ' . ($pendentes > 0 ? 'disabled' : '') . '><i class="fas fa-check"></i> Fechar Janela</button>
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-md-4">
                    <div class="card"><div class="card-body">
                        <div class="text-muted">Total pedidos</div>
                        <div class="h4 mb-0">' . (int) $total . '</div>
                    </div></div>
                </div>
                <div class="col-md-4">
                    <div class="card"><div class="card-body">
                        <div class="text-muted">Etiquetas geradas</div>
                        <div class="h4 mb-0">' . (int) $geradas . '</div>
                    </div></div>
                </div>
                <div class="col-md-4">
                    <div class="card"><div class="card-body">
                        <div class="text-muted">Pendentes</div>
                        <div class="h4 mb-0">' . (int) $pendentes . '</div>
                    </div></div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <strong>Pedidos desta janela</strong>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle">
                            <thead>
                                <tr>
                                    <th>Pedido</th>
                                    <th>Cliente</th>
                                    <th>Entrou na janela</th>
                                    <th>Total</th>
                                    <th>Etiqueta</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>';

        if (!$pedidos) {
            echo '<tr><td colspan="6" class="text-center text-muted">Nenhum pedido nesta janela.</td></tr>';
        } else {
            $usdToBrl = $this->getUsdToBrlRate();
            $brlToUsd = ($usdToBrl > 0.000001) ? (1.0 / $usdToBrl) : 1.0;
            foreach ($pedidos as $p) {
                $pid = (int) ($p['pedido_id'] ?? 0);
                $et = (int) ($p['etiqueta_gerada'] ?? 0);
                $etBadge = $et === 1 ? 'success' : 'warning';
                $etLabel = $et === 1 ? 'Gerada' : 'Pendente';
                $wxStatus = (string) ($p['wexpress_status'] ?? '');
                $wxShipId = (string) ($p['wexpress_shipping_id'] ?? '');
                $wxCourier = (string) ($p['courier_tracking_number'] ?? '');
                $dt = !empty($p['entrou_janela_em']) ? date('d/m/Y H:i', strtotime((string) $p['entrou_janela_em'])) : '-';
                $dtPedido = !empty($p['pedido_criado_em']) ? date('d/m/Y H:i', strtotime((string) $p['pedido_criado_em'])) : '';
                $moeda = strtoupper(trim((string) ($p['moeda'] ?? ($p['currency'] ?? 'USD'))));
                if ($moeda === '') $moeda = 'USD';
                $totalValue = is_numeric($p['total'] ?? null) ? (float) $p['total'] : null;
                if ($totalValue !== null && $moeda === 'BRL') {
                    $totalValue = $totalValue * $brlToUsd;
                }
                $totalV = $totalValue !== null ? number_format((float) $totalValue, 2, ',', '.') : '-';

                echo '<tr>
                    <td><strong>#' . str_pad((string) $pid, 6, '0', STR_PAD_LEFT) . '</strong></td>
                    <td>' . htmlspecialchars((string) ($p['cliente_nome'] ?? 'N/A')) . '<br><small class="text-muted">' . htmlspecialchars((string) ($p['cliente_email'] ?? '')) . '</small></td>
                    <td>' . $dt . ($dtPedido && $dtPedido !== $dt ? '<br><small class="text-muted" title="Data do pedido">Pedido: ' . $dtPedido . '</small>' : '') . '</td>
                    <td>US$ ' . $totalV . '</td>
                    <td>
                        <span class="badge bg-' . $etBadge . '">' . $etLabel . '</span>';

                if ($wxStatus !== '') {
                    echo '<br><small class="text-muted">W-Express: ' . htmlspecialchars($wxStatus) . '</small>';
                }
                if ($wxShipId !== '') {
                    echo '<br><small class="text-muted">ShipID: ' . htmlspecialchars($wxShipId) . '</small>';
                }
                if ($wxCourier !== '') {
                    echo '<br><small class="text-muted">Tracking: ' . htmlspecialchars($wxCourier) . '</small>';
                }

                echo '    </td>
                    <td>
                        <a class="btn btn-sm btn-outline-primary" href="/admin/remessa-internacional/janela/' . (int) $janelaId . '/pedido/' . (int) $pid . '"><i class="fas fa-eye"></i> Detalhes</a>
                        <button class="btn btn-sm btn-outline-success ms-1" type="button" onclick="marcarEtiquetaGerada(' . (int) $pid . ')" ' . ($et === 1 ? 'disabled' : '') . '><i class="fas fa-tag"></i> Marcar etiqueta</button>
                    </td>
                </tr>';
            }
        }

        echo '</tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
';

        echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const janelaId = ' . (int) $janelaId . ';

function marcarEtiquetaGerada(pedidoId) {
    if (!confirm("Marcar etiqueta como gerada para este pedido?")) return;
    fetch("/admin/remessa-internacional/janela/" + janelaId + "/pedido/" + pedidoId + "/etiqueta-gerada", { method: "POST" })
        .then(r => r.json().catch(() => ({})).then(data => ({ ok: r.ok, data })))
        .then(({ok, data}) => {
            if (ok && data.success) {
                location.reload();
            } else {
                alert("Erro: " + (data.message || data.error || JSON.stringify(data)));
            }
        })
        .catch(err => alert("Erro: " + err.message));
}

function fecharJanela() {
    if (!confirm("Fechar esta janela? (Somente se todas as etiquetas estiverem geradas)")) return;
    fetch("/admin/remessa-internacional/janela/" + janelaId + "/fechar", { method: "POST" })
        .then(r => r.json().catch(() => ({})).then(data => ({ ok: r.ok, data })))
        .then(({ok, data}) => {
            if (ok && data.success) {
                window.location.href = "/admin/remessa-internacional";
            } else {
                alert("Erro: " + (data.message || data.error || JSON.stringify(data)));
            }
        })
        .catch(err => alert("Erro: " + err.message));
}
</script>
</body>
</html>';
        exit;
    }

    public function detalhesPedidoJanela($request, $janelaId, $pedidoId) {
        $this->requireAdmin();

        $jid = (int) $janelaId;
        $pid = (int) $pedidoId;
        if ($jid <= 0 || $pid <= 0) {
            header('Location: /admin/remessa-internacional');
            exit;
        }

        $this->syncPedidosParaJanela($jid);

        $stChk = $this->connection->prepare('SELECT etiqueta_gerada, etiqueta_gerada_em, wexpress_shipping_id, wexpress_status, wexpress_tracking_number, courier_tracking_number FROM remessa_janela_pedidos WHERE janela_id = ? AND pedido_id = ? LIMIT 1');
        $stChk->execute([$jid, $pid]);
        $rel = $stChk->fetch(\PDO::FETCH_ASSOC) ?: [];

        $wexpressPurpose = '';
        $wexpressInvoice = '';
        try {
            $stExtra = $this->connection->prepare('SELECT wexpress_shipment_purpose, wexpress_invoice_number FROM remessa_janela_pedidos WHERE janela_id = ? AND pedido_id = ? LIMIT 1');
            $stExtra->execute([$jid, $pid]);
            $extra = $stExtra->fetch(\PDO::FETCH_ASSOC) ?: [];
            $wexpressPurpose = (string) ($extra['wexpress_shipment_purpose'] ?? '');
            $wexpressInvoice = (string) ($extra['wexpress_invoice_number'] ?? '');
        } catch (\Exception $e) {
            $wexpressPurpose = '';
            $wexpressInvoice = '';
        }

        $pedido = $this->getPedidoCompleto($pid);
        if (!$pedido) {
            header('Location: /admin/remessa-internacional/janela/' . $jid);
            exit;
        }

        $usdToBrl = $this->getUsdToBrlRate();
        $brlToUsd = ($usdToBrl > 0.000001) ? (1.0 / $usdToBrl) : 1.0;

        $moedaPedido = strtoupper(trim((string) ($pedido['moeda'] ?? ($pedido['currency'] ?? 'USD'))));
        if ($moedaPedido === '') {
            $moedaPedido = 'USD';
        }

        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';
        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pedido #' . (int) $pid . ' - Janela #' . (int) $jid . '</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';
        renderAdminSidebarStyles();
        echo '</head>
<body>
<div class="container-fluid">
  <div class="row">';
        renderAdminSidebar('remessa-internacional');

        $et = (int) ($rel['etiqueta_gerada'] ?? 0);
        $etBadge = $et === 1 ? 'success' : 'warning';
        $etLabel = $et === 1 ? 'Gerada' : 'Pendente';

        $wxShipId = (string) ($rel['wexpress_shipping_id'] ?? '');
        $wxStatus = (string) ($rel['wexpress_status'] ?? '');
        $wxTrack = (string) ($rel['wexpress_tracking_number'] ?? '');
        $wxCourier = (string) ($rel['courier_tracking_number'] ?? '');

        $wxHasLabel = ($et === 1) || ($wxShipId !== '') || ($wxStatus === 'LABEL_CREATED');

        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1 class="h3 mb-0">Pedido #' . str_pad((string) $pid, 6, '0', STR_PAD_LEFT) . '</h1>
                    <div class="text-muted small">Janela #' . (int) $jid . ' | Etiqueta: <span class="badge bg-' . $etBadge . '">' . $etLabel . '</span></div>
                </div>
                <div class="d-flex gap-2">
                    <a class="btn btn-outline-secondary" href="/admin/remessa-internacional/janela/' . (int) $jid . '"><i class="fas fa-arrow-left"></i> Voltar</a>
                    ' . ($wxShipId !== '' ? ('<a class="btn btn-outline-primary" href="/admin/remessa-internacional/janela/' . (int) $jid . '/pedido/' . (int) $pid . '/etiqueta-download" target="_blank"><i class="fas fa-download"></i> Baixar etiqueta</a>') : '') . '
                    <button class="btn btn-success" type="button" onclick="gerarEtiqueta()" ' . ($wxHasLabel ? 'disabled' : '') . '><i class="fas fa-tag"></i> ' . ($wxHasLabel ? 'Etiqueta já gerada' : 'Gerar etiqueta') . '</button>
                    ' . ($wxHasLabel ? '<button class="btn btn-warning" type="button" onclick="regerarEtiqueta()"><i class="fas fa-redo"></i> Regerar etiqueta</button>' : '') . '
                </div>
            </div>

            ' . ($wxStatus !== '' || $wxShipId !== '' || $wxCourier !== '' ? ('
            <div class="alert alert-light border mb-3">
                <div class="small">
                    ' . ($wxStatus !== '' ? ('<div><strong>Status:</strong> ' . htmlspecialchars($wxStatus) . '</div>') : '') . '
                    ' . ($wxShipId !== '' ? ('<div><strong>Shipping ID:</strong> ' . htmlspecialchars($wxShipId) . '</div>') : '') . '
                    ' . ($wxTrack !== '' ? ('<div><strong>WExpress tracking:</strong> ' . htmlspecialchars($wxTrack) . '</div>') : '') . '
                    ' . ($wxCourier !== '' ? ('<div><strong>Courier tracking:</strong> ' . htmlspecialchars($wxCourier) . '</div>') : '') . '
                </div>
            </div>
            ') : '') . '

            <div class="card mb-3">
                <div class="card-header"><strong>W-Express</strong></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Finalidade do envio (shipment_purpose)</label>
                            <input class="form-control" value="personal" disabled />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Invoice number</label>
                            <input class="form-control" value="' . htmlspecialchars((string) $pid, ENT_QUOTES, 'UTF-8') . '" disabled />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tariff code (NCM)</label>
                            <div class="form-text">Será sempre enviado com base no NCM cadastrado no produto.</div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-6">
                    <div class="card mb-3">
                        <div class="card-header"><strong>Cliente</strong></div>
                        <div class="card-body">
                            <div><strong>Nome:</strong> ' . htmlspecialchars((string) ($pedido['cliente_nome'] ?? '')) . '</div>
                            <div><strong>Email:</strong> ' . htmlspecialchars((string) ($pedido['cliente_email'] ?? '')) . '</div>
                            <div><strong>Telefone:</strong> ' . htmlspecialchars((string) ($pedido['cliente_telefone'] ?? '')) . '</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card mb-3">
                        <div class="card-header"><strong>Pedido</strong></div>
                        <div class="card-body">
                            <div><strong>Data:</strong> ' . (!empty($pedido['created_at']) ? date('d/m/Y H:i', strtotime((string) $pedido['created_at'])) : '-') . '</div>
                            <div><strong>Total:</strong> ';

        $totalUsd = null;
        if (is_numeric($pedido['total'] ?? null)) {
            $totalUsd = (float) $pedido['total'];
            // Se o pedido foi pago em BRL, converter para USD para exibição
            $moedaPedido = strtoupper(trim((string) ($pedido['moeda'] ?? ($pedido['currency'] ?? 'USD'))));
            if ($moedaPedido === 'BRL' && $totalUsd > 0) {
                $usdRate = $this->getUsdToBrlRate();
                $brlRate = ($usdRate > 0.000001) ? (1.0 / $usdRate) : (1.0 / 5.85);
                $totalUsd = $totalUsd * $brlRate;
            }
        }
        echo 'US$ ' . ($totalUsd !== null ? number_format((float) $totalUsd, 2, ',', '.') : '-') . '</div>
                            <div><strong>Status:</strong> ' . htmlspecialchars((string) ($pedido['status'] ?? '')) . '</div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><strong>Endereço</strong></div>
                <div class="card-body">';

        $end = $pedido['endereco'] ?? null;
        if (is_array($end) && !empty($end)) {
            echo htmlspecialchars(trim((string) (($end['logradouro'] ?? '') . ' ' . ($end['numero'] ?? '') . ' ' . ($end['bairro'] ?? '') . ' ' . ($end['cidade'] ?? '') . ' ' . ($end['estado'] ?? '') . ' ' . ($end['cep'] ?? ''))));
        } elseif (is_string($end) && trim($end) !== '') {
            // endereco é string (logradouro) — montar a partir dos campos individuais
            $endDisplay = trim(implode(', ', array_filter([
                trim((string) ($pedido['endereco_entrega'] ?? ($pedido['endereco'] ?? ''))),
                trim((string) ($pedido['numero_entrega'] ?? ($pedido['numero'] ?? ''))),
                trim((string) ($pedido['complemento_entrega'] ?? ($pedido['complemento'] ?? ''))),
                trim((string) ($pedido['bairro_entrega'] ?? ($pedido['bairro'] ?? ''))),
                trim((string) ($pedido['cidade_entrega'] ?? ($pedido['cidade'] ?? ''))),
                trim((string) ($pedido['estado_entrega'] ?? ($pedido['estado'] ?? ''))),
                trim((string) ($pedido['cep_entrega'] ?? ($pedido['cep'] ?? ''))),
            ])));
            echo htmlspecialchars($endDisplay !== '' ? $endDisplay : (string) $end);
        } else {
            // Fallback: tentar campos individuais do pedido
            $endDisplay = trim(implode(', ', array_filter([
                trim((string) ($pedido['endereco_entrega'] ?? ($pedido['endereco'] ?? ''))),
                trim((string) ($pedido['numero_entrega'] ?? ($pedido['numero'] ?? ''))),
                trim((string) ($pedido['complemento_entrega'] ?? ($pedido['complemento'] ?? ''))),
                trim((string) ($pedido['bairro_entrega'] ?? ($pedido['bairro'] ?? ''))),
                trim((string) ($pedido['cidade_entrega'] ?? ($pedido['cidade'] ?? ''))),
                trim((string) ($pedido['estado_entrega'] ?? ($pedido['estado'] ?? ''))),
                trim((string) ($pedido['cep_entrega'] ?? ($pedido['cep'] ?? ''))),
            ])));
            if ($endDisplay !== '') {
                echo htmlspecialchars($endDisplay);
            } else {
                // Último fallback: buscar da tabela enderecos
                $endFound = false;
                $uid = (int) ($pedido['usuario_id'] ?? 0);
                $endEntregaId = (int) ($pedido['endereco_entrega_id'] ?? 0);
                if ($uid > 0 || $endEntregaId > 0) {
                    try {
                        if ($endEntregaId > 0) {
                            $stE = $this->connection->prepare('SELECT * FROM enderecos WHERE id = ? LIMIT 1');
                            $stE->execute([$endEntregaId]);
                        } else {
                            $stE = $this->connection->prepare('SELECT * FROM enderecos WHERE usuario_id = ? ORDER BY principal DESC, id DESC LIMIT 1');
                            $stE->execute([$uid]);
                        }
                        $rowE = $stE->fetch(\PDO::FETCH_ASSOC);
                        if (is_array($rowE) && !empty($rowE)) {
                            $parts = array_filter([
                                trim((string) ($rowE['endereco'] ?? ($rowE['logradouro'] ?? ''))),
                                trim((string) ($rowE['numero'] ?? '')),
                                trim((string) ($rowE['complemento'] ?? '')),
                                trim((string) ($rowE['bairro'] ?? '')),
                                trim((string) ($rowE['cidade'] ?? '')),
                                trim((string) ($rowE['estado'] ?? ($rowE['uf'] ?? ''))),
                                trim((string) ($rowE['cep'] ?? '')),
                            ]);
                            echo htmlspecialchars(implode(', ', $parts));
                            $endFound = true;
                        }
                    } catch (\Exception $e) {}
                }
                if (!$endFound) {
                    echo '<span class="text-muted">Não encontrado</span>';
                }
            }
        }

        echo '</div>
            </div>

            <div class="card">
                <div class="card-header"><strong>Itens</strong></div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead><tr><th>Produto</th><th>SKU</th><th>Qtd</th><th>Preço</th></tr></thead>
                            <tbody>';

        $itens = $pedido['itens'] ?? [];
        if (!is_array($itens) || !$itens) {
            echo '<tr><td colspan="4" class="text-center text-muted">Nenhum item.</td></tr>';
        } else {
            foreach ($itens as $it) {
                $precoUnit = null;
                foreach (['preco_unitario', 'valor_unitario', 'preco', 'price'] as $c) {
                    if (isset($it[$c]) && is_numeric($it[$c])) {
                        $precoUnit = (float) $it[$c];
                        break;
                    }
                }

                if ($precoUnit !== null && $moedaPedido === 'BRL') {
                    // preco_unitario é sempre em USD; não converter
                }
                echo '<tr>
                    <td>' . htmlspecialchars((string) ($it['produto_nome'] ?? '')) . '</td>
                    <td>' . htmlspecialchars((string) ($it['sku'] ?? '')) . '</td>
                    <td>' . (int) ($it['quantidade'] ?? 0) . '</td>
                    <td>US$ ' . ($precoUnit !== null ? number_format((float) $precoUnit, 2, ',', '.') : '-') . '</td>
                </tr>';
            }
        }

        echo '</tbody></table></div>
                </div>
            </div>

        </main>
    </div>
</div>
';

        echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
const janelaId = ' . (int) $jid . ';
const pedidoId = ' . (int) $pid . ';

function gerarEtiqueta() {
    if (!confirm("Gerar etiqueta agora?")) return;
    const payload = {};
    fetch("/admin/remessa-internacional/janela/" + janelaId + "/pedido/" + pedidoId + "/etiqueta-gerada", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(payload)
    })
        .then(r => r.json().catch(() => ({})).then(data => ({ ok: r.ok, data })))
        .then(({ok, data}) => {
            if (ok && data.success) {
                location.reload();
            } else {
                alert("Erro: " + (data.message || data.error || JSON.stringify(data)));
            }
        })
        .catch(err => alert("Erro: " + err.message));
}

function regerarEtiqueta() {
    if (!confirm("Isso vai cancelar a etiqueta atual e gerar uma nova. Continuar?")) return;
    fetch("/admin/remessa-internacional/janela/" + janelaId + "/pedido/" + pedidoId + "/resetar-etiqueta", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({})
    })
        .then(r => r.json().catch(() => ({})))
        .then(data => {
            if (data.success) {
                gerarEtiqueta();
            } else {
                alert("Erro ao resetar: " + (data.error || JSON.stringify(data)));
            }
        })
        .catch(err => alert("Erro: " + err.message));
}
</script>
</body>
</html>';
        exit;
    }

    public function marcarEtiquetaGerada($request, $janelaId, $pedidoId) {
        $this->requireAdmin();

        $jid = (int) $janelaId;
        $pid = (int) $pedidoId;
        if ($jid <= 0 || $pid <= 0) {
            echo json_encode(['success' => false, 'error' => 'Parâmetros inválidos']);
            exit;
        }

        try {
            $this->syncPedidosParaJanela($jid);

            $stCheck = $this->connection->prepare('SELECT etiqueta_gerada, wexpress_shipping_id, wexpress_status FROM remessa_janela_pedidos WHERE janela_id = ? AND pedido_id = ? LIMIT 1');
            $stCheck->execute([$jid, $pid]);
            $rowCheck = $stCheck->fetch(\PDO::FETCH_ASSOC) ?: [];
            $hasShipId = trim((string) ($rowCheck['wexpress_shipping_id'] ?? '')) !== '';
            $hasEtiqueta = ((int) ($rowCheck['etiqueta_gerada'] ?? 0)) === 1;
            $status = (string) ($rowCheck['wexpress_status'] ?? '');
            if ($hasShipId || $hasEtiqueta || $status === 'LABEL_CREATED') {
                echo json_encode([
                    'success' => false,
                    'error' => 'Etiqueta já foi gerada para este pedido nesta janela. Para reemitir, remova o shipping_id/status no vínculo da janela/pedido.',
                    'shipping_id' => (string) ($rowCheck['wexpress_shipping_id'] ?? ''),
                    'wexpress_status' => $status,
                ]);
                exit;
            }

            $pedido = $this->getPedidoCompleto($pid);
            if (!$pedido) {
                echo json_encode(['success' => false, 'error' => 'Pedido não encontrado']);
                exit;
            }

            if (!$this->isPedidoCaixaFechada($pedido)) {
                echo json_encode(['success' => false, 'error' => 'Pedido não está em Caixa Fechada. Marque como Caixa Fechada antes de gerar a etiqueta internacional.']);
                exit;
            }

            $svc = new WExpressService();

            $payload = $this->buildWExpressShippingPayload($svc, $pedido, $jid, '', '');

            $resp = null;
            $httpCode = null;
            $errorMsg = null;
            try {
                $resp = $svc->createShipping($payload);
                $httpCode = $svc->getLastHttpCode();
            } catch (\Exception $e) {
                $httpCode = $svc->getLastHttpCode();
                $errorMsg = $e->getMessage();
            }

            $wxStatus = is_array($resp) ? (string) ($resp['shipping_status'] ?? '') : '';
            $wxShipId = is_array($resp) ? (string) ($resp['shipping_id'] ?? '') : '';
            $wxTrack = is_array($resp) ? (string) ($resp['wexpress_tracking_number'] ?? '') : '';
            $wxCourier = is_array($resp) ? (string) ($resp['courier_tracking_number'] ?? '') : '';

            $etiquetaGerada = ($wxStatus === 'LABEL_CREATED');

            $stUp = $this->connection->prepare(
                'UPDATE remessa_janela_pedidos
                 SET
                    etiqueta_gerada = ?,
                    etiqueta_gerada_em = IF(?, COALESCE(etiqueta_gerada_em, NOW()), etiqueta_gerada_em),
                    wexpress_shipping_id = ?,
                    wexpress_tracking_number = ?,
                    courier_tracking_number = ?,
                    wexpress_status = ?,
                    wexpress_last_request_json = ?,
                    wexpress_last_response_json = ?,
                    wexpress_last_http_code = ?,
                    wexpress_updated_at = NOW()
                 WHERE janela_id = ? AND pedido_id = ?'
            );

            $stUp->execute([
                $etiquetaGerada ? 1 : 0,
                $etiquetaGerada ? 1 : 0,
                $wxShipId !== '' ? $wxShipId : null,
                $wxTrack !== '' ? $wxTrack : null,
                $wxCourier !== '' ? $wxCourier : null,
                $wxStatus !== '' ? $wxStatus : ($errorMsg !== null ? 'ERROR' : null),
                json_encode($payload),
                json_encode($resp !== null ? $resp : ['error' => $errorMsg]),
                $httpCode,
                $jid,
                $pid,
            ]);

            $this->tryAutoCloseJanela($jid);

            if ($etiquetaGerada) {
                try {
                    $pedidoModel = new PedidoEcommerce();
                    $obs = 'Etiqueta internacional gerada (W-Express)';
                    if ($wxCourier !== '') {
                        $obs .= ' - Rastreio: ' . $wxCourier;
                    } elseif ($wxTrack !== '') {
                        $obs .= ' - Rastreio: ' . $wxTrack;
                    }
                    $pedidoModel->atualizarStatus((int) $pid, 'em_transporte', $obs, $_SESSION['usuario_id'] ?? null);
                } catch (\Exception $e) {
                }

                try {
                    $notif = new \App\Services\NotificationService();
                    $labelUrl = 'https://label.wexpress.me/wexpress-premium/?shipping_id=' . rawurlencode($wxShipId !== '' ? $wxShipId : $shipId ?? '');
                    $notif->notificarEventoPedido('wexpress_label_created', (int) $pid, [
                        'courier_tracking_number' => $wxCourier,
                        'wexpress_tracking_number' => $wxTrack,
                        'shipping_id' => $wxShipId,
                        'label_url' => $labelUrl,
                    ]);
                } catch (\Exception $e) {
                }
            }

            if ($errorMsg !== null) {
                echo json_encode(['success' => false, 'error' => $errorMsg]);
                exit;
            }

            echo json_encode(['success' => true, 'wexpress_status' => $wxStatus, 'shipping_id' => $wxShipId]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    public function resetarEtiqueta($request, $janelaId, $pedidoId) {
        $this->requireAdmin();
        $jid = (int) $janelaId;
        $pid = (int) $pedidoId;
        if ($jid <= 0 || $pid <= 0) {
            echo json_encode(['success' => false, 'error' => 'Parâmetros inválidos']);
            exit;
        }
        try {
            $st = $this->connection->prepare(
                'UPDATE remessa_janela_pedidos
                 SET etiqueta_gerada = 0,
                     etiqueta_gerada_em = NULL,
                     wexpress_shipping_id = NULL,
                     wexpress_tracking_number = NULL,
                     courier_tracking_number = NULL,
                     wexpress_status = NULL,
                     wexpress_last_request_json = NULL,
                     wexpress_last_response_json = NULL,
                     wexpress_last_http_code = NULL,
                     wexpress_updated_at = NOW()
                 WHERE janela_id = ? AND pedido_id = ?'
            );
            $st->execute([$jid, $pid]);
            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    private function buildWExpressShippingPayload(WExpressService $svc, array $pedido, int $janelaId, string $shipmentPurposeOverride = '', string $invoiceNumberOverride = ''): array {
        $sender = $svc->getSender();
        if (!is_array($sender) || empty($sender)) {
            $err = method_exists($svc, 'getSenderJsonError') ? $svc->getSenderJsonError() : null;
            if (!empty($err)) {
                throw new \Exception('W-Express: Sender (JSON) inválido em /admin/configuracoes > Entrega: ' . $err);
            }
            throw new \Exception('W-Express: configure o Sender (JSON) em /admin/configuracoes > Entrega');
        }

        $nome = trim((string) ($pedido['cliente_nome'] ?? ''));
        $partes = preg_split('/\s+/', $nome) ?: [];
        $firstName = $partes[0] ?? $nome;
        $lastName = count($partes) > 1 ? implode(' ', array_slice($partes, 1)) : '';

        $doc = (string) ($pedido['documento'] ?? ($pedido['cliente_documento'] ?? ''));
        $docDigits = (string) preg_replace('/\D+/', '', $doc);
        $taxType = strlen($docDigits) > 11 ? 'CNPJ' : 'CPF';
        $taxId = $docDigits;

        if ($taxType === 'CPF') {
            if (!$this->isValidCpf($taxId)) {
                throw new \Exception('W-Express: CPF do destinatário inválido. Corrija o documento do cliente antes de gerar a etiqueta. [' . $taxId . ']');
            }
        } else {
            if (!$this->isValidCnpj($taxId)) {
                throw new \Exception('W-Express: CNPJ do destinatário inválido. Corrija o documento do cliente antes de gerar a etiqueta. [' . $taxId . ']');
            }
        }

        $recipientType = ($taxType === 'CPF') ? 'individual' : 'business';

        $shipmentPurpose = 'personal';
        $invoiceNumber = (string) ((int) ($pedido['id'] ?? 0));
        if ($invoiceNumber === '0') {
            $invoiceNumber = (string) ('PEDIDO-' . (int) ($pedido['id'] ?? 0));
        }

        $end = $pedido['endereco'] ?? [];
        // Se 'endereco' é uma string (logradouro) e não um sub-array, montar a partir dos campos do pedido
        if (!is_array($end)) {
            $end = [
                'cep' => $pedido['cep_entrega'] ?? ($pedido['cep'] ?? ''),
                'logradouro' => $pedido['endereco_entrega'] ?? ($pedido['endereco'] ?? ''),
                'endereco' => $pedido['endereco_entrega'] ?? ($pedido['endereco'] ?? ''),
                'numero' => $pedido['numero_entrega'] ?? ($pedido['numero'] ?? ''),
                'complemento' => $pedido['complemento_entrega'] ?? ($pedido['complemento'] ?? ''),
                'bairro' => $pedido['bairro_entrega'] ?? ($pedido['bairro'] ?? ''),
                'cidade' => $pedido['cidade_entrega'] ?? ($pedido['cidade'] ?? ''),
                'estado' => $pedido['estado_entrega'] ?? ($pedido['estado'] ?? ''),
            ];
        }

        // Fallback: se endereço ainda vazio, buscar da tabela enderecos pelo usuario_id
        $endCep = trim((string) ($end['cep'] ?? ''));
        $endAddr = trim((string) ($end['endereco'] ?? ($end['logradouro'] ?? '')));
        if (($endCep === '' || $endAddr === '') && $this->tableExists('enderecos')) {
            $uid = (int) ($pedido['usuario_id'] ?? 0);
            $endEntregaId = (int) ($pedido['endereco_entrega_id'] ?? 0);
            if ($uid > 0 || $endEntregaId > 0) {
                try {
                    $sqlEnd = '';
                    $paramsEnd = [];
                    if ($endEntregaId > 0) {
                        $sqlEnd = 'SELECT * FROM enderecos WHERE id = ? LIMIT 1';
                        $paramsEnd = [$endEntregaId];
                    } else {
                        // Buscar endereço principal ou mais recente do usuário
                        $sqlEnd = 'SELECT * FROM enderecos WHERE usuario_id = ? ORDER BY principal DESC, id DESC LIMIT 1';
                        $paramsEnd = [$uid];
                    }
                    $stEnd = $this->connection->prepare($sqlEnd);
                    $stEnd->execute($paramsEnd);
                    $rowEnd = $stEnd->fetch(\PDO::FETCH_ASSOC);
                    if (is_array($rowEnd) && !empty($rowEnd)) {
                        $end = [
                            'cep' => $rowEnd['cep'] ?? '',
                            'logradouro' => $rowEnd['endereco'] ?? ($rowEnd['logradouro'] ?? ''),
                            'endereco' => $rowEnd['endereco'] ?? ($rowEnd['logradouro'] ?? ''),
                            'numero' => $rowEnd['numero'] ?? '',
                            'complemento' => $rowEnd['complemento'] ?? '',
                            'bairro' => $rowEnd['bairro'] ?? '',
                            'cidade' => $rowEnd['cidade'] ?? '',
                            'estado' => $rowEnd['estado'] ?? ($rowEnd['uf'] ?? ''),
                        ];
                    }
                } catch (\Exception $e) {}
            }
        }

        $cep = preg_replace('/\D+/', '', (string) ($end['cep'] ?? ($pedido['cep_entrega'] ?? ($pedido['cep'] ?? ''))));
        $addr1 = trim((string) ($end['endereco'] ?? ($end['logradouro'] ?? ($pedido['endereco_entrega'] ?? ($pedido['endereco'] ?? '')))));
        $addr2Parts = [];
        $compl = trim((string) ($end['complemento'] ?? ($pedido['complemento_entrega'] ?? ($pedido['complemento'] ?? ''))));
        $bairro = trim((string) ($end['bairro'] ?? ($pedido['bairro_entrega'] ?? ($pedido['bairro'] ?? ''))));
        if ($compl !== '') $addr2Parts[] = $compl;
        if ($bairro !== '') $addr2Parts[] = $bairro;
        $addr2 = trim(implode(', ', $addr2Parts));
        $numero = trim((string) ($end['numero'] ?? ($pedido['numero_entrega'] ?? ($pedido['numero'] ?? ''))));
        $cidade = (string) ($end['cidade'] ?? ($pedido['cidade_entrega'] ?? ($pedido['cidade'] ?? '')));
        $estado = (string) ($end['estado'] ?? ($pedido['estado_entrega'] ?? ($pedido['estado'] ?? '')));

        $itens = $pedido['itens'] ?? [];
        $items = [];

        $moeda = strtoupper(trim((string) ($pedido['moeda'] ?? ($pedido['currency'] ?? 'USD'))));
        if ($moeda === '') $moeda = 'USD';

        $usdToBrl = $this->getUsdToBrlRate();
        $brlToUsd = ($usdToBrl > 0.000001) ? (1.0 / $usdToBrl) : 1.0;

        if (is_array($itens)) {
            foreach ($itens as $it) {
                $qtd = (int) ($it['quantidade'] ?? 1);
                if ($qtd <= 0) $qtd = 1;

                $unitValue = null;
                foreach (['preco_unitario', 'valor_unitario', 'price', 'preco'] as $c) {
                    if (isset($it[$c]) && is_numeric($it[$c])) {
                        $unitValue = (float) $it[$c];
                        break;
                    }
                }
                if ($unitValue === null) {
                    $unitValue = 1.0;
                }
                // Se o pedido foi pago em BRL, converter o preço unitário para USD
                if ($moeda === 'BRL' && $unitValue > 0) {
                    $unitValue = $unitValue * $brlToUsd;
                }

                $row = [
                    'description' => (string) ($it['produto_nome'] ?? ($it['nome_produto'] ?? 'item')),
                    'quantity' => $qtd,
                    'unit_value' => round((float) $unitValue, 2),
                ];

                $ncm = (string) ($it['ncm'] ?? ($it['tariff_code'] ?? ''));
                $ncmDigits = preg_replace('/\D+/', '', $ncm);
                if ($ncmDigits === '') {
                    throw new \Exception('W-Express: NCM (tariff_code) não encontrado no produto. Cadastre o NCM em todos os produtos antes de gerar etiqueta.');
                }
                $row['tariff_code'] = (int) $ncmDigits;

                $items[] = $row;
            }
        }

        // Usar peso_total do pedido como prioridade; fallback: somar peso dos itens
        $pesoTotal = 0.0;
        if (is_numeric($pedido['peso_total'] ?? null) && (float) $pedido['peso_total'] > 0) {
            $pesoTotal = (float) $pedido['peso_total'];
        } else {
            if (is_array($itens)) {
                foreach ($itens as $it) {
                    $qtd = (int) ($it['quantidade'] ?? 1);
                    if ($qtd <= 0) $qtd = 1;
                    $peso = null;
                    foreach (['peso', 'weight', 'peso_kg'] as $c) {
                        if (isset($it[$c]) && is_numeric($it[$c])) {
                            $peso = (float) $it[$c];
                            break;
                        }
                    }
                    if ($peso !== null && $peso > 0) {
                        $pesoTotal += ($peso * $qtd);
                    }
                }
            }
        }
        if ($pesoTotal <= 0) {
            $pesoTotal = 1.0;
        }
        $pesoTotal = max(0.001, (float) $pesoTotal);

        $freteDeclarado = 1.80;

        $packages = [[
            'weight' => round($pesoTotal * 1000, 2),
            'width'  => (float) ($pedido['largura'] ?? 0) > 0 ? (float) $pedido['largura'] : 10,
            'length' => (float) ($pedido['comprimento'] ?? 0) > 0 ? (float) $pedido['comprimento'] : 15,
            'height' => (float) ($pedido['altura'] ?? 0) > 0 ? (float) $pedido['altura'] : 10,
        ]];

        // declared_value = subtotal dos produtos em USD (soma de unit_value * quantity dos itens)
        $declared = 0.0;
        foreach ($items as $item) {
            $declared += (float) ($item['unit_value'] ?? 0) * (int) ($item['quantity'] ?? 1);
        }
        if ($declared <= 0) {
            // fallback: pegar valor do pedido caso itens não tenham preço
            foreach (['valor_total', 'total', 'valor', 'amount'] as $c) {
                if (isset($pedido[$c]) && is_numeric($pedido[$c])) {
                    $declared = (float) $pedido[$c];
                    break;
                }
            }
            if ($moeda === 'BRL') {
                $declared = $declared * $brlToUsd;
            }
        }

        $externalShippingId = (string) (($pedido['codigo_pedido'] ?? '') !== '' ? $pedido['codigo_pedido'] : ('PEDIDO-' . (int) ($pedido['id'] ?? 0)));
        $externalRef = 'janela-' . (int) $janelaId;

        return [
            'shipment_purpose' => $shipmentPurpose,
            'external_shipping_id' => $externalShippingId,
            'external_shipping_reference' => $externalRef,
            'service_code' => $svc->getServiceCode(),
            'incoterms' => 'DDU',
            'dimensions_unit' => 'cm',
            'weight_unit' => 'g',
            'currency' => 'USD',
            'declared_value' => round((float) $declared, 2),
            'freight_value' => (float) $freteDeclarado,
            'insurance_value' => 0,
            'invoice_number' => $invoiceNumber,
            'packages' => $packages,
            'sender' => $sender,
            'recipient' => [
                'type' => $recipientType,
                'business_name' => $recipientType === 'business' ? (string) ($pedido['cliente_nome'] ?? '') : ' ',
                'first_name' => $firstName,
                'last_name' => $lastName,
                'tax_id_type' => $taxType,
                'tax_id' => $taxId,
                'email' => (string) ($pedido['cliente_email'] ?? ''),
                'phone' => (string) ($pedido['cliente_telefone'] ?? ''),
                'address' => [
                    'address_number' => $numero,
                    'address_line_1' => $addr1,
                    'address_line_2' => $addr2,
                    'postal_code' => $cep,
                    'city' => $cidade,
                    'state' => $estado,
                    'country' => 'BR',
                ],
            ],
            'items' => $items,
        ];
    }

    private function getUsdToBrlRate(): float {
        try {
            // Primeiro: tentar da tabela configuracoes_moeda
            $st = $this->connection->query('DESCRIBE configuracoes_moeda');
            $cols = $st ? ($st->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            if (is_array($cols) && !empty($cols) && in_array('taxa_conversao', $cols, true)) {
                $stTx = $this->connection->prepare("SELECT taxa_conversao FROM configuracoes_moeda WHERE moeda_origem = 'USD' AND moeda_destino = 'BRL' ORDER BY id DESC LIMIT 1");
                $stTx->execute();
                $tx = (float) ($stTx->fetchColumn() ?: 0);
                if ($tx > 1.01) return $tx;
            }
        } catch (\Exception $e) {}

        // Fallback: buscar de configuracoes_sistema (sistema_usd_brl_rate ou usd_brl_rate)
        try {
            foreach (['sistema_usd_brl_rate', 'usd_brl_rate'] as $k) {
                $stCfg = $this->connection->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1");
                $stCfg->execute([$k]);
                $v = (float) str_replace(',', '.', (string) ($stCfg->fetchColumn() ?: '0'));
                if ($v > 1.01) return $v;
            }
        } catch (\Exception $e) {}

        return 5.85;
    }

    public function fecharJanela($request, $id) {
        $this->requireAdmin();

        $janelaId = (int) $id;
        if ($janelaId <= 0) {
            echo json_encode(['success' => false, 'error' => 'ID inválido']);
            exit;
        }

        try {
            $this->syncPedidosParaJanela($janelaId);
            $stPend = $this->connection->prepare('SELECT COUNT(*) FROM remessa_janela_pedidos WHERE janela_id = ? AND etiqueta_gerada = 0');
            $stPend->execute([$janelaId]);
            $pend = (int) $stPend->fetchColumn();
            if ($pend > 0) {
                echo json_encode(['success' => false, 'error' => "Ainda existem {$pend} pedido(s) sem etiqueta nesta janela. Gere todas as etiquetas antes de fechar."]);
                exit;
            }

            $stUp = $this->connection->prepare("UPDATE remessa_janelas SET status = 'remessa_gerada', closed_at = COALESCE(closed_at, NOW()), updated_at = NOW() WHERE id = ?");
            $stUp->execute([$janelaId]);

            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    private function getJanelasRemessa() {
        $stmt = $this->connection->prepare("
            SELECT * FROM remessa_janelas 
            WHERE status = 'aberta' 
            ORDER BY data_inicio DESC 
            LIMIT 10
        ");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function getPedidosPendentes() {
        $stmt = $this->connection->prepare("
            SELECT p.*, u.nome as cliente_nome 
            FROM pedidos p 
            LEFT JOIN usuarios u ON p.usuario_id = u.id 
            WHERE p.status = 'pendente_envio' 
            AND p.created_at >= DATE_SUB(NOW(), INTERVAL 15 DAY)
            ORDER BY p.created_at ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function getPedidosAtraso() {
        $stmt = $this->connection->prepare("
            SELECT p.*, u.nome as cliente_nome 
            FROM pedidos p 
            LEFT JOIN usuarios u ON p.usuario_id = u.id 
            WHERE p.status = 'pendente_envio' 
            AND p.created_at < DATE_SUB(NOW(), INTERVAL 15 DAY)
            ORDER BY p.created_at ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function getRemessasGeradas() {
        $hasRemessasInternacionais = $this->tableExists('remessas_internacionais');

        // Observação: a geração de etiqueta (W-Express) salva em remessa_janela_pedidos.
        // A tabela remessas_internacionais só é preenchida pelo botão/endpoint de "Gerar remessa".
        // Para o admin enxergar o que acabou de gerar, listamos também as etiquetas geradas.
        $sqlEtiquetaGerada = "
            SELECT
                rjp.id as id,
                rjp.pedido_id,
                rjp.etiqueta_gerada_em as created_at,
                'etiqueta_gerada' as status,
                0 as webhook_enviado,
                p.usuario_id,
                u.nome as cliente_nome,
                rjp.wexpress_shipping_id,
                rjp.wexpress_tracking_number,
                rjp.courier_tracking_number,
                rjp.wexpress_status
            FROM remessa_janela_pedidos rjp
            LEFT JOIN pedidos p ON p.id = rjp.pedido_id
            LEFT JOIN usuarios u ON u.id = p.usuario_id
            WHERE rjp.etiqueta_gerada = 1
        ";

        if ($hasRemessasInternacionais) {
            $sql = "
                (
                    SELECT
                        r.id as id,
                        r.pedido_id,
                        r.created_at as created_at,
                        r.status as status,
                        r.webhook_enviado as webhook_enviado,
                        p.usuario_id,
                        u.nome as cliente_nome,
                        rjp.wexpress_shipping_id,
                        rjp.wexpress_tracking_number,
                        rjp.courier_tracking_number,
                        rjp.wexpress_status
                    FROM remessas_internacionais r
                    LEFT JOIN pedidos p ON r.pedido_id = p.id
                    LEFT JOIN usuarios u ON p.usuario_id = u.id
                    LEFT JOIN remessa_janela_pedidos rjp ON rjp.pedido_id = r.pedido_id
                    WHERE r.status = 'remessa_gerada'
                )
                UNION ALL
                (
                    {$sqlEtiquetaGerada}
                    AND rjp.pedido_id NOT IN (SELECT pedido_id FROM remessas_internacionais WHERE status = 'remessa_gerada')
                )
                ORDER BY created_at DESC
                LIMIT 50
            ";
        } else {
            $sql = $sqlEtiquetaGerada . " ORDER BY rjp.etiqueta_gerada_em DESC LIMIT 50";
        }

        $stmt = $this->connection->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function calcularDias($data) {
        $dataCriacao = new \DateTime($data);
        $dataAtual = new \DateTime();
        return $dataAtual->diff($dataCriacao)->days;
    }

    public function gerarRemessa($request) {
        $pedidoId = $request->getParam('id');
        
        try {
            $this->connection->beginTransaction();
            
            // Buscar dados completos do pedido
            $pedido = $this->getPedidoCompleto($pedidoId);
            
            if (!$pedido) {
                echo json_encode(['success' => false, 'message' => 'Pedido não encontrado']);
                exit;
            }
            
            // Criar remessa internacional
            $stmt = $this->connection->prepare("
                INSERT INTO remessas_internacionais 
                (pedido_id, dados_pedido, dados_cliente, status, webhook_enviado, created_at) 
                VALUES (?, ?, ?, 'remessa_gerada', 0, NOW())
            ");
            $stmt->execute([
                $pedidoId,
                json_encode($pedido),
                json_encode($pedido['cliente']),
                'remessa_gerada'
            ]);
            
            $remessaId = $this->connection->lastInsertId();
            
            // Atualizar status do pedido
            $stmt = $this->connection->prepare("
                UPDATE pedidos SET status = 'remessa_gerada', updated_at = NOW() WHERE id = ?
            ");
            $stmt->execute([$pedidoId]);
            
            // Enviar webhook
            $webhookEnviado = $this->enviarWebhook($remessaId, $pedido);
            
            if ($webhookEnviado) {
                $stmt = $this->connection->prepare("
                    UPDATE remessas_internacionais SET webhook_enviado = 1 WHERE id = ?
                ");
                $stmt->execute([$remessaId]);
            }
            
            $this->connection->commit();
            
            echo json_encode([
                'success' => true, 
                'message' => 'Remessa gerada com sucesso!',
                'remessa_id' => $remessaId,
                'webhook_enviado' => $webhookEnviado
            ]);
            
        } catch (\Exception $e) {
            $this->connection->rollBack();
            echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
        }
        exit;
    }

    private function getPedidoCompleto($pedidoId) {
        // Buscar pedido principal
        $stmt = $this->connection->prepare("
            SELECT p.*, u.nome as cliente_nome, u.email as cliente_email, u.telefone as cliente_telefone 
            FROM pedidos p 
            LEFT JOIN usuarios u ON p.usuario_id = u.id 
            WHERE p.id = ?
        ");
        $stmt->execute([$pedidoId]);
        $pedido = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$pedido) return null;
        
        // Buscar itens do pedido
        $produtoNomeCol = $this->getProdutosNomeColumn();
        $produtoCols = [];
        try {
            $stCols = $this->connection->query('DESCRIBE produtos');
            $produtoCols = $stCols ? ($stCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
        } catch (\Exception $e) {
            $produtoCols = [];
        }
        $ncmCol = null;
        foreach (['ncm', 'tariff_code', 'ncm_code', 'codigo_ncm', 'ncm_produto'] as $c) {
            if (is_array($produtoCols) && in_array($c, $produtoCols, true)) {
                $ncmCol = $c;
                break;
            }
        }
        $selNcm = $ncmCol ? (', pr.' . $ncmCol . ' as ncm') : '';
        $pesoCol = null;
        foreach (['peso', 'weight', 'peso_kg'] as $c) {
            if (is_array($produtoCols) && in_array($c, $produtoCols, true)) {
                $pesoCol = $c;
                break;
            }
        }
        $selPeso = $pesoCol ? (', pr.' . $pesoCol . ' as peso') : '';

        $stmt = $this->connection->prepare("
            SELECT pi.*, pr.{$produtoNomeCol} as produto_nome, pr.sku{$selNcm}{$selPeso}
            FROM pedido_itens pi 
            LEFT JOIN produtos pr ON pi.produto_id = pr.id 
            WHERE pi.pedido_id = ?
        ");
        $stmt->execute([$pedidoId]);
        $pedido['itens'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Buscar endereço
        $stmt = $this->connection->prepare("
            SELECT * FROM enderecos 
            WHERE usuario_id = ? AND tipo = 'entrega' 
            LIMIT 1
        ");
        $stmt->execute([$pedido['usuario_id']]);
        $pedido['endereco'] = $stmt->fetch(\PDO::FETCH_ASSOC);

        // Documento do usuário
        try {
            $stDoc = $this->connection->prepare('SELECT documento FROM usuarios WHERE id = ? LIMIT 1');
            $stDoc->execute([$pedido['usuario_id']]);
            $pedido['documento'] = (string) ($stDoc->fetchColumn() ?: '');
        } catch (\Exception $e) {
            $pedido['documento'] = '';
        }

        return $pedido;
    }

    private function getProdutosNomeColumn() {
        static $cached = null;
        if ($cached !== null) return $cached;

        $candidates = ['nome', 'titulo', 'nome_produto', 'name', 'title'];
        try {
            $db = (string) $this->connection->query('SELECT DATABASE()')->fetchColumn();
            if ($db !== '') {
                $placeholders = implode(',', array_fill(0, count($candidates), '?'));
                $sql = "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = 'produtos' AND COLUMN_NAME IN ({$placeholders}) ORDER BY FIELD(COLUMN_NAME, {$placeholders}) LIMIT 1";
                $params = array_merge([$db], $candidates, $candidates);
                $st = $this->connection->prepare($sql);
                $st->execute($params);
                $col = (string) ($st->fetchColumn() ?: '');
                if ($col !== '') {
                    $cached = $col;
                    return $cached;
                }
            }
        } catch (\Exception $e) {
            // ignore and fallback below
        }

        $cached = 'nome';
        return $cached;
    }

    private function enviarWebhook($remessaId, $pedido) {
        $webhookUrl = "https://api.logistica-internacional.com/webhook/remessa"; // URL do webhook
        
        $payload = [
            'remessa_id' => $remessaId,
            'pedido_id' => $pedido['id'],
            'codigo_pedido' => $pedido['codigo_pedido'],
            'cliente' => [
                'nome' => $pedido['cliente_nome'],
                'email' => $pedido['cliente_email'],
                'telefone' => $pedido['cliente_telefone']
            ],
            'endereco' => $pedido['endereco'],
            'itens' => $pedido['itens'],
            'total' => $pedido['total'],
            'data_pedido' => $pedido['created_at']
        ];
        
        $ch = curl_init($webhookUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer TOKEN_API' // Token da API
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode >= 200 && $httpCode < 300;
    }

    public function reenviarWebhook($request) {
        $remessaId = $request->getParam('id');
        
        try {
            $stmt = $this->connection->prepare("
                SELECT * FROM remessas_internacionais WHERE id = ?
            ");
            $stmt->execute([$remessaId]);
            $remessa = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$remessa) {
                echo json_encode(['success' => false, 'message' => 'Remessa não encontrada']);
                exit;
            }
            
            $pedido = json_decode($remessa['dados_pedido'], true);
            $webhookEnviado = $this->enviarWebhook($remessaId, $pedido);
            
            if ($webhookEnviado) {
                $stmt = $this->connection->prepare("
                    UPDATE remessas_internacionais SET webhook_enviado = 1 WHERE id = ?
                ");
                $stmt->execute([$remessaId]);
                
                echo json_encode(['success' => true, 'message' => 'Webhook reenviado com sucesso!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Falha ao enviar webhook']);
            }
            
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erro: ' . $e->getMessage()]);
        }
        exit;
    }
}
