<?php
namespace App\Models;

class PacoteRecebido extends Model {
    protected $table = 'pacotes_recebidos';

    /**
     * Lista NCM - usa a mesma lista do cadastro de produtos
     */
    public static function getNcmOptions(): array {
        return (new \App\Controllers\AdminProdutosController())->getPublicNcmOptions();
    }

    /**
     * Buscar pacotes pendentes por suite (para auto-adição ao carrinho)
     */
    public function getPendentesPorSuite(int $suite): array {
        $stmt = $this->connection->prepare(
            "SELECT * FROM {$this->table} WHERE numero_suite = :suite AND status = 'pendente' ORDER BY data_recebimento DESC"
        );
        $stmt->execute([':suite' => $suite]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Buscar pacotes por usuario
     */
    public function getByUsuario(int $usuarioId): array {
        $stmt = $this->connection->prepare(
            "SELECT * FROM {$this->table} WHERE usuario_id = :uid ORDER BY created_at DESC"
        );
        $stmt->execute([':uid' => $usuarioId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Buscar pacotes por pedido
     */
    public function getByPedido(int $pedidoId): array {
        $stmt = $this->connection->prepare(
            "SELECT * FROM {$this->table} WHERE pedido_id = :pid ORDER BY id ASC"
        );
        $stmt->execute([':pid' => $pedidoId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Listagem com filtros e paginação
     */
    public function listar(array $filtros = [], int $pagina = 1, int $porPagina = 20): array {
        $where = [];
        $params = [];

        if (!empty($filtros['suite'])) {
            $where[] = 'p.numero_suite = :suite';
            $params[':suite'] = (int) $filtros['suite'];
        }
        if (!empty($filtros['status'])) {
            $where[] = 'p.status = :status';
            $params[':status'] = $filtros['status'];
        }
        if (!empty($filtros['data_inicio'])) {
            $where[] = 'p.data_recebimento >= :data_inicio';
            $params[':data_inicio'] = $filtros['data_inicio'];
        }
        if (!empty($filtros['data_fim'])) {
            $where[] = 'p.data_recebimento <= :data_fim';
            $params[':data_fim'] = $filtros['data_fim'];
        }
        if (!empty($filtros['busca'])) {
            $where[] = '(p.nome LIKE :busca OR p.fornecedor LIKE :busca2 OR CAST(p.numero_suite AS CHAR) LIKE :busca3)';
            $params[':busca'] = '%' . $filtros['busca'] . '%';
            $params[':busca2'] = '%' . $filtros['busca'] . '%';
            $params[':busca3'] = '%' . $filtros['busca'] . '%';
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $offset = ($pagina - 1) * $porPagina;

        // Total
        $stmtTotal = $this->connection->prepare("SELECT COUNT(*) FROM {$this->table} p LEFT JOIN usuarios u ON u.id = p.usuario_id {$whereClause}");
        $stmtTotal->execute($params);
        $total = (int) $stmtTotal->fetchColumn();

        // Registros
        $sql = "SELECT p.*, u.nome AS usuario_nome, u.email AS usuario_email 
                FROM {$this->table} p 
                LEFT JOIN usuarios u ON u.id = p.usuario_id 
                {$whereClause} 
                ORDER BY p.created_at DESC 
                LIMIT {$porPagina} OFFSET {$offset}";
        $stmt = $this->connection->prepare($sql);
        $stmt->execute($params);
        $registros = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        return [
            'registros' => $registros,
            'total' => $total,
            'pagina' => $pagina,
            'por_pagina' => $porPagina,
            'total_paginas' => (int) ceil($total / $porPagina),
        ];
    }

    /**
     * Atualizar status do pacote
     */
    public function atualizarStatus(int $id, string $status, ?int $pedidoId = null): bool {
        $data = ['status' => $status];
        if ($pedidoId !== null) {
            $data['pedido_id'] = $pedidoId;
        }
        return $this->update($id, $data);
    }

    /**
     * Buscar pacotes pendentes com dias expirados (para cron)
     */
    public function getPendentesComDias(): array {
        $stmt = $this->connection->prepare(
            "SELECT *, DATEDIFF(CURDATE(), data_recebimento) AS dias_desde_recebimento 
             FROM {$this->table} 
             WHERE status = 'pendente' 
             ORDER BY data_recebimento ASC"
        );
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Buscar usuario por suite
     */
    public function buscarUsuarioPorSuite(int $suite): ?array {
        $stmt = $this->connection->prepare(
            "SELECT id, nome, email, suite, telefone FROM usuarios WHERE suite = :suite LIMIT 1"
        );
        $stmt->execute([':suite' => $suite]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $row ?: null;
    }
}
