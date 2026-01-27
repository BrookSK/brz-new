<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Url;

class AdminProdutosController extends Controller {

    private function fetchLojasSafe(): array {
        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            $stmt = $pdo->query("SHOW TABLES LIKE 'lojas'");
            $exists = $stmt->fetchColumn();
            if (!$exists) {
                return [];
            }

            $stmtLojas = $pdo->query("SELECT id, nome, slug, ativo FROM lojas ORDER BY nome ASC");
            $rows = $stmtLojas->fetchAll(\PDO::FETCH_ASSOC);
            return $rows ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getProdutoUploadsDir(): string {
        $docRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
        $candidates = [
            $docRoot . '/public/uploads/produtos/',
            $docRoot . '/uploads/produtos/',
        ];

        foreach ($candidates as $dir) {
            if (is_dir($dir)) {
                return rtrim($dir, '/\\') . '/';
            }
        }

        // Prefer the path that matches .htaccess rewrite (/uploads -> public/uploads)
        return rtrim($candidates[0], '/\\') . '/';
    }

    private function ensureDir(string $dir): void {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    private function resolveUploadsPublicPath(string $urlPath): ?string {
        $normalized = '/' . ltrim($urlPath, '/');

        $docRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
        $candidates = [
            $docRoot . '/public' . $normalized,
            $docRoot . $normalized,
        ];

        foreach ($candidates as $path) {
            $path = str_replace('\\', '/', $path);
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    private function isAjaxRequest(): bool {
        return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
    }

    private function getTableColumns(\PDO $pdo, string $table): array {
        $cols = [];
        try {
            $stmt = $pdo->query('SHOW COLUMNS FROM ' . $table);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                if (!empty($r['Field'])) {
                    $cols[] = $r['Field'];
                }
            }
        } catch (\Exception $e) {
        }

        return $cols;
    }

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
            '85444200' => 'Fios, Cabos e Outros Condutores',
            '87150000' => 'Carrinhos de Bebê e Suas Partes',
            '90191000' => 'Massageadores',
            '90251990' => 'Termometro Digital',
            '94012000' => 'Cadeirinhas e Assentos para Carros',
            '94017100' => 'Cadeiras de Alimentação',
            '94049000' => 'Travesseiros, Almofadas, Puffes e Edredões',
            '94052900' => 'Luminária e Abajures Elétricos',
            '94055000' => 'Luminarias não eletricas',
            '95030022' => 'Bonecos',
            '95030099' => 'Brinquedos em Geral',
            '95045000' => 'Video Games',
            '95069100' => 'Produtos de Ginastica e Esportes',
            '96032100' => 'Escova de Dentes',
            '96033000' => 'Pincéis de Maquiagem/ Pincéis Artista',
            '96081000' => 'Canetas Esferograficas',
            '96082000' => 'Canetas e Marcadores',
            '96084000' => 'Lapiseiras',
            '96159000' => 'Pentes, Presilhas, Grampos',
            '96162000' => 'Esponja de Maquiagem',
            '96170010' => 'Garrafas e Recipientes Termicos',
            '96190000' => 'Absorventes, Tampões e Fraldas',
            '96200000' => 'Monopés, bipés, tripés e artigos semelhantes.',
        ];
    }

    public function index(Request $request) {
        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            $pagina = (int) $request->getParam('pagina', 1);
            $limite = 12;
            $offset = ($pagina - 1) * $limite;
            $busca = $request->getParam('busca', '');

            $sql = "SELECT p.*, c.name as categoria FROM produtos p LEFT JOIN categorias c ON p.category_id = c.id WHERE 1=1";
            $params = [];

            if (!empty($busca)) {
                $sql .= " AND (p.name LIKE :busca OR p.sku LIKE :busca)";
                $params[':busca'] = "%{$busca}%";
            }

            $sql .= " ORDER BY p.created_at DESC LIMIT :limite OFFSET :offset";

            $stmt = $pdo->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue(':limite', $limite, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
            $stmt->execute();
            $produtos = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Buscar imagens (priorizar capa do produto)
            foreach ($produtos as &$produto) {
                $produto['imagem'] = Url::absolute('/uploads/produtos/placeholder.jpg');

                $fotoCapa = $produto['foto_principal'] ?? null;
                if (!empty($fotoCapa)) {
                    $filePath = $this->resolveUploadsPublicPath((string) $fotoCapa);
                    if ($filePath) {
                        $produto['imagem'] = Url::absolute((string) $fotoCapa);
                        continue;
                    }
                }

                // fallback: primeira foto da galeria (se existir)
                $stmtFotos = $pdo->prepare("SELECT nome_arquivo FROM produto_fotos WHERE produto_id = :produto_id ORDER BY ordem ASC, id ASC LIMIT 1");
                $stmtFotos->bindParam(':produto_id', $produto['id']);
                $stmtFotos->execute();
                $foto = $stmtFotos->fetch(\PDO::FETCH_ASSOC);

                if ($foto && !empty($foto['nome_arquivo'])) {
                    $filePath = $this->resolveUploadsPublicPath($foto['nome_arquivo']);
                    if ($filePath) {
                        $produto['imagem'] = Url::absolute($foto['nome_arquivo']);
                    }
                }
            }

            $stmtTotal = $pdo->prepare("SELECT COUNT(*) as total FROM produtos WHERE 1=1" . (!empty($busca) ? " AND (name LIKE :busca OR sku LIKE :busca)" : ""));
            if (!empty($busca)) {
                $stmtTotal->bindValue(':busca', "%{$busca}%");
            }
            $stmtTotal->execute();
            $total = (int) ($stmtTotal->fetch(\PDO::FETCH_ASSOC)['total'] ?? 0);
            $totalPaginas = (int) ceil($total / $limite);
        } catch (\Exception $e) {
            $produtos = [];
            $total = 0;
            $totalPaginas = 0;
            $pagina = 1;
            $busca = '';
        }

        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produtos - Braziliana Shop Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';

        renderAdminSidebarStyles();

        echo '<style>
        .product-card { transition: transform 0.2s; }
        .product-card:hover { transform: translateY(-5px); }
        .product-image { height: 200px; object-fit: cover; }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">';

        renderAdminSidebar('produtos');

        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Produtos (' . $total . ')</h1>
                    <a href="/admin/produtos/novo" class="btn btn-primary"><i class="fas fa-plus"></i> Novo</a>
                </div>';

        echo '<form method="GET" class="row g-3 mb-4">
                <div class="col-md-8">
                    <input type="text" class="form-control" name="busca" placeholder="Buscar produto..." value="' . htmlspecialchars($busca) . '">
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-outline-primary"><i class="fas fa-search"></i> Buscar</button>
                </div>
            </form>';

        echo '<div class="row">';
        foreach ($produtos as $produto) {
            echo '<div class="col-md-6 col-lg-4 mb-4">
                    <div class="card product-card h-100">
                        <img src="' . $produto['imagem'] . '" class="card-img-top product-image" alt="' . htmlspecialchars($produto['name']) . '">
                        <div class="card-body">
                            <h5 class="card-title">' . htmlspecialchars($produto['name']) . '</h5>
                            <p class="text-muted small">SKU: ' . htmlspecialchars($produto['sku']) . '</p>
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span class="fw-bold text-primary">$' . number_format($produto['price'], 2, '.', ',') . '</span>
                                <span class="badge ' . ($produto['active'] ? 'bg-success' : 'bg-danger') . '">' . ($produto['active'] ? 'Ativo' : 'Inativo') . '</span>
                            </div>
                            <div class="d-flex justify-content-between">
                                <a href="/admin/produtos/editar/' . $produto['id'] . '" class="btn btn-sm btn-outline-warning"><i class="fas fa-edit"></i></a>
                                <form method="POST" action="/admin/produtos/excluir/' . $produto['id'] . '" style="display: inline;">
                                    <button type="submit" onclick="return confirm(\'Tem certeza?\')" class="btn btn-sm btn-outline-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>';
        }
        echo '</div>';

        if ($totalPaginas > 1) {
            echo '<nav class="mt-4"><ul class="pagination justify-content-center">';
            for ($i = 1; $i <= $totalPaginas; $i++) {
                $url = "/admin/produtos?pagina={$i}" . (!empty($busca) ? "&busca=" . urlencode($busca) : "");
                echo '<li class="page-item ' . ($i == $pagina ? 'active' : '') . '">
                        <a class="page-link" href="' . $url . '">' . $i . '</a>
                      </li>';
            }
            echo '</ul></nav>';
        }

        echo '</main></div></div>';

        renderAdminScripts();

        echo '</body></html>';
        exit;
    }
    
    public function novo(Request $request) {
        // Buscar categorias
        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            $stmtCats = $pdo->query("SELECT * FROM categorias ORDER BY name ASC");
            $categorias = $stmtCats->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            $categorias = [];
        }

        $lojas = $this->fetchLojasSafe();
        $ncmOptions = $this->getNcmOptions();

        // Incluir o partial do menu lateral
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Novo Produto - Braziliana Shop Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';

        // Renderizar estilos do menu
        renderAdminSidebarStyles();

        echo '</head>
<body>
    <div class="container-fluid">
        <div class="row">';

        // Renderizar menu lateral usando o partial
        renderAdminSidebar('produtos');

        echo <<<HTML
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Novo Produto</h1>
                    <a href="/admin/produtos" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
                </div>
                
                <form method="POST" action="/admin/produtos/salvar" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="card mb-4">
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Nome *</label>
                                        <input type="text" class="form-control" name="name" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">SKU *</label>
                                        <input type="text" class="form-control" name="sku" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Loja *</label>
                                        <select class="form-select" name="loja" required>
                                            <option value="">Selecione...</option>
HTML;

        if (!empty($lojas)) {
            foreach ($lojas as $l) {
                echo '<option value="' . htmlspecialchars($l['slug']) . '">' . htmlspecialchars($l['nome']) . '</option>';
            }
        } else {
            echo '<option value="sams">Sams</option>';
            echo '<option value="costco">Costco</option>';
            echo '<option value="outro">Outro</option>';
        }

        echo <<<HTML
                                        </select>
                                        <small class="text-muted">Selecione a loja onde este produto está disponível</small>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">NCM</label>
                                        <input type="text" class="form-control" id="ncmSearch" placeholder="Pesquisar NCM...">
                                        <select class="form-select mt-2" name="ncm" id="ncmSelect">
                                            <option value="">Selecione...</option>
HTML;

        foreach ($ncmOptions as $code => $label) {
            echo '<option value="' . htmlspecialchars($code) . '">' . htmlspecialchars($code . ' - ' . $label) . '</option>';
        }

        echo <<<HTML
                                        </select>
                                        <small class="text-muted">Opcional</small>
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
                                        <label class="form-label">Categoria</label>
                                        <select class="form-select" name="category_id">
                                            <option value="">Selecione...</option>
HTML;

        foreach ($categorias as $cat) {
            $catName = $cat['name'] ?? $cat['nome'] ?? '';
            echo '<option value="' . $cat['id'] . '">' . htmlspecialchars($catName) . '</option>';
        }

        echo <<<HTML
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Foto de Capa</label>
                                        <input type="file" class="form-control" name="capa" accept="image/*" id="capaInput">
                                        <div id="capaPreview" class="row mt-3"></div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Galeria de Fotos</label>
                                        <input type="file" class="form-control" name="imagens[]" multiple accept="image/*" id="imagensInput">
                                        <div id="imagePreview" class="row mt-3"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card mb-4">
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Preço (USD) *</label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="text" class="form-control" name="price" required>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Preço de Custo (USD)</label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="text" class="form-control" name="cost_price">
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Preço Promocional (USD)</label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="text" class="form-control" name="sale_price">
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Peso (kg)</label>
                                        <input type="text" class="form-control" name="weight">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Estoque</label>
                                        <input type="number" class="form-control" name="stock">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Estoque Mínimo</label>
                                        <input type="number" class="form-control" name="min_stock">
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
                                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-save"></i> Salvar</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </main>
        </div>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Preview de capa ao selecionar
        var capaInput = document.getElementById('capaInput');
        if (capaInput) {
            capaInput.addEventListener('change', function(e) {
                const preview = document.getElementById('capaPreview');
                if (!preview) return;
                preview.innerHTML = '';

                const file = (e.target.files || [])[0];
                if (file && file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(ev) {
                        const div = document.createElement('div');
                        div.className = 'col-md-4 mb-2';
                        div.innerHTML = '<img src="' + ev.target.result + '" class="img-thumbnail" style="width: 100%; height: 200px; object-fit: cover;">';
                        preview.appendChild(div);
                    };
                    reader.readAsDataURL(file);
                }
            });
        }

        // Preview de imagens ao selecionar
        var imagensInput = document.getElementById('imagensInput');
        if (imagensInput) {
            imagensInput.addEventListener('change', function(e) {
                const preview = document.getElementById('imagePreview');
                if (!preview) return;
                preview.innerHTML = '';

                Array.from(e.target.files || []).forEach((file) => {
                    if (!file.type || !file.type.startsWith('image/')) return;
                    const reader = new FileReader();
                    reader.onload = function(ev) {
                        const div = document.createElement('div');
                        div.className = 'col-md-3 mb-2';
                        div.innerHTML = '<img src="' + ev.target.result + '" class="img-thumbnail" style="width: 100%; height: 150px; object-fit: cover;">';
                        preview.appendChild(div);
                    };
                    reader.readAsDataURL(file);
                });
            });
        }

        // Busca no select de NCM
        (function() {
            const input = document.getElementById('ncmSearch');
            const select = document.getElementById('ncmSelect');
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
    </script>
</body>
</html>
HTML;
        exit;
    }
    
    public function salvar(Request $request) {
        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            $pdo->beginTransaction();
            
            $cols = $this->getTableColumns($pdo, 'produtos');

            $price = str_replace(['$', '.', ','], ['', '', '.'], (string) $request->getParam('price'));
            $costPrice = str_replace(['$', '.', ','], ['', '', '.'], (string) $request->getParam('cost_price'));
            $salePrice = str_replace(['$', '.', ','], ['', '', '.'], (string) $request->getParam('sale_price'));
            
            // Validar categoria se fornecida
            $categoryParam = $request->getParam('category_id');
            $categoryId = null;
            if (!empty($categoryParam)) {
                $stmtCat = $pdo->prepare("SELECT id FROM categorias WHERE id = ?");
                $stmtCat->execute([$categoryParam]);
                if (!$stmtCat->fetch()) {
                    throw new \Exception("Categoria selecionada não existe");
                }
                $categoryId = $categoryParam;
            }

            $data = [];
            if (in_array('name', $cols, true)) {
                $data['name'] = $request->getParam('name');
            } elseif (in_array('nome', $cols, true)) {
                $data['nome'] = $request->getParam('name');
            }

            if (in_array('sku', $cols, true)) $data['sku'] = $request->getParam('sku');
            if (in_array('loja', $cols, true)) $data['loja'] = $request->getParam('loja');
            if (in_array('ncm', $cols, true)) $data['ncm'] = $request->getParam('ncm');
            if (in_array('short_description', $cols, true)) $data['short_description'] = $request->getParam('short_description');
            if (in_array('description', $cols, true)) $data['description'] = $request->getParam('description');

            if (in_array('category_id', $cols, true)) {
                $data['category_id'] = $categoryId;
            } elseif (in_array('categoria_id', $cols, true)) {
                $data['categoria_id'] = $categoryId;
            }

            if (in_array('price', $cols, true)) $data['price'] = $price;
            if (in_array('cost_price', $cols, true) && $costPrice !== '') $data['cost_price'] = $costPrice;
            if (in_array('sale_price', $cols, true) && $salePrice !== '') $data['sale_price'] = $salePrice;

            if (in_array('weight', $cols, true)) $data['weight'] = $request->getParam('weight') ?: 0;
            if (in_array('stock', $cols, true)) $data['stock'] = $request->getParam('stock') ?: 0;
            if (in_array('min_stock', $cols, true)) $data['min_stock'] = $request->getParam('min_stock') ?: 0;

            if (in_array('status', $cols, true)) $data['status'] = $request->getParam('status') ?: 'published';
            if (in_array('active', $cols, true)) $data['active'] = $request->getParam('active') ?: 1;
            if (in_array('featured', $cols, true)) $data['featured'] = $request->getParam('featured') ?: 0;

            if (in_array('created_at', $cols, true) && empty($data['created_at'])) {
                $data['created_at'] = date('Y-m-d H:i:s');
            }
            if (in_array('updated_at', $cols, true) && empty($data['updated_at'])) {
                $data['updated_at'] = date('Y-m-d H:i:s');
            }

            $columnsSql = implode(', ', array_keys($data));
            $placeholders = ':' . implode(', :', array_keys($data));
            $stmt = $pdo->prepare("INSERT INTO produtos ({$columnsSql}) VALUES ({$placeholders})");
            foreach ($data as $k => $v) {
                $stmt->bindValue(':' . $k, $v);
            }
            $stmt->execute();
            
            $produto_id = $pdo->lastInsertId();

            // Processar foto de capa
            if (isset($_FILES['capa']) && !empty($_FILES['capa']['name']) && ($_FILES['capa']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $uploadDir = $this->getProdutoUploadsDir();
                $webDir = '/uploads/produtos/';
                $this->ensureDir($uploadDir);

                $name = $_FILES['capa']['name'];
                $fileName = preg_replace('/[^A-Za-z0-9\-_\.]/', '', $name);
                $fileName = time() . '_' . $fileName;
                $filePath = $uploadDir . $fileName;
                $webPath = $webDir . $fileName;

                if (move_uploaded_file($_FILES['capa']['tmp_name'], $filePath)) {
                    if (in_array('foto_principal', $cols, true)) {
                        $stmtCover = $pdo->prepare('UPDATE produtos SET foto_principal = ? WHERE id = ?');
                        $stmtCover->execute([$webPath, $produto_id]);
                    }
                }
            }
            
            // Processar galeria de imagens
            if (isset($_FILES['imagens']) && !empty($_FILES['imagens']['name'][0])) {
                $uploadDir = $this->getProdutoUploadsDir();
                $webDir = '/uploads/produtos/';
                $this->ensureDir($uploadDir);
                
                foreach ($_FILES['imagens']['name'] as $key => $name) {
                    if ($_FILES['imagens']['error'][$key] === UPLOAD_ERR_OK) {
                        // Limpar nome do arquivo
                        $fileName = preg_replace('/[^A-Za-z0-9\-_\.]/', '', $name);
                        $fileName = time() . '_' . $fileName;
                        
                        $filePath = $uploadDir . $fileName;
                        $webPath = $webDir . $fileName;
                        
                        if (move_uploaded_file($_FILES['imagens']['tmp_name'][$key], $filePath)) {
                            $stmt = $pdo->prepare("
                                INSERT INTO produto_fotos (produto_id, nome_arquivo, arquivo_original, principal, ordem, created_at, updated_at)
                                VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                            ");
                            $stmt->execute([
                                $produto_id,
                                $webPath,
                                $name,
                                0,
                                $key
                            ]);
                            
                            error_log('✅ [ADMIN-PRODUTO] Foto salva: ' . $webPath);
                        } else {
                            error_log('❌ [ADMIN-PRODUTO] Erro ao salvar foto: ' . $name);
                        }
                    }
                }
            } else {
                error_log('⚠️ [ADMIN-PRODUTO] Nenhuma imagem enviada');
            }
            
            $pdo->commit();
            header('Location: /admin/produtos?success=1');
            exit;
            
        } catch (\Exception $e) {
            if (isset($pdo)) $pdo->rollBack();
            echo '<div class="alert alert-danger">Erro: ' . $e->getMessage() . '</div>';
            exit;
        }
    }
    
    public function editar(Request $request) {
        $id = (int) $request->getParam('id');

        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');

            $stmtProduto = $pdo->prepare('SELECT * FROM produtos WHERE id = ?');
            $stmtProduto->execute([$id]);
            $produto = $stmtProduto->fetch(\PDO::FETCH_ASSOC);
            if (!$produto) {
                throw new \Exception('Produto não encontrado');
            }

            $stmtCat = $pdo->query('SELECT * FROM categorias ORDER BY name ASC');
            $categorias = $stmtCat->fetchAll(\PDO::FETCH_ASSOC);

            $stmtFotos = $pdo->prepare('SELECT * FROM produto_fotos WHERE produto_id = ? ORDER BY principal DESC, id ASC');
            $stmtFotos->execute([$id]);
            $fotos = $stmtFotos->fetchAll(\PDO::FETCH_ASSOC);

            $lojas = $this->fetchLojasSafe();
            $ncmOptions = $this->getNcmOptions();
        } catch (\Exception $e) {
            echo '<div class="alert alert-danger">Erro: ' . $e->getMessage() . '</div>';
            exit;
        }

        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Produto - Braziliana Shop Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';

        renderAdminSidebarStyles();

        echo '</head>
<body>
    <div class="container-fluid">
        <div class="row">';

        renderAdminSidebar('produtos');

        $fotoCapa = $produto['foto_principal'] ?? null;
        $fotoCapaPath = null;
        $fotoCapaUrl = null;
        if (!empty($fotoCapa)) {
            $fotoCapaPath = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim((string) $fotoCapa, '/');
            if (file_exists($fotoCapaPath)) {
                $fotoCapaUrl = Url::absolute((string) $fotoCapa);
            }
        }

        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Editar Produto</h1>
                    <a href="/admin/produtos" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
                </div>

                <form method="POST" action="/admin/produtos/atualizar/' . $id . '" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="card mb-4">
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Nome *</label>
                                        <input type="text" class="form-control" name="name" value="' . htmlspecialchars($produto['name']) . '" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">SKU *</label>
                                        <input type="text" class="form-control" name="sku" value="' . htmlspecialchars($produto['sku']) . '" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Loja *</label>
                                        <select class="form-select" name="loja" required>
                                            <option value="">Selecione...</option>';

        if (!empty($lojas)) {
            $produtoLoja = trim((string) ($produto['loja'] ?? ''));
            $produtoLojaNorm = strtolower($produtoLoja);
            foreach ($lojas as $l) {
                $lojaSlug = (string) ($l['slug'] ?? '');
                $lojaId = (string) ($l['id'] ?? '');
                $lojaNome = (string) ($l['nome'] ?? '');

                $lojaSlugNorm = strtolower(trim($lojaSlug));
                $lojaIdNorm = strtolower(trim($lojaId));
                $lojaNomeNorm = strtolower(trim($lojaNome));

                $selected = ($produtoLojaNorm !== '' && (
                    $produtoLojaNorm === $lojaSlugNorm ||
                    $produtoLojaNorm === $lojaIdNorm ||
                    $produtoLojaNorm === $lojaNomeNorm
                )) ? 'selected' : '';
                echo '<option value="' . htmlspecialchars($l['slug']) . '" ' . $selected . '>' . htmlspecialchars($l['nome']) . '</option>';
            }
        } else {
            echo '<option value="sams" ' . (($produto['loja'] ?? '') === 'sams' ? 'selected' : '') . '>Sams</option>';
            echo '<option value="costco" ' . (($produto['loja'] ?? '') === 'costco' ? 'selected' : '') . '>Costco</option>';
            echo '<option value="outro" ' . (($produto['loja'] ?? '') === 'outro' ? 'selected' : '') . '>Outro</option>';
        }

        echo '</select>
                                        <small class="text-muted">Selecione a loja onde este produto está disponível</small>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">NCM</label>
                                        <input type="text" class="form-control" id="ncmSearch" placeholder="Pesquisar NCM...">
                                        <select class="form-select mt-2" name="ncm" id="ncmSelect">
                                            <option value="">Selecione...</option>';

        $currentNcm = preg_replace('/\D+/', '', (string) ($produto['ncm'] ?? ''));
        foreach ($ncmOptions as $code => $label) {
            $selected = ($currentNcm !== '' && $currentNcm === (string) $code) ? 'selected' : '';
            echo '<option value="' . htmlspecialchars($code) . '" ' . $selected . '>' . htmlspecialchars($code . ' - ' . $label) . '</option>';
        }

        echo '                        </select>
                                        <small class="text-muted">Opcional</small>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Descrição Curta</label>
                                        <textarea class="form-control" name="short_description" rows="3">' . htmlspecialchars($produto['short_description'] ?? '') . '</textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Descrição Completa</label>
                                        <textarea class="form-control" name="description" rows="5">' . htmlspecialchars($produto['description'] ?? '') . '</textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Categoria</label>
                                        <select class="form-select" name="category_id">
                                            <option value="">Selecione...</option>';

        foreach ($categorias as $cat) {
            $selected = ((string) ($cat['id'] ?? '') === (string) ($produto['category_id'] ?? '')) ? 'selected' : '';
            echo '<option value="' . (int) $cat['id'] . '" ' . $selected . '>' . htmlspecialchars($cat['name']) . '</option>';
        }

        echo '                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Foto de Capa</label>
                                        <div class="row mb-3">';

        if (!empty($fotoCapaUrl)) {
            echo '<div class="col-6 col-md-3 mb-2">
                    <a href="' . $fotoCapaUrl . '" target="_blank">
                        <img src="' . $fotoCapaUrl . '" alt="Capa" class="img-thumbnail" style="width: 100%; height: 140px; object-fit: cover;">
                    </a>
                </div>';
        } else {
            echo '<div class="col-6 col-md-3 mb-2">
                    <img src="' . Url::absolute('/uploads/produtos/placeholder.jpg') . '" alt="Sem capa" class="img-thumbnail" style="width: 100%; height: 140px; object-fit: cover;">
                </div>';
        }

        echo '</div>
                                        <div class="d-flex gap-2 align-items-center">
                                            <input type="file" class="form-control" name="capa" accept="image/*">
                                            <button type="submit" class="btn btn-outline-danger" formaction="/admin/produtos/remover-capa/' . (int) $id . '" formmethod="POST" formnovalidate ' . (!empty($fotoCapaUrl) ? '' : 'disabled') . '>Remover capa</button>
                                        </div>
                                        <small class="text-muted">A foto de capa é usada como imagem principal do produto</small>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Galeria de Fotos</label>
                                        <div class="row mb-3">';

        foreach ($fotos as $foto) {
            $filePath = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($foto['nome_arquivo'], '/');
            $imageUrl = file_exists($filePath) ? Url::absolute($foto['nome_arquivo']) : Url::absolute('/uploads/produtos/placeholder.jpg');
            $fotoId = (int) ($foto['id'] ?? 0);
            $ordem = (int) ($foto['ordem'] ?? 0);
            echo '<div class="col-6 col-md-2 mb-2">
                    <a href="' . $imageUrl . '" target="_blank">
                        <img src="' . $imageUrl . '" alt="Foto" class="img-thumbnail" style="width: 100%; height: 100px; object-fit: cover;">
                    </a>
                    <div class="mt-2">
                        <input type="number" class="form-control form-control-sm" name="ordens[' . $fotoId . ']" value="' . $ordem . '" min="0">
                    </div>
                    <div class="mt-2">
                        <button type="submit" class="btn btn-sm btn-outline-danger w-100" formaction="/admin/produtos/remover-foto/' . $fotoId . '" formmethod="POST" formnovalidate onclick="return confirm(\'Remover esta foto?\')">Remover</button>
                    </div>
                </div>';
        }

        echo '                        </div>
                                        <div class="d-flex gap-2 align-items-center">
                                            <input type="file" class="form-control" name="imagens[]" multiple accept="image/*">
                                            <button type="submit" class="btn btn-outline-primary" formaction="/admin/produtos/galeria/ordem/' . (int) $id . '" formmethod="POST" formnovalidate>Salvar ordem</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card mb-4">
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Preço (USD) *</label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="text" class="form-control" name="price" value="' . htmlspecialchars($produto['price'] ?? '') . '" required>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Preço de Custo (USD)</label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="text" class="form-control" name="cost_price" value="' . htmlspecialchars($produto['cost_price'] ?? '') . '">
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Preço Promocional (USD)</label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="text" class="form-control" name="sale_price" value="' . htmlspecialchars($produto['sale_price'] ?? '') . '">
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Estoque</label>
                                        <input type="number" class="form-control" name="stock" value="' . htmlspecialchars($produto['stock'] ?? 0) . '">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Estoque Mínimo</label>
                                        <input type="number" class="form-control" name="min_stock" value="' . htmlspecialchars($produto['min_stock'] ?? 0) . '">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Peso (kg)</label>
                                        <input type="text" class="form-control" name="weight" value="' . htmlspecialchars($produto['weight'] ?? 0) . '">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Status</label>
                                        <select class="form-select" name="status">
                                            <option value="draft" ' . (($produto['status'] ?? '') === 'draft' ? 'selected' : '') . '>Rascunho</option>
                                            <option value="published" ' . (($produto['status'] ?? '') === 'published' ? 'selected' : '') . '>Publicado</option>
                                            <option value="archived" ' . (($produto['status'] ?? '') === 'archived' ? 'selected' : '') . '>Arquivado</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Ativo</label>
                                        <select class="form-select" name="active">
                                            <option value="1" ' . (!empty($produto['active']) ? 'selected' : '') . '>Ativo</option>
                                            <option value="0" ' . (empty($produto['active']) ? 'selected' : '') . '>Inativo</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Destaque</label>
                                        <select class="form-select" name="featured">
                                            <option value="1" ' . (!empty($produto['featured']) ? 'selected' : '') . '>Sim</option>
                                            <option value="0" ' . (empty($produto['featured']) ? 'selected' : '') . '>Não</option>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-save"></i> Atualizar</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </main>
        </div>
    </div>
    ';

        renderAdminScripts();

        echo '<script>
        // Busca no select de NCM
        (function() {
            const input = document.getElementById("ncmSearch");
            const select = document.getElementById("ncmSelect");
            if (!input || !select) return;

            input.addEventListener("input", function() {
                const q = (input.value || "").toLowerCase().trim();
                Array.from(select.options).forEach((opt) => {
                    if (opt.value === "") return;
                    const text = (opt.text || "").toLowerCase();
                    opt.hidden = q !== "" && !text.includes(q);
                });
            });
        })();
        </script>';

        echo '
</body>
</html>';
        exit;
    }
    
    public function atualizar(Request $request, $id = null) {
        $id = $id ?? $request->getParam('id');
        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            $pdo->beginTransaction();
            
            $price = str_replace(['$', '.', ','], ['', '', '.'], $request->getParam('price'));
            $costPrice = str_replace(['$', '.', ','], ['', '', '.'], $request->getParam('cost_price'));
            $salePrice = str_replace(['$', '.', ','], ['', '', '.'], $request->getParam('sale_price'));
            
            // Validar categoria se fornecida
            $categoryId = $request->getParam('category_id');
            if (!empty($categoryId)) {
                $stmtCat = $pdo->prepare("SELECT id FROM categorias WHERE id = ?");
                $stmtCat->execute([$categoryId]);
                if (!$stmtCat->fetch()) {
                    throw new \Exception("Categoria selecionada não existe");
                }
            } else {
                $categoryId = null;
            }
            
            $stmt = $pdo->prepare("
                UPDATE produtos SET 
                    name = ?, sku = ?, loja = ?, ncm = ?, description = ?, short_description = ?, category_id = ?, 
                    price = ?, cost_price = ?, sale_price = ?, stock = ?, min_stock = ?, weight = ?, 
                    status = ?, active = ?, featured = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $request->getParam('name'),
                $request->getParam('sku'),
                $request->getParam('loja'),
                $request->getParam('ncm'),
                $request->getParam('description'),
                $request->getParam('short_description'),
                $categoryId,
                $price,
                $costPrice,
                $salePrice,
                $request->getParam('stock') ?: 0,
                $request->getParam('min_stock') ?: 0,
                $request->getParam('weight') ?: 0,
                $request->getParam('status'),
                $request->getParam('active') ?: 0,
                $request->getParam('featured') ?: 0,
                $id
            ]);

            // Atualizar foto de capa (se enviada)
            if (isset($_FILES['capa']) && !empty($_FILES['capa']['name']) && ($_FILES['capa']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $uploadDir = $this->getProdutoUploadsDir();
                $webDir = '/uploads/produtos/';
                $this->ensureDir($uploadDir);

                $name = $_FILES['capa']['name'];
                $fileName = preg_replace('/[^A-Za-z0-9\-_\.]/', '', $name);
                $fileName = time() . '_' . $fileName;
                $filePath = $uploadDir . $fileName;
                $webPath = $webDir . $fileName;

                if (move_uploaded_file($_FILES['capa']['tmp_name'], $filePath)) {
                    $stmtCover = $pdo->prepare('UPDATE produtos SET foto_principal = ? WHERE id = ?');
                    $stmtCover->execute([$webPath, $id]);
                }
            }
            
            // Processar novas imagens
            if (isset($_FILES['imagens'])) {
                $uploadDir = $this->getProdutoUploadsDir();
                $webDir = '/uploads/produtos/';
                $this->ensureDir($uploadDir);
                
                foreach ($_FILES['imagens']['name'] as $key => $name) {
                    if ($_FILES['imagens']['error'][$key] === 0) {
                        // Limpar nome do arquivo
                        $fileName = preg_replace('/[^A-Za-z0-9\-_\.]/', '', $name);
                        $fileName = time() . '_' . $fileName;
                        
                        $filePath = $uploadDir . $fileName;
                        $webPath = $webDir . $fileName;
                        
                        if (move_uploaded_file($_FILES['imagens']['tmp_name'][$key], $filePath)) {
                            $stmt = $pdo->prepare("
                                INSERT INTO produto_fotos (produto_id, nome_arquivo, arquivo_original, principal, ordem)
                                VALUES (?, ?, ?, ?, ?)
                            ");
                            $stmt->execute([
                                $id,
                                $webPath,
                                $name,
                                0, // Galeria: não é principal
                                $key
                            ]);
                        }
                    }
                }
            }
            
            $pdo->commit();
            header('Location: /admin/produtos?success=2');
            exit;
            
        } catch (\Exception $e) {
            if (isset($pdo)) $pdo->rollBack();
            echo '<div class="alert alert-danger">Erro: ' . $e->getMessage() . '</div>';
            exit;
        }
    }
    
    public function removerFoto(Request $request, $fotoId = null) {
        $fotoId = $fotoId ?? $request->getParam('id');
        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            
            $stmt = $pdo->prepare("SELECT nome_arquivo FROM produto_fotos WHERE id = ?");
            $stmt->execute([$fotoId]);
            $foto = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($foto) {
                // Remover arquivo físico
                $filePath = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($foto['nome_arquivo'], '/');
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
                
                // Remover do banco
                $stmt = $pdo->prepare("DELETE FROM produto_fotos WHERE id = ?");
                $stmt->execute([$fotoId]);
                
                if ($this->isAjaxRequest()) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true]);
                } else {
                    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/admin/produtos'));
                }
            } else {
                if ($this->isAjaxRequest()) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Foto não encontrada']);
                } else {
                    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/admin/produtos'));
                }
            }
        } catch (\Exception $e) {
            if ($this->isAjaxRequest()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            } else {
                header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/admin/produtos'));
            }
        }
    }

    public function removerCapa(Request $request, $id = null) {
        $id = (int) ($id ?? $request->getParam('id'));
        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            $stmt = $pdo->prepare('SELECT foto_principal FROM produtos WHERE id = ?');
            $stmt->execute([$id]);
            $produto = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($produto && !empty($produto['foto_principal'])) {
                $filePath = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim((string) $produto['foto_principal'], '/');
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }

            $stmt = $pdo->prepare('UPDATE produtos SET foto_principal = NULL WHERE id = ?');
            $stmt->execute([$id]);

            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? ('/admin/produtos/editar/' . $id)));
            exit;
        } catch (\Exception $e) {
            echo '<div class="alert alert-danger">Erro: ' . $e->getMessage() . '</div>';
            exit;
        }
    }

    public function salvarOrdemGaleria(Request $request, $id = null) {
        $id = (int) ($id ?? $request->getParam('id'));
        $ordens = $request->getParam('ordens', []);
        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            $pdo->beginTransaction();

            if (is_array($ordens)) {
                foreach ($ordens as $fotoId => $ordem) {
                    $fotoId = (int) $fotoId;
                    $ordem = (int) $ordem;
                    if ($fotoId <= 0) {
                        continue;
                    }
                    $stmt = $pdo->prepare('UPDATE produto_fotos SET ordem = ? WHERE id = ? AND produto_id = ?');
                    $stmt->execute([$ordem, $fotoId, $id]);
                }
            }

            $pdo->commit();
            header('Location: /admin/produtos/editar/' . $id);
            exit;
        } catch (\Exception $e) {
            if (isset($pdo)) {
                $pdo->rollBack();
            }
            echo '<div class="alert alert-danger">Erro: ' . $e->getMessage() . '</div>';
            exit;
        }
    }
    
    public function excluir(Request $request, $id = null) {
        $id = $id ?? $request->getParam('id');
        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            $pdo->beginTransaction();
            
            // Buscar produto para obter imagens
            $stmt = $pdo->prepare("SELECT * FROM produtos WHERE id = ?");
            $stmt->execute([$id]);
            $produto = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$produto) {
                throw new \Exception("Produto não encontrado");
            }
            
            // Remover imagens físicas
            $stmtFotos = $pdo->prepare("SELECT nome_arquivo FROM produto_fotos WHERE produto_id = ?");
            $stmtFotos->execute([$id]);
            $fotos = $stmtFotos->fetchAll(\PDO::FETCH_ASSOC);
            
            foreach ($fotos as $foto) {
                $filePath = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($foto['nome_arquivo'], '/');
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
            
            // Remover fotos do banco
            $stmt = $pdo->prepare("DELETE FROM produto_fotos WHERE produto_id = ?");
            $stmt->execute([$id]);
            
            // Remover produto
            $stmt = $pdo->prepare("DELETE FROM produtos WHERE id = ?");
            $stmt->execute([$id]);
            
            $pdo->commit();
            header('Location: /admin/produtos?success=3');
            exit;
            
        } catch (\Exception $e) {
            if (isset($pdo)) $pdo->rollBack();
            echo '<div class="alert alert-danger">Erro: ' . $e->getMessage() . '</div>';
            exit;
        }
    }
}
