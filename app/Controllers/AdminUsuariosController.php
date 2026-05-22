<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;
use App\Models\Usuario;

class AdminUsuariosController extends Controller {
    
    public function index(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'suporte', 'vendedor']);
        try {
            $helper = new \App\Controllers\AdminUsuariosHelper();
            
            $pagina = $request->getParam('pagina', 1);
            $limite = 100;
            $offset = ($pagina - 1) * $limite;
            $busca = $request->getParam('busca', '');
            $ordem = $request->getParam('ordem', 'nome_asc');
            
            $usuarios = $helper->getUsuariosComCarteira($busca, $limite, $offset, $ordem);
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

        // Group users by first letter for alphabetical view
        $usersGrouped = [];
        foreach ($usuarios as $u) {
            $nome = trim($u['nome'] ?? '');
            $letter = mb_strtoupper(mb_substr($nome, 0, 1, 'UTF-8'), 'UTF-8');
            if (!preg_match('/[A-Z]/', $letter)) $letter = '#';
            $usersGrouped[$letter][] = $u;
        }
        ksort($usersGrouped);

        // Helper: get initials from name
        $getInitials = function($nome) {
            $parts = preg_split('/\s+/', trim($nome));
            $first = mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1, 'UTF-8'), 'UTF-8');
            $last = count($parts) > 1 ? mb_strtoupper(mb_substr(end($parts), 0, 1, 'UTF-8'), 'UTF-8') : '';
            return $first . $last;
        };

        // Helper: role label and class
        $getRoleInfo = function($usuario) {
            $perfil = '';
            if (is_array($usuario)) {
                if (array_key_exists('perfil', $usuario) && $usuario['perfil'] !== null && trim((string) $usuario['perfil']) !== '') {
                    $perfil = strtolower(trim((string) $usuario['perfil']));
                } elseif (array_key_exists('role', $usuario) && $usuario['role'] !== null && trim((string) $usuario['role']) !== '') {
                    $perfil = strtolower(trim((string) $usuario['role']));
                }
            }
            if ($perfil === '') $perfil = 'cliente';
            $map = [
                'admin' => ['Admin', 'role-admin'],
                'vendedor' => ['Vendedor', 'role-vendedor'],
                'suporte' => ['Suporte', 'role-suporte'],
            ];
            return $map[$perfil] ?? ['Cliente', 'role-cliente'];
        };

        // Helper: format wallet
        $formatWallet = function($u) {
            return '$' . number_format((float)($u['carteira_usd'] ?? 0), 2, '.', ',');
        };

        // Helper: user status
        $getUserStatus = function($u) {
            if (isset($u['ativo']) && (int)$u['ativo'] === 1) return ['Ativo', 'badge-green'];
            if (isset($u['status']) && strtolower($u['status']) === 'ativo') return ['Ativo', 'badge-green'];
            if (!isset($u['ativo']) && !isset($u['status'])) return ['Ativo', 'badge-green'];
            return ['Inativo', 'badge-red'];
        };
        
        // Incluir o partial do menu lateral
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuários - Braziliana Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="/assets/css/usuarios-redesign.css" rel="stylesheet">';
        
        // Renderizar estilos do menu
        renderAdminSidebarStyles();
        
        echo '</head>
<body style="background:var(--bg-page);">
    <div class="container-fluid">
        <div class="row">';
        
        // Renderizar menu lateral usando o partial
        renderAdminSidebar('usuarios');
        
        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="users-page">';

        // === PAGE HEADER ===
        echo '<div class="page-header">
                <div class="page-title-wrap">
                    <div>
                        <h1 class="page-title">Usuários</h1>
                        <div class="page-count">' . (int)$total . ' usuários cadastrados</div>
                    </div>
                </div>
                <div class="header-actions">
                    <button type="button" class="btn btn-success-soft" onclick="adicionarCreditosEmLote()">
                        <i class="bi bi-cash-stack"></i> Adicionar Créditos em Lote
                    </button>
                    <a href="/admin/usuarios/novo" class="btn btn-primary-navy">
                        <i class="bi bi-plus-lg"></i> Novo Usuário
                    </a>
                </div>
            </div>';

        // === ERROR ===
        if (isset($erro)) {
            echo '<div class="alert alert-danger" style="border-radius:10px;margin-bottom:20px;">
                <i class="bi bi-exclamation-triangle me-2"></i>
                <strong>Erro:</strong> ' . htmlspecialchars($erro) . '
            </div>';
        }

        // === KPI GRID ===
        $totalCarteiras = '$' . number_format((float)($stats['total_carteira_usd'] ?? 0), 2, '.', ',');
        $usuariosAtivos = (int)($stats['usuarios_ativos'] ?? $total);
        $novosHoje = (int)($stats['usuarios_hoje'] ?? 0);

        echo '<div class="kpi-grid">
                <div class="kpi-card">
                    <div class="kpi-label">Total Usuários</div>
                    <div class="kpi-value">' . (int)$total . '</div>
                </div>
                <div class="kpi-card is-highlight">
                    <div class="kpi-label">Total em Carteiras</div>
                    <div class="kpi-value">' . $totalCarteiras . '</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">Usuários Ativos</div>
                    <div class="kpi-value">' . $usuariosAtivos . '</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-label">Novos Hoje</div>
                    <div class="kpi-value">' . $novosHoje . '</div>
                </div>
            </div>';

        // === FILTERS CARD ===
        echo '<div class="filters-card">
                <form method="GET" action="/admin/usuarios" class="filters-grid">
                    <div class="input-wrap">
                        <i class="bi bi-search"></i>
                        <input type="text" name="busca" placeholder="Buscar por nome, email, CPF ou suite..." value="' . htmlspecialchars($busca) . '">
                    </div>
                    <select name="ordem" onchange="this.form.submit()">
                        <option value="nome_asc"' . ($ordem === 'nome_asc' ? ' selected' : '') . '>Nome A→Z</option>
                        <option value="nome_desc"' . ($ordem === 'nome_desc' ? ' selected' : '') . '>Nome Z→A</option>
                        <option value=""' . ($ordem === '' ? ' selected' : '') . '>Mais recentes</option>
                        <option value="carteira_desc"' . ($ordem === 'carteira_desc' ? ' selected' : '') . '>Carteira maior→menor</option>
                        <option value="carteira_asc"' . ($ordem === 'carteira_asc' ? ' selected' : '') . '>Carteira menor→maior</option>
                    </select>
                    <button type="submit" class="btn btn-search"><i class="bi bi-search"></i> Buscar</button>
                    <a href="/admin/usuarios" class="btn btn-clear"><i class="bi bi-x-lg"></i> Limpar</a>
                </form>
            </div>';

        // === ALPHABETICAL LIST ===
        if (empty($usuarios)) {
            echo '<div style="text-align:center;padding:60px 20px;">
                <i class="bi bi-people" style="font-size:48px;color:var(--text-muted);"></i>
                <h5 style="color:var(--text-secondary);margin-top:16px;">Nenhum usuário encontrado</h5>
                <p style="color:var(--text-muted);">Tente ajustar sua busca ou cadastre um novo usuário.</p>
            </div>';
        } else {
            echo '<div class="alphabetical-list">';
            foreach ($usersGrouped as $letter => $users) {
                echo '<div class="letter-section">
                    <div class="letter-header"><span>' . htmlspecialchars($letter) . '</span></div>
                    <div class="users-list">';

                foreach ($users as $u) {
                    $initials = $getInitials($u['nome'] ?? '');
                    [$roleLabel, $roleClass] = $getRoleInfo($u);
                    $wallet = $formatWallet($u);
                    [$statusLabel, $statusClass] = $getUserStatus($u);
                    $email = htmlspecialchars($u['email'] ?? '');
                    $nome = htmlspecialchars($u['nome'] ?? '');
                    $suite = htmlspecialchars($u['suite'] ?? '');
                    $pedidos = (int)($u['total_pedidos'] ?? 0);
                    $userId = (int)$u['id'];

                    // Desktop row
                    echo '<div class="user-row">
                        <div class="user-main">
                            <div class="user-avatar">' . htmlspecialchars($initials) . '</div>
                            <div class="user-data">
                                <div class="user-name-line">
                                    <span class="user-name">' . $nome . '</span>
                                    <span class="role-badge ' . $roleClass . '">' . $roleLabel . '</span>
                                </div>
                                <div class="user-email">' . $email . '</div>'
                                . ($suite ? '<div class="user-suite">Suite: ' . $suite . '</div>' : '') .
                            '</div>
                        </div>
                        <div>
                            <div class="list-metric-label">Pedidos</div>
                            <div class="list-metric-value">' . $pedidos . '</div>
                        </div>
                        <div>
                            <div class="list-metric-label">Carteira</div>
                            <div class="wallet-value">' . $wallet . '</div>
                        </div>
                        <div>
                            <span class="badge ' . $statusClass . '">' . $statusLabel . '</span>
                        </div>
                        <div class="user-actions">
                            <a href="/admin/usuarios/detalhes/' . $userId . '" class="btn-view"><i class="bi bi-eye"></i> Ver</a>
                            <form method="POST" action="/admin/usuarios/impersonar/' . $userId . '" style="display:inline;margin:0;"><input type="hidden" name="csrf_token" value="' . htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') . '"><button type="submit" class="icon-btn icon-btn-admin" title="Login As"><i class="bi bi-box-arrow-in-right"></i></button></form>
                            <span class="icon-btn icon-btn-credit" title="Crédito" onclick="adicionarCredito(' . $userId . ', \'' . addslashes($nome) . '\')"><i class="bi bi-wallet2"></i></span>
                            <a href="/admin/usuarios/editar/' . $userId . '" class="icon-btn icon-btn-edit" title="Editar"><i class="bi bi-pencil"></i></a>
                            <form method="POST" action="/admin/usuarios/excluir/' . $userId . '" style="display:inline;margin:0;" onsubmit="return confirm(\'Excluir ' . addslashes($nome) . '?\')"><button type="submit" class="icon-btn icon-btn-delete" title="Excluir"><i class="bi bi-trash"></i></button></form>
                        </div>
                    </div>';

                    // Mobile card
                    echo '<div class="user-card-mobile">
                        <div class="mobile-header">
                            <div class="user-avatar">' . htmlspecialchars($initials) . '</div>
                            <div class="user-data">
                                <div class="user-name-line">
                                    <span class="user-name">' . $nome . '</span>
                                    <span class="role-badge ' . $roleClass . '">' . $roleLabel . '</span>
                                </div>
                                <div class="user-email">' . $email . '</div>'
                                . ($suite ? '<div class="user-suite">Suite: ' . $suite . '</div>' : '') .
                            '</div>
                        </div>
                        <div class="mobile-metrics">
                            <div class="metric">
                                <div class="metric-label">Pedidos</div>
                                <div class="metric-value">' . $pedidos . '</div>
                            </div>
                            <div class="metric">
                                <div class="metric-label">Carteira</div>
                                <div class="metric-value">' . $wallet . '</div>
                            </div>
                            <div class="metric">
                                <div class="metric-label">Status</div>
                                <div class="metric-value"><span class="badge ' . $statusClass . '">' . $statusLabel . '</span></div>
                            </div>
                        </div>
                        <div class="mobile-actions">
                            <a href="/admin/usuarios/detalhes/' . $userId . '" class="btn-view"><i class="bi bi-eye"></i> Ver</a>
                            <form method="POST" action="/admin/usuarios/impersonar/' . $userId . '" style="display:inline;margin:0;"><input type="hidden" name="csrf_token" value="' . htmlspecialchars((string) ($_SESSION['csrf_token'] ?? ''), ENT_QUOTES, 'UTF-8') . '"><button type="submit" class="icon-btn icon-btn-admin" title="Login As"><i class="bi bi-box-arrow-in-right"></i></button></form>
                            <span class="icon-btn icon-btn-credit" onclick="adicionarCredito(' . $userId . ', \'' . addslashes($nome) . '\')"><i class="bi bi-wallet2"></i></span>
                            <a href="/admin/usuarios/editar/' . $userId . '" class="icon-btn icon-btn-edit"><i class="bi bi-pencil"></i></a>
                            <form method="POST" action="/admin/usuarios/excluir/' . $userId . '" style="display:inline;margin:0;" onsubmit="return confirm(\'Excluir ' . addslashes($nome) . '?\')"><button type="submit" class="icon-btn icon-btn-delete" title="Excluir"><i class="bi bi-trash"></i></button></form>
                        </div>
                    </div>';
                }

                echo '</div></div>'; // .users-list, .letter-section
            }
            echo '</div>'; // .alphabetical-list
        }

        // === PAGINATION ===
        if ($totalPaginas > 1) {
            $paginaAtual = (int) $pagina;
            if ($paginaAtual < 1) $paginaAtual = 1;
            if ($paginaAtual > (int) $totalPaginas) $paginaAtual = (int) $totalPaginas;
            $mkUrl = function(int $p) use ($busca, $ordem): string {
                $url = "/admin/usuarios?pagina={$p}";
                if (!empty($busca)) $url .= "&busca=" . urlencode($busca);
                if (!empty($ordem)) $url .= "&ordem=" . urlencode($ordem);
                return $url;
            };

            $start = max(1, $paginaAtual - 1);
            $end = min((int) $totalPaginas, $paginaAtual + 1);
            if (($end - $start + 1) < 3) {
                if ($start === 1) {
                    $end = min((int) $totalPaginas, $start + 2);
                } elseif ($end === (int) $totalPaginas) {
                    $start = max(1, $end - 2);
                }
            }

            echo '<nav class="mt-4"><ul class="pagination justify-content-center flex-wrap">';

            $prev = max(1, $paginaAtual - 1);
            echo '<li class="page-item ' . ($paginaAtual <= 1 ? 'disabled' : '') . '">'
                . '<a class="page-link" href="' . ($paginaAtual <= 1 ? '#' : $mkUrl($prev)) . '" tabindex="-1">Anterior</a>'
                . '</li>';

            if ($start > 1) {
                echo '<li class="page-item"><a class="page-link" href="' . $mkUrl(1) . '">1</a></li>';
                if ($start > 2) {
                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                }
            }

            for ($i = $start; $i <= $end; $i++) {
                echo '<li class="page-item ' . ($i === $paginaAtual ? 'active' : '') . '">'
                    . '<a class="page-link" href="' . $mkUrl($i) . '">' . $i . '</a>'
                    . '</li>';
            }

            if ($end < (int) $totalPaginas) {
                if ($end < ((int) $totalPaginas - 1)) {
                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                }
                echo '<li class="page-item"><a class="page-link" href="' . $mkUrl((int) $totalPaginas) . '">' . (int) $totalPaginas . '</a></li>';
            }

            $next = min((int) $totalPaginas, $paginaAtual + 1);
            echo '<li class="page-item ' . ($paginaAtual >= (int) $totalPaginas ? 'disabled' : '') . '">'
                . '<a class="page-link" href="' . ($paginaAtual >= (int) $totalPaginas ? '#' : $mkUrl($next)) . '">Próxima</a>'
                . '</li>';

            echo '</ul></nav>';
        }
                
        echo '</div></main></div></div>'; // .users-page, main, row, container

    // Renderizar scripts
    renderAdminScripts();
    
    // Adicionar scripts dos usuários
    echo '<script>
        // Armazenar dados dos usuários para uso nos scripts
        var usuariosData = ' . json_encode(array_values($usuarios)) . ';
    </script>';
    echo \App\Controllers\AdminUsuariosViews::getScripts();
    
    // Adicionar modais
    echo \App\Controllers\AdminUsuariosViews::renderModalAdicionarCredito();
    echo \App\Controllers\AdminUsuariosViews::renderModalConverterMoeda();
    echo \App\Controllers\AdminUsuariosViews::renderModalCreditosLote();
    
    echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
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
    <title>Novo Usuário - Braziliana Admin</title>
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
                <h1 class="page-title">Novo Usuário</h1>
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
                                    <option value="conferente">Conferente</option>
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

        // Buscar endereço principal do usuário
        $endereco = [];
        try {
            $dbEnd = \Config\Database::getConnection();
            $colsEnd = [];
            $stColsE = $dbEnd->query('DESCRIBE enderecos');
            $colsEnd = $stColsE ? ($stColsE->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];

            if (in_array('usuario_id', $colsEnd, true)) {
                $orderBy = in_array('principal', $colsEnd, true) ? 'principal DESC, id DESC' : 'id DESC';
                $stEnd = $dbEnd->prepare('SELECT * FROM enderecos WHERE usuario_id = ? ORDER BY ' . $orderBy . ' LIMIT 1');
                $stEnd->execute([(int) $usuario['id']]);
                $endereco = $stEnd->fetch(\PDO::FETCH_ASSOC) ?: [];
            }
        } catch (\Exception $e) {
            $endereco = [];
        }

        $perfilAtual = strtolower(trim((string) ($usuario['perfil'] ?? ($usuario['role'] ?? 'cliente'))));
        if ($perfilAtual === '') {
            $perfilAtual = 'cliente';
        }

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Usuário - Braziliana Admin</title>
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
                <h1 class="page-title">Editar Usuário</h1>
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
                                <input type="text" class="form-control" name="cpf" value="' . htmlspecialchars(\App\Services\CpfValidator::format(($usuario['cpf'] ?? '') !== '' ? ($usuario['cpf'] ?? '') : ($usuario['documento'] ?? ''))) . '">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Telefone</label>
                                <input type="text" class="form-control" name="telefone" value="' . htmlspecialchars($usuario['telefone'] ?? '') . '">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Data de Nascimento</label>
                                <input type="date" class="form-control" name="data_nascimento" value="' . htmlspecialchars($usuario['data_nascimento'] ?? '') . '">
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
                                    <option value="cliente" ' . ($perfilAtual === 'cliente' ? 'selected' : '') . '>Cliente</option>
                                    <option value="admin" ' . ($perfilAtual === 'admin' ? 'selected' : '') . '>Administrador</option>
                                    <option value="vendedor" ' . ($perfilAtual === 'vendedor' ? 'selected' : '') . '>Vendedor</option>
                                    <option value="conferente" ' . ($perfilAtual === 'conferente' ? 'selected' : '') . '>Conferente</option>
                                    <option value="suporte" ' . ($perfilAtual === 'suporte' ? 'selected' : '') . '>Suporte</option>
                                    <option value="representante" ' . ($perfilAtual === 'representante' ? 'selected' : '') . '>Representante</option>
                                    <option value="redirecionador" ' . ($perfilAtual === 'redirecionador' ? 'selected' : '') . '>Redirecionador</option>
                                </select>
                            </div>
                        </div>

                        <hr class="my-4">
                        <h5 class="mb-3"><i class="fas fa-map-marker-alt me-2"></i>Endereço Principal</h5>
                        <input type="hidden" name="endereco_id" value="' . (int) ($endereco['id'] ?? 0) . '">';

        $paisAtual = strtoupper(trim((string) ($endereco['pais'] ?? 'BR')));
        if ($paisAtual === '') $paisAtual = 'BR';
        $todosPaises = [
            'AF'=>'Afeganistão','ZA'=>'África do Sul','AL'=>'Albânia','DE'=>'Alemanha','AD'=>'Andorra','AO'=>'Angola','AG'=>'Antígua e Barbuda','SA'=>'Arábia Saudita','DZ'=>'Argélia','AR'=>'Argentina','AM'=>'Armênia','AU'=>'Austrália','AT'=>'Áustria','AZ'=>'Azerbaijão',
            'BS'=>'Bahamas','BH'=>'Bahrein','BD'=>'Bangladesh','BB'=>'Barbados','BE'=>'Bélgica','BZ'=>'Belize','BJ'=>'Benin','BY'=>'Bielorrússia','BO'=>'Bolívia','BA'=>'Bósnia e Herzegovina','BW'=>'Botsuana','BR'=>'Brasil','BN'=>'Brunei','BG'=>'Bulgária','BF'=>'Burkina Faso','BI'=>'Burundi','BT'=>'Butão',
            'CV'=>'Cabo Verde','CM'=>'Camarões','KH'=>'Camboja','CA'=>'Canadá','QA'=>'Catar','KZ'=>'Cazaquistão','TD'=>'Chade','CL'=>'Chile','CN'=>'China','CY'=>'Chipre','CO'=>'Colômbia','KM'=>'Comores','CG'=>'Congo','KP'=>'Coreia do Norte','KR'=>'Coreia do Sul','CI'=>'Costa do Marfim','CR'=>'Costa Rica','HR'=>'Croácia','CU'=>'Cuba',
            'DK'=>'Dinamarca','DJ'=>'Djibuti','DM'=>'Dominica',
            'EG'=>'Egito','SV'=>'El Salvador','AE'=>'Emirados Árabes','EC'=>'Equador','ER'=>'Eritreia','SK'=>'Eslováquia','SI'=>'Eslovênia','ES'=>'Espanha','US'=>'Estados Unidos','EE'=>'Estônia','SZ'=>'Eswatini','ET'=>'Etiópia',
            'FJ'=>'Fiji','PH'=>'Filipinas','FI'=>'Finlândia','FR'=>'França',
            'GA'=>'Gabão','GM'=>'Gâmbia','GH'=>'Gana','GE'=>'Geórgia','GR'=>'Grécia','GD'=>'Granada','GT'=>'Guatemala','GY'=>'Guiana','GN'=>'Guiné','GQ'=>'Guiné Equatorial','GW'=>'Guiné-Bissau',
            'HT'=>'Haiti','HN'=>'Honduras','HU'=>'Hungria',
            'YE'=>'Iêmen','IN'=>'Índia','ID'=>'Indonésia','IQ'=>'Iraque','IR'=>'Irã','IE'=>'Irlanda','IS'=>'Islândia','IL'=>'Israel','IT'=>'Itália',
            'JM'=>'Jamaica','JP'=>'Japão','JO'=>'Jordânia',
            'KW'=>'Kuwait',
            'LA'=>'Laos','LS'=>'Lesoto','LV'=>'Letônia','LB'=>'Líbano','LR'=>'Libéria','LY'=>'Líbia','LI'=>'Liechtenstein','LT'=>'Lituânia','LU'=>'Luxemburgo',
            'MK'=>'Macedônia do Norte','MG'=>'Madagascar','MY'=>'Malásia','MW'=>'Malawi','MV'=>'Maldivas','ML'=>'Mali','MT'=>'Malta','MA'=>'Marrocos','MU'=>'Maurício','MR'=>'Mauritânia','MX'=>'México','MM'=>'Mianmar','FM'=>'Micronésia','MZ'=>'Moçambique','MD'=>'Moldávia','MC'=>'Mônaco','MN'=>'Mongólia','ME'=>'Montenegro',
            'NA'=>'Namíbia','NR'=>'Nauru','NP'=>'Nepal','NI'=>'Nicarágua','NE'=>'Níger','NG'=>'Nigéria','NO'=>'Noruega','NZ'=>'Nova Zelândia',
            'OM'=>'Omã',
            'NL'=>'Países Baixos','PW'=>'Palau','PA'=>'Panamá','PG'=>'Papua Nova Guiné','PK'=>'Paquistão','PY'=>'Paraguai','PE'=>'Peru','PL'=>'Polônia','PT'=>'Portugal',
            'KE'=>'Quênia','KG'=>'Quirguistão',
            'GB'=>'Reino Unido','CF'=>'República Centro-Africana','CD'=>'República Dem. do Congo','DO'=>'República Dominicana','CZ'=>'República Tcheca','RO'=>'Romênia','RW'=>'Ruanda','RU'=>'Rússia',
            'WS'=>'Samoa','SM'=>'San Marino','LC'=>'Santa Lúcia','KN'=>'São Cristóvão e Névis','ST'=>'São Tomé e Príncipe','VC'=>'São Vicente e Granadinas','SC'=>'Seicheles','SN'=>'Senegal','SL'=>'Serra Leoa','RS'=>'Sérvia','SG'=>'Singapura','SY'=>'Síria','SO'=>'Somália','LK'=>'Sri Lanka','SD'=>'Sudão','SS'=>'Sudão do Sul','SE'=>'Suécia','CH'=>'Suíça','SR'=>'Suriname',
            'TJ'=>'Tajiquistão','TH'=>'Tailândia','TZ'=>'Tanzânia','TL'=>'Timor-Leste','TG'=>'Togo','TO'=>'Tonga','TT'=>'Trinidad e Tobago','TN'=>'Tunísia','TM'=>'Turcomenistão','TR'=>'Turquia','TV'=>'Tuvalu',
            'UA'=>'Ucrânia','UG'=>'Uganda','UY'=>'Uruguai','UZ'=>'Uzbequistão',
            'VU'=>'Vanuatu','VA'=>'Vaticano','VE'=>'Venezuela','VN'=>'Vietnã',
            'ZM'=>'Zâmbia','ZW'=>'Zimbábue',
        ];
        $paisOptions = '';
        foreach ($todosPaises as $code => $nome) {
            $sel = ($code === $paisAtual) ? ' selected' : '';
            $paisOptions .= '<option value="' . $code . '"' . $sel . '>' . htmlspecialchars($nome) . '</option>';
        }
        if ($paisAtual !== '' && !isset($todosPaises[$paisAtual])) {
            $paisOptions = '<option value="' . htmlspecialchars($paisAtual) . '" selected>' . htmlspecialchars($paisAtual) . '</option>' . $paisOptions;
        }

        echo '
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">País</label>
                                <select class="form-select" name="end_pais" id="end_pais" onchange="atualizarCamposPorPais()">
                                    ' . $paisOptions . '
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" id="lbl_cep">CEP</label>
                                <input type="text" class="form-control" name="end_cep" id="end_cep" value="' . htmlspecialchars((string) ($endereco['cep'] ?? '')) . '" placeholder="00000-000">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" id="lbl_endereco">Endereço</label>
                                <input type="text" class="form-control" name="end_endereco" value="' . htmlspecialchars((string) ($endereco['endereco'] ?? ($endereco['logradouro'] ?? ''))) . '">
                            </div>
                            <div class="col-md-3" id="wrap_numero">
                                <label class="form-label" id="lbl_numero">Número</label>
                                <input type="text" class="form-control" name="end_numero" value="' . htmlspecialchars((string) ($endereco['numero'] ?? '')) . '">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" id="lbl_complemento">Complemento</label>
                                <input type="text" class="form-control" name="end_complemento" value="' . htmlspecialchars((string) ($endereco['complemento'] ?? '')) . '">
                            </div>
                            <div class="col-md-4" id="wrap_bairro">
                                <label class="form-label">Bairro</label>
                                <input type="text" class="form-control" name="end_bairro" value="' . htmlspecialchars((string) ($endereco['bairro'] ?? '')) . '">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Cidade</label>
                                <input type="text" class="form-control" name="end_cidade" minlength="3" value="' . htmlspecialchars((string) ($endereco['cidade'] ?? '')) . '">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" id="lbl_estado">Estado</label>
                                <select class="form-select" name="end_estado" id="end_estado_select">
                                    <option value="">Selecione...</option>
                                </select>
                                <input type="text" class="form-control" name="end_estado_text" id="end_estado_text" value="' . htmlspecialchars((string) ($endereco['estado'] ?? '')) . '" maxlength="2" placeholder="SP" style="display:none;">
                            </div>
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>Salvar Alterações
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    var estadoAtual = ' . json_encode((string) ($endereco['estado'] ?? '')) . ';
    var statesByCountry = {
        BR: ["AC","AL","AP","AM","BA","CE","DF","ES","GO","MA","MT","MS","MG","PA","PB","PR","PE","PI","RJ","RN","RS","RO","RR","SC","SP","SE","TO"],
        US: ["AL","AK","AZ","AR","CA","CO","CT","DE","FL","GA","HI","ID","IL","IN","IA","KS","KY","LA","ME","MD","MA","MI","MN","MS","MO","MT","NE","NV","NH","NJ","NM","NY","NC","ND","OH","OK","OR","PA","RI","SC","SD","TN","TX","UT","VT","VA","WA","WV","WI","WY","DC"],
        CA: ["AB","BC","MB","NB","NL","NS","NT","NU","ON","PE","QC","SK","YT"]
    };

    function atualizarCamposPorPais() {
        var paisSel = document.getElementById("end_pais");
        var pais = paisSel ? paisSel.value.toUpperCase().trim() : "BR";
        // Normalizar variações de Brasil
        if (pais === "BRASIL" || pais === "BRAZIL") pais = "BR";
        var cep = document.getElementById("end_cep");
        var lblCep = document.getElementById("lbl_cep");
        var lblEnd = document.getElementById("lbl_endereco");
        var lblComp = document.getElementById("lbl_complemento");
        var lblNum = document.getElementById("lbl_numero");
        var wrapNum = document.getElementById("wrap_numero");
        var wrapBairro = document.getElementById("wrap_bairro");
        var selEstado = document.getElementById("end_estado_select");
        var txtEstado = document.getElementById("end_estado_text");
        var lblEstado = document.getElementById("lbl_estado");

        if (cep && lblCep) {
            if (pais === "BR") { cep.placeholder = "00000-000"; cep.maxLength = 9; lblCep.textContent = "CEP"; }
            else if (pais === "US") { cep.placeholder = "00000"; cep.maxLength = 10; lblCep.textContent = "ZIP Code"; }
            else if (pais === "CA") { cep.placeholder = "A1A 1A1"; cep.maxLength = 7; lblCep.textContent = "Postal Code"; }
            else { cep.placeholder = ""; cep.maxLength = 12; lblCep.textContent = "Postal Code"; }
        }

        if (lblEnd) lblEnd.textContent = (pais === "BR") ? "Endereço" : "Address";
        if (lblComp) lblComp.textContent = (pais === "BR") ? "Complemento" : "Address line 2";
        if (lblNum) lblNum.textContent = (pais === "BR") ? "Número" : "Number";
        if (wrapNum) wrapNum.style.display = (pais === "BR") ? "" : "none";
        if (wrapBairro) wrapBairro.style.display = (pais === "BR") ? "" : "none";
        if (lblEstado) lblEstado.textContent = (pais === "BR") ? "Estado" : "State";

        var list = statesByCountry[pais] || null;
        if (selEstado && txtEstado) {
            if (Array.isArray(list) && list.length > 0) {
                var cur = (selEstado.value || txtEstado.value || estadoAtual || "").toUpperCase();
                selEstado.innerHTML = "";
                var optE = document.createElement("option");
                optE.value = ""; optE.textContent = "Selecione...";
                selEstado.appendChild(optE);
                list.forEach(function(uf) {
                    var opt = document.createElement("option");
                    opt.value = uf; opt.textContent = uf;
                    if (cur && uf === cur) opt.selected = true;
                    selEstado.appendChild(opt);
                });
                selEstado.style.display = "";
                selEstado.name = "end_estado";
                txtEstado.style.display = "none";
                txtEstado.name = "end_estado_text";
            } else {
                selEstado.style.display = "none";
                selEstado.name = "end_estado_ui";
                txtEstado.style.display = "";
                txtEstado.name = "end_estado";
            }
        }
    }
    atualizarCamposPorPais();
    </script>
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
                'data_nascimento' => $request->getParam('data_nascimento'),
                'ativo' => $request->getParam('ativo', 1),
                'senha' => $request->getParam('senha'),
                'perfil' => $request->getParam('perfil', 'cliente')
            ];

            if (!empty($id)) {
                $helper->atualizarUsuario($id, $dados);

                // Salvar endereço principal
                try {
                    $dbEnd = \Config\Database::getConnection();
                    $colsEnd = [];
                    $stColsE = $dbEnd->query('DESCRIBE enderecos');
                    $colsEnd = $stColsE ? ($stColsE->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];

                    $endCep = trim((string) $request->getParam('end_cep'));
                    $endEndereco = trim((string) $request->getParam('end_endereco'));
                    $endNumero = trim((string) $request->getParam('end_numero'));
                    $endComplemento = trim((string) $request->getParam('end_complemento'));
                    $endBairro = trim((string) $request->getParam('end_bairro'));
                    $endCidade = trim((string) $request->getParam('end_cidade'));

                    // Estado: pode vir do select (end_estado) ou do text (end_estado_text)
                    $endEstado = trim((string) $request->getParam('end_estado'));
                    if ($endEstado === '') {
                        $endEstado = trim((string) $request->getParam('end_estado_text'));
                    }

                    // País: vem direto do select com todos os países
                    $endPais = strtoupper(trim((string) $request->getParam('end_pais')));
                    if ($endPais === '') {
                        $endPais = 'BR';
                    }
                    $enderecoId = (int) $request->getParam('endereco_id');

                    // Só salvar se pelo menos um campo de endereço foi preenchido
                    $temDados = ($endCep !== '' || $endEndereco !== '' || $endCidade !== '');

                    // Validar cidade: mínimo 3 caracteres
                    if ($endCidade !== '' && mb_strlen($endCidade) < 3) {
                        throw new \Exception('Cidade deve ter no mínimo 3 caracteres');
                    }

                    if ($temDados && in_array('usuario_id', $colsEnd, true)) {
                        $endData = [];
                        if (in_array('cep', $colsEnd, true)) $endData['cep'] = $endCep;
                        if (in_array('endereco', $colsEnd, true)) $endData['endereco'] = $endEndereco;
                        elseif (in_array('logradouro', $colsEnd, true)) $endData['logradouro'] = $endEndereco;
                        if (in_array('numero', $colsEnd, true)) $endData['numero'] = $endNumero;
                        if (in_array('complemento', $colsEnd, true)) $endData['complemento'] = $endComplemento;
                        if (in_array('bairro', $colsEnd, true)) $endData['bairro'] = $endBairro;
                        if (in_array('cidade', $colsEnd, true)) $endData['cidade'] = $endCidade;
                        if (in_array('estado', $colsEnd, true)) $endData['estado'] = $endEstado;
                        if (in_array('pais', $colsEnd, true)) $endData['pais'] = $endPais !== '' ? $endPais : 'BR';
                        if (in_array('principal', $colsEnd, true)) $endData['principal'] = 1;

                        if ($enderecoId > 0) {
                            // Atualizar endereço existente
                            $sets = [];
                            $params = [];
                            foreach ($endData as $k => $v) {
                                $sets[] = $k . ' = ?';
                                $params[] = $v;
                            }
                            if (in_array('updated_at', $colsEnd, true)) {
                                $sets[] = 'updated_at = NOW()';
                            }
                            $params[] = $enderecoId;
                            $params[] = (int) $id;
                            $stUpd = $dbEnd->prepare('UPDATE enderecos SET ' . implode(', ', $sets) . ' WHERE id = ? AND usuario_id = ?');
                            $stUpd->execute($params);
                        } else {
                            // Criar novo endereço
                            $endData['usuario_id'] = (int) $id;
                            // Campo 'nome' obrigatório na tabela enderecos
                            if (in_array('nome', $colsEnd, true) && !isset($endData['nome'])) {
                                $endData['nome'] = trim((string) ($dados['nome'] ?? 'Endereço Principal'));
                            }
                            if (in_array('created_at', $colsEnd, true)) $endData['created_at'] = date('Y-m-d H:i:s');
                            if (in_array('updated_at', $colsEnd, true)) $endData['updated_at'] = date('Y-m-d H:i:s');

                            $cols = implode(', ', array_keys($endData));
                            $placeholders = implode(', ', array_fill(0, count($endData), '?'));
                            $stIns = $dbEnd->prepare('INSERT INTO enderecos (' . $cols . ') VALUES (' . $placeholders . ')');
                            $stIns->execute(array_values($endData));
                        }
                    }
                } catch (\Exception $e) {
                    error_log('[ADMIN_EDITAR_USUARIO] Erro ao salvar endereço: ' . $e->getMessage());
                }

                // Auto-criar registro na tabela redirecionadores quando perfil = redirecionador
                try {
                    $perfilSalvo = strtolower(trim((string) ($dados['perfil'] ?? '')));
                    if ($perfilSalvo === 'redirecionador') {
                        $dbRed = \Config\Database::getConnection();
                        // Verificar se já existe
                        $stCheck = $dbRed->prepare("SELECT id FROM redirecionadores WHERE usuario_id = ? LIMIT 1");
                        $stCheck->execute([(int) $id]);
                        if (!$stCheck->fetchColumn()) {
                            // Verificar por email também
                            $emailUser = trim((string) ($dados['email'] ?? ''));
                            $stCheck2 = $dbRed->prepare("SELECT id FROM redirecionadores WHERE email = ? LIMIT 1");
                            $stCheck2->execute([$emailUser]);
                            $existeId = (int) $stCheck2->fetchColumn();
                            if ($existeId > 0) {
                                // Vincular usuario_id ao registro existente
                                $dbRed->prepare("UPDATE redirecionadores SET usuario_id = ? WHERE id = ?")->execute([(int) $id, $existeId]);
                            } else {
                                // Criar novo registro
                                $nomeUser = trim((string) ($dados['nome'] ?? 'Redirecionador'));
                                $suite = 'BR-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
                                $dbRed->prepare("INSERT INTO redirecionadores (usuario_id, nome, email, suite, status) VALUES (?, ?, ?, ?, 'ativo')")
                                    ->execute([(int) $id, $nomeUser, $emailUser, $suite]);
                            }
                        }
                    }
                } catch (\Exception $e) {
                    error_log('[ADMIN_EDITAR_USUARIO] Erro ao criar redirecionador: ' . $e->getMessage());
                }

                header('Location: /admin/usuarios/detalhes/' . (int)$id . '?success=1');
                exit;
            }

            $novoId = $helper->criarUsuario($dados);

            // Auto-criar registro na tabela redirecionadores quando perfil = redirecionador
            try {
                $perfilSalvo = strtolower(trim((string) ($dados['perfil'] ?? '')));
                if ($perfilSalvo === 'redirecionador' && $novoId > 0) {
                    $dbRed = \Config\Database::getConnection();
                    $emailUser = trim((string) ($dados['email'] ?? ''));
                    $nomeUser = trim((string) ($dados['nome'] ?? 'Redirecionador'));
                    $suite = 'BR-' . str_pad((string) $novoId, 5, '0', STR_PAD_LEFT);
                    $dbRed->prepare("INSERT INTO redirecionadores (usuario_id, nome, email, suite, status) VALUES (?, ?, ?, ?, 'ativo')")
                        ->execute([(int) $novoId, $nomeUser, $emailUser, $suite]);
                }
            } catch (\Exception $e) {
                error_log('[ADMIN_CRIAR_USUARIO] Erro ao criar redirecionador: ' . $e->getMessage());
            }

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

            // Buscar endereço da tabela enderecos (sempre preferir sobre campos da tabela usuarios)
            $endFields = ['cep', 'endereco', 'numero', 'complemento', 'bairro', 'cidade', 'estado', 'pais'];
            try {
                $dbEnd = \Config\Database::getConnection();
                $stEnd = $dbEnd->prepare("SELECT * FROM enderecos WHERE usuario_id = ? ORDER BY principal DESC, id DESC LIMIT 1");
                $stEnd->execute([(int) $id]);
                $endRow = $stEnd->fetch(\PDO::FETCH_ASSOC);
                if ($endRow) {
                    foreach ($endFields as $ef) {
                        if (!empty($endRow[$ef])) {
                            $usuario[$ef] = (string) $endRow[$ef];
                        }
                    }
                    // Fallback logradouro -> endereco
                    if (empty($usuario['endereco']) && !empty($endRow['logradouro'])) {
                        $usuario['endereco'] = (string) $endRow['logradouro'];
                    }
                }
            } catch (\Exception $e) {}
            
            $pedidos = $helper->getPedidosUsuario($id);

            $carteiraTransacoes = [];
            $carteiraRendimentoResumo = [
                'credito_usd' => 0.0,
                'credito_brl' => 0.0,
                'debito_usd' => 0.0,
                'debito_brl' => 0.0,
            ];
            try {
                $carteiraTransacoes = $helper->getTransacoesCarteiraUsuario((int) $usuario['id'], 50);
                $carteiraRendimentoResumo = $helper->getResumoRendimentoClubeCarteira((int) $usuario['id'], 200);
            } catch (\Exception $e) {
                $carteiraTransacoes = [];
            }

            $totalPedidos = is_array($pedidos) ? count($pedidos) : 0;
            $totalGastoBrl = 0.0;
            $totalGastoUsd = 0.0;
            if (!empty($pedidos) && is_array($pedidos)) {
                foreach ($pedidos as $p) {
                    $moedaPedido = strtoupper(trim((string)($p['moeda'] ?? $p['currency'] ?? 'BRL')));
                    $valor = (float)($p['total'] ?? $p['valor_total'] ?? 0);
                    if ($moedaPedido === 'USD') {
                        $totalGastoUsd += $valor;
                    } else {
                        $totalGastoBrl += $valor;
                    }
                }
            }

            $stats = [
                'total_pedidos' => $totalPedidos,
                'total_gasto_brl' => $totalGastoBrl,
                'total_gasto_usd' => $totalGastoUsd,
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
    <title>' . htmlspecialchars($usuario['nome']) . ' - Braziliana Admin</title>
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
                    <h1 class="page-title">' . htmlspecialchars($usuario['nome']) . '</h1>
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
                                    ' . (function() use ($stats) {
                                        $brl = (float)($stats['total_gasto_brl'] ?? 0);
                                        $usd = (float)($stats['total_gasto_usd'] ?? 0);
                                        if ($usd > 0 && $brl > 0) {
                                            $val = 'R$ ' . number_format($brl, 2, ',', '.') . '<br><span style="font-size:.85rem;" class="text-muted">US$ ' . number_format($usd, 2, ',', '.') . '</span>';
                                        } elseif ($usd > 0) {
                                            $val = 'US$ ' . number_format($usd, 2, ',', '.');
                                        } else {
                                            $val = 'R$ ' . number_format($brl, 2, ',', '.');
                                        }
                                        return '<h3 class="text-success">' . $val . '</h3>';
                                    })() . '
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
                                        <p><strong>CPF:</strong> ' . htmlspecialchars($usuario['cpf'] ?? ($usuario['documento'] ?? 'N/A')) . '</p>
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
                                <h5 class="mb-0"><i class="fas fa-chart-line me-2"></i>Rendimentos da Carteira (Clube)</h5>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <div class="border rounded p-3" style="background: rgba(16, 185, 129, 0.06); border-color: rgba(16, 185, 129, 0.18) !important;">
                                            <div class="small text-muted">Total creditado</div>
                                            <div class="fw-bold">'
                                            . ((float) ($carteiraRendimentoResumo['credito_brl'] ?? 0) > 0 ? ('R$ ' . number_format((float) ($carteiraRendimentoResumo['credito_brl'] ?? 0), 2, ',', '.') ) : 'R$ 0,00')
                                            . (((float) ($carteiraRendimentoResumo['credito_usd'] ?? 0) > 0) ? ('<br><span class="text-muted">US$ ' . number_format((float) ($carteiraRendimentoResumo['credito_usd'] ?? 0), 2, ',', '.') . '</span>') : '')
                                            . '</div>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="border rounded p-3" style="background: rgba(239, 68, 68, 0.06); border-color: rgba(239, 68, 68, 0.18) !important;">
                                            <div class="small text-muted">Total estornado</div>
                                            <div class="fw-bold">'
                                            . ((float) ($carteiraRendimentoResumo['debito_brl'] ?? 0) > 0 ? ('R$ ' . number_format((float) ($carteiraRendimentoResumo['debito_brl'] ?? 0), 2, ',', '.') ) : 'R$ 0,00')
                                            . (((float) ($carteiraRendimentoResumo['debito_usd'] ?? 0) > 0) ? ('<br><span class="text-muted">US$ ' . number_format((float) ($carteiraRendimentoResumo['debito_usd'] ?? 0), 2, ',', '.') . '</span>') : '')
                                            . '</div>
                                        </div>
                                    </div>
                                </div>

                                <div class="table-responsive mt-3">
                                    <table class="table table-sm table-hover align-middle">
                                        <thead>
                                            <tr>
                                                <th>Data</th>
                                                <th>Descrição</th>
                                                <th class="text-end">Valor</th>
                                            </tr>
                                        </thead>
                                        <tbody>';

        if (empty($carteiraTransacoes)) {
            echo '<tr><td colspan="3" class="text-center text-muted py-3">Nenhuma movimentação encontrada.</td></tr>';
        } else {
            foreach ($carteiraTransacoes as $t) {
                $desc = (string) ($t['descricao'] ?? '');
                $tipo = strtolower(trim((string) ($t['tipo'] ?? '')));
                $isRend = (stripos($desc, 'Rendimento Clube') !== false);
                $vUsd = (float) ($t['valor_usd'] ?? 0);
                $vBrl = (float) ($t['valor_brl'] ?? 0);

                $valorStr = '-';
                if (abs($vBrl) > 0.00001) {
                    $valorStr = 'R$ ' . number_format(abs($vBrl), 2, ',', '.');
                } elseif (abs($vUsd) > 0.00001) {
                    $valorStr = 'US$ ' . number_format(abs($vUsd), 2, ',', '.');
                }
                $valorClass = ($tipo === 'debito') ? 'text-danger' : 'text-success';
                $rowClass = $isRend ? '' : 'text-muted';
                $dt = !empty($t['created_at']) ? date('d/m/Y H:i', strtotime((string) $t['created_at'])) : '-';

                echo '<tr class="' . $rowClass . '">' .
                    '<td style="white-space:nowrap;">' . $dt . '</td>' .
                    '<td>' . ($isRend ? '<span class="badge bg-light text-dark me-1">Clube</span>' : '') . htmlspecialchars($desc) . '</td>' .
                    '<td class="text-end ' . $valorClass . '" style="white-space:nowrap;">' . ($tipo === 'debito' ? '-' : '+') . ' ' . $valorStr . '</td>' .
                '</tr>';
            }
        }

        echo '                        </tbody>
                                    </table>
                                    <div class="small text-muted">Mostrando as últimas 50 movimentações.</div>
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
                                            $moedaPed = strtoupper(trim((string)($pedido['moeda'] ?? $pedido['currency'] ?? 'BRL')));
                                            $valorPed = (float)($pedido['valor_total'] ?? $pedido['total'] ?? 0);
                                            $valorFmt = ($moedaPed === 'USD')
                                                ? 'US$ ' . number_format($valorPed, 2, ',', '.')
                                                : 'R$ ' . number_format($valorPed, 2, ',', '.');
                                            echo '<tr>
                                                <td>#' . str_pad($pedido['id'], 6, '0', STR_PAD_LEFT) . '</td>
                                                <td>' . date('d/m/Y H:i', strtotime($pedido['created_at'])) . '</td>
                                                <td><span class="badge bg-' . ($pedido['status'] == 'pago' ? 'success' : 'warning') . '">' . ucfirst($pedido['status']) . '</span></td>
                                                <td>' . $valorFmt . '</td>
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

    public function impersonar(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'suporte', 'vendedor']);

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!empty($_SESSION['impersonation']['active'])) {
            $_SESSION['message'] = 'Impersonação já está ativa.';
            $_SESSION['message_type'] = 'warning';
            header('Location: /admin/usuarios');
            exit;
        }

        $id = (int) $request->getParam('id');
        if ($id <= 0) {
            $_SESSION['message'] = 'Usuário inválido.';
            $_SESSION['message_type'] = 'danger';
            header('Location: /admin/usuarios');
            exit;
        }

        $csrf = (string) $request->getParam('csrf_token', '');
        if (!$auth->validarCSRF($csrf)) {
            $_SESSION['message'] = 'Token de segurança inválido.';
            $_SESSION['message_type'] = 'danger';
            header('Location: /admin/usuarios');
            exit;
        }

        $admin = $auth->getUsuarioLogado();
        if (!$admin) {
            header('Location: /loginadmin');
            exit;
        }

        $adminPerfil = strtolower(trim((string) ($admin['perfil'] ?? '')));
        $adminRole = strtolower(trim((string) ($admin['role'] ?? '')));
        if (!in_array($adminPerfil, ['admin', 'suporte', 'vendedor'], true) && !in_array($adminRole, ['admin', 'suporte', 'vendedor'], true)) {
            $_SESSION['message'] = 'Acesso negado.';
            $_SESSION['message_type'] = 'danger';
            header('Location: /admin/usuarios');
            exit;
        }

        $uModel = new Usuario();
        $target = $uModel->find($id);
        if (!is_array($target) || empty($target['id'])) {
            $_SESSION['message'] = 'Usuário não encontrado.';
            $_SESSION['message_type'] = 'danger';
            header('Location: /admin/usuarios');
            exit;
        }

        // Normalizar perfil: checar 'perfil' e 'role', tratar vazio como 'cliente'
        $targetPerfil = '';
        if (!empty($target['perfil']) && trim((string) $target['perfil']) !== '') {
            $targetPerfil = strtolower(trim((string) $target['perfil']));
        } elseif (!empty($target['role']) && trim((string) $target['role']) !== '') {
            $targetPerfil = strtolower(trim((string) $target['role']));
        } else {
            $targetPerfil = 'cliente';
        }
        // Aceitar variações comuns de perfil de cliente (ex: 'customer' do WooCommerce)
        $perfisCliente = ['cliente', 'customer', 'subscriber', ''];
        if (!in_array($targetPerfil, $perfisCliente, true) && !str_contains($targetPerfil, 'customer')) {
            $_SESSION['message'] = 'Você só pode logar como clientes.';
            $_SESSION['message_type'] = 'warning';
            header('Location: /admin/usuarios');
            exit;
        }

        // Salvar snapshot do admin para restaurar depois
        $rememberToken = '';
        try {
            if (isset($_COOKIE['remember_token']) && is_string($_COOKIE['remember_token'])) {
                $rememberToken = (string) $_COOKIE['remember_token'];
            }
        } catch (\Exception $e) {
            $rememberToken = '';
        }
        $_SESSION['impersonation'] = [
            'active' => true,
            'admin_user' => [
                'id' => (int) ($admin['id'] ?? 0),
                'nome' => (string) ($admin['nome'] ?? ''),
                'email' => (string) ($admin['email'] ?? ''),
                'documento' => (string) ($admin['documento'] ?? ''),
                'perfil' => (string) ($admin['perfil'] ?? ''),
                'role' => (string) ($admin['role'] ?? ''),
                'avatar' => $admin['avatar'] ?? null,
            ],
            'target_user_id' => (int) $target['id'],
            'started_at' => time(),
            'remember_token' => $rememberToken,
        ];

        if ($rememberToken !== '') {
            $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
            if (PHP_VERSION_ID >= 70300) {
                setcookie('remember_token', '', [
                    'expires' => time() - 3600,
                    'path' => '/',
                    'secure' => $secure,
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]);
            } else {
                setcookie('remember_token', '', time() - 3600, '/; samesite=Lax', '', $secure, true);
            }
        }

        try {
            $auth->registrarLogAuditoria((int) ($admin['id'] ?? 0), 'impersonacao_iniciar', 'usuarios', (int) $target['id'], null, ['target_user_id' => (int) $target['id']]);
        } catch (\Exception $e) {
        }

        // Trocar sessão para o cliente sem emitir remember_token
        $auth->criarSessaoSemRemember($target);

        header('Location: /minha-conta');
        exit;
    }
}
