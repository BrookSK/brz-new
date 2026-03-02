<?php ob_start(); ?>
<div class="container-fluid px-0">
    <form id="checkout-form" method="POST">
    <script>
        window.CHECKOUT_I18N = {
            attention: <?= json_encode(__('checkout.attention', 'Atenção'), JSON_UNESCAPED_UNICODE) ?>,
            complete_data_and_terms: <?= json_encode(__('checkout.complete_data_and_terms', 'você precisa completar seus dados e aceitar os termos para finalizar a compra.'), JSON_UNESCAPED_UNICODE) ?>,
            pending_fields: <?= json_encode(__('checkout.pending_fields', 'Campos pendentes:'), JSON_UNESCAPED_UNICODE) ?>,
            complete_registration: <?= json_encode(__('checkout.complete_registration', 'Completar cadastro'), JSON_UNESCAPED_UNICODE) ?>,
            other: <?= json_encode(__('auth.other', 'Outro'), JSON_UNESCAPED_UNICODE) ?>,
            number: <?= json_encode(__('auth.number', 'Número'), JSON_UNESCAPED_UNICODE) ?>,
            ddi_example: <?= json_encode(__('auth.ddi_example', 'Ex: 81'), JSON_UNESCAPED_UNICODE) ?>,
            select: <?= json_encode(__('common.select', 'Selecione...'), JSON_UNESCAPED_UNICODE) ?>,
            free_shipping: <?= json_encode(__('cart.free_shipping', 'Frete grátis'), JSON_UNESCAPED_UNICODE) ?>,
            processing_order: <?= json_encode(__('checkout.processing_order', 'Processando seu pedido...'), JSON_UNESCAPED_UNICODE) ?>,
            stripe_not_configured: <?= json_encode(__('checkout.stripe_not_configured', 'Stripe não configurado. Verifique as configurações de pagamento.'), JSON_UNESCAPED_UNICODE) ?>,
            stripe_card_form_error: <?= json_encode(__('checkout.stripe_card_form_error', 'Erro ao carregar o formulário de cartão. Verifique a chave pública do Stripe.'), JSON_UNESCAPED_UNICODE) ?>,
            payment_error_prefix: <?= json_encode(__('checkout.payment_error_prefix', 'Erro no pagamento:'), JSON_UNESCAPED_UNICODE) ?>,
            delivery_outside_br_no_taxes: <?= json_encode(__('checkout.delivery_outside_br_no_taxes', 'A entrega para fora do Brasil não inclui impostos brasileiros. A tributação local é responsabilidade do cliente.'), JSON_UNESCAPED_UNICODE) ?>,
            terms_and_conditions: <?= json_encode(__('checkout.terms_and_conditions', 'termos e condições'), JSON_UNESCAPED_UNICODE) ?>,
            terms_accept_full: <?= json_encode(__('checkout.terms_accept_full', 'Li e aceito os {terms} de uso e política de privacidade. *'), JSON_UNESCAPED_UNICODE) ?>,
            finalize_secure: <?= json_encode(__('checkout.finalize_secure', 'Finalizar Pedido com Pagamento Seguro'), JSON_UNESCAPED_UNICODE) ?>,
            address_line_1: <?= json_encode(__('checkout.address_line_1', 'Address line 1 *'), JSON_UNESCAPED_UNICODE) ?>,
            address_line_2_optional: <?= json_encode(__('checkout.address_line_2_optional', 'Address line 2 (optional)'), JSON_UNESCAPED_UNICODE) ?>,
            cpf_cnpj_required: <?= json_encode(__('checkout.cpf_cnpj_required', 'CPF/CNPJ *'), JSON_UNESCAPED_UNICODE) ?>,
            cpf_cnpj_optional: <?= json_encode(__('checkout.cpf_cnpj_optional', 'CPF/CNPJ (opcional)'), JSON_UNESCAPED_UNICODE) ?>,
            subtotal_products: <?= json_encode(__('checkout.subtotal_products', 'Subtotal Produtos:'), JSON_UNESCAPED_UNICODE) ?>,
            subtotal_products_us_tax: <?= json_encode(__('checkout.subtotal_products_us_tax', 'Subtotal Produtos (incl. imposto EUA 10%):'), JSON_UNESCAPED_UNICODE) ?>,
            select_state: <?= json_encode(__('common.select', 'Selecione...'), JSON_UNESCAPED_UNICODE) ?>,
            state_label: <?= json_encode(__('auth.state', 'Estado'), JSON_UNESCAPED_UNICODE) ?>,
            state_label_en: <?= json_encode(__('checkout.state_en', 'State'), JSON_UNESCAPED_UNICODE) ?>,
            district_label: <?= json_encode(__('auth.neighborhood', 'Bairro'), JSON_UNESCAPED_UNICODE) ?>,
            district_label_en: <?= json_encode(__('checkout.district_en', 'District'), JSON_UNESCAPED_UNICODE) ?>,
            number_label: <?= json_encode(__('auth.number_label', 'Número'), JSON_UNESCAPED_UNICODE) ?>,
            number_label_en: <?= json_encode(__('checkout.number_en', 'Number'), JSON_UNESCAPED_UNICODE) ?>
        };
    </script>
    <div class="row g-4 align-items-start">
        <!-- Formulário Principal -->
        <div class="col-lg-8 mb-4 mb-lg-0">
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                        <?php
                            $warnPerfil = (!empty($usuario) && (!($perfil_ok ?? true) || !($termos_ok ?? true)));
                            $warnMissing = (!empty($campos_faltando) && is_array($campos_faltando)) ? array_values($campos_faltando) : [];
                        ?>
                        <div class="alert alert-warning" id="checkout-perfil-warning" style="display: <?= $warnPerfil ? 'block' : 'none' ?>;" data-missing='<?= htmlspecialchars(json_encode($warnMissing, JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>' data-termos-ok="<?= (!empty($termos_ok) ? '1' : '0') ?>">
                            <div><strong><?= __('checkout.attention', 'Atenção') ?>:</strong> <?= __('checkout.complete_data_and_terms', 'você precisa completar seus dados e aceitar os termos para finalizar a compra.') ?></div>
                            <div class="small mt-1" id="checkout-perfil-warning-missing" style="display:none;"><?= __('checkout.pending_fields', 'Campos pendentes:') ?> <strong></strong></div>
                            <div class="mt-2"><a class="btn btn-sm btn-outline-dark" href="/meus-dados"><?= __('checkout.complete_registration', 'Completar cadastro') ?></a></div>
                        </div>

                        <?php if (empty($usuario) || empty($usuario['id'])): ?>
                            <div class="alert alert-info">
                                <div><strong><?= __('checkout.have_account_title', 'Já tem conta?') ?></strong> <?= __('checkout.have_account_hint', 'Faça login para finalizar mais rápido.') ?></div>
                                <div class="mt-2 d-flex flex-wrap gap-2">
                                    <a class="btn btn-sm btn-outline-primary" href="/login?redirect=<?= urlencode('/checkout') ?>"><?= __('auth.login', 'Fazer login') ?></a>
                                    <a class="btn btn-sm btn-primary" href="/register?redirect=<?= urlencode('/checkout') ?>"><?= __('auth.create_account', 'Criar conta') ?></a>
                                </div>
                                <div class="small mt-2 text-muted"><?= __('checkout.auto_account_note', 'Se você continuar preenchendo os dados, o sistema cria sua conta automaticamente no final do checkout.') ?></div>
                            </div>
                        <?php endif; ?>

                        <!-- Campo oculto para moeda -->
                        <input type="hidden" name="moeda" id="moeda_hidden" value="BRL">
                        
                        <!-- Dados Pessoais -->
                        <div class="mb-4">
                            <h6 class="mb-3"><i class="fas fa-user"></i> <?= __('checkout.personal_data', 'Dados Pessoais') ?></h6>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><?= __('auth.full_name', 'Nome Completo') ?> *</label>
                                    <input type="text" class="form-control" name="nome" required 
                                           value="<?= htmlspecialchars($usuario['nome'] ?? '') ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><?= __('auth.email', 'E-mail') ?> *</label>
                                    <input type="email" class="form-control" name="email" required 
                                           value="<?= htmlspecialchars($usuario['email'] ?? '') ?>">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label" id="label-documento">CPF</label>
                                    <input type="text" class="form-control" name="documento" id="documento" 
                                           value="<?= htmlspecialchars($usuario['documento'] ?? '') ?>">
                                    <small class="text-muted" id="hint-documento" style="display:none;"><?= __('auth.document_br_required_hint', 'Obrigatório apenas para residentes no Brasil.') ?></small>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><?= __('checkout.phone_whatsapp', 'Telefone com WhatsApp') ?> *</label>
                                    <div class="input-group w-100" style="flex-wrap: nowrap;">
                                        <select class="form-select" id="telefone_ddi" name="telefone_ddi" style="flex: 0 0 76px; min-width: 76px; padding-left: 8px; padding-right: 24px;">
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
                                        <input type="text" class="form-control" id="telefone_numero" name="telefone_numero" style="flex: 1 1 0; min-width: 0;" placeholder="<?= htmlspecialchars(__('auth.number', 'Número'), ENT_QUOTES, 'UTF-8') ?>" required>
                                        <input type="hidden" class="form-control" name="telefone" id="telefone" value="<?= htmlspecialchars($usuario['telefone'] ?? '') ?>">
                                    </div>
                                    <div class="input-group mt-2" id="telefone_ddi_outro_box" style="display:none;">
                                        <span class="input-group-text">DDI</span>
                                        <input type="text" class="form-control" id="telefone_ddi_outro" name="telefone_ddi_outro" placeholder="<?= htmlspecialchars(__('auth.ddi_example', 'Ex: 81'), ENT_QUOTES, 'UTF-8') ?>">
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><?= __('auth.birth_date', 'Data de Nascimento') ?> *</label>
                                    <input type="date" class="form-control" name="data_nascimento" required
                                           value="<?= htmlspecialchars((string) ($usuario['data_nascimento'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                </div>
                            </div>
                        </div>

                        <div class="mb-4">
                            <h6 class="mb-3"><i class="fas fa-truck"></i> <?= __('checkout.delivery', 'Entrega') ?></h6>
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" value="1" id="entrega_para_outro" name="entrega_para_outro">
                                <label class="form-check-label" for="entrega_para_outro"><?= __('checkout.deliver_to_other', 'Entregar para outra pessoa / outro endereço') ?></label>
                            </div>

                            <div id="destinatario-box" style="display:none;">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label"><?= __('checkout.recipient_name', 'Nome do destinatário') ?> *</label>
                                        <input type="text" class="form-control" name="destinatario_nome" id="destinatario_nome" value="">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label" id="label-destinatario-documento"><?= __('checkout.recipient_cpf', 'CPF do destinatário') ?></label>
                                        <input type="text" class="form-control" name="destinatario_documento" id="destinatario_documento" value="">
                                        <small class="text-muted" id="hint-destinatario-documento" style="display:none;"><?= __('checkout.recipient_doc_br_only', 'Obrigatório apenas para entregas no Brasil.') ?></small>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label"><?= __('checkout.recipient_phone', 'Telefone do destinatário') ?> *</label>
                                        <input type="text" class="form-control" name="destinatario_telefone" id="destinatario_telefone" value="">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Endereço de Entrega -->
                        <div class="mb-4">
                            <h6 class="mb-3"><i class="fas fa-map-marker-alt"></i> <?= __('checkout.shipping_address', 'Endereço de Entrega') ?></h6>
                            
                            <?php if (!empty($usuario) && !empty($enderecos)): ?>
                                <!-- Dropdown para selecionar endereço -->
                                <div class="mb-3">
                                    <label class="form-label"><?= __('checkout.select_address', 'Selecione um endereço') ?></label>
                                    <select class="form-select" id="endereco-select" name="endereco_selecionado">
                                        <option value=""><?= __('checkout.new_address', 'Novo endereço...') ?></option>
                                        <?php foreach ($enderecos as $endereco): ?>
                                            <option value="<?= $endereco['id'] ?>" 
                                                    data-pais="<?= htmlspecialchars((string) ($endereco['pais'] ?? 'BR')) ?>"
                                                    data-cep="<?= htmlspecialchars($endereco['cep']) ?>"
                                                    data-endereco="<?= htmlspecialchars($endereco['endereco']) ?>"
                                                    data-numero="<?= htmlspecialchars($endereco['numero']) ?>"
                                                    data-complemento="<?= htmlspecialchars($endereco['complemento']) ?>"
                                                    data-bairro="<?= htmlspecialchars($endereco['bairro']) ?>"
                                                    data-cidade="<?= htmlspecialchars($endereco['cidade']) ?>"
                                                    data-estado="<?= htmlspecialchars($endereco['estado']) ?>"
                                                    <?= $endereco['principal'] ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($endereco['endereco']) ?>, <?= htmlspecialchars($endereco['numero']) ?> - <?= htmlspecialchars($endereco['bairro']) ?>, <?= htmlspecialchars($endereco['cidade']) ?>/<?= htmlspecialchars($endereco['estado']) ?><?= $endereco['principal'] ? ' (Principal)' : '' ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <!-- Botão para adicionar novo endereço -->
                                <div class="mb-3">
                                    <button type="button" class="btn btn-outline-primary btn-sm" id="btn-novo-endereco">
                                        <i class="fas fa-plus me-2"></i> <?= __('checkout.add_new_address', 'Adicionar Novo Endereço') ?>
                                    </button>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Formulário de endereço (inicialmente oculto se houver endereços) -->
                            <div id="endereco-form" <?= (!empty($usuario) && !empty($enderecos)) ? 'style="display: none;"' : '' ?>>
                                <div class="row">
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label"><?= __('checkout.country', 'País') ?> / <?= __('checkout.country_en', 'Country') ?> *</label>
                                        <?php require __DIR__ . '/../_countries.php'; ?>
                                        <?php $pp = strtoupper((string) (($endereco_prefill['pais'] ?? 'BR'))); ?>
                                        <select class="form-select" name="pais" id="pais" required>
                                            <?php foreach ($countries as $code => $name): ?>
                                                <option value="<?= htmlspecialchars($code) ?>" <?= $pp === $code ? 'selected' : '' ?>><?= htmlspecialchars($name) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input type="text" class="form-control mt-2" id="pais_search" placeholder="<?= htmlspecialchars(__('auth.filter_countries', 'Digite para filtrar países...'), ENT_QUOTES, 'UTF-8') ?>">
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label" id="label-cep"><?= __('auth.zip', 'CEP') ?> / <?= __('checkout.zip', 'ZIP Code') ?> *</label>
                                        <input type="text" class="form-control" name="cep" required 
                                               id="cep" maxlength="12"
                                               value="<?= htmlspecialchars((string) ($endereco_prefill['cep'] ?? '')) ?>">
                                    </div>
                                    <div class="col-md-9 mb-3">
                                        <label class="form-label" id="label-endereco"><?= __('checkout.street', 'Rua') ?> / <?= __('checkout.street_en', 'Street') ?> *</label>
                                        <input type="text" class="form-control" name="endereco" required id="endereco"
                                               value="<?= htmlspecialchars((string) ($endereco_prefill['endereco'] ?? '')) ?>">
                                    </div>
                                    <div class="col-md-3 mb-3" id="numero-wrap">
                                        <label class="form-label" id="label-numero"><?= __('auth.number_label', 'Número') ?> / <?= __('checkout.number_en', 'Number') ?> *</label>
                                        <input type="text" class="form-control" name="numero" id="numero"
                                               value="<?= htmlspecialchars((string) ($endereco_prefill['numero'] ?? '')) ?>">
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label" id="label-complemento"><?= __('auth.complement', 'Complemento') ?> / <?= __('checkout.complement_en', 'Complement') ?></label>
                                        <input type="text" class="form-control" name="complemento"
                                               value="<?= htmlspecialchars((string) ($endereco_prefill['complemento'] ?? '')) ?>">
                                    </div>
                                    <div class="col-md-3 mb-3" id="bairro-wrap">
                                        <label class="form-label" id="label-bairro"><?= __('auth.neighborhood', 'Bairro') ?> / <?= __('checkout.district_en', 'District') ?></label>
                                        <input type="text" class="form-control" name="bairro" id="bairro"
                                               value="<?= htmlspecialchars((string) ($endereco_prefill['bairro'] ?? '')) ?>">
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label"><?= __('auth.city', 'Cidade') ?> / <?= __('checkout.city_en', 'City') ?> *</label>
                                        <input type="text" class="form-control" name="cidade" required id="cidade"
                                               value="<?= htmlspecialchars((string) ($endereco_prefill['cidade'] ?? '')) ?>">
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <label class="form-label" id="label-estado"><?= __('auth.state', 'Estado') ?> / <?= __('checkout.state_en', 'State') ?> *</label>
                                        <select class="form-select" name="estado" id="estado">
                                            <option value=""><?= __('common.select', 'Selecione...') ?></option>
                                            <?php $ufSel = (string) ($endereco_prefill['estado'] ?? ''); ?>
                                            <?php foreach (['AC','AL','AP','AM','BA','CE','DF','ES','GO','MA','MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN','RS','RO','RR','SC','SP','SE','TO'] as $uf): ?>
                                                <option value="<?= $uf ?>" <?= $ufSel === $uf ? 'selected' : '' ?>><?= $uf ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input type="text" class="form-control" id="estado_text" name="estado_text" style="display:none;" value="<?= htmlspecialchars((string) ($endereco_prefill['estado'] ?? '')) ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Senha (se não logado) -->
                        <?php if (empty($usuario)): ?>
                        <div class="mb-4">
                            <h6 class="mb-3"><i class="fas fa-lock"></i> <?= __('auth.create_account', 'Criar Conta') ?></h6>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><?= __('auth.password_min', 'Senha') ?> *</label>
                                    <input type="password" class="form-control" name="senha" required minlength="6">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label"><?= __('auth.confirm_password', 'Confirmar Senha') ?> *</label>
                                    <input type="password" class="form-control" name="senha_confirmacao" required>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                </div>
            </div>
        </div>

        <script>
        (function() {
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
                var paisEl = document.getElementById('pais');
                var docEl = document.getElementById('documento');
                var labelEl = document.getElementById('label-documento');
                var hintEl = document.getElementById('hint-documento');
                if (!paisEl || !docEl || !labelEl) return;
                var br = ((paisEl.value || '').toString().toUpperCase() === 'BR');
                labelEl.textContent = br ? (window.CHECKOUT_I18N && window.CHECKOUT_I18N.cpf_cnpj_required ? window.CHECKOUT_I18N.cpf_cnpj_required : 'CPF *') : 'CPF';
                docEl.required = br;
                if (hintEl) {
                    hintEl.style.display = br ? 'none' : 'block';
                }
            }

            function syncBairroRules() {
                var paisEl = document.getElementById('pais');
                var bairroEl = document.getElementById('bairro');
                var labelEl = document.getElementById('label-bairro');
                if (!paisEl || !bairroEl || !labelEl) return;
                var br = ((paisEl.value || '').toString().toUpperCase() === 'BR');
                bairroEl.required = br;
                const pt = (window.CHECKOUT_I18N && window.CHECKOUT_I18N.district_label) ? window.CHECKOUT_I18N.district_label : 'Bairro';
                const en = (window.CHECKOUT_I18N && window.CHECKOUT_I18N.district_label_en) ? window.CHECKOUT_I18N.district_label_en : 'District';
                labelEl.textContent = br ? (pt + ' / ' + en + ' *') : (pt + ' / ' + en);
            }

            function syncImpostosRules() {
                var paisEl = document.getElementById('pais');
                if (!paisEl) return;

                var pais = ((paisEl.value || '').toString().toUpperCase() || 'BR');
                var br = (pais === 'BR');
                var us = (pais === 'US');
                var impostosRow = document.getElementById('impostos-row');
                var impostosEl = document.getElementById('impostos');
                var alertEl = document.getElementById('entrega-fora-br-alert');
                var subtotalLabel = document.getElementById('subtotal-label');

                if (!window.checkoutBaseValues) return;
                if (!window.checkoutOriginalValues) {
                    window.checkoutOriginalValues = Object.assign({}, window.checkoutBaseValues);
                }

                var baseSubtotal = (window.checkoutBaseValues.subtotal || 0);
                if (us) {
                    window.checkoutOriginalValues.subtotal = baseSubtotal * 1.10;
                    if (subtotalLabel) subtotalLabel.textContent = (window.CHECKOUT_I18N && window.CHECKOUT_I18N.subtotal_products_us_tax) ? window.CHECKOUT_I18N.subtotal_products_us_tax : 'Subtotal Produtos (incl. imposto EUA 10%):';
                } else {
                    window.checkoutOriginalValues.subtotal = baseSubtotal;
                    if (subtotalLabel) subtotalLabel.textContent = (window.CHECKOUT_I18N && window.CHECKOUT_I18N.subtotal_products) ? window.CHECKOUT_I18N.subtotal_products : 'Subtotal Produtos:';
                }

                if (br) {
                    window.checkoutOriginalValues.impostos = (window.checkoutBaseValues.impostos || 0);
                    window.checkoutOriginalValues.total = (window.checkoutBaseValues.total || 0);
                    if (impostosRow) {
                        impostosRow.classList.remove('d-none');
                    }
                    if (alertEl) {
                        alertEl.classList.add('d-none');
                    }
                } else {
                    window.checkoutOriginalValues.impostos = 0;
                    window.checkoutOriginalValues.total = (window.checkoutOriginalValues.subtotal || 0) + (window.checkoutBaseValues.frete || 0) + (window.checkoutBaseValues.taxaServico || 0);
                    if (impostosEl) {
                        impostosEl.setAttribute('data-original-value', '0');
                        impostosEl.textContent = '0';
                    }
                    if (impostosRow) {
                        impostosRow.classList.add('d-none');
                    }
                    if (alertEl) {
                        alertEl.classList.remove('d-none');
                    }
                }

                try {
                    var moedaHidden = document.getElementById('moeda_hidden');
                    var curr = moedaHidden ? (moedaHidden.value || 'BRL') : 'BRL';
                    updatePrices(curr);
                } catch (e) {
                }
            }

            function computeMissingFiltered() {
                var warn = document.getElementById('checkout-perfil-warning');
                if (!warn) return;

                var missing = [];
                try {
                    missing = JSON.parse(warn.getAttribute('data-missing') || '[]') || [];
                } catch (e) {
                    missing = [];
                }

                var termosOk = (warn.getAttribute('data-termos-ok') === '1');

                var sel = document.getElementById('endereco-select');
                if (sel && sel.value) {
                    var opt = sel.options[sel.selectedIndex];
                    if (opt) {
                        var pais = (opt.getAttribute('data-pais') || 'BR').toString().trim().toUpperCase();
                        if (!pais) pais = 'BR';
                        var addrFields = ['cep', 'endereco', 'cidade'];
                        if (pais === 'BR') {
                            addrFields.push('numero');
                            addrFields.push('bairro');
                        }
                        if (pais === 'BR' || pais === 'US' || pais === 'CA') {
                            addrFields.push('estado');
                        }
                        var hasAll = true;
                        for (var i = 0; i < addrFields.length; i++) {
                            var k = 'data-' + addrFields[i];
                            var v = (opt.getAttribute(k) || '').toString().trim();
                            if (!v) { hasAll = false; break; }
                        }
                        if (hasAll) {
                            missing = missing.filter(function(it) { return addrFields.indexOf((it || '').toString()) === -1; });
                        }
                    }
                }

                var show = (!termosOk) || (missing && missing.length > 0);
                warn.style.display = show ? 'block' : 'none';

                var box = document.getElementById('checkout-perfil-warning-missing');
                if (box) {
                    var strong = box.querySelector('strong');
                    if (missing && missing.length > 0) {
                        if (strong) strong.textContent = missing.join(', ');
                        box.style.display = 'block';
                    } else {
                        if (strong) strong.textContent = '';
                        box.style.display = 'none';
                    }
                }
            }

            document.addEventListener('DOMContentLoaded', function() {
                computeMissingFiltered();
                var sel = document.getElementById('endereco-select');
                if (sel) {
                    sel.addEventListener('change', computeMissingFiltered);
                }

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

                    // Garante montagem antes de submit
                    var form = numeroEl.closest('form');
                    if (form) {
                        form.addEventListener('submit', function() {
                            mountTelefoneHidden();
                        });
                    }
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
                        syncDocumentoRules();
                        syncBairroRules();
                        syncImpostosRules();
                    });
                }

                syncDocumentoRules();
                syncBairroRules();
                syncImpostosRules();
            });
        })();
        </script>

        <!-- Resumo do Pedido (Fixo) -->
        <div class="col-lg-4">
            <div class="checkout-sticky">
                <div class="card border-0 shadow-sm">
                    <div class="card-header">
                        <h6 class="mb-0"><i class="fas fa-receipt"></i> <?= __('cart.order_summary', 'Resumo do Pedido') ?></h6>
                    </div>
                    <div class="card-body">
                        <div id="resumo-pedido">
                            <!-- Itens do Carrinho -->
                            <div class="mb-3">
                                <h6><?= __('checkout.order_items', 'Itens do Pedido') ?></h6>
                                <div id="items-resumo">
                                    <?php foreach ($items as $item): ?>
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <small>
                                                <?= htmlspecialchars($item['nome']) ?> (<?= $item['quantidade'] ?>x)
                                                <?php if (!empty($item['clube_ativo'])): ?>
                                                    <span class="badge" style="background:#0b1f3a; margin-left: 6px;"><i class="fas fa-crown me-1"></i><?= __('cart.club_active', 'Clube Ativo') ?></span>
                                                <?php endif; ?>
                                            </small>
                                            <?php if (!empty($item['variacao_descricao'])): ?>
                                                <div class="small text-muted"><?= htmlspecialchars((string) $item['variacao_descricao'], ENT_QUOTES, 'UTF-8') ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <small class="item-price" data-original-price="<?= $item['subtotal'] ?>"><?= number_format($item['subtotal'], 2, '.', ',') ?></small>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <hr>

                            <!-- Informações de Pagamento -->
                            <div class="mb-3">
                                <h6><i class="fas fa-credit-card"></i> <?= __('checkout.payment_info', 'Informações de Pagamento') ?></h6>
                                <div class="border rounded p-3" style="background: rgba(248, 250, 252, 0.85);">
                                    <div class="row g-2">
                                        <div class="col-12">
                                            <label class="form-label"><?= __('checkout.payment_method', 'Forma de Pagamento') ?></label>
                                            <select name="forma_pagamento" class="form-select" id="forma_pagamento" required onchange="atualizarFormaPagamento()">
                                                <option value=""><?= __('common.select', 'Selecione...') ?></option>
                                                <option value="carteira"><?= __('checkout.payment.wallet_credit', 'Crédito da Carteira') ?></option>
                                                <option value="cartao_credito"><?= __('checkout.payment.credit_card', 'Cartão de Crédito') ?></option>
                                                <option value="boleto"><?= __('checkout.payment.boleto', 'Boleto Bancário') ?></option>
                                                <option value="pix">PIX</option>
                                            </select>
                                            <script>
                                            // Adicionar listener para debug
                                            document.getElementById('forma_pagamento').addEventListener('change', function() {
                                                console.log('🔍 [DEBUG] Forma de pagamento alterada para:', this.value);
                                                console.log('🔍 [DEBUG] Chamando atualizarFormaPagamento()');
                                                atualizarFormaPagamento();
                                            });
                                            
                                            // Verificar se já há uma forma de pagamento selecionada ao carregar
                                            document.addEventListener('DOMContentLoaded', function() {
                                                const formaPagamentoSelect = document.getElementById('forma_pagamento');
                                                if (formaPagamentoSelect && formaPagamentoSelect.value) {
                                                    console.log('🔍 [INIT] Forma de pagamento já selecionada:', formaPagamentoSelect.value);
                                                    atualizarFormaPagamento();
                                                }
                                            });
                                            
                                            // Fallback para garantir que a função esteja disponível
                                            setTimeout(function() {
                                                if (typeof atualizarFormaPagamento === 'function') {
                                                    console.log('🔍 [VERIFY] Função atualizarFormaPagamento está disponível');
                                                } else {
                                                    console.error('❌ [ERROR] Função atualizarFormaPagamento não está disponível');
                                                }
                                            }, 100);
                                            </script>
                                        </div>
                                        <div class="col-12" id="campos-cartao" style="display: none;">
                                            <div id="campos-cartao-stripe" style="display:none;">
                                                <label class="form-label"><?= __('checkout.card', 'Cartão') ?></label>
                                                <div id="stripe-card-element" class="form-control" style="padding: 12px; background: #fff;"></div>
                                                <div id="stripe-card-errors" class="text-danger small mt-2" style="display:none;"></div>
                                            </div>
                                            <div id="campos-cartao-manual">
                                                <label class="form-label"><?= __('checkout.card_holder_name', 'Nome no Cartão') ?></label>
                                                <input type="text" name="card_holder_name" class="form-control" placeholder="<?= htmlspecialchars(__('checkout.card_holder_name_placeholder', 'Nome como está no cartão'), ENT_QUOTES, 'UTF-8') ?>" required>
                                                <div class="row g-2 mt-2">
                                                    <div class="col-6">
                                                        <label class="form-label"><?= __('checkout.card_number', 'Número do Cartão') ?></label>
                                                        <input type="text" name="card_number" class="form-control" placeholder="0000 0000 0000 0000" maxlength="19" autocomplete="cc-number" inputmode="numeric" required>
                                                    </div>
                                                    <div class="col-3">
                                                        <label class="form-label"><?= __('checkout.card_expiry', 'Validade') ?></label>
                                                        <input type="text" name="card_expiry_month" class="form-control" placeholder="MM" maxlength="2" autocomplete="cc-exp-month" inputmode="numeric" required>
                                                    </div>
                                                    <div class="col-3">
                                                        <label class="form-label">&nbsp;</label>
                                                        <input type="text" name="card_expiry_year" class="form-control" placeholder="AAAA" maxlength="4" autocomplete="cc-exp-year" inputmode="numeric" required>
                                                    </div>
                                                </div>
                                                <div class="row g-2 mt-2">
                                                    <div class="col-6">
                                                        <label class="form-label">CVV</label>
                                                        <input type="text" name="card_cvv" class="form-control" placeholder="123" maxlength="4" autocomplete="cc-csc" inputmode="numeric" required>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12" id="campos-boleto" style="display: none;">
                                            <label class="form-label"><?= __('checkout.holder_document', 'CPF/CNPJ do Titular') ?></label>
                                            <input type="text" name="boleto_cpf" class="form-control" placeholder="000.000.000-00">
                                        </div>
                                        <div class="col-12" id="campos-transferencia" style="display: none;">
                                            <label class="form-label"><?= __('checkout.bank', 'Banco') ?></label>
                                            <select name="banco" class="form-select">
                                                <option value=""><?= __('common.select', 'Selecione...') ?></option>
                                                <option value="001">Banco do Brasil</option>
                                                <option value="104">Caixa Econômica Federal</option>
                                                <option value="237">Banco Bradesco</option>
                                                <option value="341">Itaú</option>
                                                <option value="033">Santander</option>
                                            </select>
                                        </div>
                                        <div class="col-12" id="campos-pagamento-entrega" style="display: none;">
                                            <label class="form-label"><?= __('checkout.pay_on_delivery_method', 'Forma de Pagamento na Entrega') ?></label>
                                            <div class="alert alert-info">
                                                <i class="fas fa-info-circle"></i>
                                                <strong><?= __('checkout.pay_on_delivery', 'Pagamento na entrega') ?>:</strong>
                                                <?= __('checkout.pay_on_delivery_desc', 'Você pode pagar com dinheiro, cartão ou maquininha na entrega.') ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <hr>

                            <!-- Valores -->
                            <div class="mb-2">
                                <div class="d-flex justify-content-between">
                                    <span id="subtotal-label"><?= __('checkout.subtotal_products', 'Subtotal Produtos:') ?></span>
                                    <span id="subtotal" class="cart-currency" data-original-value="<?= $subtotal ?>"><?= number_format($subtotal, 2, '.', ',') ?></span>
                                </div>

                                <?php if (!empty($desconto_clube) || !empty($cashback_clube_estimado) || !empty($peso_clube_total) || !empty($subtotal_clube)): ?>
                                    <div class="mt-2 mb-2 p-2" style="background: rgba(11,31,58,0.04); border: 1px solid rgba(11,31,58,0.08); border-radius: 12px;">
                                        <div class="fw-semibold mb-1" style="color:#0b1f3a;"><?= __('cart.club', 'Clube Brasiliana') ?></div>
                                        <div class="d-flex justify-content-between small">
                                            <span class="text-muted"><?= __('cart.club_weight', 'Peso Clube') ?></span>
                                            <span><?= number_format((float) ($peso_clube_total ?? 0), 3, ',', '.') ?> kg</span>
                                        </div>
                                        <div class="d-flex justify-content-between small">
                                            <span class="text-muted"><?= __('cart.club_subtotal', 'Subtotal Clube') ?></span>
                                            <span class="cart-currency" data-original-value="<?= (float) ($subtotal_clube ?? 0) ?>"><?= number_format((float) ($subtotal_clube ?? 0), 2, '.', ',') ?></span>
                                        </div>
                                        <?php if (!empty($desconto_clube)): ?>
                                            <div class="d-flex justify-content-between small">
                                                <span class="text-muted"><?= __('cart.club_discount', 'Desconto Clube') ?></span>
                                                <span class="cart-currency" data-original-value="<?= (float) ($desconto_clube ?? 0) ?>">-<?= number_format((float) ($desconto_clube ?? 0), 2, '.', ',') ?></span>
                                            </div>
                                        <?php endif; ?>
                                        <?php if (!empty($cashback_clube_estimado)): ?>
                                            <div class="d-flex justify-content-between small">
                                                <span class="text-muted"><?= __('cart.club_cashback_est', 'Cashback estimado') ?></span>
                                                <span class="cart-currency" data-original-value="<?= (float) ($cashback_clube_estimado ?? 0) ?>"><?= number_format((float) ($cashback_clube_estimado ?? 0), 2, '.', ',') ?></span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                <div class="d-flex justify-content-between">
                                    <span><?= __('cart.service_fee_kg', 'Taxa de Serviço ({kg} kg)', ['kg' => number_format(ceil($peso_total), 0, ',', '.')]) ?></span>
                                    <span id="taxa-servico" class="cart-currency" data-original-value="<?= $taxa_servico ?? 0 ?>"><?= number_format(($taxa_servico ?? 0), 2, '.', ',') ?></span>
                                </div>
                                <?php if (!empty($pix_desconto_taxa_servico_percent) && (float) $pix_desconto_taxa_servico_percent > 0): ?>
                                    <div class="alert alert-info small mt-2 mb-2" id="pixTaxaServicoInfo" style="display:none;"></div>
                                <?php endif; ?>
                                <?php if (!empty($cobra_impostos_br)): ?>
                                    <div class="d-flex justify-content-between" id="impostos-row">
                                        <span><?= __('cart.taxes', 'Impostos') ?>:</span>
                                        <span id="impostos" class="cart-currency" data-original-value="<?= $impostos ?? 0 ?>"><?= number_format(($impostos ?? 0), 2, '.', ',') ?></span>
                                    </div>
                                <?php else: ?>
                                    <div class="d-flex justify-content-between d-none" id="impostos-row">
                                        <span><?= __('cart.taxes', 'Impostos') ?>:</span>
                                        <span id="impostos" class="cart-currency" data-original-value="0">0</span>
                                    </div>
                                <?php endif; ?>
                                <div class="d-flex justify-content-between">
                                    <span><?= __('cart.shipping_kg', 'Frete ({kg} kg)', ['kg' => number_format(ceil($peso_total), 0, ',', '.')]) ?>:</span>
                                    <span id="frete" class="cart-currency frete-value" data-original-value="<?= (float) ($frete ?? 0) ?>">
                                        <?= (((float) ($frete ?? 0)) <= 0) ? __('cart.free_shipping', 'Frete grátis') : ('$' . number_format(($frete ?? 0), 2, '.', ',')) ?>
                                    </span>
                                </div>
                            </div>

                            <hr>

                            <div class="d-flex justify-content-between mb-3">
                                <h6><?= __('cart.total', 'Total') ?>:</h6>
                                <h6 class="text-primary" id="total" class="cart-currency" data-original-value="<?= $total ?? ($subtotal + ($frete ?? 0) + ($taxa_servico ?? 0) + ($impostos ?? 0)) ?>"><?= number_format(($total ?? ($subtotal + ($frete ?? 0) + ($taxa_servico ?? 0) + ($impostos ?? 0))), 2, '.', ',') ?></h6>
                            </div>

                            <div class="alert alert-info small">
                                <i class="fas fa-info-circle"></i> 
                                <strong><?= __('checkout.total_weight', 'Peso Total') ?>:</strong> <?= number_format($peso_total, 3, ',', '.') ?> kg
                            </div>

                            <?php if (!empty($entrega_fora_br) && !empty($mensagem_entrega_fora_br)): ?>
                                <div class="alert alert-warning small">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    <?= htmlspecialchars((string) $mensagem_entrega_fora_br, ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            <?php endif; ?>

                            <div class="alert alert-warning small d-none" id="entrega-fora-br-alert">
                                <i class="fas fa-exclamation-triangle"></i>
                                <?= __('checkout.delivery_outside_br_no_taxes', 'A entrega para fora do Brasil não inclui impostos brasileiros. A tributação local é responsabilidade do cliente.') ?>
                            </div>

                            <!-- Termos Legais -->
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="consentimento_legal" id="consentimento_legal" required>
                                    <label class="form-check-label small" for="consentimento_legal">
                                        <?php
                                            $termsLink = '<a href="#" data-bs-toggle="modal" data-bs-target="#termosModal">' . __('checkout.terms_and_conditions', 'termos e condições') . '</a>';
                                            echo __('checkout.terms_accept_full', 'Li e aceito os {terms} de uso e política de privacidade. *', ['terms' => $termsLink]);
                                        ?>
                                    </label>
                                </div>
                            </div>

                            <!-- Botão Finalizar -->
                            <button type="button" class="btn btn-primary btn-lg w-100" id="btn-finalizar" <?= (!empty($usuario) && (!($perfil_ok ?? true) || !($termos_ok ?? true))) ? 'disabled' : '' ?>
                                    onclick="console.log('🔍 [INLINE] Botão clicado!'); processarPedidoDireto();">
                                <i class="fas fa-lock"></i> <?= __('checkout.finalize_secure', 'Finalizar Pedido com Pagamento Seguro') ?>
                            </button>
                            
                            <!-- Botão de Teste Inline -->
                            <button type="button" class="btn btn-warning btn-sm w-100 mt-2 d-none" 
                                    onclick="console.log('🔍 [TESTE] Botão de teste clicado!'); alert('Botão de teste funciona!');">
                                <i class="fas fa-bug"></i> Teste Inline
                            </button>
                            
                            <!-- Botão de Debug -->
                            <button type="button" class="btn btn-info btn-sm w-100 mt-2 d-none" 
                                    onclick="debugBotaoFinalizar();">
                                <i class="fas fa-bug"></i> Debug Botão
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    </form>
</div>

<div id="checkout-loading" style="display:none; position: fixed; inset: 0; background: rgba(0,0,0,0.35); z-index: 9999;">
    <div style="position:absolute; top:50%; left:50%; transform: translate(-50%, -50%); text-align:center; color:#fff;">
        <div class="spinner-border" role="status" aria-hidden="true"></div>
        <div style="margin-top: 10px; font-weight: 600;"><?= __('checkout.processing_order', 'Processando seu pedido...') ?></div>
    </div>
</div>

<script src="https://js.stripe.com/v3/"></script>

<script>
console.log('🔍 [DEBUG] Script carregado - início - VERSÃO ATUALIZADA');

let stripeClient = null;
let stripeElements = null;
let stripeCard = null;
let stripeCardMounted = false;

function ensureStripeInit() {
    const publishableKey = <?php echo json_encode((string) ($stripe_publishable_key ?? '')); ?>;
    const stripeEnabled = <?php echo json_encode((bool) ($stripe_enabled ?? false)); ?>;

    if (!stripeEnabled || !publishableKey) {
        console.error('[STRIPE] Não inicializado: stripe_enabled ou publishable_key ausente', { stripeEnabled, publishableKeyPresent: !!publishableKey });
        return false;
    }
    if (typeof Stripe !== 'function') {
        console.error('[STRIPE] Não inicializado: Stripe.js (window.Stripe) não está disponível');
        return false;
    }

    const mountEl = document.getElementById('stripe-card-element');
    if (!mountEl) {
        console.error('[STRIPE] Não inicializado: elemento #stripe-card-element não encontrado');
        return false;
    }

    if (!stripeClient) {
        try {
            stripeClient = Stripe(publishableKey);
            stripeElements = stripeClient.elements();
            stripeCard = stripeElements.create('card');
            stripeCardMounted = false;

            stripeCard.on('change', function(event) {
                const errEl = document.getElementById('stripe-card-errors');
                if (!errEl) return;
                if (event.error) {
                    errEl.style.display = 'block';
                    errEl.textContent = event.error.message;
                } else {
                    errEl.style.display = 'none';
                    errEl.textContent = '';
                }
            });
        } catch (e) {
            console.error('[STRIPE] Falha ao criar client/elements', e);
            return false;
        }
    }

    // Montar somente quando ainda não montado (e após o bloco ficar visível)
    if (stripeCard && !stripeCardMounted) {
        try {
            requestAnimationFrame(() => {
                try {
                    stripeCard.mount('#stripe-card-element');
                    stripeCardMounted = true;
                    console.log('[STRIPE] CardElement montado');
                } catch (err) {
                    console.error('[STRIPE] Falha ao montar CardElement', err);
                    const errEl = document.getElementById('stripe-card-errors');
                    if (errEl) {
                        errEl.style.display = 'block';
                        errEl.textContent = (window.CHECKOUT_I18N && window.CHECKOUT_I18N.stripe_card_form_error) ? window.CHECKOUT_I18N.stripe_card_form_error : 'Erro ao carregar o formulário de cartão. Verifique a chave pública do Stripe.';
                    }
                }
            });
        } catch (e) {
            console.error('[STRIPE] requestAnimationFrame falhou', e);
        }
    }
    return true;
}

function iniciarPagamentoStripeElements(pedidoId, email) {
    if (!ensureStripeInit()) {
        hideCheckoutLoading();
        alert((window.CHECKOUT_I18N && window.CHECKOUT_I18N.stripe_not_configured) ? window.CHECKOUT_I18N.stripe_not_configured : 'Stripe não configurado. Verifique as configurações de pagamento.');
        return;
    }

    fetch('/checkout/stripe/payment-intent', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: new URLSearchParams({ pedido_id: String(pedidoId), email: String(email || '') }).toString()
    })
    .then(r => r.json())
    .then(async (piResp) => {
        if (!piResp.success || !piResp.client_secret) {
            throw new Error(piResp.error || 'Falha ao iniciar pagamento Stripe');
        }

        const result = await stripeClient.confirmCardPayment(piResp.client_secret, {
            payment_method: { card: stripeCard }
        });

        if (result.error) {
            throw new Error(result.error.message || 'Pagamento não autorizado');
        }

        const paymentIntent = result.paymentIntent;
        const paymentIntentId = paymentIntent && paymentIntent.id ? paymentIntent.id : (piResp.payment_intent_id || '');
        if (!paymentIntentId) {
            throw new Error('PaymentIntent inválido');
        }

        return fetch('/checkout/stripe/finalizar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: new URLSearchParams({ pedido_id: String(pedidoId), payment_intent_id: String(paymentIntentId) }).toString()
        });
    })
    .then(r => r.json())
    .then((finalResp) => {
        if (!finalResp.success) {
            throw new Error(finalResp.error || 'Pagamento não confirmado');
        }
        const destino = '/checkout/conclusao/' + pedidoId;
        window.location.href = destino;
    })
    .catch((e) => {
        console.error(e);
        hideCheckoutLoading();
        const prefix = (window.CHECKOUT_I18N && window.CHECKOUT_I18N.payment_error_prefix) ? window.CHECKOUT_I18N.payment_error_prefix : 'Erro no pagamento:';
        alert(prefix + ' ' + (e && e.message ? e.message : e));
        const botao = document.getElementById('btn-finalizar');
        if (botao) {
            botao.disabled = false;
        }
    });
}

function atualizarEnderecoPorPais() {
    const pais = (document.getElementById('pais')?.value || 'BR').toUpperCase();
    const cep = document.getElementById('cep');
    const estadoSelect = document.getElementById('estado');
    const estadoText = document.getElementById('estado_text');
    const moedaHidden = document.getElementById('moeda_hidden');
    const docInput = document.getElementById('documento');
    const docLabel = document.getElementById('label-documento');

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

    // Endereço internacional: forçar moeda USD (gateways BR não aceitam)
    if (moedaHidden) {
        const desired = (pais !== 'BR') ? 'USD' : 'BRL';
        moedaHidden.value = desired;
        if (typeof updatePrices === 'function') {
            try {
                updatePrices(desired);
            } catch (e) {
            }
        }
    }

    // Documento (CPF/CNPJ) só é obrigatório para BR
    if (docInput) {
        const requiredDoc = (pais === 'BR');
        docInput.required = requiredDoc;
        if (docLabel) {
            docLabel.textContent = requiredDoc
                ? ((window.CHECKOUT_I18N && window.CHECKOUT_I18N.cpf_cnpj_required) ? window.CHECKOUT_I18N.cpf_cnpj_required : 'CPF/CNPJ *')
                : ((window.CHECKOUT_I18N && window.CHECKOUT_I18N.cpf_cnpj_optional) ? window.CHECKOUT_I18N.cpf_cnpj_optional : 'CPF/CNPJ (opcional)');
        }
    }

    // Endereço: regras globais (BR tem campos específicos; fora do BR, simplificar)
    if (enderecoLabel) {
        enderecoLabel.textContent = (pais === 'BR') ? 'Rua / Street *' : ((window.CHECKOUT_I18N && window.CHECKOUT_I18N.address_line_1) ? window.CHECKOUT_I18N.address_line_1 : 'Address line 1 *');
    }
    if (compLabel) {
        compLabel.textContent = (pais === 'BR') ? 'Complemento / Complement' : ((window.CHECKOUT_I18N && window.CHECKOUT_I18N.address_line_2_optional) ? window.CHECKOUT_I18N.address_line_2_optional : 'Address line 2 (optional)');
    }

    if (numeroWrap && numeroInput && numeroLabel) {
        if (pais === 'BR') {
            numeroWrap.style.display = '';
            numeroInput.required = true;
            const pt = (window.CHECKOUT_I18N && window.CHECKOUT_I18N.number_label) ? window.CHECKOUT_I18N.number_label : 'Número';
            const en = (window.CHECKOUT_I18N && window.CHECKOUT_I18N.number_label_en) ? window.CHECKOUT_I18N.number_label_en : 'Number';
            numeroLabel.textContent = pt + ' / ' + en + ' *';
        } else {
            numeroWrap.style.display = 'none';
            numeroInput.required = false;
            numeroInput.value = '';
            numeroLabel.textContent = (window.CHECKOUT_I18N && window.CHECKOUT_I18N.number_label_en) ? window.CHECKOUT_I18N.number_label_en : 'Number';
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

    if (typeof syncPaymentOptionsByCurrency === 'function') {
        try {
            syncPaymentOptionsByCurrency();
        } catch (e) {
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
            optEmpty.textContent = (window.CHECKOUT_I18N && window.CHECKOUT_I18N.select_state) ? window.CHECKOUT_I18N.select_state : 'Selecione...';
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

document.addEventListener('DOMContentLoaded', function(){
    const paisSel = document.getElementById('pais');
    if (paisSel) {
        paisSel.addEventListener('change', atualizarEnderecoPorPais);
    }
    atualizarEnderecoPorPais();

    const cepInput = document.getElementById('cep');
    if (cepInput) {
        cepInput.addEventListener('input', function(e) {
            const pais = (document.getElementById('pais')?.value || 'BR').toUpperCase();
            if (pais !== 'BR') {
                return;
            }
            let value = (e.target.value || '').toString().replace(/\D/g, '');
            if (value.length <= 8) {
                value = value.replace(/^(\d{5})(\d{3}).*/, '$1-$2');
            }
            e.target.value = value;
        });

        cepInput.addEventListener('blur', function(e) {
            const pais = (document.getElementById('pais')?.value || 'BR').toUpperCase();
            if (pais !== 'BR') {
                return;
            }

            const cep = (e.target.value || '').toString().replace(/\D/g, '');
            if (cep.length !== 8) {
                return;
            }

            fetch('/api/cep/' + encodeURIComponent(cep))
                .then(r => r.json())
                .then(data => {
                    if (!data || !data.success || !data.endereco) {
                        return;
                    }
                    const end = data.endereco;
                    const elEndereco = document.getElementById('endereco');
                    const elBairro = document.getElementById('bairro');
                    const elCidade = document.getElementById('cidade');
                    const elEstado = document.getElementById('estado');
                    if (elEndereco && (end.logradouro || '')) elEndereco.value = end.logradouro || '';
                    if (elBairro) elBairro.value = end.bairro || '';
                    if (elCidade) elCidade.value = end.localidade || '';
                    if (elEstado) elEstado.value = end.uf || '';

                    const numero = document.getElementById('numero');
                    if (numero) {
                        numero.focus();
                    }
                })
                .catch(() => {});
        });
    }

    function syncDestinatarioRules() {
        const cb = document.getElementById('entrega_para_outro');
        const box = document.getElementById('destinatario-box');
        const nome = document.getElementById('destinatario_nome');
        const doc = document.getElementById('destinatario_documento');
        const tel = document.getElementById('destinatario_telefone');
        const docLabel = document.getElementById('label-destinatario-documento');
        const docHint = document.getElementById('hint-destinatario-documento');
        const pais = (document.getElementById('pais')?.value || 'BR').toUpperCase();
        const enabled = !!(cb && cb.checked);

        if (box) {
            box.style.display = enabled ? 'block' : 'none';
        }

        if (nome) {
            nome.required = enabled;
            if (!enabled) nome.value = '';
        }

        if (doc) {
            const requiredDoc = enabled && (pais === 'BR');
            doc.required = requiredDoc;
            if (docLabel) docLabel.textContent = requiredDoc ? 'CPF do destinatário *' : 'CPF do destinatário (opcional)';
            if (docHint) docHint.style.display = (enabled && pais !== 'BR') ? 'block' : 'none';
            if (!enabled) doc.value = '';
        }

        if (tel) {
            tel.required = enabled;
            if (!enabled) tel.value = '';
        }
    }

    const cb = document.getElementById('entrega_para_outro');
    if (cb) {
        cb.addEventListener('change', syncDestinatarioRules);
    }
    if (paisSel) {
        paisSel.addEventListener('change', syncDestinatarioRules);
    }
    syncDestinatarioRules();
});

function showCheckoutLoading() {
    const el = document.getElementById('checkout-loading');
    if (el) el.style.display = 'block';
}

function hideCheckoutLoading() {
    const el = document.getElementById('checkout-loading');
    if (el) el.style.display = 'none';
}

// Função para debug do botão
function debugBotaoFinalizar() {
    console.log('🔍 [DEBUG] Iniciando debug do botão finalizar');
    
    const botao = document.getElementById('btn-finalizar');
    const checkbox = document.getElementById('consentimento_legal');
    
    console.log('🔍 [DEBUG] Botão encontrado:', !!botao);
    console.log('🔍 [DEBUG] Botão disabled:', botao ? botao.disabled : 'N/A');
    console.log('🔍 [DEBUG] Botão onclick:', botao ? botao.getAttribute('onclick') : 'N/A');
    console.log('🔍 [DEBUG] Checkbox encontrado:', !!checkbox);
    console.log('🔍 [DEBUG] Checkbox checked:', checkbox ? checkbox.checked : 'N/A');
    
    if (botao) {
        // Forçar habilitar botão
        botao.disabled = false;
        botao.className = 'btn btn-success btn-lg w-100';
        console.log('🔍 [DEBUG] Botão forçado a habilitar');
        
        // Adicionar listener adicional
        botao.addEventListener('click', function() {
            console.log('🔍 [DEBUG] Listener adicional acionado!');
            alert('Listener adicional funcionou!');
        });
        
        console.log('🔍 [DEBUG] Listener adicional adicionado');
    }
}

// Função para processar pedido diretamente
function processarPedidoDireto() {
    console.log('🔍 [DIRETO] Processando pedido diretamente...');
    
    const form = document.getElementById('checkout-form');
    const botao = document.getElementById('btn-finalizar');
    const checkbox = document.getElementById('consentimento_legal');
    
    if (!form) {
        console.error('❌ [DIRETO] Formulário não encontrado');
        alert('Formulário não encontrado!');
        return;
    }
    
    console.log('🔍 [DIRETO] Formulário encontrado, verificando checkbox...');
    console.log('🔍 [DIRETO] Checkbox encontrado:', !!checkbox);
    console.log('🔍 [DIRETO] Checkbox checked:', checkbox ? checkbox.checked : 'N/A');
    
    // Verificar se os termos foram aceitos
    if (!checkbox || !checkbox.checked) {
        console.error('❌ [DIRETO] Termos não aceitos');
        alert('É necessário aceitar os termos para continuar');
        return;
    }
    
    console.log('🔍 [DIRETO] Termos aceitos, coletando dados...');
    
    // Coletar dados do formulário manualmente
    const formData = new FormData();
    
    // Adicionar todos os campos do formulário
    const inputs = form.querySelectorAll('input, select, textarea');
    inputs.forEach(input => {
        if (input.name && input.type !== 'checkbox') {
            formData.append(input.name, input.value);
            console.log(`🔍 [DIRETO] ${input.name}: ${input.value}`);
        } else if (input.type === 'checkbox') {
            // Sempre adicionar checkbox, mesmo que não esteja marcado
            formData.append(input.name, input.checked ? 'on' : '');
            console.log(`🔍 [DIRETO] ${input.name}: ${input.checked ? 'on' : ''}`);
        }
    });
    
    // Garantir que o consentimento_legal seja adicionado
    formData.append('consentimento_legal', checkbox.checked ? 'on' : '');
    console.log(`🔍 [DIRETO] consentimento_legal: ${checkbox.checked ? 'on' : ''} (garantido)`);
    
    const moedaHidden = document.getElementById('moeda_hidden');
    const currentCurrency = (moedaHidden && moedaHidden.value ? moedaHidden.value : 'BRL').toString().trim().toUpperCase();

    // Garantir que a forma de pagamento seja adicionada
    const formaPagamentoSelect = document.getElementById('forma_pagamento');
    if (formaPagamentoSelect) {
        const formaPagamento = formaPagamentoSelect.value;
        formData.append('forma_pagamento', formaPagamento);
        console.log(`🔍 [DIRETO] forma_pagamento: ${formaPagamento} (garantido)`);

        // Garantir coleta explícita dos campos do cartão quando selecionado (apenas BRL/AppMax)
        if (formaPagamento === 'cartao_credito' && currentCurrency === 'BRL') {
            const camposCartao = document.getElementById('campos-cartao');
            const nomeCartao = camposCartao ? camposCartao.querySelector('input[name="card_holder_name"]') : null;
            const numeroCartao = camposCartao ? camposCartao.querySelector('input[name="card_number"]') : null;
            const mesCartao = camposCartao ? camposCartao.querySelector('input[name="card_expiry_month"]') : null;
            const anoCartao = camposCartao ? camposCartao.querySelector('input[name="card_expiry_year"]') : null;
            const cvvCartao = camposCartao ? camposCartao.querySelector('input[name="card_cvv"]') : null;

            const vNome = nomeCartao ? nomeCartao.value : '';
            const vNumero = numeroCartao ? numeroCartao.value : '';
            const vMes = mesCartao ? mesCartao.value : '';
            const vAno = anoCartao ? anoCartao.value : '';
            const vCvv = cvvCartao ? cvvCartao.value : '';

            formData.set('card_holder_name', vNome);
            formData.set('card_number', vNumero);
            formData.set('card_expiry_month', vMes);
            formData.set('card_expiry_year', vAno);
            formData.set('card_cvv', vCvv);

            console.log('🔍 [DIRETO] [CARTAO] card_holder_name:', vNome);
            console.log('🔍 [DIRETO] [CARTAO] card_number:', vNumero);
            console.log('🔍 [DIRETO] [CARTAO] card_expiry_month:', vMes);
            console.log('🔍 [DIRETO] [CARTAO] card_expiry_year:', vAno);
            console.log('🔍 [DIRETO] [CARTAO] card_cvv:', vCvv);
        }
    } else {
        console.error('❌ [DIRETO] Campo forma_pagamento não encontrado');
    }
    
    console.log('🔍 [DIRETO] Total de campos no FormData:', [...formData.keys()].length);
    console.log('🔍 [DIRETO] Verificando consentimento_legal no FormData:', formData.get('consentimento_legal'));
    console.log('🔍 [DIRETO] Verificando forma_pagamento no FormData:', formData.get('forma_pagamento'));

    console.log('🔍 [DIRETO] Verificando card_holder_name no FormData:', formData.get('card_holder_name'));
    console.log('🔍 [DIRETO] Verificando card_number no FormData:', formData.get('card_number'));
    console.log('🔍 [DIRETO] Verificando card_expiry_month no FormData:', formData.get('card_expiry_month'));
    console.log('🔍 [DIRETO] Verificando card_expiry_year no FormData:', formData.get('card_expiry_year'));
    console.log('🔍 [DIRETO] Verificando card_cvv no FormData:', formData.get('card_cvv'));
    
    // Desabilitar botão e mostrar loading
    botao.disabled = true;
    botao.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processando...';
    showCheckoutLoading();
    
    // Enviar requisição AJAX (criar pedido)
    fetch('/checkout/processar', {
        method: 'POST',
        body: formData
    })
    .then(response => {
        console.log('🔍 [DIRETO] Resposta recebida:', response.status);
        return response.json();
    })
    .then(data => {
        console.log('🔍 [DIRETO] Dados recebidos:', data);
        
        if (data.success) {
            console.log('✅ [DIRETO] Pedido criado com sucesso:', data.pedido_id);

            const isStripeUsd = (currentCurrency !== 'BRL') && (data.stripe_required === true);
            if (!isStripeUsd) {
                // Manter overlay até redirecionar para página de conclusão
                const destino = data.redirect || ('/checkout/conclusao/' + data.pedido_id);
                console.log('🔍 [DIRETO] Redirecionando para:', destino);
                setTimeout(function() {
                    window.location.href = destino;
                }, 300);
                return;
            }

            // Stripe Elements (USD)
            iniciarPagamentoStripeElements(data.pedido_id, formData.get('email') || '');
        } else {
            console.error('❌ [DIRETO] Erro ao processar pedido:', data.error);
            alert('Erro: ' + data.error);

            hideCheckoutLoading();
            
            // Restaurar botão
            botao.disabled = false;
            botao.innerHTML = '<i class="fas fa-lock"></i> Finalizar Pedido com Pagamento Seguro';
        }
    })
    .catch(error => {
        console.error('❌ [DIRETO] Erro na requisição:', error);
        alert('Erro de conexão: ' + error.message);

        hideCheckoutLoading();
        
        // Restaurar botão
        botao.disabled = false;
        botao.innerHTML = '<i class="fas fa-lock"></i> Finalizar Pedido com Pagamento Seguro';
    });
}

// Função toggleButton simplificada
function toggleButton() {
    const checkbox = document.getElementById('consentimento_legal');
    const botao = document.getElementById('btn-finalizar');
    
    console.log('🔍 [BOTÃO] toggleButton() chamada');
    console.log('🔍 [BOTÃO] Checkbox marcado:', checkbox ? checkbox.checked : 'não');
    console.log('🔍 [BOTÃO] Botão encontrado:', !!botao);
    
    if (checkbox && botao) {
        // NÃO desabilitar o botão - apenas mudar a cor
        if (checkbox.checked) {
            botao.className = 'btn btn-primary btn-lg w-100';
            console.log('🔍 [BOTÃO] Botão azul (termos aceitos)');
        } else {
            botao.className = 'btn btn-secondary btn-lg w-100';
            console.log('🔍 [BOTÃO] Botão cinza (termos não aceitos)');
        }
        
        // Garantir que o botão NUNCA seja desabilitado
        botao.disabled = false;
        console.log('🔍 [BOTÃO] Botão garantido como habilitado');
    } else {
        console.error('❌ [BOTÃO] Checkbox ou botão não encontrado');
    }
}

// Teste simples
document.addEventListener('DOMContentLoaded', function() {
    console.log('🔍 [DEBUG] DOMContentLoaded iniciado - VERSÃO ATUALIZADA');
    
    const form = document.getElementById('checkout-form');
    const botao = document.getElementById('btn-finalizar');
    const checkbox = document.getElementById('consentimento_legal');
    
    console.log('🔍 [DEBUG] Formulário encontrado:', !!form);
    console.log('🔍 [DEBUG] Botão encontrado:', !!botao);
    console.log('🔍 [DEBUG] Checkbox encontrado:', !!checkbox);
    
    if (checkbox) {
        // Adicionar listener ao checkbox
        checkbox.addEventListener('change', function() {
            console.log('🔍 [CHECKBOX] Checkbox alterado:', this.checked);
            toggleButton();
        });
        
        console.log('🔍 [DEBUG] Listener adicionado ao checkbox');
        
        // Verificar estado inicial
        toggleButton();
    }
    
    if (botao) {
        console.log('🔍 [DEBUG] Botão estado inicial:', botao.disabled);
        console.log('🔍 [DEBUG] Garantindo que botão esteja habilitado...');
        
        // Forçar botão a ser habilitado
        botao.disabled = false;
        console.log('🔍 [DEBUG] Botão forçado a habilitado no DOMContentLoaded');
    }
    
    if (form) {
        console.log('🔍 [DEBUG] Adicionando listener de submit como backup');
        
        // Adicionar event listener de submit como backup
        form.addEventListener('submit', function(e) {
            console.log('🔍 [FORM] Event submit acionado (backup)!');
            e.preventDefault();
            console.log('🔍 [FORM] Usando método de backup...');
            processarPedidoDireto();
        });
        
        console.log('🔍 [DEBUG] Event listener submit adicionado como backup');
    } else {
        console.error('❌ [DEBUG] Formulário não encontrado!');
    }
});

console.log('🔍 [DEBUG] Script carregado - fim - VERSÃO ATUALIZADA');
</script>

<!-- Selos de Segurança -->
                <div class="text-center mt-3">
                    <div class="d-flex justify-content-center gap-3">
                        <i class="fas fa-lock fa-2x text-success"></i>
                        <i class="fas fa-shield-alt fa-2x text-primary"></i>
                        <i class="fab fa-cc-visa fa-2x text-info"></i>
                        <i class="fab fa-cc-mastercard fa-2x text-warning"></i>
                    </div>
                    <small class="text-muted d-block mt-2">Pagamento 100% seguro</small>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Inicializar na carga da página
document.addEventListener('DOMContentLoaded', () => {
    atualizarFormaPagamento();
    setupCardMasks();
});

// Função para atualizar campos de pagamento
function atualizarFormaPagamento() {
    console.log('🔍 [INÍCIO] atualizarFormaPagamento() chamada');
    
    // Garantir que o elemento forma_pagamento existe
    const formaPagamentoElement = document.getElementById('forma_pagamento');
    if (!formaPagamentoElement) {
        console.error('❌ [ERRO] Elemento forma_pagamento não encontrado!');
        return;
    }
    
    const formaPagamento = formaPagamentoElement.value;
    console.log('🔍 [DEBUG] Valor selecionado:', formaPagamento);
    
    // Verificar se os elementos dos campos existem
    const camposCartao = document.getElementById('campos-cartao');
    const camposBoleto = document.getElementById('campos-boleto');
    const camposPix = document.getElementById('campos-pix');
    const camposTransferencia = document.getElementById('campos-transferencia');
    const camposPagamentoEntrega = document.getElementById('campos-pagamento-entrega');
    
    console.log('🔍 [DEBUG] Elementos dos campos:');
    console.log('🔍 [DEBUG] campos-cartao:', !!camposCartao);
    console.log('🔍 [DEBUG] campos-boleto:', !!camposBoleto);
    console.log('🔍 [DEBUG] campos-pix:', !!camposPix);
    console.log('🔍 [DEBUG] campos-transferencia:', !!camposTransferencia);
    console.log('🔍 [DEBUG] campos-pagamento-entrega:', !!camposPagamentoEntrega);
    
    // Esconder todos os campos específicos primeiro
    if (camposCartao) {
        camposCartao.style.display = 'none';
        console.log('🔍 [DEBUG] Campos de cartão escondidos');
    }
    if (camposBoleto) {
        camposBoleto.style.display = 'none';
        console.log('🔍 [DEBUG] Campos de boleto escondidos');
    }
    if (camposPix) {
        camposPix.style.display = 'none';
        console.log('🔍 [DEBUG] Campos de PIX escondidos');
    }
    if (camposTransferencia) {
        camposTransferencia.style.display = 'none';
        console.log('🔍 [DEBUG] Campos de transferência escondidos');
    }
    if (camposPagamentoEntrega) {
        camposPagamentoEntrega.style.display = 'none';
        console.log('🔍 [DEBUG] Campos de pagamento na entrega escondidos');
    }
    
    console.log('🔍 [DEBUG] Todos os campos foram escondidos');
    
    // Mostrar campos específicos conforme a forma de pagamento
    switch(formaPagamento) {
        case 'carteira':
            console.log('🔍 [PAGAMENTO] Pagamento via carteira selecionado');
            break;
        case 'cartao_credito':
            if (camposCartao) {
                camposCartao.style.display = 'block';
                console.log('🔍 [PAGAMENTO] Campos de cartão exibidos');

                const moedaHidden = document.getElementById('moeda_hidden');
                const cur = (moedaHidden && moedaHidden.value ? moedaHidden.value : 'BRL').toString().trim().toUpperCase();
                const stripeMode = (cur !== 'BRL');

                const blocoStripe = document.getElementById('campos-cartao-stripe');
                const blocoManual = document.getElementById('campos-cartao-manual');

                if (stripeMode) {
                    if (blocoStripe) blocoStripe.style.display = 'block';
                    if (blocoManual) blocoManual.style.display = 'none';
                    // Remover required dos inputs manuais
                    const inputsManual = blocoManual ? blocoManual.querySelectorAll('input') : [];
                    inputsManual.forEach(i => { i.required = false; });
                    ensureStripeInit();
                } else {
                    if (blocoStripe) blocoStripe.style.display = 'none';
                    if (blocoManual) blocoManual.style.display = 'block';
                    // Garantir required para BRL
                    const nomeCartao = blocoManual ? blocoManual.querySelector('input[name="card_holder_name"]') : null;
                    const numeroCartao = blocoManual ? blocoManual.querySelector('input[name="card_number"]') : null;
                    const cvvCartao = blocoManual ? blocoManual.querySelector('input[name="card_cvv"]') : null;
                    if (nomeCartao) nomeCartao.required = true;
                    if (numeroCartao) numeroCartao.required = true;
                    if (cvvCartao) cvvCartao.required = true;
                }
            } else {
                console.error('❌ [ERRO] Elemento campos-cartao não encontrado');
            }
            break;
        case 'boleto':
            if (camposBoleto) {
                camposBoleto.style.display = 'block';
                console.log('🔍 [PAGAMENTO] Campos de boleto exibidos');
            } else {
                console.error('❌ [ERRO] Elemento campos-boleto não encontrado');
            }
            break;
        case 'pix':
            if (camposPix) {
                camposPix.style.display = 'block';
                console.log('🔍 [PAGAMENTO] Campos de PIX exibidos');
            } else {
                console.error('❌ [ERRO] Elemento campos-pix não encontrado');
            }
            break;
        case 'transferencia':
            if (camposTransferencia) {
                camposTransferencia.style.display = 'block';
                console.log('🔍 [PAGAMENTO] Campos de transferência exibidos');
            } else {
                console.error('❌ [ERRO] Elemento campos-transferencia não encontrado');
            }
            break;
        case 'pagamento_entrega':
            if (camposPagamentoEntrega) {
                camposPagamentoEntrega.style.display = 'block';
                console.log('🔍 [PAGAMENTO] Campos de pagamento na entrega exibidos');
            } else {
                console.error('❌ [ERRO] Elemento campos-pagamento-entrega não encontrado');
            }
            break;
        default:
            console.log('🔍 [PAGAMENTO] Nenhuma forma de pagamento selecionada');
    }

    <?php if (!empty($pix_desconto_taxa_servico_percent) && (float) $pix_desconto_taxa_servico_percent > 0): ?>
    try { updatePixServiceFeeInfo(); } catch (e) {}
    <?php endif; ?>
    
    // Atualizar texto do botão conforme a forma de pagamento
    const botaoFinalizar = document.getElementById('btn-finalizar');
    console.log('🔍 [DEBUG] Botão btn-finalizar:', !!botaoFinalizar);
    
    if (botaoFinalizar) {
        switch(formaPagamento) {
            case 'carteira':
                botaoFinalizar.innerHTML = '<i class="fas fa-wallet"></i> Pagar com Crédito da Carteira';
                console.log('🔍 [BOTÃO] Texto atualizado para carteira');
                break;
            case 'cartao_credito':
                botaoFinalizar.innerHTML = '<i class="fas fa-credit-card"></i> Finalizar com Cartão de Crédito';
                console.log('🔍 [BOTÃO] Texto atualizado para cartão de crédito');
                break;
            case 'boleto':
                botaoFinalizar.innerHTML = '<i class="fas fa-barcode"></i> Gerar Boleto';
                console.log('🔍 [BOTÃO] Texto atualizado para boleto');
                break;
            case 'pix':
                botaoFinalizar.innerHTML = '<i class="fas fa-qrcode"></i> Gerar PIX';
                console.log('🔍 [BOTÃO] Texto atualizado para PIX');
                break;
            case 'transferencia':
                botaoFinalizar.innerHTML = '<i class="fas fa-university"></i> Finalizar com Transferência';
                console.log('🔍 [BOTÃO] Texto atualizado para transferência');
                break;
            case 'pagamento_entrega':
                botaoFinalizar.innerHTML = '<i class="fas fa-truck"></i> Finalizar para Pagamento na Entrega';
                console.log('🔍 [BOTÃO] Texto atualizado para pagamento na entrega');
                break;
            default:
                botaoFinalizar.innerHTML = '<i class="fas fa-lock"></i> Finalizar Pedido com Pagamento Seguro';
                console.log('🔍 [BOTÃO] Texto padrão definido');
        }
    } else {
        console.error('❌ [ERRO] Botão btn-finalizar não encontrado');
    }
    
    console.log('🔍 [FIM] atualizarFormaPagamento() concluída');
}

// Função para habilitar/desabilitar botão finalizar
function toggleButton() {
    const checkbox = document.getElementById('consentimento_legal');
    const botao = document.getElementById('btn-finalizar');
    
    console.log('🔍 [BOTÃO] toggleButton() chamada');
    console.log('🔍 [BOTÃO] Checkbox marcado:', checkbox ? checkbox.checked : 'não');
    
    if (checkbox && botao) {
        const isChecked = checkbox.checked;
        botao.disabled = !isChecked;
        
        if (isChecked) {
            botao.className = 'btn btn-primary btn-lg w-100';
            console.log('🔍 [BOTÃO] Botão habilitado');
        } else {
            botao.className = 'btn btn-secondary btn-lg w-100';
            console.log('🔍 [BOTÃO] Botão desabilitado');
        }
        
        console.log('🔍 [BOTÃO] Estado final do botão:', !botao.disabled);
    } else {
        console.error('❌ [BOTÃO] Checkbox ou botão não encontrado');
    }
}
</script>

<!-- Modal Termos -->
<div class="modal fade" id="termosModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Termos e Condições</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <h6>1. Aceitação dos Termos</h6>
                <p>Ao utilizar nossos serviços, você concorda com estes termos e condições.</p>
                
                <h6>2. Produtos e Serviços</h6>
                <p>Oferecemos produtos internacionais com serviço completo de importação.</p>
                
                <h6>3. Pagamentos</h6>
                <p>O pagamento é processado 100% no checkout através de gateways seguros.</p>
                
                <h6>4. Importação e Impostos</h6>
                <p>Todos os impostos são calculados e cobrados no momento da compra.</p>
                
                <h6>5. Entrega</h6>
                <p>O prazo de entrega estimado é de até 30 dias após a aprovação do pagamento.</p>
                
                <h6>6. Privacidade</h6>
                <p>Seus dados são protegidos conforme nossa política de privacidade.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<script>
// Usar taxas de conversão globais se existirem, senão definir locais
window.exchangeRates = <?php echo json_encode(($exchange_rates ?? ['BRL' => 5.50, 'USD' => 1.00]), JSON_UNESCAPED_UNICODE); ?>;

<?php if (!empty($pix_desconto_taxa_servico_percent) && (float) $pix_desconto_taxa_servico_percent > 0): ?>
window.pixDescontoTaxaServicoPercent = <?php echo json_encode((float) ($pix_desconto_taxa_servico_percent ?? 0), JSON_UNESCAPED_UNICODE); ?>;

function formatCurrencyValue(v, currency) {
    const n = Number(v || 0);
    const symbol = currency === 'BRL' ? 'R$' : '$';
    return symbol + ' ' + n.toFixed(2).replace('.', ',');
}

function updatePixServiceFeeInfo() {
    const box = document.getElementById('pixTaxaServicoInfo');
    if (!box) return;

    const moedaHidden = document.getElementById('moeda_hidden');
    const currency = moedaHidden ? String(moedaHidden.value || 'BRL').toUpperCase() : 'BRL';

    // PIX só aparece no checkout BRL
    if (currency !== 'BRL') {
        box.textContent = '';
        box.style.display = 'none';
        return;
    }

    const pct = Number(window.pixDescontoTaxaServicoPercent || 0);
    if (!isFinite(pct) || pct <= 0) {
        box.textContent = '';
        box.style.display = 'none';
        return;
    }

    const formaPagamentoEl = document.getElementById('forma_pagamento');
    const forma = formaPagamentoEl ? String(formaPagamentoEl.value || '') : '';
    if (forma !== 'pix') {
        box.textContent = '';
        box.style.display = 'none';
        return;
    }

    const taxaEl = document.getElementById('taxa-servico');
    if (!taxaEl) {
        box.textContent = '';
        box.style.display = 'none';
        return;
    }

    // Valor atual exibido (já convertido) não é confiável para cálculo; usar o originalValues já ajustado por país.
    const base = (window.checkoutOriginalValues && window.checkoutOriginalValues.taxaServico) ? Number(window.checkoutOriginalValues.taxaServico) : 0;
    const taxaComDesconto = Math.max(0, base * (1 - (pct / 100)));

    box.textContent = 'PIX: desconto de ' + pct.toFixed(2) + '% na taxa de serviço. Taxa com desconto: ' + formatCurrencyValue(taxaComDesconto, 'BRL') + '.';
    box.style.display = '';
}
<?php endif; ?>

// Função para atualizar valores com base na moeda
function updatePrices(currency) {
    console.log('🔍 [MOEDA] updatePrices() chamada com currency:', currency);
    console.log('🔍 [MOEDA] window.exchangeRates:', window.exchangeRates);
    
    const currencySymbol = currency === 'BRL' ? 'R$' : '$';
    const rate = window.exchangeRates[currency];
    
    console.log('🔍 [MOEDA] currencySymbol:', currencySymbol);
    console.log('🔍 [MOEDA] rate:', rate);
    
    if (!rate) {
        console.error('❌ [MOEDA] Taxa de conversão não encontrada para:', currency);
        return;
    }
    
    // Valores originais em USD (mutáveis, pois país pode mudar no select)
    if (!window.checkoutBaseValues) {
        window.checkoutBaseValues = {
            subtotal: <?= $subtotal ?>,
            frete: <?= ($frete ?? 0) ?>,
            taxaServico: <?= ($taxa_servico ?? 0) ?>,
            impostos: <?= ($impostos ?? 0) ?>,
            total: <?= ($total ?? 0) ?>
        };
    }
    if (!window.checkoutOriginalValues) {
        window.checkoutOriginalValues = Object.assign({}, window.checkoutBaseValues);
    }
    const originalValues = window.checkoutOriginalValues;
    
    console.log('🔍 [MOEDA] Valores originais:', originalValues);
    
    // Calcular valores convertidos dos originais
    const convertedValues = {
        subtotal: originalValues.subtotal * rate,
        frete: originalValues.frete * rate,
        taxaServico: originalValues.taxaServico * rate,
        impostos: originalValues.impostos * rate,
        total: originalValues.total * rate
    };
    
    console.log('🔍 [MOEDA] Valores convertidos:', convertedValues);
    
    // Atualizar elementos do resumo do pedido
    const elements = {
        subtotal: document.getElementById('subtotal'),
        frete: document.getElementById('frete'),
        taxaServico: document.getElementById('taxa-servico'),
        impostos: document.getElementById('impostos'),
        total: document.getElementById('total')
    };
    
    console.log('🔍 [MOEDA] Elementos do resumo encontrados:');
    for (const [key, element] of Object.entries(elements)) {
        console.log(`🔍 [MOEDA] ${key}:`, !!element);
    }
    
    // Atualizar cada elemento do resumo
    for (const [key, element] of Object.entries(elements)) {
        if (element) {
            if (key === 'frete' && originalValues.frete === 0) {
                element.textContent = 'Frete grátis';
            } else {
                const value = convertedValues[key];
                const formattedValue = currencySymbol + ' ' + value.toFixed(2).replace('.', ',');
                element.textContent = formattedValue;
            }
            console.log(`🔍 [MOEDA] ${key} atualizado para:`, element.textContent);
        } else {
            console.error(`❌ [MOEDA] Elemento ${key} não encontrado`);
        }
    }

    if (typeof updatePixServiceFeeInfo === 'function') {
        try { updatePixServiceFeeInfo(); } catch (e) {}
    }
    
    // Atualizar elementos ocultos se existirem
    const hiddenSubtotal = document.getElementById('subtotal_hidden');
    const hiddenFrete = document.getElementById('frete_hidden');
    const hiddenTotal = document.getElementById('total_hidden');
    
    if (hiddenSubtotal) {
        hiddenSubtotal.value = convertedValues.subtotal.toFixed(2);
        console.log('🔍 [MOEDA] Campo oculto subtotal atualizado para:', convertedValues.subtotal.toFixed(2));
    }
    
    if (hiddenFrete) {
        hiddenFrete.value = convertedValues.frete.toFixed(2);
        console.log('🔍 [MOEDA] Campo oculto frete atualizado para:', convertedValues.frete.toFixed(2));
    }
    
    if (hiddenTotal) {
        hiddenTotal.value = convertedValues.total.toFixed(2);
        console.log('🔍 [MOEDA] Campo oculto total atualizado para:', convertedValues.total.toFixed(2));
    }
    
    // Atualizar elementos com classe cart-currency (itens do carrinho)
    const cartCurrencyElements = document.querySelectorAll('.cart-currency');
    console.log('🔍 [MOEDA] Elementos .cart-currency encontrados:', cartCurrencyElements.length);
    
    cartCurrencyElements.forEach(element => {
        const originalValue = parseFloat(element.getAttribute('data-original-value'));
        if (!isNaN(originalValue)) {
            if (element.id === 'frete' && originalValues.frete === 0) {
                element.textContent = 'Frete grátis';
                return;
            }
            const convertedValue = originalValue * rate;
            element.textContent = `${currencySymbol} ${convertedValue.toFixed(2).replace('.', ',')}`;
            console.log(`🔍 [MOEDA] ${element.id}: ${originalValue} → ${convertedValue.toFixed(2)}`);
        }
    });
    
    // Atualizar itens do carrinho
    const itemPrices = document.querySelectorAll('.item-price');
    console.log('🔍 [MOEDA] Elementos .item-price encontrados:', itemPrices.length);
    
    itemPrices.forEach(element => {
        const originalValue = parseFloat(element.getAttribute('data-original-price'));
        if (!isNaN(originalValue)) {
            const convertedValue = originalValue * rate;
            element.textContent = `${currencySymbol} ${convertedValue.toFixed(2).replace('.', ',')}`;
            console.log(`🔍 [MOEDA] Item: ${originalValue} → ${convertedValue.toFixed(2)}`);
        }
    });
    
    // Atualizar botão finalizar
    const botaoFinalizar = document.getElementById('btn-finalizar');
    if (botaoFinalizar) {
        switch(currency) {
            case 'BRL':
                botaoFinalizar.innerHTML = '<i class="fas fa-lock"></i> Finalizar Pedido com Pagamento Seguro (R$)';
                break;
            case 'USD':
                botaoFinalizar.innerHTML = '<i class="fas fa-lock"></i> Finalizar Pedido com Pagamento Seguro ($)';
                break;
            default:
                botaoFinalizar.innerHTML = '<i class="fas fa-lock"></i> Finalizar Pedido com Pagamento Seguro';
        }
        console.log('🔍 [MOEDA] Botão finalizar atualizado para moeda:', currency);
    }
    
    // Atualizar selo de moeda se existir
    const moedaSelect = document.getElementById('moeda_select');
    if (moedaSelect) {
        moedaSelect.value = currency;
        console.log('🔍 [MOEDA] Select de moeda atualizado para:', currency);
    }
    
    // Atualizar campo oculto de moeda
    const moedaHidden = document.getElementById('moeda_hidden');
    if (moedaHidden) {
        moedaHidden.value = currency;
        console.log('🔍 [MOEDA] Campo oculto de moeda atualizado para:', currency);
    }

    // Atualizar opções de forma de pagamento conforme moeda (BRL=AppMax, USD=Stripe)
    updatePaymentMethodsForCurrency(currency);
    
    // Atualizar símbolo da moeda no header se existir
    const currentCurrencyElement = document.getElementById('current-currency');
    if (currentCurrencyElement) {
        currentCurrencyElement.textContent = currency;
        console.log('🔍 [MOEDA] Símbolo no header atualizado para:', currency);
    }
    
    console.log('🔍 [MOEDA] updatePrices() concluída com sucesso');
}

function updatePaymentMethodsForCurrency(currency) {
    const select = document.getElementById('forma_pagamento');
    if (!select) return;

    const cur = (currency || '').toString().trim().toUpperCase();
    const isBRL = (cur === 'BRL');

    const currentValue = select.value;

    // Recriar options (evita opções inconsistentes ao trocar moeda)
    select.innerHTML = '';
    select.appendChild(new Option('Selecione...', ''));

    // Carteira deve aparecer sempre (independente da moeda)
    select.appendChild(new Option('Crédito da Carteira', 'carteira'));

    if (isBRL) {
        select.appendChild(new Option('Cartão de Crédito', 'cartao_credito'));
        select.appendChild(new Option('Boleto Bancário', 'boleto'));
        select.appendChild(new Option('PIX', 'pix'));
    } else {
        select.appendChild(new Option('Cartão de Crédito', 'cartao_credito'));
    }

    // Manter seleção se ainda válida
    const stillValid = Array.from(select.options).some(o => o.value === currentValue);
    if (stillValid) {
        select.value = currentValue;
    } else {
        if (!isBRL) {
            // Em USD, não pode ficar sem seleção quando o usuário já havia escolhido um método inválido (pix/boleto)
            select.value = (currentValue === 'carteira') ? 'carteira' : 'cartao_credito';
        } else {
            select.value = '';
        }
    }

    // Atualizar exibição dos campos conforme a forma selecionada
    if (typeof atualizarFormaPagamento === 'function') {
        atualizarFormaPagamento();
    }
}

// Inicializar com a moeda do header
function initCurrency() {
    var headerCurrency = document.getElementById('current-currency');
    var currentCurrency = headerCurrency ? headerCurrency.textContent : 'BRL';
    
    console.log('Header currency encontrado:', headerCurrency ? 'sim' : 'não'); // Debug
    console.log('Moeda inicial:', currentCurrency); // Debug
    
    // Atualizar campo oculto
    var hiddenField = document.getElementById('moeda_hidden');
    if (hiddenField) {
        hiddenField.value = currentCurrency;
        console.log('Campo oculto atualizado para:', currentCurrency); // Debug
    }
    
    // Atualizar preços
    updatePrices(currentCurrency);
}

// Função global para atualizar moeda no checkout
window.updateCheckoutCurrency = function(currency) {
    console.log('Atualizando checkout para:', currency); // Debug
    
    // Atualizar campo oculto
    var hiddenField = document.getElementById('moeda_hidden');
    if (hiddenField) {
        hiddenField.value = currency;
        console.log('Campo oculto atualizado para:', currency); // Debug
    }
    
    // Atualizar preços
    updatePrices(currency);
};

// Inicializar
initCurrency();

function onlyDigits(value) {
    return (value || '').toString().replace(/\D+/g, '');
}

function formatCardNumber(value) {
    const digits = onlyDigits(value).slice(0, 19);
    const groups = digits.match(/.{1,4}/g) || [];
    return groups.join(' ');
}

function clampMonth(mm) {
    const d = onlyDigits(mm).slice(0, 2);
    if (d.length === 0) return '';
    const n = Math.max(1, Math.min(12, parseInt(d, 10)));
    return String(n).padStart(2, '0');
}

function setupCardMasks() {
    const camposCartao = document.getElementById('campos-cartao');
    if (!camposCartao) return;

    const inputNumero = camposCartao.querySelector('input[name="card_number"]');
    const inputMes = camposCartao.querySelector('input[name="card_expiry_month"]');
    const inputAno = camposCartao.querySelector('input[name="card_expiry_year"]');
    const inputCvv = camposCartao.querySelector('input[name="card_cvv"]');

    if (inputNumero) {
        inputNumero.addEventListener('input', () => {
            const start = inputNumero.selectionStart || 0;
            const before = inputNumero.value;
            inputNumero.value = formatCardNumber(before);
            const delta = inputNumero.value.length - before.length;
            const nextPos = Math.max(0, start + delta);
            try { inputNumero.setSelectionRange(nextPos, nextPos); } catch (e) {}
        });
    }

    if (inputMes) {
        inputMes.addEventListener('input', () => {
            const raw = onlyDigits(inputMes.value).slice(0, 2);
            inputMes.value = raw;
            if (raw.length === 2 && inputAno) {
                inputAno.focus();
            }
        });
        inputMes.addEventListener('blur', () => {
            inputMes.value = clampMonth(inputMes.value);
        });
    }

    if (inputAno) {
        inputAno.addEventListener('input', () => {
            inputAno.value = onlyDigits(inputAno.value).slice(0, 4);
        });
    }

    if (inputCvv) {
        inputCvv.addEventListener('input', () => {
            inputCvv.value = onlyDigits(inputCvv.value).slice(0, 4);
        });
    }
}

// Verificar mudanças na moeda do header a cada 200ms (mais rápido)
setInterval(function() {
    var headerCurrency = document.getElementById('current-currency');
    if (headerCurrency) {
        var newCurrency = headerCurrency.textContent;
        var currentHiddenCurrency = document.getElementById('moeda_hidden').value;
        
        if (newCurrency !== currentHiddenCurrency) {
            console.log('Moeda mudou de', currentHiddenCurrency, 'para', newCurrency); // Debug
            document.getElementById('moeda_hidden').value = newCurrency;
            
            // Usar função global do header se existir
            if (typeof updateAllPrices === 'function') {
                updateAllPrices();
            } else {
                updatePrices(newCurrency);
            }
        }
    }
}, 200);

// Também verificar mudanças no localStorage
setInterval(function() {
    var storedCurrency = localStorage.getItem('selected_currency');
    var currentHiddenCurrency = document.getElementById('moeda_hidden').value;
    
    if (storedCurrency && storedCurrency !== currentHiddenCurrency) {
        console.log('Moeda mudou no localStorage de', currentHiddenCurrency, 'para', storedCurrency); // Debug
        document.getElementById('moeda_hidden').value = storedCurrency;
        
        // Usar função global do header se existir
        if (typeof updateAllPrices === 'function') {
            updateAllPrices();
        } else {
            updatePrices(storedCurrency);
        }
    }
}, 200);

// Função para habilitar/desabilitar botão finalizar
function toggleButton() {
    const checkbox = document.getElementById('consentimento_legal');
    const botao = document.getElementById('btn-finalizar');
    
    console.log('🔍 [BOTÃO] toggleButton() chamada');
    console.log('🔍 [BOTÃO] Checkbox marcado:', checkbox ? checkbox.checked : 'não');
    
    if (checkbox && botao) {
        const isChecked = checkbox.checked;
        botao.disabled = !isChecked;
        
        if (isChecked) {
            botao.className = 'btn btn-primary btn-lg w-100';
            console.log('🔍 [BOTÃO] Botão habilitado');
        } else {
            botao.className = 'btn btn-secondary btn-lg w-100';
            console.log('🔍 [BOTÃO] Botão desabilitado');
        }
        
        console.log('🔍 [BOTÃO] Estado final do botão:', !botao.disabled);
    } else {
        console.error('❌ [BOTÃO] Checkbox ou botão não encontrado');
    }
}

// Função para gerenciar seleção de endereço
document.addEventListener('DOMContentLoaded', function() {
    const enderecoSelect = document.getElementById('endereco-select');
    const btnNovoEndereco = document.getElementById('btn-novo-endereco');
    const enderecoForm = document.getElementById('endereco-form');
    
    if (enderecoSelect) {
        function fillEnderecoFormFromSelectedOption(selectedOption) {
            if (!enderecoForm) return;
            enderecoForm.style.display = 'block';
            if (document.getElementById('pais')) {
                document.getElementById('pais').value = (selectedOption && selectedOption.dataset && selectedOption.dataset.pais) ? (selectedOption.dataset.pais || 'BR') : 'BR';
            }
            document.getElementById('cep').value = (selectedOption && selectedOption.dataset) ? (selectedOption.dataset.cep || '') : '';
            document.getElementById('endereco').value = (selectedOption && selectedOption.dataset) ? (selectedOption.dataset.endereco || '') : '';
            document.querySelector('input[name="numero"]').value = (selectedOption && selectedOption.dataset) ? (selectedOption.dataset.numero || '') : '';
            document.querySelector('input[name="complemento"]').value = (selectedOption && selectedOption.dataset) ? (selectedOption.dataset.complemento || '') : '';
            document.getElementById('bairro').value = (selectedOption && selectedOption.dataset) ? (selectedOption.dataset.bairro || '') : '';
            document.getElementById('cidade').value = (selectedOption && selectedOption.dataset) ? (selectedOption.dataset.cidade || '') : '';
            document.getElementById('estado').value = (selectedOption && selectedOption.dataset) ? (selectedOption.dataset.estado || '') : '';
            try { atualizarEnderecoPorPais(); } catch (e) {}
        }

        function clearEnderecoForm() {
            if (!enderecoForm) return;
            enderecoForm.style.display = 'block';
            if (document.getElementById('pais')) document.getElementById('pais').value = 'BR';
            document.getElementById('cep').value = '';
            document.getElementById('endereco').value = '';
            document.querySelector('input[name="numero"]').value = '';
            document.querySelector('input[name="complemento"]').value = '';
            document.getElementById('bairro').value = '';
            document.getElementById('cidade').value = '';
            document.getElementById('estado').value = '';
            try { atualizarEnderecoPorPais(); } catch (e) {}
        }

        function enderecoSelecionadoTemCamposObrigatoriosFaltando(selectedOption) {
            if (!selectedOption || !selectedOption.dataset) return true;
            var pais = (selectedOption.dataset.pais || 'BR').toString().toUpperCase();
            var required = ['cep', 'endereco', 'cidade'];
            if (['BR','US','CA'].indexOf(pais) !== -1) {
                required.push('estado');
            }
            if (pais === 'BR') {
                required.push('numero');
                required.push('bairro');
            }
            for (var i = 0; i < required.length; i++) {
                var k = required[i];
                var v = (selectedOption.dataset[k] || '').toString().trim();
                if (!v) return true;
            }
            return false;
        }

        function handleEnderecoChange() {
            const selectedOption = enderecoSelect.options[enderecoSelect.selectedIndex];
            if (enderecoSelect.value === '') {
                clearEnderecoForm();
                return;
            }

            // Se o endereço selecionado está incompleto (ex.: sem numero/bairro para BR), mostrar formulário para completar.
            if (enderecoSelecionadoTemCamposObrigatoriosFaltando(selectedOption)) {
                fillEnderecoFormFromSelectedOption(selectedOption);
            } else {
                // Endereço completo: manter formulário oculto (melhor UX)
                if (enderecoForm) enderecoForm.style.display = 'none';
            }
        }

        enderecoSelect.addEventListener('change', handleEnderecoChange);

        // Quando existe um endereço principal já selecionado, o evento change não dispara.
        // Se ele estiver incompleto, abrimos o formulário automaticamente para permitir preencher numero/bairro.
        try { handleEnderecoChange(); } catch (e) {}
    }
    
    if (btnNovoEndereco) {
        btnNovoEndereco.addEventListener('click', function() {
            enderecoSelect.value = '';
            clearEnderecoForm();
        });
    }
});
</script>

<style>
.sticky-top {
    position: -webkit-sticky;
    position: sticky;
}

/* Garantir que o resumo não sobreponha o header */
@media (min-width: 992px) {
    .sticky-top {
        top: 100px !important;
        max-height: calc(100vh - 120px);
        overflow-y: auto;
    }
}

@media (max-width: 768px) {
    .container-fluid {
        padding: 0;
    }
    
    .sticky-top {
        top: 0 !important;
        position: relative !important;
    }
}

/* Evitar sobreposição com elementos fixos */
#resumo-pedido {
    z-index: 10;
    position: relative;
}

/* Garantir que o header fique acima do conteúdo */
header {
    z-index: 1000;
    position: relative;
}

/* Ajustar para não interferir com o seletor de moeda */
.currency-selector {
    z-index: 1001;
}

/* Ajuste específico para o header fixo */
.navbar {
    z-index: 1030 !important;
}

/* Garantir que o conteúdo principal fique abaixo do header */
main {
    margin-top: 0 !important;
    padding-top: 20px !important;
}

/* Container do checkout com espaçamento correto */
.container-fluid {
    padding-top: 20px;
    margin: 0 !important;
}

/* Ajustar o sticky-top do checkout para considerar o header fixo */
.checkout-sticky {
    position: sticky;
    top: 90px; /* Ajustado para header fixo */
    z-index: 10;
}

@media (max-width: 991.98px) {
    .checkout-sticky {
        position: static;
        top: auto;
    }
}

@media (max-width: 768px) {
    .container-fluid {
        padding-left: 12px;
        padding-right: 12px;
    }
}
</style>
<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
