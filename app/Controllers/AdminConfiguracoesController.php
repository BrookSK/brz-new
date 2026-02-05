<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;
use Config\Database;

class AdminConfiguracoesController extends Controller {
    
    public function index(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfil('admin');
        try {
            $pdo = Database::getConnection();
            
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

            // Comissão específica por representante (tabela dedicada)
            try {
                $repData = $request->getParam('representante_comissoes', null);
                if (is_array($repData) && $this->tableExists($pdo, 'representante_comissoes')) {
                    foreach ($repData as $rid => $percent) {
                        $rid = (int) $rid;
                        if ($rid <= 0) continue;
                        $p = is_numeric($percent) ? (float) $percent : null;
                        if ($p === null) continue;
                        if ($p < 0) $p = 0;
                        if ($p > 100) $p = 100;
                        $stmtUp = $pdo->prepare('INSERT INTO representante_comissoes (representante_id, percentual, ativo, created_at, updated_at) VALUES (?, ?, 1, NOW(), NOW()) ON DUPLICATE KEY UPDATE percentual = VALUES(percentual), ativo = 1, updated_at = NOW()');
                        $stmtUp->execute([$rid, $p]);
                    }
                }
            } catch (\Exception $e) {
            }
            
        } catch (\Exception $e) {
            $config = [];
        }

        $mapaCalor = [];
        try {
            $mapaCalor = $this->getMapaCalorData($pdo ?? null);
        } catch (\Exception $e) {
            $mapaCalor = [];
        }

        $mapaCalorTabHtml = '';
        try {
            $mapaCalorTabHtml = $this->renderMapaCalorTabHtml($mapaCalor);
        } catch (\Exception $e) {
            $mapaCalorTabHtml = $this->renderMapaCalorTabHtml([]);
        }

        $repComissoesHtml = '';
        try {
            $repComissoesHtml = $this->renderRepresentantesComissoesHtml($pdo ?? null);
        } catch (\Exception $e) {
            $repComissoesHtml = '';
        }
        
        // Incluir o partial do menu lateral
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        $clubeFaixas = [];
        try {
            if (isset($pdo) && $pdo instanceof \PDO && $this->tableExists($pdo, 'clube_descontos_faixas')) {
                $st = $pdo->query('SELECT id, peso_min_kg, peso_max_kg, percentual_desconto, ativo, ordem FROM clube_descontos_faixas ORDER BY ativo DESC, ordem ASC, peso_min_kg ASC, id ASC');
                $clubeFaixas = $st ? ($st->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];
            }
        } catch (\Exception $e) {
            $clubeFaixas = [];
        }

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
                            <button class="nav-link" id="v-pills-assessoria-tab" data-bs-toggle="pill" data-bs-target="#v-pills-assessoria" type="button">
                                <i class="fas fa-robot"></i> Assessoria / IA
                            </button>
                            <button class="nav-link" id="v-pills-comissoes-tab" data-bs-toggle="pill" data-bs-target="#v-pills-comissoes" type="button">
                                <i class="fas fa-percentage"></i> Comissões
                            </button>
                            <button class="nav-link" id="v-pills-mapa-calor-tab" data-bs-toggle="pill" data-bs-target="#v-pills-mapa-calor" type="button">
                                <i class="fas fa-chart-area"></i> Mapa de calor
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
                                                    <small class="text-muted">Esses campos são mesclados no payload final enviado ao webhook.</small>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label">Template da Mensagem</label>
                                                    <textarea name="webhook_template" class="form-control" rows="4" placeholder="Olá {{nome}}, seu pedido #{{codigo_pedido}} está {{status}}"></textarea>
                                                    <small class="text-muted">Você pode usar variáveis no formato <code>{{nome}}</code>, <code>{{codigo_pedido}}</code>, <code>{{status}}</code>, etc.</small>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label">Campos Enviados no Webhook</label>
                                                    <div class="border rounded p-3 bg-light">
                                                        <div class="mb-2"><strong>Variáveis disponíveis:</strong></div>
                                                        <div class="row">
                                                            <div class="col-md-4"><code>{{evento}}</code></div>
                                                            <div class="col-md-4"><code>{{pedido_id}}</code></div>
                                                            <div class="col-md-4"><code>{{codigo_pedido}}</code></div>
                                                            <div class="col-md-4"><code>{{status}}</code></div>
                                                            <div class="col-md-4"><code>{{moeda}}</code></div>
                                                            <div class="col-md-4"><code>{{valor_total}}</code></div>
                                                            <div class="col-md-4"><code>{{nome}}</code></div>
                                                            <div class="col-md-4"><code>{{email}}</code></div>
                                                            <div class="col-md-4"><code>{{telefone}}</code></div>
                                                            <div class="col-md-4"><code>{{data}}</code></div>
                                                        </div>
                                                        <div class="mt-2"><small class="text-muted">Além disso, o sistema pode adicionar campos extras do evento (quando aplicável) e também tudo que você colocar em “Campos Personalizados (JSON)”.</small></div>
                                                    </div>
                                                </div>

                                                <div class="mb-3">
                                                    <label class="form-label">Exemplo de Payload (JSON)</label>
                                                    <pre class="border rounded p-3 bg-light mb-0" style="white-space: pre-wrap;">{
  "channel": "whatsapp",
  "evento": "novo_pedido",
  "to": "5511999999999",
  "message": "Olá Cliente, seu pedido #ABC123 está aprovado.",
  "vars": {
    "evento": "novo_pedido",
    "pedido_id": "123",
    "codigo_pedido": "ABC123",
    "status": "aprovado",
    "moeda": "BRL",
    "valor_total": "199.90",
    "nome": "Cliente",
    "email": "cliente@exemplo.com",
    "telefone": "5511999999999",
    "data": "2026-01-30 12:00:00"
  }
}</pre>
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
                                                    <div class="d-flex justify-content-end">
                                                        <button type="button" class="btn btn-sm btn-outline-danger" onclick="limparLogsWebhookNotificacoes()">
                                                            <i class="fas fa-trash"></i> Limpar logs
                                                        </button>
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
                                            <div class="mb-3">
                                                <label class="form-label">Email de teste (para)</label>
                                                <input type="email" class="form-control" name="email_test_to" value="' . $this->getConfigValue($config, 'email', 'test_to', '') . '">
                                                <small class="text-muted">Usado ao clicar em “Testar” nos templates de e-mail.</small>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Criador de E-mail -->
                                <div class="tab-pane fade" id="v-pills-email-creator" role="tabpanel">
                                    <div class="card">
                                        <div class="card-header d-flex justify-content-between align-items-center">
                                            <h5 class="mb-0">Criador de E-mail</h5>
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
                                                    </div>
                                                </div>
                                            </div>

                                            <hr>
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
                                                <label class="form-label">Custo fixo interno por item (USD)</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">$</span>
                                                    <input type="text" class="form-control" name="entrega_custo_envio_por_item_usd" value="' . $this->getConfigValue($config, 'entrega', 'custo_envio_por_item_usd', '0') . '">
                                                </div>
                                                <small class="text-muted">Usado nos relatórios para calcular custo interno de envio (custo por item x quantidade total de itens do pedido).</small>
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

                                            <hr>

                                            <h6 class="mb-3">W-Express (Etiquetas)</h6>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Ativo</label>
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" id="wexpress_enabled" name="entrega_wexpress_enabled" value="1" ' . ($this->getConfigValue($config, 'entrega', 'wexpress_enabled', '0') === '1' ? 'checked' : '') . '>
                                                            <label class="form-check-label" for="wexpress_enabled">Habilitar W-Express</label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Ambiente</label>
                                                        <select class="form-select" name="entrega_wexpress_ambiente">
                                                            <option value="sandbox" ' . ($this->getConfigValue($config, 'entrega', 'wexpress_ambiente', 'sandbox') === 'sandbox' ? 'selected' : '') . '>Sandbox</option>
                                                            <option value="production" ' . ($this->getConfigValue($config, 'entrega', 'wexpress_ambiente', '') === 'production' ? 'selected' : '') . '>Produção</option>
                                                        </select>
                                                        <small class="text-muted">A API do Swagger usa sandbox.wexpress.me</small>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">API Key</label>
                                                <div class="input-group">
                                                    <input type="password" class="form-control" name="entrega_wexpress_api_key" value="' . $this->getConfigValue($config, 'entrega', 'wexpress_api_key', '') . '" placeholder="Cole a API Key da W-Express">
                                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility(this)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </div>
                                                <small class="text-muted">Solicite a chave por e-mail conforme a documentação da W-Express</small>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Service Code</label>
                                                <select class="form-select" name="entrega_wexpress_service_code">
                                                    <option value="wexpress_correios_std" ' . ($this->getConfigValue($config, 'entrega', 'wexpress_service_code', 'wexpress_correios_std') === 'wexpress_correios_std' ? 'selected' : '') . '>wexpress_correios_std</option>
                                                    <option value="wexpress_correios_exp" ' . ($this->getConfigValue($config, 'entrega', 'wexpress_service_code', '') === 'wexpress_correios_exp' ? 'selected' : '') . '>wexpress_correios_exp</option>
                                                    <option value="wexpress_correios_prime_express" ' . ($this->getConfigValue($config, 'entrega', 'wexpress_service_code', '') === 'wexpress_correios_prime_express' ? 'selected' : '') . '>wexpress_correios_prime_express</option>
                                                    <option value="wexpress_premium" ' . ($this->getConfigValue($config, 'entrega', 'wexpress_service_code', '') === 'wexpress_premium' ? 'selected' : '') . '>wexpress_premium</option>
                                                </select>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Sender (JSON)</label>
                                                <textarea class="form-control" name="entrega_wexpress_sender_json" rows="6" placeholder="{\n  \"first_name\": \"Tim\", ... }">' . htmlspecialchars((string) $this->getConfigValue($config, 'entrega', 'wexpress_sender_json', ''), ENT_QUOTES, 'UTF-8') . '</textarea>
                                                <small class="text-muted">Dados do remetente (EUA). Pode colar o objeto sender do exemplo oficial da W-Express.</small>
                                            </div>

                                            <hr>

                                            <h6 class="mb-3">Correios (SIGEP Web)</h6>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Ativo</label>
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" id="sigep_enabled" name="entrega_sigep_enabled" value="1" ' . ($this->getConfigValue($config, 'entrega', 'sigep_enabled', '0') === '1' ? 'checked' : '') . '>
                                                            <label class="form-check-label" for="sigep_enabled">Habilitar SIGEP Web</label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Ambiente</label>
                                                        <select class="form-select" name="entrega_sigep_ambiente">
                                                            <option value="homologacao" ' . ($this->getConfigValue($config, 'entrega', 'sigep_ambiente', 'homologacao') === 'homologacao' ? 'selected' : '') . '>Homologação</option>
                                                            <option value="producao" ' . ($this->getConfigValue($config, 'entrega', 'sigep_ambiente', '') === 'producao' ? 'selected' : '') . '>Produção</option>
                                                        </select>
                                                        <small class="text-muted">Use Homologação até validar contrato/cartão e serviços.</small>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Usuário</label>
                                                        <input type="text" class="form-control" name="entrega_sigep_usuario" value="' . $this->getConfigValue($config, 'entrega', 'sigep_usuario', '') . '" placeholder="Usuário SIGEP">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Senha</label>
                                                        <div class="input-group">
                                                            <input type="password" class="form-control" name="entrega_sigep_senha" value="' . $this->getConfigValue($config, 'entrega', 'sigep_senha', '') . '" placeholder="Senha SIGEP">
                                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility(this)">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-4">
                                                    <div class="mb-3">
                                                        <label class="form-label">Contrato</label>
                                                        <input type="text" class="form-control" name="entrega_sigep_numero_contrato" value="' . $this->getConfigValue($config, 'entrega', 'sigep_numero_contrato', '') . '" placeholder="Número do contrato">
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="mb-3">
                                                        <label class="form-label">Cartão de Postagem</label>
                                                        <input type="text" class="form-control" name="entrega_sigep_cartao_postagem" value="' . $this->getConfigValue($config, 'entrega', 'sigep_cartao_postagem', '') . '" placeholder="Cartão de postagem">
                                                    </div>
                                                </div>
                                                <div class="col-md-4">
                                                    <div class="mb-3">
                                                        <label class="form-label">CNPJ</label>
                                                        <input type="text" class="form-control" name="entrega_sigep_cnpj" value="' . $this->getConfigValue($config, 'entrega', 'sigep_cnpj', '') . '" placeholder="CNPJ do contrato">
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Serviço</label>
                                                        <select class="form-select" name="entrega_sigep_servico">
                                                            <option value="PAC" ' . ($this->getConfigValue($config, 'entrega', 'sigep_servico', 'PAC') === 'PAC' ? 'selected' : '') . '>PAC</option>
                                                            <option value="SEDEX" ' . ($this->getConfigValue($config, 'entrega', 'sigep_servico', '') === 'SEDEX' ? 'selected' : '') . '>SEDEX</option>
                                                        </select>
                                                        <small class="text-muted">Você pode ajustar quando tiver o contrato em mãos.</small>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Código do Serviço no Contrato</label>
                                                        <input type="text" class="form-control" name="entrega_sigep_servico_codigo" value="' . $this->getConfigValue($config, 'entrega', 'sigep_servico_codigo', '') . '" placeholder="Ex.: 04162 (depende do contrato)">
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="d-flex gap-2 flex-wrap">
                                                <button type="button" class="btn btn-outline-primary" onclick="testarSigepAPI()">
                                                    <i class="fas fa-plug"></i> Testar SIGEP
                                                </button>
                                                <small class="text-muted align-self-center">Executa um teste de solicita\u00E7\u00E3o de etiqueta via SIGEP e mostra o retorno.</small>
                                            </div>

                                            <hr>

                                            <h6 class="mb-3">Correios (Rastreamento)</h6>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Ativo</label>
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" id="correios_tracking_enabled" name="entrega_correios_tracking_enabled" value="1" ' . ($this->getConfigValue($config, 'entrega', 'correios_tracking_enabled', '0') === '1' ? 'checked' : '') . '>
                                                            <label class="form-check-label" for="correios_tracking_enabled">Habilitar rastreamento via API</label>
                                                        </div>
                                                        <small class="text-muted">Ative apenas quando tiver o token/API key e o endpoint.</small>
                                                    </div>
                                                </div>
                                                <div class="col-md-6"></div>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Token / API Key</label>
                                                <div class="input-group">
                                                    <input type="password" class="form-control" name="entrega_correios_tracking_token" value="' . $this->getConfigValue($config, 'entrega', 'correios_tracking_token', '') . '" placeholder="Cole o token/API key">
                                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility(this)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </div>
                                                <small class="text-muted">O sistema usa automaticamente o endpoint do Packet Service conforme o ambiente selecionado em SIGEP (Homologação/Produção).</small>
                                            </div>

                                            <hr>

                                            <h6 class="mb-3">Stamps (UPS) - Exterior</h6>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Ativo</label>
                                                        <div class="form-check form-switch">
                                                            <input class="form-check-input" type="checkbox" id="stamps_enabled" name="entrega_stamps_enabled" value="1" ' . ($this->getConfigValue($config, 'entrega', 'stamps_enabled', '0') === '1' ? 'checked' : '') . '>
                                                            <label class="form-check-label" for="stamps_enabled">Habilitar Stamps (UPS)</label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Ambiente</label>
                                                        <select class="form-select" name="entrega_stamps_ambiente">
                                                            <option value="staging" ' . ($this->getConfigValue($config, 'entrega', 'stamps_ambiente', 'staging') === 'staging' ? 'selected' : '') . '>Staging</option>
                                                            <option value="production" ' . ($this->getConfigValue($config, 'entrega', 'stamps_ambiente', '') === 'production' ? 'selected' : '') . '>Produção</option>
                                                        </select>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Client ID</label>
                                                        <input type="text" class="form-control" name="entrega_stamps_client_id" value="' . $this->getConfigValue($config, 'entrega', 'stamps_client_id', '') . '" placeholder="Client ID">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Client Secret</label>
                                                        <div class="input-group">
                                                            <input type="password" class="form-control" name="entrega_stamps_client_secret" value="' . $this->getConfigValue($config, 'entrega', 'stamps_client_secret', '') . '" placeholder="Client Secret">
                                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility(this)">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Refresh Token</label>
                                                <div class="input-group">
                                                    <input type="password" class="form-control" name="entrega_stamps_refresh_token" value="' . $this->getConfigValue($config, 'entrega', 'stamps_refresh_token', '') . '" placeholder="Refresh Token">
                                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility(this)">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </div>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">From Address (JSON)</label>
                                                <textarea class="form-control" name="entrega_stamps_from_address_json" rows="6" placeholder="{\n  \"name\": \"Sender\", ... }">' . htmlspecialchars((string) $this->getConfigValue($config, 'entrega', 'stamps_from_address_json', ''), ENT_QUOTES, 'UTF-8') . '</textarea>
                                                <small class="text-muted">Endereço do remetente (EUA) no formato esperado pela API da Stamps.</small>
                                            </div>

                                            <div class="row">
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Service Type</label>
                                                        <input type="text" class="form-control" name="entrega_stamps_service_type" value="' . $this->getConfigValue($config, 'entrega', 'stamps_service_type', '') . '" placeholder="Ex.: ups_ground / ups_worldwide_saver">
                                                    </div>
                                                </div>
                                                <div class="col-md-6">
                                                    <div class="mb-3">
                                                        <label class="form-label">Packaging Type</label>
                                                        <input type="text" class="form-control" name="entrega_stamps_packaging_type" value="' . $this->getConfigValue($config, 'entrega', 'stamps_packaging_type', 'package') . '" placeholder="package">
                                                    </div>
                                                </div>
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

                                <!-- Configurações Assessoria / IA -->
                                <div class="tab-pane fade" id="v-pills-assessoria" role="tabpanel">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="mb-0">Configurações da Assessoria (ScrapingBee + ChatGPT)</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="row">
                                                <div class="col-md-6">
                                                    <h6 class="mb-3">ScrapingBee</h6>
                                                    <div class="mb-3">
                                                        <label class="form-label">API Key</label>
                                                        <div class="input-group">
                                                            <input type="password" class="form-control" name="scrapingbee_api_key" value="' . $this->getConfigValue($config, 'scrapingbee', 'api_key', '') . '" placeholder="Cole a API Key do ScrapingBee">
                                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility(this)">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                </div>

                                                <div class="col-md-6">
                                                    <h6 class="mb-3">ChatGPT</h6>
                                                    <div class="mb-3">
                                                        <label class="form-label">API Key</label>
                                                        <div class="input-group">
                                                            <input type="password" class="form-control" name="chatgpt_api_key" value="' . $this->getConfigValue($config, 'chatgpt', 'api_key', '') . '" placeholder="Cole a API Key do ChatGPT">
                                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility(this)">
                                                                <i class="fas fa-eye"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Modelo</label>
                                                        <input type="text" class="form-control" name="chatgpt_model" value="' . $this->getConfigValue($config, 'chatgpt', 'model', 'gpt-3.5-turbo') . '">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Temperature</label>
                                                        <input type="text" class="form-control" name="chatgpt_temperature" value="' . $this->getConfigValue($config, 'chatgpt', 'temperature', '0.1') . '">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Max Tokens</label>
                                                        <input type="number" class="form-control" name="chatgpt_max_tokens" value="' . $this->getConfigValue($config, 'chatgpt', 'max_tokens', '1000') . '">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Margem de peso (%%)</label>
                                                        <input type="number" class="form-control" name="chatgpt_peso_margem" value="' . $this->getConfigValue($config, 'chatgpt', 'peso_margem', '15') . '">
                                                    </div>
                                                </div>
                                            </div>

                                            <hr>

                                            <div class="row">
                                                <div class="col-md-12">
                                                    <h6 class="mb-3">Webhooks da Assessoria</h6>
                                                    <div class="mb-3">
                                                        <label class="form-label">Webhook - Início do processamento do orçamento (URL)</label>
                                                        <input type="url" class="form-control" name="assessoria_webhook_inicio_url" value="' . $this->getConfigValue($config, 'assessoria', 'webhook_inicio_url', '') . '" placeholder="https://seu-webhook.com/assessoria/inicio">
                                                    </div>
                                                    <div class="mb-3">
                                                        <label class="form-label">Webhook - Conclusão do processamento do orçamento (URL)</label>
                                                        <input type="url" class="form-control" name="assessoria_webhook_conclusao_url" value="' . $this->getConfigValue($config, 'assessoria', 'webhook_conclusao_url', '') . '" placeholder="https://seu-webhook.com/assessoria/concluido">
                                                    </div>
                                                    <small class="text-muted">O sistema enviará POST em JSON com dados do usuário e do orçamento quando o processamento iniciar e quando finalizar.</small>
                                                </div>
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
                                                            <div class="mb-3">
                                                                <label class="form-label">Webhook Signing Secret</label>
                                                                <div class="input-group">
                                                                    <input type="password" class="form-control" name="pagamentos_stripe_webhook_secret" value="' . $this->getConfigValue($config, 'pagamentos', 'stripe_webhook_secret', '') . '" placeholder="whsec_...">
                                                                    <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility(this)">
                                                                        <i class="fas fa-eye"></i>
                                                                    </button>
                                                                </div>
                                                                <small class="text-muted">Signing secret do endpoint de webhook (whsec_...). Necessário para validar o Stripe-Signature.</small>
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
                                            
                                            <hr>

                                            <div class="row">
                                                <div class="col-12">
                                                    <div class="card">
                                                        <div class="card-header d-flex justify-content-between align-items-center">
                                                            <h6 class="mb-0">🇧🇷 AppMax</h6>
                                                            <div class="form-check form-switch">
                                                                <input class="form-check-input" type="checkbox" id="appmax_enabled" name="pagamentos_appmax_enabled" value="1" ' . ($this->getConfigValue($config, 'pagamentos', 'appmax_enabled', '0') === '1' ? 'checked' : '') . '>
                                                                <label class="form-check-label" for="appmax_enabled">Ativo</label>
                                                            </div>
                                                        </div>
                                                        <div class="card-body">
                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Client ID</label>
                                                                        <div class="input-group">
                                                                            <input type="password" class="form-control" name="pagamentos_appmax_client_id" value="' . $this->getConfigValue($config, 'pagamentos', 'appmax_client_id', '') . '" placeholder="CLIENT_ID">
                                                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility(this)">
                                                                                <i class="fas fa-eye"></i>
                                                                            </button>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Client Secret</label>
                                                                        <div class="input-group">
                                                                            <input type="password" class="form-control" name="pagamentos_appmax_client_secret" value="' . $this->getConfigValue($config, 'pagamentos', 'appmax_client_secret', '') . '" placeholder="CLIENT_SECRET">
                                                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility(this)">
                                                                                <i class="fas fa-eye"></i>
                                                                            </button>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="mb-3">
                                                                        <label class="form-label">App ID</label>
                                                                        <input type="text" class="form-control" name="pagamentos_appmax_app_id" value="' . $this->getConfigValue($config, 'pagamentos', 'appmax_app_id', '') . '" placeholder="APP_ID">
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Access Token</label>
                                                                        <div class="input-group">
                                                                            <input type="password" class="form-control" name="pagamentos_appmax_access_token" value="' . $this->getConfigValue($config, 'pagamentos', 'appmax_access_token', '') . '" placeholder="XXXXXXXX-XXXXXXXX-XXXXXXXX-XXXXXXXX">
                                                                            <button class="btn btn-outline-secondary" type="button" onclick="togglePasswordVisibility(this)">
                                                                                <i class="fas fa-eye"></i>
                                                                            </button>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Ambiente</label>
                                                                        <select class="form-select" name="pagamentos_appmax_ambiente">
                                                                            <option value="production" ' . ($this->getConfigValue($config, 'pagamentos', 'appmax_ambiente', 'production') === 'production' ? 'selected' : '') . '>Produção</option>
                                                                            <option value="homolog" ' . ($this->getConfigValue($config, 'pagamentos', 'appmax_ambiente', 'production') === 'homolog' ? 'selected' : '') . '>Homologação</option>
                                                                        </select>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-6">
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Base URL (opcional)</label>
                                                                        <input type="url" class="form-control" name="pagamentos_appmax_base_url" value="' . $this->getConfigValue($config, 'pagamentos', 'appmax_base_url', '') . '" placeholder="https://admin.appmax.com.br/api/v3">
                                                                    </div>
                                                                </div>
                                                            </div>

                                                            <div class="row">
                                                                <div class="col-md-6">
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Webhook URL</label>
                                                                        <input type="text" class="form-control" value="' . htmlspecialchars((isset($_SERVER['HTTP_HOST']) ? ((isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] : '') . '/webhook/appmax', ENT_QUOTES, 'UTF-8') . '" readonly>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>

                                            <div class="row">
                                                <div class="col-12">
                                                    <h6 class="mb-3">Webhook - Pedido Manual</h6>
                                                    <div class="mb-3">
                                                        <label class="form-label">Webhook - Link de Pagamento do Pedido Manual (URL)</label>
                                                        <input type="url" class="form-control" name="pagamentos_webhook_link_pagamento_pedido_manual_url" value="' . $this->getConfigValue($config, 'pagamentos', 'webhook_link_pagamento_pedido_manual_url', '') . '" placeholder="https://seu-webhook.com/pedidos/manual/link-pagamento">
                                                        <small class="text-muted">O sistema enviará POST em JSON com dados do pedido, cliente e link de pagamento assim que o link for gerado.</small>
                                                    </div>
                                                </div>
                                            </div>

                                            <hr>

                                            <div class="row">
                                                <div class="col-12">
                                                    <div class="card">
                                                        <div class="card-header">
                                                            <h6 class="mb-0">👑 Clube Brasiliana</h6>
                                                        </div>
                                                        <div class="card-body">
                                                            <div class="row">
                                                                <div class="col-md-4">
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Cashback (%)</label>
                                                                        <input type="number" step="0.01" min="0" class="form-control" name="clube_cashback_percent" value="' . htmlspecialchars((string) $this->getConfigValue($config, 'clube', 'cashback_percent', '0'), ENT_QUOTES, 'UTF-8') . '">
                                                                        <small class="text-muted">Percentual de cashback em créditos internos (apenas produtos com Clube Ativo).</small>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-4">
                                                                    <div class="mb-3">
                                                                        <label class="form-label">Rendimento (%)</label>
                                                                        <input type="number" step="0.01" min="0" class="form-control" name="clube_rendimento_percent" value="' . htmlspecialchars((string) $this->getConfigValue($config, 'clube', 'rendimento_percent', '0'), ENT_QUOTES, 'UTF-8') . '">
                                                                        <small class="text-muted">Percentual de créditos internos gerados periodicamente (saldo mínimo necessário).</small>
                                                                    </div>
                                                                </div>
                                                                <div class="col-md-4">
                                                                    <label class="form-label">Intervalo do rendimento</label>
                                                                    <div class="input-group mb-3">
                                                                        <input type="number" min="1" step="1" class="form-control" name="clube_rendimento_intervalo_valor" value="' . htmlspecialchars((string) $this->getConfigValue($config, 'clube', 'rendimento_intervalo_valor', '30'), ENT_QUOTES, 'UTF-8') . '">
                                                                        <select class="form-select" name="clube_rendimento_intervalo_unidade">
                                                                            ';

        $unit = (string) $this->getConfigValue($config, 'clube', 'rendimento_intervalo_unidade', 'dia');
        $unit = strtolower(trim($unit));
        if (!in_array($unit, ['minuto', 'hora', 'dia', 'mes'], true)) {
            $unit = 'dia';
        }

        echo '                                                                <option value="minuto" ' . ($unit === 'minuto' ? 'selected' : '') . '>Minuto(s)</option>
                                                                            <option value="hora" ' . ($unit === 'hora' ? 'selected' : '') . '>Hora(s)</option>
                                                                            <option value="dia" ' . ($unit === 'dia' ? 'selected' : '') . '>Dia(s)</option>
                                                                            <option value="mes" ' . ($unit === 'mes' ? 'selected' : '') . '>Mês(es)</option>
                                                                        </select>
                                                                    </div>
                                                                    <small class="text-muted">Configura a periodicidade do crédito por permanência.</small>
                                                                </div>
                                                            </div>

                                                            <div class="row mt-2">
                                                                <div class="col-12">
                                                                    <div class="border rounded p-3 bg-light">
                                                                        <div class="fw-semibold mb-2">Faixas de desconto progressivo (peso total de produtos com Clube Ativo)</div>
                                                                        <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                                                                            <div class="text-muted small">Para cadastrar uma nova faixa: preencha a linha <strong>Nova</strong> abaixo (o <strong>Peso mín</strong> pode ser <strong>0</strong>) e clique em <strong>Salvar Configurações</strong>.</div>
                                                                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="try{var els=document.getElementsByName(\'clube_faixa_nova[percentual_desconto]\'); if(els&&els[0]) els[0].focus();}catch(e){}">Nova faixa</button>
                                                                        </div>
                                                                        <div class="table-responsive">
                                                                            <table class="table table-sm align-middle mb-0">
                                                                                <thead>
                                                                                    <tr>
                                                                                        <th style="width:80px;">Ativo</th>
                                                                                        <th style="width:120px;">Ordem</th>
                                                                                        <th style="width:180px;">Peso mín (kg)</th>
                                                                                        <th style="width:180px;">Peso máx (kg)</th>
                                                                                        <th style="width:180px;">Desconto (%)</th>
                                                                                        <th style="width:120px;">Remover</th>
                                                                                    </tr>
                                                                                </thead>
                                                                                <tbody>';

        if (empty($clubeFaixas)) {
            echo '<tr><td colspan="6" class="text-center text-muted">Nenhuma faixa cadastrada</td></tr>';
        } else {
            foreach ($clubeFaixas as $fx) {
                $idFx = (int) ($fx['id'] ?? 0);
                $ativoFx = (int) ($fx['ativo'] ?? 0);
                $ordFx = (int) ($fx['ordem'] ?? 0);
                $minFx = (string) ($fx['peso_min_kg'] ?? '0');
                $maxFx = (string) ($fx['peso_max_kg'] ?? '0');
                $pctFx = (string) ($fx['percentual_desconto'] ?? '0');

                echo '<tr>'
                    . '<td>'
                    . '<input type="hidden" name="clube_faixas[' . $idFx . '][id]" value="' . $idFx . '">'
                    . '<input type="hidden" name="clube_faixas[' . $idFx . '][ativo]" value="0">'
                    . '<input class="form-check-input" type="checkbox" name="clube_faixas[' . $idFx . '][ativo]" value="1" ' . ($ativoFx ? 'checked' : '') . '>'
                    . '</td>'
                    . '<td><input type="number" class="form-control form-control-sm" name="clube_faixas[' . $idFx . '][ordem]" value="' . htmlspecialchars((string) $ordFx, ENT_QUOTES, 'UTF-8') . '" step="1"></td>'
                    . '<td><input type="number" class="form-control form-control-sm" name="clube_faixas[' . $idFx . '][peso_min_kg]" value="' . htmlspecialchars((string) $minFx, ENT_QUOTES, 'UTF-8') . '" step="0.001" min="0"></td>'
                    . '<td><input type="number" class="form-control form-control-sm" name="clube_faixas[' . $idFx . '][peso_max_kg]" value="' . htmlspecialchars((string) $maxFx, ENT_QUOTES, 'UTF-8') . '" step="0.001" min="0"></td>'
                    . '<td><input type="number" class="form-control form-control-sm" name="clube_faixas[' . $idFx . '][percentual_desconto]" value="' . htmlspecialchars((string) $pctFx, ENT_QUOTES, 'UTF-8') . '" step="0.01" min="0"></td>'
                    . '<td class="text-center"><input class="form-check-input" type="checkbox" name="clube_faixas_remover[]" value="' . $idFx . '"></td>'
                    . '</tr>';
            }
        }

        echo '                                                                <tr>'
                                                                                    . '<td>'
                                                                                    . '<input type="hidden" name="clube_faixa_nova[ativo]" value="0">'
                                                                                    . '<input class="form-check-input" type="checkbox" name="clube_faixa_nova[ativo]" value="1" checked>'
                                                                                    . '</td>'
                                                                                    . '<td><input type="number" class="form-control form-control-sm" name="clube_faixa_nova[ordem]" value="0" step="1"></td>'
                                                                                    . '<td><input type="number" class="form-control form-control-sm" name="clube_faixa_nova[peso_min_kg]" value="0" step="0.001" min="0"></td>'
                                                                                    . '<td><input type="number" class="form-control form-control-sm" name="clube_faixa_nova[peso_max_kg]" value="0" step="0.001" min="0"></td>'
                                                                                    . '<td><input type="number" class="form-control form-control-sm" name="clube_faixa_nova[percentual_desconto]" value="0" step="0.01" min="0"></td>'
                                                                                    . '<td class="text-muted small">Nova</td>'
                                                                                    . '</tr>';

        echo '                                                                </tbody>
                                                                            </table>
                                                                        </div>
                                                                        <div class="text-muted small mt-2">O desconto progressivo será calculado somente com base no peso total dos produtos com Clube Ativo.</div>
                                                                    </div>
                                                                </div>
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

                                <div class="tab-pane fade" id="v-pills-comissoes" role="tabpanel">
                                    <div class="card">
                                        <div class="card-header">
                                            <h5 class="mb-0">Configurações de Comissões</h5>
                                        </div>
                                        <div class="card-body">
                                            <div class="row g-3 mb-4">
                                                <div class="col-md-4">
                                                    <label class="form-label">Início da 1ª janela</label>
                                                    <input type="date" class="form-control" name="comissao_janela_primeiro_inicio" value="' . htmlspecialchars($this->getConfigValue($config, 'comissao', 'janela_primeiro_inicio', ''), ENT_QUOTES, 'UTF-8') . '">
                                                    <small class="text-muted">Defina a data de início da primeira janela global.</small>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Fim da 1ª janela</label>
                                                    <input type="date" class="form-control" name="comissao_janela_primeiro_fim" value="' . htmlspecialchars($this->getConfigValue($config, 'comissao', 'janela_primeiro_fim', ''), ENT_QUOTES, 'UTF-8') . '">
                                                    <small class="text-muted">Defina a data de término da primeira janela global.</small>
                                                </div>
                                                <div class="col-md-4">
                                                    <label class="form-label">Duração das janelas (dias)</label>
                                                    <input type="number" min="1" step="1" class="form-control" name="comissao_janela_duracao_dias" value="' . htmlspecialchars($this->getConfigValue($config, 'comissao', 'janela_duracao_dias', '14'), ENT_QUOTES, 'UTF-8') . '">
                                                    <small class="text-muted">Após a 1ª janela, as próximas são calculadas automaticamente.</small>
                                                </div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label">Faixas de comissão (Pedidos Manuais)</label>
                                                <input type="hidden" name="comissao_manual_faixas" id="comissao_manual_faixas" value="' . htmlspecialchars($this->getConfigValue($config, 'comissao', 'manual_faixas', '[{"min":0,"max":999999999,"percent":0}]'), ENT_QUOTES, 'UTF-8') . '">
                                                <div class="table-responsive">
                                                    <table class="table table-sm table-bordered align-middle" id="comissaoManualFaixasTable">
                                                        <thead>
                                                            <tr>
                                                                <th style="width: 30%">Mínimo (R$)</th>
                                                                <th style="width: 30%">Máximo (R$)</th>
                                                                <th style="width: 25%">Comissão (%)</th>
                                                                <th style="width: 15%">Ações</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody id="comissaoManualFaixasBody"></tbody>
                                                    </table>
                                                </div>
                                                <button type="button" class="btn btn-outline-primary btn-sm" id="btnAddComissaoFaixa">
                                                    <i class="fas fa-plus"></i> Adicionar faixa
                                                </button>
                                                <small class="text-muted d-block mt-2">O faturamento usado é a soma do total faturado de pedidos manuais pagos.</small>
                                            </div>

                                            ' . $repComissoesHtml . '
                                        </div>
                                    </div>
                                </div>

                                ' . $mapaCalorTabHtml . '
                                
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
                                                    <option value="USD" ' . ($this->getConfigValue($config, 'sistema', 'moeda', 'USD') === 'USD' ? 'selected' : '') . '>Dólar (USD)</option>
                                                    <option value="BRL" ' . ($this->getConfigValue($config, 'sistema', 'moeda', '') === 'BRL' ? 'selected' : '') . '>Real (BRL)</option>
                                                    <option value="EUR" ' . ($this->getConfigValue($config, 'sistema', 'moeda', '') === 'EUR' ? 'selected' : '') . '>Euro (EUR)</option>
                                                </select>
                                            </div>

                                            <div class="mb-3">
                                                <label class="form-label">Taxa de conversão USD → BRL</label>
                                                <input type="number" step="0.0001" min="0" class="form-control" name="sistema_usd_brl_rate" value="' . htmlspecialchars($this->getConfigValue($config, 'sistema', 'usd_brl_rate', '5.5'), ENT_QUOTES, 'UTF-8') . '">
                                                <small class="text-muted">Taxa usada no conversor global e para cálculos auxiliares em BRL quando necessário.</small>
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
                            
                            <div class="d-flex justify-content-end mt-4" id="admin-config-salvar-geral">
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
    ' . $this->getPagamentosJS() . $this->getEmailCreatorJS() . $this->getNotificacoesJS() . $this->getEntregaJS() . $this->getComissoesJS() . '
</body>
</html>';
        exit;
    }

    private function getComissoesJS(): string {
        return <<<'JS'
<script>
document.addEventListener('DOMContentLoaded', function(){
    const hidden = document.getElementById('comissao_manual_faixas');
    const body = document.getElementById('comissaoManualFaixasBody');
    const btnAdd = document.getElementById('btnAddComissaoFaixa');
    if (!hidden || !body || !btnAdd) return;

    const normalizeNumber = (v) => {
        if (v === null || v === undefined) return 0;
        const s = String(v).replace(',', '.').trim();
        const n = Number(s);
        return isNaN(n) ? 0 : n;
    };

    const parseJson = () => {
        try {
            const raw = String(hidden.value || '').trim();
            if (!raw) return [];
            const arr = JSON.parse(raw);
            return Array.isArray(arr) ? arr : [];
        } catch (e) {
            return [];
        }
    };

    const serialize = () => {
        const rows = [];
        body.querySelectorAll('tr').forEach(tr => {
            const min = normalizeNumber(tr.querySelector('.cm-min')?.value);
            const max = normalizeNumber(tr.querySelector('.cm-max')?.value);
            const percent = normalizeNumber(tr.querySelector('.cm-percent')?.value);
            rows.push({ min, max, percent });
        });
        hidden.value = JSON.stringify(rows);
    };

    const addRow = (min, max, percent) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td><input type="number" step="0.01" min="0" class="form-control form-control-sm cm-min" value="${String(min ?? 0)}"></td>
            <td><input type="number" step="0.01" min="0" class="form-control form-control-sm cm-max" value="${String(max ?? 0)}"></td>
            <td><input type="number" step="0.01" min="0" class="form-control form-control-sm cm-percent" value="${String(percent ?? 0)}"></td>
            <td>
                <button type="button" class="btn btn-outline-danger btn-sm cm-del"><i class="fas fa-trash"></i></button>
            </td>
        `;
        body.appendChild(tr);

        tr.querySelectorAll('input').forEach(inp => {
            inp.addEventListener('input', serialize);
            inp.addEventListener('change', serialize);
        });
        tr.querySelector('.cm-del')?.addEventListener('click', function(){
            tr.remove();
            serialize();
        });
    };

    const initial = parseJson();
    if (initial.length === 0) {
        addRow(0, 999999999, 0);
    } else {
        initial.forEach(it => addRow(it.min ?? 0, it.max ?? 0, it.percent ?? 0));
    }
    serialize();

    btnAdd.addEventListener('click', function(){
        addRow(0, 0, 0);
        serialize();
    });

    const form = hidden.closest('form');
    if (form) {
        form.addEventListener('submit', function(){
            serialize();
        });
    }
});
</script>
JS;
    }

    private function tableExists(\PDO $pdo, string $table): bool {
        try {
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
            $stmt->execute([$table]);
            return ((int) $stmt->fetchColumn() > 0);
        } catch (\Exception $e) {
            return false;
        }
    }

    private function renderRepresentantesComissoesHtml(?\PDO $pdo): string {
        if (!$pdo || !$this->tableExists($pdo, 'usuarios') || !$this->tableExists($pdo, 'representante_comissoes')) {
            return '';
        }

        $uCols = $this->getColumns($pdo, 'usuarios');
        $nomeCol = $this->pickColumn($uCols, ['nome', 'name']);
        if (!$nomeCol) {
            return '';
        }
        if (!in_array('perfil', $uCols, true)) {
            return '';
        }

        $reps = [];
        try {
            $st = $pdo->prepare('SELECT id, ' . $nomeCol . ' AS nome, email FROM usuarios WHERE LOWER(COALESCE(perfil,\'\')) = \'representante\' ORDER BY ' . $nomeCol . ' ASC');
            $st->execute();
            $reps = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            $reps = [];
        }

        if (empty($reps)) {
            return '<div class="alert alert-info">Nenhum usuário com perfil Representante encontrado.</div>';
        }

        $map = [];
        try {
            $ids = array_values(array_filter(array_map(function ($r) {
                return (int) ($r['id'] ?? 0);
            }, $reps)));
            $ids = array_values(array_unique($ids));
            if (!empty($ids)) {
                $in = implode(',', array_fill(0, count($ids), '?'));
                $st2 = $pdo->prepare('SELECT representante_id, percentual, ativo FROM representante_comissoes WHERE representante_id IN (' . $in . ')');
                $st2->execute($ids);
                foreach (($st2->fetchAll(\PDO::FETCH_ASSOC) ?: []) as $row) {
                    $rid = (int) ($row['representante_id'] ?? 0);
                    if ($rid > 0) {
                        $map[$rid] = [
                            'percentual' => (float) ($row['percentual'] ?? 0),
                            'ativo' => (int) ($row['ativo'] ?? 1),
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            $map = [];
        }

        $html = '<hr>'
            . '<h6 class="mb-2">Comissões por Representante</h6>'
            . '<div class="text-muted small mb-2">Configure o percentual (%) de comissão para cada representante. Usado no painel do representante e no cálculo: (venda - custo) * %.</div>'
            . '<div class="table-responsive">'
            . '<table class="table table-sm table-bordered align-middle">'
            . '<thead><tr><th style="width:45%">Representante</th><th style="width:35%">E-mail</th><th style="width:20%">Comissão (%)</th></tr></thead><tbody>';

        foreach ($reps as $r) {
            $rid = (int) ($r['id'] ?? 0);
            $nome = htmlspecialchars((string) ($r['nome'] ?? ''), ENT_QUOTES, 'UTF-8');
            $email = htmlspecialchars((string) ($r['email'] ?? ''), ENT_QUOTES, 'UTF-8');
            $pct = isset($map[$rid]) ? (float) ($map[$rid]['percentual'] ?? 0) : 0.0;
            $pctEsc = htmlspecialchars((string) $pct, ENT_QUOTES, 'UTF-8');
            $html .= '<tr>'
                . '<td>' . $nome . ' <span class="text-muted">(#' . $rid . ')</span></td>'
                . '<td>' . $email . '</td>'
                . '<td><input type="number" min="0" max="100" step="0.01" class="form-control form-control-sm" name="representante_comissoes[' . $rid . ']" value="' . $pctEsc . '"></td>'
                . '</tr>';
        }

        $html .= '</tbody></table></div>';
        return $html;
    }

    private function getColumns(\PDO $pdo, string $table): array {
        try {
            $stmt = $pdo->query('DESCRIBE ' . $table);
            $cols = $stmt ? $stmt->fetchAll(\PDO::FETCH_COLUMN) : [];
            return is_array($cols) ? $cols : [];
        } catch (\Exception $e) {
            return [];
        }
    }

    private function pickColumn(array $cols, array $candidates): ?string {
        foreach ($candidates as $c) {
            if (in_array($c, $cols, true)) {
                return $c;
            }
        }
        return null;
    }

    private function detectItensTable(\PDO $pdo): ?string {
        foreach (['pedido_itens', 'pedido_items', 'itens_pedido'] as $t) {
            if ($this->tableExists($pdo, $t)) {
                return $t;
            }
        }
        return null;
    }

    private function normalizePaidWhere(?string $pedidoStatusCol): string {
        if (!$pedidoStatusCol) {
            return '';
        }
        $paid = [
            'pago','paid','approved','confirmed','received','succeeded','success','enviado','entregue'
        ];
        return " WHERE LOWER(COALESCE(ped.{$pedidoStatusCol}, '')) IN ('" . implode("','", $paid) . "')";
    }

    private function getMapaCalorData($pdo): array {
        if (!$pdo instanceof \PDO) {
            return [];
        }

        $out = [
            'sexo' => [],
            'faixa_etaria_consumo' => [],
            'regioes_estado' => [],
            'regioes_pais' => [],
            'mais_vendidos_produtos' => [],
            'mais_vendidos_categorias' => [],
            'mais_vendidos_tipos' => [],
        ];

        // Usuários
        if ($this->tableExists($pdo, 'usuarios')) {
            $uCols = $this->getColumns($pdo, 'usuarios');
            $sexoCol = $this->pickColumn($uCols, ['sexo', 'genero', 'gender']);
            if ($sexoCol) {
                try {
                    $stmt = $pdo->query("SELECT LOWER(TRIM(COALESCE({$sexoCol}, '')) ) AS sexo, COUNT(*) AS total FROM usuarios GROUP BY LOWER(TRIM(COALESCE({$sexoCol}, '')) ) ORDER BY total DESC");
                    $out['sexo'] = $stmt ? ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];
                } catch (\Exception $e) {
                    $out['sexo'] = [];
                }
            }

            $estadoCol = $this->pickColumn($uCols, ['estado', 'uf', 'state']);
            if ($estadoCol) {
                try {
                    $stmt = $pdo->query("SELECT UPPER(TRIM(COALESCE({$estadoCol}, ''))) AS estado, COUNT(*) AS total FROM usuarios GROUP BY UPPER(TRIM(COALESCE({$estadoCol}, ''))) ORDER BY total DESC");
                    $out['regioes_estado'] = $stmt ? ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];
                } catch (\Exception $e) {
                    $out['regioes_estado'] = [];
                }
            }

            $paisCol = $this->pickColumn($uCols, ['pais_residencia', 'pais', 'country']);
            if ($paisCol) {
                try {
                    $stmt = $pdo->query("SELECT UPPER(TRIM(COALESCE({$paisCol}, ''))) AS pais, COUNT(*) AS total FROM usuarios GROUP BY UPPER(TRIM(COALESCE({$paisCol}, ''))) ORDER BY total DESC");
                    $out['regioes_pais'] = $stmt ? ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];
                } catch (\Exception $e) {
                    $out['regioes_pais'] = [];
                }
            }
        }

        // Consumo por faixa etária
        if ($this->tableExists($pdo, 'usuarios') && $this->tableExists($pdo, 'pedidos')) {
            $uCols = $this->getColumns($pdo, 'usuarios');
            $pCols = $this->getColumns($pdo, 'pedidos');

            $dataNascCol = $this->pickColumn($uCols, ['data_nascimento', 'nascimento', 'birthdate', 'dob']);
            $usuarioIdCol = $this->pickColumn($pCols, ['usuario_id', 'user_id']);
            $totalCol = $this->pickColumn($pCols, ['valor_total', 'total', 'amount', 'valor']);
            $statusCol = $this->pickColumn($pCols, ['status', 'payment_status', 'status_pagamento', 'pagamento_status']);

            if ($dataNascCol && $usuarioIdCol && $totalCol) {
                $wherePaid = '';
                if ($statusCol) {
                    $paid = [
                        'pago','paid','approved','confirmed','received','succeeded','success','enviado','entregue'
                    ];
                    $wherePaid = " AND LOWER(COALESCE(p.{$statusCol}, '')) IN ('" . implode("','", $paid) . "')";
                }

                $idadeExpr = "TIMESTAMPDIFF(YEAR, u.{$dataNascCol}, CURDATE())";
                $faixaExpr = "CASE\n"
                    . " WHEN {$idadeExpr} < 18 THEN '0-17'\n"
                    . " WHEN {$idadeExpr} BETWEEN 18 AND 24 THEN '18-24'\n"
                    . " WHEN {$idadeExpr} BETWEEN 25 AND 34 THEN '25-34'\n"
                    . " WHEN {$idadeExpr} BETWEEN 35 AND 44 THEN '35-44'\n"
                    . " WHEN {$idadeExpr} BETWEEN 45 AND 54 THEN '45-54'\n"
                    . " WHEN {$idadeExpr} BETWEEN 55 AND 64 THEN '55-64'\n"
                    . " ELSE '65+'\n END";

                try {
                    $sql = "SELECT {$faixaExpr} AS faixa, SUM(COALESCE(p.{$totalCol},0)) AS total_gasto, COUNT(*) AS pedidos\n"
                        . "FROM pedidos p\n"
                        . "INNER JOIN usuarios u ON u.id = p.{$usuarioIdCol}\n"
                        . "WHERE u.{$dataNascCol} IS NOT NULL {$wherePaid}\n"
                        . "GROUP BY faixa\n"
                        . "ORDER BY total_gasto DESC";
                    $stmt = $pdo->query($sql);
                    $out['faixa_etaria_consumo'] = $stmt ? ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];
                } catch (\Exception $e) {
                    $out['faixa_etaria_consumo'] = [];
                }
            }
        }

        // Mais vendidos (produtos / categorias / tipos)
        $itensTable = null;
        foreach (['pedido_itens', 'pedido_items', 'itens_pedido'] as $t) {
            if ($this->tableExists($pdo, $t)) {
                $itensTable = $t;
                break;
            }
        }
        if ($itensTable && $this->tableExists($pdo, 'produtos') && $this->tableExists($pdo, 'pedidos')) {
            $iCols = $this->getColumns($pdo, $itensTable);
            $pCols = $this->getColumns($pdo, 'pedidos');
            $prCols = $this->getColumns($pdo, 'produtos');

            $colPedidoId = $this->pickColumn($iCols, ['pedido_id']);
            $colProdutoId = $this->pickColumn($iCols, ['produto_id', 'product_id']);
            $colQtd = $this->pickColumn($iCols, ['quantidade', 'qty', 'qtd']);
            $pedidoStatusCol = $this->pickColumn($pCols, ['status', 'payment_status', 'status_pagamento', 'pagamento_status']);

            $wherePaid = '';
            if ($pedidoStatusCol) {
                $paid = [
                    'pago','paid','approved','confirmed','received','succeeded','success','enviado','entregue'
                ];
                $wherePaid = " WHERE LOWER(COALESCE(ped.{$pedidoStatusCol}, '')) IN ('" . implode("','", $paid) . "')";
            }

            if ($colPedidoId && $colProdutoId) {
                $qtdExpr = $colQtd ? "SUM(COALESCE(i.{$colQtd},0))" : 'COUNT(*)';
                $nomeProdutoCol = $this->pickColumn($prCols, ['name', 'nome']);
                $tipoCol = $this->pickColumn($prCols, ['type', 'tipo']);

                // Produtos mais vendidos
                if ($nomeProdutoCol) {
                    try {
                        $sql = "SELECT pr.id, pr.{$nomeProdutoCol} AS produto, {$qtdExpr} AS quantidade\n"
                            . "FROM {$itensTable} i\n"
                            . "INNER JOIN pedidos ped ON ped.id = i.{$colPedidoId}\n"
                            . "INNER JOIN produtos pr ON pr.id = i.{$colProdutoId}\n"
                            . $wherePaid . "\n"
                            . "GROUP BY pr.id, pr.{$nomeProdutoCol}\n"
                            . "ORDER BY quantidade DESC\n"
                            . "LIMIT 15";
                        $stmt = $pdo->query($sql);
                        $out['mais_vendidos_produtos'] = $stmt ? ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];
                    } catch (\Exception $e) {
                        $out['mais_vendidos_produtos'] = [];
                    }
                }

                // Categorias mais vendidas
                if ($this->tableExists($pdo, 'categorias') && in_array('category_id', $prCols, true)) {
                    $cCols = $this->getColumns($pdo, 'categorias');
                    $catNomeCol = $this->pickColumn($cCols, ['name', 'nome']);
                    if ($catNomeCol) {
                        try {
                            $sql = "SELECT c.{$catNomeCol} AS categoria, {$qtdExpr} AS quantidade\n"
                                . "FROM {$itensTable} i\n"
                                . "INNER JOIN pedidos ped ON ped.id = i.{$colPedidoId}\n"
                                . "INNER JOIN produtos pr ON pr.id = i.{$colProdutoId}\n"
                                . "LEFT JOIN categorias c ON c.id = pr.category_id\n"
                                . $wherePaid . "\n"
                                . "GROUP BY c.{$catNomeCol}\n"
                                . "ORDER BY quantidade DESC\n"
                                . "LIMIT 15";
                            $stmt = $pdo->query($sql);
                            $out['mais_vendidos_categorias'] = $stmt ? ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];
                        } catch (\Exception $e) {
                            $out['mais_vendidos_categorias'] = [];
                        }
                    }
                }

                // Tipos mais vendidos
                if ($tipoCol) {
                    try {
                        $sql = "SELECT COALESCE(NULLIF(TRIM(pr.{$tipoCol}),''), 'sem_tipo') AS tipo, {$qtdExpr} AS quantidade\n"
                            . "FROM {$itensTable} i\n"
                            . "INNER JOIN pedidos ped ON ped.id = i.{$colPedidoId}\n"
                            . "INNER JOIN produtos pr ON pr.id = i.{$colProdutoId}\n"
                            . $wherePaid . "\n"
                            . "GROUP BY tipo\n"
                            . "ORDER BY quantidade DESC\n"
                            . "LIMIT 15";
                        $stmt = $pdo->query($sql);
                        $out['mais_vendidos_tipos'] = $stmt ? ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];
                    } catch (\Exception $e) {
                        $out['mais_vendidos_tipos'] = [];
                    }
                }
            }
        }

        return $out;
    }

    private function renderMapaCalorTabHtml(array $data): string {
        $sexo = $data['sexo'] ?? [];
        $faixa = $data['faixa_etaria_consumo'] ?? [];
        $estados = $data['regioes_estado'] ?? [];
        $paises = $data['regioes_pais'] ?? [];
        $produtos = $data['mais_vendidos_produtos'] ?? [];
        $categorias = $data['mais_vendidos_categorias'] ?? [];
        $tipos = $data['mais_vendidos_tipos'] ?? [];

        $renderRows = function($rows, $cols) {
            if (!is_array($rows) || empty($rows)) {
                return '<tr><td colspan="' . count($cols) . '" class="text-center text-muted">Sem dados</td></tr>';
            }
            $html = '';
            foreach ($rows as $r) {
                if (!is_array($r)) continue;
                $html .= '<tr>';
                foreach ($cols as $c) {
                    $v = $r[$c] ?? '';
                    $html .= '<td>' . htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8') . '</td>';
                }
                $html .= '</tr>';
            }
            return $html;
        };

        $sexRows = $renderRows($sexo, ['sexo', 'total']);
        $faixaRows = $renderRows($faixa, ['faixa', 'total_gasto', 'pedidos']);
        $estadoRows = $renderRows($estados, ['estado', 'total']);
        $paisRows = $renderRows($paises, ['pais', 'total']);
        $prodRows = $renderRows($produtos, ['id', 'produto', 'quantidade']);
        $catRows = $renderRows($categorias, ['categoria', 'quantidade']);
        $tipoRows = $renderRows($tipos, ['tipo', 'quantidade']);

        $cardsCategorias = '';
        if (is_array($categorias) && !empty($categorias)) {
            foreach ($categorias as $c) {
                if (!is_array($c)) continue;
                $nome = (string) ($c['categoria'] ?? '');
                if (trim($nome) === '') continue;
                $qtd = (string) ($c['quantidade'] ?? '0');
                $cardsCategorias .= '<div class="col-6 col-lg-4">'
                    . '<a href="#" class="card h-100 text-decoration-none mapa-calor-card" data-seg="categoria" data-val="' . htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') . '">'
                    . '<div class="card-body">'
                    . '<div class="small text-muted">Categoria</div>'
                    . '<div class="fw-bold">' . htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') . '</div>'
                    . '<div class="small">Vendidos: <span class="fw-semibold">' . htmlspecialchars($qtd, ENT_QUOTES, 'UTF-8') . '</span></div>'
                    . '</div></a></div>';
            }
        }
        if ($cardsCategorias === '') {
            $cardsCategorias = '<div class="col-12"><div class="text-muted">Sem dados de categorias para segmentar.</div></div>';
        }

        $cardsLojas = '<div class="col-12"><div class="text-muted">Sem dados de lojas para segmentar.</div></div>';
        // Lojas serão carregadas via AJAX (quando existir tabela lojas ou colunas loja/loja_id)
        $cardsLojas = '<div class="col-12" id="mapaCalorLojasWrap"><div class="text-muted">Carregando lojas...</div></div>';

        $mapaCalorScript = <<<'HTML'
                        <script>
                            (function(){
                                function qs(sel){ return document.querySelector(sel); }
                                function esc(s){
                                    return String(s ?? '')
                                        .replace(/&/g,'&amp;')
                                        .replace(/</g,'&lt;')
                                        .replace(/>/g,'&gt;')
                                        .replace(/"/g,'&quot;')
                                        .replace(/'/g,'&#039;');
                                }

                                async function loadClientes(seg, val){
                                    const card = qs('#mapaCalorSegmentoCard');
                                    const body = qs('#mapaCalorClientesBody');
                                    const title = qs('#mapaCalorSegmentoTitulo');
                                    const sub = qs('#mapaCalorSegmentoSub');
                                    const exportBtn = qs('#mapaCalorExportBtn');
                                    if (!card || !body || !title || !sub || !exportBtn) return;

                                    card.style.display = 'block';
                                    title.textContent = 'Clientes do segmento';
                                    sub.textContent = seg + ': ' + val;
                                    exportBtn.href = '/admin/configuracoes/mapa-calor/export-emails?seg=' + encodeURIComponent(seg) + '&val=' + encodeURIComponent(val);

                                    body.innerHTML = '<tr><td colspan="4" class="text-center text-muted">Carregando...</td></tr>';

                                    try {
                                        const resp = await fetch('/admin/configuracoes/mapa-calor/clientes?seg=' + encodeURIComponent(seg) + '&val=' + encodeURIComponent(val));
                                        const json = await resp.json();
                                        const rows = (json && json.clientes) ? json.clientes : [];
                                        if (!rows.length) {
                                            body.innerHTML = '<tr><td colspan="4" class="text-center text-muted">Sem dados</td></tr>';
                                            return;
                                        }
                                        let html = '';
                                        rows.forEach(r => {
                                            html += '<tr>'
                                                + '<td>' + esc(r.nome || '') + '</td>'
                                                + '<td>' + esc(r.email || '') + '</td>'
                                                + '<td>' + esc(r.pedidos || 0) + '</td>'
                                                + '<td>' + esc(r.total_gasto || 0) + '</td>'
                                                + '</tr>';
                                        });
                                        body.innerHTML = html;
                                    } catch (e) {
                                        body.innerHTML = '<tr><td colspan="4" class="text-center text-danger">Erro ao carregar</td></tr>';
                                    }
                                }

                                function bindCards(){
                                    document.querySelectorAll('.mapa-calor-card').forEach(el => {
                                        el.addEventListener('click', function(ev){
                                            ev.preventDefault();
                                            const seg = this.getAttribute('data-seg') || '';
                                            const val = this.getAttribute('data-val') || '';
                                            if (!seg || !val) return;
                                            loadClientes(seg, val);
                                        });
                                    });
                                }

                                async function loadLojas(){
                                    const grid = qs('#mapaCalorLojasGrid');
                                    if (!grid) return;
                                    try {
                                        const resp = await fetch('/admin/configuracoes/mapa-calor/clientes?seg=lojas');
                                        const json = await resp.json();
                                        const lojas = (json && json.lojas) ? json.lojas : [];
                                        if (!lojas.length) {
                                            grid.innerHTML = '<div class="col-12"><div class="text-muted">Sem dados de lojas para segmentar.</div></div>';
                                            bindCards();
                                            return;
                                        }
                                        let html = '';
                                        lojas.forEach(l => {
                                            html += '<div class="col-6 col-lg-4">'
                                                + '<a href="#" class="card h-100 text-decoration-none mapa-calor-card" data-seg="loja" data-val="' + esc(l.label || '') + '">'
                                                + '<div class="card-body">'
                                                + '<div class="small text-muted">Loja</div>'
                                                + '<div class="fw-bold">' + esc(l.label || '') + '</div>'
                                                + '<div class="small">Vendidos: <span class="fw-semibold">' + esc(l.quantidade || 0) + '</span></div>'
                                                + '</div></a></div>';
                                        });
                                        grid.innerHTML = html;
                                        bindCards();
                                    } catch (e) {
                                        grid.innerHTML = '<div class="col-12"><div class="text-danger">Erro ao carregar lojas</div></div>';
                                    }
                                }

                                bindCards();
                                loadLojas();
                            })();
                        </script>
HTML;

        return '
            <div class="tab-pane fade" id="v-pills-mapa-calor" role="tabpanel">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Mapa de calor</h5>
                        <span class="badge bg-secondary">Beta</span>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info" style="border-radius: 12px;">
                            Clique em uma categoria ou loja para ver os clientes que mais consumiram e exportar e-mails para campanhas.
                        </div>

                        <div class="row g-3 mb-4">
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header"><strong>Segmentação por categoria</strong></div>
                                    <div class="card-body">
                                        <div class="row g-2">' . $cardsCategorias . '</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header"><strong>Segmentação por loja</strong></div>
                                    <div class="card-body">
                                        <div class="row g-2" id="mapaCalorLojasGrid">' . $cardsLojas . '</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card mb-4" id="mapaCalorSegmentoCard" style="display:none;">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <div>
                                    <strong id="mapaCalorSegmentoTitulo">Clientes do segmento</strong>
                                    <div class="small text-muted" id="mapaCalorSegmentoSub"></div>
                                </div>
                                <div class="d-flex gap-2">
                                    <a class="btn btn-sm btn-outline-primary" id="mapaCalorExportBtn" href="#" target="_blank">
                                        <i class="fas fa-file-csv me-1"></i>Exportar e-mails (CSV)
                                    </a>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-sm table-striped mb-0">
                                        <thead>
                                            <tr>
                                                <th>Cliente</th>
                                                <th>E-mail</th>
                                                <th>Pedidos</th>
                                                <th>Total gasto</th>
                                            </tr>
                                        </thead>
                                        <tbody id="mapaCalorClientesBody">
                                            <tr><td colspan="4" class="text-center text-muted">Selecione um segmento acima.</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-lg-6">
                                <div class="card">
                                    <div class="card-header"><strong>Usuários por sexo</strong></div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-sm table-striped mb-0">
                                                <thead><tr><th>Sexo</th><th>Total</th></tr></thead>
                                                <tbody>' . $sexRows . '</tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="card">
                                    <div class="card-header"><strong>Faixa etária com maior consumo</strong></div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-sm table-striped mb-0">
                                                <thead><tr><th>Faixa</th><th>Total gasto</th><th>Pedidos</th></tr></thead>
                                                <tbody>' . $faixaRows . '</tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="card">
                                    <div class="card-header"><strong>Regiões de cadastro (Estados)</strong></div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-sm table-striped mb-0">
                                                <thead><tr><th>Estado</th><th>Total</th></tr></thead>
                                                <tbody>' . $estadoRows . '</tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="card">
                                    <div class="card-header"><strong>Regiões de cadastro (Países)</strong></div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-sm table-striped mb-0">
                                                <thead><tr><th>País</th><th>Total</th></tr></thead>
                                                <tbody>' . $paisRows . '</tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header"><strong>Produtos mais vendidos</strong></div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-sm table-striped mb-0">
                                                <thead><tr><th>ID</th><th>Produto</th><th>Quantidade</th></tr></thead>
                                                <tbody>' . $prodRows . '</tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="card">
                                    <div class="card-header"><strong>Categorias mais vendidas</strong></div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-sm table-striped mb-0">
                                                <thead><tr><th>Categoria</th><th>Quantidade</th></tr></thead>
                                                <tbody>' . $catRows . '</tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-6">
                                <div class="card">
                                    <div class="card-header"><strong>Tipos de produtos mais vendidos</strong></div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-sm table-striped mb-0">
                                                <thead><tr><th>Tipo</th><th>Quantidade</th></tr></thead>
                                                <tbody>' . $tipoRows . '</tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        ' . $mapaCalorScript . '
                    </div>
                </div>
            </div>
        ';
    }

    public function mapaCalorClientes(Request $request) {
        header('Content-Type: application/json; charset=utf-8');

        try {
            $pdo = Database::getConnection();
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => 'Sem conexão com banco']);
            return;
        }

        $seg = (string) ($request->getParam('seg', '') ?? '');
        $val = (string) ($request->getParam('val', '') ?? '');
        $seg = trim($seg);
        $val = trim($val);

        // Endpoint auxiliar: retornar lista de lojas para cards
        if ($seg === 'lojas') {
            $lojas = $this->getLojasSegmentos($pdo);
            echo json_encode(['success' => true, 'lojas' => $lojas]);
            return;
        }

        if ($seg === '' || $val === '') {
            echo json_encode(['success' => true, 'clientes' => []]);
            return;
        }

        $clientes = $this->getClientesTopPorSegmento($pdo, $seg, $val, 100);
        echo json_encode(['success' => true, 'clientes' => $clientes]);
    }

    public function mapaCalorExportEmails(Request $request) {
        try {
            $pdo = Database::getConnection();
        } catch (\Exception $e) {
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Sem conexão com banco';
            return;
        }

        $seg = (string) ($request->getParam('seg', '') ?? '');
        $val = (string) ($request->getParam('val', '') ?? '');
        $seg = trim($seg);
        $val = trim($val);

        if ($seg === '' || $val === '') {
            header('Content-Type: text/plain; charset=utf-8');
            echo 'Parâmetros inválidos';
            return;
        }

        $clientes = $this->getClientesTopPorSegmento($pdo, $seg, $val, 1000);
        $emails = [];
        foreach ($clientes as $c) {
            if (!is_array($c)) continue;
            $em = trim((string) ($c['email'] ?? ''));
            if ($em === '') continue;
            $emails[$em] = true;
        }
        $emails = array_keys($emails);

        $fileName = 'emails_' . preg_replace('/[^a-z0-9\-_]+/i', '_', $seg) . '_' . preg_replace('/[^a-z0-9\-_]+/i', '_', $val) . '.csv';
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $fileName . '"');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['email']);
        foreach ($emails as $em) {
            fputcsv($out, [$em]);
        }
        fclose($out);
    }

    private function getLojasSegmentos(\PDO $pdo): array {
        $itensTable = $this->detectItensTable($pdo);
        if (!$itensTable || !$this->tableExists($pdo, 'produtos') || !$this->tableExists($pdo, 'pedidos')) {
            return [];
        }
        $iCols = $this->getColumns($pdo, $itensTable);
        $pCols = $this->getColumns($pdo, 'pedidos');
        $prCols = $this->getColumns($pdo, 'produtos');

        $colPedidoId = $this->pickColumn($iCols, ['pedido_id']);
        $colProdutoId = $this->pickColumn($iCols, ['produto_id', 'product_id']);
        $colQtd = $this->pickColumn($iCols, ['quantidade', 'qty', 'qtd']);
        if (!$colPedidoId || !$colProdutoId) {
            return [];
        }

        $pedidoStatusCol = $this->pickColumn($pCols, ['status', 'payment_status', 'status_pagamento', 'pagamento_status']);
        $wherePaid = $this->normalizePaidWhere($pedidoStatusCol);
        $qtdExpr = $colQtd ? "SUM(COALESCE(i.{$colQtd},0))" : 'COUNT(*)';

        $lojaIdCol = $this->pickColumn($prCols, ['loja_id']);
        $lojaSlugCol = $this->pickColumn($prCols, ['loja', 'store', 'seller']);

        // Preferir tabela lojas quando existir
        if ($lojaIdCol && $this->tableExists($pdo, 'lojas')) {
            try {
                $sql = "SELECT COALESCE(l.nome, CONCAT('Loja #', pr.{$lojaIdCol})) AS label, {$qtdExpr} AS quantidade\n"
                    . "FROM {$itensTable} i\n"
                    . "INNER JOIN pedidos ped ON ped.id = i.{$colPedidoId}\n"
                    . "INNER JOIN produtos pr ON pr.id = i.{$colProdutoId}\n"
                    . "LEFT JOIN lojas l ON l.id = pr.{$lojaIdCol}\n"
                    . $wherePaid . "\n"
                    . "GROUP BY label\n"
                    . "ORDER BY quantidade DESC\n"
                    . "LIMIT 15";
                $stmt = $pdo->query($sql);
                $rows = $stmt ? ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];
                return $rows;
            } catch (\Exception $e) {
                return [];
            }
        }

        if ($lojaSlugCol) {
            try {
                $sql = "SELECT COALESCE(NULLIF(TRIM(pr.{$lojaSlugCol}),''), 'sem_loja') AS label, {$qtdExpr} AS quantidade\n"
                    . "FROM {$itensTable} i\n"
                    . "INNER JOIN pedidos ped ON ped.id = i.{$colPedidoId}\n"
                    . "INNER JOIN produtos pr ON pr.id = i.{$colProdutoId}\n"
                    . $wherePaid . "\n"
                    . "GROUP BY label\n"
                    . "ORDER BY quantidade DESC\n"
                    . "LIMIT 15";
                $stmt = $pdo->query($sql);
                $rows = $stmt ? ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];
                return $rows;
            } catch (\Exception $e) {
                return [];
            }
        }

        return [];
    }

    private function getClientesTopPorSegmento(\PDO $pdo, string $seg, string $val, int $limit): array {
        $limit = max(1, min(2000, (int) $limit));

        $itensTable = $this->detectItensTable($pdo);
        if (!$itensTable || !$this->tableExists($pdo, 'produtos') || !$this->tableExists($pdo, 'pedidos')) {
            return [];
        }

        $uTable = $this->tableExists($pdo, 'usuarios') ? 'usuarios' : null;
        if ($uTable === null) {
            return [];
        }

        $iCols = $this->getColumns($pdo, $itensTable);
        $pCols = $this->getColumns($pdo, 'pedidos');
        $prCols = $this->getColumns($pdo, 'produtos');
        $uCols = $this->getColumns($pdo, 'usuarios');

        $colPedidoId = $this->pickColumn($iCols, ['pedido_id']);
        $colProdutoId = $this->pickColumn($iCols, ['produto_id', 'product_id']);
        $colQtd = $this->pickColumn($iCols, ['quantidade', 'qty', 'qtd']);
        $usuarioIdCol = $this->pickColumn($pCols, ['usuario_id', 'user_id']);
        $totalCol = $this->pickColumn($pCols, ['valor_total', 'total', 'amount', 'valor']);
        $pedidoStatusCol = $this->pickColumn($pCols, ['status', 'payment_status', 'status_pagamento', 'pagamento_status']);

        if (!$colPedidoId || !$colProdutoId || !$usuarioIdCol || !$totalCol) {
            return [];
        }

        $nomeUserCol = $this->pickColumn($uCols, ['nome', 'name']);
        if (!$nomeUserCol) {
            $nomeUserCol = 'id';
        }
        $emailCol = $this->pickColumn($uCols, ['email']);
        if (!$emailCol) {
            return [];
        }

        $wherePaid = $this->normalizePaidWhere($pedidoStatusCol);
        $qtdExpr = $colQtd ? "SUM(COALESCE(i.{$colQtd},0))" : 'COUNT(*)';

        $joinSeg = '';
        $whereSeg = '';
        $params = [];

        if ($seg === 'categoria') {
            if (!in_array('category_id', $prCols, true) || !$this->tableExists($pdo, 'categorias')) {
                return [];
            }
            $cCols = $this->getColumns($pdo, 'categorias');
            $catNomeCol = $this->pickColumn($cCols, ['name', 'nome']);
            if (!$catNomeCol) {
                return [];
            }
            $joinSeg = 'LEFT JOIN categorias c ON c.id = pr.category_id';
            $whereSeg = ' AND c.' . $catNomeCol . ' = :seg_val';
            $params[':seg_val'] = $val;
        } elseif ($seg === 'loja') {
            $lojaIdCol = $this->pickColumn($prCols, ['loja_id']);
            $lojaSlugCol = $this->pickColumn($prCols, ['loja', 'store', 'seller']);

            if ($lojaIdCol && $this->tableExists($pdo, 'lojas')) {
                $joinSeg = 'LEFT JOIN lojas l ON l.id = pr.' . $lojaIdCol;
                $whereSeg = ' AND COALESCE(l.nome, CONCAT(\'Loja #\', pr.' . $lojaIdCol . ')) = :seg_val';
                $params[':seg_val'] = $val;
            } elseif ($lojaSlugCol) {
                $whereSeg = ' AND COALESCE(NULLIF(TRIM(pr.' . $lojaSlugCol . '),\'\'), \'sem_loja\') = :seg_val';
                $params[':seg_val'] = $val;
            } else {
                return [];
            }
        } else {
            return [];
        }

        // Para ranking por segmento, o total_gasto será soma do total do pedido
        try {
            $sql = "SELECT\n"
                . "  COALESCE(u.{$nomeUserCol}, CONCAT('Cliente #', u.id)) AS nome,\n"
                . "  u.{$emailCol} AS email,\n"
                . "  COUNT(DISTINCT ped.id) AS pedidos,\n"
                . "  SUM(COALESCE(ped.{$totalCol},0)) AS total_gasto\n"
                . "FROM pedidos ped\n"
                . "INNER JOIN {$itensTable} i ON i.{$colPedidoId} = ped.id\n"
                . "INNER JOIN produtos pr ON pr.id = i.{$colProdutoId}\n"
                . "INNER JOIN usuarios u ON u.id = ped.{$usuarioIdCol}\n"
                . ($joinSeg ? ($joinSeg . "\n") : '')
                . $wherePaid
                . " AND u.{$emailCol} IS NOT NULL AND u.{$emailCol} <> ''"
                . $whereSeg
                . "\nGROUP BY nome, email\n"
                . "ORDER BY total_gasto DESC\n"
                . "LIMIT {$limit}";

            $stmt = $pdo->prepare($sql);
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v);
            }
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            return $rows;
        } catch (\Exception $e) {
            return [];
        }
    }
    
    public function salvar(Request $request) {
        try {
            $pdo = Database::getConnection();
            $pdo->beginTransaction();

            $tableInfo = $this->getConfigTableInfo($pdo);
            $table = $tableInfo['table'];
            $valueCol = $tableInfo['valueCol'];
            $updatedAtCol = $tableInfo['updatedAtCol'];

            // Se for schema single_row, garantir que existe uma linha (e descobrir o id correto)
            if (($tableInfo['mode'] ?? '') === 'single_row') {
                $idCol = $tableInfo['idCol'] ?? 'id';
                try {
                    $stmtFirst = $pdo->query("SELECT {$idCol} AS id FROM {$table} ORDER BY {$idCol} ASC LIMIT 1");
                    $firstRow = $stmtFirst ? ($stmtFirst->fetch(\PDO::FETCH_ASSOC) ?: null) : null;
                    $firstId = is_array($firstRow) ? (int) ($firstRow['id'] ?? 0) : 0;
                    if ($firstId <= 0) {
                        // cria uma linha vazia com defaults
                        $pdo->exec("INSERT INTO {$table} () VALUES ()");
                        $firstId = (int) $pdo->lastInsertId();
                    }
                    if ($firstId > 0) {
                        $tableInfo['idVal'] = $firstId;
                    }
                } catch (\Exception $e) {
                }
            }
            
            // Mapeamento de configurações
            $configMap = [
                'loja' => ['nome', 'descricao', 'email', 'telefone', 'endereco', 'logo'],
                'email' => ['driver', 'host', 'port', 'username', 'password', 'encryption', 'from', 'from_name', 'test_to'],
                'pagamentos' => ['asaas_enabled', 'asaas_ambiente', 'asaas_api_key', 'stripe_enabled', 'stripe_ambiente', 'stripe_publishable_key', 'stripe_secret_key', 'stripe_webhook_secret', 'appmax_enabled', 'appmax_client_id', 'appmax_client_secret', 'appmax_app_id', 'appmax_access_token', 'appmax_ambiente', 'appmax_base_url', 'webhook_link_pagamento_pedido_manual_url'],
                'clube' => ['cashback_percent', 'rendimento_percent', 'rendimento_intervalo_valor', 'rendimento_intervalo_unidade'],
                'comissao' => ['manual_faixas', 'janela_primeiro_inicio', 'janela_primeiro_fim', 'janela_duracao_dias'],
                'entrega' => ['moeda_padrao', 'taxa_servico_kg', 'frete_gratis_acima', 'frete_padrao', 'custo_envio_por_item_usd', 'prazo_padrao', 'cep_origem', 'calcular_automatico', 'wexpress_enabled', 'wexpress_ambiente', 'wexpress_api_key', 'wexpress_service_code', 'wexpress_sender_json', 'sigep_enabled', 'sigep_ambiente', 'sigep_usuario', 'sigep_senha', 'sigep_cnpj', 'sigep_servico_codigo', 'sigep_numero_contrato', 'sigep_cartao_postagem', 'correios_tracking_enabled', 'correios_tracking_base_url', 'correios_tracking_token', 'correios_tracking_header', 'stamps_enabled', 'stamps_ambiente', 'stamps_client_id', 'stamps_client_secret', 'stamps_refresh_token', 'stamps_from_address_json', 'stamps_service_type', 'stamps_packaging_type'],
                'seo' => ['title', 'description', 'keywords', 'google_analytics', 'google_tag_manager', 'sitemap_gerado'],
                'sistema' => ['timezone', 'idioma', 'moeda', 'usd_brl_rate', 'manutencao', 'debug', 'cache_ativado'],
                'scrapingbee' => ['api_key'],
                'chatgpt' => ['api_key', 'model', 'temperature', 'max_tokens', 'peso_margem'],
                'assessoria' => ['webhook_inicio_url', 'webhook_conclusao_url']
            ];
            
            $checkboxKeys = ['calcular_automatico', 'sitemap_gerado', 'manutencao', 'debug', 'cache_ativado', 'asaas_enabled', 'stripe_enabled', 'appmax_enabled', 'wexpress_enabled', 'sigep_enabled', 'correios_tracking_enabled', 'stamps_enabled'];

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
                        if ($chave === 'custo_envio_por_item_usd') {
                            $valor = is_numeric($valor) ? floatval($valor) : 0;
                        }
                        if ($chave === 'comissao_percentual') {
                            $valor = is_numeric($valor) ? floatval($valor) : 0;
                        }
                        if ($chave === 'prazo_padrao') {
                            $valor = is_numeric($valor) ? intval($valor) : 30;
                        }

                        if ($categoria === 'comissao' && $chave === 'manual_faixas') {
                            $decoded = json_decode((string) $valor, true);
                            if ($decoded === null || !is_array($decoded)) {
                                $valor = '[{"min":0,"max":999999999,"percent":0}]';
                            } else {
                                $valor = json_encode($decoded, JSON_UNESCAPED_UNICODE);
                            }
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

                        // rowCount() pode ser 0 mesmo quando o registro existe (valor não mudou).
                        // Só inserir quando realmente não existir.
                        if ($stmtUpdate->rowCount() === 0) {
                            $exists = false;
                            if (($tableInfo['mode'] ?? '') === 'categoria_chave') {
                                $catCol = $tableInfo['categoriaCol'];
                                $keyCol = $tableInfo['chaveCol'];
                                $stExists = $pdo->prepare("SELECT 1 FROM {$table} WHERE {$catCol} = ? AND {$keyCol} = ? LIMIT 1");
                                $stExists->execute([$categoria, $chave]);
                                $exists = (bool) $stExists->fetchColumn();
                            } else {
                                $keyCol = $tableInfo['keyCol'];
                                $stExists = $pdo->prepare("SELECT 1 FROM {$table} WHERE {$keyCol} = ? LIMIT 1");
                                $stExists->execute([$fullKey]);
                                $exists = (bool) $stExists->fetchColumn();
                            }

                            if (!$exists) {
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
            }

            try {
                if ($this->tableExists($pdo, 'clube_descontos_faixas')) {
                    $rem = $request->getParam('clube_faixas_remover', []);
                    if (!is_array($rem)) $rem = [];
                    $remIds = array_values(array_unique(array_map('intval', $rem)));
                    if (!empty($remIds)) {
                        $in = implode(',', array_fill(0, count($remIds), '?'));
                        $stDel = $pdo->prepare('DELETE FROM clube_descontos_faixas WHERE id IN (' . $in . ')');
                        $stDel->execute($remIds);
                    }

                    $faixas = $request->getParam('clube_faixas', []);
                    if (!is_array($faixas)) $faixas = [];

                    foreach ($faixas as $row) {
                        if (!is_array($row)) continue;
                        $idFx = (int) ($row['id'] ?? 0);
                        if ($idFx <= 0) continue;
                        if (in_array($idFx, $remIds, true)) continue;

                        $ativo = (int) (($row['ativo'] ?? 0) ? 1 : 0);
                        $ordem = (int) ($row['ordem'] ?? 0);
                        $min = (float) str_replace(',', '.', (string) ($row['peso_min_kg'] ?? 0));
                        $max = (float) str_replace(',', '.', (string) ($row['peso_max_kg'] ?? 0));
                        $pct = (float) str_replace(',', '.', (string) ($row['percentual_desconto'] ?? 0));
                        if ($min < 0) $min = 0.0;
                        if ($max < 0) $max = 0.0;
                        if ($pct < 0) $pct = 0.0;

                        $stUp = $pdo->prepare('UPDATE clube_descontos_faixas SET peso_min_kg = ?, peso_max_kg = ?, percentual_desconto = ?, ativo = ?, ordem = ?, updated_at = NOW() WHERE id = ?');
                        $stUp->execute([$min, $max, $pct, $ativo, $ordem, $idFx]);
                    }

                    $nova = $request->getParam('clube_faixa_nova', []);
                    if (is_array($nova)) {
                        $minN = (float) str_replace(',', '.', (string) ($nova['peso_min_kg'] ?? 0));
                        $maxN = (float) str_replace(',', '.', (string) ($nova['peso_max_kg'] ?? 0));
                        $pctN = (float) str_replace(',', '.', (string) ($nova['percentual_desconto'] ?? 0));
                        $ativoN = (int) (($nova['ativo'] ?? 0) ? 1 : 0);
                        $ordN = (int) ($nova['ordem'] ?? 0);
                        if ($minN < 0) $minN = 0.0;
                        if ($maxN < 0) $maxN = 0.0;
                        if ($pctN < 0) $pctN = 0.0;
                        if ($minN > 0 || $maxN > 0 || $pctN > 0) {
                            $stIns = $pdo->prepare('INSERT INTO clube_descontos_faixas (peso_min_kg, peso_max_kg, percentual_desconto, ativo, ordem, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())');
                            $stIns->execute([$minN, $maxN, $pctN, $ativoN, $ordN]);
                        }
                    }
                }
            } catch (\Exception $e) {
            }

            $pdo->commit();
            
            header('Location: /admin/configuracoes?success=1');
            exit;
            
        } catch (\Exception $e) {
            try {
                if (isset($pdo) && $pdo instanceof \PDO && $pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            } catch (\Exception $e3) {
            }
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
    
    public function testarSigep(Request $request) {
        try {
            $pdo = Database::getConnection();
            $tableInfo = $this->getConfigTableInfo($pdo);
            $table = $tableInfo['table'];

            $get = function(string $cat, string $key, string $default = '') use ($pdo, $tableInfo, $table): string {
                try {
                    $mode = (string) ($tableInfo['mode'] ?? '');
                    if ($mode === 'single_row') {
                        $cols = [];
                        try {
                            $st = $pdo->query('DESCRIBE ' . $table);
                            $cols = $st->fetchAll(\PDO::FETCH_COLUMN) ?: [];
                        } catch (\Exception $e) {
                            $cols = [];
                        }

                        $col = null;
                        $map = $tableInfo['columnMap'] ?? [];
                        if (isset($map[$cat]) && isset($map[$cat][$key])) {
                            $col = $map[$cat][$key];
                        } else {
                            $guess = $key;
                            if (in_array($guess, $cols, true)) {
                                $col = $guess;
                            }
                        }

                        if (!$col || !preg_match('/^[a-zA-Z0-9_]+$/', (string) $col)) {
                            return $default;
                        }

                        $idCol = (string) ($tableInfo['idCol'] ?? 'id');
                        $stmt = $pdo->query('SELECT ' . $col . ' FROM ' . $table . ' ORDER BY ' . $idCol . ' ASC LIMIT 1');
                        $v = $stmt->fetchColumn();
                        return ($v === false || $v === null) ? $default : (string) $v;
                    }

                    if ($mode === 'categoria_chave') {
                        $catCol = $tableInfo['categoriaCol'];
                        $keyCol = $tableInfo['chaveCol'];
                        $valCol = $tableInfo['valueCol'];
                        $stmt = $pdo->prepare('SELECT ' . $valCol . ' FROM ' . $table . ' WHERE ' . $catCol . ' = ? AND ' . $keyCol . ' = ? LIMIT 1');
                        $stmt->execute([$cat, $key]);
                        $v = $stmt->fetchColumn();
                        return ($v === false || $v === null) ? $default : (string) $v;
                    }

                    $keyCol = $tableInfo['keyCol'];
                    $valCol = $tableInfo['valueCol'];
                    $fullKey = $cat . '_' . $key;
                    $stmt = $pdo->prepare('SELECT ' . $valCol . ' FROM ' . $table . ' WHERE ' . $keyCol . ' = ? LIMIT 1');
                    $stmt->execute([$fullKey]);
                    $v = $stmt->fetchColumn();
                    return ($v === false || $v === null) ? $default : (string) $v;
                } catch (\Exception $e) {
                    return $default;
                }
            };

            $enabled = $get('entrega', 'sigep_enabled', '0');
            if ($enabled !== '1') {
                echo json_encode(['success' => false, 'error' => 'SIGEP está desabilitado nas configurações.']);
                exit;
            }

            $ambiente = $get('entrega', 'sigep_ambiente', 'homologacao');
            $usuario = $get('entrega', 'sigep_usuario', '');
            $senha = $get('entrega', 'sigep_senha', '');
            $cnpj = $get('entrega', 'sigep_cnpj', '');
            $servicoCodigo = $get('entrega', 'sigep_servico_codigo', '');
            $contrato = $get('entrega', 'sigep_numero_contrato', '');
            $cartao = $get('entrega', 'sigep_cartao_postagem', '');

            if ($usuario === '' || $senha === '' || $contrato === '' || $cartao === '' || $servicoCodigo === '') {
                echo json_encode(['success' => false, 'error' => 'Preencha usuário, senha, contrato, cartão de postagem e código do serviço.']);
                exit;
            }

            if (!class_exists('\\SoapClient')) {
                echo json_encode(['success' => false, 'error' => 'Extensão SOAP não disponível no PHP do servidor.']);
                exit;
            }

            $amb = strtolower(trim((string) $ambiente));
            $wsdl = ($amb === 'producao' || $amb === 'production')
                ? 'https://apps.correios.com.br/SigepMasterJPA/AtendeClienteService/AtendeCliente?wsdl'
                : 'https://hom.correios.com.br/SigepMasterJPA/AtendeClienteService/AtendeCliente?wsdl';

            $client = new \SoapClient($wsdl, [
                'exceptions' => true,
                'trace' => false,
                'cache_wsdl' => WSDL_CACHE_BOTH,
                'connection_timeout' => 20,
            ]);

            $params = [
                'tipoDestinatario' => 'C',
                'identificador' => $cnpj,
                'idServico' => $servicoCodigo,
                'qtdEtiquetas' => 1,
                'usuario' => $usuario,
                'senha' => $senha,
            ];

            $resp = $client->__soapCall('solicitaEtiquetas', [$params]);

            echo json_encode([
                'success' => true,
                'ambiente' => $ambiente,
                'response' => $resp,
            ]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    private function getEntregaJS() {
        ob_start();
        ?>
        <script>
        function testarSigepAPI() {
            fetch('/admin/configuracoes/testar-sigep', { method: 'POST' })
                .then(async response => {
                    const data = await response.json().catch(() => ({}));
                    if (response.ok && data.success) {
                        alert('✅ SIGEP OK (' + (data.ambiente || '') + ')\n\nResposta: ' + JSON.stringify(data.response));
                    } else {
                        alert('❌ SIGEP falhou: ' + (data.error || JSON.stringify(data)));
                    }
                })
                .catch(err => alert('❌ Erro ao testar SIGEP: ' + err.message));
        }
        </script>
        <?php
        return ob_get_clean();
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
            const apiKeyEl = document.querySelector('input[name="pagamentos_asaas_api_key"]');
            const ambEl = document.querySelector('select[name="pagamentos_asaas_ambiente"]');
            const apiKey = apiKeyEl ? apiKeyEl.value : '';
            const ambiente = ambEl ? ambEl.value : 'sandbox';
            
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
            const pkEl = document.querySelector('input[name="pagamentos_stripe_publishable_key"]');
            const skEl = document.querySelector('input[name="pagamentos_stripe_secret_key"]');
            const ambEl = document.querySelector('select[name="pagamentos_stripe_ambiente"]');
            const publishableKey = pkEl ? pkEl.value : '';
            const secretKey = skEl ? skEl.value : '';
            const ambiente = ambEl ? ambEl.value : 'test';
            
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
        function syncSalvarGeralVisibilityNotificacoes() {
            const btnContainer = document.getElementById('admin-config-salvar-geral');
            if (!btnContainer) {
                return;
            }

            const tabPane = document.getElementById('v-pills-notificacoes');
            const isActive = !!(tabPane && tabPane.classList.contains('active') && tabPane.classList.contains('show'));
            btnContainer.style.display = isActive ? 'none' : '';
        }

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

        document.addEventListener('DOMContentLoaded', function() {
            const tabBtn = document.getElementById('v-pills-notificacoes-tab');
            if (tabBtn) {
                tabBtn.addEventListener('shown.bs.tab', syncSalvarGeralVisibilityNotificacoes);
                tabBtn.addEventListener('hidden.bs.tab', syncSalvarGeralVisibilityNotificacoes);
            }
            syncSalvarGeralVisibilityNotificacoes();

            const eventoSelect = document.querySelector('#formNotificacoes select[name="evento"]');
            if (eventoSelect) {
                eventoSelect.addEventListener('change', function() {
                    carregarNotificacaoPorEvento(eventoSelect.value);
                });
                if (eventoSelect.value) {
                    carregarNotificacaoPorEvento(eventoSelect.value);
                }
            }
        });

        function carregarNotificacaoPorEvento(evento) {
            const container = document.getElementById('formNotificacoes');
            if (!container) {
                return;
            }
            if (!evento) {
                const urlEl = container.querySelector('input[name="webhook_url"]');
                const metodoEl = container.querySelector('select[name="webhook_method"]');
                const headersEl = container.querySelector('textarea[name="webhook_headers"]');
                const camposEl = container.querySelector('textarea[name="webhook_campos"]');
                const tplEl = container.querySelector('textarea[name="webhook_template"]');
                const ativoEl = container.querySelector('input[name="webhook_ativo"]');
                const retriesEl = container.querySelector('input[name="webhook_retries"]');
                if (urlEl) urlEl.value = '';
                if (metodoEl) metodoEl.value = 'POST';
                if (headersEl) headersEl.value = '';
                if (camposEl) camposEl.value = '';
                if (tplEl) tplEl.value = '';
                if (ativoEl) ativoEl.checked = true;
                if (retriesEl) retriesEl.checked = true;
                return;
            }

            const params = new URLSearchParams();
            params.set('evento', evento);

            fetch('/admin/notificacao?' + params.toString())
                .then(async response => {
                    const data = await response.json().catch(() => ({}));
                    if (!response.ok || !data.success || !data.notificacao) {
                        throw new Error(data.error || 'Falha ao carregar configuração');
                    }

                    const n = data.notificacao;
                    const urlEl = container.querySelector('input[name="webhook_url"]');
                    const metodoEl = container.querySelector('select[name="webhook_method"]');
                    const headersEl = container.querySelector('textarea[name="webhook_headers"]');
                    const camposEl = container.querySelector('textarea[name="webhook_campos"]');
                    const tplEl = container.querySelector('textarea[name="webhook_template"]');
                    const ativoEl = container.querySelector('input[name="webhook_ativo"]');
                    const retriesEl = container.querySelector('input[name="webhook_retries"]');

                    if (urlEl) urlEl.value = n.url || '';
                    if (metodoEl) metodoEl.value = (n.metodo || 'POST').toUpperCase();
                    if (headersEl) headersEl.value = n.headers || '';
                    if (camposEl) camposEl.value = n.campos || '';
                    if (tplEl) tplEl.value = n.template || '';
                    if (ativoEl) ativoEl.checked = (n.ativo || '1') === '1';
                    if (retriesEl) retriesEl.checked = (n.retries || '1') === '1';
                })
                .catch(() => {
                });
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
                                    <button type="button" class="btn btn-sm btn-outline-danger ms-1" onclick="excluirLogWebhookNotificacoes(${log.id})">
                                        <i class="fas fa-trash"></i>
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

        function excluirLogWebhookNotificacoes(logId) {
            if (!confirm('Deseja excluir este log?')) {
                return;
            }

            fetch(`/admin/log-webhook/${logId}/excluir`, {
                method: 'POST'
            })
            .then(async response => {
                const data = await response.json().catch(() => ({}));
                if (response.ok && data.success) {
                    carregarLogsWebhookNotificacoes();
                } else {
                    alert('Erro ao excluir log: ' + (data.error || JSON.stringify(data)));
                }
            })
            .catch(error => {
                alert('Erro ao excluir log: ' + error.message);
            });
        }

        function limparLogsWebhookNotificacoes() {
            if (!confirm('Deseja limpar todos os logs?')) {
                return;
            }

            fetch('/admin/logs-webhook/limpar', {
                method: 'POST'
            })
            .then(async response => {
                const data = await response.json().catch(() => ({}));
                if (response.ok && data.success) {
                    carregarLogsWebhookNotificacoes();
                } else {
                    alert('Erro ao limpar logs: ' + (data.error || JSON.stringify(data)));
                }
            })
            .catch(error => {
                alert('Erro ao limpar logs: ' + error.message);
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
                "{{pedido_id}}": "ID do Pedido",
                "{{codigo_pedido}}": "Código do Pedido",
                "{{numero_pedido}}": "Número do Pedido",
                "{{cliente_nome}}": "Nome do Cliente",
                "{{cliente_email}}": "Email do Cliente",
                "{{valor_total}}": "Total do Pedido",
                "{{moeda}}": "Moeda",
                "{{data_pedido}}": "Data do Pedido",
                "{{itens}}": "Lista de Itens",
                "{{endereco_entrega}}": "Endereço de Entrega"
            },
            "pedido_aprovado": {
                "{{pedido_id}}": "ID do Pedido",
                "{{codigo_pedido}}": "Código do Pedido",
                "{{numero_pedido}}": "Número do Pedido",
                "{{cliente_nome}}": "Nome do Cliente",
                "{{data_aprovacao}}": "Data de Aprovação",
                "{{valor_total}}": "Total do Pedido"
            },
            "pedido_enviado": {
                "{{pedido_id}}": "ID do Pedido",
                "{{codigo_pedido}}": "Código do Pedido",
                "{{numero_pedido}}": "Número do Pedido",
                "{{codigo_rastreamento}}": "Código de Rastreamento",
                "{{data_envio}}": "Data de Envio",
                "{{transportadora}}": "Transportadora"
            },
            "pedido_entregue": {
                "{{pedido_id}}": "ID do Pedido",
                "{{codigo_pedido}}": "Código do Pedido",
                "{{numero_pedido}}": "Número do Pedido",
                "{{data_entrega}}": "Data de Entrega",
                "{{recebedor}}": "Quem Recebeu"
            },
            "pedido_cancelado": {
                "{{pedido_id}}": "ID do Pedido",
                "{{codigo_pedido}}": "Código do Pedido",
                "{{numero_pedido}}": "Número do Pedido",
                "{{motivo_cancelamento}}": "Motivo do Cancelamento",
                "{{data_cancelamento}}": "Data do Cancelamento"
            },
            "novo_usuario": {
                "{{usuario_nome}}": "Nome do Usuário",
                "{{usuario_email}}": "Email do Usuário",
                "{{data_cadastro}}": "Data de Cadastro",
                "{{token_confirmacao}}": "Token de Confirmação"
            },
            "recuperar_senha": {
                "{{usuario_nome}}": "Nome do Usuário",
                "{{usuario_email}}": "Email do Usuário",
                "{{token_reset}}": "Token de Reset",
                "{{data_solicitacao}}": "Data da Solicitação"
            },
            "contato_contato": {
                "{{nome_contato}}": "Nome do Contato",
                "{{email_contato}}": "Email do Contato",
                "{{mensagem}}": "Mensagem",
                "{{data_contato}}": "Data do Contato"
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
            
            const formData = new FormData();
            formData.set('evento', evento);
            formData.set('assunto', assunto);
            formData.set('corpo_html', conteudo);
            formData.set('ativo', '1');

            fetch('/admin/salvar-email-template', {
                method: 'POST',
                body: formData
            })
            .then(async response => {
                const data = await response.json().catch(() => ({}));
                if (response.ok && data.success) {
                    alert('Template salvo com sucesso!');
                    carregarTemplatesSalvos();
                } else {
                    alert('Erro ao salvar template: ' + (data.error || JSON.stringify(data)));
                }
            })
            .catch(error => {
                alert('Erro ao processar requisição: ' + error.message);
            });
        }
        
        function carregarTemplatesSalvos() {
            const div = document.getElementById("templates_salvos");

            div.innerHTML = "<small class=\"text-muted\">Carregando...</small>";

            fetch('/admin/email-templates')
            .then(async response => {
                const data = await response.json().catch(() => ({}));
                if (!response.ok || !data.success) {
                    div.innerHTML = "<small class=\"text-muted\">Erro ao carregar templates</small>";
                    return;
                }

                const templates = Array.isArray(data.templates) ? data.templates : [];
                if (templates.length === 0) {
                    div.innerHTML = "<small class=\"text-muted\">Nenhum template salvo ainda</small>";
                    return;
                }

                let html = "<div class=\"row\">";
                for (const tpl of templates) {
                    const evento = tpl.evento;
                    html += "<div class=\"col-md-4 mb-3\">";
                    html += "<div class=\"card\">";
                    html += "<div class=\"card-body\">";
                    html += "<h6 class=\"card-title\">" + (evento || '') + "</h6>";
                    html += "<p class=\"card-text\"><small>" + (tpl.assunto || '') + "</small></p>";
                    html += "<p class=\"card-text\"><small class=\"text-muted\">" + (tpl.updated_at || '') + "</small></p>";
                    html += "<div class=\"d-flex gap-2\">";
                    html += "<button class=\"btn btn-sm btn-outline-primary\" onclick=\"carregarTemplate('" + evento + "')\">Carregar</button>";
                    html += "<button class=\"btn btn-sm btn-outline-success\" onclick=\"testarTemplateEmail('" + evento + "')\">Testar</button>";
                    html += "</div>";
                    html += "</div>";
                    html += "</div>";
                    html += "</div>";
                }
                html += "</div>";
                div.innerHTML = html;
            })
            .catch(error => {
                div.innerHTML = "<small class=\"text-muted\">Erro ao carregar templates: " + error.message + "</small>";
            });
        }
        
        function carregarTemplate(evento) {
            const params = new URLSearchParams();
            params.set('evento', evento);

            fetch('/admin/email-template?' + params.toString())
            .then(async response => {
                const data = await response.json().catch(() => ({}));
                if (!response.ok || !data.success || !data.template) {
                    alert('Erro ao carregar template: ' + (data.error || JSON.stringify(data)));
                    return;
                }
                const tpl = data.template;
                document.getElementById("evento_tipo").value = tpl.evento || evento;
                document.getElementById("email_assunto").value = tpl.assunto || '';
                document.getElementById("email_conteudo").value = tpl.corpo_html || '';
                carregarVariaveis();
            })
            .catch(error => {
                alert('Erro ao carregar template: ' + error.message);
            });
        }

        function testarTemplateEmail(evento) {
            const formData = new FormData();
            formData.set('evento', evento);

            const testTo = document.querySelector('input[name="email_test_to"]');
            if (testTo && testTo.value) {
                formData.set('to', testTo.value);
            }

            fetch('/admin/testar-email-template', {
                method: 'POST',
                body: formData
            })
            .then(async response => {
                const data = await response.json().catch(() => ({}));
                if (response.ok && data.success) {
                    alert('Email de teste enviado para: ' + (data.to || 'admin'));
                } else {
                    alert('Erro ao testar e-mail: ' + (data.error || JSON.stringify(data)));
                }
            })
            .catch(error => {
                alert('Erro ao testar e-mail: ' + error.message);
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
                foreach ($updatedCandidates as $c) {
                    if (in_array($c, $cols, true)) {
                        $updatedAtCol = $c;
                        break;
                    }
                }

                $columnMap = [
                    'pagamentos' => [
                        'asaas_enabled' => 'asaas_enabled',
                        'asaas_ambiente' => 'asaas_ambiente',
                        'asaas_api_key' => 'asaas_api_key',
                        'stripe_enabled' => 'stripe_enabled',
                        'stripe_ambiente' => 'stripe_ambiente',
                        'stripe_publishable_key' => 'stripe_publishable_key',
                        'stripe_secret_key' => 'stripe_secret_key',
                    ],
                ];

                if (in_array('webhook_link_pagamento_pedido_manual_url', $cols, true)) {
                    $columnMap['pagamentos']['webhook_link_pagamento_pedido_manual_url'] = 'webhook_link_pagamento_pedido_manual_url';
                }

                if (in_array('comissao_manual_faixas', $cols, true)) {
                    $columnMap['comissao'] = [
                        'manual_faixas' => 'comissao_manual_faixas',
                    ];
                }

                $emailMapCandidates = [
                    'driver' => ['email_driver'],
                    'host' => ['email_host', 'smtp_host'],
                    'port' => ['email_port', 'smtp_port'],
                    'username' => ['email_username', 'smtp_usuario', 'smtp_user'],
                    'password' => ['email_password', 'smtp_senha', 'smtp_pass'],
                    'encryption' => ['email_encryption', 'smtp_criptografia'],
                    'from' => ['email_from', 'email_remetente'],
                    'from_name' => ['email_from_name', 'nome_remetente'],
                    'test_to' => ['email_test_to', 'email_teste_para'],
                ];

                $emailColumnMap = [];
                foreach ($emailMapCandidates as $k => $cands) {
                    foreach ($cands as $colName) {
                        if (in_array($colName, $cols, true)) {
                            $emailColumnMap[$k] = $colName;
                            break;
                        }
                    }
                }
                if (!empty($emailColumnMap)) {
                    $columnMap['email'] = $emailColumnMap;
                }

                // Entrega / W-Express (configuracoes_sistema legado)
                $wexpressCols = [
                    'wexpress_enabled',
                    'wexpress_ambiente',
                    'wexpress_api_key',
                    'wexpress_service_code',
                    'wexpress_sender_json',
                ];
                $temWexpress = false;
                foreach ($wexpressCols as $wc) {
                    if (in_array($wc, $cols, true)) {
                        $temWexpress = true;
                        break;
                    }
                }
                if ($temWexpress) {
                    $columnMap['entrega'] = $columnMap['entrega'] ?? [];
                    foreach ($wexpressCols as $wc) {
                        if (in_array($wc, $cols, true)) {
                            $columnMap['entrega'][$wc] = $wc;
                        }
                    }
                }

                return [
                    'mode' => 'single_row',
                    'table' => $table,
                    'idCol' => 'id',
                    'idVal' => 1,
                    'updatedAtCol' => $updatedAtCol,
                    'valueCol' => '',
                    'columnMap' => $columnMap
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
