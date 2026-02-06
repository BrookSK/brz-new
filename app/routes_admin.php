<?php
// Rotas do Painel Administrativo

// Dashboard
$router->get('/admin/dashboard', 'AdminDashboardController', 'index');

// Produtos
$router->get('/admin/produtos', 'AdminProdutosController', 'index');
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

// Importação de produtos (CSV)
$router->get('/admin/produtos/importar/modelo', 'AdminProdutosController', 'importarProdutosModelo');
$router->post('/admin/produtos/importar/iniciar', 'AdminProdutosController', 'importarProdutosIniciar');
$router->post('/admin/produtos/importar/processar', 'AdminProdutosController', 'importarProdutosProcessar');

// Pedidos
$router->get('/admin/pedidos', 'AdminPedidosController', 'index');
$router->get('/admin/pedidos/detalhes/{id}', 'AdminPedidosController', 'detalhes');
$router->get('/admin/pedidos/editar/{id}', 'AdminPedidosEditController', 'editar');
$router->post('/admin/pedidos/salvar', 'AdminPedidosEditController', 'salvar');
$router->get('/admin/pedidos/atualizar-status/{id}/{status}', 'AdminPedidosController', 'atualizarStatus');

// Importação de pedidos (CSV)
$router->get('/admin/pedidos/importar/modelo', 'AdminPedidosController', 'importarPedidosModelo');
$router->post('/admin/pedidos/importar/iniciar', 'AdminPedidosController', 'importarPedidosIniciar');
$router->post('/admin/pedidos/importar/processar', 'AdminPedidosController', 'importarPedidosProcessar');

// Usuários
$router->get('/admin/usuarios', 'AdminUsuariosController', 'index');
$router->get('/admin/usuarios/detalhes/{id}', 'AdminUsuariosController', 'detalhes');
$router->get('/admin/usuarios/editar/{id}', 'AdminUsuariosController', 'editar');
$router->post('/admin/usuarios/atualizar/{id}', 'AdminUsuariosController', 'atualizar');
$router->post('/admin/usuarios/excluir/{id}', 'AdminUsuariosController', 'excluir');
$router->post('/admin/usuarios/atualizar-status/{id}', 'AdminUsuariosController', 'atualizarStatus');

// Pagamentos
$router->get('/admin/pagamentos', 'AdminPagamentosController', 'index');
$router->post('/admin/pagamentos/confirmar/{id}', 'AdminPagamentosController', 'confirmarPagamento');
$router->post('/admin/pagamentos/refresh/{id}', 'AdminPagamentosController', 'refreshPagamento');
$router->post('/admin/pagamentos/cancelar/{id}', 'AdminPagamentosController', 'cancelarPagamento');
$router->post('/admin/pagamentos/estornar/{id}', 'AdminPagamentosController', 'estornarPagamento');
$router->post('/admin/pagamentos/cancelar-pedido/{id}', 'AdminPagamentosController', 'cancelarPedido');
$router->get('/admin/pagamentos/configuracoes', 'AdminPagamentosController', 'configuracoes');
$router->post('/admin/pagamentos/salvar-configuracoes', 'AdminPagamentosController', 'salvarConfiguracoes');

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
