<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;
use App\Services\PedidoManualService;
use App\Models\Carrinho;
use App\Models\Usuario;

class AdminPedidosManualController extends Controller {
    public function clientesBusca(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor']);

        $q = trim((string) $request->getParam('q', ''));
        $limit = (int) $request->getParam('limit', 20);
        if ($limit <= 0) $limit = 20;
        if ($limit > 50) $limit = 50;

        $pdo = \Config\Database::getConnection();

        $cols = [];
        try {
            $stmtCols = $pdo->query('DESCRIBE usuarios');
            $cols = $stmtCols ? ($stmtCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
        } catch (\Exception $e) {
            $cols = [];
        }

        $nomeCol = '';
        foreach (['nome', 'name', 'nome_completo', 'full_name', 'nomeCompleto', 'firstname', 'first_name'] as $cand) {
            if (in_array($cand, $cols, true)) {
                $nomeCol = $cand;
                break;
            }
        }

        $emailCol = '';
        foreach (['email', 'user_email', 'mail'] as $cand) {
            if (in_array($cand, $cols, true)) {
                $emailCol = $cand;
                break;
            }
        }

        $switchCol = '';
        foreach (['switch', 'switch_id', 'switchid', 'sw', 'codigo_switch', 'switch_code', 'switchcode', 'chave', 'codigo', 'identificador'] as $cand) {
            if (in_array($cand, $cols, true)) {
                $switchCol = $cand;
                break;
            }
        }

        $out = ['ok' => true, 'items' => []];

        if ($q === '') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($out);
            exit;
        }

        if (!ctype_digit($q) && mb_strlen($q) < 2) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($out);
            exit;
        }

        $params = [];
        $whereParts = [];

        if (ctype_digit($q)) {
            $whereParts[] = 'id = :id_exact';
            $params[':id_exact'] = (int) $q;
        }

        $like = '%' . $q . '%';
        if ($nomeCol !== '') {
            $whereParts[] = $nomeCol . ' LIKE :like';
        }
        if ($emailCol !== '') {
            $whereParts[] = $emailCol . ' LIKE :like';
        }
        if ($switchCol !== '') {
            $whereParts[] = $switchCol . ' LIKE :like';
        }
        $params[':like'] = $like;

        if (empty($whereParts)) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($out);
            exit;
        }

        $sql = 'SELECT id'
            . ($nomeCol !== '' ? (', ' . $nomeCol . ' AS nome') : '')
            . ($emailCol !== '' ? (', ' . $emailCol . ' AS email') : '')
            . ($switchCol !== '' ? (', ' . $switchCol . ' AS switch_val') : '')
            . ' FROM usuarios'
            . ' WHERE (' . implode(' OR ', $whereParts) . ')'
            . ' ORDER BY id DESC'
            . ' LIMIT :lim';

        try {
            $st = $pdo->prepare($sql);
            foreach ($params as $k => $v) {
                if ($k === ':id_exact') {
                    $st->bindValue($k, (int) $v, \PDO::PARAM_INT);
                } else {
                    $st->bindValue($k, (string) $v);
                }
            }
            $st->bindValue(':lim', (int) $limit, \PDO::PARAM_INT);
            $st->execute();
            $rows = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            foreach ($rows as $r) {
                $id = (int) ($r['id'] ?? 0);
                if ($id <= 0) continue;
                $nome = (string) ($r['nome'] ?? '');
                $email = (string) ($r['email'] ?? '');
                $sw = (string) ($r['switch_val'] ?? '');
                $label = trim($nome);
                if ($label === '') {
                    $label = 'Cliente #' . $id;
                }
                $extra = [];
                if ($email !== '') $extra[] = $email;
                if ($sw !== '') $extra[] = $sw;
                if (!empty($extra)) {
                    $label .= ' (' . implode(' | ', $extra) . ')';
                }
                $out['items'][] = ['id' => $id, 'text' => $label];
            }
        } catch (\Exception $e) {
            $out = ['ok' => false, 'error' => 'Falha ao buscar clientes'];
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($out);
        exit;
    }

    public function novo(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor']);

        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $formToken = '';
        try {
            $formToken = bin2hex(random_bytes(16));
        } catch (\Throwable $e) {
            $formToken = bin2hex((string) microtime(true));
        }
        $_SESSION['pedido_manual_form_token'] = $formToken;

        $usuarios = [];
        $pdo = \Config\Database::getConnection();
        $produtos = [];
        try {
            $cols = [];
            try {
                $stmtCols = $pdo->query('DESCRIBE produtos');
                $cols = $stmtCols ? $stmtCols->fetchAll(\PDO::FETCH_COLUMN) : [];
            } catch (\Exception $e) {
                $cols = [];
            }

            $nameCol = in_array('name', $cols, true) ? 'name' : (in_array('nome', $cols, true) ? 'nome' : '');
            $priceCol = in_array('price', $cols, true) ? 'price' : (in_array('valor', $cols, true) ? 'valor' : '');
            $activeCol = in_array('active', $cols, true) ? 'active' : (in_array('ativo', $cols, true) ? 'ativo' : '');
            $pesoCol = '';
            foreach (['peso', 'weight', 'product_weight', 'peso_kg'] as $cPeso) {
                if (in_array($cPeso, $cols, true)) {
                    $pesoCol = $cPeso;
                    break;
                }
            }

            $custoCol = '';
            foreach (['custo', 'cost', 'custo_produto', 'valor_custo'] as $c) {
                if (in_array($c, $cols, true)) {
                    $custoCol = $c;
                    break;
                }
            }

            $ncmCol = '';
            foreach (['ncm', 'ncm_code'] as $c) {
                if (in_array($c, $cols, true)) {
                    $ncmCol = $c;
                    break;
                }
            }

            // Detectar coluna de preço promocional
            $salePriceCol = '';
            foreach (['sale_price', 'preco_promocional', 'preco_promocao'] as $c) {
                if (in_array($c, $cols, true)) {
                    $salePriceCol = $c;
                    break;
                }
            }
            $salePriceExpiresCol = '';
            foreach (['sale_price_expires', 'promocao_expira', 'sale_expires'] as $c) {
                if (in_array($c, $cols, true)) {
                    $salePriceExpiresCol = $c;
                    break;
                }
            }

            $select = ['id'];
            if ($nameCol !== '') $select[] = $nameCol . ' AS name';
            if ($priceCol !== '') $select[] = $priceCol . ' AS price';
            if ($salePriceCol !== '') $select[] = $salePriceCol . ' AS sale_price';
            if ($salePriceExpiresCol !== '') $select[] = $salePriceExpiresCol . ' AS sale_price_expires';
            if (in_array('sku', $cols, true)) $select[] = 'sku';
            if ($pesoCol !== '') $select[] = $pesoCol . ' AS peso';
            if ($custoCol !== '') $select[] = $custoCol . ' AS custo';
            if ($ncmCol !== '') $select[] = $ncmCol . ' AS ncm';

            $fotoCol = '';
            foreach (['foto_principal', 'capa', 'imagem', 'image'] as $c) {
                if (in_array($c, $cols, true)) {
                    $fotoCol = $c;
                    break;
                }
            }
            if ($fotoCol !== '') {
                $select[] = $fotoCol . ' AS foto_principal';
            }

            $where = '';
            if ($activeCol !== '') {
                $where = ' WHERE ' . $activeCol . ' = 1 ';
            }

            $orderBy = ($nameCol !== '') ? ' ORDER BY ' . $nameCol . ' ASC' : ' ORDER BY id DESC';

            $sql = 'SELECT ' . implode(', ', $select) . ' FROM produtos' . $where . $orderBy;
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $produtos = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Enriquecer com imagem (primeiro tenta foto_principal; depois primeira foto de produto_fotos)
            foreach ($produtos as &$p) {
                $img = (string) ($p['foto_principal'] ?? '');
                if ($img === '') {
                    try {
                        $stmtFoto = $pdo->prepare('SELECT nome_arquivo FROM produto_fotos WHERE produto_id = :produto_id ORDER BY principal DESC, ordem ASC, id ASC LIMIT 1');
                        $stmtFoto->bindValue(':produto_id', (int) ($p['id'] ?? 0), \PDO::PARAM_INT);
                        $stmtFoto->execute();
                        $foto = $stmtFoto->fetch(\PDO::FETCH_ASSOC) ?: [];
                        $img = (string) ($foto['nome_arquivo'] ?? '');
                    } catch (\Exception $e) {
                        $img = '';
                    }
                }

                $img = trim($img);
                if ($img !== '' && !preg_match('#^https?://#i', $img)) {
                    if ($img[0] !== '/') {
                        $img = '/' . $img;
                    }
                    if (strpos($img, '/uploads/') !== 0) {
                        $img = '/uploads/produtos/' . ltrim($img, '/');
                    }
                }

                $p['imagem'] = $img;

                // Aplicar preço promocional ativo (se existir e não expirado)
                $salePrice = floatval($p['sale_price'] ?? 0);
                if ($salePrice > 0) {
                    $expires = $p['sale_price_expires'] ?? null;
                    if (!empty($expires)) {
                        $expiresTime = strtotime($expires);
                        if ($expiresTime !== false && $expiresTime < time()) {
                            $salePrice = 0; // Promoção expirada
                        }
                    }
                }
                $p['sale_price'] = $salePrice;
            }
        } catch (\Exception $e) {
            $produtos = [];
        }

        $pedidoId = (int) $request->getParam('pedido_id', 0);
        $erro = (string) $request->getParam('erro', '');

        $existingPedido = null;
        $existingItens = [];
        if ($pedidoId > 0) {
            try {
                $colsPedidos = [];
                try {
                    $stmtColsP = $pdo->query('DESCRIBE pedidos');
                    $colsPedidos = $stmtColsP ? $stmtColsP->fetchAll(\PDO::FETCH_COLUMN) : [];
                } catch (\Exception $e) {
                    $colsPedidos = [];
                }

                $usuarioCol = in_array('usuario_id', $colsPedidos, true) ? 'usuario_id' : (in_array('user_id', $colsPedidos, true) ? 'user_id' : '');
                $origemCol = in_array('origem_pedido', $colsPedidos, true) ? 'origem_pedido' : '';
                $codigoCol = in_array('codigo_pedido', $colsPedidos, true) ? 'codigo_pedido' : (in_array('numero_pedido', $colsPedidos, true) ? 'numero_pedido' : '');

                $sel = ['id'];
                if ($usuarioCol !== '') $sel[] = $usuarioCol . ' AS cliente_id';
                if ($origemCol !== '') $sel[] = $origemCol . ' AS origem_pedido';
                if ($codigoCol !== '') $sel[] = $codigoCol . ' AS codigo_pedido';

                $stmtP = $pdo->prepare('SELECT ' . implode(', ', $sel) . ' FROM pedidos WHERE id = :id LIMIT 1');
                $stmtP->bindValue(':id', (int) $pedidoId, \PDO::PARAM_INT);
                $stmtP->execute();
                $existingPedido = $stmtP->fetch(\PDO::FETCH_ASSOC) ?: null;

                $itensTable = '';
                try {
                    $st = $pdo->prepare('SHOW TABLES LIKE ?');
                    $st->execute(['pedido_itens']);
                    if ($st->fetchColumn()) $itensTable = 'pedido_itens';
                } catch (\Exception $e) {
                    $itensTable = '';
                }
                if ($itensTable === '') {
                    try {
                        $st = $pdo->prepare('SHOW TABLES LIKE ?');
                        $st->execute(['pedido_items']);
                        if ($st->fetchColumn()) $itensTable = 'pedido_items';
                    } catch (\Exception $e) {
                        $itensTable = '';
                    }
                }

                if ($itensTable !== '') {
                    $colsItens = [];
                    try {
                        $stmtColsI = $pdo->query('DESCRIBE ' . $itensTable);
                        $colsItens = $stmtColsI ? $stmtColsI->fetchAll(\PDO::FETCH_COLUMN) : [];
                    } catch (\Exception $e) {
                        $colsItens = [];
                    }

                    $colPedido = in_array('pedido_id', $colsItens, true) ? 'pedido_id' : '';
                    $colProduto = in_array('produto_id', $colsItens, true) ? 'produto_id' : '';
                    $colQtd = in_array('quantidade', $colsItens, true) ? 'quantidade' : (in_array('qty', $colsItens, true) ? 'qty' : '');
                    $colVal = in_array('valor_unitario', $colsItens, true) ? 'valor_unitario' : (in_array('price', $colsItens, true) ? 'price' : (in_array('preco', $colsItens, true) ? 'preco' : ''));

                    if ($colPedido !== '' && $colProduto !== '' && $colQtd !== '' && $colVal !== '') {
                        $stmtI = $pdo->prepare('SELECT id, ' . $colProduto . ' AS produto_id, ' . $colQtd . ' AS quantidade, ' . $colVal . ' AS valor_unitario FROM ' . $itensTable . ' WHERE ' . $colPedido . ' = :pid ORDER BY id ASC');
                        $stmtI->bindValue(':pid', (int) $pedidoId, \PDO::PARAM_INT);
                        $stmtI->execute();
                        $existingItens = $stmtI->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                    }
                }
            } catch (\Exception $e) {
                $existingPedido = null;
                $existingItens = [];
            }
        }

        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novo Pedido Manual - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';

        renderAdminSidebarStyles();

        echo '<style>
            #itensTable .prodResults { max-height: 420px !important; }
            #itensTable .prodResults .list-group-item { white-space: normal; }
            #itensTable td { overflow: visible; }
            #itensTable { overflow: visible; }
            .table-responsive { overflow: visible !important; }
        </style></head>
<body>
    <div class="container-fluid">
        <div class="row">';

        renderAdminSidebar('pedidos');

        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Novo Pedido Manual</h1>
                    <div>
                        <a href="/admin/pedidos" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Voltar
                        </a>
                    </div>
                </div>';

        if ($erro !== '') {
            echo '<div class="alert alert-danger">' . htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') . '</div>';
        }

        if ($pedidoId > 0) {
            echo '<div class="alert alert-success">Pedido manual criado com sucesso: <strong>#' . (int) $pedidoId . '</strong></div>';
        }

        echo '<form method="POST" action="/admin/pedidos/novo-manual/salvar" id="formPedidoManual">
                <input type="hidden" name="pedido_manual_token" value="' . htmlspecialchars((string) $formToken, ENT_QUOTES, 'UTF-8') . '">
                <div class="card mb-4">
                    <div class="card-header"><strong>Cliente</strong></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label">Cliente</label>
                                <input type="text" class="form-control" id="cliente_busca" placeholder="Digite nome, e-mail ou switch..." autocomplete="off" required>
                                <div id="cliente_busca_results" class="list-group" style="position:relative; z-index: 1050; display:none;"></div>
                                <select class="form-select" name="cliente_id" id="cliente_id" required style="display:none;">
                                    <option value="">Selecione...</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Moeda</label>
                                <select class="form-select" name="moeda" id="moeda">
                                    <option value="USD" selected>Dólar (USD)</option>
                                    <option value="BRL">Real (BRL)</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Tipo de compra</label>
                                <select class="form-select" name="tipo_compra" id="tipo_compra" required>
                                    <option value="" selected>Selecione...</option>
                                    <option value="online">Online</option>
                                    <option value="offline">Offline</option>
                                </select>
                                <div class="form-text">Obrigatório para pedidos manuais.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header"><strong>Endereço de Entrega</strong></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label class="form-label">CEP</label>
                                <input type="text" class="form-control" name="endereco_entrega_cep" id="endereco_entrega_cep" value="">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Endereço</label>
                                <input type="text" class="form-control" name="endereco_entrega_endereco" id="endereco_entrega_endereco" value="">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Número</label>
                                <input type="text" class="form-control" name="endereco_entrega_numero" id="endereco_entrega_numero" value="">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Complemento</label>
                                <input type="text" class="form-control" name="endereco_entrega_complemento" id="endereco_entrega_complemento" value="">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Bairro</label>
                                <input type="text" class="form-control" name="endereco_entrega_bairro" id="endereco_entrega_bairro" value="">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Cidade</label>
                                <input type="text" class="form-control" name="endereco_entrega_cidade" id="endereco_entrega_cidade" value="">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Estado</label>
                                <input type="text" class="form-control" name="endereco_entrega_estado" id="endereco_entrega_estado" value="">
                            </div>
                            <div class="col-12">
                                <div class="form-text">Se o cliente não tiver endereço cadastrado, preencha aqui para criar e vincular ao pedido manual.</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header"><strong>Pagamento</strong></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Método de Pagamento</label>
                                <select class="form-select" name="forma_pagamento" id="forma_pagamento">
                                    <option value="pix" selected>PIX (Câmbio Real + Câmbio Real Taxas)</option>
                                    <!-- <option value="boleto">Boleto (Câmbio Real + Câmbio Real Taxas)</option> <!-- OCULTO TEMPORARIAMENTE -->
                                    <option value="cartao_credito">Cartão de Crédito</option>
                                    <option value="cartao_debito">Cartão de Débito</option>
                                    <option value="carteira">Carteira</option>
                                    <option value="pagdev">PagDev (offline)</option>
                                </select>
                                <div class="form-text">BRL: valor dos produtos via Câmbio Real, taxas/impostos via AppMax. Para pagamentos offline, será necessário anexar o comprovante no pedido.</div>
                            </div>
                            <div class="col-md-6" id="offlineInfoWrap" style="display:none;">
                                <label class="form-label">Instruções</label>
                                <div class="alert alert-warning mb-0" id="offlineInfoBox"></div>
                            </div>
                        </div>
                        <div class="form-check mt-3">
                            <input class="form-check-input" type="checkbox" value="1" id="sem_comissao" name="sem_comissao">
                            <label class="form-check-label" for="sem_comissao">
                                <i class="fas fa-store me-1 text-muted"></i> Já lançado no vendas.braziliana
                            </label>
                            <div class="form-text">Se marcado, este pedido não será contabilizado nas comissões.</div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <strong>Itens do Pedido</strong>
                        <button type="button" class="btn btn-outline-primary btn-sm" onclick="addItemRow()">
                            <i class="fas fa-plus"></i> Adicionar Produto
                        </button>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-sm" id="itensTable">
                                <thead>
                                    <tr>
                                        <th>Produto</th>
                                        <th style="width:140px">Qtd</th>
                                        <th style="width:160px">Valor</th>
                                        <th style="width:200px">Desconto</th>
                                        <th style="width:60px"></th>
                                        <th style="width:100px" class="text-center">Já comprado</th>
                                        <th style="width:90px">Ações</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-header"><strong>Resumo</strong></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <div class="border rounded p-3 h-100">
                                    <div class="text-muted small">Quantidade de Itens</div>
                                    <div class="fs-5 fw-bold" id="resumoQtdItens">0</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border rounded p-3 h-100">
                                    <div class="text-muted small">Peso Total</div>
                                    <div class="fs-5 fw-bold"><span id="resumoPeso">0.000</span> kg</div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border rounded p-3 h-100">
                                    <div class="text-muted small">Subtotal</div>
                                    <div class="fs-5 fw-bold"><span id="resumoMoedaSymbol">$</span> <span id="resumoSubtotal">0.00</span></div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border rounded p-3 h-100">
                                    <div class="text-muted small">Total</div>
                                    <div class="fs-5 fw-bold"><span id="resumoMoedaSymbol2">$</span> <span id="resumoTotal">0.00</span></div>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <div class="row">
                            <div class="col-md-6 offset-md-6">
                                <div class="d-flex justify-content-between py-1">
                                    <span class="text-muted">Taxa de Serviço</span>
                                    <span><span id="resumoMoedaSymbol3">$</span> <span id="resumoTaxaServico">0.00</span></span>
                                </div>
                                <div class="alert alert-info small mt-2 mb-0" id="pixDiscountInfo" style="display:none;"></div>
                                <div class="d-flex justify-content-between py-1">
                                    <span class="text-muted">Impostos</span>
                                    <span><span id="resumoMoedaSymbol4">$</span> <span id="resumoImpostos">0.00</span></span>
                                </div>
                                <div class="d-flex justify-content-between py-1" id="impostoLocalRow" style="display:none;">
                                    <span class="text-muted">Imposto local (<span id="resumoImpostoLocalPct">0</span>%)</span>
                                    <span><span id="resumoMoedaSymbol7">$</span> <span id="resumoImpostoLocal">0.00</span></span>
                                </div>
                                <div class="d-flex justify-content-between py-1">
                                    <span class="text-muted">Frete</span>
                                    <span id="resumoFreteWrap"><span id="resumoMoedaSymbol5">$</span> <span id="resumoFrete">0.00</span></span>
                                </div>

                                <!-- Desconto na Taxa de Serviço -->
                                <div class="border rounded p-2 mt-2 mb-2" id="descontoGlobalWrap">
                                    <div class="d-flex align-items-center justify-content-between mb-1">
                                        <span class="text-muted small fw-bold"><i class="fas fa-tag me-1"></i>Desconto na Taxa de Serviço</span>
                                    </div>
                                    <div class="d-flex gap-2 align-items-center">
                                        <input type="number" class="form-control form-control-sm" id="descontoGlobalValor" value="" min="0" step="0.01" placeholder="0" style="max-width:100px;">
                                        <select class="form-select form-select-sm" id="descontoGlobalTipo" style="max-width:70px;">
                                            <option value="percentual">%</option>
                                            <option value="fixo">$</option>
                                        </select>
                                        <button type="button" class="btn btn-outline-warning btn-sm" id="btnSolicitarDescontoGlobal" onclick="solicitarDescontoGlobal()" style="font-size:11px;white-space:nowrap;">
                                            <i class="fas fa-tag"></i> Solicitar
                                        </button>
                                    </div>
                                    <input type="hidden" id="descontoGlobalToken" value="">
                                    <div id="descontoGlobalStatus" class="mt-1" style="font-size:11px;"></div>
                                    <div id="descontoGlobalAplicado" class="d-flex justify-content-between py-1 text-success fw-bold" style="display:none !important;">
                                        <span>Desconto aplicado</span>
                                        <span>- <span id="resumoMoedaSymbol8">$</span> <span id="resumoDescontoGlobal">0.00</span></span>
                                    </div>
                                </div>

                                <hr>
                                <div class="d-flex justify-content-between">
                                    <strong>Total</strong>
                                    <strong><span id="resumoMoedaSymbol6">$</span> <span id="resumoTotal2">0.00</span></strong>
                                </div>
                            </div>
                        </div>

                        <input type="hidden" name="subtotal_produtos" id="subtotal_produtos" value="0">
                        <input type="hidden" name="peso_total" id="peso_total" value="0">
                        <input type="hidden" name="desconto_global_tipo" id="desconto_global_tipo" value="">
                        <input type="hidden" name="desconto_global_valor" id="desconto_global_valor" value="0">
                        <input type="hidden" name="desconto_global_aplicado" id="desconto_global_aplicado" value="0">
                        <input type="hidden" name="desconto_global_token" id="desconto_global_token_hidden" value="">
                        <input type="hidden" name="taxa_servico" id="taxa_servico" value="0">
                        <input type="hidden" name="valor_impostos" id="valor_impostos" value="0">
                        <input type="hidden" name="imposto_local" id="imposto_local" value="0">
                        <input type="hidden" name="valor_frete" id="valor_frete" value="0">
                        <input type="hidden" name="valor_total" id="valor_total" value="0">
                    </div>
                </div>

                <div class="d-flex gap-2 mb-4">
                    <button type="submit" class="btn btn-success" id="btnCriarPedidoManual">
                        <i class="fas fa-save"></i> Criar Pedido Manual
                    </button>
                    <button type="button" class="btn btn-outline-secondary" id="btnGerarMensagemOrcamento" onclick="gerarMensagemOrcamento()">
                        <i class="fas fa-comment-dots"></i> Gerar mensagem de orçamento
                    </button>
                </div>
                <div id="createResult" style="display:none;"></div>
                <div id="orcamentoResult" style="display:none;"></div>
            </form>

            <div class="card mb-4" id="linkPagamentoCard">
                <div class="card-header"><strong>Pagamento (<span id="gatewayLabel">Câmbio Real + Câmbio Real Taxas</span>)</strong></div>
                <div class="card-body">
                    <div class="alert alert-info mb-3" id="linkPagamentoInfo">Após criar o pedido manual, clique em <strong>Gerar Link de Pagamento</strong> para gerar os links de cobrança.<br><small class="text-muted">BRL: link de checkout Câmbio Real (produtos) + link de pagamento Câmbio Real Taxas (taxas/impostos). Copie e envie para o cliente.</small></div>
                    <div class="row g-3 align-items-end">
                        <div class="col-md-4" id="billingTypeWrap" style="display:none;">
                            <input type="hidden" id="billingType" value="PIX">
                        </div>
                        <div class="col-md-4">
                            <button type="button" class="btn btn-primary" id="btnGerarLinkPagamento" onclick="gerarLinkPagamento()" disabled>
                                <i class="fas fa-link"></i> Gerar Link de Pagamento
                            </button>
                        </div>
                    </div>
                    <div class="mt-3" id="linkResult" style="display:none;"></div>
                </div>
            </div>
        </main>
    </div>
</div>';

        echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>';
        echo "<script>\n";
        echo 'const PRODUTOS = ' . json_encode($produtos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';' . "\n";
        echo 'let PEDIDO_ID = ' . (int) $pedidoId . ';' . "\n";
        echo 'const EXISTING_PEDIDO = ' . json_encode($existingPedido, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';' . "\n";
        echo 'const EXISTING_ITENS = ' . json_encode($existingItens, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';' . "\n";

        // NCM options para o select de NCM no pedido manual
        $ncmOptions = (new \App\Controllers\AdminProdutosController())->getPublicNcmOptions();
        $ncmOptionsJson = json_encode(array_map(function($code, $label) {
            return ['code' => $code, 'label' => $code . ' - ' . $label];
        }, array_keys($ncmOptions), array_values($ncmOptions)), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        echo 'const NCM_OPTIONS = ' . $ncmOptionsJson . ';' . "\n";

        echo 'const TAXA_SERVICO_POR_KG_BRL = ' . json_encode((float) (new \App\Services\PedidoManualService())->getTaxaServicoPorKgBRL(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';' . "\n";
        echo 'const TAXA_SERVICO_POR_KG_USD = ' . json_encode((float) (new \App\Services\PedidoManualService())->getTaxaServicoPorKgUSD(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';' . "\n";
        echo 'const PIX_DESCONTO_TAXA_SERVICO_PERCENT = ' . json_encode((float) (new \App\Services\PedidoManualService())->getPixDescontoTaxaServicoPercent(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';' . "\n";
        echo 'const USD_BRL_RATE = ' . json_encode((float) (new \App\Services\PedidoManualService())->getTaxaConversaoUSDBRL(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';' . "\n";
        echo 'const ALIQUOTA_ICMS = ' . json_encode((float) (new \App\Services\PedidoManualService())->getAliquota('icms_aliquota'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';' . "\n";

        echo "\n";
        echo <<<'JSCB'
function initClienteBusca(){
    const input = document.getElementById('cliente_busca');
    const results = document.getElementById('cliente_busca_results');
    const sel = document.getElementById('cliente_id');
    if (!input || !results || !sel) return;
    let lastQ = '';
    let t = null;
    function hide(){ results.style.display = 'none'; results.innerHTML = ''; }
    function ensureOption(id, text){
        const v = String(id || '');
        if (!v) return;
        let opt = null;
        for (let i=0;i<sel.options.length;i++){ if (sel.options[i].value === v){ opt = sel.options[i]; break; } }
        if (!opt){ opt = document.createElement('option'); opt.value = v; sel.appendChild(opt); }
        opt.text = String(text || ('Cliente #' + v));
        sel.value = v;
        const ev = new Event('change', { bubbles: true });
        sel.dispatchEvent(ev);
    }
    function render(items){
        results.innerHTML = '';
        if (!items || !items.length){ hide(); return; }
        items.forEach(it => {
            const a = document.createElement('button');
            a.type = 'button';
            a.className = 'list-group-item list-group-item-action';
            a.textContent = String(it.text || '');
            a.dataset.id = String(it.id || '');
            a.addEventListener('click', function(){
                const id = this.dataset.id;
                const text = this.textContent || ('Cliente #' + id);
                ensureOption(id, text);
                input.value = text;
                input.dataset.selectedId = String(id || '');
                hide();
            });
            results.appendChild(a);
        });
        results.style.display = 'block';
    }
    function search(q){
        const qq = String(q || '').trim();
        lastQ = qq;
        if (qq.length < 2){ hide(); return; }
        fetch('/admin/pedidos/novo-manual/clientes?q=' + encodeURIComponent(qq) + '&limit=20')
            .then(r => r.json())
            .then(j => {
                if (!j || !j.ok) { hide(); return; }
                if (String(input.value || '').trim() !== lastQ) return;
                render(j.items || []);
            })
            .catch(() => { hide(); });
    }
    input.addEventListener('input', function(){
        const q = String(this.value || '');
        this.dataset.selectedId = '';
        if (t) clearTimeout(t);
        t = setTimeout(() => search(q), 220);
    });
    input.addEventListener('focus', function(){
        const q = String(this.value || '').trim();
        if (q.length >= 2) search(q);
    });
    input.addEventListener('blur', function(){ setTimeout(hide, 180); });
    window.__ensureClienteOption = ensureOption;
}
JSCB;
        echo "\n";
        echo 'const ALIQUOTA_IPI = ' . json_encode((float) (new \App\Services\PedidoManualService())->getAliquota('ipi_aliquota'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';' . "\n";

        echo <<<'JS'

function formatMoney(v){
    const n = Number(v || 0);
    return n.toFixed(2);
}

function parseMoneyInput(raw){
    let s = String(raw || '').trim();
    if (!s) return 0;
    s = s.replace(/\s+/g, '');
    s = s.replace(/[^0-9,\.\-]/g, '');
    const lastDot = s.lastIndexOf('.');
    const lastComma = s.lastIndexOf(',');
    let decSep = '';
    if (lastDot >= 0 || lastComma >= 0) {
        decSep = (lastDot > lastComma) ? '.' : ',';
    }
    if (decSep) {
        const thousandSep = (decSep === '.') ? ',' : '.';
        s = s.split(thousandSep).join('');
        if (decSep === ',') {
            s = s.replace(',', '.');
        }
    }
    const n = Number(s);
    return isFinite(n) ? n : 0;
}

function formatPeso(v){
    const n = Number(v || 0);
    return n.toFixed(3);
}

function buildProdutoOptions(){
    let html = '<option value="">Selecione...</option>';
    for (const p of PRODUTOS) {
        const id = p.id;
        const name = (p.name || '');
        const sku = (p.sku || '');
        const salePrice = Number(p.sale_price || 0);
        const regularPrice = Number(p.price || 0);
        const effectivePrice = (salePrice > 0 && salePrice < regularPrice) ? salePrice : regularPrice;
        html += `<option value="${id}" data-price="${effectivePrice}">${escapeHtml(name)} (${escapeHtml(sku)}) - ${formatMoney(effectivePrice)}</option>`;
    }
    return html;
}

function getSelectedMoeda(){
    const sel = document.getElementById('moeda');
    const m = sel ? String(sel.value || '').toUpperCase() : 'USD';
    return m === 'BRL' ? 'BRL' : 'USD';
}

function getSymbol(m){
    return m === 'BRL' ? 'R$' : '$';
}

function formatForDisplay(value, moeda){
    const n = Number(value || 0);
    if (moeda === 'BRL') {
        return n.toFixed(2).replace('.', ',');
    }
    return n.toFixed(2);
}

function getUsdBrlRateSafe(){
    const r = Number(USD_BRL_RATE || 0);
    return (isFinite(r) && r > 0) ? r : 1;
}

function convertValueBetweenCurrencies(value, fromMoeda, toMoeda){
    const n = Number(value || 0);
    if (!isFinite(n) || n === 0) return 0;
    if (fromMoeda === toMoeda) return n;
    const rate = getUsdBrlRateSafe();
    if (fromMoeda === 'USD' && toMoeda === 'BRL') return n * rate;
    if (fromMoeda === 'BRL' && toMoeda === 'USD') return n / rate;
    return n;
}

function produtoLabel(p){
    const name = (p && p.name) ? String(p.name) : '';
    const sku = (p && p.sku) ? String(p.sku) : '';
    const salePrice = (p && p.sale_price) ? Number(p.sale_price) : 0;
    const regularPrice = (p && p.price) ? Number(p.price) : 0;
    const price = (salePrice > 0 && salePrice < regularPrice) ? salePrice : regularPrice;
    const partSku = sku ? ` (${sku})` : '';
    const moeda = getSelectedMoeda();
    const sym = getSymbol(moeda);
    const shown = moeda === 'BRL' ? (price * Number(USD_BRL_RATE || 1)) : price;
    return `${name}${partSku} - ${sym} ${formatForDisplay(shown, moeda)}`;
}

function produtoImagem(p){
    const img = (p && p.imagem) ? String(p.imagem) : '';
    if (!img) return '/uploads/produtos/placeholder.jpg';
    return img;
}

function findProdutos(term){
    const t = String(term || '').trim().toLowerCase();
    if (!t) return [];
    const max = 12;
    const res = [];
    for (const p of PRODUTOS) {
        const name = String(p.name || '').toLowerCase();
        const sku = String(p.sku || '').toLowerCase();
        const id = String(p.id || '');
        if (name.includes(t) || sku.includes(t) || id === t) {
            res.push(p);
            if (res.length >= max) break;
        }
    }
    return res;
}

function closeAllProductResults(){
    document.querySelectorAll('.prodResults').forEach(el => {
        el.style.display = 'none';
        el.innerHTML = '';
    });
}

function escapeHtml(str){
    return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}

function updateExtraCamposProduto(tr, prod){
    const wrap = tr ? tr.querySelector('.extraProdutoCampos') : null;
    if (!wrap) return;

    const custoInp = wrap.querySelector('.custoInp');
    let ncmInp = wrap.querySelector('.ncmInp');

    const custoAtual = prod ? Number(prod.custo || prod.cost || prod.custo_produto || prod.valor_custo || 0) : 0;
    const ncmAtual = prod ? String(prod.ncm || prod.ncm_code || '').trim() : '';

    const precisaCusto = !(isFinite(custoAtual) && custoAtual > 0);
    const precisaNcm = (ncmAtual === '');

    if (custoInp) {
        if (precisaCusto) {
            custoInp.style.display = '';
            custoInp.required = true;
        } else {
            custoInp.style.display = 'none';
            custoInp.required = false;
            custoInp.value = String(custoAtual);
        }
    }

    // Substituir input por select com opções de NCM se ainda não foi feito
    if (ncmInp && ncmInp.tagName !== 'SELECT' && typeof NCM_OPTIONS !== 'undefined' && NCM_OPTIONS.length > 0) {
        const sel = document.createElement('select');
        sel.className = ncmInp.className;
        sel.name = ncmInp.name;
        // Copiar atributos
        sel.innerHTML = '<option value="">Selecione NCM...</option>';
        NCM_OPTIONS.forEach(n => {
            const opt = document.createElement('option');
            opt.value = n.code;
            opt.textContent = n.label;
            sel.appendChild(opt);
        });
        // Adicionar campo de busca antes do select
        const searchInp = document.createElement('input');
        searchInp.type = 'text';
        searchInp.className = 'form-control form-control-sm mb-1';
        searchInp.placeholder = 'Buscar NCM...';
        searchInp.addEventListener('input', function(){
            const term = this.value.toLowerCase().trim();
            let firstVisible = null;
            for (let i = 1; i < sel.options.length; i++) {
                const opt = sel.options[i];
                const match = !term || opt.textContent.toLowerCase().includes(term) || opt.value.includes(term);
                opt.style.display = match ? '' : 'none';
                if (match && !firstVisible) firstVisible = opt;
            }
            if (firstVisible && term) {
                sel.value = firstVisible.value;
            }
        });
        ncmInp.parentNode.insertBefore(searchInp, ncmInp);
        ncmInp.parentNode.replaceChild(sel, ncmInp);
        ncmInp = sel;
    }

    if (ncmInp) {
        if (precisaNcm) {
            ncmInp.style.display = '';
            ncmInp.required = true;
            ncmInp.value = '';
            // Mostrar campo de busca
            const searchEl = ncmInp.previousElementSibling;
            if (searchEl && searchEl.placeholder === 'Buscar NCM...') searchEl.style.display = '';
        } else {
            ncmInp.style.display = 'none';
            ncmInp.required = false;
            ncmInp.value = ncmAtual;
            // Esconder campo de busca
            const searchEl = ncmInp.previousElementSibling;
            if (searchEl && searchEl.placeholder === 'Buscar NCM...') searchEl.style.display = 'none';
        }
    }

    const showWrap = precisaCusto || precisaNcm;
    wrap.style.display = showWrap ? '' : 'none';
}

function addItemRow(){
    const tbody = document.querySelector('#itensTable tbody');
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td>
            <div class="d-flex align-items-center gap-2 position-relative">
                <img src="/uploads/produtos/placeholder.jpg" class="rounded border" style="width:34px;height:34px;object-fit:cover" alt="">
                <div class="flex-grow-1">
                    <input type="hidden" class="produtoIdInp" name="produto_id[]" value="" required>
                    <input type="text" class="form-control form-control-sm produtoSearch" placeholder="Buscar produto..." autocomplete="off" oninput="onProdutoSearchInput(this)" onfocus="onProdutoSearchInput(this)">
                    <div class="list-group position-absolute w-100 prodResults" style="z-index: 1050; display:none; max-height: 420px; overflow:auto;"></div>
                    <div class="row g-2 mt-2 extraProdutoCampos" style="display:none;">
                        <div class="col-6">
                            <input type="text" class="form-control form-control-sm custoInp" name="produto_custo[]" value="" placeholder="Custo (obrigatório)">
                        </div>
                        <div class="col-6">
                            <input type="text" class="form-control form-control-sm ncmInp" name="produto_ncm[]" value="" placeholder="NCM (obrigatório)">
                        </div>
                    </div>
                </div>
            </div>
        </td>
        <td>
            <input type="number" class="form-control form-control-sm qtdInp" name="quantidade[]" min="1" value="1" onchange="calcTotal()" required>
        </td>
        <td>
            <input type="text" class="form-control form-control-sm valorInp" name="valor_unitario[]" value="0.00" onchange="calcTotal()" required>
        </td>
        <td>
            <div class="descontoWrap">
                <div class="input-group input-group-sm">
                    <input type="number" class="form-control form-control-sm descontoValorInp" value="" min="0" step="0.01" placeholder="0" disabled>
                    <select class="form-select form-select-sm descontoTipoInp" style="max-width:70px" disabled>
                        <option value="percentual">%</option>
                        <option value="fixo">$</option>
                    </select>
                </div>
                <input type="hidden" class="descontoTokenInp" value="">
                <div class="descontoStatus mt-1" style="font-size:11px;"></div>
                <button type="button" class="btn btn-outline-warning btn-sm mt-1 btnSolicitarDesconto" onclick="solicitarDesconto(this)" style="font-size:11px;" disabled>
                    <i class="fas fa-tag"></i> Solicitar desconto
                </button>
            </div>
        </td>
        <td>
            <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeRow(this)">
                <i class="fas fa-trash"></i>
            </button>
        </td>
        <td class="text-center">
            <div class="form-check d-flex justify-content-center">
                <input type="hidden" class="jaCompradoHidden" name="ja_comprado[]" value="0">
                <input type="checkbox" class="form-check-input jaCompradoInp" value="1" title="Marcar se já foi comprado" onchange="this.previousElementSibling.value = this.checked ? '1' : '0'">
            </div>
        </td>
    `;
    tbody.appendChild(tr);
    calcTotal();
}

function validateProdutosObrigatorios(){
    const rows = document.querySelectorAll('#itensTable tbody tr');
    for (const tr of rows) {
        const pid = Number(tr.querySelector('.produtoIdInp')?.value || 0);
        if (!pid) continue;
        const wrap = tr.querySelector('.extraProdutoCampos');
        if (!wrap || wrap.style.display === 'none') continue;

        const custoInp = wrap.querySelector('.custoInp');
        if (custoInp && custoInp.required) {
            const v = Number(String(custoInp.value || '').replace(',', '.'));
            if (!isFinite(v) || v <= 0) {
                custoInp.focus();
                alert('Informe o custo do produto selecionado (maior que 0).');
                return false;
            }
        }

        const ncmInp = wrap.querySelector('.ncmInp');
        if (ncmInp && ncmInp.required) {
            const ncm = String(ncmInp.value || '').trim();
            if (!ncm) {
                ncmInp.focus();
                alert('Informe o NCM do produto selecionado.');
                return false;
            }
        }
    }
    return true;
}

function prefillRowFromExisting(tr, item){
    if (!tr || !item) return;
    const pid = Number(item.produto_id || 0);
    const qtd = Number(item.quantidade || 0);
    const unit = Number(item.valor_unitario || 0);

    const prod = PRODUTOS.find(p => Number(p.id) === pid);

    const hidden = tr.querySelector('.produtoIdInp');
    const search = tr.querySelector('.produtoSearch');
    const imgEl = tr.querySelector('img');
    const valor = tr.querySelector('.valorInp');
    const qtdEl = tr.querySelector('.qtdInp');

    if (hidden) hidden.value = pid ? String(pid) : '';
    if (search) {
        if (prod) {
            search.value = produtoLabel(prod);
        } else {
            search.value = pid ? ('Produto #' + String(pid)) : '';
        }
    }
    if (imgEl && prod) imgEl.src = produtoImagem(prod);
    if (valor) valor.value = formatMoney(unit);
    if (qtdEl) qtdEl.value = String(qtd > 0 ? qtd : 1);

    updateExtraCamposProduto(tr, prod || null);

    habilitarDescontoRow(tr);

    const resultsEl = tr.querySelector('.prodResults');
    if (resultsEl) {
        resultsEl.style.display = 'none';
        resultsEl.innerHTML = '';
    }
}

function removeRow(btn){
    const tr = btn.closest('tr');
    if (tr) tr.remove();
    calcTotal();
}

function updateLinkVisibility(){
    const moeda = getSelectedMoeda();
    const fpSel = document.getElementById('forma_pagamento');
    const linkCard = document.getElementById('linkPagamentoCard');
    const linkInfo = document.getElementById('linkPagamentoInfo');
    const linkResult = document.getElementById('linkResult');
    const billingWrap = document.getElementById('billingTypeWrap');
    const billingType = document.getElementById('billingType');
    const gatewayLabel = document.getElementById('gatewayLabel');

    const v = fpSel ? String(fpSel.value || '') : '';
    const isOffline = (v === 'pagdev');
    const isCarteira = (v === 'carteira');

    // Regras:
    // - Offline (PagDev) ou Carteira: não gera link
    // - BRL: Câmbio Real (link checkout produtos) + AppMax PIX (taxas) - gera link split
    // - BRL cartao: também gera link (Câmbio Real checkout aceita cartão)
    // - USD: Stripe (gera link de checkout)
    const isCartao = (v === 'cartao_credito' || v === 'cartao_debito');
    const canShowLinkCard = (!isOffline && !isCarteira && (moeda === 'BRL' || moeda === 'USD'));
    const shouldShowBillingType = false; // billingType não é mais necessário

    // Atualizar gateway label
    if (gatewayLabel) {
        if (moeda === 'BRL') {
            gatewayLabel.textContent = 'Câmbio Real + AppMax';
        } else {
            gatewayLabel.textContent = 'Stripe';
        }
    }

    if (billingWrap) {
        billingWrap.style.display = 'none';
    }

    if (linkCard) {
        linkCard.style.display = canShowLinkCard ? '' : 'none';
    }
    if (linkInfo) {
        if (isOffline || isCarteira) {
            linkInfo.style.display = 'none';
        } else {
            linkInfo.style.display = '';
            linkInfo.innerHTML = 'Após criar o pedido manual, clique em <strong>Gerar Link de Pagamento</strong> para gerar os links de cobrança.<br><small class="text-muted">BRL: link de checkout Câmbio Real (produtos) + link de pagamento Câmbio Real Taxas (taxas/impostos). Copie e envie para o cliente.</small>';
        }
    }
    if (linkResult && !canShowLinkCard) {
        linkResult.style.display = 'none';
        linkResult.innerHTML = '';
    }
}

function onProdutoSearchInput(inp){
    const tr = inp.closest('tr');
    if (!tr) return;
    const resultsEl = tr.querySelector('.prodResults');
    if (!resultsEl) return;

    const term = inp.value;
    const items = findProdutos(term);
    if (!term || items.length === 0) {
        resultsEl.style.display = 'none';
        resultsEl.innerHTML = '';
        return;
    }

    resultsEl.innerHTML = items.map(p => {
        const img = produtoImagem(p);
        const label = produtoLabel(p);
        const pid = Number(p.id || 0);
        const sp = Number(p.sale_price || 0);
        const rp = Number(p.price || 0);
        const ep = (sp > 0 && sp < rp) ? sp : rp;
        return `
            <button type="button" class="list-group-item list-group-item-action d-flex align-items-center gap-2" onclick="selectProdutoFromSearch(this, ${pid})">
                <img src="${escapeHtml(img)}" class="rounded border" style="width:40px;height:40px;object-fit:cover" alt="">
                <div class="text-start">
                    <div class="fw-semibold">${escapeHtml(String(p.name || ''))}</div>
                    <div class="small text-muted">${sp > 0 && sp < rp ? '<del>R$ ' + formatMoney(rp) + '</del> ' : ''}R$ ${formatMoney(ep)}</div>
                </div>
            </button>
        `;
    }).join('');

    resultsEl.style.display = 'block';
}

function selectProdutoFromSearch(btn, produtoId){
    const tr = btn.closest('tr');
    if (!tr) return;
    const prod = PRODUTOS.find(p => Number(p.id) === Number(produtoId));
    if (!prod) return;

    const hidden = tr.querySelector('.produtoIdInp');
    const search = tr.querySelector('.produtoSearch');
    const imgEl = tr.querySelector('img');
    const valor = tr.querySelector('.valorInp');
    if (hidden) hidden.value = String(prod.id);
    if (search) search.value = produtoLabel(prod);
    if (imgEl) imgEl.src = produtoImagem(prod);
    if (valor) {
        const moeda = getSelectedMoeda();
        const salePrice = Number(prod.sale_price || 0);
        const regularPrice = Number(prod.price || 0);
        const unitUsd = (salePrice > 0 && salePrice < regularPrice) ? salePrice : regularPrice;
        const shown = (moeda === 'BRL') ? convertValueBetweenCurrencies(unitUsd, 'USD', 'BRL') : unitUsd;
        valor.value = formatMoney(shown);
    }

    const resultsEl = tr.querySelector('.prodResults');
    if (resultsEl) {
        resultsEl.style.display = 'none';
        resultsEl.innerHTML = '';
    }

    updateExtraCamposProduto(tr, prod);

    habilitarDescontoRow(tr);

    calcTotal();
}

function calcTotal(){
    const moeda = getSelectedMoeda();
    const sym = getSymbol(moeda);
    let subtotal = 0;
    let pesoTotal = 0;
    let qtdItens = 0;
    const itensPayload = [];
    const rows = document.querySelectorAll('#itensTable tbody tr');
    rows.forEach(r => {
        const qtd = Number(r.querySelector('.qtdInp')?.value || 0);
        const raw = String(r.querySelector('.valorInp')?.value || '0');
        const val = parseMoneyInput(raw);
        const pid = Number(r.querySelector('.produtoIdInp')?.value || 0);
        const prod = PRODUTOS.find(p => Number(p.id) === pid);
        const peso = prod ? Number(prod.peso || prod.weight || prod.peso_kg || prod.product_weight || 0) : 0;
        if (qtd > 0 && val >= 0) {
            subtotal += (qtd * val);
            qtdItens += qtd;
            pesoTotal += (peso * qtd);
            if (pid > 0) {
                itensPayload.push({ produto_id: pid, quantidade: qtd, valor_unitario: val });
            }
        }
    });

    // Atualiza imediatamente parte visual básica (subtotal/peso/itens)
    document.getElementById('resumoQtdItens').textContent = String(qtdItens);
    document.getElementById('resumoPeso').textContent = formatPeso(pesoTotal);
    const setSym = (id) => { const el = document.getElementById(id); if (el) el.textContent = sym; };
    ['resumoMoedaSymbol','resumoMoedaSymbol2','resumoMoedaSymbol3','resumoMoedaSymbol4','resumoMoedaSymbol5','resumoMoedaSymbol6','resumoMoedaSymbol7'].forEach(setSym);
    document.getElementById('resumoSubtotal').textContent = formatForDisplay(subtotal, moeda);

    // Chama backend para calcular taxa/impostos/frete/total com a mesma regra do carrinho/checkout
    window.__CALC_SEQ__ = (window.__CALC_SEQ__ || 0) + 1;
    const seq = window.__CALC_SEQ__;

    const fd = new FormData();
    fd.append('moeda', moeda);
    fd.append('subtotal', String(subtotal.toFixed(2)));
    fd.append('peso_total', String(pesoTotal.toFixed(3)));
    fd.append('itens', JSON.stringify(itensPayload));
    fetch('/admin/pedidos/novo-manual/calcular-resumo', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (seq !== window.__CALC_SEQ__) return;
            if (!data || !data.success) {
                throw new Error((data && data.error) ? data.error : 'Falha ao calcular resumo');
            }

            const frete = Number(data.frete || 0);
            const taxaServico = Number(data.taxa_servico || 0);
            const impostos = Number(data.impostos || 0);
            const impostoLocal = Number(data.imposto_local || 0);
            const impostoLocalPct = Number(data.imposto_local_percent || 0);
            const total = Number(data.total || 0);

            const pixPct = getPixPct();
            const moedaSel = getSelectedMoeda();
            const billingSel = document.getElementById('billingType');
            const billingType = billingSel ? String(billingSel.value || '').toUpperCase() : '';

            let taxaServicoShown = taxaServico;
            let totalShown = total;

            if (moedaSel === 'BRL' && billingType === 'PIX' && pixPct > 0) {
                taxaServicoShown = Math.max(0, taxaServico * (1 - (pixPct / 100)));
                totalShown = Math.max(0, total - (taxaServico - taxaServicoShown));
            }

            const pesoBack = Number(data.peso_total || 0);
            const pesoEl = document.getElementById('resumoPeso');
            if (pesoEl) {
                pesoEl.textContent = formatPeso(pesoBack);
            }

            document.getElementById('resumoTaxaServico').textContent = formatForDisplay(taxaServicoShown, moeda);
            document.getElementById('resumoImpostos').textContent = formatForDisplay(impostos, moeda);

            // Imposto local
            const ilRow = document.getElementById('impostoLocalRow');
            if (ilRow) {
                if (impostoLocal > 0) {
                    ilRow.style.display = '';
                    document.getElementById('resumoImpostoLocal').textContent = formatForDisplay(impostoLocal, moeda);
                    document.getElementById('resumoImpostoLocalPct').textContent = String(impostoLocalPct);
                } else {
                    ilRow.style.display = 'none';
                }
            }

            const freteWrap = document.getElementById('resumoFreteWrap');
            if (Number(frete) <= 0) {
                if (freteWrap) freteWrap.textContent = 'Frete grátis';
            } else {
                if (freteWrap) freteWrap.innerHTML = `<span id="resumoMoedaSymbol5">${escapeHtml(sym)}</span> <span id="resumoFrete">${escapeHtml(formatForDisplay(frete, moeda))}</span>`;
                const rf = document.getElementById('resumoFrete');
                if (rf) rf.textContent = formatForDisplay(frete, moeda);
            }

            document.getElementById('resumoTotal').textContent = formatForDisplay(totalShown, moeda);
            document.getElementById('resumoTotal2').textContent = formatForDisplay(totalShown, moeda);

            // Aplicar desconto global (se aprovado)
            const dgAplicado = window.__DESCONTO_GLOBAL_APROVADO__ ? window.__DESCONTO_GLOBAL_VALOR_APLICADO__ : 0;
            if (dgAplicado > 0) {
                // Recalcular desconto sobre base atualizada (taxa pode ter mudado)
                const dgTipo = document.getElementById('descontoGlobalTipo')?.value || 'percentual';
                const dgVal = parseFloat(document.getElementById('descontoGlobalValor')?.value || '0');
                const dgBase = taxaServicoShown;
                let dgReal = dgTipo === 'percentual' ? dgBase * (dgVal / 100) : dgVal;
                dgReal = Math.min(dgReal, dgBase);
                dgReal = Math.round(dgReal * 100) / 100;
                window.__DESCONTO_GLOBAL_VALOR_APLICADO__ = dgReal;
                document.getElementById('desconto_global_aplicado').value = dgReal.toFixed(2);

                const totalComDesconto = Math.max(0, totalShown - dgReal);
                document.getElementById('resumoTotal').textContent = formatForDisplay(totalComDesconto, moeda);
                document.getElementById('resumoTotal2').textContent = formatForDisplay(totalComDesconto, moeda);
                totalShown = totalComDesconto;

                const dgEl = document.getElementById('resumoDescontoGlobal');
                if (dgEl) dgEl.textContent = formatForDisplay(dgReal, moeda);
                const aplicadoEl = document.getElementById('descontoGlobalAplicado');
                if (aplicadoEl) aplicadoEl.style.cssText = 'display:flex !important;';
            }

            const setVal = (id, v) => {
                const el = document.getElementById(id);
                if (el) el.value = String(v);
            };
            setVal('subtotal_produtos', Number(data.subtotal || subtotal).toFixed(2));
            setVal('peso_total', Number(data.peso_total || pesoTotal).toFixed(3));
            setVal('taxa_servico', taxaServicoShown.toFixed(2));
            setVal('valor_impostos', impostos.toFixed(2));
            setVal('imposto_local', impostoLocal.toFixed(2));
            setVal('valor_frete', frete.toFixed(2));
            setVal('valor_total', totalShown.toFixed(2));

            const pixBox = document.getElementById('pixDiscountInfo');
            if (pixBox) {
                if (moedaSel === 'BRL' && pixPct > 0) {
                    const info = `PIX: desconto de ${pixPct.toFixed(2)}% na taxa de serviço. Taxa com desconto: ${getSymbol(moedaSel)} ${formatForDisplay(taxaServicoShown, moedaSel)}.`;
                    pixBox.textContent = info;
                    pixBox.style.display = '';
                } else {
                    pixBox.textContent = '';
                    pixBox.style.display = 'none';
                }
            }
        })
        .catch(_err => {
            // fallback simples (não quebra o formulário)
            const frete = 0;
            const taxaServico = 0;
            const impostos = 0;
            const total = subtotal;
            document.getElementById('resumoTaxaServico').textContent = formatForDisplay(taxaServico, moeda);
            document.getElementById('resumoImpostos').textContent = formatForDisplay(impostos, moeda);
            document.getElementById('resumoTotal').textContent = formatForDisplay(total, moeda);
            document.getElementById('resumoTotal2').textContent = formatForDisplay(total, moeda);
            const ilRowFb = document.getElementById('impostoLocalRow');
            if (ilRowFb) ilRowFb.style.display = 'none';
            const setVal = (id, v) => {
                const el = document.getElementById(id);
                if (el) el.value = String(v);
            };
            setVal('subtotal_produtos', subtotal.toFixed(2));
            setVal('peso_total', pesoTotal.toFixed(3));
            setVal('taxa_servico', taxaServico.toFixed(2));
            setVal('valor_impostos', impostos.toFixed(2));
            setVal('valor_frete', frete.toFixed(2));
            setVal('valor_total', total.toFixed(2));

            const pixBox = document.getElementById('pixDiscountInfo');
            if (pixBox) {
                pixBox.textContent = '';
                pixBox.style.display = 'none';
            }
        });
}

function getSelectedClienteLabel(){
    const sel = document.getElementById('cliente_id');
    if (!sel) return '';
    const opt = sel.options && sel.selectedIndex >= 0 ? sel.options[sel.selectedIndex] : null;
    if (!opt) return '';
    return String(opt.text || '').trim();
}

function formatBRL(v){
    const n = Number(v || 0);
    return n.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
}

function getPixPct(){
    const p = Number(PIX_DESCONTO_TAXA_SERVICO_PERCENT || 0);
    if (!isFinite(p) || p <= 0) return 0;
    return Math.max(0, Math.min(100, p));
}

function gerarMensagemOrcamento(){
    try { calcTotal(); } catch (e) {}

    const cliente = getSelectedClienteLabel();
    const nomeCliente = cliente ? cliente.split('(')[0].trim() : 'Cliente';

    const itens = [];
    document.querySelectorAll('#itensTable tbody tr').forEach(r => {
        const qtd = Number(r.querySelector('.qtdInp')?.value || 0);
        const val = Number(String(r.querySelector('.valorInp')?.value || '0').replace(',', '.'));
        const pid = Number(r.querySelector('.produtoIdInp')?.value || 0);
        if (!pid || qtd <= 0) return;
        const prod = PRODUTOS.find(p => Number(p.id) === pid);
        const nome = prod ? String(prod.name || '') : ('Produto #' + String(pid));
        const sku = prod ? String(prod.sku || '') : '';
        const subtotal = qtd * val;
        itens.push({ nome, sku, qtd, val, subtotal });
    });

    const subtotal = Number(document.getElementById('subtotal_produtos')?.value || 0);
    const pesoTotal = Number(document.getElementById('peso_total')?.value || 0);
    const taxaServico = Number(document.getElementById('taxa_servico')?.value || 0);
    const impostos = Number(document.getElementById('valor_impostos')?.value || 0);
    const impostoLocal = Number(document.getElementById('imposto_local')?.value || 0);
    const frete = Number(document.getElementById('valor_frete')?.value || 0);
    const total = Number(document.getElementById('valor_total')?.value || 0);

    const now = new Date();
    const dt = now.toLocaleDateString('pt-BR') + ' ' + now.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });

    let msg = '';
    msg += `Olá, ${nomeCliente}!\n\n`;
    msg += `Segue o orçamento solicitado (${dt}):\n\n`;
    msg += `Itens:\n`;

    if (itens.length === 0) {
        msg += `- (nenhum item selecionado)\n`;
    } else {
        itens.forEach((it, idx) => {
            const skuTxt = it.sku ? ` | SKU: ${it.sku}` : '';
            msg += `${idx + 1}) ${it.nome}${skuTxt}\n`;
            msg += `   Qtd: ${it.qtd} | Unitário: ${formatBRL(it.val)} | Subtotal: ${formatBRL(it.subtotal)}\n`;
        });
    }

    msg += `\nResumo:\n`;
    msg += `- Subtotal dos produtos: ${formatBRL(subtotal)}\n`;
    msg += `- Peso total: ${Number(pesoTotal || 0).toFixed(3)} kg\n`;
    msg += `- Taxa de serviço: ${formatBRL(taxaServico)}\n`;
    const pixPct = getPixPct();
    if (pixPct > 0) {
        const taxaPix = Math.max(0, Number(taxaServico || 0) * (1 - (pixPct / 100)));
        msg += `- PIX: desconto de ${pixPct.toFixed(2)}% na taxa de serviço (taxa com desconto: ${formatBRL(taxaPix)})\n`;
    }
    msg += `- Impostos: ${formatBRL(impostos)}\n`;
    if (impostoLocal > 0) {
        const ilPctEl = document.getElementById('resumoImpostoLocalPct');
        const ilPctTxt = ilPctEl ? ilPctEl.textContent : '';
        msg += `- Imposto local${ilPctTxt ? ' (' + ilPctTxt + '%)' : ''}: ${formatBRL(impostoLocal)}\n`;
    }
    msg += `- Frete: ${Number(frete || 0) <= 0 ? 'Frete grátis' : formatBRL(frete)}\n`;
    msg += `- Total: ${formatBRL(total)}\n\n`;
    msg += `Se desejar, posso gerar o link de pagamento e te enviar aqui.\n`;

    const out = document.getElementById('orcamentoResult');
    if (out) {
        out.style.display = 'block';
        out.innerHTML = `<div class="alert alert-info d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div><strong>Mensagem de orçamento gerada.</strong> Clique para copiar.</div>
            <button type="button" class="btn btn-sm btn-dark" onclick="copiarTextoOrcamento()"><i class="fas fa-copy"></i> Copiar</button>
        </div>
        <textarea class="form-control" id="orcamentoTexto" rows="10" style="font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;">${escapeHtml(msg)}</textarea>`;
    }
    window.__ORCAMENTO_MSG__ = msg;
}

function copiarTextoOrcamento(){
    const msg = String(window.__ORCAMENTO_MSG__ || '');
    if (!msg) return;

    const ok = () => {
        const out = document.getElementById('orcamentoResult');
        if (out) {
            out.querySelector('.alert')?.classList.remove('alert-info');
            out.querySelector('.alert')?.classList.add('alert-success');
            const d = out.querySelector('.alert > div');
            if (d) d.innerHTML = '<strong>Copiado!</strong> A mensagem foi enviada para a área de transferência.';
        }
    };
    const fail = () => {
        const ta = document.getElementById('orcamentoTexto');
        if (ta) {
            ta.focus();
            ta.select();
        }
    };

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(msg).then(ok).catch(() => {
            try {
                const ta = document.getElementById('orcamentoTexto');
                if (ta) {
                    ta.focus();
                    ta.select();
                    document.execCommand('copy');
                    ok();
                } else {
                    fail();
                }
            } catch (e) {
                fail();
            }
        });
        return;
    }

    try {
        const ta = document.getElementById('orcamentoTexto');
        if (ta) {
            ta.focus();
            ta.select();
            document.execCommand('copy');
            ok();
        } else {
            fail();
        }
    } catch (e) {
        fail();
    }
}

function copiarLinkPagamento(){
    const txt = String(window.__PAGAMENTO_LINK__ || '').trim();
    if (!txt) return;

    const ok = () => {
        const out = document.getElementById('linkResult');
        if (out) {
            const alertEl = out.querySelector('.alert');
            if (alertEl) {
                alertEl.classList.remove('alert-info');
                alertEl.classList.add('alert-success');
            }
            const d = out.querySelector('.alert > div');
            if (d) d.innerHTML = '<strong>Copiado!</strong> Link de pagamento copiado para a área de transferência.';
        }
    };

    // Fallback robusto que funciona em HTTP e HTTPS
    const copyFallback = () => {
        try {
            const ta = document.getElementById('linkPagamentoTexto');
            if (ta) {
                ta.focus();
                ta.select();
                ta.setSelectionRange(0, ta.value.length);
                const result = document.execCommand('copy');
                if (result) { ok(); return; }
            }
        } catch(e) {}
        // Último fallback: criar textarea temporária
        try {
            const tmp = document.createElement('textarea');
            tmp.value = txt;
            tmp.style.cssText = 'position:fixed;left:-9999px;top:-9999px;opacity:0;';
            document.body.appendChild(tmp);
            tmp.focus();
            tmp.select();
            tmp.setSelectionRange(0, tmp.value.length);
            document.execCommand('copy');
            document.body.removeChild(tmp);
            ok();
        } catch(e) {
            // Se nada funcionar, pelo menos selecionar o texto
            const ta = document.getElementById('linkPagamentoTexto');
            if (ta) { ta.focus(); ta.select(); }
        }
    };

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(txt).then(ok).catch(copyFallback);
        return;
    }
    copyFallback();
}

function gerarLinkPagamento(){
    const moeda = getSelectedMoeda();
    let bt = 'CREDIT_CARD';
    if (moeda === 'BRL') {
        // Mapear forma de pagamento selecionada para billingType da API
        const fpSel = document.getElementById('forma_pagamento');
        const fp = fpSel ? String(fpSel.value || '').toLowerCase() : '';
        if (fp === 'cartao_credito' || fp === 'cartao_debito') {
            bt = 'CREDIT_CARD';
        } else if (fp === 'boleto') {
            bt = 'BOLETO';
        } else {
            bt = 'PIX'; // pix ou qualquer outro método BRL usa PIX nas taxas
        }
    }

    // Garantir que os hidden inputs estejam atualizados
    try { calcTotal(); } catch (e) {}

    const el = document.getElementById('linkResult');
    el.style.display = 'block';
    el.innerHTML = `<div class="alert alert-info">Processando...</div>`;

    if (!PEDIDO_ID || Number(PEDIDO_ID) <= 0) {
        el.innerHTML = `<div class="alert alert-warning">Crie o pedido manual antes de gerar o link de pagamento.</div>`;
        return;
    }

    const fd = new FormData();
    fd.append('pedido_id', String(PEDIDO_ID));
    fd.append('billingType', bt);
    fetch('/admin/pedidos/novo-manual/gerar-link', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (data && data.success) {
                const isSplit = !!data.split;

                const buildSection = function(title, obj){
                    obj = obj || {};
                    const url = String(obj.init_point || obj.invoiceUrl || obj.invoice_url || '').trim();
                    const pixPayload = String((obj.pix && obj.pix.payload) ? obj.pix.payload : '').trim();
                    const pixImg = String((obj.pix && obj.pix.encodedImage) ? obj.pix.encodedImage : '').trim();
                    const bankSlipUrl = String(obj.bankSlipUrl || '').trim();
                    const digitableLine = String(obj.digitableLine || '').trim();

                    const displayText = url || pixPayload || bankSlipUrl || digitableLine;
                    const textToCopy = displayText;

                    let actions = '';
                    if (url) {
                        actions = `<a class="btn btn-sm btn-outline-dark" href="${escapeHtml(url)}" target="_blank" rel="noopener"><i class="fas fa-external-link-alt"></i> Abrir</a>`;
                    } else if (bankSlipUrl) {
                        actions = `<a class="btn btn-sm btn-outline-dark" href="${escapeHtml(bankSlipUrl)}" target="_blank" rel="noopener"><i class="fas fa-external-link-alt"></i> Abrir</a>`;
                    }
                    actions += `<button type="button" class="btn btn-sm btn-dark" onclick="copiarTexto(${JSON.stringify(textToCopy)})"><i class="fas fa-copy"></i> Copiar</button>`;

                    let extra = '';
                    if (pixImg) {
                        extra += `<div class="mt-3"><img alt="QR Code PIX" style="max-width:220px;width:100%;height:auto" src="data:image/png;base64,${escapeHtml(pixImg)}" /></div>`;
                    }

                    return `<div class="border rounded p-3 mb-3">
                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div><strong>${escapeHtml(title)}</strong></div>
                            <div class="d-flex gap-2">${actions}</div>
                        </div>
                        <textarea class="form-control mt-2" rows="${pixPayload ? 4 : 2}" style="font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;" readonly>${escapeHtml(displayText)}</textarea>
                        ${extra}
                    </div>`;
                };

                if (isSplit) {
                    const produto = data.produto || null;
                    const taxa = data.taxa || null;
                    const isStripe = (data.moeda && data.moeda !== 'BRL');

                    const label1 = isStripe
                        ? 'Pagamento 1: Produtos (Stripe — Cartão de Crédito)'
                        : 'Pagamento 1: Produtos (Câmbio Real — Link de Checkout)';
                    const label2 = isStripe
                        ? 'Pagamento 2: Taxas, Impostos e Imposto Local (Stripe — Cartão de Crédito)'
                        : 'Pagamento 2: Taxas e Impostos (Câmbio Real Taxas — Link de Pagamento)';
                    const alertMsg = isStripe
                        ? '<strong>Links Stripe gerados.</strong> Copie e envie para o cliente (link de produtos + link de taxas/impostos).'
                        : '<strong>Links gerados.</strong> Copie e envie para o cliente (link de checkout Câmbio Real para produtos + link de pagamento Câmbio Real Taxas para taxas).';

                    el.innerHTML = `<div class="alert alert-success">${alertMsg}</div>
                    ${produto ? buildSection(label1, produto) : ''}
                    ${taxa ? buildSection(label2, taxa) : ''}
                    <div class="small text-muted mt-2">Se precisar, você pode ajustar o pedido e gerar novamente.</div>`;
                } else {
                    const url = String(data.invoiceUrl || '').trim();
                    const pixPayload = String((data.pix && data.pix.payload) ? data.pix.payload : '').trim();
                    const pixImg = String((data.pix && data.pix.encodedImage) ? data.pix.encodedImage : '').trim();
                    const bankSlipUrl = String(data.bankSlipUrl || '').trim();
                    const digitableLine = String(data.digitableLine || '').trim();

                    const textToCopy = (url || pixPayload || bankSlipUrl || digitableLine);
                    window.__PAGAMENTO_LINK__ = textToCopy;

                    let actions = '';
                    if (url) {
                        actions = `<a class="btn btn-sm btn-outline-dark" href="${escapeHtml(url)}" target="_blank" rel="noopener"><i class="fas fa-external-link-alt"></i> Abrir</a>`;
                    } else if (bankSlipUrl) {
                        actions = `<a class="btn btn-sm btn-outline-dark" href="${escapeHtml(bankSlipUrl)}" target="_blank" rel="noopener"><i class="fas fa-external-link-alt"></i> Abrir</a>`;
                    }
                    actions += `<button type="button" class="btn btn-sm btn-dark" onclick="copiarLinkPagamento()"><i class="fas fa-copy"></i> Copiar</button>`;

                    const headerMsg = url ? 'Link gerado.' : (pixPayload ? 'PIX gerado.' : (bankSlipUrl ? 'Boleto gerado.' : 'Pagamento gerado.'));

                    let extra = '';
                    if (pixImg) {
                        extra += `<div class="mt-3"><img alt="QR Code PIX" style="max-width:220px;width:100%;height:auto" src="data:image/png;base64,${escapeHtml(pixImg)}" /></div>`;
                    }

                    const displayText = url || pixPayload || bankSlipUrl || digitableLine;

                    el.innerHTML = `<div class="alert alert-info d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <strong>${headerMsg}</strong> Agora é só copiar e enviar para o cliente.
                        </div>
                        <div class="d-flex gap-2">
                            ${actions}
                        </div>
                    </div>
                    <textarea class="form-control" id="linkPagamentoTexto" rows="${pixPayload ? 4 : 2}" style="font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', 'Courier New', monospace;" readonly>${escapeHtml(displayText)}</textarea>
                    ${extra}
                    <div class="small text-muted mt-2">Se precisar, você pode ajustar o pedido e gerar outro link.</div>`;
                }
            } else {
                el.innerHTML = `<div class="alert alert-danger">Erro: ${escapeHtml((data && data.error) ? data.error : 'Falha ao gerar link')}</div>`;
            }
        })
        .catch(err => {
            el.innerHTML = `<div class="alert alert-danger">Erro: ${escapeHtml(err && err.message ? err.message : String(err))}</div>`;
        });
}

function copiarTexto(txt){
    try {
        const v = String(txt || '').trim();
        if (!v) {
            alert('Nada para copiar.');
            return;
        }
        if (navigator && navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(v).then(() => {
                alert('Copiado!');
            }).catch(() => {
                fallbackCopyTextToClipboard(v);
            });
            return;
        }
    } catch(e) {}
    fallbackCopyTextToClipboard(String(txt || ''));
}

function fallbackCopyTextToClipboard(text){
    try {
        const ta = document.createElement('textarea');
        ta.value = String(text || '');
        ta.style.cssText = 'position:fixed;left:-9999px;top:-9999px;opacity:0;';
        document.body.appendChild(ta);
        ta.focus();
        ta.select();
        ta.setSelectionRange(0, ta.value.length);
        document.execCommand('copy');
        document.body.removeChild(ta);
        alert('Copiado!');
    } catch(e) {
        alert('Falha ao copiar. Selecione o texto manualmente.');
    }
}

document.addEventListener('DOMContentLoaded', function(){
    const moedaSel = document.getElementById('moeda');
    const fpSel = document.getElementById('forma_pagamento');
    const linkCard = document.getElementById('linkPagamentoCard');
    const linkInfo = document.getElementById('linkPagamentoInfo');
    const linkResult = document.getElementById('linkResult');
    const clienteSel = document.getElementById('cliente_id');
    const clienteBuscaInp = document.getElementById('cliente_busca');

    let __lastEnderecoPrefillClienteId = 0;

    function enderecoFormHasAnyValue(){
        const ids = [
            'endereco_entrega_cep',
            'endereco_entrega_endereco',
            'endereco_entrega_numero',
            'endereco_entrega_complemento',
            'endereco_entrega_bairro',
            'endereco_entrega_cidade',
            'endereco_entrega_estado'
        ];
        for (const id of ids) {
            const el = document.getElementById(id);
            if (el && String(el.value || '').trim() !== '') return true;
        }
        return false;
    }

    function setEnderecoValue(id, v){
        const el = document.getElementById(id);
        if (!el) return;
        el.value = String(v || '');
        el.dataset.prefilled = '1';
    }

    function clearEnderecoFieldsIfPrefilled(){
        const ids = [
            'endereco_entrega_cep',
            'endereco_entrega_endereco',
            'endereco_entrega_numero',
            'endereco_entrega_complemento',
            'endereco_entrega_bairro',
            'endereco_entrega_cidade',
            'endereco_entrega_estado'
        ];
        ids.forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;
            if (String(el.dataset.prefilled || '') === '1') {
                el.value = '';
            }
            el.dataset.prefilled = '';
        });
    }

    function hookEnderecoManualEdits(){
        const ids = [
            'endereco_entrega_cep',
            'endereco_entrega_endereco',
            'endereco_entrega_numero',
            'endereco_entrega_complemento',
            'endereco_entrega_bairro',
            'endereco_entrega_cidade',
            'endereco_entrega_estado'
        ];
        ids.forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;
            el.addEventListener('input', function(){
                // Se o admin mexeu manualmente, não sobrescrever automaticamente.
                this.dataset.prefilled = '0';
            });
        });
    }

    function fetchAndPrefillEndereco(clienteId, force){
        const cid = Number(clienteId || 0);
        if (!cid) return;
        if (!force && enderecoFormHasAnyValue()) return;
        fetch('/admin/pedidos/novo-manual/cliente-endereco/' + String(cid))
            .then(r => r.json())
            .then(resp => {
                if (!resp || !resp.success || !resp.endereco) return;
                const e = resp.endereco;
                __lastEnderecoPrefillClienteId = cid;
                setEnderecoValue('endereco_entrega_cep', e.cep);
                setEnderecoValue('endereco_entrega_endereco', e.endereco);
                setEnderecoValue('endereco_entrega_numero', e.numero);
                setEnderecoValue('endereco_entrega_complemento', e.complemento);
                setEnderecoValue('endereco_entrega_bairro', e.bairro);
                setEnderecoValue('endereco_entrega_cidade', e.cidade);
                setEnderecoValue('endereco_entrega_estado', e.estado);
            })
            .catch(() => {});
    }

    function convertAllItemValues(fromMoeda, toMoeda){
        const rows = document.querySelectorAll('#itensTable tbody tr');
        rows.forEach(r => {
            const inp = r.querySelector('.valorInp');
            if (!inp) return;
            const current = parseMoneyInput(String(inp.value || '0'));
            const converted = convertValueBetweenCurrencies(current, fromMoeda, toMoeda);
            inp.value = formatMoney(converted);
        });
    }

    function updateManualPaymentMethodsForCurrency(){
        if (moedaSel && fpSel) {
            const prev = String(fpSel.value || '');
            const moeda = getSelectedMoeda();
            fpSel.innerHTML = '';
            if (moeda === 'BRL') {
                fpSel.appendChild(new Option('PIX (Câmbio Real + Câmbio Real Taxas)', 'pix'));
                // fpSel.appendChild(new Option('Boleto (Câmbio Real + Câmbio Real Taxas)', 'boleto')); // OCULTO TEMPORARIAMENTE
                fpSel.appendChild(new Option('Cartão de Crédito', 'cartao_credito'));
                fpSel.appendChild(new Option('Cartão de Débito', 'cartao_debito'));
            } else {
                fpSel.appendChild(new Option('Online (Stripe)', ''));
            }
            fpSel.appendChild(new Option('Carteira', 'carteira'));
            fpSel.appendChild(new Option('PagDev (offline)', 'pagdev'));
            const stillValid = Array.from(fpSel.options).some(o => o.value === prev);
            fpSel.value = stillValid ? prev : fpSel.options[0].value;
        }
    }

    if (moedaSel) {
        moedaSel.value = (EXISTING_PEDIDO && String(EXISTING_PEDIDO.moeda || '').toUpperCase() === 'BRL') ? 'BRL' : 'USD';
        moedaSel.addEventListener('change', function(){
            const moedaBefore = (this.__prevMoeda || 'USD');
            const moedaNow = getSelectedMoeda();
            if (moedaBefore !== moedaNow) {
                convertAllItemValues(moedaBefore, moedaNow);
            }
            this.__prevMoeda = moedaNow;
            const g = document.getElementById('gatewayLabel');
            if (g) {
                g.textContent = (moedaNow === 'BRL') ? 'Câmbio Real + AppMax' : 'Stripe';
            }
            updateManualPaymentMethodsForCurrency();
            updateLinkVisibility();
            calcTotal();
        });

        moedaSel.__prevMoeda = getSelectedMoeda();
        updateManualPaymentMethodsForCurrency();
        updateLinkVisibility();
        calcTotal();
    }

    if (typeof initClienteBusca === 'function') {
        initClienteBusca();
    }

    if (EXISTING_PEDIDO && Number(EXISTING_PEDIDO.cliente_id || 0) > 0) {
        const cid = Number(EXISTING_PEDIDO.cliente_id || 0);
        if (window.__ensureClienteOption) {
            window.__ensureClienteOption(cid, 'Cliente #' + String(cid));
        } else if (clienteSel) {
            clienteSel.value = String(cid);
            const ev = new Event('change', { bubbles: true });
            clienteSel.dispatchEvent(ev);
        }
        if (clienteBuscaInp && !String(clienteBuscaInp.value || '').trim()) {
            clienteBuscaInp.value = 'Cliente #' + String(cid);
        }
    }

    const offlineWrap = document.getElementById('offlineInfoWrap');
    const offlineBox = document.getElementById('offlineInfoBox');
    const refreshOffline = function(){
        const v = fpSel ? String(fpSel.value || '') : '';
        if (!offlineWrap || !offlineBox) return;

        const isOffline = (v === 'pagdev');
        if (isOffline) {
            offlineWrap.style.display = 'block';
            offlineBox.textContent = 'Pagamento offline (PagDev). Após o pagamento, anexe o comprovante no pedido para que possamos alterar o status para pago.';
        } else {
            offlineWrap.style.display = 'none';
            offlineBox.textContent = '';
        }

        // Delegar visibilidade do link para a regra central (updateLinkVisibility)
        try { updateLinkVisibility(); } catch (e) {}

        try { calcTotal(); } catch (e) {}
    };
    if (fpSel) {
        fpSel.addEventListener('change', refreshOffline);
        updateManualPaymentMethodsForCurrency();
        refreshOffline();
        updateLinkVisibility();
    }

    const g = document.getElementById('gatewayLabel');
    if (g) g.textContent = (getSelectedMoeda() === 'BRL') ? 'Câmbio Real + AppMax' : 'Stripe';
    if (EXISTING_PEDIDO && Number(EXISTING_PEDIDO.cliente_id || 0) > 0) {
        const sel = document.getElementById('cliente_id');
        if (sel) {
            sel.value = String(EXISTING_PEDIDO.cliente_id);
        }
        fetchAndPrefillEndereco(EXISTING_PEDIDO.cliente_id, false);
    }

    if (clienteSel) {
        hookEnderecoManualEdits();
        clienteSel.addEventListener('change', function(){
            const nextId = Number(this.value || 0);
            const shouldOverride = (
                nextId > 0 &&
                __lastEnderecoPrefillClienteId > 0 &&
                nextId !== __lastEnderecoPrefillClienteId
            );

            if (shouldOverride) {
                clearEnderecoFieldsIfPrefilled();
                fetchAndPrefillEndereco(nextId, true);
                return;
            }

            fetchAndPrefillEndereco(nextId, false);
        });
    }

    const tbody = document.querySelector('#itensTable tbody');
    if (tbody && Array.isArray(EXISTING_ITENS) && EXISTING_ITENS.length > 0) {
        tbody.innerHTML = '';
        EXISTING_ITENS.forEach(it => {
            addItemRow();
            const last = tbody.querySelector('tr:last-child');
            prefillRowFromExisting(last, it);
        });
        calcTotal();
    } else {
        addItemRow();
    }

    const btnLink = document.getElementById('btnGerarLinkPagamento');
    if (btnLink) {
        btnLink.disabled = !(PEDIDO_ID && Number(PEDIDO_ID) > 0);
    }

    const form = document.getElementById('formPedidoManual');
    const createBox = document.getElementById('createResult');
    if (form) {
        form.addEventListener('submit', function(e){
            e.preventDefault();
            if (!validateProdutosObrigatorios()) {
                return false;
            }
            try { calcTotal(); } catch (err) {}

            const btn = document.getElementById('btnCriarPedidoManual');
            let btnOriginalHtml = '';
            if (btn) {
                btnOriginalHtml = String(btn.innerHTML || '');
                btn.disabled = true;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Criando...';
            }

            if (createBox) {
                createBox.style.display = 'block';
                createBox.innerHTML = `<div class="alert alert-info">Criando pedido...</div>`;
            }

            const fd = new FormData(form);
            fetch('/admin/pedidos/novo-manual/criar', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(resp => {
                    if (!resp || !resp.success) {
                        throw new Error((resp && resp.error) ? resp.error : 'Falha ao criar pedido');
                    }

                    PEDIDO_ID = Number(resp.pedidoId || resp.pedido_id || resp.id || 0);

                    const fp = String(fd.get('forma_pagamento') || (fpSel ? String(fpSel.value || '') : ''));
                    if (fp === 'nomad_transferencia' || fp === 'appmax_pix' || fp === 'carteira' || fp === 'pagdev') {
                        if (PEDIDO_ID && Number(PEDIDO_ID) > 0) {
                            window.location.href = '/admin/pedidos/detalhes/' + String(PEDIDO_ID) + '#comprovante';
                            return;
                        }
                    }
                    if (!PEDIDO_ID) {
                        throw new Error('Pedido inválido');
                    }

                    if (createBox) {
                        createBox.innerHTML = `<div class="alert alert-success">Pedido manual criado com sucesso: <strong>#${PEDIDO_ID}</strong></div>`;
                    }
                    if (btnLink) btnLink.disabled = false;
                })
                .catch(err => {
                    if (createBox) {
                        createBox.innerHTML = `<div class="alert alert-danger">Erro: ${escapeHtml(err && err.message ? err.message : String(err))}</div>`;
                    }
                })
                .finally(() => {
                    if (btn) {
                        btn.disabled = false;
                        if (btnOriginalHtml !== '') {
                            btn.innerHTML = btnOriginalHtml;
                        }
                    }
                });
        });
    }

    document.addEventListener('click', function(e){
        if (!(e.target && (e.target.closest('.produtoSearch') || e.target.closest('.prodResults')))) {
            closeAllProductResults();
        }
    });
});

// === SISTEMA DE DESCONTO COM AUTORIZAÇÃO ===
window.__DESCONTO_POLLS__ = {};

function habilitarDescontoRow(tr) {
    if (!tr) return;
    const pid = Number(tr.querySelector('.produtoIdInp')?.value || 0);
    const btn = tr.querySelector('.btnSolicitarDesconto');
    const valInp = tr.querySelector('.descontoValorInp');
    const tipoInp = tr.querySelector('.descontoTipoInp');
    if (pid > 0) {
        if (btn) btn.disabled = false;
        if (valInp) valInp.disabled = false;
        if (tipoInp) tipoInp.disabled = false;
    }
}

function solicitarDesconto(btn) {
    const tr = btn.closest('tr');
    if (!tr) return;

    const pid = Number(tr.querySelector('.produtoIdInp')?.value || 0);
    const prodSearch = tr.querySelector('.produtoSearch');
    const prodNome = prodSearch ? prodSearch.value.split(' - ')[0].trim() : ('Produto #' + pid);
    const valorInp = tr.querySelector('.descontoValorInp');
    const tipoInp = tr.querySelector('.descontoTipoInp');
    const precoInp = tr.querySelector('.valorInp');
    const statusEl = tr.querySelector('.descontoStatus');

    const descontoValor = parseFloat(valorInp?.value || '0');
    const descontoTipo = tipoInp?.value || 'percentual';
    const precoOriginal = parseMoneyInput(precoInp?.value || '0');

    if (descontoValor <= 0) {
        alert('Informe o valor do desconto antes de solicitar.');
        if (valorInp) valorInp.focus();
        return;
    }
    if (precoOriginal <= 0) {
        alert('O produto precisa ter um preço definido.');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Solicitando...';
    if (statusEl) statusEl.innerHTML = '<span class="text-muted">Enviando solicitação...</span>';

    const fd = new FormData();
    fd.append('produto_id', pid);
    fd.append('produto_nome', prodNome);
    fd.append('desconto_tipo', descontoTipo);
    fd.append('desconto_valor', descontoValor);
    fd.append('preco_original', precoOriginal);
    fd.append('moeda', getSelectedMoeda());

    fetch('/admin/configuracoes/desconto/solicitar', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (!data.ok) throw new Error(data.error || 'Erro ao solicitar');

            const token = data.token;
            const tokenInp = tr.querySelector('.descontoTokenInp');
            if (tokenInp) tokenInp.value = token;

            btn.innerHTML = '<i class="fas fa-clock"></i> Aguardando...';
            btn.classList.remove('btn-outline-warning');
            btn.classList.add('btn-outline-info');
            if (statusEl) statusEl.innerHTML = '<span class="text-warning"><i class="fas fa-hourglass-half"></i> Aguardando autorização...</span>';

            if (valorInp) valorInp.disabled = true;
            if (tipoInp) tipoInp.disabled = true;

            iniciarPollingDesconto(tr, token);
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-tag"></i> Solicitar desconto';
            if (statusEl) statusEl.innerHTML = '<span class="text-danger">' + escapeHtml(err.message) + '</span>';
        });
}

function iniciarPollingDesconto(tr, token) {
    if (window.__DESCONTO_POLLS__[token]) {
        clearInterval(window.__DESCONTO_POLLS__[token]);
    }

    window.__DESCONTO_POLLS__[token] = setInterval(() => {
        fetch('/admin/configuracoes/desconto/verificar?token=' + encodeURIComponent(token))
            .then(r => r.json())
            .then(data => {
                if (!data.ok) return;

                const statusEl = tr.querySelector('.descontoStatus');
                const btn = tr.querySelector('.btnSolicitarDesconto');
                const valorInp = tr.querySelector('.descontoValorInp');
                const tipoInp = tr.querySelector('.descontoTipoInp');
                const precoInp = tr.querySelector('.valorInp');

                if (data.status === 'aprovado') {
                    clearInterval(window.__DESCONTO_POLLS__[token]);
                    delete window.__DESCONTO_POLLS__[token];

                    if (statusEl) statusEl.innerHTML = '<span class="text-success"><i class="fas fa-check-circle"></i> Aprovado!</span>';
                    if (btn) {
                        btn.innerHTML = '<i class="fas fa-check"></i> Aprovado';
                        btn.classList.remove('btn-outline-info', 'btn-outline-warning');
                        btn.classList.add('btn-success');
                        btn.disabled = true;
                    }

                    if (precoInp && data.preco_final > 0) {
                        precoInp.value = formatMoney(data.preco_final);
                        calcTotal();
                    }

                } else if (data.status === 'negado') {
                    clearInterval(window.__DESCONTO_POLLS__[token]);
                    delete window.__DESCONTO_POLLS__[token];

                    const motivo = data.motivo ? ' - ' + data.motivo : '';
                    if (statusEl) statusEl.innerHTML = '<span class="text-danger"><i class="fas fa-times-circle"></i> Negado' + escapeHtml(motivo) + '</span>';
                    if (btn) {
                        btn.innerHTML = '<i class="fas fa-tag"></i> Solicitar desconto';
                        btn.classList.remove('btn-outline-info', 'btn-success');
                        btn.classList.add('btn-outline-warning');
                        btn.disabled = false;
                    }
                    if (valorInp) valorInp.disabled = false;
                    if (tipoInp) tipoInp.disabled = false;
                }
            })
            .catch(() => {});
    }, 3000);
}

// ========== DESCONTO GLOBAL (subtotal + taxa de serviço) ==========
window.__DESCONTO_GLOBAL_POLL__ = null;
window.__DESCONTO_GLOBAL_APROVADO__ = false;
window.__DESCONTO_GLOBAL_VALOR_APLICADO__ = 0;

function solicitarDescontoGlobal() {
    const valorInp = document.getElementById('descontoGlobalValor');
    const tipoInp = document.getElementById('descontoGlobalTipo');
    const btn = document.getElementById('btnSolicitarDescontoGlobal');
    const statusEl = document.getElementById('descontoGlobalStatus');

    const descontoValor = parseFloat(valorInp?.value || '0');
    const descontoTipo = tipoInp?.value || 'percentual';

    if (descontoValor <= 0) {
        alert('Informe o valor do desconto global.');
        if (valorInp) valorInp.focus();
        return;
    }

    // Calcular base (apenas taxa de serviço)
    const taxaServico = parseFloat(document.getElementById('taxa_servico')?.value || '0');
    const base = taxaServico;

    if (base <= 0) {
        alert('Adicione produtos ao pedido antes de solicitar desconto na taxa de serviço.');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Solicitando...';
    if (statusEl) statusEl.innerHTML = '<span class="text-muted">Enviando solicitação...</span>';

    const fd = new FormData();
    fd.append('produto_id', 0);
    fd.append('produto_nome', 'DESCONTO NA TAXA DE SERVIÇO');
    fd.append('desconto_tipo', descontoTipo);
    fd.append('desconto_valor', descontoValor);
    fd.append('preco_original', base);
    fd.append('moeda', getSelectedMoeda());

    fetch('/admin/configuracoes/desconto/solicitar', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            if (!data.ok) throw new Error(data.error || 'Erro ao solicitar');

            const token = data.token;
            document.getElementById('descontoGlobalToken').value = token;
            document.getElementById('desconto_global_token_hidden').value = token;

            btn.innerHTML = '<i class="fas fa-clock"></i> Aguardando...';
            btn.classList.remove('btn-outline-warning');
            btn.classList.add('btn-outline-info');
            if (statusEl) statusEl.innerHTML = '<span class="text-warning"><i class="fas fa-hourglass-half"></i> Aguardando autorização...</span>';

            if (valorInp) valorInp.disabled = true;
            if (tipoInp) tipoInp.disabled = true;

            iniciarPollingDescontoGlobal(token);
        })
        .catch(err => {
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-tag"></i> Solicitar';
            if (statusEl) statusEl.innerHTML = '<span class="text-danger">' + escapeHtml(err.message) + '</span>';
        });
}

function iniciarPollingDescontoGlobal(token) {
    if (window.__DESCONTO_GLOBAL_POLL__) {
        clearInterval(window.__DESCONTO_GLOBAL_POLL__);
    }

    window.__DESCONTO_GLOBAL_POLL__ = setInterval(() => {
        fetch('/admin/configuracoes/desconto/verificar?token=' + encodeURIComponent(token))
            .then(r => r.json())
            .then(data => {
                if (!data.ok) return;

                const statusEl = document.getElementById('descontoGlobalStatus');
                const btn = document.getElementById('btnSolicitarDescontoGlobal');
                const valorInp = document.getElementById('descontoGlobalValor');
                const tipoInp = document.getElementById('descontoGlobalTipo');

                if (data.status === 'aprovado') {
                    clearInterval(window.__DESCONTO_GLOBAL_POLL__);
                    window.__DESCONTO_GLOBAL_POLL__ = null;
                    window.__DESCONTO_GLOBAL_APROVADO__ = true;

                    if (statusEl) statusEl.innerHTML = '<span class="text-success"><i class="fas fa-check-circle"></i> Aprovado!</span>';
                    if (btn) {
                        btn.innerHTML = '<i class="fas fa-check"></i> Aprovado';
                        btn.classList.remove('btn-outline-info', 'btn-outline-warning');
                        btn.classList.add('btn-success');
                        btn.disabled = true;
                    }

                    // Calcular e aplicar desconto (apenas sobre taxa de serviço)
                    const taxaServico = parseFloat(document.getElementById('taxa_servico')?.value || '0');
                    const base = taxaServico;
                    const tipo = tipoInp?.value || 'percentual';
                    const val = parseFloat(valorInp?.value || '0');

                    let descontoAplicado = 0;
                    if (tipo === 'percentual') {
                        descontoAplicado = base * (val / 100);
                    } else {
                        descontoAplicado = val;
                    }
                    descontoAplicado = Math.min(descontoAplicado, base);
                    descontoAplicado = Math.round(descontoAplicado * 100) / 100;

                    window.__DESCONTO_GLOBAL_VALOR_APLICADO__ = descontoAplicado;

                    // Atualizar hidden fields
                    document.getElementById('desconto_global_tipo').value = tipo;
                    document.getElementById('desconto_global_valor').value = val;
                    document.getElementById('desconto_global_aplicado').value = descontoAplicado;

                    // Mostrar desconto aplicado
                    const aplicadoEl = document.getElementById('descontoGlobalAplicado');
                    if (aplicadoEl) aplicadoEl.style.cssText = 'display:flex !important;';
                    const moeda = getSelectedMoeda();
                    const dgEl = document.getElementById('resumoDescontoGlobal');
                    if (dgEl) dgEl.textContent = formatForDisplay(descontoAplicado, moeda);

                    // Recalcular total
                    calcTotal();

                } else if (data.status === 'negado') {
                    clearInterval(window.__DESCONTO_GLOBAL_POLL__);
                    window.__DESCONTO_GLOBAL_POLL__ = null;
                    window.__DESCONTO_GLOBAL_APROVADO__ = false;
                    window.__DESCONTO_GLOBAL_VALOR_APLICADO__ = 0;

                    const motivo = data.motivo ? ' — ' + data.motivo : '';
                    if (statusEl) statusEl.innerHTML = '<span class="text-danger"><i class="fas fa-times-circle"></i> Negado' + escapeHtml(motivo) + '</span>';
                    if (btn) {
                        btn.innerHTML = '<i class="fas fa-tag"></i> Solicitar';
                        btn.classList.remove('btn-outline-info', 'btn-success');
                        btn.classList.add('btn-outline-warning');
                        btn.disabled = false;
                    }
                    if (valorInp) valorInp.disabled = false;
                    if (tipoInp) tipoInp.disabled = false;

                    document.getElementById('desconto_global_tipo').value = '';
                    document.getElementById('desconto_global_valor').value = '0';
                    document.getElementById('desconto_global_aplicado').value = '0';
                }
            })
            .catch(() => {});
    }, 3000);
}

JS;

        echo "</script>";

        renderAdminScripts();

        echo '</body></html>';
        exit;
    }

    private function validarEAtualizarCustoENcmProdutos(array $produtoIds, array $custos, array $ncms): void {
        try {
            $pdo = \Config\Database::getConnection();

            $cols = [];
            try {
                $stmtCols = $pdo->query('DESCRIBE produtos');
                $cols = $stmtCols ? ($stmtCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            } catch (\Exception $e) {
                $cols = [];
            }

            $custoCol = '';
            foreach (['custo', 'cost', 'custo_produto', 'valor_custo'] as $c) {
                if (in_array($c, $cols, true)) {
                    $custoCol = $c;
                    break;
                }
            }

            $ncmCol = '';
            foreach (['ncm', 'ncm_code'] as $c) {
                if (in_array($c, $cols, true)) {
                    $ncmCol = $c;
                    break;
                }
            }

            if ($custoCol === '' && $ncmCol === '') {
                return;
            }

            $selCols = ['id'];
            if ($custoCol !== '') $selCols[] = $custoCol . ' AS custo';
            if ($ncmCol !== '') $selCols[] = $ncmCol . ' AS ncm';
            $stmtGet = $pdo->prepare('SELECT ' . implode(', ', $selCols) . ' FROM produtos WHERE id = ? LIMIT 1');

            for ($i = 0; $i < count($produtoIds); $i++) {
                $pid = (int) ($produtoIds[$i] ?? 0);
                if ($pid <= 0) continue;

                $stmtGet->execute([$pid]);
                $row = $stmtGet->fetch(\PDO::FETCH_ASSOC) ?: [];

                $custoAtual = (float) ($row['custo'] ?? 0);
                $ncmAtual = trim((string) ($row['ncm'] ?? ''));

                $needsCusto = ($custoCol !== '' && !($custoAtual > 0));
                $needsNcm = ($ncmCol !== '' && $ncmAtual === '');

                if (!$needsCusto && !$needsNcm) {
                    continue;
                }

                $set = [];
                $params = [':id' => $pid];

                if ($needsCusto) {
                    $custoInformado = (float) str_replace(',', '.', (string) ($custos[$i] ?? '0'));
                    if (!($custoInformado > 0)) {
                        throw new \Exception('Produto #' . $pid . ' sem custo cadastrado. Informe o custo (maior que 0).');
                    }
                    $set[] = $custoCol . ' = :custo';
                    $params[':custo'] = $custoInformado;
                }

                if ($needsNcm) {
                    $ncmInformado = trim((string) ($ncms[$i] ?? ''));
                    if ($ncmInformado === '') {
                        throw new \Exception('Produto #' . $pid . ' sem NCM cadastrado. Informe o NCM.');
                    }
                    $set[] = $ncmCol . ' = :ncm';
                    $params[':ncm'] = $ncmInformado;
                }

                if (!empty($set)) {
                    $pdo->prepare('UPDATE produtos SET ' . implode(', ', $set) . ' WHERE id = :id')->execute($params);
                }
            }
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function salvar(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor']);
        try {
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }
            $token = (string) $request->getParam('pedido_manual_token', '');
            $expected = (string) ($_SESSION['pedido_manual_form_token'] ?? '');
            if ($token === '' || $expected === '' || !hash_equals($expected, $token)) {
                $usedId = (int) (($_SESSION['pedido_manual_form_token_used'][$token] ?? 0));
                if ($usedId > 0) {
                    header('Location: /admin/pedidos/novo-manual?pedido_id=' . (int) $usedId);
                    exit;
                }
                throw new \Exception('Formulário expirado. Recarregue a página e tente novamente.');
            }

            $clienteId = (int) $request->getParam('cliente_id');
            $moeda = (string) $request->getParam('moeda', 'USD');
            $formaPagamento = (string) $request->getParam('forma_pagamento', '');
            $tipoCompra = strtolower(trim((string) $request->getParam('tipo_compra', '')));
            if (!in_array($tipoCompra, ['online', 'offline'], true)) {
                throw new \Exception('Selecione o tipo de compra (online/offline)');
            }

            $enderecoEntrega = [
                'cep' => (string) $request->getParam('endereco_entrega_cep', ''),
                'endereco' => (string) $request->getParam('endereco_entrega_endereco', ''),
                'numero' => (string) $request->getParam('endereco_entrega_numero', ''),
                'complemento' => (string) $request->getParam('endereco_entrega_complemento', ''),
                'bairro' => (string) $request->getParam('endereco_entrega_bairro', ''),
                'cidade' => (string) $request->getParam('endereco_entrega_cidade', ''),
                'estado' => (string) $request->getParam('endereco_entrega_estado', ''),
            ];

            $resumo = [
                'subtotal_produtos' => (float) str_replace(',', '.', (string) $request->getParam('subtotal_produtos', '0')),
                'peso_total' => (float) str_replace(',', '.', (string) $request->getParam('peso_total', '0')),
                'taxa_servico' => (float) str_replace(',', '.', (string) $request->getParam('taxa_servico', '0')),
                'valor_impostos' => (float) str_replace(',', '.', (string) $request->getParam('valor_impostos', '0')),
                'imposto_local' => (float) str_replace(',', '.', (string) $request->getParam('imposto_local', '0')),
                'valor_frete' => (float) str_replace(',', '.', (string) $request->getParam('valor_frete', '0')),
                'valor_total' => (float) str_replace(',', '.', (string) $request->getParam('valor_total', '0')),
            ];

            $produtoIds = $request->getParam('produto_id', []);
            $qtds = $request->getParam('quantidade', []);
            $vals = $request->getParam('valor_unitario', []);
            $custos = $request->getParam('produto_custo', []);
            $ncms = $request->getParam('produto_ncm', []);
            $jaComprados = $request->getParam('ja_comprado', []);

            if (!is_array($produtoIds)) $produtoIds = [];
            if (!is_array($qtds)) $qtds = [];
            if (!is_array($vals)) $vals = [];
            if (!is_array($custos)) $custos = [];
            if (!is_array($ncms)) $ncms = [];
            if (!is_array($jaComprados)) $jaComprados = [];

            $this->validarEAtualizarCustoENcmProdutos($produtoIds, $custos, $ncms);

            $itens = [];
            $count = max(count($produtoIds), count($qtds), count($vals));
            for ($i = 0; $i < $count; $i++) {
                $pid = (int) ($produtoIds[$i] ?? 0);
                $q = (int) ($qtds[$i] ?? 0);
                $v = (float) (is_string(($vals[$i] ?? '')) ? str_replace(',', '.', (string) ($vals[$i] ?? '0')) : ($vals[$i] ?? 0));
                if ($pid > 0 && $q > 0) {
                    $itens[] = [
                        'produto_id' => $pid,
                        'quantidade' => $q,
                        'valor_unitario' => $v,
                        'ja_comprado' => ((int) ($jaComprados[$i] ?? 0)) === 1 ? 1 : 0,
                    ];
                }
            }

            $adminId = null;
            try {
                $u = $auth->getUsuarioLogado();
                $perfil = strtolower(trim((string) ($u['perfil'] ?? '')));
                if (is_array($u) && in_array($perfil, ['admin', 'vendedor'], true)) {
                    $adminId = (int) ($u['id'] ?? 0);
                }
            } catch (\Exception $e) {
                $adminId = null;
            }

            $svc = new PedidoManualService();
            $semComissao = ((string) $request->getParam('sem_comissao', '0') === '1') ? 1 : 0;
            $pedidoId = $svc->criarPedidoManual($clienteId, $moeda, $itens, $resumo, $adminId, $formaPagamento !== '' ? $formaPagamento : null, $enderecoEntrega, $tipoCompra, $semComissao);

            $_SESSION['pedido_manual_form_token_used'] = $_SESSION['pedido_manual_form_token_used'] ?? [];
            $_SESSION['pedido_manual_form_token_used'][$token] = (int) $pedidoId;
            unset($_SESSION['pedido_manual_form_token']);

            header('Location: /admin/pedidos/novo-manual?pedido_id=' . (int) $pedidoId);
            exit;
        } catch (\Exception $e) {
            header('Location: /admin/pedidos/novo-manual?erro=' . urlencode($e->getMessage()));
            exit;
        }
    }

    public function criar(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor']);
        try {
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }
            $token = (string) $request->getParam('pedido_manual_token', '');
            $expected = (string) ($_SESSION['pedido_manual_form_token'] ?? '');
            if ($token === '' || $expected === '' || !hash_equals($expected, $token)) {
                $usedId = (int) (($_SESSION['pedido_manual_form_token_used'][$token] ?? 0));
                if ($usedId > 0) {
                    $this->json(['success' => true, 'pedido_id' => (int) $usedId]);
                    return;
                }
                throw new \Exception('Formulário expirado. Recarregue a página e tente novamente.');
            }

            $clienteId = (int) $request->getParam('cliente_id');
            $moeda = (string) $request->getParam('moeda', 'USD');
            $formaPagamento = (string) $request->getParam('forma_pagamento', '');
            $tipoCompra = strtolower(trim((string) $request->getParam('tipo_compra', '')));
            if (!in_array($tipoCompra, ['online', 'offline'], true)) {
                throw new \Exception('Selecione o tipo de compra (online/offline)');
            }

            $enderecoEntrega = [
                'cep' => (string) $request->getParam('endereco_entrega_cep', ''),
                'endereco' => (string) $request->getParam('endereco_entrega_endereco', ''),
                'numero' => (string) $request->getParam('endereco_entrega_numero', ''),
                'complemento' => (string) $request->getParam('endereco_entrega_complemento', ''),
                'bairro' => (string) $request->getParam('endereco_entrega_bairro', ''),
                'cidade' => (string) $request->getParam('endereco_entrega_cidade', ''),
                'estado' => (string) $request->getParam('endereco_entrega_estado', ''),
            ];

            $resumo = [
                'subtotal_produtos' => (float) str_replace(',', '.', (string) $request->getParam('subtotal_produtos', '0')),
                'peso_total' => (float) str_replace(',', '.', (string) $request->getParam('peso_total', '0')),
                'taxa_servico' => (float) str_replace(',', '.', (string) $request->getParam('taxa_servico', '0')),
                'valor_impostos' => (float) str_replace(',', '.', (string) $request->getParam('valor_impostos', '0')),
                'imposto_local' => (float) str_replace(',', '.', (string) $request->getParam('imposto_local', '0')),
                'valor_frete' => (float) str_replace(',', '.', (string) $request->getParam('valor_frete', '0')),
                'valor_total' => (float) str_replace(',', '.', (string) $request->getParam('valor_total', '0')),
            ];

            $produtoIds = $request->getParam('produto_id', []);
            $qtds = $request->getParam('quantidade', []);
            $vals = $request->getParam('valor_unitario', []);
            $custos = $request->getParam('produto_custo', []);
            $ncms = $request->getParam('produto_ncm', []);
            $jaComprados = $request->getParam('ja_comprado', []);

            if (!is_array($produtoIds)) $produtoIds = [];
            if (!is_array($qtds)) $qtds = [];
            if (!is_array($vals)) $vals = [];
            if (!is_array($custos)) $custos = [];
            if (!is_array($ncms)) $ncms = [];
            if (!is_array($jaComprados)) $jaComprados = [];

            $this->validarEAtualizarCustoENcmProdutos($produtoIds, $custos, $ncms);

            $itens = [];
            $count = max(count($produtoIds), count($qtds), count($vals));
            for ($i = 0; $i < $count; $i++) {
                $pid = (int) ($produtoIds[$i] ?? 0);
                $q = (int) ($qtds[$i] ?? 0);
                $v = (float) (is_string(($vals[$i] ?? '')) ? str_replace(',', '.', (string) ($vals[$i] ?? '0')) : ($vals[$i] ?? 0));
                if ($pid > 0 && $q > 0) {
                    $itens[] = [
                        'produto_id' => $pid,
                        'quantidade' => $q,
                        'valor_unitario' => $v,
                        'ja_comprado' => ((int) ($jaComprados[$i] ?? 0)) === 1 ? 1 : 0,
                    ];
                }
            }

            $adminId = null;
            try {
                $u = $auth->getUsuarioLogado();
                $perfil = strtolower(trim((string) ($u['perfil'] ?? '')));
                if (is_array($u) && in_array($perfil, ['admin', 'vendedor'], true)) {
                    $adminId = (int) ($u['id'] ?? 0);
                }
            } catch (\Exception $e) {
                $adminId = null;
            }

            $svc = new PedidoManualService();
            $semComissao = ((string) $request->getParam('sem_comissao', '0') === '1') ? 1 : 0;
            $pedidoId = $svc->criarPedidoManual($clienteId, $moeda, $itens, $resumo, $adminId, $formaPagamento !== '' ? $formaPagamento : null, $enderecoEntrega, $tipoCompra, $semComissao);

            $_SESSION['pedido_manual_form_token_used'] = $_SESSION['pedido_manual_form_token_used'] ?? [];
            $_SESSION['pedido_manual_form_token_used'][$token] = (int) $pedidoId;
            unset($_SESSION['pedido_manual_form_token']);
            $this->json(['success' => true, 'pedido_id' => (int) $pedidoId]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function gerarLink(Request $request) {
        try {
            $pedidoId = (int) $request->getParam('pedido_id', 0);
            $billingType = (string) $request->getParam('billingType', 'PIX');

            $svc = new PedidoManualService();
            $result = $svc->gerarLinkPagamentoPedidoManual($pedidoId, $billingType);
            $this->json($result);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function calcularResumo(Request $request) {
        // Suprimir notices/warnings que podem quebrar o JSON
        @ini_set('display_errors', '0');
        error_reporting(E_ERROR | E_PARSE);

        try {
            $moeda = strtoupper(trim((string) $request->getParam('moeda', 'USD')));
            if (!in_array($moeda, ['BRL', 'USD'], true)) {
                $moeda = 'USD';
            }

            $itensRaw = (string) $request->getParam('itens', '[]');
            $itens = json_decode($itensRaw, true);
            if (!is_array($itens)) {
                $itens = [];
            }

            error_log('[CALC_RESUMO] moeda=' . $moeda . ' itensRaw=' . substr($itensRaw, 0, 500) . ' itensCount=' . count($itens));

            // Subtotal autoritativo: soma dos itens (evita divergências de parsing no front)
            $subtotal = 0.0;
            foreach ($itens as $it) {
                if (!is_array($it)) continue;
                $qtd = (int) ($it['quantidade'] ?? 0);
                $vu = (float) ($it['valor_unitario'] ?? 0);
                if ($qtd > 0 && $vu >= 0) {
                    $subtotal += ($qtd * $vu);
                }
            }
            $subtotal = round($subtotal, 2);

            // Peso enviado pelo front é ignorado; recalculamos via BD.
            $pesoTotal = 0.0;

            // Recalcular peso de forma autoritativa (garante peso real do produto)
            $db = \Config\Database::getConnection();
            $pesoCache = [];
            $stPeso = null;
            try {
                $colsP = [];
                try {
                    $stCols = $db->query('DESCRIBE produtos');
                    $colsP = $stCols ? $stCols->fetchAll(\PDO::FETCH_COLUMN) : [];
                } catch (\Exception $e) {
                    $colsP = [];
                }

                $pesoCol = '';
                foreach (['peso', 'weight', 'product_weight', 'peso_kg'] as $cPeso) {
                    if (is_array($colsP) && in_array($cPeso, $colsP, true)) {
                        $pesoCol = $cPeso;
                        break;
                    }
                }

                if ($pesoCol !== '') {
                    $stPeso = $db->prepare('SELECT ' . $pesoCol . ' AS peso FROM produtos WHERE id = ? LIMIT 1');
                } else {
                    $stPeso = null;
                }
            } catch (\Exception $e) {
                $stPeso = null;
            }

            foreach ($itens as $it) {
                if (!is_array($it)) continue;
                $pid = (int) ($it['produto_id'] ?? 0);
                $qtd = (int) ($it['quantidade'] ?? 0);
                if ($pid <= 0 || $qtd <= 0) continue;

                $pesoUnit = 0.0;
                if (array_key_exists($pid, $pesoCache)) {
                    $pesoUnit = (float) $pesoCache[$pid];
                } elseif ($stPeso) {
                    try {
                        $stPeso->execute([$pid]);
                        $pesoUnit = (float) ($stPeso->fetchColumn() ?: 0);
                        $pesoCache[$pid] = $pesoUnit;
                    } catch (\Exception $e) {
                        $pesoCache[$pid] = 0.0;
                    }
                }

                if ($pesoUnit <= 0) {
                    $pesoUnit = 0.5;
                }
                $pesoTotal += ($pesoUnit * $qtd);
            }

            // Frete (mesma regra do carrinho/checkout): configuracoes_sistema entrega_* e ceil(peso)
            $getConfigValue = function(string $chave, $default = null) use ($db) {
                try {
                    $st = $db->prepare('SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1');
                    $st->execute([$chave]);
                    $row = $st->fetch(\PDO::FETCH_ASSOC);
                    if ($row && array_key_exists('valor', $row)) {
                        return $row['valor'];
                    }
                } catch (\Exception $e) {
                }
                return $default;
            };

            $calcularFrete = function(float $subtotalUsd, float $pesoTotalKg) use ($getConfigValue): float {
                $calcularAutomatico = (string) $getConfigValue('entrega_calcular_automatico', '1');
                $calcularAutomatico = ($calcularAutomatico === '1' || strtolower($calcularAutomatico) === 'true');
                if (!$calcularAutomatico) {
                    return 0.0;
                }

                $freteGratisAcima = (float) $getConfigValue('entrega_frete_gratis_acima', '0');
                if ($freteGratisAcima <= 0 || $subtotalUsd >= $freteGratisAcima) {
                    return 0.0;
                }

                $fretePorKg = (float) $getConfigValue('entrega_frete_padrao', '15');
                if ($fretePorKg <= 0) {
                    return 0.0;
                }

                $pesoArredondado = (float) ceil((float) $pesoTotalKg);
                return $fretePorKg * $pesoArredondado;
            };

            $carrinhoModel = new Carrinho();

            // Calcular usando as mesmas regras do carrinho/checkout.
            // Base Receita Federal opera em USD; quando moeda for BRL, converte para USD para calcular e converte de volta.
            $taxaConversao = 1.0;
            if ($moeda === 'BRL') {
                try {
                    $r = (float) $carrinhoModel->getTaxaConversao('BRL');
                    if ($r > 1.01) {
                        $taxaConversao = $r;
                    }
                } catch (\Exception $e) {
                }
            }

            if ($moeda === 'BRL' && $taxaConversao > 1.01) {
                $subtotalUsd = $subtotal / $taxaConversao;
                $freteUsd = (float) $calcularFrete((float) $subtotalUsd, (float) $pesoTotal);
                $taxaServicoUsd = (float) $carrinhoModel->calcularTaxaServico($pesoTotal, 'USD', 1.0);
                $impostosUsd = (float) $carrinhoModel->calcularImpostos((float) $subtotalUsd, (float) $freteUsd);

                $taxaServico = $taxaServicoUsd * $taxaConversao;
                $impostos = $impostosUsd * $taxaConversao;
                $frete = $freteUsd * $taxaConversao;
                $total = $subtotal + $frete + $taxaServico + $impostos;
            } else {
                $freteCalc = (float) $calcularFrete((float) $subtotal, (float) $pesoTotal);
                $taxaServicoCalc = 0.0;
                $impostosCalc = 0.0;
                try {
                    $taxaServicoCalc = (float) $carrinhoModel->calcularTaxaServico($pesoTotal, 'USD', 1.0);
                } catch (\Exception $e) {
                    error_log('[CALC_RESUMO_USD] ERRO calcularTaxaServico: ' . $e->getMessage());
                }
                try {
                    $impostosCalc = (float) $carrinhoModel->calcularImpostos((float) $subtotal, (float) $freteCalc);
                } catch (\Exception $e) {
                    error_log('[CALC_RESUMO_USD] ERRO calcularImpostos: ' . $e->getMessage());
                }
                $taxaServico = $taxaServicoCalc;
                $frete = $freteCalc;
                $impostos = $impostosCalc;
                $total = $subtotal + $frete + $taxaServico + $impostos;
                error_log('[CALC_RESUMO_USD] subtotal=' . $subtotal . ' pesoTotal=' . $pesoTotal . ' taxaServico=' . $taxaServico . ' frete=' . $frete . ' impostos=' . $impostos . ' total=' . $total);
            }

            // Imposto local do grupo de compras
            $impostoLocal = 0.0;
            $impostoLocalPercent = 0.0;
            try {
                $pids = [];
                foreach ($itens as $it) {
                    if (!is_array($it)) continue;
                    $pid = (int) ($it['produto_id'] ?? 0);
                    if ($pid > 0) $pids[$pid] = true;
                }
                $pids = array_keys($pids);
                if (!empty($pids)) {
                    $in = implode(',', array_fill(0, count($pids), '?'));
                    $stImpL = $db->prepare("SELECT MAX(g.imposto_local_percent) FROM grupos_compras g INNER JOIN produtos p ON p.grupo_compras_id = g.id WHERE p.id IN ($in) AND g.imposto_local_percent > 0");
                    $stImpL->execute($pids);
                    $impostoLocalPercent = (float) ($stImpL->fetchColumn() ?: 0);
                    if ($impostoLocalPercent > 0) {
                        $impostoLocal = $subtotal * ($impostoLocalPercent / 100.0);
                        $total = $total + $impostoLocal;
                    }
                }
            } catch (\Throwable $e) {}

            $this->json([
                'success' => true,
                'moeda' => $moeda,
                'subtotal' => round($subtotal, 2),
                'peso_total' => round($pesoTotal, 3),
                'taxa_servico' => round((float) $taxaServico, 2),
                'impostos' => round((float) $impostos, 2),
                'imposto_local' => round((float) $impostoLocal, 2),
                'imposto_local_percent' => round((float) $impostoLocalPercent, 2),
                'frete' => round((float) $frete, 2),
                'total' => round((float) $total, 2),
            ]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function clienteEndereco(Request $request) {
        try {
            $clienteId = (int) $request->getParam('id', 0);
            if ($clienteId <= 0) {
                $this->json(['success' => false, 'error' => 'Cliente inválido']);
            }

            $pdo = \Config\Database::getConnection();
            $cols = [];
            try {
                $st = $pdo->query('DESCRIBE enderecos');
                $cols = $st ? $st->fetchAll(\PDO::FETCH_COLUMN) : [];
            } catch (\Exception $e) {
                $cols = [];
            }

            if (empty($cols)) {
                $this->json(['success' => true, 'endereco' => null]);
            }

            $usuarioCol = in_array('usuario_id', $cols, true) ? 'usuario_id' : (in_array('user_id', $cols, true) ? 'user_id' : '');
            if ($usuarioCol === '') {
                $this->json(['success' => true, 'endereco' => null]);
            }

            $cepCol = in_array('cep', $cols, true) ? 'cep' : '';
            $endCol = in_array('endereco', $cols, true) ? 'endereco' : (in_array('logradouro', $cols, true) ? 'logradouro' : '');
            $numCol = in_array('numero', $cols, true) ? 'numero' : '';
            $complCol = in_array('complemento', $cols, true) ? 'complemento' : '';
            $bairroCol = in_array('bairro', $cols, true) ? 'bairro' : '';
            $cidadeCol = in_array('cidade', $cols, true) ? 'cidade' : '';
            $estadoCol = in_array('estado', $cols, true) ? 'estado' : (in_array('uf', $cols, true) ? 'uf' : '');
            $principalCol = in_array('principal', $cols, true) ? 'principal' : (in_array('is_principal', $cols, true) ? 'is_principal' : '');

            $select = ['id'];
            if ($cepCol !== '') $select[] = $cepCol . ' AS cep';
            if ($endCol !== '') $select[] = $endCol . ' AS endereco';
            if ($numCol !== '') $select[] = $numCol . ' AS numero';
            if ($complCol !== '') $select[] = $complCol . ' AS complemento';
            if ($bairroCol !== '') $select[] = $bairroCol . ' AS bairro';
            if ($cidadeCol !== '') $select[] = $cidadeCol . ' AS cidade';
            if ($estadoCol !== '') $select[] = $estadoCol . ' AS estado';

            $order = ' ORDER BY id DESC';
            if ($principalCol !== '') {
                $order = ' ORDER BY ' . $principalCol . ' DESC, id DESC';
            }

            $sql = 'SELECT ' . implode(', ', $select) . ' FROM enderecos WHERE ' . $usuarioCol . ' = :uid' . $order . ' LIMIT 1';
            $stmt = $pdo->prepare($sql);
            $stmt->bindValue(':uid', $clienteId, \PDO::PARAM_INT);
            $stmt->execute();
            $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;

            if (!$row) {
                // Fallback: buscar endereço da tabela usuarios (preenchido via "Meus Dados")
                $row = $this->buscarEnderecoDoUsuario($pdo, $clienteId);
            }

            if (!$row) {
                $this->json(['success' => true, 'endereco' => null]);
                return;
            }

            $this->json([
                'success' => true,
                'endereco' => [
                    'cep' => (string) ($row['cep'] ?? ''),
                    'endereco' => (string) ($row['endereco'] ?? ''),
                    'numero' => (string) ($row['numero'] ?? ''),
                    'complemento' => (string) ($row['complemento'] ?? ''),
                    'bairro' => (string) ($row['bairro'] ?? ''),
                    'cidade' => (string) ($row['cidade'] ?? ''),
                    'estado' => (string) ($row['estado'] ?? ''),
                ],
            ]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Busca dados de endereço diretamente da tabela usuarios (preenchidos via "Meus Dados")
     * como fallback quando o cliente não tem registro na tabela enderecos.
     */
    private function buscarEnderecoDoUsuario(\PDO $pdo, int $clienteId): ?array {
        try {
            $colsUsr = [];
            $st = $pdo->query('DESCRIBE usuarios');
            $colsUsr = $st ? $st->fetchAll(\PDO::FETCH_COLUMN) : [];
            if (empty($colsUsr)) {
                return null;
            }

            $map = [
                'cep' => ['cep', 'zip_code'],
                'endereco' => ['endereco', 'address', 'logradouro'],
                'numero' => ['numero', 'number'],
                'complemento' => ['complemento'],
                'bairro' => ['bairro', 'neighborhood'],
                'cidade' => ['cidade', 'city'],
                'estado' => ['estado', 'state', 'uf'],
            ];

            $selectCols = [];
            foreach ($map as $key => $candidates) {
                foreach ($candidates as $c) {
                    if (in_array($c, $colsUsr, true)) {
                        $selectCols[] = $c . ' AS ' . $key;
                        break;
                    }
                }
            }

            if (empty($selectCols)) {
                return null;
            }

            $stUsr = $pdo->prepare('SELECT ' . implode(', ', $selectCols) . ' FROM usuarios WHERE id = ? LIMIT 1');
            $stUsr->execute([$clienteId]);
            $row = $stUsr->fetch(\PDO::FETCH_ASSOC);
            if (!$row) {
                return null;
            }

            $cep = trim((string) ($row['cep'] ?? ''));
            $endereco = trim((string) ($row['endereco'] ?? ''));
            $cidade = trim((string) ($row['cidade'] ?? ''));

            // Precisa ter pelo menos CEP ou endereço+cidade
            if ($cep === '' && ($endereco === '' || $cidade === '')) {
                return null;
            }

            return $row;
        } catch (\Exception $e) {
            return null;
        }
    }
}
