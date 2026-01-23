<?php
namespace App\Models;

class Endereco extends Model {
    protected $table = 'enderecos';
    
    public function __construct() {
        parent::__construct();
    }
    
    public function create($data) {
        $sql = "INSERT INTO {$this->table} 
                (usuario_id, tipo, cep, logradouro, numero, complemento, bairro, cidade, estado, created_at) 
                VALUES (:usuario_id, :tipo, :cep, :logradouro, :numero, :complemento, :bairro, :cidade, :estado, NOW())";
        
        $stmt = $this->connection->prepare($sql);
        
        $stmt->bindParam(':usuario_id', $data['usuario_id']);
        $stmt->bindParam(':tipo', $data['tipo']);
        $stmt->bindParam(':cep', $data['cep']);
        $stmt->bindParam(':logradouro', $data['logradouro']);
        $stmt->bindParam(':numero', $data['numero']);
        $stmt->bindParam(':complemento', $data['complemento']);
        $stmt->bindParam(':bairro', $data['bairro']);
        $stmt->bindParam(':cidade', $data['cidade']);
        $stmt->bindParam(':estado', $data['estado']);
        
        return $stmt->execute();
    }
    
    public function findByUsuario($usuarioId) {
        $sql = "SELECT * FROM {$this->table} WHERE usuario_id = :usuario_id ORDER BY created_at DESC";
        $stmt = $this->connection->prepare($sql);
        $stmt->bindParam(':usuario_id', $usuarioId);
        $stmt->execute();
        
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function find($id) {
        $sql = "SELECT * FROM {$this->table} WHERE id = :id";
        $stmt = $this->connection->prepare($sql);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
    
    public function update($id, $data) {
        $sql = "UPDATE {$this->table} SET 
                tipo = :tipo, 
                cep = :cep, 
                logradouro = :logradouro, 
                numero = :numero, 
                complemento = :complemento, 
                bairro = :bairro, 
                cidade = :cidade, 
                estado = :estado, 
                updated_at = NOW() 
                WHERE id = :id";
        
        $stmt = $this->connection->prepare($sql);
        
        $stmt->bindParam(':tipo', $data['tipo']);
        $stmt->bindParam(':cep', $data['cep']);
        $stmt->bindParam(':logradouro', $data['logradouro']);
        $stmt->bindParam(':numero', $data['numero']);
        $stmt->bindParam(':complemento', $data['complemento']);
        $stmt->bindParam(':bairro', $data['bairro']);
        $stmt->bindParam(':cidade', $data['cidade']);
        $stmt->bindParam(':estado', $data['estado']);
        $stmt->bindParam(':id', $id);
        
        return $stmt->execute();
    }
    
    public function delete($id) {
        $sql = "DELETE FROM {$this->table} WHERE id = :id";
        $stmt = $this->connection->prepare($sql);
        $stmt->bindParam(':id', $id);
        
        return $stmt->execute();
    }
}
