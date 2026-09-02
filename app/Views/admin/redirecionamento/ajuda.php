<?php
$sidebarActive = 'redirecionamento-ajuda';
$title = __('admin.redirect.help_title', 'Como Funciona - Guia do Redirecionador');
?>
<?php ob_start(); ?>
<div class="container-fluid p-4" style="max-width:960px">
    <div class="text-center mb-5">
        <div class="mb-3"><i class="fas fa-truck-fast fa-3x text-primary"></i></div>
        <h1 class="h2 fw-bold"><?= __('admin.redirect.redirector_guide', 'Guia do Redirecionador') ?></h1>
        <p class="text-muted"><?= __('admin.redirect.help_intro', 'Tudo o que você precisa saber para enviar pacotes pela Braziliana') ?></p>
    </div>

    <!-- Fluxo visual -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <h5 class="fw-bold mb-3"><i class="fas fa-route me-2 text-primary"></i><?= __('admin.redirect.how_process_works', 'Como funciona o processo') ?></h5>
            <div class="row text-center g-3">
                <?php
                $steps = [
                    ['icon'=>'fa-user-plus','title'=>__('admin.redirect.step1_title','1. Cadastre o cliente'),'desc'=>__('admin.redirect.step1_desc','Cadastre o destinatário com CPF, endereço no Brasil e dados de contato.')],
                    ['icon'=>'fa-box','title'=>__('admin.redirect.step2_title','2. Crie o envio'),'desc'=>__('admin.redirect.step2_desc','Informe o peso, dimensões, produtos (com NCM) e o ID do pedido do cliente.')],
                    ['icon'=>'fa-credit-card','title'=>__('admin.redirect.step3_title','3. Pague'),'desc'=>__('admin.redirect.step3_desc','O valor é calculado pela tabela de pesos. Pague via Stripe (cartão).')],
                    ['icon'=>'fa-tag','title'=>__('admin.redirect.step4_title','4. Gere a etiqueta'),'desc'=>__('admin.redirect.step4_desc','Após o pagamento, clique em "Gerar Etiqueta". O rastreio é criado automaticamente.')],
                    ['icon'=>'fa-print','title'=>__('admin.redirect.step5_title','5. Imprima e cole'),'desc'=>__('admin.redirect.step5_desc','Baixe/imprima a etiqueta e cole na caixa do pacote.')],
                    ['icon'=>'fa-calendar-check','title'=>__('admin.redirect.step6_title','6. Agende a coleta'),'desc'=>__('admin.redirect.step6_desc','Agende a coleta na aba "Coletas". Nós vamos buscar o pacote.')],
                    ['icon'=>'fa-weight-hanging','title'=>__('admin.redirect.step7_title','7. Verificação'),'desc'=>__('admin.redirect.step7_desc','Conferimos peso e dimensões reais. Se houver diferença, cobramos ou reembolsamos.')],
                    ['icon'=>'fa-plane','title'=>__('admin.redirect.step8_title','8. Envio e entrega'),'desc'=>__('admin.redirect.step8_desc','O pacote é enviado ao Brasil. Acompanhe pelo código de rastreio.')],
                ];
                foreach ($steps as $s): ?>
                <div class="col-md-3 col-6">
                    <div class="p-3 rounded-3" style="background:#f8fafc">
                        <i class="fas <?= $s['icon'] ?> fa-2x text-primary mb-2"></i>
                        <div class="fw-bold small"><?= $s['title'] ?></div>
                        <div class="text-muted" style="font-size:.75rem"><?= $s['desc'] ?></div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Seções detalhadas -->
    <div class="accordion" id="accordionAjuda">

        <!-- Cadastro de clientes -->
        <div class="accordion-item border-0 shadow-sm mb-3">
            <h2 class="accordion-header">
                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#secClientes">
                    <i class="fas fa-users me-2 text-primary"></i><?= __('admin.redirect.help_client_registration', 'Cadastro de Clientes') ?>
                </button>
            </h2>
            <div id="secClientes" class="accordion-collapse collapse show" data-bs-parent="#accordionAjuda">
                <div class="accordion-body">
                    <p><?= __('admin.redirect.help_before_shipment_register', 'Antes de criar um envio, você precisa cadastrar o destinatário (cliente final no Brasil).') ?></p>
                    <ul>
                        <li><strong><?= __('admin.redirect.help_full_name', 'Nome completo') ?></strong> — <?= __('admin.redirect.help_as_in_document', 'como consta no documento') ?></li>
                        <li><strong>CPF</strong> — <?= __('admin.redirect.help_cpf_required_intl', 'obrigatório para envios internacionais ao Brasil') ?></li>
                        <li><strong><?= __('admin.redirect.help_birth_date', 'Data de nascimento') ?></strong> — <?= __('admin.redirect.help_recipient_18plus', 'o destinatário deve ter 18+ anos') ?></li>
                        <li><strong><?= __('admin.redirect.help_full_address', 'Endereço completo') ?></strong> — <?= __('admin.redirect.help_address_fields', 'CEP, rua, número, bairro, cidade e estado') ?></li>
                        <li><strong><?= __('admin.redirect.help_email_phone', 'E-mail e telefone') ?></strong> — <?= __('admin.redirect.help_contact_delivery_problems', 'para contato em caso de problemas na entrega') ?></li>
                    </ul>
                    <div class="alert alert-info small py-2">
                        <i class="fas fa-lightbulb me-2"></i><?= __('admin.redirect.help_tip_cep_autofill', 'Dica: o CEP preenche automaticamente o endereço. Basta digitar o CEP e os campos são preenchidos.') ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Criando um envio -->
        <div class="accordion-item border-0 shadow-sm mb-3">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#secEnvio">
                    <i class="fas fa-box me-2 text-primary"></i><?= __('admin.redirect.help_creating_shipment', 'Criando um Envio') ?>
                </button>
            </h2>
            <div id="secEnvio" class="accordion-collapse collapse" data-bs-parent="#accordionAjuda">
                <div class="accordion-body">
                    <p><?= __('admin.redirect.help_to_create_go_to', 'Para criar um envio, vá em') ?> <strong><?= __('admin.redirect.help_shipments_new_shipment', 'Envios → Novo Envio') ?></strong> <?= __('admin.redirect.help_and_follow_steps', 'e siga os passos:') ?></p>
                    <ol>
                        <li><strong><?= __('admin.redirect.step_order', 'Pedido') ?>:</strong> <?= __('admin.redirect.help_order_step', 'Informe o ID do pedido do seu cliente (referência interna sua)') ?></li>
                        <li><strong><?= __('admin.redirect.recipient', 'Destinatário') ?>:</strong> <?= __('admin.redirect.help_recipient_step', 'Selecione o cliente cadastrado') ?></li>
                        <li><strong><?= __('admin.redirect.step_shipment', 'Envio') ?>:</strong> <?= __('admin.redirect.help_shipment_step', 'Informe peso (kg), largura, altura e comprimento (cm)') ?></li>
                        <li><strong><?= __('admin.redirect.products', 'Produtos') ?>:</strong> <?= __('admin.redirect.help_products_step', 'Adicione cada produto com descrição, NCM, preço e peso') ?></li>
                        <li><strong><?= __('admin.redirect.payment', 'Pagamento') ?>:</strong> <?= __('admin.redirect.help_payment_step', 'Pague o valor calculado via cartão') ?></li>
                    </ol>
                    <div class="alert alert-warning small py-2">
                        <i class="fas fa-exclamation-triangle me-2"></i><strong><?= __('admin.redirect.help_important', 'Importante:') ?></strong> <?= __('admin.redirect.help_precise_weight_warning', 'Informe o peso e dimensões com precisão! Após a coleta, verificamos os valores reais. Se houver diferença, será cobrada automaticamente.') ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- NCM -->
        <div class="accordion-item border-0 shadow-sm mb-3">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#secNcm">
                    <i class="fas fa-barcode me-2 text-primary"></i><?= __('admin.redirect.help_what_is_ncm', 'O que é NCM?') ?>
                </button>
            </h2>
            <div id="secNcm" class="accordion-collapse collapse" data-bs-parent="#accordionAjuda">
                <div class="accordion-body">
                    <p><?= __('admin.redirect.help_ncm_definition', 'NCM (Nomenclatura Comum do Mercosul) é o código que classifica o produto para a alfândega. É <strong>obrigatório</strong> para gerar a etiqueta.') ?></p>
                    <p><?= __('admin.redirect.help_ncm_autosuggest', 'Na hora de adicionar produtos, comece a digitar o nome ou código NCM e o sistema sugere automaticamente. Exemplos:') ?></p>
                    <ul>
                        <li><code>33030010</code> — <?= __('admin.redirect.help_ncm_perfumes', 'Perfumes') ?></li>
                        <li><code>85171300</code> — <?= __('admin.redirect.help_ncm_phones', 'Celulares') ?></li>
                        <li><code>63090090</code> — <?= __('admin.redirect.help_ncm_clothing', 'Roupas (geral)') ?></li>
                        <li><code>95030099</code> — <?= __('admin.redirect.help_ncm_toys', 'Brinquedos em Geral') ?></li>
                        <li><code>18063110</code> — <?= __('admin.redirect.help_ncm_chocolates', 'Chocolates') ?></li>
                    </ul>
                    <div class="alert alert-info small py-2">
                        <i class="fas fa-lightbulb me-2"></i><?= __('admin.redirect.help_ncm_search_tip', 'Se não souber o NCM, pesquise no campo — o sistema tem uma lista completa com descrições.') ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Etiqueta -->
        <div class="accordion-item border-0 shadow-sm mb-3">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#secEtiqueta">
                    <i class="fas fa-tag me-2 text-primary"></i><?= __('admin.redirect.help_generating_label', 'Gerando a Etiqueta') ?>
                </button>
            </h2>
            <div id="secEtiqueta" class="accordion-collapse collapse" data-bs-parent="#accordionAjuda">
                <div class="accordion-body">
                    <p><?= __('admin.redirect.help_after_payment_confirmed', 'Após o pagamento ser confirmado:') ?></p>
                    <ol>
                        <li><?= __('admin.redirect.help_open_shipment_detail', 'Abra o detalhe do envio') ?></li>
                        <li><?= __('admin.redirect.help_click_green_button', 'Clique no botão verde') ?> <strong>"<?= __('admin.redirect.generate_label', 'Gerar Etiqueta') ?>"</strong></li>
                        <li><?= __('admin.redirect.help_wait_generation', 'Aguarde a geração (alguns segundos)') ?></li>
                        <li><?= __('admin.redirect.help_tracking_appears', 'O código de rastreio aparece automaticamente') ?></li>
                        <li><?= __('admin.redirect.help_click_on', 'Clique em') ?> <strong>"<?= __('admin.redirect.print_download_label', 'Imprimir / Baixar Etiqueta') ?>"</strong></li>
                        <li><?= __('admin.redirect.help_print_and_glue', 'Imprima e cole na caixa') ?></li>
                    </ol>
                    <div class="alert alert-danger small py-2">
                        <i class="fas fa-exclamation-circle me-2"></i><strong><?= __('admin.redirect.help_attention', 'Atenção:') ?></strong> <?= __('admin.redirect.help_label_once', 'A etiqueta só pode ser gerada UMA vez. Confira todos os dados antes de gerar.') ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Coleta -->
        <div class="accordion-item border-0 shadow-sm mb-3">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#secColeta">
                    <i class="fas fa-calendar-check me-2 text-primary"></i><?= __('admin.redirect.help_scheduling_collection', 'Agendando a Coleta') ?>
                </button>
            </h2>
            <div id="secColeta" class="accordion-collapse collapse" data-bs-parent="#accordionAjuda">
                <div class="accordion-body">
                    <p><?= __('admin.redirect.help_after_label_glued', 'Após gerar a etiqueta e colar na caixa:') ?></p>
                    <ol>
                        <li><?= __('admin.redirect.help_go_to', 'Vá em') ?> <strong><?= __('admin.redirect.collections', 'Coletas') ?></strong> <?= __('admin.redirect.help_in_side_menu', 'no menu lateral') ?></li>
                        <li><?= __('admin.redirect.help_click_on', 'Clique em') ?> <strong>"<?= __('admin.redirect.schedule_collection', 'Agendar Coleta') ?>"</strong></li>
                        <li><?= __('admin.redirect.help_select_shipment_date_time', 'Selecione o envio, data e horário') ?></li>
                        <li><?= __('admin.redirect.help_await_admin_confirmation', 'Aguarde a confirmação do admin') ?></li>
                        <li><?= __('admin.redirect.help_have_package_ready', 'No dia agendado, tenha o pacote pronto para retirada') ?></li>
                    </ol>
                </div>
            </div>
        </div>

        <!-- Tabela de preços -->
        <div class="accordion-item border-0 shadow-sm mb-3">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#secPrecos">
                    <i class="fas fa-dollar-sign me-2 text-primary"></i><?= __('admin.redirect.help_price_table', 'Tabela de Preços') ?>
                </button>
            </h2>
            <div id="secPrecos" class="accordion-collapse collapse" data-bs-parent="#accordionAjuda">
                <div class="accordion-body">
                    <p><?= __('admin.redirect.help_value_by_weight_range', 'O valor do envio é calculado automaticamente pela <strong>faixa de peso</strong> (em USD).') ?></p>
                    <p><?= __('admin.redirect.help_see_full_table', 'Consulte a tabela completa em <strong>Tabela de Pesos e Preços</strong> no menu lateral.') ?></p>
                    <ul>
                        <li><?= __('admin.redirect.help_charged_by_range', 'O valor é cobrado por faixa (ex: até 0.5kg, até 1.0kg, etc.)') ?></li>
                        <li><?= __('admin.redirect.help_max_weight', 'Peso máximo: 30kg por pacote') ?></li>
                        <li><?= __('admin.redirect.help_weight_diff_adjust', 'Se o peso real for diferente do informado, a diferença é cobrada ou reembolsada') ?></li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Divergências -->
        <div class="accordion-item border-0 shadow-sm mb-3">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#secDivergencia">
                    <i class="fas fa-scale-balanced me-2 text-primary"></i><?= __('admin.redirect.help_weight_divergences', 'Divergências de Peso') ?>
                </button>
            </h2>
            <div id="secDivergencia" class="accordion-collapse collapse" data-bs-parent="#accordionAjuda">
                <div class="accordion-body">
                    <p><?= __('admin.redirect.help_after_collection_verify', 'Após a coleta, verificamos o peso e dimensões reais do pacote.') ?></p>
                    <ul>
                        <li><strong><?= __('admin.redirect.help_if_real_weight_greater', 'Se o peso real for maior:') ?></strong> <?= __('admin.redirect.help_receive_email_diff', 'você receberá um e-mail com o valor da diferença a pagar') ?></li>
                        <li><strong><?= __('admin.redirect.help_if_real_weight_smaller', 'Se o peso real for menor:') ?></strong> <?= __('admin.redirect.help_excess_refunded', 'o valor excedente será reembolsado') ?></li>
                    </ul>
                    <p><?= __('admin.redirect.help_track_divergences', 'Acompanhe divergências em <strong>Divergências e Ajustes</strong> no menu lateral.') ?></p>
                    <div class="alert alert-info small py-2">
                        <i class="fas fa-lightbulb me-2"></i><?= __('admin.redirect.help_tip_precise_scale', 'Dica: use uma balança precisa para evitar divergências e cobranças extras.') ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Dúvidas -->
        <div class="accordion-item border-0 shadow-sm mb-3">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#secDuvidas">
                    <i class="fas fa-question-circle me-2 text-primary"></i><?= __('admin.redirect.help_faq', 'Dúvidas Frequentes') ?>
                </button>
            </h2>
            <div id="secDuvidas" class="accordion-collapse collapse" data-bs-parent="#accordionAjuda">
                <div class="accordion-body">
                    <div class="mb-3">
                        <strong><?= __('admin.redirect.help_faq_q_any_product', 'Posso enviar qualquer produto?') ?></strong>
                        <p class="text-muted mb-0"><?= __('admin.redirect.help_faq_a_any_product', 'Não. Produtos proibidos pela alfândega brasileira (armas, drogas, medicamentos controlados, etc.) não podem ser enviados.') ?></p>
                    </div>
                    <div class="mb-3">
                        <strong><?= __('admin.redirect.help_faq_q_delivery_time', 'Quanto tempo demora a entrega?') ?></strong>
                        <p class="text-muted mb-0"><?= __('admin.redirect.help_faq_a_delivery_time', 'O prazo varia de 15 a 45 dias úteis, dependendo da liberação aduaneira.') ?></p>
                    </div>
                    <div class="mb-3">
                        <strong><?= __('admin.redirect.help_faq_q_taxes', 'O destinatário paga impostos?') ?></strong>
                        <p class="text-muted mb-0"><?= __('admin.redirect.help_faq_a_taxes', 'Sim, a Receita Federal pode cobrar impostos de importação. O valor depende do tipo de produto e valor declarado.') ?></p>
                    </div>
                    <div class="mb-3">
                        <strong><?= __('admin.redirect.help_faq_q_cancel', 'Posso cancelar um envio?') ?></strong>
                        <p class="text-muted mb-0"><?= __('admin.redirect.help_faq_a_cancel', 'Antes da coleta, entre em contato com o suporte. Após a coleta, não é possível cancelar.') ?></p>
                    </div>
                    <div class="mb-3">
                        <strong><?= __('admin.redirect.help_faq_q_tracking', 'Como acompanho o rastreio?') ?></strong>
                        <p class="text-muted mb-0"><?= __('admin.redirect.help_faq_a_tracking', 'O código de rastreio aparece no detalhe do envio após gerar a etiqueta. Use-o no site dos Correios ou da transportadora.') ?></p>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Contato -->
    <div class="text-center mt-4 mb-5">
        <div class="card border-0 shadow-sm">
            <div class="card-body py-4">
                <i class="fas fa-headset fa-2x text-primary mb-2"></i>
                <h5><?= __('admin.redirect.help_need_help', 'Precisa de ajuda?') ?></h5>
                <p class="text-muted mb-2"><?= __('admin.redirect.help_contact_support', 'Entre em contato com nosso suporte') ?></p>
                <a href="mailto:suporte@brazilianashop.com.br" class="btn btn-outline-primary btn-sm"><i class="fas fa-envelope me-2"></i>suporte@brazilianashop.com.br</a>
            </div>
        </div>
    </div>
</div>
<?php $content = ob_get_clean(); include __DIR__ . '/../../layouts/admin.php'; ?>
