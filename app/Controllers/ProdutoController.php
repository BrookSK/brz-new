<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Url;
use App\Models\Produto;
use App\Models\ProdutoFoto;
use App\Models\Carrinho;
use App\Services\AuthService;

class ProdutoController extends Controller {
    private $produtoModel;
    private $produtoFotoModel;

    private function getDirectPdo(): \PDO {
        $pdo = new \PDO('mysql:host=127.0.0.1;dbname=novobr', 'novobr', '33537095Ab12$');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        return $pdo;
    }

    public function __construct() {
        $this->produtoModel = new Produto();
        $this->produtoFotoModel = new ProdutoFoto();
    }

    /**
     * Verifica se o usuário logado tem acesso ao Clube Braziliana (saldo >= US$39).
     * Retorna [logado, acesso_liberado, saldo_usd].
     */
    private function checkClubeAccess(): array {
        $auth = new AuthService();
        if (!$auth->estaLogado()) {
            return ['logado' => false, 'acesso' => false, 'saldo' => 0.0];
        }
        $usuario = $auth->getUsuarioLogado();
        $uid = (int) ($usuario['id'] ?? 0);
        if ($uid <= 0) {
            return ['logado' => true, 'acesso' => false, 'saldo' => 0.0];
        }
        try {
            $pdo = $this->getDirectPdo();
            $st = $pdo->prepare('SELECT saldo_usd FROM carteiras WHERE usuario_id = ? LIMIT 1');
            $st->execute([$uid]);
            $saldo = (float) ($st->fetchColumn() ?: 0);
            return ['logado' => true, 'acesso' => ($saldo >= 39.00), 'saldo' => $saldo];
        } catch (\Throwable $e) {
            return ['logado' => true, 'acesso' => false, 'saldo' => 0.0];
        }
    }

    /**
     * Marca produtos com flag clube_only baseado no grupo de compras.
     */
    private function markClubeOnly(array &$produtos): void {
        if (empty($produtos)) return;
        try {
            $pdo = $this->getDirectPdo();
            $ids = array_values(array_filter(array_map(fn($p) => (int) ($p['id'] ?? 0), $produtos)));
            if (empty($ids)) return;
            $in = implode(',', array_fill(0, count($ids), '?'));
            $st = $pdo->prepare("SELECT p.id FROM produtos p INNER JOIN grupos_compras g ON g.id = p.grupo_compras_id WHERE p.id IN ($in) AND g.clube_only = 1");
            $st->execute($ids);
            $clubeIds = array_flip($st->fetchAll(\PDO::FETCH_COLUMN) ?: []);
            foreach ($produtos as &$p) {
                $p['clube_only'] = isset($clubeIds[(int) ($p['id'] ?? 0)]);
            }
            unset($p);
        } catch (\Throwable $e) {
            foreach ($produtos as &$p) {
                $p['clube_only'] = false;
            }
            unset($p);
        }
    }

    public function index(Request $request) {
        $search = $request->getParam('search');
        $categoria = $request->getParam('categoria');
        $verTodos = ($request->getParam('ver_todos') === '1');

        $page = (int) $request->getParam('page');
        if ($page <= 0) $page = 1;
        $limit = 20;
        $offset = ($page - 1) * $limit;

        // Sem paginação quando há busca ativa ou ver_todos
        $buscaAtiva = ($search !== null && trim($search) !== '');
        $semPaginacao = $buscaAtiva || $verTodos;

        $total = 0;
        
        if ($buscaAtiva && $categoria) {
            $total = $this->produtoModel->countSearch($search, $categoria);
            $produtos = $this->produtoModel->search($search, $total ?: 9999, $categoria, 0);
            $page = 1;
        } elseif ($buscaAtiva) {
            $total = $this->produtoModel->countSearch($search, null);
            $produtos = $this->produtoModel->search($search, $total ?: 9999, null, 0);
            $page = 1;
        } elseif ($verTodos && $categoria) {
            $total = $this->produtoModel->countByCategoriaId($categoria);
            $produtos = $this->produtoModel->getByCategoriaIdPaginado($categoria, $total ?: 9999, 0);
            $page = 1;
        } elseif ($verTodos) {
            $total = $this->produtoModel->countAllWithCategoria();
            $produtos = $this->produtoModel->getAllWithCategoriaPaginado($total ?: 9999, 0);
            $page = 1;
        } elseif ($categoria) {
            $total = $this->produtoModel->countByCategoriaId($categoria);
            $produtos = $this->produtoModel->getByCategoriaIdPaginado($categoria, $limit, $offset);
        } else {
            // Usar método que inclui JOIN com categorias
            $total = $this->produtoModel->countAllWithCategoria();
            $produtos = $this->produtoModel->getAllWithCategoriaPaginado($limit, $offset);
        }

        $totalPages = $semPaginacao ? 1 : (int) ceil(($total > 0 ? $total : 0) / $limit);
        if ($totalPages <= 0) $totalPages = 1;
        if ($page > $totalPages) {
            $page = $totalPages;
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

        // Marcar quais produtos possuem variações (para impedir add-to-cart direto na listagem)
        try {
            $pdo = $this->produtoModel->getConnection();
            $ids = array_values(array_filter(array_map(function ($p) {
                return (int) ($p['id'] ?? 0);
            }, $produtos)));
            $ids = array_values(array_unique($ids));

            $hasTable = false;
            try {
                $st = $pdo->query("SHOW TABLES LIKE 'produto_variacoes'");
                $hasTable = (bool) ($st && $st->fetch());
            } catch (\Throwable $e) {
                $hasTable = false;
            }

            $variavelMap = [];
            if ($hasTable && !empty($ids)) {
                $in = implode(',', array_fill(0, count($ids), '?'));
                $st = $pdo->prepare('SELECT produto_id, COUNT(*) AS cnt FROM produto_variacoes WHERE produto_id IN (' . $in . ') GROUP BY produto_id');
                $st->execute($ids);
                foreach (($st->fetchAll(\PDO::FETCH_ASSOC) ?: []) as $r) {
                    $pid = (int) ($r['produto_id'] ?? 0);
                    $cnt = (int) ($r['cnt'] ?? 0);
                    if ($pid > 0) {
                        $variavelMap[$pid] = ($cnt > 0);
                    }
                }
            }

            foreach ($produtos as &$p) {
                $pid = (int) ($p['id'] ?? 0);
                $p['is_variavel'] = ($pid > 0 && !empty($variavelMap[$pid]));
            }
            unset($p);
        } catch (\Throwable $e) {
            foreach ($produtos as &$p) {
                $p['is_variavel'] = false;
            }
            unset($p);
        }
        
        $categorias = $this->produtoModel->getCategorias();

        // Marcar produtos de grupos exclusivos do Clube Braziliana
        $this->markClubeOnly($produtos);
        $clubeAccess = $this->checkClubeAccess();
        
        $this->view('produto/index_moderno', [
            'produtos' => $produtos,
            'categorias' => $categorias,
            'search' => $search,
            'categoriaSelecionada' => $categoria,
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'totalPages' => $totalPages,
            'clube_acesso' => $clubeAccess['acesso'],
            'ver_todos' => $verTodos,
        ]);
    }

    public function arquivados(Request $request) {
        $produtos = $this->produtoModel->getArquivadosWithCategoria();

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
            'search' => null,
            'categoriaSelecionada' => null
        ]);
    }

    public function representante(Request $request) {
        $slug = (string) ($request->getParam('slug') ?? '');
        $slug = strtolower(trim($slug));
        if ($slug === '') {
            $this->redirect('/produtos');
            return;
        }

        $produtos = [];
        $categorias = [];
        try {
            $pdo = $this->produtoModel->getConnection();

            // Resolver representante pelo slug
            $hasUsers = false;
            try {
                $st = $pdo->query("SHOW TABLES LIKE 'usuarios'");
                $hasUsers = (bool) ($st && $st->fetch());
            } catch (\Throwable $e) {
                $hasUsers = false;
            }

            if (!$hasUsers) {
                $this->view('errors/404');
                return;
            }

            $uCols = [];
            try {
                $stCols = $pdo->query('DESCRIBE usuarios');
                $uCols = $stCols ? ($stCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            } catch (\Throwable $e) {
                $uCols = [];
            }

            if (!in_array('representante_slug', $uCols, true)) {
                $this->view('errors/404');
                return;
            }

            $stRep = $pdo->prepare('SELECT id, nome, name FROM usuarios WHERE representante_slug = ? LIMIT 1');
            $stRep->execute([$slug]);
            $rep = $stRep->fetch(\PDO::FETCH_ASSOC);
            if (!$rep || empty($rep['id'])) {
                $this->view('errors/404');
                return;
            }
            $repId = (int) ($rep['id'] ?? 0);
            if ($repId <= 0) {
                $this->view('errors/404');
                return;
            }

            // Listar produtos do representante (publicados/ativos)
            $pCols = [];
            try {
                $stColsP = $pdo->query('DESCRIBE produtos');
                $pCols = $stColsP ? ($stColsP->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            } catch (\Throwable $e) {
                $pCols = [];
            }

            if (!in_array('representante_id', $pCols, true)) {
                $this->view('errors/404');
                return;
            }

            $where = ['p.representante_id = :rid'];
            if (in_array('active', $pCols, true)) {
                $where[] = 'p.active = 1';
            } elseif (in_array('ativo', $pCols, true)) {
                $where[] = 'p.ativo = 1';
            }
            if (in_array('status', $pCols, true)) {
                $where[] = "LOWER(COALESCE(p.status,'')) IN ('published','ativo','active')";
            }
            // Excluir assessoria
            $where[] = "(p.sku IS NULL OR p.sku NOT LIKE 'ASS-%')";
            if (in_array('attributes', $pCols, true)) {
                $where[] = "(p.attributes IS NULL OR p.attributes NOT LIKE '%\"fonte\":\"assessoria\"%')";
            }

            $sql = "SELECT p.*, c.name as categoria
                    FROM produtos p
                    LEFT JOIN categorias c ON p.category_id = c.id
                    WHERE " . implode(' AND ', $where) . "
                    ORDER BY p.featured DESC, p.name ASC";

            $st = $pdo->prepare($sql);
            $st->bindValue(':rid', $repId, \PDO::PARAM_INT);
            $st->execute();
            $produtos = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];

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
        } catch (\Throwable $e) {
            $produtos = [];
            $categorias = [];
        }

        $this->view('produto/index_moderno', [
            'produtos' => $produtos,
            'categorias' => $categorias,
            'search' => null,
            'categoriaSelecionada' => null
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

        // Bloquear acesso direto a produtos ocultos (só visíveis no pedido manual)
        if (!empty($produto['oculto'])) {
            $this->view('errors/404');
            return;
        }

        // Bloquear acesso direto a produtos de assessoria/redirecionamento
        $produtoSku = (string) ($produto['sku'] ?? '');
        if (stripos($produtoSku, 'ASS-') === 0) {
            $this->view('errors/404');
            return;
        }
        $produtoAttrs = $produto['attributes'] ?? null;
        if (is_string($produtoAttrs) && stripos($produtoAttrs, '"fonte":"assessoria"') !== false) {
            $this->view('errors/404');
            return;
        }
        // Bloquear por categoria Assessoria/Redirecionamento
        $produtoCategoria = strtolower(trim((string) ($produto['categoria'] ?? '')));
        if ($produtoCategoria === '') {
            // find() não traz categoria via JOIN, buscar pelo category_id
            try {
                $catId = (int) ($produto['categoria_id'] ?? 0);
                if ($catId > 0) {
                    $pdo = $this->getDirectPdo();
                    $stCat = $pdo->prepare('SELECT name FROM categorias WHERE id = ? LIMIT 1');
                    $stCat->execute([$catId]);
                    $produtoCategoria = strtolower(trim((string) ($stCat->fetchColumn() ?: '')));
                }
            } catch (\Throwable $e) {}
        }
        if (in_array($produtoCategoria, ['assessoria', 'redirecionamento'], true)) {
            $this->view('errors/404');
            return;
        }

        // Verificar se produto pertence a grupo exclusivo do Clube Braziliana
        $clubeOnly = false;
        try {
            $grupoId = (int) ($produto['grupo_compras_id'] ?? 0);
            if ($grupoId > 0) {
                $pdo = $this->getDirectPdo();
                $st = $pdo->prepare('SELECT clube_only FROM grupos_compras WHERE id = ? LIMIT 1');
                $st->execute([$grupoId]);
                $clubeOnly = ((int) ($st->fetchColumn() ?: 0)) === 1;
            }
        } catch (\Throwable $e) {}

        $clubeAccess = $this->checkClubeAccess();
        $clubeBloqueado = $clubeOnly && !$clubeAccess['acesso'];
        
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
            'variacoesUi' => $variacoesUi,
            'imposto_local_percent' => $this->getImpostoLocalPercentForProduct($produto),
            'clube_only' => $clubeOnly,
            'clube_bloqueado' => $clubeBloqueado,
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
                    $st = $pdo->prepare('SELECT COUNT(*) FROM produto_variacoes WHERE produto_id = ? AND ativo = 1');
                    $st->execute([$produtoId]);
                    $counts['produto_variacoes_ativas'] = (int) $st->fetchColumn();
                } catch (\Throwable $e) {
                    $counts['produto_variacoes_ativas'] = null;
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

                $schema = [];
                try {
                    $st = $pdo->prepare("SELECT COLUMN_NAME, DATA_TYPE FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'produto_variacoes' ORDER BY ORDINAL_POSITION ASC");
                    $st->execute();
                    $schema['produto_variacoes_cols'] = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                } catch (\Throwable $e) {
                    $schema['produto_variacoes_cols'] = null;
                }
                try {
                    $st = $pdo->prepare("SELECT COLUMN_NAME, DATA_TYPE FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'produto_variacao_itens' ORDER BY ORDINAL_POSITION ASC");
                    $st->execute();
                    $schema['produto_variacao_itens_cols'] = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                } catch (\Throwable $e) {
                    $schema['produto_variacao_itens_cols'] = null;
                }
                try {
                    $st = $pdo->prepare("SELECT COLUMN_NAME, DATA_TYPE FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'variacao_tipos' ORDER BY ORDINAL_POSITION ASC");
                    $st->execute();
                    $schema['variacao_tipos_cols'] = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                } catch (\Throwable $e) {
                    $schema['variacao_tipos_cols'] = null;
                }
                try {
                    $st = $pdo->prepare("SELECT COLUMN_NAME, DATA_TYPE FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'variacao_opcoes' ORDER BY ORDINAL_POSITION ASC");
                    $st->execute();
                    $schema['variacao_opcoes_cols'] = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                } catch (\Throwable $e) {
                    $schema['variacao_opcoes_cols'] = null;
                }

                $samples = [];
                try {
                    $st = $pdo->prepare('SELECT id, produto_id, price_override, stock, ativo FROM produto_variacoes WHERE produto_id = ? ORDER BY id ASC LIMIT 10');
                    $st->execute([$produtoId]);
                    $samples['produto_variacoes'] = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                } catch (\Throwable $e) {
                    $samples['produto_variacoes'] = null;
                }
                try {
                    $st = $pdo->prepare('SELECT pvi.* FROM produto_variacao_itens pvi INNER JOIN produto_variacoes pv ON pv.id = pvi.produto_variacao_id WHERE pv.produto_id = ? ORDER BY pvi.produto_variacao_id ASC, pvi.tipo_id ASC, pvi.opcao_id ASC LIMIT 50');
                    $st->execute([$produtoId]);
                    $samples['produto_variacao_itens'] = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                } catch (\Throwable $e) {
                    $samples['produto_variacao_itens'] = null;
                }

                $data['debug_schema'] = $schema;
                $data['debug_samples'] = $samples;
            }

            $this->json($data);
        } catch (\Throwable $e) {
            $this->json(['enabled' => false, 'atributos' => [], 'variacoes' => [], 'fotos_por_variacao' => []]);
        }
    }

    public function selecionar(Request $request) {
        $produtoId = $request->getParam('id');
        error_log("[PRODUTO_CART] selecionar chamado produto_id={$produtoId}");
        $produtoVariacaoId = $request->getParam('produto_variacao_id');
        $quantidade = $request->getParam('quantidade', 1);
        
        if (!$produtoId) {
            $this->json(['error' => 'Produto não informado'], 400);
        }
        
        $produto = $this->produtoModel->find($produtoId);
        
        if (!$produto) {
            $this->json(['error' => 'Produto não encontrado'], 404);
        }
        
        $precoBase = (float) ($produto['preco'] ?? $produto['valor'] ?? 0);
        if ($precoBase < 0) $precoBase = 0.0;
        $precoPromo = (float) ($produto['preco_promocao'] ?? 0);
        if ($precoPromo < 0) $precoPromo = 0.0;
        $itemPrice = ($precoPromo > 0 && $precoPromo < $precoBase) ? $precoPromo : $precoBase;
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
        
        $auth = new AuthService();
        $u = $auth->getUsuarioLogado();
        $uid = (int) ($u['id'] ?? 0);
        if ($uid > 0) {
            try {
                $cartModel = new Carrinho();
                $cart = $cartModel->getOrCreateCarrinho($uid, null, 'BRL');
                $cartId = is_array($cart) ? (int) ($cart['id'] ?? 0) : (int) $cart;
                $ok = $cartModel->adicionarItem($cartId, (int) $produtoId, (int) $quantidade, $pvId, $variacaoDescricao);
                if (!$ok) {
                    $this->json(['error' => 'Não foi possível adicionar o item ao carrinho'], 400);
                    return;
                }

                $stCnt = $cartModel->getConnection()->prepare('SELECT COALESCE(SUM(quantidade),0) FROM carrinho_items WHERE carrinho_id = ?');
                $stCnt->execute([$cartId]);
                $totalItens = (int) ($stCnt->fetchColumn() ?: 0);

                $stTot = $cartModel->getConnection()->prepare('SELECT valor_total FROM carrinhos WHERE id = ? LIMIT 1');
                $stTot->execute([$cartId]);
                $totalValor = (float) ($stTot->fetchColumn() ?: 0);

                // Verificar brinde ativo para este produto
                $brindeMsg = '';
                try {
                    $brindeService = new \App\Services\BrindeService();
                    $brinde = $brindeService->getBrindeAtivo((int) $produtoId);
                    if ($brinde) {
                        $brindeProductId = (int) $brinde['brinde_produto_id'];
                        $stCheck = $cartModel->getConnection()->prepare('SELECT id FROM carrinho_items WHERE carrinho_id = ? AND produto_id = ? LIMIT 1');
                        $stCheck->execute([$cartId, $brindeProductId]);
                        if (!$stCheck->fetchColumn()) {
                            $cartModel->adicionarItem($cartId, $brindeProductId, (int) ($brinde['quantidade_brinde'] ?? 1), null, null);
                            $cartModel->getConnection()->prepare('UPDATE carrinho_items SET preco_unitario = 0, subtotal = 0 WHERE carrinho_id = ? AND produto_id = ? ORDER BY id DESC LIMIT 1')->execute([$cartId, $brindeProductId]);
                            $brindeMsg = ' + brinde: ' . ($brinde['brinde_nome'] ?? 'Brinde') . ' 🎁';
                            $totalItens++;
                        }
                    }
                } catch (\Exception $e) {
                    error_log('[BRINDE] Erro ProdutoController: ' . $e->getMessage());
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
            $vars = [];
            try {
                $stmtVars = $pdo->prepare('SELECT id, price_override, stock, ativo FROM produto_variacoes WHERE produto_id = ? AND ativo = 1 ORDER BY id ASC');
                $stmtVars->execute([$produtoId]);
                $vars = $stmtVars->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            } catch (\Throwable $e) {
                $stmtVars = $pdo->prepare('SELECT id, price_override, stock FROM produto_variacoes WHERE produto_id = ? ORDER BY id ASC');
                $stmtVars->execute([$produtoId]);
                $vars = $stmtVars->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            }
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
                    SELECT pvi.produto_variacao_id, pvi.tipo_id, pvi.opcao_id, vt.nome AS tipo_nome, vo.valor AS opcao_valor, vo.nome_exibicao AS opcao_nome_exibicao, vo.imagem AS opcao_imagem
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
                        $it['opcao_nome_exibicao'] = '';
                        $it['opcao_imagem'] = '';
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
                $opNomeExibicao = (string) ($it['opcao_nome_exibicao'] ?? '');
                $opImagem = (string) ($it['opcao_imagem'] ?? '');

                $itensPorVar[$vId][] = [
                    'tipo_id' => $tipoId,
                    'opcao_id' => $opId,
                    'tipo_nome' => $tipoNome,
                    'opcao_valor' => $opValor,
                    'opcao_nome_exibicao' => $opNomeExibicao,
                    'opcao_imagem' => $opImagem,
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
                    'nome_exibicao' => $opNomeExibicao,
                    'imagem' => $opImagem,
                ];
            }

            $fotosPorVar = [];
            if ($this->tableExists($pdo, 'produto_variacao_fotos')) {
                try {
                    $hasLegenda = false;
                    try {
                        $stCol = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'produto_variacao_fotos' AND column_name = 'legenda'");
                        $stCol->execute();
                        $hasLegenda = ((int) $stCol->fetchColumn()) > 0;
                    } catch (\Throwable $e) {
                        $hasLegenda = false;
                    }

                    $cols = $hasLegenda
                        ? 'id, produto_variacao_id, nome_arquivo, legenda, ordem'
                        : 'id, produto_variacao_id, nome_arquivo, ordem';

                    $sqlFotos = 'SELECT ' . $cols . ' FROM produto_variacao_fotos WHERE produto_variacao_id IN (' . $in . ') ORDER BY produto_variacao_id ASC, ordem ASC, id ASC';
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
                } catch (\Throwable $e) {
                    $fotosPorVar = [];
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

    private function getImpostoLocalPercentForProduct(array $produto): float {
        try {
            $pid = (int) ($produto['id'] ?? 0);
            if ($pid <= 0) return 0.0;
            $pdo = $this->getDirectPdo();
            $st = $pdo->prepare("SELECT g.imposto_local_percent FROM grupos_compras g INNER JOIN produtos p ON p.grupo_compras_id = g.id WHERE p.id = ? AND g.imposto_local_percent > 0 LIMIT 1");
            $st->execute([$pid]);
            return (float) ($st->fetchColumn() ?: 0);
        } catch (\Throwable $e) {
            return 0.0;
        }
    }
}
