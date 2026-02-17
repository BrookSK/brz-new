<?php ob_start(); ?>

<?php
function getUserProfileLabel($perfil) {
    $labels = [
        'admin' => __('admin.users.profile.admin', 'Administrador'),
        'cliente' => __('admin.users.profile.client', 'Cliente')
    ];
    return $labels[(string) $perfil] ?? (string) $perfil;
}

function getUserStatusLabel($status) {
    $labels = [
        'ativo' => __('admin.users.status.active', 'Ativo'),
        'inativo' => __('admin.users.status.inactive', 'Inativo'),
        'bloqueado' => __('admin.users.status.blocked', 'Bloqueado')
    ];
    return $labels[(string) $status] ?? (string) $status;
}
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1 class="h3 mb-0">
            <i class="fas fa-users me-2"></i>
            <?= __('admin.users.title', 'Gerenciamento de Usuários') ?>
        </h1>
        <div>
            <a href="/admin" class="btn btn-secondary">
                <i class="fas fa-arrow-left me-2"></i><?= __('common.back', 'Voltar') ?>
            </a>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#modalUsuario">
                <i class="fas fa-plus me-2"></i><?= __('admin.users.new', 'Novo Usuário') ?>
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
                        <option value="ativo" <?= ($status ?? '') == 'ativo' ? 'selected' : '' ?>><?= __('admin.users.status.active', 'Ativo') ?></option>
                        <option value="inativo" <?= ($status ?? '') == 'inativo' ? 'selected' : '' ?>><?= __('admin.users.status.inactive', 'Inativo') ?></option>
                        <option value="bloqueado" <?= ($status ?? '') == 'bloqueado' ? 'selected' : '' ?>><?= __('admin.users.status.blocked', 'Bloqueado') ?></option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label"><?= __('admin.users.profile', 'Perfil') ?></label>
                    <select name="perfil" class="form-select">
                        <option value=""><?= __('common.all', 'Todos') ?></option>
                        <option value="admin" <?= ($perfil ?? '') == 'admin' ? 'selected' : '' ?>><?= __('admin.users.profile.admin', 'Administrador') ?></option>
                        <option value="cliente" <?= ($perfil ?? '') == 'cliente' ? 'selected' : '' ?>><?= __('admin.users.profile.client', 'Cliente') ?></option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label"><?= __('common.search', 'Buscar') ?></label>
                    <input type="text" name="busca" class="form-control" placeholder="<?= htmlspecialchars(__('admin.users.search_placeholder', 'Nome, email ou CPF'), ENT_QUOTES, 'UTF-8') ?>" value="<?= $busca ?? '' ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search me-2"></i><?= __('common.filter', 'Filtrar') ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Lista de Usuários -->
    <div class="card border-0 shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th><?= __('admin.users.table.id', 'ID') ?></th>
                            <th><?= __('common.name', 'Nome') ?></th>
                            <th><?= __('common.email', 'E-mail') ?></th>
                            <th><?= __('checkout.cpf_cnpj', 'CPF/CNPJ') ?></th>
                            <th><?= __('common.phone', 'Telefone') ?></th>
                            <th><?= __('admin.users.profile', 'Perfil') ?></th>
                            <th><?= __('common.status', 'Status') ?></th>
                            <th><?= __('admin.users.created_at', 'Cadastro') ?></th>
                            <th><?= __('common.actions', 'Ações') ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($usuarios)): ?>
                            <tr>
                                <td colspan="9" class="text-center"><?= __('admin.users.empty', 'Nenhum usuário encontrado.') ?></td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($usuarios as $usuario): ?>
                                <tr>
                                    <td><?= $usuario['id'] ?></td>
                                    <td>
                                        <div>
                                            <strong><?= htmlspecialchars($usuario['nome']) ?></strong>
                                            <small class="text-muted d-block"><?= htmlspecialchars($usuario['email']) ?></small>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($usuario['documento']) ?></td>
                                    <td><?= htmlspecialchars($usuario['telefone']) ?></td>
                                    <td>
                                        <?php $perfilStyle = ($usuario['perfil'] == 'admin')
                                            ? 'background: rgba(239, 68, 68, 0.12); border: 1px solid rgba(239, 68, 68, 0.22); color: #7f1d1d;'
                                            : 'background: rgba(11, 31, 58, 0.08); border: 1px solid rgba(11, 31, 58, 0.14); color: rgba(11, 31, 58, 1);'; ?>
                                        <span class="badge" style="<?= $perfilStyle ?>">
                                            <?= getUserProfileLabel($usuario['perfil']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php
                                            $statusStyle = 'background: rgba(148, 163, 184, 0.16); border: 1px solid rgba(148, 163, 184, 0.28); color: #334155;';
                                            if (($usuario['status'] ?? '') == 'ativo') {
                                                $statusStyle = 'background: rgba(16, 185, 129, 0.12); border: 1px solid rgba(16, 185, 129, 0.22); color: #065f46;';
                                            } elseif (($usuario['status'] ?? '') == 'bloqueado') {
                                                $statusStyle = 'background: rgba(245, 158, 11, 0.12); border: 1px solid rgba(245, 158, 11, 0.22); color: #7c2d12;';
                                            } elseif (($usuario['status'] ?? '') == 'inativo') {
                                                $statusStyle = 'background: rgba(148, 163, 184, 0.16); border: 1px solid rgba(148, 163, 184, 0.28); color: #334155;';
                                            }
                                        ?>
                                        <span class="badge" style="<?= $statusStyle ?>">
                                            <?= getUserStatusLabel($usuario['status']) ?>
                                        </span>
                                    </td>
                                    <td><?= date('d/m/Y H:i', strtotime($usuario['created_at'] ?? $usuario['data_criacao'] ?? 'now')) ?></td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-sm btn-outline-primary" onclick="editarUsuario(<?= $usuario['id'] ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-<?= $usuario['status'] == 'ativo' ? 'warning' : 'success' ?>" onclick="alterarStatus(<?= $usuario['id'] ?>)">
                                                <i class="fas fa-<?= $usuario['status'] == 'ativo' ? 'ban' : 'check' ?>"></i>
                                            </button>
                                            <button type="button" class="btn btn-sm btn-outline-danger" onclick="excluirUsuario(<?= $usuario['id'] ?>)">
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
            <?php if (($totalPaginas ?? 0) > 1): ?>
                <nav aria-label="<?= htmlspecialchars(__('common.pagination', 'Paginação'), ENT_QUOTES, 'UTF-8') ?>">
                    <ul class="pagination justify-content-center">
                        <?php for ($i = 1; $i <= $totalPaginas; $i++): ?>
                            <li class="page-item <?= $i == ($pagina ?? 1) ? 'active' : '' ?>">
                                <a class="page-link" href="/admin/usuarios?pagina=<?= $i ?><?= ($status ?? '') ? '&status=' . $status : '' ?><?= ($perfil ?? '') ? '&perfil=' . $perfil : '' ?><?= ($busca ?? '') ? '&busca=' . urlencode($busca) : '' ?>">
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

<!-- Modal Usuário -->
<div class="modal fade" id="modalUsuario" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalUsuarioTitle"><?= __('admin.users.modal.new_title', 'Novo Usuário') ?></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="formUsuario">
                    <input type="hidden" name="id" id="usuario_id">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label"><?= __('admin.users.form.full_name', 'Nome Completo *') ?></label>
                            <input type="text" name="nome" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= __('common.email', 'E-mail') ?> *</label>
                            <input type="email" name="email" class="form-control" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= __('checkout.cpf_cnpj', 'CPF/CNPJ') ?></label>
                            <input type="text" name="documento" class="form-control">
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
                        <div class="col-md-6">
                            <label class="form-label"><?= __('common.password_confirm', 'Confirmar Senha') ?></label>
                            <input type="password" name="senha_confirmacao" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= __('admin.users.profile', 'Perfil') ?></label>
                            <select name="perfil" class="form-select" required>
                                <option value="cliente"><?= __('admin.users.profile.client', 'Cliente') ?></option>
                                <option value="admin"><?= __('admin.users.profile.admin', 'Administrador') ?></option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label"><?= __('common.status', 'Status') ?></label>
                            <select name="status" class="form-select" required>
                                <option value="ativo"><?= __('admin.users.status.active', 'Ativo') ?></option>
                                <option value="inativo"><?= __('admin.users.status.inactive', 'Inativo') ?></option>
                                <option value="bloqueado"><?= __('admin.users.status.blocked', 'Bloqueado') ?></option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label"><?= __('admin.users.credits_available', 'Créditos Disponíveis') ?></label>
                            <div class="input-group">
                                <span class="input-group-text"><?= __('admin.orders.js.currency_brl', 'R$') ?></span>
                                <input type="number" name="creditos_disponiveis" class="form-control" step="0.01" value="0.00">
                            </div>
                            <div class="form-text"><?= __('admin.users.credits_hint', 'Saldo atual de créditos do usuário') ?></div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal"><?= __('common.cancel', 'Cancelar') ?></button>
                <button type="button" class="btn btn-primary" onclick="salvarUsuario()"><?= __('common.save', 'Salvar') ?></button>
            </div>
        </div>
    </div>
</div>

<script>
window.ADMIN_USERS_I18N = {
    invalid_user_id: <?= json_encode(__('admin.users.js.invalid_user_id', 'ID do usuário inválido. ID recebido: {id} (tipo: {type})'), JSON_UNESCAPED_UNICODE) ?>,
    edit_user: <?= json_encode(__('admin.users.modal.edit_title', 'Editar Usuário'), JSON_UNESCAPED_UNICODE) ?>,
    error_load_user_prefix: <?= json_encode(__('admin.users.js.error_load_user_prefix', 'Erro ao carregar usuário:'), JSON_UNESCAPED_UNICODE) ?>,
    error_unknown_check_console: <?= json_encode(__('admin.users.js.error_unknown_check_console', 'Erro desconhecido - verifique o console'), JSON_UNESCAPED_UNICODE) ?>,
    error_network_check_connection: <?= json_encode(__('admin.users.js.error_network_check_connection', 'Erro de rede. Verifique a conexão com o servidor.'), JSON_UNESCAPED_UNICODE) ?>,
    error_syntax_response: <?= json_encode(__('admin.users.js.error_syntax_response', 'Erro de sintaxe na resposta do servidor.'), JSON_UNESCAPED_UNICODE) ?>,
    error_load_user_check_console: <?= json_encode(__('admin.users.js.error_load_user_check_console', 'Erro ao carregar usuário. Verifique o console para mais detalhes.'), JSON_UNESCAPED_UNICODE) ?>,
    confirm_change_status: <?= json_encode(__('admin.users.js.confirm_change_status', 'Deseja realmente alterar o status deste usuário?'), JSON_UNESCAPED_UNICODE) ?>,
    status_changed_success: <?= json_encode(__('admin.users.js.status_changed_success', 'Status alterado com sucesso!'), JSON_UNESCAPED_UNICODE) ?>,
    error_change_status_prefix: <?= json_encode(__('admin.users.js.error_change_status_prefix', 'Erro ao alterar status:'), JSON_UNESCAPED_UNICODE) ?>,
    confirm_delete_user: <?= json_encode(__('admin.users.js.confirm_delete_user', 'Deseja realmente excluir este usuário? Esta ação não pode ser desfeita!'), JSON_UNESCAPED_UNICODE) ?>,
    user_deleted_success: <?= json_encode(__('admin.users.js.user_deleted_success', 'Usuário excluído com sucesso!'), JSON_UNESCAPED_UNICODE) ?>,
    error_delete_user_prefix: <?= json_encode(__('admin.users.js.error_delete_user_prefix', 'Erro ao excluir usuário:'), JSON_UNESCAPED_UNICODE) ?>,
    passwords_mismatch: <?= json_encode(__('admin.users.js.passwords_mismatch', 'As senhas não conferem!'), JSON_UNESCAPED_UNICODE) ?>,
    user_saved_success: <?= json_encode(__('admin.users.js.user_saved_success', 'Usuário salvo com sucesso!'), JSON_UNESCAPED_UNICODE) ?>,
    error_save_user_prefix: <?= json_encode(__('admin.users.js.error_save_user_prefix', 'Erro ao salvar usuário:'), JSON_UNESCAPED_UNICODE) ?>
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
        alert(((window.ADMIN_USERS_I18N && window.ADMIN_USERS_I18N.invalid_user_id) ? window.ADMIN_USERS_I18N.invalid_user_id : 'ID do usuário inválido. ID recebido: {id} (tipo: {type})').replace('{id}', id).replace('{type}', typeof id));
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
                console.log('✅ [SUCESSO] Usuário encontrado, preenchendo modal...');
                console.log('🔍 [MODAL] Dados do usuário:', {
                    id: data.usuario.id,
                    nome: data.usuario.nome,
                    email: data.usuario.email,
                    perfil: data.usuario.perfil,
                    status: data.usuario.status,
                    creditos_disponiveis: data.usuario.creditos_disponiveis
                });
                
                // Preencher o modal existente
                console.log('🔍 [DOM] Verificando elementos do modal...');
                
                const modalTitle = document.getElementById('modalUsuarioTitle');
                console.log('🔍 [DOM] modalUsuarioTitle:', !!modalTitle);
                
                const usuarioId = document.getElementById('usuario_id');
                console.log('🔍 [DOM] usuario_id:', !!usuarioId);
                
                const nomeInput = document.querySelector('input[name="nome"]');
                console.log('🔍 [DOM] input[name="nome"]:', !!nomeInput);
                
                const emailInput = document.querySelector('input[name="email"]');
                console.log('🔍 [DOM] input[name="email"]:', !!emailInput);
                
                const documentoInput = document.querySelector('input[name="documento"]');
                console.log('🔍 [DOM] input[name="documento"]:', !!documentoInput);
                
                const telefoneInput = document.querySelector('input[name="telefone"]');
                console.log('🔍 [DOM] input[name="telefone"]:', !!telefoneInput);
                
                const enderecoInput = document.querySelector('input[name="endereco"]');
                console.log('🔍 [DOM] input[name="endereco"]:', !!enderecoInput);
                
                const cidadeInput = document.querySelector('input[name="cidade"]');
                console.log('🔍 [DOM] input[name="cidade"]:', !!cidadeInput);
                
                const estadoInput = document.querySelector('input[name="estado"]');
                console.log('🔍 [DOM] input[name="estado"]:', !!estadoInput);
                
                const cepInput = document.querySelector('input[name="cep"]');
                console.log('🔍 [DOM] input[name="cep"]:', !!cepInput);
                
                const perfilSelect = document.querySelector('select[name="perfil"]');
                console.log('🔍 [DOM] select[name="perfil"]:', !!perfilSelect);
                
                const statusSelect = document.querySelector('select[name="status"]');
                console.log('🔍 [DOM] select[name="status"]:', !!statusSelect);
                
                const creditosInput = document.querySelector('input[name="creditos_disponiveis"]');
                console.log('🔍 [DOM] input[name="creditos_disponiveis"]:', !!creditosInput);
                
                console.log('🔍 [MODAL] Preenchendo campos...');
                
                if (modalTitle) {
                    console.log('🔍 [MODAL] Preenchendo título...');
                    modalTitle.textContent = (window.ADMIN_USERS_I18N && window.ADMIN_USERS_I18N.edit_user) ? window.ADMIN_USERS_I18N.edit_user : 'Editar Usuário';
                }
                
                if (usuarioId) {
                    console.log('🔍 [MODAL] Preenchendo ID...');
                    usuarioId.value = data.usuario.id;
                }
                
                if (nomeInput) {
                    console.log('🔍 [MODAL] Preenchendo nome...');
                    nomeInput.value = data.usuario.nome || '';
                } else {
                    console.error('❌ [ERRO] input[name="nome"] não encontrado');
                }
                
                if (emailInput) {
                    console.log('🔍 [MODAL] Preenchendo email...');
                    emailInput.value = data.usuario.email || '';
                } else {
                    console.error('❌ [ERRO] input[name="email"] não encontrado');
                }
                
                if (documentoInput) {
                    console.log('🔍 [MODAL] Preenchendo documento...');
                    documentoInput.value = data.usuario.documento || '';
                } else {
                    console.error('❌ [ERRO] input[name="documento"] não encontrado');
                }
                
                if (telefoneInput) {
                    console.log('🔍 [MODAL] Preenchendo telefone...');
                    telefoneInput.value = data.usuario.telefone || '';
                } else {
                    console.error('❌ [ERRO] input[name="telefone"] não encontrado');
                }
                
                if (enderecoInput) {
                    console.log('🔍 [MODAL] Preenchendo endereço...');
                    enderecoInput.value = data.usuario.endereco || '';
                } else {
                    console.error('❌ [ERRO] input[name="endereco"] não encontrado');
                }
                
                if (cidadeInput) {
                    console.log('🔍 [MODAL] Preenchendo cidade...');
                    cidadeInput.value = data.usuario.cidade || '';
                } else {
                    console.error('❌ [ERRO] input[name="cidade"] não encontrado');
                }
                
                if (estadoInput) {
                    console.log('🔍 [MODAL] Preenchendo estado...');
                    estadoInput.value = data.usuario.estado || '';
                } else {
                    console.error('❌ [ERRO] input[name="estado"] não encontrado');
                }
                
                if (cepInput) {
                    console.log('🔍 [MODAL] Preenchendo CEP...');
                    cepInput.value = data.usuario.cep || '';
                } else {
                    console.error('❌ [ERRO] input[name="cep"] não encontrado');
                }
                
                if (perfilSelect) {
                    console.log('🔍 [MODAL] Preenchendo perfil...');
                    perfilSelect.value = data.usuario.perfil || 'cliente';
                } else {
                    console.error('❌ [ERRO] select[name="perfil"] não encontrado');
                }
                
                if (statusSelect) {
                    console.log('🔍 [MODAL] Preenchendo status...');
                    statusSelect.value = data.usuario.status || 'ativo';
                } else {
                    console.error('❌ [ERRO] select[name="status"] não encontrado');
                }
                
                if (creditosInput) {
                    console.log('🔍 [MODAL] Preenchendo créditos...');
                    creditosInput.value = data.usuario.creditos_disponiveis || 0;
                } else {
                    console.error('❌ [ERRO] input[name="creditos_disponiveis"] não encontrado');
                }
                
                console.log('🔍 [BOOTSTRAP] Abrindo modal...');
                new bootstrap.Modal(document.getElementById('modalUsuario')).show();
                console.log('✅ [SUCESSO] Modal aberto com sucesso!');
            } else {
                console.error('❌ [ERRO] Resposta sem sucesso:', data);
                console.error('❌ [ERRO] Mensagem de erro:', data.error);
                alert(((window.ADMIN_USERS_I18N && window.ADMIN_USERS_I18N.error_load_user_prefix) ? window.ADMIN_USERS_I18N.error_load_user_prefix : 'Erro ao carregar usuário:') + ' ' + (data.error || ((window.ADMIN_USERS_I18N && window.ADMIN_USERS_I18N.error_unknown_check_console) ? window.ADMIN_USERS_I18N.error_unknown_check_console : 'Erro desconhecido - verifique o console')));
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
                alert((window.ADMIN_USERS_I18N && window.ADMIN_USERS_I18N.error_network_check_connection) ? window.ADMIN_USERS_I18N.error_network_check_connection : 'Erro de rede. Verifique a conexão com o servidor.');
            } else if (error.name === 'SyntaxError') {
                console.error('❌ [ERRO SINTAX] Erro de sintaxe na resposta JSON');
                alert((window.ADMIN_USERS_I18N && window.ADMIN_USERS_I18N.error_syntax_response) ? window.ADMIN_USERS_I18N.error_syntax_response : 'Erro de sintaxe na resposta do servidor.');
            } else {
                alert((window.ADMIN_USERS_I18N && window.ADMIN_USERS_I18N.error_load_user_check_console) ? window.ADMIN_USERS_I18N.error_load_user_check_console : 'Erro ao carregar usuário. Verifique o console para mais detalhes.');
            }
        });
}

function alterarStatus(id) {
    if (confirm((window.ADMIN_USERS_I18N && window.ADMIN_USERS_I18N.confirm_change_status) ? window.ADMIN_USERS_I18N.confirm_change_status : 'Deseja realmente alterar o status deste usuário?')) {
        fetch(`/admin/alterar-status-usuario/${id}`, {
            method: 'POST'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert((window.ADMIN_USERS_I18N && window.ADMIN_USERS_I18N.status_changed_success) ? window.ADMIN_USERS_I18N.status_changed_success : 'Status alterado com sucesso!');
                location.reload();
            } else {
                alert(((window.ADMIN_USERS_I18N && window.ADMIN_USERS_I18N.error_change_status_prefix) ? window.ADMIN_USERS_I18N.error_change_status_prefix : 'Erro ao alterar status:') + ' ' + data.error);
            }
        });
    }
}

function excluirUsuario(id) {
    if (confirm((window.ADMIN_USERS_I18N && window.ADMIN_USERS_I18N.confirm_delete_user) ? window.ADMIN_USERS_I18N.confirm_delete_user : 'Deseja realmente excluir este usuário? Esta ação não pode ser desfeita!')) {
        fetch(`/admin/excluir-usuario/${id}`, {
            method: 'DELETE'
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert((window.ADMIN_USERS_I18N && window.ADMIN_USERS_I18N.user_deleted_success) ? window.ADMIN_USERS_I18N.user_deleted_success : 'Usuário excluído com sucesso!');
                location.reload();
            } else {
                alert(((window.ADMIN_USERS_I18N && window.ADMIN_USERS_I18N.error_delete_user_prefix) ? window.ADMIN_USERS_I18N.error_delete_user_prefix : 'Erro ao excluir usuário:') + ' ' + data.error);
            }
        });
    }
}

function salvarUsuario() {
    const form = document.getElementById('formUsuario');
    const formData = new FormData(form);
    
    const senha = formData.get('senha');
    const senhaConfirmacao = formData.get('senha_confirmacao');
    
    if (senha && senha !== senhaConfirmacao) {
        alert((window.ADMIN_USERS_I18N && window.ADMIN_USERS_I18N.passwords_mismatch) ? window.ADMIN_USERS_I18N.passwords_mismatch : 'As senhas não conferem!');
        return;
    }
    
    const id = formData.get('id');
    const url = id ? `/admin/atualizar-usuario/${id}` : '/admin/salvar-usuario';
    
    fetch(url, {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert((window.ADMIN_USERS_I18N && window.ADMIN_USERS_I18N.user_saved_success) ? window.ADMIN_USERS_I18N.user_saved_success : 'Usuário salvo com sucesso!');
            location.reload();
        } else {
            alert(((window.ADMIN_USERS_I18N && window.ADMIN_USERS_I18N.error_save_user_prefix) ? window.ADMIN_USERS_I18N.error_save_user_prefix : 'Erro ao salvar usuário:') + ' ' + data.error);
        }
    });
}
</script>

<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/admin.php'; ?>
