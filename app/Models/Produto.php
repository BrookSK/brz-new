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
                'nome' => $produto['name'] ?? '',
                'sku' => $produto['sku'] ?? '',
                'descricao_curta' => $produto['descricao_curta'] ?? '',
                'descricao_completa' => $produto['descricao_completa'] ?? '',
                'categoria_id' => $produto['category_id'] ?? 0,
                'valor' => floatval($produto['price'] ?? 0),
                'moeda' => $produto['currency'] ?? 'USD',
                'peso' => floatval($produto['weight'] ?? 0),
                'estoque' => intval($produto['stock'] ?? 0),
                'status' => $produto['active'] == 1 ? 'published' : 'draft',
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
        $stmt = $this->getConnection()->prepare("SELECT * FROM {$this->table} ORDER BY name ASC");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getAllWithCategoria() {
        $stmt = $this->getConnection()->prepare("
            SELECT p.*, c.name as categoria_nome 
            FROM {$this->table} p 
            LEFT JOIN categorias c ON p.category_id = c.id 
            WHERE p.active = 1 
            ORDER BY p.name ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getDestaque($limit = 8) {
        $stmt = $this->getConnection()->prepare("
            SELECT p.*, c.name as categoria_nome 
            FROM {$this->table} p 
            LEFT JOIN categorias c ON p.category_id = c.id 
            WHERE p.status = 'published' AND p.active = 1 
            ORDER BY p.created_at DESC 
            LIMIT :limit
        ");
        $stmt->bindParam(':limit', $limit);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getCategoria($categoriaId) {
        $stmt = $this->getConnection()->prepare("
            SELECT * FROM categorias WHERE id = :id
        ");
        $stmt->bindParam(':id', $categoriaId);
        $stmt->execute();
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
    
    public function search($term, $limit = 20) {
        $stmt = $this->getConnection()->prepare("
            SELECT p.*, c.name as categoria_nome 
            FROM {$this->table} p 
            LEFT JOIN categorias c ON p.category_id = c.id 
            WHERE (p.name LIKE :term OR p.description LIKE :term OR c.name LIKE :term)
            AND p.status = 'published' AND p.active = 1
            ORDER BY p.name ASC 
            LIMIT :limit
        ");
        $term = "%{$term}%";
        $stmt->bindParam(':term', $term);
        $stmt->bindParam(':limit', $limit);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getByCategoriaId($categoriaId) {
        $stmt = $this->getConnection()->prepare("
            SELECT * FROM {$this->table} 
            WHERE category_id = :category_id 
            ORDER BY name ASC
        ");
        $stmt->bindParam(':category_id', $categoriaId);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function create($data, $usuarioId = null) {
        error_log('🔍 [PRODUTO-MODEL-CREATE] Iniciando criação de produto');
        error_log('🔍 [PRODUTO-MODEL-CREATE] Dados recebidos: ' . print_r($data, true));
        
        // Mapear campos do formulário para o banco
        $dadosBanco = [
            'name' => $data['name'] ?? '',
            'sku' => $data['sku'] ?? '',
            'description' => $data['description'] ?? '',
            'category_id' => $data['category_id'] ?? 0,
            'price' => floatval($data['price'] ?? 0),
            'currency' => $data['currency'] ?? 'USD',
            'weight' => floatval($data['weight'] ?? 0),
            'stock' => intval($data['stock'] ?? 0),
            'status' => $data['status'] ?? 'published',
            'active' => $data['status'] === 'published' ? 1 : 0,
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        error_log('🔍 [PRODUTO-MODEL-CREATE] Dados mapeados para o banco: ' . print_r($dadosBanco, true));
        
        $stmt = $this->getConnection()->prepare("
            INSERT INTO {$this->table} (name, sku, description, category_id, price, currency, weight, stock, status, active, created_at, updated_at)
            VALUES (:name, :sku, :description, :category_id, :price, :currency, :weight, :stock, :status, :active, :created_at, :updated_at)
        ");
        
        $stmt->bindParam(':name', $dadosBanco['name']);
        $stmt->bindParam(':sku', $dadosBanco['sku']);
        $stmt->bindParam(':description', $dadosBanco['description']);
        $stmt->bindParam(':category_id', $dadosBanco['category_id']);
        $stmt->bindParam(':price', $dadosBanco['price']);
        $stmt->bindParam(':currency', $dadosBanco['currency']);
        $stmt->bindParam(':weight', $dadosBanco['weight']);
        $stmt->bindParam(':stock', $dadosBanco['stock']);
        $stmt->bindParam(':status', $dadosBanco['status']);
        $stmt->bindParam(':active', $dadosBanco['active']);
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
            'name' => $data['name'] ?? '',
            'sku' => $data['sku'] ?? '',
            'description' => $data['description'] ?? '',
            'category_id' => $data['category_id'] ?? 0,
            'price' => floatval($data['price'] ?? 0),
            'currency' => $data['currency'] ?? 'USD',
            'weight' => floatval($data['weight'] ?? 0),
            'stock' => intval($data['stock'] ?? 0),
            'status' => $data['status'] ?? 'published',
            'active' => ($data['status'] ?? 'published') === 'published' ? 1 : 0,
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        error_log(' [PRODUTO-MODEL-UPDATE] Dados mapeados para o banco: ' . print_r($dadosBanco, true));
        
        $stmt = $this->getConnection()->prepare("
            UPDATE {$this->table} 
            SET name = :name, 
                sku = :sku, 
                description = :description, 
                category_id = :category_id, 
                price = :price, 
                currency = :currency, 
                weight = :weight, 
                stock = :stock, 
                status = :status, 
                active = :active, 
                updated_at = :updated_at
            WHERE id = :id
        ");
        
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':name', $dadosBanco['name']);
        $stmt->bindParam(':sku', $dadosBanco['sku']);
        $stmt->bindParam(':description', $dadosBanco['description']);
        $stmt->bindParam(':category_id', $dadosBanco['category_id']);
        $stmt->bindParam(':price', $dadosBanco['price']);
        $stmt->bindParam(':currency', $dadosBanco['currency']);
        $stmt->bindParam(':weight', $dadosBanco['weight']);
        $stmt->bindParam(':stock', $dadosBanco['stock']);
        $stmt->bindParam(':status', $dadosBanco['status']);
        $stmt->bindParam(':active', $dadosBanco['active']);
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
        error_log('🔍 [PRODUTO-MODEL] Buscando galeria de imagens do produto ID: ' . $produtoId);
        
        $stmt = $this->getConnection()->prepare("
            SELECT * FROM produto_fotos 
            WHERE produto_id = :produto_id 
            ORDER BY principal DESC, ordem ASC, created_at ASC
        ");
        $stmt->bindParam(':produto_id', $produtoId);
        $stmt->execute();
        
        $fotos = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        // Verificar existência dos arquivos físicos
        foreach ($fotos as &$foto) {
            if ($foto['nome_arquivo'] && strpos($foto['nome_arquivo'], '/uploads/') === 0) {
                $caminhoFisico = $_SERVER['DOCUMENT_ROOT'] . $foto['nome_arquivo'];
                $foto['arquivo_existe'] = file_exists($caminhoFisico);
                $foto['url_completa'] = 'https://novobr.brazilianashop.com.br' . $foto['nome_arquivo'];
                
                if (!$foto['arquivo_existe']) {
                    error_log('❌ [PRODUTO-MODEL] Arquivo não encontrado: ' . $caminhoFisico);
                }
            }
        }
        
        error_log('🔍 [PRODUTO-MODEL] Galerias encontradas: ' . count($fotos));
        
        return $fotos;
    }
    
    public function getAtivos() {
        $stmt = $this->getConnection()->prepare("SELECT * FROM {$this->table} WHERE status = 'published' AND active = 1 ORDER BY name ASC");
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getCategorias() {
        $stmt = $this->getConnection()->prepare("SELECT DISTINCT category_id FROM {$this->table} WHERE category_id IS NOT NULL ORDER BY category_id");
        $stmt->execute();
        $categoriaIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        
        $categorias = [];
        foreach ($categoriaIds as $id) {
            $categoria = $this->getCategoria($id);
            if ($categoria) {
                $categorias[] = $categoria;
            }
        }
        
        return $categorias;
    }
    
    public function updateEstoque($id, $quantidade) {
        $stmt = $this->connection->prepare("UPDATE {$this->table} SET stock = stock - :quantidade WHERE id = :id AND stock >= :quantidade");
        $stmt->bindParam(':quantidade', $quantidade);
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
}
