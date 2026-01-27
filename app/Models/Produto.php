<?php
namespace App\Models;

use App\Core\Url;

class Produto extends Model {
    protected $table = 'produtos';

    private function debugLog(string $message): void {
        $enabled = false;
        if (isset($_ENV['APP_DEBUG'])) {
            $enabled = ($_ENV['APP_DEBUG'] === '1' || strtolower((string) $_ENV['APP_DEBUG']) === 'true');
        } elseif (isset($_SERVER['APP_DEBUG'])) {
            $enabled = ($_SERVER['APP_DEBUG'] === '1' || strtolower((string) $_SERVER['APP_DEBUG']) === 'true');
        }

        if ($enabled) {
            error_log($message);
        }
    }

    public function __construct() {
        parent::__construct();
    }
    
    public function find($id) {
        $this->debugLog('[PRODUTO-MODEL] Buscando produto ID: ' . $id);
        
        $stmt = $this->getConnection()->prepare("SELECT * FROM {$this->table} WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        $produto = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        $this->debugLog('[PRODUTO-MODEL] Produto bruto do banco: ' . print_r($produto, true));
        
        if ($produto) {
            // Mapear campos do banco para o frontend
            $produtoMapeado = [
                'id' => $produto['id'],
                'nome' => $produto['name'] ?? '',
                'slug' => $produto['slug'] ?? '',
                'sku' => $produto['sku'] ?? '',
                'descricao' => $produto['description'] ?? '',
                'descricao_curta' => $produto['short_description'] ?? '',
                'foto_principal' => $produto['foto_principal'] ?? null,
                'categoria_id' => $produto['category_id'] ?? 0,
                'valor' => floatval($produto['price'] ?? 0),
                'preco' => floatval($produto['price'] ?? 0), // Adicionar campo 'preco' para compatibilidade
                'preco_custo' => floatval($produto['cost_price'] ?? 0),
                'preco_promocao' => floatval($produto['sale_price'] ?? 0),
                'estoque' => intval($produto['stock'] ?? 0),
                'estoque_minimo' => intval($produto['min_stock'] ?? 0),
                'estoque_maximo' => intval($produto['max_stock'] ?? 999999),
                'comprimento' => floatval($produto['length'] ?? 0),
                'largura' => floatval($produto['width'] ?? 0),
                'altura' => floatval($produto['height'] ?? 0),
                'peso' => floatval($produto['weight'] ?? 0),
                'tipo' => $produto['type'] ?? 'physical',
                'status' => $produto['status'] ?? 'draft',
                'tags' => $produto['tags'] ? json_decode($produto['tags'], true) : [],
                'imagens' => $produto['images'] ? json_decode($produto['images'], true) : [],
                'variacoes' => $produto['variations'] ? json_decode($produto['variations'], true) : [],
                'atributos' => $produto['attributes'] ? json_decode($produto['attributes'], true) : [],
                'moeda' => $produto['currency'] ?? 'USD', // Adicionar campo moeda
                'ativo' => $produto['active'] ?? true,
                'destaque' => $produto['featured'] ?? false,
                'digital' => $produto['digital'] ?? false,
                'arquivo_digital' => $produto['digital_file'] ?? null,
                'downloads_digitais' => intval($produto['digital_downloads'] ?? 0),
                'visualizacoes' => intval($produto['views'] ?? 0),
                'created_at' => $produto['created_at'] ?? null,
                'updated_at' => $produto['updated_at'] ?? null,
                'published_at' => $produto['published_at'] ?? null
            ];
            
            $this->debugLog('[PRODUTO-MODEL] Produto mapeado para frontend: ' . print_r($produtoMapeado, true));
            return $produtoMapeado;
        } else {
            $this->debugLog('[PRODUTO-MODEL] Produto ID ' . $id . ' nao encontrado no banco');
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
            SELECT p.*, c.name as categoria 
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
            SELECT p.*, c.name as categoria 
            FROM {$this->table} p 
            LEFT JOIN categorias c ON p.category_id = c.id 
            WHERE p.status = 'published' AND p.active = 1 
            ORDER BY p.created_at DESC 
            LIMIT :limit
        ");
        $stmt->bindParam(':limit', $limit, \PDO::PARAM_INT);
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
            SELECT p.*, c.name as categoria 
            FROM {$this->table} p 
            LEFT JOIN categorias c ON p.category_id = c.id 
            WHERE (p.name LIKE :term OR p.description LIKE :term OR c.name LIKE :term)
            AND p.status = 'published' AND p.active = 1
            ORDER BY p.name ASC 
            LIMIT :limit
        ");
        $term = "%{$term}%";
        $limit = (int) $limit;
        $stmt->bindParam(':term', $term);
        $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
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
        
        // Gerar slug automaticamente se não fornecido
        $slug = $data['slug'] ?? '';
        if (empty($slug)) {
            $slug = $this->generateSlug($data['name'] ?? '');
        }
        
        // Adicionar timestamp para garantir unicidade temporária
        $slug = $slug . '-' . time();
        
        // Gerar SKU automaticamente se não fornecido
        $sku = $data['sku'] ?? '';
        if (empty($sku)) {
            $sku = $this->generateSKU();
        }
        
        // Garantir SKU único
        $sku = $this->ensureUniqueSKU($sku);
        
        // Mapear campos do formulário para o banco
        $dadosBanco = [
            'name' => $data['name'] ?? '',
            'slug' => $slug,
            'sku' => $sku,
            'description' => $data['description'] ?? '',
            'short_description' => $data['short_description'] ?? '',
            'category_id' => $data['category_id'] ?? 0,
            'price' => floatval($data['price'] ?? 0),
            'cost_price' => floatval($data['cost_price'] ?? 0),
            'sale_price' => floatval($data['sale_price'] ?? 0),
            'stock' => intval($data['stock'] ?? 0),
            'min_stock' => intval($data['min_stock'] ?? 0),
            'max_stock' => intval($data['max_stock'] ?? 999999),
            'length' => floatval($data['length'] ?? 0),
            'width' => floatval($data['width'] ?? 0),
            'height' => floatval($data['height'] ?? 0),
            'weight' => floatval($data['weight'] ?? 0),
            'type' => $data['type'] ?? 'physical',
            'status' => $data['status'] ?? 'draft',
            'tags' => isset($data['tags']) ? json_encode($data['tags']) : null,
            'images' => isset($data['images']) ? json_encode($data['images']) : null,
            'variations' => isset($data['variations']) ? json_encode($data['variations']) : null,
            'attributes' => isset($data['attributes']) ? json_encode($data['attributes']) : null,
            'active' => ($data['status'] ?? 'draft') === 'published' ? 1 : 0,
            'featured' => $data['featured'] ?? false,
            'digital' => $data['digital'] ?? false,
            'digital_file' => $data['digital_file'] ?? null,
            'digital_downloads' => intval($data['digital_downloads'] ?? 0),
            'views' => intval($data['views'] ?? 0),
            'created_at' => date('Y-m-d H:i:s'),
            'updated_at' => date('Y-m-d H:i:s'),
            'published_at' => ($data['status'] ?? 'draft') === 'published' ? date('Y-m-d H:i:s') : null
        ];
        
        error_log('🔍 [PRODUTO-MODEL-CREATE] Dados mapeados para o banco: ' . print_r($dadosBanco, true));
        
        $stmt = $this->getConnection()->prepare("
            INSERT INTO {$this->table} (
                name, slug, sku, description, short_description, category_id, 
                price, cost_price, sale_price, stock, min_stock, max_stock, 
                length, width, height, weight, type, status, tags, images, 
                variations, attributes, active, featured, digital, digital_file, 
                digital_downloads, views, created_at, updated_at, published_at
            ) VALUES (
                :name, :slug, :sku, :description, :short_description, :category_id, 
                :price, :cost_price, :sale_price, :stock, :min_stock, :max_stock, 
                :length, :width, :height, :weight, :type, :status, :tags, :images, 
                :variations, :attributes, :active, :featured, :digital, :digital_file, 
                :digital_downloads, :views, :created_at, :updated_at, :published_at
            )
        ");
        
        $stmt->bindParam(':name', $dadosBanco['name']);
        $stmt->bindParam(':slug', $dadosBanco['slug']);
        $stmt->bindParam(':sku', $dadosBanco['sku']);
        $stmt->bindParam(':description', $dadosBanco['description']);
        $stmt->bindParam(':short_description', $dadosBanco['short_description']);
        $stmt->bindParam(':category_id', $dadosBanco['category_id']);
        $stmt->bindParam(':price', $dadosBanco['price']);
        $stmt->bindParam(':cost_price', $dadosBanco['cost_price']);
        $stmt->bindParam(':sale_price', $dadosBanco['sale_price']);
        $stmt->bindParam(':stock', $dadosBanco['stock']);
        $stmt->bindParam(':min_stock', $dadosBanco['min_stock']);
        $stmt->bindParam(':max_stock', $dadosBanco['max_stock']);
        $stmt->bindParam(':length', $dadosBanco['length']);
        $stmt->bindParam(':width', $dadosBanco['width']);
        $stmt->bindParam(':height', $dadosBanco['height']);
        $stmt->bindParam(':weight', $dadosBanco['weight']);
        $stmt->bindParam(':type', $dadosBanco['type']);
        $stmt->bindParam(':status', $dadosBanco['status']);
        $stmt->bindParam(':tags', $dadosBanco['tags']);
        $stmt->bindParam(':images', $dadosBanco['images']);
        $stmt->bindParam(':variations', $dadosBanco['variations']);
        $stmt->bindParam(':attributes', $dadosBanco['attributes']);
        $stmt->bindParam(':active', $dadosBanco['active']);
        $stmt->bindParam(':featured', $dadosBanco['featured']);
        $stmt->bindParam(':digital', $dadosBanco['digital']);
        $stmt->bindParam(':digital_file', $dadosBanco['digital_file']);
        $stmt->bindParam(':digital_downloads', $dadosBanco['digital_downloads']);
        $stmt->bindParam(':views', $dadosBanco['views']);
        $stmt->bindParam(':created_at', $dadosBanco['created_at']);
        $stmt->bindParam(':updated_at', $dadosBanco['updated_at']);
        $stmt->bindParam(':published_at', $dadosBanco['published_at']);
        
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
            'short_description' => $data['short_description'] ?? '',
            'category_id' => $data['category_id'] ?? 0,
            'price' => floatval($data['price'] ?? 0),
            'cost_price' => floatval($data['cost_price'] ?? 0),
            'sale_price' => floatval($data['sale_price'] ?? 0),
            'stock' => intval($data['stock'] ?? 0),
            'min_stock' => intval($data['min_stock'] ?? 0),
            'max_stock' => intval($data['max_stock'] ?? 999999),
            'length' => floatval($data['length'] ?? 0),
            'width' => floatval($data['width'] ?? 0),
            'height' => floatval($data['height'] ?? 0),
            'weight' => floatval($data['weight'] ?? 0),
            'type' => $data['type'] ?? 'physical',
            'status' => $data['status'] ?? 'draft',
            'tags' => isset($data['tags']) ? json_encode($data['tags']) : null,
            'images' => isset($data['images']) ? json_encode($data['images']) : null,
            'variations' => isset($data['variations']) ? json_encode($data['variations']) : null,
            'attributes' => isset($data['attributes']) ? json_encode($data['attributes']) : null,
            'active' => ($data['status'] ?? 'draft') === 'published' ? 1 : 0,
            'featured' => $data['featured'] ?? false,
            'digital' => $data['digital'] ?? false,
            'digital_file' => $data['digital_file'] ?? null,
            'digital_downloads' => intval($data['digital_downloads'] ?? 0),
            'views' => intval($data['views'] ?? 0),
            'updated_at' => date('Y-m-d H:i:s')
        ];
        
        error_log(' [PRODUTO-MODEL-UPDATE] Dados mapeados para o banco: ' . print_r($dadosBanco, true));
        
        $stmt = $this->getConnection()->prepare("
            UPDATE {$this->table} 
            SET name = :name, 
                sku = :sku, 
                description = :description, 
                short_description = :short_description, 
                category_id = :category_id, 
                price = :price, 
                cost_price = :cost_price, 
                sale_price = :sale_price, 
                stock = :stock, 
                min_stock = :min_stock, 
                max_stock = :max_stock, 
                length = :length, 
                width = :width, 
                height = :height, 
                weight = :weight, 
                type = :type, 
                status = :status, 
                tags = :tags, 
                images = :images, 
                variations = :variations, 
                attributes = :attributes, 
                active = :active, 
                featured = :featured, 
                digital = :digital, 
                digital_file = :digital_file, 
                digital_downloads = :digital_downloads, 
                views = :views, 
                updated_at = :updated_at
            WHERE id = :id
        ");
        
        $stmt->bindParam(':id', $id);
        $stmt->bindParam(':name', $dadosBanco['name']);
        $stmt->bindParam(':sku', $dadosBanco['sku']);
        $stmt->bindParam(':description', $dadosBanco['description']);
        $stmt->bindParam(':short_description', $dadosBanco['short_description']);
        $stmt->bindParam(':category_id', $dadosBanco['category_id']);
        $stmt->bindParam(':price', $dadosBanco['price']);
        $stmt->bindParam(':cost_price', $dadosBanco['cost_price']);
        $stmt->bindParam(':sale_price', $dadosBanco['sale_price']);
        $stmt->bindParam(':stock', $dadosBanco['stock']);
        $stmt->bindParam(':min_stock', $dadosBanco['min_stock']);
        $stmt->bindParam(':max_stock', $dadosBanco['max_stock']);
        $stmt->bindParam(':length', $dadosBanco['length']);
        $stmt->bindParam(':width', $dadosBanco['width']);
        $stmt->bindParam(':height', $dadosBanco['height']);
        $stmt->bindParam(':weight', $dadosBanco['weight']);
        $stmt->bindParam(':type', $dadosBanco['type']);
        $stmt->bindParam(':status', $dadosBanco['status']);
        $stmt->bindParam(':tags', $dadosBanco['tags']);
        $stmt->bindParam(':images', $dadosBanco['images']);
        $stmt->bindParam(':variations', $dadosBanco['variations']);
        $stmt->bindParam(':attributes', $dadosBanco['attributes']);
        $stmt->bindParam(':active', $dadosBanco['active']);
        $stmt->bindParam(':featured', $dadosBanco['featured']);
        $stmt->bindParam(':digital', $dadosBanco['digital']);
        $stmt->bindParam(':digital_file', $dadosBanco['digital_file']);
        $stmt->bindParam(':digital_downloads', $dadosBanco['digital_downloads']);
        $stmt->bindParam(':views', $dadosBanco['views']);
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
                $foto['url_completa'] = Url::absolute($foto['nome_arquivo']);
                
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
    
    /**
     * Gerar slug a partir do nome
     */
    private function generateSlug($name) {
        // Converter para minúsculas e remover caracteres especiais
        $slug = strtolower($name);
        
        // Substituir caracteres especiais
        $slug = preg_replace('/[áàâãä]/', 'a', $slug);
        $slug = preg_replace('/[éèêë]/', 'e', $slug);
        $slug = preg_replace('/[íìîï]/', 'i', $slug);
        $slug = preg_replace('/[óòôõö]/', 'o', $slug);
        $slug = preg_replace('/[úùûü]/', 'u', $slug);
        $slug = preg_replace('/[ç]/', 'c', $slug);
        
        // Remover caracteres não alfanuméricos exceto espaços e hífens
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        
        // Converter espaços em hífens
        $slug = preg_replace('/\s+/', '-', $slug);
        
        // Remover múltiplos hífens
        $slug = preg_replace('/-+/', '-', $slug);
        
        // Remover hífens do início e fim
        $slug = trim($slug, '-');
        
        return $slug;
    }
    
    /**
     * Garantir que o slug seja único
     */
    private function ensureUniqueSlug($slug) {
        $originalSlug = $slug;
        $counter = 1;
        
        while ($this->slugExists($slug)) {
            $slug = $originalSlug . '-' . $counter;
            $counter++;
        }
        
        return $slug;
    }
    
    /**
     * Verificar se slug já existe
     */
    private function slugExists($slug) {
        $stmt = $this->getConnection()->prepare("SELECT COUNT(*) as count FROM {$this->table} WHERE slug = :slug");
        $stmt->bindParam(':slug', $slug);
        $stmt->execute();
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result['count'] > 0;
    }
    
    /**
     * Gerar SKU automático
     */
    private function generateSKU() {
        // Formato: PRD + timestamp + random
        $timestamp = time();
        $random = rand(1000, 9999);
        return 'PRD' . $timestamp . $random;
    }
    
    /**
     * Garantir que o SKU seja único
     */
    private function ensureUniqueSKU($sku) {
        $originalSKU = $sku;
        $counter = 1;
        
        while ($this->skuExists($sku)) {
            $sku = $originalSKU . '-' . $counter;
            $counter++;
        }
        
        return $sku;
    }
    
    /**
     * Verificar se SKU já existe
     */
    private function skuExists($sku) {
        $stmt = $this->getConnection()->prepare("SELECT COUNT(*) as count FROM {$this->table} WHERE sku = :sku");
        $stmt->bindParam(':sku', $sku);
        $stmt->execute();
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        return $result['count'] > 0;
    }
}
