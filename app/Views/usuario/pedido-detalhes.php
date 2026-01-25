<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Detalhes do Pedido #<?= htmlspecialchars($pedido['codigo_pedido'] ?? $pedido['id']) ?> - Brazilianashop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #6366f1;
            --secondary-color: #6c757d;
            --success-color: #198754;
            --warning-color: #ffc107;
            --danger-color: #dc3545;
            --info-color: #0dcaf0;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --border-radius: 0.5rem;
            --box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
            --box-shadow-lg: 0 1rem 3rem rgba(0, 0, 0, 0.175);
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            color: #343a40;
            margin: 0;
            padding: 0;
        }

        .main-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 1rem;
            margin: 2rem auto;
            max-width: 1200px;
            box-shadow: var(--box-shadow-lg);
            backdrop-filter: blur(10px);
            overflow: hidden;
        }

        .header-section {
            background: linear-gradient(135deg, var(--primary-color), #8b5cf6);
            color: white;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .breadcrumb {
            background: rgba(255, 255, 255, 0.1);
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }

        .breadcrumb-item a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: color 0.3s ease;
        }

        .breadcrumb-item a:hover {
            color: white;
        }

        .breadcrumb-item.active {
            color: rgba(255, 255, 255, 1);
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 2rem;
            font-weight: 600;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .card {
            border: none;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            transition: all 0.3s ease;
            overflow: hidden;
            margin-bottom: 1.5rem;
        }

        .card:hover {
            transform: translateY(-2px);
            box-shadow: var(--box-shadow-lg);
        }

        .card-header {
            background: white;
            border-bottom: 1px solid #e9ecef;
            padding: 1.5rem;
            font-weight: 600;
            color: var(--dark-color);
        }

        .card-body {
            padding: 1.5rem;
        }

        .timeline {
            position: relative;
            padding-left: 2rem;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 0.75rem;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #e9ecef;
        }

        .timeline-item {
            position: relative;
            margin-bottom: 2rem;
        }

        .timeline-marker {
            position: absolute;
            left: -2rem;
            top: 0;
            width: 2rem;
            height: 2rem;
            background: white;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #e9ecef;
            z-index: 1;
        }

        .timeline-marker i {
            font-size: 0.75rem;
        }

        .timeline-content {
            background: white;
            padding: 1rem 1.5rem;
            border-radius: var(--border-radius);
            border-left: 4px solid var(--primary-color);
            margin-left: 1rem;
        }

        .timeline-content h6 {
            color: var(--primary-color);
            margin-bottom: 0.5rem;
            font-weight: 600;
        }

        .timeline-content p {
            color: var(--secondary-color);
            font-size: 0.875rem;
            margin-bottom: 0;
        }

        .timeline-content small {
            color: var(--secondary-color);
            font-size: 0.75rem;
        }

        .product-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid #e9ecef;
            transition: background-color 0.3s ease;
        }

        .product-item:hover {
            background-color: var(--light-color);
        }

        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: var(--border-radius);
            margin-right: 1rem;
        }

        .product-info h6 {
            margin-bottom: 0.25rem;
            color: var(--dark-color);
            font-weight: 600;
        }

        .product-info small {
            color: var(--secondary-color);
            font-size: 0.75rem;
        }

        .quantity-badge {
            background: var(--primary-color);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 1rem;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .price-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #e9ecef;
        }

        .price-row:last-child {
            border-bottom: none;
            font-weight: 600;
            font-size: 1.1rem;
            color: var(--primary-color);
        }

        .address-section {
            background: var(--light-color);
            padding: 1.5rem;
            border-radius: var(--border-radius);
        }

        .address-section h6 {
            color: var(--dark-color);
            margin-bottom: 1rem;
            font-weight: 600;
        }

        .address-section address {
            margin: 0;
            color: var(--dark-color);
            line-height: 1.6;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-top: 2rem;
        }

        .btn {
            padding: 0.75rem 1.5rem;
            border-radius: var(--border-radius);
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--box-shadow);
        }

        .btn-primary {
            background: var(--primary-color);
            color: white;
            border: none;
        }

        .btn-primary:hover {
            background: #5a67d8;
            color: white;
        }

        .btn-outline-secondary {
            background: transparent;
            color: var(--secondary-color);
            border: 1px solid var(--secondary-color);
        }

        .btn-outline-secondary:hover {
            background: var(--secondary-color);
            color: white;
        }

        .sidebar {
            background: white;
            border-radius: var(--border-radius);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--box-shadow);
        }

        .user-avatar img {
            border: 3px solid #fff;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }

        .nav-link {
            border-radius: 8px;
            padding: 12px 16px;
            margin-bottom: 8px;
            transition: all 0.3s ease;
            color: #6c757d;
            text-decoration: none;
            display: flex;
            align-items: center;
        }

        .nav-link:hover {
            background-color: #f8f9fa;
            color: #495057;
            transform: translateX(5px);
        }

        .nav-link.active {
            background-color: var(--primary-color);
            color: white !important;
        }

        @media (max-width: 768px) {
            .main-container {
                margin: 1rem;
                border-radius: 0.5rem;
            }

            .header-section {
                padding: 1.5rem;
            }

            .card {
                margin-bottom: 1rem;
            }

            .product-item {
                flex-direction: column;
                text-align: center;
                padding: 1rem;
            }

            .product-image {
                margin-right: 0;
                margin-bottom: 1rem;
            }

            .action-buttons {
                flex-direction: column;
                align-items: stretch;
            }

            .btn {
                justify-content: center;
            }
        }

        @media print {
            body {
                background: white;
                color: black;
            }

            .main-container {
                box-shadow: none;
                background: white;
            }

            .header-section,
            .card {
                border: 1px solid #000 !important;
                box-shadow: none !important;
            }

            .timeline::before {
                background: #000 !important;
            }

            .action-buttons {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="main-container">
        <!-- Header Section -->
        <div class="header-section">
            <div class="container">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="/meus-pedidos" style="color: rgba(255,255,255,0.8);"><i class="fas fa-arrow-left me-2"></i> Meus Pedidos</a></li>
                        <li class="breadcrumb-item active">Detalhes do Pedido</li>
                    </ol>
                </nav>
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1 class="h3 mb-0">Pedido #<?= htmlspecialchars($pedido['codigo_pedido'] ?? $pedido['id']) ?></h1>
                        <p class="mb-0 opacity-75">Data: <?= date('d/m/Y \à\s H:i', strtotime($pedido['created_at'])) ?></p>
                    </div>
                    <div class="text-end">
                        <span class="status-badge bg-<?= getStatusColor($pedido['status']) ?>">
                            <?= getStatusText($pedido['status']) ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="container">
            <div class="row">
                <!-- Sidebar -->
                <div class="col-lg-3">
                    <!-- Profile Card -->
                    <div class="sidebar">
                        <div class="text-center">
                            <div class="user-avatar mx-auto mb-3">
                                <img src="https://ui-avatars.com/api/?name=<?= urlencode($usuario['nome']) ?>&background=6366f1&color=fff&size=128" 
                                     alt="<?= htmlspecialchars($usuario['nome']) ?>" 
                                     class="rounded-circle" width="80" height="80">
                            </div>
                            <h5 class="mb-1"><?= htmlspecialchars($usuario['nome']) ?></h5>
                            <p class="text-muted small mb-3"><?= htmlspecialchars($usuario['email']) ?></p>
                            <span class="badge bg-primary px-3 py-2"><?= ucfirst($usuario['perfil']) ?></span>
                        </div>
                    </div>
                    
                    <!-- Quick Menu -->
                    <div class="sidebar">
                        <h6 class="mb-3">Menu Rápido</h6>
                        <nav class="nav flex-column">
                            <a class="nav-link mb-2" href="/minha-conta">
                                <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                            </a>
                            <a class="nav-link mb-2" href="/meus-dados">
                                <i class="fas fa-user me-2"></i> Meus Dados
                            </a>
                            <a class="nav-link mb-2 active" href="/meus-pedidos">
                                <i class="fas fa-shopping-bag me-2"></i> Meus Pedidos
                            </a>
                            <a class="nav-link mb-2" href="/carrinho">
                                <i class="fas fa-shopping-cart me-2"></i> Meu Carrinho
                                <?php if (!empty($_SESSION['carrinho'])): ?>
                                    <span class="badge bg-danger rounded-pill ms-auto"><?= count($_SESSION['carrinho']) ?></span>
                                <?php endif; ?>
                            </a>
                            <hr class="my-3">
                            <a class="nav-link text-danger mb-2" href="/logout">
                                <i class="fas fa-sign-out-alt me-2"></i> Sair
                            </a>
                        </nav>
                    </div>
                </div>
                
                <!-- Main Content -->
                <div class="col-lg-9">
                    <!-- Status Timeline -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-truck me-2"></i> Status do Pedido
                            </h5>
                        </div>
                        <div class="card-body">
                            <div class="timeline">
                                <?php if (!empty($historico)): ?>
                                    <?php foreach ($historico as $index => $item): ?>
                                        <div class="timeline-item">
                                            <div class="timeline-marker">
                                                <i class="fas fa-check-circle text-success"></i>
                                            </div>
                                            <div class="timeline-content">
                                                <h6><?= htmlspecialchars($item['novo_status'] ?? 'Status atualizado') ?></h6>
                                                <p class="mb-0"><?= htmlspecialchars($item['observacao'] ?? 'Sem observação') ?></p>
                                                <small><?= date('d/m/Y H:i', strtotime($item['created_at'])) ?> - Por: <?= htmlspecialchars($item['usuario_alterou'] ?? 'Sistema') ?></small>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        Nenhum histórico de status disponível para este pedido.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Order Items -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="mb-0">
                                <i class="fas fa-box me-2"></i> Itens do Pedido
                                <span class="badge bg-primary ms-2"><?= count($pedido['items']) ?> itens</span>
                            </h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($pedido['items'])): ?>
                                <?php foreach ($pedido['items'] as $item): ?>
                                    <div class="product-item">
                                        <img src="https://novobr.brazilianashop.com.br/uploads/produtos/<?= $item['imagem'] ?? 'default.jpg' ?>" 
                                             alt="<?= htmlspecialchars($item['nome_produto'] ?? 'Produto') ?>" 
                                             class="product-image">
                                        <div class="product-info flex-grow-1">
                                            <h6><?= htmlspecialchars($item['nome_produto'] ?? 'Produto sem nome') ?></h6>
                                            <small class="text-muted"><?= htmlspecialchars($item['referencia'] ?? '') ?></small>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="quantity-badge"><?= $item['quantidade'] ?? 1 ?></span>
                                            <span class="text-muted">x</span>
                                            <span class="fw-bold">R$ <?= number_format($item['preco_unitario'] ?? 0, 2, ',', '.') ?></span>
                                        </div>
                                        <div class="text-end">
                                            <small class="text-muted">Subtotal: R$ <?= number_format($item['subtotal'] ?? 0, 2, ',', '.') ?></small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-exclamation-triangle me-2"></i>
                                    Nenhum item encontrado neste pedido.
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Order Summary -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-map-marker-alt me-2"></i> Endereço de Entrega
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <address class="mb-0">
                                        <?= htmlspecialchars($pedido['endereco_entrega'] ?? 'Não informado') ?>, <?= htmlspecialchars($pedido['numero_entrega'] ?? '') ?><br>
                                        <?= htmlspecialchars($pedido['complemento_entrega'] ?? '') ?><br>
                                        <?= htmlspecialchars($pedido['bairro_entrega'] ?? '') ?><br>
                                        <?= htmlspecialchars($pedido['cidade_entrega'] ?? '') ?> - <?= htmlspecialchars($pedido['estado_entrega'] ?? '') ?><br>
                                        CEP: <?= htmlspecialchars($pedido['cep_entrega'] ?? '') ?>
                                    </address>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="card">
                                <div class="card-header">
                                    <h5 class="mb-0">
                                        <i class="fas fa-calculator me-2"></i> Resumo do Pedido
                                    </h5>
                                </div>
                                <div class="card-body">
                                    <div class="price-row">
                                        <span>Subtotal:</span>
                                        <span>R$ <?= number_format($pedido['subtotal_produtos'] ?? 0, 2, ',', '.') ?></span>
                                    </div>
                                    <div class="price-row">
                                        <span>Frete:</span>
                                        <span>R$ <?= number_format($pedido['valor_frete'] ?? 0, 2, ',', '.') ?></span>
                                    </div>
                                    <div class="price-row">
                                        <span>Taxa de Serviço:</span>
                                        <span>R$ <?= number_format($pedido['taxa_servico'] ?? 0, 2, ',', '.') ?></span>
                                    </div>
                                    <div class="price-row">
                                        <span>Impostos:</span>
                                        <span>R$ <?= number_format($pedido['valor_impostos'] ?? 0, 2, ',', '.') ?></span>
                                    </div>
                                    <hr>
                                    <div class="price-row">
                                        <span>Total:</span>
                                        <span class="text-primary">R$ <?= number_format($pedido['valor_total'] ?? 0, 2, ',', '.') ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="action-buttons">
                        <a href="/meus-pedidos" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i> Voltar
                        </a>
                        <button class="btn btn-primary" onclick="window.print()">
                            <i class="fas fa-print me-2"></i> Imprimir
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

<script>
// Helper functions
function getStatusColor(status) {
    const colors = {
        'pendente' : 'warning',
        'processando' : 'info',
        'enviado' : 'primary',
        'entregue' : 'success',
        'cancelado' : 'danger',
        'pago' : 'success'
    };
    return colors[status] ?? 'secondary';
}

function getStatusText(status) {
    const texts = {
        'pendente' : 'Pendente',
        'processando' : 'Processando',
        'enviado' : 'Enviado',
        'entregue' : 'Entregue',
        'cancelado' : 'Cancelado',
        'pago' : 'Pago'
    };
    return texts[status] ?? status.charAt(0).toUpperCase() + status.slice(1);
}
</script>

</body>
</html>
