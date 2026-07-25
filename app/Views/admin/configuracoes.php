<?php ob_start(); ?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="fas fa-cog me-2"></i>
            <?= __('admin.settings.title', 'Configurações do Sistema') ?>
        </h1>
        <div>
            <a href="/admin" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i><?= __('common.back', 'Voltar') ?>
            </a>
        </div>
    </div>

    <!-- Abas de Configuração -->
    <div class="card">
        <div class="card-header">
            <ul class="nav nav-tabs" id="configTab" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="geral-tab" data-bs-toggle="tab" data-bs-target="#geral" type="button" role="tab" aria-controls="geral" aria-selected="true">
                        <i class="fas fa-cogs me-2"></i><?= __('admin.settings.tabs.general', 'Geral') ?>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="email-tab" data-bs-toggle="tab" data-bs-target="#email" type="button" role="tab" aria-controls="email" aria-selected="false">
                        <i class="fas fa-envelope me-2"></i><?= __('admin.settings.tabs.email', 'E-mail') ?>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="pagamento-tab" data-bs-toggle="tab" data-bs-target="#pagamento" type="button" role="tab" aria-controls="pagamento" aria-selected="false">
                        <i class="fas fa-credit-card me-2"></i><?= __('admin.settings.tabs.payment', 'Pagamento') ?>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="entrega-tab" data-bs-toggle="tab" data-bs-target="#entrega" type="button" role="tab" aria-controls="entrega" aria-selected="false">
                        <i class="fas fa-truck me-2"></i><?= __('admin.settings.tabs.delivery', 'Entrega') ?>
                    </button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="notificacoes-tab" data-bs-toggle="tab" data-bs-target="#notificacoes" type="button" role="tab" aria-controls="notificacoes" aria-selected="false">
                        <i class="fas fa-bell me-2"></i><?= __('admin.settings.tabs.notifications', 'Notificações') ?>
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
                                <label class="form-label"><?= __('admin.settings.general.site_name', 'Nome do Site') ?></label>
                                <input type="text" name="site_nome" class="form-control" value="<?= $configuracoes['site_nome'] ?? '' ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><?= __('admin.settings.general.site_url', 'URL do Site') ?></label>
                                <input type="url" name="site_url" class="form-control" value="<?= $configuracoes['site_url'] ?? '' ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><?= __('admin.settings.general.contact_email', 'Email de Contato') ?></label>
                                <input type="email" name="email_contato" class="form-control" value="<?= $configuracoes['email_contato'] ?? '' ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><?= __('admin.settings.general.contact_phone', 'Telefone de Contato') ?></label>
                                <input type="text" name="telefone_contato" class="form-control" value="<?= $configuracoes['telefone_contato'] ?? '' ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><?= __('admin.settings.general.default_currency', 'Moeda Padrão') ?></label>
                                <select name="moeda_padrao" class="form-select">
                                    <option value="BRL" <?= ($configuracoes['moeda_padrao'] ?? 'BRL') == 'BRL' ? 'selected' : '' ?>><?= __('admin.settings.general.currency_brl', 'Real Brasileiro (BRL)') ?></option>
                                    <option value="USD" <?= ($configuracoes['moeda_padrao'] ?? 'BRL') == 'USD' ? 'selected' : '' ?>><?= __('admin.settings.general.currency_usd', 'Dólar Americano (USD)') ?></option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><?= __('admin.settings.general.exchange_rate', 'Taxa de Câmbio (1 USD)') ?></label>
                <input type="number" name="taxa_cambio" class="form-control" step="0.01" value="<?= $configuracoes['taxa_cambio'] ?? \App\Core\ExchangeRate::getUsdToBrl() ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label"><?= __('admin.settings.general.site_description', 'Descrição do Site') ?></label>
                                <textarea name="site_descricao" class="form-control" rows="3"><?= $configuracoes['site_descricao'] ?? '' ?></textarea>
                            </div>
                            <div class="col-12">
                                <label class="form-label"><?= __('admin.settings.general.seo_keywords', 'Palavras-chave SEO') ?></label>
                                <input type="text" name="palavras_chave" class="form-control" value="<?= $configuracoes['palavras_chave'] ?? '' ?>" placeholder="<?= htmlspecialchars(__('admin.settings.general.seo_keywords_placeholder', 'Separadas por vírgula'), ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i><?= __('admin.settings.save_settings', 'Salvar Configurações') ?>
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
                                <label class="form-label"><?= __('admin.settings.email.smtp_host', 'SMTP Host') ?></label>
                                <input type="text" name="smtp_host" class="form-control" value="<?= $configuracoes['smtp_host'] ?? '' ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><?= __('admin.settings.email.smtp_port', 'SMTP Porta') ?></label>
                                <input type="number" name="smtp_port" class="form-control" value="<?= $configuracoes['smtp_port'] ?? '587' ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><?= __('admin.settings.email.smtp_user', 'SMTP Usuário') ?></label>
                                <input type="text" name="smtp_usuario" class="form-control" value="<?= $configuracoes['smtp_usuario'] ?? '' ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><?= __('admin.settings.email.smtp_password', 'SMTP Senha') ?></label>
                                <input type="password" name="smtp_senha" class="form-control" value="<?= $configuracoes['smtp_senha'] ?? '' ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><?= __('admin.settings.email.smtp_encryption', 'SMTP Criptografia') ?></label>
                                <select name="smtp_criptografia" class="form-select">
                                    <option value="tls" <?= ($configuracoes['smtp_criptografia'] ?? 'tls') == 'tls' ? 'selected' : '' ?>>TLS</option>
                                    <option value="ssl" <?= ($configuracoes['smtp_criptografia'] ?? 'tls') == 'ssl' ? 'selected' : '' ?>>SSL</option>
                                    <option value="" <?= ($configuracoes['smtp_criptografia'] ?? 'tls') == '' ? 'selected' : '' ?>><?= __('admin.settings.email.smtp_encryption_none', 'Nenhuma') ?></option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><?= __('admin.settings.email.from_email', 'Email de Remetente') ?></label>
                                <input type="email" name="email_remetente" class="form-control" value="<?= $configuracoes['email_remetente'] ?? '' ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label"><?= __('admin.settings.email.from_name', 'Nome do Remetente') ?></label>
                                <input type="text" name="nome_remetente" class="form-control" value="<?= $configuracoes['nome_remetente'] ?? '' ?>">
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i><?= __('admin.settings.save_settings', 'Salvar Configurações') ?>
                                </button>
                                <button type="button" class="btn btn-success ms-2" onclick="testarEmail()">
                                    <i class="fas fa-paper-plane me-2"></i><?= __('admin.settings.email.test', 'Testar E-mail') ?>
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
                                <label class="form-label"><?= __('admin.settings.payment.gateway', 'Gateway de Pagamento') ?></label>
                                <select name="gateway_pagamento" class="form-select">
                                    <option value="mercadopago" <?= ($configuracoes['gateway_pagamento'] ?? 'mercadopago') == 'mercadopago' ? 'selected' : '' ?>>Mercado Pago</option>
                                    <option value="paypal" <?= ($configuracoes['gateway_pagamento'] ?? 'mercadopago') == 'paypal' ? 'selected' : '' ?>>PayPal</option>
                                    <option value="stripe" <?= ($configuracoes['gateway_pagamento'] ?? 'mercadopago') == 'stripe' ? 'selected' : '' ?>>Stripe</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><?= __('admin.settings.payment.public_key', 'Chave Pública') ?></label>
                                <input type="text" name="chave_publica" class="form-control" value="<?= $configuracoes['chave_publica'] ?? '' ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><?= __('admin.settings.payment.private_key', 'Chave Privada') ?></label>
                                <input type="password" name="chave_privada" class="form-control" value="<?= $configuracoes['chave_privada'] ?? '' ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label"><?= __('admin.settings.payment.access_token', 'Token de Acesso') ?></label>
                                <input type="text" name="token_acesso" class="form-control" value="<?= $configuracoes['token_acesso'] ?? '' ?>">
                            </div>
                            <div class="col-12">
                                <label class="form-label"><?= __('admin.settings.payment.webhook_url', 'Webhook URL') ?></label>
                                <input type="url" name="webhook_url" class="form-control" value="<?= $configuracoes['webhook_url'] ?? '' ?>">
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i><?= __('admin.settings.save_settings', 'Salvar Configurações') ?>
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
                                        <?= __('admin.settings.webhook.title', 'Configurar Notificações por Webhook') ?>
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <form id="formNotificacoes">
                                        <div class="mb-4">
                                            <label class="form-label"><?= __('admin.settings.webhook.event', 'Evento') ?></label>
                                            <select name="evento" class="form-select" required>
                                                <option value=""><?= __('admin.settings.webhook.select_event', 'Selecione um evento...') ?></option>
                                                <option value="novo_pedido"><?= __('admin.settings.webhook.events.new_order', 'Novo Pedido') ?></option>
                                                <option value="pedido_aprovado"><?= __('admin.settings.webhook.events.order_approved', 'Pedido Aprovado') ?></option>
                                                <option value="pedido_enviado"><?= __('admin.settings.webhook.events.order_shipped', 'Pedido Enviado') ?></option>
                                                <option value="pedido_entregue"><?= __('admin.settings.webhook.events.order_delivered', 'Pedido Entregue') ?></option>
                                                <option value="pedido_cancelado"><?= __('admin.settings.webhook.events.order_cancelled', 'Pedido Cancelado') ?></option>
                                                <optgroup label="Carnê Braziliana">
                                                    <option value="carne_criado">Carnê Criado</option>
                                                    <option value="carne_cobranca">Carnê Cobrança (parcela em atraso)</option>
                                                    <option value="carne_parcela_proxima_vencimento">Carnê Parcela Próxima do Vencimento</option>
                                                    <option value="carne_pagamento_confirmado">Carnê Pagamento Confirmado</option>
                                                    <option value="carne_quitado">Carnê Quitado</option>
                                                    <option value="carne_envio_liberado">Carnê Envio Liberado</option>
                                                    <option value="carne_aviso_cancelamento">Carnê Aviso de Cancelamento</option>
                                                    <option value="carne_cancelado">Carnê Cancelado</option>
                                                </optgroup>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-4">
                                            <label class="form-label"><?= __('admin.settings.webhook.url', 'URL do Webhook') ?></label>
                                            <input type="url" name="webhook_url" class="form-control" placeholder="<?= htmlspecialchars(__('admin.settings.webhook.url_placeholder', 'https://seu-webhook.com/notificacoes'), ENT_QUOTES, 'UTF-8') ?>" required>
                                            <div class="form-text"><?= __('admin.settings.webhook.url_hint', 'URL completa do endpoint que receberá as notificações') ?></div>
                                        </div>
                                        
                                        <div class="mb-4">
                                            <label class="form-label"><?= __('admin.settings.webhook.http_method', 'Método HTTP') ?></label>
                                            <select name="webhook_method" class="form-select">
                                                <option value="POST">POST</option>
                                                <option value="PUT">PUT</option>
                                                <option value="PATCH">PATCH</option>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-4">
                                            <label class="form-label"><?= __('admin.settings.webhook.headers', 'Headers (JSON)') ?></label>
                                            <textarea name="webhook_headers" class="form-control" rows="3" placeholder='{"Authorization": "Bearer token123", "Content-Type": "application/json"}'></textarea>
                                            <div class="form-text"><?= __('admin.settings.webhook.headers_hint', 'Headers em formato JSON que serão enviados com a requisição') ?></div>
                                        </div>
                                        
                                        <div class="mb-4">
                                            <label class="form-label"><?= __('admin.settings.webhook.custom_fields', 'Campos Personalizados (JSON)') ?></label>
                                            <textarea name="webhook_campos" class="form-control" rows="5" placeholder='{"empresa": "Braziliana", "ambiente": "producao"}'></textarea>
                                            <div class="form-text"><?= __('admin.settings.webhook.custom_fields_hint', 'Campos adicionais em formato JSON que serão incluídos no payload') ?></div>
                                        </div>
                                        
                                        <div class="mb-4">
                                            <label class="form-label"><?= __('admin.settings.webhook.message_template', 'Template da Mensagem') ?></label>
                                            <textarea name="webhook_template" class="form-control" rows="4" placeholder='Olá {{nome}}, seu pedido #{{pedido_id}} foi {{status}}!'></textarea>
                                            <div class="form-text"><?= __('admin.settings.webhook.template_hint', 'Use variáveis entre chaves duplas: {{nome}}, {{email}}, {{pedido_id}}, {{status}}, {{data}}, etc.') ?></div>
                                        </div>
                                        
                                        <div class="mb-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="webhook_ativo" id="webhook_ativo" checked>
                                                <label class="form-check-label" for="webhook_ativo">
                                                    <?= __('admin.settings.webhook.active', 'Webhook Ativo') ?>
                                                </label>
                                            </div>
                                            <div class="form-text"><?= __('admin.settings.webhook.active_hint', 'Desmarque para desativar temporariamente este webhook') ?></div>
                                        </div>
                                        
                                        <div class="mb-4">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" name="webhook_retries" id="webhook_retries" checked>
                                                <label class="form-check-label" for="webhook_retries">
                                                    <?= __('admin.settings.webhook.retries', 'Tentativas de Reenvio') ?>
                                                </label>
                                            </div>
                                            <div class="form-text"><?= __('admin.settings.webhook.retries_hint', 'Se falhar, tentará novamente em 1, 5 e 15 minutos') ?></div>
                                        </div>
                                        
                                        <div class="mb-4">
                                            <label class="form-label"><?= __('admin.settings.webhook.logs', 'Logs de Envio') ?></label>
                                            <div class="table-responsive">
                                                <table class="table table-sm">
                                                    <thead>
                                                        <tr>
                                                            <th><?= __('admin.settings.webhook.logs_table.date', 'Data') ?></th>
                                                            <th><?= __('common.status', 'Status') ?></th>
                                                            <th><?= __('admin.settings.webhook.logs_table.response', 'Resposta') ?></th>
                                                            <th><?= __('common.actions', 'Ações') ?></th>
                                                        </tr>
                                                    </thead>
                                                    <tbody id="logs-webhook">
                                                        <tr>
                                                            <td colspan="4" class="text-center"><?= __('admin.settings.webhook.logs_empty', 'Nenhum log encontrado') ?></td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                        
                                        <div class="row">
                                            <div class="col-md-6">
                                                <button type="button" class="btn btn-primary" onclick="salvarNotificacao()">
                                                    <i class="fas fa-save me-2"></i> <?= __('admin.settings.webhook.save', 'Salvar Configuração') ?>
                                                </button>
                                            </div>
                                            <div class="col-md-6">
                                                <button type="button" class="btn btn-success" onclick="testarWebhook()">
                                                    <i class="fas fa-paper-plane me-2"></i> <?= __('admin.settings.webhook.test', 'Testar Webhook') ?>
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
                                        <?= __('admin.settings.webhook.available_variables', 'Variáveis Disponíveis') ?>
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <h6><?= __('admin.settings.webhook.vars.birthday_title', 'Aniversário:') ?></h6>
                                    <ul class="small">
                                        <li><code>{{nome}}</code> - <?= __('admin.settings.webhook.vars.customer_name', 'Nome do cliente') ?></li>
                                        <li><code>{{email}}</code> - <?= __('admin.settings.webhook.vars.customer_email', 'Email do cliente') ?></li>
                                        <li><code>{{data_nascimento}}</code> - <?= __('admin.settings.webhook.vars.birth_date', 'Data de nascimento') ?></li>
                                        <li><code>{{idade}}</code> - <?= __('admin.settings.webhook.vars.age', 'Idade calculada') ?></li>
                                    </ul>
                                    
                                    <h6><?= __('admin.settings.webhook.vars.purchase_title', 'Compra:') ?></h6>
                                    <ul class="small">
                                        <li><code>{{nome}}</code> - <?= __('admin.settings.webhook.vars.customer_name', 'Nome do cliente') ?></li>
                                        <li><code>{{email}}</code> - <?= __('admin.settings.webhook.vars.customer_email', 'Email do cliente') ?></li>
                                        <li><code>{{pedido_id}}</code> - <?= __('admin.settings.webhook.vars.order_id', 'ID do pedido') ?></li>
                                        <li><code>{{valor_total}}</code> - <?= __('admin.settings.webhook.vars.total_value', 'Valor total') ?></li>
                                        <li><code>{{data_compra}}</code> - <?= __('admin.settings.webhook.vars.purchase_date', 'Data da compra') ?></li>
                                    </ul>
                                    
                                    <h6><?= __('admin.settings.webhook.vars.status_update_title', 'Atualização de Status:') ?></h6>
                                    <ul class="small">
                                        <li><code>{{nome}}</code> - <?= __('admin.settings.webhook.vars.customer_name', 'Nome do cliente') ?></li>
                                        <li><code>{{pedido_id}}</code> - <?= __('admin.settings.webhook.vars.order_id', 'ID do pedido') ?></li>
                                        <li><code>{{status_anterior}}</code> - <?= __('admin.settings.webhook.vars.previous_status', 'Status anterior') ?></li>
                                        <li><code>{{status_novo}}</code> - <?= __('admin.settings.webhook.vars.new_status', 'Novo status') ?></li>
                                        <li><code>{{data_atualizacao}}</code> - <?= __('admin.settings.webhook.vars.update_date', 'Data da atualização') ?></li>
                                    </ul>
                                </div>
                            </div>
                            
                            <div class="card mt-3">
                                <div class="card-header">
                                    <h6 class="mb-0">
                                        <i class="fas fa-code me-2"></i>
                                        <?= __('admin.settings.webhook.payload_example', 'Exemplo de Payload') ?>
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
    "valor_total": "<?= __('admin.orders.js.currency_brl', 'R$') ?> 1.500,00",
    "status": "pago"
  },
  "empresa": "Braziliana",
  "ambiente": "produção"
}</code></pre>
                                </div>
                            </div>
                            
                            <div class="card mt-3">
                                <div class="card-header">
                                    <h6 class="mb-0">
                                        <i class="fas fa-shield-alt me-2"></i>
                                        <?= __('admin.settings.webhook.security_title', 'Segurança') ?>
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <ul class="small">
                                        <li><strong>HTTPS:</strong> <?= __('admin.settings.webhook.security.https', 'Use sempre URLs seguras') ?></li>
                                        <li><strong><?= __('admin.settings.webhook.security.auth_label', 'Autenticação') ?>:</strong> <?= __('admin.settings.webhook.security.auth', 'Configure headers de autenticação') ?></li>
                                        <li><strong><?= __('admin.settings.webhook.security.rate_limit_label', 'Rate Limit') ?>:</strong> <?= __('admin.settings.webhook.security.rate_limit', 'Limite de requisições por minuto') ?></li>
                                        <li><strong><?= __('admin.settings.webhook.security.timeout_label', 'Timeout') ?>:</strong> <?= __('admin.settings.webhook.security.timeout', '30 segundos por requisição') ?></li>
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
window.ADMIN_SETTINGS_I18N = {
    test_email_sent_success: <?= json_encode(__('admin.settings.js.test_email_sent_success', 'E-mail de teste enviado com sucesso!'), JSON_UNESCAPED_UNICODE) ?>,
    error_send_email_prefix: <?= json_encode(__('admin.settings.js.error_send_email_prefix', 'Erro ao enviar e-mail:'), JSON_UNESCAPED_UNICODE) ?>,
    error_process_request_prefix: <?= json_encode(__('admin.settings.js.error_process_request_prefix', 'Erro ao processar requisição:'), JSON_UNESCAPED_UNICODE) ?>,
    notification_saved_success: <?= json_encode(__('admin.settings.js.notification_saved_success', 'Configuração de notificação salva com sucesso!'), JSON_UNESCAPED_UNICODE) ?>,
    error_save_config_prefix: <?= json_encode(__('admin.settings.js.error_save_config_prefix', 'Erro ao salvar configuração:'), JSON_UNESCAPED_UNICODE) ?>,
    select_event: <?= json_encode(__('admin.settings.js.select_event', 'Selecione um evento.'), JSON_UNESCAPED_UNICODE) ?>,
    webhook_tested_success_prefix: <?= json_encode(__('admin.settings.js.webhook_tested_success_prefix', 'Webhook testado com sucesso!\n\nResposta:'), JSON_UNESCAPED_UNICODE) ?>,
    error_test_webhook_prefix: <?= json_encode(__('admin.settings.js.error_test_webhook_prefix', 'Erro ao testar webhook:'), JSON_UNESCAPED_UNICODE) ?>,
    no_response: <?= json_encode(__('admin.settings.js.no_response', 'Sem resposta'), JSON_UNESCAPED_UNICODE) ?>,
    no_logs_found: <?= json_encode(__('admin.settings.webhook.logs_empty', 'Nenhum log encontrado'), JSON_UNESCAPED_UNICODE) ?>,
    log_details_title_prefix: <?= json_encode(__('admin.settings.js.log_details_title_prefix', 'Detalhes do Log #{id}:'), JSON_UNESCAPED_UNICODE) ?>,
    log_date: <?= json_encode(__('admin.settings.js.log_date', 'Data:'), JSON_UNESCAPED_UNICODE) ?>,
    log_status: <?= json_encode(__('admin.settings.js.log_status', 'Status:'), JSON_UNESCAPED_UNICODE) ?>,
    log_url: <?= json_encode(__('admin.settings.js.log_url', 'URL:'), JSON_UNESCAPED_UNICODE) ?>,
    log_method: <?= json_encode(__('admin.settings.js.log_method', 'Método:'), JSON_UNESCAPED_UNICODE) ?>,
    log_headers: <?= json_encode(__('admin.settings.js.log_headers', 'Headers:'), JSON_UNESCAPED_UNICODE) ?>,
    log_payload: <?= json_encode(__('admin.settings.js.log_payload', 'Payload:'), JSON_UNESCAPED_UNICODE) ?>,
    log_response: <?= json_encode(__('admin.settings.js.log_response', 'Resposta:'), JSON_UNESCAPED_UNICODE) ?>,
    loading_email_settings: <?= json_encode(__('admin.settings.js.loading_email_settings', 'Carregando configurações de e-mail...'), JSON_UNESCAPED_UNICODE) ?>,
    locale: <?= json_encode(\App\Core\I18n::getLocaleHtml(), JSON_UNESCAPED_UNICODE) ?>
};

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
            alert((window.ADMIN_SETTINGS_I18N && window.ADMIN_SETTINGS_I18N.test_email_sent_success) ? window.ADMIN_SETTINGS_I18N.test_email_sent_success : 'E-mail de teste enviado com sucesso!');
        } else {
            alert(((window.ADMIN_SETTINGS_I18N && window.ADMIN_SETTINGS_I18N.error_send_email_prefix) ? window.ADMIN_SETTINGS_I18N.error_send_email_prefix : 'Erro ao enviar e-mail:') + ' ' + data.error);
        }
    })
    .catch(error => {
        alert(((window.ADMIN_SETTINGS_I18N && window.ADMIN_SETTINGS_I18N.error_process_request_prefix) ? window.ADMIN_SETTINGS_I18N.error_process_request_prefix : 'Erro ao processar requisição:') + ' ' + error.message);
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
            alert((window.ADMIN_SETTINGS_I18N && window.ADMIN_SETTINGS_I18N.notification_saved_success) ? window.ADMIN_SETTINGS_I18N.notification_saved_success : 'Configuração de notificação salva com sucesso!');
            carregarLogsWebhook();
        } else {
            alert(((window.ADMIN_SETTINGS_I18N && window.ADMIN_SETTINGS_I18N.error_save_config_prefix) ? window.ADMIN_SETTINGS_I18N.error_save_config_prefix : 'Erro ao salvar configuração:') + ' ' + data.error);
        }
    });
}

function testarWebhook() {
    const evento = document.querySelector('select[name="evento"]').value;
    
    if (!evento) {
        alert((window.ADMIN_SETTINGS_I18N && window.ADMIN_SETTINGS_I18N.select_event) ? window.ADMIN_SETTINGS_I18N.select_event : 'Selecione um evento.');
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
            alert(((window.ADMIN_SETTINGS_I18N && window.ADMIN_SETTINGS_I18N.webhook_tested_success_prefix) ? window.ADMIN_SETTINGS_I18N.webhook_tested_success_prefix : 'Webhook testado com sucesso!\n\nResposta:') + ' ' + JSON.stringify(data, null, 2));
        } else {
            alert(((window.ADMIN_SETTINGS_I18N && window.ADMIN_SETTINGS_I18N.error_test_webhook_prefix) ? window.ADMIN_SETTINGS_I18N.error_test_webhook_prefix : 'Erro ao testar webhook:') + ' ' + (data.error || JSON.stringify(data)));
        }
        carregarLogsWebhook();
    })
    .catch(error => {
        alert(((window.ADMIN_SETTINGS_I18N && window.ADMIN_SETTINGS_I18N.error_test_webhook_prefix) ? window.ADMIN_SETTINGS_I18N.error_test_webhook_prefix : 'Erro ao testar webhook:') + ' ' + error.message);
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
                    const locale = (window.ADMIN_SETTINGS_I18N && window.ADMIN_SETTINGS_I18N.locale) ? window.ADMIN_SETTINGS_I18N.locale : ((document.documentElement && document.documentElement.lang) ? document.documentElement.lang : 'pt-BR');
                    const noResponse = (window.ADMIN_SETTINGS_I18N && window.ADMIN_SETTINGS_I18N.no_response) ? window.ADMIN_SETTINGS_I18N.no_response : 'Sem resposta';
                    tr.innerHTML = `
                        <td>${new Date(log.data_envio).toLocaleString(locale)}</td>
                        <td><span class="badge" style="background: ${log.status == 'sucesso' ? 'rgba(16, 185, 129, 0.12)' : 'rgba(239, 68, 68, 0.12)'}; border: 1px solid ${log.status == 'sucesso' ? 'rgba(16, 185, 129, 0.22)' : 'rgba(239, 68, 68, 0.22)'}; color: ${log.status == 'sucesso' ? '#065f46' : '#7f1d1d'};">${log.status}</span></td>
                        <td><small>${log.resposta || noResponse}</small></td>
                        <td>
                            <button type="button" class="btn btn-sm btn-outline-info" onclick="verDetalhesLog(${log.id})">
                                <i class="fas fa-eye"></i>
                            </button>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
            } else {
                tbody.innerHTML = '<tr><td colspan="4" class="text-center">' + ((window.ADMIN_SETTINGS_I18N && window.ADMIN_SETTINGS_I18N.no_logs_found) ? window.ADMIN_SETTINGS_I18N.no_logs_found : 'Nenhum log encontrado') + '</td></tr>';
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
                const locale = (window.ADMIN_SETTINGS_I18N && window.ADMIN_SETTINGS_I18N.locale) ? window.ADMIN_SETTINGS_I18N.locale : ((document.documentElement && document.documentElement.lang) ? document.documentElement.lang : 'pt-BR');
                const titlePrefix = (window.ADMIN_SETTINGS_I18N && window.ADMIN_SETTINGS_I18N.log_details_title_prefix) ? window.ADMIN_SETTINGS_I18N.log_details_title_prefix : 'Detalhes do Log #{id}:';
                const lblDate = (window.ADMIN_SETTINGS_I18N && window.ADMIN_SETTINGS_I18N.log_date) ? window.ADMIN_SETTINGS_I18N.log_date : 'Data:';
                const lblStatus = (window.ADMIN_SETTINGS_I18N && window.ADMIN_SETTINGS_I18N.log_status) ? window.ADMIN_SETTINGS_I18N.log_status : 'Status:';
                const lblUrl = (window.ADMIN_SETTINGS_I18N && window.ADMIN_SETTINGS_I18N.log_url) ? window.ADMIN_SETTINGS_I18N.log_url : 'URL:';
                const lblMethod = (window.ADMIN_SETTINGS_I18N && window.ADMIN_SETTINGS_I18N.log_method) ? window.ADMIN_SETTINGS_I18N.log_method : 'Método:';
                const lblHeaders = (window.ADMIN_SETTINGS_I18N && window.ADMIN_SETTINGS_I18N.log_headers) ? window.ADMIN_SETTINGS_I18N.log_headers : 'Headers:';
                const lblPayload = (window.ADMIN_SETTINGS_I18N && window.ADMIN_SETTINGS_I18N.log_payload) ? window.ADMIN_SETTINGS_I18N.log_payload : 'Payload:';
                const lblResp = (window.ADMIN_SETTINGS_I18N && window.ADMIN_SETTINGS_I18N.log_response) ? window.ADMIN_SETTINGS_I18N.log_response : 'Resposta:';
                alert(titlePrefix.replace('{id}', String(logId)) + "\n\n" +
                    `${lblDate} ${new Date(data.log.data_envio).toLocaleString(locale)}\n` +
                    `${lblStatus} ${data.log.status}\n` +
                    `${lblUrl} ${data.log.webhook_url}\n` +
                    `${lblMethod} ${data.log.metodo}\n` +
                    `${lblHeaders} ${data.log.headers}\n` +
                    `${lblPayload} ${data.log.payload}\n` +
                    `${lblResp} ${data.log.resposta}`);
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
            console.log((window.ADMIN_SETTINGS_I18N && window.ADMIN_SETTINGS_I18N.loading_email_settings) ? window.ADMIN_SETTINGS_I18N.loading_email_settings : 'Carregando configurações de e-mail...');
        }
    });
});
</script>

<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/admin.php'; ?>
