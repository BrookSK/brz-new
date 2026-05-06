<?php
/**
 * Template de email de cobrança de parcela do carnê.
 * 
 * Variáveis disponíveis:
 * $clienteNome, $carneId, $pedidoId, $numeroParcela, $totalParcelas,
 * $valorTotal, $valorProdutos, $valorTaxas, $vencimento, $status, $urlMeuCarne
 */
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cobrança - Carnê Braziliana</title>
</head>
<body style="margin:0;padding:0;background-color:#f4f6f9;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif;">
    <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f4f6f9;padding:30px 0;">
        <tr>
            <td align="center">
                <table width="600" cellpadding="0" cellspacing="0" style="background-color:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.08);">
                    <!-- Header -->
                    <tr>
                        <td style="background-color:#1a2332;padding:24px 30px;text-align:center;">
                            <h1 style="color:#ffffff;margin:0;font-size:22px;font-weight:600;">Braziliana Shop</h1>
                        </td>
                    </tr>

                    <!-- Body -->
                    <tr>
                        <td style="padding:30px;">
                            <p style="font-size:16px;color:#333;margin:0 0 16px;">
                                Olá, <strong><?= htmlspecialchars($clienteNome) ?></strong>!
                            </p>

                            <?php if ($status === 'em_atraso' || $status === 'vencida'): ?>
                            <div style="background-color:#fff3cd;border-left:4px solid #ffc107;padding:12px 16px;margin-bottom:20px;border-radius:4px;">
                                <p style="margin:0;color:#856404;font-size:14px;">
                                    <strong>⚠️ Atenção:</strong> Esta parcela está <strong><?= $status === 'em_atraso' ? 'em atraso' : 'vencida' ?></strong>. Regularize o pagamento para evitar o cancelamento do seu carnê.
                                </p>
                            </div>
                            <?php endif; ?>

                            <p style="font-size:14px;color:#555;margin:0 0 20px;">
                                Estamos entrando em contato para lembrar sobre o pagamento da parcela do seu carnê:
                            </p>

                            <!-- Dados da parcela -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f8f9fa;border-radius:6px;margin-bottom:24px;">
                                <tr>
                                    <td style="padding:20px;">
                                        <table width="100%" cellpadding="4" cellspacing="0">
                                            <tr>
                                                <td style="font-size:13px;color:#666;padding:4px 0;">Carnê:</td>
                                                <td style="font-size:13px;color:#333;font-weight:600;padding:4px 0;text-align:right;">#<?= (int) $carneId ?> (Pedido #<?= (int) $pedidoId ?>)</td>
                                            </tr>
                                            <tr>
                                                <td style="font-size:13px;color:#666;padding:4px 0;">Parcela:</td>
                                                <td style="font-size:13px;color:#333;font-weight:600;padding:4px 0;text-align:right;"><?= (int) $numeroParcela ?> de <?= (int) $totalParcelas ?></td>
                                            </tr>
                                            <tr>
                                                <td style="font-size:13px;color:#666;padding:4px 0;">Vencimento:</td>
                                                <td style="font-size:13px;color:<?= ($status === 'em_atraso' || $status === 'vencida') ? '#dc3545' : '#333' ?>;font-weight:600;padding:4px 0;text-align:right;"><?= $vencimento ?></td>
                                            </tr>
                                            <tr>
                                                <td colspan="2" style="border-top:1px solid #dee2e6;padding-top:8px;margin-top:8px;"></td>
                                            </tr>
                                            <tr>
                                                <td style="font-size:13px;color:#666;padding:4px 0;">Produtos:</td>
                                                <td style="font-size:13px;color:#333;padding:4px 0;text-align:right;">R$ <?= $valorProdutos ?></td>
                                            </tr>
                                            <tr>
                                                <td style="font-size:13px;color:#666;padding:4px 0;">Taxas:</td>
                                                <td style="font-size:13px;color:#333;padding:4px 0;text-align:right;">R$ <?= $valorTaxas ?></td>
                                            </tr>
                                            <tr>
                                                <td style="font-size:14px;color:#333;font-weight:700;padding:8px 0 4px;">Total da parcela:</td>
                                                <td style="font-size:16px;color:#1a2332;font-weight:700;padding:8px 0 4px;text-align:right;">R$ <?= $valorTotal ?></td>
                                            </tr>
                                        </table>
                                    </td>
                                </tr>
                            </table>

                            <!-- CTA -->
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center" style="padding:10px 0 24px;">
                                        <a href="<?= htmlspecialchars($urlMeuCarne) ?>" style="display:inline-block;background-color:#1a2332;color:#ffffff;text-decoration:none;padding:14px 32px;border-radius:6px;font-size:14px;font-weight:600;">
                                            Ver meu carnê e pagar
                                        </a>
                                    </td>
                                </tr>
                            </table>

                            <p style="font-size:13px;color:#888;margin:0 0 8px;">
                                Se você já realizou o pagamento, por favor desconsidere este email. O processamento pode levar até 2 dias úteis para ser confirmado.
                            </p>

                            <p style="font-size:13px;color:#888;margin:0;">
                                Em caso de dúvidas, entre em contato com nosso suporte.
                            </p>
                        </td>
                    </tr>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color:#f8f9fa;padding:20px 30px;text-align:center;border-top:1px solid #eee;">
                            <p style="font-size:12px;color:#999;margin:0;">
                                Braziliana Shop — Este é um email automático, não responda.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
