$ErrorActionPreference = 'Stop'
$root = 'd:\GITHUB\brz-new'
$lines = [System.IO.File]::ReadAllLines("$root\scripts\untranslated_en2.txt", [System.Text.Encoding]::UTF8)

# Palavras que sao iguais/validas em ingles -> NAO indicam PT sozinhas
$falseFriends = @('Total','Data','Nome','Normal')  # Data/Total/Normal existem em EN; Nome tratamos a parte

# Palavras PT reais (fortes) - se aparecer qualquer uma, e PT de verdade
$strongPt = @(
 'Gerar','Revisar','Reprovad','Aprovar','Aprovad','Ativo','Inativo','Ativa','Inativa','Ativar','Desativar',
 'Salvar','Excluir','Editar','Voltar','Enviar','Buscar','Cancelar','Fechar','Adicionar','Remover','Pesquisar','Limpar','Atualizar',
 'Pendente','Concluid','Enviado','Entregue','Cancelad','Recusad','Selecione','Selecionar','Confirmar',
 'Usuario','Produto','Categoria','Cliente','Vendedor','Descricao','Configuracao','Relatorio','Senha','Endereco','Estoque','Compra',
 'Preco','Acao','Acoes','Nenhum','Nenhuma','Todos','Todas','Novo','Nova','Sim','Registrar','Registrad','Imprimir','Baixar',
 'Comprad','Etiqueta','Remessa','Fatura','Frete','Peso','Quantidade','Comissao','Carteira','Perfil','Titulo','Conteudo','Imagem',
 'Ordem','Detalhes','Historico','Anterior','Proxim','Pagina','Digite','Aguardando','Grupo','Loja','Lojas','Valor','Data da','Data de','Data compra','Opcional'
)
$escaped = ($strongPt | ForEach-Object { [regex]::Escape($_) }) -join '|'
$pattern = "(?i)($escaped)"

$out = [System.Collections.Generic.List[object]]::new()
foreach ($l in $lines) {
    if ($l.Trim() -eq '') { continue }
    $parts = $l -split '\|', 2
    if ($parts.Count -lt 2) { continue }
    $key = $parts[0]; $val = $parts[1]
    # Pular se contem URL http
    if ($val -match 'https?://') { continue }
    if ($val -match $pattern) {
        $out.Add([PSCustomObject]@{ key = $key; pt = $val })
    }
}
Write-Output ("PT reais para corrigir: {0}" -f $out.Count)
$out | ConvertTo-Json -Depth 3 | Out-File "$root\scripts\real_pt_fix_input.json" -Encoding UTF8
