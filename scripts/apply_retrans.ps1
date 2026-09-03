$ErrorActionPreference = 'Stop'
$root = 'd:\GITHUB\brz-new'
$enFile = "$root\app\lang\en.php"

function Escape-PhpSingle($s) {
    return ($s -replace '\\', '\\' -replace "'", "\'")
}

# Carregar todas as correcoes
$fixes = @{}
foreach ($f in (Get-ChildItem "$root\scripts\retrans_fix_*.json")) {
    $rawJson = [System.IO.File]::ReadAllText($f.FullName, [System.Text.Encoding]::UTF8)
    $data = $rawJson | ConvertFrom-Json
    foreach ($item in $data) {
        if ($item.key -and $item.en) { $fixes[$item.key] = [string]$item.en }
    }
}
Write-Output ("Correcoes carregadas: {0}" -f $fixes.Count)

$lines = [System.IO.File]::ReadAllLines($enFile, [System.Text.Encoding]::UTF8)
$applied = 0
$notFound = 0
for ($i = 0; $i -lt $lines.Count; $i++) {
    $line = $lines[$i]
    if ($line -match "^(\s*)'([a-zA-Z0-9_.]+)'\s*=>\s*'.*',\s*$") {
        $indent = $Matches[1]
        $key = $Matches[2]
        if ($fixes.ContainsKey($key)) {
            $newVal = Escape-PhpSingle $fixes[$key]
            $lines[$i] = "$indent'$key' => '$newVal',"
            $applied++
        }
    }
}
Write-Output ("Linhas substituidas: {0}" -f $applied)

$enc = New-Object System.Text.UTF8Encoding($false)
[System.IO.File]::WriteAllLines($enFile, $lines, $enc)
Write-Output "OK - en.php atualizado"
