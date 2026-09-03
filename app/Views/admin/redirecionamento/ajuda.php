<?php
$sidebarActive = 'redirecionamento-ajuda';
$title = 'Como Funciona - Guia do Redirecionador';
?>
<?php ob_start(); ?>
<div class="container-fluid p-4" style="max-width:960px">
    <div class="text-center mb-5">
        <div class="mb-3"><i class="fas fa-truck-fast fa-3x text-primary"></i></div>
        <h1 class="h2 fw-bold">Guia do Redirecionador</h1>
        <p class="text-muted">Tudo o que você precisa saber para enviar pacotes pela Braziliana</p>
    </div>

    <!-- Fluxo visual -->
    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body">
            <h5 class="fw-bold mb-3"><i class="fas fa-route me-2 text-primary"></i>Como funciona o processo</h5>
            <div class="row text-center g-3">
                <?php
                $steps = [
                    ['icon'=>'fa-user-plus','title'=>'1. Cadastre o cliente','desc'=>'Cadastre o destinatário com CPF, endereço no Brasil e dados de contato.'],
                    ['icon'=>'fa-box','title'=>'2. Crie o envio','desc'=>'Informe o peso, dimensões, produtos (com NCM) e o ID do pedido do cliente.'],
                    ['icon'=>'fa-credit-card','title'=>'3. Pague','desc'=>'O valor é calculado pela tabela de pesos. Pague via Stripe (cartão).'],
                    ['icon'=>'fa-tag','title'=>'4. Gere a etiqueta','desc'=>'Após o pagamento, clique em "Gerar Etiqueta". O rastreio é criado automaticamente.'],
                    ['icon'=>'fa-print','title'=>'5. Imprima e cole','desc'=>'Baixe/imprima a etiqueta e cole na caixa do pacote.'],
                    ['icon'=>'fa-calendar-check','title'=>'6. Coleta ou envio','desc'=>'Agende a coleta na aba "Coletas" ou envie o pacote você mesmo para a nossa sede.'],
                    ['icon'=>'fa-weight-hanging','title'=>'7. Verificação','desc'=>'Conferimos peso e dimensões reais. Se houver diferença, cobramos ou reembolsamos.'],
                    ['icon'=>'fa-plane','title'=>'8. Envio e entrega','desc'=>'O pacote é enviado ao Brasil. Acompanhe pelo código de rastreio.'],
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
                    <i class="fas fa-users me-2 text-primary"></i>Cadastro de Clientes
                </button>
            </h2>
            <div id="secClientes" class="accordion-collapse collapse show" data-bs-parent="#accordionAjuda">
                <div class="accordion-body">
                    <p>Antes de criar um envio, você precisa cadastrar o destinatário (cliente final no Brasil).</p>
                    <ul>
                        <li><strong>Nome completo</strong> — como consta no documento</li>
                        <li><strong>CPF</strong> — obrigatório para envios internacionais ao Brasil</li>
                        <li><strong>Data de nascimento</strong> — o destinatário deve ter 18+ anos</li>
                        <li><strong>Endereço completo</strong> — CEP, rua, número, bairro, cidade e estado</li>
                        <li><strong>E-mail e telefone</strong> — para contato em caso de problemas na entrega</li>
                    </ul>
                    <div class="alert alert-info small py-2">
                        <i class="fas fa-lightbulb me-2"></i>Dica: o CEP preenche automaticamente o endereço. Basta digitar o CEP e os campos são preenchidos.
                    </div>
                </div>
            </div>
        </div>

        <!-- Criando um envio -->
        <div class="accordion-item border-0 shadow-sm mb-3">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#secEnvio">
                    <i class="fas fa-box me-2 text-primary"></i>Criando um Envio
                </button>
            </h2>
            <div id="secEnvio" class="accordion-collapse collapse" data-bs-parent="#accordionAjuda">
                <div class="accordion-body">
                    <p>Para criar um envio, vá em <strong>Envios → Novo Envio</strong> e siga os passos:</p>
                    <ol>
                        <li><strong>Pedido:</strong> Informe o ID do pedido do seu cliente (referência interna sua)</li>
                        <li><strong>Destinatário:</strong> Selecione o cliente cadastrado</li>
                        <li><strong>Envio:</strong> Informe peso (kg), largura, altura e comprimento (cm)</li>
                        <li><strong>Produtos:</strong> Adicione cada produto com descrição, NCM, preço e peso</li>
                        <li><strong>Pagamento:</strong> Pague o valor calculado via cartão</li>
                    </ol>
                    <div class="alert alert-warning small py-2">
                        <i class="fas fa-exclamation-triangle me-2"></i><strong>Importante:</strong> Informe o peso e dimensões com precisão! Após a coleta, verificamos os valores reais. Se houver diferença, será cobrada automaticamente.
                    </div>
                </div>
            </div>
        </div>

        <!-- NCM -->
        <div class="accordion-item border-0 shadow-sm mb-3">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#secNcm">
                    <i class="fas fa-barcode me-2 text-primary"></i>O que é NCM?
                </button>
            </h2>
            <div id="secNcm" class="accordion-collapse collapse" data-bs-parent="#accordionAjuda">
                <div class="accordion-body">
                    <p>NCM (Nomenclatura Comum do Mercosul) é o código que classifica o produto para a alfândega. É <strong>obrigatório</strong> para gerar a etiqueta.</p>
                    <p>Na hora de adicionar produtos, comece a digitar o nome ou código NCM e o sistema sugere automaticamente. Exemplos:</p>
                    <ul>
                        <li><code>33030010</code> — Perfumes</li>
                        <li><code>85171300</code> — Celulares</li>
                        <li><code>63090090</code> — Roupas (geral)</li>
                        <li><code>95030099</code> — Brinquedos em Geral</li>
                        <li><code>18063110</code> — Chocolates</li>
                    </ul>
                    <div class="alert alert-info small py-2">
                        <i class="fas fa-lightbulb me-2"></i>Se não souber o NCM, pesquise no campo — o sistema tem uma lista completa com descrições.
                    </div>
                </div>
            </div>
        </div>

        <!-- Etiqueta -->
        <div class="accordion-item border-0 shadow-sm mb-3">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#secEtiqueta">
                    <i class="fas fa-tag me-2 text-primary"></i>Gerando a Etiqueta
                </button>
            </h2>
            <div id="secEtiqueta" class="accordion-collapse collapse" data-bs-parent="#accordionAjuda">
                <div class="accordion-body">
                    <p>Após o pagamento ser confirmado:</p>
                    <ol>
                        <li>Abra o detalhe do envio</li>
                        <li>Clique no botão verde <strong>"Gerar Etiqueta"</strong></li>
                        <li>Aguarde a geração (alguns segundos)</li>
                        <li>O código de rastreio aparece automaticamente</li>
                        <li>Clique em <strong>"Imprimir / Baixar Etiqueta"</strong></li>
                        <li>Imprima e cole na caixa</li>
                    </ol>
                    <div class="alert alert-danger small py-2">
                        <i class="fas fa-exclamation-circle me-2"></i><strong>Atenção:</strong> A etiqueta só pode ser gerada UMA vez. Confira todos os dados antes de gerar.
                    </div>
                </div>
            </div>
        </div>

        <!-- Coleta -->
        <div class="accordion-item border-0 shadow-sm mb-3">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#secColeta">
                    <i class="fas fa-calendar-check me-2 text-primary"></i>Agendando a Coleta
                </button>
            </h2>
            <div id="secColeta" class="accordion-collapse collapse" data-bs-parent="#accordionAjuda">
                <div class="accordion-body">
                    <p>Após gerar a etiqueta e colar na caixa:</p>
                    <ol>
                        <li>Vá em <strong>Coletas</strong> no menu lateral</li>
                        <li>Clique em <strong>"Agendar Coleta"</strong></li>
                        <li>Selecione o envio, data e horário</li>
                        <li>Aguarde a confirmação do admin</li>
                        <li>No dia agendado, tenha o pacote pronto para retirada</li>
                    </ol>
                </div>
            </div>
        </div>

        <!-- Enviar para a sede -->
        <div class="accordion-item border-0 shadow-sm mb-3">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#secEnvioSede">
                    <i class="fas fa-dolly me-2 text-primary"></i>Enviar o pacote para a sede
                </button>
            </h2>
            <div id="secEnvioSede" class="accordion-collapse collapse" data-bs-parent="#accordionAjuda">
                <div class="accordion-body">
                    <p>Se preferir não aguardar a coleta, você mesmo pode despachar o pacote para a nossa sede:</p>
                    <ol>
                        <li>Gere e cole a etiqueta na caixa</li>
                        <li>Envie o pacote para o nosso endereço de recebimento (abaixo)</li>
                        <li>Vá em <strong>Envios à Sede</strong> no menu lateral e clique em <strong>"Registrar envio à sede"</strong></li>
                        <li>Selecione o envio, informe a transportadora e o rastreio, e confirme — nossa equipe é notificada na hora</li>
                    </ol>
                    <div class="alert alert-info small py-2 mb-0">
                        <i class="fas fa-map-marker-alt me-2"></i><strong>Endereço de recebimento:</strong>
                        <?= htmlspecialchars($enderecoSede ?? '1227 W Broad St, Saint Pauls, NC 28384', ENT_QUOTES, 'UTF-8') ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabela de preços -->
        <div class="accordion-item border-0 shadow-sm mb-3">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#secPrecos">
                    <i class="fas fa-dollar-sign me-2 text-primary"></i>Tabela de Preços
                </button>
            </h2>
            <div id="secPrecos" class="accordion-collapse collapse" data-bs-parent="#accordionAjuda">
                <div class="accordion-body">
                    <p>O valor do envio é calculado automaticamente pela <strong>faixa de peso</strong> (em USD).</p>
                    <p>Consulte a tabela completa em <strong>Tabela de Pesos e Preços</strong> no menu lateral.</p>
                    <ul>
                        <li>O valor é cobrado por faixa (ex: até 0.5kg, até 1.0kg, etc.)</li>
                        <li>Peso máximo: 30kg por pacote</li>
                        <li>Se o peso real for diferente do informado, a diferença é cobrada ou reembolsada</li>
                    </ul>
                </div>
            </div>
        </div>

        <!-- Divergências -->
        <div class="accordion-item border-0 shadow-sm mb-3">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#secDivergencia">
                    <i class="fas fa-scale-balanced me-2 text-primary"></i>Divergências de Peso
                </button>
            </h2>
            <div id="secDivergencia" class="accordion-collapse collapse" data-bs-parent="#accordionAjuda">
                <div class="accordion-body">
                    <p>Após a coleta, verificamos o peso e dimensões reais do pacote.</p>
                    <ul>
                        <li><strong>Se o peso real for maior:</strong> você receberá um e-mail com o valor da diferença a pagar</li>
                        <li><strong>Se o peso real for menor:</strong> o valor excedente será reembolsado</li>
                    </ul>
                    <p>Acompanhe divergências em <strong>Divergências e Ajustes</strong> no menu lateral.</p>
                    <div class="alert alert-info small py-2">
                        <i class="fas fa-lightbulb me-2"></i>Dica: use uma balança precisa para evitar divergências e cobranças extras.
                    </div>
                </div>
            </div>
        </div>

        <!-- Dúvidas -->
        <div class="accordion-item border-0 shadow-sm mb-3">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#secDuvidas">
                    <i class="fas fa-question-circle me-2 text-primary"></i>Dúvidas Frequentes
                </button>
            </h2>
            <div id="secDuvidas" class="accordion-collapse collapse" data-bs-parent="#accordionAjuda">
                <div class="accordion-body">
                    <div class="mb-3">
                        <strong>Posso enviar qualquer produto?</strong>
                        <p class="text-muted mb-0">Não. Produtos proibidos pela alfândega brasileira (armas, drogas, medicamentos controlados, etc.) não podem ser enviados.</p>
                    </div>
                    <div class="mb-3">
                        <strong>Quanto tempo demora a entrega?</strong>
                        <p class="text-muted mb-0">O prazo varia de 15 a 45 dias úteis, dependendo da liberação aduaneira.</p>
                    </div>
                    <div class="mb-3">
                        <strong>O destinatário paga impostos?</strong>
                        <p class="text-muted mb-0">Sim, a Receita Federal pode cobrar impostos de importação. O valor depende do tipo de produto e valor declarado.</p>
                    </div>
                    <div class="mb-3">
                        <strong>Posso cancelar um envio?</strong>
                        <p class="text-muted mb-0">Antes da coleta, entre em contato com o suporte. Após a coleta, não é possível cancelar.</p>
                    </div>
                    <div class="mb-3">
                        <strong>Como acompanho o rastreio?</strong>
                        <p class="text-muted mb-0">O código de rastreio aparece no detalhe do envio após gerar a etiqueta. Use-o no site dos Correios ou da transportadora.</p>
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
                <h5>Precisa de ajuda?</h5>
                <p class="text-muted mb-2">Entre em contato com nosso suporte</p>
                <a href="mailto:suporte@brazilianashop.com.br" class="btn btn-outline-primary btn-sm"><i class="fas fa-envelope me-2"></i>suporte@brazilianashop.com.br</a>
            </div>
        </div>
    </div>
</div>
<?php $content = ob_get_clean(); include __DIR__ . '/../../layouts/admin.php'; ?>
