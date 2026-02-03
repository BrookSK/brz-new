<?php
namespace App\Controllers;

use App\Core\Request;

class AdminProdutosNovoController extends Controller {

    private function getPdo(): \PDO {
        return new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
    }

    private function ensureDir(string $dir): void {
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
    }

    private function getProdutoUploadsDir(): string {
        $docRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
        $candidates = [
            $docRoot . '/public/uploads/produtos/',
            $docRoot . '/uploads/produtos/',
            __DIR__ . '/../../public/uploads/produtos/',
        ];

        foreach ($candidates as $c) {
            if (is_string($c) && $c !== '' && (is_dir($c) || @mkdir($c, 0777, true))) {
                return rtrim($c, '/\\') . DIRECTORY_SEPARATOR;
            }
        }

        return rtrim((string) $candidates[0], '/\\') . DIRECTORY_SEPARATOR;
    }

    private function tableExists(\PDO $pdo, string $table): bool {
        try {
            $st = $pdo->prepare('SHOW TABLES LIKE ?');
            $st->execute([$table]);
            return (bool) $st->fetchColumn();
        } catch (\Exception $e) {
            return false;
        }
    }

    private function parseMoneyToDb($value): string {
        $s = is_string($value) ? trim($value) : '';
        if ($s === '') {
            return '0';
        }
        $s = str_replace(['R$', '$', ' '], '', $s);
        $s = str_replace(['.'], '', $s);
        $s = str_replace([','], '.', $s);
        $s = preg_replace('/[^0-9\.\-]/', '', $s);
        if ($s === '' || $s === '.' || $s === '-') {
            return '0';
        }
        return $s;
    }

    private function getTableColumns(\PDO $pdo, string $table): array {
        $cols = [];
        try {
            $stmt = $pdo->query('SHOW COLUMNS FROM ' . $table);
            $rows = $stmt ? ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];
            foreach ($rows as $r) {
                if (!empty($r['Field'])) $cols[] = $r['Field'];
            }
        } catch (\Exception $e) {
        }
        return $cols;
    }

    private function getCategorias(): array {
        try {
            $pdo = $this->getPdo();
            $stmt = $pdo->query('SELECT * FROM categorias ORDER BY name ASC');
            return $stmt ? ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getLojasSafe(): array {
        try {
            $pdo = $this->getPdo();
            $st = $pdo->prepare('SHOW TABLES LIKE ?');
            $st->execute(['lojas']);
            if (!$st->fetchColumn()) {
                return [];
            }
            $stmt = $pdo->query('SELECT id, nome, slug FROM lojas ORDER BY nome ASC');
            return $stmt ? ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getVariacaoTiposComOpcoes(): array {
        $result = [];
        try {
            $pdo = $this->getPdo();
            if (!$this->tableExists($pdo, 'variacao_tipos') || !$this->tableExists($pdo, 'variacao_opcoes')) {
                return [];
            }
            $tipos = $pdo->query('SELECT * FROM variacao_tipos WHERE ativo = 1 ORDER BY nome ASC')->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $opcoes = $pdo->query('SELECT * FROM variacao_opcoes WHERE ativo = 1 ORDER BY tipo_id ASC, ordem ASC, valor ASC')->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            $mapOp = [];
            foreach ($opcoes as $o) {
                $tid = (int) ($o['tipo_id'] ?? 0);
                if ($tid <= 0) continue;
                if (!isset($mapOp[$tid])) $mapOp[$tid] = [];
                $mapOp[$tid][] = $o;
            }

            foreach ($tipos as $t) {
                $tid = (int) ($t['id'] ?? 0);
                $t['opcoes'] = $mapOp[$tid] ?? [];
                $result[] = $t;
            }
        } catch (\Exception $e) {
            return [];
        }

        return $result;
    }

    public function index(Request $request) {
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        $categorias = $this->getCategorias();
        $lojas = $this->getLojasSafe();
        $tipos = $this->getVariacaoTiposComOpcoes();

        $schemaOk = true;
        try {
            $pdo = $this->getPdo();
            $schemaOk = $this->tableExists($pdo, 'variacao_tipos')
                && $this->tableExists($pdo, 'variacao_opcoes')
                && $this->tableExists($pdo, 'produto_atributos')
                && $this->tableExists($pdo, 'produto_variacoes')
                && $this->tableExists($pdo, 'produto_variacao_itens');
        } catch (\Exception $e) {
            $schemaOk = false;
        }

        renderAdminSidebarStyles();

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novo Produto - Braziliana Shop Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
</head>
<body>
    <div class="container-fluid admin-shell">
        <div class="row">';

        renderAdminSidebar('produtos');

        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Novo Produto</h1>
                    <a href="/admin/produtos" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
                </div>';

        echo '<ul class="nav nav-tabs" id="novoProdutoTabs" role="tablist">
                <li class="nav-item" role="presentation">
                    <button class="nav-link active" id="tab-simples" data-bs-toggle="tab" data-bs-target="#pane-simples" type="button" role="tab">Simples</button>
                </li>
                <li class="nav-item" role="presentation">
                    <button class="nav-link" id="tab-variavel" data-bs-toggle="tab" data-bs-target="#pane-variavel" type="button" role="tab">Variável</button>
                </li>
              </ul>';

        echo '<div class="tab-content pt-3" id="novoProdutoTabsContent">
                <div class="tab-pane fade show active" id="pane-simples" role="tabpanel">
                    <div class="card">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div class="text-muted">Cadastro simples (original)</div>
                                <a class="btn btn-sm btn-outline-primary" href="/admin/produtos/novo-simples" target="_blank">Abrir em nova aba</a>
                            </div>
                            <div id="novoProdutoSimplesContainer">
                                <div class="text-muted">Carregando formulário...</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="tab-pane fade" id="pane-variavel" role="tabpanel">';

        if (!$schemaOk) {
            echo '<div class="alert alert-warning">Para cadastrar produto variável, rode a migration <strong>061_create_produto_variacoes_schema.sql</strong> no banco.</div>';
        }

        echo '<form method="POST" action="/admin/produtos/variavel/salvar" enctype="multipart/form-data">
                <div class="row">
                    <div class="col-md-8">
                        <div class="card mb-4">
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Nome *</label>
                                    <input type="text" class="form-control" name="name" required>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">SKU (opcional)</label>
                                    <input type="text" class="form-control" name="sku">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">NCM</label>
                                    <input type="text" class="form-control" name="ncm" placeholder="Pesquisar NCM...">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Loja</label>
                                    <select class="form-select" name="loja">
                                        <option value="">Selecione...</option>';

        if (!empty($lojas)) {
            foreach ($lojas as $l) {
                echo '<option value="' . htmlspecialchars((string) ($l['id'] ?? ''), ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars((string) ($l['nome'] ?? ''), ENT_QUOTES, 'UTF-8') . '</option>';
            }
        }

        echo '                 </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Categoria</label>
                                    <select class="form-select" name="category_id">
                                        <option value="">Selecione...</option>';

        foreach ($categorias as $cat) {
            $catName = (string) ($cat['name'] ?? ($cat['nome'] ?? ''));
            echo '<option value="' . htmlspecialchars((string) ($cat['id'] ?? ''), ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($catName, ENT_QUOTES, 'UTF-8') . '</option>';
        }

        echo '                 </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Descrição Curta</label>
                                    <textarea class="form-control" name="short_description" rows="3"></textarea>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Descrição Completa</label>
                                    <textarea class="form-control" name="description" rows="5"></textarea>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Foto de Capa</label>
                                    <input type="file" class="form-control" name="capa" accept="image/*">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Galeria de Fotos (produto pai)</label>
                                    <input type="file" class="form-control" name="imagens[]" multiple accept="image/*">
                                </div>
                            </div>
                        </div>

                        <div class="card mb-4">
                            <div class="card-header bg-white"><strong>Atributos e Variações</strong></div>
                            <div class="card-body">';

        if (empty($tipos)) {
            echo '<div class="alert alert-warning mb-0">
                    Para gerar combinações (WooCommerce), você precisa cadastrar <strong>Tipos</strong> e <strong>Opções</strong> em <a href="/admin/variacoes" target="_blank">Variações</a>.
                  </div>
                  <div class="mt-3">
                    <a class="btn btn-primary" href="/admin/variacoes" target="_blank"><i class="fas fa-sliders-h"></i> Ir para Variações</a>
                  </div>';
        } else {
            echo '<div class="alert alert-info">Selecione as opções por tipo e clique em <strong>Gerar variações</strong>. Você pode ajustar preço/estoque por variação.</div>';

            foreach ($tipos as $t) {
                $tid = (int) ($t['id'] ?? 0);
                $tnome = (string) ($t['nome'] ?? '');
                $opcs = is_array($t['opcoes'] ?? null) ? $t['opcoes'] : [];

                echo '<div class="mb-3">
                        <div class="fw-semibold">' . htmlspecialchars($tnome, ENT_QUOTES, 'UTF-8') . '</div>
                        <div class="row g-2 mt-1">';

                foreach ($opcs as $o) {
                    $oid = (int) ($o['id'] ?? 0);
                    $ovalor = (string) ($o['valor'] ?? '');
                    echo '<div class="col-6 col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" data-tipo-nome="' . htmlspecialchars($tnome, ENT_QUOTES, 'UTF-8') . '" name="opcoes[' . $tid . '][]" value="' . $oid . '" id="nv_opt_' . $tid . '_' . $oid . '">
                                <label class="form-check-label" for="nv_opt_' . $tid . '_' . $oid . '">' . htmlspecialchars($ovalor, ENT_QUOTES, 'UTF-8') . '</label>
                            </div>
                          </div>';
                }

                echo '  </div>
                      </div>';
            }

            echo '<div class="d-flex flex-wrap gap-2">
                    <button type="button" class="btn btn-outline-primary" id="btnGerarVariacoes"><i class="fas fa-cogs"></i> Gerar variações</button>
                    <button type="button" class="btn btn-outline-danger" id="btnLimparVariacoes"><i class="fas fa-trash"></i> Limpar</button>
                  </div>';

            echo '<div class="table-responsive mt-3">
                    <table class="table table-sm align-middle" id="tabelaVariacoes">
                        <thead>
                            <tr>
                                <th>Variação</th>
                                <th style="width:180px">Preço (override)</th>
                                <th style="width:140px">Estoque</th>
                                <th style="width:260px">Fotos da variação</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                  </div>';

            echo '<input type="hidden" name="variacoes_json" id="variacoes_json" value="">';
        }

        echo '          </div>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="card mb-4">
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Preço base (USD)</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="text" class="form-control" name="price" value="0">
                                    </div>
                                    <small class="text-muted">Variações podem sobrescrever este preço.</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Preço de custo (USD)</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="text" class="form-control" name="cost_price" value="0">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Preço promocional (USD)</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="text" class="form-control" name="sale_price" value="0">
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Estoque base</label>
                                    <input type="number" class="form-control" name="stock" value="0" min="0">
                                    <small class="text-muted">Quando o produto tiver variações, o estoque real passa a ser o da variação.</small>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Estoque mínimo</label>
                                    <input type="number" class="form-control" name="min_stock" value="0" min="0">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Peso (kg)</label>
                                    <input type="text" class="form-control" name="weight" value="0">
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Status</label>
                                    <select class="form-select" name="status">
                                        <option value="draft">Rascunho</option>
                                        <option value="published" selected>Publicado</option>
                                        <option value="archived">Arquivado</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Ativo</label>
                                    <select class="form-select" name="active">
                                        <option value="1" selected>Ativo</option>
                                        <option value="0">Inativo</option>
                                    </select>
                                </div>

                                <div class="mb-3">
                                    <label class="form-label">Destaque</label>
                                    <select class="form-select" name="featured">
                                        <option value="0" selected>Não</option>
                                        <option value="1">Sim</option>
                                    </select>
                                </div>

                                <button type="submit" class="btn btn-primary w-100" ' . (!$schemaOk ? 'disabled' : '') . '><i class="fas fa-save"></i> Salvar Produto Variável</button>
                            </div>
                        </div>
                    </div>
                </div>
              </form>';

        echo <<<'HTML'
      </div>
            </div>
            </main>
        </div>
    </div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function() {
    const simplesContainer = document.getElementById('novoProdutoSimplesContainer');
    let simplesLoaded = false;

    async function loadSimplesOnce() {
        if (!simplesContainer || simplesLoaded) return;
        simplesLoaded = true;

        try {
            const res = await fetch('/admin/produtos/novo-simples', { credentials: 'same-origin' });
            const html = await res.text();
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const form = doc.querySelector('main form');
            if (!form) {
                simplesContainer.innerHTML = '<div class="text-danger">Não foi possível carregar o formulário simples.</div>';
                return;
            }

            // Renderizar apenas o formulário para evitar duplicar cabeçalho/layout do admin
            simplesContainer.innerHTML = form.outerHTML;
        } catch (e) {
            simplesContainer.innerHTML = '<div class="text-danger">Erro ao carregar o formulário simples.</div>';
        }
    }

    // Carrega ao abrir a página (aba Simples é a default)
    loadSimplesOnce();

    const tabSimples = document.getElementById('tab-simples');
    if (tabSimples) {
        tabSimples.addEventListener('shown.bs.tab', loadSimplesOnce);
    }

    const btnGerar = document.getElementById('btnGerarVariacoes');
    const btnLimpar = document.getElementById('btnLimparVariacoes');
    const tableBody = document.querySelector('#tabelaVariacoes tbody');
    const hidden = document.getElementById('variacoes_json');

    if (!btnGerar || !btnLimpar || !tableBody || !hidden) return;

    function getSelectedOptions() {
        const selected = {};
        document.querySelectorAll('input[name^="opcoes["]:checked').forEach((el) => {
            const name = el.getAttribute('name') || '';
            const m = name.match(/^opcoes\[(\d+)\]\[\]\s*$/);
            if (!m) return;
            const tid = m[1];
            if (!selected[tid]) selected[tid] = [];
            selected[tid].push({
                tipo_id: Number(tid),
                opcao_id: Number(el.value),
                tipo_label: (el.getAttribute('data-tipo-nome') || '').trim(),
                label: (el.nextElementSibling ? el.nextElementSibling.textContent : '').trim(),
            });
        });
        return selected;
    }

    function cartesian(types) {
        let combos = [{}];
        types.forEach((t) => {
            const next = [];
            combos.forEach((c) => {
                t.options.forEach((o) => {
                    const nn = Object.assign({}, c);
                    nn[t.tipo_id] = o;
                    next.push(nn);
                });
            });
            combos = next;
        });
        return combos;
    }

    function renderRows(combos) {
        tableBody.innerHTML = '';
        const variacoes = [];
        combos.forEach((comb, idx) => {
            const parts = [];
            const itens = [];
            Object.keys(comb).sort((a,b) => Number(a)-Number(b)).forEach((tid) => {
                const o = comb[tid];
                parts.push(o.tipo_label ? (o.tipo_label + '=' + o.label) : o.label);
                itens.push({ tipo_id: Number(tid), opcao_id: Number(o.opcao_id) });
            });

            const desc = parts.join(' / ');
            variacoes.push({ descricao: desc, itens: itens, stock: 0, price_override: '' });

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><div class="fw-semibold">${desc || ('Variação #' + (idx+1))}</div></td>
                <td><input type="text" class="form-control form-control-sm" data-idx="${idx}" data-field="price_override" placeholder="ex: 99.90"></td>
                <td><input type="number" class="form-control form-control-sm" data-idx="${idx}" data-field="stock" value="0" min="0"></td>
                <td><input type="file" class="form-control form-control-sm" name="variacao_fotos[${idx}][]" multiple accept="image/*"></td>
            `;
            tableBody.appendChild(tr);
        });

        hidden.value = JSON.stringify(variacoes);

        tableBody.querySelectorAll('input[data-idx]').forEach((inp) => {
            inp.addEventListener('input', function() {
                try {
                    const arr = JSON.parse(hidden.value || '[]');
                    const i = Number(inp.getAttribute('data-idx'));
                    const field = inp.getAttribute('data-field');
                    if (!arr[i]) return;
                    arr[i][field] = inp.value;
                    hidden.value = JSON.stringify(arr);
                } catch (e) {}
            });
        });
    }

    btnGerar.addEventListener('click', function() {
        const sel = getSelectedOptions();
        const typeIds = Object.keys(sel);
        if (typeIds.length === 0) {
            alert('Selecione opções para gerar variações.');
            return;
        }

        const types = typeIds.map((tid) => {
            return {
                tipo_id: Number(tid),
                options: sel[tid].map((x) => ({ tipo_id: x.tipo_id, opcao_id: x.opcao_id, label: x.label, tipo_label: x.tipo_label || '' })),
            };
        });

        const combos = cartesian(types);
        renderRows(combos);
    });

    btnLimpar.addEventListener('click', function() {
        tableBody.innerHTML = '';
        hidden.value = '';
        document.querySelectorAll('input[name^="opcoes["]:checked').forEach((el) => { el.checked = false; });
    });
})();
</script>
</body>
</html>
HTML;

        exit;
    }

    public function salvarVariavel(Request $request) {
        try {
            $pdo = $this->getPdo();

            $schemaOk = $this->tableExists($pdo, 'variacao_tipos')
                && $this->tableExists($pdo, 'variacao_opcoes')
                && $this->tableExists($pdo, 'produto_atributos')
                && $this->tableExists($pdo, 'produto_variacoes')
                && $this->tableExists($pdo, 'produto_variacao_itens');

            if (!$schemaOk) {
                $_SESSION['message'] = 'Tabelas de variações não encontradas. Rode a migration 061.';
                $_SESSION['message_type'] = 'warning';
                header('Location: /admin/produtos/novo');
                exit;
            }

            $cols = $this->getTableColumns($pdo, 'produtos');

            $name = trim((string) $request->getParam('name', ''));
            if ($name === '') {
                $_SESSION['message'] = 'Informe o nome do produto.';
                $_SESSION['message_type'] = 'danger';
                header('Location: /admin/produtos/novo');
                exit;
            }

            $skuInput = trim((string) $request->getParam('sku', ''));
            if (in_array('sku', $cols, true) && $skuInput === '') {
                $_SESSION['message'] = 'Informe o SKU do produto.';
                $_SESSION['message_type'] = 'danger';
                header('Location: /admin/produtos/novo');
                exit;
            }

            $pdo->beginTransaction();

            $price = $this->parseMoneyToDb($request->getParam('price'));
            $costPrice = $this->parseMoneyToDb($request->getParam('cost_price'));
            $salePrice = $this->parseMoneyToDb($request->getParam('sale_price'));
            $stock = (int) $request->getParam('stock', 0);
            $minStock = (int) $request->getParam('min_stock', 0);
            $weight = $this->parseMoneyToDb($request->getParam('weight'));

            $data = [];
            if (in_array('name', $cols, true)) $data['name'] = $name;
            if (in_array('nome', $cols, true)) $data['nome'] = $name;
            if (in_array('sku', $cols, true)) $data['sku'] = $skuInput;
            $lojaParam = $request->getParam('loja');
            $lojaId = is_numeric($lojaParam) ? (int) $lojaParam : 0;
            if (in_array('loja_id', $cols, true) && $lojaId > 0) {
                $data['loja_id'] = $lojaId;
            }
            if (in_array('loja', $cols, true)) {
                $data['loja'] = $lojaParam;
            }
            if (in_array('ncm', $cols, true)) $data['ncm'] = (string) $request->getParam('ncm', '');
            if (in_array('description', $cols, true)) $data['description'] = (string) $request->getParam('description', '');
            if (in_array('short_description', $cols, true)) $data['short_description'] = (string) $request->getParam('short_description', '');
            if (in_array('descricao', $cols, true)) $data['descricao'] = (string) $request->getParam('description', '');
            if (in_array('descricao_curta', $cols, true)) $data['descricao_curta'] = (string) $request->getParam('short_description', '');

            $cat = $request->getParam('category_id');
            if (in_array('category_id', $cols, true)) $data['category_id'] = ($cat !== '' ? $cat : null);
            if (in_array('categoria_id', $cols, true)) $data['categoria_id'] = ($cat !== '' ? $cat : null);

            if (in_array('price', $cols, true)) $data['price'] = $price;
            if (in_array('preco', $cols, true)) $data['preco'] = $price;
            if (in_array('valor', $cols, true)) $data['valor'] = $price;
            if (in_array('cost_price', $cols, true) && $costPrice !== '') $data['cost_price'] = $costPrice;
            if (in_array('sale_price', $cols, true) && $salePrice !== '') $data['sale_price'] = $salePrice;
            if (in_array('stock', $cols, true)) $data['stock'] = $stock;
            if (in_array('min_stock', $cols, true)) $data['min_stock'] = $minStock;
            if (in_array('weight', $cols, true)) $data['weight'] = $weight;
            if (in_array('estoque', $cols, true)) $data['estoque'] = $stock;
            if (in_array('estoque_minimo', $cols, true)) $data['estoque_minimo'] = $minStock;
            if (in_array('peso', $cols, true)) $data['peso'] = $weight;
            if (in_array('moeda', $cols, true)) {
                $data['moeda'] = 'USD';
            }
            if (in_array('status', $cols, true)) $data['status'] = (string) $request->getParam('status', 'published');
            if (in_array('active', $cols, true)) $data['active'] = (int) $request->getParam('active', 1);
            if (in_array('featured', $cols, true)) $data['featured'] = (int) $request->getParam('featured', 0);
            if (in_array('ativo', $cols, true)) $data['ativo'] = (int) $request->getParam('active', 1);
            if (in_array('created_at', $cols, true)) $data['created_at'] = date('Y-m-d H:i:s');
            if (in_array('updated_at', $cols, true)) $data['updated_at'] = date('Y-m-d H:i:s');

            if (empty($data)) {
                throw new \Exception('Não foi possível salvar: nenhuma coluna compatível foi encontrada na tabela produtos.');
            }

            $columnsSql = implode(', ', array_keys($data));
            $placeholders = ':' . implode(', :', array_keys($data));
            $stmt = $pdo->prepare('INSERT INTO produtos (' . $columnsSql . ') VALUES (' . $placeholders . ')');
            foreach ($data as $k => $v) {
                $stmt->bindValue(':' . $k, $v);
            }
            $stmt->execute();

            $produtoId = (int) $pdo->lastInsertId();

            if (isset($_FILES['capa']) && !empty($_FILES['capa']['name']) && ($_FILES['capa']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $uploadDir = $this->getProdutoUploadsDir();
                $webDir = '/uploads/produtos/';
                $this->ensureDir($uploadDir);

                $nameFile = $_FILES['capa']['name'];
                $fileName = preg_replace('/[^A-Za-z0-9\-_\.]/', '', (string) $nameFile);
                $fileName = time() . '_' . $fileName;
                $filePath = $uploadDir . $fileName;
                $webPath = $webDir . $fileName;

                if (move_uploaded_file($_FILES['capa']['tmp_name'], $filePath)) {
                    if (in_array('foto_principal', $cols, true)) {
                        $stmtCover = $pdo->prepare('UPDATE produtos SET foto_principal = ? WHERE id = ?');
                        $stmtCover->execute([$webPath, $produtoId]);
                    }
                }
            }

            if (isset($_FILES['imagens']) && !empty($_FILES['imagens']['name'][0])) {
                $uploadDir = $this->getProdutoUploadsDir();
                $webDir = '/uploads/produtos/';
                $this->ensureDir($uploadDir);

                foreach ($_FILES['imagens']['name'] as $key => $nameUp) {
                    if (($_FILES['imagens']['error'][$key] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                        continue;
                    }
                    $fileName = preg_replace('/[^A-Za-z0-9\-_\.]/', '', (string) $nameUp);
                    $fileName = time() . '_' . $fileName;

                    $filePath = $uploadDir . $fileName;
                    $webPath = $webDir . $fileName;

                    if (move_uploaded_file($_FILES['imagens']['tmp_name'][$key], $filePath)) {
                        $stFoto = $pdo->prepare('INSERT INTO produto_fotos (produto_id, nome_arquivo, arquivo_original, principal, ordem, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())');
                        $stFoto->execute([
                            $produtoId,
                            $webPath,
                            $nameUp,
                            0,
                            (int) $key,
                        ]);
                    }
                }
            }

            $opcoes = $request->getParam('opcoes', []);
            if (!is_array($opcoes)) $opcoes = [];

            $tipoIds = [];
            foreach ($opcoes as $tid => $list) {
                $tid = (int) $tid;
                if ($tid <= 0) continue;
                if (!is_array($list) || empty($list)) continue;
                $tipoIds[$tid] = true;
            }

            if (!empty($tipoIds)) {
                $stmtInsAttr = $pdo->prepare('INSERT INTO produto_atributos (produto_id, tipo_id, created_at, updated_at) VALUES (:pid, :tid, NOW(), NOW())');
                foreach (array_keys($tipoIds) as $tid) {
                    $stmtInsAttr->execute([':pid' => $produtoId, ':tid' => (int) $tid]);
                }
            }

            $variacoesJson = (string) $request->getParam('variacoes_json', '');
            $variacoes = [];
            if ($variacoesJson !== '') {
                $tmp = json_decode($variacoesJson, true);
                if (is_array($tmp)) $variacoes = $tmp;
            }

            if (!empty($variacoes)) {
                $stmtInsVar = $pdo->prepare('INSERT INTO produto_variacoes (produto_id, sku, price_override, stock, ativo, created_at, updated_at) VALUES (:pid, NULL, :po, :st, 1, NOW(), NOW())');
                $stmtInsItem = $pdo->prepare('INSERT INTO produto_variacao_itens (produto_variacao_id, tipo_id, opcao_id, created_at, updated_at) VALUES (:pvi, :tid, :oid, NOW(), NOW())');

                $stmtInsVarFoto = null;
                if ($this->tableExists($pdo, 'produto_variacao_fotos')) {
                    $stmtInsVarFoto = $pdo->prepare('INSERT INTO produto_variacao_fotos (produto_variacao_id, nome_arquivo, arquivo_original, legenda, ordem, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())');
                }

                foreach ($variacoes as $idx => $v) {
                    $st = (int) ($v['stock'] ?? 0);
                    $poRaw = trim((string) ($v['price_override'] ?? ''));
                    $po = ($poRaw !== '') ? $this->parseMoneyToDb($poRaw) : null;

                    $stmtInsVar->bindValue(':pid', $produtoId, \PDO::PARAM_INT);
                    if ($po === null || $po === '') {
                        $stmtInsVar->bindValue(':po', null, \PDO::PARAM_NULL);
                    } else {
                        $stmtInsVar->bindValue(':po', $po);
                    }
                    $stmtInsVar->bindValue(':st', $st, \PDO::PARAM_INT);
                    $stmtInsVar->execute();

                    $varId = (int) $pdo->lastInsertId();

                    $itens = $v['itens'] ?? [];
                    if (is_array($itens)) {
                        foreach ($itens as $it) {
                            $tid = (int) ($it['tipo_id'] ?? 0);
                            $oid = (int) ($it['opcao_id'] ?? 0);
                            if ($tid <= 0 || $oid <= 0) continue;
                            $stmtInsItem->execute([':pvi' => $varId, ':tid' => $tid, ':oid' => $oid]);
                        }
                    }

                    if ($stmtInsVarFoto && isset($_FILES['variacao_fotos']) && isset($_FILES['variacao_fotos']['name'][$idx])) {
                        $names = $_FILES['variacao_fotos']['name'][$idx] ?? [];
                        $tmps = $_FILES['variacao_fotos']['tmp_name'][$idx] ?? [];
                        $errs = $_FILES['variacao_fotos']['error'][$idx] ?? [];

                        if (is_array($names)) {
                            $uploadDir = $this->getProdutoUploadsDir();
                            $webDir = '/uploads/produtos/';
                            $this->ensureDir($uploadDir);

                            $ord = 0;
                            foreach ($names as $k => $nm) {
                                if (($errs[$k] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
                                $clean = preg_replace('/[^A-Za-z0-9\-_\.]/', '', (string) $nm);
                                $fileName = time() . '_' . $varId . '_' . $clean;
                                $filePath = $uploadDir . $fileName;
                                $webPath = $webDir . $fileName;
                                if (move_uploaded_file($tmps[$k] ?? '', $filePath)) {
                                    $stmtInsVarFoto->execute([$varId, $webPath, $nm, null, $ord]);
                                    $ord++;
                                }
                            }
                        }
                    }
                }
            }

            $pdo->commit();
            $_SESSION['message'] = 'Produto variável criado com sucesso.';
            $_SESSION['message_type'] = 'success';

            header('Location: /admin/produtos/editar/' . $produtoId);
            exit;

        } catch (\Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $_SESSION['message'] = 'Erro ao criar produto variável: ' . $e->getMessage();
            $_SESSION['message_type'] = 'danger';
            header('Location: /admin/produtos/novo');
            exit;
        }
    }
}
