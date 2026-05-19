<?php
// Rotas do Painel Administrativo

// Dashboard
$router->get('/admin/dashboard', 'AdminDashboardController', 'index');

// Marketing Calendar
$router->get('/admin/marketing-calendar/dates', 'AdminMarketingCalendarController', 'getDates');

// Produtos
$router->get('/admin/produtos', 'AdminProdutosController', 'index');
$router->get('/admin/produtos/arquivados', 'AdminProdutosController', 'arquivados');
$router->get('/admin/produtos/novo', 'AdminProdutosController', 'novo');
$router->get('/admin/produtos/cadastro-rapido', 'AdminProdutosController', 'cadastroRapido');
$router->post('/admin/produtos/cadastro-rapido', 'AdminProdutosController', 'cadastroRapido');
$router->post('/admin/produtos/cadastro-rapido/salvar', 'AdminProdutosController', 'cadastroRapidoSalvar');
$router->post('/admin/produtos/cadastro-rapido/salvar-lote', 'AdminProdutosController', 'cadastroRapidoSalvarLote');
$router->post('/admin/produtos/cadastro-rapido/transcrever-audio', 'AdminProdutosController', 'transcreverAudio');
$router->post('/admin/produtos/cadastro-rapido/enriquecer-ia', 'AdminProdutosController', 'enriquecerComIA');
$router->get('/admin/produtos/cadastro-rapido/categorias', 'AdminProdutosController', 'listarCategorias');
$router->post('/admin/produtos/cadastro-rapido/categorias/criar', 'AdminProdutosController', 'criarCategoria');
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

// Custo (atalho AJAX)
$router->post('/admin/produtos/custo/atualizar/{id}', 'AdminProdutosController', 'custoAtualizar');

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
$router->post('/admin/grupos-compras/api/excluir-produto', 'AdminGruposComprasController', 'excluirProduto');
$router->get('/admin/grupos-compras/snapshots/{id}', 'AdminGruposComprasController', 'listarSnapshots');

// Pedidos
$router->get('/admin/pedidos', 'AdminPedidosController', 'index');
$router->get('/admin/pedidos/export-xlsx', 'AdminPedidosController', 'exportXlsx');
$router->get('/admin/pedidos/detalhes/{id}', 'AdminPedidosController', 'detalhes');
$router->get('/admin/pedidos/editar/{id}', 'AdminPedidosEditController', 'editar');
$router->post('/admin/pedidos/salvar', 'AdminPedidosEditController', 'salvar');
$router->post('/admin/pedidos/gerar-link-diferenca', 'AdminPedidosEditController', 'gerarLinkDiferenca');
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
$router->get('/admin/pedidos-wp/estatisticas', 'AdminPedidosWpController', 'estatisticas');
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
$router->post('/admin/correios-mundial/pedido/{id}/regerar-etiqueta', 'AdminCorreiosMundialController', 'regerarEtiqueta');

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

// Visão global de comissões e resumo financeiro (admin only, sem link na sidebar)
$router->get('/admin/comissoes-global', 'AdminComissoesGlobalController', 'comissoesTodas');
$router->get('/admin/resumo-financeiro', 'AdminComissoesGlobalController', 'resumoFinanceiro');

// Despesas
$router->get('/admin/despesas', 'AdminDespesasController', 'index');
$router->post('/admin/despesas/criar', 'AdminDespesasController', 'criar');
$router->post('/admin/despesas/pagar/{id}', 'AdminDespesasController', 'marcarPaga');
$router->post('/admin/despesas/cancelar/{id}', 'AdminDespesasController', 'cancelar');
$router->post('/admin/despesas/excluir/{id}', 'AdminDespesasController', 'excluir');

// DRE Completo (API JSON)
$router->get('/admin/dre-completo/dados', 'AdminDreCompletoController', 'dados');
$router->get('/admin/dre-completo/exportar', 'AdminDreCompletoController', 'exportar');
$router->get('/admin/dre-completo/conciliacao', 'AdminDreCompletoController', 'conciliacao');
$router->get('/admin/dre-completo/cron-conciliacao', 'AdminDreCompletoController', 'cronConciliacao');

// Demandas
$router->get('/admin/demandas/painel', 'AdminDemandasController', 'painel');
$router->post('/admin/demandas/painel', 'AdminDemandasController', 'painel');
$router->get('/admin/demandas/minhas', 'AdminDemandasController', 'minhasSolicitacoes');
$router->get('/admin/demandas/minha/{id}', 'AdminDemandasController', 'minhaDetalhe');
$router->post('/admin/demandas/minha/{id}/mensagem', 'AdminDemandasController', 'enviarMensagemSolicitante');
$router->get('/admin/demandas/nova', 'AdminDemandasController', 'nova');
$router->get('/admin/demandas/concluidos', 'AdminDemandasController', 'concluidos');
$router->get('/admin/demandas/detalhe/{id}', 'AdminDemandasController', 'detalhe');
$router->post('/admin/demandas/criar', 'AdminDemandasController', 'criar');
$router->post('/admin/demandas/mover/{id}', 'AdminDemandasController', 'moverStatus');
$router->get('/admin/demandas/pdf/{id}', 'AdminDemandasController', 'pdf');
$router->post('/admin/demandas/{id}/mensagem', 'AdminDemandasController', 'enviarMensagem');
$router->get('/admin/demandas/api/notificacoes', 'AdminDemandasController', 'notificacoes');
$router->post('/admin/demandas/api/notificacao/{id}/lida', 'AdminDemandasController', 'marcarLida');
$router->get('/admin/demandas/configuracoes', 'AdminDemandasController', 'configuracoes');
$router->post('/admin/demandas/configuracoes', 'AdminDemandasController', 'salvarConfiguracoes');

// Oferta Gratuita
$router->get('/admin/oferta-gratuita', 'AdminOfertaGratuitaController', 'index');
$router->post('/admin/oferta-gratuita/toggle-global', 'AdminOfertaGratuitaController', 'toggleGlobal');
$router->post('/admin/oferta-gratuita/sincronizar', 'AdminOfertaGratuitaController', 'sincronizar');
$router->post('/admin/oferta-gratuita/acao-massa', 'AdminOfertaGratuitaController', 'acaoMassa');
$router->post('/admin/oferta-gratuita/adicionar', 'AdminOfertaGratuitaController', 'adicionar');
$router->post('/admin/oferta-gratuita/remover', 'AdminOfertaGratuitaController', 'remover');
$router->get('/admin/oferta-gratuita/buscar-produtos', 'AdminOfertaGratuitaController', 'buscarProdutos');

// Brindes vinculados a produtos
$router->get('/admin/brindes', 'AdminBrindesController', 'index');
$router->get('/admin/produtos/brindes/listar', 'AdminProdutosController', 'brindesListar');
$router->post('/admin/produtos/brindes/salvar', 'AdminProdutosController', 'brindeSalvar');
$router->post('/admin/produtos/brindes/remover', 'AdminProdutosController', 'brindeRemover');
$router->get('/admin/produtos/busca-ajax', 'AdminProdutosController', 'buscaAjax');

// Configurações
$router->get('/admin/configuracoes', 'AdminConfiguracoesController', 'index');
$router->post('/admin/configuracoes/salvar', 'AdminConfiguracoesController', 'salvar');

// FAQ / Termos
$router->get('/admin/faq', 'AdminFaqController', 'index');
$router->post('/admin/faq/salvar', 'AdminFaqController', 'salvar');
$router->post('/admin/faq/toggle/{id}', 'AdminFaqController', 'toggle');
$router->post('/admin/faq/excluir/{id}', 'AdminFaqController', 'excluir');

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
$router->post('/admin/redirecionamento/envios/marcar-pago',             'AdminRedirecionamentoController', 'envioMarcarPago');
$router->post('/admin/redirecionamento/envios/{id}/entregue',           'AdminRedirecionamentoController', 'envioMarcarEntregue');
$router->get('/admin/redirecionamento/divergencias',                    'AdminRedirecionamentoController', 'divergencias');
$router->post('/admin/redirecionamento/divergencias/gerar-link',        'AdminRedirecionamentoController', 'divergenciaGerarLink');
$router->post('/admin/redirecionamento/divergencias/pagar',             'AdminRedirecionamentoController', 'divergenciaPagar');
$router->post('/admin/redirecionamento/divergencias/marcar-pago',       'AdminRedirecionamentoController', 'divergenciaMarcarPago');
$router->get('/admin/redirecionamento/clientes',                        'AdminRedirecionamentoController', 'clientes');
$router->post('/admin/redirecionamento/clientes/salvar',                'AdminRedirecionamentoController', 'clienteSalvar');
$router->post('/admin/redirecionamento/clientes/atualizar',             'AdminRedirecionamentoController', 'clienteAtualizar');
$router->post('/admin/redirecionamento/clientes/excluir',               'AdminRedirecionamentoController', 'clienteExcluir');
$router->get('/admin/redirecionamento/clientes/get',                    'AdminRedirecionamentoController', 'clienteGet');
$router->get('/admin/redirecionamento/clientes/lista',                  'AdminRedirecionamentoController', 'clientesLista');
$router->get('/admin/redirecionamento/tabela-pesos',                    'AdminRedirecionamentoController', 'tabelaPesos');
$router->post('/admin/redirecionamento/tabela-pesos/salvar',            'AdminRedirecionamentoController', 'tabelaPesosSalvar');
$router->post('/admin/redirecionamento/tabela-pesos/excluir',           'AdminRedirecionamentoController', 'tabelaPesosExcluir');
$router->post('/admin/redirecionamento/configuracao/salvar',            'AdminRedirecionamentoController', 'configuracaoSalvar');
$router->get('/admin/redirecionamento/tabela-pesos/calcular',           'AdminRedirecionamentoController', 'calcularSimulador');
$router->get('/admin/redirecionamento/pagamentos',                      'AdminRedirecionamentoController', 'pagamentos');
$router->get('/admin/redirecionamento/comprovantes',                    'AdminRedirecionamentoController', 'comprovantes');
$router->post('/admin/redirecionamento/comprovantes/upload',            'AdminRedirecionamentoController', 'uploadComprovante');
$router->get('/admin/redirecionamento/coletas',                         'AdminRedirecionamentoController', 'coletas');
$router->post('/admin/redirecionamento/coletas/agendar',                'AdminRedirecionamentoController', 'coletaAgendar');
$router->post('/admin/redirecionamento/coletas/confirmar',              'AdminRedirecionamentoController', 'coletaConfirmar');
$router->post('/admin/redirecionamento/coletas/cancelar',               'AdminRedirecionamentoController', 'coletaCancelar');
$router->post('/admin/redirecionamento/coletas/coletado',               'AdminRedirecionamentoController', 'coletaMarcarColetado');
$router->post('/admin/redirecionamento/coletas/reagendar',              'AdminRedirecionamentoController', 'coletaReagendar');
$router->post('/admin/redirecionamento/pagamento/criar-intent',         'AdminRedirecionamentoController', 'criarIntentPagamento');
$router->post('/admin/redirecionamento/pagamento/confirmar',            'AdminRedirecionamentoController', 'confirmarPagamento');
$router->post('/admin/redirecionamento/envios/gerar-etiqueta',          'AdminRedirecionamentoController', 'gerarEtiqueta');
$router->get('/admin/redirecionamento/envios/baixar-etiqueta',          'AdminRedirecionamentoController', 'baixarEtiqueta');
$router->get('/admin/redirecionamento/ajuda',                           'AdminRedirecionamentoController', 'ajuda');

// Migração de Produtos (exportar/importar entre servidores)
$router->get('/admin/migracao', 'AdminMigracaoController', 'index');
$router->get('/admin/migracao/exportar', 'AdminMigracaoController', 'exportar');
$router->get('/admin/migracao/exportar-imagens', 'AdminMigracaoController', 'exportarImagens');
$router->post('/admin/migracao/importar', 'AdminMigracaoController', 'importar');

// Webhooks
$router->post('/webhook/cambioreal', 'WebhookController', 'cambioreal');

// Co-Piloto Braziliana
$router->get('/admin/copiloto', 'AdminCopilotoController', 'index');
$router->post('/admin/copiloto/salvar', 'AdminCopilotoController', 'salvar');
$router->get('/admin/copiloto/analytics', 'AdminCopilotoController', 'analytics');
$router->get('/admin/copiloto/aprendizado', 'AdminCopilotoController', 'aprendizado');
$router->post('/admin/copiloto/aprendizado/aceitar/{id}', 'AdminCopilotoController', 'aceitarPendencia');
$router->post('/admin/copiloto/aprendizado/recusar/{id}', 'AdminCopilotoController', 'recusarPendencia');
$router->get('/admin/copiloto/conteudo', 'AdminCopilotoController', 'conteudo');
$router->post('/admin/copiloto/conteudo/upload', 'AdminCopilotoController', 'conteudoUpload');
$router->post('/admin/copiloto/conteudo/remover/{id}', 'AdminCopilotoController', 'conteudoRemover');
$router->post('/admin/copiloto/conteudo/toggle/{id}', 'AdminCopilotoController', 'conteudoToggle');
$router->get('/admin/copiloto/cancelamentos', 'AdminCopilotoController', 'cancelamentos');
$router->get('/admin/copiloto/conversas', 'AdminCopilotoController', 'conversas');
$router->get('/admin/copiloto/conversas/{sessaoId}', 'AdminCopilotoController', 'verConversa');
$router->post('/admin/copiloto/cancelamentos/autorizar/{id}', 'AdminCopilotoController', 'autorizarCancelamento');
$router->post('/admin/copiloto/cancelamentos/recusar/{id}', 'AdminCopilotoController', 'recusarCancelamento');
$router->get('/api/copiloto/config', 'AdminCopilotoController', 'apiConfig');
$router->get('/api/copiloto/status', 'AdminCopilotoController', 'apiStatus');
$router->post('/api/copiloto/log', 'AdminCopilotoController', 'apiLog');
$router->post('/api/copiloto/aprendizado', 'AdminCopilotoController', 'apiAprendizado');

// Co-Piloto API (endpoints chamados pelo widget JS — 100% PHP)
$router->post('/api/copiloto/chat', 'CopilotoApiController', 'chat');
$router->get('/api/copiloto/context', 'CopilotoApiController', 'context');
$router->post('/api/copiloto/calculo', 'CopilotoApiController', 'calculo');
$router->post('/api/copiloto/addcart', 'CopilotoApiController', 'carrinhoAdicionar');
$router->post('/api/copiloto/clearcart', 'CopilotoApiController', 'carrinhoLimpar');
$router->get('/api/copiloto/buscarproduto', 'CopilotoApiController', 'buscarProduto');
$router->get('/api/copiloto/meucarrinho', 'CopilotoApiController', 'meuCarrinho');
$router->get('/api/copiloto/meuspedidos', 'CopilotoApiController', 'meusPedidos');
$router->post('/api/copiloto/ticket', 'CopilotoApiController', 'ticket');
$router->post('/api/copiloto/orcamento', 'CopilotoApiController', 'orcamento');
$router->post('/api/copiloto/prepararcheckout', 'CopilotoApiController', 'prepararCheckout');
$router->post('/api/copiloto/atualizarperfil', 'CopilotoApiController', 'atualizarPerfil');
$router->get('/api/copiloto/cron', 'CopilotoApiController', 'cron');

// Co-Piloto Admin (chat interno para equipe)
$router->post('/api/copiloto/admin/chat', 'CopilotoApiController', 'adminChat');

// Desconto com Autorização
$router->get('/admin/promocoes-auditoria', 'AdminPromocoesAuditoriaController', 'index');
$router->get('/admin/promocoes-agendadas', 'AdminPromocoesAgendadasController', 'index');
$router->post('/admin/promocoes-agendadas/criar', 'AdminPromocoesAgendadasController', 'criar');
$router->post('/admin/promocoes-agendadas/cancelar/{id}', 'AdminPromocoesAgendadasController', 'cancelar');
$router->get('/admin/promocoes-agendadas/processar', 'AdminPromocoesAgendadasController', 'processar');
$router->get('/admin/promocoes-agendadas/buscar-produtos', 'AdminPromocoesAgendadasController', 'buscarProdutos');
$router->post('/admin/configuracoes/desconto/solicitar', 'AdminDescontoAutorizacaoController', 'solicitar');
$router->get('/admin/configuracoes/desconto/verificar', 'AdminDescontoAutorizacaoController', 'verificar');
$router->get('/admin/configuracoes/desconto/autorizar', 'AdminDescontoAutorizacaoController', 'autorizarTela');
$router->post('/admin/configuracoes/desconto/autorizar', 'AdminDescontoAutorizacaoController', 'autorizarTela');
$router->get('/admin/configuracoes/desconto/email-autorizar', 'AdminDescontoAutorizacaoController', 'autorizarEmail');
$router->get('/admin/configuracoes/desconto/configuracao', 'AdminDescontoAutorizacaoController', 'configuracao');
$router->post('/admin/configuracoes/desconto/configuracao', 'AdminDescontoAutorizacaoController', 'configuracao');
$router->get('/admin/configuracoes/desconto/painel', 'AdminDescontoAutorizacaoController', 'painel');
$router->post('/admin/configuracoes/desconto/painel', 'AdminDescontoAutorizacaoController', 'painel');

// QuickBooks
$router->get('/admin/quickbooks', 'AdminQuickBooksController', 'index');

// Debug temporário split
$router->get('/admin/split-debug', function() {
    if (session_status() === PHP_SESSION_NONE) session_start();
    header('Content-Type: application/json');
    echo json_encode($_SESSION['_split_debug'] ?? ['msg' => 'Nenhum debug ainda. Faça um checkout primeiro.'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit;
});
$router->get('/admin/quickbooks/invoices', 'AdminQuickBooksController', 'invoices');
$router->get('/admin/quickbooks/conectar', 'AdminQuickBooksController', 'conectar');
$router->get('/admin/quickbooks/callback', 'AdminQuickBooksController', 'oauthCallback');
$router->post('/admin/quickbooks/desconectar', 'AdminQuickBooksController', 'desconectar');
$router->post('/admin/quickbooks/config', 'AdminQuickBooksController', 'salvarConfig');
$router->post('/admin/quickbooks/sincronizar-pedido', 'AdminQuickBooksController', 'sincronizarPedido');
$router->post('/admin/quickbooks/sincronizar-lote', 'AdminQuickBooksController', 'sincronizarLote');

// ═══════════════════════════════════════════════════════════════
// Lives — Live Shopping (TikTok-style)
// ═══════════════════════════════════════════════════════════════

// Admin — CRUD de Lives
$router->get('/admin/lives', 'AdminLivesController', 'index');
$router->get('/admin/lives/nova', 'AdminLivesController', 'create');
$router->post('/admin/lives', 'AdminLivesController', 'store');
$router->get('/admin/lives/{id}/editar', 'AdminLivesController', 'edit');
$router->post('/admin/lives/{id}/atualizar', 'AdminLivesController', 'update');
$router->delete('/admin/lives/{id}', 'AdminLivesController', 'destroy');

// Admin — Estúdio e Transmissão
$router->get('/admin/lives/{id}/studio', 'AdminLivesController', 'studio');
$router->post('/admin/lives/{id}/start', 'AdminLivesController', 'start');
$router->post('/admin/lives/{id}/stop', 'AdminLivesController', 'stop');
$router->post('/admin/lives/{id}/feature', 'AdminLivesController', 'feature');
$router->post('/api/live/{id}/whip', 'AdminLivesController', 'whipProxy');

// Admin — Produtos da Live
$router->get('/admin/lives/buscar-produtos', 'AdminLivesController', 'searchProducts');
$router->post('/admin/lives/{id}/products', 'AdminLivesController', 'addProduct');
$router->post('/admin/lives/{id}/products/{lpId}/update', 'AdminLivesController', 'updateProduct');
$router->post('/admin/lives/{id}/products/{lpId}/remove', 'AdminLivesController', 'removeProduct');

// Admin — Moderação
$router->post('/admin/lives/{id}/chat/{msgId}/hide', 'AdminLivesController', 'hideMessage');
$router->post('/admin/lives/{id}/ban/{userId}', 'AdminLivesController', 'banUser');

// Admin — Relatório
$router->get('/admin/lives/{id}/report', 'AdminLivesController', 'report');
$router->get('/admin/live-shop/orders', 'AdminLivesController', 'orders');

// Aliases para o menu lateral (live-shop → lives)
$router->get('/admin/live-shop', 'AdminLivesController', 'index');
$router->get('/admin/live-shop/create', 'AdminLivesController', 'create');
$router->get('/admin/live-shop/reports', 'AdminLivesController', 'reports');

// Admin — Configurações Lives
$router->get('/admin/configuracoes/lives', 'AdminLivesConfigController', 'index');
$router->post('/admin/configuracoes/lives', 'AdminLivesConfigController', 'store');

// Cliente — Páginas públicas
$router->get('/lives', 'LivesController', 'index');
$router->get('/lives/ao-vivo', 'LivesController', 'liveNow');
$router->get('/lives/{id}', 'LivesController', 'watch');
$router->get('/api/lives/status', 'LivesController', 'checkLiveStatus');

// Cliente — API REST
$router->get('/api/live/{id}/poll', 'LivePollController', 'poll');
$router->post('/api/live/{id}/whep', 'LivePollController', 'whepProxy');
$router->post('/api/live/{id}/heartbeat', 'LiveApiController', 'heartbeat');
$router->post('/api/live/{id}/chat', 'LiveApiController', 'chat');
$router->post('/api/live/{id}/like', 'LiveApiController', 'like');
$router->post('/api/live/{id}/share', 'LiveApiController', 'share');
$router->post('/api/live/{id}/buy', 'LiveApiController', 'buy');

// Cliente — SSE (DESATIVADO - usar polling via /api/live/{id}/poll)
// $router->get('/api/live/{id}/events', 'LiveSseController', 'stream');
$router->get('/api/live/{id}/status', 'LiveSseController', 'status');

// Cliente — Métodos de Pagamento
$router->get('/api/me/payment-methods', 'LiveApiController', 'paymentMethods');
$router->post('/api/me/payment-methods', 'LiveApiController', 'storePaymentMethod');
$router->post('/api/me/payment-methods/{id}/default', 'LiveApiController', 'setDefaultPaymentMethod');
$router->post('/api/me/payment-methods/{id}/delete', 'LiveApiController', 'deletePaymentMethod');

// Descrição de Produtos (IA)
$router->get('/admin/descricao-produtos', 'AdminDescricaoProdutosController', 'index');
$router->post('/admin/descricao-produtos/gerar', 'AdminDescricaoProdutosController', 'gerarDescricao');
$router->post('/admin/descricao-produtos/gerar-lote', 'AdminDescricaoProdutosController', 'gerarLote');
$router->get('/admin/descricao-produtos/revisar/{id}', 'AdminDescricaoProdutosController', 'revisar');
$router->post('/admin/descricao-produtos/aprovar', 'AdminDescricaoProdutosController', 'aprovar');
$router->post('/admin/descricao-produtos/reprovar', 'AdminDescricaoProdutosController', 'reprovar');
$router->post('/admin/descricao-produtos/aprovar-lote', 'AdminDescricaoProdutosController', 'aprovarLote');
$router->post('/admin/descricao-produtos/reprovar-lote', 'AdminDescricaoProdutosController', 'reprovarLote');


// Email Marketing Inteligente
$router->get('/admin/email-marketing', 'AdminEmailMarketingController', 'index');
$router->post('/admin/email-marketing/gerar', 'AdminEmailMarketingController', 'gerarCampanhas');
$router->post('/admin/email-marketing/transcrever', 'AdminEmailMarketingController', 'transcrever');
$router->get('/admin/email-marketing/buscar-usuarios', 'AdminEmailMarketingController', 'buscarUsuarios');
$router->get('/admin/email-marketing/buscar-produtos', 'AdminEmailMarketingController', 'buscarProdutos');
$router->post('/admin/email-marketing/aprovar', 'AdminEmailMarketingController', 'aprovar');
$router->post('/admin/email-marketing/rejeitar', 'AdminEmailMarketingController', 'rejeitar');
$router->post('/admin/email-marketing/excluir', 'AdminEmailMarketingController', 'excluir');
$router->post('/admin/email-marketing/arquivar', 'AdminEmailMarketingController', 'arquivar');
$router->post('/admin/email-marketing/disparar', 'AdminEmailMarketingController', 'disparar');
$router->post('/admin/email-marketing/agendar', 'AdminEmailMarketingController', 'agendar');
$router->post('/admin/email-marketing/gerar-segmentos', 'AdminEmailMarketingController', 'gerarSegmentos');
$router->post('/admin/email-marketing/excluir-segmento', 'AdminEmailMarketingController', 'excluirSegmento');
$router->get('/admin/email-marketing/segmento-detalhes', 'AdminEmailMarketingController', 'segmentoDetalhes');
$router->get('/admin/email-marketing/segmento-clientes', 'AdminEmailMarketingController', 'segmentoClientes');
$router->post('/admin/email-marketing/segmento-remover-cliente', 'AdminEmailMarketingController', 'segmentoRemoverCliente');
$router->post('/admin/email-marketing/salvar-segmento', 'AdminEmailMarketingController', 'salvarSegmento');
$router->get('/admin/email-marketing/detalhes', 'AdminEmailMarketingController', 'detalhes');
$router->post('/admin/email-marketing/config', 'AdminEmailMarketingController', 'salvarConfig');


// Email Tracking (open pixel + click redirect + conversion cron)
$router->get('/email-track/open/{hash}', 'EmailTrackController', 'open');
$router->get('/email-track/click/{hash}', 'EmailTrackController', 'click');
$router->get('/email-track/check-conversions', 'EmailTrackController', 'checkConversions');


// Mapa de Calor do Site
$router->get('/admin/mapa-calor-site', 'AdminMapaCalorSiteController', 'index');
$router->post('/admin/mapa-calor-site/collect', 'AdminMapaCalorSiteController', 'collect');
$router->get('/admin/mapa-calor-site/dados-pagina', 'AdminMapaCalorSiteController', 'dadosPagina');
$router->post('/admin/mapa-calor-site/analise-ia', 'AdminMapaCalorSiteController', 'analiseIA');


// Recuperação de Carrinho
$router->get('/admin/cart-recovery', 'AdminCartRecoveryController', 'index');
$router->get('/admin/cart-recovery/detalhes', 'AdminCartRecoveryController', 'detalhes');
$router->post('/admin/cart-recovery/status', 'AdminCartRecoveryController', 'atualizarStatus');

// Pacotes WordPress (etiquetas puxadas do WP)
$router->get('/admin/pacotes-wordpress', 'AdminPacotesWordpressController', 'index');
$router->post('/admin/pacotes-wordpress/sincronizar', 'AdminPacotesWordpressController', 'sincronizar');
$router->get('/admin/pacotes-wordpress/containers', 'AdminPacotesWordpressController', 'containers');
$router->get('/admin/pacotes-wordpress/containers/novo', 'AdminPacotesWordpressController', 'containerNovo');
$router->post('/admin/pacotes-wordpress/containers/criar', 'AdminPacotesWordpressController', 'containerCriar');
$router->get('/admin/pacotes-wordpress/container/{id}', 'AdminPacotesWordpressController', 'containerDetalhes');
$router->post('/admin/pacotes-wordpress/container/{id}/deletar', 'AdminPacotesWordpressController', 'containerDeletar');
$router->get('/admin/pacotes-wordpress/faturas', 'AdminPacotesWordpressController', 'faturas');
$router->get('/admin/pacotes-wordpress/faturas/nova', 'AdminPacotesWordpressController', 'faturaNova');
$router->post('/admin/pacotes-wordpress/faturas/criar', 'AdminPacotesWordpressController', 'faturaCriar');
$router->get('/admin/pacotes-wordpress/etiqueta/{id}/pdf', 'AdminPacotesWordpressController', 'etiquetaPdf');
