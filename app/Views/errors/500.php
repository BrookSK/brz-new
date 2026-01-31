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
