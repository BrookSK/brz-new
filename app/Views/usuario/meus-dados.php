<?php ob_start(); ?>
<div class="container py-4">
    <div class="row g-4">
        <!-- Sidebar -->
        <?php $activePage = 'dados'; include __DIR__ . '/../partials/usuario_sidebar.php'; ?>
        
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

            <script>
            (function() {
                function filterSelectOptions(selectEl, query) {
                    if (!selectEl) return;
                    query = (query || '').toString().trim().toLowerCase();
                    var opts = selectEl.querySelectorAll('option');
                    for (var i = 0; i < opts.length; i++) {
                        var o = opts[i];
                        var txt = (o.textContent || '').toString().toLowerCase();
                        var val = (o.value || '').toString().toLowerCase();
                        var match = (query === '') || (txt.indexOf(query) !== -1) || (val.indexOf(query) !== -1);
                        o.style.display = match ? '' : 'none';
                    }
                }

                function syncDocumentoRules() {
                    var paisRes = document.getElementById('pais_residencia');
                    var docEl = document.getElementById('documento');
                    var labelEl = document.getElementById('label-documento');
                    var hintEl = document.getElementById('hint-documento');
                    if (!paisRes || !docEl || !labelEl) return;
                    var br = ((paisRes.value || '').toString().toUpperCase() === 'BR');
                    labelEl.textContent = br ? 'CPF *' : 'CPF';
                    docEl.required = br;
                    if (hintEl) {
                        hintEl.style.display = br ? 'none' : 'block';
                    }
                }

                function syncBairroRules() {
                    var paisEl = document.getElementById('pais');
                    var bairroEl = document.getElementById('bairro');
                    if (!paisEl || !bairroEl) return;
                    var br = ((paisEl.value || '').toString().toUpperCase() === 'BR');
                    bairroEl.required = br;
                }

                function parseTelefone(telefoneRaw) {
                    var raw = (telefoneRaw || '').toString().trim();
                    var m = raw.match(/^\+\s*(\d{1,4})\s*(.*)$/);
                    if (m) {
                        return { ddi: (m[1] || '').trim(), numero: (m[2] || '').trim() };
                    }
                    return { ddi: '55', numero: raw };
                }

                function getDdiValue() {
                    var ddi = (document.getElementById('telefone_ddi')?.value || '').toString();
                    if (ddi === '0') {
                        ddi = (document.getElementById('telefone_ddi_outro')?.value || '').toString();
                    }
                    return ddi.replace(/\D/g, '');
                }

                function isDdiBR() {
                    return getDdiValue() === '55';
                }

                function syncTelefoneOutroBox() {
                    var sel = document.getElementById('telefone_ddi');
                    var box = document.getElementById('telefone_ddi_outro_box');
                    if (!sel || !box) return;
                    box.style.display = (sel.value === '0') ? 'flex' : 'none';
                }

                function mountTelefoneHidden() {
                    var hidden = document.getElementById('telefone');
                    var numero = document.getElementById('telefone_numero');
                    if (!hidden || !numero) return;
                    var ddi = getDdiValue();
                    var n = (numero.value || '').toString().trim();
                    hidden.value = ddi ? ('+' + ddi + ' ' + n) : n;
                }

                function applyTelefoneMaskIfBR() {
                    var numero = document.getElementById('telefone_numero');
                    if (!numero) return;
                    if (!isDdiBR()) return;
                    var v = (numero.value || '').toString().replace(/\D/g, '');
                    if (v.length <= 10) {
                        v = v.replace(/(\d{2})(\d{4})(\d{0,4})/, '($1) $2-$3');
                    } else {
                        v = v.replace(/(\d{2})(\d{5})(\d{0,4})/, '($1) $2-$3');
                    }
                    numero.value = v;
                }

                document.addEventListener('DOMContentLoaded', function() {
                    // Telefone: separar DDI e número
                    var telefoneHidden = document.getElementById('telefone');
                    var ddiSel = document.getElementById('telefone_ddi');
                    var numeroEl = document.getElementById('telefone_numero');
                    if (telefoneHidden && ddiSel && numeroEl) {
                        var parsed = parseTelefone(telefoneHidden.value);
                        numeroEl.value = parsed.numero || '';
                        var hasOption = false;
                        for (var i = 0; i < ddiSel.options.length; i++) {
                            if ((ddiSel.options[i].value || '') === parsed.ddi) { hasOption = true; break; }
                        }
                        if (parsed.ddi && hasOption) {
                            ddiSel.value = parsed.ddi;
                        } else if (parsed.ddi) {
                            ddiSel.value = '0';
                            var outro = document.getElementById('telefone_ddi_outro');
                            if (outro) outro.value = parsed.ddi;
                        }

                        syncTelefoneOutroBox();
                        applyTelefoneMaskIfBR();
                        mountTelefoneHidden();

                        ddiSel.addEventListener('change', function() {
                            syncTelefoneOutroBox();
                            applyTelefoneMaskIfBR();
                            mountTelefoneHidden();
                        });
                        var outroEl = document.getElementById('telefone_ddi_outro');
                        if (outroEl) {
                            outroEl.addEventListener('input', function() {
                                applyTelefoneMaskIfBR();
                                mountTelefoneHidden();
                            });
                        }
                        numeroEl.addEventListener('input', function() {
                            applyTelefoneMaskIfBR();
                            mountTelefoneHidden();
                        });

                        var form = document.getElementById('formMeusDados');
                        if (form) {
                            form.addEventListener('submit', function() {
                                mountTelefoneHidden();
                            });
                        }
                    }

                    var paisResSearch = document.getElementById('pais_residencia_search');
                    var paisResSelect = document.getElementById('pais_residencia');
                    if (paisResSearch && paisResSelect) {
                        paisResSearch.addEventListener('input', function() {
                            filterSelectOptions(paisResSelect, paisResSearch.value);
                        });
                    }
                    if (paisResSelect) {
                        paisResSelect.addEventListener('change', function() {
                            syncDocumentoRules();
                        });
                    }

                    var paisSearch = document.getElementById('pais_search');
                    var paisSelect = document.getElementById('pais');
                    if (paisSearch && paisSelect) {
                        paisSearch.addEventListener('input', function() {
                            filterSelectOptions(paisSelect, paisSearch.value);
                        });
                    }
                    if (paisSelect) {
                        paisSelect.addEventListener('change', function() {
                            syncBairroRules();
                        });
                    }

                    syncDocumentoRules();
                    syncBairroRules();
                });
            })();
            </script>
            
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
                                <div class="input-group w-100" style="flex-wrap: nowrap;">
                                    <select class="form-select" id="telefone_ddi" style="flex: 0 0 76px; min-width: 76px; padding-left: 8px; padding-right: 24px;">
                                        <option value="55" selected>+55</option>
                                        <option value="1">+1</option>
                                        <option value="44">+44</option>
                                        <option value="49">+49</option>
                                        <option value="33">+33</option>
                                        <option value="34">+34</option>
                                        <option value="39">+39</option>
                                        <option value="351">+351</option>
                                        <option value="54">+54</option>
                                        <option value="56">+56</option>
                                        <option value="57">+57</option>
                                        <option value="0">Outro</option>
                                    </select>
                                    <input type="text" class="form-control" id="telefone_numero" style="flex: 1 1 0; min-width: 0;" placeholder="Número" required>
                                    <input type="hidden" class="form-control" id="telefone" name="telefone" value="<?= htmlspecialchars($usuario['telefone'] ?? '') ?>">
                                </div>
                                <div class="input-group mt-2" id="telefone_ddi_outro_box" style="display:none;">
                                    <span class="input-group-text">DDI</span>
                                    <input type="text" class="form-control" id="telefone_ddi_outro" placeholder="Ex: 81">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label for="documento" class="form-label" id="label-documento">CPF</label>
                                <input type="text" class="form-control" id="documento" name="documento" 
                                       value="<?= htmlspecialchars($usuario['documento'] ?? '') ?>" 
                                       placeholder="000.000.000-00">
                                <small class="text-muted" id="hint-documento" style="display:none;">Obrigatório apenas para residentes no Brasil.</small>
                            </div>

                            <div class="col-md-6">
                                <label for="data_nascimento" class="form-label">Data de Nascimento</label>
                                <input type="date" class="form-control" id="data_nascimento" name="data_nascimento"
                                       value="<?= htmlspecialchars($usuario['data_nascimento'] ?? '') ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="pais_residencia" class="form-label">País de Residência</label>
                                <?php require __DIR__ . '/../_countries.php'; ?>
                                <?php $pr = strtoupper((string) ($usuario['pais_residencia'] ?? 'BR')); ?>
                                <select class="form-select" id="pais_residencia" name="pais_residencia" required>
                                    <?php foreach ($countries as $code => $name): ?>
                                        <option value="<?= htmlspecialchars($code) ?>" <?= $pr === $code ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" class="form-control mt-2" id="pais_residencia_search" placeholder="Digite para filtrar países...">
                            </div>
                        </div>
                </div>
            </div>

            <?php if (empty($usuario['termos_aceitos_em'])): ?>
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-white border-0 pt-4 pb-3">
                    <h5 class="mb-0 fw-bold"><i class="fas fa-file-signature me-2"></i> Termos e Condições</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-warning">Você precisa aceitar os termos para continuar comprando.</div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="aceitar_termos" id="aceitar_termos" required>
                        <label class="form-check-label" for="aceitar_termos">
                            Li e aceito os <a href="/termos-uso" target="_blank" rel="noopener">Termos de Uso</a> e a <a href="/politica-privacidade" target="_blank" rel="noopener">Política de Privacidade</a>
                        </label>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Address Form -->
            <div class="card border-0 shadow-sm mt-4">
                <div class="card-header bg-white border-0 pt-4 pb-3">
                    <h5 class="mb-0 fw-bold">
                        <i class="fas fa-map-marker-alt me-2"></i> Endereço de Entrega
                    </h5>
                </div>
                <div class="card-body">
                        <?php $enderecoEntrega = $enderecoEntrega ?? null; ?>
                        <?php $ee = is_array($enderecoEntrega) ? $enderecoEntrega : []; ?>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="pais" class="form-label">País / Country</label>
                                <?php $pp = strtoupper((string) ($ee['pais'] ?? ($usuario['pais_residencia'] ?? 'BR'))); ?>
                                <select class="form-select" id="pais" name="pais" required>
                                    <?php foreach ($countries as $code => $name): ?>
                                        <option value="<?= htmlspecialchars($code) ?>" <?= $pp === $code ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <input type="text" class="form-control mt-2" id="pais_search" placeholder="Digite para filtrar países...">
                            </div>
                            <div class="col-md-6">
                                <label for="cep" class="form-label">CEP</label>
                                <input type="text" class="form-control" id="cep" name="cep" 
                                       value="<?= htmlspecialchars((string) ($ee['cep'] ?? ($usuario['cep'] ?? ''))) ?>" 
                                       placeholder="00000-000" required>
                            </div>
                            <div class="col-md-6">
                                <label for="endereco" class="form-label" id="label-endereco">Endereço</label>
                                <input type="text" class="form-control" id="endereco" name="endereco" 
                                       value="<?= htmlspecialchars((string) ($ee['endereco'] ?? ($ee['logradouro'] ?? ($usuario['endereco'] ?? '')))) ?>" 
                                       placeholder="Rua, Avenida, etc." required>
                            </div>
                            <div class="col-md-3" id="numero-wrap">
                                <label for="numero" class="form-label" id="label-numero">Número</label>
                                <input type="text" class="form-control" id="numero" name="numero" 
                                       value="<?= htmlspecialchars((string) ($ee['numero'] ?? ($usuario['numero'] ?? ''))) ?>" 
                                       placeholder="123">
                            </div>
                            <div class="col-md-3">
                                <label for="complemento" class="form-label" id="label-complemento">Complemento</label>
                                <input type="text" class="form-control" id="complemento" name="complemento" 
                                       value="<?= htmlspecialchars((string) ($ee['complemento'] ?? ($usuario['complemento'] ?? ''))) ?>" 
                                       placeholder="Apto, Casa, etc.">
                            </div>
                            <div class="col-md-4" id="bairro-wrap">
                                <label for="bairro" class="form-label" id="label-bairro">Bairro</label>
                                <input type="text" class="form-control" id="bairro" name="bairro" 
                                       value="<?= htmlspecialchars((string) ($ee['bairro'] ?? ($usuario['bairro'] ?? ''))) ?>" 
                                       placeholder="Centro">
                            </div>
                            <div class="col-md-4">
                                <label for="cidade" class="form-label">Cidade</label>
                                <input type="text" class="form-control" id="cidade" name="cidade" 
                                       value="<?= htmlspecialchars((string) ($ee['cidade'] ?? ($usuario['cidade'] ?? ''))) ?>" 
                                       placeholder="São Paulo" required>
                            </div>
                            <div class="col-md-4">
                                <label for="estado" class="form-label" id="label-estado">Estado</label>
                                <select class="form-select" id="estado" name="estado">
                                    <option value="">Selecione...</option>
                                    <?php $selectedUf = (string) ($ee['estado'] ?? ($ee['uf'] ?? ($usuario['estado'] ?? ''))); ?>
                                    <option value="AC" <?= $selectedUf === 'AC' ? 'selected' : '' ?>>Acre</option>
                                    <option value="AL" <?= $selectedUf === 'AL' ? 'selected' : '' ?>>Alagoas</option>
                                    <option value="AP" <?= $selectedUf === 'AP' ? 'selected' : '' ?>>Amapá</option>
                                    <option value="AM" <?= $selectedUf === 'AM' ? 'selected' : '' ?>>Amazonas</option>
                                    <option value="BA" <?= $selectedUf === 'BA' ? 'selected' : '' ?>>Bahia</option>
                                    <option value="CE" <?= $selectedUf === 'CE' ? 'selected' : '' ?>>Ceará</option>
                                    <option value="DF" <?= $selectedUf === 'DF' ? 'selected' : '' ?>>Distrito Federal</option>
                                    <option value="ES" <?= $selectedUf === 'ES' ? 'selected' : '' ?>>Espírito Santo</option>
                                    <option value="GO" <?= $selectedUf === 'GO' ? 'selected' : '' ?>>Goiás</option>
                                    <option value="MA" <?= $selectedUf === 'MA' ? 'selected' : '' ?>>Maranhão</option>
                                    <option value="MT" <?= $selectedUf === 'MT' ? 'selected' : '' ?>>Mato Grosso</option>
                                    <option value="MS" <?= $selectedUf === 'MS' ? 'selected' : '' ?>>Mato Grosso do Sul</option>
                                    <option value="MG" <?= $selectedUf === 'MG' ? 'selected' : '' ?>>Minas Gerais</option>
                                    <option value="PA" <?= $selectedUf === 'PA' ? 'selected' : '' ?>>Pará</option>
                                    <option value="PB" <?= $selectedUf === 'PB' ? 'selected' : '' ?>>Paraíba</option>
                                    <option value="PR" <?= $selectedUf === 'PR' ? 'selected' : '' ?>>Paraná</option>
                                    <option value="PE" <?= $selectedUf === 'PE' ? 'selected' : '' ?>>Pernambuco</option>
                                    <option value="PI" <?= $selectedUf === 'PI' ? 'selected' : '' ?>>Piauí</option>
                                    <option value="RJ" <?= $selectedUf === 'RJ' ? 'selected' : '' ?>>Rio de Janeiro</option>
                                    <option value="RN" <?= $selectedUf === 'RN' ? 'selected' : '' ?>>Rio Grande do Norte</option>
                                    <option value="RS" <?= $selectedUf === 'RS' ? 'selected' : '' ?>>Rio Grande do Sul</option>
                                    <option value="RO" <?= $selectedUf === 'RO' ? 'selected' : '' ?>>Rondônia</option>
                                    <option value="RR" <?= $selectedUf === 'RR' ? 'selected' : '' ?>>Roraima</option>
                                    <option value="SC" <?= $selectedUf === 'SC' ? 'selected' : '' ?>>Santa Catarina</option>
                                    <option value="SP" <?= $selectedUf === 'SP' ? 'selected' : '' ?>>São Paulo</option>
                                    <option value="SE" <?= $selectedUf === 'SE' ? 'selected' : '' ?>>Sergipe</option>
                                    <option value="TO" <?= $selectedUf === 'TO' ? 'selected' : '' ?>>Tocantins</option>
                                </select>
                                <input type="text" class="form-control" id="estado_text" name="estado_text" style="display:none;" value="<?= htmlspecialchars((string) ($ee['estado'] ?? ($ee['uf'] ?? ($usuario['estado'] ?? '')))) ?>">
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
            const paisSel = document.getElementById('pais');
            const pais = (paisSel && paisSel.value ? String(paisSel.value) : 'BR').toUpperCase();
            if (pais !== 'BR') {
                return;
            }
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

    function atualizarEnderecoPorPais() {
        const pais = (document.getElementById('pais')?.value || 'BR').toUpperCase();
        const cep = document.getElementById('cep');
        const estadoSelect = document.getElementById('estado');
        const estadoText = document.getElementById('estado_text');

        const enderecoLabel = document.getElementById('label-endereco');
        const numeroWrap = document.getElementById('numero-wrap');
        const numeroInput = document.getElementById('numero');
        const numeroLabel = document.getElementById('label-numero');
        const compLabel = document.getElementById('label-complemento');
        const bairroWrap = document.getElementById('bairro-wrap');
        const bairroInput = document.getElementById('bairro');

        const statesByCountry = {
            BR: [
                'AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'
            ],
            US: [
                'AL','AK','AZ','AR','CA','CO','CT','DE','FL','GA','HI','ID','IL','IN','IA','KS','KY','LA','ME','MD','MA','MI','MN','MS','MO','MT','NE','NV','NH','NJ','NM','NY','NC','ND','OH','OK','OR','PA','RI','SC','SD','TN','TX','UT','VT','VA','WA','WV','WI','WY','DC'
            ],
            CA: [
                'AB','BC','MB','NB','NL','NS','NT','NU','ON','PE','QC','SK','YT'
            ]
        };

        if (cep) {
            if (pais === 'BR') {
                cep.placeholder = '00000-000';
                cep.maxLength = 9;
            } else if (pais === 'US') {
                cep.placeholder = '00000';
                cep.maxLength = 10;
            } else {
                cep.placeholder = '';
                cep.maxLength = 12;
            }
        }

        if (enderecoLabel) {
            enderecoLabel.textContent = (pais === 'BR') ? 'Rua / Street *' : 'Address line 1 *';
        }
        if (compLabel) {
            compLabel.textContent = (pais === 'BR') ? 'Complemento / Complement' : 'Address line 2 (optional)';
        }

        if (numeroWrap && numeroInput && numeroLabel) {
            if (pais === 'BR') {
                numeroWrap.style.display = '';
                numeroInput.required = true;
                numeroLabel.textContent = 'Número / Number *';
            } else {
                numeroWrap.style.display = 'none';
                numeroInput.required = false;
                numeroInput.value = '';
            }
        }

        if (bairroWrap && bairroInput) {
            if (pais === 'BR') {
                bairroWrap.style.display = '';
                bairroInput.required = true;
            } else {
                bairroWrap.style.display = 'none';
                bairroInput.required = false;
                bairroInput.value = '';
            }
        }

        if (estadoSelect && estadoText) {
            const list = statesByCountry[pais] || null;
            const shouldUseSelect = Array.isArray(list) && list.length > 0;

            const estadoRequired = (pais === 'BR' || pais === 'US' || pais === 'CA');

            if (shouldUseSelect) {
                const current = String(estadoSelect.value || estadoText.value || '').trim();

                while (estadoSelect.options.length > 0) {
                    estadoSelect.remove(0);
                }
                const optEmpty = document.createElement('option');
                optEmpty.value = '';
                optEmpty.textContent = 'Selecione...';
                estadoSelect.appendChild(optEmpty);
                list.forEach((uf) => {
                    const opt = document.createElement('option');
                    opt.value = uf;
                    opt.textContent = uf;
                    if (current && uf === current.toUpperCase()) {
                        opt.selected = true;
                    }
                    estadoSelect.appendChild(opt);
                });

                estadoSelect.style.display = '';
                estadoText.style.display = 'none';

                estadoSelect.name = 'estado';
                estadoSelect.required = estadoRequired;
                estadoSelect.disabled = false;

                estadoText.name = 'estado_text';
                estadoText.required = false;
                estadoText.disabled = true;
            } else {
                estadoSelect.style.display = 'none';
                estadoText.style.display = '';

                estadoSelect.name = 'estado_ui';
                estadoSelect.required = false;
                estadoSelect.disabled = true;

                estadoText.name = 'estado';
                estadoText.required = estadoRequired;
                estadoText.disabled = false;
            }
        }
    }

    const paisSel = document.getElementById('pais');
    if (paisSel) {
        paisSel.addEventListener('change', atualizarEnderecoPorPais);
    }
    atualizarEnderecoPorPais();
    
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

.col-lg-9 .nav-link {
    border-radius: 8px;
    padding: 12px 16px;
    margin-bottom: 8px;
    transition: all 0.3s ease;
    color: #6c757d;
    text-decoration: none;
    display: flex;
    align-items: center;
}

.col-lg-9 .nav-link:hover {
    background-color: #f8f9fa;
    color: #495057;
    transform: none;
}

.col-lg-9 .nav-link.active {
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

<?php
    $avatarColumnCandidates = ['avatar', 'foto_perfil', 'imagem_perfil', 'foto'];
    $avatarUrl = null;
    foreach ($avatarColumnCandidates as $c) {
        if (!empty($usuario[$c]) && is_string($usuario[$c])) {
            $avatarUrl = $usuario[$c];
            break;
        }
    }
    if (empty($avatarUrl)) {
        $avatarUrl = $_SESSION['usuario_avatar'] ?? null;
    }
    if (empty($avatarUrl)) {
        $avatarUrl = 'https://ui-avatars.com/api/?name=' . urlencode((string) ($usuario['nome'] ?? '')) . '&background=0b1f3a&color=fff&size=512';
    }
?>

<div class="modal fade" id="avatarViewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Foto de Perfil</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img src="<?= htmlspecialchars((string) $avatarUrl) ?>" alt="<?= htmlspecialchars((string) ($usuario['nome'] ?? '')) ?>" class="img-fluid rounded" style="max-height: 70vh; object-fit: contain;">
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

.col-lg-9 .nav-link {
    border-radius: 8px;
    padding: 12px 16px;
    margin-bottom: 8px;
    transition: all 0.3s ease;
    color: #6c757d;
    text-decoration: none;
    display: flex;
    align-items: center;
}

.col-lg-9 .nav-link:hover {
    background-color: #f8f9fa;
    color: #495057;
    transform: none;
}

.col-lg-9 .nav-link.active {
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
