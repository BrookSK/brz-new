<?php

function image_to_base64(String $filename) : String {
    if (!file_exists($filename)) {
        throw new Exception("Arquivo não encontrado: $filename");
    }
    
    $mime = mime_content_type($filename);
    if ($mime === false) {
        throw new Exception('Tipo MIME inválido.');
    }
    
    $raw_data = file_get_contents($filename);
    if ($raw_data === false) {
        throw new Exception('Arquivo não pode ser lido ou está vazio.');
    }
    
    return "data:{$mime};base64," . base64_encode($raw_data);
}
