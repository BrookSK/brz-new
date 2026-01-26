<?php
// Rotas para o Módulo de Estoque Interno
// Adicionar estas rotas ao arquivo app/routes.php

// Rotas Principais de Estoque
$router->get('/admin/estoque', 'App\Controllers\AdminEstoqueController@index');
$router->post('/admin/estoque/salvar', 'App\Controllers\AdminEstoqueController@salvar');
$router->post('/admin/estoque/marcar-comprado', 'App\Controllers\AdminEstoqueController@marcarComprado');

// Rotas de Lista de Compras
$router->get('/admin/estoque/compras', 'App\Controllers\AdminComprasController@index');
$router->post('/admin/estoque/compras/salvar', 'App\Controllers\AdminComprasController@salvar');
$router->post('/admin/estoque/compras/mudar-status', 'App\Controllers\AdminComprasController@mudarStatus');
$router->get('/admin/estoque/compras/pdf', 'App\Controllers\AdminComprasController@gerarPDF');
$router->get('/admin/estoque/verificar-estoque/{produto_id}', 'App\Controllers\AdminComprasController@verificarEstoque');

// Rotas de Relatórios
$router->get('/admin/estoque/relatorios', 'App\Controllers\AdminRelatoriosController@index');
$router->get('/admin/estoque/relatorio-pdf', 'App\Controllers\AdminRelatoriosController@gerarPDF');

// Rotas Adicionais (para implementação futura)
$router->get('/admin/estoque/movimentacao', 'App\Controllers\AdminMovimentacaoController@index');
$router->get('/admin/estoque/historico/{produto_id}', 'App\Controllers\AdminMovimentacaoController@historicoProduto');
$router->post('/admin/estoque/ajustar-estoque', 'App\Controllers\AdminEstoqueController@ajustarEstoque');
$router->post('/admin/estoque/baixa-estoque', 'App\Controllers\AdminEstoqueController@baixaEstoque');

?>
