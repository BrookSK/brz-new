<?php
// Criar um placeholder.jpg válido
$img = imagecreatetruecolor(200, 200);
$bg = imagecolorallocate($img, 200, 200, 200);
$text_color = imagecolorallocate($img, 100, 100, 100);

// Preencher fundo
imagefilledrectangle($img, 0, 0, 199, 199, $bg);

// Adicionar texto
$text = "Sem Imagem";
$font = 5;
$text_width = imagefontwidth($font, $text);
$text_height = imagefontheight($font);
$x = (200 - $text_width) / 2;
$y = (200 - $text_height) / 2;
imagestring($img, $font, $x, $y, $text, $text_color);

// Salvar imagem
imagejpeg($img, 'public/uploads/produtos/placeholder.jpg', 90);
imagedestroy($img);

echo "Placeholder criado com sucesso!";
?>
