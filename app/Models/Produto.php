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
            // Mapear campos do banco para o frontend
            $produtoMapeado = [
                'id' => $produto['id'],
                'nome' => $produto['nome'] ?? '',
                'sku' => $produto['sku'] ?? '',
                'descricao_curta' => $produto['descricao_curta'] ?? '',
                'categoria_id' => $produto['categoria_id'] ?? 0,
                'valor' => floatval($produto['valor'] ?? 0),
                'moeda' => $produto['moeda'] ?? 'USD',
                'peso' => floatval($produto['peso'] ?? 0),
                'estoque' => intval($produto['estoque'] ?? 0),
                'status' => $produto['ativo'] == 1 ? 'ativo' : 'inativo',
                'foto_principal' => $produto['foto_principal'] ?? null,
                'created_at' => $produto['created_at'] ?? null,
                'updated_at' => $produto['updated_at'] ?? null
            ];
            
            error_log('🔍 [PRODUTO-MODEL] Produto mapeado para frontend: ' . print_r($produtoMapeado, true));
            return $produtoMapeado;
        } else {
            error_log('🔍 [PRODUTO-MODEL] Produto ID ' . $id . ' não encontrado no banco');
        }
        
        return null;
    }
    
    public function getAll() {
        $stmt = $this->getConnection()->prepare("SELECT * FROM {$this->table} ORDER BY nome ASC");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getAllWithCategoria() {
        $stmt = $this->getConnection()->prepare("
            SELECT p.*, c.nome as categoria_nome 
            FROM {$this->table} p 
            LEFT JOIN categorias c ON p.categoria_id = c.id 
            WHERE p.ativo = 1 
            ORDER BY p.nome ASC
        ");
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
        
        // Mapear campos do formulário para o banco
        $dadosBanco = [
            'nome' => $data['nome'] ?? '',
            'sku' => $data['sku'] ?? '',
            'descricao_curta' => $data['descricao_curta'] ?? '',
            'categoria_id' => $data['categoria_id'] ?? 0,
            'valor' => floatval($data['valor'] ?? 0),
            'moeda' => $data['moeda'] ?? 'USD',
            'peso' => floatval($data['peso'] ?? 0),
            'estoque' => intval($data['estoque'] ?? 0),
            'status' => $data['status'] ?? 'ativo',
            'ativo' => $data['status'] === 'ativo' ? 1 : 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        error_log('🔍 [PRODUTO-MODEL-CREATE] Dados mapeados para o banco: ' . print_r($dadosBanco, true));
        
        $stmt = $this->getConnection()->prepare("
            INSERT INTO {$this->table} (nome, sku, descricao_curta, categoria_id, valor, moeda, peso, estoque, status, ativo, created_at, updated_at)
            VALUES (:nome, :sku, :descricao_curta, :categoria_id, :valor, :moeda, :peso, :estoque, :status, :ativo, :created_at, :updated_at)
        ");
        
        $stmt->bindParam(':nome', $dadosBanco['nome']);
        $stmt->bindParam(':sku', $dadosBanco['sku']);
        $stmt->bindParam(':descricao_curta', $dadosBanco['descricao_curta']);
        $stmt->bindParam(':categoria_id', $dadosBanco['categoria_id']);
        $stmt->bindParam(':valor', $dadosBanco['valor']);
        $stmt->bindParam(':moeda', $dadosBanco['moeda']);
        $stmt->bindParam(':peso', $dadosBanco['peso']);
        $stmt->bindParam(':estoque', $dadosBanco['estoque']);
        $stmt->bindParam(':status', $dadosBanco['status']);
        $stmt->bindParam(':ativo', $dadosBanco['ativo']);
        $stmt->bindParam(':created_at', $dadosBanco['created_at']);
        $stmt->bindParam(':updated_at', $dadosBanco['updated_at']);
        
        error_log('🔍 [PRODUTO-MODEL-CREATE] Executando INSERT no banco...');
        $result = $stmt->execute();
        error_log('🔍 [PRODUTO-MODEL-CREATE] Resultado do INSERT: ' . ($result ? 'true' : 'false'));
        error_log('🔍 [PRODUTO-MODEL-CREATE] SQL Error: ' . print_r($stmt->errorInfo(), true));
        
        $lastId = $this->getConnection()->lastInsertId();
        error_log('🔍 [PRODUTO-MODEL-CREATE] Last Insert ID: ' . $lastId);
        
        return $lastId;
    }
    
    public function update($id, $data, $usuarioId = null) {
        error_log('🔍 [PRODUTO-MODEL-UPDATE] Iniciando atualização do produto ID: ' . $id);
        error_log('🔍 [PRODUTO-MODEL-UPDATE] Dados recebidos: ' . print_r($data, true));
        
        // Mapear campos do formulário para o banco
        $dadosBanco = [
            'nome' => $data['nome'] ?? '',
            'sku' => $data['sku'] ?? '',
            'descricao_curta' => $data['descricao_curta'] ?? '',
            'categoria_id' => $data['categoria_id'] ?? 0,
            'valor' => floatval($data['valor'] ?? 0),
            'moeda' => $data['moeda'] ?? 'USD',
            'peso' => floatval($data['peso'] ?? 0),
            'estoque' => intval($data['estoque'] ?? 0),
            'status' => $data['status'] ?? 'ativo',
            'ativo' => ($data['status'] ?? 'ativo') === 'ativo' ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        error_log('🔍 [PRODUTO-MODEL-UPDATE] Dados mapeados para o banco: ' . print_r($dadosBanco, true));
        
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
                ativo = :ativo, 
                updated_at = :updated_at
            WHERE id = :id
        ");
        
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':nome', $dadosBanco['nome']);
        $stmt->bindParam(':sku', $dadosBanco['sku']);
        $stmt->bindParam(':descricao_curta', $dadosBanco['descricao_curta']);
        $stmt->bindParam(':categoria_id', $dadosBanco['categoria_id']);
        $stmt->bindParam(':valor', $dadosBanco['valor']);
        $stmt->bindParam(':moeda', $dadosBanco['moeda']);
        $stmt->bindParam(':peso', $dadosBanco['peso']);
        $stmt->bindParam(':estoque', $dadosBanco['estoque']);
        $stmt->bindParam(':status', $dadosBanco['status']);
        $stmt->bindParam(':ativo', $dadosBanco['ativo']);
        $stmt->bindParam(':updated_at', $dadosBanco['updated_at']);
        
        error_log('🔍 [PRODUTO-MODEL-UPDATE] Executando UPDATE no banco...');
        $result = $stmt->execute();
        error_log('🔍 [PRODUTO-MODEL-UPDATE] Resultado do UPDATE: ' . ($result ? 'true' : 'false'));
        error_log('🔍 [PRODUTO-MODEL-UPDATE] SQL Error: ' . print_r($stmt->errorInfo(), true));
        
        return $result;
    }
    
    public function updateFotoPrincipal($id, $fotoPrincipal, $usuarioId = null) {
        error_log('🔍 [PRODUTO-MODEL] Atualizando foto principal do produto ID: ' . $id . ' - Foto: ' . $fotoPrincipal);
        
        // Se já for URL completa, usar diretamente
        if (strpos($fotoPrincipal, '/uploads/') === 0) {
            $urlCompleta = $fotoPrincipal;
        } else {
            // Se for apenas nome do arquivo, criar URL completa
            $urlCompleta = '/uploads/produtos/' . $fotoPrincipal;
        }
        
        $stmt = $this->getConnection()->prepare("
            UPDATE {$this->table} 
            SET foto_principal = :foto_principal, 
                updated_at = NOW()
            WHERE id = :id
        ");
        
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':foto_principal', $urlCompleta);
        
        $result = $stmt->execute();
        error_log('🔍 [PRODUTO-MODEL] URL salva no banco: ' . $urlCompleta);
        error_log('🔍 [PRODUTO-MODEL] Resultado da atualização da foto principal: ' . ($result ? 'true' : 'false'));
        error_log('🔍 [PRODUTO-MODEL] SQL Error: ' . print_r($stmt->errorInfo(), true));
        
        return $result;
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
