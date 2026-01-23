<?php
namespace App\Models;

class Categoria extends Model {
    protected $table = 'categorias';
    
    public function __construct() {
        parent::__construct();
    }
    
    /**
     * Busca todas as categorias
     */
    public function getAll() {
        $stmt = $this->getConnection()->prepare("SELECT * FROM {$this->table} ORDER BY nome ASC");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Busca categoria por ID
     */
    public function find($id) {
        $stmt = $this->getConnection()->prepare("SELECT * FROM {$this->table} WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Busca categoria por nome
     */
    public function findByNome($nome) {
        $stmt = $this->getConnection()->prepare("SELECT * FROM {$this->table} WHERE nome = :nome");
        $stmt->bindParam(':nome', $nome);
        $stmt->execute();
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Cria nova categoria
     */
    public function create($data) {
        $stmt = $this->getConnection()->prepare("
            INSERT INTO {$this->table} (nome, descricao, status, created_at, updated_at)
            VALUES (:nome, :descricao, :status, NOW(), NOW())
        ");
        
        $stmt->bindParam(':nome', $data['nome']);
        $stmt->bindParam(':descricao', $data['descricao']);
        $stmt->bindParam(':status', $data['status']);
        
        return $stmt->execute();
    }
    
    /**
     * Atualiza categoria
     */
    public function update($id, $data) {
        $stmt = $this->getConnection()->prepare("
            UPDATE {$this->table} 
            SET nome = :nome, 
                descricao = :descricao, 
                status = :status, 
                updated_at = NOW()
            WHERE id = :id
        ");
        
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':nome', $data['nome']);
        $stmt->bindParam(':descricao', $data['descricao']);
        $stmt->bindParam(':status', $data['status']);
        
        return $stmt->execute();
    }
    
    /**
     * Exclui categoria
     */
    public function delete($id) {
        $stmt = $this->getConnection()->prepare("DELETE FROM {$this->table} WHERE id = :id");
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
    
    /**
     * Busca categorias ativas
     */
    public function getAtivas() {
        $stmt = $this->getConnection()->prepare("SELECT * FROM {$this->table} WHERE status = 'ativo' ORDER BY nome ASC");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    /**
     * Conta produtos por categoria
     */
    public function contarProdutos($categoriaId) {
        $stmt = $this->getConnection()->prepare("
            SELECT COUNT(*) as total 
            FROM produtos 
            WHERE categoria_id = :categoria_id AND status = 'ativo'
        ");
        $stmt->bindParam(':categoria_id', $categoriaId);
        $stmt->execute();
        return $stmt->fetch(\PDO::FETCH_ASSOC)['total'];
    }
    
    /**
     * Verifica se categoria pode ser excluída (sem produtos associados)
     */
    public function podeExcluir($id) {
        $total = $this->contarProdutos($id);
        return $total == 0;
    }
}
