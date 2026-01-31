<?php
namespace App\Models;

class ProdutoFoto extends Model {
    protected $table = 'produto_fotos';

    private function normalizeNomeArquivo($nomeArquivo): ?string {
        $nomeArquivo = is_string($nomeArquivo) ? trim($nomeArquivo) : '';
        if ($nomeArquivo === '') {
            return null;
        }

        if (preg_match('#^https?://#i', $nomeArquivo)) {
            return $nomeArquivo;
        }

        if (strpos($nomeArquivo, '//') === 0) {
            return 'https:' . $nomeArquivo;
        }

        if (str_starts_with($nomeArquivo, '/')) {
            return $nomeArquivo;
        }

        return '/uploads/produtos/' . ltrim($nomeArquivo, '/');
    }
    
    public function getFotosProduto($produtoId) {
        $stmt = $this->connection->prepare("
            SELECT * FROM {$this->table} 
            WHERE produto_id = :produto_id 
            ORDER BY principal DESC, ordem ASC, created_at ASC
        ");
        $stmt->bindParam(':produto_id', $produtoId);
        $stmt->execute();
        $fotos = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        foreach ($fotos as &$foto) {
            if (array_key_exists('nome_arquivo', $foto)) {
                $foto['nome_arquivo'] = $this->normalizeNomeArquivo($foto['nome_arquivo']);
            }
        }
        return $fotos;
    }
    
    public function getFotoPrincipal($produtoId) {
        $stmt = $this->connection->prepare("
            SELECT * FROM {$this->table} 
            WHERE produto_id = :produto_id AND principal = TRUE 
            LIMIT 1
        ");
        $stmt->bindParam(':produto_id', $produtoId);
        $stmt->execute();
        $foto = $stmt->fetch(\PDO::FETCH_ASSOC);
        if ($foto && array_key_exists('nome_arquivo', $foto)) {
            $foto['nome_arquivo'] = $this->normalizeNomeArquivo($foto['nome_arquivo']);
        }
        return $foto;
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
        $nomeArquivo = $this->normalizeNomeArquivo($foto['nome_arquivo'] ?? '');
        $caminhoArquivo = $nomeArquivo ? ($_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($nomeArquivo, '/')) : null;
        if ($caminhoArquivo && file_exists($caminhoArquivo)) {
            unlink($caminhoArquivo);
            error_log('🔍 [PRODUTO-FOTO] Arquivo físico excluído: ' . $caminhoArquivo);
        } else {
            error_log('⚠️ [PRODUTO-FOTO] Arquivo físico não encontrado para exclusão: ' . $caminhoArquivo);
        }
        
        return $this->delete($id);
    }
    
    public function marcarComoPrincipal($fotoId) {
        error_log('🔍 [PRODUTO-FOTO] Marcando foto como principal. ID: ' . $fotoId);
        
        // Primeiro, obter o produto_id desta foto
        $stmt = $this->connection->prepare("SELECT produto_id FROM {$this->table} WHERE id = :id");
        $stmt->bindParam(':id', $fotoId);
        $stmt->execute();
        $produto = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        if (!$produto) {
            error_log('❌ [PRODUTO-FOTO] Foto não encontrada: ' . $fotoId);
            return false;
        }
        
        error_log('🔍 [PRODUTO-FOTO] Produto ID: ' . $produto['produto_id']);
        
        // Remover todas as fotos principais deste produto
        $stmt = $this->connection->prepare("
            UPDATE {$this->table} 
            SET principal = 0 
            WHERE produto_id = :produto_id
        ");
        $stmt->bindParam(':produto_id', $produto['produto_id']);
        $stmt->execute();
        
        error_log('🔍 [PRODUTO-FOTO] Fotos principais removidas do produto ' . $produto['produto_id']);
        
        // Marcar esta foto como principal
        $stmt = $this->connection->prepare("
            UPDATE {$this->table} 
            SET principal = 1 
            WHERE id = :id
        ");
        $stmt->bindParam(':id', $fotoId);
        $result = $stmt->execute();
        
        error_log('🔍 [PRODUTO-FOTO] Foto marcada como principal. ID: ' . $fotoId . ' - Resultado: ' . ($result ? 'true' : 'false'));
        
        return $result;
    }
    
    public function uploadFoto($arquivo, $produtoId) {
        error_log('🔍 [PRODUTO-FOTO] Iniciando upload da foto para produto ID: ' . $produtoId);
        
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
        $diretorio = $_SERVER['DOCUMENT_ROOT'] . '/uploads/produtos/';
        if (!is_dir($diretorio)) {
            mkdir($diretorio, 0755, true);
            error_log('🔍 [PRODUTO-FOTO] Diretório criado: ' . $diretorio);
        }
        
        error_log('🔍 [PRODUTO-FOTO] Diretório de uploads: ' . $diretorio);
        error_log('🔍 [PRODUTO-FOTO] DOCUMENT_ROOT: ' . $_SERVER['DOCUMENT_ROOT']);
        
        // Gerar nome único
        $extensao = pathinfo($arquivo['name'], PATHINFO_EXTENSION);
        $prefixo = $produtoId ? 'produto_' . $produtoId . '_' : 'produto_temp_';
        $nomeArquivo = uniqid($prefixo) . '.' . $extensao;
        
        // Mover arquivo
        $caminhoCompleto = $diretorio . $nomeArquivo;
        if (!move_uploaded_file($arquivo['tmp_name'], $caminhoCompleto)) {
            throw new \Exception('Erro ao fazer upload do arquivo.');
        }
        
        // Opcional: redimensionar imagem
        try {
            $this->redimensionarImagem($caminhoCompleto, $arquivo['type']);
            error_log('✅ [PRODUTO-FOTO] Imagem redimensionada com sucesso');
        } catch (\Exception $e) {
            error_log('⚠️ [PRODUTO-FOTO] Erro ao redimensionar imagem: ' . $e->getMessage());
            // Continuar mesmo se falhar o redimensionamento
        }
        
        // Criar URL completa (estilo WordPress)
        $urlCompleta = '/uploads/produtos/' . $nomeArquivo;
        
        error_log('🔍 [PRODUTO-FOTO] Arquivo salvo: ' . $nomeArquivo);
        error_log('🔍 [PRODUTO-FOTO] URL gerada: ' . $urlCompleta);
        error_log('🔍 [PRODUTO-FOTO] Caminho físico: ' . $caminhoCompleto);
        
        // Verificar se arquivo físico foi criado
        if (!file_exists($caminhoCompleto)) {
            error_log('❌ [PRODUTO-FOTO] ERRO: Arquivo físico não foi criado: ' . $caminhoCompleto);
            throw new \Exception('Erro ao salvar arquivo físico no servidor.');
        }
        
        error_log('✅ [PRODUTO-FOTO] Arquivo físico verificado: ' . $caminhoCompleto);
        
        // Salvar no banco de dados com URL completa
        $fotoData = [
            'produto_id' => $produtoId, // Pode ser null para upload temporário
            'nome_arquivo' => $urlCompleta, // Salvar URL completa em vez de apenas nome
            'arquivo_original' => $arquivo['name'],
            'legenda' => pathinfo($arquivo['name'], PATHINFO_FILENAME),
            'principal' => 1, // Marcar como principal por padrão
            'ordem' => 0
        ];
        
        // Se for upload temporário (produto_id null ou vazio), não salvar no banco ainda
        if ($produtoId === null || $produtoId === '') {
            error_log('🔍 [PRODUTO-FOTO] Upload temporário detectado - produto_id: ' . var_export($produtoId, true));
            error_log('🔍 [PRODUTO-FOTO] Upload temporário - não salvando no banco ainda');
            error_log('🔍 [PRODUTO-FOTO] Retornando dados temporários para: ' . $urlCompleta);
            return [
                'id' => null, // ID temporário
                'nome_arquivo' => $urlCompleta, // Retornar URL completa
                'url' => $urlCompleta,
                'temp' => true // Marcar como temporário
            ];
        }
        
        error_log('🔍 [PRODUTO-FOTO] Salvando no banco - produto_id: ' . $produtoId);
        
        $fotoId = $this->create($fotoData);
        
        error_log('🔍 [PRODUTO-FOTO] Foto salva no banco com ID: ' . $fotoId);
        
        return [
            'id' => $fotoId,
            'nome_arquivo' => $urlCompleta, // Retornar URL completa
            'url' => $urlCompleta
        ];
    }

    public function moverImagemTemporaria($urlTemporaria, $produtoId) {
        error_log('🔍 [PRODUTO-FOTO] Movendo imagem temporária para produto ID: ' . $produtoId);
        
        // Extrair nome do arquivo da URL
        $nomeArquivo = basename($urlTemporaria);
        
        // Usar o mesmo caminho do upload
        $diretorio = $_SERVER['DOCUMENT_ROOT'] . '/uploads/produtos/';
        $caminhoTemp = $diretorio . $nomeArquivo;
        
        error_log('🔍 [PRODUTO-FOTO] Caminho temporário: ' . $caminhoTemp);
        
        // Verificar se arquivo temporário existe
        if (!file_exists($caminhoTemp)) {
            error_log('❌ [PRODUTO-FOTO] Arquivo temporário não encontrado: ' . $caminhoTemp);
            return false;
        }
        
        // Gerar novo nome com produto_id
        $extensao = pathinfo($nomeArquivo, PATHINFO_EXTENSION);
        $novoNome = uniqid('produto_' . $produtoId . '_') . '.' . $extensao;
        $novoCaminho = $diretorio . $novoNome;
        
        error_log('🔍 [PRODUTO-FOTO] Novo caminho: ' . $novoCaminho);
        
        // Mover arquivo
        if (!rename($caminhoTemp, $novoCaminho)) {
            error_log('❌ [PRODUTO-FOTO] Erro ao mover arquivo temporário');
            return false;
        }
        
        // Criar nova URL
        $novaUrl = '/uploads/produtos/' . $novoNome;
        
        error_log('✅ [PRODUTO-FOTO] Arquivo movido: ' . $nomeArquivo . ' → ' . $novoNome);
        
        // Salvar no banco
        $fotoData = [
            'produto_id' => $produtoId,
            'nome_arquivo' => $novaUrl,
            'arquivo_original' => $nomeArquivo,
            'legenda' => pathinfo($nomeArquivo, PATHINFO_FILENAME),
            'principal' => 1,
            'ordem' => 0
        ];
        
        $fotoId = $this->create($fotoData);
        
        error_log('✅ [PRODUTO-FOTO] Imagem movida e salva: ' . $novaUrl);
        
        return [
            'id' => $fotoId,
            'nome_arquivo' => $novaUrl,
            'url' => $novaUrl
        ];
    }

    public function redimensionarImagem($caminhoArquivo, $tipo) {
        error_log('🔍 [PRODUTO-FOTO] Iniciando redimensionamento: ' . $caminhoArquivo . ' Tipo: ' . $tipo);

        // Verificar se GD está disponível
        if (!extension_loaded('gd') || !function_exists('gd_info')) {
            error_log(' [PRODUTO-FOTO] Extensão GD não está carregada');
            return;
        }

        // Obter dimensões originais
        $info = getimagesize($caminhoArquivo);
        if (!$info) {
            error_log(' [PRODUTO-FOTO] Não foi possível obter dimensões da imagem');
            return;
        }

        list($larguraOriginal, $alturaOriginal) = $info;
        error_log(' [PRODUTO-FOTO] Dimensões originais: ' . $larguraOriginal . 'x' . $alturaOriginal);

        // Definir dimensões máximas
        $larguraMax = 1200;
        $alturaMax = 1200;

        // Calcular novas dimensões
        $ratio = min($larguraMax / $larguraOriginal, $alturaMax / $alturaOriginal);
        $novaLargura = intval($larguraOriginal * $ratio);
        $novaAltura = intval($alturaOriginal * $ratio);

        error_log(' [PRODUTO-FOTO] Novas dimensões: ' . $novaLargura . 'x' . $novaAltura);

        // Se não precisar redimensionar
        if ($novaLargura >= $larguraOriginal && $novaAltura >= $alturaOriginal) {
            error_log(' [PRODUTO-FOTO] Imagem não precisa ser redimensionada');
            return;
        }

        // Criar nova imagem
        $novaImagem = imagecreatetruecolor($novaLargura, $novaAltura);
        if (!$novaImagem) {
            error_log(' [PRODUTO-FOTO] Falha ao criar nova imagem');
            return;
        }

        // Carregar imagem original
        $imagemOriginal = null;
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
                error_log(' [PRODUTO-FOTO] Tipo de imagem não suportado: ' . $tipo);
                imagedestroy($novaImagem);
                return;
        }

        if (!$imagemOriginal) {
            error_log(' [PRODUTO-FOTO] Falha ao carregar imagem original');
            imagedestroy($novaImagem);
            return;
        }

        // Redimensionar
        $resultado = imagecopyresampled($novaImagem, $imagemOriginal, 0, 0, 0, 0, $novaLargura, $novaAltura, $larguraOriginal, $alturaOriginal);

        if (!$resultado) {
            error_log(' [PRODUTO-FOTO] Falha no redimensionamento');
            imagedestroy($novaImagem);
            imagedestroy($imagemOriginal);
            return;
        }

        // Salvar nova imagem
        $salvou = false;
        switch ($tipo) {
            case 'image/jpeg':
                $salvou = imagejpeg($novaImagem, $caminhoArquivo, 85);
                break;
            case 'image/png':
                $salvou = imagepng($novaImagem, $caminhoArquivo, 8);
                break;
            case 'image/webp':
                $salvou = imagewebp($novaImagem, $caminhoArquivo, 85);
                break;
        }

        // Liberar memória
        imagedestroy($imagemOriginal);
        imagedestroy($novaImagem);

        if (!$salvou) {
            error_log(' [PRODUTO-FOTO] Falha ao salvar imagem redimensionada');
            return;
        }

        error_log(' [PRODUTO-FOTO] Imagem redimensionada com sucesso');
    }
}
