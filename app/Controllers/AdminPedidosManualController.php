<?php
namespace App\Controllers;

use App\Core\Request;
use App\Models\Usuario;
use App\Services\PedidoManualService;

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
            $pesoCol = in_array('peso', $cols, true) ? 'peso' : '';

            $select = ['id'];
            if ($nameCol !== '') $select[] = $nameCol . ' AS name';
            if ($priceCol !== '') $select[] = $priceCol . ' AS price';
            if (in_array('sku', $cols, true)) $select[] = 'sku';
            if ($pesoCol !== '') $select[] = $pesoCol . ' AS peso';

            $where = '';
            if ($activeCol !== '') {
                $where = ' WHERE ' . $activeCol . ' = 1 ';
            }

            $orderBy = ($nameCol !== '') ? ' ORDER BY ' . $nameCol . ' ASC' : ' ORDER BY id DESC';

            $sql = 'SELECT ' . implode(', ', $select) . ' FROM produtos' . $where . $orderBy;
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $produtos = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            $produtos = [];
        }

        $pedidoId = (int) $request->getParam('pedido_id', 0);
        $erro = (string) $request->getParam('erro', '');

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

        echo '</head>
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
                                <input type="text" class="form-control" name="moeda" value="BRL" readonly>
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
                                    <div class="fs-5 fw-bold">R$ <span id="resumoSubtotal">0.00</span></div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border rounded p-3 h-100">
                                    <div class="text-muted small">Total</div>
                                    <div class="fs-5 fw-bold">R$ <span id="resumoTotal">0.00</span></div>
                                </div>
                            </div>
                        </div>

                        <hr>

                        <div class="row">
                            <div class="col-md-6 offset-md-6">
                                <div class="d-flex justify-content-between py-1">
                                    <span class="text-muted">Taxa de Serviço</span>
                                    <span>R$ <span id="resumoTaxaServico">0.00</span></span>
                                </div>
                                <div class="d-flex justify-content-between py-1">
                                    <span class="text-muted">Impostos</span>
                                    <span>R$ <span id="resumoImpostos">0.00</span></span>
                                </div>
                                <div class="d-flex justify-content-between py-1">
                                    <span class="text-muted">Frete</span>
                                    <span>R$ <span id="resumoFrete">0.00</span></span>
                                </div>
                                <hr>
                                <div class="d-flex justify-content-between">
                                    <strong>Total</strong>
                                    <strong>R$ <span id="resumoTotal2">0.00</span></strong>
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
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save"></i> Criar Pedido Manual
                    </button>
                </div>
            </form>

            <div class="card mb-4">
                <div class="card-header"><strong>Pagamento (Asaas)</strong></div>
                <div class="card-body">
                    <div class="alert alert-info mb-3">Primeiro crie o pedido manual. Depois gere o link de pagamento.</div>
                    <div class="row g-3 align-items-end">
                        <div class="col-md-4">
                            <label class="form-label">Billing Type</label>
                            <select id="billingType" class="form-select">
                                <option value="BOLETO">BOLETO</option>
                                <option value="PIX">PIX</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <button type="button" class="btn btn-primary" onclick="gerarLinkPagamento()" ' . ($pedidoId > 0 ? '' : 'disabled') . '>
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
        echo 'const PEDIDO_ID = ' . (int) $pedidoId . ';' . "\n";
        echo 'const TAXA_SERVICO_POR_KG = ' . json_encode((float) (new \App\Services\PedidoManualService())->getTaxaServicoPorKgBRL(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';' . "\n";
        echo 'const ALIQUOTA_ICMS = ' . json_encode((float) (new \App\Services\PedidoManualService())->getAliquota('icms_aliquota'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';' . "\n";
        echo 'const ALIQUOTA_IPI = ' . json_encode((float) (new \App\Services\PedidoManualService())->getAliquota('ipi_aliquota'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';' . "\n";

        echo <<<'JS'

function formatMoney(v){
    const n = Number(v || 0);
    return n.toFixed(2);
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

function escapeHtml(str){
    return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#039;');
}

function addItemRow(){
    const tbody = document.querySelector('#itensTable tbody');
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td>
            <select class="form-select form-select-sm produtoSel" name="produto_id[]" onchange="onProdutoChange(this)" required>
                ${buildProdutoOptions()}
            </select>
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

function removeRow(btn){
    const tr = btn.closest('tr');
    if (tr) tr.remove();
    calcTotal();
}

function onProdutoChange(sel){
    const opt = sel.options[sel.selectedIndex];
    const price = opt ? opt.getAttribute('data-price') : '0';
    const tr = sel.closest('tr');
    if (!tr) return;
    const valor = tr.querySelector('.valorInp');
    if (valor) valor.value = formatMoney(price);
    calcTotal();
}

function calcTotal(){
    let subtotal = 0;
    let pesoTotal = 0;
    let qtdItens = 0;
    const rows = document.querySelectorAll('#itensTable tbody tr');
    rows.forEach(r => {
        const qtd = Number(r.querySelector('.qtdInp')?.value || 0);
        const val = Number(String(r.querySelector('.valorInp')?.value || '0').replace(',', '.'));
        const sel = r.querySelector('.produtoSel');
        const pid = sel ? Number(sel.value || 0) : 0;
        const prod = PRODUTOS.find(p => Number(p.id) === pid);
        const peso = prod ? Number(prod.peso || 0) : 0;
        if (qtd > 0 && val >= 0) {
            subtotal += (qtd * val);
            qtdItens += qtd;
            pesoTotal += (peso * qtd);
        }
    });

    const frete = 0;
    const taxaServico = (Number(TAXA_SERVICO_POR_KG || 0) > 0) ? (pesoTotal * Number(TAXA_SERVICO_POR_KG)) : 0;
    const baseImpostos = subtotal + frete;
    const icms = (Number(ALIQUOTA_ICMS || 0) > 0) ? (baseImpostos * (Number(ALIQUOTA_ICMS) / 100)) : 0;
    const ipi = (Number(ALIQUOTA_IPI || 0) > 0) ? (baseImpostos * (Number(ALIQUOTA_IPI) / 100)) : 0;
    const impostos = icms + ipi;
    const total = subtotal + frete + taxaServico + impostos;

    document.getElementById('resumoQtdItens').textContent = String(qtdItens);
    document.getElementById('resumoPeso').textContent = formatPeso(pesoTotal);
    document.getElementById('resumoSubtotal').textContent = formatMoney(subtotal);
    document.getElementById('resumoTaxaServico').textContent = formatMoney(taxaServico);
    document.getElementById('resumoImpostos').textContent = formatMoney(impostos);
    document.getElementById('resumoFrete').textContent = formatMoney(frete);
    document.getElementById('resumoTotal').textContent = formatMoney(total);
    document.getElementById('resumoTotal2').textContent = formatMoney(total);

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
}

function gerarLinkPagamento(){
    if (!PEDIDO_ID) return;
    const bt = document.getElementById('billingType').value;

    const fd = new FormData();
    fd.append('pedido_id', String(PEDIDO_ID));
    fd.append('billingType', bt);

    fetch('/admin/pedidos/novo-manual/gerar-link', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
            const el = document.getElementById('linkResult');
            el.style.display = 'block';
            if (data && data.success) {
                const url = data.invoiceUrl || '';
                el.innerHTML = `<div class="alert alert-success">Link gerado: <a href="${escapeHtml(url)}" target="_blank">${escapeHtml(url)}</a></div>`;
            } else {
                el.innerHTML = `<div class="alert alert-danger">Erro: ${escapeHtml((data && data.error) ? data.error : 'Falha ao gerar link')}</div>`;
            }
        })
        .catch(err => {
            const el = document.getElementById('linkResult');
            el.style.display = 'block';
            el.innerHTML = `<div class="alert alert-danger">Erro: ${escapeHtml(err && err.message ? err.message : String(err))}</div>`;
        });
}

document.addEventListener('DOMContentLoaded', function(){
    addItemRow();
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
            $moeda = (string) $request->getParam('moeda', 'BRL');

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

            $svc = new PedidoManualService();
            $pedidoId = $svc->criarPedidoManual($clienteId, $moeda, $itens, $resumo);

            header('Location: /admin/pedidos/novo-manual?pedido_id=' . (int) $pedidoId);
            exit;
        } catch (\Exception $e) {
            header('Location: /admin/pedidos/novo-manual?erro=' . urlencode($e->getMessage()));
            exit;
        }
    }

    public function gerarLink(Request $request) {
        try {
            $pedidoId = (int) $request->getParam('pedido_id', 0);
            $billingType = (string) $request->getParam('billingType', 'BOLETO');

            $svc = new PedidoManualService();
            $result = $svc->gerarLinkPagamentoAsaasPedidoManual($pedidoId, $billingType);
            $this->json($result);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}
