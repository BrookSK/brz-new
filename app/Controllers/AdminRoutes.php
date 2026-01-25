<?php
// Rotas Admin
$app->get('/admin/produtos/editar/{id}', ['App\Controllers\AdminProdutosController', 'editar']);
$app->post('/admin/produtos/atualizar/{id}', ['App\Controllers\AdminProdutosController', 'atualizar']);
$app->delete('/admin/produtos/remover-foto/{id}', ['App\Controllers\AdminProdutosController', 'removerFoto']);
$app->post('/admin/produtos/salvar', ['App\Controllers\AdminProdutosController', 'salvar']);
$app->get('/admin/produtos/novo', ['App\Controllers\AdminProdutosController', 'novo']);
$app->get('/admin/produtos', ['App\Controllers\AdminProdutosController', 'index']);
$app->post('/admin/produtos/excluir/{id}', ['App\Controllers\AdminProdutosController', 'excluir']);
?>
