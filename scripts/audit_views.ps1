$ErrorActionPreference = 'Stop'
$root = 'd:\GITHUB\brz-new'
$dirs = @("$root\app\Views\admin","$root\app\Views\partials","$root\app\Views\layouts")

# Palavras/marcadores PT fortes (com e sem acento via mojibake)
$ptWords = @('ção','ções','Ã§Ã£o','Ãµes','Ã£','Ã©','Ã­','Ã¡','Ã³','Ãº',
 'Salvar','Excluir','Editar','Voltar','Enviar','Buscar','Cancelar','Fechar','Adicionar','Remover','Pesquisar','Atualizar',
 'Pendente','Concluíd','Concluid','Enviado','Entregue','Cancelad','Pedido','Usuário','Usuario','Produto','Categoria','Cliente',
 'Vendedor','Descrição','Descricao','Configura','Relatório','Relatorio','Senha','Endereço','Endereco','Estoque','Compra',
 'Nenhum','Nenhuma','Selecione','Selecionar','Voltar','Aprovar','Reprovar','Ativo','Inativo','Comissão','Comissao',
 'Faltando','Buscar','Imprimir','Baixar','Registrar','Nome','Preço','Valor','Quantidade','Peso','Frete','Total ','Ações','Acoes')

$results = [System.Collections.Generic.List[object]]::new()
foreach ($dir in $dirs) {
    if (-not (Test-Path $dir)) { continue }
    foreach ($f in (Get-ChildItem $dir -Recurse -Filter *.php -File)) {
        $content = [System.IO.File]::ReadAllText($f.FullName, [System.Text.Encoding]::UTF8)
        # Remover blocos PHP <?php ... ?> para nao contar codigo/comentarios? Nao: texto PT em HTML esta fora do PHP.
        # Contar ocorrencias de marcadores PT
        $hits = 0
        foreach ($w in $ptWords) {
            $m = [regex]::Matches($content, [regex]::Escape($w))
            $hits += $m.Count
        }
        # Contar quantos __() ja existem
        $i18n = ([regex]::Matches($content, "__\(\s*'")).Count
        if ($hits -gt 3) {
            $rel = $f.FullName.Substring($root.Length + 1)
            $results.Add([PSCustomObject]@{ file = $rel; ptHits = $hits; i18n = $i18n })
        }
    }
}
$sorted = $results | Sort-Object -Property ptHits -Descending
Write-Output ("Views com texto PT (>3 hits): {0}" -f $sorted.Count)
foreach ($r in $sorted) { Write-Output ("{0}  ptHits={1} i18n={2}" -f $r.file, $r.ptHits, $r.i18n) }
