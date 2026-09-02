<?php
// Extract __('key','fallback') pairs from the 5 group-A controllers.
// Uses PHP tokenizer for robustness (handles concatenation, htmlspecialchars wrap, etc.)

$files = [
    __DIR__ . '/../app/Controllers/AdminDescricaoProdutosController.php',
    __DIR__ . '/../app/Controllers/AdminComprasController.php',
    __DIR__ . '/../app/Controllers/AdminEstoqueController.php',
    __DIR__ . '/../app/Controllers/AdminProdutosController.php',
    __DIR__ . '/../app/Controllers/AdminConfiguracoesController.php',
];

$results = []; // key => pt fallback

foreach ($files as $file) {
    $code = file_get_contents($file);
    $tokens = token_get_all($code);
    $n = count($tokens);
    for ($i = 0; $i < $n; $i++) {
        $t = $tokens[$i];
        // Look for a T_STRING '__' followed by '('
        if (is_array($t) && $t[0] === T_STRING && $t[1] === '__') {
            // find next non-whitespace token
            $j = $i + 1;
            while ($j < $n && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE) $j++;
            if ($j < $n && $tokens[$j] === '(') {
                // collect first two string arguments at depth 1
                $depth = 0;
                $args = [];          // list of arg strings (only literal single T_CONSTANT_ENCAPSED_STRING args)
                $curHasLiteral = false;
                $curLiteral = null;
                $curTokenCount = 0;  // count meaningful tokens in current arg
                $k = $j;
                for (; $k < $n; $k++) {
                    $tk = $tokens[$k];
                    if ($tk === '(') { $depth++; if ($depth === 1) continue; }
                    if ($tk === ')') { $depth--; if ($depth === 0) { // close arg
                            $args[] = [$curTokenCount, $curLiteral];
                            break; } }
                    if ($depth === 1 && $tk === ',') {
                        $args[] = [$curTokenCount, $curLiteral];
                        $curTokenCount = 0; $curLiteral = null;
                        continue;
                    }
                    if ($depth >= 1) {
                        if (is_array($tk)) {
                            if ($tk[0] === T_WHITESPACE || $tk[0] === T_COMMENT || $tk[0] === T_DOC_COMMENT) continue;
                            $curTokenCount++;
                            if ($tk[0] === T_CONSTANT_ENCAPSED_STRING) {
                                $curLiteral = $tk[1];
                            }
                        } else {
                            $curTokenCount++;
                        }
                    }
                }
                // args[0] = key, args[1] = fallback
                if (count($args) >= 1 && $args[0][0] === 1 && $args[0][1] !== null) {
                    $keyRaw = $args[0][1];
                    $key = stripcslashes(substr($keyRaw, 1, -1));
                    if (strpos($key, 'admin.') === 0) {
                        $pt = '';
                        if (count($args) >= 2 && $args[1][0] === 1 && $args[1][1] !== null) {
                            $ptRaw = $args[1][1];
                            // Unquote (single or double)
                            $q = $ptRaw[0];
                            $inner = substr($ptRaw, 1, -1);
                            if ($q === "'") {
                                $pt = str_replace(["\\'", "\\\\"], ["'", "\\"], $inner);
                            } else {
                                $pt = stripcslashes($inner);
                            }
                        }
                        if (!isset($results[$key])) {
                            $results[$key] = $pt;
                        } elseif ($results[$key] === '' && $pt !== '') {
                            $results[$key] = $pt;
                        }
                    }
                }
            }
        }
    }
}

ksort($results);
echo "TOTAL KEYS: " . count($results) . "\n";
file_put_contents(__DIR__ . '/group_a_raw.json', json_encode($results, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo "written group_a_raw.json\n";
