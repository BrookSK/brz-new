<?php
use App\Core\Router;

$router = new Router();

// Rotas Públicas
$router->get('/', 'HomeController', 'index');
$router->get('/produtos', 'ProdutoController', 'index');
$router->get('/produto/detalhes/{id}', 'ProdutoController', 'detalhes');
$router->get('/produtos/selecionar', 'ProdutoController', 'selecionar');
$router->post('/produtos/carrinho', 'ProdutoController', 'adicionarAoCarrinho');
$router->get('/cobranca', 'CobrancaController', 'index');
$router->post('/cobranca/calcular', 'CobrancaController', 'calcular');
$router->get('/rastreamento', 'RastreamentoController', 'index');

// Autenticação
$router->get('/login', 'AuthController', 'login');
$router->post('/login', 'AuthController', 'login');
$router->get('/logout', 'AuthController', 'logout');
$router->get('/register', 'AuthController', 'register');
$router->post('/register', 'AuthController', 'register');
$router->get('/perfil', 'AuthController', 'perfil');
$router->post('/perfil', 'AuthController', 'perfil');

// Checkout
$router->get('/checkout', 'CheckoutController', 'index');
$router->post('/checkout/processar', 'CheckoutController', 'processar');
$router->post('/checkout/calcular', 'CheckoutController', 'calcular');

// Pedidos (Cliente)
$router->get('/pedido/detalhes/{id}', 'PedidoController', 'detalhes');
$router->get('/meus-pedidos', 'PedidoController', 'meusPedidos');

// Área Administrativa
$router->get('/admin/dashboard', 'AdminController', 'dashboard');
$router->get('/admin/pedidos', 'AdminController', 'pedidos');
$router->get('/admin/pedido/detalhes/{id}', 'AdminController', 'pedidoDetalhes');
$router->post('/admin/pedido/atualizar-status', 'AdminController', 'atualizarStatus');
$router->get('/admin/consolidar-pedidos', 'AdminController', 'consolidarPedidos');
$router->post('/admin/consolidar-pedidos', 'AdminController', 'consolidarPedidos');
$router->post('/admin/pedido/gerar-etiqueta', 'AdminController', 'gerarEtiqueta');
$router->post('/admin/pedido/efetivar-etiqueta', 'AdminController', 'efetivarEtiqueta');
$router->get('/admin/configuracoes', 'AdminController', 'configuracoes');
$router->post('/admin/configuracoes', 'AdminController', 'configuracoes');
$router->get('/admin/usuarios', 'AdminController', 'usuarios');

// Webhooks
$router->post('/webhook/asaas', 'WebhookController', 'asaas');
$router->post('/webhook/stripe', 'WebhookController', 'stripe');

// API
$router->post('/api/carrinho/adicionar', 'ApiController', 'adicionarAoCarrinho');
$router->post('/api/carrinho/remover', 'ApiController', 'removerDoCarrinho');
$router->post('/api/carrinho/atualizar', 'ApiController', 'atualizarCarrinho');
$router->get('/api/produtos/buscar', 'ApiController', 'buscarProdutos');
$router->get('/api/cep/{cep}', 'ApiController', 'consultarCEP');
