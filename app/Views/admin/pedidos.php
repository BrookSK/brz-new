<?php ob_start(); ?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="fas fa-shopping-cart me-2"></i>
            Gerenciamento de Pedidos
        </h1>
        <div>
            <a href="/admin" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i>Voltar
            </a>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCriarPedido">
                <i class="fas fa-plus me-2"></i>Novo Pedido
            </button>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="">Todos</option>
                        <option value="pendente" <?= $status == 'pendente' ? 'selected' : '' ?>>Pendente</option>
                        <option value="pago" <?= $status == 'pago' ? 'selected' : '' ?>>Pago</option>
                        <option value="processando" <?= $status == 'processando' ? 'selected' : '' ?>>Processando</option>
                        <option value="enviado" <?= $status == 'enviado' ? 'selected' : '' ?>>Enviado</option>
                        <option value="entregue" <?= $status == 'entregue' ? 'selected' : '' ?>>Entregue</option>
                        <option value="cancelado" <?= $status == 'cancelado' ? 'selected' : '' ?>>Cancelado</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Data Inicial</label>
                    <input type="date" name="data_inicio" class="form-control" value="<?= $data_inicio ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Data Final</label>
                    <input type="date" name="data_fim" class="form-control" value="<?= $data_fim ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label">Buscar</label>
                    <input type="text" name="busca" class="form-control" placeholder="ID, nome ou email" value="<?= $busca ?>">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-2"></i>Filtrar
                    </button>
                    <a href="/admin/pedidos" class="btn btn-secondary">
                        <i class="fas fa-times me-2"></i>Limpar
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Lista de Pedidos -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Cliente</th>
                            <th>Data</th>
                            <th>Total</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pedidos)): ?>
                            <tr>
                                <td colspan="6" class="text-center">Nenhum pedido encontrado.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pedidos as $pedido): ?>
                                <tr>
                                    <td>#<?= str_pad($pedido['id'], 6, '0', STR_PAD_LEFT) ?></td>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($pedido['nome_cliente']) ?></strong><br>
                                            <small class="text-muted"><?= htmlspecialchars($pedido['email_cliente']) ?></small>
                                        </div>
                                    </td>
                                    <td><?= date('d/m/Y H:i', strtotime($pedido['data_criacao'])) ?></td>
                                    <td>
                                        <span class="badge badge-<?= $pedido['status'] == 'pago' ? 'success' : ($pedido['status'] == 'pendente' ? 'warning' : ($pedido['status'] == 'cancelado' ? 'danger' : 'info')) ?>">
                                            <?= ucfirst($pedido['status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="editarUsuario(<?= $pedido['usuario_id'] ?? '' ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-info" onclick="verPerfilUsuario(<?= $pedido['usuario_id'] ?? '' ?>)">
                                                <i class="fas fa-user"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-success" onclick="gerenciarCreditos(<?= $pedido['usuario_id'] ?? '' ?>)">
                                                <i class="fas fa-credit-card"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-success" onclick="atualizarStatus(<?= $pedido['id'] ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-info" onclick="gerarEtiqueta(<?= $pedido['id'] ?>)">
                                                <i class="fas fa-tag"></i>
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
                            <li class="page-item <?= $i == $pagina ? 'active' : '' ?>">
                                <a class="page-link" href="/admin/pedidos?pagina=<?= $i ?><?= $status ? '&status=' . $status : '' ?><?= $data_inicio ? '&data_inicio=' . $data_inicio : '' ?><?= $data_fim ? '&data_fim=' . $data_fim : '' ?><?= $busca ? '&busca=' . urlencode($busca) : '' ?>">
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

<!-- Modal Criar Pedido -->
<div class="modal fade" id="modalCriarPedido" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Criar Novo Pedido</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formCriarPedido">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Selecionar Cliente *</label>
                            <select name="usuario_id" class="form-select" required>
                                <option value="">Selecione um cliente...</option>
                                <?php foreach ($usuarios as $usuario): ?>
                                    <option value="<?= $usuario['id'] ?>"><?= htmlspecialchars($usuario['nome']) ?> (<?= htmlspecialchars($usuario['email']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn btn-outline-primary btn-sm mt-2" onclick="abrirModalNovoCliente()">
                                <i class="fas fa-user-plus me-2"></i> Novo Cliente
                            </button>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Data do Pedido</label>
                            <input type="datetime-local" name="data_pedido" class="form-control" required>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="row g-3">
                        <div class="col-12">
                            <h6>Dados do Cliente</h6>
                            <div id="dados-cliente" class="alert alert-info">
                                <p><strong>Nome:</strong> <span id="cliente-nome">Selecione um cliente...</span></p>
                                <p><strong>Email:</strong> <span id="cliente-email">-</span></p>
                                <p><strong>CPF/CNPJ:</strong> <span id="cliente-documento">-</span></p>
                                <p><strong>Telefone:</strong> <span id="cliente-telefone">-</span></p>
                                <p><strong>Créditos Disponíveis:</strong> <span id="cliente-creditos" class="text-success">R$ 0,00</span></p>
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="row g-3">
                        <div class="col-12">
                            <h6>Adicionar Produtos</h6>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>Produto</th>
                                            <th>Preço</th>
                                            <th>Estoque</th>
                                            <th>Qtd</th>
                                            <th>Subtotal</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody id="itens-pedido">
                                        <tr>
                                            <td colspan="7" class="text-center">Nenhum item adicionado</td>
                                        </tr>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="4"></td>
                                            <td><strong>Total:</strong></td>
                                            <td id="total-pedido">R$ 0,00</td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            
                            <div class="row g-3 mt-3">
                                <div class="col-md-8">
                                    <label class="form-label">Buscar Produto</label>
                                    <div class="input-group">
                                        <input type="text" id="busca-produto" class="form-control" placeholder="Nome ou SKU">
                                        <button class="btn btn-outline-secondary" type="button" onclick="buscarProdutos()">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label">Categoria</label>
                                    <select id="filtro-categoria" class="form-select">
                                        <option value="">Todas</option>
                                        <?php foreach ($categorias as $categoria): ?>
                                            <option value="<?= $categoria['id'] ?>"><?= htmlspecialchars($categoria['nome']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row g-3 mt-3" id="resultados-busca" style="display: none;">
                                <div class="col-12">
                                    <h6>Resultados da Busca:</h6>
                                    <div id="lista-produtos" class="row g-3">
                                        <!-- Produtos carregados via JavaScript -->
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Forma de Pagamento</label>
                            <select name="forma_pagamento" class="form-select" required>
                                <option value="">Selecione...</option>
                                <option value="cartao_credito">Cartão de Crédito</option>
                                <option value="boleto">Boleto</option>
                                <option value="pix">PIX</option>
                                <option value="transferencia">Transferência Bancária</option>
                                <option value="pagamento_entrega">Pagamento na Entrega</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Usar Créditos?</label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="usar_creditos" id="usar-creditos">
                                <label class="form-check-label" for="usar-creditos">
                                    Usar créditos disponíveis como desconto
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Subtotal</label>
                            <div class="input-group">
                                <span class="input-group-text">R$</span>
                                <input type="text" id="subtotal-pedido" class="form-control" value="0.00" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Desconto</label>
                            <div class="input-group">
                                <span class="input-group-text">R$</span>
                                <input type="text" id="desconto-pedido" class="form-control" value="0.00" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Total</label>
                            <div class="input-group">
                                <span class="input-group-text">R$</span>
                                <input type="text" id="total-final" class="form-control" value="0.00" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Total com Crédito</label>
                            <div class="input-group">
                                <span class="input-group-text">R$</span>
                                <input type="text" id="total-com-credito" class="form-control" value="0.00" readonly>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Observações</label>
                            <textarea name="observacoes" class="form-control" rows="3" placeholder="Observações do pedido"></textarea>
                        </div>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-12">
                            <button type="button" class="btn btn-primary" onclick="criarPedido()">
                                <i class="fas fa-shopping-cart me-2"></i> Criar Pedido
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="limparItensPedido()">
                                <i class="fas fa-trash me-2"></i> Limpar Itens
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="criarPedido()">
                    <i class="fas fa-shopping-cart me-2"></i> Criar Pedido
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Novo Cliente -->
<div class="modal fade" id="modalNovoCliente" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Novo Cliente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formNovoCliente">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Nome Completo *</label>
                            <input type="text" name="nome" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Email *</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">CPF/CNPJ *</label>
                            <input type="text" name="documento" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Telefone</label>
                            <input type="text" name="telefone" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Endereço</label>
                            <input type="text" name="endereco" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Cidade</label>
                            <input type="text" name="cidade" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Estado</label>
                            <input type="text" name="estado" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">CEP</label>
                            <input type="text" name="cep" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Senha</label>
                            <input type="password" name="senha" class="form-control">
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="status" id="cliente-status" checked>
                                <label class="form-check-label" for="cliente-status">
                                    Usuário Ativo
                                </label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Créditos Iniciais</label>
                            <input type="number" name="creditos_iniciais" class="form-control" step="0.01" value="0.00">
                            <div class="form-text">Valor inicial na carteira digital</div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="salvarNovoCliente()">Salvar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Detalhes do Pedido -->
<div class="modal fade" id="modalDetalhes" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Detalhes do Pedido</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="conteudoDetalhes">
                <!-- Conteúdo carregado via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Atualizar Status -->
<div class="modal fade" id="modalStatus" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Atualizar Status</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formStatus">
                    <input type="hidden" name="pedido_id" id="pedido_id">
                    <div class="mb-3">
                        <label class="form-label">Novo Status</label>
                        <select name="status" class="form-select" required>
                            <option value="">Selecione...</option>
                            <option value="pendente">Pendente</option>
                            <option value="pago">Pago</option>
                            <option value="processando">Processando</option>
                            <option value="enviado">Enviado</option>
                            <option value="entregue">Entregue</option>
                            <option value="cancelado">Cancelado</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Observações</label>
                        <textarea name="observacoes" class="form-control" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="button" class="btn btn-primary" onclick="salvarStatus()">Salvar</button>
            </div>
        </div>
    </div>
</div>

<script>
function editarUsuario(id) {
    console.log('🔍 [INÍCIO] editarUsuario() - ID recebido:', id);
    console.log('🔍 [INÍCIO] editarUsuario() - Tipo do ID:', typeof id);
    console.log('🔍 [INÍCIO] editarUsuario() - ID convertido para número:', Number(id));
    console.log('🔍 [INÍCIO] editarUsuario() - isNaN(Number(id)):', isNaN(Number(id)));
    console.log('🔍 [INÍCIO] editarUsuario() - Number(id) > 0:', Number(id) > 0);
    
    // Converter ID para número se for string
    const idNumerico = Number(id);
    console.log('🔍 [VALIDAÇÃO] ID numérico final:', idNumerico);
    
    if (!idNumerico || isNaN(idNumerico) || idNumerico <= 0) {
        console.error('❌ [ERRO] ID inválido:', id, '->', idNumerico);
        alert('ID do usuário inválido. ID recebido: ' + id + ' (tipo: ' + typeof id + ')');
        return;
    }
    
    const url = `/admin/usuario/${idNumerico}`;
    console.log('🔍 [URL] URL da requisição:', url);
    console.log('🔍 [URL] URL completa:', window.location.origin + url);
    
    console.log('🔄 [FETCH] Iniciando requisição fetch...');
    console.time('⏱️ [FETCH] Tempo da requisição');
    
    fetch(url)
        .then(response => {
            console.timeEnd('⏱️ [FETCH] Tempo da requisição');
            console.log('🔍 [RESPONSE] Status HTTP:', response.status);
            console.log('🔍 [RESPONSE] Status Text:', response.statusText);
            console.log('🔍 [RESPONSE] Headers:', [...response.headers.entries()]);
            console.log('🔍 [RESPONSE] URL:', response.url);
            console.log('🔍 [RESPONSE] ok:', response.ok);
            console.log('🔍 [RESPONSE] redirected:', response.redirected);
            console.log('🔍 [RESPONSE] type:', response.type);
            console.log('🔍 [RESPONSE] Content-Type:', response.headers.get('content-type'));
            
            // Verificar se o content-type é JSON
            const contentType = response.headers.get('content-type');
            console.log('🔍 [RESPONSE] Content-Type:', contentType);
            
            if (!response.ok) {
                console.error('❌ [ERRO] Response não ok:', response.status, response.statusText);
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            // Verificar se o content-type é JSON
            if (contentType && contentType.includes('application/json')) {
                console.log('🔍 [JSON] Content-Type correto, parseando como JSON...');
                return response.json();
            } else if (contentType && contentType.includes('text/html')) {
                console.log('❌ [ERRORO] Content-Type é HTML, não JSON:', contentType);
                console.log('🔍 [HTML] Primeiros 200 caracteres da resposta:');
                return response.text().then(html => {
                    console.log('🔍 [HTML] Conteúdo HTML recebido:', html.substring(0, 200));
                    throw new Error('Resposta HTML recebida em vez de JSON');
                });
            } else {
                console.log('🔍 [JSON] Content-Type não especificado, tentando JSON...');
                return response.json();
            }
        })
        .then(data => {
            console.log('🔍 [JSON] Dados recebidos:', data);
            console.log('🔍 [JSON] Tipo dos dados:', typeof data);
            console.log('🔍 [JSON] Chaves do objeto:', Object.keys(data));
            console.log('🔍 [JSON] data.success:', data.success);
            console.log('🔍 [JSON] data.error:', data.error);
            console.log('🔍 [JSON] data.usuario:', data.usuario);
            
            if (data.success && data.usuario) {
                console.log('✅ [SUCESSO] Usuário encontrado, criando modal...');
                console.log('🔍 [MODAL] Dados do usuário:', {
                    id: data.usuario.id,
                    nome: data.usuario.nome,
                    email: data.usuario.email,
                    perfil: data.usuario.perfil,
                    status: data.usuario.status,
                    creditos_disponiveis: data.usuario.creditos_disponiveis
                });
                
                // Criar modal de edição dinamicamente
                console.log('🔍 [MODAL] Criando HTML do modal...');
                const modalHtml = `
                    <div class="modal fade" id="modalEditarUsuario" tabindex="-1">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Editar Usuário</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <form id="formEditarUsuario">
                                        <input type="hidden" name="id" value="${data.usuario.id}">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label">Nome Completo *</label>
                                                <input type="text" name="nome" class="form-control" value="${data.usuario.nome || ''}" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Email *</label>
                                                <input type="email" name="email" class="form-control" value="${data.usuario.email || ''}" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">CPF/CNPJ</label>
                                                <input type="text" name="documento" class="form-control" value="${data.usuario.documento || ''}">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Telefone</label>
                                                <input type="text" name="telefone" class="form-control" value="${data.usuario.telefone || ''}">
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label">Endereço</label>
                                                <input type="text" name="endereco" class="form-control" value="${data.usuario.endereco || ''}">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Cidade</label>
                                                <input type="text" name="cidade" class="form-control" value="${data.usuario.cidade || ''}">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Estado</label>
                                                <input type="text" name="estado" class="form-control" value="${data.usuario.estado || ''}">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">CEP</label>
                                                <input type="text" name="cep" class="form-control" value="${data.usuario.cep || ''}">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Perfil</label>
                                                <select name="perfil" class="form-select">
                                                    <option value="cliente" ${data.usuario.perfil == 'cliente' ? 'selected' : ''}>Cliente</option>
                                                    <option value="admin" ${data.usuario.perfil == 'admin' ? 'selected' : ''}>Administrador</option>
                                                </select>
                                            </div>
                                            <div class="col-12">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="status" id="usuario-editar-status" ${data.usuario.status == 'ativo' ? 'checked' : ''}>
                                                    <label class="form-check-label" for="usuario-editar-status">
                                                        Usuário Ativo
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label">Créditos Disponíveis</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">R$</span>
                                                    <input type="number" name="creditos_disponiveis" class="form-control" step="0.01" value="${data.usuario.creditos_disponiveis || 0}">
                                                </div>
                                                <div class="form-text">Saldo atual de créditos do usuário</div>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                                    <button type="button" class="btn btn-primary" onclick="salvarEdicaoUsuario()">Salvar</button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                
                console.log('🔍 [MODAL] HTML do modal criado, tamanho:', modalHtml.length);
                console.log('🔍 [MODAL] Primeiros 100 caracteres:', modalHtml.substring(0, 100));
                
                // Remover modal anterior se existir
                const modalAnterior = document.getElementById('modalEditarUsuario');
                if (modalAnterior) {
                    console.log('🔍 [DOM] Removendo modal anterior...');
                    modalAnterior.remove();
                }
                
                // Adicionar novo modal ao body
                console.log('🔍 [DOM] Adicionando modal ao body...');
                document.body.insertAdjacentHTML('beforeend', modalHtml);
                
                // Verificar se o modal foi adicionado
                const modalElement = document.getElementById('modalEditarUsuario');
                console.log('🔍 [DOM] Modal encontrado no DOM:', !!modalElement);
                
                if (modalElement) {
                    console.log('🔍 [BOOTSTRAP] Inicializando modal Bootstrap...');
                    try {
                        const modal = new bootstrap.Modal(modalElement);
                        console.log('🔍 [BOOTSTRAP] Modal Bootstrap criado:', !!modal);
                        
                        console.log('🔍 [BOOTSTRAP] Abrindo modal...');
                        modal.show();
                        console.log('✅ [SUCESSO] Modal aberto com sucesso!');
                    } catch (error) {
                        console.error('❌ [ERRO BOOTSTRAP] Erro ao criar modal:', error);
                        console.error('❌ [ERRO BOOTSTRAP] Stack:', error.stack);
                        alert('Erro ao abrir modal. Verifique o console para mais detalhes.');
                    }
                } else {
                    console.error('❌ [ERRO DOM] Elemento do modal não encontrado no DOM');
                    console.log('🔍 [DOM] Elementos com ID modalEditarUsuario:', document.querySelectorAll('[id="modalEditarUsuario"]'));
                    alert('Erro: Elemento do modal não encontrado no DOM.');
                }
            } else {
                console.error('❌ [ERRO] Resposta sem sucesso:', data);
                console.error('❌ [ERRO] Mensagem de erro:', data.error);
                alert('Erro ao carregar usuário: ' + (data.error || 'Erro desconhecido - verifique o console'));
            }
        })
        .catch(error => {
            console.timeEnd('⏱️ [FETCH] Tempo da requisição');
            console.error('❌ [ERRO CATCH] Erro na requisição:', error);
            console.error('❌ [ERRO CATCH] Nome do erro:', error.name);
            console.error('❌ [ERRO CATCH] Mensagem:', error.message);
            console.error('❌ [ERRO CATCH] Stack completo:', error.stack);
            console.error('❌ [ERRO CATCH] URL:', url);
            console.error('❌ [ERRO CATCH] ID:', idNumerico);
            
            // Verificar se é erro de rede
            if (error.name === 'TypeError' && error.message.includes('Failed to fetch')) {
                console.error('❌ [ERRO REDE] Possível erro de rede ou CORS');
                alert('Erro de rede. Verifique a conexão com o servidor.');
            } else if (error.name === 'SyntaxError') {
                console.error('❌ [ERRO SINTAX] Erro de sintaxe na resposta JSON');
                alert('Erro de sintaxe na resposta do servidor.');
            } else {
                alert('Erro ao carregar usuário. Verifique o console para mais detalhes.');
            }
        });
}

let itensPedido = [];
let clienteAtual = null;
let produtosDisponiveis = [];

function abrirModalNovoCliente() {
    document.getElementById('formNovoCliente').reset();
    new bootstrap.Modal(document.getElementById('modalNovoCliente')).show();
}

function salvarNovoCliente() {
    const form = document.getElementById('formNovoCliente');
    const formData = new FormData(form);
    
    fetch('/admin/salvar-usuario', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Cliente criado com sucesso!');
            new bootstrap.Modal(document.getElementById('modalNovoCliente')).hide();
            document.getElementById('modalNovoCliente').remove();
            
            // Adicionar o novo cliente ao select do pedido
            const select = document.querySelector('#formCriarPedido select[name="usuario_id"]');
            const option = document.createElement('option');
            option.value = data.usuario.id;
            option.textContent = `${data.usuario.nome} (${data.usuario.email})`;
            select.appendChild(option);
            
            // Selecionar automaticamente o novo cliente
            select.value = data.usuario.id;
            carregarDadosCliente(data.usuario.id);
        } else {
            alert('Erro ao criar cliente: ' + data.error);
        }
    });
}

function carregarDadosCliente(usuarioId) {
    fetch(`/admin/usuario/${usuarioId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                clienteAtual = data.usuario;
                document.getElementById('cliente-nome').textContent = data.usuario.nome;
                document.getElementById('cliente-email').textContent = data.usuario.email;
                document.getElementById('cliente-documento').textContent = data.usuario.documento;
                document.getElementById('cliente-telefone').textContent = data.usuario.telefone;
                document.getElementById('cliente-creditos').textContent = `R$ ${number_format(data.usuario.creditos_disponiveis, 2, ',', '.')}`;
                
                // Atualizar checkbox de uso de créditos
                document.getElementById('usar-creditos').checked = data.usuario.creditos_disponiveis > 0;
                calcularTotais();
            }
        });
}

function buscarProdutos() {
    const termo = document.getElementById('busca-produto').value;
    const categoriaId = document.getElementById('filtro-categoria').value;
    
    if (!termo && !categoriaId) {
        alert('Digite um termo ou selecione uma categoria para buscar.');
        return;
    }
    
    fetch(`/admin/buscar-produtos?termo=${encodeURIComponent(termo)}&categoria_id=${categoriaId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                produtosDisponiveis = data.produtos;
                exibirResultadosBusca();
            } else {
                alert('Nenhum produto encontrado.');
            }
        });
}

function exibirResultadosBusca() {
    const resultadosDiv = document.getElementById('resultados-busca');
    const listaDiv = document.getElementById('lista-produtos');
    
    listaDiv.innerHTML = '';
    
    produtosDisponiveis.forEach(produto => {
        const produtoDiv = document.createElement('div');
        produtoDiv.className = 'col-md-4 mb-3';
        produtoDiv.innerHTML = `
            <div class="card">
                <div class="card-body">
                    <h6>${produto.nome}</h6>
                    <p><strong>SKU:</strong> ${produto.sku}</p>
                    <p><strong>Preço:</strong> R$ ${number_format(produto.valor, 2, ',', '.')}</p>
                    <p><strong>Estoque:</strong> ${produto.estoque}</p>
                    <button type="button" class="btn btn-sm btn-primary btn-block" onclick="adicionarItemPedido(${produto.id}, '${produto.nome}', ${produto.valor}, ${produto.estoque})">
                        <i class="fas fa-plus me-2"></i> Adicionar
                    </button>
                </div>
            </div>
        `;
        listaDiv.appendChild(produtoDiv);
    });
    
    resultadosDiv.style.display = 'block';
}

function adicionarItemPedido(produtoId, nome, preco, estoque) {
    const quantidade = 1;
    const subtotal = preco * quantidade;
    
    // Verificar se o produto já está no carrinho
    const itemExistente = itensPedido.find(item => item.produto_id === produtoId);
    if (itemExistente) {
        alert('Este produto já foi adicionado ao pedido.');
        return;
    }
    
    // Verificar estoque
    if (quantidade > estoque) {
        alert('Estoque insuficiente. Estoque disponível: ' + estoque);
        return;
    }
    
    // Adicionar ao carrinho
    itensPedido.push({
        produto_id: produtoId,
        nome: nome,
        preco: preco,
        quantidade: quantidade,
        subtotal: subtotal
    });
    
    atualizarTabelaItens();
    calcularTotais();
}

function atualizarTabelaItens() {
    const tbody = document.getElementById('itens-pedido');
    tbody.innerHTML = '';
    
    itensPedido.forEach((item, index) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${item.nome}</td>
            <td>R$ ${number_format(item.preco, 2, ',', '.')}</td>
            <td>${item.estoque}</td>
            <td>
                <input type="number" type="number" min="1" max="${item.estoque}" value="${item.quantidade}" onchange="atualizarQuantidade(${index})" class="form-control form-control-sm" style="width: 80px;">
            </td>
            <td>R$ <span id="subtotal-${index}">${number_format(item.subtotal, 2, ',', '.')}</span></td>
            <td>
                <button type="button" class="btn btn-sm btn-danger btn-sm" onclick="removerItem(${index})">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    });
}

function atualizarQuantidade(index) {
    const quantidade = parseInt(document.querySelector(`input[name="quantidade-${index}"]`).value);
    const item = itensPedido[index];
    const estoque = item.estoque;
    
    if (quantidade > estoque) {
        alert('Quantidade maior que o estoque disponível: ' + estoque);
        document.querySelector(`input[name="quantidade-${index}"]`).value = estoque;
        return;
    }
    
    item.quantidade = quantidade;
    item.subtotal = item.preco * quantidade;
    
    document.getElementById(`subtotal-${index}`).textContent = number_format(item.subtotal, 2, ',');
    calcularTotais();
}

function removerItem(index) {
    itensPedido.splice(index, 1);
    atualizarTabelaItens();
    calcularTotais();
}

function limparItensPedido() {
    itensPedido = [];
    atualizarTabelaItens();
    calcularTotais();
}

function calcularTotais() {
    const subtotal = itensPedido.reduce((total, item) => total + item.subtotal, 0);
    const usarCreditos = document.getElementById('usar-creditos').checked;
    const creditosDisponiveis = clienteAtual ? clienteAtual.creditos_disponiveis : 0;
    
    let desconto = 0;
    if (usarCreditos && creditosDisponiveis > 0) {
        desconto = Math.min(creditosDisponiveis, subtotal);
    }
    
    const total = subtotal - desconto;
    
    document.getElementById('subtotal-pedido').value = number_format(subtotal, 2, ',');
    document.getElementById('desconto-pedido').value = number_format(desconto, 2, ',');
    document.getElementById('total-final').value = number_format(total, 2, ',');
    document.getElementById('total-com-credito').value = number_format(total - desconto, 2, ',');
    
    // Atualizar total na tabela
    document.querySelector('#total-pedido').textContent = number_format(total, 2, ',');
}

function criarPedido() {
    if (!clienteAtual) {
        alert('Selecione um cliente para continuar.');
        return;
    }
    
    if (itensPedido.length === 0) {
        alert('Adicione pelo menos um produto ao pedido.');
        return;
    }
    
    const form = document.getElementById('formCriarPedido');
    const formData = new FormData(form);
    
    // Adicionar dados do cliente
    formData.set('cliente_id', clienteAtual.id);
    formData.set('cliente_nome', clienteAtual.nome);
    formData.set('cliente_email', clienteAtual.email);
    
    // Adicionar itens do pedido
    formData.set('itens', JSON.stringify(itensPedido));
    formData.set('subtotal', document.getElementById('subtotal-pedido').value);
    formData.set('desconto', document.getElementById('desconto-pedido').value);
    formData.set('total', document.getElementById('total-final').value);
    formData.set('total_com_credito', document.getElementById('total-com-credito').value);
    formData.set('usar_creditos', document.getElementById('usar-creditos').checked ? '1' : '0');
    
    fetch('/admin/criar-pedido-completo', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Pedido criado com sucesso! ID: ' + data.pedido_id);
            new bootstrap.Modal(document.getElementById('modalCriarPedido')).hide();
            document.getElementById('formCriarPedido').reset();
            itensPedido = [];
            clienteAtual = null;
            location.reload();
        } else {
            alert('Erro ao criar pedido: ' + data.error);
        }
    });
}

// Event listeners
document.getElementById('formCriarPedido').addEventListener('change', function(e) {
    if (e.target.name === 'usuario_id') {
        carregarDadosCliente(e.target.value);
    }
});

document.getElementById('usar-creditos').addEventListener('change', function() {
    calcularTotais();
});

document.getElementById('filtro-categoria').addEventListener('change', function() {
    document.getElementById('busca-produto').value = '';
    document.getElementById('resultados-busca').style.display = 'none';
});

function criarPedido() {

function verDetalhes(pedidoId) {
    fetch(`/admin/pedido-detalhes/${pedidoId}`)
        .then(response => response.text())
        .then(html => {
            document.getElementById('conteudoDetalhes').innerHTML = html;
            new bootstrap.Modal(document.getElementById('modalDetalhes')).show();
        });
}

function atualizarStatus(pedidoId) {
    document.getElementById('pedido_id').value = pedidoId;
    new bootstrap.Modal(document.getElementById('modalStatus')).show();
}

function salvarStatus() {
    const form = document.getElementById('formStatus');
    const formData = new FormData(form);
    
    fetch('/admin/atualizar-status', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Status atualizado com sucesso!');
            location.reload();
        } else {
            alert('Erro ao atualizar status: ' + data.error);
        }
    });
}

function gerarEtiqueta(pedidoId) {
    window.open(`/admin/gerar-etiqueta/${pedidoId}`, '_blank');
}

function excluirUsuario(id) {
    if (confirm('Deseja realmente excluir este usuário? Esta ação não pode ser desfeita!')) {
        fetch(`/admin/excluir-usuario/${id}`, {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Usuário excluído com sucesso!');
                carregarUsuarios();
            } else {
                alert('Erro ao excluir usuário: ' + data.error);
            }
        });
    }
}

function atualizarEstatisticasUsuarios() {
    fetch('/admin/estatisticas-usuarios')
        .then(response => response.json())
        .then(data => {
            document.getElementById('total-usuarios').textContent = data.total_usuarios;
            document.getElementById('usuarios-ativos').textContent = data.usuarios_ativos;
            document.getElementById('usuarios-mes').textContent = data.usuarios_mes;
        });
}

// Funções de Créditos
function abrirModalCreditos() {
    carregarUsuarios();
    new bootstrap.Modal(document.getElementById('modalCreditos')).show();
}

function carregarUsuariosSelect() {
    fetch('/admin/usuarios-json')
        .then(response => response.json())
        .then(data => {
            const select = document.querySelector('#formCreditos select[name="usuario_id"]');
            select.innerHTML = '<option value="">Selecione um usuário...</option>';
            
            data.usuarios.forEach(usuario => {
                const option = document.createElement('option');
                option.value = usuario.id;
                option.textContent = `${usuario.nome} (Saldo: R$ ${number_format(usuario.creditos_disponiveis, 2, ',', '.')})`;
                select.appendChild(option);
            });
        });
}

function salvarCreditos() {
    const form = document.getElementById('formCreditos');
    const formData = new FormData(form);
    
    fetch('/admin/adicionar-creditos', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Créditos adicionados com sucesso!');
            new bootstrap.Modal(document.getElementById('modalCreditos')).hide();
            carregarLogsCreditos();
            carregarUsuarios();
        } else {
            alert('Erro ao adicionar créditos: ' + data.error);
        }
    });
}

function carregarLogsCreditos() {
    fetch('/admin/logs-creditos')
        .then(response => response.json())
        .then(data => {
            const tbody = document.getElementById('creditos-lista');
            tbody.innerHTML = '';
            
            data.logs.forEach(log => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td>${log.id}</td>
                    <td>${log.usuario_nome}</td>
                    <td>R$ ${number_format(log.valor, 2, ',', '.')}</td>
                    <td>${new Date(log.data_criacao).toLocaleString('pt-BR')}</td>
                    <td><span class="badge badge-${log.status == 'ativo' ? 'success' : 'danger'}">${log.status}</span></td>
                    <td>
                        <button type="button" class="btn btn-sm btn-outline-info" onclick="verDetalhesCredito(${log.id})">
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        })
        .catch(error => {
            console.error('Erro ao carregar logs de créditos:', error);
        });
}

function verDetalhesCredito(id) {
    fetch(`/admin/credito-detalhes/${id}`)
        .then(response => response.json())
        .then(data => {
            if (data.log) {
                alert(`Detalhes do Crédito #${id}:\n\n` +
                    `Usuário: ${data.log.usuario_nome}\n` +
                    `Valor: R$ ${number_format(data.log.valor, 2, ',', '.')}\n` +
                    `Data: ${new Date(data.log.data_criacao).toLocaleString('pt-BR')}\n` +
                    `Status: ${data.log.status}\n` +
                    `Descrição: ${data.log.descricao}\n` +
                    `Válido até: ${data.validade_dias} dias`);
            }
        });
}

function imprimirPedido() {
    const pedidoId = document.getElementById('pedido-id').textContent;
    window.open(`/admin/imprimir-pedido/${pedidoId}`, '_blank');
}

// Carregar dados ao carregar a página
document.addEventListener('DOMContentLoaded', function() {
    carregarUsuarios();
    carregarLogsCreditos();
    atualizarEstatisticasUsuarios();
});

// Mudar para aba de criação ao clicar em "Novo Pedido"
document.querySelector('[data-bs-target="#criar"]').addEventListener('click', function() {
    document.getElementById('formCriarPedido').reset();
});
</script>

<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/admin.php'; ?>
