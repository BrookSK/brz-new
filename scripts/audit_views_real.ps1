<#
Audita Views buscando texto PT HARDCODED fora de chamadas __().
Remove os fallbacks __('chave','PT') antes de contar, para nao gerar falso positivo.
Grava relatorio em views_real.txt
#>
$ErrorActionPreference = 'Stop'
$root = Split-Path -Parent $PSScriptRoot
$enc = New-Object System.Text.UTF8Encoding($false)
$dirs = @("$root\app\Views\admin","$root\app\Views\partials","$root\app\Views\layouts")

# Palavras PT fortes (indicadores de texto de UI em portugues)
$ptWords = @(
 'Salvar','Excluir','Editar','Voltar','Enviar','Buscar','Cancelar','Fechar','Adicionar','Remover','Pesquisar','Atualizar',
 'Pendente','Enviado','Entregue','Pedido','Produto','Categoria','Cliente','Vendedor','Senha','Estoque','Compra','Compras',
 'Nenhum','Nenhuma','Selecione','Selecionar','Aprovar','Reprovar','Ativo','Inativo','Faltando','Imprimir','Baixar','Registrar',
 'Nome','Valor','Quantidade','Peso','Frete','Data','Status','Descricao','Configura','Relatorio','Endereco','Comissao','Acoes',
 'Todos','Todas','Detalhes','Total','Gerar','Criar','Novo','Nova','Filtrar','Limpar','Mensagem','Arquivar','Arquivados',
 'Pagamento','Entrega','Situacao','Tipo','Loja','Prioridade','Confirmar','Excluir','Alterar','Visualizar')

$results = [System.Collections.Generic.List[object]]::new()
foreach ($dir in $dirs) {
    if (-not (Test-Path $dir)) { continue }
    foreach ($f in (Get-ChildItem $dir -Recurse -Filter *.php -File)) {
        $content = [System.IO.File]::ReadAllText($f.FullName, [System.Text.Encoding]::UTF8)

        # 1) Remover chamadas __('...','...') e __("...","...") inteiras (com possivel 3o arg)
        $stripped = [regex]::Replace($content, "__\(\s*['""][^'""]*['""]\s*,\s*['""][^'""]*['""]", ' ')
        # 2) Remover comentarios PHP de linha // ... e blocos /* */
        $stripped = [regex]::Replace($stripped, "//[^\r\n]*", ' ')
        $stripped = [regex]::Replace($stripped, "/\*[\s\S]*?\*/", ' ')

        $i18n = ([regex]::Matches($content, "__\(\s*['""]")).Count
        $hits = 0
        $sample = @()
        foreach ($w in $ptWords) {
            $m = [regex]::Matches($stripped, "\b" + [regex]::Escape($w) + "\b")
            if ($m.Count -gt 0) { $hits += $m.Count; if ($sample.Count -lt 6) { $sample += $w } }
        }
        if ($hits -gt 2) {
            $rel = $f.FullName.Substring($root.Length + 1)
            $results.Add([PSCustomObject]@{ file = $rel; realPt = $hits; i18n = $i18n; sample = ($sample -join ',') })
        }
    }
}
$sorted = $results | Sort-Object -Property @{Expression='i18n';Descending=$false}, @{Expression='realPt';Descending=$true}
$out = @()
$out += ("Views com PT REAL (hardcoded, fora de __): {0}" -f $sorted.Count)
$out += "--- Ordenadas por i18n ASC (i18n=0 = nunca traduzidas) ---"
foreach ($r in $sorted) { $out += ("{0}  realPt={1} i18n={2}  [{3}]" -f $r.file, $r.realPt, $r.i18n, $r.sample) }
[System.IO.File]::WriteAllLines((Join-Path $root 'views_real.txt'), $out, $enc)
Write-Output ("done: {0} views" -f $sorted.Count)
