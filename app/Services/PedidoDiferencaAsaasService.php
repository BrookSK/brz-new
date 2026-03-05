<?php
namespace App\Services;

class PedidoDiferencaAsaasService {
    private \PDO $db;

    public function __construct(?\PDO $db = null) {
        $this->db = $db ?? \Config\Database::getConnection();
    }

    private function tableExists(string $table): bool {
        try {
            $stmt = $this->db->prepare('SHOW TABLES LIKE ?');
            $stmt->execute([$table]);
            return (bool) $stmt->fetchColumn();
        } catch (\Exception $e) {
            return false;
        }
    }

    private function columnExists(string $table, string $column): bool {
        try {
            $stmt = $this->db->prepare('SHOW COLUMNS FROM `' . $table . '` LIKE ?');
            $stmt->execute([$column]);
            return (bool) $stmt->fetchColumn();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Gera cobrança de diferença no Asaas para um pedido.
     * Diferença = (novo_total_do_pedido) - (valor_ja_pago).
     *
     * Valor já pago = valor do payment original do pedido (Asaas) + (eventual diferença já quitada, se houver colunas).
     */
    public function gerarCobrancaDiferenca(int $pedidoId, ?float $valorOverride = null): array {
        if ($pedidoId <= 0) {
            throw new \Exception('Pedido inválido');
        }

        if (!$this->tableExists('pedidos')) {
            throw new \Exception('Tabela pedidos não encontrada');
        }

        $stmt = $this->db->prepare('SELECT * FROM pedidos WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $pedidoId]);
        $pedido = $stmt->fetch(\PDO::FETCH_ASSOC);
        if (!$pedido) {
            throw new \Exception('Pedido não encontrado');
        }

        $gateway = strtolower((string) ($pedido['payment_gateway'] ?? ''));
        $paymentId = (string) ($pedido['payment_id'] ?? '');
        if ($gateway !== 'asaas' || $paymentId === '') {
            throw new \Exception('Pedido sem pagamento Asaas vinculado');
        }

        $novoTotal = 0.0;
        if (isset($pedido['total'])) {
            $novoTotal = (float) $pedido['total'];
        } elseif (isset($pedido['valor_total'])) {
            $novoTotal = (float) $pedido['valor_total'];
        }
        if ($novoTotal <= 0) {
            throw new \Exception('Pedido sem total válido para calcular diferença');
        }

        // Valor já pago: pelo payment original do pedido
        $pg = new PaymentService();
        $payment = $pg->obterPagamentoAsaas($paymentId);

        $valorPagoOriginal = 0.0;
        if (isset($payment['value'])) {
            $valorPagoOriginal = (float) $payment['value'];
        }

        // Se existir uma diferença já quitada no pedido, somar no "valor já pago".
        $valorPagoDiferenca = 0.0;
        $temColsDiferenca = $this->columnExists('pedidos', 'payment_diferenca_paid_at')
            && $this->columnExists('pedidos', 'payment_diferenca_valor');
        if ($temColsDiferenca) {
            $paidAt = (string) ($pedido['payment_diferenca_paid_at'] ?? '');
            if ($paidAt !== '') {
                $valorPagoDiferenca = (float) ($pedido['payment_diferenca_valor'] ?? 0);
            }
        }

        $valorJaPago = $valorPagoOriginal + $valorPagoDiferenca;
        $diferenca = 0.0;
        if ($valorOverride !== null && (float) $valorOverride > 0) {
            $diferenca = round((float) $valorOverride, 2);
        } else {
            $diferenca = round($novoTotal - $valorJaPago, 2);
            if ($diferenca <= 0) {
                throw new \Exception('Sem diferença a cobrar (novo total não excede o valor já pago)');
            }
        }

        $customerId = (string) ($payment['customer'] ?? '');
        if ($customerId === '') {
            throw new \Exception('Não foi possível identificar o cliente no Asaas para este pedido');
        }

        $billingType = strtoupper((string) ($payment['billingType'] ?? ''));
        if (!in_array($billingType, ['PIX', 'BOLETO'], true)) {
            $billingType = 'BOLETO';
        }

        $codigo = (string) ($pedido['codigo_pedido'] ?? '');
        $descricao = 'Diferença do pedido #' . ($codigo !== '' ? $codigo : (string) $pedidoId);

        $svc = new AsaasLinkService();
        $created = $svc->criarCobrancaDiferenca(
            $customerId,
            (float) $diferenca,
            $descricao,
            date('Y-m-d', strtotime('+1 day')),
            (string) $pedidoId,
            $billingType
        );

        // Persistir no pedido (se houver colunas).
        $cols = [];
        try {
            $stmtCols = $this->db->query('DESCRIBE pedidos');
            $cols = $stmtCols ? $stmtCols->fetchAll(\PDO::FETCH_COLUMN) : [];
        } catch (\Exception $e) {
            $cols = [];
        }

        if (is_array($cols) && !empty($cols)) {
            $set = [];
            $params = [':id' => $pedidoId];

            if (in_array('payment_diferenca_id', $cols, true)) {
                $set[] = 'payment_diferenca_id = :pid';
                $params[':pid'] = (string) ($created['id'] ?? '');
            }
            if (in_array('payment_diferenca_status', $cols, true)) {
                $set[] = 'payment_diferenca_status = :pst';
                $params[':pst'] = (string) ($created['status'] ?? '');
            }
            if (in_array('payment_diferenca_valor', $cols, true)) {
                $set[] = 'payment_diferenca_valor = :pval';
                $params[':pval'] = (float) $diferenca;
            }
            if (in_array('payment_diferenca_invoice_url', $cols, true)) {
                $set[] = 'payment_diferenca_invoice_url = :inv';
                $params[':inv'] = (string) ($created['invoiceUrl'] ?? '');
            }
            if (in_array('payment_diferenca_bank_slip_url', $cols, true)) {
                $set[] = 'payment_diferenca_bank_slip_url = :boleto';
                $params[':boleto'] = (string) ($created['bankSlipUrl'] ?? '');
            }
            if (in_array('payment_diferenca_created_at', $cols, true)) {
                $set[] = 'payment_diferenca_created_at = NOW()';
            }
            if (in_array('payment_diferenca_paid_at', $cols, true)) {
                $set[] = 'payment_diferenca_paid_at = NULL';
            }

            if (!empty($set)) {
                $stmtUp = $this->db->prepare('UPDATE pedidos SET ' . implode(', ', $set) . ' WHERE id = :id LIMIT 1');
                $stmtUp->execute($params);
            }
        }

        return [
            'pedido_id' => $pedidoId,
            'novo_total' => $novoTotal,
            'valor_ja_pago' => $valorJaPago,
            'diferenca' => $diferenca,
            'billingType' => $billingType,
            'created' => $created,
        ];
    }
}
