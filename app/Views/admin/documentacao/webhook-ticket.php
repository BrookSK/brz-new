<?php
/**
 * Documentação - Webhook Criar Ticket (WhatsApp)
 */

// Buscar URL de callback atual
$callbackUrlAtual = '';
try {
    $pdo = \Config\Database::getConnection();
    $st = $pdo->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = 'webhook_ticket_callback_url' LIMIT 1");
    $st->execute();
    $callbackUrlAtual = (string)($st->fetchColumn() ?: '');
} catch (\Exception $e) {}

// Salvar se enviou formulário
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['callback_url'])) {
    try {
        $novaUrl = trim((string)$_POST['callback_url']);
        $pdo = \Config\Database::getConnection();
        $st = $pdo->prepare("SELECT COUNT(*) FROM configuracoes_sistema WHERE chave = 'webhook_ticket_callback_url'");
        $st->execute();
        if ((int)$st->fetchColumn() > 0) {
            $st = $pdo->prepare("UPDATE configuracoes_sistema SET valor = ? WHERE chave = 'webhook_ticket_callback_url'");
            $st->execute([$novaUrl]);
        } else {
            $st = $pdo->prepare("INSERT INTO configuracoes_sistema (chave, valor) VALUES ('webhook_ticket_callback_url', ?)");
            $st->execute([$novaUrl]);
        }
        $callbackUrlAtual = $novaUrl;
        $salvoOk = true;
    } catch (\Exception $e) {
        $salvoErro = $e->getMessage();
    }
}
?>
<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h2 class="fw-bold mb-1"><?= htmlspecialchars(__('admin.webhook_docs.title', 'Documentação: Webhook Criar Ticket'), ENT_QUOTES, 'UTF-8') ?></h2>
            <p class="text-muted mb-0"><?= htmlspecialchars(__('admin.webhook_docs.subtitle', 'Integração WhatsApp → Sistema de Tickets'), ENT_QUOTES, 'UTF-8') ?></p>
        </div>
        <a href="/admin" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i><?= htmlspecialchars(__('common.back', 'Voltar'), ENT_QUOTES, 'UTF-8') ?></a>
    </div>

    <!-- Callback URL configuration -->
    <div class="card mb-4 border-primary">
        <div class="card-header bg-primary text-white"><strong><i class="fas fa-link me-2"></i><?= htmlspecialchars(__('admin.webhook_docs.callback_url_title', 'URL de Callback (Resposta)'), ENT_QUOTES, 'UTF-8') ?></strong></div>
        <div class="card-body">
            <p class="small text-muted mb-3"><?= htmlspecialchars(__('admin.webhook_docs.callback_url_desc', 'Após processar a criação do ticket, o sistema envia a resposta (sucesso ou erro) para esta URL. Configure aqui a URL da sua automação que vai receber o resultado.'), ENT_QUOTES, 'UTF-8') ?></p>
            <?php if (!empty($salvoOk)): ?>
                <div class="alert alert-success py-2"><?= htmlspecialchars(__('admin.webhook_docs.callback_saved', 'URL de callback salva com sucesso!'), ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <?php if (!empty($salvoErro)): ?>
                <div class="alert alert-danger py-2"><?= htmlspecialchars(__('admin.webhook_docs.save_error', 'Erro ao salvar:'), ENT_QUOTES, 'UTF-8') ?> <?= htmlspecialchars($salvoErro) ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-globe"></i></span>
                    <input type="url" name="callback_url" class="form-control" value="<?= htmlspecialchars($callbackUrlAtual) ?>" placeholder="https://your-automation.com/webhook/ticket-response">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i><?= htmlspecialchars(__('common.save', 'Salvar'), ENT_QUOTES, 'UTF-8') ?></button>
                </div>
                <div class="form-text"><?= htmlspecialchars(__('admin.webhook_docs.callback_url_hint', 'A resposta será enviada via POST com JSON contendo success, ticket_id, error, message, etc.'), ENT_QUOTES, 'UTF-8') ?></div>
            </form>

            <?php
            // Buscar URL de resposta de ticket
            $respostaUrlAtual = '';
            try {
                $st = $pdo->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = 'webhook_ticket_resposta_url' LIMIT 1");
                $st->execute();
                $respostaUrlAtual = (string)($st->fetchColumn() ?: '');
            } catch (\Exception $e) {}

            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['resposta_url'])) {
                try {
                    $novaRespostaUrl = trim((string)$_POST['resposta_url']);
                    $st = $pdo->prepare("SELECT COUNT(*) FROM configuracoes_sistema WHERE chave = 'webhook_ticket_resposta_url'");
                    $st->execute();
                    if ((int)$st->fetchColumn() > 0) {
                        $st = $pdo->prepare("UPDATE configuracoes_sistema SET valor = ? WHERE chave = 'webhook_ticket_resposta_url'");
                        $st->execute([$novaRespostaUrl]);
                    } else {
                        $st = $pdo->prepare("INSERT INTO configuracoes_sistema (chave, valor) VALUES ('webhook_ticket_resposta_url', ?)");
                        $st->execute([$novaRespostaUrl]);
                    }
                    $respostaUrlAtual = $novaRespostaUrl;
                    $salvoRespostaOk = true;
                } catch (\Exception $e) { $salvoRespostaErro = $e->getMessage(); }
            }
            ?>

            <hr class="my-3">
            <p class="small text-muted mb-2"><strong><?= htmlspecialchars(__('admin.webhook_docs.response_url_title', 'URL de Notificação de Resposta:'), ENT_QUOTES, 'UTF-8') ?></strong> <?= htmlspecialchars(__('admin.webhook_docs.response_url_desc', 'Quando um especialista responder o ticket dentro de 30 minutos, o sistema envia um webhook para esta URL com a resposta.'), ENT_QUOTES, 'UTF-8') ?></p>
            <?php if (!empty($salvoRespostaOk)): ?>
                <div class="alert alert-success py-2"><?= htmlspecialchars(__('admin.webhook_docs.response_saved', 'URL de resposta salva com sucesso!'), ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-reply"></i></span>
                    <input type="url" name="resposta_url" class="form-control" value="<?= htmlspecialchars($respostaUrlAtual) ?>" placeholder="https://your-automation.com/webhook/ticket-answered">
                    <button type="submit" class="btn btn-success"><i class="fas fa-save me-1"></i><?= htmlspecialchars(__('common.save', 'Salvar'), ENT_QUOTES, 'UTF-8') ?></button>
                </div>
                <div class="form-text"><?= htmlspecialchars(__('admin.webhook_docs.response_url_hint', 'Recebe: evento, ticket_id, mensagem_resposta, cliente_nome, telefone_limpo, etc. Só dispara na primeira resposta e dentro de 30 min.'), ENT_QUOTES, 'UTF-8') ?></div>
            </form>
        </div>
    </div>

    <!-- Overview -->
    <div class="card mb-4">
        <div class="card-header"><strong><i class="fas fa-info-circle me-2"></i><?= htmlspecialchars(__('admin.webhook_docs.overview_title', 'Visão Geral'), ENT_QUOTES, 'UTF-8') ?></strong></div>
        <div class="card-body">
            <p><?= htmlspecialchars(__('admin.webhook_docs.overview_desc', 'Este endpoint permite que automações externas (como bots de WhatsApp) criem tickets de suporte automaticamente no sistema da Braziliana.'), ENT_QUOTES, 'UTF-8') ?></p>
            <div class="alert alert-info">
                <strong><?= htmlspecialchars(__('admin.webhook_docs.endpoint_url', 'URL do Endpoint:'), ENT_QUOTES, 'UTF-8') ?></strong><br>
                <code class="fs-6">POST https://brazilianashop.com.br/webhook/criar-ticket</code>
            </div>
            <p><strong>Content-Type:</strong> <code>application/json</code></p>
        </div>
    </div>

    <!-- Flow -->
    <div class="card mb-4">
        <div class="card-header"><strong><i class="fas fa-project-diagram me-2"></i><?= htmlspecialchars(__('admin.webhook_docs.flow_title', 'Fluxo de Funcionamento'), ENT_QUOTES, 'UTF-8') ?></strong></div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-12">
                    <ol class="list-group list-group-numbered">
                        <li class="list-group-item"><?= htmlspecialchars(__('admin.webhook_docs.flow_1', 'Cliente manda mensagem no WhatsApp e escolhe "Suporte"'), ENT_QUOTES, 'UTF-8') ?></li>
                        <li class="list-group-item"><?= __('admin.webhook_docs.flow_2', 'Bot pergunta a <strong>suite</strong> do cliente') ?></li>
                        <li class="list-group-item"><?= htmlspecialchars(__('admin.webhook_docs.flow_3', 'Cliente informa a suite'), ENT_QUOTES, 'UTF-8') ?></li>
                        <li class="list-group-item"><?= htmlspecialchars(__('admin.webhook_docs.flow_4', 'Bot pergunta qual o problema (mensagem)'), ENT_QUOTES, 'UTF-8') ?></li>
                        <li class="list-group-item"><?= __('admin.webhook_docs.flow_5', 'Bot envia webhook para <code>/webhook/criar-ticket</code> com suite + mensagem') ?></li>
                        <li class="list-group-item"><?= __('admin.webhook_docs.flow_6', 'Sistema busca o usuário pela suite → se encontrar, cria o ticket') ?></li>
                        <li class="list-group-item">
                            <?= __('admin.webhook_docs.flow_7', '<strong>Se não encontrar pela suite:</strong> retorna erro <code>usuario_nao_encontrado</code><br>→ Bot pede o <strong>email</strong> do cliente<br>→ Bot envia webhook novamente com email + mensagem') ?>
                        </li>
                        <li class="list-group-item"><?= htmlspecialchars(__('admin.webhook_docs.flow_8', 'Sistema retorna sucesso com o número do ticket'), ENT_QUOTES, 'UTF-8') ?></li>
                        <li class="list-group-item"><?= htmlspecialchars(__('admin.webhook_docs.flow_9', 'Bot informa ao cliente: "Ticket #XX criado! Aguarde resposta."'), ENT_QUOTES, 'UTF-8') ?></li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Payload -->
    <div class="card mb-4">
        <div class="card-header"><strong><i class="fas fa-code me-2"></i><?= htmlspecialchars(__('admin.webhook_docs.payload_title', 'Payload (Corpo da Requisição)'), ENT_QUOTES, 'UTF-8') ?></strong></div>
        <div class="card-body">
            <table class="table table-sm table-bordered">
                <thead class="table-light">
                    <tr><th><?= htmlspecialchars(__('admin.webhook_docs.col_field', 'Campo'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(__('admin.webhook_docs.col_type', 'Tipo'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(__('admin.webhook_docs.col_required', 'Obrigatório'), ENT_QUOTES, 'UTF-8') ?></th><th><?= htmlspecialchars(__('admin.webhook_docs.col_description', 'Descrição'), ENT_QUOTES, 'UTF-8') ?></th></tr>
                </thead>
                <tbody>
                    <tr><td><code>suite</code></td><td>string</td><td><?= htmlspecialchars(__('admin.webhook_docs.yes_star', 'Sim*'), ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars(__('admin.webhook_docs.field_suite', 'Número da suite do cliente (prioridade na busca)'), ENT_QUOTES, 'UTF-8') ?></td></tr>
                    <tr><td><code>email</code></td><td>string</td><td><?= htmlspecialchars(__('admin.webhook_docs.yes_star', 'Sim*'), ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars(__('admin.webhook_docs.field_email', 'Email do cliente (usado se suite não encontrar)'), ENT_QUOTES, 'UTF-8') ?></td></tr>
                    <tr><td><code>mensagem</code></td><td>string</td><td><strong><?= htmlspecialchars(__('admin.webhook_docs.yes', 'Sim'), ENT_QUOTES, 'UTF-8') ?></strong></td><td><?= htmlspecialchars(__('admin.webhook_docs.field_message', 'Descrição do problema relatado pelo cliente'), ENT_QUOTES, 'UTF-8') ?></td></tr>
                    <tr><td><code>assunto</code></td><td>string</td><td><?= htmlspecialchars(__('admin.webhook_docs.no', 'Não'), ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars(__('admin.webhook_docs.field_subject', 'Assunto do ticket (padrão: "Suporte via WhatsApp")'), ENT_QUOTES, 'UTF-8') ?></td></tr>
                    <tr><td><code>telefone</code></td><td>string</td><td><?= htmlspecialchars(__('admin.webhook_docs.no', 'Não'), ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars(__('admin.webhook_docs.field_phone', 'Telefone do cliente (só números, ex: 5519998980873)'), ENT_QUOTES, 'UTF-8') ?></td></tr>
                    <tr><td><code>nome</code></td><td>string</td><td><?= htmlspecialchars(__('admin.webhook_docs.no', 'Não'), ENT_QUOTES, 'UTF-8') ?></td><td><?= htmlspecialchars(__('admin.webhook_docs.field_name', 'Nome do cliente (informativo)'), ENT_QUOTES, 'UTF-8') ?></td></tr>
                </tbody>
            </table>
            <p class="text-muted small"><?= __('admin.webhook_docs.at_least_one', '* Pelo menos um dos dois (<code>suite</code> ou <code>email</code>) deve ser informado.') ?></p>

            <h6 class="mt-4"><?= htmlspecialchars(__('admin.webhook_docs.example_by_suite', 'Exemplo - Busca por Suite:'), ENT_QUOTES, 'UTF-8') ?></h6>
            <pre class="bg-dark text-light p-3 rounded"><code>{
  "suite": "16013",
  "nome": "Ana Silva",
  "mensagem": "Meu pedido não chegou, já faz 30 dias",
  "assunto": "Pedido atrasado",
  "telefone": "5519998980873"
}</code></pre>

            <h6 class="mt-4"><?= htmlspecialchars(__('admin.webhook_docs.example_by_email', 'Exemplo - Busca por Email (quando suite não encontrou):'), ENT_QUOTES, 'UTF-8') ?></h6>
            <pre class="bg-dark text-light p-3 rounded"><code>{
  "email": "ana.silva@gmail.com",
  "nome": "Ana Silva",
  "mensagem": "Meu pedido não chegou, já faz 30 dias",
  "assunto": "Pedido atrasado",
  "telefone": "5519998980873"
}</code></pre>

            <h6 class="mt-4"><?= htmlspecialchars(__('admin.webhook_docs.example_suite_email', 'Exemplo - Suite + Email (tenta suite primeiro, fallback email):'), ENT_QUOTES, 'UTF-8') ?></h6>
            <pre class="bg-dark text-light p-3 rounded"><code>{
  "suite": "16013",
  "email": "ana.silva@gmail.com",
  "nome": "Ana Silva",
  "mensagem": "Preciso de ajuda com minha compra",
  "telefone": "5519998980873"
}</code></pre>
        </div>
    </div>

    <!-- Responses -->
    <div class="card mb-4">
        <div class="card-header"><strong><i class="fas fa-reply me-2"></i><?= htmlspecialchars(__('admin.webhook_docs.responses_title', 'Respostas do Webhook'), ENT_QUOTES, 'UTF-8') ?></strong></div>
        <div class="card-body">

            <h6 class="text-success"><i class="fas fa-check-circle me-1"></i><?= htmlspecialchars(__('admin.webhook_docs.resp_success', 'Sucesso (HTTP 200)'), ENT_QUOTES, 'UTF-8') ?></h6>
            <pre class="bg-dark text-light p-3 rounded"><code>{
  "success": true,
  "ticket_id": 45,
  "message": "Ticket criado com sucesso",
  "usuario_id": 123,
  "usuario_nome": "Ana Silva",
  "usuario_email": "ana.silva@gmail.com",
  "nome_informado": "Ana Silva",
  "telefone_informado": "5519998980873"
}</code></pre>

            <hr>

            <h6 class="text-danger"><i class="fas fa-times-circle me-1"></i><?= htmlspecialchars(__('admin.webhook_docs.resp_err_user_not_found', 'Erro: Usuário não encontrado (HTTP 200)'), ENT_QUOTES, 'UTF-8') ?></h6>
            <pre class="bg-dark text-light p-3 rounded"><code>{
  "success": false,
  "error": "usuario_nao_encontrado",
  "message": "Nenhum usuário encontrado com a suite 99999",
  "suite_informada": "99999",
  "email_informado": "",
  "nome_informado": "Ana Silva",
  "telefone_informado": "5519998980873"
}</code></pre>
            <p class="text-muted small"><?= htmlspecialchars(__('admin.webhook_docs.resp_err_user_not_found_note', '→ Neste caso, o bot deve pedir o email e enviar novamente.'), ENT_QUOTES, 'UTF-8') ?></p>

            <hr>

            <h6 class="text-danger"><i class="fas fa-times-circle me-1"></i><?= htmlspecialchars(__('admin.webhook_docs.resp_err_empty_message', 'Erro: Mensagem vazia (HTTP 200)'), ENT_QUOTES, 'UTF-8') ?></h6>
            <pre class="bg-dark text-light p-3 rounded"><code>{
  "success": false,
  "error": "mensagem_vazia",
  "message": "A mensagem é obrigatória"
}</code></pre>

            <hr>

            <h6 class="text-danger"><i class="fas fa-times-circle me-1"></i><?= htmlspecialchars(__('admin.webhook_docs.resp_err_no_identification', 'Erro: Sem identificação (HTTP 200)'), ENT_QUOTES, 'UTF-8') ?></h6>
            <pre class="bg-dark text-light p-3 rounded"><code>{
  "success": false,
  "error": "identificacao_ausente",
  "message": "Informe a suite ou o email do cliente"
}</code></pre>

            <hr>

            <h6 class="text-danger"><i class="fas fa-times-circle me-1"></i><?= htmlspecialchars(__('admin.webhook_docs.resp_err_invalid_payload', 'Erro: Payload inválido (HTTP 200)'), ENT_QUOTES, 'UTF-8') ?></h6>
            <pre class="bg-dark text-light p-3 rounded"><code>{
  "success": false,
  "error": "payload_invalido",
  "message": "Payload JSON inválido"
}</code></pre>

            <hr>

            <h6 class="text-warning"><i class="fas fa-exclamation-triangle me-1"></i><?= htmlspecialchars(__('admin.webhook_docs.resp_err_internal', 'Erro interno (HTTP 200)'), ENT_QUOTES, 'UTF-8') ?></h6>
            <pre class="bg-dark text-light p-3 rounded"><code>{
  "success": false,
  "error": "erro_interno",
  "message": "Erro ao criar ticket: [detalhes]"
}</code></pre>
        </div>
    </div>

    <!-- Bot Logic -->
    <div class="card mb-4">
        <div class="card-header"><strong><i class="fas fa-robot me-2"></i><?= htmlspecialchars(__('admin.webhook_docs.bot_logic_title', 'Lógica para o Bot do WhatsApp'), ENT_QUOTES, 'UTF-8') ?></strong></div>
        <div class="card-body">
            <pre class="bg-light p-3 rounded border" style="font-size:13px;"><code><?= htmlspecialchars(__('admin.webhook_docs.bot_logic_code', "// Pseudocódigo do fluxo do bot

1. Cliente escolhe \"Suporte\" no menu
2. Bot: \"Para identificar sua conta, me informe sua suite. 
         Você encontra em: brazilianashop.com.br/minha-conta\"
3. Cliente: \"16013\"
4. Bot: \"Certo! Descreva seu problema:\"
5. Cliente: \"Meu pedido não chegou\"

6. Bot envia POST /webhook/criar-ticket:
   { \"suite\": \"16013\", \"mensagem\": \"Meu pedido não chegou\" }

7. SE resposta.success == true:
     Bot: \"Ticket #{{ticket_id}} criado! Nossa equipe vai 
           responder em breve. Acompanhe pelo site.\"
   
   SE resposta.error == \"usuario_nao_encontrado\":
     Bot: \"Não encontrei sua conta com essa suite. 
           Me informe seu email cadastrado:\"
     Cliente: \"ana@gmail.com\"
     
     Bot envia POST /webhook/criar-ticket:
     { \"email\": \"ana@gmail.com\", \"mensagem\": \"Meu pedido não chegou\" }
     
     SE resposta.success == true:
       Bot: \"Ticket #{{ticket_id}} criado!\"
     SENÃO:
       Bot: \"Não encontrei sua conta. Entre em contato 
             pelo email suporte@brazilianashop.com.br\""), ENT_QUOTES, 'UTF-8') ?></code></pre>
        </div>
    </div>

    <!-- cURL test -->
    <div class="card mb-4">
        <div class="card-header"><strong><i class="fas fa-terminal me-2"></i><?= htmlspecialchars(__('admin.webhook_docs.curl_title', 'Teste com cURL'), ENT_QUOTES, 'UTF-8') ?></strong></div>
        <div class="card-body">
            <pre class="bg-dark text-light p-3 rounded"><code>curl -X POST https://brazilianashop.com.br/webhook/criar-ticket \
  -H "Content-Type: application/json" \
  -d '{
    "suite": "16013",
    "mensagem": "Teste de criação de ticket via webhook",
    "assunto": "Teste WhatsApp",
    "telefone": "5519998980873"
  }'</code></pre>
        </div>
    </div>

    <!-- Notes -->
    <div class="card mb-4">
        <div class="card-header"><strong><i class="fas fa-sticky-note me-2"></i><?= htmlspecialchars(__('admin.webhook_docs.notes_title', 'Notas Importantes'), ENT_QUOTES, 'UTF-8') ?></strong></div>
        <div class="card-body">
            <ul>
                <li><?= __('admin.webhook_docs.note_1', 'O endpoint <strong>não requer autenticação</strong> (é público para receber webhooks).') ?></li>
                <li><?= __('admin.webhook_docs.note_2', 'A busca por suite é <strong>exata</strong> (case-sensitive).') ?></li>
                <li><?= __('admin.webhook_docs.note_3', 'A busca por email é <strong>exata</strong> (case-insensitive no MySQL).') ?></li>
                <li><?= __('admin.webhook_docs.note_4', 'O ticket é criado com status <code>open</code> e origem <code>whatsapp</code>.') ?></li>
                <li><?= htmlspecialchars(__('admin.webhook_docs.note_5', 'A mensagem do cliente é salva como primeira mensagem do ticket.'), ENT_QUOTES, 'UTF-8') ?></li>
                <li><?= __('admin.webhook_docs.note_6', 'Todas as respostas retornam HTTP 200 — use o campo <code>success</code> para verificar.') ?></li>
                <li><?= __('admin.webhook_docs.note_7', 'O campo <code>error</code> identifica o tipo de erro para tratamento no bot.') ?></li>
            </ul>
        </div>
    </div>
</div>
