<?php
namespace App\Models;

class Imagem extends Model {
    protected $table = 'imagens';
    
    public function upload($arquivo, $tipo = 'produto') {
        error_log('🔍 [IMAGEM] Iniciando upload de imagem tipo: ' . $tipo);
        
        // Validar arquivo
        $tiposPermitidos = ['image/jpeg', 'image/png', 'image/webp'];
        $tamanhoMaximo = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($arquivo['type'], $tiposPermitidos)) {
            throw new \Exception('Tipo de arquivo não permitido. Use JPEG, PNG ou WebP.');
        }
        
        if ($arquivo['size'] > $tamanhoMaximo) {
            throw new \Exception('Arquivo muito grande. Máximo 5MB.');
        }
        
        // Criar diretório base se não existir
        $diretorioBase = __DIR__ . '/../../public/uploads/';
        if (!is_dir($diretorioBase)) {
            mkdir($diretorioBase, 0755, true);
        }
        
        // Criar diretório do tipo se não existir
        // Para produtos, usar sempre /uploads/produtos/ para compatibilidade
        if ($tipo === 'produto') {
            $diretorioTipo = $diretorioBase . 'produtos/';
        } else {
            $diretorioTipo = $diretorioBase . $tipo . '/';
        }
        
        if (!is_dir($diretorioTipo)) {
            mkdir($diretorioTipo, 0755, true);
        }
        
        // Gerar nome único
        $extensao = pathinfo($arquivo['name'], PATHINFO_EXTENSION);
        $nomeArquivo = uniqid($tipo . '_') . '.' . $extensao;
        
        // Mover arquivo
        $caminhoCompleto = $diretorioTipo . $nomeArquivo;
        if (!move_uploaded_file($arquivo['tmp_name'], $caminhoCompleto)) {
            throw new \Exception('Erro ao fazer upload do arquivo.');
        }
        
        // Redimensionar para tamanho máximo fixo (800x800 para exibição consistente)
        $this->redimensionarParaTamanhoMaximo($caminhoCompleto, $arquivo['type']);
        
        // Criar URL imediatamente para exibição
        $url = '/uploads/produtos/' . $nomeArquivo; // Corrigido para produtos (plural) para compatibilidade
        
        error_log('✅ [IMAGEM] Upload concluído: ' . $url);
        error_log('✅ [IMAGEM] URL gerada para exibição: ' . $url);
        
        // Salvar no banco
        $dados = [
            'tipo' => $tipo,
            'nome_arquivo' => $nomeArquivo,
            'url' => $url,
            'caminho_fisico' => $caminhoCompleto,
            'tamanho' => $arquivo['size'],
            'mime_type' => $arquivo['type'],
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        $imagemId = $this->create($dados);
        
        return [
            'id' => $imagemId,
            'url' => $url,
            'nome_arquivo' => $nomeArquivo,
            'href' => $url, // URL pronta para uso em href
            'src' => $url   // URL pronta para uso em src
        ];
    }
    
    public function redimensionarParaTamanhoMaximo($caminhoArquivo, $tipo) {
        // Obter dimensões originais
        list($larguraOriginal, $alturaOriginal) = getimagesize($caminhoArquivo);
        
        // Definir tamanho máximo fixo para exibição consistente
        $tamanhoMaximo = 800;
        
        // Calcular novas dimensões mantendo proporção
        if ($larguraOriginal > $alturaOriginal) {
            // Imagem horizontal
            $novaLargura = $tamanhoMaximo;
            $novaAltura = intval(($alturaOriginal / $larguraOriginal) * $tamanhoMaximo);
        } else {
            // Imagem vertical ou quadrada
            $novaAltura = $tamanhoMaximo;
            $novaLargura = intval(($larguraOriginal / $alturaOriginal) * $tamanhoMaximo);
        }
        
        // Se a imagem for menor que o tamanho máximo, não redimensionar
        if ($larguraOriginal <= $tamanhoMaximo && $alturaOriginal <= $tamanhoMaximo) {
            $novaLargura = $larguraOriginal;
            $novaAltura = $alturaOriginal;
        }
        
        error_log('🔍 [IMAGEM] Redimensionando: ' . $larguraOriginal . 'x' . $alturaOriginal . ' → ' . $novaLargura . 'x' . $novaAltura);
        
        // Criar nova imagem
        $novaImagem = imagecreatetruecolor($novaLargura, $novaAltura);
        
        // Carregar imagem original
        switch ($tipo) {
            case 'image/jpeg':
                $imagemOriginal = imagecreatefromjpeg($caminhoArquivo);
                break;
            case 'image/png':
                $imagemOriginal = imagecreatefrompng($caminhoArquivo);
                // Manter transparência para PNG
                imagealphablending($novaImagem, false);
                imagesavealpha($novaImagem, true);
                break;
            case 'image/webp':
                $imagemOriginal = imagecreatefromwebp($caminhoArquivo);
                break;
            default:
                return false;
        }
        
        // Redimensionar com alta qualidade
        imagecopyresampled($novaImagem, $imagemOriginal, 0, 0, 0, 0, $novaLargura, $novaAltura, $larguraOriginal, $alturaOriginal);
        
        // Salvar nova imagem com alta qualidade
        switch ($tipo) {
            case 'image/jpeg':
                imagejpeg($novaImagem, $caminhoArquivo, 90); // 90% de qualidade
                break;
            case 'image/png':
                imagepng($novaImagem, $caminhoArquivo, 9); // Máxima compressão
                break;
            case 'image/webp':
                imagewebp($novaImagem, $caminhoArquivo, 90); // 90% de qualidade
                break;
        }
        
        // Liberar memória
        imagedestroy($novaImagem);
        imagedestroy($imagemOriginal);
        
        return true;
    }
    
    public function getById($id) {
        $stmt = $this->getConnection()->prepare("SELECT * FROM {$this->table} WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
    
    public function delete($id) {
        $imagem = $this->getById($id);
        
        if ($imagem && file_exists($imagem['caminho_fisico'])) {
            unlink($imagem['caminho_fisico']);
        }
        
        $stmt = $this->getConnection()->prepare("DELETE FROM {$this->table} WHERE id = :id");
        $stmt->bindParam(':id', $id);
        return $stmt->execute();
    }
}
