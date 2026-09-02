$raw = Get-Content -Raw -Encoding UTF8 (Join-Path $PSScriptRoot 'group_a_raw.json') | ConvertFrom-Json
$EN = @{ 'admin.inventory.access_denied'='Access denied.'; 'admin.products.save'='Save' }
$out = @()
$out += "EN_TYPE=" + $EN.GetType().Name
$out += "EN_COUNT=" + $EN.Count
$k = 'admin.products.save'
$out += "DIRECT=[" + $EN[$k] + "]"
$firstProp = ($raw.PSObject.Properties | Select-Object -First 1)
$out += "RAW_FIRST_NAME=[" + $firstProp.Name + "]"
$out += "RAW_HAS_SAVE=" + ($raw.PSObject.Properties.Name -contains 'admin.products.save')
$saveProp = $raw.PSObject.Properties | Where-Object { $_.Name -eq 'admin.products.save' }
$out += "SAVE_LOOKUP=[" + $EN[$saveProp.Name] + "]"
$out | Set-Content (Join-Path $PSScriptRoot '_dbg.txt') -Encoding ascii
