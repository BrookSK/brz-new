<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'Painel Administrativo - Braziliana Shop' ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #0b1f3a;
            --primary-color-2: #1d4ed8;
            --secondary-color: #94a3b8;
            --danger-color: #ef4444;

            --bg-gradient: linear-gradient(180deg, #0b1f3a 0%, #eef2f7 55%, #ffffff 100%);
            --primary-gradient: linear-gradient(135deg, #0b1f3a 0%, #1d4ed8 55%, #e2e8f0 120%);

            --radius-md: 14px;
            --shadow-sm: 0 6px 18px rgba(15, 23, 42, 0.08);
            --shadow-md: 0 10px 28px rgba(15, 23, 42, 0.10);
            --shadow-lg: 0 16px 44px rgba(15, 23, 42, 0.12);
        }

        html {
            height: 100%;
        }

        body {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            margin: 0;
            background: var(--bg-gradient);
            color: #0f172a;
        }

        .card,
        .dropdown-menu,
        .modal-content,
        .form-control,
        .form-select,
        .btn {
            border-radius: var(--radius-md);
        }

        .card {
            border: 1px solid rgba(148, 163, 184, 0.35);
            box-shadow: var(--shadow-sm);
        }

        .shadow {
            box-shadow: var(--shadow-md) !important;
        }

        .shadow-sm {
            box-shadow: var(--shadow-sm) !important;
        }

        .shadow-lg {
            box-shadow: var(--shadow-lg) !important;
        }

        .btn:not(:disabled):not(.disabled):hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-sm);
        }

        .btn:not(:disabled):not(.disabled):active {
            transform: translateY(0);
            box-shadow: none;
        }

        .btn-success {
            border: none;
            background: linear-gradient(135deg, #10b981 0%, #ecfdf5 100%);
            color: #064e3b;
        }

        .btn-info {
            border: none;
            background: linear-gradient(135deg, #38bdf8 0%, #ffffff 100%);
            color: #0b1f3a;
        }

        .btn-warning {
            border: none;
            background: linear-gradient(135deg, #f59e0b 0%, #fff7ed 100%);
            color: #7c2d12;
        }

        .btn-primary {
            border: none;
            background: var(--primary-gradient);
        }

        .admin-shell {
            flex: 1 0 auto;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }

        .admin-shell > .row {
            flex: 1 0 auto;
            min-height: 0;
        }

        .sidebar {
            min-height: 100vh;
            background: linear-gradient(180deg, #0b1f3a 10%, #1d4ed8 100%);
        }
        
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            border-radius: 0.35rem;
            margin: 0.2rem 0;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: #fff;
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .sidebar .sidebar-brand {
            color: #fff;
            font-weight: bold;
            padding: 1rem;
        }
        
        .border-left-primary {
            border-left: 0.25rem solid #1d4ed8 !important;
        }
        
        .border-left-success {
            border-left: 0.25rem solid #1cc88a !important;
        }
        
        .border-left-info {
            border-left: 0.25rem solid #38bdf8 !important;
        }
        
        .border-left-warning {
            border-left: 0.25rem solid #f6c23e !important;
        }
        
        .text-xs {
            font-size: 0.7rem;
        }
        
        .font-weight-bold {
            font-weight: 700 !important;
        }
        
        .text-gray-800 {
            color: #5a5c69 !important;
        }
        
        .text-gray-300 {
            color: #dddfeb !important;
        }
    </style>
</head>
<body>
    <div class="container-fluid admin-shell">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    
                    <a class="sidebar-brand d-flex align-items-center justify-content-center" href="/admin/dashboard">
                        <div class="sidebar-brand-icon">
                            <i class="fas fa-shipping-fast"></i>
                        </div>
                        <div class="sidebar-brand-text mx-3">Braziliana Shop Admin</div>
                    </a>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="/admin/dashboard">
                                <i class="fas fa-fw fa-tachometer-alt"></i>
                                <span>Dashboard</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/admin/pedidos">
                                <i class="fas fa-fw fa-shopping-cart"></i>
                                <span>Pedidos</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/admin/consolidar-pedidos">
                                <i class="fas fa-fw fa-compress"></i>
                                <span>Consolidar Pedidos</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/admin/produtos">
                                <i class="fas fa-fw fa-box"></i>
                                <span>Produtos</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/admin/usuarios">
                                <i class="fas fa-fw fa-users"></i>
                                <span>Usuários</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="/admin/configuracoes">
                                <i class="fas fa-fw fa-cog"></i>
                                <span>Configurações</span>
                            </a>
                        </li>
                    </ul>
                    
                    <hr class="sidebar-divider">
                    
                    <div class="nav-item">
                        <a class="nav-link" href="/logout">
                            <i class="fas fa-fw fa-sign-out-alt"></i>
                            <span>Sair</span>
                        </a>
                    </div>
                </div>
            </nav>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 d-flex flex-column">
                <?php if (isset($_SESSION['message'])): ?>
                    <div class="alert alert-<?= $_SESSION['message_type'] ?? 'info' ?> alert-dismissible fade show mt-3" role="alert">
                        <?= $_SESSION['message'] ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                    <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
                <?php endif; ?>
                
                <?= $content ?? '' ?>
            </main>
        </div>

        <footer class="border-top py-3 mt-auto">
            <div class="text-center text-muted small">&copy; 2024 Braziliana Shop. Todos os direitos reservados.</div>
        </footer>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</body>
</html>
