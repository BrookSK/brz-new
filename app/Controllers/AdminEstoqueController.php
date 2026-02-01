<?php
namespace App\Controllers;

class AdminEstoqueController extends Controller {
    private $connection;

    public function __construct() {
        $this->connection = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
    }

    private function setFlash(string $message, string $type = 'success'): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        $_SESSION['message'] = $message;
        $_SESSION['message_type'] = $type;
    }

    private function renderFlashIfAny(): void {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        if (!isset($_SESSION['message'])) {
            return;
        }
        $type = (string) ($_SESSION['message_type'] ?? 'info');
        $msg = (string) $_SESSION['message'];
        unset($_SESSION['message'], $_SESSION['message_type']);
        echo '<div class="alert alert-' . htmlspecialchars($type) . ' alert-dismissible fade show mt-3" role="alert">'
            . htmlspecialchars($msg)
            . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>'
            . '</div>';
    }

    private function tableExists(string $table): bool {
        try {
            $stmt = $this->connection->prepare('SHOW TABLES LIKE ?');
            $stmt->execute([$table]);
            return (bool) $stmt->fetchColumn();
        } catch (\Exception $e) {
            return false;
        }
    }

    private function getProdutosSchema(): array {
        $cols = [];
        try {
            $stmtCols = $this->connection->query('DESCRIBE produtos');
            $cols = $stmtCols ? $stmtCols->fetchAll(\PDO::FETCH_COLUMN) : [];
        } catch (\Exception $e) {
            $cols = [];
        }

        $nameCol = null;
        foreach (['name', 'nome', 'titulo', 'title', 'produto_nome'] as $c) {
            if (in_array($c, $cols, true)) {
                $nameCol = $c;
                break;
            }
        }

        $skuCol = in_array('sku', $cols, true) ? 'sku' : null;

        $activeCol = null;
        foreach (['active', 'ativo'] as $c) {
            if (in_array($c, $cols, true)) {
                $activeCol = $c;
                break;
            }
        }

        $priceCol = null;
        foreach (['price', 'valor', 'preco', 'sale_price', 'cost_price'] as $c) {
            if (in_array($c, $cols, true)) {
                $priceCol = $c;
                break;
            }
        }

        $currencyCol = null;
        foreach (['moeda', 'currency'] as $c) {
            if (in_array($c, $cols, true)) {
                $currencyCol = $c;
                break;
            }
        }

        $imgCol = null;
        foreach (['foto_principal', 'image_url', 'image', 'imagem', 'images'] as $c) {
            if (in_array($c, $cols, true)) {
                $imgCol = $c;
                break;
            }
        }

        return [
            'cols' => $cols,
            'nameCol' => $nameCol,
            'skuCol' => $skuCol,
            'activeCol' => $activeCol,
            'priceCol' => $priceCol,
            'currencyCol' => $currencyCol,
            'imgCol' => $imgCol,
        ];
    }

    private function resolveProdutoImagem(array $produto, ?string $imgCol): ?string {
        if (!$imgCol) {
            return null;
        }

        $raw = $produto[$imgCol] ?? null;
        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }

        $raw = trim($raw);

        // coluna images pode vir como JSON (array de URLs/paths)
        if ($imgCol === 'images' && ($raw[0] === '[' || $raw[0] === '{')) {
            $decoded = json_decode($raw, true);
            if (is_array($decoded)) {
                $candidate = null;
                if (isset($decoded[0]) && is_string($decoded[0])) {
                    $candidate = $decoded[0];
                } elseif (isset($decoded['0']) && is_string($decoded['0'])) {
                    $candidate = $decoded['0'];
                } elseif (isset($decoded['url']) && is_string($decoded['url'])) {
                    $candidate = $decoded['url'];
                }
                if (is_string($candidate) && trim($candidate) !== '') {
                    $raw = trim($candidate);
                }
            }
        }

        if (preg_match('#^https?://#i', $raw)) {
            return $raw;
        }

        if (strpos($raw, '/uploads/') === 0) {
            return $raw;
        }

        if (strpos($raw, 'uploads/') === 0) {
            return '/' . $raw;
        }

        return '/uploads/produtos/' . ltrim($raw, '/');
    }

    public function buscarProdutos($request) {
        try {
            $schema = $this->getProdutosSchema();
            $nameCol = $schema['nameCol'];
            $skuCol = $schema['skuCol'];
            $activeCol = $schema['activeCol'];
            $priceCol = $schema['priceCol'];
            $currencyCol = $schema['currencyCol'];
            $imgCol = $schema['imgCol'];

            $q = trim((string) $request->getParam('q', ''));
            $limit = (int) $request->getParam('limit', 25);
            if ($limit <= 0) {
                $limit = 25;
            }
            if ($limit > 50) {
                $limit = 50;
            }

            $select = ['id'];
            if ($nameCol) {
                $select[] = $nameCol . ' AS nome';
            } else {
                $select[] = "CAST(id AS CHAR) AS nome";
            }
            if ($skuCol) {
                $select[] = $skuCol . ' AS sku';
            } else {
                $select[] = "'' AS sku";
            }
            if ($priceCol) {
                $select[] = $priceCol . ' AS preco';
            } else {
                $select[] = "NULL AS preco";
            }
            if ($currencyCol) {
                $select[] = $currencyCol . ' AS moeda';
            } else {
                $select[] = "'' AS moeda";
            }
            if ($imgCol) {
                $select[] = $imgCol . ' AS imagem_raw';
            } else {
                $select[] = "'' AS imagem_raw";
            }

            $sql = 'SELECT ' . implode(', ', $select) . ' FROM produtos';
            $where = [];
            $params = [];

            if ($activeCol) {
                $where[] = $activeCol . ' = 1';
            }

            if ($q !== '') {
                $likeClauses = [];
                if ($nameCol) {
                    $likeClauses[] = $nameCol . ' LIKE :q';
                }
                if ($skuCol) {
                    $likeClauses[] = $skuCol . ' LIKE :q';
                }

                $params[':q'] = '%' . $q . '%';

                if (ctype_digit($q)) {
                    $or = ['id = :id'];
                    $params[':id'] = (int) $q;
                    if (!empty($likeClauses)) {
                        $or[] = '(' . implode(' OR ', $likeClauses) . ')';
                    }
                    $where[] = '(' . implode(' OR ', $or) . ')';
                } else {
                    if (!empty($likeClauses)) {
                        $where[] = '(' . implode(' OR ', $likeClauses) . ')';
                    }
                }
            }

            if (!empty($where)) {
                $sql .= ' WHERE ' . implode(' AND ', $where);
            }
            $sql .= ' ORDER BY nome LIMIT ' . (int) $limit;

            $stmt = $this->connection->prepare($sql);
            foreach ($params as $k => $v) {
                $stmt->bindValue($k, $v);
            }
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $out = [];
            foreach ($rows as $r) {
                $img = null;
                if ($imgCol) {
                    $img = $this->resolveProdutoImagem(['imagem_raw' => $r['imagem_raw']], 'imagem_raw');
                }

                $out[] = [
                    'id' => (int) ($r['id'] ?? 0),
                    'nome' => (string) ($r['nome'] ?? ''),
                    'sku' => (string) ($r['sku'] ?? ''),
                    'preco' => isset($r['preco']) ? (float) $r['preco'] : null,
                    'moeda' => (string) ($r['moeda'] ?? ''),
                    'imagem' => $img,
                ];
            }

            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => true, 'items' => $out]);
            exit;
        } catch (\Exception $e) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['success' => false, 'items' => []]);
            exit;
        }
    }

    public function entrada($request) {
        $prefillProdutoId = (int) $request->getParam('produto_id', 0);

        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrada de Estoque - Braziliana Shop Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">';

        renderAdminSidebarStyles();

        echo '</head>
<body>
    <div class="container-fluid">
        <div class="row">';

        renderAdminSidebar('estoque');

        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-plus me-2"></i>Entrada de Estoque (Galpão)</h1>
                    <div>
                        <a class="btn btn-outline-secondary" href="/admin/estoque">
                            <i class="fas fa-arrow-left me-1"></i>Voltar
                        </a>
                    </div>
                </div>';

        $this->renderFlashIfAny();

        echo '
                <div class="row g-4">
                    <div class="col-lg-5">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">Buscar produto</h6>
                            </div>
                            <div class="card-body">
                                <input type="text" class="form-control" id="produto_busca" placeholder="Digite nome ou SKU..." oninput="buscarProdutos()" autocomplete="off">
                                <div class="text-muted small mt-2">Clique em um produto para selecionar.</div>
                                <div id="resultado_busca" class="mt-3" style="max-height: 520px; overflow:auto;"></div>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-7">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="mb-0">Dados da entrada</h6>
                            </div>
                            <div class="card-body">
                                <form method="POST" action="/admin/estoque/salvar" id="form_entrada_estoque">
                                    <input type="hidden" name="produto_id" id="produto_id" value="' . (int) $prefillProdutoId . '">

                                    <div class="mb-3">
                                        <label class="form-label">Produto selecionado</label>
                                        <div id="produto_selecionado" class="p-3" style="border: 1px solid rgba(148, 163, 184, 0.28); border-radius: 14px; background: #fff;">
                                            <div class="text-muted">Nenhum produto selecionado.</div>
                                        </div>
                                    </div>

                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label class="form-label">Quantidade disponível</label>
                                            <input type="number" class="form-control" name="quantidade" min="1" step="1" required>
                                        </div>

                                        <div class="col-md-4">
                                            <label class="form-label">Data da compra</label>
                                            <input type="date" class="form-control" name="data_compra">
                                        </div>

                                        <div class="col-md-4">
                                            <label class="form-label">Alimentício</label>
                                            <div class="form-check form-switch mt-1">
                                                <input class="form-check-input" type="checkbox" value="1" id="is_alimenticio" name="is_alimenticio" onchange="toggleValidade()">
                                                <label class="form-check-label" for="is_alimenticio">Controlar validade</label>
                                            </div>
                                        </div>

                                        <div class="col-md-4" id="grupo_validade" style="display:none;">
                                            <label class="form-label">Data de validade</label>
                                            <input type="date" class="form-control" name="data_validade">
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">Galpão</label>
                                            <input type="text" class="form-control" name="galpao" placeholder="Ex: Galpão A">
                                        </div>

                                        <div class="col-md-6">
                                            <label class="form-label">Prateleira</label>
                                            <input type="text" class="form-control" name="prateleira" placeholder="Ex: 3">
                                        </div>

                                        <div class="col-12">
                                            <label class="form-label">Observação</label>
                                            <input type="text" class="form-control" name="observacao" placeholder="Opcional">
                                        </div>
                                    </div>

                                    <div class="mt-4 d-flex gap-2">
                                        <button type="submit" class="btn btn-primary" onclick="return validarEntradaEstoque()">
                                            <i class="fas fa-save me-1"></i>Salvar entrada
                                        </button>
                                        <a class="btn btn-outline-secondary" href="/admin/estoque">Cancelar</a>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <script>
        var lastQuery = "";
        var busy = false;
        var produtosCache = {};

        function toggleValidade() {
            var chk = document.getElementById("is_alimenticio");
            var grp = document.getElementById("grupo_validade");
            if (!chk || !grp) return;
            grp.style.display = chk.checked ? "" : "none";
        }

        function formatMoney(v, moeda) {
            if (v === null || typeof v === "undefined" || isNaN(v)) return "";
            try {
                return (moeda ? (moeda + " ") : "") + Number(v).toFixed(2);
            } catch (e) {
                return (moeda ? (moeda + " ") : "") + v;
            }
        }

        function renderItem(item) {
            produtosCache[item.id] = item;
            var img = item.imagem ? `<img src="${item.imagem}" alt="" style="width:56px;height:56px;object-fit:cover;border-radius:12px;">` : `<div style="width:56px;height:56px;border-radius:12px;background:rgba(148,163,184,.12);border:1px solid rgba(148,163,184,.22);display:flex;align-items:center;justify-content:center;color:#64748b;"><i class="fas fa-image"></i></div>`;
            var sku = item.sku ? `<div class="text-muted small">SKU: ${item.sku}</div>` : ``;
            var preco = item.preco !== null ? `<div class="small" style="color:#0b1f3a;font-weight:700;">${formatMoney(item.preco, item.moeda)}</div>` : ``;
            return `
                <button type="button" class="w-100 text-start p-2 mb-2" style="border:1px solid rgba(148,163,184,.22);border-radius:14px;background:#fff;" onclick="selecionarProduto(${item.id})" id="produto_item_${item.id}">
                    <div class="d-flex gap-3 align-items-center">
                        ${img}
                        <div class="flex-grow-1">
                            <div style="font-weight:700;color:#0f172a;">${item.nome || "(Sem nome)"}</div>
                            ${sku}
                            ${preco}
                        </div>
                    </div>
                </button>
            `;
        }

        function buscarProdutos(force) {
            var input = document.getElementById("produto_busca");
            var box = document.getElementById("resultado_busca");
            if (!input || !box) return;
            var q = (input.value || "").trim();
            if (!force && q === lastQuery) return;
            lastQuery = q;
            if (busy) return;
            busy = true;

            fetch("/admin/estoque/buscar-produtos?q=" + encodeURIComponent(q) + "&limit=30")
                .then(r => r.json())
                .then(data => {
                    var items = (data && data.items) ? data.items : [];
                    if (!items.length) {
                        box.innerHTML = `<div class="text-muted">Nenhum produto encontrado.</div>`;
                        return;
                    }
                    box.innerHTML = items.map(renderItem).join("");
                })
                .catch(() => {
                    box.innerHTML = `<div class="text-muted">Erro ao buscar produtos.</div>`;
                })
                .finally(() => {
                    busy = false;
                });
        }

        function selecionarProduto(id) {
            var inputId = document.getElementById("produto_id");
            var preview = document.getElementById("produto_selecionado");
            if (!inputId || !preview) return;

            var item = produtosCache[id] || null;
            if (!item) {
                alert("Não foi possível carregar os dados do produto selecionado.");
                return;
            }

            inputId.value = String(id);
            var img = item.imagem ? `<img src="${item.imagem}" alt="" style="width:64px;height:64px;object-fit:cover;border-radius:12px;">` : `<div style="width:64px;height:64px;border-radius:12px;background:rgba(148,163,184,.12);border:1px solid rgba(148,163,184,.22);display:flex;align-items:center;justify-content:center;color:#64748b;"><i class="fas fa-image"></i></div>`;
            var sku = item.sku ? `<div class="text-muted small">SKU: ${item.sku}</div>` : ``;
            var preco = item.preco !== null ? `<div class="small" style="color:#0b1f3a;font-weight:700;">${formatMoney(item.preco, item.moeda)}</div>` : ``;

            preview.innerHTML = `
                <div class="d-flex gap-3 align-items-center">
                    ${img}
                    <div>
                        <div style="font-weight:800;color:#0f172a;">${item.nome || "(Sem nome)"}</div>
                        ${sku}
                        ${preco}
                    </div>
                </div>
            `;
        }

        function validarEntradaEstoque() {
            var produtoId = document.getElementById("produto_id");
            if (!produtoId || !produtoId.value || produtoId.value === "0") {
                alert("Selecione um produto antes de salvar.");
                return false;
            }
            return true;
        }

        document.addEventListener("DOMContentLoaded", function() {
            buscarProdutos(true);
            var pre = ' . (int) $prefillProdutoId . ';
            if (pre && pre > 0) {
                fetch("/admin/estoque/buscar-produtos?q=" + encodeURIComponent(String(pre)) + "&limit=30")
                    .then(r => r.json())
                    .then(data => {
                        var items = (data && data.items) ? data.items : [];
                        for (var i = 0; i < items.length; i++) {
                            if (items[i].id === pre) {
                                var box = document.getElementById("resultado_busca");
                                if (box) box.innerHTML = items.map(renderItem).join("");
                                selecionarProduto(pre);
                                break;
                            }
                        }
                    });
            }
        });
    </script>';

        renderAdminScripts();

        echo '</body></html>';
        exit;
    }

    public function index($request) {
        try {
            // Esta tela é apenas para listagem. A entrada é feita em /admin/estoque/entrada.

            $schemaProdutos = $this->getProdutosSchema();
            $imgCol = $schemaProdutos['imgCol'] ?? null;
            $imgSelect = "'' AS imagem_raw";
            if (is_string($imgCol) && $imgCol !== '') {
                $imgSelect = 'p.' . $imgCol . ' AS imagem_raw';
            }

            // Buscar status geral do estoque (apenas itens com quantidade no galpão)
            $stmt = $this->connection->prepare("
                SELECT
                    v.*, 
                    loc.localizacao,
                    loc.data_compra_mais_recente,
                    loc.validade_mais_proxima,
                    {$imgSelect}
                FROM vw_status_geral_estoque v
                JOIN produtos p ON p.id = v.produto_id
                JOIN (
                    SELECT
                        e.produto_id,
                        GROUP_CONCAT(DISTINCT CONCAT(COALESCE(e.galpao, ''), ' - Prateleira ', COALESCE(e.prateleira, '')) SEPARATOR ', ') AS localizacao,
                        MAX(e.data_compra) AS data_compra_mais_recente,
                        MIN(CASE WHEN e.is_alimenticio = 1 AND e.data_validade IS NOT NULL THEN e.data_validade ELSE NULL END) AS validade_mais_proxima
                    FROM estoque_interno e
                    WHERE e.quantidade > 0
                    GROUP BY e.produto_id
                ) loc ON loc.produto_id = v.produto_id
                ORDER BY v.produto_nome
            ");
            $stmt->execute();
            $status_geral = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Estatísticas
            $stmt = $this->connection->prepare("SELECT
                    COUNT(*) as total_produtos,
                    SUM(CASE WHEN status_estoque IN ('crítico','critico') THEN 1 ELSE 0 END) as criticos,
                    SUM(CASE WHEN status_estoque = 'baixo' THEN 1 ELSE 0 END) as baixos,
                    SUM(CASE WHEN status_estoque = 'normal' THEN 1 ELSE 0 END) as normais
                FROM (
                    SELECT v.*
                    FROM vw_status_geral_estoque v
                    JOIN (SELECT DISTINCT produto_id FROM estoque_interno WHERE quantidade > 0) e ON e.produto_id = v.produto_id
                ) t
            ");
            $stmt->execute();
            $estatisticas = $stmt->fetch(\PDO::FETCH_ASSOC);

        } catch (\Exception $e) {
            $produtos = [];
            $status_geral = [];
            $estatisticas = ['total_produtos' => 0, 'criticos' => 0, 'baixos' => 0, 'normais' => 0];
        }

        // Incluir o partial do menu lateral
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Estoque Interno - Braziliana Shop Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">';
        
        // Renderizar estilos do menu
        renderAdminSidebarStyles();
        
        echo '</head>
<body>
    <div class="container-fluid">
        <div class="row">';
        
        // Renderizar menu lateral usando o partial
        renderAdminSidebar('estoque');
        
        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><i class="fas fa-warehouse me-2"></i>Estoque Interno</h1>
                    <div>
                        <a class="btn btn-success me-2" href="/admin/estoque/entrada">
                            <i class="fas fa-plus me-1"></i>Entrada de Estoque
                        </a>
                        <button type="button" class="btn btn-primary me-2" onclick="window.open(\'/admin/estoque/compras/pdf\', \'_blank\')">
                            <i class="fas fa-file-pdf me-1"></i>Gerar PDF
                        </button>
                        <button type="button" class="btn btn-info" onclick="location.reload()">
                            <i class="fas fa-sync me-1"></i>Atualizar
                        </button>
                    </div>
                </div>';

        $this->renderFlashIfAny();

                // Cards de Estatísticas
                echo '<div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card card-stats bg-primary text-white">
                            <div class="card-body">
                                <h5 class="card-title">Total Produtos</h5>
                                <h3>' . number_format($estatisticas['total_produtos']) . '</h3>
                                <small>Ativos no sistema</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-stats bg-danger text-white">
                            <div class="card-body">
                                <h5 class="card-title">Estoque Crítico</h5>
                                <h3>' . number_format($estatisticas['criticos']) . '</h3>
                                <small>Abaixo do mínimo</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-stats bg-warning text-dark">
                            <div class="card-body">
                                <h5 class="card-title">Estoque Baixo</h5>
                                <h3>' . number_format($estatisticas['baixos']) . '</h3>
                                <small>Abaixo do ideal</small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-stats bg-success text-white">
                            <div class="card-body">
                                <h5 class="card-title">Estoque Normal</h5>
                                <h3>' . number_format($estatisticas['normais']) . '</h3>
                                <small>Níveis adequados</small>
                            </div>
                        </div>
                    </div>
                </div>';

                // Tabela de Estoque
                echo '<div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-list me-2"></i>Estoque Atual</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Produto</th>
                                        <th>SKU</th>
                                        <th>Loja</th>
                                        <th>Disponível</th>
                                        <th>Data compra</th>
                                        <th>Validade</th>
                                        <th>Localização</th>
                                        <th>Status</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>';
                                
                                foreach ($status_geral as $item) {
                                    $status_class = $item['status_estoque'] == 'crítico' ? 'danger' : 
                                                   ($item['status_estoque'] == 'baixo' ? 'warning' : 'success');

                                    $imgUrl = null;
                                    if (!empty($item['imagem_raw'])) {
                                        $imgUrl = $this->resolveProdutoImagem(['imagem_raw' => (string) $item['imagem_raw']], 'imagem_raw');
                                    }
                                    $imgTag = $imgUrl
                                        ? '<img src="' . htmlspecialchars($imgUrl) . '" alt="" style="width:36px;height:36px;object-fit:cover;border-radius:10px; border: 1px solid rgba(148, 163, 184, 0.22); background: rgba(148, 163, 184, 0.06);">'
                                        : '<div style="width:36px;height:36px;border-radius:10px;background:rgba(148,163,184,.12);border:1px solid rgba(148,163,184,.22);display:flex;align-items:center;justify-content:center;color:#64748b;"><i class="fas fa-image"></i></div>';
                                    
                                    echo '<tr>
                                        <td>
                                            <div class="d-flex gap-2 align-items-center">
                                                ' . $imgTag . '
                                                <div>
                                                    <strong>' . htmlspecialchars($item['produto_nome']) . '</strong>
                                                    <br><small class="text-muted">ID: ' . $item['produto_id'] . '</small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>' . htmlspecialchars($item['sku']) . '</td>
                                        <td>
                                            <span class="badge bg-' . ($item['loja'] == 'sams' ? 'primary' : ($item['loja'] == 'costco' ? 'success' : 'secondary')) . '">
                                                ' . ucfirst($item['loja']) . '
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge bg-' . $status_class . '">' . $item['quantidade_estoque'] . '</span>
                                        </td>
                                        <td>' . (!empty($item['data_compra_mais_recente']) ? date('d/m/Y', strtotime($item['data_compra_mais_recente'])) : '-') . '</td>
                                        <td>' . (!empty($item['validade_mais_proxima']) ? date('d/m/Y', strtotime($item['validade_mais_proxima'])) : '-') . '</td>
                                        <td>' . (!empty($item['localizacao']) ? htmlspecialchars($item['localizacao']) : '-') . '</td>
                                        <td>
                                            <span class="badge bg-' . $status_class . '">' . ucfirst($item['status_estoque']) . '</span>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a class="btn btn-outline-success" href="/admin/estoque/entrada?produto_id=' . (int) $item['produto_id'] . '">
                                                    <i class="fas fa-plus"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>';
                                }
                                
                                echo '</tbody>
                            </table>
                        </div>
                    </div>
                </div>';

                echo '</main>
        </div>
    </div>';

    // Renderizar scripts
    renderAdminScripts();
    
    echo '</body>
</html>';
    }

    public function salvar($request) {
        try {
            $produtoId = (int) $request->getParam('produto_id');
            $quantidade = (int) $request->getParam('quantidade');
            $dataCompra = trim((string) $request->getParam('data_compra', ''));
            $isAlimenticio = $request->getParam('is_alimenticio', '0') ? 1 : 0;
            $dataValidade = trim((string) $request->getParam('data_validade', ''));
            $galpao = trim((string) $request->getParam('galpao', ''));
            $prateleira = trim((string) $request->getParam('prateleira', ''));
            $observacao = trim((string) $request->getParam('observacao', ''));

            if ($produtoId <= 0) {
                $this->setFlash('Selecione um produto válido.', 'danger');
                header('Location: /admin/estoque');
                exit;
            }
            if ($quantidade <= 0) {
                $this->setFlash('Informe uma quantidade válida.', 'danger');
                header('Location: /admin/estoque');
                exit;
            }
            if ($isAlimenticio === 0) {
                $dataValidade = '';
            }

            if (!$this->tableExists('estoque_interno') || !$this->tableExists('estoque_movimentacao')) {
                $this->setFlash('Tabelas de estoque não encontradas no banco. Rode a migration 020_create_estoque_profissional_fix.sql no banco do servidor.', 'danger');
                header('Location: /admin/estoque/entrada');
                exit;
            }

            // Validar produto existente
            $stmtProduto = $this->connection->prepare('SELECT id FROM produtos WHERE id = :id LIMIT 1');
            $stmtProduto->execute([':id' => $produtoId]);
            if (!$stmtProduto->fetchColumn()) {
                $this->setFlash('Produto não encontrado.', 'danger');
                header('Location: /admin/estoque');
                exit;
            }

            $this->connection->beginTransaction();

            $stmtEstoque = $this->connection->prepare('
                INSERT INTO estoque_interno (
                    produto_id,
                    quantidade,
                    data_compra,
                    data_validade,
                    is_alimenticio,
                    galpao,
                    prateleira,
                    observacao
                ) VALUES (
                    :produto_id,
                    :quantidade,
                    :data_compra,
                    :data_validade,
                    :is_alimenticio,
                    :galpao,
                    :prateleira,
                    :observacao
                )
            ');

            $stmtEstoque->execute([
                ':produto_id' => $produtoId,
                ':quantidade' => $quantidade,
                ':data_compra' => ($dataCompra !== '' ? $dataCompra : null),
                ':data_validade' => ($dataValidade !== '' ? $dataValidade : null),
                ':is_alimenticio' => $isAlimenticio,
                ':galpao' => ($galpao !== '' ? $galpao : null),
                ':prateleira' => ($prateleira !== '' ? $prateleira : null),
                ':observacao' => ($observacao !== '' ? $observacao : null),
            ]);

            // Registrar movimentação (entrada)
            $stmtMov = $this->connection->prepare('
                INSERT INTO estoque_movimentacao (
                    produto_id,
                    tipo_movimentacao,
                    quantidade,
                    quantidade_anterior,
                    quantidade_nova,
                    motivo,
                    usuario_id
                ) VALUES (
                    :produto_id,
                    :tipo_movimentacao,
                    :quantidade,
                    :quantidade_anterior,
                    :quantidade_nova,
                    :motivo,
                    :usuario_id
                )
            ');

            $stmtAtual = $this->connection->prepare('SELECT COALESCE(SUM(quantidade),0) as total FROM estoque_interno WHERE produto_id = :produto_id');
            $stmtAtual->execute([':produto_id' => $produtoId]);
            $atual = (int) ($stmtAtual->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0);
            $anterior = $atual - $quantidade;

            $usuarioId = null;
            if (session_status() !== PHP_SESSION_ACTIVE) {
                @session_start();
            }
            if (!empty($_SESSION['user_id'])) {
                $usuarioId = (int) $_SESSION['user_id'];
            } elseif (!empty($_SESSION['usuario_id'])) {
                $usuarioId = (int) $_SESSION['usuario_id'];
            }

            $motivo = 'Entrada de estoque (galpão)';
            if ($galpao !== '' || $prateleira !== '') {
                $motivo .= ' - ' . trim($galpao . ' - Prateleira ' . $prateleira);
            }

            $stmtMov->execute([
                ':produto_id' => $produtoId,
                ':tipo_movimentacao' => 'entrada',
                ':quantidade' => $quantidade,
                ':quantidade_anterior' => $anterior,
                ':quantidade_nova' => $atual,
                ':motivo' => $motivo,
                ':usuario_id' => $usuarioId,
            ]);

            $this->connection->commit();

            $this->setFlash('Entrada de estoque registrada com sucesso.', 'success');
            header('Location: /admin/estoque');
            exit;
        } catch (\Exception $e) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            error_log('Erro ao registrar entrada de estoque: ' . $e->getMessage());
            $this->setFlash('Erro ao registrar entrada de estoque: ' . $e->getMessage(), 'danger');
            header('Location: /admin/estoque/entrada');
            exit;
        }
    }

    public function marcarComprado($request) {
        echo json_encode(['success' => false, 'message' => 'Funcionalidade em desenvolvimento']);
    }
}
