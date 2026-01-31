<?php
$title = 'Assessoria de Compras - Braziliana Shop';
require __DIR__ . '/../layouts/main.php';
?>

<div class="container-fluid py-4">
    <div class="row">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="h3 mb-0">Assessoria de Compras</h1>
                    <p class="text-muted mb-0">Adicione links de produtos e gere um orçamento personalizado</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <div class="col-lg-8 mx-auto">
            <div class="card shadow-sm border-0">
                <div class="card-body p-4">
                    <form id="assessoriaForm">
                        <div class="mb-4">
                            <label for="links" class="form-label fw-semibold">
                                <i class="fas fa-link me-2"></i>Links dos Produtos
                            </label>
                            <div class="mb-3">
                                <small class="text-muted">
                                    Adicione um ou mais links de produtos de lojas internacionais. 
                                    Cada link será processado individualmente.
                                </small>
                            </div>
                            <div id="linksContainer">
                                <div class="link-input-group mb-2">
                                    <div class="input-group">
                                        <span class="input-group-text">
                                            <i class="fas fa-globe"></i>
                                        </span>
                                        <input type="url" 
                                               class="form-control link-input" 
                                               placeholder="https://exemplo.com/produto" 
                                               required>
                                        <button type="button" class="btn btn-outline-danger remove-link" style="display: none;">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                    <div class="form-text">Ex: Amazon, eBay, Best Buy, etc.</div>
                                </div>
                            </div>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-between">
                            <button type="button" class="btn btn-outline-primary" id="addLinkBtn">
                                <i class="fas fa-plus me-2"></i>Adicionar outro link
                            </button>
                            <button type="submit" class="btn btn-primary btn-lg px-4" id="processBtn">
                                <i class="fas fa-magic me-2"></i>Gerar Orçamento
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Card de Informações -->
            <div class="card mt-4 border-0 bg-light">
                <div class="card-body">
                    <h5 class="card-title">
                        <i class="fas fa-info-circle text-primary me-2"></i>Como Funciona
                    </h5>
                    <div class="row">
                        <div class="col-md-6">
                            <ul class="list-unstyled">
                                <li class="mb-2">
                                    <i class="fas fa-check text-success me-2"></i>
                                    Adicione quantos links desejar
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check text-success me-2"></i>
                                    Processamos cada produto individualmente
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check text-success me-2"></i>
                                    Aplicamos taxas e impostos automáticos
                                </li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <ul class="list-unstyled">
                                <li class="mb-2">
                                    <i class="fas fa-check text-success me-2"></i>
                                    Orçamento detalhado com todos os custos
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check text-success me-2"></i>
                                    Escolha os produtos que deseja comprar
                                </li>
                                <li class="mb-2">
                                    <i class="fas fa-check text-success me-2"></i>
                                    Integração direta com o checkout
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div id="loadingOverlay" class="position-fixed top-0 start-0 w-100 h-100 d-none" 
     style="background: rgba(0,0,0,0.7); z-index: 9999;">
    <div class="d-flex flex-column justify-content-center align-items-center h-100">
        <div class="spinner-border text-primary mb-3" style="width: 3rem; height: 3rem;"></div>
        <h4 class="text-white mb-2">Preparando seu orçamento</h4>
        <p class="text-white-50">Processando os produtos... Isso pode levar alguns instantes.</p>
        <div class="progress w-50" style="height: 6px;">
            <div id="progressBar" class="progress-bar progress-bar-striped progress-bar-animated" 
                 role="progressbar" style="width: 0%"></div>
        </div>
    </div>
</div>

<!-- Container de Notificações -->
<div id="notificationContainer" class="position-fixed top-0 end-0 p-3" style="z-index: 9998;">
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
$(document).ready(function() {
    let linkCount = 1;

    const isLoggedIn = <?= json_encode((bool) ($assessoria_logged_in ?? false)) ?>;
    const disclaimerAccepted = <?= json_encode((bool) ($assessoria_disclaimer_accepted ?? false)) ?>;

    async function ensureLoggedIn() {
        if (isLoggedIn) return true;

        const result = await Swal.fire({
            icon: 'warning',
            title: 'Login obrigatório',
            text: 'Para acessar a Assessoria de Compras, você precisa realizar login.',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showCancelButton: true,
            confirmButtonText: 'Realizar login',
            cancelButtonText: 'Voltar',
            confirmButtonColor: '#0b1f3a'
        });

        if (result.isConfirmed) {
            window.location.href = '/login?redirect=' + encodeURIComponent('/assessoria');
            return false;
        }

        window.location.href = '/';
        return false;
    }

    async function ensureDisclaimerAccepted() {
        if (disclaimerAccepted) return true;

        const result = await Swal.fire({
            icon: 'info',
            title: 'Aviso importante - Assessoria',
            html: `
                <div class="text-start">
                    <p><strong>Este é um processo inovador</strong> e, por conta disso, pode apresentar alguma inconsistência.</p>
                    <p>Caso isso ocorra, a <strong>equipe de revisão</strong> entrará em contato para:</p>
                    <div>
                        - cobrança de diferenças que possam existir<br>
                        - e/ou devolução/estorno de valores
                    </div>
                    <hr>
                    <p><strong>Promoções e estoque</strong>: se valores promocionais e/ou estoques esgotarem, sua compra poderá ser <strong>estornada</strong> ou será necessário <strong>pagar a diferença</strong> para seguir com o pedido, até que o processamento seja concluído.</p>
                    <p><strong>Prazo</strong>: nosso prazo é de <strong>48h úteis</strong> para processamento e efetivação do pedido.</p>
                </div>
            `,
            allowOutsideClick: false,
            allowEscapeKey: false,
            showCancelButton: true,
            confirmButtonText: 'Li e aceito',
            cancelButtonText: 'Não aceito',
            confirmButtonColor: '#0b1f3a'
        });

        if (result.isConfirmed) {
            try {
                await $.ajax({
                    url: '/assessoria/aceitar-disclaimer',
                    method: 'POST'
                });
            } catch (e) {
            }
            return true;
        }

        window.location.href = '/';
        return false;
    }

    // Gate de acesso
    (async function() {
        const okLogin = await ensureLoggedIn();
        if (!okLogin) return;
        const okDisc = await ensureDisclaimerAccepted();
        if (!okDisc) return;
    })();

    // Adicionar novo campo de link
    $('#addLinkBtn').click(function() {
        linkCount++;
        const newLinkGroup = `
            <div class="link-input-group mb-2">
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="fas fa-globe"></i>
                    </span>
                    <input type="url" 
                           class="form-control link-input" 
                           placeholder="https://exemplo.com/produto" 
                           required>
                    <button type="button" class="btn btn-outline-danger remove-link">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="form-text">Ex: Amazon, eBay, Best Buy, etc.</div>
            </div>
        `;
        $('#linksContainer').append(newLinkGroup);
        updateRemoveButtons();
    });

    // Remover campo de link
    $(document).on('click', '.remove-link', function() {
        $(this).closest('.link-input-group').remove();
        linkCount--;
        updateRemoveButtons();
    });

    function updateRemoveButtons() {
        $('.remove-link').toggle(linkCount > 1);
    }

    // Processar formulário
    $('#assessoriaForm').submit(function(e) {
        e.preventDefault();

        if (!isLoggedIn) {
            ensureLoggedIn();
            return;
        }

        if (!disclaimerAccepted) {
            ensureDisclaimerAccepted();
            return;
        }
        
        const links = [];
        $('.link-input').each(function() {
            const url = $(this).val().trim();
            if (url) {
                links.push(url);
            }
        });

        if (links.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'Atenção',
                text: 'Adicione pelo menos um link de produto'
            });
            return;
        }

        // Validar URLs
        for (let link of links) {
            try {
                new URL(link);
            } catch (e) {
                Swal.fire({
                    icon: 'error',
                    title: 'URL Inválida',
                    text: `Verifique o link: ${link}`
                });
                return;
            }
        }

        processarLinks(links);
    });

    function processarLinks(links) {
        // Mostrar loading
        $('#loadingOverlay').removeClass('d-none');
        $('#processBtn').prop('disabled', true);
        
        // Limpar notificações anteriores
        $('#notificationContainer').empty();

        const totalLinks = links.length;
        $('#progressBar').css('width', '0%');

        const enqueue = function(links) {
            return new Promise(function(resolve, reject) {
                $.ajax({
                    url: '/assessoria/enfileirar',
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({ links: links }),
                    success: function(resp) {
                        resolve(resp);
                    },
                    error: function(xhr, status, error) {
                        reject({ xhr: xhr, status: status, error: error });
                    }
                });
            });
        };

        const fetchStatus = function(jobId) {
            return new Promise(function(resolve, reject) {
                $.ajax({
                    url: '/assessoria/status?job_id=' + encodeURIComponent(jobId),
                    method: 'GET',
                    success: function(resp) {
                        resolve(resp);
                    },
                    error: function(xhr, status, error) {
                        reject({ xhr: xhr, status: status, error: error });
                    }
                });
            });
        };

        const logDebugHeaders = function(xhr) {
            console.group('🔍 ScrapingBee/ChatGPT Debug Logs');
            var debugHeaders = [
                'X-ScrapingBee-Debug',
                'X-ScrapingBee-Data',
                'X-ScrapingBee-Error',
                'X-ScrapingBee-Request-URL',
                'X-ScrapingBee-HTTP-Code',
                'X-ScrapingBee-Response-Length',
                'X-ScrapingBee-Response-Prefix',
                'X-ScrapingBee-CURL-Error',
                'X-ScrapingBee-HTTP-Error',
                'X-ScrapingBee-Empty-Response',
                'X-ScrapingBee-JSON-Error',
                'X-ScrapingBee-Response-Raw',
                'X-ScrapingBee-JSON-Keys',
                'X-ScrapingBee-JSON-Type',
                'X-ScrapingBee-Normalization-Error',
                'X-ChatGPT-Debug',
                'X-ChatGPT-Error',
                'X-ChatGPT-Prompt-Length',
                'X-ChatGPT-HTTP-Code',
                'X-ChatGPT-Response-Length',
                'X-ChatGPT-CURL-Error',
                'X-ChatGPT-HTTP-Error',
                'X-ChatGPT-JSON-Error',
                'X-ChatGPT-Content-Length',
                'X-ChatGPT-Content-Prefix',
                'X-ChatGPT-Parse-Error',
                'X-ChatGPT-Raw-Content',
                'X-ChatGPT-Missing-Field',
                'X-ChatGPT-Success',
                'X-ChatGPT-Product-Data'
            ];

            debugHeaders.forEach(function(header) {
                var value = xhr.getResponseHeader(header);
                if (value) {
                    console.log('📋 ' + header + ':', value);
                    try {
                        if (header.includes('Data') || header.includes('Keys') || header.includes('Normalized') || header.includes('Product-Data')) {
                            var parsed = JSON.parse(value);
                            console.log('🔍 ' + header + ' (parsed):', parsed);
                        }
                    } catch (e) {
                    }
                }
            });

            console.groupEnd();
        };

        (async function() {
            try {
                const enqResp = await enqueue(links);
                if (!enqResp || enqResp.success !== true || !enqResp.data || !enqResp.data.job_id) {
                    const msg = enqResp && enqResp.message ? enqResp.message : 'Erro ao iniciar processamento';
                    throw new Error(msg);
                }

                const jobId = enqResp.data.job_id;
                window.location.href = '/assessoria/orcamento?job_id=' + encodeURIComponent(jobId);
            } catch (e) {
                $('#loadingOverlay').addClass('d-none');
                $('#processBtn').prop('disabled', false);
                handleErrorResponse(e && e.message ? e.message : 'Erro ao processar requisição.');
            }
        })();
    }

    function handleSuccessResponse(data) {
        // Mostrar notificações de erros se houver
        if (data.erros && data.erros.length > 0) {
            data.erros.forEach(erro => {
                showNotification(
                    'error',
                    `Erro ao processar produto`,
                    `Link: ${erro.link.substring(0, 50)}...<br>Mensagem: ${erro.error}`
                );
            });
        }

        // Mostrar sucesso
        Swal.fire({
            icon: data.total_erros > 0 ? 'warning' : 'success',
            title: 'Orçamento Gerado!',
            html: `
                <div class="text-start">
                    <p><strong>Produtos processados:</strong> ${data.total_produtos}</p>
                    <p><strong>Erros:</strong> ${data.total_erros}</p>
                    ${data.total_erros > 0 ? '<p class="text-warning">Alguns produtos não puderam ser processados. Verifique as notificações.</p>' : ''}
                    <p class="text-muted">Você será redirecionado para a página do orçamento.</p>
                </div>
            `,
            confirmButtonText: 'Ver Orçamento',
            confirmButtonColor: '#0b1f3a'
        }).then((result) => {
            if (result.isConfirmed) {
                window.location.href = '/assessoria/orcamento';
            }
        });
    }

    function handleErrorResponse(message) {
        // Mensagem amigável para timeout
        if (message.includes('demorou muito para responder') || message.includes('timeout')) {
            message = 'O servidor está demorando para responder. Isso pode acontecer com sites complexos. Tente novamente ou use um link mais simples.';
        }
        
        Swal.fire({
            icon: 'error',
            title: 'Erro no Processamento',
            text: message,
            confirmButtonColor: '#0b1f3a'
        });
    }

    function showNotification(type, title, message) {
        const alertClass = type === 'error' ? 'danger' : 'success';
        const icon = type === 'error' ? 'exclamation-triangle' : 'check-circle';
        
        const notification = `
            <div class="alert alert-${alertClass} alert-dismissible fade show" role="alert">
                <div class="d-flex align-items-start">
                    <i class="fas fa-${icon} me-2 mt-1"></i>
                    <div>
                        <strong>${title}</strong><br>
                        <small>${message}</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        $('#notificationContainer').append(notification);
        
        // Auto remover após 10 segundos
        setTimeout(() => {
            $('.alert').last().alert('close');
        }, 10000);
    }
});
</script>
