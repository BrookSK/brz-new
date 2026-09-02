$ErrorActionPreference = 'Stop'
$root = 'd:\GITHUB\brz-new'
$enFile = "$root\app\lang\en.php"

# Lista ampla de palavras PT (sem acento tambem) que nunca deveriam aparecer sozinhas num valor EN.
# Usadas com \b para casar palavra inteira, case-insensitive.
$ptWords = @(
 'Gerar','Revisar','Reprovado','Reprovados','Reprovada','Aprovar','Aprovado','Aprovados','Ativo','Inativo','Ativa','Inativa',
 'Salvar','Excluir','Editar','Voltar','Enviar','Buscar','Cancelar','Fechar','Adicionar','Remover','Pesquisar','Limpar','Atualizar',
 'Pendente','Pendentes','Concluido','Concluida','Enviado','Entregue','Cancelado','Cancelada','Pago','Recusado','Recusada',
 'Pedido','Pedidos','Usuario','Usuarios','Produto','Produtos','Categoria','Categorias','Cliente','Clientes','Vendedor','Vendedores',
 'Descricao','Descricoes','Configuracao','Configuracoes','Relatorio','Relatorios','Senha','Endereco','Estoque','Compra','Compras',
 'Nome','Preco','Valor','Data','Acao','Acoes','Erro','Sucesso','Mensagem','Observacao','Selecione','Selecionar',
 'Nenhum','Nenhuma','Todos','Todas','Novo','Nova','Sim','Nao','Registrar','Registrado','Imprimir','Baixar',
 'Comprado','Comprada','Etiqueta','Etiquetas','Remessa','Remessas','Fatura','Faturas','Frete','Peso','Quantidade','Total','Desconto',
 'Vendas','Venda','Comissao','Comissoes','Carteira','Saldo','Credito','Debito','Perfil','Titulo','Conteudo','Imagem','Imagens',
 'Ordem','Ativar','Desativar','Confirmar','Detalhes','Detalhe','Painel','Historico','Anterior','Proximo','Proxima','Pagina'
)
# Construir regex unica com bordas de palavra
$escaped = ($ptWords | ForEach-Object { [regex]::Escape($_) }) -join '|'
$pattern = "(?i)\b($escaped)\b"

$lines = [System.IO.File]::ReadAllLines($enFile, [System.Text.Encoding]::UTF8)
$out = [System.Collections.Generic.List[string]]::new()
foreach ($line in $lines) {
    if ($line -match "^\s*'([a-zA-Z0-9_.]+)'\s*=>\s*'(.*)',\s*$") {
        $key = $Matches[1]
        $val = $Matches[2]
        # Ignorar valores que sao claramente nomes proprios/tecnicos aceitos
        if ($val -match $pattern) {
            $out.Add(("{0}|{1}" -f $key, $val))
        }
    }
}
Write-Output ("Valores EN com palavra PT detectada: {0}" -f $out.Count)
$out | Out-File "$root\scripts\untranslated_en2.txt" -Encoding UTF8
