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
            background: #f6f8fb;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .error-container {
            text-align: center;
            color: #0b1f3a;
        }
        
        .error-code {
            font-size: 8rem;
            font-weight: bold;
            margin: 0;
            text-shadow: none;
        }
        
        .error-title {
            font-size: 2.5rem;
            margin: 1rem 0;
            font-weight: 300;
        }
        
        .error-description {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            opacity: 1;
        }
        
        .btn-home {
            background: #0b1f3a;
            color: #ffffff;
            padding: 12px 30px;
            border-radius: 50px;
            text-decoration: none;
            font-weight: 500;
            transition: background-color 0.2s ease, filter 0.2s ease;
            display: inline-block;
            margin: 0 10px;
        }
        
        .btn-home:hover {
            filter: brightness(1.03);
        }

        .error-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 1;
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
