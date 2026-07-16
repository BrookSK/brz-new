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

        // Cache: só verificar a cada 2 minutos
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
        $cacheKey = 'pacotes_auto_check_' . $usuarioId;
        $lastCheck = $_SESSION[$cacheKey] ?? 0;
        if ((time() - $lastCheck) < 120) {
            return; // Verificou há menos de 2 min
        }
        $_SESSION[$cacheKey] = time();

        // Buscar suite do usuario
        $suite = $this->getUserSuite($usuarioId);
        if (!$suite) return;

        // Buscar pacotes pendentes
        $pacotes = $this->getPacotesPendentes($suite);
        if (empty($pacotes)) return;

        // Buscar/criar carrinho
        $cartId = $this->getOrCreateCart($usuarioId);
        if ($cartId <= 0) return;

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

    private function getUserSuite(int $usuarioId): ?int {
        try {
            $stmt = $this->connection->prepare('SELECT suite FROM usuarios WHERE id = ? LIMIT 1');
            $stmt->execute([$usuarioId]);
            $suite = $stmt->fetchColumn();
            return ($suite && (int) $suite > 0) ? (int) $suite : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function getPacotesPendentes(int $suite): array {
        try {
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
            $stmt = $this->connection->prepare(
                "SELECT id FROM carrinho_items WHERE carrinho_id = ? AND pacote_id = ? AND tipo_item = 'pacote_redirecionamento' LIMIT 1"
            );
            $stmt->execute([$cartId, $pacoteId]);
            return (bool) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function adicionarPacoteAoCarrinho(int $cartId, array $pacote): void {
        try {
            $stmt = $this->connection->prepare(
                "INSERT INTO carrinho_items 
                (carrinho_id, produto_id, quantidade, valor_unitario, subtotal, tipo_item, pacote_id, nome_item, peso_kg, foto_url)
                VALUES (?, 0, ?, 0, 0, 'pacote_redirecionamento', ?, ?, ?, ?)"
            );
            $stmt->execute([
                $cartId,
                (int) ($pacote['quantidade'] ?? 1),
                (int) $pacote['id'],
                $pacote['nome'] ?? 'Pacote',
                (float) ($pacote['peso_kg'] ?? 0),
                $pacote['foto_url'] ?? null,
            ]);
        } catch (\Throwable $e) {
            error_log('[PacoteCarrinhoService] Erro ao adicionar pacote #' . ($pacote['id'] ?? '?') . ': ' . $e->getMessage());
        }
    }
}
