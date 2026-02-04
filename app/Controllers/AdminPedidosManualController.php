<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;
use App\Services\PedidoManualService;
use App\Models\Carrinho;
use App\Models\Usuario;

class AdminPedidosManualController extends Controller {
    public function novo(Request $request) {
        $usuarioModel = new Usuario();
        $usuarios = [];
        try {
            $usuarios = $usuarioModel->getAll();
        } catch (\Exception $e) {
            $usuarios = [];
        }

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

            $select = ['id'];
            if ($nameCol !== '') $select[] = $nameCol . ' AS name';
            if ($priceCol !== '') $select[] = $priceCol . ' AS price';
            if (in_array('sku', $cols, true)) $select[] = 'sku';
            if ($pesoCol !== '') $select[] = $pesoCol . ' AS peso';

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
                <div class="card mb-4">
                    <div class="card-header"><strong>Cliente</strong></div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-8">
                                <label class="form-label">Cliente</label>
                                <select class="form-select" name="cliente_id" id="cliente_id" required>
                                    <option value="">Selecione...</option>';

        foreach ($usuarios as $u) {
            $uid = (int) ($u['id'] ?? 0);
            $nome = (string) ($u['nome'] ?? ($u['name'] ?? ''));
            $email = (string) ($u['email'] ?? '');
            if ($uid <= 0) continue;
            echo '<option value="' . $uid . '">' . htmlspecialchars($nome . ' (' . $email . ')', ENT_QUOTES, 'UTF-8') . '</option>';
        }

        echo '                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Moeda</label>
                                <select class="form-select" name="moeda" id="moeda">
                                    <option value="USD" selected>Dólar (USD)</option>
                                    <option value="BRL">Real (BRL)</option>
                                </select>
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
                                    <option value="" selected>Online</option>
                                    <option value="pagdev">PagDev (offline)</option>
                                </select>
                                <div class="form-text">Para pagamentos offline, será necessário anexar o comprovante no pedido.</div>
                            </div>
                            <div class="col-md-6" id="offlineInfoWrap" style="display:none;">
                                <label class="form-label">Instruções</label>
                                <div class="alert alert-warning mb-0" id="offlineInfoBox"></div>
                            </div>
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
                                <div class="d-flex justify-content-between py-1">
                                    <span class="text-muted">Impostos</span>
                                    <span><span id="resumoMoedaSymbol4">$</span> <span id="resumoImpostos">0.00</span></span>
                                </div>
                                <div class="d-flex justify-content-between py-1">
                                    <span class="text-muted">Frete</span>
                                    <span id="resumoFreteWrap"><span id="resumoMoedaSymbol5">$</span> <span id="resumoFrete">0.00</span></span>
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
                        <input type="hidden" name="taxa_servico" id="taxa_servico" value="0">
                        <input type="hidden" name="valor_impostos" id="valor_impostos" value="0">
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
                <div class="card-header"><strong>Pagamento (<span id="gatewayLabel">AppMax</span>)</strong></div>
                <div class="card-body">
                    <div class="alert alert-info mb-3" id="linkPagamentoInfo">Após criar o pedido manual, clique em <strong>Gerar Link de Pagamento</strong> para emitir a cobrança.</div>
                    <div class="row g-3 align-items-end">
                        <div class="col-md-4" id="billingTypeWrap">
                            <label class="form-label">Billing Type</label>
                            <select id="billingType" class="form-select">
                                <option value="BOLETO">BOLETO</option>
                                <option value="PIX">PIX</option>
                            </select>
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
        echo 'const TAXA_SERVICO_POR_KG_BRL = ' . json_encode((float) (new \App\Services\PedidoManualService())->getTaxaServicoPorKgBRL(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';' . "\n";
        echo 'const TAXA_SERVICO_POR_KG_USD = ' . json_encode((float) (new \App\Services\PedidoManualService())->getTaxaServicoPorKgUSD(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';' . "\n";
        echo 'const USD_BRL_RATE = ' . json_encode((float) (new \App\Services\PedidoManualService())->getTaxaConversaoUSDBRL(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';' . "\n";
        echo 'const ALIQUOTA_ICMS = ' . json_encode((float) (new \App\Services\PedidoManualService())->getAliquota('icms_aliquota'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';' . "\n";
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
        const price = (p.price || 0);
        html += `<option value="${id}" data-price="${price}">${escapeHtml(name)} (${escapeHtml(sku)}) - ${formatMoney(price)}</option>`;
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
    const price = (p && p.price) ? Number(p.price) : 0;
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
            <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeRow(this)">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    `;
    tbody.appendChild(tr);
    calcTotal();
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

    const v = fpSel ? String(fpSel.value || '') : '';
    const isOffline = (v === 'pagdev');

    // Regras:
    // - Offline (PagDev): não gera link
    // - Online BRL: AppMax (gera link)
    // - Online USD: Stripe (gera link de checkout)
    const canShowLinkCard = (!isOffline && (moeda === 'BRL' || moeda === 'USD'));
    const shouldShowBillingType = (!isOffline && moeda === 'BRL');

    if (billingWrap) {
        billingWrap.style.display = shouldShowBillingType ? '' : 'none';
    }

    if (linkCard) {
        linkCard.style.display = canShowLinkCard ? '' : 'none';
    }
    if (linkInfo) {
        linkInfo.style.display = isOffline ? 'none' : '';
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
        return `
            <button type="button" class="list-group-item list-group-item-action d-flex align-items-center gap-2" onclick="selectProdutoFromSearch(this, ${pid})">
                <img src="${escapeHtml(img)}" class="rounded border" style="width:40px;height:40px;object-fit:cover" alt="">
                <div class="text-start">
                    <div class="fw-semibold">${escapeHtml(String(p.name || ''))}</div>
                    <div class="small text-muted">R$ ${formatMoney(p.price || 0)}</div>
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
        const unitUsd = Number(prod.price || 0);
        const shown = (moeda === 'BRL') ? convertValueBetweenCurrencies(unitUsd, 'USD', 'BRL') : unitUsd;
        valor.value = formatMoney(shown);
    }

    const resultsEl = tr.querySelector('.prodResults');
    if (resultsEl) {
        resultsEl.style.display = 'none';
        resultsEl.innerHTML = '';
    }

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
    ['resumoMoedaSymbol','resumoMoedaSymbol2','resumoMoedaSymbol3','resumoMoedaSymbol4','resumoMoedaSymbol5','resumoMoedaSymbol6'].forEach(setSym);
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
            const total = Number(data.total || 0);

            const pesoBack = Number(data.peso_total || 0);
            const pesoEl = document.getElementById('resumoPeso');
            if (pesoEl) {
                pesoEl.textContent = formatPeso(pesoBack);
            }

            document.getElementById('resumoTaxaServico').textContent = formatForDisplay(taxaServico, moeda);
            document.getElementById('resumoImpostos').textContent = formatForDisplay(impostos, moeda);

            const freteWrap = document.getElementById('resumoFreteWrap');
            if (Number(frete) <= 0) {
                if (freteWrap) freteWrap.textContent = 'Frete grátis';
            } else {
                if (freteWrap) freteWrap.innerHTML = `<span id="resumoMoedaSymbol5">${escapeHtml(sym)}</span> <span id="resumoFrete">${escapeHtml(formatForDisplay(frete, moeda))}</span>`;
                const rf = document.getElementById('resumoFrete');
                if (rf) rf.textContent = formatForDisplay(frete, moeda);
            }

            document.getElementById('resumoTotal').textContent = formatForDisplay(total, moeda);
            document.getElementById('resumoTotal2').textContent = formatForDisplay(total, moeda);

            const setVal = (id, v) => {
                const el = document.getElementById(id);
                if (el) el.value = String(v);
            };
            setVal('subtotal_produtos', Number(data.subtotal || subtotal).toFixed(2));
            setVal('peso_total', Number(data.peso_total || pesoTotal).toFixed(3));
            setVal('taxa_servico', taxaServico.toFixed(2));
            setVal('valor_impostos', impostos.toFixed(2));
            setVal('valor_frete', frete.toFixed(2));
            setVal('valor_total', total.toFixed(2));
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
    msg += `- Impostos: ${formatBRL(impostos)}\n`;
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
            out.querySelector('.alert')?.classList.remove('alert-info');
            out.querySelector('.alert')?.classList.remove('alert-success');
            out.querySelector('.alert')?.classList.add('alert-success');
            const d = out.querySelector('.alert > div');
            if (d) d.innerHTML = '<strong>Copiado!</strong> Link de pagamento copiado para a área de transferência.';
        }
    };
    const fail = () => {
        const ta = document.getElementById('linkPagamentoTexto');
        if (ta) {
            ta.focus();
            ta.select();
        }
    };

    if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(txt).then(ok).catch(() => {
            try {
                const ta = document.getElementById('linkPagamentoTexto');
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
        const ta = document.getElementById('linkPagamentoTexto');
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

function gerarLinkPagamento(){
    const moeda = getSelectedMoeda();
    let bt = 'CREDIT_CARD';
    if (moeda === 'BRL') {
        const sel = document.getElementById('billingType');
        bt = sel ? String(sel.value || 'BOLETO') : 'BOLETO';
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
            } else {
                el.innerHTML = `<div class="alert alert-danger">Erro: ${escapeHtml((data && data.error) ? data.error : 'Falha ao gerar link')}</div>`;
            }
        })
        .catch(err => {
            el.innerHTML = `<div class="alert alert-danger">Erro: ${escapeHtml(err && err.message ? err.message : String(err))}</div>`;
        });
}

document.addEventListener('DOMContentLoaded', function(){
    const moedaSel = document.getElementById('moeda');
    const fpSel = document.getElementById('forma_pagamento');
    const linkCard = document.getElementById('linkPagamentoCard');
    const linkInfo = document.getElementById('linkPagamentoInfo');
    const linkResult = document.getElementById('linkResult');
    const clienteSel = document.getElementById('cliente_id');

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
        if (!fpSel) return;
        const prev = String(fpSel.value || '');
        const moeda = getSelectedMoeda();
        fpSel.innerHTML = '';
        fpSel.appendChild(new Option(moeda === 'BRL' ? 'Online (AppMax)' : 'Online (Stripe)', ''));
        fpSel.appendChild(new Option('PagDev (offline)', 'pagdev'));
        const stillValid = Array.from(fpSel.options).some(o => o.value === prev);
        fpSel.value = stillValid ? prev : '';
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
                g.textContent = (moedaNow === 'BRL') ? 'AppMax' : 'Stripe';
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
    if (g) g.textContent = (getSelectedMoeda() === 'BRL') ? 'AppMax' : 'Stripe';
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
            try { calcTotal(); } catch (err) {}

            const btn = document.getElementById('btnCriarPedidoManual');
            if (btn) btn.disabled = true;

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
                    if (fp === 'nomad_transferencia' || fp === 'appmax_pix') {
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
                    if (btn) btn.disabled = false;
                });
        });
    }

    document.addEventListener('click', function(e){
        if (!(e.target && (e.target.closest('.produtoSearch') || e.target.closest('.prodResults')))) {
            closeAllProductResults();
        }
    });
});

JS;

        echo "</script>";

        renderAdminScripts();

        echo '</body></html>';
        exit;
    }

    public function salvar(Request $request) {
        try {
            $clienteId = (int) $request->getParam('cliente_id');
            $moeda = (string) $request->getParam('moeda', 'USD');
            $formaPagamento = (string) $request->getParam('forma_pagamento', '');

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
                'valor_frete' => (float) str_replace(',', '.', (string) $request->getParam('valor_frete', '0')),
                'valor_total' => (float) str_replace(',', '.', (string) $request->getParam('valor_total', '0')),
            ];

            $produtoIds = $request->getParam('produto_id', []);
            $qtds = $request->getParam('quantidade', []);
            $vals = $request->getParam('valor_unitario', []);

            if (!is_array($produtoIds)) $produtoIds = [];
            if (!is_array($qtds)) $qtds = [];
            if (!is_array($vals)) $vals = [];

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
                    ];
                }
            }

            $adminId = null;
            try {
                $auth = new AuthService();
                $u = $auth->getUsuarioLogado();
                if (is_array($u) && (($u['perfil'] ?? '') === 'admin')) {
                    $adminId = (int) ($u['id'] ?? 0);
                }
            } catch (\Exception $e) {
                $adminId = null;
            }

            $svc = new PedidoManualService();
            $pedidoId = $svc->criarPedidoManual($clienteId, $moeda, $itens, $resumo, $adminId, $formaPagamento !== '' ? $formaPagamento : null, $enderecoEntrega);

            header('Location: /admin/pedidos/novo-manual?pedido_id=' . (int) $pedidoId);
            exit;
        } catch (\Exception $e) {
            header('Location: /admin/pedidos/novo-manual?erro=' . urlencode($e->getMessage()));
            exit;
        }
    }

    public function criar(Request $request) {
        try {
            $clienteId = (int) $request->getParam('cliente_id');
            $moeda = (string) $request->getParam('moeda', 'USD');
            $formaPagamento = (string) $request->getParam('forma_pagamento', '');

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
                'valor_frete' => (float) str_replace(',', '.', (string) $request->getParam('valor_frete', '0')),
                'valor_total' => (float) str_replace(',', '.', (string) $request->getParam('valor_total', '0')),
            ];

            $produtoIds = $request->getParam('produto_id', []);
            $qtds = $request->getParam('quantidade', []);
            $vals = $request->getParam('valor_unitario', []);

            if (!is_array($produtoIds)) $produtoIds = [];
            if (!is_array($qtds)) $qtds = [];
            if (!is_array($vals)) $vals = [];

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
                    ];
                }
            }

            $adminId = null;
            try {
                $auth = new AuthService();
                $u = $auth->getUsuarioLogado();
                if (is_array($u) && (($u['perfil'] ?? '') === 'admin')) {
                    $adminId = (int) ($u['id'] ?? 0);
                }
            } catch (\Exception $e) {
                $adminId = null;
            }

            $svc = new PedidoManualService();
            $pedidoId = $svc->criarPedidoManual($clienteId, $moeda, $itens, $resumo, $adminId, $formaPagamento !== '' ? $formaPagamento : null, $enderecoEntrega);
            $this->json(['success' => true, 'pedido_id' => (int) $pedidoId]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function gerarLink(Request $request) {
        try {
            $pedidoId = (int) $request->getParam('pedido_id', 0);
            $billingType = (string) $request->getParam('billingType', 'BOLETO');

            $svc = new PedidoManualService();
            $result = $svc->gerarLinkPagamentoPedidoManual($pedidoId, $billingType);
            $this->json($result);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    public function calcularResumo(Request $request) {
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
                $taxaServico = (float) $carrinhoModel->calcularTaxaServico($pesoTotal, 'USD', 1.0);
                $frete = (float) $calcularFrete((float) $subtotal, (float) $pesoTotal);
                $impostos = (float) $carrinhoModel->calcularImpostos((float) $subtotal, (float) $frete);
                $total = $subtotal + $frete + $taxaServico + $impostos;
            }

            $this->json([
                'success' => true,
                'moeda' => $moeda,
                'subtotal' => round($subtotal, 2),
                'peso_total' => round($pesoTotal, 3),
                'taxa_servico' => round((float) $taxaServico, 2),
                'impostos' => round((float) $impostos, 2),
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
                $this->json(['success' => true, 'endereco' => null]);
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
}
