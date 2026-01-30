<?php ob_start(); ?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="fas fa-cog me-2"></i>
            Configurações do Sistema
        </h1>
        <div>
            <a href="/admin" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Voltar
            </a>
        </div>
    </div>

    <!-- Abas de Configuração -->
    <div class="card">
        <div class="card-header">
            <ul class="nav nav-tabs" id="configTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="geral-tab" data-bs-toggle="tab" data-bs-target="#geral" type="button" role="tab" aria-controls="geral" aria-selected="true">
                        <i class="fas fa-cogs me-2"></i>Geral
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="email-tab" data-bs-toggle="tab" data-bs-target="#email" type="button" role="tab" aria-controls="email" aria-selected="false">
                        <i class="fas fa-envelope me-2"></i>E-mail
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="pagamento-tab" data-bs-toggle="tab" data-bs-target="#pagamento" type="button" role="tab" aria-controls="pagamento" aria-selected="false">
                        <i class="fas fa-credit-card me-2"></i>Pagamento
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="entrega-tab" data-bs-toggle="tab" data-bs-target="#entrega" type="button" role="tab" aria-controls="entrega" aria-selected="false">
                        <i class="fas fa-truck me-2"></i>Entrega
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="notificacoes-tab" data-bs-toggle="tab" data-bs-target="#notificacoes" type="button" role="tab" aria-controls="notificacoes" aria-selected="false">
                        <i class="fas fa-bell me-2"></i>Notificações
                    </button>
                </li>
            </ul>
        </div>
        <div class="card-body">
            <div class="tab-content" id="configTabContent">
                <!-- Tab Geral -->
                <div class="tab-pane fade show active" id="geral" role="tabpanel">
                    <form method="POST" id="formGeral">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Nome do Site</label>
                                <input type="text" name="site_nome" class="form-control" value="<?= $configuracoes['site_nome'] ?? '' ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">URL do Site</label>
                                <input type="url" name="site_url" class="form-control" value="<?= $configuracoes['site_url'] ?? '' ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email de Contato</label>
                                <input type="email" name="email_contato" class="form-control" value="<?= $configuracoes['email_contato'] ?? '' ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Telefone de Contato</label>
                                <input type="text" name="telefone_contato" class="form-control" value="<?= $configuracoes['telefone_contato'] ?? '' ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Moeda Padrão</label>
                                <select name="moeda_padrao" class="form-select">
                                    <option value="BRL" <?= ($configuracoes['moeda_padrao'] ?? 'BRL') == 'BRL' ? 'selected' : '' ?>>Real Brasileiro (BRL)</option>
                                    <option value="USD" <?= ($configuracoes['moeda_padrao'] ?? 'BRL') == 'USD' ? 'selected' : '' ?>>Dólar Americano (USD)</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Taxa de Câmbio (1 USD)</label>
                                <input type="number" name="taxa_cambio" class="form-control" step="0.01" value="<?= $configuracoes['taxa_cambio'] ?? '5.50' ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Descrição do Site</label>
                                <textarea name="site_descricao" class="form-control" rows="3"><?= $configuracoes['site_descricao'] ?? '' ?></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Palavras-chave SEO</label>
                                <input type="text" name="palavras_chave" class="form-control" value="<?= $configuracoes['palavras_chave'] ?? '' ?>" placeholder="Separadas por vírgula">
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Salvar Configurações
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Tab E-mail -->
                <div class="tab-pane fade" id="email" role="tabpanel">
                    <form method="POST" id="formEmail">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">SMTP Host</label>
                                <input type="text" name="smtp_host" class="form-control" value="<?= $configuracoes['smtp_host'] ?? '' ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">SMTP Porta</label>
                                <input type="number" name="smtp_port" class="form-control" value="<?= $configuracoes['smtp_port'] ?? '587' ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">SMTP Usuário</label>
                                <input type="text" name="smtp_usuario" class="form-control" value="<?= $configuracoes['smtp_usuario'] ?? '' ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">SMTP Senha</label>
                                <input type="password" name="smtp_senha" class="form-control" value="<?= $configuracoes['smtp_senha'] ?? '' ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">SMTP Criptografia</label>
                                <select name="smtp_criptografia" class="form-select">
                                    <option value="tls" <?= ($configuracoes['smtp_criptografia'] ?? 'tls') == 'tls' ? 'selected' : '' ?>>TLS</option>
                                    <option value="ssl" <?= ($configuracoes['smtp_criptografia'] ?? 'tls') == 'ssl' ? 'selected' : '' ?>>SSL</option>
                                    <option value="" <?= ($configuracoes['smtp_criptografia'] ?? 'tls') == '' ? 'selected' : '' ?>>Nenhuma</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Email de Remetente</label>
                                <input type="email" name="email_remetente" class="form-control" value="<?= $configuracoes['email_remetente'] ?? '' ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Nome do Remetente</label>
                                <input type="text" name="nome_remetente" class="form-control" value="<?= $configuracoes['nome_remetente'] ?? '' ?>">
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Salvar Configurações
                                </button>
                                <button type="button" class="btn btn-success ms-2" onclick="testarEmail()">
                                    <i class="fas fa-paper-plane me-2"></i>Testar E-mail
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Tab Pagamento -->
                <div class="tab-pane fade" id="pagamento" role="tabpanel">
                    <form method="POST" id="formPagamento">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Gateway de Pagamento</label>
                                <select name="gateway_pagamento" class="form-select">
                                    <option value="mercadopago" <?= ($configuracoes['gateway_pagamento'] ?? 'mercadopago') == 'mercadopago' ? 'selected' : '' ?>>Mercado Pago</option>
                                    <option value="paypal" <?= ($configuracoes['gateway_pagamento'] ?? 'mercadopago') == 'paypal' ? 'selected' : '' ?>>PayPal</option>
                                    <option value="stripe" <?= ($configuracoes['gateway_pagamento'] ?? 'mercadopago') == 'stripe' ? 'selected' : '' ?>>Stripe</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Chave Pública</label>
                                <input type="text" name="chave_publica" class="form-control" value="<?= $configuracoes['chave_publica'] ?? '' ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Chave Privada</label>
                                <input type="password" name="chave_privada" class="form-control" value="<?= $configuracoes['chave_privada'] ?? '' ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Token de Acesso</label>
                                <input type="text" name="token_acesso" class="form-control" value="<?= $configuracoes['token_acesso'] ?? '' ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Webhook URL</label>
                                <input type="url" name="webhook_url" class="form-control" value="<?= $configuracoes['webhook_url'] ?? '' ?>">
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Salvar Configurações
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Tab Notificações -->
                <div class="tab-pane fade" id="notificacoes" role="tabpanel">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">
                                        <i class="fas fa-bell me-2"></i>
                                        Configurar Notificações por Webhook
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <form id="formNotificacoes">
                                        <div class="mb-4">
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
                                        
                                        <div class="mb-4">
                                            <label class="form-label">URL do Webhook</label>
                                            <input type="url" name="webhook_url" class="form-control" placeholder="https://seu-webhook.com/notificacoes" required>
                                            <div class="form-text">URL completa do endpoint que receberá as notificações</div>
                                        </div>
                                        
                                        <div class="mb-4">
                                            <label class="form-label">Método HTTP</label>
                                            <select name="webhook_method" class="form-select">
                                                <option value="POST">POST</option>
                                                <option value="PUT">PUT</option>
                                                <option value="PATCH">PATCH</option>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-4">
                                            <label class="form-label">Headers (JSON)</label>
                                            <textarea name="webhook_headers" class="form-control" rows="3" placeholder='{"Authorization": "Bearer token123", "Content-Type": "application/json"}'></textarea>
                                            <div class="form-text">Headers em formato JSON que serão enviados com a requisição</div>
                                        </div>
                                        
                                        <div class="mb-4">
                                            <label class="form-label">Campos Personalizados (JSON)</label>
                                            <textarea name="webhook_campos" class="form-control" rows="5" placeholder='{"empresa": "Braziliana Shop", "ambiente": "producao"}'></textarea>
                                            <div class="form-text">Campos adicionais em formato JSON que serão incluídos no payload</div>
                                        </div>
                                        
                                        <div class="mb-4">
                                            <label class="form-label">Template da Mensagem</label>
                                            <textarea name="webhook_template" class="form-control" rows="4" placeholder='Olá {{nome}}, seu pedido #{{pedido_id}} foi {{status}}!'></textarea>
                                            <div class="form-text">Use variáveis entre chaves duplas: {{nome}}, {{email}}, {{pedido_id}}, {{status}}, {{data}}, etc.</div>
                                        </div>
                                        
                                        <div class="mb-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="webhook_ativo" id="webhook_ativo" checked>
                                                <label class="form-check-label" for="webhook_ativo">
                                                    Webhook Ativo
                                                </label>
                                            </div>
                                            <div class="form-text">Desmarque para desativar temporariamente este webhook</div>
                                        </div>
                                        
                                        <div class="mb-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="webhook_retries" id="webhook_retries" checked>
                                                <label class="form-check-label" for="webhook_retries">
                                                    Tentativas de Reenvio
                                                </label>
                                            </div>
                                            <div class="form-text">Se falhar, tentará novamente em 1, 5 e 15 minutos</div>
                                        </div>
                                        
                                        <div class="mb-4">
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
                                                    <tbody id="logs-webhook">
                                                        <tr>
                                                            <td colspan="4" class="text-center">Nenhum log encontrado</td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <button type="button" class="btn btn-primary" onclick="salvarNotificacao()">
                                                    <i class="fas fa-save me-2"></i> Salvar Configuração
                                                </button>
                                            </div>
                                            <div class="col-md-6">
                                                <button type="button" class="btn btn-success" onclick="testarWebhook()">
                                                    <i class="fas fa-paper-plane me-2"></i> Testar Webhook
                                                </button>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                        
                        <div class="col-md-4">
                            <div class="card">
                                <div class="card-header">
                                    <h6 class="mb-0">
                                        <i class="fas fa-info-circle me-2"></i>
                                        Variáveis Disponíveis
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <h6>Aniversário:</h6>
                                    <ul class="small">
                                        <li><code>{{nome}}</code> - Nome do cliente</li>
                                        <li><code>{{email}}</code> - Email do cliente</li>
                                        <li><code>{{data_nascimento}}</code> - Data de nascimento</li>
                                        <li><code>{{idade}}</code> - Idade calculada</li>
                                    </ul>
                                    
                                    <h6>Compra:</h6>
                                    <ul class="small">
                                        <li><code>{{nome}}</code> - Nome do cliente</li>
                                        <li><code>{{email}}</code> - Email do cliente</li>
                                        <li><code>{{pedido_id}}</code> - ID do pedido</li>
                                        <li><code>{{valor_total}}</code> - Valor total</li>
                                        <li><code>{{data_compra}}</code> - Data da compra</li>
                                    </ul>
                                    
                                    <h6>Atualização de Status:</h6>
                                    <ul class="small">
                                        <li><code>{{nome}}</code> - Nome do cliente</li>
                                        <li><code>{{pedido_id}}</code> - ID do pedido</li>
                                        <li><code>{{status_anterior}}</code> - Status anterior</li>
                                        <li><code>{{status_novo}}</code> - Novo status</li>
                                        <li><code>{{data_atualizacao}}</code> - Data da atualização</li>
                                    </ul>
                                </div>
                            </div>
                            
                            <div class="card mt-3">
                                <div class="card-header">
                                    <h6 class="mb-0">
                                        <i class="fas fa-code me-2"></i>
                                        Exemplo de Payload
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <pre class="small"><code>{
  "evento": "compra",
  "data": "2024-01-23T16:30:00Z",
  "cliente": {
    "nome": "João Silva",
    "email": "joao@email.com"
  },
  "pedido": {
    "id": "12345",
    "valor_total": "R$ 1.500,00",
    "status": "pago"
  },
  "empresa": "Braziliana Shop",
  "ambiente": "produção"
}</code></pre>
                                </div>
                            </div>
                            
                            <div class="card mt-3">
                                <div class="card-header">
                                    <h6 class="mb-0">
                                        <i class="fas fa-shield-alt me-2"></i>
                                        Segurança
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <ul class="small">
                                        <li><strong>HTTPS:</strong> Use sempre URLs seguras</li>
                                        <li><strong>Autenticação:</strong> Configure headers de autenticação</li>
                                        <li><strong>Rate Limit:</strong> Limite de requisições por minuto</li>
                                        <li><strong>Timeout:</strong> 30 segundos por requisição</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function testarEmail() {
    const form = document.getElementById('formEmail');
    const formData = new FormData(form);
    
    fetch('/admin/testar-email', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('E-mail de teste enviado com sucesso!');
        } else {
            alert('Erro ao enviar e-mail: ' + data.error);
        }
    })
    .catch(error => {
        alert('Erro ao processar requisição: ' + error.message);
    });
}

function salvarNotificacao() {
    const form = document.getElementById('formNotificacoes');
    const formData = new FormData(form);
    
    // Converter checkboxes para string
    const webhookAtivo = document.getElementById('webhook_ativo').checked ? '1' : '0';
    const webhookRetries = document.getElementById('webhook_retries').checked ? '1' : '0';
    
    formData.set('webhook_ativo', webhookAtivo);
    formData.set('webhook_retries', webhookRetries);
    
    fetch('/admin/salvar-notificacao', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Configuração de notificação salva com sucesso!');
            carregarLogsWebhook();
        } else {
            alert('Erro ao salvar configuração: ' + data.error);
        }
    });
}

function testarWebhook() {
    const evento = document.querySelector('select[name="evento"]').value;
    
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
        carregarLogsWebhook();
    })
    .catch(error => {
        alert('Erro ao testar webhook: ' + error.message);
        carregarLogsWebhook();
    });
}

function carregarLogsWebhook() {
    fetch('/admin/logs-webhook')
        .then(response => response.json())
        .then(data => {
            const tbody = document.getElementById('logs-webhook');
            tbody.innerHTML = '';
            
            if (data.logs && data.logs.length > 0) {
                data.logs.forEach(log => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${new Date(log.data_envio).toLocaleString('pt-BR')}</td>
                        <td><span class="badge badge-${log.status == 'sucesso' ? 'success' : 'danger'}">${log.status}</span></td>
                        <td><small>${log.resposta || 'Sem resposta'}</small></td>
                        <td>
                            <button type="button" class="btn btn-sm btn-outline-info" onclick="verDetalhesLog(${log.id})">
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
        .catch(error => {
            console.error('Erro ao carregar logs:', error);
        });
}

function verDetalhesLog(logId) {
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

// Carregar logs ao carregar a página
document.addEventListener('DOMContentLoaded', function() {
    // Verificar se estamos na aba de notificações
    const notificacoesTab = document.getElementById('notificacoes-tab');
    if (notificacoesTab) {
        notificacoesTab.addEventListener('shown.bs.tab', function() {
            carregarLogsWebhook();
        });
    }
});

// Função para formatar JSON em textarea
function formatarJSON(textarea) {
    try {
        const value = textarea.value;
        const formatted = JSON.stringify(JSON.parse(value), null, 2);
        textarea.value = formatted;
    } catch (e) {
        // Não faz nada se JSON inválido
    }
}

// Adicionar listeners para formatar JSON
document.addEventListener('DOMContentLoaded', function() {
    const headersTextarea = document.querySelector('textarea[name="webhook_headers"]');
    const camposTextarea = document.querySelector('textarea[name="webhook_campos"]');
    
    if (headersTextarea) {
        headersTextarea.addEventListener('blur', function() {
            formatarJSON(this);
        });
    }
    
    if (camposTextarea) {
        camposTextarea.addEventListener('blur', function() {
            formatarJSON(this);
        });
    }
});

// Adicionar listeners para salvar automaticamente quando mudar de aba
document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
    tab.addEventListener('shown', function (e) {
        const target = e.target.getAttribute('data-bs-target');
        if (target === '#email') {
            // Carregar configurações de e-mail se necessário
            console.log('Carregando configurações de e-mail...');
        }
    });
});
</script>

<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/admin.php'; ?>
