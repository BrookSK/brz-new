<?php ob_start(); ?>

<?php
function getOrderStatusLabel($status) {
    $labels = [
        'pendente' => __('admin.orders.status.pending', 'Pendente'),
        'pago' => __('admin.orders.status.paid', 'Pago'),
        'processando' => __('admin.orders.status.processing', 'Processando'),
        'enviado' => __('admin.orders.status.shipped', 'Enviado'),
        'entregue' => __('admin.orders.status.delivered', 'Entregue'),
        'cancelado' => __('admin.orders.status.cancelled', 'Cancelado')
    ];
    return $labels[(string) $status] ?? (string) $status;
}
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="fas fa-shopping-cart me-2"></i>
            <?= __('admin.orders.title', 'Gerenciamento de Pedidos') ?>
        </h1>
        <div>
            <a href="/admin" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i><?= __('common.back', 'Voltar') ?>
            </a>
            <a href="/admin/pedidos/novo-manual" class="btn btn-primary ms-2">
                <i class="fas fa-plus me-2"></i><?= __('admin.orders.new_manual', 'Novo Pedido Manual') ?>
            </a>
            <a href="/admin/pedidos/comissoes" class="btn btn-outline-primary ms-2">
                <i class="fas fa-percentage me-2"></i><?= __('admin.menu.my_commissions', 'Minhas Comissões') ?>
            </a>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalCriarPedido">
                <i class="fas fa-plus me-2"></i><?= __('admin.orders.new', 'Novo Pedido') ?>
            </button>
        </div>
    </div>

    <!-- Filtros -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label"><?= __('common.status', 'Status') ?></label>
                    <select name="status" class="form-select">
                        <option value=""><?= __('common.all', 'Todos') ?></option>
                        <option value="pendente" <?= $status == 'pendente' ? 'selected' : '' ?>><?= __('admin.orders.status.pending', 'Pendente') ?></option>
                        <option value="pago" <?= $status == 'pago' ? 'selected' : '' ?>><?= __('admin.orders.status.paid', 'Pago') ?></option>
                        <option value="processando" <?= $status == 'processando' ? 'selected' : '' ?>><?= __('admin.orders.status.processing', 'Processando') ?></option>
                        <option value="enviado" <?= $status == 'enviado' ? 'selected' : '' ?>><?= __('admin.orders.status.shipped', 'Enviado') ?></option>
                        <option value="entregue" <?= $status == 'entregue' ? 'selected' : '' ?>><?= __('admin.orders.status.delivered', 'Entregue') ?></option>
                        <option value="cancelado" <?= $status == 'cancelado' ? 'selected' : '' ?>><?= __('admin.orders.status.cancelled', 'Cancelado') ?></option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label"><?= __('admin.orders.start_date', 'Data Inicial') ?></label>
                    <input type="date" name="data_inicio" class="form-control" value="<?= $data_inicio ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label"><?= __('admin.orders.end_date', 'Data Final') ?></label>
                    <input type="date" name="data_fim" class="form-control" value="<?= $data_fim ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label"><?= __('common.search', 'Buscar') ?></label>
                    <input type="text" name="busca" class="form-control" placeholder="<?= htmlspecialchars(__('admin.orders.search_placeholder', 'ID, nome ou email'), ENT_QUOTES, 'UTF-8') ?>" value="<?= $busca ?>">
                </div>
                <div class="col-12">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-search me-2"></i><?= __('common.filter', 'Filtrar') ?>
                    </button>
                    <a href="/admin/pedidos" class="btn btn-secondary">
                        <i class="fas fa-times me-2"></i><?= __('common.clear', 'Limpar') ?>
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Lista de Pedidos -->
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th><?= __('admin.orders.table.id', 'ID') ?></th>
                            <th><?= __('admin.orders.table.customer', 'Cliente') ?></th>
                            <th><?= __('admin.orders.table.date', 'Data') ?></th>
                            <th><?= __('admin.orders.table.total', 'Total') ?></th>
                            <th><?= __('admin.orders.table.status', 'Status') ?></th>
                            <th><?= __('common.actions', 'Ações') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pedidos)): ?>
                            <tr>
                                <td colspan="6" class="text-center"><?= __('admin.orders.empty', 'Nenhum pedido encontrado.') ?></td>
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
                                        <?php
                                            $pedidoStatus = (string) ($pedido['status'] ?? '');
                                            $badgeStyle = 'background: rgba(148, 163, 184, 0.16); border: 1px solid rgba(148, 163, 184, 0.28); color: #334155;';
                                            if ($pedidoStatus === 'pago') {
                                                $badgeStyle = 'background: rgba(16, 185, 129, 0.12); border: 1px solid rgba(16, 185, 129, 0.22); color: #065f46;';
                                            } elseif ($pedidoStatus === 'pendente') {
                                                $badgeStyle = 'background: rgba(245, 158, 11, 0.12); border: 1px solid rgba(245, 158, 11, 0.22); color: #7c2d12;';
                                            } elseif ($pedidoStatus === 'cancelado') {
                                                $badgeStyle = 'background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.22); color: #7f1d1d;';
                                            } else {
                                                $badgeStyle = 'background: rgba(56, 189, 248, 0.10); border: 1px solid rgba(56, 189, 248, 0.22); color: #0b1f3a;';
                                            }
                                        ?>
                                        <span class="badge" style="<?= $badgeStyle ?>">
                                            <?= getOrderStatusLabel($pedidoStatus) ?>
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
                <nav aria-label="<?= htmlspecialchars(__('common.pagination', 'Paginação'), ENT_QUOTES, 'UTF-8') ?>">
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
                <h5 class="modal-title"><?= __('admin.orders.create.title', 'Criar Novo Pedido') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formCriarPedido">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label"><?= __('admin.orders.create.select_customer', 'Selecionar Cliente *') ?></label>
                            <select name="usuario_id" class="form-select" required>
                                <option value=""><?= __('admin.orders.create.select_customer_placeholder', 'Selecione um cliente...') ?></option>
                                <?php foreach ($usuarios as $usuario): ?>
                                    <option value="<?= $usuario['id'] ?>"><?= htmlspecialchars($usuario['nome']) ?> (<?= htmlspecialchars($usuario['email']) ?>)</option>
                                <?php endforeach; ?>
                            </select>
                            <button type="button" class="btn btn-outline-primary btn-sm mt-2" onclick="abrirModalNovoCliente()">
                                <i class="fas fa-user-plus me-2"></i> <?= __('admin.orders.create.new_customer', 'Novo Cliente') ?>
                            </button>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= __('admin.orders.create.order_date', 'Data do Pedido') ?></label>
                            <input type="datetime-local" name="data_pedido" class="form-control" required>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="row g-3">
                        <div class="col-12">
                            <h6><?= __('admin.orders.create.customer_data', 'Dados do Cliente') ?></h6>
                            <div id="dados-cliente" class="alert alert-info">
                                <p><strong><?= __('common.name', 'Nome') ?>:</strong> <span id="cliente-nome"><?= __('admin.orders.create.select_customer_placeholder', 'Selecione um cliente...') ?></span></p>
                                <p><strong><?= __('common.email', 'Email') ?>:</strong> <span id="cliente-email">-</span></p>
                                <p><strong><?= __('checkout.cpf_cnpj', 'CPF/CNPJ') ?>:</strong> <span id="cliente-documento">-</span></p>
                                <p><strong><?= __('common.phone', 'Telefone') ?>:</strong> <span id="cliente-telefone">-</span></p>
                                <p><strong><?= __('admin.users.credits_available', 'Créditos Disponíveis') ?>:</strong> <span id="cliente-creditos" class="text-success"><?= __('admin.orders.js.currency_brl', 'R$') ?> 0,00</span></p>
                            </div>
                        </div>
                    </div>
                    
                    <hr>
                    
                    <div class="row g-3">
                        <div class="col-12">
                            <h6><?= __('admin.orders.create.add_products', 'Adicionar Produtos') ?></h6>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th><?= __('admin.orders.create.table.product', 'Produto') ?></th>
                                            <th><?= __('admin.orders.create.table.price', 'Preço') ?></th>
                                            <th><?= __('admin.orders.create.table.stock', 'Estoque') ?></th>
                                            <th><?= __('admin.orders.create.table.qty', 'Qtd') ?></th>
                                            <th><?= __('admin.orders.create.table.subtotal', 'Subtotal') ?></th>
                                            <th><?= __('common.actions', 'Ações') ?></th>
                                        </tr>
                                    </thead>
                                    <tbody id="itens-pedido">
                                        <tr>
                                            <td colspan="7" class="text-center"><?= __('admin.orders.create.no_items', 'Nenhum item adicionado') ?></td>
                                        </tr>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="4"></td>
                                            <td><strong><?= __('common.total', 'Total') ?>:</strong></td>
                                            <td id="total-pedido"><?= __('admin.orders.js.currency_brl', 'R$') ?> 0,00</td>
                                            <td></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                            
                            <div class="row g-3 mt-3">
                                <div class="col-md-8">
                                    <label class="form-label"><?= __('admin.orders.create.search_product', 'Buscar Produto') ?></label>
                                    <div class="input-group">
                                        <input type="text" id="busca-produto" class="form-control" placeholder="<?= htmlspecialchars(__('admin.orders.create.search_product_placeholder', 'Nome ou SKU'), ENT_QUOTES, 'UTF-8') ?>">
                                        <button class="btn btn-outline-secondary" type="button" onclick="buscarProdutos()">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label"><?= __('admin.orders.create.category', 'Categoria') ?></label>
                                    <select id="filtro-categoria" class="form-select">
                                        <option value=""><?= __('common.all', 'Todas') ?></option>
                                        <?php foreach ($categorias as $categoria): ?>
                                            <option value="<?= $categoria['id'] ?>"><?= htmlspecialchars($categoria['nome']) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row g-3 mt-3" id="resultados-busca" style="display: none;">
                                <div class="col-12">
                                    <h6><?= __('admin.orders.create.search_results', 'Resultados da Busca:') ?></h6>
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
                            <label class="form-label"><?= __('checkout.payment_method', 'Forma de Pagamento') ?></label>
                            <select name="forma_pagamento" class="form-select" required>
                                <option value=""><?= __('common.select', 'Selecione...') ?></option>
                                <option value="cartao_credito"><?= __('admin.orders.payment.credit_card', 'Cartão de Crédito') ?></option>
                                <option value="boleto"><?= __('checkout.payment.boleto', 'Boleto') ?></option>
                                <option value="pix"><?= __('admin.orders.payment.pix', 'PIX') ?></option>
                                <option value="transferencia"><?= __('admin.orders.payment.bank_transfer', 'Transferência Bancária') ?></option>
                                <option value="pagamento_entrega"><?= __('admin.orders.payment.pay_on_delivery', 'Pagamento na Entrega') ?></option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= __('admin.orders.create.use_credits', 'Usar Créditos?') ?></label>
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="usar_creditos" id="usar-creditos">
                                <label class="form-check-label" for="usar-creditos">
                                    <?= __('admin.orders.create.use_credits_hint', 'Usar créditos disponíveis como desconto') ?>
                                </label>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label"><?= __('checkout.subtotal', 'Subtotal') ?></label>
                            <div class="input-group">
                                <span class="input-group-text"><?= __('admin.orders.js.currency_brl', 'R$') ?></span>
                                <input type="text" id="subtotal-pedido" class="form-control" value="0.00" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= __('admin.orders.create.discount', 'Desconto') ?></label>
                            <div class="input-group">
                                <span class="input-group-text"><?= __('admin.orders.js.currency_brl', 'R$') ?></span>
                                <input type="text" id="desconto-pedido" class="form-control" value="0.00" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= __('common.total', 'Total') ?></label>
                            <div class="input-group">
                                <span class="input-group-text"><?= __('admin.orders.js.currency_brl', 'R$') ?></span>
                                <input type="text" id="total-final" class="form-control" value="0.00" readonly>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= __('admin.orders.create.total_with_credit', 'Total com Crédito') ?></label>
                            <div class="input-group">
                                <span class="input-group-text"><?= __('admin.orders.js.currency_brl', 'R$') ?></span>
                                <input type="text" id="total-com-credito" class="form-control" value="0.00" readonly>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label"><?= __('admin.orders.create.notes', 'Observações') ?></label>
                            <textarea name="observacoes" class="form-control" rows="3" placeholder="<?= htmlspecialchars(__('admin.orders.create.notes_placeholder', 'Observações do pedido'), ENT_QUOTES, 'UTF-8') ?>"></textarea>
                        </div>
                    </div>
                    
                    <div class="row g-3">
                        <div class="col-12">
                            <button type="button" class="btn btn-primary" onclick="criarPedido()">
                                <i class="fas fa-shopping-cart me-2"></i> <?= __('admin.orders.create.submit', 'Criar Pedido') ?>
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="limparItensPedido()">
                                <i class="fas fa-trash me-2"></i> <?= __('admin.orders.create.clear_items', 'Limpar Itens') ?>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('common.cancel', 'Cancelar') ?></button>
                <button type="button" class="btn btn-primary" onclick="criarPedido()">
                    <i class="fas fa-shopping-cart me-2"></i> <?= __('admin.orders.create.submit', 'Criar Pedido') ?>
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
                <h5 class="modal-title"><?= __('admin.orders.customer_new.title', 'Novo Cliente') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formNovoCliente">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label"><?= __('admin.users.form.full_name', 'Nome Completo *') ?></label>
                            <input type="text" name="nome" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= __('common.email', 'Email') ?> *</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= __('checkout.cpf_cnpj', 'CPF/CNPJ') ?> *</label>
                            <input type="text" name="documento" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= __('common.phone', 'Telefone') ?></label>
                            <input type="text" name="telefone" class="form-control">
                        </div>
                        <div class="col-12">
                            <label class="form-label"><?= __('common.address', 'Endereço') ?></label>
                            <input type="text" name="endereco" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= __('common.city', 'Cidade') ?></label>
                            <input type="text" name="cidade" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= __('common.state', 'Estado') ?></label>
                            <input type="text" name="estado" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= __('checkout.zip_code', 'CEP') ?></label>
                            <input type="text" name="cep" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= __('common.password', 'Senha') ?></label>
                            <input type="password" name="senha" class="form-control">
                        </div>
                        <div class="col-12">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="status" id="cliente-status" checked>
                                <label class="form-check-label" for="cliente-status">
                                    <?= __('admin.orders.js.user_active', 'Usuário Ativo') ?>
                                </label>
                            </div>
                        </div>
                        <div class="col-12">
                            <label class="form-label"><?= __('admin.orders.customer_new.initial_credits', 'Créditos Iniciais') ?></label>
                            <input type="number" name="creditos_iniciais" class="form-control" step="0.01" value="0.00">
                            <div class="form-text"><?= __('admin.orders.customer_new.initial_credits_hint', 'Valor inicial na carteira digital') ?></div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('common.cancel', 'Cancelar') ?></button>
                <button type="button" class="btn btn-primary" onclick="salvarNovoCliente()"><?= __('common.save', 'Salvar') ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Detalhes do Pedido -->
<div class="modal fade" id="modalDetalhes" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= __('admin.orders.details.title', 'Detalhes do Pedido') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="conteudoDetalhes">
                <!-- Conteúdo carregado via AJAX -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('common.close', 'Fechar') ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Atualizar Status -->
<div class="modal fade" id="modalStatus" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><?= __('admin.orders.status_update.title', 'Atualizar Status') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formStatus">
                    <input type="hidden" name="pedido_id" id="pedido_id">
                    <div class="mb-3">
                        <label class="form-label"><?= __('admin.orders.status_update.new_status', 'Novo Status') ?></label>
                        <select name="status" class="form-select" required>
                            <option value=""><?= __('common.select', 'Selecione...') ?></option>
                            <option value="pendente"><?= __('admin.orders.status.pending', 'Pendente') ?></option>
                            <option value="pago"><?= __('admin.orders.status.paid', 'Pago') ?></option>
                            <option value="processando"><?= __('admin.orders.status.processing', 'Processando') ?></option>
                            <option value="enviado"><?= __('admin.orders.status.shipped', 'Enviado') ?></option>
                            <option value="entregue"><?= __('admin.orders.status.delivered', 'Entregue') ?></option>
                            <option value="cancelado"><?= __('admin.orders.status.cancelled', 'Cancelado') ?></option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label"><?= __('admin.orders.create.notes', 'Observações') ?></label>
                        <textarea name="observacoes" class="form-control" rows="3"></textarea>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('common.cancel', 'Cancelar') ?></button>
                <button type="button" class="btn btn-primary" onclick="salvarStatus()"><?= __('common.save', 'Salvar') ?></button>
            </div>
        </div>
    </div>
</div>

<script>
window.ADMIN_ORDERS_I18N = {
    locale: <?= json_encode(\App\Core\I18n::getLocaleHtml(), JSON_UNESCAPED_UNICODE) ?>,
    invalid_user_id: <?= json_encode(__('admin.orders.js.invalid_user_id', 'ID do usuário inválido. ID recebido: {id} (tipo: {type})'), JSON_UNESCAPED_UNICODE) ?>,
    edit_user: <?= json_encode(__('admin.users.modal.edit_title', 'Editar Usuário'), JSON_UNESCAPED_UNICODE) ?>,
    full_name_required: <?= json_encode(__('admin.users.form.full_name', 'Nome Completo *'), JSON_UNESCAPED_UNICODE) ?>,
    cpf_cnpj: <?= json_encode(__('checkout.cpf_cnpj', 'CPF/CNPJ'), JSON_UNESCAPED_UNICODE) ?>,
    zip: <?= json_encode(__('checkout.zip_code', 'CEP'), JSON_UNESCAPED_UNICODE) ?>,
    email: <?= json_encode(__('common.email', 'Email'), JSON_UNESCAPED_UNICODE) ?>,
    phone: <?= json_encode(__('common.phone', 'Telefone'), JSON_UNESCAPED_UNICODE) ?>,
    address: <?= json_encode(__('common.address', 'Endereço'), JSON_UNESCAPED_UNICODE) ?>,
    city: <?= json_encode(__('common.city', 'Cidade'), JSON_UNESCAPED_UNICODE) ?>,
    state: <?= json_encode(__('common.state', 'Estado'), JSON_UNESCAPED_UNICODE) ?>,
    profile: <?= json_encode(__('admin.users.profile', 'Perfil'), JSON_UNESCAPED_UNICODE) ?>,
    profile_client: <?= json_encode(__('admin.users.profile.client', 'Cliente'), JSON_UNESCAPED_UNICODE) ?>,
    profile_admin: <?= json_encode(__('admin.users.profile.admin', 'Administrador'), JSON_UNESCAPED_UNICODE) ?>,
    user_active: <?= json_encode(__('admin.orders.js.user_active', 'Usuário Ativo'), JSON_UNESCAPED_UNICODE) ?>,
    credits_available: <?= json_encode(__('admin.users.credits_available', 'Créditos Disponíveis'), JSON_UNESCAPED_UNICODE) ?>,
    credits_hint: <?= json_encode(__('admin.users.credits_hint', 'Saldo atual de créditos do usuário'), JSON_UNESCAPED_UNICODE) ?>,
    cancel: <?= json_encode(__('common.cancel', 'Cancelar'), JSON_UNESCAPED_UNICODE) ?>,
    save: <?= json_encode(__('common.save', 'Salvar'), JSON_UNESCAPED_UNICODE) ?>,
    error_open_modal_check_console: <?= json_encode(__('admin.orders.js.error_open_modal_check_console', 'Erro ao abrir modal. Verifique o console para mais detalhes.'), JSON_UNESCAPED_UNICODE) ?>,
    error_modal_not_found: <?= json_encode(__('admin.orders.js.error_modal_not_found', 'Erro: Elemento do modal não encontrado no DOM.'), JSON_UNESCAPED_UNICODE) ?>,
    error_load_user_prefix: <?= json_encode(__('admin.users.js.error_load_user_prefix', 'Erro ao carregar usuário:'), JSON_UNESCAPED_UNICODE) ?>,
    error_unknown_check_console: <?= json_encode(__('admin.users.js.error_unknown_check_console', 'Erro desconhecido - verifique o console'), JSON_UNESCAPED_UNICODE) ?>,
    error_network_check_connection: <?= json_encode(__('admin.users.js.error_network_check_connection', 'Erro de rede. Verifique a conexão com o servidor.'), JSON_UNESCAPED_UNICODE) ?>,
    error_syntax_response: <?= json_encode(__('admin.users.js.error_syntax_response', 'Erro de sintaxe na resposta do servidor.'), JSON_UNESCAPED_UNICODE) ?>,
    error_load_user_check_console: <?= json_encode(__('admin.users.js.error_load_user_check_console', 'Erro ao carregar usuário. Verifique o console para mais detalhes.'), JSON_UNESCAPED_UNICODE) ?>,
    customer_created_success: <?= json_encode(__('admin.orders.js.customer_created_success', 'Cliente criado com sucesso!'), JSON_UNESCAPED_UNICODE) ?>,
    error_create_customer_prefix: <?= json_encode(__('admin.orders.js.error_create_customer_prefix', 'Erro ao criar cliente:'), JSON_UNESCAPED_UNICODE) ?>,
    search_require_term_or_category: <?= json_encode(__('admin.orders.js.search_require_term_or_category', 'Digite um termo ou selecione uma categoria para buscar.'), JSON_UNESCAPED_UNICODE) ?>,
    no_product_found: <?= json_encode(__('admin.orders.js.no_product_found', 'Nenhum produto encontrado.'), JSON_UNESCAPED_UNICODE) ?>,
    product_already_added: <?= json_encode(__('admin.orders.js.product_already_added', 'Este produto já foi adicionado ao pedido.'), JSON_UNESCAPED_UNICODE) ?>,
    insufficient_stock_prefix: <?= json_encode(__('admin.orders.js.insufficient_stock_prefix', 'Estoque insuficiente. Estoque disponível:'), JSON_UNESCAPED_UNICODE) ?>,
    qty_gt_stock_prefix: <?= json_encode(__('admin.orders.js.qty_gt_stock_prefix', 'Quantidade maior que o estoque disponível:'), JSON_UNESCAPED_UNICODE) ?>,
    select_customer_to_continue: <?= json_encode(__('admin.orders.js.select_customer_to_continue', 'Selecione um cliente para continuar.'), JSON_UNESCAPED_UNICODE) ?>,
    add_product_to_continue: <?= json_encode(__('admin.orders.js.add_product_to_continue', 'Adicione pelo menos um produto ao pedido.'), JSON_UNESCAPED_UNICODE) ?>,
    order_created_success_prefix: <?= json_encode(__('admin.orders.js.order_created_success_prefix', 'Pedido criado com sucesso! ID:'), JSON_UNESCAPED_UNICODE) ?>,
    error_create_order_prefix: <?= json_encode(__('admin.orders.js.error_create_order_prefix', 'Erro ao criar pedido:'), JSON_UNESCAPED_UNICODE) ?>,
    status_updated_success: <?= json_encode(__('admin.orders.js.status_updated_success', 'Status atualizado com sucesso!'), JSON_UNESCAPED_UNICODE) ?>,
    error_update_status_prefix: <?= json_encode(__('admin.orders.js.error_update_status_prefix', 'Erro ao atualizar status:'), JSON_UNESCAPED_UNICODE) ?>,
    credits_added_success: <?= json_encode(__('admin.orders.js.credits_added_success', 'Créditos adicionados com sucesso!'), JSON_UNESCAPED_UNICODE) ?>,
    error_add_credits_prefix: <?= json_encode(__('admin.orders.js.error_add_credits_prefix', 'Erro ao adicionar créditos:'), JSON_UNESCAPED_UNICODE) ?>,
    credit_details_title: <?= json_encode(__('admin.orders.js.credit_details_title', 'Detalhes do Crédito'), JSON_UNESCAPED_UNICODE) ?>,
    credit_details_user: <?= json_encode(__('admin.orders.js.credit_details_user', 'Usuário:'), JSON_UNESCAPED_UNICODE) ?>,
    credit_details_value: <?= json_encode(__('admin.orders.js.credit_details_value', 'Valor:'), JSON_UNESCAPED_UNICODE) ?>,
    credit_details_date: <?= json_encode(__('admin.orders.js.credit_details_date', 'Data:'), JSON_UNESCAPED_UNICODE) ?>,
    credit_details_status: <?= json_encode(__('admin.orders.js.credit_details_status', 'Status:'), JSON_UNESCAPED_UNICODE) ?>,
    credit_details_description: <?= json_encode(__('admin.orders.js.credit_details_description', 'Descrição:'), JSON_UNESCAPED_UNICODE) ?>,
    credit_details_valid_until: <?= json_encode(__('admin.orders.js.credit_details_valid_until', 'Válido até:'), JSON_UNESCAPED_UNICODE) ?>,
    days_suffix: <?= json_encode(__('admin.orders.js.days_suffix', 'dias'), JSON_UNESCAPED_UNICODE) ?>,
    confirm_delete_user: <?= json_encode(__('admin.users.js.confirm_delete_user', 'Deseja realmente excluir este usuário? Esta ação não pode ser desfeita!'), JSON_UNESCAPED_UNICODE) ?>,
    user_deleted_success: <?= json_encode(__('admin.users.js.user_deleted_success', 'Usuário excluído com sucesso!'), JSON_UNESCAPED_UNICODE) ?>,
    error_delete_user_prefix: <?= json_encode(__('admin.users.js.error_delete_user_prefix', 'Erro ao excluir usuário:'), JSON_UNESCAPED_UNICODE) ?>,
    select_user_placeholder: <?= json_encode(__('admin.orders.js.select_user_placeholder', 'Selecione um usuário...'), JSON_UNESCAPED_UNICODE) ?>,
    sku: <?= json_encode(__('admin.orders.js.sku', 'SKU'), JSON_UNESCAPED_UNICODE) ?>,
    price: <?= json_encode(__('admin.orders.js.price', 'Preço'), JSON_UNESCAPED_UNICODE) ?>,
    stock: <?= json_encode(__('admin.orders.js.stock', 'Estoque'), JSON_UNESCAPED_UNICODE) ?>,
    add: <?= json_encode(__('common.add', 'Adicionar'), JSON_UNESCAPED_UNICODE) ?>,
    remove: <?= json_encode(__('common.remove', 'Remover'), JSON_UNESCAPED_UNICODE) ?>,
    currency_brl: <?= json_encode(__('admin.orders.js.currency_brl', 'R$'), JSON_UNESCAPED_UNICODE) ?>,
    error_load_users_console: <?= json_encode(__('admin.orders.js.error_load_users_console', 'Erro ao carregar usuários:'), JSON_UNESCAPED_UNICODE) ?>,
    error_load_credit_logs_console: <?= json_encode(__('admin.orders.js.error_load_credit_logs_console', 'Erro ao carregar logs de créditos:'), JSON_UNESCAPED_UNICODE) ?>
};

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
        alert(((window.ADMIN_ORDERS_I18N && window.ADMIN_ORDERS_I18N.invalid_user_id) ? window.ADMIN_ORDERS_I18N.invalid_user_id : 'ID do usuário inválido. ID recebido: {id} (tipo: {type})').replace('{id}', id).replace('{type}', typeof id));
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
                                    <h5 class="modal-title">${(window.ADMIN_ORDERS_I18N && window.ADMIN_ORDERS_I18N.edit_user) ? window.ADMIN_ORDERS_I18N.edit_user : 'Editar Usuário'}</h5>
                                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <form id="formEditarUsuario">
                                        <input type="hidden" name="id" value="${data.usuario.id}">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label">${(window.ADMIN_ORDERS_I18N && window.ADMIN_ORDERS_I18N.full_name_required) ? window.ADMIN_ORDERS_I18N.full_name_required : 'Nome Completo *'}</label>
                                                <input type="text" name="nome" class="form-control" value="${data.usuario.nome || ''}" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">${(window.ADMIN_ORDERS_I18N && window.ADMIN_ORDERS_I18N.email) ? window.ADMIN_ORDERS_I18N.email : 'Email'} *</label>
                                                <input type="email" name="email" class="form-control" value="${data.usuario.email || ''}" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">${(window.ADMIN_ORDERS_I18N && window.ADMIN_ORDERS_I18N.cpf_cnpj) ? window.ADMIN_ORDERS_I18N.cpf_cnpj : 'CPF/CNPJ'}</label>
                                                <input type="text" name="documento" class="form-control" value="${data.usuario.documento || ''}">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">${(window.ADMIN_ORDERS_I18N && window.ADMIN_ORDERS_I18N.phone) ? window.ADMIN_ORDERS_I18N.phone : 'Telefone'}</label>
                                                <input type="text" name="telefone" class="form-control" value="${data.usuario.telefone || ''}">
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label">${(window.ADMIN_ORDERS_I18N && window.ADMIN_ORDERS_I18N.address) ? window.ADMIN_ORDERS_I18N.address : 'Endereço'}</label>
                                                <input type="text" name="endereco" class="form-control" value="${data.usuario.endereco || ''}">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">${(window.ADMIN_ORDERS_I18N && window.ADMIN_ORDERS_I18N.city) ? window.ADMIN_ORDERS_I18N.city : 'Cidade'}</label>
                                                <input type="text" name="cidade" class="form-control" value="${data.usuario.cidade || ''}">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">${(window.ADMIN_ORDERS_I18N && window.ADMIN_ORDERS_I18N.state) ? window.ADMIN_ORDERS_I18N.state : 'Estado'}</label>
                                                <input type="text" name="estado" class="form-control" value="${data.usuario.estado || ''}">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">${(window.ADMIN_ORDERS_I18N && window.ADMIN_ORDERS_I18N.zip) ? window.ADMIN_ORDERS_I18N.zip : 'CEP'}</label>
                                                <input type="text" name="cep" class="form-control" value="${data.usuario.cep || ''}">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">${(window.ADMIN_ORDERS_I18N && window.ADMIN_ORDERS_I18N.profile) ? window.ADMIN_ORDERS_I18N.profile : 'Perfil'}</label>
                                                <select name="perfil" class="form-select">
                                                    <option value="cliente" ${data.usuario.perfil == 'cliente' ? 'selected' : ''}>${(window.ADMIN_ORDERS_I18N && window.ADMIN_ORDERS_I18N.profile_client) ? window.ADMIN_ORDERS_I18N.profile_client : 'Cliente'}</option>
                                                    <option value="admin" ${data.usuario.perfil == 'admin' ? 'selected' : ''}>${(window.ADMIN_ORDERS_I18N && window.ADMIN_ORDERS_I18N.profile_admin) ? window.ADMIN_ORDERS_I18N.profile_admin : 'Administrador'}</option>
                                                </select>
                                            </div>
                                            <div class="col-12">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="checkbox" name="status" id="usuario-editar-status" ${data.usuario.status == 'ativo' ? 'checked' : ''}>
                                                    <label class="form-check-label" for="usuario-editar-status">
                                                        ${(window.ADMIN_ORDERS_I18N && window.ADMIN_ORDERS_I18N.user_active) ? window.ADMIN_ORDERS_I18N.user_active : 'Usuário Ativo'}
                                                    </label>
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label">${(window.ADMIN_ORDERS_I18N && window.ADMIN_ORDERS_I18N.credits_available) ? window.ADMIN_ORDERS_I18N.credits_available : 'Créditos Disponíveis'}</label>
                                                <div class="input-group">
                                                    <span class="input-group-text">R$</span>
                                                    <input type="number" name="creditos_disponiveis" class="form-control" step="0.01" value="${data.usuario.creditos_disponiveis || 0}">
                                                </div>
                                                <div class="form-text">${(window.ADMIN_ORDERS_I18N && window.ADMIN_ORDERS_I18N.credits_hint) ? window.ADMIN_ORDERS_I18N.credits_hint : 'Saldo atual de créditos do usuário'}</div>
                                            </div>
                                        </div>
                                    </form>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">${(window.ADMIN_ORDERS_I18N && window.ADMIN_ORDERS_I18N.cancel) ? window.ADMIN_ORDERS_I18N.cancel : 'Cancelar'}</button>
                                    <button type="button" class="btn btn-primary" onclick="salvarEdicaoUsuario()">${(window.ADMIN_ORDERS_I18N && window.ADMIN_ORDERS_I18N.save) ? window.ADMIN_ORDERS_I18N.save : 'Salvar'}</button>
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
                        alert((window.ADMIN_ORDERS_I18N && window.ADMIN_ORDERS_I18N.error_open_modal_check_console) ? window.ADMIN_ORDERS_I18N.error_open_modal_check_console : 'Erro ao abrir modal. Verifique o console para mais detalhes.');
                    }
                } else {
                    console.error('❌ [ERRO DOM] Elemento do modal não encontrado no DOM');
                    console.log('🔍 [DOM] Elementos com ID modalEditarUsuario:', document.querySelectorAll('[id="modalEditarUsuario"]'));
                    alert((window.ADMIN_ORDERS_I18N && window.ADMIN_ORDERS_I18N.error_modal_not_found) ? window.ADMIN_ORDERS_I18N.error_modal_not_found : 'Erro: Elemento do modal não encontrado no DOM.');
                }
            } else {
                console.error('❌ [ERRO] Resposta sem sucesso:', data);
                console.error('❌ [ERRO] Mensagem de erro:', data.error);
                alert(((window.ADMIN_ORDERS_I18N && window.ADMIN_ORDERS_I18N.error_load_user_prefix) ? window.ADMIN_ORDERS_I18N.error_load_user_prefix : 'Erro ao carregar usuário:') + ' ' + (data.error || ((window.ADMIN_ORDERS_I18N && window.ADMIN_ORDERS_I18N.error_unknown_check_console) ? window.ADMIN_ORDERS_I18N.error_unknown_check_console : 'Erro desconhecido - verifique o console')));
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
                alert((window.ADMIN_ORDERS_I18N && window.ADMIN_ORDERS_I18N.error_network_check_connection) ? window.ADMIN_ORDERS_I18N.error_network_check_connection : 'Erro de rede. Verifique a conexão com o servidor.');
            } else if (error.name === 'SyntaxError') {
                console.error('❌ [ERRO SINTAX] Erro de sintaxe na resposta JSON');
                alert((window.ADMIN_ORDERS_I18N && window.ADMIN_ORDERS_I18N.error_syntax_response) ? window.ADMIN_ORDERS_I18N.error_syntax_response : 'Erro de sintaxe na resposta do servidor.');
            } else {
                alert((window.ADMIN_ORDERS_I18N && window.ADMIN_ORDERS_I18N.error_load_user_check_console) ? window.ADMIN_ORDERS_I18N.error_load_user_check_console : 'Erro ao carregar usuário. Verifique o console para mais detalhes.');
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
            alert((window.ADMIN_ORDERS_I18N && window.ADMIN_ORDERS_I18N.customer_created_success) ? window.ADMIN_ORDERS_I18N.customer_created_success : 'Cliente criado com sucesso!');
            new bootstrap.Modal(document.getElementById('modalNovoCliente')).hide();
            document.getElementById('modalNovoCliente').remove();
            
            // Adicionar o novo cliente ao select do pedido
            const select = document.querySelector('form[name="formCriarPedido"] select[name="usuario_id"]');
            if (select) {
                const option = document.createElement('option');
                option.value = data.usuario.id;
                option.textContent = `${data.usuario.nome} (${data.usuario.email})`;
                select.appendChild(option);
            }
            
            // Selecionar automaticamente o novo cliente
            select.value = data.usuario.id;
            carregarDadosCliente(data.usuario.id);
        } else {
            alert(((window.ADMIN_ORDERS_I18N && window.ADMIN_ORDERS_I18N.error_create_customer_prefix) ? window.ADMIN_ORDERS_I18N.error_create_customer_prefix : 'Erro ao criar cliente:') + ' ' + data.error);
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
                const brl = (window.ADMIN_ORDERS_I18N && window.ADMIN_ORDERS_I18N.currency_brl) ? window.ADMIN_ORDERS_I18N.currency_brl : 'R$';
                document.getElementById('cliente-creditos').textContent = `${brl} ${number_format(data.usuario.creditos_disponiveis, 2, ',', '.')}`;
                
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
        alert((window.ADMIN_ORDERS_I18N && window.ADMIN_ORDERS_I18N.search_require_term_or_category) ? window.ADMIN_ORDERS_I18N.search_require_term_or_category : 'Digite um termo ou selecione uma categoria para buscar.');
        return;
    }
    
    fetch(`/admin/buscar-produtos?termo=${encodeURIComponent(termo)}&categoria_id=${categoriaId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                produtosDisponiveis = data.produtos;
                exibirResultadosBusca();
            } else {
                alert((window.ADMIN_ORDERS_I18N && window.ADMIN_ORDERS_I18N.no_product_found) ? window.ADMIN_ORDERS_I18N.no_product_found : 'Nenhum produto encontrado.');
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
        const skuLbl = (window.ADMIN_ORDERS_I18N && window.ADMIN_ORDERS_I18N.sku) ? window.ADMIN_ORDERS_I18N.sku : 'SKU';
        const priceLbl = (window.ADMIN_ORDERS_I18N && window.ADMIN_ORDERS_I18N.price) ? window.ADMIN_ORDERS_I18N.price : 'Preço';
        const stockLbl = (window.ADMIN_ORDERS_I18N && window.ADMIN_ORDERS_I18N.stock) ? window.ADMIN_ORDERS_I18N.stock : 'Estoque';
        const addLbl = (window.ADMIN_ORDERS_I18N && window.ADMIN_ORDERS_I18N.add) ? window.ADMIN_ORDERS_I18N.add : 'Adicionar';
        const brl = (window.ADMIN_ORDERS_I18N && window.ADMIN_ORDERS_I18N.currency_brl) ? window.ADMIN_ORDERS_I18N.currency_brl : 'R$';
        produtoDiv.innerHTML = `
            <div class="card">
                <div class="card-body">
                    <h6>${produto.nome}</h6>
                    <p><strong>${skuLbl}:</strong> ${produto.sku}</p>
                    <p><strong>${priceLbl}:</strong> ${brl} ${number_format(produto.valor, 2, ',', '.')}</p>
                    <p><strong>${stockLbl}:</strong> ${produto.estoque}</p>
                    <button type="button" class="btn btn-sm btn-primary btn-block" onclick="adicionarItemPedido(${produto.id}, '${produto.nome}', ${produto.valor}, ${produto.estoque})">
                        <i class="fas fa-plus me-2"></i> ${addLbl}
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
        alert((window.ADMIN_ORDERS_I18N && window.ADMIN_ORDERS_I18N.product_already_added) ? window.ADMIN_ORDERS_I18N.product_already_added : 'Este produto já foi adicionado ao pedido.');
        return;
    }
    
    // Verificar estoque
    if (quantidade > estoque) {
        alert(((window.ADMIN_ORDERS_I18N && window.ADMIN_ORDERS_I18N.insufficient_stock_prefix) ? window.ADMIN_ORDERS_I18N.insufficient_stock_prefix : 'Estoque insuficiente. Estoque disponível:') + ' ' + estoque);
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

    const brl = (window.ADMIN_ORDERS_I18N && window.ADMIN_ORDERS_I18N.currency_brl) ? window.ADMIN_ORDERS_I18N.currency_brl : 'R$';
    const removeLbl = (window.ADMIN_ORDERS_I18N && window.ADMIN_ORDERS_I18N.remove) ? window.ADMIN_ORDERS_I18N.remove : 'Remover';
    
    itensPedido.forEach((item, index) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${item.nome}</td>
            <td>${brl} ${number_format(item.preco, 2, ',', '.')}</td>
            <td>${item.estoque}</td>
            <td>
                <input type="number" type="number" min="1" max="${item.estoque}" value="${item.quantidade}" onchange="atualizarQuantidade(${index})" class="form-control form-control-sm" style="width: 80px;">
            </td>
            <td>${brl} <span id="subtotal-${index}">${number_format(item.subtotal, 2, ',', '.')}</span></td>
            <td>
                <button type="button" class="btn btn-sm btn-danger btn-sm" onclick="removerItem(${index})" title="${removeLbl}">
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
        alert(((window.ADMIN_ORDERS_I18N && window.ADMIN_ORDERS_I18N.qty_gt_stock_prefix) ? window.ADMIN_ORDERS_I18N.qty_gt_stock_prefix : 'Quantidade maior que o estoque disponível:') + ' ' + estoque);
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
    const brl = (window.ADMIN_ORDERS_I18N && window.ADMIN_ORDERS_I18N.currency_brl) ? window.ADMIN_ORDERS_I18N.currency_brl : 'R$';
    document.querySelector('#total-pedido').textContent = brl + ' ' + number_format(total, 2, ',');
}

function criarPedido() {
    if (!clienteAtual) {
        alert((window.ADMIN_ORDERS_I18N && window.ADMIN_ORDERS_I18N.select_customer_to_continue) ? window.ADMIN_ORDERS_I18N.select_customer_to_continue : 'Selecione um cliente para continuar.');
        return;
    }
    
    if (itensPedido.length === 0) {
        alert((window.ADMIN_ORDERS_I18N && window.ADMIN_ORDERS_I18N.add_product_to_continue) ? window.ADMIN_ORDERS_I18N.add_product_to_continue : 'Adicione pelo menos um produto ao pedido.');
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
            alert(((window.ADMIN_ORDERS_I18N && window.ADMIN_ORDERS_I18N.order_created_success_prefix) ? window.ADMIN_ORDERS_I18N.order_created_success_prefix : 'Pedido criado com sucesso! ID:') + ' ' + data.pedido_id);
            new bootstrap.Modal(document.getElementById('modalCriarPedido')).hide();
            document.getElementById('formCriarPedido').reset();
            itensPedido = [];
            clienteAtual = null;
            location.reload();
        } else {
            alert(((window.ADMIN_ORDERS_I18N && window.ADMIN_ORDERS_I18N.error_create_order_prefix) ? window.ADMIN_ORDERS_I18N.error_create_order_prefix : 'Erro ao criar pedido:') + ' ' + data.error);
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
            alert((window.ADMIN_ORDERS_I18N && window.ADMIN_ORDERS_I18N.status_updated_success) ? window.ADMIN_ORDERS_I18N.status_updated_success : 'Status atualizado com sucesso!');
            location.reload();
        } else {
            alert(((window.ADMIN_ORDERS_I18N && window.ADMIN_ORDERS_I18N.error_update_status_prefix) ? window.ADMIN_ORDERS_I18N.error_update_status_prefix : 'Erro ao atualizar status:') + ' ' + data.error);
        }
    });
}

function gerarEtiqueta(pedidoId) {
    window.open(`/admin/gerar-etiqueta/${pedidoId}`, '_blank');
}

function excluirUsuario(id) {
    if (confirm((window.ADMIN_ORDERS_I18N && window.ADMIN_ORDERS_I18N.confirm_delete_user) ? window.ADMIN_ORDERS_I18N.confirm_delete_user : 'Deseja realmente excluir este usuário? Esta ação não pode ser desfeita!')) {
        fetch(`/admin/excluir-usuario/${id}`, {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert((window.ADMIN_ORDERS_I18N && window.ADMIN_ORDERS_I18N.user_deleted_success) ? window.ADMIN_ORDERS_I18N.user_deleted_success : 'Usuário excluído com sucesso!');
                carregarUsuarios();
            } else {
                alert(((window.ADMIN_ORDERS_I18N && window.ADMIN_ORDERS_I18N.error_delete_user_prefix) ? window.ADMIN_ORDERS_I18N.error_delete_user_prefix : 'Erro ao excluir usuário:') + ' ' + data.error);
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
            const select = document.querySelector('form[name="formCreditos"] select[name="usuario_id"]');
            if (select) {
                select.innerHTML = '<option value="">' + ((window.ADMIN_ORDERS_I18N && window.ADMIN_ORDERS_I18N.select_user_placeholder) ? window.ADMIN_ORDERS_I18N.select_user_placeholder : 'Selecione um usuário...') + '</option>';
                
                data.usuarios.forEach(usuario => {
                    const option = document.createElement('option');
                    option.value = usuario.id;
                    option.textContent = `${usuario.nome} (${usuario.email})`;
                    select.appendChild(option);
                });
            }
        })
        .catch(error => {
            console.error(((window.ADMIN_ORDERS_I18N && window.ADMIN_ORDERS_I18N.error_load_users_console) ? window.ADMIN_ORDERS_I18N.error_load_users_console : 'Erro ao carregar usuários:'), error);
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
            alert((window.ADMIN_ORDERS_I18N && window.ADMIN_ORDERS_I18N.credits_added_success) ? window.ADMIN_ORDERS_I18N.credits_added_success : 'Créditos adicionados com sucesso!');
            new bootstrap.Modal(document.getElementById('modalCreditos')).hide();
            carregarLogsCreditos();
            carregarUsuarios();
        } else {
            alert(((window.ADMIN_ORDERS_I18N && window.ADMIN_ORDERS_I18N.error_add_credits_prefix) ? window.ADMIN_ORDERS_I18N.error_add_credits_prefix : 'Erro ao adicionar créditos:') + ' ' + data.error);
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
                const locale = (window.ADMIN_ORDERS_I18N && window.ADMIN_ORDERS_I18N.locale) ? window.ADMIN_ORDERS_I18N.locale : 'pt-BR';
                const brl = (window.ADMIN_ORDERS_I18N && window.ADMIN_ORDERS_I18N.currency_brl) ? window.ADMIN_ORDERS_I18N.currency_brl : 'R$';
                tr.innerHTML = `
                    <td>${log.id}</td>
                    <td>${log.usuario_nome}</td>
                    <td>${brl} ${number_format(log.valor, 2, ',', '.')}</td>
                    <td>${new Date(log.data_criacao).toLocaleString(locale)}</td>
                    <td><span class="badge" style="background: ${log.status == 'ativo' ? 'rgba(16, 185, 129, 0.12)' : 'rgba(239, 68, 68, 0.12)'}; border: 1px solid ${log.status == 'ativo' ? 'rgba(16, 185, 129, 0.22)' : 'rgba(239, 68, 68, 0.22)'}; color: ${log.status == 'ativo' ? '#065f46' : '#7f1d1d'};">${log.status}</span></td>
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
            console.error(((window.ADMIN_ORDERS_I18N && window.ADMIN_ORDERS_I18N.error_load_credit_logs_console) ? window.ADMIN_ORDERS_I18N.error_load_credit_logs_console : 'Erro ao carregar logs de créditos:'), error);
        });
}

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
