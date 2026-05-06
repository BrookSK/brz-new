<?php
/**
 * Template genérico de notificação do carnê.
 * 
 * Variáveis disponíveis:
 * $clienteNome, $carneId, $pedidoId, $evento, $titulo, $mensagem,
 * $detalhes (array de label=>valor), $alerta (null|'warning'|'danger'|'success'),
 * $alertaMensagem, $urlMeuCarne, $ctaTexto
 */
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($titulo) ?></title>
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

                            <?php if (!empty($alerta) && !empty($alertaMensagem)): ?>
                            <?php
                                $alertaCores = [
                                    'warning' => ['bg' => '#fff3cd', 'border' => '#ffc107', 'text' => '#856404'],
                                    'danger'  => ['bg' => '#f8d7da', 'border' => '#dc3545', 'text' => '#721c24'],
                                    'success' => ['bg' => '#d4edda', 'border' => '#28a745', 'text' => '#155724'],
                                ];
                                $cor = $alertaCores[$alerta] ?? $alertaCores['warning'];
                            ?>
                            <div style="background-color:<?= $cor['bg'] ?>;border-left:4px solid <?= $cor['border'] ?>;padding:12px 16px;margin-bottom:20px;border-radius:4px;">
                                <p style="margin:0;color:<?= $cor['text'] ?>;font-size:14px;">
                                    <?= $alertaMensagem ?>
                                </p>
                            </div>
                            <?php endif; ?>

                            <p style="font-size:14px;color:#555;margin:0 0 20px;">
                                <?= $mensagem ?>
                            </p>

                            <?php if (!empty($detalhes)): ?>
                            <!-- Detalhes -->
                            <table width="100%" cellpadding="0" cellspacing="0" style="background-color:#f8f9fa;border-radius:6px;margin-bottom:24px;">
                                <tr>
                                    <td style="padding:20px;">
                                        <table width="100%" cellpadding="4" cellspacing="0">
                                            <?php foreach ($detalhes as $label => $valor): ?>
                                            <tr>
                                                <td style="font-size:13px;color:#666;padding:4px 0;"><?= htmlspecialchars($label) ?>:</td>
                                                <td style="font-size:13px;color:#333;font-weight:600;padding:4px 0;text-align:right;"><?= htmlspecialchars($valor) ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </table>
                                    </td>
                                </tr>
                            </table>
                            <?php endif; ?>

                            <!-- CTA -->
                            <?php if (!empty($urlMeuCarne)): ?>
                            <table width="100%" cellpadding="0" cellspacing="0">
                                <tr>
                                    <td align="center" style="padding:10px 0 24px;">
                                        <a href="<?= htmlspecialchars($urlMeuCarne) ?>" style="display:inline-block;background-color:#1a2332;color:#ffffff;text-decoration:none;padding:14px 32px;border-radius:6px;font-size:14px;font-weight:600;">
                                            <?= htmlspecialchars($ctaTexto ?? 'Ver meu carnê') ?>
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            <?php endif; ?>

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
