<?php
// Criar um placeholder.jpg válido
$img = imagecreatetruecolor(400, 400);
$bg = imagecolorallocate($img, 240, 240, 240);
$text_color = imagecolorallocate($img, 150, 150, 150);

// Preencher fundo
imagefilledrectangle($img, 0, 0, 399, 399, $bg);

// Adicionar texto
$text = "Sem Imagem";
$font = 5;
$text_width = imagefontwidth($font) * strlen($text);
$text_height = imagefontheight($font);
$x = (400 - $text_width) / 2;
$y = (400 - $text_height) / 2;
imagestring($img, $font, $x, $y, $text, $text_color);

// Salvar imagem
imagejpeg($img, 'c:\Users\Pichau\Documents\GitHub\brz-new\public\uploads\produtos\placeholder.jpg', 90);
imagedestroy($img);

echo "Placeholder criado com sucesso!";
?>
