<?php ob_start(); ?>
<div class="container py-5">
    <div class="row">
        <div class="col-lg-9 mx-auto">
            <div class="text-center mb-5">
                <h1 class="display-5 mb-3">Suporte</h1>
                <p class="lead text-muted">Estamos aqui para ajudar. Veja os canais de atendimento e orientações.</p>
            </div>

            <div class="row g-4">
                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4">
                            <h5 class="mb-3"><i class="fas fa-headset me-2"></i> Atendimento</h5>
                            <p class="text-muted mb-3">
                                Para solicitações, dúvidas ou problemas com pedidos, entre em contato pelos canais abaixo.
                            </p>
                            <ul class="text-muted mb-0">
                                <li>Horário: Segunda a Sexta, 9h às 18h</li>
                                <li>E-mail: suporte@brzlogistics.com</li>
                                <li>Formulário: <a href="/contato">Página de Contato</a></li>
                            </ul>
                        </div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body p-4">
                            <h5 class="mb-3"><i class="fas fa-search-location me-2"></i> Rastreamento</h5>
                            <p class="text-muted mb-3">
                                Acompanhe seu pedido e veja o status atualizado.
                            </p>
                            <a href="/rastreamento" class="btn btn-primary">
                                <i class="fas fa-truck me-2"></i> Ir para Rastreamento
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-body p-4">
                            <h5 class="mb-3"><i class="fas fa-life-ring me-2"></i> Dicas rápidas</h5>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <div class="p-3 bg-light rounded">
                                        <div class="fw-bold mb-1">Pedido atrasado</div>
                                        <div class="text-muted">Verifique o rastreamento e, se necessário, fale com o suporte.</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-3 bg-light rounded">
                                        <div class="fw-bold mb-1">Dados da conta</div>
                                        <div class="text-muted">Atualize seus dados em <a href="/meus-dados">Meus Dados</a>.</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="p-3 bg-light rounded">
                                        <div class="fw-bold mb-1">Dúvidas comuns</div>
                                        <div class="text-muted">Consulte o <a href="/faq">FAQ</a> para respostas rápidas.</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
