<?php ob_start(); ?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="fas fa-box me-2"></i>
            Gerenciamento de Produtos
        </h1>
        <div>
            <a href="/admin" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Voltar
            </a>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalProduto">
                <i class="fas fa-plus me-2"></i>Novo Produto
            </button>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Categoria</label>
                    <select name="categoria_id" class="form-select">
                        <option value="">Todas</option>
                        <?php foreach ($categorias as $categoria): ?>
                            <option value="<?= $categoria['id'] ?>" <?= $categoria_id == $categoria['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($categoria['nome']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">Todos</option>
                        <option value="ativo" <?= $status == 'ativo' ? 'selected' : '' ?>>Ativo</option>
                        <option value="inativo" <?= $status == 'inativo' ? 'selected' : '' ?>>Inativo</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Buscar</label>
                    <input type="text" name="busca" class="form-control" placeholder="Nome ou SKU" value="<?= $busca ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-2"></i>Filtrar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Lista de Produtos -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Imagem</th>
                            <th>Nome</th>
                            <th>SKU</th>
                            <th>Categoria</th>
                            <th>Preço</th>
                            <th>Estoque</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($produtos)): ?>
                            <tr>
                                <td colspan="9" class="text-center">Nenhum produto encontrado.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($produtos as $produto): ?>
                                <tr>
                                    <td><?= $produto['id'] ?></td>
                                    <td>
                                        <?php 
                                        $fotoUrl = '';
                                        if (!empty($produto['foto_principal'])) {
                                            $fotoUrl = '/uploads/produtos/' . $produto['foto_principal'];
                                        } else {
                                            $fotoUrl = '/uploads/produtos/placeholder.svg';
                                        }
                                        ?>
                                        <img src="<?= $fotoUrl ?>" 
                                             alt="<?= htmlspecialchars($produto['nome']) ?>"
                                             class="img-thumbnail"
                                             style="width: 60px; height: 60px; object-fit: cover;"
                                             onerror="this.src='/uploads/produtos/placeholder.svg'">
                                    </td>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($produto['nome']) ?></strong>
                                            <small class="text-muted d-block">SKU: <?= htmlspecialchars($produto['sku']) ?></small>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($produto['sku']) ?></td>
                                    <td><?= htmlspecialchars($produto['categoria_nome']) ?></td>
                                    <td>
                                        <span class="badge bg-primary product-price" data-original-value="<?= $produto['valor'] ?>">$ <?= number_format($produto['valor'], 2, '.', ',') ?> USD</span>
                                        <?php if ($produto['moeda'] === 'BRL'): ?>
                                        <br><small class="text-warning">⚠️ Moeda incorreta: BRL</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= $produto['estoque'] > 0 ? 'success' : 'danger' ?>">
                                            <?= $produto['estoque'] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?= $produto['status'] == 'ativo' ? 'success' : 'secondary' ?>">
                                            <?= ucfirst($produto['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="/admin/editar-produto/<?= $produto['id'] ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <button type="button" class="btn btn-sm btn-outline-success" onclick="alterarStatus(<?= $produto['id'] ?>)">
                                                <i class="fas fa-<?= $produto['status'] == 'ativo' ? 'ban' : 'check' ?>"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-info" onclick="gerenciarImagens(<?= $produto['id'] ?>)">
                                                <i class="fas fa-images"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="excluirProduto(<?= $produto['id'] ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Paginação -->
            <?php if ($total_paginas > 1): ?>
                <nav aria-label="Paginação">
                    <ul class="pagination justify-content-center">
                        <?php for ($i = 1; $i <= $total_paginas; $i++): ?>
                            <li class="page-item <?= $i == $pagina ? 'active' : '' ?>">
                                <a class="page-link" href="/admin/produtos?pagina=<?= $i ?><?= $categoria_id ? '&categoria_id=' . $categoria_id : '' ?><?= $status ? '&status=' . $status : '' ?><?= $busca ? '&busca=' . urlencode($busca) : '' ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Produto -->
<div class="modal fade" id="modalProduto" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalProdutoTitle">Novo Produto</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formProduto" enctype="multipart/form-data">
                    <input type="hidden" name="id" id="produto_id">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nome *</label>
                            <input type="text" name="nome" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">SKU *</label>
                            <input type="text" name="sku" class="form-control" required>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Descrição *</label>
                            <textarea name="descricao_curta" class="form-control" rows="3" required placeholder="Descreva o produto brevemente"></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Categoria *</label>
                            <select name="categoria_id" class="form-select" required>
                                <option value="">Selecione...</option>
                                <?php foreach ($categorias as $categoria): ?>
                                    <option value="<?= $categoria['id'] ?>"><?= htmlspecialchars($categoria['nome']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Valor (USD) *</label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" name="valor" class="form-control" step="0.01" placeholder="0.00" required>
                                <span class="input-group-text">USD</span>
                            </div>
                            <small class="text-muted">Todos os produtos devem ser cadastrados em Dólar Americano (USD)</small>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Moeda</label>
                            <select name="moeda" class="form-select" required>
                                <option value="USD" selected>Dólar Americano (USD) - Padrão</option>
                                <option value="BRL" disabled>Real Brasileiro (BRL) - Desativado</option>
                            </select>
                            <small class="text-muted">Moeda padrão fixada em USD para todos os produtos</small>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Peso (kg)</label>
                            <input type="number" name="peso" class="form-control" step="0.001" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Estoque</label>
                            <input type="number" name="estoque" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select" required>
                                <option value="ativo">Ativo</option>
                                <option value="inativo">Inativo</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Imagem Principal</label>
                            <input type="file" name="imagem_principal" class="form-control" accept="image/jpeg,image/jpg,image/png,image/webp">
                            <small class="text-muted">Formatos aceitos: JPEG, JPG, PNG, WebP (Máx: 5MB)</small>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Imagens Adicionais</label>
                            <input type="file" name="imagens[]" class="form-control" accept="image/jpeg,image/jpg,image/png,image/webp" multiple>
                            <small class="text-muted">Formatos aceitos: JPEG, JPG, PNG, WebP (Máx: 5MB por imagem)</small>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="salvarProduto()">Salvar</button>
            </div>
        </div>
    </div>
</div>

<script>
function editarProduto(id) {
    console.log('🔍 [PRODUTOS] editarProduto() chamada com ID:', id);
    
    // Limpar formulário
    document.getElementById('formProduto').reset();
    document.getElementById('produto_id').value = '';
    document.getElementById('modalProdutoTitle').textContent = 'Carregando...';
    
    fetch(`/admin/produto/${id}`)
        .then(response => response.json())
        .then(data => {
            console.log('🔍 [PRODUTOS] Dados recebidos:', data);
            
            if (data.success && data.produto) {
                // Preencher o modal
                document.getElementById('modalProdutoTitle').textContent = 'Editar Produto';
                document.getElementById('produto_id').value = data.produto.id || '';
                
                // Preencher campos com validação robusta
                const campos = {
                    'nome': data.produto.nome || '',
                    'sku': data.produto.sku || '',
                    'descricao_curta': data.produto.descricao_curta || '',
                    'categoria_id': data.produto.categoria_id || '',
                    'valor': data.produto.valor || '',
                    'moeda': data.produto.moeda || 'USD',
                    'peso': data.produto.peso || '',
                    'estoque': data.produto.estoque || '',
                    'status': data.produto.status || 'ativo'
                };
                
                console.log('🔍 [PRODUTOS] Campos a preencher:', campos);
                
                // Preencher cada campo
                Object.keys(campos).forEach(campo => {
                    const elemento = document.querySelector(`[name="${campo}"]`);
                    if (elemento) {
                        elemento.value = campos[campo];
                        console.log(`🔍 [PRODUTOS] Campo ${campo} preenchido com:`, campos[campo]);
                        
                        // Forçar atualização visual
                        elemento.dispatchEvent(new Event('input', { bubbles: true }));
                        elemento.dispatchEvent(new Event('change', { bubbles: true }));
                    } else {
                        console.warn(`🔍 [PRODUTOS] Campo ${campo} não encontrado`);
                    }
                });
                
                // Forçar moeda USD
                const moedaSelect = document.querySelector('select[name="moeda"]');
                if (moedaSelect) {
                    moedaSelect.value = 'USD';
                    moedaSelect.dispatchEvent(new Event('change', { bubbles: true }));
                }
                
                // Pequeno delay para garantir que os campos foram preenchidos antes de abrir o modal
                setTimeout(() => {
                    // Abrir modal
                    const modalElement = document.getElementById('modalProduto');
                    const modal = new bootstrap.Modal(modalElement);
                    
                    // Remover aria-hidden temporariamente
                    modalElement.removeAttribute('aria-hidden');
                    
                    modal.show();
                    
                    console.log('🔍 [PRODUTOS] Modal aberto com sucesso');
                }, 100);
                
            } else {
                alert('Erro ao carregar produto: ' + (data.error || 'Dados não encontrados'));
            }
        })
        .catch(error => {
            console.error('❌ [PRODUTOS] Erro:', error);
            alert('Erro ao carregar produto');
        });
}

function alterarStatus(id) {
    if (confirm('Deseja realmente alterar o status deste produto?')) {
        fetch(`/admin/alterar-status-produto/${id}`, {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Status alterado com sucesso!');
                location.reload();
            } else {
                alert('Erro ao alterar status: ' + data.error);
            }
        });
    }
}

function excluirProduto(id) {
    if (confirm('Deseja realmente excluir este produto? Esta ação não pode ser desfeita!')) {
        fetch(`/admin/excluir-produto/${id}`, {
            method: 'DELETE'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Produto excluído com sucesso!');
                location.reload();
            } else {
                alert('Erro ao excluir produto: ' + data.error);
            }
        });
    }
}

function gerenciarImagens(id) {
    window.open(`/admin/gerenciar-imagens/${id}`, '_blank');
}

// Função para atualizar preços com base na moeda - CORRIGIDO PARA USD ORIGINAL
function updateProductPrices(currency) {
    console.log('🔍 [PRODUTOS] updateProductPrices() chamada com currency:', currency);
    
    const currencySymbol = currency === 'BRL' ? 'R$' : '$';
    const rate = window.exchangeRates ? window.exchangeRates[currency] : 1;
    
    console.log('🔍 [PRODUTOS] currencySymbol:', currencySymbol);
    console.log('🔍 [PRODUTOS] rate:', rate);
    console.log('🔍 [PRODUTOS] window.exchangeRates:', window.exchangeRates);
    
    // DEBUG: Mostrar que o valor original está em USD
    console.log('🔍 [PRODUTOS] VALOR ORIGINAL EM USD');
    console.log('🔍 [PRODUTOS] - Se currency = BRL: USD × 5.5 = BRL');
    console.log('🔍 [PRODUTOS] - Se currency = USD: USD × 1 = USD (sem conversão)');
    
    if (!rate) {
        console.error('❌ [PRODUTOS] Taxa de conversão não encontrada para:', currency);
        console.error('❌ [PRODUTOS] Taxas disponíveis:', window.exchangeRates);
        return;
    }
    
    // Verificar se a tabela existe
    const table = document.querySelector('table');
    console.log('🔍 [PRODUTOS] Tabela encontrada:', !!table);
    
    if (table) {
        const rows = table.querySelectorAll('tbody tr');
        console.log('🔍 [PRODUTOS] Linhas na tabela:', rows.length);
    }
    
    // Atualizar todos os preços de produtos - VALOR ORIGINAL EM USD
    const productPrices = document.querySelectorAll('.product-price');
    console.log('🔍 [PRODUTOS] Preços de produtos encontrados:', productPrices.length);
    
    // Se não encontrar com a classe, tentar encontrar spans com preço
    if (productPrices.length === 0) {
        console.log('🔍 [PRODUTOS] Tentando encontrar spans com preço...');
        const allSpans = document.querySelectorAll('span');
        console.log('🔍 [PRODUTOS] Total de spans na página:', allSpans.length);
        
        allSpans.forEach((span, index) => {
            const text = span.textContent.trim();
            if (text.includes('R$') || text.includes('$')) {
                const originalValue = parseFloat(span.getAttribute('data-original-value'));
                console.log(`🔍 [PRODUTOS] Span ${index} com preço:`, text, 'data-original-value:', span.getAttribute('data-original-value'));
                
                // LÓGICA CORRETA: valor original em USD
                if (!isNaN(originalValue)) {
                    let convertedPrice;
                    
                    if (currency === 'BRL') {
                        // Converter USD para BRL: multiplicar pela taxa
                        convertedPrice = originalValue * rate;
                        console.log(`🔍 [PRODUTOS] Convertendo USD para BRL: ${originalValue} × ${rate} = ${convertedPrice}`);
                    } else {
                        // Manter em USD: sem conversão
                        convertedPrice = originalValue;
                        console.log(`🔍 [PRODUTOS] Mantendo USD: ${originalValue} (sem conversão)`);
                    }
                    
                    const formattedPrice = currencySymbol + ' ' + convertedPrice.toFixed(2).replace('.', ',');
                    span.textContent = formattedPrice;
                    console.log(`🔍 [PRODUTOS] Span ${index} convertido:`, formattedPrice);
                }
            }
        });
    } else {
        productPrices.forEach((element, index) => {
            if (element) {
                const originalValue = parseFloat(element.getAttribute('data-original-value'));
                console.log(`🔍 [PRODUTOS] Produto ${index} - Valor original (USD):`, originalValue);
                
                // LÓGICA CORRETA: valor original em USD
                if (!isNaN(originalValue)) {
                    let convertedPrice;
                    
                    if (currency === 'BRL') {
                        // Converter USD para BRL: multiplicar pela taxa
                        convertedPrice = originalValue * rate;
                        console.log(`🔍 [PRODUTOS] Convertendo USD para BRL: ${originalValue} × ${rate} = ${convertedPrice}`);
                    } else {
                        // Manter em USD: sem conversão
                        convertedPrice = originalValue;
                        console.log(`🔍 [PRODUTOS] Mantendo USD: ${originalValue} (sem conversão)`);
                    }
                    
                    const formattedPrice = currencySymbol + ' ' + convertedPrice.toFixed(2).replace('.', ',');
                    element.textContent = formattedPrice;
                    console.log(`🔍 [PRODUTOS] Produto ${index} - Valor convertido:`, formattedPrice);
                } else {
                    console.error(`❌ [PRODUTOS] Produto ${index} - Valor original inválido:`, element.getAttribute('data-original-value'));
                }
            }
        });
    }
    
    console.log('🔍 [PRODUTOS] updateProductPrices() concluída');
    console.log('🔍 [PRODUTOS] LÓGICA: valor_original_USD × rate (para BRL) ou valor_original_USD (para USD)');
}

// Função global para atualizar preços de produtos
window.updateProductPrices = updateProductPrices;

function salvarProduto() {
    const form = document.getElementById('formProduto');
    const formData = new FormData(form);
    
    // FORÇAR MOEDA USD SEMPRE
    formData.set('moeda', 'USD');
    console.log('🔍 [PRODUTOS] Moeda forçada para USD no salvamento');
    
    // Verificar se é edição ou criação
    const produtoId = formData.get('id');
    const isEdicao = produtoId && produtoId !== '';
    
    const url = isEdicao ? `/admin/atualizar-produto/${produtoId}` : '/admin/salvar-produto';
    
    // Validar campos obrigatórios
    const nome = formData.get('nome');
    const sku = formData.get('sku');
    const descricao_curta = formData.get('descricao_curta');
    const valor = parseFloat(formData.get('valor'));
    const categoriaId = formData.get('categoria_id');
    const peso = parseFloat(formData.get('peso'));
    const estoque = parseInt(formData.get('estoque'));
    
    // Validação robusta
    if (!nome || nome.trim() === '') {
        alert('Por favor, informe o nome do produto!');
        return;
    }
    
    if (!sku || sku.trim() === '') {
        alert('Por favor, informe o SKU do produto!');
        return;
    }
    
    if (sku.trim().length < 3) {
        alert('O SKU deve ter pelo menos 3 caracteres!');
        return;
    }
    
    if (!descricao_curta || descricao_curta.trim() === '') {
        alert('Por favor, informe a descrição do produto!');
        return;
    }
    
    if (!categoriaId || categoriaId === '') {
        alert('Por favor, selecione uma categoria!');
        return;
    }
    
    if (isNaN(valor) || valor <= 0) {
        alert('Por favor, informe um valor válido em USD!');
        return;
    }
    
    if (isNaN(peso) || peso < 0) {
        alert('Por favor, informe um peso válido!');
        return;
    }
    
    if (isNaN(estoque) || estoque < 0) {
        alert('Por favor, informe um estoque válido!');
        return;
    }
    
    // Limpar e formatar dados
    formData.set('nome', nome.trim());
    formData.set('sku', sku.trim().toUpperCase());
    formData.set('descricao_curta', descricao_curta.trim());
    formData.set('valor', valor.toFixed(2));
    formData.set('peso', peso.toFixed(3));
    formData.set('estoque', estoque);
    
    console.log('🔍 [PRODUTOS] Dados finais antes de enviar:');
    for (let [key, value] of formData.entries()) {
        console.log(`  ${key}: ${value}`);
    }
    
    console.log('🔍 [PRODUTOS] Salvando produto - Edição:', isEdicao, 'ID:', produtoId, 'Valor USD:', valor);
    
    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('🔍 [PRODUTOS] Status da resposta:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('🔍 [PRODUTOS] Resposta do servidor:', data);
        
        if (data.success) {
            const mensagem = isEdicao ? 
                'Produto atualizado com sucesso em USD!' : 
                'Produto criado com sucesso em USD!';
            alert(mensagem);
            
            // Fechar modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('modalProduto'));
            if (modal) {
                modal.hide();
            }
            
            // Recarregar página para mostrar alterações
            location.reload();
        } else {
            console.error('❌ [PRODUTOS] Erro do servidor:', data.error);
            alert('Erro ao salvar produto: ' + data.error);
        }
    })
    .catch(error => {
        console.error('🔍 [PRODUTOS] Erro ao salvar produto:', error);
        alert('Erro ao salvar produto. Verifique o console para mais detalhes.');
    });
}

// Função para resetar formulário e garantir USD
function resetarFormularioProduto() {
    document.getElementById('formProduto').reset();
    document.getElementById('modalProdutoTitle').textContent = 'Novo Produto';
    document.getElementById('produto_id').value = '';
    
    // Forçar moeda USD para novos produtos
    const moedaSelect = document.querySelector('select[name="moeda"]');
    if (moedaSelect) {
        moedaSelect.value = 'USD';
    }
    
    console.log('🔍 [PRODUTOS] Formulário resetado com moeda USD');
}

// Event listener para quando o modal de produto for aberto
document.addEventListener('DOMContentLoaded', function() {
    const modalProduto = document.getElementById('modalProduto');
    if (modalProduto) {
        modalProduto.addEventListener('show.bs.modal', function (event) {
            const button = event.relatedTarget;
            
            // Se não for um botão de edição, é um novo produto
            if (!button || !button.getAttribute('onclick') || !button.getAttribute('onclick').includes('editarProduto')) {
                resetarFormularioProduto();
            }
        });
    }
});

</script>

<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/admin.php'; ?>
