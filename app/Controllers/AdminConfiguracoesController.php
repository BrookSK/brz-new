<?php
namespace App\Controllers;

use App\Core\Request;

class AdminConfiguracoesController extends Controller {
    
    public function index(Request $request) {
        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            
            // Buscar configurações
            $tableInfo = $this->getConfigTableInfo($pdo);
            $table = $tableInfo['table'];

            $config = [];

            if (($tableInfo['mode'] ?? '') === 'single_row') {
                $stmt = $pdo->query("SELECT * FROM {$table} ORDER BY {$tableInfo['idCol']} ASC LIMIT 1");
                $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
                $map = $tableInfo['columnMap'] ?? [];
                foreach ($map as $categoria => $chaves) {
                    foreach ($chaves as $chave => $col) {
                        if (array_key_exists($col, $row)) {
                            if (!isset($config[$categoria])) {
                                $config[$categoria] = [];
                            }
                            $config[$categoria][$chave] = (string) ($row[$col] ?? '');
                        }
                    }
                }
            } else {
                $orderBy = [];
                if (($tableInfo['mode'] ?? '') === 'categoria_chave') {
                    $orderBy = [$tableInfo['categoriaCol'], $tableInfo['chaveCol']];
                } else {
                    $orderBy = [$tableInfo['keyCol']];
                }
                if (!empty($tableInfo['updatedAtCol'])) {
                    $orderBy[] = $tableInfo['updatedAtCol'] . ' ASC';
                }
                if (!empty($tableInfo['idCol'])) {
                    $orderBy[] = $tableInfo['idCol'] . ' ASC';
                }

                if (($tableInfo['mode'] ?? '') === 'categoria_chave') {
                    $sql = "SELECT {$tableInfo['categoriaCol']} AS categoria, {$tableInfo['chaveCol']} AS chave, {$tableInfo['valueCol']} AS valor FROM {$table} ORDER BY " . implode(', ', $orderBy);
                } else {
                    $sql = "SELECT {$tableInfo['keyCol']} AS chave, {$tableInfo['valueCol']} AS valor FROM {$table} ORDER BY " . implode(', ', $orderBy);
                }

                $stmt = $pdo->query($sql);
                $configuracoes = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                foreach ($configuracoes as $c) {
                    $fullKey = '';
                    if (($tableInfo['mode'] ?? '') === 'categoria_chave') {
                        $cat = (string) ($c['categoria'] ?? '');
                        $k = (string) ($c['chave'] ?? '');
                        $fullKey = ($cat !== '' && $k !== '') ? ($cat . '_' . $k) : '';
                    } else {
                        $fullKey = (string) ($c['chave'] ?? '');
                    }

                    $valor = $c['valor'] ?? '';
                    if ($fullKey === '') {
                        continue;
                    }
                    if (strpos($fullKey, '_') !== false) {
                        [$categoria, $chave] = explode('_', $fullKey, 2);
                    } else {
                        $categoria = 'geral';
                        $chave = $fullKey;
                    }
                    if (!isset($config[$categoria])) {
                        $config[$categoria] = [];
                    }
                    $config[$categoria][$chave] = $valor;
                }
            }
            
        } catch (\Exception $e) {
            $config = [];
        }
        
        // Incluir o partial do menu lateral
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurações - Braziliana Shop Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';
        
        // Renderizar estilos do menu
        renderAdminSidebarStyles();
        
        echo '</head>
<body>
    <div class="container-fluid">
        <div class="row">';
        
        // Renderizar menu lateral usando o partial
        renderAdminSidebar('configuracoes');
        
        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Configurações</h1>
                    <div>
                        <button type="button" class="btn btn-success me-2" onclick="alert(\'Funcionalidade em desenvolvimento\')">
                            <i class="fas fa-download me-1"></i>Backup
                        </button>
                        <button type="button" class="btn btn-info" onclick="location.reload()">
                            <i class="fas fa-sync me-1"></i>Atualizar
                        </button>
                    </div>
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
                            <button class="nav-link" id="v-pills-email-creator-tab" data-bs-toggle="pill" data-bs-target="#v-pills-email-creator" type="button">
                                <i class="fas fa-edit"></i> Criar E-mail
                            </button>
                            <button class="nav-link" id="v-pills-notificacoes-tab" data-bs-toggle="pill" data-bs-target="#v-pills-notificacoes" type="button">
                                <i class="fas fa-bell"></i> Notificações
                            </button>
                            <button class="nav-link" id="v-pills-pagamentos-tab" data-bs-toggle="pill" data-bs-target="#v-pills-pagamentos" type="button">
                                <i class="fas fa-credit-card"></i> Pagamentos
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
                        <form method="POST" action="/admin/configuracoes/salvar" novalidate>
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
                                                <input type="text" class="form-control" name="loja_nome" value="' . $this->getConfigValue($config, 'loja', 'nome', 'Braziliana Shop') . '">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Descrição</label>
                                                <textarea class="form-control" name="loja_descricao" rows="3">' . $this->getConfigValue($config, 'loja', 'descricao', '') . '</textarea>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Email de Contato</label>
                                                <input type="email" class="form-control" name="loja_email" value="' . $this->getConfigValue($config, 'loja', 'email', 'contato@brazilianashop.com.br') . '">
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
                                                <input type="text" class="form-control" name="loja_logo" value="' . $this->getConfigValue($config, 'loja', 'logo', '') . '">
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="tab-pane fade" id="v-pills-notificacoes" role="tabpanel">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="mb-0">Configurar Notificações por Webhook</h5>
                                        </div>
                                        <div class="card-body">
                                            <div id="formNotificacoes">
                                                <div class="mb-3">
                                                    <label class="form-label">Evento</label>
                                                    <select name="evento" class="form-select" required>
                                                        <option value="">Selecione um evento...</option>
                                                        <option value="novo_pedido">Novo Pedido</option>
                                                        <option value="pedido_aprovado">Pedido Aprovado</option>
                                                        <option value="pedido_enviado">Pedido Enviado</option>
                                                        <option value="pedido_entregue">Pedido Entregue</option>
                                                        <option value="pedido_cancelado">Pedido Cancelado</option>
                                                    </select>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label">URL do Webhook</label>
                                                    <input type="url" name="webhook_url" class="form-control" placeholder="https://seu-webhook.com/notificacoes" required>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label">Método HTTP</label>
                                                    <select name="webhook_method" class="form-select">
                                                        <option value="POST">POST</option>
                                                        <option value="PUT">PUT</option>
                                                        <option value="PATCH">PATCH</option>
                                                    </select>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label">Headers (JSON)</label>
                                                    <textarea name="webhook_headers" class="form-control" rows="3" placeholder="{&quot;Authorization&quot;: &quot;Bearer token123&quot;}"></textarea>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label">Campos Personalizados (JSON)</label>
                                                    <textarea name="webhook_campos" class="form-control" rows="5" placeholder="{&quot;empresa&quot;: &quot;Braziliana Shop&quot;}"></textarea>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label">Template da Mensagem</label>
                                                    <textarea name="webhook_template" class="form-control" rows="4" placeholder="Olá {{nome}}, seu pedido #{{codigo_pedido}} está {{status}}"></textarea>
                                                </div>

                                                <div class="mb-3">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="webhook_ativo" id="notificacoes_webhook_ativo" checked>
                                                        <label class="form-check-label" for="notificacoes_webhook_ativo">Webhook Ativo</label>
                                                    </div>
                                                </div>

                                                <div class="mb-3">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" name="webhook_retries" id="notificacoes_webhook_retries" checked>
                                                        <label class="form-check-label" for="notificacoes_webhook_retries">Tentativas de Reenvio</label>
                                                    </div>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label">Logs de Envio</label>
                                                    <div class="table-responsive">
                                                        <table class="table table-sm">
                                                            <thead>
                                                                <tr>
                                                                    <th>Data</th>
                                                                    <th>Status</th>
                                                                    <th>Resposta</th>
                                                                    <th>Ações</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody id="notificacoes-logs-webhook">
                                                                <tr>
                                                                    <td colspan="4" class="text-center">Nenhum log encontrado</td>
                                                                </tr>
                                                            </tbody>
                                                        </table>
                                                    </div>
                                                </div>

                                                <div class="d-flex gap-2">
                                                    <button type="button" class="btn btn-primary" onclick="salvarNotificacaoAdmin()">
                                                        <i class="fas fa-save me-2"></i>Salvar Configuração
                                                    </button>
                                                    <button type="button" class="btn btn-success" onclick="testarWebhookNotificacoes()">
                                                        <i class="fas fa-paper-plane me-2"></i>Testar Webhook
                                                    </button>
                                                </div>
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
                                                <input type="email" class="form-control" name="email_from" value="' . $this->getConfigValue($config, 'email', 'from', 'noreply@brazilianashop.com.br') . '">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Nome de Envio</label>
                                                <input type="text" class="form-control" name="email_from_name" value="' . $this->getConfigValue($config, 'email', 'from_name', 'Braziliana Shop') . '">
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Criador de E-mail -->
                                <div class="tab-pane fade" id="v-pills-email-creator" role="tabpanel">
                                    <div class="card">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <h5 class="mb-0">Criador de E-mail</h5>
                                            <div class="form-check form-switch">
                                                <input class="form-check-input" type="checkbox" id="webhook_enabled" ' . ($this->getConfigValue($config, 'email', 'webhook_enabled', '0') === '1' ? 'checked' : '') . '>
                                                <label class="form-check-label" for="webhook_enabled">Webhook Ativo</label>
                                            </div>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Tipo de Evento</label>
                                                        <select class="form-select" id="evento_tipo" onchange="carregarVariaveis()">
                                                            <option value="">Selecione um evento...</option>
                                                            <option value="novo_pedido">🛒 Novo Pedido</option>
                                                            <option value="pedido_aprovado">✅ Pedido Aprovado</option>
                                                            <option value="pedido_enviado">📦 Pedido Enviado</option>
                                                            <option value="pedido_entregue">🎁 Pedido Entregue</option>
                                                            <option value="pedido_cancelado">❌ Pedido Cancelado</option>
                                                            <option value="novo_usuario">👤 Novo Usuário</option>
                                                            <option value="recuperar_senha">🔑 Recuperar Senha</option>
                                                            <option value="contato_contato">📧 Contato</option>
                                                        </select>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Assunto do E-mail</label>
                                                        <input type="text" class="form-control" id="email_assunto" placeholder="Assunto do e-mail">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Variáveis Disponíveis</label>
                                                        <div class="border rounded p-2 bg-light" style="max-height: 150px; overflow-y: auto;" id="variaveis_disponiveis">
                                                            <small class="text-muted">Selecione um evento para ver as variáveis disponíveis</small>
                                                        </div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">URL do Webhook</label>
                                                        <input type="url" class="form-control" id="webhook_url" value="' . $this->getConfigValue($config, 'email', 'webhook_url', '') . '" placeholder="https://sua-api.com/webhook">
                                                        <small class="text-muted">URL para receber os dados do evento</small>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Editor HTML</label>
                                                        <textarea class="form-control" id="email_conteudo" rows="15" placeholder="Digite o conteúdo HTML do e-mail..."></textarea>
                                                    </div>
                                                    <div class="d-flex gap-2">
                                                        <button type="button" class="btn btn-outline-primary" onclick="inserirVariavel()">
                                                            <i class="fas fa-code"></i> Inserir Variável
                                                        </button>
                                                        <button type="button" class="btn btn-outline-success" onclick="previsualizarEmail()">
                                                            <i class="fas fa-eye"></i> Pré-visualizar
                                                        </button>
                                                        <button type="button" class="btn btn-outline-info" onclick="salvarTemplate()">
                                                            <i class="fas fa-save"></i> Salvar Template
                                                        </button>
                                                        <button type="button" class="btn btn-outline-warning" onclick="testarWebhook()">
                                                            <i class="fas fa-plug"></i> Testar Webhook
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Pré-visualização -->
                                            <div class="row mt-4" id="preview_section" style="display: none;">
                                                <div class="col-12">
                                                    <div class="card">
                                                        <div class="card-header">
                                                            <h6 class="mb-0">📧 Pré-visualização do E-mail</h6>
                                                        </div>
                                                        <div class="card-body">
                                                            <iframe id="email_preview" style="width: 100%; height: 400px; border: 1px solid #ddd;"></iframe>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Templates Salvos -->
                                            <div class="row mt-4">
                                                <div class="col-12">
                                                    <div class="card">
                                                        <div class="card-header">
                                                            <h6 class="mb-0">📋 Templates Salvos</h6>
                                                        </div>
                                                        <div class="card-body">
                                                            <div id="templates_salvos">
                                                                <small class="text-muted">Nenhum template salvo ainda</small>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
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
                                                <select class="form-select" name="entrega_moeda_padrao">
                                                    <option value="USD" ' . ($this->getConfigValue($config, 'entrega', 'moeda_padrao', 'USD') === 'USD' ? 'selected' : '') . '>USD - Dólar Americano</option>
                                                    <option value="BRL" ' . ($this->getConfigValue($config, 'entrega', 'moeda_padrao', 'USD') === 'BRL' ? 'selected' : '') . '>BRL - Real Brasileiro</option>
                                                </select>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Taxa de Serviço (USD por kg)</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">$</span>
                                                    <input type="text" class="form-control" name="entrega_taxa_servico_kg" value="' . $this->getConfigValue($config, 'entrega', 'taxa_servico_kg', '39') . '">
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Frete Grátis Acima de</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">$</span>
                                                    <input type="text" class="form-control" name="entrega_frete_gratis_acima" value="' . $this->getConfigValue($config, 'entrega', 'frete_gratis_acima', '0') . '">
                                                </div>
                                                <small class="text-muted">Deixe como 0 para frete sempre grátis</small>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Valor Padrão do Frete (USD por kg)</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">$</span>
                                                    <input type="text" class="form-control" name="entrega_frete_padrao" value="' . $this->getConfigValue($config, 'entrega', 'frete_padrao', '15') . '">
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Prazo Padrão (dias)</label>
                                                <input type="number" class="form-control" name="entrega_prazo_padrao" value="' . $this->getConfigValue($config, 'entrega', 'prazo_padrao', '30') . '">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">CEP de Origem</label>
                                                <input type="text" class="form-control" name="entrega_cep_origem" value="' . $this->getConfigValue($config, 'entrega', 'cep_origem', '') . '">
                                            </div>
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="entrega_calcular_automatico" value="1" ' . ($this->getConfigValue($config, 'entrega', 'calcular_automatico', '1') === '1' ? 'checked' : '') . '>
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
                                                <input type="text" class="form-control" name="seo_title" value="' . $this->getConfigValue($config, 'seo', 'title', 'Braziliana Shop - Produtos de Qualidade') . '">
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Meta Description Padrão</label>
                                                <textarea class="form-control" name="seo_description" rows="3">' . $this->getConfigValue($config, 'seo', 'description', 'Encontre os melhores produtos na Braziliana Shop') . '</textarea>
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
                                
                                <!-- Configurações de Pagamentos -->
                                <div class="tab-pane fade" id="v-pills-pagamentos" role="tabpanel">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="mb-0">Configurações de Pagamentos</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="card">
                                                        <div class="card-header d-flex justify-content-between align-items-center">
                                                            <h6 class="mb-0">🇧🇷 Asaas</h6>
                                                            <div class="form-check form-switch">
                                                                <input class="form-check-input" type="checkbox" id="asaas_enabled" name="pagamentos_asaas_enabled" value="1" ' . ($this->getConfigValue($config, 'pagamentos', 'asaas_enabled', '0') === '1' ? 'checked' : '') . '>
                                                                <label class="form-check-label" for="asaas_enabled">Ativo</label>
                                                            </div>
                                                        </div>
                                                        <div class="card-body">
                                                            <div class="mb-3">
                                                                <label class="form-label">Ambiente</label>
                                                                <select class="form-select" name="pagamentos_asaas_ambiente">
                                                                    <option value="sandbox" ' . ($this->getConfigValue($config, 'pagamentos', 'asaas_ambiente', 'sandbox') === 'sandbox' ? 'selected' : '') . '>Sandbox (Testes)</option>
                                                                    <option value="production" ' . ($this->getConfigValue($config, 'pagamentos', 'asaas_ambiente', '') === 'production' ? 'selected' : '') . '>Produção</option>
                                                                </select>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">API Key</label>
                                                                <div class="input-group">
                                                                    <input type="password" class="form-control" name="pagamentos_asaas_api_key" value="' . $this->getConfigValue($config, 'pagamentos', 'asaas_api_key', '') . '" placeholder="Sua API Key do Asaas">
                                                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility(this)">
                                                                        <i class="fas fa-eye"></i>
                                                                    </button>
                                                                </div>
                                                                <small class="text-muted">API Key obtida no painel do Asaas</small>
                                                            </div>
                                                            <div class="d-flex gap-2">
                                                                <button type="button" class="btn btn-outline-primary" onclick="testarAsaasAPI()">
                                                                    <i class="fas fa-plug"></i> Testar Conexão
                                                                </button>
                                                                <button type="button" class="btn btn-outline-info" onclick="verDocumentacaoAsaas()">
                                                                    <i class="fas fa-book"></i> Documentação
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="card">
                                                        <div class="card-header d-flex justify-content-between align-items-center">
                                                            <h6 class="mb-0">💳 Stripe</h6>
                                                            <div class="form-check form-switch">
                                                                <input class="form-check-input" type="checkbox" id="stripe_enabled" name="pagamentos_stripe_enabled" value="1" ' . ($this->getConfigValue($config, 'pagamentos', 'stripe_enabled', '0') === '1' ? 'checked' : '') . '>
                                                                <label class="form-check-label" for="stripe_enabled">Ativo</label>
                                                            </div>
                                                        </div>
                                                        <div class="card-body">
                                                            <div class="mb-3">
                                                                <label class="form-label">Ambiente</label>
                                                                <select class="form-select" name="pagamentos_stripe_ambiente">
                                                                    <option value="test" ' . ($this->getConfigValue($config, 'pagamentos', 'stripe_ambiente', 'test') === 'test' ? 'selected' : '') . '>Test (Chaves de Teste)</option>
                                                                    <option value="live" ' . ($this->getConfigValue($config, 'pagamentos', 'stripe_ambiente', '') === 'live' ? 'selected' : '') . '>Live (Chaves de Produção)</option>
                                                                </select>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Publishable Key</label>
                                                                <div class="input-group">
                                                                    <input type="password" class="form-control" name="pagamentos_stripe_publishable_key" value="' . $this->getConfigValue($config, 'pagamentos', 'stripe_publishable_key', '') . '" placeholder="pk_test_...">
                                                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility(this)">
                                                                        <i class="fas fa-eye"></i>
                                                                    </button>
                                                                </div>
                                                                <small class="text-muted">Chave pública (pk_test_... ou pk_live_...)</small>
                                                            </div>
                                                            <div class="mb-3">
                                                                <label class="form-label">Secret Key</label>
                                                                <div class="input-group">
                                                                    <input type="password" class="form-control" name="pagamentos_stripe_secret_key" value="' . $this->getConfigValue($config, 'pagamentos', 'stripe_secret_key', '') . '" placeholder="sk_test_...">
                                                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility(this)">
                                                                        <i class="fas fa-eye"></i>
                                                                    </button>
                                                                </div>
                                                                <small class="text-muted">Chave secreta (sk_test_... ou sk_live_...)</small>
                                                            </div>
                                                            <div class="d-flex gap-2">
                                                                <button type="button" class="btn btn-outline-primary" onclick="testarStripeAPI()">
                                                                    <i class="fas fa-plug"></i> Testar Conexão
                                                                </button>
                                                                <button type="button" class="btn btn-outline-info" onclick="verDocumentacaoStripe()">
                                                                    <i class="fas fa-book"></i> Documentação
                                                                </button>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            
                                            <!-- Status dos Gateways -->
                                            <div class="row mt-4">
                                                <div class="col-12">
                                                    <div class="card">
                                                        <div class="card-header">
                                                            <h6 class="mb-0">📊 Status dos Gateways de Pagamento</h6>
                                                        </div>
                                                        <div class="card-body">
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="d-flex align-items-center mb-3">
                                                                        <div class="me-3">
                                                                            <i class="fas fa-circle text-' . ($this->getConfigValue($config, 'pagamentos', 'asaas_enabled', '0') === '1' ? 'success' : 'secondary') . '"></i>
                                                                            <strong>Asaas:</strong>
                                                                        </div>
                                                                        <span class="badge bg-' . ($this->getConfigValue($config, 'pagamentos', 'asaas_enabled', '0') === '1' ? 'success' : 'secondary') . '">
                                                                            ' . ($this->getConfigValue($config, 'pagamentos', 'asaas_enabled', '0') === '1' ? 'Ativo' : 'Inativo') . '
                                                                        </span>
                                                                    </div>
                                                                    <div class="text-muted small">
                                                                        Ambiente: ' . ucfirst($this->getConfigValue($config, 'pagamentos', 'asaas_ambiente', 'sandbox')) . ' | 
                                                                        API Key: ' . (empty($this->getConfigValue($config, 'pagamentos', 'asaas_api_key', '')) ? 'Não configurada' : 'Configurada') . '
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <div class="d-flex align-items-center mb-3">
                                                                        <div class="me-3">
                                                                            <i class="fas fa-circle text-' . ($this->getConfigValue($config, 'pagamentos', 'stripe_enabled', '0') === '1' ? 'success' : 'secondary') . '"></i>
                                                                            <strong>Stripe:</strong>
                                                                        </div>
                                                                        <span class="badge bg-' . ($this->getConfigValue($config, 'pagamentos', 'stripe_enabled', '0') === '1' ? 'success' : 'secondary') . '">
                                                                            ' . ($this->getConfigValue($config, 'pagamentos', 'stripe_enabled', '0') === '1' ? 'Ativo' : 'Inativo') . '
                                                                        </span>
                                                                    </div>
                                                                    <div class="text-muted small">
                                                                        Ambiente: ' . ucfirst($this->getConfigValue($config, 'pagamentos', 'stripe_ambiente', 'test')) . ' | 
                                                                        Keys: ' . (empty($this->getConfigValue($config, 'pagamentos', 'stripe_publishable_key', '')) ? 'Não configuradas' : 'Configuradas') . '
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
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
    </div>';

    // Renderizar scripts
    renderAdminScripts();
    
    echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    ' . $this->getPagamentosJS() . $this->getEmailCreatorJS() . $this->getNotificacoesJS() . '
</body>
</html>';
        exit;
    }
    
    public function salvar(Request $request) {
        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            $pdo->beginTransaction();

            $tableInfo = $this->getConfigTableInfo($pdo);
            $table = $tableInfo['table'];
            $valueCol = $tableInfo['valueCol'];
            $updatedAtCol = $tableInfo['updatedAtCol'];
            
            // Mapeamento de configurações
            $configMap = [
                'loja' => ['nome', 'descricao', 'email', 'telefone', 'endereco', 'logo'],
                'email' => ['driver', 'host', 'port', 'username', 'password', 'encryption', 'from', 'from_name', 'webhook_enabled', 'webhook_url'],
                'pagamentos' => ['asaas_enabled', 'asaas_ambiente', 'asaas_api_key', 'stripe_enabled', 'stripe_ambiente', 'stripe_publishable_key', 'stripe_secret_key'],
                'entrega' => ['moeda_padrao', 'taxa_servico_kg', 'frete_gratis_acima', 'frete_padrao', 'prazo_padrao', 'cep_origem', 'calcular_automatico'],
                'seo' => ['title', 'description', 'keywords', 'google_analytics', 'google_tag_manager', 'sitemap_gerado'],
                'sistema' => ['timezone', 'idioma', 'moeda', 'manutencao', 'debug', 'cache_ativado']
            ];
            
            $checkboxKeys = ['calcular_automatico', 'sitemap_gerado', 'manutencao', 'debug', 'cache_ativado', 'webhook_enabled', 'asaas_enabled', 'stripe_enabled'];

            foreach ($configMap as $categoria => $chaves) {
                foreach ($chaves as $chave) {
                    $valor = $request->getParam($categoria . '_' . $chave);

                    // Checkboxes não enviados no POST quando desmarcados
                    if ($valor === null && in_array($chave, $checkboxKeys, true)) {
                        $valor = '0';
                    }

                    if ($valor !== null) {
                        // Converter checkboxes para 0/1
                        if (in_array($chave, $checkboxKeys, true)) {
                            $valor = ($valor === '1' || $valor === 1 || $valor === true) ? '1' : '0';
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
                        $fullKey = $categoria . '_' . $chave;

                        if (($tableInfo['mode'] ?? '') === 'single_row') {
                            $map = $tableInfo['columnMap'] ?? [];
                            $col = $map[$categoria][$chave] ?? null;
                            if (!empty($col) && preg_match('/^[a-zA-Z0-9_]+$/', (string) $col)) {
                                $idCol = $tableInfo['idCol'];
                                $idVal = $tableInfo['idVal'] ?? 1;
                                $set = "{$col} = ?";
                                if (!empty($updatedAtCol)) {
                                    $set .= ", {$updatedAtCol} = NOW()";
                                }
                                $stmtUpdate = $pdo->prepare("UPDATE {$table} SET {$set} WHERE {$idCol} = ?");
                                $stmtUpdate->execute([$valor, $idVal]);
                            }
                            continue;
                        }

                        if (($tableInfo['mode'] ?? '') === 'categoria_chave') {
                            $catCol = $tableInfo['categoriaCol'];
                            $keyCol = $tableInfo['chaveCol'];

                            if (!empty($updatedAtCol)) {
                                $stmtUpdate = $pdo->prepare("UPDATE {$table} SET {$valueCol} = ?, {$updatedAtCol} = NOW() WHERE {$catCol} = ? AND {$keyCol} = ?");
                            } else {
                                $stmtUpdate = $pdo->prepare("UPDATE {$table} SET {$valueCol} = ? WHERE {$catCol} = ? AND {$keyCol} = ?");
                            }
                            $stmtUpdate->execute([$valor, $categoria, $chave]);
                        } else {
                            $keyCol = $tableInfo['keyCol'];
                            if (!empty($updatedAtCol)) {
                                $stmtUpdate = $pdo->prepare("UPDATE {$table} SET {$valueCol} = ?, {$updatedAtCol} = NOW() WHERE {$keyCol} = ?");
                            } else {
                                $stmtUpdate = $pdo->prepare("UPDATE {$table} SET {$valueCol} = ? WHERE {$keyCol} = ?");
                            }
                            $stmtUpdate->execute([$valor, $fullKey]);
                        }

                        if ($stmtUpdate->rowCount() === 0) {
                            if (($tableInfo['mode'] ?? '') === 'categoria_chave') {
                                $catCol = $tableInfo['categoriaCol'];
                                $keyCol = $tableInfo['chaveCol'];
                                if (!empty($updatedAtCol)) {
                                    $stmtInsert = $pdo->prepare("INSERT INTO {$table} ({$catCol}, {$keyCol}, {$valueCol}, {$updatedAtCol}) VALUES (?, ?, ?, NOW())");
                                    $stmtInsert->execute([$categoria, $chave, $valor]);
                                } else {
                                    $stmtInsert = $pdo->prepare("INSERT INTO {$table} ({$catCol}, {$keyCol}, {$valueCol}) VALUES (?, ?, ?)");
                                    $stmtInsert->execute([$categoria, $chave, $valor]);
                                }
                            } else {
                                $keyCol = $tableInfo['keyCol'];
                                if (!empty($updatedAtCol)) {
                                    $stmtInsert = $pdo->prepare("INSERT INTO {$table} ({$keyCol}, {$valueCol}, {$updatedAtCol}) VALUES (?, ?, NOW())");
                                } else {
                                    $stmtInsert = $pdo->prepare("INSERT INTO {$table} ({$keyCol}, {$valueCol}) VALUES (?, ?)");
                                }
                                $stmtInsert->execute([$fullKey, $valor]);
                            }
                        }
                    }
                }
            }
            
            $pdo->commit();
            
            header('Location: /admin/configuracoes?success=1');
            exit;
            
        } catch (\Exception $e) {
            if (isset($pdo)) $pdo->rollBack();
            $schemaInfo = '';
            try {
                if (isset($pdo)) {
                    $ti = $this->getConfigTableInfo($pdo);
                    $schemaInfo = ' (tabela=' . htmlspecialchars((string) ($ti['table'] ?? '')) . ', modo=' . htmlspecialchars((string) ($ti['mode'] ?? '')) . ')';
                }
            } catch (\Exception $e2) {
            }
            echo '<div class="alert alert-danger">Erro ao salvar configurações: ' . $e->getMessage() . $schemaInfo . '</div>';
            echo '<a href="/admin/configuracoes" class="btn btn-secondary">Voltar</a>';
            exit;
        }
    }
    
    // JavaScript para pagamentos
    private function getPagamentosJS() {
        ob_start();
        ?>
        <script>
        function togglePasswordVisibility(button) {
            const input = button.previousElementSibling;
            const icon = button.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
        
        function testarAsaasAPI() {
            const apiKey = document.querySelector('input[name="asaas_api_key"]').value;
            const ambiente = document.querySelector('select[name="asaas_ambiente"]').value;
            
            if (!apiKey) {
                alert('Digite a API Key do Asaas primeiro');
                return;
            }
            
            // URL da API do Asaas
            const url = ambiente === 'production' ? 'https://www.asaas.com/api/v3/myAccount' : 'https://sandbox.asaas.com/api/v3/myAccount';
            
            fetch(url, {
                headers: {
                    'access_token': apiKey,
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.id) {
                    alert('✅ Conexão com Asaas bem-sucedida!\n\nConta: ' + data.name + '\nAmbiente: ' + ambiente);
                } else {
                    alert('❌ Erro na conexão com Asaas: ' + (data.errors?.[0]?.description || 'Verifique sua API Key'));
                }
            })
            .catch(error => {
                alert('❌ Erro ao testar conexão: ' + error.message);
            });
        }
        
        function testarStripeAPI() {
            const publishableKey = document.querySelector('input[name="stripe_publishable_key"]').value;
            const secretKey = document.querySelector('input[name="stripe_secret_key"]').value;
            const ambiente = document.querySelector('select[name="stripe_ambiente"]').value;
            
            if (!publishableKey || !secretKey) {
                alert('Digite as chaves do Stripe primeiro');
                return;
            }
            
            // Testar com a API do Stripe (usando a chave secreta)
            fetch('https://api.stripe.com/v1/account', {
                headers: {
                    'Authorization': 'Bearer ' + secretKey,
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.id) {
                    alert('✅ Conexão com Stripe bem-sucedida!\n\nConta: ' + (data.business_profile?.name || data.display_name) + '\nAmbiente: ' + ambiente);
                } else {
                    alert('❌ Erro na conexão com Stripe: ' + (data.error?.message || 'Verifique suas chaves'));
                }
            })
            .catch(error => {
                alert('❌ Erro ao testar conexão: ' + error.message);
            });
        }
        
        function verDocumentacaoAsaas() {
            window.open('https://docs.asaas.com/reference/introduction', '_blank');
        }
        
        function verDocumentacaoStripe() {
            window.open('https://stripe.com/docs/api', '_blank');
        }
        </script>
        <?php
        return ob_get_clean();
    }

    private function getNotificacoesJS() {
        ob_start();
        ?>
        <script>
        function getNotificacoesFormData() {
            const container = document.getElementById('formNotificacoes');
            const formData = new FormData();
            if (!container) {
                return formData;
            }

            const fields = container.querySelectorAll('input[name], select[name], textarea[name]');
            fields.forEach(el => {
                if (el.type === 'checkbox') {
                    formData.set(el.name, el.checked ? '1' : '0');
                } else {
                    formData.set(el.name, el.value || '');
                }
            });
            return formData;
        }

        function salvarNotificacaoAdmin() {
            const formData = getNotificacoesFormData();

            fetch('/admin/salvar-notificacao', {
                method: 'POST',
                body: formData
            })
            .then(async response => {
                const data = await response.json().catch(() => ({}));
                if (response.ok && data.success) {
                    alert('Configuração de notificação salva com sucesso!');
                    carregarLogsWebhookNotificacoes();
                } else {
                    alert('Erro ao salvar configuração: ' + (data.error || JSON.stringify(data)));
                }
            })
            .catch(error => {
                alert('Erro ao processar requisição: ' + error.message);
            });
        }

        function testarWebhookNotificacoes() {
            const evento = document.querySelector('#formNotificacoes select[name="evento"]').value;
            if (!evento) {
                alert('Selecione um evento.');
                return;
            }

            const formData = new FormData();
            formData.set('evento', evento);

            fetch('/admin/testar-webhook', {
                method: 'POST',
                body: formData
            })
            .then(async response => {
                const data = await response.json().catch(() => ({}));
                if (response.ok && data.success) {
                    alert('Webhook testado com sucesso!\n\nResposta: ' + JSON.stringify(data, null, 2));
                } else {
                    alert('Erro ao testar webhook: ' + (data.error || JSON.stringify(data)));
                }
                carregarLogsWebhookNotificacoes();
            })
            .catch(error => {
                alert('Erro ao testar webhook: ' + error.message);
                carregarLogsWebhookNotificacoes();
            });
        }

        function carregarLogsWebhookNotificacoes() {
            fetch('/admin/logs-webhook')
                .then(response => response.json())
                .then(data => {
                    const tbody = document.getElementById('notificacoes-logs-webhook');
                    tbody.innerHTML = '';

                    if (data.logs && data.logs.length > 0) {
                        data.logs.forEach(log => {
                            const tr = document.createElement('tr');
                            tr.innerHTML = `
                                <td>${new Date(log.data_envio).toLocaleString('pt-BR')}</td>
                                <td><span class="badge bg-${log.status == 'sucesso' ? 'success' : 'danger'}">${log.status}</span></td>
                                <td><small>${log.resposta || 'Sem resposta'}</small></td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-outline-info" onclick="verDetalhesLogNotificacoes(${log.id})">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </td>
                            `;
                            tbody.appendChild(tr);
                        });
                    } else {
                        tbody.innerHTML = '<tr><td colspan="4" class="text-center">Nenhum log encontrado</td></tr>';
                    }
                })
                .catch(() => {
                });
        }

        function verDetalhesLogNotificacoes(logId) {
            fetch(`/admin/log-webhook/${logId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.log) {
                        alert(`Detalhes do Log #${logId}:\n\n` +
                            `Data: ${new Date(data.log.data_envio).toLocaleString('pt-BR')}\n` +
                            `Status: ${data.log.status}\n` +
                            `URL: ${data.log.webhook_url}\n` +
                            `Método: ${data.log.metodo}\n` +
                            `Headers: ${data.log.headers}\n` +
                            `Payload: ${data.log.payload}\n` +
                            `Resposta: ${data.log.resposta}`);
                    }
                });
        }

        function formatarJSONNotificacoes(textarea) {
            try {
                const value = textarea.value;
                const formatted = JSON.stringify(JSON.parse(value), null, 2);
                textarea.value = formatted;
            } catch (e) {
            }
        }

        document.addEventListener('DOMContentLoaded', function() {
            const tab = document.getElementById('v-pills-notificacoes-tab');
            if (tab) {
                tab.addEventListener('shown.bs.tab', function() {
                    carregarLogsWebhookNotificacoes();
                });
            }

            const headersTextarea = document.querySelector('#formNotificacoes textarea[name="webhook_headers"]');
            const camposTextarea = document.querySelector('#formNotificacoes textarea[name="webhook_campos"]');

            if (headersTextarea) {
                headersTextarea.addEventListener('blur', function() {
                    formatarJSONNotificacoes(this);
                });
            }
            if (camposTextarea) {
                camposTextarea.addEventListener('blur', function() {
                    formatarJSONNotificacoes(this);
                });
            }
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    // JavaScript para o criador de e-mail
    private function getEmailCreatorJS() {
        ob_start();
        ?>
        <script>
        // Variáveis disponíveis por evento
        const variaveisEvento = {
            "novo_pedido": {
                "{pedido_id}": "ID do Pedido",
                "{numero_pedido}": "Número do Pedido",
                "{cliente_nome}": "Nome do Cliente",
                "{cliente_email}": "Email do Cliente",
                "{total}": "Total do Pedido",
                "{moeda}": "Moeda",
                "{data_pedido}": "Data do Pedido",
                "{itens}": "Lista de Itens",
                "{endereco_entrega}": "Endereço de Entrega"
            },
            "pedido_aprovado": {
                "{pedido_id}": "ID do Pedido",
                "{numero_pedido}": "Número do Pedido",
                "{cliente_nome}": "Nome do Cliente",
                "{data_aprovacao}": "Data de Aprovação",
                "{total}": "Total do Pedido"
            },
            "pedido_enviado": {
                "{pedido_id}": "ID do Pedido",
                "{numero_pedido}": "Número do Pedido",
                "{codigo_rastreamento}": "Código de Rastreamento",
                "{data_envio}": "Data de Envio",
                "{transportadora}": "Transportadora"
            },
            "pedido_entregue": {
                "{pedido_id}": "ID do Pedido",
                "{numero_pedido}": "Número do Pedido",
                "{data_entrega}": "Data de Entrega",
                "{recebedor}": "Quem Recebeu"
            },
            "pedido_cancelado": {
                "{pedido_id}": "ID do Pedido",
                "{numero_pedido}": "Número do Pedido",
                "{motivo_cancelamento}": "Motivo do Cancelamento",
                "{data_cancelamento}": "Data do Cancelamento"
            },
            "novo_usuario": {
                "{usuario_nome}": "Nome do Usuário",
                "{usuario_email}": "Email do Usuário",
                "{data_cadastro}": "Data de Cadastro",
                "{token_confirmacao}": "Token de Confirmação"
            },
            "recuperar_senha": {
                "{usuario_nome}": "Nome do Usuário",
                "{usuario_email}": "Email do Usuário",
                "{token_reset}": "Token de Reset",
                "{data_solicitacao}": "Data da Solicitação"
            },
            "contato_contato": {
                "{nome_contato}": "Nome do Contato",
                "{email_contato}": "Email do Contato",
                "{mensagem}": "Mensagem",
                "{data_contato}": "Data do Contato"
            }
        };
        
        function carregarVariaveis() {
            const evento = document.getElementById("evento_tipo").value;
            const variaveisDiv = document.getElementById("variaveis_disponiveis");
            
            if (!evento || !variaveisEvento[evento]) {
                variaveisDiv.innerHTML = "<small class=\"text-muted\">Selecione um evento para ver as variáveis disponíveis</small>";
                return;
            }
            
            let html = "<div class=\"mb-2\"><strong>Variáveis disponíveis:</strong></div>";
            for (const [variavel, descricao] of Object.entries(variaveisEvento[evento])) {
                html += "<div class=\"mb-1\">";
                html += "<code class=\"bg-light p-1 rounded\" style=\"cursor: pointer; font-size: 12px;\" onclick=\"inserirVariavelNoCursor('" + variavel + "')\">" + variavel + "</code>";
                html += "<small class=\"text-muted ms-2\">" + descricao + "</small>";
                html += "</div>";
            }
            
            variaveisDiv.innerHTML = html;
        }
        
        function inserirVariavelNoCursor(variavel) {
            const textarea = document.getElementById("email_conteudo");
            const start = textarea.selectionStart;
            const end = textarea.selectionEnd;
            const text = textarea.value;
            
            textarea.value = text.substring(0, start) + variavel + text.substring(end);
            textarea.selectionStart = textarea.selectionEnd = start + variavel.length;
            textarea.focus();
        }
        
        function inserirVariavel() {
            const evento = document.getElementById("evento_tipo").value;
            if (!evento) {
                alert("Selecione um evento primeiro");
                return;
            }
            
            const variaveis = Object.keys(variaveisEvento[evento]);
            if (variaveis.length === 0) return;
            
            const variavel = prompt("Variáveis disponíveis:\n" + variaveis.join("\n"), variaveis[0]);
            if (variavel) {
                inserirVariavelNoCursor(variavel);
            }
        }
        
        function previsualizarEmail() {
            const conteudo = document.getElementById("email_conteudo").value;
            const preview = document.getElementById("email_preview");
            const previewSection = document.getElementById("preview_section");
            
            if (!conteudo) {
                alert("Digite o conteúdo do e-mail primeiro");
                return;
            }
            
            // Criar HTML básico para preview
            const htmlCompleto = `
                <!DOCTYPE html>
                <html>
                <head>
                    <meta charset="UTF-8">
                    <title>Preview</title>
                    <style>
                        body { font-family: Arial, sans-serif; padding: 20px; }
                    </style>
                </head>
                <body>
                    ${conteudo}
                </body>
                </html>
            `;
            
            preview.srcdoc = htmlCompleto;
            previewSection.style.display = "block";
        }
        
        function salvarTemplate() {
            const evento = document.getElementById("evento_tipo").value;
            const assunto = document.getElementById("email_assunto").value;
            const conteudo = document.getElementById("email_conteudo").value;
            
            if (!evento || !assunto || !conteudo) {
                alert("Preencha todos os campos");
                return;
            }
            
            // Salvar no localStorage (em produção, salvar no banco)
            const templates = JSON.parse(localStorage.getItem("email_templates") || "{}");
            templates[evento] = { assunto, conteudo, data: new Date().toISOString() };
            localStorage.setItem("email_templates", JSON.stringify(templates));
            
            alert("Template salvo com sucesso!");
            carregarTemplatesSalvos();
        }
        
        function carregarTemplatesSalvos() {
            const templates = JSON.parse(localStorage.getItem("email_templates") || "{}");
            const div = document.getElementById("templates_salvos");
            
            const eventos = Object.keys(templates);
            if (eventos.length === 0) {
                div.innerHTML = "<small class=\"text-muted\">Nenhum template salvo ainda</small>";
                return;
            }
            
            let html = "<div class=\"row\">";
            for (const evento of eventos) {
                const template = templates[evento];
                html += "<div class=\"col-md-4 mb-3\">";
                html += "<div class=\"card\">";
                html += "<div class=\"card-body\">";
                html += "<h6 class=\"card-title\">" + evento + "</h6>";
                html += "<p class=\"card-text\"><small>" + template.assunto + "</small></p>";
                html += "<p class=\"card-text\"><small class=\"text-muted\">" + new Date(template.data).toLocaleDateString() + "</small></p>";
                html += "<button class=\"btn btn-sm btn-outline-primary\" onclick=\"carregarTemplate('" + evento + "')\">Carregar</button>";
                html += "</div>";
                html += "</div>";
                html += "</div>";
            }
            html += "</div>";
            div.innerHTML = html;
        }
        
        function carregarTemplate(evento) {
            const templates = JSON.parse(localStorage.getItem("email_templates") || "{}");
            const template = templates[evento];
            
            if (template) {
                document.getElementById("evento_tipo").value = evento;
                document.getElementById("email_assunto").value = template.assunto;
                document.getElementById("email_conteudo").value = template.conteudo;
                carregarVariaveis();
            }
        }
        
        function testarWebhook() {
            const webhookUrl = document.getElementById("webhook_url").value;
            const evento = document.getElementById("evento_tipo").value;
            
            if (!webhookUrl) {
                alert("Digite a URL do webhook");
                return;
            }
            
            if (!evento) {
                alert("Selecione um evento");
                return;
            }
            
            // Dados de teste para o webhook
            const dadosTeste = {
                evento: evento,
                timestamp: new Date().toISOString(),
                dados: variaveisEvento[evento]
            };
            
            fetch(webhookUrl, {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                },
                body: JSON.stringify(dadosTeste)
            })
            .then(response => response.json())
            .then(data => {
                alert("Webhook testado com sucesso! Resposta: " + JSON.stringify(data));
            })
            .catch(error => {
                alert("Erro ao testar webhook: " + error.message);
            });
        }
        
        // Carregar templates ao iniciar
        document.addEventListener("DOMContentLoaded", function() {
            carregarTemplatesSalvos();
        });
        </script>
        <?php
        return ob_get_clean();
    }
    
    private function getConfigValue($config, $categoria, $chave, $default = '') {
        if (isset($config[$categoria]) && is_array($config[$categoria]) && array_key_exists($chave, $config[$categoria])) {
            return (string) $config[$categoria][$chave];
        }
        return $default;
    }

    private function getConfigTableInfo(\PDO $pdo): array {
        $tableCandidates = ['configuracoes_sistema', 'configuracoes', 'settings', 'config'];
        $table = null;
        foreach ($tableCandidates as $t) {
            try {
                $stmtTable = $pdo->prepare("SHOW TABLES LIKE ?");
                $stmtTable->execute([$t]);
                if ($stmtTable->fetchColumn()) {
                    $table = $t;
                    break;
                }
            } catch (\Exception $e) {
            }
        }

        if (!$table) {
            throw new \Exception('Tabela de configurações não encontrada');
        }

        $stmt = $pdo->query('DESCRIBE ' . $table);
        $describeRows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        $cols = [];
        $types = [];
        foreach ($describeRows as $r) {
            $field = (string) ($r['Field'] ?? '');
            if ($field === '') {
                continue;
            }
            $cols[] = $field;
            $types[$field] = strtolower((string) ($r['Type'] ?? ''));
        }

        $keyCandidates = ['chave', 'key', 'nome', 'config_key', 'configuracao', 'slug', 'parametro'];
        $valueCandidates = ['valor', 'value', 'conteudo', 'content', 'config_value'];
        $updatedCandidates = ['updated_at', 'data_atualizacao', 'updated'];
        $idCandidates = ['id'];

        // Suporte para schema com categoria + chave + valor
        $hasCategoria = in_array('categoria', $cols, true);
        $hasChave = in_array('chave', $cols, true);

        // Schema de 1 linha com colunas diretas (ex: configuracoes_sistema legado)
        if (!$hasCategoria && !$hasChave && in_array('id', $cols, true)) {
            $paymentCols = [
                'asaas_enabled',
                'asaas_ambiente',
                'asaas_api_key',
                'stripe_enabled',
                'stripe_ambiente',
                'stripe_publishable_key',
                'stripe_secret_key',
            ];

            $temAlguma = false;
            foreach ($paymentCols as $pc) {
                if (in_array($pc, $cols, true)) {
                    $temAlguma = true;
                    break;
                }
            }

            if ($temAlguma) {
                $updatedAtCol = '';
                foreach (['updated_at', 'data_atualizacao', 'updated'] as $c) {
                    if (in_array($c, $cols, true)) {
                        $updatedAtCol = $c;
                        break;
                    }
                }

                return [
                    'mode' => 'single_row',
                    'table' => $table,
                    'idCol' => 'id',
                    'idVal' => 1,
                    'updatedAtCol' => $updatedAtCol,
                    'valueCol' => '',
                    'columnMap' => [
                        'pagamentos' => [
                            'asaas_enabled' => 'asaas_enabled',
                            'asaas_ambiente' => 'asaas_ambiente',
                            'asaas_api_key' => 'asaas_api_key',
                            'stripe_enabled' => 'stripe_enabled',
                            'stripe_ambiente' => 'stripe_ambiente',
                            'stripe_publishable_key' => 'stripe_publishable_key',
                            'stripe_secret_key' => 'stripe_secret_key',
                        ]
                    ]
                ];
            }
        }

        if ($hasCategoria && $hasChave) {
            $valueCol = null;
            foreach ($valueCandidates as $c) {
                if (in_array($c, $cols, true)) {
                    $valueCol = $c;
                    break;
                }
            }
            if ($valueCol) {
                $updatedAtCol = '';
                foreach ($updatedCandidates as $c) {
                    if (in_array($c, $cols, true)) {
                        $updatedAtCol = $c;
                        break;
                    }
                }

                $idCol = '';
                foreach ($idCandidates as $c) {
                    if (in_array($c, $cols, true)) {
                        $idCol = $c;
                        break;
                    }
                }

                return [
                    'mode' => 'categoria_chave',
                    'table' => $table,
                    'categoriaCol' => 'categoria',
                    'chaveCol' => 'chave',
                    'valueCol' => $valueCol,
                    'updatedAtCol' => $updatedAtCol,
                    'idCol' => $idCol,
                ];
            }
        }

        $keyCol = null;
        foreach ($keyCandidates as $c) {
            if (in_array($c, $cols, true)) {
                $keyCol = $c;
                break;
            }
        }
        $valueCol = null;
        foreach ($valueCandidates as $c) {
            if (in_array($c, $cols, true)) {
                $valueCol = $c;
                break;
            }
        }

        // Inferência por tipo/nome quando colunas não seguem o padrão
        if (!$keyCol || !$valueCol) {
            $reserved = array_merge($idCandidates, $updatedCandidates, ['created_at', 'data_criacao', 'descricao', 'tipo', 'type']);

            $textLike = [];
            foreach ($cols as $c) {
                if (in_array($c, $reserved, true)) {
                    continue;
                }
                $t = $types[$c] ?? '';
                if (strpos($t, 'char') !== false || strpos($t, 'text') !== false || strpos($t, 'enum') !== false) {
                    $textLike[] = $c;
                }
            }

            if (!$keyCol) {
                foreach ($textLike as $c) {
                    $lc = strtolower($c);
                    if (strpos($lc, 'chav') !== false || strpos($lc, 'key') !== false || strpos($lc, 'nome') !== false || strpos($lc, 'slug') !== false || strpos($lc, 'param') !== false) {
                        $keyCol = $c;
                        break;
                    }
                }
                if (!$keyCol && !empty($textLike)) {
                    $keyCol = $textLike[0];
                }
            }

            if (!$valueCol) {
                foreach ($textLike as $c) {
                    if ($c === $keyCol) {
                        continue;
                    }
                    $lc = strtolower($c);
                    if (strpos($lc, 'val') !== false || strpos($lc, 'conteud') !== false || strpos($lc, 'content') !== false) {
                        $valueCol = $c;
                        break;
                    }
                }
                if (!$valueCol) {
                    foreach ($textLike as $c) {
                        if ($c !== $keyCol) {
                            $valueCol = $c;
                            break;
                        }
                    }
                }
            }
        }

        if (!$keyCol || !$valueCol) {
            throw new \Exception('Tabela de configurações incompatível: colunas não encontradas (cols=' . implode(', ', $cols) . ')');
        }

        $updatedAtCol = '';
        foreach ($updatedCandidates as $c) {
            if (in_array($c, $cols, true)) {
                $updatedAtCol = $c;
                break;
            }
        }

        $idCol = '';
        foreach ($idCandidates as $c) {
            if (in_array($c, $cols, true)) {
                $idCol = $c;
                break;
            }
        }

        return [
            'mode' => 'chave_valor',
            'table' => $table,
            'keyCol' => $keyCol,
            'valueCol' => $valueCol,
            'updatedAtCol' => $updatedAtCol,
            'idCol' => $idCol,
        ];
    }
}
