<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Página Não Encontrada - Brazilianashop</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #0b1f3a 0%, #1d4ed8 55%, #e2e8f0 120%);
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
            animation: pulse 2s infinite;
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
            color: #0b1f3a;
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
            color: #1d4ed8;
        }
        
        .error-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            opacity: 0.8;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: -1;
        }
        
        .particle {
            position: absolute;
            background: white;
            border-radius: 50%;
            opacity: 0.3;
            animation: float 6s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }
    </style>
</head>
<body>
    <div class="particles" id="particles"></div>
    
    <div class="container">
        <div class="error-container">
            <div class="error-icon">
                <i class="fas fa-search"></i>
            </div>
            
            <h1 class="error-code">404</h1>
            <h2 class="error-title">Página Não Encontrada</h2>
            <p class="error-description">
                Ops! A página que você está procurando não existe ou foi movida.
            </p>
            
            <div class="error-actions">
                <a href="/" class="btn-home">
                    <i class="fas fa-home me-2"></i> Página Inicial
                </a>
                <a href="/produtos" class="btn-home">
                    <i class="fas fa-shopping-bag me-2"></i> Ver Produtos
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
    
    <script>
        // Criar partículas animadas
        function createParticles() {
            const particlesContainer = document.getElementById('particles');
            const particleCount = 20;
            
            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                
                // Tamanho aleatório
                const size = Math.random() * 4 + 1;
                particle.style.width = size + 'px';
                particle.style.height = size + 'px';
                
                // Posição aleatória
                particle.style.left = Math.random() * 100 + '%';
                particle.style.top = Math.random() * 100 + '%';
                
                // Animação com delay aleatório
                particle.style.animationDelay = Math.random() * 6 + 's';
                
                particlesContainer.appendChild(particle);
            }
        }
        
        // Inicializar partículas quando a página carregar
        document.addEventListener('DOMContentLoaded', createParticles);
        
        // Adicionar efeito de parallax no mouse move
        document.addEventListener('mousemove', (e) => {
            const x = e.clientX / window.innerWidth;
            const y = e.clientY / window.innerHeight;
            
            const errorCode = document.querySelector('.error-code');
            if (errorCode) {
                errorCode.style.transform = `translate(${x * 10}px, ${y * 10}px)`;
            }
        });
    </script>
</body>
</html>
