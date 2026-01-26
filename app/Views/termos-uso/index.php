<?php ob_start(); ?>
<div class="container py-5">
    <div class="row">
        <div class="col-lg-9 mx-auto">
            <div class="text-center mb-5">
                <h1 class="display-5 mb-3">Termos de Uso</h1>
                <p class="lead text-muted">Regras e condições para utilização da plataforma.</p>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4 p-md-5">
                    <p class="text-muted mb-4">
                        Estes Termos de Uso regulam o acesso e a utilização do site Braziliana Shop.
                        Ao utilizar nossos serviços, você concorda com estes termos.
                    </p>

                    <h4 class="mb-3">1. Uso da plataforma</h4>
                    <p class="text-muted">
                        Você se compromete a utilizar a plataforma de forma lícita, sem violar direitos de terceiros, e a fornecer informações verdadeiras ao se cadastrar.
                    </p>

                    <h4 class="mt-4 mb-3">2. Conta e segurança</h4>
                    <p class="text-muted">
                        Você é responsável por manter a confidencialidade das credenciais de acesso e por todas as atividades realizadas em sua conta.
                    </p>

                    <h4 class="mt-4 mb-3">3. Compras, pagamentos e prazos</h4>
                    <p class="text-muted">
                        Ao efetuar uma compra, você concorda com os valores apresentados, prazos estimados e eventuais políticas de cancelamento aplicáveis.
                        As condições podem variar conforme o produto, forma de pagamento e logística.
                    </p>

                    <h4 class="mt-4 mb-3">4. Propriedade intelectual</h4>
                    <p class="text-muted">
                        Conteúdos, marcas, logotipos e materiais presentes no site são protegidos por leis de propriedade intelectual e não podem ser utilizados sem autorização.
                    </p>

                    <h4 class="mt-4 mb-3">5. Limitação de responsabilidade</h4>
                    <p class="text-muted">
                        Buscamos manter o serviço estável e seguro, mas não garantimos disponibilidade ininterrupta.
                        Não nos responsabilizamos por danos indiretos decorrentes do uso da plataforma, na medida permitida por lei.
                    </p>

                    <h4 class="mt-4 mb-3">6. Suspensão e encerramento</h4>
                    <p class="text-muted">
                        Podemos suspender ou encerrar contas em caso de suspeita de fraude, uso indevido ou violação destes termos.
                    </p>

                    <h4 class="mt-4 mb-3">7. Alterações</h4>
                    <p class="text-muted">
                        Podemos atualizar estes Termos de Uso periodicamente. A versão vigente será sempre disponibilizada nesta página.
                    </p>

                    <h4 class="mt-4 mb-3">8. Contato</h4>
                    <p class="text-muted mb-0">
                        Para dúvidas, utilize a página de <a href="/contato">Contato</a>.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
