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

    private function getUserCartIdPreferNonEmpty(int $usuarioId): int {
        if ($usuarioId <= 0) return 0;
        try {
            $db = $this->carrinhoModel->getConnection();
            $st = $db->prepare('SELECT id FROM carrinhos WHERE usuario_id = ? ORDER BY created_at DESC LIMIT 10');
            $st->execute([$usuarioId]);
            $ids = $st->fetchAll(\PDO::FETCH_COLUMN) ?: [];
            $ids = array_values(array_filter(array_map('intval', $ids)));
            if (empty($ids)) {
                return 0;
            }

            foreach ($ids as $cid) {
                try {
                    $stCnt = $db->prepare('SELECT COALESCE(SUM(quantidade),0) FROM carrinho_items WHERE carrinho_id = ?');
                    $stCnt->execute([$cid]);
                    $cnt = (int) ($stCnt->fetchColumn() ?: 0);
                    if ($cnt > 0) {
                        return $cid;
                    }
                } catch (\Throwable $e) {
                }
            }

            // fallback: carrinho mais recente (mesmo vazio)
            return (int) $ids[0];
        } catch (\Throwable $e) {
            return 0;
        }
    }

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

    private function hydrateCartFromCookie(): void {
        try {
            if (!empty($_SESSION['carrinho']) || empty($_COOKIE['guest_cart'])) {
                return;
            }
            $raw = (string) $_COOKIE['guest_cart'];
            $decoded = base64_decode($raw, true);
            if ($decoded === false || $decoded === '') {
                return;
            }
            $arr = json_decode($decoded, true);
            if (is_array($arr) && !empty($arr)) {
                $_SESSION['carrinho'] = $arr;
            }
        } catch (\Throwable $e) {
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
            $cartId = (int) $this->getUserCartIdPreferNonEmpty($usuarioId);
            if ($cartId <= 0) {
                $cart = $this->carrinhoModel->getOrCreateCarrinho($usuarioId, null, 'BRL');
                $cartId = is_array($cart) ? (int) ($cart['id'] ?? 0) : (int) $cart;
                if ($cartId <= 0) return [];
            }

            $db = $this->carrinhoModel->getConnection();

            $cols = [];
            try {
                $stCols = $db->query('DESCRIBE carrinho_items');
                $cols = $stCols ? ($stCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            } catch (\Throwable $e) {
                $cols = [];
            }

            $unitCol = (is_array($cols) && in_array('preco_unitario', $cols, true)) ? 'preco_unitario' : 'valor_unitario';
            $varCol = (is_array($cols) && in_array('produto_variacao_id', $cols, true))
                ? 'produto_variacao_id'
                : ((is_array($cols) && in_array('variacao_id', $cols, true)) ? 'variacao_id' : 'produto_variacao_id');

            $st = $db->prepare('SELECT *, ' . $unitCol . ' AS unit_price, ' . $varCol . ' AS var_id FROM carrinho_items WHERE carrinho_id = ?');
            $st->execute([$cartId]);
            $items = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            $out = [];
            foreach (($items ?: []) as $it) {
                $pid = (int) ($it['produto_id'] ?? 0);
                if ($pid <= 0) continue;
                $pvId = (int) ($it['var_id'] ?? ($it['produto_variacao_id'] ?? ($it['variacao_id'] ?? 0)));
                $key = ((string) $pid) . ':' . ((string) $pvId);
                $qtd = (int) ($it['quantidade'] ?? 1);
                if ($qtd < 1) $qtd = 1;
                $vu = (float) ($it['unit_price'] ?? ($it['valor_unitario'] ?? ($it['preco_unitario'] ?? 0)));
                $sub = (float) ($it['subtotal'] ?? ($vu * $qtd));
                $priceSnap = null;
                if (array_key_exists('preco_unit_snapshot', $it) && $it['preco_unit_snapshot'] !== null && $it['preco_unit_snapshot'] !== '') {
                    $priceSnap = (float) $it['preco_unit_snapshot'];
                }
                $weightSnap = null;
                if (array_key_exists('peso_unit_snapshot', $it) && $it['peso_unit_snapshot'] !== null && $it['peso_unit_snapshot'] !== '') {
                    $weightSnap = (float) $it['peso_unit_snapshot'];
                }
                $out[$key] = [
                    'produto_id' => $pid,
                    'produto_variacao_id' => ($pvId > 0 ? $pvId : null),
                    'variacao_descricao' => $it['variacao_descricao'] ?? null,
                    'nome' => $it['nome'] ?? null,
                    'price' => $vu,
                    'preco_unitario' => $vu,
                    'stored_price' => $priceSnap !== null ? $priceSnap : $vu,
                    'stored_subtotal' => $sub,
                    'stored_peso_unit' => $weightSnap,
                    'quantidade' => $qtd,
                    'subtotal' => $sub,
                    'is_free_offer' => (int) ($it['is_free_offer'] ?? 0),
                    'free_offer_original_price' => isset($it['free_offer_original_price']) ? (float) $it['free_offer_original_price'] : null,
                ];
            }
            return $out;
        } catch (\Throwable $e) {
            return [];
        }
    }

    public function index(Request $request) {
        session_start();
        $this->hydrateCartFromCookie();

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
        $totalItensAll = 0;

        $pesoClubeTotal = 0.0;
        $subtotalClube = 0.0;
        $descontoClube = 0.0;
        $cashbackClubeEstimado = 0.0;

        $removedExpired = false;
        
        $cartChanges = [];
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

                $storedUnit = null;
                if (isset($item['stored_price'])) {
                    $storedUnit = (float) $item['stored_price'];
                } elseif (isset($item['preco_unitario'])) {
                    $storedUnit = (float) $item['preco_unitario'];
                } elseif (isset($item['price'])) {
                    $storedUnit = (float) $item['price'];
                }
                $storedPesoUnit = null;
                if (isset($item['stored_peso_unit'])) {
                    $storedPesoUnit = (float) $item['stored_peso_unit'];
                } elseif (isset($item['peso_unit'])) {
                    $storedPesoUnit = (float) $item['peso_unit'];
                }

                $precoBase = floatval($produto['preco'] ?? ($produto['valor'] ?? 0));
                if ($precoBase < 0) $precoBase = 0.0;
                $precoPromo = floatval($produto['preco_promocao'] ?? 0);
                if ($precoPromo < 0) $precoPromo = 0.0;
                $itemPrice = ($precoPromo > 0 && $precoPromo < $precoBase) ? $precoPromo : $precoBase;

                // Se o cliente informou o valor manualmente (assessoria), preservar o preço da sessão
                $isValorInformado = !empty($item['valor_informado_cliente']);
                if ($isValorInformado && $storedUnit !== null && $storedUnit > 0) {
                    $itemPrice = $storedUnit;
                }

                // Se o item foi adicionado como brinde (preço 0 no carrinho), manter preço 0
                if ($storedUnit !== null && (float) $storedUnit === 0.0 && $itemPrice > 0) {
                    $itemPrice = 0;
                    $changedFields = []; // Não marcar como "alterado"
                }
                // Fallback: verificar preco_unitario original do item
                if (isset($item['price']) && (float) $item['price'] === 0.0 && $itemPrice > 0) {
                    $itemPrice = 0;
                    $changedFields = [];
                }
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

                // Item gratuito: manter preço = 0 e subtotal = 0
                $isFreeOffer = !empty($item['is_free_offer']);
                if ($isFreeOffer) {
                    $itemPrice = 0;
                    $itemSubtotal = 0;
                }

                $pesoUnit = (float) ($produto['peso'] ?? 0.5);
                if ($pesoUnit <= 0) {
                    $pesoUnit = 0.5;
                }
                $pesoItem = ((int) ($item['quantidade'] ?? 0)) * $pesoUnit;

                $changedFields = [];
                if (!$isFreeOffer && $itemPrice > 0 && $storedUnit !== null && $storedUnit > 0) {
                    if (abs(((float) $itemPrice) - ((float) $storedUnit)) > 0.009) {
                        $changedFields[] = 'price';
                    }
                }
                if (!$isFreeOffer && $storedPesoUnit !== null && $storedPesoUnit > 0) {
                    if (abs(((float) $pesoUnit) - ((float) $storedPesoUnit)) > 0.0005) {
                        $changedFields[] = 'weight';
                    }
                }
                $itemChanged = !empty($changedFields);
                if ($itemChanged) {
                    $cartChanges[(string) $k] = $changedFields;
                }

                $ativo = $this->getItemAtivoFromSession((string) $k);
                // Se não existir flag ainda, inicializar como ativo
                $this->setItemAtivoInSession((string) $k, (bool) $ativo);

                // Normalizar sessão para refletir os valores corretos na view/resumo
                if (isset($_SESSION['carrinho'][$k]) && is_array($_SESSION['carrinho'][$k])) {
                    $_SESSION['carrinho'][$k]['nome'] = (string) ($produto['nome'] ?? ($_SESSION['carrinho'][$k]['nome'] ?? ''));
                    if (!isset($_SESSION['carrinho'][$k]['stored_price']) && isset($_SESSION['carrinho'][$k]['price'])) {
                        $_SESSION['carrinho'][$k]['stored_price'] = (float) $_SESSION['carrinho'][$k]['price'];
                    }
                    if (!isset($_SESSION['carrinho'][$k]['stored_peso_unit']) && isset($_SESSION['carrinho'][$k]['peso_unit'])) {
                        $_SESSION['carrinho'][$k]['stored_peso_unit'] = (float) $_SESSION['carrinho'][$k]['peso_unit'];
                    }
                    $_SESSION['carrinho'][$k]['price'] = $itemPrice;
                    $_SESSION['carrinho'][$k]['preco_unitario'] = $itemPrice;
                    $_SESSION['carrinho'][$k]['subtotal'] = $itemSubtotal;
                    $_SESSION['carrinho'][$k]['peso_unit'] = $pesoUnit;
                    $_SESSION['carrinho'][$k]['peso_item'] = $pesoItem;
                    $_SESSION['carrinho'][$k]['ativo'] = $ativo ? 1 : 0;
                    $_SESSION['carrinho'][$k]['item_changed'] = $itemChanged ? 1 : 0;
                    $_SESSION['carrinho'][$k]['changed_fields'] = $changedFields;
                    // Preservar flags de oferta gratuita
                    if ($isFreeOffer) {
                        $_SESSION['carrinho'][$k]['is_free_offer'] = 1;
                        if (!isset($_SESSION['carrinho'][$k]['free_offer_original_price'])) {
                            $_SESSION['carrinho'][$k]['free_offer_original_price'] = floatval($produto['preco'] ?? ($produto['valor'] ?? 0));
                        }
                    }
                }

                // Normalizar o carrinho local usado pela view
                if (isset($carrinho[$k]) && is_array($carrinho[$k])) {
                    if (!isset($carrinho[$k]['stored_price']) && isset($carrinho[$k]['price'])) {
                        $carrinho[$k]['stored_price'] = (float) $carrinho[$k]['price'];
                    }
                    if (!isset($carrinho[$k]['stored_peso_unit']) && isset($carrinho[$k]['peso_unit'])) {
                        $carrinho[$k]['stored_peso_unit'] = (float) $carrinho[$k]['peso_unit'];
                    }
                    $carrinho[$k]['nome'] = (string) ($produto['nome'] ?? ($carrinho[$k]['nome'] ?? ''));
                    $carrinho[$k]['price'] = $itemPrice;
                    $carrinho[$k]['preco_unitario'] = $itemPrice;
                    $carrinho[$k]['subtotal'] = $itemSubtotal;
                    $carrinho[$k]['peso_unit'] = $pesoUnit;
                    $carrinho[$k]['peso_item'] = $pesoItem;
                    $carrinho[$k]['ativo'] = $ativo ? 1 : 0;
                    $carrinho[$k]['item_changed'] = $itemChanged ? 1 : 0;
                    $carrinho[$k]['changed_fields'] = $changedFields;
                    // Preservar flags de oferta gratuita
                    if ($isFreeOffer) {
                        $carrinho[$k]['is_free_offer'] = 1;
                        if (!isset($carrinho[$k]['free_offer_original_price'])) {
                            $carrinho[$k]['free_offer_original_price'] = floatval($produto['preco'] ?? ($produto['valor'] ?? 0));
                        }
                    }
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

                $totalItensAll += (int) ($item['quantidade'] ?? 0);

                $isFreeOffer = !empty($item['is_free_offer']);

                if ($ativo) {
                    if (!$isFreeOffer) {
                        $subtotal += $itemSubtotal;
                    }
                    $pesoTotal += $pesoItem;
                    if (!$isFreeOffer) {
                        $totalItensAtivos += (int) ($item['quantidade'] ?? 0);
                    }
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
        $taxaServicoBruta = (float) $this->carrinhoModel->calcularTaxaServico($pesoTotal, 'USD', 1.0);

        // Aplicar desconto promocional na taxa de serviço (compra orgânica)
        $descontoTaxaInfo = $this->carrinhoModel->calcularDescontoTaxaServico($taxaServicoBruta);
        $taxaServico = $descontoTaxaInfo['final'];
        $taxaServicoOriginal = $descontoTaxaInfo['original'];
        $taxaServicoDescontoTipo = $descontoTaxaInfo['tipo'];
        $taxaServicoDescontoValor = $descontoTaxaInfo['valor_config'];
        $taxaServicoDescontoAplicado = $descontoTaxaInfo['desconto_aplicado'];

        $impostos = (float) $this->carrinhoModel->calcularImpostos($subtotal, $frete);

        // Detectar país do usuário para isenção de impostos BR
        $entregaForaBR = false;
        if ($uid > 0) {
            try {
                $dbPais = \Config\Database::getConnection();

                // Descobrir colunas disponíveis na tabela usuarios
                $uCols = [];
                try {
                    $stUCols = $dbPais->query('DESCRIBE usuarios');
                    $uCols = $stUCols ? ($stUCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
                } catch (\Throwable $e) { $uCols = []; }

                $paisUsuario = '';
                $selectCols = [];
                if (in_array('pais_residencia', $uCols, true)) $selectCols[] = 'pais_residencia';
                if (in_array('pais', $uCols, true)) $selectCols[] = 'pais';
                if (!empty($selectCols)) {
                    $stPais = $dbPais->prepare('SELECT ' . implode(',', $selectCols) . ' FROM usuarios WHERE id = ? LIMIT 1');
                    $stPais->execute([$uid]);
                    $rowPais = $stPais->fetch(\PDO::FETCH_ASSOC) ?: [];
                    $paisUsuario = strtoupper(trim((string) ($rowPais['pais_residencia'] ?? ($rowPais['pais'] ?? ''))));
                }

                if ($paisUsuario !== '' && $paisUsuario !== 'BR') {
                    $entregaForaBR = true;
                }

                // Se pais_residencia é BR ou vazio, verificar endereços cadastrados
                if (!$entregaForaBR) {
                    try {
                        // Verificar se tabela enderecos existe e tem coluna pais
                        $endCols = [];
                        try {
                            $stEC = $dbPais->query('DESCRIBE enderecos');
                            $endCols = $stEC ? ($stEC->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
                        } catch (\Throwable $e) { $endCols = []; }

                        if (in_array('pais', $endCols, true)) {
                            // Buscar qualquer endereço do usuário que não seja BR
                            $hasTipo = in_array('tipo', $endCols, true);
                            $sql = 'SELECT pais FROM enderecos WHERE usuario_id = ? AND pais IS NOT NULL AND pais != \'\' ORDER BY id DESC LIMIT 5';
                            $stEnd = $dbPais->prepare($sql);
                            $stEnd->execute([$uid]);
                            $enderecos = $stEnd->fetchAll(\PDO::FETCH_COLUMN) ?: [];
                            foreach ($enderecos as $pe) {
                                $pe = strtoupper(trim((string) $pe));
                                if ($pe !== '' && $pe !== 'BR' && $pe !== 'BRASIL' && $pe !== 'BRAZIL') {
                                    $entregaForaBR = true;
                                    break;
                                }
                            }
                        }
                    } catch (\Throwable $e) {}
                }
            } catch (\Throwable $e) {}
        }

        // Impostos BR só para entrega no Brasil (mesma regra do checkout)
        if ($entregaForaBR) {
            $impostos = 0.0;
        }
        
        $total = $subtotal + $taxaServico + $impostos + $frete;

        // Calcular imposto local do grupo de compras
        $impostoLocal = 0.0;
        $impostoLocalPercent = 0.0;
        try {
            $dbImpLocal = \Config\Database::getConnection();
            $pidsForTax = [];
            foreach ($carrinho as $ck => $cItem) {
                $ativoCheck = $this->getItemAtivoFromSession((string) $ck);
                if (!$ativoCheck) continue;
                $cpid = (int) ($cItem['produto_id'] ?? 0);
                if ($cpid > 0) $pidsForTax[$cpid] = true;
            }
            $pidsForTax = array_keys($pidsForTax);
            if (!empty($pidsForTax)) {
                $in = implode(',', array_fill(0, count($pidsForTax), '?'));
                $stImpL = $dbImpLocal->prepare("SELECT MAX(g.imposto_local_percent) FROM grupos_compras g INNER JOIN produtos p ON p.grupo_compras_id = g.id WHERE p.id IN ($in) AND g.imposto_local_percent > 0");
                $stImpL->execute($pidsForTax);
                $impostoLocalPercent = (float) ($stImpL->fetchColumn() ?: 0);
                if ($impostoLocalPercent > 0) {
                    $impostoLocal = $subtotal * ($impostoLocalPercent / 100.0);
                    $total = $total + $impostoLocal;
                }
            }
        } catch (\Throwable $e) {}

        // Se o carrinho estiver no DB, usar os totais persistidos (inclui desconto/cashback do Clube)
        if ($uid > 0 && $cartId > 0) {
            try {
                // Se existir item desativado, não usar totais do DB (DB não conhece a flag de ativo/inativo)
                $hasInactive = ($totalItensAtivos < $totalItensAll);
                if ($hasInactive) {
                    throw new \RuntimeException('Skip DB totals due to inactive items');
                }

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
                    $cartMoedaFromDb = strtoupper(trim((string) ($row['moeda'] ?? '')));
                    if (array_key_exists('subtotal_produtos', $row)) {
                        $subtotalFromDb = (float) ($row['subtotal_produtos'] ?? 0);
                        if ($subtotalFromDb > 0) {
                            $subtotal = $subtotalFromDb;
                        }
                    }
                    if (array_key_exists('peso_total', $row)) {
                        $pesoFromDb = (float) ($row['peso_total'] ?? 0);
                        if ($pesoFromDb > 0) {
                            $pesoTotal = $pesoFromDb;
                        }
                    }

                    $taxaServicoFromDb = null;
                    $impostosFromDb = null;
                    $totalFromDb = null;
                    if (array_key_exists('taxa_servico', $row)) $taxaServicoFromDb = (float) ($row['taxa_servico'] ?? 0);
                    if (array_key_exists('valor_impostos', $row)) $impostosFromDb = (float) ($row['valor_impostos'] ?? 0);
                    if (array_key_exists('valor_total', $row)) $totalFromDb = (float) ($row['valor_total'] ?? 0);

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

                    // Desconto promocional na taxa de serviço (usar dados do DB apenas se a promoção ainda estiver ativa)
                    $promoAinda = ((string) ($this->carrinhoModel->calcularDescontoTaxaServico(1.0)['tipo'] ?? '') !== '');
                    if ($promoAinda) {
                        if (is_array($cols) && in_array('taxa_servico_original', $cols, true) && $row['taxa_servico_original'] !== null) {
                            $taxaServicoOriginal = (float) $row['taxa_servico_original'];
                        }
                        if (is_array($cols) && in_array('taxa_servico_desconto_tipo', $cols, true) && $row['taxa_servico_desconto_tipo'] !== null) {
                            $taxaServicoDescontoTipo = $row['taxa_servico_desconto_tipo'];
                        }
                        if (is_array($cols) && in_array('taxa_servico_desconto_valor', $cols, true) && $row['taxa_servico_desconto_valor'] !== null) {
                            $taxaServicoDescontoValor = (float) $row['taxa_servico_desconto_valor'];
                        }
                        if (is_array($cols) && in_array('taxa_servico_desconto_aplicado', $cols, true) && $row['taxa_servico_desconto_aplicado'] !== null) {
                            $taxaServicoDescontoAplicado = (float) $row['taxa_servico_desconto_aplicado'];
                        }
                    } else {
                        // Promoção desativada: zerar desconto independente do que está no DB
                        $taxaServicoDescontoAplicado = 0.0;
                        $taxaServicoDescontoTipo = null;
                        $taxaServicoDescontoValor = 0.0;
                    }

                    // Se o DB tiver valores válidos, usar; senão manter cálculo atual
                    $skipPersistedTotals = (isset($cartMoedaFromDb) && $cartMoedaFromDb === 'BRL');
                    if (!$skipPersistedTotals) {
                        if (isset($taxaServicoFromDb) && (float) $taxaServicoFromDb > 0) {
                            $taxaServico = (float) $taxaServicoFromDb;
                        }
                        if (isset($impostosFromDb) && (float) $impostosFromDb > 0 && !$entregaForaBR) {
                            $impostos = (float) $impostosFromDb;
                        }
                        if (isset($totalFromDb) && (float) $totalFromDb > 0) {
                            $total = (float) $totalFromDb;
                            // Se entrega fora do BR, subtrair impostos BR que o DB pode ter incluído
                            if ($entregaForaBR && isset($impostosFromDb) && (float) $impostosFromDb > 0) {
                                $total = $total - (float) $impostosFromDb;
                            }
                        } else {
                            $total = (float) $subtotal + (float) $taxaServico + (float) $impostos + (float) $frete;
                        }
                    } else {
                        $total = (float) $subtotal + (float) $taxaServico + (float) $impostos + (float) $frete;
                    }

                    // Re-adicionar imposto local ao total (DB não persiste imposto local no carrinho)
                    if ($impostoLocal > 0) {
                        $total = (float) $total + (float) $impostoLocal;
                    }
                }
            } catch (\Throwable $e) {
            }
        }
        
        // Calcular dados do item gratuito para exibição no resumo
        $freeOfferInfo = null;
        foreach ($carrinho as $item) {
            if (!empty($item['is_free_offer'])) {
                $freeOrigPrice = (float) ($item['free_offer_original_price'] ?? ($item['price'] ?? 0));
                $freePeso = (float) ($item['peso_unit'] ?? ($item['peso_item'] ?? 0.5));
                if ($freePeso <= 0) $freePeso = 0.5;
                $freeImpostoTeorico = (float) $this->carrinhoModel->calcularImpostos($freeOrigPrice, 0);
                $freeTaxaServico = (float) $this->carrinhoModel->calcularTaxaServico($freePeso, 'USD', 1.0);
                $freeOfferInfo = [
                    'nome' => (string) ($item['nome'] ?? ''),
                    'preco_original' => $freeOrigPrice,
                    'imposto_teorico' => $freeImpostoTeorico,
                    'taxa_servico' => $freeTaxaServico,
                ];
                break;
            }
        }

        $this->view('carrinho/index', [
            'carrinho' => $carrinho,
            'produtosDetalhados' => $produtosDetalhados,
            'cart_changes' => $cartChanges,
            'subtotal' => $subtotal,
            'peso_clube_total' => $pesoClubeTotal,
            'subtotal_clube' => $subtotalClube,
            'desconto_clube' => $descontoClube,
            'cashback_clube_estimado' => $cashbackClubeEstimado,
            'taxa_servico' => $taxaServico,
            'taxa_servico_original' => $taxaServicoOriginal ?? null,
            'taxa_servico_desconto_tipo' => $taxaServicoDescontoTipo ?? null,
            'taxa_servico_desconto_valor' => $taxaServicoDescontoValor ?? null,
            'taxa_servico_desconto_aplicado' => $taxaServicoDescontoAplicado ?? null,
            'impostos' => $impostos,
            'imposto_local' => $impostoLocal,
            'imposto_local_percent' => $impostoLocalPercent,
            'frete' => $frete,
            'peso_total' => $pesoTotal,
            'total' => $total,
            'total_itens' => $totalItensAtivos,
            'peso_max_kg' => $pesoMaxKg,
            'excede_peso' => $excedePeso,
            'entrega_fora_br' => $entregaForaBR,
            'free_offer_info' => $freeOfferInfo,
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
        $this->hydrateCartFromCookie();

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
        $this->hydrateCartFromCookie();

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

                // Verificar brinde para usuário logado (DB path)
                $brindeMsg = '';
                try {
                    $brindeService = new \App\Services\BrindeService();
                    $brinde = $brindeService->getBrindeAtivo((int) $produtoId);
                    if ($brinde) {
                        $brindeProductId = (int) $brinde['brinde_produto_id'];
                        $stCheck = $this->carrinhoModel->getConnection()->prepare('SELECT id FROM carrinho_items WHERE carrinho_id = ? AND produto_id = ? LIMIT 1');
                        $stCheck->execute([$cartId, $brindeProductId]);
                        if (!$stCheck->fetchColumn()) {
                            $this->carrinhoModel->adicionarItem($cartId, $brindeProductId, (int) ($brinde['quantidade_brinde'] ?? 1), null, null);
                            $this->carrinhoModel->getConnection()->prepare('UPDATE carrinho_items SET preco_unitario = 0, subtotal = 0, preco_unit_snapshot = 0 WHERE carrinho_id = ? AND produto_id = ? ORDER BY id DESC LIMIT 1')->execute([$cartId, $brindeProductId]);
                            // Recalcular totais do carrinho após zerar preço do brinde
                            $this->carrinhoModel->atualizarTotais($cartId);
                            $brindeMsg = ' + brinde: ' . ($brinde['brinde_nome'] ?? 'Brinde') . ' 🎁';
                            $totalItens++;
                        }
                    }
                } catch (\Exception $e) {
                    error_log('[BRINDE] Erro CarrinhoController DB path: ' . $e->getMessage());
                }

                $this->json([
                    'success' => true,
                    'message' => 'Produto adicionado ao carrinho' . $brindeMsg,
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

        $precoBase = (float) ($produto['preco'] ?? ($produto['valor'] ?? 0));
        if ($precoBase < 0) $precoBase = 0.0;
        $precoPromo = (float) ($produto['preco_promocao'] ?? ($produto['preco_promo'] ?? ($produto['sale_price'] ?? 0)));
        if ($precoPromo < 0) $precoPromo = 0.0;
        $itemPrice = ($precoPromo > 0 && $precoPromo < $precoBase) ? $precoPromo : $precoBase;

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

        // Verificar se o produto tem brinde ativo e adicionar automaticamente
        $brindeAdicionado = null;
        error_log("[BRINDE_CART] Verificando brinde para produto_id={$produtoId}");
        try {
            $brindeService = new \App\Services\BrindeService();
            $brinde = $brindeService->getBrindeAtivo($produtoId);
            if ($brinde) {
                $brindeKey = (string) $brinde['brinde_produto_id'] . ':brinde';
                // Só adicionar se ainda não está no carrinho como brinde
                if (!isset($_SESSION['carrinho'][$brindeKey])) {
                    $_SESSION['carrinho'][$brindeKey] = [
                        'produto_id' => (int) $brinde['brinde_produto_id'],
                        'produto_variacao_id' => null,
                        'nome' => (string) ($brinde['brinde_nome'] ?? 'Brinde'),
                        'price' => 0,
                        'preco_unitario' => 0,
                        'quantidade' => (int) ($brinde['quantidade_brinde'] ?? 1),
                        'subtotal' => 0,
                        'is_brinde' => true,
                        'brinde_de_produto_id' => $produtoId,
                        'brinde_preco_original' => (float) ($brinde['brinde_preco'] ?? 0),
                        'brinde_peso' => (float) ($brinde['brinde_peso'] ?? 0),
                    ];
                    $brindeAdicionado = (string) ($brinde['brinde_nome'] ?? 'Brinde');
                    $totalItens = array_sum(array_column($_SESSION['carrinho'], 'quantidade'));
                }
            }
        } catch (\Exception $e) {
            error_log('[BRINDE] Erro ao verificar brinde: ' . $e->getMessage());
        }
        
        $response = [
            'success' => true,
            'message' => 'Produto adicionado ao carrinho' . ($brindeAdicionado ? ' + brinde: ' . $brindeAdicionado . ' 🎁' : ''),
            'carrinho' => $_SESSION['carrinho'],
            'total_itens' => $totalItens,
            'total_valor' => $totalValor,
            'brinde_adicionado' => $brindeAdicionado,
        ];
        
        $this->debugLog('Resposta JSON: ' . json_encode($response));
        
        $this->json($response);
    }

    public function remover(Request $request) {
        session_start();
        $this->hydrateCartFromCookie();
        $produtoId = $request->getParam('id', null);
        $produtoIdFallback = $request->getParam('produto_id', null);
        
        if (($produtoId === null || $produtoId === '') && ($produtoIdFallback === null || $produtoIdFallback === '')) {
            $this->json(['error' => 'Produto não informado'], 400);
            return;
        }

        if (($produtoId === null || $produtoId === '') && ($produtoIdFallback !== null && $produtoIdFallback !== '')) {
            $produtoId = $produtoIdFallback;
        }

        $uid = $this->getLoggedUserId();
        $dbRemoveDebug = null;
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
                $affected = (int) $this->carrinhoModel->removerItem($cartId, (int) $produtoIdDb, $pvId);
                if ($affected < 1) {
                    // Não encontrou no carrinho do usuário no DB.
                    // Pode ser que a view esteja exibindo itens da sessão (fallback) mesmo com usuário logado.
                    // Nesse caso, tentaremos remover da sessão abaixo.
                    $dbRemoveDebug = [
                        'cart_id' => $cartId,
                        'produto_id' => (int) $produtoIdDb,
                        'produto_variacao_id' => ($pvId !== null ? (int) $pvId : null),
                        'request_id' => (string) $produtoId,
                        'uid' => (int) $uid
                    ];
                } else {
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
                }
            } catch (\Throwable $e) {
                // fallback session
            }
        }
        
        $keyToRemove = null;
        if (isset($_SESSION['carrinho'][$produtoId])) {
            $keyToRemove = $produtoId;
        } else {
            // Tentar sem o sufixo :0 (assessoria adiciona sem variação)
            $produtoIdSemVariacao = $produtoId;
            if (is_string($produtoId) && strpos($produtoId, ':') !== false) {
                $parts = explode(':', $produtoId);
                $produtoIdSemVariacao = $parts[0];
                // Se variação é 0, tentar a key sem sufixo
                if ((int) ($parts[1] ?? 0) === 0 && isset($_SESSION['carrinho'][$produtoIdSemVariacao])) {
                    $keyToRemove = $produtoIdSemVariacao;
                }
            }
            if ($keyToRemove === null) {
                foreach (($_SESSION['carrinho'] ?? []) as $k => $item) {
                    if (is_array($item) && array_key_exists('produto_id', $item) && (string) $item['produto_id'] === (string) $produtoIdSemVariacao) {
                        $keyToRemove = $k;
                        break;
                    }
                }
            }
        }

        if ($keyToRemove !== null) {
            // Se o item removido é um produto principal que tem brinde, remover o brinde também
            $removedItem = $_SESSION['carrinho'][$keyToRemove] ?? [];
            unset($_SESSION['carrinho'][$keyToRemove]);

            // Remover brindes vinculados a este produto
            $removedProdutoId = (int) ($removedItem['produto_id'] ?? 0);
            if ($removedProdutoId > 0) {
                foreach ($_SESSION['carrinho'] as $bk => $bItem) {
                    if (!empty($bItem['is_brinde']) && (int) ($bItem['brinde_de_produto_id'] ?? 0) === $removedProdutoId) {
                        unset($_SESSION['carrinho'][$bk]);
                    }
                }
            }
            
            $totalItens = array_sum(array_column($_SESSION['carrinho'], 'quantidade'));
            $totalValor = 0;
            foreach ($_SESSION['carrinho'] as $item) {
                $totalValor += floatval($item['subtotal']);
            }
            
            $this->json([
                'success' => true,
                'message' => 'Produto removido do carrinho',
                'total_itens' => $totalItens,
                'total_valor' => $totalValor,
                'debug' => ($dbRemoveDebug !== null ? ['db_not_found' => $dbRemoveDebug] : null)
            ]);
        } else {
            $payload = ['error' => 'Produto não encontrado no carrinho'];
            if ($dbRemoveDebug !== null) {
                $payload['debug'] = ['db_not_found' => $dbRemoveDebug];
            }
            $this->json($payload, 404);
        }
    }

    public function atualizar(Request $request) {
        session_start();
        $this->hydrateCartFromCookie();
        $produtoId = $request->getParam('id', null);
        $produtoIdFallback = $request->getParam('produto_id', null);
        $quantidade = $request->getParam('quantidade', null);
        
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

        $uid = $this->getLoggedUserId();
        if ($uid > 0) {
            try {
                $pvId = null;
                $produtoIdDb = (int) $produtoId;
                if (is_string($produtoId) && strpos($produtoId, ':') !== false) {
                    $parts = explode(':', $produtoId);
                    $produtoIdDb = (int) ($parts[0] ?? 0);
                    $pvTmp = (int) ($parts[1] ?? 0);
                    if ($pvTmp > 0) {
                        $pvId = $pvTmp;
                    }
                } elseif ($produtoIdFallback !== null && $produtoIdFallback !== '') {
                    $produtoIdDb = (int) $produtoIdFallback;
                }

                if ($produtoIdDb <= 0) {
                    $this->json(['error' => 'Produto não informado'], 400);
                    return;
                }

                $produto = $this->produtoModel->find($produtoIdDb);
                if ($produto && (int) ($produto['estoque'] ?? 0) < (int) $quantidade) {
                    $this->json(['error' => 'Estoque insuficiente'], 400);
                    return;
                }

                $cart = $this->carrinhoModel->getOrCreateCarrinho($uid, null, 'BRL');
                $cartId = is_array($cart) ? (int) ($cart['id'] ?? 0) : (int) $cart;
                if ($cartId <= 0) {
                    $this->json(['error' => 'Carrinho não encontrado'], 400);
                    return;
                }

                $ok = $this->carrinhoModel->setQuantidadeItem($cartId, $produtoIdDb, (int) $quantidade, $pvId);
                if (!$ok) {
                    $this->json(['error' => 'Produto não encontrado no carrinho'], 404);
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
                    'message' => 'Carrinho atualizado',
                    'total_itens' => $totalItens,
                    'total_valor' => $totalValor
                ]);
                return;
            } catch (\Throwable $e) {
                // fallback session
            }
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

            // Garantir preço numérico (com desconto quando válido; override da variação tem prioridade)
            $itemPrice = 0.0;
            if ($produto) {
                $precoBase = floatval($produto['preco'] ?? $produto['valor'] ?? 0);
                if ($precoBase < 0) $precoBase = 0.0;
                $precoPromo = floatval($produto['preco_promocao'] ?? 0);
                if ($precoPromo < 0) $precoPromo = 0.0;
                $itemPrice = ($precoPromo > 0 && $precoPromo < $precoBase) ? $precoPromo : $precoBase;
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
        $this->hydrateCartFromCookie();
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
        $this->hydrateCartFromCookie();

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
        $taxaServicoBruta = (float) $this->carrinhoModel->calcularTaxaServico($pesoTotal, 'USD', 1.0);

        // Aplicar desconto promocional na taxa de serviço
        $descontoTaxaInfo = $this->carrinhoModel->calcularDescontoTaxaServico($taxaServicoBruta);
        $taxaServico = $descontoTaxaInfo['final'];
        
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
            'taxa_servico_original' => $descontoTaxaInfo['original'],
            'taxa_servico_desconto_aplicado' => $descontoTaxaInfo['desconto_aplicado'],
            'frete' => $frete,
            'impostos' => $impostos,
            'subtotal' => $subtotal,
            'total' => $total
        ]);
    }
}
