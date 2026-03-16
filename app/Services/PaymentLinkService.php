<?php
namespace App\Services;

class PaymentLinkService {
    private \PDO $db;

    public function __construct(?\PDO $db = null) {
        $this->db = $db ?? \Config\Database::getConnection();
        $this->ensureTables();
    }

    private function tableExists(string $table): bool {
        try {
            $st = $this->db->prepare('SHOW TABLES LIKE ?');
            $st->execute([$table]);
            return (bool) $st->fetchColumn();
        } catch (\Exception $e) {
            return false;
        }
    }

    private function ensureTables(): void {
        try {
            if (!$this->tableExists('payment_links')) {
                $this->db->exec("CREATE TABLE IF NOT EXISTS `payment_links` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `token` varchar(64) NOT NULL,
                    `status` varchar(20) NOT NULL DEFAULT 'active',
                    `currency` varchar(3) NOT NULL DEFAULT 'USD',
                    `produto_valor` decimal(10,2) NOT NULL DEFAULT 0.00,
                    `taxa_servico_valor` decimal(10,2) NOT NULL DEFAULT 0.00,
                    `impostos_valor` decimal(10,2) NOT NULL DEFAULT 0.00,
                    `total_valor` decimal(10,2) NOT NULL DEFAULT 0.00,
                    `descricao` varchar(255) DEFAULT NULL,
                    `created_by_user_id` int(11) DEFAULT NULL,
                    `expires_at` datetime DEFAULT NULL,
                    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    UNIQUE KEY `uk_token` (`token`),
                    KEY `idx_status` (`status`),
                    KEY `idx_currency` (`currency`),
                    KEY `idx_expires_at` (`expires_at`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            }

            if (!$this->tableExists('payment_link_payments')) {
                $this->db->exec("CREATE TABLE IF NOT EXISTS `payment_link_payments` (
                    `id` int(11) NOT NULL AUTO_INCREMENT,
                    `payment_link_id` int(11) NOT NULL,
                    `componente` varchar(30) NOT NULL DEFAULT 'pagamento',
                    `status` varchar(20) NOT NULL DEFAULT 'pending',
                    `gateway` varchar(40) NOT NULL DEFAULT '',
                    `metodo` varchar(30) NOT NULL DEFAULT '',
                    `currency` varchar(3) NOT NULL DEFAULT 'USD',
                    `produto_valor` decimal(10,2) NOT NULL DEFAULT 0.00,
                    `taxa_servico_valor` decimal(10,2) NOT NULL DEFAULT 0.00,
                    `impostos_valor` decimal(10,2) NOT NULL DEFAULT 0.00,
                    `total_valor` decimal(10,2) NOT NULL DEFAULT 0.00,
                    `customer_name` varchar(255) DEFAULT NULL,
                    `customer_email` varchar(255) DEFAULT NULL,
                    `customer_document` varchar(40) DEFAULT NULL,
                    `customer_phone` varchar(40) DEFAULT NULL,
                    `gateway_payment_id` varchar(120) DEFAULT NULL,
                    `invoice_url` text,
                    `bank_slip_url` text,
                    `digitable_line` varchar(255) DEFAULT NULL,
                    `pix_payload` text,
                    `pix_encoded_image` longtext,
                    `metadata` longtext,
                    `raw_response` longtext,
                    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    PRIMARY KEY (`id`),
                    KEY `idx_link_id` (`payment_link_id`),
                    KEY `idx_componente` (`componente`),
                    KEY `idx_status` (`status`),
                    KEY `idx_gateway` (`gateway`)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
            } else {
                // Garantir coluna componente em instalações existentes
                try {
                    $cols = [];
                    $st = $this->db->query('DESCRIBE payment_link_payments');
                    $cols = $st ? ($st->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
                    if (!in_array('componente', $cols, true)) {
                        $this->db->exec("ALTER TABLE payment_link_payments ADD COLUMN componente varchar(30) NOT NULL DEFAULT 'pagamento' AFTER payment_link_id");
                    }
                    if (!in_array('idx_componente', $cols, true)) {
                        // ignore (index check por DESCRIBE não funciona); tenta criar e ignora erro
                        try { $this->db->exec("CREATE INDEX idx_componente ON payment_link_payments (componente)"); } catch (\Exception $e) {}
                    }
                } catch (\Exception $e) {
                }
            }
        } catch (\Exception $e) {
        }
    }

    public function createLink(array $data, int $adminUserId): array {
        $currency = strtoupper(trim((string) ($data['currency'] ?? 'USD')));
        if (!in_array($currency, ['USD', 'BRL'], true)) {
            $currency = 'USD';
        }

        $produto = (float) ($data['produto_valor'] ?? 0);
        $taxa = (float) ($data['taxa_servico_valor'] ?? 0);
        $impostos = (float) ($data['impostos_valor'] ?? 0);
        if ($produto < 0) $produto = 0.0;
        if ($taxa < 0) $taxa = 0.0;
        if ($impostos < 0) $impostos = 0.0;

        $total = round($produto + $taxa + $impostos, 2);
        if ($total <= 0) {
            return ['success' => false, 'error' => 'Informe um valor total válido.'];
        }

        $descricao = trim((string) ($data['descricao'] ?? ''));
        $expiresAt = (string) ($data['expires_at'] ?? '');
        $expiresAt = trim($expiresAt);
        if ($expiresAt === '') {
            $expiresAt = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60));
        }

        $token = '';
        try {
            $token = bin2hex(random_bytes(24));
        } catch (\Throwable $e) {
            $token = bin2hex((string) microtime(true));
        }

        try {
            $st = $this->db->prepare('INSERT INTO payment_links (token, status, currency, produto_valor, taxa_servico_valor, impostos_valor, total_valor, descricao, created_by_user_id, expires_at) VALUES (:token, :status, :currency, :produto, :taxa, :impostos, :total, :descricao, :uid, :expires_at)');
            $st->execute([
                ':token' => $token,
                ':status' => 'active',
                ':currency' => $currency,
                ':produto' => $produto,
                ':taxa' => $taxa,
                ':impostos' => $impostos,
                ':total' => $total,
                ':descricao' => $descricao !== '' ? $descricao : null,
                ':uid' => $adminUserId > 0 ? $adminUserId : null,
                ':expires_at' => $expiresAt,
            ]);
            $id = (int) $this->db->lastInsertId();

            return [
                'success' => true,
                'id' => $id,
                'token' => $token,
                'public_url' => '/pagar/' . $token,
                'currency' => $currency,
                'total' => $total,
                'produto_valor' => $produto,
                'taxa_servico_valor' => $taxa,
                'impostos_valor' => $impostos,
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Falha ao criar link de pagamento'];
        }
    }

    public function findLinkByToken(string $token): ?array {
        $token = trim($token);
        if ($token === '') return null;
        try {
            $st = $this->db->prepare('SELECT * FROM payment_links WHERE token = ? LIMIT 1');
            $st->execute([$token]);
            $row = $st->fetch(\PDO::FETCH_ASSOC) ?: null;
            return is_array($row) ? $row : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    public function listLinks(int $limit = 50): array {
        if ($limit <= 0) $limit = 50;
        if ($limit > 200) $limit = 200;
        try {
            $st = $this->db->prepare('SELECT * FROM payment_links ORDER BY id DESC LIMIT ' . (int) $limit);
            $st->execute();
            return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }

    public function createPaymentAttempt(int $linkId, array $customer, string $metodo, string $componente = 'pagamento', array $amounts = []): array {
        $linkId = (int) $linkId;
        if ($linkId <= 0) {
            return ['success' => false, 'error' => 'Link inválido'];
        }

        $metodo = strtolower(trim($metodo));
        if ($metodo === '') $metodo = 'unknown';

        $componente = strtolower(trim($componente));
        if ($componente === '') $componente = 'pagamento';

        $link = null;
        try {
            $st = $this->db->prepare('SELECT * FROM payment_links WHERE id = ? LIMIT 1');
            $st->execute([$linkId]);
            $link = $st->fetch(\PDO::FETCH_ASSOC) ?: null;
        } catch (\Exception $e) {
            $link = null;
        }
        if (!is_array($link) || empty($link['id'])) {
            return ['success' => false, 'error' => 'Link não encontrado'];
        }

        $currency = strtoupper(trim((string) ($link['currency'] ?? 'USD')));
        if ($currency === '') $currency = 'USD';

        $produto = (float) ($link['produto_valor'] ?? 0);
        $taxa = (float) ($link['taxa_servico_valor'] ?? 0);
        $impostos = (float) ($link['impostos_valor'] ?? 0);
        $total = (float) ($link['total_valor'] ?? ($produto + $taxa + $impostos));

        // Override para registrar valores por componente
        if (!empty($amounts)) {
            if (array_key_exists('produto_valor', $amounts)) {
                $produto = (float) $amounts['produto_valor'];
            }
            if (array_key_exists('taxa_servico_valor', $amounts)) {
                $taxa = (float) $amounts['taxa_servico_valor'];
            }
            if (array_key_exists('impostos_valor', $amounts)) {
                $impostos = (float) $amounts['impostos_valor'];
            }
            if (array_key_exists('total_valor', $amounts)) {
                $total = (float) $amounts['total_valor'];
            } else {
                $total = round($produto + $taxa + $impostos, 2);
            }
        }

        $name = trim((string) ($customer['name'] ?? ''));
        $email = trim((string) ($customer['email'] ?? ''));
        $doc = trim((string) ($customer['document'] ?? ''));
        $phone = trim((string) ($customer['phone'] ?? ''));

        try {
            $st = $this->db->prepare('INSERT INTO payment_link_payments (payment_link_id, componente, status, gateway, metodo, currency, produto_valor, taxa_servico_valor, impostos_valor, total_valor, customer_name, customer_email, customer_document, customer_phone) VALUES (:lid, :componente, :status, :gateway, :metodo, :currency, :produto, :taxa, :impostos, :total, :name, :email, :doc, :phone)');
            $st->execute([
                ':lid' => $linkId,
                ':componente' => $componente,
                ':status' => 'pending',
                ':gateway' => '',
                ':metodo' => $metodo,
                ':currency' => $currency,
                ':produto' => $produto,
                ':taxa' => $taxa,
                ':impostos' => $impostos,
                ':total' => $total,
                ':name' => $name !== '' ? $name : null,
                ':email' => $email !== '' ? $email : null,
                ':doc' => $doc !== '' ? $doc : null,
                ':phone' => $phone !== '' ? $phone : null,
            ]);

            $id = (int) $this->db->lastInsertId();
            return ['success' => true, 'id' => $id, 'link' => $link];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => 'Falha ao registrar tentativa de pagamento'];
        }
    }

    public function updatePaymentAttempt(int $paymentAttemptId, array $fields): void {
        $paymentAttemptId = (int) $paymentAttemptId;
        if ($paymentAttemptId <= 0) return;

        $allowed = [
            'status', 'gateway', 'gateway_payment_id',
            'invoice_url', 'bank_slip_url', 'digitable_line',
            'pix_payload', 'pix_encoded_image',
            'metadata', 'raw_response'
        ];

        $set = [];
        $params = [':id' => $paymentAttemptId];
        foreach ($allowed as $k) {
            if (!array_key_exists($k, $fields)) continue;
            $set[] = $k . ' = :' . $k;
            $params[':' . $k] = is_string($fields[$k]) ? $fields[$k] : (is_null($fields[$k]) ? null : json_encode($fields[$k], JSON_UNESCAPED_UNICODE));
        }
        if (empty($set)) return;

        try {
            $sql = 'UPDATE payment_link_payments SET ' . implode(', ', $set) . ', updated_at = NOW() WHERE id = :id';
            $st = $this->db->prepare($sql);
            $st->execute($params);
        } catch (\Exception $e) {
        }
    }

    public function listPaymentsByLinkId(int $linkId, int $limit = 100): array {
        $linkId = (int) $linkId;
        if ($linkId <= 0) return [];
        if ($limit <= 0) $limit = 100;
        if ($limit > 500) $limit = 500;

        try {
            $st = $this->db->prepare('SELECT * FROM payment_link_payments WHERE payment_link_id = ? ORDER BY id DESC LIMIT ' . (int) $limit);
            $st->execute([$linkId]);
            return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }

    public function findPaymentAttemptById(int $id): ?array {
        $id = (int) $id;
        if ($id <= 0) return null;
        try {
            $st = $this->db->prepare('SELECT * FROM payment_link_payments WHERE id = ? LIMIT 1');
            $st->execute([$id]);
            $row = $st->fetch(\PDO::FETCH_ASSOC);
            return is_array($row) ? $row : null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
