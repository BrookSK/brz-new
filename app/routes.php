<?php
use App\Core\Router;

$router = new Router();

$router->get('/', 'HomeController', 'index');
$router->get('/produtos', 'ProdutoController', 'index');
$router->get('/produtos/selecionar', 'ProdutoController', 'selecionar');
$router->post('/produtos/carrinho', 'ProdutoController', 'adicionarAoCarrinho');
$router->get('/cobranca', 'CobrancaController', 'index');
$router->post('/cobranca/calcular', 'CobrancaController', 'calcular');
$router->post('/processar', 'ProcessamentoController', 'processar');
$router->get('/rastreamento', 'RastreamentoController', 'index');
