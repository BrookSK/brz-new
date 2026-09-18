$ErrorActionPreference = 'Stop'
$root = 'd:\GITHUB\brz-new'
foreach ($f in @('app\lang\en.php','app\lang\pt-BR.php')) {
    $path = Join-Path $root $f
    $lines = [System.IO.File]::ReadAllLines($path, [System.Text.Encoding]::UTF8)
    $bad = @()
    $ln = 0
    foreach ($line in $lines) {
        $ln++
        $t = $line.Trim()
        # Só linhas de entrada 'chave' => 'valor',
        if ($t -notmatch "^'[a-zA-Z0-9_.]+'\s*=>\s*'") { continue }
        # Extrair a parte do valor entre a primeira aspa apos => e a ultima aspa antes da virgula final
        if ($t -match "^'[a-zA-Z0-9_.]+'\s*=>\s*'(.*)',\s*$") {
            $val = $Matches[1]
            # Contar aspas simples nao escapadas dentro do valor
            # Remover \' (escapadas) e ver se sobra ' solta
            $stripped = $val -replace "\\'", ""
            if ($stripped.Contains("'")) {
                $bad += ("L{0}: {1}" -f $ln, $t)
            }
        } else {
            # Linha de chave que nao casou o padrao fechado -> suspeita (valor multiline ou aspas quebradas)
            $bad += ("L{0} [FORMATO]: {1}" -f $ln, $t)
        }
    }
    Write-Output ("=== {0}: {1} linha(s) suspeita(s) ===" -f $f, $bad.Count)
    $bad | Select-Object -First 40 | ForEach-Object { Write-Output $_ }
}
