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

// Suporte e políticas
$router->get('/suporte', 'SuporteController', 'index');
$router->get('/politicas', 'PoliticasController', 'index');

// Páginas institucionais
$router->get('/politica-privacidade', 'PoliticaPrivacidadeController', 'index');
$router->get('/termos-uso', 'TermosUsoController', 'index');

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
$router->post('/meus-dados/avatar', 'UsuarioController', 'avatarUpload');
$router->post('/meus-dados/avatar/remover', 'UsuarioController', 'avatarRemover');
$router->get('/meus-pedidos', 'UsuarioController', 'meusPedidos');
$router->get('/pedido/detalhes/{id}', 'UsuarioController', 'pedidoDetalhes');
$router->post('/pedido/reemitir-pagamento/{id}', 'UsuarioController', 'reemitirPagamento');

// Checkout
$router->get('/checkout', 'CheckoutController', 'index');
$router->post('/checkout/processar', 'CheckoutController', 'processar');
$router->get('/checkout/conclusao/{id}', 'CheckoutController', 'conclusao');
$router->post('/checkout/calcular', 'CheckoutController', 'calcular');

// Assessoria de Compras
$router->get('/assessoria', 'AssessoriaController', 'index');
$router->post('/assessoria/processar', 'AssessoriaController', 'processarLinks');
$router->post('/assessoria/processar-um', 'AssessoriaController', 'processarLinkUnico');
$router->post('/assessoria/enfileirar', 'AssessoriaController', 'enfileirarLinks');
$router->get('/assessoria/status', 'AssessoriaController', 'statusJob');
$router->get('/assessoria/orcamento', 'AssessoriaController', 'orcamento');
$router->get('/assessoria/reprocessar', 'AssessoriaController', 'reprocessarOrcamento');
$router->post('/assessoria/adicionar-ao-carrinho', 'AssessoriaController', 'adicionarAoCarrinho');
$router->post('/assessoria/aceitar-disclaimer', 'AssessoriaController', 'aceitarDisclaimer');

// Cron / Manutenção
$router->get('/cron/assessoria/limpar-temporarios', 'AssessoriaController', 'cronLimparTemporarios');

// Área Administrativa - Novos Controllers
$router->get('/admin', function() {
    echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Braziliana Shop Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #0b1f3a;
            --bg-surface: #f6f8fb;
            --radius-md: 16px;
            --shadow-sm: 0 10px 28px rgba(15, 23, 42, 0.10);
        }
        body {
            background: var(--bg-surface);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .admin-card {
            background: #ffffff;
            border-radius: var(--radius-md);
            box-shadow: var(--shadow-sm);
            padding: 3rem;
            max-width: 800px;
            width: 100%;
        }
        .admin-logo {
            font-size: 3rem;
            color: var(--primary);
            margin-bottom: 1rem;
        }
        .admin-title {
            font-size: 2.5rem;
            font-weight: bold;
            color: var(--primary);
            margin-bottom: 2rem;
        }
        .admin-menu {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 2rem;
        }
        .admin-menu-item {
            background: var(--primary);
            color: #ffffff;
            padding: 1.5rem;
            border-radius: var(--radius-md);
            text-decoration: none;
            transition: background-color 0.2s ease, box-shadow 0.2s ease;
            text-align: center;
        }
        .admin-menu-item:hover {
            box-shadow: 0 12px 30px rgba(15, 23, 42, 0.14);
            color: #ffffff;
        }
        .admin-menu-item i {
            font-size: 2rem;
            display: block;
            margin-bottom: 0.5rem;
        }
    </style>
</head>
<body>
    <div class="admin-card">
        <div class="text-center">
            <div class="admin-logo">
                <i class="fas fa-shipping-fast"></i>
            </div>
            <h1 class="admin-title">Braziliana Shop Admin</h1>
            <p class="text-muted mb-4">Painel Administrativo da Loja</p>
            
            <div class="admin-menu">
                <a href="/admin/dashboard" class="admin-menu-item">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Dashboard</span>
                </a>
                <a href="/admin/produtos" class="admin-menu-item">
                    <i class="fas fa-box"></i>
                    <span>Produtos</span>
                </a>
                <a href="/admin/pedidos" class="admin-menu-item">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Pedidos</span>
                </a>
                <a href="/admin/estoque" class="admin-menu-item">
                    <i class="fas fa-warehouse"></i>
                    <span>Estoque</span>
                </a>
                <a href="/admin/estoque/compras" class="admin-menu-item">
                    <i class="fas fa-shopping-basket"></i>
                    <span>Compras</span>
                </a>
                <a href="/admin/estoque/relatorios" class="admin-menu-item">
                    <i class="fas fa-file-pdf"></i>
                    <span>Relatórios</span>
                </a>
                <a href="/admin/usuarios" class="admin-menu-item">
                    <i class="fas fa-users"></i>
                    <span>Usuários</span>
                </a>
                <a href="/admin/pagamentos" class="admin-menu-item">
                    <i class="fas fa-credit-card"></i>
                    <span>Pagamentos</span>
                </a>
                <a href="/admin/configuracoes" class="admin-menu-item">
                    <i class="fas fa-cog"></i>
                    <span>Configurações</span>
                </a>
            </div>
            
            <div class="mt-4">
                <a href="/" class="btn btn-outline-secondary">
                    <i class="fas fa-home"></i> Ir para a Loja
                </a>
            </div>
        </div>
    </div>
</body>
</html>';
});

// Dashboard
$router->get('/admin/dashboard', 'AdminDashboardController', 'index');

// Produtos
$router->get('/admin/produtos', 'AdminProdutosController', 'index');
$router->get('/admin/produtos/novo', 'AdminProdutosController', 'novo');
$router->post('/admin/produtos/salvar', 'AdminProdutosController', 'salvar');
$router->get('/admin/produtos/editar/{id}', 'AdminProdutosController', 'editar');
$router->post('/admin/produtos/atualizar/{id}', 'AdminProdutosController', 'atualizar');
$router->post('/admin/produtos/upload-capa/{id}', 'AdminProdutosController', 'uploadCapa');
$router->post('/admin/produtos/upload-galeria/{id}', 'AdminProdutosController', 'uploadGaleria');
$router->post('/admin/produtos/remover-capa/{id}', 'AdminProdutosController', 'removerCapa');
$router->post('/admin/produtos/remover-foto/{id}', 'AdminProdutosController', 'removerFoto');
$router->post('/admin/produtos/galeria/ordem/{id}', 'AdminProdutosController', 'salvarOrdemGaleria');
$router->post('/admin/produtos/excluir/{id}', 'AdminProdutosController', 'excluir');

// Lojas
$router->get('/admin/lojas', 'AdminLojasController', 'index');
$router->get('/admin/lojas/novo', 'AdminLojasController', 'novo');
$router->get('/admin/lojas/editar/{id}', 'AdminLojasController', 'editar');
$router->post('/admin/lojas/salvar', 'AdminLojasController', 'salvar');
$router->post('/admin/lojas/excluir/{id}', 'AdminLojasController', 'excluir');

// Categorias
$router->get('/admin/categorias', 'AdminCategoriasController', 'index');
$router->get('/admin/categorias/novo', 'AdminCategoriasController', 'novo');
$router->get('/admin/categorias/editar/{id}', 'AdminCategoriasController', 'editar');
$router->post('/admin/categorias/salvar', 'AdminCategoriasController', 'salvar');
$router->post('/admin/categorias/excluir/{id}', 'AdminCategoriasController', 'excluir');

// Pedidos
$router->get('/admin/pedidos', 'AdminPedidosController', 'index');
$router->get('/admin/pedidos/detalhes/{id}', 'AdminPedidosController', 'detalhes');
$router->post('/admin/pedidos/reemitir-pagamento/{id}', 'AdminPedidosController', 'reemitirPagamento');
$router->get('/admin/pedidos/editar/{id}', 'AdminPedidosEditController', 'editar');
$router->post('/admin/pedidos/salvar', 'AdminPedidosEditController', 'salvar');
$router->get('/admin/pedidos/excluir/{id}', 'AdminPedidosController', 'excluir');
$router->post('/admin/pedidos/excluir/{id}', 'AdminPedidosController', 'excluir');
$router->get('/admin/pedidos/atualizar-status/{id}/{status}', 'AdminPedidosController', 'atualizarStatus');

// Usuários
$router->get('/admin/usuarios', 'AdminUsuariosController', 'index');
$router->get('/admin/usuarios/detalhes/{id}', 'AdminUsuariosController', 'detalhes');
$router->post('/admin/usuarios/atualizar-status/{id}', 'AdminUsuariosController', 'atualizarStatus');

// Pagamentos
$router->get('/admin/pagamentos', 'AdminPagamentosController', 'index');
$router->post('/admin/pagamentos/confirmar/{id}', 'AdminPagamentosController', 'confirmarPagamento');

// Configurações
$router->get('/admin/configuracoes', 'AdminConfiguracoesController', 'index');
$router->post('/admin/configuracoes/salvar', 'AdminConfiguracoesController', 'salvar');

// Notificações (Webhooks / Email teste)
$router->post('/admin/salvar-notificacao', 'AdminNotificacoesController', 'salvarNotificacao');
$router->get('/admin/notificacao', 'AdminNotificacoesController', 'obterNotificacao');
$router->get('/admin/logs-webhook', 'AdminNotificacoesController', 'logsWebhook');
$router->get('/admin/log-webhook/{id}', 'AdminNotificacoesController', 'logWebhook');
$router->post('/admin/log-webhook/{id}/excluir', 'AdminNotificacoesController', 'excluirLogWebhook');
$router->post('/admin/logs-webhook/limpar', 'AdminNotificacoesController', 'limparLogsWebhook');
$router->post('/admin/testar-email', 'AdminNotificacoesController', 'testarEmail');
$router->post('/admin/testar-webhook', 'AdminNotificacoesController', 'testarWebhook');

// Templates de E-mail
$router->post('/admin/salvar-email-template', 'AdminNotificacoesController', 'salvarEmailTemplate');
$router->get('/admin/email-templates', 'AdminNotificacoesController', 'listarEmailTemplates');
$router->get('/admin/email-template', 'AdminNotificacoesController', 'obterEmailTemplate');
$router->post('/admin/testar-email-template', 'AdminNotificacoesController', 'testarEmailTemplate');

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

// Rotas do Módulo de Estoque Interno
$router->get('/admin/estoque', 'AdminEstoqueController', 'index');
$router->get('/admin/estoque/entrada', 'AdminEstoqueController', 'entrada');
$router->get('/admin/estoque/buscar-produtos', 'AdminEstoqueController', 'buscarProdutos');
$router->get('/admin/estoque/editar/{produto_id}', 'AdminEstoqueController', 'editar');
$router->post('/admin/estoque/editar/salvar', 'AdminEstoqueController', 'salvarEdicao');
$router->post('/admin/estoque/editar/excluir', 'AdminEstoqueController', 'excluirEntrada');
$router->post('/admin/estoque/salvar', 'AdminEstoqueController', 'salvar');
$router->post('/admin/estoque/marcar-comprado', 'AdminEstoqueController', 'marcarComprado');

// Rotas de Lista de Compras
$router->get('/admin/estoque/compras', 'AdminComprasController', 'index');
$router->post('/admin/estoque/compras/salvar', 'AdminComprasController', 'salvar');
$router->post('/admin/estoque/compras/editar-item', 'AdminComprasController', 'editarItem');
$router->post('/admin/estoque/compras/remover-item', 'AdminComprasController', 'removerItem');
$router->post('/admin/estoque/compras/definir-loja', 'AdminComprasController', 'definirLojaProduto');
$router->post('/admin/estoque/compras/mudar-status', 'AdminComprasController', 'mudarStatus');
$router->post('/admin/estoque/compras/concluir', 'AdminComprasController', 'concluirCompras');
$router->get('/admin/estoque/compras/pdf', 'AdminComprasController', 'gerarPDF');
$router->get('/admin/estoque/verificar-estoque/{produto_id}', 'AdminComprasController', 'verificarEstoque');

// Rotas de Relatórios
$router->get('/admin/estoque/relatorios', 'AdminRelatoriosController', 'index');
$router->get('/admin/estoque/relatorio-pdf', 'AdminRelatoriosController', 'gerarPDF');

// Rotas de Remessa Internacional
$router->get('/admin/remessa-internacional', 'AdminRemessaInternacionalController', 'index');
$router->post('/admin/remessa-internacional/gerar/{id}', 'AdminRemessaInternacionalController', 'gerarRemessa');
$router->post('/admin/remessa-internacional/reenviar-webhook/{id}', 'AdminRemessaInternacionalController', 'reenviarWebhook');
$router->get('/admin/remessa-internacional/janela/{id}', 'AdminRemessaInternacionalController', 'verJanela');
$router->get('/admin/remessa-internacional/janela/{janelaId}/pedido/{pedidoId}', 'AdminRemessaInternacionalController', 'detalhesPedidoJanela');
$router->post('/admin/remessa-internacional/janela/{janelaId}/pedido/{pedidoId}/etiqueta-gerada', 'AdminRemessaInternacionalController', 'marcarEtiquetaGerada');
$router->post('/admin/remessa-internacional/janela/{id}/fechar', 'AdminRemessaInternacionalController', 'fecharJanela');

// Rotas de Remessa Correios
$router->get('/admin/remessa-correios', 'AdminRemessaCorreiosController', 'index');
$router->post('/admin/remessa-correios/gerar-etiqueta/{id}', 'AdminRemessaCorreiosController', 'gerarEtiqueta');
$router->post('/admin/remessa-correios/gerar-lote-etiquetas', 'AdminRemessaCorreiosController', 'gerarLoteEtiquetas');
$router->get('/admin/remessa-correios/imprimir-etiqueta/{id}', 'AdminRemessaCorreiosController', 'imprimirEtiqueta');
$router->get('/admin/remessa-correios/imprimir-todas-etiquetas', 'AdminRemessaCorreiosController', 'imprimirTodasEtiquetas');
$router->post('/admin/remessa-correios/confirmar-postagem/{id}', 'AdminRemessaCorreiosController', 'confirmarPostagem');
$router->get('/admin/remessa-correios/rastrear/{id}', 'AdminRemessaCorreiosController', 'rastrearEtiqueta');

// Rotas de Carteira
$router->post('/admin/usuarios/adicionar-credito', 'AdminUsuariosController', 'adicionarCredito');
$router->post('/admin/carteira/converter-para-brl', 'AdminCarteiraController', 'converterParaBRL');
$router->post('/admin/carteira/adicionar-creditos-em-lote', 'AdminCarteiraController', 'adicionarCreditosEmLote');
$router->get('/admin/carteira/saldo/{usuario_id}', 'AdminCarteiraController', 'getSaldo');
$router->get('/admin/carteira/extrato/{usuario_id}', 'AdminCarteiraController', 'getExtrato');
$router->get('/admin/carteira/stats', 'AdminCarteiraController', 'getStatsGerais');

// Rotas de Usuários
$router->get('/admin/usuarios/editar/{id}', 'AdminUsuariosController', 'editar');
$router->post('/admin/usuarios/salvar', 'AdminUsuariosController', 'salvar');
$router->get('/admin/usuarios/excluir/{id}', 'AdminUsuariosController', 'excluir');
$router->post('/admin/usuarios/excluir/{id}', 'AdminUsuariosController', 'excluir');
$router->get('/admin/usuarios/novo', 'AdminUsuariosController', 'novo');
$router->post('/admin/usuarios/atualizar-status/{id}', 'AdminUsuariosController', 'atualizarStatus');
