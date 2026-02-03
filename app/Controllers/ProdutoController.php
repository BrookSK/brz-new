<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Url;
use App\Models\Produto;
use App\Models\ProdutoFoto;

class ProdutoController extends Controller {
    private $produtoModel;
    private $produtoFotoModel;

    private function getDirectPdo(): \PDO {
        $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        return $pdo;
    }

    public function __construct() {
        $this->produtoModel = new Produto();
        $this->produtoFotoModel = new ProdutoFoto();
    }

    public function index(Request $request) {
        $search = $request->getParam('search');
        $categoria = $request->getParam('categoria');
        
        if ($search) {
            $produtos = $this->produtoModel->search($search);
        } elseif ($categoria) {
            $produtos = $this->produtoModel->getByCategoriaId($categoria);
        } else {
            // Usar método que inclui JOIN com categorias
            $produtos = $this->produtoModel->getAllWithCategoria();
        }
        
        // Adicionar foto de capa (produtos.foto_principal) com fallback para galeria
        foreach ($produtos as &$produto) {
            $capa = $this->normalizeProdutoImagemPath($produto['foto_principal'] ?? null);
            if (!empty($capa) && $this->produtoImagemExiste($capa)) {
                $produto['foto_principal'] = Url::absolute($capa);
                continue;
            }

            $fotoGaleria = $this->produtoFotoModel->getFotoPrincipal($produto['id']);
            if ($fotoGaleria && !empty($fotoGaleria['nome_arquivo'])) {
                $fotoUrl = $this->normalizeProdutoImagemPath($fotoGaleria['nome_arquivo']);
                $produto['foto_principal'] = ($fotoUrl && $this->produtoImagemExiste($fotoUrl)) ? Url::absolute($fotoUrl) : null;
            } else {
                $produto['foto_principal'] = null;
            }
        }

        unset($produto);
        
        $categorias = $this->produtoModel->getCategorias();
        
        $this->view('produto/index_moderno', [
            'produtos' => $produtos,
            'categorias' => $categorias,
            'search' => $search,
            'categoriaSelecionada' => $categoria
        ]);
    }

    public function detalhes(Request $request) {
        $produtoId = $request->getParam('id');
        
        if (!$produtoId) {
            $this->redirect('/produtos');
        }
        
        $produto = $this->produtoModel->find($produtoId);
        
        if (!$produto) {
            $this->view('errors/404');
            return;
        }
        
        $fotos = $this->produtoModel->getImagens($produtoId);

        $variacoesUi = [
            'enabled' => false,
            'atributos' => [],
            'variacoes' => [],
            'fotos_por_variacao' => [],
        ];
        try {
            $pdo = $this->getDirectPdo();
            $variacoesUi = $this->buildVariacoesUiData($pdo, (int) $produtoId);
        } catch (\Throwable $e) {
        }

        $fotoPrincipal = null;
        $capa = $this->normalizeProdutoImagemPath($produto['foto_principal'] ?? null);
        if (!empty($capa) && $this->produtoImagemExiste($capa)) {
            $fotoPrincipal = [
                'nome_arquivo' => (string) $capa,
                'principal' => true,
                'arquivo_existe' => true,
                'url_completa' => Url::absolute((string) $capa)
            ];

            $jaExisteNaGaleria = false;
            foreach ($fotos as $f) {
                if (!empty($f['nome_arquivo']) && (string) $f['nome_arquivo'] === (string) $capa) {
                    $jaExisteNaGaleria = true;
                    break;
                }
            }
            if (!$jaExisteNaGaleria) {
                array_unshift($fotos, [
                    'nome_arquivo' => (string) $capa,
                    'arquivo_original' => null,
                    'legenda' => 'Capa',
                    'ordem' => -1,
                    'principal' => true,
                    'arquivo_existe' => true,
                    'url_completa' => Url::absolute((string) $capa)
                ]);
            }
        }

        if (!$fotoPrincipal && !empty($fotos)) {
            foreach ($fotos as $foto) {
                if (!empty($foto['principal'])) {
                    $fotoPrincipal = $foto;
                    break;
                }
            }
            if (!$fotoPrincipal) {
                $fotoPrincipal = $fotos[0];
            }
        }
        
        $produtosRelacionados = $this->produtoModel->getByCategoriaId($produto['categoria_id']);
        $produtosRelacionados = array_filter($produtosRelacionados, function($p) use ($produtoId) {
            return $p['id'] != $produtoId;
        });
        
        foreach ($produtosRelacionados as &$relacionado) {
            $capaRel = $this->normalizeProdutoImagemPath($relacionado['foto_principal'] ?? null);
            if (!empty($capaRel) && $this->produtoImagemExiste($capaRel)) {
                $relacionado['foto_principal'] = Url::absolute((string) $capaRel);
                continue;
            }

            $fotoPrincipalRel = $this->produtoFotoModel->getFotoPrincipal($relacionado['id']);
            if ($fotoPrincipalRel && !empty($fotoPrincipalRel['nome_arquivo'])) {
                $fotoUrl = $this->normalizeProdutoImagemPath($fotoPrincipalRel['nome_arquivo']);
                $relacionado['foto_principal'] = ($fotoUrl && $this->produtoImagemExiste($fotoUrl)) ? Url::absolute($fotoUrl) : null;
            } else {
                $relacionado['foto_principal'] = null;
            }
        }
        
        $this->view('produto/detalhes', [
            'produto' => $produto,
            'fotos' => $fotos,
            'fotoPrincipal' => $fotoPrincipal,
            'produtosRelacionados' => array_slice($produtosRelacionados, 0, 4),
            'variacoesUi' => $variacoesUi
        ]);
    }

    public function variacoes(Request $request) {
        $produtoId = (int) $request->getParam('id');
        $debug = (string) $request->getParam('debug', '');
        if ($produtoId <= 0) {
            $this->json(['enabled' => false, 'atributos' => [], 'variacoes' => [], 'fotos_por_variacao' => []], 400);
        }

        try {
            $pdo = $this->getDirectPdo();
            $err = null;
            try {
                $data = $this->buildVariacoesUiData($pdo, $produtoId);
            } catch (\Throwable $e) {
                $data = ['enabled' => false, 'atributos' => [], 'variacoes' => [], 'fotos_por_variacao' => []];
                $err = $e->getMessage();
            }

            if ($debug === '1' || $debug === 'true') {
                $counts = [];
                try {
                    $st = $pdo->prepare('SELECT COUNT(*) FROM produto_variacoes WHERE produto_id = ?');
                    $st->execute([$produtoId]);
                    $counts['produto_variacoes'] = (int) $st->fetchColumn();
                } catch (\Throwable $e) {
                    $counts['produto_variacoes'] = null;
                }
                try {
                    $st = $pdo->prepare('SELECT COUNT(*) FROM produto_variacao_itens pvi INNER JOIN produto_variacoes pv ON pv.id = pvi.produto_variacao_id WHERE pv.produto_id = ?');
                    $st->execute([$produtoId]);
                    $counts['produto_variacao_itens'] = (int) $st->fetchColumn();
                } catch (\Throwable $e) {
                    $counts['produto_variacao_itens'] = null;
                }
                try {
                    $st = $pdo->query('SELECT COUNT(*) FROM variacao_tipos WHERE ativo = 1');
                    $counts['variacao_tipos_ativos'] = (int) $st->fetchColumn();
                } catch (\Throwable $e) {
                    $counts['variacao_tipos_ativos'] = null;
                }
                try {
                    $st = $pdo->query('SELECT COUNT(*) FROM variacao_opcoes WHERE ativo = 1');
                    $counts['variacao_opcoes_ativas'] = (int) $st->fetchColumn();
                } catch (\Throwable $e) {
                    $counts['variacao_opcoes_ativas'] = null;
                }

                $data['debug_counts'] = $counts;
                $data['debug_error'] = $err;
                $data['debug_produto_id'] = $produtoId;
            }

            $this->json($data);
        } catch (\Throwable $e) {
            $this->json(['enabled' => false, 'atributos' => [], 'variacoes' => [], 'fotos_por_variacao' => []]);
        }
    }

    public function selecionar(Request $request) {
        $produtoId = $request->getParam('id');
        $produtoVariacaoId = $request->getParam('produto_variacao_id');
        $quantidade = $request->getParam('quantidade', 1);
        
        if (!$produtoId) {
            $this->json(['error' => 'Produto não informado'], 400);
        }
        
        $produto = $this->produtoModel->find($produtoId);
        
        if (!$produto) {
            $this->json(['error' => 'Produto não encontrado'], 404);
        }
        
        $itemPrice = (float) ($produto['preco'] ?? $produto['valor'] ?? 0);
        $itemStock = (int) ($produto['estoque'] ?? 0);
        $variacaoDescricao = null;
        $pvId = null;

        if ($produtoVariacaoId !== null && $produtoVariacaoId !== '') {
            $pvId = (int) $produtoVariacaoId;
            if ($pvId > 0) {
                try {
                    $pdo = $this->getDirectPdo();
                    $st = $pdo->prepare('SELECT id, produto_id, price_override, stock, ativo FROM produto_variacoes WHERE id = ? LIMIT 1');
                    $st->execute([$pvId]);
                    $row = $st->fetch(\PDO::FETCH_ASSOC);
                    if (!$row || (int) ($row['produto_id'] ?? 0) !== (int) $produtoId) {
                        $this->json(['error' => 'Variação inválida para este produto'], 400);
                    }
                    if (!(int) ($row['ativo'] ?? 1)) {
                        $this->json(['error' => 'Variação indisponível'], 400);
                    }

                    $itemStock = (int) ($row['stock'] ?? 0);
                    $po = $row['price_override'];
                    if ($po !== null && $po !== '') {
                        $itemPrice = (float) $po;
                    }

                    $variacaoDescricao = $this->buildVariacaoDescricao($pdo, $pvId);
                } catch (\Throwable $e) {
                }
            }
        }

        if ($itemStock < (int) $quantidade) {
            $this->json(['error' => 'Estoque insuficiente'], 400);
        }
        
        session_start();

        if (!isset($_SESSION['carrinho'])) {
            $_SESSION['carrinho'] = [];
        }

        $itemKey = ((string) $produtoId) . ':' . ((string) ($pvId ?? 0));

        if (isset($_SESSION['carrinho'][$itemKey])) {
            $_SESSION['carrinho'][$itemKey]['quantidade'] += (int) $quantidade;
            $_SESSION['carrinho'][$itemKey]['subtotal'] = $_SESSION['carrinho'][$itemKey]['quantidade'] * $itemPrice;
            $_SESSION['carrinho'][$itemKey]['preco_unitario'] = $itemPrice;
            $_SESSION['carrinho'][$itemKey]['price'] = $itemPrice;
        } else {
            $_SESSION['carrinho'][$itemKey] = [
                'produto_id' => (int) $produtoId,
                'produto_variacao_id' => $pvId,
                'variacao_descricao' => $variacaoDescricao,
                'nome' => $produto['nome'],
                'preco_unitario' => $itemPrice,
                'price' => $itemPrice,
                'quantidade' => (int) $quantidade,
                'subtotal' => ((int) $quantidade) * $itemPrice
            ];
        }

        $totalItens = 0;
        $totalValor = 0.0;
        foreach (($_SESSION['carrinho'] ?? []) as $item) {
            $totalItens += (int) ($item['quantidade'] ?? 0);
            $totalValor += (float) ($item['subtotal'] ?? 0);
        }

        $this->json([
            'success' => true,
            'message' => 'Produto adicionado ao carrinho',
            'carrinho' => $_SESSION['carrinho'],
            'total_itens' => $totalItens,
            'total_valor' => $totalValor
        ]);
    }

    public function adicionarAoCarrinho(Request $request) {
        $this->selecionar($request);
    }

    private function tableExists(\PDO $pdo, string $table): bool {
        try {
            $st = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1');
            $st->execute([$table]);
            return (bool) $st->fetchColumn();
        } catch (\Exception $e) {
            return false;
        }
    }

    private function buildVariacaoDescricao(\PDO $pdo, int $produtoVariacaoId): ?string {
        try {
            $sql = '
                SELECT vt.nome AS tipo_nome, vo.valor AS opcao_valor
                FROM produto_variacao_itens pvi
                INNER JOIN variacao_tipos vt ON vt.id = pvi.tipo_id
                INNER JOIN variacao_opcoes vo ON vo.id = pvi.opcao_id
                WHERE pvi.produto_variacao_id = ?
                ORDER BY vt.nome ASC, vo.valor ASC
            ';
            $st = $pdo->prepare($sql);
            $st->execute([$produtoVariacaoId]);
            $rows = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            if (empty($rows)) return null;
            $parts = [];
            foreach ($rows as $r) {
                $parts[] = (string) ($r['tipo_nome'] ?? '') . '=' . (string) ($r['opcao_valor'] ?? '');
            }
            return implode(' / ', $parts);
        } catch (\Exception $e) {
            return null;
        }
    }

    private function buildVariacoesUiData(\PDO $pdo, int $produtoId): array {
        $out = [
            'enabled' => false,
            'atributos' => [],
            'variacoes' => [],
            'fotos_por_variacao' => [],
        ];

        try {
            $stmtVars = $pdo->prepare('SELECT id, price_override, stock, ativo FROM produto_variacoes WHERE produto_id = ? ORDER BY id ASC');
            $stmtVars->execute([$produtoId]);
            $vars = $stmtVars->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            if (empty($vars)) {
                return $out;
            }

            $varIds = array_map(function($v) { return (int) ($v['id'] ?? 0); }, $vars);
            $varIds = array_values(array_filter($varIds));
            if (empty($varIds)) {
                return $out;
            }

            // Itens de variação
            $in = implode(',', array_fill(0, count($varIds), '?'));
            $itens = [];
            try {
                $sqlItens = '
                    SELECT pvi.produto_variacao_id, pvi.tipo_id, pvi.opcao_id, vt.nome AS tipo_nome, vo.valor AS opcao_valor
                    FROM produto_variacao_itens pvi
                    INNER JOIN variacao_tipos vt ON vt.id = pvi.tipo_id
                    INNER JOIN variacao_opcoes vo ON vo.id = pvi.opcao_id
                    WHERE pvi.produto_variacao_id IN (' . $in . ')
                    ORDER BY pvi.produto_variacao_id ASC, vt.nome ASC, vo.valor ASC
                ';
                $stmtItens = $pdo->prepare($sqlItens);
                $stmtItens->execute($varIds);
                $itens = $stmtItens->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            } catch (\Exception $e) {
                $itens = [];
            }

            if (empty($itens)) {
                try {
                    $sqlItens2 = 'SELECT produto_variacao_id, tipo_id, opcao_id FROM produto_variacao_itens WHERE produto_variacao_id IN (' . $in . ') ORDER BY produto_variacao_id ASC, tipo_id ASC, opcao_id ASC';
                    $stmtItens2 = $pdo->prepare($sqlItens2);
                    $stmtItens2->execute($varIds);
                    $itens2 = $stmtItens2->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                    $tipoIds = [];
                    $opIds = [];
                    foreach ($itens2 as $it) {
                        $tid = (int) ($it['tipo_id'] ?? 0);
                        $oid = (int) ($it['opcao_id'] ?? 0);
                        if ($tid > 0) $tipoIds[$tid] = true;
                        if ($oid > 0) $opIds[$oid] = true;
                    }

                    $tiposById = [];
                    if (!empty($tipoIds)) {
                        $ids = array_keys($tipoIds);
                        $inTipos = implode(',', array_fill(0, count($ids), '?'));
                        $stT = $pdo->prepare('SELECT id, nome FROM variacao_tipos WHERE id IN (' . $inTipos . ')');
                        $stT->execute($ids);
                        foreach (($stT->fetchAll(\PDO::FETCH_ASSOC) ?: []) as $r) {
                            $tiposById[(int) ($r['id'] ?? 0)] = (string) ($r['nome'] ?? '');
                        }
                    }

                    $opById = [];
                    if (!empty($opIds)) {
                        $ids = array_keys($opIds);
                        $inOps = implode(',', array_fill(0, count($ids), '?'));
                        $stO = $pdo->prepare('SELECT id, valor FROM variacao_opcoes WHERE id IN (' . $inOps . ')');
                        $stO->execute($ids);
                        foreach (($stO->fetchAll(\PDO::FETCH_ASSOC) ?: []) as $r) {
                            $opById[(int) ($r['id'] ?? 0)] = (string) ($r['valor'] ?? '');
                        }
                    }

                    foreach ($itens2 as $it) {
                        $it['tipo_nome'] = $tiposById[(int) ($it['tipo_id'] ?? 0)] ?? '';
                        $it['opcao_valor'] = $opById[(int) ($it['opcao_id'] ?? 0)] ?? '';
                        $itens[] = $it;
                    }
                } catch (\Exception $e) {
                    $itens = [];
                }
            }

            $itensPorVar = [];
            $atributos = [];
            foreach ($itens as $it) {
                $vId = (int) ($it['produto_variacao_id'] ?? 0);
                if ($vId <= 0) continue;
                if (!isset($itensPorVar[$vId])) $itensPorVar[$vId] = [];

                $tipoId = (int) ($it['tipo_id'] ?? 0);
                $opId = (int) ($it['opcao_id'] ?? 0);
                $tipoNome = (string) ($it['tipo_nome'] ?? '');
                $opValor = (string) ($it['opcao_valor'] ?? '');

                $itensPorVar[$vId][] = [
                    'tipo_id' => $tipoId,
                    'opcao_id' => $opId,
                    'tipo_nome' => $tipoNome,
                    'opcao_valor' => $opValor,
                ];

                if (!isset($atributos[$tipoId])) {
                    $atributos[$tipoId] = [
                        'tipo_id' => $tipoId,
                        'nome' => $tipoNome,
                        'opcoes' => [],
                    ];
                }
                $atributos[$tipoId]['opcoes'][$opId] = [
                    'opcao_id' => $opId,
                    'valor' => $opValor,
                ];
            }

            $fotosPorVar = [];
            if ($this->tableExists($pdo, 'produto_variacao_fotos')) {
                $sqlFotos = 'SELECT id, produto_variacao_id, nome_arquivo, legenda, ordem FROM produto_variacao_fotos WHERE produto_variacao_id IN (' . $in . ') ORDER BY produto_variacao_id ASC, ordem ASC, id ASC';
                $stmtFotos = $pdo->prepare($sqlFotos);
                $stmtFotos->execute($varIds);
                $rowsFotos = $stmtFotos->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                foreach ($rowsFotos as $f) {
                    $vId = (int) ($f['produto_variacao_id'] ?? 0);
                    if ($vId <= 0) continue;
                    if (!isset($fotosPorVar[$vId])) $fotosPorVar[$vId] = [];

                    $path = $this->normalizeProdutoImagemPath($f['nome_arquivo'] ?? null);
                    $fotosPorVar[$vId][] = [
                        'id' => (int) ($f['id'] ?? 0),
                        'nome_arquivo' => $path,
                        'url_completa' => $path ? Url::absolute($path) : null,
                        'legenda' => $f['legenda'] ?? null,
                        'ordem' => (int) ($f['ordem'] ?? 0),
                    ];
                }
            }

            $atributosList = array_values($atributos);
            foreach ($atributosList as &$a) {
                $a['opcoes'] = array_values($a['opcoes']);
            }
            unset($a);

            $variacoesList = [];
            foreach ($vars as $v) {
                $vId = (int) ($v['id'] ?? 0);
                if ($vId <= 0) continue;
                $map = [];
                $descParts = [];
                foreach (($itensPorVar[$vId] ?? []) as $it) {
                    $map[(string) $it['tipo_id']] = (int) $it['opcao_id'];
                    $descParts[] = (string) $it['tipo_nome'] . '=' . (string) $it['opcao_valor'];
                }
                $variacoesList[] = [
                    'id' => $vId,
                    'stock' => (int) ($v['stock'] ?? 0),
                    'price_override' => ($v['price_override'] === null || $v['price_override'] === '') ? null : (float) $v['price_override'],
                    'map' => $map,
                    'descricao' => implode(' / ', $descParts),
                ];
            }

            $out['enabled'] = !empty($atributosList) && !empty($variacoesList);
            $out['atributos'] = $atributosList;
            $out['variacoes'] = $variacoesList;
            $out['fotos_por_variacao'] = $fotosPorVar;
            return $out;

        } catch (\Exception $e) {
            return $out;
        }
    }

    private function normalizeProdutoImagemPath($path): ?string {
        if (!is_string($path)) {
            return null;
        }

        $path = trim($path);
        if ($path === '') {
            return null;
        }

        // URLs externas devem ser preservadas (usado por produtos importados)
        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        if (strpos($path, '//') === 0) {
            return 'https:' . $path;
        }

        if ($path[0] !== '/') {
            $path = '/' . $path;
        }

        if (strpos($path, '/uploads/') === 0) {
            return $path;
        }

        return '/uploads/produtos/' . ltrim($path, '/');
    }

    private function produtoImagemExiste(string $path): bool {
        // URL externa: não há como validar via file_exists
        if (preg_match('#^https?://#i', $path)) {
            return true;
        }

        $docRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
        if ($docRoot === '') {
            return false;
        }

        $rel = '/' . ltrim($path, '/');
        return (
            file_exists($docRoot . $rel) ||
            file_exists($docRoot . '/public' . $rel)
        );
    }
}
