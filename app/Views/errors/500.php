<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erro do Servidor - Brazilianashop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #ef4444 0%, #fee2e2 100%);
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
            animation: shake 0.5s;
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
            color: #7f1d1d;
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
            color: #7f1d1d;
        }
        
        .error-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.8;
        }
        
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="error-container">
            <div class="error-icon">
                <i class="fas fa-exclamation-triangle"></i>
            </div>
            
            <h1 class="error-code">500</h1>
            <h2 class="error-title">Erro do Servidor</h2>
            <p class="error-description">
                Ocorreu um erro inesperado. Nossa equipe já foi notificada e está trabalhando para resolver.
            </p>
            
            <div class="error-actions">
                <a href="/" class="btn-home">
                    <i class="fas fa-home me-2"></i> Página Inicial
                </a>
                <a href="/contato" class="btn-home">
                    <i class="fas fa-envelope me-2"></i> Contato
                </a>
            </div>
            
            <div class="mt-4">
                <small class="opacity-75">
                    <i class="fas fa-info-circle me-1"></i>
                    Tente novamente em alguns minutos. Se o problema persistir, entre em contato conosco.
                </small>
            </div>
        </div>
    </div>
</body>
</html>
