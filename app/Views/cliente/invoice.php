<?php ob_start(); ?>

<div class="container py-4" style="max-width: 900px;">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="fas fa-file-invoice me-2"></i>Conferir Invoice - Pedido #<?= $pedido['id'] ?>
        </h1>
        <a href="/meus-pedidos" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left me-2"></i>Voltar
        </a>
    </div>

    <!-- Mensagem Flash -->
    <?php if (!empty($_SESSION['message'])): ?>
        <div class="alert alert-<?= $_SESSION['message_type'] ?? 'info' ?> alert-dismissible fade show">
            <?= htmlspecialchars($_SESSION['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
    <?php endif; ?>

    <div class="alert alert-info">
        <i class="fas fa-info-circle me-2"></i>
        <strong>Confira os dados abaixo.</strong> As informações que você preencher serão usadas na declaração aduaneira da etiqueta de envio. 
        Certifique-se de que os nomes dos produtos estão corretos.
    </div>

    <form method="POST" action="/minha-conta/invoice/finalizar" id="formInvoice">
        <input type="hidden" name="invoice_id" value="<?= $invoice['id'] ?>">
        <input type="hidden" name="pedido_id" value="<?= $pedido['id'] ?>">

        <!-- Dados Pessoais (somente leitura) -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-user me-2"></i>Dados Pessoais</h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="form-label text-muted">Nome</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($usuario['nome'] ?? '') ?>" readonly>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label text-muted">E-mail</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($usuario['email'] ?? '') ?>" readonly>
                    </div>
                </div>
            </div>
        </div>

        <!-- Endereço de Entrega (editável) -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-map-marker-alt me-2"></i>Endereço de Entrega <small class="text-success">(editável)</small></h5>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-8">
                        <label class="form-label">Rua</label>
                        <input type="text" name="endereco[rua]" class="form-control" value="<?= htmlspecialchars($endereco['rua'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Número</label>
                        <input type="text" name="endereco[numero]" class="form-control" value="<?= htmlspecialchars($endereco['numero'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Complemento</label>
                        <input type="text" name="endereco[complemento]" class="form-control" value="<?= htmlspecialchars($endereco['complemento'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Bairro</label>
                        <input type="text" name="endereco[bairro]" class="form-control" value="<?= htmlspecialchars($endereco['bairro'] ?? '') ?>">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">CEP</label>
                        <input type="text" name="endereco[cep]" class="form-control" value="<?= htmlspecialchars($endereco['cep'] ?? '') ?>">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label">Cidade</label>
                        <input type="text" name="endereco[cidade]" class="form-control" value="<?= htmlspecialchars($endereco['cidade'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Estado</label>
                        <input type="text" name="endereco[estado]" class="form-control" value="<?= htmlspecialchars($endereco['estado'] ?? '') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">País</label>
                        <input type="text" name="endereco[pais]" class="form-control" value="<?= htmlspecialchars($endereco['pais'] ?? 'Brasil') ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Itens do Invoice (editáveis) -->
        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0"><i class="fas fa-box-open me-2"></i>Itens para Declaração Aduaneira</h5>
            </div>
            <div class="card-body p-0">
                <?php if (empty($itens)): ?>
                    <p class="text-muted text-center py-4">Nenhum item encontrado.</p>
                <?php else: ?>
                    <?php foreach ($itens as $idx => $item): ?>
                        <?php 
                        // Só mostrar itens de redirecionamento (com pacote_id)
                        if (empty($item['pacote_id'])) continue;
                        ?>
                        <div class="p-3 <?= $idx > 0 ? 'border-top' : '' ?>">
                            <div class="row g-3 align-items-start">
                                <!-- Foto -->
                                <div class="col-auto">
                                    <?php if (!empty($item['foto_url'])): ?>
                                        <img src="<?= htmlspecialchars($item['foto_url']) ?>" alt="" 
                                             style="width:60px;height:60px;object-fit:cover;border-radius:6px;border:1px solid #ddd;">
                                    <?php else: ?>
                                        <div style="width:60px;height:60px;background:#f0f0f0;border-radius:6px;display:flex;align-items:center;justify-content:center;">
                                            <i class="fas fa-box text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <!-- Campos -->
                                <div class="col">
                                    <div class="row g-2">
                                        <!-- Nome do produto (EDITÁVEL - vai na etiqueta) -->
                                        <div class="col-md-6">
                                            <label class="form-label small fw-bold text-primary">
                                                Nome do Produto (vai na etiqueta) *
                                            </label>
                                            <input type="text" name="itens[<?= $item['id'] ?>][nome_produto]" 
                                                   class="form-control" 
                                                   value="<?= htmlspecialchars($item['nome_produto']) ?>" required>
                                        </div>

                                        <!-- Valor Declarado (editável, max = original) -->
                                        <div class="col-md-3">
                                            <label class="form-label small fw-bold">Valor Declarado (USD)</label>
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text">$</span>
                                                <input type="number" name="itens[<?= $item['id'] ?>][declaration_value]" 
                                                       class="form-control" step="0.01" min="0.01"
                                                       max="<?= number_format((float)$item['declaration_value'], 2, '.', '') ?>"
                                                       value="<?= number_format((float)$item['declaration_value'], 2, '.', '') ?>">
                                            </div>
                                            <small class="text-muted">Máx: $<?= number_format((float)$item['declaration_value'], 2) ?></small>
                                        </div>

                                        <!-- Peso e Qtd (readonly) -->
                                        <div class="col-md-3">
                                            <label class="form-label small">Peso / Qtd</label>
                                            <input type="text" class="form-control form-control-sm" 
                                                   value="<?= number_format((float)$item['peso_kg'], 3) ?> kg × <?= $item['quantidade'] ?>" readonly>
                                        </div>

                                        <!-- Bateria -->
                                        <div class="col-md-3">
                                            <label class="form-label small">Contém Bateria?</label>
                                            <select name="itens[<?= $item['id'] ?>][tem_bateria]" class="form-select form-select-sm">
                                                <option value="N" <?= ($item['tem_bateria'] ?? 'N') === 'N' ? 'selected' : '' ?>>Não</option>
                                                <option value="S" <?= ($item['tem_bateria'] ?? 'N') === 'S' ? 'selected' : '' ?>>Sim</option>
                                            </select>
                                        </div>

                                        <!-- Perfume -->
                                        <div class="col-md-3">
                                            <label class="form-label small">Contém Perfume/Líquido?</label>
                                            <select name="itens[<?= $item['id'] ?>][tem_perfume]" class="form-select form-select-sm">
                                                <option value="N" <?= ($item['tem_perfume'] ?? 'N') === 'N' ? 'selected' : '' ?>>Não</option>
                                                <option value="S" <?= ($item['tem_perfume'] ?? 'N') === 'S' ? 'selected' : '' ?>>Sim</option>
                                            </select>
                                        </div>

                                        <!-- NCM (editável) -->
                                        <div class="col-md-6">
                                            <label class="form-label small">NCM</label>
                                            <select name="itens[<?= $item['id'] ?>][ncm]" class="form-select form-select-sm">
                                                <option value="">Selecione...</option>
                                                <?php foreach ($ncmOptions as $ncmCode => $ncmLabel): ?>
                                                    <option value="<?= $ncmCode ?>" <?= (($item['ncm'] ?? '') == $ncmCode) ? 'selected' : '' ?>>
                                                        <?= $ncmCode ?> - <?= $ncmLabel ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Ações -->
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <h6 class="text-danger"><i class="fas fa-exclamation-triangle me-1"></i>Há algo errado?</h6>
                        <p class="small text-muted mb-2">Se os dados estiverem incorretos ou faltando algo, conteste o invoice.</p>
                        <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#modalContestar">
                            <i class="fas fa-times-circle me-2"></i>Contestar Invoice
                        </button>
                    </div>
                    <div class="col-md-6 text-end">
                        <h6 class="text-success"><i class="fas fa-check-circle me-1"></i>Tudo certo?</h6>
                        <p class="small text-muted mb-2">Ao finalizar, esses dados serão usados na etiqueta de envio.</p>
                        <button type="submit" class="btn btn-success btn-lg">
                            <i class="fas fa-check me-2"></i>Finalizar e Confirmar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Modal Contestar -->
<div class="modal fade" id="modalContestar" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="/minha-conta/invoice/contestar">
                <input type="hidden" name="invoice_id" value="<?= $invoice['id'] ?>">
                <input type="hidden" name="pedido_id" value="<?= $pedido['id'] ?>">
                <div class="modal-header">
                    <h5 class="modal-title">Contestar Invoice</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Informe o motivo da contestação. Nossa equipe irá analisar e ajustar os dados.</p>
                    <div class="mb-3">
                        <label class="form-label fw-bold">Motivo *</label>
                        <textarea name="motivo" class="form-control" rows="4" required 
                                  placeholder="Descreva o que está incorreto ou o que precisa ser ajustado..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-times-circle me-2"></i>Enviar Contestação
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
$title = 'Conferir Invoice - Pedido #' . $pedido['id'];
include __DIR__ . '/../layouts/main.php';
?>
