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
                                        <?php if ($produto['foto_principal']): ?>
                                            <img src="/uploads/produtos/<?= $produto['foto_principal'] ?>" alt="<?= htmlspecialchars($produto['nome']) ?>" style="width: 50px; height: 50px; object-fit: cover;">
                                        <?php else: ?>
                                            <img src="https://via.placeholder.com/50x50?text=Sem+Imagem" alt="<?= htmlspecialchars($produto['nome']) ?>" style="width: 50px; height: 50px; object-fit: cover;">
                                        <?php endif; ?>
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
                                        <span class="badge bg-success product-price" data-original-value="<?= $produto['valor'] ?>">R$ <?= number_format($produto['valor'], 2, ',', '.') ?></span>
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
                                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="editarProduto(<?= $produto['id'] ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
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
            <?php if ($totalPaginas > 1): ?>
                <nav aria-label="Paginação">
                    <ul class="pagination justify-content-center">
                        <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                            <li class="page-item <?= $i == $pagina ? 'active' : '' ?>>
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
                            <label class="form-label">Descrição Curta *</label>
                            <textarea name="descricao_curta" class="form-control" rows="3" required></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Descrição Completa</label>
                            <textarea name="descricao_completa" class="form-control" rows="5"></textarea>
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
                            <label class="form-label">Valor *</label>
                            <div class="input-group">
                                <span class="input-group-text" id="valor-currency">R$</span>
                                <input type="number" name="valor" class="form-control" step="0.01" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Moeda</label>
                            <select name="moeda" class="form-select">
                                <option value="BRL">Real Brasileiro (BRL)</option>
                                <option value="USD">Dólar Americano (USD)</option>
                            </select>
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
                            <input type="file" name="imagem_principal" class="form-control" accept="image/*">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Imagens Adicionais</label>
                            <input type="file" name="imagens[]" class="form-control" accept="image/*" multiple>
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
    
    fetch(`/admin/produto/${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                console.log('🔍 [PRODUTOS] Dados do produto recebidos:', data.produto);
                
                // Preencher o modal
                document.getElementById('modalProdutoTitle').textContent = 'Editar Produto';
                document.getElementById('produto_id').value = data.produto.id;
                document.querySelector('input[name="nome"]').value = data.produto.nome || '';
                document.querySelector('input[name="sku"]').value = data.produto.sku || '';
                document.querySelector('textarea[name="descricao_curta"]').value = data.produto.descricao_curta || '';
                document.querySelector('textarea[name="descricao_completa"]').value = data.produto.descricao_completa || '';
                document.querySelector('select[name="categoria_id"]').value = data.produto.categoria_id;
                document.querySelector('input[name="valor"]').value = data.produto.valor || '';
                document.querySelector('select[name="moeda"]').value = data.produto.moeda || 'BRL';
                document.querySelector('input[name="peso"]').value = data.produto.peso || '';
                document.querySelector('input[name="estoque"]').value = data.produto.estoque || '';
                document.querySelector('select[name="status"]').value = data.produto.status || 'ativo';
                
                // Atualizar o símbolo da moeda no campo de valor
                const valorCurrency = document.getElementById('valor-currency');
                if (valorCurrency) {
                    valorCurrency.textContent = data.produto.moeda === 'USD' ? '$' : 'R$';
                    console.log('🔍 [PRODUTOS] Símbolo da moeda atualizado para:', valorCurrency.textContent);
                }
                
                console.log('🔍 [PRODUTOS] Modal preenchido com sucesso');
                new bootstrap.Modal(document.getElementById('modalProduto')).show();
            } else {
                console.error('❌ [PRODUTOS] Erro ao carregar produto:', data.error);
                alert('Erro ao carregar produto: ' + data.error);
            }
        })
        .catch(error => {
            console.error('❌ [PRODUTOS] Erro na requisição:', error);
            alert('Erro ao carregar produto. Verifique o console para mais detalhes.');
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

// Função para atualizar preços com base na moeda
function updateProductPrices(currency) {
    console.log('🔍 [PRODUTOS] updateProductPrices() chamada com currency:', currency);
    
    const currencySymbol = currency === 'BRL' ? 'R$' : '$';
    const rate = window.exchangeRates ? window.exchangeRates[currency] : 1;
    
    console.log('🔍 [PRODUTOS] currencySymbol:', currencySymbol);
    console.log('🔍 [PRODUTOS] rate:', rate);
    console.log('🔍 [PRODUTOS] window.exchangeRates:', window.exchangeRates);
    
    // DEBUG: Mostrar comparação com o layout principal
    console.log('🔍 [COMPARAÇÃO] LÓGICA DE CONVERSÃO:');
    console.log('🔍 [COMPARAÇÃO] - PRODUTOS (admin/produtos.php): valor_original × rate');
    console.log('🔍 [COMPARAÇÃO] - LAYOUT PRINCIPAL (layouts/main.php): price / 1 × rate');
    console.log('🔍 [COMPARAÇÃO] - CHECKOUT (checkout/index.php): valor_original × rate');
    console.log('🔍 [COMPARAÇÃO] - CARRINHO (layouts/main.php): data-original-price × rate');
    
    // DEBUG: Mostrar detalhes da conta
    console.log('🔍 [DEBUG] CONTA DA CONVERSÃO:');
    console.log('🔍 [DEBUG] - Moeda alvo:', currency);
    console.log('🔍 [DEBUG] - Símbolo:', currencySymbol);
    console.log('🔍 [DEBUG] - Taxa aplicada:', rate);
    console.log('🔍 [DEBUG] - Taxa original (BRL):', window.exchangeRates ? window.exchangeRates.BRL : 'N/A');
    console.log('🔍 [DEBUG] - Taxa original (USD):', window.exchangeRates ? window.exchangeRates.USD : 'N/A');
    console.log('🔍 [DEBUG] - Fórmula PRODUTOS: valor_original × rate = valor_convertido');
    console.log('🔍 [DEBUG] - Fórmula LAYOUT: (price / 1) × rate = valor_convertido');
    console.log('🔍 [DEBUG] - Fórmula CHECKOUT: valor_original × rate = valor_convertido');
    console.log('🔍 [DEBUG] - Fórmula CARRINHO: data-original-price × rate = valor_convertido');
    
    if (!rate) {
        console.error('❌ [PRODUTOS] Taxa de conversão não encontrada para:', currency);
        console.error('❌ [DEBUG] Taxas disponíveis:', window.exchangeRates);
        return;
    }
    
    // Verificar se a tabela existe
    const table = document.querySelector('table');
    console.log('🔍 [PRODUTOS] Tabela encontrada:', !!table);
    
    if (table) {
        const rows = table.querySelectorAll('tbody tr');
        console.log('🔍 [PRODUTOS] Linhas na tabela:', rows.length);
        
        rows.forEach((row, index) => {
            const cells = row.querySelectorAll('td');
            console.log(`🔍 [PRODUTOS] Linha ${index} - Células:`, cells.length);
            
            cells.forEach((cell, cellIndex) => {
                const text = cell.textContent.trim();
                if (text.includes('R$') || text.includes('$')) {
                    console.log(`🔍 [PRODUTOS] Linha ${index}, Célula ${cellIndex}:`, text, 'HTML:', cell.innerHTML);
                }
            });
        });
    }
    
    // Atualizar todos os preços de produtos - MESMA LÓGICA DO CHECKOUT
    const productPrices = document.querySelectorAll('.product-price');
    console.log('🔍 [PRODUTOS] Preços de produtos encontrados:', productPrices.length);
    console.log('🔍 [PRODUTOS] Elementos encontrados:', productPrices);
    
    // Se não encontrar com a classe, tentar encontrar spans com preço
    if (productPrices.length === 0) {
        console.log('🔍 [PRODUTOS] Tentando encontrar spans com preço...');
        const allSpans = document.querySelectorAll('span');
        console.log('🔍 [PRODUTOS] Total de spans na página:', allSpans.length);
        
        allSpans.forEach((span, index) => {
            const text = span.textContent.trim();
            if (text.includes('R$') || text.includes('$')) {
                const originalValue = parseFloat(span.getAttribute('data-original-value'));
                console.log(`🔍 [PRODUTOS] Span ${index} com preço:`, text, 'classe:', span.className, 'data-original-value:', span.getAttribute('data-original-value'));
                
                // DEBUG: Mostrar conta antes da conversão
                console.log(`🔍 [DEBUG] CONTA DO PRODUTO ${index}:`);
                console.log(`🔍 [DEBUG] - Valor original:`, originalValue);
                console.log(`🔍 [DEBUG] - Taxa: ${rate}`);
                console.log(`🔍 [DEBUG] - Conta PRODUTOS: ${originalValue} × ${rate} = ${originalValue * rate}`);
                console.log(`🔍 [DEBUG] - Conta LAYOUT: (${originalValue} / 1) × ${rate} = ${(originalValue / 1) * rate}`);
                console.log(`🔍 [DEBUG] - Moeda alvo: ${currency}`);
                
                // Tentar converter mesmo sem a classe
                if (!isNaN(originalValue)) {
                    // LÓGICA IDÊNTICA AO CHECKOUT: sempre multiplica pela taxa
                    const convertedPrice = originalValue * rate;
                    const formattedPrice = currencySymbol + ' ' + convertedPrice.toFixed(2).replace('.', ',');
                    span.textContent = formattedPrice;
                    console.log(`🔍 [PRODUTOS] Span ${index} convertido:`, formattedPrice);
                    console.log(`🔍 [DEBUG] RESULTADO FINAL PRODUTOS: ${formattedPrice}`);
                    
                    // DEBUG: Mostrar como seria com a lógica do layout
                    const layoutPrice = (originalValue / 1) * rate;
                    const layoutFormatted = currencySymbol + ' ' + layoutPrice.toFixed(2).replace('.', ',');
                    console.log(`🔍 [DEBUG] RESULTADO LAYOUT: ${layoutFormatted}`);
                } else {
                    console.error(`❌ [DEBUG] ERRO: Valor original inválido:`, originalValue);
                }
            }
        });
    } else {
        productPrices.forEach((element, index) => {
            if (element) {
                const originalValue = parseFloat(element.getAttribute('data-original-value'));
                console.log(`🔍 [PRODUTOS] Produto ${index} - Valor original:`, originalValue);
                
                // DEBUG: Mostrar conta antes da conversão
                console.log(`🔍 [DEBUG] CONTA DO PRODUTO ${index}:`);
                console.log(`🔍 [DEBUG] - Valor original:`, originalValue);
                console.log(`🔍 [DEBUG] - Taxa: ${rate}`);
                console.log(`🔍 [DEBUG] - Conta PRODUTOS: ${originalValue} × ${rate} = ${originalValue * rate}`);
                console.log(`🔍 [DEBUG] - Conta LAYOUT: (${originalValue} / 1) × ${rate} = ${(originalValue / 1) * rate}`);
                console.log(`🔍 [DEBUG] - Moeda alvo: ${currency}`);
                
                if (!isNaN(originalValue)) {
                    // LÓGICA IDÊNTICA AO CHECKOUT: sempre multiplica pela taxa
                    const convertedPrice = originalValue * rate;
                    const formattedPrice = currencySymbol + ' ' + convertedPrice.toFixed(2).replace('.', ',');
                    element.textContent = formattedPrice;
                    console.log(`🔍 [PRODUTOS] Produto ${index} - Valor convertido:`, formattedPrice);
                    console.log(`🔍 [DEBUG] RESULTADO FINAL PRODUTOS: ${formattedPrice}`);
                    
                    // DEBUG: Mostrar como seria com a lógica do layout
                    const layoutPrice = (originalValue / 1) * rate;
                    const layoutFormatted = currencySymbol + ' ' + layoutPrice.toFixed(2).replace('.', ',');
                    console.log(`🔍 [DEBUG] RESULTADO LAYOUT: ${layoutFormatted}`);
                } else {
                    console.error(`❌ [DEBUG] ERRO: Valor original inválido:`, element.getAttribute('data-original-value'));
                }
            }
        });
    }
    
    console.log('🔍 [PRODUTOS] updateProductPrices() concluída');
    console.log('🔍 [DEBUG] RESUMO DA CONVERSÃO:');
    console.log(`🔍 [DEBUG] - Moeda: ${currency}`);
    console.log(`🔍 [DEBUG] - Taxa: ${rate}`);
    console.log(`🔍 [DEBUG] - Produtos processados: ${productPrices.length > 0 ? productPrices.length : document.querySelectorAll('span').length}`);
    console.log(`🔍 [DEBUG] - LÓGICA USADA: valor_original × rate (igual ao checkout)`);
}

// Verificar mudanças na moeda do header
setInterval(function() {
    const headerCurrency = document.getElementById('current-currency');
    if (headerCurrency) {
        const newCurrency = headerCurrency.textContent;
        
        // Verificar se a moeda mudou
        if (typeof window.lastCurrency === 'undefined' || window.lastCurrency !== newCurrency) {
            window.lastCurrency = newCurrency;
            console.log('🔍 [PRODUTOS] Moeda mudou para:', newCurrency);
            updateProductPrices(newCurrency);
        }
    }
}, 200);

// Inicializar com a moeda atual
document.addEventListener('DOMContentLoaded', function() {
    console.log('🔍 [PRODUTOS] DOMContentLoaded iniciado');
    
    const headerCurrency = document.getElementById('current-currency');
    if (headerCurrency) {
        const currentCurrency = headerCurrency.textContent;
        console.log('🔍 [PRODUTOS] Moeda inicial:', currentCurrency);
        
        // Definir taxas de conversão se não existirem
        if (typeof window.exchangeRates === 'undefined') {
            window.exchangeRates = {
                'BRL': 5.50,
                'USD': 1.00
            };
            console.log('🔍 [PRODUTOS] Taxas de conversão definidas:', window.exchangeRates);
        }
        
        // Teste manual da função
        console.log('🔍 [PRODUTOS] Chamando updateProductPrices manualmente...');
        updateProductPrices(currentCurrency);
        
        // Teste após 1 segundo para garantir que o DOM está pronto
        setTimeout(function() {
            console.log('🔍 [PRODUTOS] Chamando updateProductPrices após 1 segundo...');
            updateProductPrices(currentCurrency);
        }, 1000);
    } else {
        console.error('❌ [PRODUTOS] Elemento current-currency não encontrado');
    }
});

// Função global para atualizar preços de produtos
window.updateProductPrices = updateProductPrices;

function salvarProduto() {
    const form = document.getElementById('formProduto');
    const formData = new FormData(form);
    
    const id = formData.get('id');
    const url = id ? `/admin/atualizar-produto/${id}` : '/admin/salvar-produto';
    
    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Produto salvo com sucesso!');
            location.reload();
        } else {
            alert('Erro ao salvar produto: ' + data.error);
        }
    });
}
</script>

<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/admin.php'; ?>
