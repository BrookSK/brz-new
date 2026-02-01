<?php ob_start(); ?>
<div class="container py-4">
    <div class="row g-4">
        <!-- Sidebar -->
        <div class="col-lg-3">
            <!-- Profile Card -->
            <div class="card border-0 shadow-sm profile-card">
                <div class="card-body text-center p-4">
                    <?php
                        $avatarColumnCandidates = ['avatar', 'foto_perfil', 'imagem_perfil', 'foto'];
                        $avatarUrl = null;
                        foreach ($avatarColumnCandidates as $c) {
                            if (!empty($usuario[$c])) {
                                $avatarUrl = $usuario[$c];
                                break;
                            }
                        }

                        if (empty($avatarUrl)) {
                            $avatarUrl = $_SESSION['usuario_avatar'] ?? null;
                        }

                        if (empty($avatarUrl)) {
                            $avatarUrl = 'https://ui-avatars.com/api/?name=' . urlencode($usuario['nome']) . '&background=1d4ed8&color=fff&size=128';
                        }
                    ?>

                    <div class="user-avatar mx-auto mb-3 dropdown">
                        <button type="button" class="btn p-0 border-0 bg-transparent" id="avatarDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <img src="<?= htmlspecialchars($avatarUrl) ?>" 
                                 alt="<?= htmlspecialchars($usuario['nome']) ?>" 
                                 class="rounded-circle" width="80" height="80" style="object-fit: cover;">
                            <span class="avatar-camera-indicator" aria-hidden="true">
                                <i class="fas fa-camera"></i>
                            </span>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="avatarDropdown">
                            <li>
                                <button type="button" class="dropdown-item" id="btnAvatarView">
                                    <i class="fas fa-eye me-2"></i> Ver foto
                                </button>
                            </li>
                            <li>
                                <button type="button" class="dropdown-item" id="btnAvatarChange">
                                    <i class="fas fa-upload me-2"></i> Alterar foto
                                </button>
                            </li>
                            <li><hr class="dropdown-divider"></li>
                            <li>
                                <button type="button" class="dropdown-item text-danger" id="btnAvatarRemove">
                                    <i class="fas fa-trash me-2"></i> Remover foto
                                </button>
                            </li>
                        </ul>
                    </div>

                    <form method="POST" action="/meus-dados/avatar" enctype="multipart/form-data" id="avatarUploadForm">
                        <input type="file" name="avatar" id="avatarFileInput" accept="image/png,image/jpeg,image/webp" class="avatar-file-input-hidden">
                    </form>
                    <form method="POST" action="/meus-dados/avatar/remover" id="avatarRemoveForm" class="d-none"></form>

                    <h5 class="card-title mb-1"><?= htmlspecialchars($usuario['nome']) ?></h5>
                    <p class="text-muted small mb-3"><?= htmlspecialchars($usuario['email']) ?></p>
                    <span class="badge px-3 py-2" style="background: rgba(11, 31, 58, 0.08); border: 1px solid rgba(11, 31, 58, 0.14); color: rgba(11, 31, 58, 1);">
                        <?= ucfirst($usuario['perfil']) ?>
                    </span>
                    <?php if (!empty($usuario['switch'])): ?>
                        <div class="mt-2 small text-muted">Switch: <strong><?= (int) $usuario['switch'] ?></strong></div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Quick Menu -->
            <div class="card border-0 shadow-sm mt-3">
                <div class="card-body p-4">
                    <h6 class="card-title mb-3">Menu Rápido</h6>
                    <nav class="nav flex-column">
                        <a class="nav-link mb-2" href="/minha-conta">
                            <i class="fas fa-tachometer-alt me-2"></i> Dashboard
                        </a>
                        <a class="nav-link active mb-2" href="/meus-dados">
                            <i class="fas fa-user me-2"></i> Meus Dados
                        </a>
                        <a class="nav-link mb-2" href="/meus-pedidos">
                            <i class="fas fa-shopping-bag me-2"></i> Meus Pedidos
                        </a>
                        <a class="nav-link mb-2" href="/carrinho">
                            <i class="fas fa-shopping-cart me-2"></i> Meu Carrinho
                            <?php if (!empty($_SESSION['carrinho'])): ?>
                                <span class="badge rounded-pill ms-auto" style="background: rgba(239, 68, 68, 0.10); border: 1px solid rgba(239, 68, 68, 0.18); color: rgba(185, 28, 28, 1);">
                                    <?= count($_SESSION['carrinho']) ?>
                                </span>
                            <?php endif; ?>
                        </a>
                        <hr class="my-3">
                        <a class="nav-link text-danger mb-2" href="/logout">
                            <i class="fas fa-sign-out-alt me-2"></i> Sair
                        </a>
                    </nav>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="col-lg-9">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4 user-page-header">
                <div>
                    <h2 class="mb-1">Meus Dados</h2>
                    <p class="text-muted mb-0">Gerencie suas informações pessoais</p>
                </div>
                <div class="text-end">
                    <small class="text-muted">Membro desde:</small><br>
                    <strong><?= date('d/m/Y', strtotime($usuario['created_at'] ?? 'now')) ?></strong>
                </div>
            </div>
            
            <!-- Success Message -->
            <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-<?= $_SESSION['message_type'] ?? 'info' ?> alert-dismissible fade show" role="alert">
                <?= $_SESSION['message'] ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['message'], $_SESSION['message_type']); ?>
            <?php endif; ?>

            <form method="POST" action="/meus-dados" id="formMeusDados">
            
            <!-- Profile Form -->
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-0 pt-4 pb-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-user-edit me-2"></i> Informações Pessoais
                    </h5>
                </div>
                <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="nome" class="form-label">Nome Completo</label>
                                <input type="text" class="form-control" id="nome" name="nome" 
                                       value="<?= htmlspecialchars($usuario['nome']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="email" class="form-label">E-mail</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?= htmlspecialchars($usuario['email']) ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="telefone" class="form-label">Telefone</label>
                                <input type="tel" class="form-control" id="telefone" name="telefone" 
                                       value="<?= htmlspecialchars($usuario['telefone'] ?? '') ?>" 
                                       placeholder="(00) 00000-0000">
                            </div>
                            <div class="col-md-6">
                                <label for="documento" class="form-label">CPF/CNPJ</label>
                                <input type="text" class="form-control" id="documento" name="documento" 
                                       value="<?= htmlspecialchars($usuario['documento'] ?? '') ?>" 
                                       placeholder="000.000.000-00">
                            </div>
                        </div>
                </div>
            </div>
            
            <!-- Address Form -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-white border-0 pt-4 pb-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-map-marker-alt me-2"></i> Endereço Principal
                    </h5>
                </div>
                <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="cep" class="form-label">CEP</label>
                                <input type="text" class="form-control" id="cep" name="cep" 
                                       value="<?= htmlspecialchars($usuario['cep'] ?? '') ?>" 
                                       placeholder="00000-000">
                            </div>
                            <div class="col-md-6">
                                <label for="endereco" class="form-label">Endereço</label>
                                <input type="text" class="form-control" id="endereco" name="endereco" 
                                       value="<?= htmlspecialchars($usuario['endereco'] ?? '') ?>" 
                                       placeholder="Rua, Avenida, etc.">
                            </div>
                            <div class="col-md-3">
                                <label for="numero" class="form-label">Número</label>
                                <input type="text" class="form-control" id="numero" name="numero" 
                                       value="<?= htmlspecialchars($usuario['numero'] ?? '') ?>" 
                                       placeholder="123">
                            </div>
                            <div class="col-md-3">
                                <label for="complemento" class="form-label">Complemento</label>
                                <input type="text" class="form-control" id="complemento" name="complemento" 
                                       value="<?= htmlspecialchars($usuario['complemento'] ?? '') ?>" 
                                       placeholder="Apto, Casa, etc.">
                            </div>
                            <div class="col-md-4">
                                <label for="bairro" class="form-label">Bairro</label>
                                <input type="text" class="form-control" id="bairro" name="bairro" 
                                       value="<?= htmlspecialchars($usuario['bairro'] ?? '') ?>" 
                                       placeholder="Centro">
                            </div>
                            <div class="col-md-4">
                                <label for="cidade" class="form-label">Cidade</label>
                                <input type="text" class="form-control" id="cidade" name="cidade" 
                                       value="<?= htmlspecialchars($usuario['cidade'] ?? '') ?>" 
                                       placeholder="São Paulo">
                            </div>
                            <div class="col-md-4">
                                <label for="estado" class="form-label">Estado</label>
                                <select class="form-select" id="estado" name="estado">
                                    <option value="">Selecione...</option>
                                    <option value="AC" <?= ($usuario['estado'] ?? '') === 'AC' ? 'selected' : '' ?>>Acre</option>
                                    <option value="AL" <?= ($usuario['estado'] ?? '') === 'AL' ? 'selected' : '' ?>>Alagoas</option>
                                    <option value="AP" <?= ($usuario['estado'] ?? '') === 'AP' ? 'selected' : '' ?>>Amapá</option>
                                    <option value="AM" <?= ($usuario['estado'] ?? '') === 'AM' ? 'selected' : '' ?>>Amazonas</option>
                                    <option value="BA" <?= ($usuario['estado'] ?? '') === 'BA' ? 'selected' : '' ?>>Bahia</option>
                                    <option value="CE" <?= ($usuario['estado'] ?? '') === 'CE' ? 'selected' : '' ?>>Ceará</option>
                                    <option value="DF" <?= ($usuario['estado'] ?? '') === 'DF' ? 'selected' : '' ?>>Distrito Federal</option>
                                    <option value="ES" <?= ($usuario['estado'] ?? '') === 'ES' ? 'selected' : '' ?>>Espírito Santo</option>
                                    <option value="GO" <?= ($usuario['estado'] ?? '') === 'GO' ? 'selected' : '' ?>>Goiás</option>
                                    <option value="MA" <?= ($usuario['estado'] ?? '') === 'MA' ? 'selected' : '' ?>>Maranhão</option>
                                    <option value="MT" <?= ($usuario['estado'] ?? '') === 'MT' ? 'selected' : '' ?>>Mato Grosso</option>
                                    <option value="MS" <?= ($usuario['estado'] ?? '') === 'MS' ? 'selected' : '' ?>>Mato Grosso do Sul</option>
                                    <option value="MG" <?= ($usuario['estado'] ?? '') === 'MG' ? 'selected' : '' ?>>Minas Gerais</option>
                                    <option value="PA" <?= ($usuario['estado'] ?? '') === 'PA' ? 'selected' : '' ?>>Pará</option>
                                    <option value="PB" <?= ($usuario['estado'] ?? '') === 'PB' ? 'selected' : '' ?>>Paraíba</option>
                                    <option value="PR" <?= ($usuario['estado'] ?? '') === 'PR' ? 'selected' : '' ?>>Paraná</option>
                                    <option value="PE" <?= ($usuario['estado'] ?? '') === 'PE' ? 'selected' : '' ?>>Pernambuco</option>
                                    <option value="PI" <?= ($usuario['estado'] ?? '') === 'PI' ? 'selected' : '' ?>>Piauí</option>
                                    <option value="RJ" <?= ($usuario['estado'] ?? '') === 'RJ' ? 'selected' : '' ?>>Rio de Janeiro</option>
                                    <option value="RN" <?= ($usuario['estado'] ?? '') === 'RN' ? 'selected' : '' ?>>Rio Grande do Norte</option>
                                    <option value="RS" <?= ($usuario['estado'] ?? '') === 'RS' ? 'selected' : '' ?>>Rio Grande do Sul</option>
                                    <option value="RO" <?= ($usuario['estado'] ?? '') === 'RO' ? 'selected' : '' ?>>Rondônia</option>
                                    <option value="RR" <?= ($usuario['estado'] ?? '') === 'RR' ? 'selected' : '' ?>>Roraima</option>
                                    <option value="SC" <?= ($usuario['estado'] ?? '') === 'SC' ? 'selected' : '' ?>>Santa Catarina</option>
                                    <option value="SP" <?= ($usuario['estado'] ?? '') === 'SP' ? 'selected' : '' ?>>São Paulo</option>
                                    <option value="SE" <?= ($usuario['estado'] ?? '') === 'SE' ? 'selected' : '' ?>>Sergipe</option>
                                    <option value="TO" <?= ($usuario['estado'] ?? '') === 'TO' ? 'selected' : '' ?>>Tocantins</option>
                                </select>
                            </div>
                        </div>
                </div>
            </div>
            
            <!-- Security Form -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-white border-0 pt-4 pb-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-shield-alt me-2"></i> Segurança
                    </h5>
                </div>
                <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="senha_atual" class="form-label">Senha Atual</label>
                                <input type="password" class="form-control" id="senha_atual" name="senha_atual" 
                                       placeholder="Digite sua senha atual">
                            </div>
                            <div class="col-md-6">
                                <label for="senha_nova" class="form-label">Nova Senha</label>
                                <input type="password" class="form-control" id="senha_nova" name="senha_nova" 
                                       placeholder="Digite a nova senha">
                            </div>
                            <div class="col-md-12">
                                <label for="senha_confirmacao" class="form-label">Confirmar Nova Senha</label>
                                <input type="password" class="form-control" id="senha_confirmacao" name="senha_confirmacao" 
                                       placeholder="Confirme a nova senha">
                            </div>
                        </div>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            Deixe os campos de senha em branco caso não queira alterá-los.
                        </div>
                </div>
            </div>
            
            <!-- Preferences Form -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-white border-0 pt-4 pb-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-cog me-2"></i> Preferências
                    </h5>
                </div>
                <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="notificacoes_email" class="form-label">
                                    <input type="checkbox" class="form-check-input me-2" id="notificacoes_email" name="notificacoes_email" 
                                           <?= ($usuario['notificacoes_email'] ?? 1) ? 'checked' : '' ?>>
                                    Receber notificações por e-mail
                                </label>
                            </div>
                            <div class="col-md-6">
                                <label for="notificacoes_sms" class="form-label">
                                    <input type="checkbox" class="form-check-input me-2" id="notificacoes_sms" name="notificacoes_sms" 
                                           <?= ($usuario['notificacoes_sms'] ?? 0) ? 'checked' : '' ?>>
                                    Receber notificações por SMS
                                </label>
                            </div>
                            <div class="col-md-12">
                                <label for="idioma" class="form-label">Idioma</label>
                                <select class="form-select" id="idioma" name="idioma">
                                    <option value="pt-BR" <?= ($usuario['idioma'] ?? 'pt-BR') === 'pt-BR' ? 'selected' : '' ?>>Português (Brasil)</option>
                                    <option value="en-US" <?= ($usuario['idioma'] ?? 'pt-BR') === 'en-US' ? 'selected' : '' ?>>English (US)</option>
                                    <option value="es-ES" <?= ($usuario['idioma'] ?? 'pt-BR') === 'es-ES' ? 'selected' : '' ?>>Español</option>
                                </select>
                            </div>
                        </div>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="row g-3 mt-4">
                <div class="col-12">
                    <div class="d-flex gap-2 justify-content-end">
                        <a href="/minha-conta" class="btn btn-outline-secondary">
                            <i class="fas fa-times me-2"></i> Cancelar
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i> Salvar Alterações
                        </button>
                    </div>
                </div>
            </div>

            </form>
        </div>
    </div>
</div>

<!-- JavaScript para validação e máscaras -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    const btnAvatarView = document.getElementById('btnAvatarView');
    const btnAvatarChange = document.getElementById('btnAvatarChange');
    const btnAvatarRemove = document.getElementById('btnAvatarRemove');
    const avatarFileInput = document.getElementById('avatarFileInput');
    const avatarUploadForm = document.getElementById('avatarUploadForm');
    const avatarRemoveForm = document.getElementById('avatarRemoveForm');

    if (btnAvatarView) {
        btnAvatarView.addEventListener('click', function() {
            const modalEl = document.getElementById('avatarViewModal');
            if (!modalEl || !window.bootstrap) return;
            const modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            modal.show();
        });
    }

    if (btnAvatarChange && avatarFileInput) {
        btnAvatarChange.addEventListener('click', function() {
            avatarFileInput.click();
        });
    }

    if (avatarFileInput && avatarUploadForm) {
        avatarFileInput.addEventListener('change', function() {
            if (avatarFileInput.files && avatarFileInput.files.length > 0) {
                avatarUploadForm.submit();
            }
        });
    }

    if (btnAvatarRemove && avatarRemoveForm) {
        btnAvatarRemove.addEventListener('click', function() {
            const ok = confirm('Remover sua foto de perfil?');
            if (ok) {
                avatarRemoveForm.submit();
            }
        });
    }

    // Máscara para telefone
    const telefoneInput = document.getElementById('telefone');
    if (telefoneInput) {
        telefoneInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length <= 11) {
                value = value.replace(/^(\d{2})(\d{5})(\d{4}).*/, '($1) $2-$3');
            }
            e.target.value = value;
        });
    }
    
    // Máscara para CPF/CNPJ
    const documentoInput = document.getElementById('documento');
    if (documentoInput) {
        documentoInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length <= 11) {
                value = value.replace(/^(\d{3})(\d{3})(\d{3})(\d{2}).*/, '$1.$2.$3-$4');
            } else if (value.length <= 14) {
                value = value.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2}).*/, '$1.$2.$3/$4-$5');
            }
            e.target.value = value;
        });
    }
    
    // Máscara para CEP
    const cepInput = document.getElementById('cep');
    if (cepInput) {
        cepInput.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length <= 8) {
                value = value.replace(/^(\d{5})(\d{3}).*/, '$1-$2');
            }
            e.target.value = value;
        });
        
        // Busca CEP via API
        cepInput.addEventListener('blur', function(e) {
            const cep = e.target.value.replace(/\D/g, '');
            if (cep.length === 8) {
                fetch(`https://viacep.com.br/ws/${cep}/json/`)
                    .then(response => response.json())
                    .then(data => {
                        if (!data.erro) {
                            document.getElementById('endereco').value = data.logradouro || '';
                            document.getElementById('bairro').value = data.bairro || '';
                            document.getElementById('cidade').value = data.localidade || '';
                            document.getElementById('estado').value = data.uf || '';
                            document.getElementById('numero').focus();
                        }
                    })
                    .catch(error => {
                        console.error('Erro ao buscar CEP:', error);
                    });
            }
        });
    }
    
    // Validação de senhas
    const senhaAtual = document.getElementById('senha_atual');
    const senhaNova = document.getElementById('senha_nova');
    const senhaConfirmacao = document.getElementById('senha_confirmacao');
    
    function validarSenhas() {
        if (senhaNova.value && senhaConfirmacao.value) {
            if (senhaNova.value !== senhaConfirmacao.value) {
                senhaConfirmacao.setCustomValidity('As senhas não conferem!');
            } else {
                senhaConfirmacao.setCustomValidity('');
            }
        }
    }
    
    if (senhaNova && senhaConfirmacao) {
        senhaNova.addEventListener('input', validarSenhas);
        senhaConfirmacao.addEventListener('input', validarSenhas);
    }
    
    // Feedback visual de salvamento
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const submitBtn = form.querySelector('button[type="submit"]');
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Salvando...';
            }
        });
    });
});
</script>

<style>
.col-lg-3 {
    position: relative;
    z-index: 10;
}

.col-lg-3 .card {
    position: relative;
    z-index: 1;
}

.col-lg-3 .profile-card {
    z-index: 20;
}

.col-lg-3 .profile-card:hover {
    z-index: 25;
}

.col-lg-3 .card:hover {
    z-index: 2;
}

.col-lg-3 .card,
.col-lg-3 .card-body {
    overflow: visible;
}

.user-avatar {
    position: relative;
    z-index: 2000;
}

.user-avatar .dropdown-menu {
    z-index: 2001;
}

.avatar-camera-indicator {
    position: absolute;
    right: -2px;
    bottom: -2px;
    width: 26px;
    height: 26px;
    border-radius: 999px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(15, 23, 42, 0.9);
    color: #fff;
    border: 2px solid #fff;
    box-shadow: 0 6px 14px rgba(0,0,0,0.18);
    pointer-events: none;
    font-size: 12px;
}

.user-avatar img {
    border: 0px solid #fff;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    height: 80px;
}

.avatar-file-input-hidden {
    position: absolute;
    left: -9999px;
    width: 1px;
    height: 1px;
    opacity: 0;
}

.nav-link {
    border-radius: 8px;
    padding: 12px 16px;
    margin-bottom: 8px;
    transition: all 0.3s ease;
    color: #6c757d;
    text-decoration: none;
    display: flex;
    align-items: center;
}

.nav-link:hover {
    background-color: #f8f9fa;
    color: #495057;
    transform: none;
}

.nav-link.active {
    background: rgba(11, 31, 58, 0.08);
    border: 1px solid rgba(11, 31, 58, 0.14);
    color: rgba(11, 31, 58, 1) !important;
}

.card {
    transition: none;
}

.card:hover {
    transform: none;
    box-shadow: none;
}

.form-label {
    font-weight: 600;
    color: #495057;
    margin-bottom: 0.5rem;
}

.form-control:focus {
    border-color: rgba(29, 78, 216, 0.55);
    box-shadow: 0 0 0 0.2rem rgba(29, 78, 216, 0.18);
}

.btn {
    border-radius: 0.375rem;
    font-weight: 500;
}

.alert {
    border: none;
    border-radius: 0.5rem;
}

.form-check-input:checked {
    background-color: #1d4ed8;
    border-color: #1d4ed8;
}

.form-check-input:focus {
    box-shadow: 0 0 0 0.2rem rgba(29, 78, 216, 0.18);
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Máscaras de CPF/CNPJ
    const documento = document.getElementById('documento');
    if (documento) {
        documento.addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '');
            
            if (value.length <= 11) {
                // CPF
                value = value.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
            } else {
                // CNPJ
                value = value.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
            }
            
            this.value = value;
        });
    }
    
    // Máscara de Telefone
    const telefone = document.getElementById('telefone');
    if (telefone) {
        telefone.addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '');
            
            if (value.length <= 10) {
                // Celular sem DDD
                value = value.replace(/(\d{5})(\d{4})(\d{1})/, '$1-$2-$3');
            } else {
                // Celular com DDD
                value = value.replace(/(\d{2})(\d{5})(\d{4})(\d{1})/, '($1) $2-$3-$4');
            }
            
            this.value = value;
        });
    }
    
    // Busca de CEP
    const cep = document.getElementById('cep');
    if (cep) {
        cep.addEventListener('blur', function() {
            const cepValue = this.value.replace(/\D/g, '');
            
            if (cepValue.length === 8) {
                // Simulação de busca de CEP
                setTimeout(() => {
                    document.getElementById('endereco').value = 'Rua Exemplo';
                    document.getElementById('bairro').value = 'Centro';
                    document.getElementById('cidade').value = 'São Paulo';
                    document.getElementById('estado').value = 'SP';
                    document.getElementById('numero').focus();
                }, 500);
            }
        });
    }
    
    // Validação de senha
    const formSeguranca = document.getElementById('formSeguranca');
    if (formSeguranca) {
        formSeguranca.addEventListener('submit', function(e) {
            const senhaNova = document.getElementById('senha_nova').value;
            const senhaConfirmacao = document.getElementById('senha_confirmacao').value;
            
            if (senhaNova && senhaNova !== senhaConfirmacao) {
                e.preventDefault();
                alert('As senhas não conferem!');
                return false;
            }
            
            if (senhaNova && senhaNova.length < 6) {
                e.preventDefault();
                alert('A senha deve ter pelo menos 6 caracteres!');
                return false;
            }
        });
    }
});
</script>

<div class="modal fade" id="avatarViewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Foto de Perfil</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img src="<?= htmlspecialchars($avatarUrl) ?>" alt="<?= htmlspecialchars($usuario['nome']) ?>" class="img-fluid rounded" style="max-height: 70vh; object-fit: contain;">
            </div>
        </div>
    </div>
</div>

<style>
.col-lg-3 .profile-card {
    z-index: 20;
}

.col-lg-3 .profile-card:hover {
    z-index: 25;
}

.col-lg-3 .card:hover {
    z-index: 2;
}

.col-lg-3 .card,
.col-lg-3 .card-body {
    overflow: visible;
}

.user-avatar {
    position: relative;
    z-index: 2000;
}

.user-avatar .dropdown-menu {
    z-index: 2001;
}

.avatar-camera-indicator {
    position: absolute;
    right: -2px;
    bottom: -2px;
    width: 26px;
    height: 26px;
    border-radius: 999px;
    display: flex;
    align-items: center;
    justify-content: center;
    background: rgba(15, 23, 42, 0.9);
    color: #fff;
    border: 2px solid #fff;
    box-shadow: 0 6px 14px rgba(0,0,0,0.18);
    pointer-events: none;
    font-size: 12px;
}

.user-avatar img {
    border: 0px solid #fff;
    box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
    height: 80px;
}

.nav-link {
    border-radius: 8px;
    padding: 12px 16px;
    margin-bottom: 8px;
    transition: all 0.3s ease;
    color: #6c757d;
    text-decoration: none;
    display: flex;
    align-items: center;
}

.nav-link:hover {
    background-color: #f8f9fa;
    color: #495057;
    transform: none;
}

.nav-link.active {
    background: rgba(11, 31, 58, 0.08);
    border: 1px solid rgba(11, 31, 58, 0.14);
    color: rgba(11, 31, 58, 1) !important;
}

.card {
    transition: none;
}

.card:hover {
    transform: none;
    box-shadow: none;
}

.form-label {
    font-weight: 600;
    color: #495057;
    margin-bottom: 0.5rem;
}

.form-control:focus {
    border-color: rgba(29, 78, 216, 0.55);
    box-shadow: 0 0 0 0.2rem rgba(29, 78, 216, 0.18);
}

.btn {
    border-radius: 0.375rem;
    font-weight: 500;
}

.alert {
    border: none;
    border-radius: 0.5rem;
}

.form-check-input:checked {
    background-color: #1d4ed8;
    border-color: #1d4ed8;
}

.form-check-input:focus {
    box-shadow: 0 0 0 0.2rem rgba(29, 78, 216, 0.18);
}

@media (max-width: 767.98px) {
    .user-page-header {
        flex-direction: column;
        align-items: flex-start !important;
        gap: 0.75rem;
    }

    .user-page-header .text-end {
        width: 100%;
        text-align: left !important;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Máscaras de CPF/CNPJ
    const documento = document.getElementById('documento');
    if (documento) {
        documento.addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '');
            
            if (value.length <= 11) {
                // CPF
                value = value.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
            } else {
                // CNPJ
                value = value.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
            }
            
            this.value = value;
        });
    }
    
    // Máscara de Telefone
    const telefone = document.getElementById('telefone');
    if (telefone) {
        telefone.addEventListener('input', function() {
            let value = this.value.replace(/\D/g, '');
            
            if (value.length <= 10) {
                // Celular sem DDD
                value = value.replace(/(\d{5})(\d{4})(\d{1})/, '$1-$2-$3');
            } else {
                // Celular com DDD
                value = value.replace(/(\d{2})(\d{5})(\d{4})(\d{1})/, '($1) $2-$3-$4');
            }
            
            this.value = value;
        });
    }
    
    // Busca de CEP
    const cep = document.getElementById('cep');
    if (cep) {
        cep.addEventListener('blur', function() {
            const cepValue = this.value.replace(/\D/g, '');
            
            if (cepValue.length === 8) {
                // Simulação de busca de CEP
                setTimeout(() => {
                    document.getElementById('endereco').value = 'Rua Exemplo';
                    document.getElementById('bairro').value = 'Centro';
                    document.getElementById('cidade').value = 'São Paulo';
                    document.getElementById('estado').value = 'SP';
                    document.getElementById('numero').focus();
                }, 500);
            }
        });
    }
    
    // Validação de senha
    const formSeguranca = document.getElementById('formSeguranca');
    if (formSeguranca) {
        formSeguranca.addEventListener('submit', function(e) {
            const senhaNova = document.getElementById('senha_nova').value;
            const senhaConfirmacao = document.getElementById('senha_confirmacao').value;
            
            if (senhaNova && senhaNova !== senhaConfirmacao) {
                e.preventDefault();
                alert('As senhas não conferem!');
                return false;
            }
            
            if (senhaNova && senhaNova.length < 6) {
                e.preventDefault();
                alert('A senha deve ter pelo menos 6 caracteres!');
                return false;
            }
        });
    }
});
</script>

<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
