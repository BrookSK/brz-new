<?php
namespace App\Controllers;

use Config\Database;
use App\Services\WExpressService;
use App\Models\PedidoEcommerce;

class AdminRemessaInternacionalController extends Controller {
    private $connection;

    public function __construct() {
        $this->connection = Database::getConnection();
    }

    private function requireAdmin(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $usuarioId = $_SESSION['usuario_id'] ?? ($_SESSION['user_id'] ?? null);
        $logado = $_SESSION['logado'] ?? null;
        $temSessao = ($logado === true) || (!empty($usuarioId));

        if (!$temSessao) {
            header('Location: /loginadmin');
            exit;
        }

        $perfil = $_SESSION['usuario_perfil'] ?? ($_SESSION['perfil'] ?? ($_SESSION['user_perfil'] ?? null));
        $isAdmin = ($perfil === 'admin') || (!empty($_SESSION['is_admin']) && $_SESSION['is_admin']);
        if (!$isAdmin) {
            header('Location: /admin');
            exit;
        }
    }

    private function ensureTables(): void {
        return;
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
        $fim = (string) $j['data_fim'];

        // Todos os pedidos dentro do período entram na janela
        $stP = $this->connection->prepare("SELECT id FROM pedidos WHERE pago_em IS NOT NULL AND pago_em >= ? AND pago_em <= ?");
        $stP->execute([$inicio, $fim]);
        $pedidoIds = $stP->fetchAll(\PDO::FETCH_COLUMN) ?: [];

        if (!$pedidoIds) {
            return;
        }

        $stIns = $this->connection->prepare('INSERT IGNORE INTO remessa_janela_pedidos (janela_id, pedido_id, created_at) VALUES (?, ?, NOW())');
        foreach ($pedidoIds as $pid) {
            $stIns->execute([$janelaId, (int) $pid]);
        }
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
        if ($pend === 0) {
            $stUp = $this->connection->prepare("UPDATE remessa_janelas SET status = 'remessa_gerada', closed_at = COALESCE(closed_at, NOW()), updated_at = NOW() WHERE id = ?");
            $stUp->execute([$janelaId]);
        }
    }

    public function index($request) {
        $this->requireAdmin();
        $errorMsg = null;
        try {
            $janelaAtual = $this->ensureJanelaAtual();

            $janelasAbertas = $this->getJanelasByStatus(['aberta']);
            $janelasFinalizadas = $this->getJanelasByStatus(['finalizada']);
            $janelasAtraso = $this->getJanelasByStatus(['atraso']);
            $janelasGeradas = $this->getJanelasByStatus(['remessa_gerada']);

            // Mantido por compatibilidade com blocos antigos no HTML (até remover/refatorar)
            $pedidosPendentes = [];
            $pedidosAtraso = [];
            $remessasGeradas = [];

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
    <title>Remessa Internacional - Braziliana Shop Admin</title>
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
                    <h1 class="h2"><i class="fas fa-globe-americas me-2"></i>Remessa Internacional</h1>
                    <div>
                        <button type="button" class="btn btn-info" onclick="location.reload()">
                            <i class="fas fa-sync me-1"></i>Atualizar
                        </button>
                    </div>
                </div>';

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
                                    echo '<div class="col-md-4 mb-3">
                                        <div class="card janela-card">
                                            <div class="card-body">
                                                <h6 class="card-title">Janela #' . $janela['id'] . '</h6>
                                                <p class="card-text">
                                                    <small class="text-muted">
                                                        <i class="fas fa-calendar"></i> ' . date('d/m/Y', strtotime($janela['data_inicio'])) . ' a ' . date('d/m/Y', strtotime($janela['data_fim'])) . '
                                                    </small><br>
                                                    <span class="badge bg-' . $statusClass . '">' . ucfirst($janela['status']) . '</span>
                                                </p>
                                                <button class="btn btn-sm btn-outline-primary" onclick="verJanela(' . $janela['id'] . ')">
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
                                    echo '<div class="col-md-4 mb-3">
                                        <div class="card janela-card">
                                            <div class="card-body">
                                                <h6 class="card-title">Janela #' . $janela['id'] . '</h6>
                                                <p class="card-text">
                                                    <small class="text-muted">
                                                        <i class="fas fa-calendar"></i> ' . date('d/m/Y', strtotime($janela['data_inicio'])) . ' a ' . date('d/m/Y', strtotime($janela['data_fim'])) . '
                                                    </small><br>
                                                    <span class="badge bg-secondary">Finalizada</span>
                                                </p>
                                                <button class="btn btn-sm btn-outline-primary" onclick="verJanela(' . $janela['id'] . ')">
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
                                    echo '<div class="col-md-4 mb-3">
                                        <div class="card janela-card">
                                            <div class="card-body">
                                                <h6 class="card-title">Janela #' . $janela['id'] . '</h6>
                                                <p class="card-text">
                                                    <small class="text-muted">
                                                        <i class="fas fa-calendar"></i> ' . date('d/m/Y', strtotime($janela['data_inicio'])) . ' a ' . date('d/m/Y', strtotime($janela['data_fim'])) . '
                                                    </small><br>
                                                    <span class="badge bg-danger">Atraso</span>
                                                </p>
                                                <button class="btn btn-sm btn-outline-primary" onclick="verJanela(' . $janela['id'] . ')">
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
                                            echo '<tr>
                                                <td><strong>#' . str_pad($remessa['id'], 6, '0', STR_PAD_LEFT) . '</strong></td>
                                                <td>#' . str_pad($remessa['pedido_id'], 6, '0', STR_PAD_LEFT) . '</td>
                                                <td>' . htmlspecialchars($remessa['cliente_nome'] ?? 'N/A') . '</td>
                                                <td>' . date('d/m/Y H:i', strtotime($remessa['created_at'])) . '</td>
                                                <td><span class="badge bg-info">Remessa Gerada</span></td>
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

    private function getJanelasByStatus(array $statuses): array {
        $statuses = array_values(array_filter(array_map('strval', $statuses)));
        if (!$statuses) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
        $sql = "SELECT id, data_inicio, data_fim, status, closed_at, created_at, updated_at FROM remessa_janelas WHERE status IN ({$placeholders}) ORDER BY data_inicio DESC LIMIT 50";
        $st = $this->connection->prepare($sql);
        $st->execute($statuses);
        return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
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
                p.created_at,
                p.total,
                p.status,
                u.nome AS cliente_nome,
                u.email AS cliente_email
            FROM remessa_janela_pedidos rjp
            LEFT JOIN pedidos p ON p.id = rjp.pedido_id
            LEFT JOIN usuarios u ON u.id = p.usuario_id
            WHERE rjp.janela_id = ?
            ORDER BY p.created_at ASC
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
                                    <th>Data</th>
                                    <th>Total</th>
                                    <th>Etiqueta</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>';

        if (!$pedidos) {
            echo '<tr><td colspan="6" class="text-center text-muted">Nenhum pedido nesta janela.</td></tr>';
        } else {
            foreach ($pedidos as $p) {
                $pid = (int) ($p['pedido_id'] ?? 0);
                $et = (int) ($p['etiqueta_gerada'] ?? 0);
                $etBadge = $et === 1 ? 'success' : 'warning';
                $etLabel = $et === 1 ? 'Gerada' : 'Pendente';
                $wxStatus = (string) ($p['wexpress_status'] ?? '');
                $wxShipId = (string) ($p['wexpress_shipping_id'] ?? '');
                $wxCourier = (string) ($p['courier_tracking_number'] ?? '');
                $dt = !empty($p['created_at']) ? date('d/m/Y H:i', strtotime((string) $p['created_at'])) : '-';
                $totalV = is_numeric($p['total'] ?? null) ? number_format((float) $p['total'], 2, ',', '.') : '-';

                echo '<tr>
                    <td><strong>#' . str_pad((string) $pid, 6, '0', STR_PAD_LEFT) . '</strong></td>
                    <td>' . htmlspecialchars((string) ($p['cliente_nome'] ?? 'N/A')) . '<br><small class="text-muted">' . htmlspecialchars((string) ($p['cliente_email'] ?? '')) . '</small></td>
                    <td>' . $dt . '</td>
                    <td>R$ ' . $totalV . '</td>
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

        $stChk = $this->connection->prepare('SELECT etiqueta_gerada, etiqueta_gerada_em FROM remessa_janela_pedidos WHERE janela_id = ? AND pedido_id = ? LIMIT 1');
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

        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <div>
                    <h1 class="h3 mb-0">Pedido #' . str_pad((string) $pid, 6, '0', STR_PAD_LEFT) . '</h1>
                    <div class="text-muted small">Janela #' . (int) $jid . ' | Etiqueta: <span class="badge bg-' . $etBadge . '">' . $etLabel . '</span></div>
                </div>
                <div class="d-flex gap-2">
                    <a class="btn btn-outline-secondary" href="/admin/remessa-internacional/janela/' . (int) $jid . '"><i class="fas fa-arrow-left"></i> Voltar</a>
                    <button class="btn btn-success" type="button" onclick="gerarEtiqueta()"><i class="fas fa-tag"></i> Gerar etiqueta</button>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><strong>W-Express</strong></div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Finalidade do envio (shipment_purpose)</label>
                            <select class="form-select" id="wexpress_shipment_purpose">
                                <option value="personal" ' . ($wexpressPurpose === 'personal' || $wexpressPurpose === '' ? 'selected' : '') . '>personal</option>
                                <option value="commercial" ' . ($wexpressPurpose === 'commercial' ? 'selected' : '') . '>commercial (revenda)</option>
                            </select>
                            <div class="form-text">CPF: personal | CNPJ revenda: commercial | CNPJ não revenda: personal</div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Invoice number (obrigatório p/ commercial)</label>
                            <input class="form-control" id="wexpress_invoice_number" value="' . htmlspecialchars($wexpressInvoice, ENT_QUOTES, 'UTF-8') . '" placeholder="12345" />
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tariff code (NCM)</label>
                            <div class="form-text">Em commercial, o NCM vira obrigatório por item (tentaremos usar o NCM do produto).</div>
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
                            <div><strong>Total:</strong> R$ ' . (is_numeric($pedido['total'] ?? null) ? number_format((float) $pedido['total'], 2, ',', '.') : '-') . '</div>
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
        } else {
            echo '<span class="text-muted">Não encontrado</span>';
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
                echo '<tr>
                    <td>' . htmlspecialchars((string) ($it['produto_nome'] ?? '')) . '</td>
                    <td>' . htmlspecialchars((string) ($it['sku'] ?? '')) . '</td>
                    <td>' . (int) ($it['quantidade'] ?? 0) . '</td>
                    <td>R$ ' . (is_numeric($it['preco'] ?? null) ? number_format((float) $it['preco'], 2, ',', '.') : '-') . '</td>
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
    const purposeEl = document.getElementById("wexpress_shipment_purpose");
    const invEl = document.getElementById("wexpress_invoice_number");
    const payload = {
        wexpress_shipment_purpose: purposeEl ? purposeEl.value : "",
        wexpress_invoice_number: invEl ? invEl.value : "",
    };
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

            $pedido = $this->getPedidoCompleto($pid);
            if (!$pedido) {
                echo json_encode(['success' => false, 'error' => 'Pedido não encontrado']);
                exit;
            }

            $svc = new WExpressService();

            $raw = file_get_contents('php://input');
            $body = json_decode((string) $raw, true);
            $purpose = '';
            $invoiceNumber = '';
            if (is_array($body)) {
                $purpose = (string) ($body['wexpress_shipment_purpose'] ?? '');
                $invoiceNumber = (string) ($body['wexpress_invoice_number'] ?? '');
            }

            // Persistir preferências no vínculo janela/pedido (se colunas existirem)
            try {
                $this->connection->query('SELECT wexpress_shipment_purpose, wexpress_invoice_number FROM remessa_janela_pedidos LIMIT 1');
                $stSave = $this->connection->prepare('UPDATE remessa_janela_pedidos SET wexpress_shipment_purpose = ?, wexpress_invoice_number = ? WHERE janela_id = ? AND pedido_id = ?');
                $stSave->execute([
                    $purpose !== '' ? $purpose : null,
                    $invoiceNumber !== '' ? $invoiceNumber : null,
                    $jid,
                    $pid,
                ]);
            } catch (\Exception $e) {
            }

            $payload = $this->buildWExpressShippingPayload($svc, $pedido, $jid, $purpose, $invoiceNumber);

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
                    $pedidoModel->atualizarStatus((int) $pid, 'em_transporte', 'Etiqueta internacional gerada (W-Express)', $_SESSION['usuario_id'] ?? null);
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

    private function buildWExpressShippingPayload(WExpressService $svc, array $pedido, int $janelaId, string $shipmentPurposeOverride = '', string $invoiceNumberOverride = ''): array {
        $sender = $svc->getSender();
        if (!is_array($sender) || empty($sender)) {
            throw new \Exception('W-Express: configure o Sender (JSON) em /admin/configuracoes > Entrega');
        }

        $nome = trim((string) ($pedido['cliente_nome'] ?? ''));
        $partes = preg_split('/\s+/', $nome) ?: [];
        $firstName = $partes[0] ?? $nome;
        $lastName = count($partes) > 1 ? implode(' ', array_slice($partes, 1)) : '';

        $doc = (string) ($pedido['documento'] ?? ($pedido['cliente_documento'] ?? ''));
        $docDigits = preg_replace('/\D+/', '', $doc);
        $taxType = strlen((string) $docDigits) > 11 ? 'CNPJ' : 'CPF';
        $taxId = (string) $docDigits;

        $recipientType = ($taxType === 'CPF') ? 'individual' : 'business';

        $shipmentPurpose = strtolower(trim($shipmentPurposeOverride));
        if ($shipmentPurpose !== 'commercial' && $shipmentPurpose !== 'personal') {
            $shipmentPurpose = 'personal';
        }

        $invoiceNumber = trim((string) $invoiceNumberOverride);
        if ($shipmentPurpose === 'commercial' && $invoiceNumber === '') {
            throw new \Exception('W-Express: invoice_number é obrigatório quando shipment_purpose = commercial');
        }

        $end = $pedido['endereco'] ?? [];
        $cep = preg_replace('/\D+/', '', (string) ($end['cep'] ?? ''));
        $addr1 = trim((string) ($end['endereco'] ?? ($end['logradouro'] ?? '')));
        $addr2Parts = [];
        $compl = trim((string) ($end['complemento'] ?? ''));
        $bairro = trim((string) ($end['bairro'] ?? ''));
        if ($compl !== '') $addr2Parts[] = $compl;
        if ($bairro !== '') $addr2Parts[] = $bairro;
        $addr2 = trim(implode(', ', $addr2Parts));
        $numero = trim((string) ($end['numero'] ?? ''));
        $cidade = (string) ($end['cidade'] ?? '');
        $estado = (string) ($end['estado'] ?? '');

        $itens = $pedido['itens'] ?? [];
        $items = [];
        if (is_array($itens)) {
            foreach ($itens as $it) {
                $qtd = (int) ($it['quantidade'] ?? 1);
                if ($qtd <= 0) $qtd = 1;
                $row = [
                    'description' => (string) ($it['produto_nome'] ?? ($it['nome_produto'] ?? 'item')),
                    'quantity' => $qtd,
                    'unit_value' => is_numeric($it['preco'] ?? null) ? (float) $it['preco'] : 1,
                ];

                // Em commercial (revenda) o tariff_code (NCM) é obrigatório
                if ($shipmentPurpose === 'commercial') {
                    $ncm = (string) ($it['ncm'] ?? ($it['tariff_code'] ?? ''));
                    $ncmDigits = preg_replace('/\D+/', '', $ncm);
                    if ($ncmDigits === '') {
                        throw new \Exception('W-Express: items.tariff_code (NCM) é obrigatório quando shipment_purpose = commercial');
                    }
                    $row['tariff_code'] = (int) $ncmDigits;
                }

                $items[] = $row;
            }
        }

        $pesoTotal = 1.0;
        if (is_numeric($pedido['peso_total'] ?? null)) {
            $pesoTotal = max(0.001, (float) $pedido['peso_total']);
        }

        $packages = [[
            'weight' => round($pesoTotal * 1000, 2),
            'width' => 10,
            'length' => 10,
            'height' => 10,
        ]];

        $declared = 0.0;
        if (is_numeric($pedido['total'] ?? null)) {
            $declared = (float) $pedido['total'];
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
            'declared_value' => $declared,
            'freight_value' => 0,
            'insurance_value' => 0,
            'invoice_number' => ($shipmentPurpose === 'commercial' ? $invoiceNumber : null),
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
                echo json_encode(['success' => false, 'error' => 'Ainda existem pedidos sem etiqueta nesta janela']);
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
        $stmt = $this->connection->prepare("
            SELECT r.*, p.usuario_id, u.nome as cliente_nome 
            FROM remessas_internacionais r 
            LEFT JOIN pedidos p ON r.pedido_id = p.id 
            LEFT JOIN usuarios u ON p.usuario_id = u.id 
            WHERE r.status = 'remessa_gerada' 
            ORDER BY r.created_at DESC 
            LIMIT 50
        ");
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
        $temNcm = is_array($produtoCols) && in_array('ncm', $produtoCols, true);
        $selNcm = $temNcm ? ', pr.ncm as ncm' : '';

        $stmt = $this->connection->prepare("
            SELECT pi.*, pr.{$produtoNomeCol} as produto_nome, pr.sku{$selNcm}
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
