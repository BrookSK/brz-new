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
            <h2 class="fw-bold mb-1">Documentação: Webhook Criar Ticket</h2>
            <p class="text-muted mb-0">Integração WhatsApp → Sistema de Tickets</p>
        </div>
        <a href="/admin" class="btn btn-outline-secondary btn-sm"><i class="fas fa-arrow-left me-1"></i>Voltar</a>
    </div>

    <!-- Configuração da URL de Callback -->
    <div class="card mb-4 border-primary">
        <div class="card-header bg-primary text-white"><strong><i class="fas fa-link me-2"></i>URL de Callback (Resposta)</strong></div>
        <div class="card-body">
            <p class="small text-muted mb-3">Após processar a criação do ticket, o sistema envia a resposta (sucesso ou erro) para esta URL. Configure aqui a URL da sua automação que vai receber o resultado.</p>
            <?php if (!empty($salvoOk)): ?>
                <div class="alert alert-success py-2">URL de callback salva com sucesso!</div>
            <?php endif; ?>
            <?php if (!empty($salvoErro)): ?>
                <div class="alert alert-danger py-2">Erro ao salvar: <?= htmlspecialchars($salvoErro) ?></div>
            <?php endif; ?>
            <form method="POST">
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-globe"></i></span>
                    <input type="url" name="callback_url" class="form-control" value="<?= htmlspecialchars($callbackUrlAtual) ?>" placeholder="https://sua-automacao.com/webhook/resposta-ticket">
                    <button type="submit" class="btn btn-primary"><i class="fas fa-save me-1"></i>Salvar</button>
                </div>
                <div class="form-text">A resposta será enviada via POST com JSON contendo success, ticket_id, error, message, etc.</div>
            </form>
        </div>
    </div>

    <!-- Visão Geral -->
    <div class="card mb-4">
        <div class="card-header"><strong><i class="fas fa-info-circle me-2"></i>Visão Geral</strong></div>
        <div class="card-body">
            <p>Este endpoint permite que automações externas (como bots de WhatsApp) criem tickets de suporte automaticamente no sistema da Braziliana.</p>
            <div class="alert alert-info">
                <strong>URL do Endpoint:</strong><br>
                <code class="fs-6">POST https://brazilianashop.com.br/webhook/criar-ticket</code>
            </div>
            <p><strong>Content-Type:</strong> <code>application/json</code></p>
        </div>
    </div>

    <!-- Fluxo -->
    <div class="card mb-4">
        <div class="card-header"><strong><i class="fas fa-project-diagram me-2"></i>Fluxo de Funcionamento</strong></div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-12">
                    <ol class="list-group list-group-numbered">
                        <li class="list-group-item">Cliente manda mensagem no WhatsApp e escolhe "Suporte"</li>
                        <li class="list-group-item">Bot pergunta a <strong>suite</strong> do cliente</li>
                        <li class="list-group-item">Cliente informa a suite</li>
                        <li class="list-group-item">Bot pergunta qual o problema (mensagem)</li>
                        <li class="list-group-item">Bot envia webhook para <code>/webhook/criar-ticket</code> com suite + mensagem</li>
                        <li class="list-group-item">Sistema busca o usuário pela suite → se encontrar, cria o ticket</li>
                        <li class="list-group-item">
                            <strong>Se não encontrar pela suite:</strong> retorna erro <code>usuario_nao_encontrado</code><br>
                            → Bot pede o <strong>email</strong> do cliente<br>
                            → Bot envia webhook novamente com email + mensagem
                        </li>
                        <li class="list-group-item">Sistema retorna sucesso com o número do ticket</li>
                        <li class="list-group-item">Bot informa ao cliente: "Ticket #XX criado! Aguarde resposta."</li>
                    </ol>
                </div>
            </div>
        </div>
    </div>

    <!-- Payload -->
    <div class="card mb-4">
        <div class="card-header"><strong><i class="fas fa-code me-2"></i>Payload (Corpo da Requisição)</strong></div>
        <div class="card-body">
            <table class="table table-sm table-bordered">
                <thead class="table-light">
                    <tr><th>Campo</th><th>Tipo</th><th>Obrigatório</th><th>Descrição</th></tr>
                </thead>
                <tbody>
                    <tr><td><code>suite</code></td><td>string</td><td>Sim*</td><td>Número da suite do cliente (prioridade na busca)</td></tr>
                    <tr><td><code>email</code></td><td>string</td><td>Sim*</td><td>Email do cliente (usado se suite não encontrar)</td></tr>
                    <tr><td><code>mensagem</code></td><td>string</td><td><strong>Sim</strong></td><td>Descrição do problema relatado pelo cliente</td></tr>
                    <tr><td><code>assunto</code></td><td>string</td><td>Não</td><td>Assunto do ticket (padrão: "Suporte via WhatsApp")</td></tr>
                    <tr><td><code>telefone</code></td><td>string</td><td>Não</td><td>Telefone do cliente (só números, ex: 5519998980873)</td></tr>
                    <tr><td><code>nome</code></td><td>string</td><td>Não</td><td>Nome do cliente (informativo)</td></tr>
                </tbody>
            </table>
            <p class="text-muted small">* Pelo menos um dos dois (<code>suite</code> ou <code>email</code>) deve ser informado.</p>

            <h6 class="mt-4">Exemplo - Busca por Suite:</h6>
            <pre class="bg-dark text-light p-3 rounded"><code>{
  "suite": "16013",
  "nome": "Ana Silva",
  "mensagem": "Meu pedido não chegou, já faz 30 dias",
  "assunto": "Pedido atrasado",
  "telefone": "5519998980873"
}</code></pre>

            <h6 class="mt-4">Exemplo - Busca por Email (quando suite não encontrou):</h6>
            <pre class="bg-dark text-light p-3 rounded"><code>{
  "email": "ana.silva@gmail.com",
  "nome": "Ana Silva",
  "mensagem": "Meu pedido não chegou, já faz 30 dias",
  "assunto": "Pedido atrasado",
  "telefone": "5519998980873"
}</code></pre>

            <h6 class="mt-4">Exemplo - Suite + Email (tenta suite primeiro, fallback email):</h6>
            <pre class="bg-dark text-light p-3 rounded"><code>{
  "suite": "16013",
  "email": "ana.silva@gmail.com",
  "nome": "Ana Silva",
  "mensagem": "Preciso de ajuda com minha compra",
  "telefone": "5519998980873"
}</code></pre>
        </div>
    </div>

    <!-- Respostas -->
    <div class="card mb-4">
        <div class="card-header"><strong><i class="fas fa-reply me-2"></i>Respostas do Webhook</strong></div>
        <div class="card-body">

            <h6 class="text-success"><i class="fas fa-check-circle me-1"></i>Sucesso (HTTP 200)</h6>
            <pre class="bg-dark text-light p-3 rounded"><code>{
  "success": true,
  "ticket_id": 45,
  "message": "Ticket criado com sucesso",
  "usuario_id": 123,
  "usuario_nome": "Ana Silva",
  "usuario_email": "ana.silva@gmail.com"
}</code></pre>

            <hr>

            <h6 class="text-danger"><i class="fas fa-times-circle me-1"></i>Erro: Usuário não encontrado (HTTP 200)</h6>
            <pre class="bg-dark text-light p-3 rounded"><code>{
  "success": false,
  "error": "usuario_nao_encontrado",
  "message": "Nenhum usuário encontrado com a suite 99999",
  "suite_informada": "99999",
  "email_informado": ""
}</code></pre>
            <p class="text-muted small">→ Neste caso, o bot deve pedir o email e enviar novamente.</p>

            <hr>

            <h6 class="text-danger"><i class="fas fa-times-circle me-1"></i>Erro: Mensagem vazia (HTTP 200)</h6>
            <pre class="bg-dark text-light p-3 rounded"><code>{
  "success": false,
  "error": "mensagem_vazia",
  "message": "A mensagem é obrigatória"
}</code></pre>

            <hr>

            <h6 class="text-danger"><i class="fas fa-times-circle me-1"></i>Erro: Sem identificação (HTTP 200)</h6>
            <pre class="bg-dark text-light p-3 rounded"><code>{
  "success": false,
  "error": "identificacao_ausente",
  "message": "Informe a suite ou o email do cliente"
}</code></pre>

            <hr>

            <h6 class="text-danger"><i class="fas fa-times-circle me-1"></i>Erro: Payload inválido (HTTP 200)</h6>
            <pre class="bg-dark text-light p-3 rounded"><code>{
  "success": false,
  "error": "payload_invalido",
  "message": "Payload JSON inválido"
}</code></pre>

            <hr>

            <h6 class="text-warning"><i class="fas fa-exclamation-triangle me-1"></i>Erro interno (HTTP 200)</h6>
            <pre class="bg-dark text-light p-3 rounded"><code>{
  "success": false,
  "error": "erro_interno",
  "message": "Erro ao criar ticket: [detalhes]"
}</code></pre>
        </div>
    </div>

    <!-- Lógica do Bot -->
    <div class="card mb-4">
        <div class="card-header"><strong><i class="fas fa-robot me-2"></i>Lógica para o Bot do WhatsApp</strong></div>
        <div class="card-body">
            <pre class="bg-light p-3 rounded border" style="font-size:13px;"><code>// Pseudocódigo do fluxo do bot

1. Cliente escolhe "Suporte" no menu
2. Bot: "Para identificar sua conta, me informe sua suite. 
         Você encontra em: brazilianashop.com.br/minha-conta"
3. Cliente: "16013"
4. Bot: "Certo! Descreva seu problema:"
5. Cliente: "Meu pedido não chegou"

6. Bot envia POST /webhook/criar-ticket:
   { "suite": "16013", "mensagem": "Meu pedido não chegou" }

7. SE resposta.success == true:
     Bot: "Ticket #{{ticket_id}} criado! Nossa equipe vai 
           responder em breve. Acompanhe pelo site."
   
   SE resposta.error == "usuario_nao_encontrado":
     Bot: "Não encontrei sua conta com essa suite. 
           Me informe seu email cadastrado:"
     Cliente: "ana@gmail.com"
     
     Bot envia POST /webhook/criar-ticket:
     { "email": "ana@gmail.com", "mensagem": "Meu pedido não chegou" }
     
     SE resposta.success == true:
       Bot: "Ticket #{{ticket_id}} criado!"
     SENÃO:
       Bot: "Não encontrei sua conta. Entre em contato 
             pelo email suporte@brazilianashop.com.br"</code></pre>
        </div>
    </div>

    <!-- Teste com cURL -->
    <div class="card mb-4">
        <div class="card-header"><strong><i class="fas fa-terminal me-2"></i>Teste com cURL</strong></div>
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

    <!-- Notas -->
    <div class="card mb-4">
        <div class="card-header"><strong><i class="fas fa-sticky-note me-2"></i>Notas Importantes</strong></div>
        <div class="card-body">
            <ul>
                <li>O endpoint <strong>não requer autenticação</strong> (é público para receber webhooks).</li>
                <li>A busca por suite é <strong>exata</strong> (case-sensitive).</li>
                <li>A busca por email é <strong>exata</strong> (case-insensitive no MySQL).</li>
                <li>O ticket é criado com status <code>open</code> e origem <code>whatsapp</code>.</li>
                <li>A mensagem do cliente é salva como primeira mensagem do ticket.</li>
                <li>Todas as respostas retornam HTTP 200 — use o campo <code>success</code> para verificar.</li>
                <li>O campo <code>error</code> identifica o tipo de erro para tratamento no bot.</li>
            </ul>
        </div>
    </div>
</div>
