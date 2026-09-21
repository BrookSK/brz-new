# Extrai pares __('chave','fallback') do codigo-fonte e compara com en.php
# Gera scripts/i18n_pairs.json (chave -> fallback PT) e scripts/i18n_missing_en.txt

$ErrorActionPreference = 'Stop'
$root = 'd:\GITHUB\brz-new'
$dirs = @(
    "$root\app\Controllers",
    "$root\app\Views"
)

# Regex para __('chave', 'fallback') ou __("chave", "fallback")
# Captura chave (grupo 1) e fallback (grupo 2). Suporta aspas simples e duplas.
$pattern = "__\(\s*(['`"])([a-zA-Z0-9_.]+)\1\s*,\s*(['`"])((?:\\.|(?!\3).)*)\3"

$pairs = @{}

foreach ($dir in $dirs) {
    if (-not (Test-Path $dir)) { continue }
    $files = Get-ChildItem -Path $dir -Recurse -Include *.php -File
    foreach ($f in $files) {
        $content = Get-Content -Path $f.FullName -Raw
        $matches = [regex]::Matches($content, $pattern)
        foreach ($m in $matches) {
            $key = $m.Groups[2].Value
            $fallback = $m.Groups[4].Value
            if (-not $pairs.ContainsKey($key)) {
                $pairs[$key] = $fallback
            }
        }
    }
}

Write-Output "Total de chaves distintas encontradas: $($pairs.Count)"

# Carregar chaves ja existentes no en.php
$enContent = Get-Content -Path "$root\app\lang\en.php" -Raw
$enKeys = @{}
$enMatches = [regex]::Matches($enContent, "(['`"])([a-zA-Z0-9_.]+)\1\s*=>")
foreach ($m in $enMatches) {
    $enKeys[$m.Groups[2].Value] = $true
}
Write-Output "Chaves ja em en.php: $($enKeys.Count)"

# Carregar chaves ja existentes no pt-BR.php
$ptContent = Get-Content -Path "$root\app\lang\pt-BR.php" -Raw
$ptKeys = @{}
$ptMatches = [regex]::Matches($ptContent, "(['`"])([a-zA-Z0-9_.]+)\1\s*=>")
foreach ($m in $ptMatches) {
    $ptKeys[$m.Groups[2].Value] = $true
}
Write-Output "Chaves ja em pt-BR.php: $($ptKeys.Count)"

# Faltantes
$missingEn = @{}
$missingPt = @{}
foreach ($k in $pairs.Keys) {
    if (-not $enKeys.ContainsKey($k)) { $missingEn[$k] = $pairs[$k] }
    if (-not $ptKeys.ContainsKey($k)) { $missingPt[$k] = $pairs[$k] }
}
Write-Output "Faltantes em en.php: $($missingEn.Count)"
Write-Output "Faltantes em pt-BR.php: $($missingPt.Count)"

# Exportar como JSON (ordenado por chave)
$pairs.GetEnumerator() | Sort-Object Name | ForEach-Object {
    [PSCustomObject]@{ key = $_.Key; fallback = $_.Value }
} | ConvertTo-Json -Depth 3 | Out-File -FilePath "$root\scripts\i18n_pairs.json" -Encoding UTF8

$missingEn.GetEnumerator() | Sort-Object Name | ForEach-Object {
    [PSCustomObject]@{ key = $_.Key; fallback = $_.Value }
} | ConvertTo-Json -Depth 3 | Out-File -FilePath "$root\scripts\i18n_missing_en.json" -Encoding UTF8

$missingPt.GetEnumerator() | Sort-Object Name | ForEach-Object {
    [PSCustomObject]@{ key = $_.Key; fallback = $_.Value }
} | ConvertTo-Json -Depth 3 | Out-File -FilePath "$root\scripts\i18n_missing_pt.json" -Encoding UTF8

Write-Output "OK - arquivos gerados em scripts\"
