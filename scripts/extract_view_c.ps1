$ErrorActionPreference = 'Stop'
$base = 'd:\GITHUB\brz-new\app\Views\admin'
$files = @(
  "$base\carne\index.php",
  "$base\carne\compras.php",
  "$base\carne\compras-internas.php",
  "$base\carne\compras-mensal.php",
  "$base\carne\detalhes.php",
  "$base\carne\configuracoes.php",
  "$base\carne\arquivados.php",
  "$base\carne\logs.php",
  "$base\despesas\index.php",
  "$base\grupos-compras\index.php",
  "$base\promocoes-agendadas\index.php",
  "$base\promocoes-auditoria.php",
  "$base\carteira-config\index.php",
  "$base\clube-recargas.php"
)

# Match __('key', 'pt'...)  capturing key (group1) and pt fallback (group2)
# key is single-quoted; pt fallback is single-quoted and may contain escaped quotes \'
$rx = [regex]"__\(\s*'((?:[^'\\]|\\.)*)'\s*,\s*'((?:[^'\\]|\\.)*)'"

$seen = [ordered]@{}
foreach ($f in $files) {
  if (-not (Test-Path $f)) { Write-Host "MISSING: $f"; continue }
  $txt = Get-Content -Raw -Encoding UTF8 $f
  foreach ($m in $rx.Matches($txt)) {
    $key = $m.Groups[1].Value
    $pt  = $m.Groups[2].Value
    if (-not $seen.Contains($key)) {
      $seen[$key] = $pt
    }
  }
}

Write-Host ("TOTAL KEYS: " + $seen.Count)
$out = foreach ($k in $seen.Keys) {
  [pscustomobject]@{ key = $k; pt = $seen[$k] }
}
$out | ConvertTo-Json -Depth 3 | Out-File -Encoding UTF8 'd:\GITHUB\brz-new\scripts\_view_c_raw.json'
Write-Host "WROTE _view_c_raw.json"
