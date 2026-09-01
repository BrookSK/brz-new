<?php
/**
 * Extrai todos os pares __('chave', 'fallback PT') dos controllers admin e views,
 * detecta quais chaves faltam em app/lang/en.php e gera:
 *  - scripts/i18n_missing_en.json  (chave => fallback PT) que precisam de tradução EN
 *  - scripts/i18n_missing_pt.json  (chave => fallback PT) que faltam no pt-BR.php
 *
 * Uso (quando houver PHP): php scripts/extract_i18n_keys.php
 * Não depende de banco.
 */

$root = dirname(__DIR__);

// Arquivos a varrer: todos os controllers e views admin
$paths = [];
$dirs = [
    $root . '/app/Controllers',
    $root . '/app/Views/admin',
    $root . '/app/Views/partials',
];
$rii = function($dir) {
    if (!is_dir($dir)) return [];
    $out = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->isFile() && strtolower($f->getExtension()) === 'php') {
            $out[] = $f->getPathname();
        }
    }
    return $out;
};
foreach ($dirs as $d) {
    $paths = array_merge($paths, $rii($d));
}

// Carregar dicionários existentes
$enFile = $root . '/app/lang/en.php';
$ptFile = $root . '/app/lang/pt-BR.php';
$en = is_file($enFile) ? (require $enFile) : [];
$pt = is_file($ptFile) ? (require $ptFile) : [];
if (!is_array($en)) $en = [];
if (!is_array($pt)) $pt = [];

// Regex para __('chave', 'fallback') ou __("chave", "fallback")
// Captura chave e fallback (com aspas simples ou duplas).
$pairs = [];   // chave => fallback PT (primeira ocorrência com fallback não vazio)
$allKeys = []; // todas as chaves vistas

$reSingle = "/__\\(\\s*'([^']+)'\\s*,\\s*'((?:[^'\\\\]|\\\\.)*)'/";
$reDouble = '/__\(\s*"([^"]+)"\s*,\s*"((?:[^"\\\\]|\\\\.)*)"/';

foreach ($paths as $p) {
    $code = file_get_contents($p);
    if ($code === false) continue;

    if (preg_match_all($reSingle, $code, $m, PREG_SET_ORDER)) {
        foreach ($m as $mm) {
            $key = $mm[1];
            $fb = stripcslashes($mm[2]);
            $allKeys[$key] = true;
            if (!isset($pairs[$key]) && trim($fb) !== '') {
                $pairs[$key] = $fb;
            }
        }
    }
    if (preg_match_all($reDouble, $code, $m2, PREG_SET_ORDER)) {
        foreach ($m2 as $mm) {
            $key = $mm[1];
            $fb = stripcslashes($mm[2]);
            $allKeys[$key] = true;
            if (!isset($pairs[$key]) && trim($fb) !== '') {
                $pairs[$key] = $fb;
            }
        }
    }
}

// Chaves faltando no EN e no PT
$missingEn = [];
$missingPt = [];
foreach ($allKeys as $key => $_) {
    $fb = $pairs[$key] ?? '';
    if (!array_key_exists($key, $en)) {
        $missingEn[$key] = $fb;
    }
    if (!array_key_exists($key, $pt)) {
        $missingPt[$key] = $fb;
    }
}

ksort($missingEn);
ksort($missingPt);

file_put_contents($root . '/scripts/i18n_missing_en.json', json_encode($missingEn, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
file_put_contents($root . '/scripts/i18n_missing_pt.json', json_encode($missingPt, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

echo "Total de chaves usadas: " . count($allKeys) . "\n";
echo "Faltando no en.php: " . count($missingEn) . "\n";
echo "Faltando no pt-BR.php: " . count($missingPt) . "\n";
echo "Arquivos gerados: scripts/i18n_missing_en.json, scripts/i18n_missing_pt.json\n";
