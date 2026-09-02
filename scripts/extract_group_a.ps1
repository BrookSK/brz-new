$files = @(
  'app/Controllers/AdminDescricaoProdutosController.php',
  'app/Controllers/AdminComprasController.php',
  'app/Controllers/AdminEstoqueController.php',
  'app/Controllers/AdminProdutosController.php',
  'app/Controllers/AdminConfiguracoesController.php'
)

# Match __( 'key' , 'fallback'  OR __( 'key' , "fallback"
# key is always single-quoted. Fallback may be single or double quoted.
$rxSingle = [regex]"__\(\s*'((?:admin\.)[^']*)'\s*,\s*'((?:[^'\\]|\\.)*)'"
$rxDouble = [regex]'__\(\s*''((?:admin\.)[^'']*)''\s*,\s*"((?:[^"\\]|\\.)*)"'
# key only (no fallback), e.g. __('admin.x.y')
$rxKeyOnly = [regex]"__\(\s*'((?:admin\.)[^']*)'\s*[\),]"

$map = [ordered]@{}

foreach ($f in $files) {
  $content = Get-Content -Raw -Encoding UTF8 $f
  foreach ($m in $rxSingle.Matches($content)) {
    $k = $m.Groups[1].Value
    $v = $m.Groups[2].Value -replace "\\'","'" -replace '\\\\','\'
    if (-not $map.Contains($k)) { $map[$k] = $v }
    elseif ([string]::IsNullOrEmpty($map[$k]) -and $v) { $map[$k] = $v }
  }
  foreach ($m in $rxDouble.Matches($content)) {
    $k = $m.Groups[1].Value
    $v = $m.Groups[2].Value -replace '\\"','"' -replace '\\\\','\'
    if (-not $map.Contains($k)) { $map[$k] = $v }
    elseif ([string]::IsNullOrEmpty($map[$k]) -and $v) { $map[$k] = $v }
  }
  foreach ($m in $rxKeyOnly.Matches($content)) {
    $k = $m.Groups[1].Value
    if (-not $map.Contains($k)) { $map[$k] = '' }
  }
}

$sorted = [ordered]@{}
foreach ($k in ($map.Keys | Sort-Object)) { $sorted[$k] = $map[$k] }

$sorted | ConvertTo-Json -Depth 3 | Set-Content -Encoding UTF8 'scripts/group_a_raw.json'
Write-Output ("TOTAL KEYS: " + $sorted.Count)
