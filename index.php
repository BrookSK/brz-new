<?php
// Entrada única da aplicação quando o servidor aponta para a raiz.
// Apenas delega para o index da pasta public, sem alterar a URL.
require _DIR_ . '/public/index.php';