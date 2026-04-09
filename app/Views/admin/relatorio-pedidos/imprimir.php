<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<title>Pedido #<?= $pedido['id'] ?> — <?= htmlspecialchars($pedido['cliente_nome'] ?? '') ?></title>
<style>
body{font-family:Arial,sans-serif;margin:20px;color:#333;font-size:12px;}
h1{font-size:18px;text-align:center;margin:0 0 5px;}
.meta{text-align:center;font-size:10px;color:#666;margin-bottom:15px;}
.section{margin-bottom:15px;page-break-inside:avoid;}
.section-title{font-weight:bold;font-size:13px;background:#f5f5f5;padding:4px 8px;border-left:3px solid #0b1f3a;margin-bottom:8px;}
table{width:100%;border-collapse:collapse;font-size:11px;margin-bottom:10px;}
th,td{padding:5px 8px;border:1px solid #ddd;text-align:left;vertical-align:top;}
th{background:#f8f9fa;font-weight:bold;width:30%;}
.item-table th{width:auto;}
.item-table img{width:50px;height:50px;object-fit:cover;border-radius:4px;}
.total-row{font-weight:bold;background:#f1f1f1;}
.highlight{color:red;font-weight:bold;}
.no-print{margin-bottom:10px;}
@media print{.no-print{display:none;} @page{margin:10mm;}}
</style>
</head>
<body>
<div class="no-print">
    <button onclick="window.print()">Imprimir / Salvar PDF</button>
    <button onclick="window.close()">Fechar</button>
</div>

<h1>Detalhes do Pedido #<?= $pedido['id'] ?></h1>
<div class="meta">
    <?= htmlspecialchars($pedido['codigo_pedido'] ?? '') ?> |
    Data: <?= date('d/m/Y H:i', strtotime($pedido['created_at'])) ?> |
    Status: <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $pedido['status'] ?? ''))) ?> |
    Moeda: <?= $moeda ?>
</div>

<!-- Cliente -->
<div class="section">
    <div class="section-title">Informações do Cliente</div>
    <table>
        <tr><th>Nome</th><td><?= htmlspecialchars($clienteConsolidado['nome']) ?></td></tr>
        <tr><th>Email</th><td><?= htmlspecialchars($clienteConsolidado['email']) ?></td></tr>
        <tr><th>CPF/CNPJ</th><td><?= htmlspecialchars($clienteConsolidado['cpf']) ?></td></tr>
        <tr><th>Telefone</th><td><?= htmlspecialchars($clienteConsolidado['telefone']) ?></td></tr>
        <?php if ($clienteConsolidado['suite']): ?><tr><th>Suite</th><td class="highlight"><?= htmlspecialchars($clienteConsolidado['suite']) ?></td></tr><?php endif; ?>
        <?php if ($clienteConsolidado['data_nascimento']): ?><tr><th>Data Nascimento</th><td><?= htmlspecialchars($clienteConsolidado['data_nascimento']) ?></td></tr><?php endif; ?>
        <?php if ($tracking): ?><tr><th>Código de Rastreio</th><td class="highlight"><?= htmlspecialchars($tracking) ?></td></tr><?php endif; ?>
    </table>
</div>

<!-- Endereço de Entrega -->
<div class="section">
    <div class="section-title">Endereço de Entrega</div>
    <table>
        <?php if (!empty($endEntrega['endereco'])): ?>
        <tr><th>Rua</th><td><?= htmlspecialchars($endEntrega['endereco']) ?></td></tr>
        <tr><th>Número</th><td><?= htmlspecialchars($endEntrega['numero'] ?? '') ?></td></tr>
        <?php if (!empty($endEntrega['complemento'])): ?><tr><th>Complemento</th><td><?= htmlspecialchars($endEntrega['complemento']) ?></td></tr><?php endif; ?>
        <tr><th>Bairro</th><td><?= htmlspecialchars($endEntrega['bairro'] ?? '') ?></td></tr>
        <tr><th>Cidade</th><td><?= htmlspecialchars($endEntrega['cidade'] ?? '') ?></td></tr>
        <tr><th>Estado</th><td><?= htmlspecialchars($endEntrega['estado'] ?? '') ?></td></tr>
        <tr><th>CEP</th><td><?= htmlspecialchars($endEntrega['cep'] ?? '') ?></td></tr>
        <tr><th>País</th><td><?= htmlspecialchars($endEntrega['pais'] ?? 'BR') ?></td></tr>
        <?php else: ?>
        <tr><td colspan="2" class="text-muted">Endereço não informado</td></tr>
        <?php endif; ?>
    </table>
</div>

<!-- Destinatário (se diferente) -->
<?php if (!empty($destinatario['nome'])): ?>
<div class="section">
    <div class="section-title">Destinatário (entrega para outra pessoa)</div>
    <table>
        <tr><th>Nome</th><td><?= htmlspecialchars($destinatario['nome']) ?></td></tr>
        <?php if (!empty($destinatario['documento'])): ?><tr><th>CPF/Doc</th><td><?= htmlspecialchars($destinatario['documento']) ?></td></tr><?php endif; ?>
        <?php if (!empty($destinatario['telefone'])): ?><tr><th>Telefone</th><td><?= htmlspecialchars($destinatario['telefone']) ?></td></tr><?php endif; ?>
    </table>
</div>
<?php endif; ?>

<!-- Itens -->
<div class="section">
    <div class="section-title">Itens do Pedido</div>
    <table class="item-table">
        <thead><tr><th style="width:60px">Foto</th><th>Produto</th><th>Qtd</th><th>Preço Unit.</th><th>Total</th></tr></thead>
        <tbody>
        <?php
        $pesoTotal = 0;
        foreach ($itens as $it):
            $nome = (string)($it['nome_produto'] ?? '');
            $foto = (string)($it['imagem'] ?? '');
            $qtd = (int)($it['quantidade'] ?? 1);
            $preco = (float)($it['preco_unitario'] ?? 0);
            $sub = (float)($it['subtotal'] ?? ($preco * $qtd));
            $peso = (float)($it['peso'] ?? ($it['weight'] ?? 0));
            // Buscar peso do produto se não veio no item
            if ($peso <= 0 && !empty($it['produto_id'])) {
                try {
                    $dbP = \Config\Database::getConnection();
                    $stP = $dbP->prepare("SELECT COALESCE(peso, weight, 0) AS peso FROM produtos WHERE id = ? LIMIT 1");
                    $stP->execute([(int)$it['produto_id']]);
                    $peso = (float)($stP->fetchColumn() ?: 0);
                } catch (\Exception $e) {}
            }
            $pesoTotal += $peso * $qtd;
        ?>
            <tr>
                <td><?= $foto ? '<img src="'.htmlspecialchars($foto).'">' : '' ?></td>
                <td><?= htmlspecialchars($nome) ?></td>
                <td><?= $qtd ?></td>
                <td><?= $fmt($preco) ?></td>
                <td><?= $fmt($sub) ?></td>
            </tr>
        <?php endforeach; ?>
            <tr class="total-row"><td colspan="4">Subtotal</td><td><?= $fmt($pedido['subtotal'] ?? 0) ?></td></tr>
            <tr class="total-row"><td colspan="4">Taxa de Serviço</td><td><?= $fmt($pedido['servicos'] ?? 0) ?></td></tr>
            <tr class="total-row"><td colspan="4">Impostos</td><td><?= $fmt($pedido['impostos'] ?? 0) ?></td></tr>
            <?php if (((float)($pedido['imposto_local'] ?? 0)) > 0): ?>
            <tr class="total-row"><td colspan="4">Imposto Local</td><td><?= $fmt($pedido['imposto_local']) ?></td></tr>
            <?php endif; ?>
            <tr class="total-row"><td colspan="4">Frete</td><td><?= ((float)($pedido['frete'] ?? 0)) <= 0 ? '<span class="highlight">Frete Grátis</span>' : $fmt($pedido['frete']) ?></td></tr>
            <tr class="total-row"><td colspan="4">Peso Total</td><td class="highlight"><?= number_format($pesoTotal, 3, ',', '.') ?> kg</td></tr>
            <tr class="total-row"><td colspan="4" style="font-size:13px;">Total do Pedido</td><td style="font-size:13px;"><?= $fmt($pedido['total'] ?? 0) ?></td></tr>
        </tbody>
    </table>
</div>

<!-- Pagamento -->
<div class="section">
    <div class="section-title">Pagamento</div>
    <table>
        <tr><th>Método</th><td><?= htmlspecialchars($pedido['forma_pagamento'] ?? '') ?></td></tr>
        <tr><th>Valor Pago</th><td><?= $fmt($pedido['total'] ?? 0) ?></td></tr>
        <tr><th>Data Pagamento</th><td><?= !empty($pedido['pago_em']) ? date('d/m/Y H:i', strtotime($pedido['pago_em'])) : 'Não pago' ?></td></tr>
        <tr><th>Gateway</th><td><?= htmlspecialchars($pedido['payment_gateway'] ?? ($pedido['gateway'] ?? '')) ?></td></tr>
    </table>
</div>

<!-- Observações -->
<?php if (!empty($pedido['observacoes'])): ?>
<div class="section">
    <div class="section-title">Observações</div>
    <p><?= nl2br(htmlspecialchars($pedido['observacoes'])) ?></p>
</div>
<?php endif; ?>

</body>
</html>
