<?php
namespace App\Models;

class Cliente extends Model {
    protected $table = 'clientes';

    public function findByEmail($email) {
        $stmt = $this->connection->prepare("SELECT * FROM {$this->table} WHERE email = :email");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function findByDocumento($documento) {
        $stmt = $this->connection->prepare("SELECT * FROM {$this->table} WHERE documento = :documento");
        $stmt->bindParam(':documento', $documento);
        $stmt->execute();
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    public function createIfNotExists($data) {
        $existing = $this->findByEmail($data['email']);
        if ($existing) {
            return $existing['id'];
        }
        
        $this->create($data);
        return $this->connection->lastInsertId();
    }
}
