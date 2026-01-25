<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso Negado - Brazilianashop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a24 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .error-container {
            text-align: center;
            color: white;
        }
        
        .error-code {
            font-size: 8rem;
            font-weight: bold;
            margin: 0;
            text-shadow: 2px 2px 4px rgba(0,0,0,0.3);
            animation: lock 1s ease-in-out;
        }
        
        .error-title {
            font-size: 2.5rem;
            margin: 1rem 0;
            font-weight: 300;
        }
        
        .error-description {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }
        
        .btn-home {
            background: white;
            color: #ee5a24;
            padding: 12px 30px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-block;
            margin: 0 10px;
        }
        
        .btn-home:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.2);
            color: #ff6b6b;
        }
        
        .error-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.8;
        }
        
        @keyframes lock {
            0% { transform: scale(1) rotate(0deg); }
            50% { transform: scale(1.1) rotate(5deg); }
            100% { transform: scale(1) rotate(0deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="error-container">
            <div class="error-icon">
                <i class="fas fa-lock"></i>
            </div>
            
            <h1 class="error-code">403</h1>
            <h2 class="error-title">Acesso Negado</h2>
            <p class="error-description">
                Você não tem permissão para acessar esta página. Faça login ou contate o administrador.
            </p>
            
            <div class="error-actions">
                <a href="/login" class="btn-home">
                    <i class="fas fa-sign-in-alt me-2"></i> Fazer Login
                </a>
                <a href="/" class="btn-home">
                    <i class="fas fa-home me-2"></i> Página Inicial
                </a>
            </div>
            
            <div class="mt-4">
                <small class="opacity-75">
                    <i class="fas fa-info-circle me-1"></i>
                    Se você acredita que isso é um erro, entre em contato com nosso suporte.
                </small>
            </div>
        </div>
    </div>
</body>
</html>
