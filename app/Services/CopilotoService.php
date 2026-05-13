<?php
namespace App\Services;

// Invalidar OPcache para garantir código atualizado (v3-variações-fix)
if (function_exists('opcache_invalidate')) { @opcache_invalidate(__FILE__, true); }

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

        // Incorporar aprendizados aceitos (pendências aprovadas pelo admin)
        $aprendizadosAceitos = $this->obterAprendizadosAceitos();
        if (!empty($aprendizadosAceitos)) {
            $docs['_aprendizados_ia'] = $aprendizadosAceitos;
        }

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
II (Imposto de Importação): US\$ {$calc['ii_usd']}
ICMS (17% por dentro): US\$ {$calc['icms_usd']}
Total impostos BR: US\$ {$calc['impostos_br_usd']}
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
                if (!empty($p['sem_estoque']) && empty($p['grupo'])) {
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
                "\n\nIMPORTANTE: Estes produtos EXISTEM no sistema. Use acao: buscar_produto com parametros.termo para que o carrossel visual apareça no chat." .
                "\nSua resposta em texto deve ser CURTA (ex: 'Encontrei! Temos Tineco sim! 🎉'). O carrossel com fotos e botões aparece automaticamente." .
                "\nNÃO liste os produtos em texto — o carrossel já mostra tudo visualmente." .
                "\nSe quiser complementar, adicione info sobre envio/impostos DEPOIS, mas mantenha curto." .
                "\nPara adicionar ao carrinho, use acao: adicionar_carrinho com parametros: {\"produto_id\": <ID_NUMERICO>, \"quantidade\": N}" .
                "\nO produto_id é o número após 'ID:' na lista acima. NUNCA omita o produto_id." .
                "\nREGRAS DE PRODUTOS:" .
                "\n- Produtos marcados ❌ SEM ESTOQUE: informe que está indisponível. NÃO tente adicionar ao carrinho." .
                "\n- Produtos de Grupo de Compras (campo 'Grupo:' na lista): NUNCA mencione estoque, quantidade disponível ou disponibilidade — esses produtos são comprados por demanda e não têm estoque fixo." .
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

        // Se carrinho_itens não veio do frontend, buscar do banco diretamente
        if (empty($contexto['carrinho_itens'])) {
            try {
                if (session_status() === PHP_SESSION_NONE) @session_start();
                
                // Pegar usuario_id de todas as formas possíveis
                $userId = (int) ($_SESSION['usuario_id'] ?? $_SESSION['user_id'] ?? $_SESSION['logado_id'] ?? 0);
                if ($userId <= 0) {
                    try {
                        $authSvc = new \App\Services\AuthService();
                        $u = $authSvc->getUsuarioLogado();
                        $userId = (int) ($u['id'] ?? 0);
                    } catch (\Throwable $e) {}
                }
                // Fallback: remember_token cookie
                if ($userId <= 0 && !empty($_COOKIE['remember_token'])) {
                    try {
                        $stU = $this->pdo->prepare("SELECT id FROM usuarios WHERE remember_token = ? LIMIT 1");
                        $stU->execute([$_COOKIE['remember_token']]);
                        $userId = (int) ($stU->fetchColumn() ?: 0);
                    } catch (\Throwable $e) {}
                }
                // Fallback: buscar usuario_id na sessão PHP armazenada no servidor
                if ($userId <= 0 && $sessionId) {
                    // Tentar ler a sessão do PHP diretamente do handler
                    try {
                        // Verificar se existe um carrinho com este session_id que tenha usuario_id
                        $stU = $this->pdo->prepare("SELECT usuario_id FROM carrinhos WHERE session_id = ? AND usuario_id > 0 ORDER BY updated_at DESC LIMIT 1");
                        $stU->execute([$sessionId]);
                        $userId = (int) ($stU->fetchColumn() ?: 0);
                    } catch (\Throwable $e) {}
                }
                // Fallback: buscar o carrinho mais recente com itens que tenha o mesmo IP (última hora)
                if ($userId <= 0) {
                    try {
                        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
                        if ($ip) {
                            // Buscar sessões recentes do copiloto com este IP que tenham usuario_id
                            $stU = $this->pdo->prepare("SELECT usuario_id FROM copiloto_sessoes WHERE ip = ? AND usuario_id IS NOT NULL AND usuario_id > 0 ORDER BY ultima_interacao DESC LIMIT 1");
                            $stU->execute([$ip]);
                            $userId = (int) ($stU->fetchColumn() ?: 0);
                        }
                    } catch (\Throwable $e) {}
                }

                $cartId = 0;

                // 1. Buscar por usuario_id
                if ($userId > 0) {
                    $st = $this->pdo->prepare('SELECT id FROM carrinhos WHERE usuario_id = ? ORDER BY updated_at DESC LIMIT 10');
                    $st->execute([$userId]);
                    $ids = $st->fetchAll(\PDO::FETCH_COLUMN) ?: [];
                    foreach ($ids as $cid) {
                        $stCnt = $this->pdo->prepare('SELECT COALESCE(SUM(quantidade),0) FROM carrinho_items WHERE carrinho_id = ?');
                        $stCnt->execute([(int)$cid]);
                        if ((int)$stCnt->fetchColumn() > 0) { $cartId = (int)$cid; break; }
                    }
                }

                // 2. Fallback: session_id
                if ($cartId <= 0) {
                    $sessionId = session_id() ?: null;
                    if ($sessionId) {
                        $st = $this->pdo->prepare('SELECT id FROM carrinhos WHERE session_id = ? ORDER BY updated_at DESC LIMIT 10');
                        $st->execute([$sessionId]);
                        $ids = $st->fetchAll(\PDO::FETCH_COLUMN) ?: [];
                        foreach ($ids as $cid) {
                            $stCnt = $this->pdo->prepare('SELECT COALESCE(SUM(quantidade),0) FROM carrinho_items WHERE carrinho_id = ?');
                            $stCnt->execute([(int)$cid]);
                            if ((int)$stCnt->fetchColumn() > 0) { $cartId = (int)$cid; break; }
                        }
                    }
                }

                // 3. Último fallback: buscar qualquer carrinho recente com itens (últimas 24h)
                // Isso cobre o caso onde o session_id do fetch é diferente do session_id do site
                if ($cartId <= 0 && $userId <= 0) {
                    // Tentar encontrar o usuario_id pelo session_id na tabela de sessões PHP ou carrinhos
                    try {
                        $stFindUser = $this->pdo->prepare("SELECT usuario_id FROM carrinhos WHERE session_id = ? AND usuario_id IS NOT NULL AND usuario_id > 0 ORDER BY updated_at DESC LIMIT 1");
                        $stFindUser->execute([$sessionId]);
                        $foundUserId = (int)($stFindUser->fetchColumn() ?: 0);
                        if ($foundUserId > 0) {
                            $userId = $foundUserId;
                            // Retry with found userId
                            $st = $this->pdo->prepare('SELECT id FROM carrinhos WHERE usuario_id = ? ORDER BY updated_at DESC LIMIT 10');
                            $st->execute([$userId]);
                            $ids = $st->fetchAll(\PDO::FETCH_COLUMN) ?: [];
                            foreach ($ids as $cid) {
                                $stCnt = $this->pdo->prepare('SELECT COALESCE(SUM(quantidade),0) FROM carrinho_items WHERE carrinho_id = ?');
                                $stCnt->execute([(int)$cid]);
                                if ((int)$stCnt->fetchColumn() > 0) { $cartId = (int)$cid; break; }
                            }
                        }
                    } catch (\Throwable $e) {}
                }

                if ($cartId > 0) {
                    $stItens = $this->pdo->prepare("SELECT ci.produto_id, ci.quantidade, ci.preco_unitario, p.name AS nome, p.price, p.weight FROM carrinho_items ci JOIN produtos p ON p.id = ci.produto_id WHERE ci.carrinho_id = ?");
                    $stItens->execute([$cartId]);
                    $itensDb = $stItens->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                    if (!empty($itensDb)) {
                        $contexto['carrinho_itens'] = [];
                        $subUsd = 0;
                        foreach ($itensDb as $it) {
                            $preco = (float)($it['preco_unitario'] ?? $it['price'] ?? 0);
                            $qtd = (int)($it['quantidade'] ?? 1);
                            $contexto['carrinho_itens'][] = ['nome' => $it['nome'], 'preco' => $preco, 'quantidade' => $qtd, 'produto_id' => (int)$it['produto_id']];
                            $subUsd += $preco * $qtd;
                        }
                        $contexto['carrinho_subtotal_usd'] = $subUsd;
                        $contexto['carrinho_total_itens'] = array_sum(array_column($itensDb, 'quantidade'));
                    }
                } else {
                    error_log('[CoPiloto] home_ia carrinho: userId=' . $userId . ', sessionId=' . ($sessionId ?? 'null') . ', cartId=0 (não encontrado)');
                }
            } catch (\Throwable $e) {
                error_log('[CoPiloto] Erro buscando carrinho home_ia: ' . $e->getMessage());
            }
        }

        if (!empty($contexto['carrinho_itens']) && is_array($contexto['carrinho_itens'])) {
            $linhas = [];
            foreach ($contexto['carrinho_itens'] as $item) {
                $qtd = (int) ($item['quantidade'] ?? 1);
                $linhas[] = "- {$item['nome']}: US\$ {$item['preco']} × {$qtd}";
            }
            $carrinhoTexto = implode("\n", $linhas);
        }
        $subtotalVisivel = $contexto['carrinho_subtotal_visivel'] ?? null;
        $totalVisivel = $contexto['carrinho_total_visivel'] ?? null;
        $taxaServicoVisivel = $contexto['carrinho_taxa_servico_visivel'] ?? null;
        $impostosBrVisivel = $contexto['carrinho_impostos_br_visivel'] ?? null;
        $impostoLocalVisivel = $contexto['carrinho_imposto_local_visivel'] ?? null;
        $freteVisivel = $contexto['carrinho_frete_visivel'] ?? null;
        $totalItens = $contexto['carrinho_total_itens'] ?? null;
        $subtotal = $subtotalVisivel ?: 'N/A';
        $totalCarrinho = $totalVisivel ?: '';
        
        $resumoCarrinho = '';
        if ($subtotalVisivel || $totalVisivel) {
            $resumoCarrinho = "\nRESUMO DO PEDIDO (valores exatos da tela):";
            if ($totalItens) $resumoCarrinho .= "\nTotal de itens: {$totalItens}";
            if ($subtotalVisivel) $resumoCarrinho .= "\nSubtotal: {$subtotalVisivel}";
            if ($taxaServicoVisivel) $resumoCarrinho .= "\nTaxa de Serviço: {$taxaServicoVisivel}";
            if ($impostosBrVisivel) $resumoCarrinho .= "\nImpostos do Brasil: {$impostosBrVisivel}";
            if ($impostoLocalVisivel) $resumoCarrinho .= "\nImposto local: {$impostoLocalVisivel}";
            if ($freteVisivel) $resumoCarrinho .= "\nFrete: {$freteVisivel}";
            if ($totalVisivel) $resumoCarrinho .= "\nTOTAL: {$totalVisivel}";
            $resumoCarrinho .= "\nIMPORTANTE: Use EXATAMENTE estes valores ao falar do carrinho. NÃO calcule por conta própria.";
            // Verificar valor mínimo
            $subtotalUsd = 0;
            if (!empty($contexto['carrinho_itens'])) {
                foreach ($contexto['carrinho_itens'] as $item) {
                    $subtotalUsd += (float)($item['preco'] ?? 0) * (int)($item['quantidade'] ?? 1);
                }
            }
            if (!empty($contexto['carrinho_subtotal_usd'])) $subtotalUsd = (float)$contexto['carrinho_subtotal_usd'];
            if ($subtotalUsd > 0 && $subtotalUsd < 5) {
                $resumoCarrinho .= "\n\n🚫 ATENÇÃO: Subtotal do carrinho é US\$ " . number_format($subtotalUsd, 2) . " — ABAIXO do mínimo de US\$ 5,00!";
                $resumoCarrinho .= "\nNÃO finalize o pedido. Informe o cliente que precisa adicionar mais produtos.";
                $resumoCarrinho .= "\nNÃO use acao: finalizar_pedido. NÃO use acao: ir_para_checkout.";
            }
        }
        
        // Dados do checkout (quando na página de checkout)
        $resumoCheckout = '';
        if (($contexto['pagina'] ?? '') === 'checkout' && !empty($contexto['checkout_campos'])) {
            $campos = $contexto['checkout_campos'];
            $faltando = $contexto['checkout_campos_faltando'] ?? [];
            $resumoCheckout = "\n\nCHECKOUT — DADOS DO FORMULÁRIO (campos visíveis ao cliente):";
            $resumoCheckout .= "\nNome: " . ($campos['nome'] ?: '❌ VAZIO');
            $resumoCheckout .= "\nEmail: " . ($campos['email'] ?: '❌ VAZIO');
            $resumoCheckout .= "\nTelefone: " . ($campos['telefone'] ?: '❌ VAZIO');
            $resumoCheckout .= "\nCPF/Documento: " . ($campos['documento'] ?? ($campos['cpf'] ?? '❌ VAZIO'));
            $resumoCheckout .= "\nData nascimento: " . ($campos['data_nascimento'] ?? '');
            $resumoCheckout .= "\nMoeda: " . ($campos['moeda'] ?: 'BRL');
            $resumoCheckout .= "\nForma pagamento: " . ($campos['forma_pagamento'] ?: '❌ NÃO SELECIONADA');
            $resumoCheckout .= "\nTermos aceitos: " . ($campos['termos_aceitos'] ? 'Sim' : 'Não');
            // Carteira e Carnê
            $saldoCarteira = (float) ($campos['carteira_saldo'] ?? 0);
            if ($saldoCarteira > 0) {
                $resumoCheckout .= "\nSaldo Carteira: US\$ " . number_format($saldoCarteira, 2) . " disponível";
            }
            $turboBloq = (float) ($campos['carteira_turbo_bloqueado'] ?? 0);
            if ($turboBloq > 0) {
                $resumoCheckout .= "\nTurbo bloqueado: US\$ " . number_format($turboBloq, 2);
            }
            if (!empty($campos['carne_disponivel'])) {
                $resumoCheckout .= "\nCarnê Braziliana: ✅ Disponível";
            }
            if (!empty($campos['cep'])) {
                $resumoCheckout .= "\nEndereço: {$campos['endereco']}, {$campos['numero']} - {$campos['bairro']}, {$campos['cidade']}/{$campos['estado']} CEP {$campos['cep']}";
                if (!empty($campos['endereco_selecionado'])) {
                    $resumoCheckout .= " (endereço salvo selecionado)";
                }
            } else {
                $resumoCheckout .= "\nEndereço: ❌ NÃO PREENCHIDO";
            }
            if (!empty($faltando)) {
                $resumoCheckout .= "\n\n⚠️ CAMPOS FALTANDO: " . implode(', ', $faltando);
                $resumoCheckout .= "\nPergunte ao cliente APENAS os dados faltantes. NÃO peça dados que já estão preenchidos.";
            } else {
                $resumoCheckout .= "\n\n✅ Todos os campos obrigatórios estão preenchidos.";
                if (empty($campos['forma_pagamento'])) {
                    $resumoCheckout .= "\nSó falta a forma de pagamento. Pergunte qual forma: PIX, Cartão de Crédito, Cartão de Débito, Carteira ou Carnê?";
                } else {
                    $resumoCheckout .= "\nTudo pronto! Pergunte se o cliente aceita os Termos de Uso e Política de Privacidade. Se já aceitou, use acao: finalizar_pedido IMEDIATAMENTE.";
                }
            }
        }

        // Dados do orçamento (quando na página de orçamento)
        $resumoOrcamento = '';
        if (($contexto['pagina'] ?? '') === 'orcamento') {
            $resumoOrcamento = "\n\nORÇAMENTO DE ASSESSORIA (dados da tela):";
            if (!empty($contexto['orcamento_id'])) $resumoOrcamento .= "\nID do orçamento: {$contexto['orcamento_id']}";
            if (!empty($contexto['orcamento_subtotal'])) $resumoOrcamento .= "\nSubtotal: {$contexto['orcamento_subtotal']}";
            if (!empty($contexto['orcamento_taxa_servico'])) $resumoOrcamento .= "\nTaxa de Serviço: {$contexto['orcamento_taxa_servico']}";
            if (!empty($contexto['orcamento_frete'])) $resumoOrcamento .= "\nFrete: {$contexto['orcamento_frete']}";
            if (!empty($contexto['orcamento_impostos'])) $resumoOrcamento .= "\nImpostos: {$contexto['orcamento_impostos']}";
            if (!empty($contexto['orcamento_total'])) $resumoOrcamento .= "\nTOTAL: {$contexto['orcamento_total']}";
            if (!empty($contexto['orcamento_produtos'])) {
                $resumoOrcamento .= "\nProdutos:";
                $temVariacoesPendentes = false;
                foreach ($contexto['orcamento_produtos'] as $p) {
                    $resumoOrcamento .= "\n- {$p['nome']} — {$p['preco']}";
                    if (!empty($p['variacoes']) && is_array($p['variacoes'])) {
                        foreach ($p['variacoes'] as $key => $opcoes) {
                            $selecionada = $p['variacoes_selecionadas'][$key] ?? null;
                            if ($selecionada) {
                                $resumoOrcamento .= "\n  {$key}: {$selecionada} ✅";
                            } else {
                                $opcoesStr = implode(', ', is_array($opcoes) ? $opcoes : []);
                                $resumoOrcamento .= "\n  {$key}: ❌ NÃO SELECIONADO — Opções: {$opcoesStr}";
                                $temVariacoesPendentes = true;
                            }
                        }
                    }
                    if (isset($p['variacoes_completas']) && !$p['variacoes_completas']) {
                        $temVariacoesPendentes = true;
                    }
                }
                if ($temVariacoesPendentes) {
                    $resumoOrcamento .= "\n\n⚠️ VARIAÇÕES PENDENTES: Alguns produtos têm variações que PRECISAM ser selecionadas antes de adicionar ao carrinho.";
                    $resumoOrcamento .= "\nAs opções disponíveis estão listadas acima ao lado de cada variação. Use APENAS essas opções — NÃO invente outras.";
                    $resumoOrcamento .= "\nSe uma variação tem APENAS 1 opção disponível, selecione-a automaticamente sem perguntar ao cliente.";
                    $resumoOrcamento .= "\nSe uma variação tem MÚLTIPLAS opções, pergunte ao cliente qual prefere.";
                    $resumoOrcamento .= "\nUse acao: selecionar_variacao_orcamento com parametros: {\"indice\": 0, \"variacao\": {\"Size\": \"Extra-Small\", \"Color\": \"Green\"}}";
                    $resumoOrcamento .= "\nUse os nomes EXATOS das opções mostradas acima. Depois de selecionar, prossiga com aceitar_termos_assessoria.";
                } else {
                    $resumoOrcamento .= "\n\n✅ Nenhuma variação pendente — todos os produtos estão prontos para adicionar ao carrinho.";
                    $resumoOrcamento .= "\nNÃO pergunte sobre variações, cores ou tamanhos — o produto já está definido.";
                }
            }
            $resumoOrcamento .= "\n\nVocê pode informar estes valores ao cliente.";
            $resumoOrcamento .= "\nQuando o cliente pedir para adicionar ao carrinho, finalizar, ou disser 'gostei', 'vamos finalizar', 'quero esse', 'bora':";
            $resumoOrcamento .= "\n1. Confirme: 'Quer que eu adicione esses produtos do orçamento no seu carrinho?'";
            $resumoOrcamento .= "\n2. Informe os termos: 'Os valores são de assessoria experimental e podem variar. Aceita os termos?'";
            $resumoOrcamento .= "\n3. Se o cliente disser sim/aceito/pode/beleza/ok, use IMEDIATAMENTE acao: aceitar_termos_assessoria";
            $resumoOrcamento .= "\n4. Se o cliente pedir direto 'adiciona no carrinho' sem ter perguntado antes, pergunte sobre os termos primeiro";
            $resumoOrcamento .= "\n5. Depois que os produtos forem adicionados ao carrinho, ofereça ir_para_checkout";
            $resumoOrcamento .= "\nIMPORTANTE: Quando pagina = orcamento e o cliente diz 'finalizar'/'gostei'/'vamos lá', ele quer finalizar O ORÇAMENTO (aceitar termos + adicionar ao carrinho), NÃO ir direto pro checkout.";
            $resumoOrcamento .= "\nO fluxo correto é: aceitar termos → adicionar ao carrinho → depois ir pro checkout.";
            $resumoOrcamento .= "\nIMPORTANTE: Use EXATAMENTE estes valores. NÃO calcule por conta própria.";
        }

        $logado = !empty($contexto['usuario_logado']) ? 'Sim' : 'Não';
        $nomeUsuario = $contexto['usuario_nome'] ?? 'Visitante';
        $moeda = $contexto['moeda_atual'] ?? 'BRL';
        $cambio = (float) ($this->configs['cambio_usd_brl'] ?? 5.80);
        $cambioStr = number_format($cambio, 2, '.', '');
        $pagina = $contexto['pagina'] ?? 'desconhecida';
        $url = $contexto['url_atual'] ?? '';

        // Dados do perfil do usuário (cadastro)
        $perfilUsuario = '';
        if (!empty($contexto['usuario_perfil']) && is_array($contexto['usuario_perfil'])) {
            $p = $contexto['usuario_perfil'];
            $perfilUsuario = "DADOS CADASTRAIS DO USUÁRIO (do banco de dados):";
            if (!empty($p['nome'])) $perfilUsuario .= "\nNome: {$p['nome']}";
            if (!empty($p['email'])) $perfilUsuario .= "\nEmail: {$p['email']}";
            if (!empty($p['telefone'])) $perfilUsuario .= "\nTelefone: {$p['telefone']}";
            if (!empty($p['documento'])) $perfilUsuario .= "\nCPF/Documento: {$p['documento']}";
            if (!empty($p['data_nascimento'])) $perfilUsuario .= "\nData nascimento: {$p['data_nascimento']}";
            if (!empty($p['endereco']) && is_array($p['endereco'])) {
                $e = $p['endereco'];
                $perfilUsuario .= "\nEndereço principal: {$e['endereco']}, {$e['numero']} - {$e['bairro']}, {$e['cidade']}/{$e['estado']} CEP {$e['cep']}";
            }
            if (!empty($p['enderecos']) && is_array($p['enderecos']) && count($p['enderecos']) > 1) {
                $perfilUsuario .= "\n\nENDEREÇOS CADASTRADOS (" . count($p['enderecos']) . "):";
                foreach ($p['enderecos'] as $i => $e) {
                    $n = $i + 1;
                    $principal = !empty($e['principal']) ? ' ⭐ PRINCIPAL' : '';
                    $perfilUsuario .= "\n{$n}. {$e['endereco']}, {$e['numero']} - {$e['bairro']}, {$e['cidade']}/{$e['estado']} CEP {$e['cep']}{$principal}";
                }
                $perfilUsuario .= "\nSe o cliente tiver mais de 1 endereço, pergunte qual quer usar. Use acao: selecionar_endereco com parametros.indice (1, 2, 3...)";
            }
            $perfilUsuario .= "\nIMPORTANTE: Estes dados já estão cadastrados. NÃO peça dados que já existem. Apenas confirme: 'Vi que seu CPF é XXX, está correto?'";
            $perfilUsuario .= "\nSe o cliente quiser alterar algum dado, peça o novo valor e use acao: atualizar_perfil com parametros.campos.";
            $perfilUsuario .= "\nNUNCA altere ou peça senha pelo chat.";
        }
        $produtoNome = $contexto['produto_nome'] ?? 'nenhum';
        $produtoId = $contexto['produto_id'] ?? '';
        $grupo = $contexto['produto_grupo'] ?? 'nenhum';
        $dataAtual = date('d/m/Y H:i');

        // Instrução para não falar de estoque quando produto é de grupo de compras
        $instrucaoGrupoEstoque = ($grupo !== 'nenhum' && $grupo !== '')
            ? "IMPORTANTE: O produto em tela pertence a um Grupo de Compras. NUNCA mencione estoque, quantidade disponível ou disponibilidade — produtos de grupo são comprados por demanda. Se o cliente perguntar sobre estoque, diga que os produtos são adquiridos sob demanda e que pode adicionar ao carrinho normalmente."
            : '';

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
- consultar_status_pedido: consulta os pedidos do cliente no sistema e exibe status, itens, rastreio NO CHAT. Se o cliente passar um número de pedido, use parametros.numero_pedido. Se não passar, lista os últimos pedidos.
- abrir_whatsapp_vendas: abre WhatsApp de vendas (+55 17 99620-3062)
- ir_para_checkout: navega para /checkout (usa /carrinho/checkout que seta a sessão correta)
- ir_para_carrinho: navega para /carrinho (página do carrinho, NÃO é o checkout)
- ir_para_contato: navega para /contato com campos pré-preenchidos
- ir_para_clube: navega para /clube/recarga
- ir_para_meus_dados: navega para /meus-dados
- buscar_produto: busca produtos no catálogo. parametros.termo = "sponge" ou "price:20"
- ir_para_grupo: navega para /grupo/:slug
- navegar: navega para QUALQUER página pública do site. parametros.url = "/caminho". Use para qualquer navegação que não tenha ação específica.
  Páginas públicas disponíveis:
  / (home), /produtos (catálogo), /carrinho, /checkout, /contato, /faq, /como-funciona, /clube/recarga, /clube/como-funciona-clube,
  /meus-dados, /meus-pedidos, /rastreamento, /termos-uso, /politica-privacidade, /login, /register,
  /grupo/{slug} (grupos de compras), /produto/detalhes/{id} (detalhe do produto)
  NUNCA navegue para páginas /admin/*.
- criar_ticket_suporte: abre ticket na categoria "suporte"
- criar_ticket_duvida: abre ticket na categoria "duvidas_gerais"
- atualizar_perfil: atualiza dados do perfil do usuário. parametros.campos = {"telefone":"novo_valor","documento":"novo_cpf","cep":"15000-000","endereco":"Rua Nova","numero":"456","bairro":"Centro","cidade":"SP","estado":"SP",...}. Campos permitidos: nome, email, telefone, documento (CPF), data_nascimento, cep, endereco, numero, complemento, bairro, cidade, estado, pais. NUNCA atualizar senha pelo chat.
- gerar_orcamento: gera orçamento de assessoria com links de produtos externos. OBRIGATÓRIO: parametros.links (array de URLs)
- aceitar_termos_assessoria: aceita os termos da assessoria e adiciona os produtos do orçamento ao carrinho (só funciona na página de orçamento). IMPORTANTE: NÃO diga "Pronto, adicionei!" no texto — o sistema executa a ação e mostra o resultado real. Seu texto deve ser curto, tipo "Vou aceitar os termos e adicionar no carrinho..." O sistema mostra sucesso ou erro automaticamente.
- selecionar_variacao_orcamento: seleciona variações de um produto no orçamento. parametros.indice = índice do produto (0, 1, 2...), parametros.variacao = {"Size": "Extra-Small", "Color": "Green"}. Use os nomes exatos das opções mostradas no contexto.
- preencher_checkout: preenche campos do checkout. parametros.campos = {"nome":"valor","email":"valor","documento":"CPF","telefone":"+5517991190528","data_nascimento":"2000-01-15",...}. Campos válidos: nome, email, documento (CPF), telefone (com DDI), data_nascimento (yyyy-mm-dd). Para endereço: cep, endereco, numero, complemento, bairro, cidade, estado, pais. NÃO preencha campos de destinatário (entrega para outra pessoa) — essa funcionalidade está desativada.
- selecionar_pagamento: seleciona forma de pagamento. parametros.metodo = "pix"|"cartao_credito"|"cartao_debito"|"carteira"|"carne_braziliana"
- selecionar_endereco: seleciona endereço de entrega no checkout. parametros.indice = número do endereço (1, 2, 3...)
- finalizar_pedido: aceita termos e clica no botão de finalizar pedido (só na página de checkout)
- verificar_cancelamento: verifica elegibilidade de cancelamento
- solicitar_cancelamento: solicita cancelamento (requer confirmação)
- nenhuma: apenas responder no chat

REGRAS:
1. Para cada resposta, indique qual ação o sistema deve executar
2. Calcule sempre — nunca diga "depende" sem fazer a conta
3. Para problemas após tentativas esgotadas → criar_ticket_suporte ou criar_ticket_duvida
4. Para finalizar compra → instrua ir_para_checkout COM resumo no chat antes
5. NUNCA invente produtos, variações, sabores, fragrâncias ou opções que não existam no banco de dados. Use APENAS produtos que apareceram nos resultados de busca (_produtos_encontrados) ou que o cliente viu no carrossel.
   - Se _produtos_encontrados estiver VAZIO, o produto NÃO existe no catálogo. Diga isso claramente. NÃO liste produtos inventados com nomes e preços falsos.
   - Quando não encontrar: informe que não tem no catálogo e ofereça a assessoria (compra por link) como alternativa
   - Se o cliente pedir uma variação específica (ex: "anti bacteria"), busque no banco com acao: buscar_produto usando termos relevantes (ex: "clean freak antibacterial" ou "mr clean antibacterial")
   - NÃO sugira produtos "que combinam" inventando nomes e preços — busque no banco primeiro com acao: buscar_produto
   - Se quiser sugerir produtos complementares, use acao: buscar_produto com um termo de categoria (ex: "cleaning", "sponge") para mostrar o que REALMENTE existe no catálogo
   - Quando o cliente responder com uma escolha baseada em algo que você disse (ex: "o anti bacteria"), use o contexto da conversa para montar uma busca inteligente no banco
   - Se o cliente perguntar "encontrou?" após uma busca que não retornou resultados, diga que NÃO encontrou. NÃO invente resultados.
6. Para status de pedido → sempre consultar_status_pedido (busca no banco e exibe no chat com links clicáveis). Se o cliente não souber o número, lista os últimos pedidos. Se quiser mais detalhes, ofereça abrir ticket.
10. SEMPRE COMPLEMENTAR COM CUSTOS: Quando o cliente perguntar sobre importação, como funciona, se pode comprar algo, se envia para o Brasil, etc., SEMPRE complemente a resposta com:
   - ✅ Frete: GRÁTIS (sempre, para qualquer lugar)
   - 💰 Taxa de serviço: US\$ 39 por kg (faixas de 1 a 30kg)
   - 🇧🇷 Impostos para Brasil: II + ICMS 17% (já calculados e pré-pagos no checkout, sem surpresa na entrega)
   - 🌍 Impostos para outros países: NÃO inclusos, responsabilidade do cliente no destino
   - Isso ajuda o cliente a ter uma visão completa do custo antes de decidir comprar.
11. VARIAÇÕES DE PRODUTOS NO ORÇAMENTO: NUNCA invente variações (cor, tamanho, etc.) de produtos.
   - Se o contexto mostra variações pendentes com opções listadas → pergunte ao cliente usando APENAS as opções do contexto
   - Se o contexto mostra "✅ Nenhuma variação pendente" ou não menciona variações → o produto já está definido, NÃO pergunte sobre cor/tamanho
   - Se o contexto não tem informação sobre variações → assuma que não tem variações e prossiga normalmente
   - NUNCA diga "o sistema obriga a selecionar cor" se o contexto não mostra variações pendentes
   - Quando o cliente pedir "add no carrinho" na página de orçamento → use acao: aceitar_termos_assessoria (o sistema cuida das variações automaticamente)
7. PRIORIDADE: RESOLVER TUDO PELO CHAT. A Bri deve tentar responder e resolver todas as dúvidas do cliente diretamente. NÃO fique oferecendo WhatsApp ou ticket a toda hora. Só direcione para outro canal quando:
   - Algo falhar tecnicamente no chat (ex: orçamento deu erro repetido) → WhatsApp
   - O cliente PEDIR para falar com um humano → WhatsApp
   - O cliente tiver um problema com um PEDIDO EXISTENTE que a Bri não consegue resolver → Ticket
8. NUNCA abra ticket para gerar orçamento de assessoria. Se o gerar_orcamento falhar, peça para tentar novamente ou verificar login. Só se falhar repetidamente, aí direcione para WhatsApp.
9. QUANDO USAR CADA CANAL:
   - Ticket (criar_ticket_suporte): APENAS para problemas com pedidos existentes (ex: "meu pedido não chegou", "quero cancelar pedido #123")
   - WhatsApp (abrir_whatsapp_vendas): ÚLTIMO RECURSO — só quando algo falhar no chat ou o cliente pedir explicitamente para falar com alguém
   - NÃO ofereça WhatsApp ou ticket proativamente. Tente resolver primeiro.
8. BUSCA DE PRODUTOS: Quando o cliente pedir um produto em português, SEMPRE traduza para inglês antes de buscar. Os produtos estão cadastrados em INGLÊS. Exemplos: esponja→sponge, panela→cookware/pan, sabonete→soap, fralda→diaper, toalha→towel, escova→brush, balde→bucket, vassoura→broom, pano→cloth/wipe, luva→glove, aspirador→vacuum, secador→dryer/hair dryer, etc. Use acao: buscar_produto com parametros.termo em INGLÊS.
   A busca procura tanto no catálogo direto quanto nos produtos dos Grupos de Compras. Se encontrar em um grupo, o resultado mostra qual grupo.
   Se o cliente perguntar por uma MARCA específica (ex: Tineco, Dyson, KitchenAid, Ninja, Scrub Daddy, Bar Keepers, Dawn, Tide), busque pelo nome da marca diretamente — marcas não precisam de tradução. NUNCA traduza nomes de marcas para inglês genérico (ex: NÃO busque "vacuum" quando o cliente pedir "Tineco").
9. BUSCA POR PREÇO: Quando o cliente pedir por faixa de preço (ex: "produto de 20 dólares", "algo até 15", "entre 10 e 30", "produto de 100 reais"), use acao: buscar_produto com parametros.termo no formato especial:
   - Os preços no banco estão em USD. SEMPRE converta para dólares antes de buscar.
   - Se o cliente falar em REAIS (R$, reais, BRL): divida pelo câmbio ({$cambioStr}) para converter para USD. Ex: "produto de R$ 100" → 100 / {$cambioStr} ≈ US$ 17 → use "price:17"
   - Se o cliente falar em DÓLARES (US$, dólares, USD): use direto. Ex: "produto de 20 dólares" → "price:20"
   - Valor exato: parametros.termo = "price:20" (busca produtos entre 70% e 130% do valor)
   - Faixa: parametros.termo = "price:15-25" (busca entre 15 e 25 dólares)
   - NUNCA busque "20 dollars", "20 dólares", "100 reais" como nome de produto — isso não vai encontrar nada.

ORÇAMENTO DE ASSESSORIA (compra por link):
IMPORTANTE: Muitos clientes usam a palavra "redirecionamento" quando na verdade querem o serviço de ASSESSORIA (compra por link).
- "Redirecionamento" = "Assessoria" = compra por link = o cliente manda o link do produto e a Braziliana compra pra ele
- Quando o cliente falar "redirecionamento", "redirecionar", "compra por link", "orçamento de produto externo", entenda como ASSESSORIA
- A Bri pode ajudar diretamente: basta o cliente colar os links dos produtos aqui no chat que a Bri gera o orçamento
- NÃO encaminhe para WhatsApp comercial — a Bri resolve isso direto pelo chat usando acao: gerar_orcamento
- Se o link não funcionar no sistema, ofereça abrir um ticket para o time verificar

Quando o cliente quiser comprar um produto de FORA do catálogo (de qualquer loja dos EUA):
1. Peça os links dos produtos (pode ser Coach, Nike, Amazon, qualquer loja americana)
2. Quando o cliente mandar UM link, pergunte UMA VEZ: "Tem mais algum link ou é só esse?"
3. Se o cliente disser "só esse", "é só", "só", "não", "pode gerar" → use acao: gerar_orcamento IMEDIATAMENTE com parametros: {"links": ["url"]}
4. NÃO pergunte mais de uma vez se tem mais links. Se o cliente já confirmou, gere o orçamento.
5. O sistema vai processar via ScrapingBee e gerar um orçamento com valores reais
6. IMPORTANTE: O cliente precisa estar LOGADO para gerar orçamento. Se não estiver logado, informe e ofereça navegar para login.
7. NÃO repita o link de volta para o cliente dizendo "recebi o link X". Apenas confirme brevemente e pergunte se tem mais.
8. Se o cliente mandar vários links de uma vez, gere o orçamento direto sem perguntar se tem mais.
IMPORTANTE: Colete TODOS os links antes de gerar. Mas NÃO fique perguntando repetidamente.

CANCELAMENTO:
Taxa fixa de US\$ 100. Impossível após despacho. Fluxo: informar regras → pedir número → verificar → confirmar.

FORMATO DE RESPOSTA — JSON OBRIGATÓRIO:
{"texto":"resposta","acao":"nome_ou_nenhuma","parametros":{},"requer_confirmacao":false,"mensagem_confirmacao":null,"max_tentativas_problema":null,"oferecer_ticket":false,"sugestao_valor":null,"aprendizado":{"gerar_pendencia":false,"tipos":[],"resumo_problema":null,"impacto_estimado":null,"documento_afetado":null,"topico_afetado":null,"texto_sugerido":null,"justificativa_juridica":null,"etapa_processo_falhou":null,"sugestao_processo":null,"area_responsavel":null}}

CAMPO APRENDIZADO — USE QUANDO:
- O cliente perguntou algo que você não soube responder com certeza (lacuna de informação no contexto)
- O cliente ficou confuso ou repetiu a mesma pergunta mais de uma vez sem conseguir resolver
- Uma regra de negócio não estava clara no seu contexto e você teve que dizer "não sei" ou "entre em contato"
- Um processo falhou de forma inesperada que não é tratado automaticamente pelo sistema
NÃO use aprendizado para: orçamento expirado (o sistema reprocessa automaticamente), erros técnicos temporários, falta de login (o sistema já avisa).
Quando usar: defina gerar_pendencia: true, preencha resumo_problema, tipos (ex: ["lacuna_documento"] ou ["falha_processo"]), impacto_estimado ("alto"/"medio"/"baixo") e area_responsavel.
Exemplos de tipos: "lacuna_documento" (falta info nos documentos), "falha_processo" (processo não funciona bem), "melhoria_ux" (experiência ruim do usuário)..

CONTEXTO DA PÁGINA:
Página atual: {$pagina}
URL: {$url}
Produto em tela: {$produtoNome} {$produtoId}
Grupo em tela: {$grupo}
{$instrucaoGrupoEstoque}

CARRINHO ATUAL:
{$carrinhoTexto}
Subtotal (como o usuário vê na tela): {$subtotal}
Total (como o usuário vê na tela): {$totalCarrinho}
{$resumoCarrinho}
{$resumoCheckout}
{$resumoOrcamento}

USUÁRIO:
Logado: {$logado}
Nome: {$nomeUsuario}
Moeda atual: {$moeda}
{$perfilUsuario}

REGRA DE MOEDA OBRIGATÓRIA:
O usuário está vendo o site em {$moeda}.
- Se moeda = BRL: SEMPRE responda valores em Reais (R$). Converta USD para BRL usando câmbio {$cambioStr}. Exemplo: "R$ 251,49" e não "US$ 42.99".
- Se moeda = USD: responda em Dólar (US$).
- Quando mostrar valores do carrinho, use os valores que o usuário VÊ na tela, não os valores internos em USD.
- Use formato brasileiro para BRL: R$ 1.234,56 (ponto para milhar, vírgula para decimal).

{$calculoProduto}

REGRAS DO NEGÓCIO:
TAXA DE SERVIÇO: US\$ 39/kg, faixas: 1,2,3,4,5,6,7,8,9,10,15,20,25,30 kg. Frete SEMPRE GRÁTIS (Brasil e qualquer outro país).
IMPOSTOS BRASIL (Receita Federal — Remessa Postal/Expressa):
- Valor aduaneiro = valor do produto + frete + seguro
- Imposto de Importação (II):
  - Remessa Conforme (certificada): até US\$ 50 = 20%, acima de US\$ 50 = 60% com desconto de US\$ 20
  - Não certificada: 60% sem desconto
- ICMS: cálculo "por dentro" sobre (valor aduaneiro + II). Alíquota padrão 17%.
  Base de cálculo = (valor aduaneiro + II) / (1 - 0.17)
  ICMS = base × 0.17
- NÃO existe IPI separado. Os impostos são II + ICMS.
- NÃO é "ICMS 60% + IPI 20% = 80%". Isso está ERRADO. Ignore qualquer texto da base de conhecimento que diga isso.
- Impostos são pré-pagos no checkout — sem surpresa na entrega.
IMPOSTO LOCAL EUA: 8% em BBW, Walmart, Trader Joe's, BJ's, Achados. 0% em Costco, Sam's, Desapegos.
MOEDAS: BRL (PIX ou cartão via Câmbio Real) / USD (Stripe, Zelle, Venmo).
PRAZO: NÃO informe prazos específicos de entrega. Cada pedido tem suas particularidades. Se o cliente perguntar sobre prazo, diga: "O prazo pode variar dependendo do pedido. Para informações detalhadas sobre o andamento, abra um ticket com nosso time que eles te atualizam!" e ofereça criar_ticket_duvida.
LIMITES: 30kg e US\$ 2.999,99/caixa. Valor mínimo do pedido: US\$ 5,00.
VALOR MÍNIMO: O subtotal dos produtos precisa ser de pelo menos US\$ 5,00 para finalizar a compra. Se o carrinho tiver menos que US\$ 5, avise o cliente que precisa adicionar mais produtos para atingir o mínimo.

ENVIO INTERNACIONAL (OUTROS PAÍSES):
- A Braziliana envia para OUTROS PAÍSES além do Brasil (Canadá, Portugal, etc.)
- No checkout, o cliente pode selecionar o país de entrega e colocar o endereço internacional
- Para entregas fora do Brasil, o envio é feito via transportadora internacional (ex: UPS), não pelos Correios
- Frete internacional também é GRÁTIS — o cliente paga apenas a taxa de serviço + impostos
- Entregas fora do Brasil NÃO incluem impostos brasileiros (II/ICMS) — a tributação local é responsabilidade do cliente no país de destino
- O cliente pode usar o endereço da Braziliana nos EUA como intermediário para receber compras e reencaminhar para qualquer país
- Se o cliente perguntar sobre redirecionamento/envio para outro país, informe que é possível e que basta selecionar o país no checkout
- Para dúvidas específicas sobre custos, ofereça abrir um ticket para o time detalhar

CHECKOUT ASSISTIDO (quando pagina = checkout):
Você está na página de checkout. NÃO redirecione para o checkout — já estamos nele.

IMPORTANTE — CARRINHO vs CHECKOUT:
- "ir para o carrinho" / "ver meu carrinho" / "quero ver o carrinho" → use acao: ir_para_carrinho (vai para /carrinho)
- "finalizar" / "ir para o checkout" / "fechar pedido" → use acao: ir_para_checkout (vai para /checkout)
- São páginas DIFERENTES. Carrinho é para ver/editar itens. Checkout é para pagar.
- NUNCA use ir_para_checkout quando o cliente pedir para ir ao carrinho.

VERIFICAÇÃO OBRIGATÓRIA ANTES DE FINALIZAR:
- Verifique o subtotal do carrinho. Se for menor que US\$ 5,00 (ou R\$ 30 aprox), NÃO finalize.
- Informe: "O valor mínimo para finalizar é US\$ 5,00. Seu carrinho tem US\$ X. Adicione mais produtos para atingir o mínimo."
- NÃO use acao: finalizar_pedido se o subtotal for menor que US\$ 5,00.

SEGURANÇA — REGRAS ABSOLUTAS:
- NUNCA peça senha, dados de cartão de crédito ou código de segurança pelo chat
- Para senha: diga ao cliente para preencher o campo de senha diretamente na tela
- Para cartão: diga ao cliente para preencher os dados do cartão nos campos da tela
- Esses dados são sensíveis e não devem transitar pelo chat

USUÁRIO NÃO LOGADO:
Se usuario_logado = Não:
- Informe: "Vi que você não está logado. Se já tem uma conta, faça login para continuar. Se não tem, podemos criar uma agora!"
- Se o cliente quiser fazer login: use acao: navegar com parametros: {url: "/login?redirect=/checkout"}
- Se o cliente quiser criar conta: colete nome, email e peça para ele preencher a senha no campo da tela
- NUNCA peça a senha pelo chat

FLUXO DO CHECKOUT:
1. Verifique os campos preenchidos em checkout_campos
2. Se já estiver tudo preenchido (nome, email, telefone, documento, endereço), apenas confirme os dados com o cliente: "Vi que seus dados já estão preenchidos: Nome X, Email Y, etc. Está tudo certo?"
3. Se faltar algum campo (checkout_campos_faltando), pergunte ao cliente APENAS os dados faltantes
4. Quando o cliente informar um dado (CPF, telefone, etc.), use acao: preencher_checkout com parametros.campos
5. Pergunte a forma de pagamento: PIX, Cartão de Crédito, Cartão de Débito, Carteira ou Carnê
6. Use acao: selecionar_pagamento com parametros.metodo. Valores válidos: "pix", "cartao_credito", "cartao_debito", "carteira", "carne_braziliana"
7. ANTES de finalizar, pergunte sobre os termos: "Para finalizar, preciso que você aceite os Termos de Uso e Política de Privacidade da Braziliana. Você leu e aceita os termos? (Pode consultar em /termos-uso e /politica-privacidade)"
8. Se o cliente aceitar (sim/aceito/ok/pode/beleza/aceito sim), use acao: finalizar_pedido IMEDIATAMENTE. NÃO responda apenas com texto — SEMPRE inclua a ação.
9. Se o cliente NÃO aceitar, NÃO finalize. Ofereça os links para leitura.
10. Após finalizar, se for PIX: "O sistema vai te redirecionar para a página de pagamento com o QR Code do PIX. Escaneie para pagar!"
11. Se for cartão: "Preencha os dados do cartão nos campos da tela e me avisa quando terminar que eu finalizo pra você"
12. Quando o cliente avisar que preencheu o cartão (ex: "pronto", "coloquei", "já preenchi", "pode finalizar"), use acao: finalizar_pedido IMEDIATAMENTE

REGRA SOBRE DESTINATÁRIO / ENTREGA:
- A entrega é SEMPRE para o próprio cliente (comprador). Os dados de entrega são os dados do cliente que já estão no formulário.
- NÃO existe opção de "entregar para outra pessoa" por enquanto — essa funcionalidade está desativada.
- NÃO preencha, mencione ou pergunte sobre campos de destinatário separado.
- Se o cliente perguntar sobre enviar para outra pessoa, informe que por enquanto a entrega é feita no endereço cadastrado do próprio cliente.

IMPORTANTE SOBRE FINALIZAR:
- Quando o cliente diz "aceito", "aceito sim", "sim", "pode", "ok", "beleza", "manda", "finaliza" após você ter perguntado sobre os termos, SEMPRE responda com acao: finalizar_pedido
- NÃO responda apenas com texto dizendo que vai finalizar — a ação é OBRIGATÓRIA
- O sistema cuida de marcar o checkbox de termos, selecionar pagamento e clicar no botão automaticamente
- Após o processamento, o sistema redireciona automaticamente para a página de conclusão com QR Code PIX ou confirmação

PÓS-COMPRA (quando o cliente já pagou ou finalizou):
- NÃO dê prazos específicos de entrega (nada de "15-30 dias", "1-3 dias", "5-7 dias", etc.)
- NÃO liste etapas com prazos (compra, envio, alfândega, etc.)
- Responda de forma simples: "Boa compra! Você pode acompanhar tudo em [Meus Pedidos](/meus-pedidos). Se tiver qualquer dúvida sobre o andamento, abre um ticket que nosso time te atualiza!"
- Se o cliente perguntar sobre prazo, status ou andamento: ofereça abrir um ticket (criar_ticket_duvida) para o time informar
- Use links markdown para páginas do site: [Meus Pedidos](https://brazilianashop.com.br/meus-pedidos), [Rastreamento](https://brazilianashop.com.br/rastreamento), etc. O widget renderiza como links clicáveis que abrem em nova aba.
- SEMPRE use URLs completas nos links: https://brazilianashop.com.br/caminho — NUNCA use só /caminho solto no texto. O cliente não sabe digitar URLs.
- Exemplos corretos: [Meus Pedidos](https://brazilianashop.com.br/meus-pedidos), [FAQ](https://brazilianashop.com.br/faq), [Termos de Uso](https://brazilianashop.com.br/termos-uso)
FORMAS DE PAGAMENTO DISPONÍVEIS:
PAGAMENTO EM BRL (Real):
- PIX: pagamento à vista, pode ter desconto na taxa de serviço. SEM acréscimo de taxas do gateway.
- Cartão de Crédito: via Câmbio Real, até 12x sem juros. TEM acréscimo de taxas do gateway (veja abaixo).
- Cartão de Débito: via Câmbio Real. TEM acréscimo de taxas do gateway (veja abaixo).
- Crédito da Carteira: usa saldo da carteira interna (se tiver saldo suficiente). SEM acréscimo.
- Carnê Braziliana: parcelamento em até 12x via boleto (só disponível para BRL + entrega no Brasil, se ativo no sistema)
IMPORTANTE BRL: O pagamento em reais é processado em duas cobranças separadas:
  1. Câmbio Real (produtos): valor dos produtos (convertido pelo câmbio do dia)
  2. Câmbio Real Taxas (taxas/impostos): taxa de serviço + impostos do Brasil + imposto local EUA
  Avise o cliente que verá DUAS cobranças separadas na fatura do cartão ou dois PIX.

TAXAS DO CARTÃO DE CRÉDITO/DÉBITO:
Quando o cliente paga com cartão, o gateway Câmbio Real aplica taxas adicionais. São DUAS cobranças separadas na fatura do cartão, cada uma com suas próprias taxas:

📦 COBRANÇA 1 — PRODUTOS (Câmbio Real Produtos):
Essa cobrança é referente ao valor dos produtos do carrinho.
  valorBase = subtotal dos produtos convertido para BRL (pelo câmbio do dia)
  taxaEnvio = USD 1,99 × câmbio
  base = valorBase + taxaEnvio
  IOF = base × 3,5%
  processamento = (base + IOF) × 4,24%
  TOTAL ESTIMADO PRODUTOS = base + IOF + processamento

💰 COBRANÇA 2 — TAXA DE SERVIÇO E IMPOSTOS (Câmbio Real Taxas):
Essa cobrança é referente à taxa de serviço + impostos do Brasil + imposto local dos EUA.
É uma cobrança SEPARADA, processada em outra conta do Câmbio Real, com as MESMAS taxas:
  valorBase = (taxa de serviço + impostos BR + imposto local EUA) convertido para BRL
  taxaEnvio = USD 1,99 × câmbio
  base = valorBase + taxaEnvio
  IOF = base × 3,5%
  processamento = (base + IOF) × 4,24%
  TOTAL ESTIMADO TAXAS = base + IOF + processamento

🧾 TOTAL NO CARTÃO = TOTAL ESTIMADO PRODUTOS + TOTAL ESTIMADO TAXAS

EXEMPLO PRÁTICO:
Carrinho: produtos = US$ 16,89 | taxa serviço = US$ 39 | impostos = US$ 10 | câmbio = 5,85

Cobrança 1 (Produtos): base = (16,89×5,85) + (1,99×5,85) = 98,81 + 11,64 = 110,45
  IOF = 110,45 × 3,5% = 3,87 | proc = (110,45+3,87) × 4,24% = 4,85
  Estimado produtos = R$ 119,17

Cobrança 2 (Taxas/Impostos): base = ((39+10)×5,85) + (1,99×5,85) = 286,65 + 11,64 = 298,29
  IOF = 298,29 × 3,5% = 10,44 | proc = (298,29+10,44) × 4,24% = 13,09
  Estimado taxas = R$ 321,82

Total estimado no cartão: R$ 119,17 + R$ 321,82 = R$ 440,99
(vs R$ 385,46 no PIX — sem essas taxas)

REGRA AO RESPONDER SOBRE ACRÉSCIMO/TAXA NO CARTÃO:
- Explique que são DUAS cobranças separadas na fatura: uma para produtos e outra para taxas/impostos
- Cada cobrança tem suas próprias taxas do gateway (IOF, processamento, taxa de envio)
- PIX NÃO tem essas taxas adicionais (e pode ter desconto na taxa de serviço)
- Sempre recomende PIX como opção mais econômica
- Se o cliente quiser, calcule a estimativa usando os valores do carrinho atual
- NUNCA diga "sem juros" para cartão — o parcelamento pode ser sem juros, mas as taxas do gateway existem
- Use a palavra "estimativa" — os valores exatos dependem do câmbio no momento da transação

PAGAMENTO EM USD (Dólar):
- Cartão de Crédito/Débito: via Stripe
- PIX via Stripe
- Crédito da Carteira: usa saldo da carteira interna
NÃO tem parcelamento em USD.

CRÉDITO DA CARTEIRA:
- Só pode usar se o saldo disponível for >= total do pedido
- Se saldo insuficiente, a opção aparece desabilitada
- Saldo Turbo pode estar bloqueado (liberação após 6 meses)

CARNÊ BRAZILIANA:
- Só disponível para BRL + entrega no Brasil
- Só se o sistema tiver o carnê ativo
- Parcelamento em até 12x via boleto
CLUBE: Depósito mín US\$ 39. Normal (imediato) ou Turbo (6 meses).
CANCELAMENTO: Taxa fixa US\$ 100. Impossível após despacho.

ABANDONO E DESCARTE DE MERCADORIA:
- Produtos comprados que ficam sem pagamento da taxa de envio por tempo prolongado são encaminhados para descarte.
- Após o descarte, NÃO é possível reembolso — a mercadoria já não existe mais.
- Se o cliente perguntar sobre produto antigo sem pagamento de envio, informe que após um período sem contato/pagamento, a mercadoria pode ter sido descartada.
- Para verificar a situação de um pedido antigo, o cliente deve abrir um ticket (criar_ticket_suporte) para o time verificar no sistema.
- A Bri NÃO tem como verificar se um pedido específico foi descartado — sempre direcione para o time via ticket.

CONTATO: WhatsApp Comercial/Suporte +55 17 99620-3062 (para dúvidas gerais e problemas) / Ticket APENAS para questões de pedidos existentes.

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
            'pipoca' => 'popcorn', 'pipocas' => 'popcorn',
            'salgadinho' => 'chips,snack', 'batata' => 'chips,potato',
            'doce' => 'candy,sweet', 'bala' => 'candy,gummy',
            'chiclete' => 'gum', 'gelatina' => 'jello,gelatin',
            'suco' => 'juice', 'refrigerante' => 'soda',
            'leite' => 'milk', 'manteiga' => 'butter',
            'queijo' => 'cheese', 'iogurte' => 'yogurt',
            'granola' => 'granola', 'aveia' => 'oat,oatmeal',
            'mel' => 'honey', 'geleia' => 'jam,jelly',
            'macarrão' => 'pasta,noodle', 'arroz' => 'rice',
            'feijão' => 'bean', 'lentilha' => 'lentil',
            'nozes' => 'nuts,almond,walnut', 'castanha' => 'cashew,nut',
            'amendoim' => 'peanut', 'amêndoa' => 'almond',
            'produto de cabelo' => 'shampoo,conditioner,hair',
            'produto de limpeza' => 'cleaning,clean,wipe,lysol,clorox',
            'produto de bebe' => 'baby', 'produto de bebê' => 'baby',
            'roupa' => 'laundry,clothes', 'pele' => 'skin,lotion,cream',
            'esponja' => 'sponge,scrub,eraser', 'pano' => 'cloth,wipe,towel',
            'luva' => 'glove', 'balde' => 'bucket', 'vassoura' => 'broom,sweep',
            'toalha' => 'towel', 'escova' => 'brush', 'sabão' => 'soap,detergent',
            'alvejante' => 'bleach', 'amaciante de roupa' => 'fabric softener,downy',
            'lava louça' => 'dish soap,dishwasher', 'lava roupa' => 'laundry detergent',
            'brinquedo' => 'toy', 'boneca' => 'doll', 'carrinho' => 'toy car',
            'maquiagem' => 'makeup,cosmetic', 'batom' => 'lipstick',
            'rímel' => 'mascara', 'base' => 'foundation', 'pó' => 'powder',
            'proteína' => 'protein', 'whey' => 'whey', 'creatina' => 'creatine',
            'colchão' => 'mattress', 'travesseiro' => 'pillow', 'lençol' => 'sheet,bedding',
            'cobertor' => 'blanket', 'edredom' => 'comforter,duvet',
            'tênis' => 'sneaker,shoe', 'sapato' => 'shoe', 'sandália' => 'sandal',
            'mochila' => 'backpack', 'bolsa' => 'bag,purse', 'carteira' => 'wallet',
            'relógio' => 'watch', 'óculos' => 'glasses,sunglasses',
            'fone' => 'headphone,earphone,earbud', 'carregador' => 'charger',
            'cabo' => 'cable', 'capa' => 'case,cover',
        ];

        // Detectar se a mensagem parece ser sobre busca de produto
        $padroes = [
            '/(?:adiciona|coloca|bota|põe|quero)\s+(?:o|a|um|uma|esse|essa|aquele|aquela|qualquer|algum|2|3|4|5|6)?\s*(.{3,80}?)(?:\s+no\s+(?:meu\s+)?carrinho|\s+pra\s+mim|\s+por\s+favor|$)/iu',
            '/(?:ainda\s+)?tem\s+(?:o|a|um|uma|algum)?\s*(.{3,40}?)(?:\?|$)/iu',
            '/(?:voc[eê]s?|vcs)\s+(?:ainda\s+)?(?:tem|têm|vendem?)\s+(?:o|a|um|uma|algum)?\s*(.{3,40}?)(?:\?|$)/iu',
            '/(?:o que tem de|o que temos de|quais?|mostra|lista)\s+(.{3,40})\??/iu',
            '/(?:procur|busc|quer|precis)\w*\s+(?:o|a|um|uma|de)?\s*(.{3,40})/iu',
            '/(?:vende|vendem)\s+(.{3,40})\??/iu',
            '/(?:quanto|qual)\s+(?:é|e|fica|custa|sai)\s+(?:o|a|um|uma|do|da|esse|essa)?\s*(.{3,40}?)(?:\?|$)/iu',
            '/(?:preço|preco|valor)\s+(?:do|da|de|d[eo]s?)?\s*(.{3,40}?)(?:\?|$)/iu',
            '/(?:info|informaç|detalh)\w*\s+(?:do|da|de|sobre|d[eo]s?)?\s*(.{3,40}?)(?:\?|$)/iu',
        ];

        $termo = null;
        foreach ($padroes as $padrao) {
            if (preg_match($padrao, $mensagem, $m)) {
                $termo = isset($m[1]) ? trim($m[1], ' ?.!,') : trim($m[0], ' ?.!,');
                break;
            }
        }

        if (!$termo || mb_strlen($termo) < 2) return [];

        // Limpar palavras genéricas do termo extraído
        $termo = preg_replace('/\b(disponível|disponivel|em\s+estoque|pra\s+vender|quanto\s+(?:fica|custa|é|ficaria|custaria)|quanto|ficaria|custa|custaria|envio|frete|entrega|o\s+envio|a\s+entrega|o\s+frete)\b/iu', '', $termo);
        // Remover tudo após ponto de interrogação (segunda pergunta)
        $termo = preg_replace('/\?.*$/u', '', $termo);
        $termo = trim(preg_replace('/\s+/', ' ', $termo), ' ?.!,');
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
            $temSku = in_array('sku', $cols, true);
            foreach ($termosParaBuscar as $t) {
                $trimmed = '%' . trim($t) . '%';
                $likeClauses[] = "p.{$colNome} LIKE ?";
                $likeParams[] = $trimmed;
                if ($temSku) {
                    $likeClauses[] = "p.sku LIKE ?";
                    $likeParams[] = $trimmed;
                }
            }
            $likeSQL = '(' . implode(' OR ', $likeClauses) . ')';

            // Filtros de visibilidade — excluir produtos ocultos, inativos e arquivados
            $filtros = [];
            if ($temOculto) {
                $filtros[] = "(p.oculto IS NULL OR p.oculto = 0)";
            }
            if ($temActive) {
                $filtros[] = "(p.active IS NULL OR p.active = 1)";
            }
            if ($temAtivo) {
                $filtros[] = "(p.ativo IS NULL OR p.ativo = 1)";
            }
            if ($temStatus) {
                $filtros[] = "(p.status IS NULL OR LOWER(COALESCE(p.status,'')) NOT IN ('archived','deleted','trash','lixeira'))";
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
                
                // Sem estoque — produtos de grupo de compras nunca são marcados como sem estoque
                // (são comprados por demanda, não têm estoque fixo)
                $ehDeGrupo = !empty($p['grupo_slug']) || (!empty($p['grupo_compras_id']) && (int)$p['grupo_compras_id'] > 0);
                $p['sem_estoque'] = (!$ehDeGrupo && $p['stock'] !== null && $p['stock'] <= 0);
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
        
        // Impostos Brasil (Receita Federal)
        $valorAduaneiro = $precoUsd; // + frete + seguro (frete grátis, seguro ~0)
        // II: 60% com desconto de US$ 20 (Remessa Conforme acima de US$ 50)
        $ii = ($valorAduaneiro > 50) ? ($valorAduaneiro * 0.60 - 20) : ($valorAduaneiro * 0.20);
        if ($ii < 0) $ii = 0;
        // ICMS: cálculo "por dentro" 17%
        $baseIcms = $valorAduaneiro + $ii;
        $aliqIcms = 0.17;
        $bcIcms = $baseIcms / (1 - $aliqIcms);
        $icms = $bcIcms * $aliqIcms;
        $impostosBr = $ii + $icms;

        $totalUsd = $precoUsd + $impostoLocal + $taxaServico + $impostosBr;
        $cambio = (float) ($this->configs['cambio_usd_brl'] ?? 5.80);
        $totalBrl = $totalUsd * $cambio;

        return [
            'produto_usd' => round($precoUsd, 2),
            'imposto_local_usd' => round($impostoLocal, 2),
            'taxa_servico_usd' => round($taxaServico, 2),
            'ii_usd' => round($ii, 2),
            'icms_usd' => round($icms, 2),
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
        // Invalidar cache se muito antigo (forçar recarga)
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

            // Fix AUTO_INCREMENT if stuck at 0
            try {
                $maxId = (int) $this->pdo->query("SELECT COALESCE(MAX(id), 0) FROM copiloto_mensagens")->fetchColumn();
                $this->pdo->exec("ALTER TABLE copiloto_mensagens AUTO_INCREMENT = " . ($maxId + 1));
            } catch (\Exception $e) {}

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

    /**
     * Busca aprendizados aceitos pelo admin para incorporar no prompt
     */
    private function obterAprendizadosAceitos(): string {
        try {
            $st = $this->pdo->query("SELECT resumo_problema, texto_sugerido, topico_afetado, tipos, sugestao_melhoria FROM copiloto_aprendizado WHERE status = 'aceita' ORDER BY impacto_estimado ASC, criado_em DESC LIMIT 30");
            $rows = $st ? $st->fetchAll(\PDO::FETCH_ASSOC) : [];
            if (empty($rows)) return '';

            $linhas = ["APRENDIZADOS DA IA (aprovados pelo admin — use como referência):"];
            foreach ($rows as $i => $r) {
                $n = $i + 1;
                $linha = "[{$n}] ";
                if (!empty($r['topico_afetado'])) $linha .= "Tópico: {$r['topico_afetado']} | ";
                $linha .= "Problema: " . ($r['resumo_problema'] ?? 'N/A');
                if (!empty($r['texto_sugerido'])) $linha .= " | Resposta correta: {$r['texto_sugerido']}";
                if (!empty($r['sugestao_melhoria'])) $linha .= " | Melhoria: {$r['sugestao_melhoria']}";
                $linhas[] = $linha;
            }
            return implode("\n", $linhas);
        } catch (\Exception $e) {
            return '';
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

    // ========== ADMIN CHAT (respostas secas, mesma base de conhecimento) ==========

    public function chamarClaudeAdmin(string $mensagem, array $historico): array {
        $apiKey = $this->configs['api_key_claude'] ?? '';
        if (empty($apiKey)) {
            return ['texto' => 'API Key do Co-Piloto não configurada.', 'tokens_usados' => 0];
        }

        // Busca automática de produtos (igual ao cliente)
        $resultadoBusca = $this->buscarProdutoNoBanco($mensagem);
        $contexto = [];
        if (!empty($resultadoBusca)) {
            $contexto['_produtos_encontrados'] = $resultadoBusca;
        }
        // Contexto mínimo pra montar o prompt completo
        $contexto['pagina'] = 'admin_interno';
        $contexto['url_atual'] = '/admin';
        $contexto['usuario_logado'] = true;
        $contexto['moeda_atual'] = 'BRL';

        // Reutilizar o prompt completo do cliente (todas as regras, cálculos, base de conhecimento)
        $systemPrompt = $this->montarSystemPrompt($contexto, $mensagem);

        // Sobrescrever identidade e tom — manter todas as regras
        $adminOverride = "\n\nOVERRIDE DE IDENTIDADE (IGNORA A IDENTIDADE ACIMA):\n"
            . "Você NÃO é a Bri. Você é o assistente interno da equipe Braziliana (admin/suporte).\n"
            . "Respostas CURTAS e DIRETAS. Sem emojis, sem frufru. Português brasileiro.\n"
            . "NÃO execute ações de frontend (carrinho, checkout, navegação). Apenas responda com texto.\n"
            . "NÃO retorne JSON. Responda em texto puro.\n"
            . "Use todas as regras de negócio, cálculos e base de conhecimento normalmente.\n"
            . "Quando encontrar produtos no banco, mostre preço, peso e faça o cálculo completo de custos.\n";

        $systemPrompt .= $adminOverride;

        $messages = [];
        $maxHist = (int) ($this->configs['max_historico_enviado'] ?? 10);
        $histSlice = array_slice($historico, -$maxHist);
        foreach ($histSlice as $m) {
            $role = ($m['role'] ?? 'user') === 'assistant' ? 'assistant' : 'user';
            $messages[] = ['role' => $role, 'content' => (string) ($m['content'] ?? '')];
        }
        $messages[] = ['role' => 'user', 'content' => $mensagem];

        $payload = [
            'model' => self::MODELO,
            'max_tokens' => 800,
            'system' => $systemPrompt,
            'messages' => $messages,
        ];

        $timeout = (int) ($this->configs['timeout_claude_ms'] ?? 15000);
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
            error_log("[CoPiloto Admin] Claude API erro: HTTP $httpCode — curl: $curlError");
            return ['texto' => 'Erro ao consultar a IA. Tente novamente.', 'tokens_usados' => 0];
        }

        $data = json_decode($response, true);
        $textoResposta = trim($data['content'][0]['text'] ?? '');
        $tokensUsados = ($data['usage']['input_tokens'] ?? 0) + ($data['usage']['output_tokens'] ?? 0);

        // Se veio JSON (formato do cliente), extrair só o texto
        if (preg_match('/\{[\s\S]*"texto"\s*:\s*"([\s\S]*?)"/u', $textoResposta, $m)) {
            $textoExtraido = $m[1];
            // Unescape JSON string
            $textoExtraido = str_replace(['\\n', '\\"', '\\\\'], ["\n", '"', '\\'], $textoExtraido);
            if (mb_strlen($textoExtraido) > 10) {
                $textoResposta = $textoExtraido;
            }
        }

        return ['texto' => $textoResposta, 'tokens_usados' => $tokensUsados];
    }
}
