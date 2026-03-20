<?php
// Rotas Admin

// ── Redirecionamento ──────────────────────────────────────────────────────────
$app->get('/admin/redirecionamento/dashboard',          ['App\Controllers\AdminRedirecionamentoController', 'dashboard']);
$app->get('/admin/redirecionamento/redirecionadores',   ['App\Controllers\AdminRedirecionamentoController', 'redirecionadores']);
$app->get('/admin/redirecionamento/redirecionadores/novo', ['App\Controllers\AdminRedirecionamentoController', 'redirecionadorNovo']);
$app->post('/admin/redirecionamento/redirecionadores/salvar', ['App\Controllers\AdminRedirecionamentoController', 'redirecionadorSalvar']);
$app->get('/admin/redirecionamento/redirecionadores/editar/{id}', ['App\Controllers\AdminRedirecionamentoController', 'redirecionadorEditar']);
$app->post('/admin/redirecionamento/redirecionadores/atualizar/{id}', ['App\Controllers\AdminRedirecionamentoController', 'redirecionadorAtualizar']);
$app->get('/admin/redirecionamento/envios',             ['App\Controllers\AdminRedirecionamentoController', 'envios']);
$app->get('/admin/redirecionamento/envios/novo',        ['App\Controllers\AdminRedirecionamentoController', 'envioNovo']);
$app->post('/admin/redirecionamento/envios/salvar',     ['App\Controllers\AdminRedirecionamentoController', 'envioSalvar']);
$app->get('/admin/redirecionamento/envios/{id}',        ['App\Controllers\AdminRedirecionamentoController', 'envioDetalhe']);
$app->post('/admin/redirecionamento/envios/{id}/peso-real', ['App\Controllers\AdminRedirecionamentoController', 'envioAtualizarPeso']);
$app->post('/admin/redirecionamento/envios/{id}/tracking', ['App\Controllers\AdminRedirecionamentoController', 'envioSalvarTracking']);
$app->post('/admin/redirecionamento/envios/{id}/coletado', ['App\Controllers\AdminRedirecionamentoController', 'envioMarcarColetado']);
$app->post('/admin/redirecionamento/envios/{id}/entregue', ['App\Controllers\AdminRedirecionamentoController', 'envioMarcarEntregue']);
$app->get('/admin/redirecionamento/divergencias',       ['App\Controllers\AdminRedirecionamentoController', 'divergencias']);
$app->post('/admin/redirecionamento/divergencias/gerar-link', ['App\Controllers\AdminRedirecionamentoController', 'divergenciaGerarLink']);
$app->post('/admin/redirecionamento/divergencias/marcar-pago', ['App\Controllers\AdminRedirecionamentoController', 'divergenciaMarcarPago']);
$app->get('/admin/redirecionamento/clientes',           ['App\Controllers\AdminRedirecionamentoController', 'clientes']);
$app->post('/admin/redirecionamento/clientes/salvar',   ['App\Controllers\AdminRedirecionamentoController', 'clienteSalvar']);
$app->get('/admin/redirecionamento/clientes/get',       ['App\Controllers\AdminRedirecionamentoController', 'clienteGet']);
$app->get('/admin/redirecionamento/tabela-pesos',       ['App\Controllers\AdminRedirecionamentoController', 'tabelaPesos']);
$app->post('/admin/redirecionamento/tabela-pesos/salvar', ['App\Controllers\AdminRedirecionamentoController', 'tabelaPesosSalvar']);
$app->post('/admin/redirecionamento/tabela-pesos/excluir', ['App\Controllers\AdminRedirecionamentoController', 'tabelaPesosExcluir']);
$app->get('/admin/redirecionamento/tabela-pesos/calcular', ['App\Controllers\AdminRedirecionamentoController', 'calcularSimulador']);
$app->get('/admin/redirecionamento/pagamentos',         ['App\Controllers\AdminRedirecionamentoController', 'pagamentos']);
$app->get('/admin/redirecionamento/comprovantes',       ['App\Controllers\AdminRedirecionamentoController', 'comprovantes']);
$app->post('/admin/redirecionamento/comprovantes/upload', ['App\Controllers\AdminRedirecionamentoController', 'uploadComprovante']);
$app->get('/admin/redirecionamento/coletas',            ['App\Controllers\AdminRedirecionamentoController', 'coletas']);
$app->post('/admin/redirecionamento/coletas/agendar',   ['App\Controllers\AdminRedirecionamentoController', 'coletaAgendar']);
$app->post('/admin/redirecionamento/coletas/confirmar', ['App\Controllers\AdminRedirecionamentoController', 'coletaConfirmar']);
$app->post('/admin/redirecionamento/coletas/coletado',  ['App\Controllers\AdminRedirecionamentoController', 'coletaMarcarColetado']);
$app->post('/admin/redirecionamento/coletas/reagendar', ['App\Controllers\AdminRedirecionamentoController', 'coletaReagendar']);
$app->post('/admin/redirecionamento/pagamento/criar-intent', ['App\Controllers\AdminRedirecionamentoController', 'criarIntentPagamento']);
$app->post('/admin/redirecionamento/pagamento/confirmar', ['App\Controllers\AdminRedirecionamentoController', 'confirmarPagamento']);

// ── Produtos ──────────────────────────────────────────────────────────────────
$app->get('/admin/produtos/editar/{id}', ['App\Controllers\AdminProdutosController', 'editar']);
$app->post('/admin/produtos/atualizar/{id}', ['App\Controllers\AdminProdutosController', 'atualizar']);
$app->delete('/admin/produtos/remover-foto/{id}', ['App\Controllers\AdminProdutosController', 'removerFoto']);
$app->post('/admin/produtos/salvar', ['App\Controllers\AdminProdutosController', 'salvar']);
$app->get('/admin/produtos/novo', ['App\Controllers\AdminProdutosController', 'novo']);
$app->get('/admin/produtos', ['App\Controllers\AdminProdutosController', 'index']);
$app->post('/admin/produtos/excluir/{id}', ['App\Controllers\AdminProdutosController', 'excluir']);
?>
