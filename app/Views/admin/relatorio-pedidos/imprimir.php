<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Pedido #<?= $pedido['id'] ?></title>
<style>
body{font-family:Arial,sans-serif;margin:20px;color:#333;font-size:12px;}
h1{font-size:18px;text-align:center;margin:0 0 5px;}
.meta{text-align:center;font-size:10px;color:#666;margin-bottom:15px;}
.section{margin-bottom:15px;}
.section-title{font-weight:bold;font-size:13px;background:#f5f5f5;padding:4px 8px;border-left:3px solid #0b1f3a;margin-bottom:8px;}
table{width:100%;border-collapse:collapse;font-size:11px;margin-bottom:10px;}
th,td{padding:5px 8px;border:1px solid #ddd;text-align:left;vertical-align:top;}
th{background:#f8f9fa;font-weight:bold;width:30%;}
.item-table th{width:auto;}
.item-table img{width:50px;height:50px;object-fit:cover;border-radius:4px;}
.total-row{font-weight:bold;background:#f1f1f1;}
.no-print{margin-bottom:10px;}
@media print{.no-print{display:none;} @page{margin:10mm;}}
</style>
</head>
<body>
<div class="no-print"><button onclick="window.print()">Imprimir / Salvar PDF</button> <button onclick="window.close()">Fechar</button></div>

<h1>Detalhes do Pedido #<?= $pedido['id'] ?></h1>
<div class="meta">
    <?= htmlspecialchars($pedido['codigo_pedido'] ?? '') ?> | 
    Data: <?= date('d/m/Y H:i', strtotime($pedido['created_at'])) ?> | 
    Status: <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $pedido['status'] ?? ''))) ?>
</div>

<div class="section">
    <div class="section-title">Informações do Cliente</div>
    <table>
        <tr><th>Nome</th><td><?= htmlspecialchars($pedido['cliente_nome'] ?? '') ?></td></tr>
        <tr><th>Email</th><td><?= htmlspecialchars($pedido['cliente_email'] ?? '') ?></td></tr>
        <tr><th>CPF</th><td><?= htmlspecialchars($pedido['cliente_cpf_cnpj'] ?? ($pedido['cliente_documento'] ?? '')) ?></td></tr>
        <tr><th>Telefone</th><td><?= htmlspecialchars($pedido['cliente_telefone'] ?? '') ?></td></tr>
        <?php if ($tracking): ?><tr><th>Rastreio</th><td style="color:red;font-weight:bold;"><?= htmlspecialchars($tracking) ?></td></tr><?php endif; ?>
    </table>
</div>

<?php if ($endereco): ?>
<div class="section">
    <div class="section-title">Endereço de Entrega</div>
    <table><tr><th>Endereço</th><td><?= htmlspecialchars($endereco) ?></td></tr></table>
</div>
<?php endif; ?>

<div class="section">
    <div class="section-title">Itens do Pedido</div>
    <table class="item-table">
        <thead><tr><th style="width:60px">Foto</th><th>Produto</th><th>Qtd</th><th>Preço Unit.</th><th>Total</th></tr></thead>
        <tbody>
        <?php
        $subTotal = 0;
        foreach ($itens as $it):
            $nome = (string)($it['nome_produto'] ?? '');
            $foto = (string)($it['imagem'] ?? '');
            $qtd = (int)($it['quantidade'] ?? 1);
            $preco = (float)($it['preco_unitario'] ?? 0);
            $sub = (float)($it['subtotal'] ?? ($preco * $qtd));
            $subTotal += $sub;
        ?>
            <tr>
                <td><?= $foto ? '<img src="'.htmlspecialchars($foto).'">' : '' ?></td>
                <td><?= htmlspecialchars($nome) ?></td>
                <td><?= $qtd ?></td>
                <td><?= $fmt($preco) ?></td>
                <td><?= $fmt($sub) ?></td>
            </tr>
        <?php endforeach; ?>
            <tr class="total-row"><td colspan="4">Subtotal</td><td><?= $fmt($pedido['subtotal'] ?? $subTotal) ?></td></tr>
            <tr class="total-row"><td colspan="4">Taxa de Serviço</td><td><?= $fmt($pedido['servicos'] ?? 0) ?></td></tr>
            <tr class="total-row"><td colspan="4">Impostos</td><td><?= $fmt($pedido['impostos'] ?? 0) ?></td></tr>
            <tr class="total-row"><td colspan="4">Frete</td><td><?= ((float)($pedido['frete'] ?? 0)) <= 0 ? 'Grátis' : $fmt($pedido['frete']) ?></td></tr>
            <tr class="total-row"><td colspan="4">Total</td><td style="font-size:14px;"><?= $fmt($pedido['total'] ?? 0) ?></td></tr>
        </tbody>
    </table>
</div>

<div class="section">
    <div class="section-title">Pagamento</div>
    <table>
        <tr><th>Método</th><td><?= htmlspecialchars($pedido['forma_pagamento'] ?? '') ?></td></tr>
        <tr><th>Valor Pago</th><td><?= $fmt($pedido['total'] ?? 0) ?></td></tr>
        <tr><th>Data Pagamento</th><td><?= !empty($pedido['pago_em']) ? date('d/m/Y H:i', strtotime($pedido['pago_em'])) : 'Não pago' ?></td></tr>
    </table>
</div>

</body>
</html>
