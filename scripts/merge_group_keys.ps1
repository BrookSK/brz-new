$ErrorActionPreference = 'Stop'
$root = 'd:\GITHUB\brz-new'
$enc = New-Object System.Text.UTF8Encoding($false)

# Carregar chaves ja existentes em en.php e pt-BR.php
function Get-ExistingKeys($file) {
    $txt = [System.IO.File]::ReadAllText($file, [System.Text.Encoding]::UTF8)
    $keys = @{}
    foreach ($m in [regex]::Matches($txt, "(?m)^\s*'([a-zA-Z0-9_.]+)'\s*=>")) {
        $keys[$m.Groups[1].Value] = $true
    }
    return $keys
}

$enFile = "$root\app\lang\en.php"
$ptFile = "$root\app\lang\pt-BR.php"
$enKeys = Get-ExistingKeys $enFile
$ptKeys = Get-ExistingKeys $ptFile

# Coletar todos os pares de todos os group JSONs
$groupFiles = Get-ChildItem "$root\scripts\keys_*.json"
$enAdd = [System.Collections.Generic.List[string]]::new()
$ptAdd = [System.Collections.Generic.List[string]]::new()
$seenEn = @{}
$seenPt = @{}

function Escape-PhpSingle($s) {
    return ($s -replace '\\', '\\' -replace "'", "\'")
}

foreach ($gf in $groupFiles) {
    $data = Get-Content $gf.FullName -Raw | ConvertFrom-Json
    foreach ($item in $data) {
        $k = $item.key
        if (-not $k) { continue }
        $en = [string]$item.en
        $pt = [string]$item.pt
        if (-not $enKeys.ContainsKey($k) -and -not $seenEn.ContainsKey($k)) {
            $seenEn[$k] = $true
            $enAdd.Add("    '" + (Escape-PhpSingle $k) + "' => '" + (Escape-PhpSingle $en) + "',")
        }
        if (-not $ptKeys.ContainsKey($k) -and -not $seenPt.ContainsKey($k)) {
            $seenPt[$k] = $true
            $ptAdd.Add("    '" + (Escape-PhpSingle $k) + "' => '" + (Escape-PhpSingle $pt) + "',")
        }
    }
}

Write-Output "Novas chaves EN a inserir: $($enAdd.Count)"
Write-Output "Novas chaves PT a inserir: $($ptAdd.Count)"

function Insert-BeforeClose($file, $lines, $marker) {
    $txt = [System.IO.File]::ReadAllText($file, [System.Text.Encoding]::UTF8)
    $idx = $txt.LastIndexOf('];')
    if ($idx -lt 0) { throw "Nao achei ]; em $file" }
    $block = "`n    // ===== $marker =====`n" + ($lines -join "`n") + "`n"
    $newTxt = $txt.Substring(0, $idx) + $block + $txt.Substring($idx)
    [System.IO.File]::WriteAllText($file, $newTxt, (New-Object System.Text.UTF8Encoding($false)))
}

if ($enAdd.Count -gt 0) { Insert-BeforeClose $enFile $enAdd "Group keys (auto EN)" }
if ($ptAdd.Count -gt 0) { Insert-BeforeClose $ptFile $ptAdd "Group keys (auto PT)" }

Write-Output "OK - merge concluido"
