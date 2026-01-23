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

                <!-- Tab Entrega -->
                <div class="tab-pane fade" id="entrega" role="tabpanel">
                    <form method="POST" id="formEntrega">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Tempo de Processamento (dias)</label>
                                <input type="number" name="tempo_processamento" class="form-control" value="<?= $configuracoes['tempo_processamento'] ?? '15' ?>" min="1">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Tempo de Entrega (dias)</label>
                                <input type="number" name="tempo_entrega" class="form-control" value="<?= $configuracoes['tempo_entrega'] ?? '30' ?>" min="1">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Taxa de Frete por kg (USD)</label>
                                <input type="number" name="taxa_frete_kg" class="form-control" step="0.01" value="<?= $configuracoes['taxa_frete_kg'] ?? '15.00' ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Taxa de Serviço por kg (USD)</label>
                                <input type="number" name="taxa_servico_kg" class="form-control" step="0.01" value="<?= $configuracoes['taxa_servico_kg'] ?? '39.00' ?>">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Percentual de Impostos (%)</label>
                                <input type="number" name="percentual_impostos" class="form-control" value="<?= $configuracoes['percentual_impostos'] ?? '80' ?>" min="0" max="100">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Peso máximo por pedido (kg)</label>
                                <input type="number" name="peso_maximo_pedido" class="form-control" value="<?= $configuracoes['peso_maximo_pedido'] ?? '30' ?>" min="1">
                            </div>
                            <div class="col-12">
                                <label class="form-label">Correios Disponíveis</label>
                                <textarea name="correios_disponiveis" class="form-control" rows="3" placeholder="Um por linha: CEP - Nome da cidade/UF"><?= $configuracoes['correios_disponiveis'] ?? '' ?></textarea>
                            </div>
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-2"></i>Salvar Configurações
                                </button>
                            </div>
                        </div>
                    </form>
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
