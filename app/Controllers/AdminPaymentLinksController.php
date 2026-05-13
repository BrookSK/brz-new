<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;
use App\Services\PaymentLinkService;

class AdminPaymentLinksController extends Controller {
    public function index(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor']);

        $svc = new PaymentLinkService();
        $links = $svc->listLinks(100);

        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Links - Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';

        renderAdminSidebarStyles();

        echo '</head>
<body>
<div class="container-fluid">
  <div class="row">';

        renderAdminSidebar('payment-links');

        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="page-title">Payment Links</h1>
            </div>';

        echo '<div class="card mb-4">
                <div class="card-header"><strong>Criar link</strong></div>
                <div class="card-body">
                    <form method="POST" action="/admin/payment-links/criar" class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label">Moeda</label>
                            <select class="form-select" name="currency" required>
                                <option value="USD" selected>USD</option>
                                <option value="BRL">BRL</option>
                            </select>
                        </div>
                        <input type="hidden" name="produto_valor" value="0" id="produto_valor_hidden">
                        <div class="col-md-3">
                            <label class="form-label">Taxa de serviço (valor)</label>
                            <input type="number" step="0.01" min="0" class="form-control" name="taxa_servico_valor" value="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Impostos (valor)</label>
                            <input type="number" step="0.01" min="0" class="form-control" name="impostos_valor" value="0">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Produtos</label>
                            <div id="pl-products" class="d-flex flex-column gap-2"></div>
                            <div class="d-flex gap-2 mt-2">
                                <button type="button" class="btn btn-sm btn-outline-primary" id="pl-add-product"><i class="fas fa-plus"></i> Adicionar produto</button>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Subtotal produtos</label>
                            <input type="text" class="form-control" id="pl-subtotal" value="0,00" readonly>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Descrição</label>
                            <input type="text" class="form-control" name="descricao" placeholder="Ex: Pagamento avulso">
                        </div>
                        <div class="col-12">
                            <div class="form-text">O link expira automaticamente em 30 dias.</div>
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary"><i class="fas fa-link"></i> Gerar link</button>
                        </div>
                    </form>
                </div>
            </div>';

        echo '<div class="card">
                <div class="card-header"><strong>Links gerados</strong></div>
                <div class="card-body">
                    <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Moeda</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Expira</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>';

        if (empty($links)) {
            echo '<tr><td colspan="6" class="text-muted">Nenhum link criado.</td></tr>';
        } else {
            foreach ($links as $l) {
                $id = (int) ($l['id'] ?? 0);
                $cur = htmlspecialchars((string) ($l['currency'] ?? ''), ENT_QUOTES, 'UTF-8');
                $total = (float) ($l['total_valor'] ?? 0);
                $status = htmlspecialchars((string) ($l['status'] ?? ''), ENT_QUOTES, 'UTF-8');
                $exp = htmlspecialchars((string) ($l['expires_at'] ?? ''), ENT_QUOTES, 'UTF-8');
                $token = (string) ($l['token'] ?? '');
                $publicUrl = '/pagar/' . rawurlencode($token);

                echo '<tr>'
                    . '<td>' . $id . '</td>'
                    . '<td>' . $cur . '</td>'
                    . '<td>' . $cur . ' ' . number_format($total, 2, ',', '.') . '</td>'
                    . '<td>' . $status . '</td>'
                    . '<td>' . $exp . '</td>'
                    . '<td class="d-flex flex-wrap gap-2">'
                    . '  <a class="btn btn-sm btn-outline-primary" href="/admin/payment-links/' . $id . '"><i class="fas fa-clock"></i> Histórico</a>'
                    . '  <form method="POST" action="/admin/payment-links/duplicar/' . $id . '" style="display:inline;">
                            <button type="submit" class="btn btn-sm btn-outline-warning"><i class="fas fa-clone"></i> Duplicar</button>
                        </form>'
                    . '  <button type="button" class="btn btn-sm btn-outline-success" onclick="copyPayLink(this)" data-link="' . htmlspecialchars($publicUrl, ENT_QUOTES, 'UTF-8') . '"><i class="fas fa-copy"></i> Copiar</button>'
                    . '  <a class="btn btn-sm btn-outline-secondary" href="' . htmlspecialchars($publicUrl, ENT_QUOTES, 'UTF-8') . '" target="_blank"><i class="fas fa-external-link-alt"></i> Abrir</a>'
                    . '</td>'
                    . '</tr>';
            }
        }

        echo '</tbody></table></div>
                </div>
            </div>';

        echo '</main></div></div>';

        renderAdminScripts();

        echo <<<'HTML'
<script>
    function copyPayLink(btn){
        try {
            var link = btn && btn.getAttribute ? (btn.getAttribute("data-link") || "") : "";
            if (!link) return;
            var full = link;
            if (link.indexOf("http") !== 0) {
                full = window.location.origin.replace(/\/$/, "") + link;
            }
            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(full).then(function(){
                    btn.innerHTML = "<i class=\"fas fa-check\"></i> Copiado";
                    setTimeout(function(){ btn.innerHTML = "<i class=\"fas fa-copy\"></i> Copiar"; }, 1200);
                });
                return;
            }
            var ta = document.createElement("textarea");
            ta.value = full;
            document.body.appendChild(ta);
            ta.select();
            document.execCommand("copy");
            document.body.removeChild(ta);
            btn.innerHTML = "<i class=\"fas fa-check\"></i> Copiado";
            setTimeout(function(){ btn.innerHTML = "<i class=\"fas fa-copy\"></i> Copiar"; }, 1200);
        } catch (e) {
        }
    }

    (function(){
        function fmtBRL(n){
            try {
                return (n || 0).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
            } catch(e) {
                return String(n || 0);
            }
        }
        function parseNum(v){
            if (v === null || v === undefined) return 0;
            v = String(v).trim();
            if (!v) return 0;
            v = v.replace(/\s+/g, '');
            var lastComma = v.lastIndexOf(',');
            var lastDot = v.lastIndexOf('.');
            var decSep = null;
            if (lastComma !== -1 || lastDot !== -1) {
                decSep = (lastComma > lastDot) ? ',' : '.';
            }
            if (decSep) {
                var thousandsSep = decSep === ',' ? '.' : ',';
                v = v.split(thousandsSep).join('');
                v = v.replace(decSep, '.');
            }
            v = v.replace(/[^0-9.\-]/g, '');
            var n = parseFloat(v);
            return isNaN(n) ? 0 : n;
        }
        function renumber(){
            var wrap = document.getElementById('pl-products');
            if (!wrap) return;
            var rows = wrap.querySelectorAll('[data-pl-row]');
            rows.forEach(function(row, idx){
                var name = row.querySelector('input[data-pl-name]');
                var val = row.querySelector('input[data-pl-value]');
                if (name) name.setAttribute('name', 'products[' + idx + '][name]');
                if (val) val.setAttribute('name', 'products[' + idx + '][value]');
            });
        }
        function recalc(){
            var wrap = document.getElementById('pl-products');
            var subtotal = 0;
            if (wrap) {
                var vals = wrap.querySelectorAll('input[data-pl-value]');
                vals.forEach(function(inp){ subtotal += parseNum(inp.value); });
            }
            subtotal = Math.round(subtotal * 100) / 100;
            var out = document.getElementById('pl-subtotal');
            if (out) out.value = fmtBRL(subtotal);
            var hid = document.getElementById('produto_valor_hidden');
            if (hid) hid.value = String(subtotal.toFixed(2));
        }
        function addRow(p){
            var wrap = document.getElementById('pl-products');
            if (!wrap) return;
            var row = document.createElement('div');
            row.setAttribute('data-pl-row', '1');
            row.className = 'row g-2 align-items-center';
            row.innerHTML = ''
                + '<div class="col-md-6"><input type="text" class="form-control" placeholder="Nome do produto" data-pl-name="1" value="' + (p && p.name ? String(p.name).replace(/"/g,'&quot;') : '') + '"></div>'
                + '<div class="col-md-3"><input type="number" step="0.01" min="0" class="form-control" placeholder="Valor" data-pl-value="1" value="' + (p && p.value ? String(p.value) : '') + '"></div>'
                + '<div class="col-md-3 d-flex gap-2">'
                + '  <button type="button" class="btn btn-outline-danger w-100" data-pl-remove="1"><i class="fas fa-minus"></i> Remover</button>'
                + '</div>';
            wrap.appendChild(row);
            renumber();
            recalc();
        }

        document.addEventListener('click', function(e){
            var t = e && e.target ? e.target : null;
            if (!t) return;
            var addBtn = t.closest ? t.closest('#pl-add-product') : null;
            if (addBtn) {
                e.preventDefault();
                addRow({ name: '', value: '' });
                return;
            }
            var rm = t.closest ? t.closest('[data-pl-remove]') : null;
            if (rm) {
                e.preventDefault();
                var row = rm.closest('[data-pl-row]');
                if (row && row.parentNode) row.parentNode.removeChild(row);
                renumber();
                recalc();
                return;
            }
        });
        document.addEventListener('input', function(e){
            var t = e && e.target ? e.target : null;
            if (!t) return;
            if (t.matches && (t.matches('input[data-pl-value]') || t.matches('input[data-pl-name]'))) {
                recalc();
            }
        });
        if (document.getElementById('pl-products') && document.getElementById('pl-products').children.length === 0) {
            addRow({ name: '', value: '' });
        }
    })();
</script>
HTML;
        echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>';
        echo '</body></html>';
        exit;
    }

    public function criar(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor']);

        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $adminId = (int) ($_SESSION['usuario_id'] ?? 0);

        $svc = new PaymentLinkService();
        $products = $request->getParam('products', []);
        if (!is_array($products)) $products = [];
        $data = [
            'currency' => (string) $request->getParam('currency', 'USD'),
            'produto_valor' => (string) $request->getParam('produto_valor', '0'),
            'taxa_servico_valor' => (string) $request->getParam('taxa_servico_valor', '0'),
            'impostos_valor' => (string) $request->getParam('impostos_valor', '0'),
            'descricao' => (string) $request->getParam('descricao', ''),
            'products' => $products,
        ];

        $res = $svc->createLink($data, $adminId);
        if (empty($res['success'])) {
            if (session_status() === PHP_SESSION_NONE) {
                @session_start();
            }
            $_SESSION['message'] = (string) ($res['error'] ?? 'Falha ao criar link');
            $_SESSION['message_type'] = 'danger';
            header('Location: /admin/payment-links');
            exit;
        }

        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $_SESSION['message'] = 'Link criado: <a href="' . htmlspecialchars((string) ($res['public_url'] ?? ''), ENT_QUOTES, 'UTF-8') . '" target="_blank">abrir link</a>';
        $_SESSION['message_type'] = 'success';
        header('Location: /admin/payment-links');
        exit;
    }

    public function duplicar(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor']);

        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $adminId = (int) ($_SESSION['usuario_id'] ?? 0);

        $id = (int) $request->getParam('id', 0);
        if ($id <= 0) {
            $_SESSION['message'] = 'Link inválido.';
            $_SESSION['message_type'] = 'danger';
            header('Location: /admin/payment-links');
            exit;
        }

        $svc = new PaymentLinkService();
        $res = $svc->duplicateLink($id, $adminId);
        if (empty($res['success'])) {
            $_SESSION['message'] = (string) ($res['error'] ?? 'Falha ao duplicar link');
            $_SESSION['message_type'] = 'danger';
            header('Location: /admin/payment-links');
            exit;
        }

        $_SESSION['message'] = 'Link duplicado: <a href="' . htmlspecialchars((string) ($res['public_url'] ?? ''), ENT_QUOTES, 'UTF-8') . '" target="_blank">abrir link</a>';
        $_SESSION['message_type'] = 'success';
        header('Location: /admin/payment-links');
        exit;
    }

    public function detalhes(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor']);

        $id = (int) $request->getParam('id', 0);
        $svc = new PaymentLinkService();

        $links = $svc->listLinks(200);
        $link = null;
        foreach ($links as $l) {
            if ((int) ($l['id'] ?? 0) === $id) {
                $link = $l;
                break;
            }
        }

        $payments = $svc->listPaymentsByLinkId($id, 200);

        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Histórico - Payment Link</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';

        renderAdminSidebarStyles();

        echo '</head>
<body>
<div class="container-fluid">
  <div class="row">';

        renderAdminSidebar('payment-links');

        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between align-items-center mb-3">'
            . '<h2 class="mb-0">Payment Link #' . (int) $link['id'] . '</h2>'
            . '<div class="d-flex gap-2">'
            . '<a class="btn btn-outline-primary" target="_blank" href="/pagar/' . rawurlencode((string) ($link['token'] ?? '')) . '/comprovante"><i class="fas fa-print"></i> Comprovante (PDF)</a>'
            . '<a class="btn btn-outline-secondary" href="/admin/payment-links"><i class="fas fa-arrow-left"></i> Voltar</a>'
            . '</div>'
            . '</div>';

        if (!is_array($link)) {
            echo '<div class="alert alert-danger">Link não encontrado.</div>';
        } else {
            $token = (string) ($link['token'] ?? '');
            $publicUrl = '/pagar/' . rawurlencode($token);
            echo '<div class="card mb-3"><div class="card-body">'
                . '<div class="d-flex flex-wrap align-items-center gap-2"><strong>URL:</strong> <a href="' . htmlspecialchars($publicUrl, ENT_QUOTES, 'UTF-8') . '" target="_blank">' . htmlspecialchars($publicUrl, ENT_QUOTES, 'UTF-8') . '</a>'
                . '<button type="button" class="btn btn-sm btn-outline-success" onclick="copyPayLink(this)" data-link="' . htmlspecialchars($publicUrl, ENT_QUOTES, 'UTF-8') . '"><i class="fas fa-copy"></i> Copiar</button>'
                . '</div>'
                . '<div><strong>Moeda:</strong> ' . htmlspecialchars((string) ($link['currency'] ?? ''), ENT_QUOTES, 'UTF-8') . '</div>'
                . '<div><strong>Produto:</strong> ' . htmlspecialchars((string) ($link['currency'] ?? ''), ENT_QUOTES, 'UTF-8') . ' ' . number_format((float) ($link['produto_valor'] ?? 0), 2, ',', '.') . '</div>'
                . '<div><strong>Taxa de serviço:</strong> ' . htmlspecialchars((string) ($link['currency'] ?? ''), ENT_QUOTES, 'UTF-8') . ' ' . number_format((float) ($link['taxa_servico_valor'] ?? 0), 2, ',', '.') . '</div>'
                . '<div><strong>Impostos:</strong> ' . htmlspecialchars((string) ($link['currency'] ?? ''), ENT_QUOTES, 'UTF-8') . ' ' . number_format((float) ($link['impostos_valor'] ?? 0), 2, ',', '.') . '</div>'
                . '<div><strong>Total:</strong> ' . htmlspecialchars((string) ($link['currency'] ?? ''), ENT_QUOTES, 'UTF-8') . ' ' . number_format((float) ($link['total_valor'] ?? 0), 2, ',', '.') . '</div>'
                . '</div></div>';
        }

        echo '<div class="card">
                <div class="card-header"><strong>Pagamentos</strong></div>
                <div class="card-body">
                    <div class="table-responsive">
                    <table class="table table-sm align-middle">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Componente</th>
                                <th>Status</th>
                                <th>Gateway</th>
                                <th>Método</th>
                                <th>Cliente</th>
                                <th>Dados</th>
                                <th>Produto</th>
                                <th>Taxa</th>
                                <th>Impostos</th>
                                <th>Total</th>
                                <th>Criado</th>
                            </tr>
                        </thead>
                        <tbody>';

        if (empty($payments)) {
            echo '<tr><td colspan="12" class="text-muted">Nenhum pagamento registrado.</td></tr>';
        } else {
            foreach ($payments as $p) {
                $pid = (int) ($p['id'] ?? 0);
                $comp = htmlspecialchars((string) ($p['componente'] ?? ''), ENT_QUOTES, 'UTF-8');
                $st = htmlspecialchars((string) ($p['status'] ?? ''), ENT_QUOTES, 'UTF-8');
                $gw = htmlspecialchars((string) ($p['gateway'] ?? ''), ENT_QUOTES, 'UTF-8');
                $met = htmlspecialchars((string) ($p['metodo'] ?? ''), ENT_QUOTES, 'UTF-8');
                $cli = trim((string) ($p['customer_name'] ?? ''));
                $cliEmail = trim((string) ($p['customer_email'] ?? ''));
                $cliLabel = htmlspecialchars($cli !== '' ? $cli : ($cliEmail !== '' ? $cliEmail : '-'), ENT_QUOTES, 'UTF-8');
                $cur = htmlspecialchars((string) ($p['currency'] ?? ''), ENT_QUOTES, 'UTF-8');
                $vProd = (float) ($p['produto_valor'] ?? 0);
                $vTaxa = (float) ($p['taxa_servico_valor'] ?? 0);
                $vImp = (float) ($p['impostos_valor'] ?? 0);
                $total = (float) ($p['total_valor'] ?? 0);
                $created = htmlspecialchars((string) ($p['created_at'] ?? ''), ENT_QUOTES, 'UTF-8');

                $meta = (string) ($p['metadata'] ?? '');
                $form = null;
                if ($meta !== '') {
                    try {
                        $j = json_decode($meta, true);
                        if (is_array($j) && isset($j['form']) && is_array($j['form'])) {
                            $form = $j['form'];
                        }
                    } catch (\Throwable $e) {
                        $form = null;
                    }
                }

                $dadosHtml = '-';
                if (is_array($form)) {
                    $addr = trim((string) ($form['endereco'] ?? ''));
                    $num = trim((string) ($form['numero'] ?? ''));
                    $bairro = trim((string) ($form['bairro'] ?? ''));
                    $cidade = trim((string) ($form['cidade'] ?? ''));
                    $estado = trim((string) ($form['estado'] ?? ($form['estado_text'] ?? '')));
                    $cep = trim((string) ($form['cep'] ?? ''));
                    $pais = trim((string) ($form['pais'] ?? ''));
                    $destNome = trim((string) ($form['destinatario_nome'] ?? ''));
                    $destTel = trim((string) ($form['destinatario_telefone'] ?? ''));
                    $dn = trim((string) ($form['data_nascimento'] ?? ''));

                    $parts = [];
                    if ($dn !== '') $parts[] = 'Nascimento: ' . htmlspecialchars($dn, ENT_QUOTES, 'UTF-8');
                    if ($addr !== '' || $cidade !== '' || $estado !== '' || $cep !== '') {
                        $linha = trim($addr . ($num !== '' ? ', ' . $num : '') . ($bairro !== '' ? ' - ' . $bairro : ''));
                        $linha2 = trim($cidade . ($estado !== '' ? '/' . $estado : '') . ($cep !== '' ? ' - ' . $cep : ''));
                        $linha3 = trim($pais);
                        $addrTxt = trim($linha . ( $linha2 !== '' ? ' | ' . $linha2 : '') . ($linha3 !== '' ? ' | ' . $linha3 : ''));
                        if ($addrTxt !== '') $parts[] = 'Endereço: ' . htmlspecialchars($addrTxt, ENT_QUOTES, 'UTF-8');
                    }
                    if ($destNome !== '' || $destTel !== '') {
                        $destTxt = trim($destNome . ($destTel !== '' ? ' (' . $destTel . ')' : ''));
                        $parts[] = 'Destinatário: ' . htmlspecialchars($destTxt, ENT_QUOTES, 'UTF-8');
                    }
                    $dadosHtml = !empty($parts) ? ('<div class="small">' . implode('<br>', $parts) . '</div>') : '-';
                }

                echo '<tr>'
                    . '<td>' . $pid . '</td>'
                    . '<td>' . $comp . '</td>'
                    . '<td>' . $st . '</td>'
                    . '<td>' . $gw . '</td>'
                    . '<td>' . $met . '</td>'
                    . '<td>' . $cliLabel . '</td>'
                    . '<td>' . $dadosHtml . '</td>'
                    . '<td>' . $cur . ' ' . number_format($vProd, 2, ',', '.') . '</td>'
                    . '<td>' . $cur . ' ' . number_format($vTaxa, 2, ',', '.') . '</td>'
                    . '<td>' . $cur . ' ' . number_format($vImp, 2, ',', '.') . '</td>'
                    . '<td>' . $cur . ' ' . number_format($total, 2, ',', '.') . '</td>'
                    . '<td>' . $created . '</td>'
                    . '</tr>';
            }
        }

        echo '</tbody></table></div>
                </div>
            </div>';

        echo '</main></div></div>';

        renderAdminScripts();
        echo '<script>
            function copyPayLink(btn){
                try {
                    var link = btn && btn.getAttribute ? (btn.getAttribute("data-link") || "") : "";
                    if (!link) return;
                    var full = link;
                    if (link.indexOf("http") !== 0) {
                        full = window.location.origin.replace(/\/$/, "") + link;
                    }
                    if (navigator.clipboard && navigator.clipboard.writeText) {
                        navigator.clipboard.writeText(full).then(function(){
                            btn.innerHTML = "<i class=\"fas fa-check\"></i> Copiado";
                            setTimeout(function(){ btn.innerHTML = "<i class=\"fas fa-copy\"></i> Copiar"; }, 1200);
                        });
                        return;
                    }
                    var ta = document.createElement("textarea");
                    ta.value = full;
                    document.body.appendChild(ta);
                    ta.select();
                    document.execCommand("copy");
                    document.body.removeChild(ta);
                    btn.innerHTML = "<i class=\"fas fa-check\"></i> Copiado";
                    setTimeout(function(){ btn.innerHTML = "<i class=\"fas fa-copy\"></i> Copiar"; }, 1200);
                } catch (e) {
                }
            }
        </script>';
        echo '<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>';
        echo '</body></html>';
        exit;
    }
}
