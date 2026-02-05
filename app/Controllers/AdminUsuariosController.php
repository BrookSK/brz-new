<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;

class AdminUsuariosController extends Controller {
    
    public function index(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'suporte', 'vendedor']);
        try {
            $helper = new \App\Controllers\AdminUsuariosHelper();
            
            $pagina = $request->getParam('pagina', 1);
            $limite = 12;
            $offset = ($pagina - 1) * $limite;
            $busca = $request->getParam('busca', '');
            
            $usuarios = $helper->getUsuariosComCarteira($busca, $limite, $offset);
            $total = $helper->getTotalUsuarios($busca);
            $totalPaginas = ceil($total / $limite);
            $stats = $helper->getStatsUsuarios();
            
        } catch (\Exception $e) {
            $usuarios = [];
            $total = 0;
            $totalPaginas = 0;
            $stats = [];
            $erro = $e->getMessage();
        }
        
        // Incluir o partial do menu lateral
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuários - Braziliana Shop Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';
        
        // Renderizar estilos do menu
        renderAdminSidebarStyles();
        
        // Adicionar estilos dos usuários
        echo \App\Controllers\AdminUsuariosViews::getStyles();
        
        echo '</head>
<body>
    <div class="container-fluid">
        <div class="row">';
        
        // Renderizar menu lateral usando o partial
        renderAdminSidebar('usuarios');
        
        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-users me-2"></i>Usuários (' . $total . ')</h1>
                    <div>
                        <button type="button" class="btn btn-success me-2" onclick="adicionarCreditosEmLote()">
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
                
        // Renderizar cards de estatísticas
        echo \App\Controllers\AdminUsuariosViews::renderStatsCards($stats);
                
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
                    echo \App\Controllers\AdminUsuariosViews::renderCardUsuario($usuario);
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
    
    // Adicionar scripts dos usuários
    echo \App\Controllers\AdminUsuariosViews::getScripts();
    
    // Adicionar modais
    echo \App\Controllers\AdminUsuariosViews::renderModalAdicionarCredito();
    echo \App\Controllers\AdminUsuariosViews::renderModalConverterMoeda();
    echo \App\Controllers\AdminUsuariosViews::renderModalCreditosLote();
    
    echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Armazenar dados dos usuários para uso nos scripts
        usuariosData = ' . json_encode(array_values($usuarios)) . ';
    </script>
</body>
</html>';
        exit;
    }

    public function novo(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'suporte', 'vendedor']);
        // Incluir o partial do menu lateral
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novo Usuário - Braziliana Shop Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';

        renderAdminSidebarStyles();

        echo '</head>
<body>
    <div class="container-fluid">
        <div class="row">';

        renderAdminSidebar('usuarios');

        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-user-plus me-2"></i>Novo Usuário</h1>
                <a href="/admin/usuarios" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i>Voltar</a>
            </div>

            <div class="card">
                <div class="card-body">
                    <form method="POST" action="/admin/usuarios/salvar">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Nome</label>
                                <input type="text" class="form-control" name="nome" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">E-mail</label>
                                <input type="email" class="form-control" name="email" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">CPF</label>
                                <input type="text" class="form-control" name="cpf">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Telefone</label>
                                <input type="text" class="form-control" name="telefone">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Senha</label>
                                <input type="password" class="form-control" name="senha" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="ativo">
                                    <option value="1" selected>Ativo</option>
                                    <option value="0">Inativo</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Perfil</label>
                                <select class="form-select" name="perfil" required>
                                    <option value="cliente" selected>Cliente</option>
                                    <option value="admin">Administrador</option>
                                    <option value="vendedor">Vendedor</option>
                                    <option value="suporte">Suporte</option>
                                    <option value="representante">Representante</option>
                                    <option value="redirecionador">Redirecionador</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>Salvar
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </main>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>';
        exit;
    }

    public function editar(Request $request, $id = null) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'suporte', 'vendedor']);
        $id = $id ?? $request->getParam('id');

        try {
            $helper = new \App\Controllers\AdminUsuariosHelper();
            $usuario = $helper->getUsuarioComCarteira($id);

            if (!$usuario) {
                echo '<div class="alert alert-danger">Usuário não encontrado</div>';
                echo '<a href="/admin/usuarios" class="btn btn-secondary">Voltar</a>';
                exit;
            }
        } catch (\Exception $e) {
            echo '<div class="alert alert-danger">Erro: ' . $e->getMessage() . '</div>';
            echo '<a href="/admin/usuarios" class="btn btn-secondary">Voltar</a>';
            exit;
        }

        // Incluir o partial do menu lateral
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Usuário - Braziliana Shop Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';

        renderAdminSidebarStyles();

        echo '</head>
<body>
    <div class="container-fluid">
        <div class="row">';

        renderAdminSidebar('usuarios');

        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><i class="fas fa-user-edit me-2"></i>Editar Usuário</h1>
                <a href="/admin/usuarios/detalhes/' . (int)$usuario['id'] . '" class="btn btn-secondary"><i class="fas fa-arrow-left me-1"></i>Voltar</a>
            </div>

            <div class="card">
                <div class="card-body">
                    <form method="POST" action="/admin/usuarios/salvar">
                        <input type="hidden" name="id" value="' . (int)$usuario['id'] . '">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Nome</label>
                                <input type="text" class="form-control" name="nome" value="' . htmlspecialchars($usuario['nome'] ?? '') . '" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">E-mail</label>
                                <input type="email" class="form-control" name="email" value="' . htmlspecialchars($usuario['email'] ?? '') . '" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">CPF</label>
                                <input type="text" class="form-control" name="cpf" value="' . htmlspecialchars($usuario['cpf'] ?? '') . '">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Telefone</label>
                                <input type="text" class="form-control" name="telefone" value="' . htmlspecialchars($usuario['telefone'] ?? '') . '">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Nova Senha (opcional)</label>
                                <input type="password" class="form-control" name="senha" placeholder="Deixe em branco para manter">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="ativo">
                                    <option value="1" ' . ((int)($usuario['ativo'] ?? 1) === 1 ? 'selected' : '') . '>Ativo</option>
                                    <option value="0" ' . ((int)($usuario['ativo'] ?? 1) === 0 ? 'selected' : '') . '>Inativo</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Perfil</label>
                                <select class="form-select" name="perfil" required>
                                    <option value="cliente" ' . ((($usuario['perfil'] ?? 'cliente') === 'cliente') ? 'selected' : '') . '>Cliente</option>
                                    <option value="admin" ' . ((($usuario['perfil'] ?? '') === 'admin') ? 'selected' : '') . '>Administrador</option>
                                    <option value="vendedor" ' . ((($usuario['perfil'] ?? '') === 'vendedor') ? 'selected' : '') . '>Vendedor</option>
                                    <option value="suporte" ' . ((($usuario['perfil'] ?? '') === 'suporte') ? 'selected' : '') . '>Suporte</option>
                                    <option value="representante" ' . ((($usuario['perfil'] ?? '') === 'representante') ? 'selected' : '') . '>Representante</option>
                                    <option value="redirecionador" ' . ((($usuario['perfil'] ?? '') === 'redirecionador') ? 'selected' : '') . '>Redirecionador</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i>Salvar Alterações
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </main>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>';
        exit;
    }

    public function salvar(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'suporte', 'vendedor']);
        try {
            $helper = new \App\Controllers\AdminUsuariosHelper();

            $id = $request->getParam('id');
            $dados = [
                'nome' => $request->getParam('nome'),
                'email' => $request->getParam('email'),
                'cpf' => $request->getParam('cpf'),
                'telefone' => $request->getParam('telefone'),
                'ativo' => $request->getParam('ativo', 1),
                'senha' => $request->getParam('senha'),
                'perfil' => $request->getParam('perfil', 'cliente')
            ];

            if (!empty($id)) {
                $helper->atualizarUsuario($id, $dados);
                header('Location: /admin/usuarios/detalhes/' . (int)$id . '?success=1');
                exit;
            }

            $novoId = $helper->criarUsuario($dados);
            header('Location: /admin/usuarios/detalhes/' . (int)$novoId . '?success=1');
            exit;
        } catch (\Exception $e) {
            echo '<div class="alert alert-danger">Erro ao salvar usuário: ' . $e->getMessage() . '</div>';
            echo '<a href="/admin/usuarios" class="btn btn-secondary">Voltar</a>';
            exit;
        }
    }

    public function detalhes(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'suporte', 'vendedor']);
        $id = $request->getParam('id');
        
        try {
            $helper = new \App\Controllers\AdminUsuariosHelper();
            $usuario = $helper->getUsuarioComCarteira($id);
            
            if (!$usuario) {
                echo '<div class="alert alert-danger">Usuário não encontrado</div>';
                echo '<a href="/admin/usuarios" class="btn btn-secondary">Voltar</a>';
                exit;
            }
            
            $pedidos = $helper->getPedidosUsuario($id);

            $totalPedidos = is_array($pedidos) ? count($pedidos) : 0;
            $totalGasto = 0;
            if (!empty($pedidos) && is_array($pedidos)) {
                foreach ($pedidos as $p) {
                    $totalGasto += (float)($p['total'] ?? 0);
                }
            }

            $stats = [
                'total_pedidos' => $totalPedidos,
                'total_gasto' => $totalGasto,
                'ultimo_pedido' => (!empty($pedidos[0]['created_at']) ? $pedidos[0]['created_at'] : null)
            ];
            
        } catch (\Exception $e) {
            echo '<div class="alert alert-danger">Erro: ' . $e->getMessage() . '</div>';
            echo '<a href="/admin/usuarios" class="btn btn-secondary">Voltar</a>';
            exit;
        }

        // Incluir o partial do menu lateral
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($usuario['nome']) . ' - Braziliana Shop Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';

        // Renderizar estilos do menu
        renderAdminSidebarStyles();

        echo '<style>
        .user-avatar { width: 120px; height: 120px; border-radius: 50%; object-fit: cover; }
        </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">';

        // Renderizar menu lateral usando o partial
        renderAdminSidebar('usuarios');

        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">' . htmlspecialchars($usuario['nome']) . '</h1>
                    <a href="/admin/usuarios" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
                </div>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="card mb-4">
                            <div class="card-body text-center">
                                <img src="https://ui-avatars.com/api/?name=' . urlencode($usuario['nome']) . '&background=4e73df&color=fff&size=120" class="user-avatar mb-3" alt="Avatar">
                                <h5>' . htmlspecialchars($usuario['nome']) . '</h5>
                                <p class="text-muted">' . htmlspecialchars($usuario['email']) . '</p>
                                <span class="badge ' . ($usuario['ativo'] ? 'bg-success' : 'bg-danger') . '">' . ($usuario['ativo'] ? 'Ativo' : 'Inativo') . '</span>
                            </div>
                        </div>
                        
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Estatísticas</h5>
                            </div>
                            <div class="card-body">
                                <div class="text-center mb-3">
                                    <h3 class="text-primary">' . $stats['total_pedidos'] . '</h3>
                                    <p class="text-muted">Total de Pedidos</p>
                                </div>
                                <div class="text-center mb-3">
                                    <h3 class="text-success">R$ ' . number_format($stats['total_gasto'], 2, ',', '.') . '</h3>
                                    <p class="text-muted">Total Gasto</p>
                                </div>
                                <hr>
                                <p class="small text-muted">
                                    <strong>Último pedido:</strong><br>
                                    ' . ($stats['ultimo_pedido'] ? date('d/m/Y H:i', strtotime($stats['ultimo_pedido'])) : 'Nenhum') . '
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-8">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="mb-0">Dados Pessoais</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <p><strong>Nome:</strong> ' . htmlspecialchars($usuario['nome']) . '</p>
                                        <p><strong>Email:</strong> ' . htmlspecialchars($usuario['email']) . '</p>
                                        <p><strong>Telefone:</strong> ' . htmlspecialchars($usuario['telefone'] ?? 'N/A') . '</p>
                                        <p><strong>CPF:</strong> ' . htmlspecialchars($usuario['cpf'] ?? 'N/A') . '</p>
                                        ' . (!empty($usuario['suite']) ? '<p><strong>Suite:</strong> ' . (int) $usuario['suite'] . '</p>' : '') . '
                                    </div>
                                    <div class="col-md-6">
                                        <p><strong>Data Nascimento:</strong> ' . ($usuario['data_nascimento'] ? date('d/m/Y', strtotime($usuario['data_nascimento'])) : 'N/A') . '</p>
                                        <p><strong>CEP:</strong> ' . htmlspecialchars($usuario['cep'] ?? 'N/A') . '</p>
                                        <p><strong>Endereço:</strong> ' . htmlspecialchars($usuario['endereco'] ?? '') . ', ' . htmlspecialchars($usuario['numero'] ?? '') . '</p>
                                        <p><strong>Bairro:</strong> ' . htmlspecialchars($usuario['bairro'] ?? 'N/A') . '</p>
                                        <p><strong>Cidade:</strong> ' . htmlspecialchars($usuario['cidade'] ?? '') . ' - ' . htmlspecialchars($usuario['estado'] ?? '') . '</p>
                                    </div>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between">
                                    <a href="/admin/usuarios/editar/' . $usuario['id'] . '" class="btn btn-warning">
                                        <i class="fas fa-edit"></i> Editar Usuário
                                    </a>
                                    <form method="POST" action="/admin/usuarios/atualizar-status/' . $usuario['id'] . '" style="display: inline;">
                                        <input type="hidden" name="ativo" value="' . ($usuario['ativo'] ? '0' : '1') . '">
                                        <button type="submit" class="btn ' . ($usuario['ativo'] ? 'btn-danger' : 'btn-success') . '">
                                            <i class="fas fa-' . ($usuario['ativo'] ? 'ban' : 'check') . '"></i> ' . ($usuario['ativo'] ? 'Desativar' : 'Ativar') . '
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card mt-4">
                            <div class="card-header">
                                <h5 class="mb-0">Pedidos Recentes</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table">
                                        <thead>
                                            <tr>
                                                <th>Pedido</th>
                                                <th>Data</th>
                                                <th>Status</th>
                                                <th>Valor</th>
                                                <th>Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>';
                                        
                                        foreach ($pedidos as $pedido) {
                                            echo '<tr>
                                                <td>#' . str_pad($pedido['id'], 6, '0', STR_PAD_LEFT) . '</td>
                                                <td>' . date('d/m/Y H:i', strtotime($pedido['created_at'])) . '</td>
                                                <td><span class="badge bg-' . ($pedido['status'] == 'pago' ? 'success' : 'warning') . '">' . ucfirst($pedido['status']) . '</span></td>
                                                <td>R$ ' . number_format($pedido['valor_total'], 2, ',', '.') . '</td>
                                                <td>
                                                    <a href="/admin/pedidos/detalhes/' . $pedido['id'] . '" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>';
                                        }
                                        
                                        if (empty($pedidos)) {
                                            echo '<tr><td colspan="5" class="text-center text-muted">Nenhum pedido encontrado</td></tr>';
                                        }
                                        
                                        echo '</tbody>
                                    </table>
                                </div>
                                <div class="text-center mt-3">
                                    <a href="/admin/pedidos?busca=' . urlencode($usuario['email']) . '" class="btn btn-outline-primary">
                                        Ver Todos os Pedidos
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>';
        exit;
    }

    public function excluir(Request $request, $id = null) {
        $id = $id ?? $request->getParam('id');

        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');

            $stmt = $pdo->prepare("DELETE FROM usuarios WHERE id = ?");
            $stmt->execute([$id]);

            header('Location: /admin/usuarios?success=excluido');
            exit;
        } catch (\Exception $e) {
            echo '<div class="alert alert-danger">Erro ao excluir usuário: ' . $e->getMessage() . '</div>';
            echo '<a href="/admin/usuarios" class="btn btn-secondary">Voltar</a>';
            exit;
        }
    }
    
    public function atualizarStatus(Request $request) {
        $id = $request->getParam('id');
        $ativo = $request->getParam('ativo');
        
        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            
            $stmt = $pdo->prepare("UPDATE usuarios SET ativo = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$ativo, $id]);
            
            header('Location: /admin/usuarios/detalhes/' . $id . '?success=1');
            exit;
            
        } catch (\Exception $e) {
            echo '<div class="alert alert-danger">Erro ao atualizar status: ' . $e->getMessage() . '</div>';
            echo '<a href="/admin/usuarios/detalhes/' . $id . '" class="btn btn-secondary">Voltar</a>';
            exit;
        }
    }
}
