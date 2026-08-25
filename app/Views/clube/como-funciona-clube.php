<?php ob_start(); ?>
<?php
$clubeEnabled = !isset($clube_enabled) || $clube_enabled === true;
$clubeWhatsapp = $clube_whatsapp ?? '13053638204';
$clubeWhatsappLabel = $clube_whatsapp_label ?? '+1 305-363-8204';
$clubeWhatsappMsg = rawurlencode('Olá, gostaria de saber mais sobre o meu Clube Braziliana.');
?>
<div class="container py-5">
    <div class="row justify-content-center">
        <div class="col-lg-9">

            <!-- Hero -->
            <div class="text-center mb-5">
                <div class="mb-3"><i class="fas fa-crown fa-3x" style="color:#c8860a;"></i></div>
                <h1 class="fw-bold" style="color:#0b1f3a;">Clube Braziliana</h1>
                <p class="lead text-muted">Programa de Benefícios</p>
                <p class="text-muted mx-auto" style="max-width:620px;">O Clube Braziliana é um programa de benefícios baseado em créditos internos da plataforma, destinado a oferecer vantagens exclusivas aos membros em compras e serviços disponíveis no sistema. Agora com duas modalidades: Normal e Turbo.</p>

                <?php if ($clubeEnabled): ?>
                <div class="d-flex gap-2 justify-content-center mt-4">
                    <a href="/clube/recarga" class="btn btn-lg" style="background:#0b1f3a;color:#fff;"><i class="fas fa-wallet me-2"></i>Ativar meu Clube</a>
                    <a href="/grupos-compras" class="btn btn-outline-secondary btn-lg"><i class="fas fa-store me-2"></i>Ver Grupos</a>
                </div>
                <?php else: ?>
                <div class="alert alert-warning border-0 shadow-sm mx-auto mt-4 text-start" style="max-width:640px;border-radius:14px;">
                    <div class="d-flex align-items-start gap-3">
                        <i class="fas fa-pause-circle fa-2x" style="color:#b45309;"></i>
                        <div>
                            <h2 class="h5 mb-1" style="color:#b45309;">Novas recargas pausadas</h2>
                            <p class="mb-2 text-muted">No momento não estamos aceitando novas recargas do Clube Braziliana. Se você já é membro e deseja utilizar seus créditos, entre em contato com a nossa equipe pelo WhatsApp para mais detalhes.</p>
                            <a href="https://wa.me/<?= htmlspecialchars($clubeWhatsapp) ?>?text=<?= $clubeWhatsappMsg ?>" target="_blank" rel="noopener noreferrer" class="btn btn-success">
                                <i class="fab fa-whatsapp me-2"></i>Falar no WhatsApp
                            </a>
                            <div class="small text-muted mt-2"><i class="fas fa-phone-alt me-1"></i><?= htmlspecialchars($clubeWhatsappLabel) ?></div>
                        </div>
                    </div>
                </div>
                <div class="mt-3">
                    <a href="/grupos-compras" class="btn btn-outline-secondary"><i class="fas fa-store me-2"></i>Ver Grupos</a>
                </div>
                <?php endif; ?>
            </div>

            <!-- Destaques rápidos -->
            <div class="row g-3 mb-5">
                <div class="col-md-3 col-6">
                    <div class="card border-0 shadow-sm text-center p-3 h-100">
                        <div class="mb-2"><i class="fas fa-dollar-sign fa-2x" style="color:#0b1f3a;"></i></div>
                        <div class="fw-bold">US$ 39</div>
                        <div class="small text-muted">Depósito mínimo</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card border-0 shadow-sm text-center p-3 h-100">
                        <div class="mb-2"><i class="fas fa-bolt fa-2x" style="color:#b45309;"></i></div>
                        <div class="fw-bold">Turbo</div>
                        <div class="small text-muted">Rendimento especial</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card border-0 shadow-sm text-center p-3 h-100">
                        <div class="mb-2"><i class="fas fa-gift fa-2x" style="color:#0b1f3a;"></i></div>
                        <div class="fw-bold">Cashback</div>
                        <div class="small text-muted">Em créditos internos</div>
                    </div>
                </div>
                <div class="col-md-3 col-6">
                    <div class="card border-0 shadow-sm text-center p-3 h-100">
                        <div class="mb-2"><i class="fas fa-trophy fa-2x" style="color:#0b1f3a;"></i></div>
                        <div class="fw-bold">Sorteios</div>
                        <div class="small text-muted">Exclusivos para membros</div>
                    </div>
                </div>
            </div>


            <!-- Seção 1: Ativação -->
            <div class="card border-0 shadow-sm mb-3" style="border-radius:14px;">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="me-3"><span class="badge rounded-pill fs-6" style="background:#0b1f3a;">1</span></div>
                        <h2 class="h5 mb-0" style="color:#0b1f3a;">Ativação e Participação</h2>
                    </div>
                    <p>Para participar do Clube Braziliana, realize um depósito mínimo de <strong>US$ 39,00</strong> em créditos dentro da sua carteira interna na plataforma.</p>
                    <p>No momento da recarga, você pode escolher entre duas modalidades:</p>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <div class="border rounded p-3 h-100" style="background:rgba(16,185,129,0.06);border-color:rgba(16,185,129,0.18) !important;">
                                <div class="fw-bold mb-1" style="color:#065f46;"><i class="fas fa-check-circle me-1"></i> Clube Normal</div>
                                <ul class="small mb-0">
                                    <li>Rendimento padrão sobre o saldo</li>
                                    <li>Saldo disponível para uso imediato</li>
                                    <li>Sem prazo mínimo de permanência</li>
                                </ul>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded p-3 h-100" style="background:rgba(245,158,11,0.06);border-color:rgba(245,158,11,0.18) !important;">
                                <div class="fw-bold mb-1" style="color:#b45309;"><i class="fas fa-bolt me-1"></i> Clube Turbo</div>
                                <ul class="small mb-0">
                                    <li>Rendimento especial (maior que o Normal)</li>
                                    <li>Permanência mínima de 6 meses</li>
                                    <li>Saldo e rendimentos bloqueados durante o prazo</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    <p class="mb-0 small text-muted">Enquanto o saldo mínimo de US$ 39 estiver mantido na modalidade Normal, você terá acesso a todos os benefícios do Clube.</p>
                </div>
            </div>

            <!-- Seção 2: Saldo Mínimo -->
            <div class="card border-0 shadow-sm mb-3" style="border-radius:14px;">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="me-3"><span class="badge rounded-pill fs-6" style="background:#0b1f3a;">2</span></div>
                        <h2 class="h5 mb-0" style="color:#0b1f3a;">Saldo Mínimo e Manutenção dos Benefícios</h2>
                    </div>
                    <p>Para manter os benefícios do Clube Normal ativos, o saldo disponível da sua carteira precisa ser de pelo menos <strong>US$ 39,00</strong>.</p>
                    <p class="fw-semibold mb-2" style="color:#0b1f3a;">Se o saldo ficar abaixo desse valor após o uso da carteira:</p>
                    <ul>
                        <li>Você receberá um aviso e terá um prazo de <strong>48 horas</strong> para fazer uma nova recarga</li>
                        <li>Se recarregar dentro do prazo, os benefícios são reativados automaticamente</li>
                        <li>Caso contrário, os benefícios do Clube serão temporariamente desativados</li>
                    </ul>
                    <p class="mb-0 small text-muted">Essa regra se aplica ao saldo do Clube Normal. O saldo Turbo possui regras próprias de permanência.</p>
                </div>
            </div>

            <!-- Seção 3: Clube Turbo -->
            <div class="card border-0 shadow-sm mb-3" style="border-radius:14px;border-left:4px solid #f59e0b !important;">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="me-3"><span class="badge rounded-pill fs-6" style="background:#b45309;">3</span></div>
                        <h2 class="h5 mb-0" style="color:#0b1f3a;"><i class="fas fa-bolt me-1" style="color:#b45309;"></i> Clube Turbo — Como Funciona</h2>
                    </div>
                    <p>Ao fazer uma recarga, você pode ativar a opção <strong>Clube Turbo</strong> para obter um rendimento especial sobre aquele valor.</p>
                    <p class="fw-semibold mb-2" style="color:#0b1f3a;">Regras do Turbo:</p>
                    <ul>
                        <li>O saldo da recarga Turbo fica <strong>bloqueado por 6 meses</strong> a partir da data do pagamento</li>
                        <li>Os rendimentos gerados pelo saldo Turbo também ficam bloqueados pelo mesmo prazo</li>
                        <li>Durante o bloqueio, o saldo Turbo <strong>não pode ser utilizado</strong> no checkout</li>
                        <li>Após o término dos 6 meses, tanto o saldo quanto os rendimentos são liberados para uso</li>
                    </ul>
                    <p class="mb-0 small text-muted">O percentual de rendimento Turbo é configurado pela Braziliana e pode ser diferente do rendimento Normal. Você pode acompanhar o status de cada recarga Turbo na área "Minha Conta".</p>
                </div>
            </div>


            <!-- Seção 4: Limite -->
            <div class="card border-0 shadow-sm mb-3" style="border-radius:14px;">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="me-3"><span class="badge rounded-pill fs-6" style="background:#0b1f3a;">4</span></div>
                        <h2 class="h5 mb-0" style="color:#0b1f3a;">Limite Máximo do Clube</h2>
                    </div>
                    <p>O Clube possui um limite máximo de participação equivalente a <strong>R$ 150.000,00</strong> em créditos totais depositados pelos participantes.</p>
                    <p class="fw-semibold mb-2" style="color:#0b1f3a;">Quando esse limite for atingido:</p>
                    <ul>
                        <li>Novos depósitos poderão ser temporariamente suspensos</li>
                        <li>Novos participantes poderão ficar em lista de espera</li>
                        <li>Depósitos adicionais poderão não ser aceitos</li>
                    </ul>
                    <p class="mb-0">A liberação ocorre quando o saldo total voltar a ficar abaixo do limite. A Braziliana poderá alterar esse limite a qualquer momento.</p>
                </div>
            </div>

            <!-- Seção 5: Produtos Elegíveis -->
            <div class="card border-0 shadow-sm mb-3" style="border-radius:14px;">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="me-3"><span class="badge rounded-pill fs-6" style="background:#0b1f3a;">5</span></div>
                        <h2 class="h5 mb-0" style="color:#0b1f3a;">Produtos Elegíveis</h2>
                    </div>
                    <p class="mb-0">Os benefícios do Clube são aplicáveis exclusivamente a produtos ou serviços identificados na plataforma como <strong>"Clube Ativo"</strong>. Produtos que não possuam essa identificação não participam das vantagens do programa.</p>
                </div>
            </div>

            <!-- Seção 6: Benefícios -->
            <div class="card border-0 shadow-sm mb-3" style="border-radius:14px;">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="me-3"><span class="badge rounded-pill fs-6" style="background:#0b1f3a;">6</span></div>
                        <h2 class="h5 mb-0" style="color:#0b1f3a;">Benefícios do Clube</h2>
                    </div>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <div class="d-flex align-items-start">
                                <i class="fas fa-tags text-success me-2 mt-1"></i>
                                <div><strong>Descontos</strong><br><span class="small text-muted">Em produtos e serviços elegíveis</span></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex align-items-start">
                                <i class="fas fa-undo text-primary me-2 mt-1"></i>
                                <div><strong>Cashback</strong><br><span class="small text-muted">Em créditos internos, direto na carteira</span></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex align-items-start">
                                <i class="fas fa-coins me-2 mt-1" style="color:#c8860a;"></i>
                                <div><strong>Rendimentos</strong><br><span class="small text-muted">Normal: rendimento padrão · Turbo: rendimento especial</span></div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="d-flex align-items-start">
                                <i class="fas fa-trophy text-warning me-2 mt-1"></i>
                                <div><strong>Sorteios exclusivos</strong><br><span class="small text-muted">Gratificações para membros</span></div>
                            </div>
                        </div>
                    </div>
                    <p class="mb-0 small text-muted">Os benefícios podem variar de acordo com campanhas promocionais e regras operacionais do sistema.</p>
                </div>
            </div>

            <!-- Seção 7: Rendimentos -->
            <div class="card border-0 shadow-sm mb-3" style="border-radius:14px;">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="me-3"><span class="badge rounded-pill fs-6" style="background:#0b1f3a;">7</span></div>
                        <h2 class="h5 mb-0" style="color:#0b1f3a;">Rendimentos e Créditos Adicionais</h2>
                    </div>
                    <p>Enquanto o saldo mínimo estiver ativo, o sistema gera créditos adicionais periodicamente, calculados sobre o saldo elegível. O percentual varia conforme a modalidade:</p>
                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <div class="border rounded p-3" style="background:rgba(16,185,129,0.06);border-color:rgba(16,185,129,0.18) !important;">
                                <div class="fw-bold" style="color:#065f46;">Rendimento Normal</div>
                                <div class="small">Aplicado sobre o saldo do Clube Normal</div>
                                <div class="small">Disponível para uso imediato</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="border rounded p-3" style="background:rgba(245,158,11,0.06);border-color:rgba(245,158,11,0.18) !important;">
                                <div class="fw-bold" style="color:#b45309;">Rendimento Turbo</div>
                                <div class="small">Aplicado sobre o saldo do Clube Turbo</div>
                                <div class="small">Bloqueado junto com o saldo até o fim da permanência</div>
                            </div>
                        </div>
                    </div>
                    <p class="fw-semibold mb-2" style="color:#0b1f3a;">Esses créditos:</p>
                    <ul>
                        <li>São gerados exclusivamente dentro do sistema</li>
                        <li>Possuem natureza promocional</li>
                        <li>Não são transferíveis para fora da plataforma</li>
                        <li>Não são saqueáveis</li>
                        <li>Podem ser utilizados para pagamentos de serviços ou produtos na plataforma</li>
                        <li>Rendimentos do Turbo seguem a mesma trava de permanência do saldo principal</li>
                    </ul>
                    <p class="mb-0 small text-muted">A Braziliana poderá alterar, reduzir, suspender ou encerrar a geração desses créditos a qualquer momento.</p>
                </div>
            </div>


            <!-- Seção 8: Uso no Checkout -->
            <div class="card border-0 shadow-sm mb-3" style="border-radius:14px;">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="me-3"><span class="badge rounded-pill fs-6" style="background:#0b1f3a;">8</span></div>
                        <h2 class="h5 mb-0" style="color:#0b1f3a;">Uso do Saldo no Checkout</h2>
                    </div>
                    <p>No checkout, ao selecionar a forma de pagamento <strong>Crédito em Carteira</strong>, o sistema considera apenas o saldo efetivamente disponível.</p>
                    <p class="fw-semibold mb-2" style="color:#0b1f3a;">Pode ser usado no checkout:</p>
                    <ul>
                        <li>Saldo do Clube Normal</li>
                        <li>Rendimentos gerados pelo Clube Normal</li>
                        <li>Saldo do Clube Turbo cujo prazo de permanência já terminou</li>
                        <li>Rendimentos do Clube Turbo cujo prazo já terminou</li>
                    </ul>
                    <p class="fw-semibold mb-2" style="color:#b42318;">Não pode ser usado no checkout:</p>
                    <ul>
                        <li>Saldo do Clube Turbo ainda em permanência mínima</li>
                        <li>Rendimentos gerados pelo Clube Turbo ainda bloqueados</li>
                    </ul>
                    <p class="mb-0 small text-muted">O checkout exibe de forma clara o saldo disponível e o saldo Turbo bloqueado, para que você saiba exatamente quanto pode utilizar.</p>
                </div>
            </div>

            <!-- Seção 9: Sorteios -->
            <div class="card border-0 shadow-sm mb-3" style="border-radius:14px;">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="me-3"><span class="badge rounded-pill fs-6" style="background:#0b1f3a;">9</span></div>
                        <h2 class="h5 mb-0" style="color:#0b1f3a;">Sorteios e Gratificações</h2>
                    </div>
                    <p>Os membros do Clube poderão participar de sorteios promocionais, campanhas de gratificação e benefícios exclusivos.</p>
                    <p class="mb-0">Essas ações são eventuais e opcionais, podendo ocorrer em datas específicas ou campanhas internas. As regras de cada sorteio serão divulgadas pela plataforma.</p>
                </div>
            </div>

            <!-- Aviso importante -->
            <div class="p-4 mb-3" style="background:rgba(11,31,58,0.04);border:1px solid rgba(11,31,58,0.12);border-radius:14px;">
                <div class="d-flex align-items-start">
                    <i class="fas fa-exclamation-circle me-3 mt-1" style="color:#0b1f3a;font-size:1.3rem;"></i>
                    <div>
                        <div class="fw-bold mb-2" style="color:#0b1f3a;">Importante: Natureza dos Créditos</div>
                        <div class="row g-2">
                            <div class="col-md-6"><i class="fas fa-times text-danger me-1"></i> A Braziliana <strong>não é</strong> instituição financeira</div>
                            <div class="col-md-6"><i class="fas fa-times text-danger me-1"></i> O Clube <strong>não constitui</strong> investimento</div>
                            <div class="col-md-6"><i class="fas fa-times text-danger me-1"></i> Os créditos <strong>não representam</strong> dinheiro</div>
                            <div class="col-md-6"><i class="fas fa-times text-danger me-1"></i> Os créditos <strong>não são</strong> saqueáveis</div>
                            <div class="col-md-6"><i class="fas fa-times text-danger me-1"></i> <strong>Sem garantia</strong> de rendimento</div>
                            <div class="col-md-6"><i class="fas fa-times text-danger me-1"></i> <strong>Sem conversão</strong> em moeda fiduciária</div>
                        </div>
                        <p class="mt-2 mb-0 small text-muted">Todos os valores do Clube são créditos internos utilizados exclusivamente dentro do ecossistema da plataforma.</p>
                    </div>
                </div>
            </div>

            <!-- Seção 10: Auditoria -->
            <div class="card border-0 shadow-sm mb-4" style="border-radius:14px;">
                <div class="card-body p-4">
                    <div class="d-flex align-items-center mb-3">
                        <div class="me-3"><span class="badge rounded-pill fs-6" style="background:#0b1f3a;">10</span></div>
                        <h2 class="h5 mb-0" style="color:#0b1f3a;">Auditoria e Alterações</h2>
                    </div>
                    <p class="mb-0">Os créditos e benefícios podem ser auditados automaticamente pelo sistema a qualquer momento. A Braziliana se reserva o direito de alterar regras, modificar benefícios, ajustar percentuais, suspender funcionalidades ou encerrar o programa sempre que necessário.</p>
                </div>
            </div>

            <!-- CTA final -->
            <?php if ($clubeEnabled): ?>
            <div class="text-center py-4">
                <h3 class="fw-bold mb-3" style="color:#0b1f3a;">Pronto para participar?</h3>
                <p class="text-muted mb-4">Ative seu Clube Braziliana com um depósito mínimo de US$ 39,00. Escolha entre Normal ou Turbo e comece a aproveitar os benefícios.</p>
                <div class="d-flex gap-2 justify-content-center">
                    <a href="/clube/recarga" class="btn btn-lg" style="background:#0b1f3a;color:#fff;"><i class="fas fa-crown me-2"></i>Ativar meu Clube</a>
                    <a href="/produtos" class="btn btn-outline-primary btn-lg"><i class="fas fa-shopping-bag me-2"></i>Ver Produtos</a>
                </div>
            </div>
            <?php else: ?>
            <div class="text-center py-4">
                <h3 class="fw-bold mb-3" style="color:#0b1f3a;">Novas recargas pausadas</h3>
                <p class="text-muted mb-4">No momento não estamos aceitando novas recargas. Já é membro? Fale com a nossa equipe pelo WhatsApp para utilizar seus créditos.</p>
                <div class="d-flex gap-2 justify-content-center">
                    <a href="https://wa.me/<?= htmlspecialchars($clubeWhatsapp) ?>?text=<?= $clubeWhatsappMsg ?>" target="_blank" rel="noopener noreferrer" class="btn btn-lg btn-success"><i class="fab fa-whatsapp me-2"></i>Falar no WhatsApp</a>
                    <a href="/produtos" class="btn btn-outline-primary btn-lg"><i class="fas fa-shopping-bag me-2"></i>Ver Produtos</a>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>
<?php $content = ob_get_clean(); ?>
<?php include __DIR__ . '/../layouts/main.php'; ?>
