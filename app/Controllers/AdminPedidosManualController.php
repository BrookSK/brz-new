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
            $stmt = $pdo->prepare("SELECT id, name, price, sku FROM produtos WHERE active = 1 ORDER BY name ASC");
            $stmt->execute();
            $produtos = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            $produtos = [];
        }

        $pedidoId = (int) $request->getParam('pedido_id', 0);

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

        if ($pedidoId > 0) {
            echo '<div class="alert alert-success">Pedido manual criado com sucesso: <strong>#' . (int) $pedidoId . '</strong></div>';
            echo '<div class="card mb-4">
                    <div class="card-header"><strong>Link de Pagamento (Asaas)</strong></div>
                    <div class="card-body">
                        <div class="row g-3 align-items-end">
                            <div class="col-md-4">
                                <label class="form-label">Billing Type</label>
                                <select id="billingType" class="form-select">
                                    <option value="BOLETO">BOLETO</option>
                                    <option value="PIX">PIX</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <button type="button" class="btn btn-primary" onclick="gerarLinkPagamento()">
                                    <i class="fas fa-link"></i> Gerar Link de Pagamento
                                </button>
                            </div>
                        </div>
                        <div class="mt-3" id="linkResult" style="display:none;"></div>
                    </div>
                </div>';
        }

        echo '<div class="card">
                <div class="card-header"><strong>Dados do Pedido</strong></div>
                <div class="card-body">
                    <form method="POST" action="/admin/pedidos/novo-manual/salvar" id="formPedidoManual">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Cliente</label>
                                <select class="form-select" name="cliente_id" required>
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
                            <div class="col-md-3">
                                <label class="form-label">Moeda</label>
                                <input type="text" class="form-control" name="moeda" value="BRL" readonly>
                            </div>
                        </div>

                        <hr>

                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <h5 class="mb-0">Itens</h5>
                            <button type="button" class="btn btn-outline-primary btn-sm" onclick="addItemRow()">
                                <i class="fas fa-plus"></i> Adicionar Produto
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table class="table table-sm" id="itensTable">
                                <thead>
                                    <tr>
                                        <th style="width:55%">Produto</th>
                                        <th style="width:15%">Qtd</th>
                                        <th style="width:15%">Valor</th>
                                        <th style="width:15%">Ações</th>
                                    </tr>
                                </thead>
                                <tbody></tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-end">
                            <h5>Total: <span id="totalSpan">0.00</span></h5>
                        </div>

                        <div class="mt-3">
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-save"></i> Criar Pedido Manual
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>';

        echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>';
        echo "<script>\n";
        echo 'const PRODUTOS = ' . json_encode($produtos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';' . "\n";
        echo 'const PEDIDO_ID = ' . (int) $pedidoId . ';' . "\n";

        echo <<<'JS'

function formatMoney(v){
    const n = Number(v || 0);
    return n.toFixed(2);
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
    let total = 0;
    const rows = document.querySelectorAll('#itensTable tbody tr');
    rows.forEach(r => {
        const qtd = Number(r.querySelector('.qtdInp')?.value || 0);
        const val = Number(String(r.querySelector('.valorInp')?.value || '0').replace(',', '.'));
        if (qtd > 0 && val >= 0) total += (qtd * val);
    });
    document.getElementById('totalSpan').textContent = formatMoney(total);
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
            $pedidoId = $svc->criarPedidoManual($clienteId, $moeda, $itens);

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
