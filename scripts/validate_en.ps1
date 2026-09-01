$ErrorActionPreference = 'Stop'
$root = 'd:\GITHUB\brz-new'
$enText = [System.IO.File]::ReadAllText("$root\app\lang\en.php", [System.Text.Encoding]::UTF8)

# Contar chaves no formato 'chave' =>
$keyMatches = [regex]::Matches($enText, "(?m)^\s*'([a-zA-Z0-9_.]+)'\s*=>")
Write-Output "Total de entradas 'chave' =>: $($keyMatches.Count)"

# Detectar duplicatas
$seen = @{}
$dups = @()
foreach ($m in $keyMatches) {
    $k = $m.Groups[1].Value
    if ($seen.ContainsKey($k)) { $dups += $k } else { $seen[$k] = 1 }
}
Write-Output "Chaves distintas: $($seen.Count)"
Write-Output "Duplicatas: $($dups.Count)"
if ($dups.Count -gt 0) {
    Write-Output "--- Lista de duplicatas ---"
    $dups | Select-Object -Unique | ForEach-Object { Write-Output $_ }
}

# Verificar fechamento
if ($enText.TrimEnd().EndsWith('];')) { Write-Output "Fechamento ']; OK" } else { Write-Output "ERRO: nao fecha com ];" }
if ($enText.TrimStart().StartsWith('<?php')) { Write-Output "Abertura <?php OK" } else { Write-Output "ERRO: nao inicia com <?php" }

# Verificar linhas suspeitas (=> sem virgula no fim, ignorando array/multi-linha)
$badLines = @()
$lines = $enText -split "`n"
$ln = 0
foreach ($line in $lines) {
    $ln++
    $t = $line.Trim()
    if ($t -match "^'[a-zA-Z0-9_.]+'\s*=>") {
        if ($t -notmatch ",\s*$") { $badLines += "L$ln : $t" }
    }
}
Write-Output "Linhas de chave sem virgula final: $($badLines.Count)"
if ($badLines.Count -gt 0) { $badLines | Select-Object -First 20 | ForEach-Object { Write-Output $_ } }
