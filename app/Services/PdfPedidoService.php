<?php
namespace App\Services;

class PdfPedidoService {
    public function renderPedidoHtml(array $pedido, array $itens, ?array $paymentDetails = null): string {
        $codigo = (string) ($pedido['codigo_pedido'] ?? $pedido['numero_pedido'] ?? $pedido['id'] ?? '');
        $dataCriacao = '';
        if (!empty($pedido['created_at'])) {
            try {
                $dataCriacao = date('d/m/Y H:i', strtotime((string) $pedido['created_at']));
            } catch (\Exception $e) {
                $dataCriacao = (string) $pedido['created_at'];
            }
        }

        $clienteNome = (string) ($pedido['cliente_nome'] ?? '');
        $clienteEmail = (string) ($pedido['cliente_email'] ?? '');
        $clienteTelefone = (string) ($pedido['cliente_telefone'] ?? '');

        $subtotal = (float) ($pedido['subtotal_produtos'] ?? ($pedido['subtotal'] ?? 0));
        $frete = (float) ($pedido['valor_frete'] ?? ($pedido['frete'] ?? 0));
        $taxaServico = (float) ($pedido['taxa_servico'] ?? ($pedido['servicos'] ?? 0));
        $impostos = (float) ($pedido['valor_impostos'] ?? ($pedido['impostos'] ?? 0));
        $total = (float) ($pedido['valor_total'] ?? ($pedido['total'] ?? 0));

        $status = (string) ($pedido['status'] ?? '');
        $moeda = (string) ($pedido['moeda'] ?? ($pedido['currency'] ?? 'BRL'));

        $paymentStatus = (string) ($pedido['pagamento_status'] ?? ($pedido['payment_status'] ?? ''));
        $paymentGateway = (string) ($pedido['pagamento_gateway'] ?? ($pedido['payment_gateway'] ?? ''));
        $paymentId = (string) ($pedido['pagamento_transacao'] ?? ($pedido['payment_id'] ?? ''));

        $invoiceUrl = '';
        if (is_array($paymentDetails) && !empty($paymentDetails['invoiceUrl'])) {
            $invoiceUrl = (string) $paymentDetails['invoiceUrl'];
        } elseif (!empty($pedido['payment_invoice_url'])) {
            $invoiceUrl = (string) $pedido['payment_invoice_url'];
        }

        $fmtMoney = function(float $v) use ($moeda): string {
            if (strtoupper($moeda) === 'BRL') {
                return 'R$ ' . number_format($v, 2, ',', '.');
            }
            return strtoupper($moeda) . ' ' . number_format($v, 2, '.', ',');
        };

        $freteLabel = ($frete <= 0.0) ? 'Frete grátis' : $fmtMoney($frete);

        $rows = '';
        $idx = 1;
        foreach ($itens as $it) {
            $nome = (string) ($it['nome_produto'] ?? $it['produto_nome'] ?? 'Produto');
            $qtd = (int) ($it['quantidade'] ?? 0);
            $pu = (float) ($it['preco_unitario'] ?? $it['valor_unitario'] ?? 0);
            $sub = (float) ($it['subtotal'] ?? ($pu * $qtd));

            $rows .= '<tr>'
                . '<td style="text-align:center;">' . $idx . '</td>'
                . '<td>' . htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td style="text-align:center;">' . $qtd . '</td>'
                . '<td style="text-align:right;">' . htmlspecialchars($fmtMoney($pu), ENT_QUOTES, 'UTF-8') . '</td>'
                . '<td style="text-align:right;">' . htmlspecialchars($fmtMoney($sub), ENT_QUOTES, 'UTF-8') . '</td>'
                . '</tr>';
            $idx++;
        }

        if ($rows === '') {
            $rows = '<tr><td colspan="5" style="text-align:center; padding: 12px;">Nenhum item</td></tr>';
        }

        $html = '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8">'
            . '<style>'
            . '@page { margin: 24px 28px; }'
            . 'body { font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size: 12px; color: #111827; }'
            . '.doc-header { border: 1px solid #111827; padding: 14px 14px 10px; margin-bottom: 14px; }'
            . '.doc-title { font-size: 16px; font-weight: 800; letter-spacing: 0.4px; }'
            . '.doc-sub { margin-top: 2px; font-size: 11px; color: #374151; }'
            . '.grid { width: 100%; border-collapse: collapse; margin-top: 10px; }'
            . '.grid td { vertical-align: top; }'
            . '.box { border: 1px solid #111827; padding: 10px; }'
            . '.box-title { font-weight: 800; margin-bottom: 6px; }'
            . '.kv { width: 100%; border-collapse: collapse; }'
            . '.kv td { padding: 2px 0; }'
            . '.kv td:first-child { color: #374151; width: 140px; }'
            . '.items { width: 100%; border-collapse: collapse; margin-top: 12px; }'
            . '.items th, .items td { border: 1px solid #111827; padding: 7px 8px; }'
            . '.items th { background: #f3f4f6; text-align: left; }'
            . '.totals { width: 100%; border-collapse: collapse; margin-top: 12px; }'
            . '.totals td { padding: 4px 0; }'
            . '.totals .label { color: #374151; }'
            . '.totals .value { text-align: right; }'
            . '.totals .grand { font-weight: 900; font-size: 14px; border-top: 2px solid #111827; padding-top: 8px; }'
            . '.footer { margin-top: 14px; border-top: 1px solid #9ca3af; padding-top: 10px; font-size: 10px; color: #374151; }'
            . '</style></head><body>'
            . '<div class="doc-header">'
            . '<div class="doc-title">DOCUMENTO DE PEDIDO</div>'
            . '<div class="doc-sub">Pedido: <strong>' . htmlspecialchars($codigo, ENT_QUOTES, 'UTF-8') . '</strong>'
            . ($dataCriacao !== '' ? (' &nbsp;|&nbsp; Emitido em: <strong>' . htmlspecialchars($dataCriacao, ENT_QUOTES, 'UTF-8') . '</strong>') : '')
            . '</div>'
            . '</div>'
            . '<table class="grid"><tr>'
            . '<td style="width: 52%; padding-right: 10px;">'
            . '<div class="box">'
            . '<div class="box-title">Dados do Cliente</div>'
            . '<table class="kv">'
            . '<tr><td>Nome:</td><td><strong>' . htmlspecialchars($clienteNome, ENT_QUOTES, 'UTF-8') . '</strong></td></tr>'
            . '<tr><td>E-mail:</td><td>' . htmlspecialchars($clienteEmail, ENT_QUOTES, 'UTF-8') . '</td></tr>'
            . '<tr><td>Telefone:</td><td>' . htmlspecialchars($clienteTelefone, ENT_QUOTES, 'UTF-8') . '</td></tr>'
            . '</table>'
            . '</div>'
            . '</td>'
            . '<td style="width: 48%;">'
            . '<div class="box">'
            . '<div class="box-title">Dados do Pedido</div>'
            . '<table class="kv">'
            . '<tr><td>Status:</td><td><strong>' . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . '</strong></td></tr>'
            . '<tr><td>Moeda:</td><td>' . htmlspecialchars($moeda, ENT_QUOTES, 'UTF-8') . '</td></tr>'
            . '<tr><td>Pagamento:</td><td>' . htmlspecialchars($paymentGateway, ENT_QUOTES, 'UTF-8') . ' ' . htmlspecialchars($paymentStatus, ENT_QUOTES, 'UTF-8') . '</td></tr>'
            . '<tr><td>Payment ID:</td><td>' . htmlspecialchars($paymentId, ENT_QUOTES, 'UTF-8') . '</td></tr>'
            . ($invoiceUrl !== '' ? ('<tr><td>Link:</td><td>' . htmlspecialchars($invoiceUrl, ENT_QUOTES, 'UTF-8') . '</td></tr>') : '')
            . '</table>'
            . '</div>'
            . '</td>'
            . '</tr></table>'
            . '<table class="items">'
            . '<thead><tr>'
            . '<th style="width: 36px; text-align:center;">#</th>'
            . '<th>Item</th>'
            . '<th style="width: 70px; text-align:center;">Qtd</th>'
            . '<th style="width: 120px; text-align:right;">Valor Unit.</th>'
            . '<th style="width: 120px; text-align:right;">Subtotal</th>'
            . '</tr></thead>'
            . '<tbody>' . $rows . '</tbody>'
            . '</table>'
            . '<table class="totals">'
            . '<tr><td class="label">Subtotal:</td><td class="value">' . htmlspecialchars($fmtMoney($subtotal), ENT_QUOTES, 'UTF-8') . '</td></tr>'
            . '<tr><td class="label">Taxa de Serviço:</td><td class="value">' . htmlspecialchars($fmtMoney($taxaServico), ENT_QUOTES, 'UTF-8') . '</td></tr>'
            . '<tr><td class="label">Impostos:</td><td class="value">' . htmlspecialchars($fmtMoney($impostos), ENT_QUOTES, 'UTF-8') . '</td></tr>'
            . '<tr><td class="label">Frete:</td><td class="value">' . htmlspecialchars($freteLabel, ENT_QUOTES, 'UTF-8') . '</td></tr>'
            . '<tr><td class="label grand">Total:</td><td class="value grand">' . htmlspecialchars($fmtMoney($total), ENT_QUOTES, 'UTF-8') . '</td></tr>'
            . '</table>'
            . '<div class="footer">Documento gerado pelo sistema. Este documento é válido como comprovante de pedido.</div>'
            . '</body></html>';

        return $html;
    }

    public function outputPdfOrHtml(string $html, string $filenameBase): void {
        $filename = preg_replace('/[^a-zA-Z0-9_\-]+/', '_', $filenameBase);
        if ($filename === '' || $filename === null) {
            $filename = 'documento';
        }

        if (class_exists('Dompdf\\Dompdf')) {
            $dompdf = new \Dompdf\Dompdf([
                'isRemoteEnabled' => true,
                'isHtml5ParserEnabled' => true,
            ]);
            $dompdf->loadHtml($html, 'UTF-8');
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . $filename . '.pdf"');
            echo $dompdf->output();
            return;
        }

        header('Content-Type: text/html; charset=UTF-8');
        echo $html;
    }
}
