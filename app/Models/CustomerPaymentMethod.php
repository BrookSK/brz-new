<?php
namespace App\Models;

class CustomerPaymentMethod extends Model {
    protected $table = 'customer_payment_methods';

    /**
     * Retorna métodos de pagamento de um usuário
     */
    public function getByUserId(int $userId): array {
        $stmt = $this->connection->prepare(
            "SELECT id, gateway, brand, last_four, holder_name, expiry_month, expiry_year, is_default, created_at
             FROM {$this->table} 
             WHERE user_id = :user_id
             ORDER BY is_default DESC, created_at DESC"
        );
        $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Retorna o método de pagamento padrão do usuário
     */
    public function getDefault(int $userId): ?array {
        $stmt = $this->connection->prepare(
            "SELECT * FROM {$this->table} 
             WHERE user_id = :user_id AND is_default = 1
             LIMIT 1"
        );
        $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Define um método como padrão (desmarca os outros)
     */
    public function setDefault(int $userId, int $methodId): bool {
        // Desmarcar todos
        $stmt = $this->connection->prepare(
            "UPDATE {$this->table} SET is_default = 0 WHERE user_id = :user_id"
        );
        $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        $stmt->execute();

        // Marcar o selecionado
        $stmt = $this->connection->prepare(
            "UPDATE {$this->table} SET is_default = 1 WHERE id = :id AND user_id = :user_id"
        );
        $stmt->bindValue(':id', $methodId, \PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        return $stmt->execute();
    }

    /**
     * Remove um método de pagamento
     */
    public function deleteByUser(int $userId, int $methodId): bool {
        $stmt = $this->connection->prepare(
            "DELETE FROM {$this->table} WHERE id = :id AND user_id = :user_id"
        );
        $stmt->bindValue(':id', $methodId, \PDO::PARAM_INT);
        $stmt->bindValue(':user_id', $userId, \PDO::PARAM_INT);
        return $stmt->execute();
    }
}
