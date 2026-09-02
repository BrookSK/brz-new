$raw = Get-Content -Raw -Encoding UTF8 (Join-Path $PSScriptRoot 'group_a_raw.json') | ConvertFrom-Json

$EN = @{}
foreach ($line in (Get-Content -Encoding UTF8 (Join-Path $PSScriptRoot 'en_data.txt'))) {
    if ($line -eq '') { continue }
    $i = $line.IndexOf('|')
    if ($i -lt 0) { continue }
    $k = $line.Substring(0, $i)
    $v = $line.Substring($i + 1)
    $EN[$k] = $v
}

$list = New-Object System.Collections.ArrayList
$missing = 0
foreach ($prop in ($raw.PSObject.Properties | Sort-Object Name)) {
    $key = $prop.Name
    $pt = [string]$prop.Value
    $en = $EN[$key]
    if ($null -eq $en -or $en -eq '') { $en = $pt; $missing++ }
    [void]$list.Add([ordered]@{ key = $key; en = $en; pt = $pt })
}

$json = $list | ConvertTo-Json -Depth 4
[System.IO.File]::WriteAllText((Join-Path $PSScriptRoot 'keys_group_A.json'), $json, (New-Object System.Text.UTF8Encoding($false)))
"TOTAL=$($list.Count) EN_COUNT=$($EN.Count) MISSING=$missing" | Set-Content (Join-Path $PSScriptRoot '_result.txt') -Encoding ascii
