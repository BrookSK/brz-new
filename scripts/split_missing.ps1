$ErrorActionPreference = 'Stop'
$root = 'd:\GITHUB\brz-new'
$data = Get-Content "$root\scripts\i18n_missing_en.json" -Raw | ConvertFrom-Json

# Ordenar por chave para agrupar por namespace
$sorted = $data | Sort-Object key

$batchSize = 160
$total = $sorted.Count
$batchNum = 0
for ($i = 0; $i -lt $total; $i += $batchSize) {
    $batchNum++
    $end = [Math]::Min($i + $batchSize - 1, $total - 1)
    $slice = $sorted[$i..$end]
    $out = "$root\scripts\batch_en_$batchNum.json"
    $slice | ConvertTo-Json -Depth 3 | Out-File -FilePath $out -Encoding UTF8
    Write-Output "Batch $batchNum : itens $($i+1)..$($end+1) -> $out"
}
Write-Output "Total batches: $batchNum"
