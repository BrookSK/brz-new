$ErrorActionPreference = 'Stop'
$root = 'd:\GITHUB\brz-new'
$lines = [System.IO.File]::ReadAllLines("$root\scripts\untranslated_en.txt", [System.Text.Encoding]::UTF8)
$items = [System.Collections.Generic.List[object]]::new()
$seen = @{}
foreach ($l in $lines) {
    if ($l.Trim() -eq '') { continue }
    $parts = $l -split '\|', 3
    if ($parts.Count -lt 2) { continue }
    $key = $parts[0]
    $pt = $parts[2]  # usar o campo pt (3o) que e o fallback
    if ($null -eq $pt -or $pt -eq '') { $pt = $parts[1] }
    if ($seen.ContainsKey($key)) { continue }
    $seen[$key] = $true
    $items.Add([PSCustomObject]@{ key = $key; pt = $pt })
}
Write-Output ("Itens unicos para retraduzir: {0}" -f $items.Count)
# Dividir em 4 lotes
$batchSize = [Math]::Ceiling($items.Count / 4)
for ($b = 0; $b -lt 4; $b++) {
    $slice = $items | Select-Object -Skip ($b * $batchSize) -First $batchSize
    if ($slice) {
        $slice | ConvertTo-Json -Depth 3 | Out-File "$root\scripts\retrans_$($b+1).json" -Encoding UTF8
        Write-Output ("retrans_$($b+1).json: {0} itens" -f @($slice).Count)
    }
}
