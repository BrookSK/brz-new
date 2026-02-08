<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Url;
use App\Models\Produto;
use App\Models\ProdutoFoto;
use App\Models\Carrinho;
use App\Services\AuthService;

class CarrinhoController extends Controller {
    private $produtoModel;
    private $produtoFotoModel;
    private $authService;
    private $carrinhoModel;

    private function tableExists(string $table): bool {
        try {
            $db = \Config\Database::getConnection();
            $stmt = $db->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1');
            $stmt->execute([$table]);
            return (bool) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            return false;
        }
    }

    private function getItemAtivoFromSession(string $itemKey): bool {
        try {
            if (!isset($_SESSION['carrinho_itens_ativos']) || !is_array($_SESSION['carrinho_itens_ativos'])) {
                $_SESSION['carrinho_itens_ativos'] = [];
            }
            if (array_key_exists($itemKey, $_SESSION['carrinho_itens_ativos'])) {
                return (bool) $_SESSION['carrinho_itens_ativos'][$itemKey];
            }
        } catch (\Throwable $e) {
        }
        return true;
    }

    private function setItemAtivoInSession(string $itemKey, bool $ativo): void {
        try {
            if (!isset($_SESSION['carrinho_itens_ativos']) || !is_array($_SESSION['carrinho_itens_ativos'])) {
                $_SESSION['carrinho_itens_ativos'] = [];
            }
            $_SESSION['carrinho_itens_ativos'][$itemKey] = $ativo ? 1 : 0;
        } catch (\Throwable $e) {
        }
    }

    private function getVariacaoInfo(int $produtoVariacaoId): ?array {
        if ($produtoVariacaoId <= 0) return null;
        if (!$this->tableExists('produto_variacoes')) return null;

        try {
            $db = \Config\Database::getConnection();
            $cols = [];
            try {
                $stCols = $db->query("DESCRIBE produto_variacoes");
                $cols = $stCols ? ($stCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            } catch (\Throwable $e) {
                $cols = [];
            }

            $hasAtivo = in_array('ativo', $cols, true);
            $select = 'id, produto_id, price_override, stock' . ($hasAtivo ? ', ativo' : '');
            $sql = 'SELECT ' . $select . ' FROM produto_variacoes WHERE id = ?' . ($hasAtivo ? ' AND ativo = 1' : '') . ' LIMIT 1';
            $st = $db->prepare($sql);
            $st->execute([$produtoVariacaoId]);
            $row = $st->fetch(\PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function getConfigValue(string $chave, $default = null) {
        try {
            $db = \Config\Database::getConnection();
            $stmt = $db->prepare('SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1');
            $stmt->execute([$chave]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row && array_key_exists('valor', $row)) {
                return $row['valor'];
            }
        } catch (\Exception $e) {
        }
        return $default;
    }

    private function calcularFrete(float $subtotal, float $pesoTotal, string $moeda = 'USD'): float {
        $calcularAutomatico = $this->getConfigValue('entrega_calcular_automatico', '1');
        $calcularAutomatico = ($calcularAutomatico === '1' || strtolower((string) $calcularAutomatico) === 'true');
        if (!$calcularAutomatico) {
            return 0.0;
        }

        $freteGratisAcima = floatval($this->getConfigValue('entrega_frete_gratis_acima', '0'));
        if ($freteGratisAcima <= 0 || $subtotal >= $freteGratisAcima) {
            return 0.0;
        }

        $fretePorKg = floatval($this->getConfigValue('entrega_frete_padrao', '15'));
        if ($fretePorKg <= 0) {
            return 0.0;
        }

        $pesoArredondado = ceil($pesoTotal);
        return $fretePorKg * $pesoArredondado;
    }

    private function debugLog(string $message): void {
        $enabled = false;
        if (isset($_ENV['APP_DEBUG'])) {
            $enabled = ($_ENV['APP_DEBUG'] === '1' || strtolower((string) $_ENV['APP_DEBUG']) === 'true');
        } elseif (isset($_SERVER['APP_DEBUG'])) {
            $enabled = ($_SERVER['APP_DEBUG'] === '1' || strtolower((string) $_SERVER['APP_DEBUG']) === 'true');
        }

        if ($enabled) {
            error_log($message);
        }
    }

    public function __construct() {
        $this->produtoModel = new Produto();
        $this->produtoFotoModel = new ProdutoFoto();
        $this->authService = new AuthService();
        $this->carrinhoModel = new Carrinho();
    }

    private function getLoggedUserId(): int {
        $u = $this->authService->getUsuarioLogado();
        $uid = (int) ($u['id'] ?? 0);
        return $uid > 0 ? $uid : 0;
    }

    private function getCarrinhoFromDb(int $usuarioId): array {
        if ($usuarioId <= 0) return [];
        try {
            $cart = $this->carrinhoModel->getOrCreateCarrinho($usuarioId, null, 'BRL');
            $cartId = is_array($cart) ? (int) ($cart['id'] ?? 0) : (int) $cart;
            if ($cartId <= 0) return [];

            $items = $this->carrinhoModel->getItems($cartId);
            $out = [];
            foreach (($items ?: []) as $it) {
                $pid = (int) ($it['produto_id'] ?? 0);
                if ($pid <= 0) continue;
                $pvId = (int) ($it['produto_variacao_id'] ?? 0);
                $key = ((string) $pid) . ':' . ((string) $pvId);
                $qtd = (int) ($it['quantidade'] ?? 1);
                if ($qtd < 1) $qtd = 1;
                $vu = (float) ($it['valor_unitario'] ?? 0);
                $sub = (float) ($it['subtotal'] ?? ($vu * $qtd));
                $out[$key] = [
                    'produto_id' => $pid,
                    'produto_variacao_id' => ($pvId > 0 ? $pvId : null),
                    'variacao_descricao' => $it['variacao_descricao'] ?? null,
                    'nome' => $it['nome'] ?? null,
                    'price' => $vu,
                    'preco_unitario' => $vu,
                    'quantidade' => $qtd,
                    'subtotal' => $sub,
                ];
            }
            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function index(Request $request) {
        session_start();

        $uid = $this->getLoggedUserId();
        $cartId = 0;
        $carrinho = [];
        if ($uid > 0) {
            try {
                $cart = $this->carrinhoModel->getOrCreateCarrinho($uid, null, 'BRL');
                $cartId = is_array($cart) ? (int) ($cart['id'] ?? 0) : (int) $cart;
            } catch (\Throwable $e) {
                $cartId = 0;
            }
            $carrinho = $this->getCarrinhoFromDb($uid);
        }
        if (empty($carrinho)) {
            $carrinho = $_SESSION['carrinho'] ?? [];
        }
        
        if (empty($carrinho)) {
            $this->view('carrinho/vazio');
            return;
        }
        
        // Obter detalhes completos dos produtos
        $produtosDetalhados = [];
        $subtotal = 0;
        $pesoTotal = 0;
        $totalItensAtivos = 0;

        $pesoClubeTotal = 0.0;
        $subtotalClube = 0.0;
        $descontoClube = 0.0;
        $cashbackClubeEstimado = 0.0;

        $removedExpired = false;
        
        foreach ($carrinho as $k => $item) {
            $this->debugLog('[CARRINHO] Processando item: ' . json_encode($item));
            
            $produto = $this->produtoModel->find($item['produto_id']);
            
            if ($produto) {
                $this->debugLog('[CARRINHO] Produto encontrado: ' . json_encode($produto));
                
                $fotoPrincipal = $this->produtoFotoModel->getFotoPrincipal($produto['id']);
                
                // Verificar e corrigir URL da foto
                $fotoUrl = null;
                if ($fotoPrincipal && !empty($fotoPrincipal['nome_arquivo'])) {
                    $filePath = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($fotoPrincipal['nome_arquivo'], '/');
                    if (file_exists($filePath)) {
                        $fotoUrl = Url::absolute($fotoPrincipal['nome_arquivo']);
                    }
                }
                
                // Usar campos mapeados do Model (com override da variação, quando existir)
                $pvId = (int) ($item['produto_variacao_id'] ?? 0);
                $itemPrice = floatval($produto['preco'] ?? 0);
                $itemStock = intval($produto['estoque'] ?? 0);
                if ($pvId > 0) {
                    $infoVar = $this->getVariacaoInfo($pvId);
                    if ($infoVar) {
                        $ov = $infoVar['price_override'] ?? null;
                        if ($ov !== null && $ov !== '' && floatval($ov) > 0) {
                            $itemPrice = floatval($ov);
                        }
                        if (array_key_exists('stock', $infoVar) && $infoVar['stock'] !== null && $infoVar['stock'] !== '') {
                            $itemStock = (int) $infoVar['stock'];
                        }
                    }
                }

                $itemSubtotal = ((int) ($item['quantidade'] ?? 0)) * $itemPrice;

                $pesoUnit = (float) ($produto['peso'] ?? 0.5);
                if ($pesoUnit <= 0) {
                    $pesoUnit = 0.5;
                }
                $pesoItem = ((int) ($item['quantidade'] ?? 0)) * $pesoUnit;

                $ativo = $this->getItemAtivoFromSession((string) $k);
                // Se não existir flag ainda, inicializar como ativo
                $this->setItemAtivoInSession((string) $k, (bool) $ativo);

                // Normalizar sessão para refletir os valores corretos na view/resumo
                if (isset($_SESSION['carrinho'][$k]) && is_array($_SESSION['carrinho'][$k])) {
                    $_SESSION['carrinho'][$k]['price'] = $itemPrice;
                    $_SESSION['carrinho'][$k]['preco_unitario'] = $itemPrice;
                    $_SESSION['carrinho'][$k]['subtotal'] = $itemSubtotal;
                    $_SESSION['carrinho'][$k]['peso_unit'] = $pesoUnit;
                    $_SESSION['carrinho'][$k]['peso_item'] = $pesoItem;
                    $_SESSION['carrinho'][$k]['ativo'] = $ativo ? 1 : 0;
                }

                // Normalizar o carrinho local usado pela view
                if (isset($carrinho[$k]) && is_array($carrinho[$k])) {
                    $carrinho[$k]['price'] = $itemPrice;
                    $carrinho[$k]['preco_unitario'] = $itemPrice;
                    $carrinho[$k]['subtotal'] = $itemSubtotal;
                    $carrinho[$k]['peso_unit'] = $pesoUnit;
                    $carrinho[$k]['peso_item'] = $pesoItem;
                    $carrinho[$k]['ativo'] = $ativo ? 1 : 0;
                }
                
                $this->debugLog('[CARRINHO] Preco: ' . $itemPrice . ', Quantidade: ' . $item['quantidade'] . ', Subtotal: ' . $itemSubtotal);
                
                $produtosDetalhados[] = [
                    'produto_id' => $item['produto_id'],
                    'sku' => $produto['sku'],
                    'name' => $produto['nome'],
                    'description' => $produto['descricao'],
                    'price' => $itemPrice,
                    'weight' => $pesoUnit,
                    'quantidade' => $item['quantidade'],
                    'subtotal' => $itemSubtotal,
                    'foto_principal' => $fotoUrl,
                    'stock' => $itemStock,
                    'ativo' => $ativo ? 1 : 0,
                ];

                if ($ativo) {
                    $subtotal += $itemSubtotal;
                    $pesoTotal += $pesoItem;
                    $totalItensAtivos += (int) ($item['quantidade'] ?? 0);
                }
            } else {
                $this->debugLog('[CARRINHO] Produto nao encontrado: ' . $item['produto_id']);

                // Produto expirou/foi removido (ex.: temporário da assessoria). Remove do carrinho.
                if (isset($_SESSION['carrinho'][$k])) {
                    unset($_SESSION['carrinho'][$k]);
                    $removedExpired = true;
                }
            }
        }

        if ($removedExpired) {
            $_SESSION['message'] = 'Alguns itens do carrinho expiraram e foram removidos. Se eram itens da Assessoria, reprocessse o orçamento para gerar novos valores e produtos.';
            $_SESSION['message_type'] = 'warning';

            $carrinho = $_SESSION['carrinho'] ?? [];
            if (empty($carrinho)) {
                $this->view('carrinho/vazio');
                return;
            }
        }
        
        // Calcular taxas e impostos com arredondamento (somente itens ativos)
        $pesoMaxKg = 30.0;
        $excedePeso = ((float) $pesoTotal) > $pesoMaxKg + 0.00001;

        $pesoArredondado = ceil($pesoTotal); // Arredondar para cima

        $frete = $this->calcularFrete($subtotal, $pesoTotal, 'USD');

        // Mesma regra do checkout (Model Carrinho): taxa por kg configurada + impostos (Receita Federal)
        $taxaServico = (float) $this->carrinhoModel->calcularTaxaServico($pesoTotal, 'USD', 1.0);
        $impostos = (float) $this->carrinhoModel->calcularImpostos($subtotal, $frete);
        
        $total = $subtotal + $taxaServico + $impostos + $frete;

        // Se o carrinho estiver no DB, usar os totais persistidos (inclui desconto/cashback do Clube)
        if ($uid > 0 && $cartId > 0) {
            try {
                $db = $this->carrinhoModel->getConnection();
                $cols = [];
                try {
                    $stCols = $db->query('DESCRIBE carrinhos');
                    $cols = $stCols ? ($stCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
                } catch (\Throwable $e) {
                    $cols = [];
                }

                $st = $db->prepare('SELECT * FROM carrinhos WHERE id = ? LIMIT 1');
                $st->execute([$cartId]);
                $row = $st->fetch(\PDO::FETCH_ASSOC) ?: [];

                if (!empty($row)) {
                    if (array_key_exists('subtotal_produtos', $row) && $row['subtotal_produtos'] !== null && $row['subtotal_produtos'] !== '' && (float) $row['subtotal_produtos'] > 0) {
                        $subtotal = (float) $row['subtotal_produtos'];
                    }
                    if (array_key_exists('peso_total', $row) && $row['peso_total'] !== null && $row['peso_total'] !== '' && (float) $row['peso_total'] > 0) {
                        $pesoTotal = (float) $row['peso_total'];
                    }
                    if (array_key_exists('taxa_servico', $row) && $row['taxa_servico'] !== null && $row['taxa_servico'] !== '' && (float) $row['taxa_servico'] >= 0) {
                        // taxa pode ser 0 em alguns cenários, mas não deve apagar cálculo se DB vier vazio
                        $taxaServico = (float) $row['taxa_servico'];
                    }
                    if (array_key_exists('valor_impostos', $row) && $row['valor_impostos'] !== null && $row['valor_impostos'] !== '' && (float) $row['valor_impostos'] >= 0) {
                        $impostos = (float) $row['valor_impostos'];
                    }
                    if (array_key_exists('valor_total', $row) && $row['valor_total'] !== null && $row['valor_total'] !== '' && (float) $row['valor_total'] > 0) {
                        $total = (float) $row['valor_total'];
                    }
                    if (array_key_exists('subtotal_produtos', $row)) $subtotal = (float) ($row['subtotal_produtos'] ?? $subtotal);
                    if (array_key_exists('peso_total', $row)) $pesoTotal = (float) ($row['peso_total'] ?? $pesoTotal);
                    if (array_key_exists('taxa_servico', $row)) {
                        $v = (float) ($row['taxa_servico'] ?? 0);
                        if ($v > 0 || ((float) $taxaServico) <= 0) {
                            $taxaServico = $v;
                        }
                    }
                    if (array_key_exists('valor_impostos', $row)) {
                        $v = (float) ($row['valor_impostos'] ?? 0);
                        if ($v > 0 || ((float) $impostos) <= 0) {
                            $impostos = $v;
                        }
                    }
                    if (array_key_exists('valor_total', $row)) {
                        $v = (float) ($row['valor_total'] ?? 0);
                        if ($v > 0 || ((float) $total) <= 0) {
                            $total = $v;
                        }
                    }

                    if (array_key_exists('frete_manual', $row) && $row['frete_manual'] !== null && $row['frete_manual'] !== '') {
                        $frete = (float) $row['frete_manual'];
                    }

                    if (is_array($cols) && in_array('peso_clube_total', $cols, true)) {
                        $pesoClubeTotal = (float) ($row['peso_clube_total'] ?? 0);
                    }
                    if (is_array($cols) && in_array('subtotal_clube', $cols, true)) {
                        $subtotalClube = (float) ($row['subtotal_clube'] ?? 0);
                    }
                    if (is_array($cols) && in_array('desconto_clube', $cols, true)) {
                        $descontoClube = (float) ($row['desconto_clube'] ?? 0);
                    }
                    if (is_array($cols) && in_array('cashback_clube_estimado', $cols, true)) {
                        $cashbackClubeEstimado = (float) ($row['cashback_clube_estimado'] ?? 0);
                    }
                }
            } catch (\Throwable $e) {
            }
        }
        
        $this->view('carrinho/index', [
            'carrinho' => $carrinho,
            'produtosDetalhados' => $produtosDetalhados,
            'subtotal' => $subtotal,
            'peso_clube_total' => $pesoClubeTotal,
            'subtotal_clube' => $subtotalClube,
            'desconto_clube' => $descontoClube,
            'cashback_clube_estimado' => $cashbackClubeEstimado,
            'taxa_servico' => $taxaServico,
            'impostos' => $impostos,
            'frete' => $frete,
            'peso_total' => $pesoTotal,
            'total' => $total,
            'total_itens' => $totalItensAtivos,
            'peso_max_kg' => $pesoMaxKg,
            'excede_peso' => $excedePeso,
        ]);
    }

    public function toggleAtivo(Request $request) {
        session_start();

        $itemKey = trim((string) ($request->getParam('id') ?? ''));
        $ativo = $request->getParam('ativo', null);
        if ($itemKey === '' || $ativo === null) {
            $this->json(['success' => false, 'error' => 'Parâmetros inválidos'], 400);
            return;
        }

        $ativoBool = ((string) $ativo === '1' || (string) $ativo === 'true' || (int) $ativo === 1);
        $this->setItemAtivoInSession($itemKey, $ativoBool);
        $this->json(['success' => true, 'id' => $itemKey, 'ativo' => $ativoBool ? 1 : 0]);
    }

    public function checkout(Request $request) {
        session_start();

        $pesoTotal = 0.0;
        $uid = $this->getLoggedUserId();
        $carrinho = [];
        if ($uid > 0) {
            $carrinho = $this->getCarrinhoFromDb($uid);
        }
        if (empty($carrinho)) {
            $carrinho = $_SESSION['carrinho'] ?? [];
        }

        if (empty($carrinho)) {
            header('Location: /carrinho');
            exit;
        }

        foreach ($carrinho as $k => $item) {
            $ativo = $this->getItemAtivoFromSession((string) $k);
            if (!$ativo) {
                continue;
            }
            try {
                $produto = $this->produtoModel->find((int) ($item['produto_id'] ?? 0));
                $pesoUnit = (float) ($produto['peso'] ?? 0.5);
                if ($pesoUnit <= 0) {
                    $pesoUnit = 0.5;
                }
                $pesoTotal += ((int) ($item['quantidade'] ?? 0)) * $pesoUnit;
            } catch (\Throwable $e) {
            }
        }

        if ($pesoTotal > 30.0 + 0.00001) {
            $_SESSION['message'] = 'Peso máximo do carrinho é 30kg. Desative alguns itens para continuar.';
            $_SESSION['message_type'] = 'warning';
            header('Location: /carrinho');
            exit;
        }

        $_SESSION['checkout_from_cart_at'] = time();
        header('Location: /checkout');
        exit;
    }

    public function adicionar(Request $request) {
        $this->debugLog('=== DEPURACAO CARRINHO ADICIONAR ===');
        $this->debugLog('Metodo: ' . $request->getMethod());
        $this->debugLog('Parametros recebidos: ' . json_encode($request->getParams()));
        
        $produtoId = $request->getParam('id');
        $quantidade = $request->getParam('quantidade', 1);
        $produtoVariacaoId = $request->getParam('produto_variacao_id', null);
        $variacaoDescricao = $request->getParam('variacao_descricao', null);
        
        $this->debugLog("Produto ID: $produtoId");
        $this->debugLog("Quantidade: $quantidade");
        
        if (!$produtoId) {
            $this->debugLog('ERRO: Produto nao informado');
            $this->json(['error' => 'Produto não informado'], 400);
            return;
        }
        
        session_start();

        $uid = $this->getLoggedUserId();

        $pvId = null;
        if ($produtoVariacaoId !== null && $produtoVariacaoId !== '') {
            $pvId = (int) $produtoVariacaoId;
            if ($pvId <= 0) {
                $pvId = null;
            }
        }
        
        // Buscar produto
        $produto = $this->produtoModel->find($produtoId);
        
        
        if (!$produto) {
            $this->debugLog('ERRO: Produto nao encontrado no banco');
            $this->json(['error' => 'Produto não encontrado'], 404);
            return;
        }
        
        $produtoStock = intval($produto['estoque'] ?? 0);
        if ($produtoStock < $quantidade) {
            $this->debugLog('ERRO: Estoque insuficiente. Estoque: ' . $produtoStock . ', Quantidade: ' . $quantidade);
            $this->json(['error' => 'Estoque insuficiente'], 400);
            return;
        }
        
        if ($uid > 0) {
            try {
                $cart = $this->carrinhoModel->getOrCreateCarrinho($uid, null, 'BRL');
                $cartId = is_array($cart) ? (int) ($cart['id'] ?? 0) : (int) $cart;
                $ok = $this->carrinhoModel->adicionarItem($cartId, (int) $produtoId, (int) $quantidade, ($pvId ?? null), ($variacaoDescricao !== null && $variacaoDescricao !== '' ? (string) $variacaoDescricao : null));
                if (!$ok) {
                    $this->json(['error' => 'Não foi possível adicionar o item ao carrinho'], 400);
                    return;
                }

                $stCnt = $this->carrinhoModel->getConnection()->prepare('SELECT COALESCE(SUM(quantidade),0) FROM carrinho_items WHERE carrinho_id = ?');
                $stCnt->execute([$cartId]);
                $totalItens = (int) ($stCnt->fetchColumn() ?: 0);

                $stTot = $this->carrinhoModel->getConnection()->prepare('SELECT valor_total FROM carrinhos WHERE id = ? LIMIT 1');
                $stTot->execute([$cartId]);
                $totalValor = (float) ($stTot->fetchColumn() ?: 0);

                $this->json([
                    'success' => true,
                    'message' => 'Produto adicionado ao carrinho',
                    'total_itens' => $totalItens,
                    'total_valor' => $totalValor
                ]);
                return;
            } catch (\Throwable $e) {
                // fallback session
            }
        }

        if (!isset($_SESSION['carrinho'])) {
            $_SESSION['carrinho'] = [];
            $this->debugLog('Criando array de carrinho vazio');
        }

        $itemKey = ((string) $produtoId) . ':' . ((string) ($pvId ?? 0));
        
        $itemPrice = floatval($produto['preco'] ?? $produto['valor'] ?? 0);
        if ($pvId !== null && $pvId > 0) {
            $infoVar = $this->getVariacaoInfo($pvId);
            if ($infoVar) {
                $ov = $infoVar['price_override'] ?? null;
                if ($ov !== null && $ov !== '' && floatval($ov) > 0) {
                    $itemPrice = floatval($ov);
                }
            }
        }
        
        if (isset($_SESSION['carrinho'][$itemKey])) {
            $_SESSION['carrinho'][$itemKey]['quantidade'] += $quantidade;
            $_SESSION['carrinho'][$itemKey]['subtotal'] = $_SESSION['carrinho'][$itemKey]['quantidade'] * $itemPrice;
            $_SESSION['carrinho'][$itemKey]['price'] = $itemPrice;
            $_SESSION['carrinho'][$itemKey]['preco_unitario'] = $itemPrice;
            $this->debugLog('Atualizando item existente no carrinho');
        } else {
            $_SESSION['carrinho'][$itemKey] = [
                'produto_id' => $produtoId,
                'produto_variacao_id' => $pvId,
                'variacao_descricao' => ($variacaoDescricao !== null && $variacaoDescricao !== '' ? (string) $variacaoDescricao : null),
                'nome' => $produto['nome'],
                'price' => $itemPrice,
                'preco_unitario' => $itemPrice,
                'quantidade' => $quantidade,
                'subtotal' => $quantidade * $itemPrice
            ];
            $this->debugLog('Adicionando novo item ao carrinho');
        }
        
        $this->debugLog('Carrinho atual: ' . json_encode($_SESSION['carrinho']));
        
        $totalItens = array_sum(array_column($_SESSION['carrinho'], 'quantidade'));
        $totalValor = 0;
        foreach ($_SESSION['carrinho'] as $item) {
            $totalValor += floatval($item['subtotal']);
        }
        
        $this->debugLog("Total itens: $totalItens");
        $this->debugLog("Total valor: $totalValor");
        
        $response = [
            'success' => true,
            'message' => 'Produto adicionado ao carrinho',
            'carrinho' => $_SESSION['carrinho'],
            'total_itens' => $totalItens,
            'total_valor' => $totalValor
        ];
        
        $this->debugLog('Resposta JSON: ' . json_encode($response));
        
        $this->json($response);
    }

    public function remover(Request $request) {
        session_start();
        $produtoId = $request->getParam('id', null);
        $produtoIdFallback = $request->getParam('produto_id', null);
        $produtoIdFallback = ($produtoIdFallback !== null ? trim((string) $produtoIdFallback) : null);
        $produtoId = ($produtoId !== null ? trim((string) $produtoId) : null);

        // Se vier um índice (ex: "0") mas existe produto_id, preferir o produto_id.
        if ($produtoId !== null && $produtoId !== '' && $produtoIdFallback !== null && $produtoIdFallback !== '') {
            $looksLikeIndex = ctype_digit($produtoId) && strpos((string) $produtoIdFallback, ':') !== false;
            if ($looksLikeIndex) {
                $produtoId = $produtoIdFallback;
            }
        }
        
        if (($produtoId === null || $produtoId === '') && ($produtoIdFallback === null || $produtoIdFallback === '')) {
            $this->json(['error' => 'Produto não informado'], 400);
            return;
        }

        if (($produtoId === null || $produtoId === '') && ($produtoIdFallback !== null && $produtoIdFallback !== '')) {
            $produtoId = $produtoIdFallback;
        }

        $uid = $this->getLoggedUserId();
        if ($uid > 0) {
            try {
                $pvId = null;
                if (is_string($produtoId) && strpos($produtoId, ':') !== false) {
                    $parts = explode(':', $produtoId);
                    $produtoIdDb = (int) ($parts[0] ?? 0);
                    $pvTmp = (int) ($parts[1] ?? 0);
                    if ($pvTmp > 0) $pvId = $pvTmp;
                } else {
                    $produtoIdDb = (int) $produtoId;
                }

                $cart = $this->carrinhoModel->getOrCreateCarrinho($uid, null, 'BRL');
                $cartId = is_array($cart) ? (int) ($cart['id'] ?? 0) : (int) $cart;
                $this->carrinhoModel->removerItem($cartId, (int) $produtoIdDb, $pvId);

                $stCnt = $this->carrinhoModel->getConnection()->prepare('SELECT COALESCE(SUM(quantidade),0) FROM carrinho_items WHERE carrinho_id = ?');
                $stCnt->execute([$cartId]);
                $totalItens = (int) ($stCnt->fetchColumn() ?: 0);

                $stTot = $this->carrinhoModel->getConnection()->prepare('SELECT valor_total FROM carrinhos WHERE id = ? LIMIT 1');
                $stTot->execute([$cartId]);
                $totalValor = (float) ($stTot->fetchColumn() ?: 0);

                $this->json([
                    'success' => true,
                    'message' => 'Produto removido do carrinho',
                    'total_itens' => $totalItens,
                    'total_valor' => $totalValor
                ]);
                return;
            } catch (\Throwable $e) {
                // fallback session
            }
        }
        
        $keyToRemove = null;
        if (isset($_SESSION['carrinho'][$produtoId])) {
            $keyToRemove = $produtoId;
        } else {
            foreach (($_SESSION['carrinho'] ?? []) as $k => $item) {
                if (is_array($item) && array_key_exists('produto_id', $item) && (string) $item['produto_id'] === (string) $produtoId) {
                    $keyToRemove = $k;
                    break;
                }
            }
        }

        if ($keyToRemove !== null) {
            unset($_SESSION['carrinho'][$keyToRemove]);
            
            $totalItens = array_sum(array_column($_SESSION['carrinho'], 'quantidade'));
            $totalValor = 0;
            foreach ($_SESSION['carrinho'] as $item) {
                $totalValor += floatval($item['subtotal']);
            }
            
            $this->json([
                'success' => true,
                'message' => 'Produto removido do carrinho',
                'total_itens' => $totalItens,
                'total_valor' => $totalValor
            ]);
        } else {
            $this->json(['error' => 'Produto não encontrado no carrinho'], 404);
        }
    }

    public function atualizar(Request $request) {
        session_start();
        $produtoId = $request->getParam('id', null);
        $produtoIdFallback = $request->getParam('produto_id', null);
        $produtoIdFallback = ($produtoIdFallback !== null ? trim((string) $produtoIdFallback) : null);
        $produtoId = ($produtoId !== null ? trim((string) $produtoId) : null);
        $quantidade = $request->getParam('quantidade', null);

        // Se vier um índice (ex: "0") mas existe produto_id, preferir o produto_id.
        if ($produtoId !== null && $produtoId !== '' && $produtoIdFallback !== null && $produtoIdFallback !== '') {
            $looksLikeIndex = ctype_digit($produtoId) && strpos((string) $produtoIdFallback, ':') !== false;
            if ($looksLikeIndex) {
                $produtoId = $produtoIdFallback;
            }
        }
        
        if ((($produtoId === null || $produtoId === '') && ($produtoIdFallback === null || $produtoIdFallback === '')) || ($quantidade === null || $quantidade === '')) {
            $this->json(['error' => 'Dados incompletos'], 400);
            return;
        }

        if (($produtoId === null || $produtoId === '') && ($produtoIdFallback !== null && $produtoIdFallback !== '')) {
            $produtoId = $produtoIdFallback;
        }

        $quantidade = (int) $quantidade;
        if ($quantidade < 1) {
            $this->json(['error' => 'Quantidade inválida'], 400);
            return;
        }
        
        $itemKey = null;
        $produtoIdDb = $produtoId;
        if (isset($_SESSION['carrinho'][$produtoId])) {
            // Quando vier a chave real do item (ex: "123:VAR"), atualizar por ela
            $itemKey = $produtoId;
            $produtoIdDb = (string) ($_SESSION['carrinho'][$itemKey]['produto_id'] ?? $produtoId);
        } else {
            foreach (($_SESSION['carrinho'] ?? []) as $k => $item) {
                if (is_array($item) && array_key_exists('produto_id', $item) && (string) $item['produto_id'] === (string) $produtoId) {
                    $itemKey = $k;
                    $produtoIdDb = (string) ($item['produto_id'] ?? $produtoId);
                    break;
                }
            }
        }

        if ($itemKey !== null) {
            $produto = $this->produtoModel->find($produtoIdDb);
            
            $produtoStock = intval($produto['estoque'] ?? 0);
            if ($produtoStock < $quantidade) {
                $this->json(['error' => 'Estoque insuficiente'], 400);
                return;
            }

            // Garantir preço numérico (com override da variação, quando existir)
            $itemPrice = 0.0;
            if ($produto) {
                $itemPrice = floatval($produto['preco'] ?? $produto['valor'] ?? 0);
            }
            $pvId = (int) ($_SESSION['carrinho'][$itemKey]['produto_variacao_id'] ?? 0);
            if ($pvId > 0) {
                $infoVar = $this->getVariacaoInfo($pvId);
                if ($infoVar) {
                    $ov = $infoVar['price_override'] ?? null;
                    if ($ov !== null && $ov !== '' && floatval($ov) > 0) {
                        $itemPrice = floatval($ov);
                    }
                }
            }
            if ($itemPrice <= 0) {
                $itemPrice = floatval($_SESSION['carrinho'][$itemKey]['price'] ?? $_SESSION['carrinho'][$itemKey]['preco_unitario'] ?? 0);
            }

            $_SESSION['carrinho'][$itemKey]['quantidade'] = $quantidade;
            $_SESSION['carrinho'][$itemKey]['price'] = $itemPrice;
            $_SESSION['carrinho'][$itemKey]['preco_unitario'] = $itemPrice;
            $_SESSION['carrinho'][$itemKey]['subtotal'] = $quantidade * $itemPrice;
            
            $totalItens = array_sum(array_column($_SESSION['carrinho'], 'quantidade'));
            $totalValor = 0;
            foreach ($_SESSION['carrinho'] as $item) {
                $totalValor += floatval($item['subtotal']);
            }
            
            $this->json([
                'success' => true,
                'message' => 'Carrinho atualizado',
                'item_subtotal' => $_SESSION['carrinho'][$itemKey]['subtotal'],
                'total_itens' => $totalItens,
                'total_valor' => $totalValor
            ]);
        } else {
            $this->json(['error' => 'Produto não encontrado no carrinho'], 404);
        }
    }

    public function limpar(Request $request) {
        session_start();
        $uid = $this->getLoggedUserId();
        if ($uid > 0) {
            try {
                $cart = $this->carrinhoModel->getOrCreateCarrinho($uid, null, 'BRL');
                $cartId = is_array($cart) ? (int) ($cart['id'] ?? 0) : (int) $cart;
                if ($cartId > 0) {
                    $this->carrinhoModel->limparCarrinho($cartId);
                }
                $this->json([
                    'success' => true,
                    'message' => 'Carrinho limpo com sucesso'
                ]);
                return;
            } catch (\Throwable $e) {
            }
        }

        unset($_SESSION['carrinho']);
        
        $this->json([
            'success' => true,
            'message' => 'Carrinho limpo com sucesso'
        ]);
    }

    public function calcular(Request $request) {
        session_start();

        $uid = $this->getLoggedUserId();
        $carrinho = [];
        if ($uid > 0) {
            $carrinho = $this->getCarrinhoFromDb($uid);
        }
        if (empty($carrinho)) {
            $carrinho = $_SESSION['carrinho'] ?? [];
        }
        
        if (empty($carrinho)) {
            $this->json(['error' => 'Carrinho vazio'], 400);
            return;
        }
        
        // Calcular peso total
        $pesoTotal = 0;
        foreach ($carrinho as $item) {
            $produto = $this->produtoModel->find($item['produto_id']);
            if ($produto) {
                $pesoTotal += floatval($produto['peso'] ?? 0.5) * $item['quantidade'];
            }
        }
        
        // Arredondar peso para cima (ex: 1.7kg → 2kg)
        $pesoArredondado = ceil($pesoTotal);

        // Calcular subtotal
        $subtotal = 0;
        foreach ($carrinho as $item) {
            $subtotal += floatval($item['subtotal']);
        }
        
        // Taxas fixas
        $taxaServico = (float) $this->carrinhoModel->calcularTaxaServico($pesoTotal, 'USD', 1.0);
        
        // Frete baseado no peso arredondado
        $frete = $this->calcularFrete($subtotal, $pesoTotal, 'USD');
        
        // Impostos (Receita Federal)
        $impostos = (float) $this->carrinhoModel->calcularImpostos($subtotal, $frete);
        
        // Total
        $total = $subtotal + $taxaServico + $impostos + $frete;
        
        $this->json([
            'peso_total' => $pesoTotal,
            'peso_arredondado' => $pesoArredondado,
            'taxa_servico' => $taxaServico,
            'frete' => $frete,
            'impostos' => $impostos,
            'subtotal' => $subtotal,
            'total' => $total
        ]);
    }
}
