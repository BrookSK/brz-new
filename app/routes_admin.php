<?php
// Rotas do Painel Administrativo

// Dashboard
$router->get('/admin/dashboard', 'AdminDashboardController', 'index');

// Produtos
$router->get('/admin/produtos', 'AdminProdutosController', 'index');
$router->get('/admin/produtos/arquivados', 'AdminProdutosController', 'arquivados');
$router->get('/admin/produtos/novo', 'AdminProdutosController', 'novo');
$router->get('/admin/produtos/cadastro-rapido', 'AdminProdutosController', 'cadastroRapido');
$router->post('/admin/produtos/cadastro-rapido', 'AdminProdutosController', 'cadastroRapido');
$router->post('/admin/produtos/cadastro-rapido/salvar', 'AdminProdutosController', 'cadastroRapidoSalvar');
$router->post('/admin/produtos/salvar', 'AdminProdutosController', 'salvar');
$router->get('/admin/produtos/editar/{id}', 'AdminProdutosController', 'editar');
$router->post('/admin/produtos/atualizar/{id}', 'AdminProdutosController', 'atualizar');
$router->post('/admin/produtos/remover-capa/{id}', 'AdminProdutosController', 'removerCapa');
$router->post('/admin/produtos/remover-foto/{id}', 'AdminProdutosController', 'removerFoto');
$router->post('/admin/produtos/galeria/ordem/{id}', 'AdminProdutosController', 'salvarOrdemGaleria');
$router->post('/admin/produtos/excluir/{id}', 'AdminProdutosController', 'excluir');

// NCM (atalhos AJAX)
$router->post('/admin/produtos/ncm/search', 'AdminProdutosController', 'ncmSearch');
$router->post('/admin/produtos/ncm/atualizar/{id}', 'AdminProdutosController', 'ncmAtualizar');

// Importação de produtos (CSV)
$router->get('/admin/produtos/importar/modelo', 'AdminProdutosController', 'importarProdutosModelo');
$router->post('/admin/produtos/importar/iniciar', 'AdminProdutosController', 'importarProdutosIniciar');
$router->post('/admin/produtos/importar/processar', 'AdminProdutosController', 'importarProdutosProcessar');

// Grupos de Compras
$router->get('/admin/grupos-compras', 'AdminGruposComprasController', 'index');
$router->post('/admin/grupos-compras/salvar', 'AdminGruposComprasController', 'salvar');
$router->post('/admin/grupos-compras/toggle-ativo', 'AdminGruposComprasController', 'toggleAtivo');
$router->post('/admin/grupos-compras/excluir/{id}', 'AdminGruposComprasController', 'excluir');
$router->get('/admin/grupos-compras/api/lista', 'AdminGruposComprasController', 'apiLista');
$router->get('/admin/grupos-compras/api/produtos', 'AdminGruposComprasController', 'apiProdutos');
$router->post('/admin/grupos-compras/api/remover-produto', 'AdminGruposComprasController', 'apiRemoverProduto');

// Pedidos
$router->get('/admin/pedidos', 'AdminPedidosController', 'index');
$router->get('/admin/pedidos/export-xlsx', 'AdminPedidosController', 'exportXlsx');
$router->get('/admin/pedidos/detalhes/{id}', 'AdminPedidosController', 'detalhes');
$router->get('/admin/pedidos/editar/{id}', 'AdminPedidosEditController', 'editar');
$router->post('/admin/pedidos/salvar', 'AdminPedidosEditController', 'salvar');
$router->get('/admin/pedidos/atualizar-status/{id}/{status}', 'AdminPedidosController', 'atualizarStatus');
$router->post('/admin/pedidos/sincronizar-pagamentos/{id}', 'AdminPedidosController', 'sincronizarPagamentos');
$router->post('/admin/pedidos/gerar-novo-pix/{id}', 'AdminPedidosController', 'gerarNovoPixSplit');
$router->post('/admin/pedidos/atualizar-cliente/{id}', 'AdminPedidosController', 'atualizarCliente');

// Importação de pedidos (CSV)
$router->get('/admin/pedidos/importar/modelo', 'AdminPedidosController', 'importarPedidosModelo');
$router->post('/admin/pedidos/importar/iniciar', 'AdminPedidosController', 'importarPedidosIniciar');
$router->post('/admin/pedidos/importar/processar', 'AdminPedidosController', 'importarPedidosProcessar');

// Pedidos do WordPress (somente leitura)
$router->get('/admin/pedidos-wp', 'AdminPedidosWpController', 'index');
$router->get('/admin/pedidos-wp/detalhes/{id}', 'AdminPedidosWpController', 'detalhes');
$router->get('/admin/pedidos-wp/export', 'AdminPedidosWpController', 'exportCsv');
$router->get('/admin/pedidos-wp/export-xlsx', 'AdminPedidosWpController', 'exportXlsx');
 $router->post('/admin/pedidos-wp/wexpress/gerar/{id}', 'AdminPedidosWpController', 'gerarEtiquetaWexpress');

// Usuários
$router->get('/admin/usuarios', 'AdminUsuariosController', 'index');
$router->get('/admin/usuarios/detalhes/{id}', 'AdminUsuariosController', 'detalhes');
$router->get('/admin/usuarios/editar/{id}', 'AdminUsuariosController', 'editar');
$router->post('/admin/usuarios/atualizar/{id}', 'AdminUsuariosController', 'atualizar');
$router->post('/admin/usuarios/excluir/{id}', 'AdminUsuariosController', 'excluir');
$router->post('/admin/usuarios/atualizar-status/{id}', 'AdminUsuariosController', 'atualizarStatus');
$router->post('/admin/usuarios/impersonar/{id}', 'AdminUsuariosController', 'impersonar');

// Rotas de Relatórios
$router->get('/admin/estoque/relatorios', 'AdminRelatoriosController', 'index');
$router->get('/admin/estoque/relatorios/financeiro', 'AdminRelatoriosController', 'financeiro');
$router->get('/admin/estoque/relatorios/financeiro/export', 'AdminRelatoriosController', 'exportFinanceiro');
$router->get('/admin/estoque/relatorios/movimentacao', 'AdminRelatoriosController', 'movimentacao');
$router->get('/admin/estoque/relatorios/auditoria', 'AdminRelatoriosController', 'auditoriaLogs');
$router->get('/admin/estoque/relatorio-pdf', 'AdminRelatoriosController', 'gerarPDF');

// Pagamentos
$router->get('/admin/pagamentos', 'AdminPagamentosController', 'index');
$router->post('/admin/pagamentos/confirmar/{id}', 'AdminPagamentosController', 'confirmarPagamento');
$router->post('/admin/pagamentos/refresh/{id}', 'AdminPagamentosController', 'refreshPagamento');
$router->post('/admin/pagamentos/cancelar/{id}', 'AdminPagamentosController', 'cancelarPagamento');
$router->post('/admin/pagamentos/estornar/{id}', 'AdminPagamentosController', 'estornarPagamento');
$router->post('/admin/pagamentos/cancelar-pedido/{id}', 'AdminPagamentosController', 'cancelarPedido');
$router->post('/admin/pagamentos/salvar-configuracoes', 'AdminPagamentosController', 'salvarConfiguracoes');

// Clube - Recargas (checkout rápido)
$router->get('/admin/clube/recargas', 'AdminClubeRecargasController', 'index');

// Correios Mundial (PACKET)
$router->get('/admin/correios-mundial', 'AdminCorreiosMundialController', 'index');
$router->get('/admin/correios-mundial/balance', 'AdminCorreiosMundialController', 'balance');
$router->get('/admin/correios-mundial/pedido/{id}', 'AdminCorreiosMundialController', 'pedido');
$router->post('/admin/correios-mundial/pedido/{id}/gerar-etiqueta', 'AdminCorreiosMundialController', 'gerarEtiqueta');

// Containers / Unitizadores
$router->get('/admin/correios-mundial/containers', 'AdminCorreiosMundialController', 'containers');
$router->get('/admin/correios-mundial/containers/novo', 'AdminCorreiosMundialController', 'containerNovo');
$router->post('/admin/correios-mundial/containers/criar', 'AdminCorreiosMundialController', 'containerCriar');
$router->post('/admin/correios-mundial/container/{id}/cancelar', 'AdminCorreiosMundialController', 'containerCancelar');
$router->post('/admin/correios-mundial/container/{id}/deletar', 'AdminCorreiosMundialController', 'containerDeletar');
$router->get('/admin/correios-mundial/container/{id}.pdf', 'AdminCorreiosMundialController', 'containerPdf');

// Faturas (CN38)
$router->get('/admin/correios-mundial/faturas', 'AdminCorreiosMundialController', 'faturas');
$router->get('/admin/correios-mundial/faturas/nova', 'AdminCorreiosMundialController', 'faturaNova');
$router->post('/admin/correios-mundial/faturas/criar', 'AdminCorreiosMundialController', 'faturaCriar');
$router->get('/admin/correios-mundial/fatura/{id}.pdf', 'AdminCorreiosMundialController', 'faturaPdf');

// Download PDF da etiqueta (gerada localmente)
$router->get('/admin/correios-mundial/etiqueta/{tracking}.pdf', 'AdminCorreiosMundialController', 'etiquetaPdf');

// Comissões gerais
$router->get('/admin/pagamentos/comissoes-gerais', 'AdminPagamentosController', 'comissoesGerais');
$router->post('/admin/pagamentos/comissoes-gerais/ajuste', 'AdminPagamentosController', 'criarAjusteComissaoGeral');
$router->post('/admin/pagamentos/comissoes-gerais/pagamento', 'AdminPagamentosController', 'criarPagamentoComissaoGeral');
$router->post('/admin/pagamentos/comissoes-gerais/aprovar/{id}', 'AdminPagamentosController', 'aprovarPagamentoComissaoGeral');
$router->post('/admin/pagamentos/comissoes-gerais/deletar/{id}', 'AdminPagamentosController', 'deletarPagamentoComissaoGeral');

// Configurações
$router->get('/admin/configuracoes', 'AdminConfiguracoesController', 'index');
$router->post('/admin/configuracoes/salvar', 'AdminConfiguracoesController', 'salvar');

// Importação de usuários (CSV)
$router->get('/admin/configuracoes/importar-usuarios/modelo', 'AdminConfiguracoesController', 'importarUsuariosModelo');
$router->post('/admin/configuracoes/importar-usuarios/iniciar', 'AdminConfiguracoesController', 'importarUsuariosIniciar');
$router->post('/admin/configuracoes/importar-usuarios/processar', 'AdminConfiguracoesController', 'importarUsuariosProcessar');

// Mapa de calor (segmentação)
$router->get('/admin/configuracoes/mapa-calor/clientes', 'AdminConfiguracoesController', 'mapaCalorClientes');
$router->get('/admin/configuracoes/mapa-calor/export-emails', 'AdminConfiguracoesController', 'mapaCalorExportEmails');

// Página principal do admin
$router->get('/admin', function() {
    header('Location: /admin/dashboard');
    exit;
});

// Redirecionamento
$router->get('/admin/redirecionamento/dashboard',                       'AdminRedirecionamentoController', 'dashboard');
$router->get('/admin/redirecionamento/redirecionadores',                'AdminRedirecionamentoController', 'redirecionadores');
$router->get('/admin/redirecionamento/redirecionadores/novo',           'AdminRedirecionamentoController', 'redirecionadorNovo');
$router->post('/admin/redirecionamento/redirecionadores/salvar',        'AdminRedirecionamentoController', 'redirecionadorSalvar');
$router->get('/admin/redirecionamento/redirecionadores/editar/{id}',    'AdminRedirecionamentoController', 'redirecionadorEditar');
$router->post('/admin/redirecionamento/redirecionadores/atualizar/{id}','AdminRedirecionamentoController', 'redirecionadorAtualizar');
$router->get('/admin/redirecionamento/envios',                          'AdminRedirecionamentoController', 'envios');
$router->get('/admin/redirecionamento/envios/novo',                     'AdminRedirecionamentoController', 'envioNovo');
$router->post('/admin/redirecionamento/envios/salvar',                  'AdminRedirecionamentoController', 'envioSalvar');
$router->get('/admin/redirecionamento/envios/{id}',                     'AdminRedirecionamentoController', 'envioDetalhe');
$router->post('/admin/redirecionamento/envios/{id}/peso-real',          'AdminRedirecionamentoController', 'envioAtualizarPeso');
$router->post('/admin/redirecionamento/envios/{id}/tracking',           'AdminRedirecionamentoController', 'envioSalvarTracking');
$router->post('/admin/redirecionamento/envios/{id}/coletado',           'AdminRedirecionamentoController', 'envioMarcarColetado');
$router->post('/admin/redirecionamento/envios/{id}/entregue',           'AdminRedirecionamentoController', 'envioMarcarEntregue');
$router->get('/admin/redirecionamento/divergencias',                    'AdminRedirecionamentoController', 'divergencias');
$router->post('/admin/redirecionamento/divergencias/gerar-link',        'AdminRedirecionamentoController', 'divergenciaGerarLink');
$router->post('/admin/redirecionamento/divergencias/marcar-pago',       'AdminRedirecionamentoController', 'divergenciaMarcarPago');
$router->get('/admin/redirecionamento/clientes',                        'AdminRedirecionamentoController', 'clientes');
$router->post('/admin/redirecionamento/clientes/salvar',                'AdminRedirecionamentoController', 'clienteSalvar');
$router->get('/admin/redirecionamento/clientes/get',                    'AdminRedirecionamentoController', 'clienteGet');
$router->get('/admin/redirecionamento/clientes/lista',                  'AdminRedirecionamentoController', 'clientesLista');
$router->get('/admin/redirecionamento/tabela-pesos',                    'AdminRedirecionamentoController', 'tabelaPesos');
$router->post('/admin/redirecionamento/tabela-pesos/salvar',            'AdminRedirecionamentoController', 'tabelaPesosSalvar');
$router->post('/admin/redirecionamento/tabela-pesos/excluir',           'AdminRedirecionamentoController', 'tabelaPesosExcluir');
$router->get('/admin/redirecionamento/tabela-pesos/calcular',           'AdminRedirecionamentoController', 'calcularSimulador');
$router->get('/admin/redirecionamento/pagamentos',                      'AdminRedirecionamentoController', 'pagamentos');
$router->get('/admin/redirecionamento/comprovantes',                    'AdminRedirecionamentoController', 'comprovantes');
$router->post('/admin/redirecionamento/comprovantes/upload',            'AdminRedirecionamentoController', 'uploadComprovante');
$router->get('/admin/redirecionamento/coletas',                         'AdminRedirecionamentoController', 'coletas');
$router->post('/admin/redirecionamento/coletas/agendar',                'AdminRedirecionamentoController', 'coletaAgendar');
$router->post('/admin/redirecionamento/coletas/confirmar',              'AdminRedirecionamentoController', 'coletaConfirmar');
$router->post('/admin/redirecionamento/coletas/coletado',               'AdminRedirecionamentoController', 'coletaMarcarColetado');
$router->post('/admin/redirecionamento/coletas/reagendar',              'AdminRedirecionamentoController', 'coletaReagendar');
$router->post('/admin/redirecionamento/pagamento/criar-intent',         'AdminRedirecionamentoController', 'criarIntentPagamento');
$router->post('/admin/redirecionamento/pagamento/confirmar',            'AdminRedirecionamentoController', 'confirmarPagamento');

// Webhooks
$router->post('/webhook/cambioreal', 'WebhookController', 'cambioreal');
