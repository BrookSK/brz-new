$ErrorActionPreference = 'Stop'
$root = 'd:\GITHUB\brz-new'
$files = Get-ChildItem "$root\app\Controllers\Admin*.php" -File

$noI18n = @()   # controllers SEM nenhum __()
$partial = @()  # controllers com poucos __() (suspeitos de estar incompletos)

foreach ($f in $files) {
    $c = [System.IO.File]::ReadAllText($f.FullName, [System.Text.Encoding]::UTF8)
    $i18nCount = ([regex]::Matches($c, "__\(\s*'")).Count
    # Contar strings PT candidatas em echo/html: palavras com acentos ou padroes tipicos
    $ptHints = ([regex]::Matches($c, "(?i)(Ã§|Ã£|Ã©|Ã­|Ã¡|Ã³|Ãº|çã|ções|ê|á|é|í|ó|ú|Buscar|Salvar|Excluir|Pendente|Aprovad|Reprovad|Descrição|Configura|Relatório|Pedido)")).Count
    if ($i18nCount -eq 0) {
        $noI18n += [PSCustomObject]@{ file = $f.Name; i18n = $i18nCount; ptHints = $ptHints }
    } elseif ($i18nCount -lt 10 -and $ptHints -gt 20) {
        $partial += [PSCustomObject]@{ file = $f.Name; i18n = $i18nCount; ptHints = $ptHints }
    }
}

Write-Output "=== Controllers SEM nenhum __() ($($noI18n.Count)) ==="
$noI18n | Sort-Object file | ForEach-Object { Write-Output ("{0}  (ptHints={1})" -f $_.file, $_.ptHints) }
Write-Output ""
Write-Output "=== Controllers com POUCO __() mas muito PT ($($partial.Count)) ==="
$partial | Sort-Object file | ForEach-Object { Write-Output ("{0}  (i18n={1}, ptHints={2})" -f $_.file, $_.i18n, $_.ptHints) }
