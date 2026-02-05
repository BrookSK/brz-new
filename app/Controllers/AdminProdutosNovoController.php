<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;

class AdminProdutosNovoController extends Controller {

    private function getNcmOptions(): array {
        return [
            '09019000' => 'Cafés',
            '09024000' => 'Chá Preto',
            '17041000' => 'Chicletes',
            '17049020' => 'Balas, Confeitos, Pastilhas, Pirulitos',
            '18063110' => 'Chocolates',
            '19012090' => 'Massas para Bolos, Panquecas, Paes',
            '19041000' => 'Pipocas e Cereais',
            '19049000' => 'Salgadinhos e Snacks',
            '19059090' => 'Bolachas e Biscoitos',
            '21011110' => 'Cafés Soluveis',
            '21039021' => 'Temperos e Preparações',
            '21039099' => 'Molhos (geral)',
            '21069030' => 'Suplementos',
            '23091000' => 'Petisco para caes e gatos',
            '30051090' => 'Curativos',
            '33030010' => 'Perfumes',
            '33041000' => 'Maquiagem para os Labios',
            '33042090' => 'Maquiagem para os Olhos',
            '33043000' => 'Manicure e Pedicure',
            '33049910' => 'Cremes de Beleza, Tonicos',
            '33049990' => 'Protetor Solar e Bronzeadores',
            '33051000' => 'Shampoos',
            '33059000' => 'Produtos p Cabelo (geral)',
            '33061000' => 'Creme Dental',
            '33062000' => 'Fio Dental',
            '33069000' => 'Enxaguante Bucal (outros)',
            '33071000' => 'Creme de Barbear',
            '33072090' => 'Desodorantes',
            '33073000' => 'Sais de Banho e outras Preparações',
            '33074900' => 'Cheirinho de Carro e Casa',
            '34011190' => 'Lenços Umedecidos',
            '34013000' => 'Sabonete Liquido, Detergente',
            '34024190' => 'Produtos de Limpeza',
            '34060000' => 'Velas e Pavis',
            '38089199' => 'Repelentes para Corpo',
            '38099190' => 'Lenços Para Secadora',
            '38229000' => 'NIMA, Fita Teste e outros medidores',
            '39204390' => 'Plastico Filme e Semelhantes',
            '39232190' => 'Sacos de Lixo/ Sacos Ziplock',
            '39241000' => 'Utensilios de Cozinha, Banheiro e Geral (PLÁSTICO)',
            '39264000' => 'Decorações e Estatuetas',
            '40149090' => 'Chupetas',
            '42029900' => 'Bolsas (geral)',
            '42010090' => 'Coleiras de Cachorro',
            '48182000' => 'Lenços, lenços (toalhitas) demaquilantes e toalhas de mão',
            '48201000' => 'Post It, Papel para Cartas e Agendas',
            '48202000' => 'Cadernos',
            '48236900' => 'Forro de AirFryer (papel)',
            '49019900' => 'Livros',
            '48025610' => 'Folha Sulfite',
            '62099090' => 'Roupas Bebe (geral)',
            '62121000' => 'Sutias e Topes',
            '63022900' => 'Roupas de Cama',
            '63026000' => 'Toalhas de Banho',
            '63071000' => 'Pano de Chão, Pano Prato, Esponja de Louça',
            '63079090' => 'Brinquedo Pelucia Pet',
            '63080000' => 'Capas de Tecido, Tapetes, Toalhas de Mesa, Guardanapos, Cestos',
            '63090090' => 'Roupas (geral)',
            '64059000' => 'Sapatos em Geral',
            '67049000' => 'Cílios Postiços, Perucas',
            '69119000' => 'Utensilios de Cozinha, Banheiro e Geral (PORCELANA)',
            '70109090' => 'Recipientes de Vidro',
            '70134210' => 'Cafeteiras e Chaleiras (VIDRO)',
            '70134900' => 'Utensilios de Cozinha, Banheiro e Geral  (VIDRO)',
            '71179000' => 'Bijuterias',
            '73102190' => 'Latas (Lavanderia)',
            '76151000' => 'Utensilios de Cozinha (Panelas), Banheiro e Geral (ALUMINIO)',
            '82059000' => 'Ferramentas Manuais',
            '82119290' => 'Facas de Cozinha',
            '82130000' => 'Tesouras',
            '82159990' => 'Talheres (Aço)',
            '84141000' => 'Bombas de Leite Materno',
            '84145990' => 'Ventiladores',
            '84148019' => 'Compressores de Ar',
            '84433240' => 'Impressoras a folhas',
            '84672100' => 'Furadeiras',
            '84716052' => 'Teclados',
            '84716053' => 'Mouses e Canetas Digitais',
            '85044010' => 'Carregadores (geral)',
            '85068090' => 'Pilhas e Baterias',
            '85086000' => 'Aspiradores',
            '85094010' => 'Liquidificadores',
            '85094020' => 'Batedeiras',
            '85094040' => 'Extratores de Suco e Polpas',
            '85094050' => 'Processadores de Alimentos',
            '85098090' => 'Esfregões e Escovas Elétricas de Limpeza',
            '85101000' => 'Aparelhos de Barbear',
            '85102000' => 'Máquinas de Cortar Cabelo',
            '85103000' => 'Aparelhos de Depilar',
            '85163100' => 'Secadores de Cabelo',
            '85163200' => 'Outros Aparelhos para Arranjo de Cabelo',
            '85164000' => 'Ferros de Passar Roupas',
            '85166000' => 'Fornos, Grelhas e Assadeiras',
            '85167100' => 'Aparelhos para fazer chás e cafés',
            '85167990' => 'Dash, Panela Ninja',
            '85171300' => 'Celulares',
            '85183000' => 'Fones de Ouvido',
            '85235190' => 'Cartão de Memória',
            '85258929' => 'Cameras e Baba Eletronicas',
            '85269200' => 'Controles Remotos',
            '85393120' => 'Lampadas',
        ];
    }

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
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte', 'representante']);
        $categorias = $this->getCategorias();
        $lojas = $this->getLojasSafe();
        $tipos = $this->getVariacaoTiposComOpcoes();
        $ncmOptions = $this->getNcmOptions();

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

        ob_start();

        echo '<div class="pt-3">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center mb-4 border-bottom" style="padding-bottom: 12px;">
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
                                    <input type="text" class="form-control" id="ncmSearchVar" placeholder="Pesquisar NCM...">
                                    <select class="form-select mt-2" name="ncm" id="ncmSelectVar">
                                        <option value="">Selecione...</option>';

        foreach ($ncmOptions as $code => $label) {
            echo '<option value="' . htmlspecialchars((string) $code, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars((string) $code . ' - ' . $label, ENT_QUOTES, 'UTF-8') . '</option>';
        }

        echo '                     </select>
                                    <small class="text-muted">Opcional</small>
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

    // Busca no select de NCM (aba Variável)
    (function() {
        const input = document.getElementById('ncmSearchVar');
        const select = document.getElementById('ncmSelectVar');
        if (!input || !select) return;

        input.addEventListener('input', function() {
            const q = (input.value || '').toLowerCase().trim();
            Array.from(select.options).forEach((opt) => {
                if (opt.value === '') return;
                const text = (opt.text || '').toLowerCase();
                opt.hidden = q !== '' && !text.includes(q);
            });
        });
    })();

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
HTML;

        echo '</div>';

        $content = ob_get_clean();
        $title = 'Novo Produto - Braziliana Shop Admin';
        include __DIR__ . '/../Views/layouts/admin.php';
        exit;
    }

    public function salvarVariavel(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte', 'representante']);
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

            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $perfilSessao = strtolower(trim((string) ($_SESSION['usuario_perfil'] ?? '')));
            $repId = (int) ($_SESSION['usuario_id'] ?? 0);
            $repEmail = (string) ($_SESSION['usuario_email'] ?? '');

            $price = $this->parseMoneyToDb($request->getParam('price'));
            $costPrice = $this->parseMoneyToDb($request->getParam('cost_price'));
            $salePrice = $this->parseMoneyToDb($request->getParam('sale_price'));
            $stock = (int) $request->getParam('stock', 0);
            $minStock = (int) $request->getParam('min_stock', 0);
            $weight = $this->parseMoneyToDb($request->getParam('weight'));

            $name = trim((string) $request->getParam('name', ''));
            if ($name === '') {
                $_SESSION['message'] = 'Informe o nome do produto.';
                $_SESSION['message_type'] = 'danger';
                header('Location: /admin/produtos/novo');
                exit;
            }

            if ($perfilSessao === 'representante') {
                if (trim((string) $costPrice) === '') {
                    $_SESSION['message'] = 'Preço de custo (USD) é obrigatório para representante.';
                    $_SESSION['message_type'] = 'danger';
                    header('Location: /admin/produtos/novo');
                    exit;
                }
            }

            $skuInput = trim((string) $request->getParam('sku', ''));
            if (in_array('sku', $cols, true) && $skuInput === '') {
                $_SESSION['message'] = 'Informe o SKU do produto.';
                $_SESSION['message_type'] = 'danger';
                header('Location: /admin/produtos/novo');
                exit;
            }

            $pdo->beginTransaction();

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
                $lojaValue = $lojaParam;
                if ($lojaId > 0) {
                    try {
                        $st = $pdo->prepare('SHOW TABLES LIKE ?');
                        $st->execute(['lojas']);
                        if ($st->fetchColumn()) {
                            $stmtL = $pdo->prepare('SELECT slug FROM lojas WHERE id = :id LIMIT 1');
                            $stmtL->execute([':id' => $lojaId]);
                            $tmpSlug = $stmtL->fetchColumn();
                            if ($tmpSlug !== false && (string) $tmpSlug !== '') {
                                $lojaValue = (string) $tmpSlug;
                            }
                        }
                    } catch (\Exception $e) {
                    }
                }
                $data['loja'] = $lojaValue;
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
            if (in_array('currency', $cols, true)) {
                $data['currency'] = 'USD';
            }

            if ($perfilSessao === 'representante') {
                if (in_array('representante_id', $cols, true)) {
                    $data['representante_id'] = ($repId > 0 ? $repId : null);
                }
                if (in_array('representante_email', $cols, true)) {
                    $data['representante_email'] = ($repEmail !== '' ? $repEmail : null);
                }
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
                    $hasLegenda = false;
                    try {
                        $stCol = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'produto_variacao_fotos' AND column_name = 'legenda'");
                        $stCol->execute();
                        $hasLegenda = ((int) $stCol->fetchColumn()) > 0;
                    } catch (\Throwable $e) {
                        $hasLegenda = false;
                    }

                    if ($hasLegenda) {
                        $stmtInsVarFoto = $pdo->prepare('INSERT INTO produto_variacao_fotos (produto_variacao_id, nome_arquivo, arquivo_original, legenda, ordem, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())');
                    } else {
                        $stmtInsVarFoto = $pdo->prepare('INSERT INTO produto_variacao_fotos (produto_variacao_id, nome_arquivo, arquivo_original, ordem, created_at, updated_at) VALUES (?, ?, ?, ?, NOW(), NOW())');
                    }
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
                                    if (stripos((string) $stmtInsVarFoto->queryString, 'legenda') !== false) {
                                        $stmtInsVarFoto->execute([$varId, $webPath, $nm, null, $ord]);
                                    } else {
                                        $stmtInsVarFoto->execute([$varId, $webPath, $nm, $ord]);
                                    }
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
