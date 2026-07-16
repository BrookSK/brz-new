<?php
namespace App\Models;

class PedidoFaturaAdicional extends Model {
    protected $table = 'pedido_faturas_adicionais';

    /**
     * Buscar faturas por pedido
     */
    public function getByPedido(int $pedidoId): array {
        $stmt = $this->connection->prepare(
            "SELECT * FROM {$this->table} WHERE pedido_id = :pid ORDER BY created_at DESC"
        );
        $stmt->execute([':pid' => $pedidoId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Buscar faturas pendentes por usuario (para auto-adição ao carrinho)
     */
    public function getPendentesPorUsuario(int $usuarioId): array {
        $stmt = $this->connection->prepare(
            "SELECT fa.*, p.usuario_id FROM {$this->table} fa
             INNER JOIN pedidos p ON p.id = fa.pedido_id
             WHERE p.usuario_id = :uid AND fa.status = 'pendente'
             ORDER BY fa.created_at ASC"
        );
        $stmt->execute([':uid' => $usuarioId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Listar todas com paginação e filtros
     */
    public function listar(array $filtros = [], int $pagina = 1, int $porPagina = 20): array {
        $where = [];
        $params = [];

        if (!empty($filtros['pedido_id'])) {
            $where[] = 'fa.pedido_id = :pedido_id';
            $params[':pedido_id'] = (int) $filtros['pedido_id'];
        }
        if (!empty($filtros['status'])) {
            $where[] = 'fa.status = :status';
            $params[':status'] = $filtros['status'];
        }

        $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
        $offset = ($pagina - 1) * $porPagina;

        $stmtTotal = $this->connection->prepare("SELECT COUNT(*) FROM {$this->table} fa {$whereClause}");
        $stmtTotal->execute($params);
        $total = (int) $stmtTotal->fetchColumn();

        $sql = "SELECT fa.*, p.usuario_id, u.nome AS usuario_nome, u.email AS usuario_email
                FROM {$this->table} fa
                INNER JOIN pedidos p ON p.id = fa.pedido_id
                LEFT JOIN usuarios u ON u.id = p.usuario_id
                {$whereClause}
                ORDER BY fa.created_at DESC
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
     * Marcar como pago
     */
    public function marcarPago(int $id): bool {
        return $this->update($id, [
            'status' => 'pago',
            'pago_em' => date('Y-m-d H:i:s'),
        ]);
    }
}
