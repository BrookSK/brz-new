<?php
namespace App\Controllers;

use App\Core\Request;

class AdminConfiguracoesController extends Controller {
    
    public function index(Request $request) {
        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            
            // Buscar configurações
            $stmt = $pdo->query("SELECT * FROM configuracoes ORDER BY categoria, chave");
            $configuracoes = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            
            // Organizar por categoria
            $config = [];
            foreach ($configuracoes as $c) {
                $config[$c['categoria']][] = $c;
            }
            
        } catch (\Exception $e) {
            $config = [];
        }
        
        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações - BRZ Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar { min-height: 100vh; background: linear-gradient(180deg, #4e73df 10%, #224abe 100%); }
        .sidebar .nav-link { color: rgba(255, 255, 255, 0.8); border-radius: 0.35rem; margin: 0.2rem 0; }
        .sidebar .nav-link:hover, .sidebar .nav-link.active { color: #fff; background-color: rgba(255, 255, 255, 0.1); }
        .sidebar .sidebar-brand { color: #fff; font-weight: bold; padding: 1rem; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <nav class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <a class="sidebar-brand d-flex align-items-center justify-content-center" href="/admin/dashboard">
                        <div class="sidebar-brand-icon"><i class="fas fa-shipping-fast"></i></div>
                        <div class="sidebar-brand-text mx-3">BRZ Admin</div>
                    </a>
                    <ul class="nav flex-column">
                        <li class="nav-item"><a class="nav-link" href="/admin/dashboard"><i class="fas fa-fw fa-tachometer-alt"></i><span>Dashboard</span></a></li>
                        <li class="nav-item"><a class="nav-link" href="/admin/produtos"><i class="fas fa-fw fa-box"></i><span>Produtos</span></a></li>
                        <li class="nav-item"><a class="nav-link" href="/admin/pedidos"><i class="fas fa-fw fa-shopping-cart"></i><span>Pedidos</span></a></li>
                        <li class="nav-item"><a class="nav-link" href="/admin/usuarios"><i class="fas fa-fw fa-users"></i><span>Usuários</span></a></li>
                        <li class="nav-item"><a class="nav-link" href="/admin/pagamentos"><i class="fas fa-fw fa-credit-card"></i><span>Pagamentos</span></a></li>
                        <li class="nav-item"><a class="nav-link active" href="/admin/configuracoes"><i class="fas fa-fw fa-cog"></i><span>Configurações</span></a></li>
                    </ul>
                    <hr class="sidebar-divider">
                    <div class="nav-item"><a class="nav-link" href="/logout"><i class="fas fa-fw fa-sign-out-alt"></i><span>Sair</span></a></div>
                </div>
            </nav>
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Configurações</h1>
                </div>
                
                <div class="row">
                    <div class="col-md-3">
                        <div class="nav flex-column nav-pills" id="v-pills-tab" role="tablist">
                            <button class="nav-link active" id="v-pills-loja-tab" data-bs-toggle="pill" data-bs-target="#v-pills-loja" type="button">
                                <i class="fas fa-store"></i> Loja
                            </button>
                            <button class="nav-link" id="v-pills-email-tab" data-bs-toggle="pill" data-bs-target="#v-pills-email" type="button">
                                <i class="fas fa-envelope"></i> Email
                            </button>
                            <button class="nav-link" id="v-pills-entrega-tab" data-bs-toggle="pill" data-bs-target="#v-pills-entrega" type="button">
                                <i class="fas fa-truck"></i> Entrega
                            </button>
                            <button class="nav-link" id="v-pills-seo-tab" data-bs-toggle="pill" data-bs-target="#v-pills-seo" type="button">
                                <i class="fas fa-search"></i> SEO
                            </button>
                            <button class="nav-link" id="v-pills-sistema-tab" data-bs-toggle="pill" data-bs-target="#v-pills-sistema" type="button">
                                <i class="fas fa-cogs"></i> Sistema
                            </button>
                        </div>
                    </div>
                    
                    <div class="col-md-9">
                        <form method="POST" action="/admin/configuracoes/salvar">
                            <div class="tab-content" id="v-pills-tabContent">
                                <!-- Configurações da Loja -->
                                <div class="tab-pane fade show active" id="v-pills-loja" role="tabpanel">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="mb-0">Configurações da Loja</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label class="form-label">Nome da Loja</label>
                                                <input type="text" class="form-control" name="loja_nome" value="' . $this->getConfigValue($config, 'loja', 'nome', 'BRZ Shop') . '">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Descrição</label>
                                                <textarea class="form-control" name="loja_descricao" rows="3">' . $this->getConfigValue($config, 'loja', 'descricao', '') . '</textarea>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Email de Contato</label>
                                                <input type="email" class="form-control" name="loja_email" value="' . $this->getConfigValue($config, 'loja', 'email', 'contato@brzshop.com.br') . '">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Telefone</label>
                                                <input type="tel" class="form-control" name="loja_telefone" value="' . $this->getConfigValue($config, 'loja', 'telefone', '') . '">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Endereço</label>
                                                <input type="text" class="form-control" name="loja_endereco" value="' . $this->getConfigValue($config, 'loja', 'endereco', '') . '">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Logo URL</label>
                                                <input type="url" class="form-control" name="loja_logo" value="' . $this->getConfigValue($config, 'loja', 'logo', '') . '">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Configurações de Email -->
                                <div class="tab-pane fade" id="v-pills-email" role="tabpanel">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="mb-0">Configurações de Email</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label class="form-label">Driver SMTP</label>
                                                <select class="form-select" name="email_driver">
                                                    <option value="smtp" ' . ($this->getConfigValue($config, 'email', 'driver', 'smtp') === 'smtp' ? 'selected' : '') . '>SMTP</option>
                                                    <option value="mail" ' . ($this->getConfigValue($config, 'email', 'driver', '') === 'mail' ? 'selected' : '') . '>PHP Mail</option>
                                                    <option value="sendmail" ' . ($this->getConfigValue($config, 'email', 'driver', '') === 'sendmail' ? 'selected' : '') . '>Sendmail</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Host SMTP</label>
                                                <input type="text" class="form-control" name="email_host" value="' . $this->getConfigValue($config, 'email', 'host', '') . '">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Porta SMTP</label>
                                                <input type="number" class="form-control" name="email_port" value="' . $this->getConfigValue($config, 'email', 'port', '587') . '">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Usuário SMTP</label>
                                                <input type="text" class="form-control" name="email_username" value="' . $this->getConfigValue($config, 'email', 'username', '') . '">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Senha SMTP</label>
                                                <input type="password" class="form-control" name="email_password" value="' . $this->getConfigValue($config, 'email', 'password', '') . '">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Criptografia</label>
                                                <select class="form-select" name="email_encryption">
                                                    <option value="tls" ' . ($this->getConfigValue($config, 'email', 'encryption', 'tls') === 'tls' ? 'selected' : '') . '>TLS</option>
                                                    <option value="ssl" ' . ($this->getConfigValue($config, 'email', 'encryption', '') === 'ssl' ? 'selected' : '') . '>SSL</option>
                                                    <option value="" ' . ($this->getConfigValue($config, 'email', 'encryption', '') === '' ? 'selected' : '') . '>Nenhuma</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Email de Envio</label>
                                                <input type="email" class="form-control" name="email_from" value="' . $this->getConfigValue($config, 'email', 'from', 'noreply@brzshop.com.br') . '">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Nome de Envio</label>
                                                <input type="text" class="form-control" name="email_from_name" value="' . $this->getConfigValue($config, 'email', 'from_name', 'BRZ Shop') . '">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Configurações de Entrega -->
                                <div class="tab-pane fade" id="v-pills-entrega" role="tabpanel">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="mb-0">Configurações de Entrega</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label class="form-label">Moeda Padrão</label>
                                                <select class="form-select" name="moeda_padrao">
                                                    <option value="USD" ' . ($this->getConfigValue($config, 'entrega', 'moeda_padrao', 'USD') === 'USD' ? 'selected' : '') . '>USD - Dólar Americano</option>
                                                    <option value="BRL" ' . ($this->getConfigValue($config, 'entrega', 'moeda_padrao', 'USD') === 'BRL' ? 'selected' : '') . '>BRL - Real Brasileiro</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Taxa de Serviço (USD por kg)</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">$</span>
                                                    <input type="text" class="form-control" name="taxa_servico_kg" value="' . $this->getConfigValue($config, 'entrega', 'taxa_servico_kg', '39') . '">
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Frete Grátis Acima de</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">$</span>
                                                    <input type="text" class="form-control" name="frete_gratis_acima" value="' . $this->getConfigValue($config, 'entrega', 'frete_gratis_acima', '0') . '">
                                                </div>
                                                <small class="text-muted">Deixe como 0 para frete sempre grátis</small>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Valor Padrão do Frete (USD por kg)</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">$</span>
                                                    <input type="text" class="form-control" name="frete_padrao" value="' . $this->getConfigValue($config, 'entrega', 'frete_padrao', '15') . '">
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Prazo Padrão (dias)</label>
                                                <input type="number" class="form-control" name="prazo_padrao" value="' . $this->getConfigValue($config, 'entrega', 'prazo_padrao', '30') . '">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">CEP de Origem</label>
                                                <input type="text" class="form-control" name="cep_origem" value="' . $this->getConfigValue($config, 'entrega', 'cep_origem', '') . '">
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="calcular_automatico" ' . ($this->getConfigValue($config, 'entrega', 'calcular_automatico', '1') === '1' ? 'checked' : '') . '>
                                                <label class="form-check-label">Calcular frete automaticamente</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Configurações SEO -->
                                <div class="tab-pane fade" id="v-pills-seo" role="tabpanel">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="mb-0">Configurações SEO</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label class="form-label">Meta Title Padrão</label>
                                                <input type="text" class="form-control" name="seo_title" value="' . $this->getConfigValue($config, 'seo', 'title', 'BRZ Shop - Produtos de Qualidade') . '">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Meta Description Padrão</label>
                                                <textarea class="form-control" name="seo_description" rows="3">' . $this->getConfigValue($config, 'seo', 'description', 'Encontre os melhores produtos na BRZ Shop') . '</textarea>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Palavras-chave</label>
                                                <input type="text" class="form-control" name="seo_keywords" value="' . $this->getConfigValue($config, 'seo', 'keywords', '') . '">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Google Analytics</label>
                                                <input type="text" class="form-control" name="google_analytics" placeholder="UA-XXXXXXXX-X" value="' . $this->getConfigValue($config, 'seo', 'google_analytics', '') . '">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Google Tag Manager</label>
                                                <input type="text" class="form-control" name="google_tag_manager" placeholder="GTM-XXXXXXX" value="' . $this->getConfigValue($config, 'seo', 'google_tag_manager', '') . '">
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="sitemap_gerado" ' . ($this->getConfigValue($config, 'seo', 'sitemap_gerado', '1') === '1' ? 'checked' : '') . '>
                                                <label class="form-check-label">Gerar Sitemap automaticamente</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Configurações do Sistema -->
                                <div class="tab-pane fade" id="v-pills-sistema" role="tabpanel">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="mb-0">Configurações do Sistema</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label class="form-label">Fuso Horário</label>
                                                <select class="form-select" name="timezone">
                                                    <option value="America/Sao_Paulo" ' . ($this->getConfigValue($config, 'sistema', 'timezone', 'America/Sao_Paulo') === 'America/Sao_Paulo' ? 'selected' : '') . '>America/São Paulo</option>
                                                    <option value="America/New_York" ' . ($this->getConfigValue($config, 'sistema', 'timezone', '') === 'America/New_York' ? 'selected' : '') . '>America/New York</option>
                                                    <option value="Europe/London" ' . ($this->getConfigValue($config, 'sistema', 'timezone', '') === 'Europe/London' ? 'selected' : '') . '>Europe/London</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Idioma Padrão</label>
                                                <select class="form-select" name="idioma">
                                                    <option value="pt-BR" ' . ($this->getConfigValue($config, 'sistema', 'idioma', 'pt-BR') === 'pt-BR' ? 'selected' : '') . '>Português (Brasil)</option>
                                                    <option value="en-US" ' . ($this->getConfigValue($config, 'sistema', 'idioma', '') === 'en-US' ? 'selected' : '') . '>English (US)</option>
                                                    <option value="es-ES" ' . ($this->getConfigValue($config, 'sistema', 'idioma', '') === 'es-ES' ? 'selected' : '') . '>Español</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Moeda Padrão</label>
                                                <select class="form-select" name="moeda">
                                                    <option value="BRL" ' . ($this->getConfigValue($config, 'sistema', 'moeda', 'BRL') === 'BRL' ? 'selected' : '') . '>Real (BRL)</option>
                                                    <option value="USD" ' . ($this->getConfigValue($config, 'sistema', 'moeda', '') === 'USD' ? 'selected' : '') . '>Dólar (USD)</option>
                                                    <option value="EUR" ' . ($this->getConfigValue($config, 'sistema', 'moeda', '') === 'EUR' ? 'selected' : '') . '>Euro (EUR)</option>
                                                </select>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="manutencao" ' . ($this->getConfigValue($config, 'sistema', 'manutencao', '0') === '1' ? 'checked' : '') . '>
                                                <label class="form-check-label">Modo Manutenção</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="debug" ' . ($this->getConfigValue($config, 'sistema', 'debug', '0') === '1' ? 'checked' : '') . '>
                                                <label class="form-check-label">Modo Debug</label>
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="cache_ativado" ' . ($this->getConfigValue($config, 'sistema', 'cache_ativado', '1') === '1' ? 'checked' : '') . '>
                                                <label class="form-check-label">Cache Ativado</label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-end mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Salvar Configurações
                                </button>
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
        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            $pdo->beginTransaction();
            
            // Mapeamento de configurações
            $configMap = [
                'loja' => ['nome', 'descricao', 'email', 'telefone', 'endereco', 'logo'],
                'email' => ['driver', 'host', 'port', 'username', 'password', 'encryption', 'from', 'from_name'],
                'entrega' => ['moeda_padrao', 'taxa_servico_kg', 'frete_gratis_acima', 'frete_padrao', 'prazo_padrao', 'cep_origem', 'calcular_automatico'],
                'seo' => ['title', 'description', 'keywords', 'google_analytics', 'google_tag_manager', 'sitemap_gerado'],
                'sistema' => ['timezone', 'idioma', 'moeda', 'manutencao', 'debug', 'cache_ativado']
            ];
            
            foreach ($configMap as $categoria => $chaves) {
                foreach ($chaves as $chave) {
                    $valor = $request->getParam($categoria . '_' . $chave);
                    
                    if ($valor !== null) {
                        // Converter checkboxes para 0/1
                        if (in_array($chave, ['calcular_automatico', 'sitemap_gerado', 'manutencao', 'debug', 'cache_ativado'])) {
                            $valor = $valor ? '1' : '0';
                        }
                        
                        // Validar valores específicos
                        if ($chave === 'moeda_padrao') {
                            $valor = in_array($valor, ['USD', 'BRL']) ? $valor : 'USD';
                        }
                        if ($chave === 'taxa_servico_kg') {
                            $valor = is_numeric($valor) ? floatval($valor) : 39;
                        }
                        if ($chave === 'frete_gratis_acima') {
                            $valor = is_numeric($valor) ? floatval($valor) : 0;
                        }
                        if ($chave === 'frete_padrao') {
                            $valor = is_numeric($valor) ? floatval($valor) : 15;
                        }
                        if ($chave === 'prazo_padrao') {
                            $valor = is_numeric($valor) ? intval($valor) : 30;
                        }
                        
                        // Atualizar ou inserir configuração
                        $stmt = $pdo->prepare("
                            INSERT INTO configuracoes (categoria, chave, valor, updated_at)
                            VALUES (?, ?, ?, NOW())
                            ON DUPLICATE KEY UPDATE valor = ?, updated_at = NOW()
                        ");
                        $stmt->execute([$categoria, $chave, $valor, $valor]);
                    }
                }
            }
            
            $pdo->commit();
            
            header('Location: /admin/configuracoes?success=1');
            exit;
            
        } catch (\Exception $e) {
            if (isset($pdo)) $pdo->rollBack();
            echo '<div class="alert alert-danger">Erro ao salvar configurações: ' . $e->getMessage() . '</div>';
            echo '<a href="/admin/configuracoes" class="btn btn-secondary">Voltar</a>';
            exit;
        }
    }
    
    private function getConfigValue($config, $categoria, $chave, $default = '') {
        if (isset($config[$categoria])) {
            foreach ($config[$categoria] as $c) {
                if ($c['chave'] === $chave) {
                    return $c['valor'];
                }
            }
        }
        return $default;
    }
}
