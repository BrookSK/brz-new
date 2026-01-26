<?php ob_start(); ?>
<div class="container py-5">
    <div class="row">
        <div class="col-lg-9 mx-auto">
            <div class="text-center mb-5">
                <h1 class="display-5 mb-3">Política de Privacidade</h1>
                <p class="lead text-muted">Entenda como coletamos, usamos e protegemos seus dados.</p>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-body p-4 p-md-5">
                    <p class="text-muted mb-4">
                        Esta Política de Privacidade descreve como a Braziliana Shop coleta, utiliza e compartilha informações quando você utiliza nosso site.
                        Ao acessar ou usar nossos serviços, você concorda com as práticas descritas neste documento.
                    </p>

                    <h4 class="mb-3">1. Coleta de informações</h4>
                    <p class="text-muted">
                        Podemos coletar informações fornecidas por você (como nome, e-mail, telefone, endereço e dados de pagamento) quando você cria uma conta,
                        realiza compras, entra em contato com o suporte ou utiliza funcionalidades do site.
                    </p>

                    <h4 class="mt-4 mb-3">2. Uso das informações</h4>
                    <p class="text-muted">
                        Utilizamos as informações para:
                    </p>
                    <ul class="text-muted">
                        <li>Processar pedidos e pagamentos</li>
                        <li>Gerenciar sua conta e preferências</li>
                        <li>Prestar suporte e atendimento</li>
                        <li>Melhorar nossos serviços e prevenir fraudes</li>
                        <li>Enviar comunicações relacionadas ao serviço (e, quando aplicável, marketing)</li>
                    </ul>

                    <h4 class="mt-4 mb-3">3. Compartilhamento</h4>
                    <p class="text-muted">
                        Podemos compartilhar informações com provedores de serviços essenciais (ex.: gateways de pagamento, logística e ferramentas de comunicação),
                        sempre limitando o acesso ao necessário para execução do serviço.
                    </p>

                    <h4 class="mt-4 mb-3">4. Cookies e tecnologias semelhantes</h4>
                    <p class="text-muted">
                        Utilizamos cookies para melhorar sua experiência, manter sua sessão e entender como o site é utilizado.
                        Você pode ajustar as permissões de cookies nas configurações do seu navegador.
                    </p>

                    <h4 class="mt-4 mb-3">5. Segurança</h4>
                    <p class="text-muted">
                        Empregamos medidas técnicas e organizacionais para proteger suas informações. Ainda assim, nenhum sistema é totalmente infalível.
                    </p>

                    <h4 class="mt-4 mb-3">6. Seus direitos</h4>
                    <p class="text-muted">
                        Você pode solicitar acesso, correção ou exclusão de seus dados, conforme aplicável.
                    </p>

                    <h4 class="mt-4 mb-3">7. Alterações nesta política</h4>
                    <p class="text-muted">
                        Podemos atualizar esta Política de Privacidade periodicamente. A versão vigente será sempre disponibilizada nesta página.
                    </p>

                    <h4 class="mt-4 mb-3">8. Contato</h4>
                    <p class="text-muted mb-0">
                        Em caso de dúvidas, entre em contato pela página de <a href="/contato">Contato</a>.
                    </p>
                </div>
            </div>
        </div>
    </div>
</div>
<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
