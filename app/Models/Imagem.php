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
        error_log('🔍 [IMAGEM] Tentando mover arquivo de ' . $arquivo['tmp_name'] . ' para ' . $caminhoCompleto);
        
        if (!move_uploaded_file($arquivo['tmp_name'], $caminhoCompleto)) {
            error_log('❌ [IMAGEM] ERRO ao mover arquivo: ' . $arquivo['error']);
            throw new \Exception('Erro ao fazer upload do arquivo.');
        }
        
        error_log('✅ [IMAGEM] Arquivo movido com sucesso. Tamanho: ' . filesize($caminhoCompleto) . ' bytes');
        
        // Verificar se o arquivo é uma imagem válida antes de redimensionar
        $info = getimagesize($caminhoCompleto);
        if (!$info) {
            error_log('❌ [IMAGEM] ERRO: Arquivo não é uma imagem válida: ' . $caminhoCompleto);
            throw new \Exception('Arquivo enviado não é uma imagem válida.');
        }
        
        error_log('🔍 [IMAGEM] Informações da imagem original: ' . print_r($info, true));
        
        // Redimensionar para tamanho máximo fixo (800x800 para exibição consistente)
        $resultadoRedimensionamento = $this->redimensionarParaTamanhoMaximo($caminhoCompleto, $arquivo['type']);
        
        if (!$resultadoRedimensionamento) {
            error_log('❌ [IMAGEM] ERRO no redimensionamento, mantendo arquivo original');
        } else {
            error_log('✅ [IMAGEM] Redimensionamento concluído. Novo tamanho: ' . filesize($caminhoCompleto) . ' bytes');
        }
        
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
        error_log('🔍 [IMAGEM-REDIMENSIONAR] Iniciando redimensionamento de: ' . $caminhoArquivo . ' Tipo: ' . $tipo);
        
        // Obter dimensões originais
        $info = getimagesize($caminhoArquivo);
        if (!$info) {
            error_log('❌ [IMAGEM-REDIMENSIONAR] ERRO: Não foi possível obter dimensões da imagem');
            return false;
        }
        
        list($larguraOriginal, $alturaOriginal) = [$info[0], $info[1]];
        error_log('🔍 [IMAGEM-REDIMENSIONAR] Dimensões originais: ' . $larguraOriginal . 'x' . $alturaOriginal);
        
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
            error_log('🔍 [IMAGEM-REDIMENSIONAR] Imagem já está dentro do tamanho máximo, mantendo original');
        }
        
        error_log('🔍 [IMAGEM-REDIMENSIONAR] Novas dimensões: ' . $novaLargura . 'x' . $novaAltura);
        
        // Verificar se GD está disponível
        if (!extension_loaded('gd') || !function_exists('gd_info')) {
            error_log('❌ [IMAGEM-REDIMENSIONAR] ERRO: Extensão GD não está carregada');
            return false;
        }
        
        // Criar nova imagem
        $novaImagem = imagecreatetruecolor($novaLargura, $novaAltura);
        if (!$novaImagem) {
            error_log('❌ [IMAGEM-REDIMENSIONAR] ERRO: Não foi possível criar nova imagem');
            return false;
        }
        
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
                error_log('❌ [IMAGEM-REDIMENSIONAR] ERRO: Tipo de imagem não suportado: ' . $tipo);
                imagedestroy($novaImagem);
                return false;
        }
        
        if (!$imagemOriginal) {
            error_log('❌ [IMAGEM-REDIMENSIONAR] ERRO: Não foi possível carregar imagem original');
            imagedestroy($novaImagem);
            return false;
        }
        
        // Redimensionar com alta qualidade
        $resultado = imagecopyresampled($novaImagem, $imagemOriginal, 0, 0, 0, 0, $novaLargura, $novaAltura, $larguraOriginal, $alturaOriginal);
        
        if (!$resultado) {
            error_log('❌ [IMAGEM-REDIMENSIONAR] ERRO: Falha no redimensionamento');
            imagedestroy($novaImagem);
            imagedestroy($imagemOriginal);
            return false;
        }
        
        // Salvar nova imagem com alta qualidade
        $salvou = false;
        switch ($tipo) {
            case 'image/jpeg':
                $salvou = imagejpeg($novaImagem, $caminhoArquivo, 90); // 90% de qualidade
                break;
            case 'image/png':
                $salvou = imagepng($novaImagem, $caminhoArquivo, 9); // Máxima compressão
                break;
            case 'image/webp':
                $salvou = imagewebp($novaImagem, $caminhoArquivo, 90); // 90% de qualidade
                break;
        }
        
        // Liberar memória
        imagedestroy($novaImagem);
        imagedestroy($imagemOriginal);
        
        if (!$salvou) {
            error_log('❌ [IMAGEM-REDIMENSIONAR] ERRO: Falha ao salvar imagem redimensionada');
            return false;
        }
        
        error_log('✅ [IMAGEM-REDIMENSIONAR] Redimensionamento concluído com sucesso');
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
