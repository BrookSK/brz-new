<?php
namespace App\Services;

use Config\Database;

/**
 * CopilotoService — Core do Co-Piloto Braziliana (100% PHP)
 * Substitui completamente o backend Node.js
 * Chama Claude via cURL, monta prompt, gerencia base de conhecimento
 */
class CopilotoService {

    private \PDO $pdo;
    private array $configs = [];

    // Modelo obrigatório — NUNCA substituir
    private const MODELO = 'claude-sonnet-4-5-20250929';

    // Faixas de peso reais
    private const FAIXAS_KG = [1,2,3,4,5,6,7,8,9,10,15,20,25,30];
    private const TAXA_POR_KG = 39;

    private const MAPA_IMPOSTO_LOCAL = [
        'bath-and-body-works' => 8, 'walmart' => 8, 'trader-joes' => 8,
        'bjs' => 8, 'achados-e-favoritos-da-fabi' => 8,
        'costco' => 0, 'sams-club' => 0, 'desapegos-braziliana' => 0,
    ];

    private const MAPA_GRUPO_LOJA = [
        'bath-and-body-works' => 'Bath & Body Works', 'costco' => 'Costco',
        'walmart' => 'Walmart', 'sams-club' => "Sam's Club",
        'trader-joes' => "Trader Joe's", 'bjs' => "BJ's",
        'desapegos-braziliana' => 'Desapegos Braziliana',
        'achados-e-favoritos-da-fabi' => 'Achados e Favoritos da Fabi',
    ];

    // Cache de base de conhecimento (em memória por request)
    private static array $cacheDocumentos = [];

    public function __construct() {
        $this->pdo = Database::getConnection();
        $this->configs = $this->carregarConfigs();
    }

    // ========== CLAUDE API (cURL) ==========

    public function chamarClaude(string $mensagem, array $contexto, array $historico): array {
        $apiKey = $this->configs['api_key_claude'] ?? '';
        if (empty($apiKey)) {
            return ['texto' => 'O Co-Piloto ainda não está configurado. Peça ao administrador para inserir a API Key.', 'acao' => 'nenhuma', 'parametros' => []];
        }

        // Busca automática de produtos no banco quando a mensagem parece ser sobre um produto
        $resultadoBusca = $this->buscarProdutoNoBanco($mensagem);
        if (!empty($resultadoBusca)) {
            $contexto['_produtos_encontrados'] = $resultadoBusca;
        }

        $systemPrompt = $this->montarSystemPrompt($contexto, $mensagem);

        // Montar mensagens para o Claude
        $messages = [];
        $maxHist = (int) ($this->configs['max_historico_enviado'] ?? 10);
        $histSlice = array_slice($historico, -$maxHist);
        foreach ($histSlice as $m) {
            $role = ($m['role'] ?? 'user') === 'assistant' ? 'assistant' : 'user';
            $messages[] = ['role' => $role, 'content' => (string) ($m['content'] ?? '')];
        }
        $messages[] = ['role' => 'user', 'content' => $mensagem];

        $timeout = (int) ($this->configs['timeout_claude_ms'] ?? 15000);

        $payload = [
            'model' => self::MODELO,
            'max_tokens' => 1200,
            'system' => $systemPrompt,
            'messages' => $messages,
        ];

        $ch = curl_init('https://api.anthropic.com/v1/messages');
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'x-api-key: ' . $apiKey,
                'anthropic-version: 2023-06-01',
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT_MS => $timeout,
            CURLOPT_CONNECTTIMEOUT => 10,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError || $httpCode !== 200) {
            $errorDetail = '';
            if ($response) {
                $errData = json_decode($response, true);
                $errorDetail = $errData['error']['message'] ?? substr($response, 0, 300);
            }
            error_log("[CoPiloto] Claude API erro: HTTP $httpCode — curl: $curlError — resposta: $errorDetail");
            return [
                'texto' => 'Desculpa, tive um problema técnico. Tenta de novo em alguns segundos?',
                'acao' => 'nenhuma', 'parametros' => [],
            ];
        }

        $data = json_decode($response, true);
        $textoResposta = trim($data['content'][0]['text'] ?? '');
        $tokensUsados = ($data['usage']['input_tokens'] ?? 0) + ($data['usage']['output_tokens'] ?? 0);

        // Tentar parsear JSON da resposta
        $parsed = null;
        if (preg_match('/\{[\s\S]*\}/u', $textoResposta, $match)) {
            $parsed = @json_decode($match[0], true);
        }

        $resultado = [
            'texto' => $parsed['texto'] ?? $textoResposta,
            'acao' => $parsed['acao'] ?? 'nenhuma',
            'parametros' => $parsed['parametros'] ?? [],
            'requer_confirmacao' => $parsed['requer_confirmacao'] ?? false,
            'mensagem_confirmacao' => $parsed['mensagem_confirmacao'] ?? null,
            'max_tentativas_problema' => $parsed['max_tentativas_problema'] ?? null,
            'oferecer_ticket' => $parsed['oferecer_ticket'] ?? false,
            'sugestao_valor' => $parsed['sugestao_valor'] ?? null,
            'aprendizado' => $parsed['aprendizado'] ?? null,
            'tokens_usados' => $tokensUsados,
        ];

        // Salvar log
        $this->salvarLog($mensagem, $resultado, $contexto);

        // Processar aprendizado
        if (!empty($resultado['aprendizado']['gerar_pendencia'])) {
            $this->salvarAprendizado($resultado['aprendizado'], $mensagem, $resultado['texto'], $contexto);
        }

        return $resultado;
    }

    // ========== SYSTEM PROMPT (montagem dinâmica) ==========

    private function montarSystemPrompt(array $contexto, string $mensagemUsuario): string {
        $docs = $this->obterBaseConhecimento();

        // Cálculo do produto atual
        $calculoProduto = '';
        $precoUsd = (float) ($contexto['produto_preco_usd'] ?? 0);
        $pesoKg = (float) ($contexto['produto_peso_kg'] ?? 0);
        $impostoLocal = (float) ($contexto['imposto_local_pct'] ?? 0);
        if ($precoUsd > 0 && $pesoKg > 0) {
            $calc = $this->calcularCustoTotal($precoUsd, $pesoKg, $impostoLocal);
            $calculoProduto = "
CÁLCULO DO PRODUTO ATUAL (já feito):
Produto: US\$ {$calc['produto_usd']}
Imposto local EUA: US\$ {$calc['imposto_local_usd']}
Taxa de serviço: US\$ {$calc['taxa_servico_usd']} (faixa {$calc['faixa_kg']}kg × \$39)
ICMS (60%): US\$ {$calc['icms_usd']}
IPI (20%): US\$ {$calc['ipi_usd']}
TOTAL: US\$ {$calc['total_usd']} ≈ R\$ {$calc['total_brl']}
Espaço restante na faixa: {$calc['espaco_restante_kg']}kg";
        }

        // Buscar conteúdo de referência relevante
        $conteudoRef = $this->buscarConteudoRelevante($mensagemUsuario);

        // Produtos encontrados no banco (busca automática)
        $secaoProdutosEncontrados = '';
        if (!empty($contexto['_produtos_encontrados'])) {
            $prods = $contexto['_produtos_encontrados'];
            $linhas = [];
            $temClubeOnly = false;
            $usuarioTemClube = false;
            foreach ($prods as $p) {
                $linha = "- ID:{$p['id']} | {$p['nome']} | US\$ " . number_format((float)($p['preco'] ?? 0), 2);
                if (!empty($p['peso'])) $linha .= " | {$p['peso']}kg";
                if (!empty($p['grupo_nome'])) $linha .= " | Grupo: {$p['grupo_nome']} (/grupo/{$p['grupo_slug']})";
                if (!empty($p['clube_only'])) {
                    $linha .= " | ⚠️ GRUPO EXCLUSIVO CLUBE";
                    $temClubeOnly = true;
                }
                if (!empty($p['produto_clube_ativo'])) {
                    $linha .= " | ⚠️ PRODUTO EXCLUSIVO CLUBE";
                    $temClubeOnly = true;
                }
                if (!empty($p['acesso_restrito'])) {
                    $linha .= " | ❌ USUÁRIO NÃO TEM CLUBE ATIVO";
                }
                if (!empty($p['sem_estoque'])) {
                    $linha .= " | ❌ SEM ESTOQUE — NÃO PODE ADICIONAR AO CARRINHO";
                }
                $linhas[] = $linha;
                if (!empty($p['usuario_tem_clube'])) $usuarioTemClube = true;
            }
            $instrucaoClube = '';
            if ($temClubeOnly) {
                if ($usuarioTemClube) {
                    $instrucaoClube = "\nNOTA: Alguns produtos são exclusivos do Clube Braziliana. O usuário TEM clube ativo, então pode acessar normalmente.";
                } else {
                    $instrucaoClube = "\nNOTA: Alguns produtos são exclusivos do Clube Braziliana. O usuário NÃO tem clube ativo. Informe que esses produtos são exclusivos para membros do Clube e ofereça explicar como ativar o Clube (acao: ir_para_clube).";
                }
            }
            $secaoProdutosEncontrados = "\n\nPRODUTOS ENCONTRADOS NO BANCO DE DADOS PARA ESTA PERGUNTA:\n" . implode("\n", $linhas) .
                "\n\nIMPORTANTE: Estes produtos EXISTEM no sistema. Informe ao cliente que encontrou e em qual grupo de compras estão." .
                "\nPara adicionar ao carrinho, use acao: adicionar_carrinho com parametros: {\"produto_id\": <ID_NUMERICO>, \"quantidade\": N}" .
                "\nO produto_id é o número após 'ID:' na lista acima. NUNCA omita o produto_id." .
                "\nREGRAS DE PRODUTOS:" .
                "\n- Produtos marcados ❌ SEM ESTOQUE: informe que está indisponível. NÃO tente adicionar ao carrinho." .
                "\n- Produtos marcados ⚠️ EXCLUSIVO CLUBE: informe que é exclusivo para membros do Clube e ofereça explicar como ativar." .
                "\n- Produtos marcados ❌ USUÁRIO NÃO TEM CLUBE: o usuário não pode comprar. Ofereça acao: ir_para_clube." .
                "\n- NUNCA mencione informações sobre oferta gratuita ou elegibilidade para brinde — isso é surpresa para o cliente." .
                "\nPara levar ao grupo, use acao: ir_para_grupo com parametros: {\"slug\": \"nome-do-grupo\"}" .
                $instrucaoClube;
        }
        $secaoReferencia = '';
        if (!empty($conteudoRef)) {
            $trechos = '';
            foreach ($conteudoRef as $i => $c) {
                $n = $i + 1;
                $trechos .= "[{$n}] {$c['titulo']} — {$c['categoria']}\n" . mb_substr($c['texto'], 0, 1500) . "\n\n";
            }
            $secaoReferencia = "
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
REFERÊNCIAS TÉCNICAS APLICÁVEIS A ESTA CONVERSA
(Use para embasar sua abordagem — não cite os títulos ao cliente)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
{$trechos}
INSTRUÇÃO: Use o conhecimento acima para calibrar tom e argumentação. Nunca mencione referências.
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━";
        }

        // Carrinho
        $carrinhoTexto = 'Vazio ou não disponível';
        if (!empty($contexto['carrinho_itens']) && is_array($contexto['carrinho_itens'])) {
            $linhas = [];
            foreach ($contexto['carrinho_itens'] as $item) {
                $linhas[] = "- {$item['nome']}: US\$ {$item['preco']} × {$item['quantidade']}";
            }
            $carrinhoTexto = implode("\n", $linhas);
        }
        $subtotalVisivel = $contexto['carrinho_subtotal_visivel'] ?? null;
        $totalVisivel = $contexto['carrinho_total_visivel'] ?? null;
        $subtotal = !empty($subtotalVisivel) ? $subtotalVisivel : (!empty($contexto['carrinho_subtotal']) ? "US\$ {$contexto['carrinho_subtotal']}" : 'N/A');
        $totalCarrinho = !empty($totalVisivel) ? $totalVisivel : '';
        $logado = !empty($contexto['usuario_logado']) ? 'Sim' : 'Não';
        $nomeUsuario = $contexto['usuario_nome'] ?? 'Visitante';
        $moeda = $contexto['moeda_atual'] ?? 'BRL';
        $cambio = (float) ($this->configs['cambio_usd_brl'] ?? 5.80);
        $cambioStr = number_format($cambio, 2, '.', '');
        $pagina = $contexto['pagina'] ?? 'desconhecida';
        $url = $contexto['url_atual'] ?? '';
        $produtoNome = $contexto['produto_nome'] ?? 'nenhum';
        $produtoId = $contexto['produto_id'] ?? '';
        $grupo = $contexto['produto_grupo'] ?? 'nenhum';
        $dataAtual = date('d/m/Y H:i');

        return <<<PROMPT
IDENTIDADE:
Você é a Bri, copiloto de compras da Braziliana.
Você não apenas responde — você age.
Quando o usuário pede algo que pode ser feito, você instrui o sistema a fazer.
Tom: direto, informal, português brasileiro. Nunca robótico.

AÇÕES QUE VOCÊ PODE INSTRUIR O SISTEMA A EXECUTAR:
- adicionar_carrinho: adiciona produto ao carrinho do usuário. OBRIGATÓRIO: parametros.produto_id (número inteiro). Sem produto_id a ação FALHA.
- limpar_carrinho: remove todos os itens do carrinho do usuário
- trocar_moeda_brl: muda exibição do site para Real (navega para /lang/pt)
- trocar_moeda_usd: muda exibição do site para Dólar (navega para /lang/en)
- consultar_status_pedido: consulta status via API e exibe resultado NO CHAT
- abrir_whatsapp_vendas: abre WhatsApp de vendas (+55 17 99620-3062)
- ir_para_checkout: navega para /checkout
- ir_para_contato: navega para /contato com campos pré-preenchidos
- ir_para_clube: navega para /clube/recarga
- ir_para_meus_dados: navega para /meus-dados
- buscar_produto: navega para /produtos?busca=termo
- ir_para_grupo: navega para /grupo/:slug
- criar_ticket_suporte: abre ticket na categoria "suporte"
- criar_ticket_duvida: abre ticket na categoria "duvidas_gerais"
- verificar_cancelamento: verifica elegibilidade de cancelamento
- solicitar_cancelamento: solicita cancelamento (requer confirmação)
- nenhuma: apenas responder no chat

REGRAS:
1. Para cada resposta, indique qual ação o sistema deve executar
2. Calcule sempre — nunca diga "depende" sem fazer a conta
3. Para problemas após tentativas esgotadas → criar_ticket_suporte ou criar_ticket_duvida
4. Para finalizar compra → instrua ir_para_checkout COM resumo no chat antes
5. Nunca invente produtos — use apenas os fornecidos no contexto
6. Para status de pedido → sempre consultar_status_pedido (exibe no chat, nunca navega)
7. NUNCA ofereça WhatsApp como canal de suporte — suporte vai EXCLUSIVAMENTE via ticket

CANCELAMENTO:
Taxa fixa de US\$ 100. Impossível após despacho. Fluxo: informar regras → pedir número → verificar → confirmar.

FORMATO DE RESPOSTA — JSON OBRIGATÓRIO:
{"texto":"resposta","acao":"nome_ou_nenhuma","parametros":{},"requer_confirmacao":false,"mensagem_confirmacao":null,"max_tentativas_problema":null,"oferecer_ticket":false,"sugestao_valor":null,"aprendizado":{"gerar_pendencia":false,"tipos":[],"resumo_problema":null,"impacto_estimado":null,"documento_afetado":null,"topico_afetado":null,"texto_sugerido":null,"justificativa_juridica":null,"etapa_processo_falhou":null,"sugestao_processo":null,"area_responsavel":null}}

CONTEXTO DA PÁGINA:
Página atual: {$pagina}
URL: {$url}
Produto em tela: {$produtoNome} {$produtoId}
Grupo em tela: {$grupo}

CARRINHO ATUAL:
{$carrinhoTexto}
Subtotal (como o usuário vê na tela): {$subtotal}
Total (como o usuário vê na tela): {$totalCarrinho}

USUÁRIO:
Logado: {$logado}
Nome: {$nomeUsuario}
Moeda atual: {$moeda}

REGRA DE MOEDA OBRIGATÓRIA:
O usuário está vendo o site em {$moeda}.
- Se moeda = BRL: SEMPRE responda valores em Reais (R$). Converta USD para BRL usando câmbio {$cambioStr}. Exemplo: "R$ 251,49" e não "US$ 42.99".
- Se moeda = USD: responda em Dólar (US$).
- Quando mostrar valores do carrinho, use os valores que o usuário VÊ na tela, não os valores internos em USD.
- Use formato brasileiro para BRL: R$ 1.234,56 (ponto para milhar, vírgula para decimal).

{$calculoProduto}

REGRAS DO NEGÓCIO:
TAXA DE SERVIÇO: US\$ 39/kg, faixas: 1,2,3,4,5,6,7,8,9,10,15,20,25,30 kg. Frete GRÁTIS.
IMPOSTOS BRASIL: ICMS 60% + IPI 20% = 80% sobre valor do produto.
IMPOSTO LOCAL EUA: 8% em BBW, Walmart, Trader Joe's, BJ's, Achados. 0% em Costco, Sam's, Desapegos.
MOEDAS: BRL (PIX ou cartão 12x via AppMax) / USD (Stripe, Zelle, Venmo).
PRAZO: 15-30 dias. LIMITES: 30kg e US\$ 2.999,99/caixa.
CLUBE: Depósito mín US\$ 39. Normal (imediato) ou Turbo (6 meses).
CANCELAMENTO: Taxa fixa US\$ 100. Impossível após despacho.
CONTATO: WhatsApp Vendas +55 17 99620-3062 / Suporte APENAS via ticket.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
BASE DE CONHECIMENTO — VERSÃO ATUAL DO SITE ({$dataAtual})
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
=== COMO FUNCIONA ===
{$docs['como_funciona']}

=== TERMOS E CONDIÇÕES ===
{$docs['termos_uso']}

=== POLÍTICA DE PRIVACIDADE ===
{$docs['politica_privacidade']}

=== CLUBE BRAZILIANA ===
{$docs['como_funciona_clube']}
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Se um documento estiver indisponível, diga ao usuário e ofereça verificar mais tarde.
Se um campo vier null, reformule a resposta sem ele.
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
{$secaoProdutosEncontrados}
{$secaoReferencia}
PROMPT;
    }

    // ========== BUSCA DE PRODUTOS NO BANCO ==========

    private function buscarProdutoNoBanco(string $mensagem): array {
        // Mapa de tradução PT→EN para termos comuns de produtos
        $traducoes = [
            'detergente' => 'dish soap', 'amaciante' => 'downy,fabric softener',
            'sabão em pó' => 'laundry detergent,tide', 'sabao em po' => 'laundry detergent,tide',
            'desinfetante' => 'lysol,clorox,disinfectant', 'limpeza' => 'cleaning,clean,wipe',
            'shampoo' => 'shampoo', 'condicionador' => 'conditioner',
            'creme' => 'cream,lotion,moisturizer', 'protetor solar' => 'sunscreen',
            'pasta de dente' => 'toothpaste', 'escova de dente' => 'toothbrush',
            'sabonete' => 'soap,body wash', 'desodorante' => 'deodorant',
            'perfume' => 'perfume,fragrance,cologne', 'hidratante' => 'moisturizer,lotion',
            'panela' => 'cookware,pan,pot', 'aspirador' => 'vacuum',
            'vitamina' => 'vitamin', 'suplemento' => 'supplement',
            'fralda' => 'diaper', 'lenço' => 'wipe,tissue',
            'papel toalha' => 'paper towel', 'papel higiênico' => 'toilet paper',
            'café' => 'coffee', 'chá' => 'tea',
            'biscoito' => 'cookie,cracker', 'chocolate' => 'chocolate',
            'cereal' => 'cereal,granola', 'tempero' => 'seasoning,spice',
            'molho' => 'sauce', 'azeite' => 'olive oil',
            'produto de cabelo' => 'shampoo,conditioner,hair',
            'produto de limpeza' => 'cleaning,clean,wipe,lysol,clorox',
            'produto de bebe' => 'baby', 'produto de bebê' => 'baby',
            'roupa' => 'laundry,clothes', 'pele' => 'skin,lotion,cream',
        ];

        // Detectar se a mensagem parece ser sobre busca de produto
        $padroes = [
            '/(?:adiciona|coloca|bota|põe|quero)\s+(?:o|a|um|uma|esse|essa|aquele|aquela|qualquer|algum|2|3|4|5|6)?\s*(.{3,80}?)(?:\s+no\s+(?:meu\s+)?carrinho|\s+pra\s+mim|\s+por\s+favor|$)/iu',
            '/tem\s+(?:o|a|um|uma|algum)?\s*(.{3,40})\??/iu',
            '/(?:o que tem de|o que temos de|quais?|mostra|lista)\s+(.{3,40})\??/iu',
            '/(?:procur|busc|quer|precis)\w*\s+(?:o|a|um|uma|de)?\s*(.{3,40})/iu',
            '/(?:vende|vendem)\s+(.{3,40})\??/iu',
        ];

        $termo = null;
        foreach ($padroes as $padrao) {
            if (preg_match($padrao, $mensagem, $m)) {
                $termo = isset($m[1]) ? trim($m[1], ' ?.!,') : trim($m[0], ' ?.!,');
                break;
            }
        }

        if (!$termo || mb_strlen($termo) < 2) return [];

        // Traduzir termos em português para inglês (produtos cadastrados em EN)
        $termoOriginal = $termo;
        $termosParaBuscar = [$termo];
        $termoLower = mb_strtolower($termo);
        foreach ($traducoes as $pt => $en) {
            if (mb_stripos($termoLower, $pt) !== false) {
                foreach (explode(',', $en) as $t) {
                    $termosParaBuscar[] = trim($t);
                }
            }
        }
        // Remover duplicatas
        $termosParaBuscar = array_unique(array_filter($termosParaBuscar));

        try {
            $cols = [];
            try {
                $stCols = $this->pdo->query('DESCRIBE produtos');
                $cols = $stCols ? $stCols->fetchAll(\PDO::FETCH_COLUMN) : [];
            } catch (\Exception $e) { return []; }

            $colNome = in_array('name', $cols, true) ? 'name' : 'nome';
            $colPreco = in_array('price', $cols, true) ? 'price' : 'preco';
            $colPeso = in_array('weight', $cols, true) ? 'weight' : 'peso';
            $temGrupo = in_array('grupo_compras_id', $cols, true);
            $temOculto = in_array('oculto', $cols, true);
            $temActive = in_array('active', $cols, true);
            $temAtivo = in_array('ativo', $cols, true);
            $temStatus = in_array('status', $cols, true);
            $temClubeAtivo = in_array('clube_ativo', $cols, true);
            $temStock = in_array('stock', $cols, true);

            $like = '%' . $termo . '%';

            // Montar LIKE para múltiplos termos (original + traduções)
            $likeClauses = [];
            $likeParams = [];
            foreach ($termosParaBuscar as $t) {
                $likeClauses[] = "p.{$colNome} LIKE ?";
                $likeParams[] = '%' . trim($t) . '%';
            }
            $likeSQL = '(' . implode(' OR ', $likeClauses) . ')';

            // Montar filtros de visibilidade (mesma lógica do Produto model)
            $filtros = [];
            if ($temStatus) {
                $filtros[] = "(p.status IS NULL OR LOWER(COALESCE(p.status,'')) IN ('published','publish','publicado','ativo','active'))";
            }
            if ($temActive) {
                $filtros[] = "(p.active = 1 OR LOWER(COALESCE(p.active,'')) IN ('true','yes','sim','ativo','active'))";
            } elseif ($temAtivo) {
                $filtros[] = "(p.ativo = 1 OR LOWER(COALESCE(p.ativo,'')) IN ('true','yes','sim','ativo','active'))";
            }
            if ($temOculto) {
                $filtros[] = "(p.oculto IS NULL OR p.oculto = 0)";
            }
            $filtroSQL = !empty($filtros) ? ' AND ' . implode(' AND ', $filtros) : '';

            // Selects extras
            $selectExtra = '';
            if ($temClubeAtivo) $selectExtra .= ', COALESCE(p.clube_ativo, 0) as produto_clube_ativo';
            if ($temStock) $selectExtra .= ', p.stock';

            if ($temGrupo) {
                $st = $this->pdo->prepare("SELECT p.id, p.{$colNome} AS nome, p.{$colPreco} AS preco, p.{$colPeso} AS peso,
                    COALESCE(gc.nome, '') as grupo_nome, COALESCE(gc.slug, '') as grupo_slug,
                    COALESCE(gc.clube_only, 0) as clube_only
                    {$selectExtra}
                    FROM produtos p
                    LEFT JOIN grupos_compras gc ON gc.id = p.grupo_compras_id
                    WHERE {$likeSQL}{$filtroSQL}
                    ORDER BY p.{$colNome} ASC LIMIT 8");
            } else {
                $st = $this->pdo->prepare("SELECT p.id, p.{$colNome} AS nome, p.{$colPreco} AS preco, p.{$colPeso} AS peso,
                    '' as grupo_nome, '' as grupo_slug, 0 as clube_only
                    {$selectExtra}
                    FROM produtos p
                    WHERE {$likeSQL}{$filtroSQL}
                    ORDER BY p.{$colNome} ASC LIMIT 8");
            }
            $st->execute($likeParams);
            $produtos = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Verificar se o usuário tem clube ativo
            $usuarioClubeAtivo = false;
            if (session_status() === PHP_SESSION_NONE) @session_start();
            $userId = (int) ($_SESSION['usuario_id'] ?? 0);
            if ($userId > 0) {
                try {
                    $stClube = $this->pdo->prepare("SELECT COALESCE(SUM(valor), 0) as total 
                        FROM carteira_recargas 
                        WHERE usuario_id = ? 
                          AND LOWER(COALESCE(status,'')) IN ('paid','approved','credited')");
                    $stClube->execute([$userId]);
                    $saldoClube = (float) ($stClube->fetchColumn() ?: 0);
                    $usuarioClubeAtivo = ($saldoClube > 0);
                } catch (\Exception $e) {}
            }

            // Marcar produtos com regras de visibilidade
            foreach ($produtos as &$p) {
                $p['clube_only'] = (int) ($p['clube_only'] ?? 0);
                $p['usuario_tem_clube'] = $usuarioClubeAtivo;
                $p['produto_clube_ativo'] = (int) ($p['produto_clube_ativo'] ?? 0);
                $p['stock'] = isset($p['stock']) ? (int) $p['stock'] : null;
                
                // Produto é de grupo exclusivo do clube E usuário não tem clube
                if ($p['clube_only'] && !$usuarioClubeAtivo) {
                    $p['acesso_restrito'] = true;
                // Produto individual marcado como clube_ativo E usuário não tem clube
                } elseif ($p['produto_clube_ativo'] && !$usuarioClubeAtivo) {
                    $p['acesso_restrito'] = true;
                } else {
                    $p['acesso_restrito'] = false;
                }
                
                // Sem estoque
                $p['sem_estoque'] = ($p['stock'] !== null && $p['stock'] <= 0);
            }
            unset($p);

            return $produtos;
        } catch (\Exception $e) {
            error_log('[CoPiloto] Erro busca produto no banco: ' . $e->getMessage());
            return [];
        }
    }

    // ========== CÁLCULO DE CUSTO (Recurso 4) ==========

    public function calcularCustoTotal(float $precoUsd, float $pesoKg, float $impostoLocalPct = 0): array {
        $faixaKg = 30;
        foreach (self::FAIXAS_KG as $f) {
            if ($f >= $pesoKg) { $faixaKg = $f; break; }
        }
        $taxaServico = $faixaKg * self::TAXA_POR_KG;
        $impostoLocal = $precoUsd * ($impostoLocalPct / 100);
        $icms = $precoUsd * 0.60;
        $ipi = $precoUsd * 0.20;
        $impostosBr = $icms + $ipi;
        $totalUsd = $precoUsd + $impostoLocal + $taxaServico + $impostosBr;
        $cambio = (float) ($this->configs['cambio_usd_brl'] ?? 5.80);
        $totalBrl = $totalUsd * $cambio;

        return [
            'produto_usd' => round($precoUsd, 2),
            'imposto_local_usd' => round($impostoLocal, 2),
            'taxa_servico_usd' => round($taxaServico, 2),
            'icms_usd' => round($icms, 2),
            'ipi_usd' => round($ipi, 2),
            'impostos_br_usd' => round($impostosBr, 2),
            'total_usd' => round($totalUsd, 2),
            'total_brl' => round($totalBrl, 2),
            'faixa_kg' => $faixaKg,
            'espaco_restante_kg' => round($faixaKg - $pesoKg, 2),
            'cambio_usado' => $cambio,
        ];
    }

    public function obterImpostoLocal(string $grupoSlug): int {
        return self::MAPA_IMPOSTO_LOCAL[$grupoSlug] ?? 0;
    }

    // ========== BASE DE CONHECIMENTO DINÂMICA (Recurso 9-B) ==========

    private function obterBaseConhecimento(): array {
        if (!empty(self::$cacheDocumentos)) return self::$cacheDocumentos;

        $fontes = [
            'como_funciona' => '/como-funciona',
            'termos_uso' => '/faq',
            'politica_privacidade' => '/politica-privacidade',
            'como_funciona_clube' => '/como-funciona-clube',
        ];

        // Tentar cache do banco (TTL 30 min)
        try {
            $st = $this->pdo->query("SELECT chave, valor FROM configuracoes_sistema WHERE chave = 'copiloto_cache_docs'");
            $row = $st->fetch(\PDO::FETCH_ASSOC);
            if ($row && !empty($row['valor'])) {
                $cache = json_decode($row['valor'], true);
                if ($cache && isset($cache['_ts']) && (time() - $cache['_ts']) < 1800) {
                    unset($cache['_ts']);
                    self::$cacheDocumentos = $cache;
                    return $cache;
                }
            }
        } catch (\Exception $e) {}

        $docs = [];
        $baseUrl = rtrim((string) ($_SERVER['REQUEST_SCHEME'] ?? 'https') . '://' . ($_SERVER['HTTP_HOST'] ?? 'brazilianashop.com.br'), '/');

        foreach ($fontes as $chave => $rota) {
            try {
                $ch = curl_init($baseUrl . $rota);
                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 8,
                    CURLOPT_FOLLOWLOCATION => true,
                    CURLOPT_USERAGENT => 'CopilotoBraziliana/1.0',
                ]);
                $html = curl_exec($ch);
                curl_close($ch);

                if ($html) {
                    // Extrair texto do conteúdo principal
                    $texto = $this->extrairTextoDoHtml($html);
                    $docs[$chave] = mb_substr($texto, 0, 6000);
                } else {
                    $docs[$chave] = '[documento temporariamente indisponível]';
                }
            } catch (\Exception $e) {
                $docs[$chave] = '[documento temporariamente indisponível]';
            }
        }

        // Salvar cache no banco
        try {
            $cacheData = $docs;
            $cacheData['_ts'] = time();
            $json = json_encode($cacheData, JSON_UNESCAPED_UNICODE);
            $st = $this->pdo->prepare("SELECT COUNT(*) FROM configuracoes_sistema WHERE chave = 'copiloto_cache_docs'");
            $st->execute();
            if ((int) $st->fetchColumn() > 0) {
                $this->pdo->prepare("UPDATE configuracoes_sistema SET valor = ? WHERE chave = 'copiloto_cache_docs'")->execute([$json]);
            } else {
                $this->pdo->prepare("INSERT INTO configuracoes_sistema (chave, valor) VALUES ('copiloto_cache_docs', ?)")->execute([$json]);
            }
        } catch (\Exception $e) {}

        self::$cacheDocumentos = $docs;
        return $docs;
    }

    private function extrairTextoDoHtml(string $html): string {
        // Remover scripts, styles, nav, footer
        $html = preg_replace('/<script[^>]*>[\s\S]*?<\/script>/i', '', $html);
        $html = preg_replace('/<style[^>]*>[\s\S]*?<\/style>/i', '', $html);
        $html = preg_replace('/<nav[^>]*>[\s\S]*?<\/nav>/i', '', $html);
        $html = preg_replace('/<footer[^>]*>[\s\S]*?<\/footer>/i', '', $html);
        $html = preg_replace('/<header[^>]*>[\s\S]*?<\/header>/i', '', $html);

        // Extrair conteúdo do main ou body
        if (preg_match('/<main[^>]*>([\s\S]*?)<\/main>/i', $html, $m)) {
            $html = $m[1];
        } elseif (preg_match('/<article[^>]*>([\s\S]*?)<\/article>/i', $html, $m)) {
            $html = $m[1];
        }

        $texto = strip_tags($html);
        $texto = html_entity_decode($texto, ENT_QUOTES, 'UTF-8');
        $texto = preg_replace('/\s+/', ' ', $texto);
        return trim($texto);
    }

    // ========== BUSCA SEMÂNTICA DE CONTEÚDO DE REFERÊNCIA ==========

    private function buscarConteudoRelevante(string $mensagem, int $topK = 3): array {
        try {
            $st = $this->pdo->query("SELECT COUNT(*) FROM copiloto_conteudo WHERE status = 'ativo' AND ativo = 1");
            if ((int) $st->fetchColumn() === 0) return [];

            // Extrair palavras-chave
            $stopwords = ['como','para','que','com','uma','por','mais','esse','essa','isso','aqui','tem','ser','ter','não','sim','meu','minha','quero','pode','qual','onde'];
            $palavras = array_filter(
                preg_split('/\s+/', mb_strtolower(preg_replace('/[^a-záàâãéèêíïóôõöúçñ0-9\s]/ui', ' ', $mensagem))),
                fn($p) => mb_strlen($p) > 3 && !in_array($p, $stopwords)
            );
            $palavras = array_slice(array_values($palavras), 0, 5);

            if (empty($palavras)) return [];

            $likeClauses = implode(' OR ', array_fill(0, count($palavras), 'cc.texto LIKE ?'));
            $likeParams = array_map(fn($p) => "%{$p}%", $palavras);
            $likeParams[] = $topK;

            $st = $this->pdo->prepare("
                SELECT cc.texto, c.titulo, c.categoria
                FROM copiloto_conteudo_chunks cc
                JOIN copiloto_conteudo c ON c.id = cc.conteudo_id
                WHERE c.status = 'ativo' AND c.ativo = 1 AND ({$likeClauses})
                LIMIT ?
            ");
            $st->execute($likeParams);
            return $st->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    // ========== LOGS ==========

    private function salvarLog(string $mensagemUsuario, array $resposta, array $contexto): void {
        try {
            $sessaoId = session_id() ?: ('anon_' . substr(md5($_SERVER['REMOTE_ADDR'] ?? ''), 0, 12));

            $this->pdo->prepare("INSERT INTO copiloto_mensagens (sessao_id, role, conteudo, contexto_pagina) VALUES (?, 'user', ?, ?)")
                ->execute([$sessaoId, $mensagemUsuario, json_encode(['pagina' => $contexto['pagina'] ?? '', 'url' => $contexto['url_atual'] ?? ''])]);

            $this->pdo->prepare("INSERT INTO copiloto_mensagens (sessao_id, role, conteudo, acao, parametros_acao, tokens_usados) VALUES (?, 'assistant', ?, ?, ?, ?)")
                ->execute([$sessaoId, $resposta['texto'], $resposta['acao'] ?? null, json_encode($resposta['parametros'] ?? null), (int) ($resposta['tokens_usados'] ?? 0)]);

            // Sessão
            $stCheck = $this->pdo->prepare("SELECT id FROM copiloto_sessoes WHERE sessao_id = ? LIMIT 1");
            $stCheck->execute([$sessaoId]);
            if ($stCheck->fetchColumn()) {
                $this->pdo->prepare("UPDATE copiloto_sessoes SET total_mensagens = total_mensagens + 1, ultima_interacao = NOW() WHERE sessao_id = ?")->execute([$sessaoId]);
            } else {
                $this->pdo->prepare("INSERT INTO copiloto_sessoes (sessao_id, usuario_id, pagina_origem, ip, total_mensagens) VALUES (?, ?, ?, ?, 1)")
                    ->execute([$sessaoId, $_SESSION['usuario_id'] ?? null, $contexto['url_atual'] ?? null, $_SERVER['REMOTE_ADDR'] ?? null]);
            }
        } catch (\Exception $e) {
            error_log('[CoPiloto] Erro salvando log: ' . $e->getMessage());
        }
    }

    private function salvarAprendizado(array $aprendizado, string $msgUsuario, string $respostaBri, array $contexto): void {
        try {
            $this->pdo->prepare("INSERT INTO copiloto_aprendizado 
                (tipos, resumo_problema, impacto_estimado, sessao_id, mensagem_usuario, resposta_bri, pagina_origem,
                 documento_afetado, topico_afetado, texto_sugerido, justificativa, etapa_falhou, sugestao_melhoria, area_responsavel)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)")
                ->execute([
                    json_encode($aprendizado['tipos'] ?? []),
                    $aprendizado['resumo_problema'] ?? 'Sem resumo',
                    $aprendizado['impacto_estimado'] ?? 'medio',
                    session_id() ?: null,
                    $msgUsuario, $respostaBri,
                    $contexto['url_atual'] ?? null,
                    $aprendizado['documento_afetado'] ?? null,
                    $aprendizado['topico_afetado'] ?? null,
                    $aprendizado['texto_sugerido'] ?? null,
                    $aprendizado['justificativa_juridica'] ?? null,
                    $aprendizado['etapa_processo_falhou'] ?? null,
                    $aprendizado['sugestao_processo'] ?? null,
                    $aprendizado['area_responsavel'] ?? null,
                ]);
        } catch (\Exception $e) {
            error_log('[CoPiloto] Erro salvando aprendizado: ' . $e->getMessage());
        }
    }

    // ========== HELPERS ==========

    private function carregarConfigs(): array {
        $configs = [];
        try {
            $st = $this->pdo->prepare("SELECT chave, valor FROM configuracoes_sistema WHERE chave LIKE 'copiloto_%'");
            $st->execute();
            while ($row = $st->fetch(\PDO::FETCH_ASSOC)) {
                $key = preg_replace('/^copiloto_/', '', $row['chave']);
                $configs[$key] = $row['valor'];
            }
        } catch (\Exception $e) {}
        return $configs;
    }

    public function getConfig(string $key, $default = null) {
        return $this->configs[$key] ?? $default;
    }

    // ========== CRON: Processar conteúdo pendente ==========

    public function processarConteudoPendente(): int {
        $st = $this->pdo->query("SELECT * FROM copiloto_conteudo WHERE status = 'processando' ORDER BY criado_em ASC LIMIT 3");
        $pendentes = $st->fetchAll(\PDO::FETCH_ASSOC);
        $processados = 0;

        foreach ($pendentes as $arq) {
            try {
                $fullPath = realpath(__DIR__ . '/../../public' . $arq['arquivo_path']);
                if (!$fullPath || !file_exists($fullPath)) {
                    $this->pdo->prepare("UPDATE copiloto_conteudo SET status = 'erro' WHERE id = ?")->execute([$arq['id']]);
                    continue;
                }

                $texto = '';
                $paginas = null;
                $tipo = $arq['arquivo_tipo'];

                if ($tipo === 'txt' || $tipo === 'md') {
                    $texto = file_get_contents($fullPath);
                } elseif ($tipo === 'pdf') {
                    // Extrair texto básico de PDF (sem dependência externa)
                    $texto = $this->extrairTextoPdfBasico($fullPath);
                } elseif ($tipo === 'docx') {
                    $texto = $this->extrairTextoDocx($fullPath);
                }

                if (mb_strlen(trim($texto)) < 50) {
                    $this->pdo->prepare("UPDATE copiloto_conteudo SET status = 'erro' WHERE id = ?")->execute([$arq['id']]);
                    continue;
                }

                // Chunking
                $chunks = $this->dividirEmChunks($texto);

                // Limpar chunks antigos
                $this->pdo->prepare("DELETE FROM copiloto_conteudo_chunks WHERE conteudo_id = ?")->execute([$arq['id']]);

                // Inserir chunks
                $stInsert = $this->pdo->prepare("INSERT INTO copiloto_conteudo_chunks (conteudo_id, chunk_index, texto) VALUES (?, ?, ?)");
                foreach ($chunks as $i => $chunk) {
                    $stInsert->execute([$arq['id'], $i, $chunk]);
                }

                $this->pdo->prepare("UPDATE copiloto_conteudo SET status = 'ativo', total_chunks = ?, total_paginas = ?, atualizado_em = NOW() WHERE id = ?")
                    ->execute([count($chunks), $paginas, $arq['id']]);

                $processados++;
            } catch (\Exception $e) {
                error_log('[CoPiloto] Erro processando conteúdo ID ' . $arq['id'] . ': ' . $e->getMessage());
                $this->pdo->prepare("UPDATE copiloto_conteudo SET status = 'erro' WHERE id = ?")->execute([$arq['id']]);
            }
        }
        return $processados;
    }

    private function dividirEmChunks(string $texto, int $chunkChars = 3200, int $overlapChars = 400): array {
        $texto = preg_replace('/\r\n/', "\n", $texto);
        $texto = preg_replace('/\n{3,}/', "\n\n", trim($texto));
        if (mb_strlen($texto) <= $chunkChars) return [$texto];

        $chunks = [];
        $inicio = 0;
        $len = mb_strlen($texto);

        while ($inicio < $len) {
            $fim = $inicio + $chunkChars;
            if ($fim < $len) {
                $trecho = mb_substr($texto, $inicio, $chunkChars + 200);
                $corteParagrafo = strrpos($trecho, "\n\n");
                $corteFrase = strrpos($trecho, '. ');
                if ($corteParagrafo !== false && $corteParagrafo > $chunkChars * 0.6) {
                    $fim = $inicio + $corteParagrafo + 2;
                } elseif ($corteFrase !== false && $corteFrase > $chunkChars * 0.6) {
                    $fim = $inicio + $corteFrase + 2;
                }
            }
            $chunk = trim(mb_substr($texto, $inicio, $fim - $inicio));
            if (mb_strlen($chunk) > 20) $chunks[] = $chunk;
            $inicio = $fim - $overlapChars;
            if ($inicio <= 0 && count($chunks) > 0) break;
        }
        return $chunks;
    }

    private function extrairTextoPdfBasico(string $path): string {
        // Extração básica de texto de PDF sem biblioteca externa
        $content = file_get_contents($path);
        $texto = '';

        // Tentar extrair streams de texto
        if (preg_match_all('/stream\s*(.*?)\s*endstream/s', $content, $matches)) {
            foreach ($matches[1] as $stream) {
                $decoded = @gzuncompress($stream);
                if ($decoded === false) $decoded = $stream;
                // Extrair texto entre parênteses (operador Tj/TJ)
                if (preg_match_all('/\(([^)]*)\)/', $decoded, $textMatches)) {
                    $texto .= implode(' ', $textMatches[1]) . "\n";
                }
                // Extrair texto de arrays TJ
                if (preg_match_all('/\[([^\]]*)\]\s*TJ/i', $decoded, $tjMatches)) {
                    foreach ($tjMatches[1] as $tj) {
                        if (preg_match_all('/\(([^)]*)\)/', $tj, $tjText)) {
                            $texto .= implode('', $tjText[1]) . ' ';
                        }
                    }
                }
            }
        }

        $texto = preg_replace('/[^\x20-\x7E\xC0-\xFF\n]/', '', $texto);
        $texto = preg_replace('/\s+/', ' ', $texto);
        return trim($texto);
    }

    private function extrairTextoDocx(string $path): string {
        // Extrair texto de DOCX (é um ZIP com XML dentro)
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) return '';

        $xml = $zip->getFromName('word/document.xml');
        $zip->close();
        if (!$xml) return '';

        // Remover tags XML e extrair texto
        $texto = strip_tags(str_replace(['<w:p ', '<w:p>', '</w:p>'], ["\n<w:p ", "\n", "\n"], $xml));
        $texto = html_entity_decode($texto, ENT_QUOTES, 'UTF-8');
        $texto = preg_replace('/\s+/', ' ', $texto);
        return trim($texto);
    }

    // ========== CRON: Limpeza ==========

    public function limparDadosAntigos(int $diasMensagens = 90, int $diasSessoes = 90): array {
        $resultado = ['mensagens' => 0, 'sessoes' => 0];
        try {
            $st = $this->pdo->prepare("DELETE FROM copiloto_mensagens WHERE criado_em < DATE_SUB(NOW(), INTERVAL ? DAY)");
            $st->execute([$diasMensagens]);
            $resultado['mensagens'] = $st->rowCount();

            $st = $this->pdo->prepare("DELETE FROM copiloto_sessoes WHERE ultima_interacao < DATE_SUB(NOW(), INTERVAL ? DAY)");
            $st->execute([$diasSessoes]);
            $resultado['sessoes'] = $st->rowCount();
        } catch (\Exception $e) {}
        return $resultado;
    }
}
