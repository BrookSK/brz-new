$ErrorActionPreference = 'Stop'
$root = 'd:\GITHUB\brz-new'
$enFile = "$root\app\lang\en.php"

# Ler en.php atual (UTF-8)
$enText = [System.IO.File]::ReadAllText($enFile, [System.Text.Encoding]::UTF8)

# Seguranca: se ja tiver fechamento apos o marcador, aborta
if ($enText.TrimEnd().EndsWith('];')) {
    Write-Output "ABORT: en.php ja termina com ']; - nao consolido de novo para evitar duplicar."
    exit 1
}

$sb = New-Object System.Text.StringBuilder
[void]$sb.Append($enText)
if (-not $enText.EndsWith("`n")) { [void]$sb.Append("`n") }

foreach ($n in 2..8) {
    $p = "$root\scripts\en_part_$n.txt"
    $lines = [System.IO.File]::ReadAllText($p, [System.Text.Encoding]::UTF8)
    # normalizar: remover linhas vazias no fim, garantir newline
    $lines = $lines.TrimEnd()
    [void]$sb.Append("`n    // ---- part $n ----`n")
    [void]$sb.Append($lines)
    [void]$sb.Append("`n")
}

[void]$sb.Append("];`n")

# Escrever de volta em UTF-8 sem BOM
$enc = New-Object System.Text.UTF8Encoding($false)
[System.IO.File]::WriteAllText($enFile, $sb.ToString(), $enc)

Write-Output "OK - en.php consolidado e fechado com ];"
