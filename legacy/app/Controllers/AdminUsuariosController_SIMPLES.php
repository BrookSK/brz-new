<?php
namespace App\Controllers;

use App\Core\Request;

class AdminUsuariosController extends Controller {
    
    public function index(Request $request) {
        try {
            $pdo = new \PDO('mysql:host=127.0.0.1;dbname=novobr', 'novobr', '33537095Ab12$');
            
            // Verificar se a tabela usuarios existe
            $stmtCheck = $pdo->prepare("SHOW TABLES LIKE 'usuarios'");
            $stmtCheck->execute();
            $tableExists = $stmtCheck->rowCount() > 0;
            
            if (!$tableExists) {
                // Criar tabela usuarios se não existir
                $this->criarTabelaUsuarios($pdo);
            }
            
            // Verificar se a tabela carteiras existe
            $stmtCheck = $pdo->prepare("SHOW TABLES LIKE 'carteiras'");
            $stmtCheck->execute();
            $carteirasExists = $stmtCheck->rowCount() > 0;
            
            if (!$carteirasExists) {
                $this->criarTabelaCarteiras($pdo);
            }
            
            $pagina = $request->getParam('pagina', 1);
            $limite = 12;
            $offset = ($pagina - 1) * $limite;
            $busca = $request->getParam('busca', '');
            
            $sql = "SELECT u.*, 
                           COALESCE(w.saldo_usd, 0) as carteira_usd,
                           COALESCE(w.saldo_brl, 0) as carteira_brl
                    FROM usuarios u 
                    LEFT JOIN carteiras w ON u.id = w.usuario_id 
                    WHERE 1=1";
            $params = [];
            
            if (!empty($busca)) {
                $sql .= " AND (u.nome LIKE :busca OR u.email LIKE :busca OR u.cpf LIKE :busca)";
                $params[':busca'] = "%{$busca}%";
            }
            
            $sql .= " ORDER BY u.created_at DESC LIMIT :limite OFFSET :offset";
            
            $stmt = $pdo->prepare($sql);
            foreach ($params as $key => $value) $stmt->bindValue($key, $value);
            $stmt->bindValue(':limite', $limite, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
            $stmt->execute();
            $usuarios = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            $stmtTotal = $pdo->prepare("SELECT COUNT(*) as total FROM usuarios u" . (!empty($busca) ? " WHERE (u.nome LIKE :busca OR u.email LIKE :busca OR u.cpf LIKE :busca)" : ""));
            if (!empty($busca)) $stmtTotal->bindValue(':busca', "%{$busca}%");
            $stmtTotal->execute();
            $total = $stmtTotal->fetch(\PDO::FETCH_ASSOC)['total'];
            $totalPaginas = ceil($total / $limite);
            
            // Buscar estatísticas para cada usuário
            foreach ($usuarios as &$usuario) {
                $stmtPedidos = $pdo->prepare("SELECT COUNT(*) as total, COALESCE(SUM(total), 0) as valor FROM pedidos WHERE usuario_id = :usuario_id");
                $stmtPedidos->bindParam(':usuario_id', $usuario['id']);
                $stmtPedidos->execute();
                $pedidosStats = $stmtPedidos->fetch(\PDO::FETCH_ASSOC);
                $usuario['total_pedidos'] = $pedidosStats['total'] ?: 0;
                $usuario['total_gasto'] = $pedidosStats['valor'] ?: 0;
                
                // Garantir que carteira exista
                if ($usuario['carteira_usd'] === null) {
                    $this->criarCarteiraUsuario($pdo, $usuario['id']);
                    $usuario['carteira_usd'] = 0;
                    $usuario['carteira_brl'] = 0;
                }
            }
            
            // Estatísticas gerais
            $stmtStats = $pdo->prepare("SELECT COUNT(*) as total, SUM(COALESCE(w.saldo_usd, 0)) as total_carteira_usd FROM usuarios u LEFT JOIN carteiras w ON u.id = w.usuario_id");
            $stmtStats->execute();
            $stats = $stmtStats->fetch(\PDO::FETCH_ASSOC);
            
        } catch (\Exception $e) {
            $usuarios = [];
            $total = 0;
            $totalPaginas = 0;
            $stats = ['total' => 0, 'total_carteira_usd' => 0];
            $erro = $e->getMessage();
        }
        
        // Incluir o partial do menu lateral
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuários - BRZ Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';
        
        // Renderizar estilos do menu
        renderAdminSidebarStyles();
        
        echo '<style>
        .user-card { transition: transform 0.2s; border-left: 4px solid #4e73df; }
        .user-card:hover { transform: translateY(-5px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
        .user-avatar { width: 60px; height: 60px; border-radius: 50%; object-fit: cover; }
        .carteira-badge { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .stats-card { transition: all 0.3s; }
        .stats-card:hover { transform: scale(1.05); }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">';
        
        // Renderizar menu lateral usando o partial
        renderAdminSidebar('usuarios');
        
        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-users me-2"></i>Usuários (' . $total . ')</h1>
                    <div>
                        <button type="button" class="btn btn-success me-2" onclick="adicionarCreditoEmLote()">
                            <i class="fas fa-dollar-sign me-1"></i>Adicionar Créditos em Lote
                        </button>
                        <a href="/admin/usuarios/novo" class="btn btn-primary">
                            <i class="fas fa-plus me-1"></i>Novo Usuário
                        </a>
                    </div>
                </div>';
                
        if (isset($erro)) {
            echo '<div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <strong>Erro:</strong> ' . htmlspecialchars($erro) . '
            </div>';
        }
                
        // Estatísticas gerais
        echo '<div class="row mb-4">
            <div class="col-md-3">
                <div class="card stats-card bg-primary text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total Usuários</h5>
                        <h3>' . ($stats['total'] ?? 0) . '</h3>
                        <small>Cadastrados</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card carteira-badge text-white">
                    <div class="card-body">
                        <h5 class="card-title">Total em Carteiras</h5>
                        <h3>$ ' . number_format($stats['total_carteira_usd'] ?? 0, 2, '.', ',') . '</h3>
                        <small>Em USD</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card bg-success text-white">
                    <div class="card-body">
                        <h5 class="card-title">Usuários Ativos</h5>
                        <h3>' . $this->getUsuariosAtivos($pdo) . '</h3>
                        <small>Online recentemente</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card bg-info text-white">
                    <div class="card-body">
                        <h5 class="card-title">Novos Hoje</h5>
                        <h3>' . $this->getUsuariosHoje($pdo) . '</h3>
                        <small>Registros</small>
                    </div>
                </div>
            </div>
        </div>';
                
        echo '<form method="GET" class="row g-3 mb-4">
                    <div class="col-md-8">
                        <input type="text" class="form-control" name="busca" placeholder="Buscar usuário por nome, email ou CPF..." value="' . htmlspecialchars($busca) . '">
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-outline-primary me-2">
                            <i class="fas fa-search me-1"></i>Buscar
                        </button>
                        <a href="/admin/usuarios" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-1"></i>Limpar
                        </a>
                    </div>
                </form>
                
                <div class="row">';
                
                if (empty($usuarios)) {
                    echo '<div class="col-12 text-center py-5">
                        <i class="fas fa-users fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">Nenhum usuário encontrado</h5>
                        <p class="text-muted">Tente ajustar sua busca ou cadastre um novo usuário.</p>
                    </div>';
                }
                
                foreach ($usuarios as $usuario) {
                    echo '<div class="col-md-6 col-lg-4 mb-4">
                        <div class="card user-card h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-3">
                                    <img src="https://ui-avatars.com/api/?name=' . urlencode($usuario['nome']) . '&background=4e73df&color=fff&size=60" class="user-avatar me-3" alt="Avatar">
                                    <div>
                                        <h6 class="card-title mb-1">' . htmlspecialchars($usuario['nome']) . '</h6>
                                        <p class="text-muted small mb-0">' . htmlspecialchars($usuario['email']) . '</p>
                                    </div>
                                </div>
                                <div class="row text-center mb-3">
                                    <div class="col-4">
                                        <small class="text-muted d-block">Pedidos</small>
                                        <strong>' . $usuario['total_pedidos'] . '</strong>
                                    </div>
                                    <div class="col-4">
                                        <small class="text-muted d-block">Carteira</small>
                                        <strong class="text-success">$' . number_format($usuario['carteira_usd'], 2, '.', ',') . '</strong>
                                    </div>
                                    <div class="col-4">
                                        <small class="text-muted d-block">Status</small>
                                        <span class="badge ' . ($usuario['ativo'] ? 'bg-success' : 'bg-danger') . '">' . ($usuario['ativo'] ? 'Ativo' : 'Inativo') . '</span>
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <a href="/admin/usuarios/detalhes/' . $usuario['id'] . '" class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-eye me-1"></i>Ver
                                    </a>
                                    <div>
                                        <button type="button" class="btn btn-sm btn-success" onclick="adicionarCredito(' . $usuario['id'] . ', \'' . htmlspecialchars($usuario['nome']) . '\')">
                                            <i class="fas fa-dollar-sign"></i>
                                        </button>
                                        <a href="/admin/usuarios/editar/' . $usuario['id'] . '" class="btn btn-sm btn-outline-warning">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <form method="POST" action="/admin/usuarios/excluir/' . $usuario['id'] . '" style="display: inline;">
                                            <button type="submit" onclick="return confirm(\'Tem certeza que deseja excluir este usuário?\')" class="btn btn-sm btn-outline-danger">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>';
                }
                
                echo '</div>';
                
                // Paginação
                if ($totalPaginas > 1) {
                    echo '<nav class="mt-4"><ul class="pagination justify-content-center">';
                    for ($i = 1; $i <= $totalPaginas; $i++) {
                        $url = "/admin/usuarios?pagina={$i}" . (!empty($busca) ? "&busca=" . urlencode($busca) : "");
                        echo '<li class="page-item ' . ($i == $pagina ? 'active' : '') . '">
                            <a class="page-link" href="' . $url . '">' . $i . '</a>
                        </li>';
                    }
                    echo '</ul></nav>';
                }
                
                echo '</main></div></div>';

    // Renderizar scripts
    renderAdminScripts();
    
    echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function adicionarCredito(usuarioId, nomeUsuario) {
            const valor = prompt("Digite o valor em USD para adicionar à carteira de " + nomeUsuario + ":");
            if (valor && !isNaN(valor) && parseFloat(valor) > 0) {
                fetch("/admin/usuarios/adicionar-credito", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify({
                        usuario_id: usuarioId,
                        valor: parseFloat(valor)
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert("Crédito adicionado com sucesso! $" + valor + " USD");
                        location.reload();
                    } else {
                        alert("Erro ao adicionar crédito: " + data.message);
                    }
                })
                .catch(error => {
                    console.error("Error:", error);
                    alert("Erro ao adicionar crédito");
                });
            }
        }

        function adicionarCreditoEmLote() {
            alert("Funcionalidade em desenvolvimento");
        }
    </script>
</body>
</html>';
        exit;
    }

    private function criarTabelaUsuarios($pdo) {
        $sql = "
        CREATE TABLE IF NOT EXISTS `usuarios` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `nome` varchar(255) NOT NULL,
            `email` varchar(255) NOT NULL,
            `senha` varchar(255) NOT NULL,
            `cpf` varchar(14) DEFAULT NULL,
            `telefone` varchar(20) DEFAULT NULL,
            `ativo` tinyint(1) DEFAULT 1,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_email` (`email`),
            KEY `idx_ativo` (`ativo`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        $pdo->exec($sql);
    }

    private function criarTabelaCarteiras($pdo) {
        $sql = "
        CREATE TABLE IF NOT EXISTS `carteiras` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `usuario_id` int(11) NOT NULL,
            `saldo_usd` decimal(10,2) DEFAULT 0.00,
            `saldo_brl` decimal(10,2) DEFAULT 0.00,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uk_usuario_id` (`usuario_id`),
            KEY `idx_saldo_usd` (`saldo_usd`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ";
        $pdo->exec($sql);
    }

    private function criarCarteiraUsuario($pdo, $usuarioId) {
        $stmt = $pdo->prepare("INSERT IGNORE INTO carteiras (usuario_id, saldo_usd, saldo_brl) VALUES (?, 0, 0)");
        $stmt->execute([$usuarioId]);
    }

    private function getUsuariosAtivos($pdo) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM usuarios WHERE ativo = 1 AND updated_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
        $stmt->execute();
        return $stmt->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0;
    }

    private function getUsuariosHoje($pdo) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM usuarios WHERE DATE(created_at) = CURDATE()");
        $stmt->execute();
        return $stmt->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0;
    }

    public function adicionarCredito(Request $request) {
        $data = json_decode(file_get_contents('php://input'), true);
        $usuarioId = $data['usuario_id'] ?? 0;
        $valor = $data['valor'] ?? 0;
        
        try {
            $pdo = new \PDO('mysql:host=127.0.0.1;dbname=novobr', 'novobr', '33537095Ab12$');
            $pdo->beginTransaction();
            
            // Verificar se usuário existe
            $stmt = $pdo->prepare("SELECT id FROM usuarios WHERE id = ?");
            $stmt->execute([$usuarioId]);
            if (!$stmt->fetch()) {
                throw new \Exception('Usuário não encontrado');
            }
            
            // Garantir carteira existe
            $this->criarCarteiraUsuario($pdo, $usuarioId);
            
            // Adicionar crédito (sempre em USD)
            $stmt = $pdo->prepare("UPDATE carteiras SET saldo_usd = saldo_usd + ?, updated_at = NOW() WHERE usuario_id = ?");
            $stmt->execute([$valor, $usuarioId]);
            
            $pdo->commit();
            
            echo json_encode(['success' => true, 'message' => 'Crédito adicionado com sucesso']);
            
        } catch (\Exception $e) {
            $pdo->rollBack();
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
        exit;
    }
}
