<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manutenção - Brazilianashop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .maintenance-container {
            text-align: center;
            color: white;
            max-width: 600px;
        }
        
        .maintenance-icon {
            font-size: 5rem;
            margin-bottom: 2rem;
            animation: spin 3s linear infinite;
        }
        
        .maintenance-title {
            font-size: 2.5rem;
            margin: 1rem 0;
            font-weight: 300;
        }
        
        .maintenance-description {
            font-size: 1.2rem;
            margin-bottom: 2rem;
            opacity: 0.9;
        }
        
        .progress {
            height: 8px;
            background: rgba(255,255,255,0.2);
            border-radius: 4px;
            overflow: hidden;
            margin: 2rem 0;
        }
        
        .progress-bar {
            background: white;
            animation: progress 5s ease-in-out infinite;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        @keyframes progress {
            0% { width: 0%; }
            50% { width: 70%; }
            100% { width: 100%; }
        }
        
        .contact-info {
            margin-top: 3rem;
            padding: 1.5rem;
            background: rgba(255,255,255,0.1);
            border-radius: 10px;
            backdrop-filter: blur(10px);
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="maintenance-container">
            <div class="maintenance-icon">
                <i class="fas fa-cogs"></i>
            </div>
            
            <h1 class="maintenance-title">Em Manutenção</h1>
            <p class="maintenance-description">
                Estamos trabalhando para melhorar nosso serviço. Voltaremos em breve!
            </p>
            
            <div class="progress">
                <div class="progress-bar" role="progressbar"></div>
            </div>
            
            <div class="contact-info">
                <h5><i class="fas fa-info-circle me-2"></i>Informações</h5>
                <p class="mb-2">
                    <i class="fas fa-clock me-2"></i>
                    Previsão de retorno: algumas horas
                </p>
                <p class="mb-2">
                    <i class="fas fa-envelope me-2"></i>
                    Email: suporte@brazilianashop.com.br
                </p>
                <p>
                    <i class="fas fa-phone me-2"></i>
                    Telefone: (11) 9999-9999
                </p>
            </div>
            
            <div class="mt-4">
                <small class="opacity-75">
                    <i class="fas fa-heart me-1"></i>
                    Agradecemos sua paciência e compreensão.
                </small>
            </div>
        </div>
    </div>
</body>
</html>
