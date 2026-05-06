<?php ob_start(); ?>
<div class="auth-page">
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-6 col-md-8">
            <div class="card shadow-lg">
                <div class="card-body p-5">
                    <div class="text-center mb-4">
                        <i class="fas fa-user-plus fa-3x text-primary mb-3"></i>
                        <h3 class="mb-0"><?= __('auth.create_account', 'Criar Conta') ?></h3>
                        <p class="text-muted"><?= __('auth.create_account_subtitle', 'Junte-se a milhares de clientes satisfeitos') ?></p>
                    </div>
                    
                    <form method="POST" action="/register" id="registerForm">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="nome" class="form-label"><?= __('auth.full_name', 'Nome Completo') ?> *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                                    <input type="text" class="form-control" id="nome" name="nome" 
                                           placeholder="<?= htmlspecialchars(__('auth.your_name', 'Seu nome'), ENT_QUOTES, 'UTF-8') ?>" required>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="telefone" class="form-label"><?= __('auth.phone', 'Telefone') ?> *</label>
                                <div class="input-group w-100" style="flex-wrap: nowrap;">
                                    <span class="input-group-text" style="padding-left: 10px; padding-right: 10px;"><i class="fas fa-phone"></i></span>
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
                                        <option value="0"><?= __('auth.other', 'Outro') ?></option>
                                    </select>
                                    <input type="text" class="form-control" id="telefone_numero" style="flex: 1 1 0; min-width: 0;" 
                                           placeholder="<?= htmlspecialchars(__('auth.number', 'Número'), ENT_QUOTES, 'UTF-8') ?>" required>
                                    <input type="hidden" class="form-control" id="telefone" name="telefone" value="">
                                </div>
                                <div class="input-group mt-2" id="telefone_ddi_outro_box" style="display:none;">
                                    <span class="input-group-text">DDI</span>
                                    <input type="text" class="form-control" id="telefone_ddi_outro" placeholder="<?= htmlspecialchars(__('auth.ddi_example', 'Ex: 81'), ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label"><?= __('auth.email', 'E-mail') ?> *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-envelope"></i></span>
                                <input type="email" class="form-control" id="email" name="email" 
                                       placeholder="seu@email.com" required>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="documento" class="form-label" id="label-documento">CPF *</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-id-card"></i></span>
                                <input type="text" class="form-control" id="documento" name="documento" 
                                       placeholder="000.000.000-00" required>
                            </div>
                            <small class="text-muted" id="hint-documento" style="display:none;">Obrigatório apenas para residentes no Brasil.</small>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="data_nascimento" class="form-label"><?= __('auth.birth_date', 'Data de Nascimento') ?> *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-cake-candles"></i></span>
                                    <input type="date" class="form-control" id="data_nascimento" name="data_nascimento" required>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="pais_residencia" class="form-label"><?= __('auth.country_residence', 'País de Residência') ?> *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-flag"></i></span>
                                    <?php require __DIR__ . '/../_countries.php'; ?>
                                    <?php $pr = strtoupper((string) (($dados['pais_residencia'] ?? 'BR'))); ?>
                                    <select class="form-select" id="pais_residencia" name="pais_residencia" required>
                                        <?php foreach ($countries as $code => $name): ?>
                                            <option value="<?= htmlspecialchars($code) ?>" <?= $pr === $code ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <input type="text" class="form-control mt-2" id="pais_residencia_search" placeholder="<?= htmlspecialchars(__('auth.filter_countries', 'Digite para filtrar países...'), ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3" id="field-cep">
                                <label for="cep" class="form-label" id="label-cep"><?= __('auth.zip', 'CEP') ?> *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-location-dot"></i></span>
                                    <input type="text" class="form-control" id="cep" name="cep" placeholder="00000-000" required>
                                </div>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="endereco" class="form-label" id="label-endereco"><?= __('auth.address', 'Endereço') ?> *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-map"></i></span>
                                    <input type="text" class="form-control" id="endereco" name="endereco" placeholder="<?= htmlspecialchars(__('auth.address_placeholder', 'Rua, Avenida, etc.'), ENT_QUOTES, 'UTF-8') ?>" required>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3" id="field-numero">
                                <label for="numero" class="form-label" id="label-numero"><?= __('auth.number_label', 'Número') ?> *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-hashtag"></i></span>
                                    <input type="text" class="form-control" id="numero" name="numero" placeholder="123" required>
                                </div>
                            </div>
                            <div class="col-md-8 mb-3">
                                <label for="complemento" class="form-label" id="label-complemento"><?= __('auth.complement', 'Complemento') ?></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-building"></i></span>
                                    <input type="text" class="form-control" id="complemento" name="complemento" placeholder="<?= htmlspecialchars(__('auth.complement_placeholder', 'Apto, Casa, etc.'), ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-4 mb-3" id="field-bairro">
                                <label for="bairro" class="form-label" id="label-bairro"><?= __('auth.neighborhood', 'Bairro') ?> *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-location-crosshairs"></i></span>
                                    <input type="text" class="form-control" id="bairro" name="bairro" placeholder="Centro" required>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="cidade" class="form-label" id="label-cidade"><?= __('auth.city', 'Cidade') ?> *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-city"></i></span>
                                    <input type="text" class="form-control" id="cidade" name="cidade" placeholder="São Paulo" required>
                                </div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="estado" class="form-label" id="label-estado"><?= __('auth.state', 'Estado') ?> *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-map-pin"></i></span>
                                    <input type="text" class="form-control" id="estado" name="estado" placeholder="SP" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="senha" class="form-label"><?= __('auth.password_min', 'Senha') ?> *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="senha" name="senha" 
                                           placeholder="<?= htmlspecialchars(__('auth.password_min_placeholder', 'Mínimo 6 caracteres'), ENT_QUOTES, 'UTF-8') ?>" required minlength="6">
                                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                        <i class="fas fa-eye"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-3">
                                <label for="confirmar_senha" class="form-label"><?= __('auth.confirm_password', 'Confirmar Senha') ?> *</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                                    <input type="password" class="form-control" id="confirmar_senha" 
                                           name="senha_confirmacao" placeholder="<?= htmlspecialchars(__('auth.confirm_password_placeholder', 'Confirme sua senha'), ENT_QUOTES, 'UTF-8') ?>" required>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-4">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="termos" name="termos" required>
                                <label class="form-check-label" for="termos">
                                    <?php
                                        $termsLink = '<a href="/termos-uso" class="text-decoration-none">' . __('auth.terms', 'Termos de Uso') . '</a>';
                                        $privacyLink = '<a href="/politica-privacidade" class="text-decoration-none">' . __('auth.privacy', 'Política de Privacidade') . '</a>';
                                        echo __('auth.accept_terms', 'Li e aceito os {terms} e {privacy}', ['terms' => $termsLink, 'privacy' => $privacyLink]);
                                    ?>
                                </label>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="newsletter" name="newsletter">
                                <label class="form-check-label" for="newsletter">
                                    <?= __('auth.newsletter_optin', 'Desejo receber ofertas exclusivas por e-mail') ?>
                                </label>
                            </div>
                        </div>
                        
                        <div class="d-grid mb-3">
                            <button type="submit" class="btn btn-primary btn-lg" id="registerBtn">
                                <i class="fas fa-user-plus me-2"></i> <?= __('auth.create', 'Criar Conta') ?>
                            </button>
                        </div>
                        
                        <div class="text-center">
                            <p class="mb-0">
                                <?= __('auth.have_account', 'Já tem uma conta?') ?>
                                <a href="/login" class="text-decoration-none"><?= __('auth.do_login', 'Faça login') ?></a>
                            </p>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    const I18N = {
        cpf_required: <?= json_encode(__('auth.document_required', 'CPF *'), JSON_UNESCAPED_UNICODE) ?>,
        cpf: <?= json_encode(__('auth.document', 'CPF'), JSON_UNESCAPED_UNICODE) ?>,
        doc_hint: <?= json_encode(__('auth.document_br_required_hint', 'Obrigatório apenas para residentes no Brasil.'), JSON_UNESCAPED_UNICODE) ?>,
        address_br: <?= json_encode(__('auth.address', 'Endereço'), JSON_UNESCAPED_UNICODE) ?>,
        complement_br: <?= json_encode(__('auth.complement', 'Complemento'), JSON_UNESCAPED_UNICODE) ?>,
        city_br: <?= json_encode(__('auth.city', 'Cidade'), JSON_UNESCAPED_UNICODE) ?>,
        state_br: <?= json_encode(__('auth.state', 'Estado'), JSON_UNESCAPED_UNICODE) ?>,
        zip_br: <?= json_encode(__('auth.zip', 'CEP'), JSON_UNESCAPED_UNICODE) ?>,
        number_br: <?= json_encode(__('auth.number_label', 'Número'), JSON_UNESCAPED_UNICODE) ?>,
        neighborhood_br: <?= json_encode(__('auth.neighborhood', 'Bairro'), JSON_UNESCAPED_UNICODE) ?>,
        address_line_1: <?= json_encode(__('auth.address_line_1', 'Address line 1'), JSON_UNESCAPED_UNICODE) ?>,
        address_line_2_optional: <?= json_encode(__('auth.address_line_2_optional', 'Address line 2 (optional)'), JSON_UNESCAPED_UNICODE) ?>,
        city_en: <?= json_encode(__('auth.city_en', 'City'), JSON_UNESCAPED_UNICODE) ?>,
        state_en: <?= json_encode(__('auth.state_en', 'State'), JSON_UNESCAPED_UNICODE) ?>,
        passwords_dont_match: <?= json_encode(__('auth.passwords_dont_match', 'As senhas não coincidem'), JSON_UNESCAPED_UNICODE) ?>,
        password_too_short: <?= json_encode(__('auth.password_too_short', 'A senha deve ter no mínimo 6 caracteres'), JSON_UNESCAPED_UNICODE) ?>,
        creating: <?= json_encode(__('auth.creating_account', 'Criando conta...'), JSON_UNESCAPED_UNICODE) ?>,
        created_redirect: <?= json_encode(__('auth.account_created_redirect', 'Conta criada com sucesso! Redirecionando...'), JSON_UNESCAPED_UNICODE) ?>,
        error_create: <?= json_encode(__('auth.error_create_account', 'Erro ao criar conta'), JSON_UNESCAPED_UNICODE) ?>,
        error_connection: <?= json_encode(__('auth.error_connection', 'Erro de conexão. Tente novamente.'), JSON_UNESCAPED_UNICODE) ?>
    };

    function filterSelectOptions($select, query) {
        query = (query || '').toString().trim().toLowerCase();
        $select.find('option').each(function() {
            const txt = ($(this).text() || '').toString().toLowerCase();
            const val = ($(this).val() || '').toString().toLowerCase();
            const match = (query === '') || txt.indexOf(query) !== -1 || val.indexOf(query) !== -1;
            $(this).toggle(match);
        });
    }

    function isBrazil() {
        return ($('#pais_residencia').val() || '').toString().toUpperCase() === 'BR';
    }

    function syncDocumentoRules() {
        const br = isBrazil();
        const $doc = $('#documento');
        const $label = $('#label-documento');
        const $hint = $('#hint-documento');

        $label.text(br ? 'CPF *' : 'CPF');
        $doc.prop('required', br);
        $doc.attr('placeholder', br ? '000.000.000-00' : '');
        $hint.text(I18N.doc_hint);
        $hint.toggle(!br);
    }

    function syncBairroRules() {
        const br = isBrazil();
        const $bairro = $('#bairro');
        const $label = $('#label-bairro');
        if ($bairro.length) {
            $bairro.prop('required', br);
            if ($label.length) $label.text(br ? (I18N.neighborhood_br + ' *') : I18N.neighborhood_br);
        }
    }

    function syncEnderecoRules() {
        const br = isBrazil();

        $('#label-endereco').text((br ? I18N.address_br : I18N.address_line_1) + ' *');
        $('#label-complemento').text(br ? I18N.complement_br : I18N.address_line_2_optional);
        $('#label-cidade').text((br ? I18N.city_br : I18N.city_en) + ' *');
        $('#label-estado').text((br ? I18N.state_br : I18N.state_en) + ' *');
        $('#label-cep').text(I18N.zip_br + ' *');
        $('#label-numero').text(I18N.number_br + (br ? ' *' : ''));

        $('#cep').prop('required', true);
        $('#endereco').prop('required', true);
        $('#cidade').prop('required', true);
        $('#estado').prop('required', true);
        $('#numero').prop('required', br);

        $('#field-cep').toggle(true);
        $('#field-numero').toggle(br);
        $('#field-bairro').toggle(br);

        if (!br) {
            $('#numero').val('');
            $('#bairro').val('');
        }

        syncBairroRules();
    }

    // Toggle password visibility
    $('#togglePassword').click(function() {
        const senhaField = $('#senha');
        const icon = $(this).find('i');
        
        if (senhaField.attr('type') === 'password') {
            senhaField.attr('type', 'text');
            icon.removeClass('fa-eye').addClass('fa-eye-slash');
        } else {
            senhaField.attr('type', 'password');
            icon.removeClass('fa-eye-slash').addClass('fa-eye');
        }
    });
    
    // Form validation
    $('#registerForm').on('submit', function(e) {
        e.preventDefault();

        // Montar telefone (DDI + número)
        (function() {
            var ddi = ($('#telefone_ddi').val() || '').toString();
            if (ddi === '0') {
                ddi = ($('#telefone_ddi_outro').val() || '').toString();
            }
            ddi = ddi.replace(/\D/g, '');
            var numero = ($('#telefone_numero').val() || '').toString().trim();
            if (ddi) {
                $('#telefone').val('+' + ddi + ' ' + numero);
            } else {
                $('#telefone').val(numero);
            }
        })();
        
        // Validar senhas
        const senha = $('#senha').val();
        const confirmarSenha = $('#confirmar_senha').val();
        
        if (senha !== confirmarSenha) {
            showAlert('danger', I18N.passwords_dont_match);
            return;
        }
        
        if (senha.length < 6) {
            showAlert('danger', I18N.password_too_short);
            return;
        }
        
        const btn = $('#registerBtn');
        const originalText = btn.html();
        
        btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin me-2"></i> ' + I18N.creating);
        
        $.ajax({
            url: '/register',
            method: 'POST',
            data: $(this).serialize(),
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    showAlert('success', I18N.created_redirect);
                    setTimeout(function() {
                        window.location.href = response.redirect || '/login';
                    }, 2000);
                } else {
                    showAlert('danger', response.error || I18N.error_create);
                }
            },
            error: function() {
                showAlert('danger', I18N.error_connection);
            },
            complete: function() {
                btn.prop('disabled', false).html(originalText);
            }
        });
    });
    
    // País: busca
    $('#pais_residencia_search').on('input', function() {
        filterSelectOptions($('#pais_residencia'), $(this).val());
    });

    // CPF mask (somente BR)
    $('#documento').on('input', function() {
        if (!isBrazil()) return;
        let value = $(this).val().replace(/\D/g, '').slice(0, 11);
        if (value.length >= 4) value = value.replace(/^(\d{3})(\d)/, '$1.$2');
        if (value.length >= 8) value = value.replace(/^(\d{3})\.(\d{3})(\d)/, '$1.$2.$3');
        if (value.length >= 12) value = value.replace(/^(\d{3})\.(\d{3})\.(\d{3})(\d{1,2})$/, '$1.$2.$3-$4');
        value = value.replace(/^(\d{3})\.(\d{3})\.(\d{3})(\d{1,2})$/, '$1.$2.$3-$4');
        $(this).val(value);
    });
    
    // Phone mask (somente BR)
    function isDdiBR() {
        var ddi = ($('#telefone_ddi').val() || '').toString();
        if (ddi === '0') {
            ddi = ($('#telefone_ddi_outro').val() || '').toString();
        }
        ddi = ddi.replace(/\D/g, '');
        return ddi === '55';
    }

    $('#telefone_ddi').on('change', function() {
        const other = ($('#telefone_ddi').val() || '').toString() === '0';
        $('#telefone_ddi_outro_box').toggle(other);
    }).trigger('change');

    $('#telefone_numero').on('input', function() {
        if (!isDdiBR()) return;
        let value = $(this).val().replace(/\D/g, '');
        
        if (value.length <= 10) {
            value = value.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3');
        } else {
            value = value.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
        }
        
        $(this).val(value);
    });

    $('#pais_residencia').on('change', function() {
        syncDocumentoRules();
        syncEnderecoRules();
    });

    syncDocumentoRules();
    syncEnderecoRules();
});

function showAlert(type, message) {
    const alertHtml = `
        <div class="alert alert-${type} alert-dismissible fade show auth-alert" role="alert">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;

    const $card = $('.card').first();
    $card.find('.auth-alert').remove();

    const $header = $card.find('.card-header').first();
    if ($header.length) {
        $header.after(alertHtml);
    } else {
        const $body = $card.find('.card-body').first();
        if ($body.length) {
            $body.prepend(alertHtml);
        } else {
            $card.prepend(alertHtml);
        }
    }
    
    setTimeout(function() {
        $card.find('.auth-alert').alert('close');
    }, 5000);
}
</script>

<style>
.auth-page .card {
    border: none;
    border-radius: 15px;
    transition: none;
}

.auth-page .card:hover {
    transform: none;
}

.auth-page .input-group-text {
    background: #f8f9fa;
    border-right: none;
}

.auth-page .form-control:focus {
    border-color: rgba(29, 78, 216, 0.55);
    box-shadow: 0 0 0 0.2rem rgba(29, 78, 216, 0.18);
}

.auth-page .btn-primary {
    background: var(--primary-color);
    border: 1px solid rgba(11, 31, 58, 0.22);
    border-radius: var(--radius-lg);
    padding: 12px 30px;
    font-weight: 600;
    color: #ffffff;
    transition: filter 0.2s ease, background-color 0.2s ease;
}

.auth-page .btn-primary:hover {
    transform: none;
    box-shadow: none;
    filter: brightness(1.03);
}

.auth-page .auth-alert {
    margin: 0;
    border-radius: 0;
    border-left: 0;
    border-right: 0;
}

.auth-page .text-decoration-none:hover {
    text-decoration: underline !important;
}
</style>
<?php $content = ob_get_clean(); ?>
</div>
<?php include __DIR__ . '/../layouts/main.php'; ?>
