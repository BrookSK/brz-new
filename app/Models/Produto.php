<?php
namespace App\Models;

class Produto extends Model {
    protected $table = 'produtos';

    public function search($term) {
        $stmt = $this->connection->prepare("SELECT * FROM {$this->table} WHERE nome LIKE :term OR descricao LIKE :term OR categoria LIKE :term");
        $term = "%{$term}%";
        $stmt->bindParam(':term', $term);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getByCategoria($categoria) {
        $stmt = $this->connection->prepare("SELECT * FROM {$this->table} WHERE categoria = :categoria ORDER BY nome");
        $stmt->bindParam(':categoria', $categoria);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getCategorias() {
        $stmt = $this->connection->prepare("SELECT DISTINCT categoria FROM {$this->table} ORDER BY categoria");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_COLUMN);
    }

    public function updateEstoque($id, $quantidade) {
        $stmt = $this->connection->prepare("UPDATE {$this->table} SET estoque = estoque - :quantidade WHERE id = :id AND estoque >= :quantidade");
        $stmt->bindParam(':quantidade', $quantidade);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
}
