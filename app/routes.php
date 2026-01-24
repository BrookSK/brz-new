<?php
use App\Core\Router;

$router = new Router();

// Rotas Públicas
$router->get('/', 'HomeController', 'index');
$router->get('/produtos', 'ProdutoController', 'index');
$router->get('/produto/detalhes/{id}', 'ProdutoController', 'detalhes');
$router->get('/produtos/selecionar', 'ProdutoController', 'selecionar');
$router->post('/produtos/carrinho', 'ProdutoController', 'adicionarAoCarrinho');
$router->get('/carrinho', 'CarrinhoController', 'index');
$router->post('/carrinho/adicionar', 'CarrinhoController', 'adicionar');
$router->post('/carrinho/remover', 'CarrinhoController', 'remover');
$router->post('/carrinho/atualizar', 'CarrinhoController', 'atualizar');
$router->post('/carrinho/limpar', 'CarrinhoController', 'limpar');
$router->get('/cobranca', 'CobrancaController', 'index');
$router->post('/cobranca/calcular', 'CobrancaController', 'calcular');
$router->get('/rastreamento', 'RastreamentoController', 'index');
$router->get('/faq', 'FaqController', 'index');
$router->get('/como-funciona', 'ComoFuncionaController', 'index');
$router->get('/contato', 'ContatoController', 'index');

// Autenticação
$router->get('/login', 'AuthController', 'login');
$router->post('/login', 'AuthController', 'login');
$router->get('/loginadmin', 'AuthController', 'loginAdmin');
$router->post('/loginadmin', 'AuthController', 'loginAdmin');
$router->get('/logout', 'AuthController', 'logout');
$router->get('/register', 'AuthController', 'register');
$router->post('/register', 'AuthController', 'register');
$router->get('/perfil', 'AuthController', 'perfil');
$router->post('/perfil', 'AuthController', 'perfil');

// Área do Usuário
$router->get('/minha-conta', 'UsuarioController', 'minhaConta');
$router->get('/meus-dados', 'UsuarioController', 'meusDados');
$router->post('/meus-dados', 'UsuarioController', 'meusDados');
$router->get('/meus-pedidos', 'UsuarioController', 'meusPedidos');
$router->get('/pedido/detalhes/{id}', 'UsuarioController', 'pedidoDetalhes');

// Checkout
$router->get('/checkout', 'CheckoutController', 'index');
$router->post('/checkout/processar', 'CheckoutController', 'processar');
$router->get('/checkout/conclusao/{id}', 'CheckoutController', 'conclusao');
$router->post('/checkout/calcular', 'CheckoutController', 'calcular');

// Área Administrativa
$router->get('/admin/dashboard', 'AdminController', 'dashboard');
$router->get('/admin/pedidos', 'AdminController', 'pedidos');
$router->get('/admin/pedido/detalhes/{id}', 'AdminController', 'pedidoDetalhes');
$router->get('/admin/criar-pedido', 'AdminController', 'criarPedido');
$router->post('/admin/criar-pedido', 'AdminController', 'criarPedido');
$router->post('/admin/criar-pedido-completo', 'AdminController', 'criarPedidoCompleto');
$router->get('/admin/buscar-produtos', 'AdminController', 'buscarProdutos');
$router->post('/admin/atualizar-status', 'AdminController', 'atualizarStatus');
$router->post('/admin/consolidar-pedidos', 'AdminController', 'consolidarPedidos');
$router->get('/admin/gerar-etiqueta/{id}', 'AdminController', 'gerarEtiqueta');
$router->post('/admin/efetivar-etiqueta/{id}', 'AdminController', 'efetivarEtiqueta');
$router->get('/admin/configuracoes', 'AdminController', 'configuracoes');
$router->post('/admin/salvar-configuracoes', 'AdminController', 'salvarConfiguracoes');
$router->get('/admin/testar-email', 'AdminController', 'testarEmail');
$router->get('/admin/usuarios', 'AdminController', 'usuarios');
$router->get('/admin/usuario/{id}', 'AdminController', 'editarUsuario');
$router->post('/admin/salvar-usuario', 'AdminController', 'salvarUsuario');
$router->post('/admin/atualizar-usuario/{id}', 'AdminController', 'atualizarUsuario');
$router->post('/admin/excluir-usuario/{id}', 'AdminController', 'excluirUsuario');
$router->get('/admin/usuarios-json', 'AdminController', 'usuariosJson');
$router->get('/admin/estatisticas-usuarios', 'AdminController', 'estatisticasUsuarios');
$router->get('/admin/produtos', 'AdminController', 'produtos');
$router->get('/admin/produto/{id}', 'AdminController', 'produto');
$router->get('/admin/novo-produto', 'AdminController', 'novoProduto');
$router->get('/admin/editar-produto/{id}', 'AdminController', 'editarProduto');
$router->post('/admin/salvar-produto', 'AdminController', 'salvarProduto');
$router->post('/admin/upload-imagem', 'AdminController', 'uploadImagem');
$router->post('/admin/atualizar-produto/{id}', 'AdminController', 'atualizarProduto');
$router->post('/admin/alterar-status-produto/{id}', 'AdminController', 'alterarStatusProduto');
$router->post('/admin/excluir-produto/{id}', 'AdminController', 'excluirProduto');
$router->post('/admin/marcar-foto-principal/{id}', 'AdminController', 'marcarFotoPrincipal');
$router->post('/admin/excluir-foto/{id}', 'AdminController', 'excluirFoto');
$router->get('/admin/gerar-imagens/{id}', 'AdminController', 'gerarImagens');
$router->post('/admin/consolidar-pedidos/exportar', 'AdminController', 'exportarConsolidarPedidos');

// Créditos
$router->post('/admin/adicionar-creditos', 'AdminController', 'adicionarCreditos');
$router->get('/admin/logs-creditos', 'AdminController', 'logsCreditos');
$router->get('/admin/credito-detalhes/{id}', 'AdminController', 'creditoDetalhes');
$router->get('/admin/usuario-perfil/{id}', 'AdminController', 'usuarioPerfil');

// Notificações
$router->post('/admin/salvar-notificacao', 'AdminController', 'salvarNotificacao');
$router->get('/admin/logs-webhook', 'AdminController', 'logsWebhook');
$router->get('/admin/log-webhook/{id}', 'AdminController', 'logWebhook');

// Categorias
$router->get('/admin/categorias', 'AdminController', 'categorias');
$router->get('/admin/categoria/{id}', 'AdminController', 'editarCategoria');
$router->post('/admin/salvar-categoria', 'AdminController', 'salvarCategoria');
$router->post('/admin/atualizar-categoria/{id}', 'AdminController', 'atualizarCategoria');
$router->post('/admin/excluir-categoria/{id}', 'AdminController', 'excluirCategoria');

// Importação
$router->post('/admin/importar-produtos', 'AdminController', 'importarProdutos');

// Webhooks
$router->post('/webhook/asaas', 'WebhookController', 'asaas');
$router->post('/webhook/stripe', 'WebhookController', 'stripe');

// API
$router->get('/api/produtos/buscar', 'ApiController', 'buscarProdutos');
$router->get('/api/produtos/destaque', 'ApiController', 'produtosDestaque');
$router->post('/api/carrinho/adicionar', 'ApiController', 'adicionarAoCarrinho');
$router->post('/api/carrinho/remover', 'ApiController', 'removerDoCarrinho');
$router->post('/api/carrinho/atualizar', 'ApiController', 'atualizarCarrinho');
$router->post('/api/carrinho/limpar', 'ApiController', 'limparCarrinho');
$router->get('/api/cep/{cep}', 'ApiController', 'consultarCEP');
$router->get('/api/frete/calcular', 'ApiController', 'calcularFrete');
