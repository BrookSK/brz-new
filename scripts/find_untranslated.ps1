$ErrorActionPreference = 'Stop'
$root = 'd:\GITHUB\brz-new'
# Palavras/caracteres claramente PT que nunca deveriam estar num valor EN
$ptMarkers = @('ç','ã','õ','á','é','í','ó','ú','â','ê','Ã','ç','Ç')
$ptWords = @('ção','ões','Aprovar','Reprovar','Salvar','Excluir','Pendente','Descrição','Configura','Pedido','Usuário','Produto','Categoria','Não ','Erro','Voltar','Enviar','Buscar','Cancelar','Fechar','Ações','Início','Relatório','Senha','Endereço','Cliente','Vendedor','Recusad','Aprovad')

$out = [System.Collections.Generic.List[string]]::new()
foreach ($gf in (Get-ChildItem "$root\scripts\keys_group_*.json")) {
    $data = Get-Content $gf.FullName -Raw | ConvertFrom-Json
    $count = 0
    foreach ($item in $data) {
        $en = [string]$item.en
        $isPt = $false
        foreach ($m in $ptMarkers) { if ($en.Contains($m)) { $isPt = $true; break } }
        if (-not $isPt) {
            foreach ($w in $ptWords) { if ($en -match [regex]::Escape($w)) { $isPt = $true; break } }
        }
        if ($isPt) { $count++; $out.Add(("{0}|{1}|{2}" -f $item.key, $en, [string]$item.pt)) }
    }
    Write-Output ("{0}: {1} valor(es) EN suspeito(s) de PT" -f $gf.Name, $count)
}
# Salvar lista para reprocessar
$out | Out-File -FilePath "$root\scripts\untranslated_en.txt" -Encoding UTF8
Write-Output ("TOTAL suspeitos: {0}" -f $out.Count)
