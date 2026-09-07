<?php
namespace App\Models;

/**
 * Model de cupons de desconto.
 *
 * O desconto incide apenas sobre o VALOR DO PRODUTO (subtotal), nunca sobre
 * taxa de serviço, impostos ou frete — que são cobrados em conta separada.
 */
class Cupom extends Model {
    protected $table = 'cupons';

    public function __construct() {
        parent::__construct();
        $this->ensureSchema();
    }

    /**
     * Garante que as tabelas de cupom existam (idempotente).
     */
    public function ensureSchema(): void {
        try {
            $this->connection->exec(
                "CREATE TABLE IF NOT EXISTS cupons (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    codigo VARCHAR(50) NOT NULL,
                    descricao VARCHAR(255) NULL,
                    tipo ENUM('percentual','fixo') NOT NULL DEFAULT 'percentual',
                    valor DECIMAL(10,2) NOT NULL DEFAULT 0,
                    data_inicio DATE NULL,
                    data_expiracao DATE NULL,
                    limite_uso_total INT NULL,
                    limite_uso_por_usuario INT NULL,
                    usos_realizados INT NOT NULL DEFAULT 0,
                    valor_minimo_pedido DECIMAL(10,2) NULL,
                    ativo TINYINT(1) NOT NULL DEFAULT 1,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_cupons_codigo (codigo)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
            $this->connection->exec(
                "CREATE TABLE IF NOT EXISTS cupom_usos (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    cupom_id INT NOT NULL,
                    usuario_id INT NULL,
                    pedido_id INT NULL,
                    valor_desconto DECIMAL(10,2) NOT NULL DEFAULT 0,
                    moeda VARCHAR(3) NOT NULL DEFAULT 'USD',
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    KEY idx_cupom_usos_cupom (cupom_id),
                    KEY idx_cupom_usos_usuario (usuario_id),
                    KEY idx_cupom_usos_pedido (pedido_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
            );
        } catch (\Throwable $e) {
            error_log('[Cupom] ensureSchema: ' . $e->getMessage());
        }
    }

    /**
     * Normaliza um código de cupom (uppercase, sem espaços nas pontas).
     */
    public static function normalizarCodigo(string $codigo): string {
        return strtoupper(trim($codigo));
    }

    /**
     * Listagem para o admin com busca opcional.
     */
    public function listar(string $busca = ''): array {
        $sql = "SELECT * FROM {$this->table}";
        $params = [];
        if ($busca !== '') {
            $sql .= " WHERE codigo LIKE :busca OR descricao LIKE :busca2";
            $params[':busca'] = '%' . $busca . '%';
            $params[':busca2'] = '%' . $busca . '%';
        }
        $sql .= " ORDER BY id DESC";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Busca um cupom pelo código.
     */
    public function findByCodigo(string $codigo): ?array {
        $codigo = self::normalizarCodigo($codigo);
        if ($codigo === '') {
            return null;
        }
        $stmt = $this->connection->prepare("SELECT * FROM {$this->table} WHERE codigo = :codigo LIMIT 1");
        $stmt->execute([':codigo' => $codigo]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Quantidade de usos de um cupom por um usuário específico.
     */
    public function usosPorUsuario(int $cupomId, int $usuarioId): int {
        if ($cupomId <= 0 || $usuarioId <= 0) {
            return 0;
        }
        try {
            $stmt = $this->connection->prepare(
                'SELECT COUNT(*) FROM cupom_usos WHERE cupom_id = ? AND usuario_id = ?'
            );
            $stmt->execute([$cupomId, $usuarioId]);
            return (int) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            return 0;
        }
    }

    /**
     * Valida um cupom para um determinado subtotal de produtos e usuário.
     *
     * @param float  $subtotalProdutos Valor dos produtos (mesma moeda do valor_minimo_pedido: USD)
     * @return array{valido:bool, motivo?:string, cupom?:array, desconto?:float}
     */
    public function validar(string $codigo, float $subtotalProdutos, int $usuarioId = 0): array {
        $cupom = $this->findByCodigo($codigo);
        if (!$cupom) {
            return ['valido' => false, 'motivo' => 'Cupom não encontrado.'];
        }
        if ((int) $cupom['ativo'] !== 1) {
            return ['valido' => false, 'motivo' => 'Cupom inativo.'];
        }

        $hoje = date('Y-m-d');
        if (!empty($cupom['data_inicio']) && $hoje < $cupom['data_inicio']) {
            return ['valido' => false, 'motivo' => 'Este cupom ainda não está disponível.'];
        }
        if (!empty($cupom['data_expiracao']) && $hoje > $cupom['data_expiracao']) {
            return ['valido' => false, 'motivo' => 'Este cupom está expirado.'];
        }

        // Limite total de usos
        if ($cupom['limite_uso_total'] !== null && $cupom['limite_uso_total'] !== ''
            && (int) $cupom['usos_realizados'] >= (int) $cupom['limite_uso_total']) {
            return ['valido' => false, 'motivo' => 'Este cupom atingiu o limite de usos.'];
        }

        // Limite por usuário
        if ($usuarioId > 0 && $cupom['limite_uso_por_usuario'] !== null && $cupom['limite_uso_por_usuario'] !== '') {
            $usados = $this->usosPorUsuario((int) $cupom['id'], $usuarioId);
            if ($usados >= (int) $cupom['limite_uso_por_usuario']) {
                return ['valido' => false, 'motivo' => 'Você já utilizou este cupom o número máximo de vezes.'];
            }
        }

        // Valor mínimo de produtos
        if ($cupom['valor_minimo_pedido'] !== null && $cupom['valor_minimo_pedido'] !== ''
            && $subtotalProdutos < (float) $cupom['valor_minimo_pedido']) {
            return [
                'valido' => false,
                'motivo' => 'Valor mínimo de produtos não atingido para este cupom.',
            ];
        }

        $desconto = $this->calcularDesconto($cupom, $subtotalProdutos);
        if ($desconto <= 0) {
            return ['valido' => false, 'motivo' => 'Cupom sem desconto aplicável.'];
        }

        return ['valido' => true, 'cupom' => $cupom, 'desconto' => $desconto];
    }

    /**
     * Calcula o desconto em cima do subtotal de produtos, com teto no próprio subtotal.
     */
    public function calcularDesconto(array $cupom, float $subtotalProdutos): float {
        if ($subtotalProdutos <= 0) {
            return 0.0;
        }
        $tipo = (string) ($cupom['tipo'] ?? 'percentual');
        $valor = (float) ($cupom['valor'] ?? 0);
        if ($valor <= 0) {
            return 0.0;
        }

        if ($tipo === 'percentual') {
            $desconto = $subtotalProdutos * ($valor / 100.0);
        } else {
            $desconto = $valor; // fixo
        }

        // Nunca descontar mais que o próprio valor dos produtos
        $desconto = min($desconto, $subtotalProdutos);
        return round(max(0.0, $desconto), 2);
    }

    /**
     * Registra o uso de um cupom (incrementa contador e grava histórico).
     */
    public function registrarUso(int $cupomId, int $usuarioId, int $pedidoId, float $valorDesconto, string $moeda = 'USD'): void {
        if ($cupomId <= 0) {
            return;
        }
        try {
            $stmt = $this->connection->prepare(
                'INSERT INTO cupom_usos (cupom_id, usuario_id, pedido_id, valor_desconto, moeda) VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $cupomId,
                $usuarioId > 0 ? $usuarioId : null,
                $pedidoId > 0 ? $pedidoId : null,
                round($valorDesconto, 2),
                strtoupper($moeda),
            ]);
            $this->connection->prepare('UPDATE cupons SET usos_realizados = usos_realizados + 1 WHERE id = ?')
                ->execute([$cupomId]);
        } catch (\Throwable $e) {
            error_log('[Cupom] registrarUso: ' . $e->getMessage());
        }
    }
}
