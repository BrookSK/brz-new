$ErrorActionPreference='Stop'
$root = Split-Path -Parent $PSScriptRoot
$out = @()
foreach($f in Get-ChildItem (Join-Path $PSScriptRoot 'keys_view_*.json')){
  try {
    $j = Get-Content $f.FullName -Raw | ConvertFrom-Json
    $out += ('{0}: {1} entries, {2} bytes' -f $f.Name, $j.Count, $f.Length)
  } catch {
    $out += ('{0}: PARSE ERROR ({1} bytes)' -f $f.Name, $f.Length)
  }
}
# Verificar quantas chaves de cada arquivo ja existem no en.php
$en = [System.IO.File]::ReadAllText((Join-Path $root 'app\lang\en.php'))
foreach($f in Get-ChildItem (Join-Path $PSScriptRoot 'keys_view_*.json')){
  try {
    $j = Get-Content $f.FullName -Raw | ConvertFrom-Json
    $present = 0; $missing = 0
    foreach($item in $j){
      $k = $item.key
      if ($null -eq $k) { continue }
      if ($en -match [regex]::Escape("'$k'")) { $present++ } else { $missing++ }
    }
    $out += ('{0}: in en.php present={1} missing={2}' -f $f.Name, $present, $missing)
  } catch {}
}
[System.IO.File]::WriteAllLines((Join-Path $root 'kv_sizes.txt'), $out, (New-Object System.Text.UTF8Encoding($false)))
Write-Output "done"
