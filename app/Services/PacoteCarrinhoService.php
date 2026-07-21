<?php
namespace App\Services;

/**
 * Service responsável pela auto-adição de pacotes pendentes ao carrinho do cliente.
 * Chamado no CarrinhoController ao carregar o carrinho, com cache via session.
 */
class PacoteCarrinhoService {
    private $connection;

    public function __construct() {
        $this->connection = \Config\Database::getConnection();
    }

    /**
     * Auto-adicionar pacotes pendentes ao carrinho do usuario
     * Usa cache de session para não consultar DB a cada request
     */
    public function autoAdicionarPacotesPendentes(int $usuarioId): void {
        if ($usuarioId <= 0) return;

        // Garantir que as colunas necessárias existem
        $this->ensureColumns();

        // Cache: só verificar a cada 2 minutos (desativado temporariamente para debug)
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        // $cacheKey = 'pacotes_auto_check_' . $usuarioId;
        // $lastCheck = (int) ($_SESSION[$cacheKey] ?? 0);
        // if ($lastCheck > 0 && (time() - $lastCheck) < 120) {
        //     return; // Verificou há menos de 2 min
        // }
        // $_SESSION[$cacheKey] = time();

        // Buscar suite do usuario
        $suite = $this->getUserSuite($usuarioId);
        if (!$suite) {
            error_log('[PacoteCarrinhoService] Sem suite para uid=' . $usuarioId);
            return;
        }

        // Buscar pacotes pendentes
        $pacotes = $this->getPacotesPendentes($suite);
        error_log('[PacoteCarrinhoService] suite=' . $suite . ' pacotes_pendentes=' . count($pacotes));
        if (empty($pacotes)) return;

        // Buscar/criar carrinho
        $cartId = $this->getOrCreateCart($usuarioId);
        if ($cartId <= 0) {
            error_log('[PacoteCarrinhoService] Nao conseguiu pegar cartId para uid=' . $usuarioId);
            return;
        }

        error_log('[PacoteCarrinhoService] cartId=' . $cartId . ' inserindo pacotes...');
        foreach ($pacotes as $pacote) {
            // Verificar se já está no carrinho
            if ($this->pacoteJaNoCarrinho($cartId, (int) $pacote['id'])) {
                continue;
            }

            // Adicionar ao carrinho
            $this->adicionarPacoteAoCarrinho($cartId, $pacote);
        }
    }

    /**
     * Auto-adicionar faturas adicionais pendentes ao carrinho
     */
    public function autoAdicionarFaturasPendentes(int $usuarioId): void {
        if ($usuarioId <= 0) return;

        try {
            $stmt = $this->connection->prepare(
                "SELECT fa.* FROM pedido_faturas_adicionais fa
                 INNER JOIN pedidos p ON p.id = fa.pedido_id
                 WHERE p.usuario_id = ? AND fa.status = 'pendente'"
            );
            $stmt->execute([$usuarioId]);
            $faturas = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            if (empty($faturas)) return;

            $cartId = $this->getOrCreateCart($usuarioId);
            if ($cartId <= 0) return;

            foreach ($faturas as $fatura) {
                // Verificar se já está no carrinho
                $stCheck = $this->connection->prepare(
                    "SELECT id FROM carrinho_items WHERE carrinho_id = ? AND fatura_adicional_id = ? AND tipo_item = 'fatura_adicional' LIMIT 1"
                );
                $stCheck->execute([$cartId, $fatura['id']]);
                if ($stCheck->fetchColumn()) continue;

                // Adicionar
                $stIns = $this->connection->prepare(
                    "INSERT INTO carrinho_items (carrinho_id, produto_id, quantidade, valor_unitario, subtotal, tipo_item, fatura_adicional_id, nome_item)
                     VALUES (?, 0, 1, ?, ?, 'fatura_adicional', ?, ?)"
                );
                $stIns->execute([
                    $cartId,
                    $fatura['valor'],
                    $fatura['valor'],
                    $fatura['id'],
                    'Fatura Adicional: ' . ($fatura['motivo'] ?? 'Cobrança'),
                ]);
            }
        } catch (\Throwable $e) {
            error_log('[PacoteCarrinhoService] Erro faturas: ' . $e->getMessage());
        }
    }

    // ==================== Métodos privados ====================

    /**
     * Garantir que as colunas necessárias existem na tabela carrinho_items
     */
    private function ensureColumns(): void {
        try {
            $cols = [];
            $st = $this->connection->query('DESCRIBE carrinho_items');
            $cols = $st ? ($st->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];

            $needed = [
                'tipo_item' => "ALTER TABLE carrinho_items ADD COLUMN tipo_item VARCHAR(30) NOT NULL DEFAULT 'produto'",
                'pacote_id' => "ALTER TABLE carrinho_items ADD COLUMN pacote_id INT NULL",
                'fatura_adicional_id' => "ALTER TABLE carrinho_items ADD COLUMN fatura_adicional_id INT NULL",
                'nome_item' => "ALTER TABLE carrinho_items ADD COLUMN nome_item VARCHAR(255) NULL",
                'peso_kg' => "ALTER TABLE carrinho_items ADD COLUMN peso_kg DECIMAL(6,3) NULL",
                'foto_url' => "ALTER TABLE carrinho_items ADD COLUMN foto_url TEXT NULL",
                'declaration_value' => "ALTER TABLE carrinho_items ADD COLUMN declaration_value DECIMAL(10,2) NULL",
            ];

            foreach ($needed as $col => $sql) {
                if (!in_array($col, $cols, true)) {
                    try {
                        $this->connection->exec($sql);
                    } catch (\Throwable $e) {
                        // Coluna pode já existir em cenário de race condition
                    }
                }
            }
        } catch (\Throwable $e) {
            // Se não conseguir verificar, segue em frente (pode falhar depois)
        }
    }

    /**
     * Garantir que a tabela pacotes_recebidos existe
     */
    private function tableExists(string $table): bool {
        try {
            $stmt = $this->connection->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1');
            $stmt->execute([$table]);
            return (bool) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function getUserSuite(int $usuarioId): ?int {
        try {
            // Verificar se a coluna suite existe
            $cols = [];
            try {
                $st = $this->connection->query('DESCRIBE usuarios');
                $cols = $st ? ($st->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            } catch (\Throwable $e) {
                $cols = [];
            }
            if (!in_array('suite', $cols, true)) {
                error_log('[PacoteCarrinhoService] Coluna suite nao existe na tabela usuarios');
                return null;
            }

            $stmt = $this->connection->prepare('SELECT suite FROM usuarios WHERE id = ? LIMIT 1');
            $stmt->execute([$usuarioId]);
            $suite = $stmt->fetchColumn();
            $suiteInt = ($suite !== false && $suite !== null && $suite !== '') ? (int) $suite : 0;
            error_log('[PacoteCarrinhoService] getUserSuite uid=' . $usuarioId . ' suite=' . var_export($suite, true) . ' int=' . $suiteInt);
            return $suiteInt > 0 ? $suiteInt : null;
        } catch (\Throwable $e) {
            error_log('[PacoteCarrinhoService] Erro getUserSuite: ' . $e->getMessage());
            return null;
        }
    }

    private function getPacotesPendentes(int $suite): array {
        try {
            if (!$this->tableExists('pacotes_recebidos')) return [];
            $stmt = $this->connection->prepare(
                "SELECT * FROM pacotes_recebidos WHERE numero_suite = ? AND status = 'pendente' ORDER BY data_recebimento ASC"
            );
            $stmt->execute([$suite]);
            return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function getOrCreateCart(int $usuarioId): int {
        try {
            $stmt = $this->connection->prepare('SELECT id FROM carrinhos WHERE usuario_id = ? ORDER BY created_at DESC LIMIT 1');
            $stmt->execute([$usuarioId]);
            $cartId = (int) $stmt->fetchColumn();

            if ($cartId <= 0) {
                $stNew = $this->connection->prepare(
                    "INSERT INTO carrinhos (usuario_id, moeda, expira_em) VALUES (?, 'USD', '2099-12-31 23:59:59')"
                );
                $stNew->execute([$usuarioId]);
                $cartId = (int) $this->connection->lastInsertId();
            }

            return $cartId;
        } catch (\Throwable $e) {
            return 0;
        }
    }

    private function pacoteJaNoCarrinho(int $cartId, int $pacoteId): bool {
        try {
            // Verificar por pacote_id OU por produto_id negativo
            $stmt = $this->connection->prepare(
                "SELECT id FROM carrinho_items WHERE carrinho_id = ? AND (pacote_id = ? OR produto_id = ?) LIMIT 1"
            );
            $fakeProdutoId = -1 * $pacoteId;
            $stmt->execute([$cartId, $pacoteId, $fakeProdutoId]);
            return (bool) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function adicionarPacoteAoCarrinho(int $cartId, array $pacote): void {
        try {
            // Detectar nome da coluna de preço
            $cols = [];
            try {
                $st = $this->connection->query('DESCRIBE carrinho_items');
                $cols = $st ? ($st->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            } catch (\Throwable $e) {
                $cols = [];
            }
            $unitCol = in_array('preco_unitario', $cols, true) ? 'preco_unitario' : 'valor_unitario';

            // Verificar se há unique key em (carrinho_id, produto_id, produto_variacao_id)
            // Usar produto_id negativo baseado no pacote_id para evitar conflito
            $fakeProdutoId = -1 * (int) $pacote['id'];

            $stmt = $this->connection->prepare(
                "INSERT INTO carrinho_items 
                (carrinho_id, produto_id, quantidade, {$unitCol}, subtotal, tipo_item, pacote_id, nome_item, peso_kg, foto_url)
                VALUES (?, ?, ?, 0, 0, 'pacote_redirecionamento', ?, ?, ?, ?)"
            );
            $stmt->execute([
                $cartId,
                $fakeProdutoId,
                (int) ($pacote['quantidade'] ?? 1),
                (int) $pacote['id'],
                $pacote['nome'] ?? 'Pacote',
                (float) ($pacote['peso_kg'] ?? 0),
                $pacote['foto_url'] ?? null,
            ]);
            error_log('[PacoteCarrinhoService] Pacote #' . $pacote['id'] . ' adicionado ao carrinho ' . $cartId);
        } catch (\Throwable $e) {
            error_log('[PacoteCarrinhoService] Erro ao adicionar pacote #' . ($pacote['id'] ?? '?') . ': ' . $e->getMessage());
        }
    }
}
