<?php
namespace App\Models;

class ProdutoFoto extends Model {
    protected $table = 'produto_fotos';
    
    public function getFotosProduto($produtoId) {
        $stmt = $this->connection->prepare("
            SELECT * FROM {$this->table} 
            WHERE produto_id = :produto_id 
            ORDER BY principal DESC, ordem ASC, created_at ASC
        ");
        $stmt->bindParam(':produto_id', $produtoId);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
    
    public function getFotoPrincipal($produtoId) {
        $stmt = $this->connection->prepare("
            SELECT * FROM {$this->table} 
            WHERE produto_id = :produto_id AND principal = TRUE 
            LIMIT 1
        ");
        $stmt->bindParam(':produto_id', $produtoId);
        $stmt->execute();
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
    
    public function adicionarFoto($produtoId, $nomeArquivo, $arquivoOriginal = null, $legenda = null, $principal = false) {
        // Se for principal, remover outras fotos principais
        if ($principal) {
            $stmt = $this->connection->prepare("
                UPDATE {$this->table} 
                SET principal = FALSE 
                WHERE produto_id = :produto_id
            ");
            $stmt->bindParam(':produto_id', $produtoId);
            $stmt->execute();
        }
        
        // Obter próxima ordem
        $stmt = $this->connection->prepare("
            SELECT COALESCE(MAX(ordem), 0) + 1 as proxima_ordem 
            FROM {$this->table} 
            WHERE produto_id = :produto_id
        ");
        $stmt->bindParam(':produto_id', $produtoId);
        $stmt->execute();
        $ordem = $stmt->fetch(\PDO::FETCH_ASSOC)['proxima_ordem'];
        
        $data = [
            'produto_id' => $produtoId,
            'nome_arquivo' => $nomeArquivo,
            'arquivo_original' => $arquivoOriginal,
            'legenda' => $legenda,
            'ordem' => $ordem,
            'principal' => $principal
        ];
        
        return $this->create($data);
    }
    
    public function atualizarFoto($id, $data) {
        return $this->update($id, $data);
    }
    
    public function definirComoPrincipal($id) {
        // Obter produto_id da foto
        $foto = $this->find($id);
        if (!$foto) {
            return false;
        }
        
        $this->connection->beginTransaction();
        
        try {
            // Remover todas as fotos principais do produto
            $stmt = $this->connection->prepare("
                UPDATE {$this->table} 
                SET principal = FALSE 
                WHERE produto_id = :produto_id
            ");
            $stmt->bindParam(':produto_id', $foto['produto_id']);
            $stmt->execute();
            
            // Definir esta como principal
            $this->update($id, ['principal' => TRUE]);
            
            $this->connection->commit();
            return true;
            
        } catch (\Exception $e) {
            $this->connection->rollback();
            throw $e;
        }
    }
    
    public function reordenarFotos($produtoId, $ordens) {
        $this->connection->beginTransaction();
        
        try {
            foreach ($ordens as $fotoId => $ordem) {
                $stmt = $this->connection->prepare("
                    UPDATE {$this->table} 
                    SET ordem = :ordem 
                    WHERE id = :id AND produto_id = :produto_id
                ");
                $stmt->bindParam(':ordem', $ordem);
                $stmt->bindParam(':id', $fotoId);
                $stmt->bindParam(':produto_id', $produtoId);
                $stmt->execute();
            }
            
            $this->connection->commit();
            return true;
            
        } catch (\Exception $e) {
            $this->connection->rollback();
            throw $e;
        }
    }
    
    public function excluirFoto($id) {
        $foto = $this->find($id);
        if (!$foto) {
            return false;
        }
        
        // Se for principal, definir próxima como principal
        if ($foto['principal']) {
            $stmt = $this->connection->prepare("
                SELECT id FROM {$this->table} 
                WHERE produto_id = :produto_id AND id != :id 
                ORDER BY ordem ASC 
                LIMIT 1
            ");
            $stmt->bindParam(':produto_id', $foto['produto_id']);
            $stmt->bindParam(':id', $id);
            $stmt->execute();
            $proximaFoto = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($proximaFoto) {
                $this->update($proximaFoto['id'], ['principal' => TRUE]);
            }
        }
        
        // Excluir arquivo físico (se existir)
        $caminhoArquivo = __DIR__ . '/../../public/uploads/produtos/' . $foto['nome_arquivo'];
        if (file_exists($caminhoArquivo)) {
            unlink($caminhoArquivo);
        }
        
        return $this->delete($id);
    }
    
    public function marcarComoPrincipal($fotoId) {
        // Primeiro, remover todas as fotos principais deste produto
        $stmt = $this->connection->prepare("
            UPDATE {$this->table} 
            SET principal = FALSE 
            WHERE produto_id = (SELECT produto_id FROM {$this->table} WHERE id = :id)
        ");
        $stmt->bindParam(':id', $fotoId);
        $stmt->execute();
        
        // Depois, marcar esta foto como principal
        return $this->update($fotoId, ['principal' => TRUE]);
    }
    
    public function uploadFoto($arquivo, $produtoId) {
        // Validar arquivo
        $tiposPermitidos = ['image/jpeg', 'image/png', 'image/webp'];
        $tamanhoMaximo = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($arquivo['type'], $tiposPermitidos)) {
            throw new \Exception('Tipo de arquivo não permitido. Use JPEG, PNG ou WebP.');
        }
        
        if ($arquivo['size'] > $tamanhoMaximo) {
            throw new \Exception('Arquivo muito grande. Máximo 5MB.');
        }
        
        // Criar diretório se não existir
        $diretorio = __DIR__ . '/../../public/uploads/produtos/';
        if (!is_dir($diretorio)) {
            mkdir($diretorio, 0755, true);
        }
        
        // Gerar nome único
        $extensao = pathinfo($arquivo['name'], PATHINFO_EXTENSION);
        $nomeArquivo = uniqid('produto_' . $produtoId . '_') . '.' . $extensao;
        
        // Mover arquivo
        $caminhoCompleto = $diretorio . $nomeArquivo;
        if (!move_uploaded_file($arquivo['tmp_name'], $caminhoCompleto)) {
            throw new \Exception('Erro ao fazer upload do arquivo.');
        }
        
        // Opcional: redimensionar imagem
        $this->redimensionarImagem($caminhoCompleto, $arquivo['type']);
        
        return $nomeArquivo;
    }
    
    private function redimensionarImagem($caminhoArquivo, $tipo) {
        // Obter dimensões originais
        list($larguraOriginal, $alturaOriginal) = getimagesize($caminhoArquivo);
        
        // Definir dimensões máximas
        $larguraMax = 1200;
        $alturaMax = 1200;
        
        // Calcular novas dimensões
        $ratio = min($larguraMax / $larguraOriginal, $alturaMax / $alturaOriginal);
        $novaLargura = intval($larguraOriginal * $ratio);
        $novaAltura = intval($alturaOriginal * $ratio);
        
        // Se não precisar redimensionar
        if ($novaLargura >= $larguraOriginal && $novaAltura >= $alturaOriginal) {
            return;
        }
        
        // Criar nova imagem
        $novaImagem = imagecreatetruecolor($novaLargura, $novaAltura);
        
        // Carregar imagem original
        switch ($tipo) {
            case 'image/jpeg':
                $imagemOriginal = imagecreatefromjpeg($caminhoArquivo);
                break;
            case 'image/png':
                $imagemOriginal = imagecreatefrompng($caminhoArquivo);
                imagealphablending($novaImagem, false);
                imagesavealpha($novaImagem, true);
                break;
            case 'image/webp':
                $imagemOriginal = imagecreatefromwebp($caminhoArquivo);
                break;
            default:
                return;
        }
        
        // Redimensionar
        imagecopyresampled($novaImagem, $imagemOriginal, 0, 0, 0, 0, $novaLargura, $novaAltura, $larguraOriginal, $alturaOriginal);
        
        // Salvar nova imagem
        switch ($tipo) {
            case 'image/jpeg':
                imagejpeg($novaImagem, $caminhoArquivo, 85);
                break;
            case 'image/png':
                imagepng($novaImagem, $caminhoArquivo, 8);
                break;
            case 'image/webp':
                imagewebp($novaImagem, $caminhoArquivo, 85);
                break;
        }
        
        // Liberar memória
        imagedestroy($imagemOriginal);
        imagedestroy($novaImagem);
    }
}
