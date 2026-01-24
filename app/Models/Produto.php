<?php
namespace App\Models;

class Produto extends Model {
    protected $table = 'produtos';

    public function __construct() {
        parent::__construct();
    }
    
    public function find($id) {
        error_log('🔍 [PRODUTO-MODEL] Buscando produto ID: ' . $id);
        
        $stmt = $this->getConnection()->prepare("SELECT * FROM {$this->table} WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $produto = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        error_log('🔍 [PRODUTO-MODEL] Produto bruto do banco: ' . print_r($produto, true));
        
        if ($produto) {
            // Garantir que todos os campos tenham valores padrão
            $produto = array_merge([
                'id' => 0,
                'nome' => '',
                'sku' => '',
                'descricao_curta' => '',
                'descricao_completa' => '',
                'categoria_id' => '',
                'valor' => 0.00,
                'moeda' => 'USD',
                'peso' => 0.000,
                'estoque' => 0,
                'status' => 'ativo',
                'foto_principal' => null,
                'created_at' => null,
                'updated_at' => null
            ], $produto);
            
            // Converter para tipos numéricos
            $produto['valor'] = floatval($produto['valor']);
            $produto['peso'] = floatval($produto['peso']);
            $produto['estoque'] = intval($produto['estoque']);
            $produto['categoria_id'] = intval($produto['categoria_id']);
            
            error_log('🔍 [PRODUTO-MODEL] Produto processado: ' . print_r($produto, true));
        } else {
            error_log('🔍 [PRODUTO-MODEL] Produto ID ' . $id . ' não encontrado no banco');
        }
        
        return $produto;
    }
    
    public function getAll() {
        $stmt = $this->getConnection()->prepare("SELECT * FROM {$this->table} ORDER BY nome ASC");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function search($term) {
        $stmt = $this->getConnection()->prepare("SELECT * FROM {$this->table} WHERE nome LIKE :term OR descricao LIKE :term OR categoria LIKE :term");
        $term = "%{$term}%";
        $stmt->bindParam(':term', $term);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getByCategoria($categoria) {
        $stmt = $this->getConnection()->prepare("SELECT * FROM {$this->table} WHERE categoria = :categoria ORDER BY nome");
        $stmt->bindParam(':categoria', $categoria);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function create($data, $usuarioId = null) {
        error_log('🔍 [PRODUTO-MODEL-CREATE] Iniciando criação de produto');
        error_log('🔍 [PRODUTO-MODEL-CREATE] Dados recebidos: ' . print_r($data, true));
        
        $stmt = $this->getConnection()->prepare("
            INSERT INTO {$this->table} (nome, sku, descricao_curta, categoria_id, valor, moeda, peso, estoque, status, created_at, updated_at)
            VALUES (:nome, :sku, :descricao_curta, :categoria_id, :valor, :moeda, :peso, :estoque, :status, NOW(), NOW())
        ");
        
        $stmt->bindParam(':nome', $data['nome']);
        $stmt->bindParam(':sku', $data['sku']);
        $stmt->bindParam(':descricao_curta', $data['descricao_curta']);
        $stmt->bindParam(':categoria_id', $data['categoria_id']);
        $stmt->bindParam(':valor', $data['valor']);
        $stmt->bindParam(':moeda', $data['moeda']);
        $stmt->bindParam(':peso', $data['peso']);
        $stmt->bindParam(':estoque', $data['estoque']);
        $stmt->bindParam(':status', $data['status']);
        
        error_log('🔍 [PRODUTO-MODEL-CREATE] Executando INSERT no banco...');
        $result = $stmt->execute();
        error_log('🔍 [PRODUTO-MODEL-CREATE] Resultado do INSERT: ' . ($result ? 'true' : 'false'));
        error_log('🔍 [PRODUTO-MODEL-CREATE] SQL Error: ' . print_r($stmt->errorInfo(), true));
        
        $lastId = $this->getConnection()->lastInsertId();
        error_log('🔍 [PRODUTO-MODEL-CREATE] Last Insert ID: ' . $lastId);
        
        return $lastId;
    }
    
    public function update($id, $data, $usuarioId = null) {
        $stmt = $this->getConnection()->prepare("
            UPDATE {$this->table} 
            SET nome = :nome, 
                sku = :sku, 
                descricao_curta = :descricao_curta, 
                categoria_id = :categoria_id, 
                valor = :valor, 
                moeda = :moeda, 
                peso = :peso, 
                estoque = :estoque, 
                status = :status, 
                updated_at = NOW()
            WHERE id = :id
        ");
        
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':nome', $data['nome']);
        $stmt->bindParam(':sku', $data['sku']);
        $stmt->bindParam(':descricao_curta', $data['descricao_curta']);
        $stmt->bindParam(':categoria_id', $data['categoria_id']);
        $stmt->bindParam(':valor', $data['valor']);
        $stmt->bindParam(':moeda', $data['moeda']);
        $stmt->bindParam(':peso', $data['peso']);
        $stmt->bindParam(':estoque', $data['estoque']);
        $stmt->bindParam(':status', $data['status']);
        
        return $stmt->execute();
    }
    
    public function delete($id) {
        $stmt = $this->getConnection()->prepare("DELETE FROM {$this->table} WHERE id = :id");
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
    
    public function atualizarStatus($id, $status) {
        $stmt = $this->getConnection()->prepare("UPDATE {$this->table} SET status = :status, updated_at = NOW() WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':status', $status);
        return $stmt->execute();
    }
    
    public function getImagens($produtoId) {
        $stmt = $this->getConnection()->prepare("SELECT * FROM produto_fotos WHERE produto_id = :produto_id ORDER BY ordem ASC");
        $stmt->bindParam(':produto_id', $produtoId);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getAtivos() {
        $stmt = $this->getConnection()->prepare("SELECT * FROM {$this->table} WHERE status = 'ativo' ORDER BY nome ASC");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getCategorias() {
        $stmt = $this->getConnection()->prepare("SELECT DISTINCT categoria FROM {$this->table} ORDER BY categoria");
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
