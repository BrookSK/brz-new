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
        $normalized = $this->normalizeUploadsWebPath($urlPath);
        if ($normalized === null) {
            return null;
        }

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

    private function normalizeUploadsWebPath(string $path): ?string {
        $path = is_string($path) ? trim($path) : '';
        if ($path === '') {
            return null;
        }

        // If a full URL was stored, keep only the path part
        if (preg_match('#^https?://#i', $path)) {
            $parsed = parse_url($path);
            $path = (string) ($parsed['path'] ?? '');
        }

        if ($path === '') {
            return null;
        }

        if ($path[0] !== '/') {
            $path = '/' . $path;
        }

        if (strpos($path, '/uploads/') === 0) {
            return $path;
        }

        return '/uploads/produtos/' . ltrim($path, '/');
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

    public function cadastroRapido(Request $request) {
        if (!$this->ensureCadastroRapidoAccess($request)) {
            return;
        }
        $this->renderCadastroRapido(null, null);
    }

    public function cadastroRapidoSalvar(Request $request) {
        if (!$this->ensureCadastroRapidoAccess($request)) {
            return;
        }
        try {
            $created = $this->salvarCadastroRapido($request);
            $this->renderCadastroRapido($created, null);
        } catch (\Exception $e) {
            $this->renderCadastroRapido(null, $e->getMessage());
        }
    }

    private function ensureCadastroRapidoAccess(Request $request): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!empty($_SESSION['cadastro_rapido_autorizado'])) {
            return true;
        }

        $senha = (string) ($request->getParam('senha', '') ?? '');
        if ($request->getMethod() === 'POST' && $senha !== '') {
            if (hash_equals('sonhodafabi', $senha)) {
                $_SESSION['cadastro_rapido_autorizado'] = true;
                return true;
            }
            $this->renderCadastroRapidoSenha('Senha inválida');
            return false;
        }

        $this->renderCadastroRapidoSenha(null);
        return false;
    }

    private function renderCadastroRapidoSenha(?string $error): void {
        $errorHtml = '';
        if (!empty($error)) {
            $errorHtml = '<div class="alert alert-danger" style="border-radius:14px;">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</div>';
        }

        echo <<<'HTML'
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso - Cadastro Rápido</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #0b1f3a;
            --bg: #f6f8fb;
            --radius: 18px;
            --shadow: 0 12px 34px rgba(15, 23, 42, 0.10);
        }
        body { background: var(--bg); }
        .topbar {
            background: radial-gradient(1200px 260px at 50% -60%, rgba(11,31,58,0.18), rgba(11,31,58,0)) ,
                        linear-gradient(180deg, rgba(11,31,58,0.06), rgba(11,31,58,0));
            padding: 16px 0 10px;
        }
        .page-title { color: var(--primary); font-weight: 800; letter-spacing: -0.02em; }
        .subtle { color: rgba(15, 23, 42, 0.62); }
        .glass {
            background: rgba(255,255,255,0.86);
            border: 1px solid rgba(148,163,184,0.24);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            backdrop-filter: blur(10px);
        }
        .form-control, .btn { border-radius: 14px; }
        .btn-primary { background: var(--primary); border-color: var(--primary); }
        .btn-outline-primary { border-color: rgba(11,31,58,0.35); color: var(--primary); }
    </style>
</head>
<body>
    <div class="topbar">
        <div class="container" style="max-width: 560px;">
            <div class="d-flex align-items-center justify-content-between">
                <a href="/" class="btn btn-outline-secondary btn-sm" style="border-radius: 999px;">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div class="text-center">
                    <div class="page-title">Acesso ao cadastro rápido</div>
                    <div class="small subtle">Digite a senha para continuar</div>
                </div>
                <span style="width: 40px;"></span>
            </div>
        </div>
    </div>

    <div class="container pb-4" style="max-width: 560px;">
HTML;

        echo $errorHtml;

        echo <<<'HTML'
        <div class="glass p-3 p-sm-4">
            <form method="POST" action="/admin/produtos/cadastro-rapido">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Senha</label>
                    <input type="password" class="form-control form-control-lg" name="senha" required autocomplete="current-password" placeholder="Digite a senha">
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-lock-open me-2"></i>Entrar
                    </button>
                </div>

                <div class="small subtle mt-3">Após validar, você poderá acessar e salvar produtos sem login (neste navegador).</div>
            </form>
        </div>
    </div>
</body>
</html>
HTML;

        exit;
    }

    public function uploadFotosVariacao(Request $request, $id = null) {
        $varId = (int) ($id ?? $request->getParam('id'));
        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            $pdo->beginTransaction();

            if (!$this->tableExists($pdo, 'produto_variacao_fotos')) {
                throw new \Exception('Tabela produto_variacao_fotos não encontrada');
            }

            $hasLegenda = false;
            try {
                $stCol = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'produto_variacao_fotos' AND column_name = 'legenda'");
                $stCol->execute();
                $hasLegenda = ((int) $stCol->fetchColumn()) > 0;
            } catch (\Throwable $e) {
                $hasLegenda = false;
            }

            $inserted = [];
            if (isset($_FILES['imagens']) && !empty($_FILES['imagens']['name'][0])) {
                $uploadDir = $this->getProdutoUploadsDir();
                $webDir = '/uploads/produtos/';
                $this->ensureDir($uploadDir);

                // ordem base
                $ordBase = 0;
                try {
                    $stMax = $pdo->prepare('SELECT COALESCE(MAX(ordem),0) FROM produto_variacao_fotos WHERE produto_variacao_id = ?');
                    $stMax->execute([$varId]);
                    $ordBase = (int) ($stMax->fetchColumn() ?: 0);
                    $ordBase++;
                } catch (\Exception $e) {
                    $ordBase = 0;
                }

                foreach ($_FILES['imagens']['name'] as $key => $name) {
                    if (($_FILES['imagens']['error'][$key] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;

                    $fileName = preg_replace('/[^A-Za-z0-9\-_\.]/', '', (string) $name);
                    $fileName = time() . '_' . $varId . '_' . $fileName;
                    $filePath = $uploadDir . $fileName;
                    $webPath = $webDir . $fileName;

                    if (!move_uploaded_file($_FILES['imagens']['tmp_name'][$key], $filePath)) {
                        continue;
                    }

                    if ($hasLegenda) {
                        $stmt = $pdo->prepare('INSERT INTO produto_variacao_fotos (produto_variacao_id, nome_arquivo, arquivo_original, legenda, ordem) VALUES (?, ?, ?, ?, ?)');
                        $stmt->execute([$varId, $webPath, $name, null, $ordBase + (int) $key]);
                    } else {
                        $stmt = $pdo->prepare('INSERT INTO produto_variacao_fotos (produto_variacao_id, nome_arquivo, arquivo_original, ordem) VALUES (?, ?, ?, ?)');
                        $stmt->execute([$varId, $webPath, $name, $ordBase + (int) $key]);
                    }
                    $insertId = (int) $pdo->lastInsertId();
                    $inserted[] = ['id' => $insertId, 'url' => Url::absolute($webPath)];
                }
            }

            $pdo->commit();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => true, 'fotos' => $inserted]);
            exit;
        } catch (\Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }

    public function removerFotoVariacao(Request $request, $id = null) {
        $fotoId = (int) ($id ?? $request->getParam('id'));
        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            if (!$this->tableExists($pdo, 'produto_variacao_fotos')) {
                throw new \Exception('Tabela produto_variacao_fotos não encontrada');
            }

            $stmt = $pdo->prepare('SELECT nome_arquivo, produto_variacao_id FROM produto_variacao_fotos WHERE id = ?');
            $stmt->execute([$fotoId]);
            $foto = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$foto) {
                header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/admin/produtos'));
                exit;
            }

            $path = (string) ($foto['nome_arquivo'] ?? '');
            $filePath = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($path, '/');
            if ($path !== '' && file_exists($filePath)) {
                @unlink($filePath);
            }

            $stmtD = $pdo->prepare('DELETE FROM produto_variacao_fotos WHERE id = ?');
            $stmtD->execute([$fotoId]);

            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/admin/produtos'));
            exit;
        } catch (\Exception $e) {
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/admin/produtos'));
            exit;
        }
    }

    public function salvarOrdemFotosVariacao(Request $request, $id = null) {
        $varId = (int) ($id ?? $request->getParam('id'));
        $ordens = $request->getParam('ordens_variacao', []);
        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            if (!$this->tableExists($pdo, 'produto_variacao_fotos')) {
                throw new \Exception('Tabela produto_variacao_fotos não encontrada');
            }

            $pdo->beginTransaction();
            if (is_array($ordens)) {
                foreach ($ordens as $fotoId => $ordem) {
                    $fotoId = (int) $fotoId;
                    $ordem = (int) $ordem;
                    if ($fotoId <= 0) continue;
                    $st = $pdo->prepare('UPDATE produto_variacao_fotos SET ordem = ? WHERE id = ? AND produto_variacao_id = ?');
                    $st->execute([$ordem, $fotoId, $varId]);
                }
            }
            $pdo->commit();
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/admin/produtos'));
            exit;
        } catch (\Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/admin/produtos'));
            exit;
        }
    }

    private function salvarCadastroRapido(Request $request): array {
        $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
        $pdo->beginTransaction();

        $cols = $this->getTableColumns($pdo, 'produtos');

        $name = (string) $request->getParam('name');
        $price = str_replace(['$', '.', ','], ['', '', '.'], (string) $request->getParam('price'));
        $weight = str_replace([','], ['.'], (string) $request->getParam('weight'));
        $stock = (int) $request->getParam('stock', 999);
        $featured = $request->getParam('featured') ? 1 : 0;

        if (trim($name) === '') {
            throw new \Exception('Nome é obrigatório');
        }

        $data = [];
        if (in_array('name', $cols, true)) {
            $data['name'] = $name;
        } elseif (in_array('nome', $cols, true)) {
            $data['nome'] = $name;
        }

        if (in_array('price', $cols, true)) $data['price'] = $price;
        if (in_array('valor', $cols, true) && !isset($data['price'])) $data['valor'] = $price;

        if (in_array('weight', $cols, true)) $data['weight'] = $weight;
        if (in_array('peso', $cols, true) && !isset($data['weight'])) $data['peso'] = $weight;

        if (in_array('stock', $cols, true)) $data['stock'] = $stock;
        if (in_array('estoque', $cols, true) && !isset($data['stock'])) $data['estoque'] = $stock;

        if (in_array('status', $cols, true)) $data['status'] = 'published';
        if (in_array('active', $cols, true)) $data['active'] = 1;
        if (in_array('ativo', $cols, true) && !isset($data['active'])) $data['ativo'] = 1;
        if (in_array('featured', $cols, true)) $data['featured'] = $featured;

        if (in_array('created_at', $cols, true)) $data['created_at'] = date('Y-m-d H:i:s');
        if (in_array('updated_at', $cols, true)) $data['updated_at'] = date('Y-m-d H:i:s');

        $columnsSql = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        $stmt = $pdo->prepare("INSERT INTO produtos ({$columnsSql}) VALUES ({$placeholders})");
        foreach ($data as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->execute();

        $produtoId = (int) $pdo->lastInsertId();
        $fotoWebPath = '';

        if (isset($_FILES['capa']) && !empty($_FILES['capa']['name']) && ($_FILES['capa']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $uploadDir = $this->getProdutoUploadsDir();
            $webDir = '/uploads/produtos/';
            $this->ensureDir($uploadDir);

            $orig = (string) $_FILES['capa']['name'];
            $fileName = preg_replace('/[^A-Za-z0-9\-_\.]/', '', $orig);
            $fileName = time() . '_' . $fileName;
            $filePath = $uploadDir . $fileName;
            $fotoWebPath = $webDir . $fileName;

            if (move_uploaded_file($_FILES['capa']['tmp_name'], $filePath)) {
                if (in_array('foto_principal', $cols, true)) {
                    $stmtCover = $pdo->prepare('UPDATE produtos SET foto_principal = ? WHERE id = ?');
                    $stmtCover->execute([$fotoWebPath, $produtoId]);
                }
            }
        }

        $pdo->commit();

        return [
            'id' => $produtoId,
            'name' => $name,
            'price' => $price,
            'weight' => $weight,
            'stock' => $stock,
            'featured' => $featured,
            'foto_principal' => $fotoWebPath,
        ];
    }

    private function renderCadastroRapido(?array $created, ?string $error): void {
        $successHtml = '';
        if (!empty($error)) {
            $successHtml = '<div class="alert alert-danger" style="border-radius:14px;">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</div>';
        } elseif (is_array($created) && !empty($created['id'])) {
            $id = (int) $created['id'];
            $nome = htmlspecialchars((string) ($created['name'] ?? ''), ENT_QUOTES, 'UTF-8');
            $foto = (string) ($created['foto_principal'] ?? '');
            $valor = htmlspecialchars((string) ($created['price'] ?? ''), ENT_QUOTES, 'UTF-8');
            $peso = htmlspecialchars((string) ($created['weight'] ?? ''), ENT_QUOTES, 'UTF-8');
            $estoque = htmlspecialchars((string) ($created['stock'] ?? ''), ENT_QUOTES, 'UTF-8');
            $destaque = !empty($created['featured']) ? 'Sim' : 'Não';
            if ($foto === '') {
                $foto = '/uploads/produtos/placeholder.jpg';
            }
            $fotoEsc = htmlspecialchars($foto, ENT_QUOTES, 'UTF-8');
            $link = '/produto/detalhes/' . $id;
            $linkEsc = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');

            $successHtml = '<div class="alert alert-success d-flex align-items-start gap-2" role="alert" style="border-radius: 14px;">'
                . '<i class="fas fa-check-circle mt-1"></i>'
                . '<div><div class="fw-bold">Produto salvo com sucesso.</div><div class="small">Se estiver como destaque, ele deve aparecer na Home.</div></div>'
                . '</div>'
                . '<div class="card border-0 shadow-sm mb-3" style="border-radius: 18px; overflow: hidden;">'
                . '<div class="row g-0">'
                . '<div class="col-4" style="min-height: 92px;"><img src="' . $fotoEsc . '" alt="' . $nome . '" style="width:100%;height:100%;object-fit:cover;min-height:92px;"></div>'
                . '<div class="col-8"><div class="card-body py-3">'
                . '<div class="fw-bold" style="line-height:1.2">' . $nome . '</div>'
                . '<div class="small text-muted">Link do produto</div>'
                . '<div class="small" style="word-break: break-all;"><a href="' . $linkEsc . '" target="_blank">' . $linkEsc . '</a></div>'
                . '<div class="small text-muted mt-2">Detalhes</div>'
                . '<div class="small">Valor (USD): <span class="fw-semibold">$ ' . $valor . '</span></div>'
                . '<div class="small">Peso (kg): <span class="fw-semibold">' . $peso . '</span></div>'
                . '<div class="small">Estoque: <span class="fw-semibold">' . $estoque . '</span></div>'
                . '<div class="small">Destaque: <span class="fw-semibold">' . htmlspecialchars($destaque, ENT_QUOTES, 'UTF-8') . '</span></div>'
                . '<div class="d-grid gap-2 mt-2">'
                . '<a class="btn btn-outline-primary" href="' . $linkEsc . '" target="_blank"><i class="fas fa-external-link-alt me-2"></i>Abrir produto</a>'
                . '<a class="btn btn-primary" href="/admin/produtos/cadastro-rapido"><i class="fas fa-plus me-2"></i>Novo envio</a>'
                . '</div>'
                . '</div></div>'
                . '</div>'
                . '</div>';
        }

        echo <<<'HTML'
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro Rápido - Produtos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #0b1f3a;
            --bg: #f6f8fb;
            --radius: 18px;
            --shadow: 0 12px 34px rgba(15, 23, 42, 0.10);
        }
        body { background: var(--bg); }
        .topbar {
            background: radial-gradient(1200px 260px at 50% -60%, rgba(11,31,58,0.18), rgba(11,31,58,0)) ,
                        linear-gradient(180deg, rgba(11,31,58,0.06), rgba(11,31,58,0));
            padding: 16px 0 10px;
        }
        .page-title { color: var(--primary); font-weight: 800; letter-spacing: -0.02em; }
        .subtle { color: rgba(15, 23, 42, 0.62); }
        .glass {
            background: rgba(255,255,255,0.86);
            border: 1px solid rgba(148,163,184,0.24);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            backdrop-filter: blur(10px);
        }
        .form-control, .input-group-text, .btn { border-radius: 14px; }
        .input-group .input-group-text { border-top-right-radius: 0; border-bottom-right-radius: 0; }
        .input-group .form-control { border-top-left-radius: 0; border-bottom-left-radius: 0; }
        .btn-primary { background: var(--primary); border-color: var(--primary); }
        .btn-outline-primary { border-color: rgba(11,31,58,0.35); color: var(--primary); }
    </style>
</head>
<body>
    <div class="topbar">
        <div class="container" style="max-width: 560px;">
            <div class="d-flex align-items-center justify-content-between">
                <a href="/admin/produtos" class="btn btn-outline-secondary btn-sm" style="border-radius: 999px;">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div class="text-center">
                    <div class="page-title">Cadastro rápido</div>
                    <div class="small subtle">Mobile-first, envio rápido para Home</div>
                </div>
                <span style="width: 40px;"></span>
            </div>
        </div>
    </div>

    <div class="container pb-4" style="max-width: 560px;">
HTML;

        echo $successHtml;

        echo <<<'HTML'
        <div class="glass p-3 p-sm-4">
            <form method="POST" action="/admin/produtos/cadastro-rapido/salvar" enctype="multipart/form-data">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Foto do produto</label>
                    <input type="file" class="form-control" name="capa" accept="image/*" id="capaInput">
                    <div id="capaPreview" class="mt-3"></div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Nome</label>
                    <input type="text" class="form-control form-control-lg" name="name" required autocomplete="off" placeholder="Ex: iPhone 15 Pro">
                </div>

                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label fw-semibold">Valor (USD)</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text">$</span>
                            <input type="text" class="form-control" name="price" required inputmode="decimal" placeholder="0,00">
                        </div>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">Peso (kg)</label>
                        <input type="text" class="form-control form-control-lg" name="weight" required inputmode="decimal" placeholder="0,000">
                    </div>
                </div>

                <div class="mt-3">
                    <label class="form-label fw-semibold">Estoque</label>
                    <input type="number" class="form-control form-control-lg" name="stock" value="999" min="0" step="1">
                    <div class="small subtle mt-1">Pré-preenchido com 999 para garantir disponibilidade.</div>
                </div>

                <div class="form-check form-switch mt-3">
                    <input class="form-check-input" type="checkbox" role="switch" id="featuredSwitch" name="featured" value="1" checked>
                    <label class="form-check-label fw-semibold" for="featuredSwitch">Destaque (aparece na Home)</label>
                </div>

                <div class="d-grid mt-4">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-bolt me-2"></i>Salvar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        var capaInput = document.getElementById('capaInput');
        if (capaInput) {
            capaInput.addEventListener('change', function(e) {
                const preview = document.getElementById('capaPreview');
                if (!preview) return;
                preview.innerHTML = '';

                const file = (e.target.files || [])[0];
                if (file && file.type && file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(ev) {
                        const img = document.createElement('img');
                        img.src = ev.target.result;
                        img.style.width = '100%';
                        img.style.maxHeight = '240px';
                        img.style.objectFit = 'cover';
                        img.style.borderRadius = '16px';
                        img.style.boxShadow = '0 14px 36px rgba(15, 23, 42, 0.14)';
                        preview.appendChild(img);
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
    </script>
</body>
</html>
HTML;

        exit;
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
                    // Se for URL externa, usar diretamente
                    if (is_string($fotoCapa) && preg_match('#^https?://#i', $fotoCapa)) {
                        $produto['imagem'] = $fotoCapa;
                        continue;
                    }
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
                    // Se for URL externa, usar diretamente
                    if (is_string($foto['nome_arquivo']) && preg_match('#^https?://#i', (string) $foto['nome_arquivo'])) {
                        $produto['imagem'] = (string) $foto['nome_arquivo'];
                        continue;
                    }
                    $filePath = $this->resolveUploadsPublicPath($foto['nome_arquivo']);
                    if ($filePath) {
                        $produto['imagem'] = Url::absolute($foto['nome_arquivo']);
                    }
                }
            }

            unset($produto);

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
        .product-card { transition: none; }
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
                    <div class="d-flex gap-2">
                        <a href="/admin/produtos/cadastro-rapido" class="btn btn-outline-primary"><i class="fas fa-bolt"></i> Cadastro rápido</a>
                        <a href="/admin/produtos/novo" class="btn btn-primary"><i class="fas fa-plus"></i> Novo</a>
                    </div>
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

        ob_start();

        echo <<<HTML
                <div class="pt-3">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center mb-4 border-bottom" style="padding-bottom: 12px;">
                    <h1 class="h2">Novo Produto</h1>
                    <a href="/admin/produtos" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
                </div>

                <div class="alert alert-info">
                    Para cadastrar variações (cor/tamanho etc.), primeiro salve o produto. Depois entre em <strong>Editar</strong> e use a seção <strong>Variações</strong>.
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
                echo '<option value="' . htmlspecialchars($l['id']) . '">' . htmlspecialchars($l['nome']) . '</option>';
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
HTML;

        echo '</div>';

        $content = ob_get_clean();
        $title = 'Novo Produto - Braziliana Shop Admin';
        include __DIR__ . '/../Views/layouts/admin.php';
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
            $lojaParam = $request->getParam('loja');
            $lojaId = is_numeric($lojaParam) ? (int) $lojaParam : 0;
            if (in_array('loja_id', $cols, true) && $lojaId > 0) {
                $data['loja_id'] = $lojaId;
            }
            if (in_array('loja', $cols, true)) {
                // manter compatibilidade: salvar slug também quando possível
                $lojaSlug = null;
                if ($lojaId > 0) {
                    try {
                        $stmtT = $pdo->query("SHOW TABLES LIKE 'lojas'");
                        if ($stmtT && $stmtT->fetchColumn()) {
                            $stmtL = $pdo->prepare('SELECT slug FROM lojas WHERE id = :id LIMIT 1');
                            $stmtL->execute([':id' => $lojaId]);
                            $lojaSlug = $stmtL->fetchColumn();
                        }
                    } catch (\Exception $e) {
                    }
                }

                if ($lojaSlug !== null && $lojaSlug !== false && (string) $lojaSlug !== '') {
                    $data['loja'] = (string) $lojaSlug;
                } else {
                    $data['loja'] = $lojaParam;
                }
            }
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

            $variacoesSchemaOk = $this->tableExists($pdo, 'variacao_tipos')
                && $this->tableExists($pdo, 'variacao_opcoes')
                && $this->tableExists($pdo, 'produto_atributos')
                && $this->tableExists($pdo, 'produto_variacoes')
                && $this->tableExists($pdo, 'produto_variacao_itens');

            $variacaoTipos = $variacoesSchemaOk ? $this->getVariacaoTipos($pdo) : [];
            $variacaoOpcoesPorTipo = $variacoesSchemaOk ? $this->getVariacaoOpcoesPorTipo($pdo) : [];
            $produtoTipoIds = $variacoesSchemaOk ? $this->getProdutoAtributos($pdo, (int) $id) : [];
            $produtoOpcoesPorTipo = $variacoesSchemaOk ? $this->getProdutoOpcoesUsadasPorTipo($pdo, (int) $id) : [];
            $produtoVariacoes = $variacoesSchemaOk ? $this->getProdutoVariacoesComDescricao($pdo, (int) $id) : [];

            $fotosPorVariacao = [];
            if ($variacoesSchemaOk && $this->tableExists($pdo, 'produto_variacao_fotos') && !empty($produtoVariacoes)) {
                $varIds = [];
                foreach ($produtoVariacoes as $vv) {
                    $vId = (int) ($vv['id'] ?? 0);
                    if ($vId > 0) $varIds[] = $vId;
                }
                $varIds = array_values(array_unique($varIds));
                if (!empty($varIds)) {
                    $in = implode(',', array_fill(0, count($varIds), '?'));
                    $sql = 'SELECT * FROM produto_variacao_fotos WHERE produto_variacao_id IN (' . $in . ') ORDER BY produto_variacao_id ASC, ordem ASC, id ASC';
                    $st = $pdo->prepare($sql);
                    $st->execute($varIds);
                    $rows = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                    foreach ($rows as $r) {
                        $pvId = (int) ($r['produto_variacao_id'] ?? 0);
                        if ($pvId <= 0) continue;
                        if (!isset($fotosPorVariacao[$pvId])) $fotosPorVariacao[$pvId] = [];
                        $webPath = $this->normalizeUploadsWebPath((string) ($r['nome_arquivo'] ?? ''));
                        $filePath = !empty($webPath) ? $this->resolveUploadsPublicPath($webPath) : null;
                        $url = (!empty($webPath) && !empty($filePath)) ? Url::absolute($webPath) : Url::absolute('/uploads/produtos/placeholder.jpg');
                        $fotosPorVariacao[$pvId][] = [
                            'id' => (int) ($r['id'] ?? 0),
                            'nome_arquivo' => $webPath,
                            'url' => $url,
                            'ordem' => (int) ($r['ordem'] ?? 0),
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            echo '<div class="alert alert-danger">Erro: ' . $e->getMessage() . '</div>';
            exit;
        }

        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        if ($request->getParam('debug_loja')) {
            echo '<pre style="padding:12px;background:#fff;border:1px solid #ddd;max-width:100%;overflow:auto">';
            var_dump([
                'produto_id' => $id,
                'produto_loja' => $produto['loja'] ?? null,
                'lojas_0' => $lojas[0] ?? null,
            ]);
            echo '</pre>';
        }

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
            $fotoCapaWeb = $this->normalizeUploadsWebPath((string) $fotoCapa);
            if (!empty($fotoCapaWeb)) {
                $fotoCapaPath = $this->resolveUploadsPublicPath((string) $fotoCapaWeb);
                if (!empty($fotoCapaPath)) {
                    $fotoCapaUrl = Url::absolute((string) $fotoCapaWeb);
                }
            }
        }

        $debugSuffix = $request->getParam('debug_loja') ? '?debug_loja=1' : '';

        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Editar Produto</h1>
                    <a href="/admin/produtos" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
                </div>

                <form method="POST" action="/admin/produtos/atualizar/' . $id . $debugSuffix . '" enctype="multipart/form-data">
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
            $produtoLojaId = (int) ($produto['loja_id'] ?? 0);
            $produtoLoja = trim((string) ($produto['loja'] ?? ''));
            $produtoLojaNorm = strtolower($produtoLoja);
            foreach ($lojas as $l) {
                $lojaSlug = (string) ($l['slug'] ?? '');
                $lojaId = (string) ($l['id'] ?? '');
                $lojaNome = (string) ($l['nome'] ?? '');

                $lojaSlugNorm = strtolower(trim($lojaSlug));
                $lojaIdNorm = strtolower(trim($lojaId));
                $lojaNomeNorm = strtolower(trim($lojaNome));

                $selected = ($produtoLojaId > 0 && (string) $produtoLojaId === (string) $l['id']) ? 'selected' : '';
                if ($selected === '') {
                    $selected = ($produtoLojaNorm !== '' && (
                        $produtoLojaNorm === $lojaSlugNorm ||
                        $produtoLojaNorm === $lojaIdNorm ||
                        $produtoLojaNorm === $lojaNomeNorm
                    )) ? 'selected' : '';
                }
                echo '<option value="' . htmlspecialchars($l['id']) . '" ' . $selected . '>' . htmlspecialchars($l['nome']) . '</option>';
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
                        <img id="capaImg" src="' . $fotoCapaUrl . '" alt="Capa" class="img-thumbnail" style="width: 100%; height: 140px; object-fit: cover;">
                    </a>
                </div>';
        } else {
            echo '<div class="col-6 col-md-3 mb-2">
                    <img id="capaImg" src="' . Url::absolute('/uploads/produtos/placeholder.jpg') . '" alt="Sem capa" class="img-thumbnail" style="width: 100%; height: 140px; object-fit: cover;">
                </div>';
        }

        echo '</div>
                                        <div class="d-flex gap-2 align-items-center">
                                            <input id="capaFile" type="file" class="form-control" name="capa" accept="image/*">
                                            <button type="button" id="btnUploadCapa" class="btn btn-outline-primary" data-url="/admin/produtos/upload-capa/' . (int) $id . '">Enviar capa</button>
                                            <button type="submit" class="btn btn-outline-danger" formaction="/admin/produtos/remover-capa/' . (int) $id . '" formmethod="POST" formnovalidate ' . (!empty($fotoCapaUrl) ? '' : 'disabled') . '>Remover capa</button>
                                        </div>
                                        <small class="text-muted">A foto de capa é usada como imagem principal do produto</small>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Galeria de Fotos</label>
                                        <div id="galeriaRow" class="row mb-3">';

        foreach ($fotos as $foto) {
            $webPath = $this->normalizeUploadsWebPath((string) ($foto['nome_arquivo'] ?? ''));
            $filePath = !empty($webPath) ? $this->resolveUploadsPublicPath($webPath) : null;
            $imageUrl = (!empty($webPath) && !empty($filePath)) ? Url::absolute($webPath) : Url::absolute('/uploads/produtos/placeholder.jpg');
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
                                            <input id="galeriaFiles" type="file" class="form-control" name="imagens[]" multiple accept="image/*">
                                            <button type="button" id="btnUploadGaleria" class="btn btn-outline-primary" data-url="/admin/produtos/upload-galeria/' . (int) $id . '">Enviar fotos</button>
                                            <button type="submit" class="btn btn-outline-primary" formaction="/admin/produtos/galeria/ordem/' . (int) $id . '" formmethod="POST" formnovalidate>Salvar ordem</button>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Variações</label>';

        if (empty($variacoesSchemaOk)) {
            echo '<div class="alert alert-warning mb-0">Para habilitar variações, rode a migration <strong>061_create_produto_variacoes_schema.sql</strong> no banco.</div>';
        } else {
            echo '<div class="alert alert-info">Use atributos e opções para gerar variações simples ou compostas. Você pode gerar todas, apagar e também criar variações individuais.</div>';

            echo '<div class="card mb-3">
                    <div class="card-body">
                        <form method="POST" action="/admin/produtos/' . (int) $id . '/variacoes/atributos">
                            <div class="row g-2">';

            foreach ($variacaoTipos as $t) {
                $tid = (int) ($t['id'] ?? 0);
                $tnome = (string) ($t['nome'] ?? '');
                $checked = in_array($tid, $produtoTipoIds, true) ? 'checked' : '';

                echo '<div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="tipo_ids[]" value="' . $tid . '" id="tipo_' . $tid . '" ' . $checked . '>
                            <label class="form-check-label" for="tipo_' . $tid . '">' . htmlspecialchars($tnome, ENT_QUOTES, 'UTF-8') . '</label>
                        </div>
                      </div>';

                $opcoes = $variacaoOpcoesPorTipo[$tid] ?? [];
                if (!empty($opcoes)) {
                    echo '<div class="col-12 ms-4">
                            <div class="row g-2">';
                    foreach ($opcoes as $o) {
                        $oid = (int) ($o['id'] ?? 0);
                        $ovalor = (string) ($o['valor'] ?? '');
                        $oChecked = (!empty($produtoOpcoesPorTipo[$tid]) && in_array($oid, $produtoOpcoesPorTipo[$tid], true)) ? 'checked' : '';
                        echo '<div class="col-6 col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="opcoes[' . $tid . '][]" value="' . $oid . '" id="opt_' . $tid . '_' . $oid . '" ' . $oChecked . '>
                                    <label class="form-check-label" for="opt_' . $tid . '_' . $oid . '">' . htmlspecialchars($ovalor, ENT_QUOTES, 'UTF-8') . '</label>
                                </div>
                              </div>';
                    }
                    echo '      </div>
                          </div>';
                }
            }

            echo '          <div class="col-12">
                                <button type="submit" class="btn btn-outline-primary w-100">Salvar atributos/opções</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>';

            echo '<div class="d-flex flex-wrap gap-2 mb-3">
                    <form method="POST" action="/admin/produtos/' . (int) $id . '/variacoes/gerar" onsubmit="return confirm(\'Gerar variações com base nas opções selecionadas?\')">
                        <input type="hidden" name="replace" value="0">
                        <button type="submit" class="btn btn-primary"><i class="fas fa-cogs"></i> Gerar todas</button>
                    </form>
                    <form method="POST" action="/admin/produtos/' . (int) $id . '/variacoes/gerar" onsubmit="return confirm(\'Isso vai apagar e recriar as variações. Continuar?\')">
                        <input type="hidden" name="replace" value="1">
                        <button type="submit" class="btn btn-outline-primary"><i class="fas fa-redo"></i> Apagar e gerar</button>
                    </form>
                    <form method="POST" action="/admin/produtos/' . (int) $id . '/variacoes/apagar" onsubmit="return confirm(\'Apagar todas as variações deste produto?\')">
                        <button type="submit" class="btn btn-outline-danger"><i class="fas fa-trash"></i> Apagar todas</button>
                    </form>
                  </div>';

            echo '<div class="card">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <strong>Variações cadastradas</strong>
                        <form class="d-flex gap-2" method="POST" action="/admin/produtos/' . (int) $id . '/variacoes/criar">
                            <input type="number" name="stock" class="form-control form-control-sm" style="width:120px" placeholder="Estoque" value="0" min="0">
                            <input type="text" name="price_override" class="form-control form-control-sm" style="width:160px" placeholder="Preço variação">
                            <button class="btn btn-sm btn-outline-primary" type="submit"><i class="fas fa-plus"></i> Criar individual</button>
                        </form>
                    </div>
                    <div class="card-body">';

            if (empty($produtoVariacoes)) {
                echo '<div class="text-muted">Nenhuma variação criada ainda.</div>';
            } else {
                echo '<div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th>Variação</th>
                                    <th>Preço</th>
                                    <th>Estoque</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>';
                foreach ($produtoVariacoes as $v) {
                    $vId = (int) ($v['id'] ?? 0);
                    $desc = (string) ($v['descricao'] ?? '');
                    $priceOv = $v['price_override'] ?? null;
                    $stockV = (int) ($v['stock'] ?? 0);
                    $ativoV = (int) ($v['ativo'] ?? 0);
                    echo '<tr>
                            <td>
                                <div class="fw-semibold">#' . $vId . '</div>
                                <div class="text-muted small">' . htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') . '</div>
                            </td>
                            <td>' . htmlspecialchars(($priceOv === null || $priceOv === '' ? '-' : (string) $priceOv), ENT_QUOTES, 'UTF-8') . '</td>
                            <td>' . $stockV . '</td>
                            <td>' . ($ativoV ? '<span class="badge bg-success">Ativa</span>' : '<span class="badge bg-secondary">Inativa</span>') . '</td>
                          </tr>';
                }
                echo '      </tbody>
                        </table>
                    </div>';
            }

            echo '      </div>
                </div>';

            echo '<div class="card mt-3">
                    <div class="card-header bg-white">
                        <strong>Galeria por variação (SKU)</strong>
                        <div class="text-muted small">Cada variação pode ter sua própria galeria. Essas fotos serão exibidas na página do produto quando o cliente selecionar a variação.</div>
                    </div>
                    <div class="card-body">';

            if (empty($produtoVariacoes)) {
                echo '<div class="text-muted">Nenhuma variação criada ainda.</div>';
            } else {
                echo '<div class="accordion" id="accVarFotos">';
                foreach ($produtoVariacoes as $idx => $v) {
                    $vId = (int) ($v['id'] ?? 0);
                    if ($vId <= 0) continue;
                    $desc = (string) ($v['descricao'] ?? '');
                    $headingId = 'varFotosHeading_' . $vId;
                    $collapseId = 'varFotosCollapse_' . $vId;

                    echo '<div class="accordion-item">
                            <h2 class="accordion-header" id="' . $headingId . '">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#' . $collapseId . '" aria-expanded="false" aria-controls="' . $collapseId . '">
                                    <span class="fw-semibold">#' . $vId . '</span>
                                    <span class="text-muted ms-2 small">' . htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') . '</span>
                                </button>
                            </h2>
                            <div id="' . $collapseId . '" class="accordion-collapse collapse" aria-labelledby="' . $headingId . '" data-bs-parent="#accVarFotos">
                                <div class="accordion-body">';

                    echo '<div id="varGaleriaRow_' . $vId . '" class="row mb-3">';
                    $fotosV = $fotosPorVariacao[$vId] ?? [];
                    foreach ($fotosV as $foto) {
                        $fotoId = (int) ($foto['id'] ?? 0);
                        $url = (string) ($foto['url'] ?? '');
                        $ordem = (int) ($foto['ordem'] ?? 0);
                        echo '<div class="col-6 col-md-2 mb-2">
                                <a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" target="_blank">
                                    <img src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" alt="Foto" class="img-thumbnail" style="width: 100%; height: 100px; object-fit: cover;">
                                </a>
                                <div class="mt-2">
                                    <input type="number" class="form-control form-control-sm" name="ordens_variacao[' . $fotoId . ']" value="' . $ordem . '" min="0">
                                </div>
                                <div class="mt-2">
                                    <button type="submit" class="btn btn-sm btn-outline-danger w-100" formaction="/admin/produtos/variacoes/fotos/remover/' . $fotoId . '" formmethod="POST" formnovalidate onclick="return confirm(\'Remover esta foto da variação?\')">Remover</button>
                                </div>
                              </div>';
                    }
                    echo '</div>';

                    echo '<div class="d-flex gap-2 align-items-center flex-wrap">
                            <input id="varGaleriaFiles_' . $vId . '" type="file" class="form-control" style="max-width: 520px;" multiple accept="image/*">
                            <button type="button" class="btn btn-outline-primary btnUploadVarGaleria" data-var-id="' . $vId . '" data-url="/admin/produtos/variacoes/' . $vId . '/fotos/upload">Enviar fotos</button>
                            <button type="submit" class="btn btn-outline-primary" formaction="/admin/produtos/variacoes/' . $vId . '/fotos/ordem" formmethod="POST" formnovalidate>Salvar ordem</button>
                          </div>';

                    echo '            </div>
                            </div>
                        </div>';
                }
                echo '</div>';
            }

            echo '    </div>
                </div>';
        }

        echo '                        </div>
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

        echo <<<'HTMLSCRIPT'
<script>
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

        (function() {
            const btnCapa = document.getElementById('btnUploadCapa');
            const capaFile = document.getElementById('capaFile');
            const capaImg = document.getElementById('capaImg');

            const btnGaleria = document.getElementById('btnUploadGaleria');
            const galeriaFiles = document.getElementById('galeriaFiles');
            const galeriaRow = document.getElementById('galeriaRow');

            async function postFormData(url, formData) {
                const res = await fetch(url, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                });
                const text = await res.text();
                let data;
                try { data = JSON.parse(text); } catch (e) { data = { ok: false, message: text }; }
                if (!res.ok || !data || data.ok !== true) {
                    throw new Error((data && data.message) ? data.message : 'Falha no upload');
                }
                return data;
            }

            if (btnCapa && capaFile && capaImg) {
                btnCapa.addEventListener('click', async function() {
                    if (!capaFile.files || !capaFile.files[0]) return;
                    const url = btnCapa.getAttribute('data-url');
                    const fd = new FormData();
                    fd.append('capa', capaFile.files[0]);
                    btnCapa.disabled = true;
                    try {
                        const data = await postFormData(url, fd);
                        if (data && data.url) {
                            capaImg.src = data.url;
                            const parentLink = capaImg.closest('a');
                            if (parentLink) parentLink.href = data.url;
                        }
                        capaFile.value = '';
                    } catch (e) {
                        alert(e.message || 'Erro ao enviar capa');
                    } finally {
                        btnCapa.disabled = false;
                    }
                });
            }

            if (btnGaleria && galeriaFiles && galeriaRow) {
                btnGaleria.addEventListener('click', async function() {
                    if (!galeriaFiles.files || galeriaFiles.files.length === 0) return;
                    const url = btnGaleria.getAttribute('data-url');
                    const fd = new FormData();
                    for (const f of galeriaFiles.files) fd.append('imagens[]', f);
                    btnGaleria.disabled = true;
                    try {
                        const data = await postFormData(url, fd);
                        const fotos = (data && data.fotos) ? data.fotos : [];
                        fotos.forEach(function(item) {
                            const col = document.createElement('div');
                            col.className = 'col-6 col-md-2 mb-2';
                            const fotoId = item && item.id ? item.id : 0;
                            const url = item && item.url ? item.url : '';
                            col.innerHTML =
                                '<a href="' + url + '" target="_blank">' +
                                '<img src="' + url + '" alt="Foto" class="img-thumbnail" style="width: 100%; height: 100px; object-fit: cover;">' +
                                '</a>' +
                                '<div class="mt-2">' +
                                '<input type="number" class="form-control form-control-sm" name="ordens[' + fotoId + ']" value="0" min="0">' +
                                '</div>' +
                                '<div class="mt-2">' +
                                '<button type="submit" class="btn btn-sm btn-outline-danger w-100" formaction="/admin/produtos/remover-foto/' + fotoId + '" formmethod="POST" formnovalidate onclick="return confirm(\'Remover esta foto?\')">Remover</button>' +
                                '</div>';
                            galeriaRow.appendChild(col);
                        });
                        galeriaFiles.value = '';
                    } catch (e) {
                        alert(e.message || 'Erro ao enviar fotos');
                    } finally {
                        btnGaleria.disabled = false;
                    }
                });
            }

            // Upload de galeria por variação
            document.querySelectorAll('.btnUploadVarGaleria').forEach((btn) => {
                btn.addEventListener('click', async function() {
                    const varId = btn.getAttribute('data-var-id');
                    const input = document.getElementById('varGaleriaFiles_' + varId);
                    const row = document.getElementById('varGaleriaRow_' + varId);
                    if (!varId || !input || !row) return;
                    if (!input.files || input.files.length === 0) return;
                    const url = btn.getAttribute('data-url');
                    const fd = new FormData();
                    for (const f of input.files) fd.append('imagens[]', f);
                    btn.disabled = true;
                    try {
                        const data = await postFormData(url, fd);
                        const fotos = (data && data.fotos) ? data.fotos : [];
                        fotos.forEach(function(item) {
                            const col = document.createElement('div');
                            col.className = 'col-6 col-md-2 mb-2';
                            const fotoId = item && item.id ? item.id : 0;
                            const url = item && item.url ? item.url : '';
                            col.innerHTML =
                                '<a href="' + url + '" target="_blank">' +
                                '<img src="' + url + '" alt="Foto" class="img-thumbnail" style="width: 100%; height: 100px; object-fit: cover;">' +
                                '</a>' +
                                '<div class="mt-2">' +
                                '<input type="number" class="form-control form-control-sm" name="ordens_variacao[' + fotoId + ']" value="0" min="0">' +
                                '</div>' +
                                '<div class="mt-2">' +
                                '<button type="submit" class="btn btn-sm btn-outline-danger w-100" formaction="/admin/produtos/variacoes/fotos/remover/' + fotoId + '" formmethod="POST" formnovalidate onclick="return confirm(\'Remover esta foto da variação?\')">Remover</button>' +
                                '</div>';
                            row.appendChild(col);
                        });
                        input.value = '';
                    } catch (e) {
                        alert(e.message || 'Erro ao enviar fotos da variação');
                    } finally {
                        btn.disabled = false;
                    }
                });
            });
        })();
</script>
HTMLSCRIPT;

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
            $cols = $this->getTableColumns($pdo, 'produtos');
            
            $price = $this->parseMoneyToDb($request->getParam('price'));
            $costPrice = $this->parseMoneyToDb($request->getParam('cost_price'));
            $salePrice = $this->parseMoneyToDb($request->getParam('sale_price'));
            
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
            
            $lojaParam = $request->getParam('loja');
            $lojaId = is_numeric($lojaParam) ? (int) $lojaParam : 0;
            $lojaSlug = $lojaParam;
            if ($lojaId > 0) {
                try {
                    $stmtT = $pdo->query("SHOW TABLES LIKE 'lojas'");
                    if ($stmtT && $stmtT->fetchColumn()) {
                        $stmtL = $pdo->prepare('SELECT slug FROM lojas WHERE id = :id LIMIT 1');
                        $stmtL->execute([':id' => $lojaId]);
                        $tmpSlug = $stmtL->fetchColumn();
                        if ($tmpSlug !== false && (string) $tmpSlug !== '') {
                            $lojaSlug = (string) $tmpSlug;
                        }
                    }
                } catch (\Exception $e) {
                }
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
                $lojaSlug,
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

            if (in_array('loja_id', $cols, true) && $lojaId > 0) {
                $stmtLojaId = $pdo->prepare('UPDATE produtos SET loja_id = ? WHERE id = ?');
                $stmtLojaId->execute([$lojaId, $id]);
            }

            $rowsUpdated = $stmt->rowCount();
            $stmtWarnings = $pdo->query('SHOW WARNINGS');
            $warnings = $stmtWarnings ? $stmtWarnings->fetchAll(\PDO::FETCH_ASSOC) : [];

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

            if ($request->getParam('debug_loja')) {
                $stmtCol = $pdo->query("SHOW COLUMNS FROM produtos LIKE 'loja'");
                $colInfo = $stmtCol ? $stmtCol->fetch(\PDO::FETCH_ASSOC) : null;

                $stmtCheck = $pdo->prepare('SELECT loja FROM produtos WHERE id = ?');
                $stmtCheck->execute([$id]);
                $dbRow = $stmtCheck->fetch(\PDO::FETCH_ASSOC);

                echo '<pre style="padding:12px;background:#fff;border:1px solid #ddd;max-width:100%;overflow:auto">';
                var_dump([
                    'produto_id' => (int) $id,
                    'loja_post' => $request->getParam('loja'),
                    'update_rowCount' => (int) $rowsUpdated,
                    'loja_db' => $dbRow['loja'] ?? null,
                    'loja_column' => $colInfo,
                    'sql_warnings' => $warnings,
                ]);
                echo '</pre>';
                exit;
            }

            header('Location: /admin/produtos?success=2');
            exit;
            
        } catch (\Exception $e) {
            if (isset($pdo)) $pdo->rollBack();
            echo '<div class="alert alert-danger">Erro: ' . $e->getMessage() . '</div>';
            exit;
        }
    }

    public function salvarAtributosVariacoes(Request $request, $id = null) {
        $produtoId = (int) ($id ?? $request->getParam('id'));
        if ($produtoId <= 0) {
            header('Location: /admin/produtos');
            exit;
        }

        $tipoIds = $request->getParam('tipo_ids', []);
        if (!is_array($tipoIds)) $tipoIds = [];
        $tipoIds = array_values(array_unique(array_map('intval', $tipoIds)));

        $opcoes = $request->getParam('opcoes', []);
        if (!is_array($opcoes)) $opcoes = [];

        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            if (!$this->tableExists($pdo, 'produto_atributos')) {
                $_SESSION['message'] = 'Tabelas de variações não encontradas. Rode a migration 061.';
                $_SESSION['message_type'] = 'warning';
                header('Location: /admin/produtos/editar/' . $produtoId);
                exit;
            }

            $pdo->beginTransaction();
            $stmtDel = $pdo->prepare('DELETE FROM produto_atributos WHERE produto_id = :pid');
            $stmtDel->execute([':pid' => $produtoId]);

            if (!empty($tipoIds)) {
                $stmtIns = $pdo->prepare('INSERT INTO produto_atributos (produto_id, tipo_id, created_at, updated_at) VALUES (:pid, :tid, NOW(), NOW())');
                foreach ($tipoIds as $tid) {
                    if ($tid <= 0) continue;
                    $stmtIns->execute([':pid' => $produtoId, ':tid' => $tid]);
                }
            }

            $pdo->commit();
            $_SESSION['message'] = 'Atributos/Opções salvos.';
            $_SESSION['message_type'] = 'success';
        } catch (\Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['message'] = 'Erro ao salvar atributos.';
            $_SESSION['message_type'] = 'danger';
        }

        header('Location: /admin/produtos/editar/' . $produtoId);
        exit;
    }

    public function apagarVariacoes(Request $request, $id = null) {
        $produtoId = (int) ($id ?? $request->getParam('id'));
        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            $pdo->beginTransaction();

            if (!$this->tableExists($pdo, 'produto_variacoes')) {
                throw new \Exception('Tabelas de variações não encontradas');
            }

            $stmtVarIds = $pdo->prepare('SELECT id FROM produto_variacoes WHERE produto_id = :pid');
            $stmtVarIds->execute([':pid' => $produtoId]);
            $ids = array_map('intval', $stmtVarIds->fetchAll(\PDO::FETCH_COLUMN) ?: []);
            if (!empty($ids)) {
                $in = implode(',', array_fill(0, count($ids), '?'));
                if ($this->tableExists($pdo, 'produto_variacao_fotos')) {
                    $pdo->prepare('DELETE FROM produto_variacao_fotos WHERE produto_variacao_id IN (' . $in . ')')->execute($ids);
                }
                if ($this->tableExists($pdo, 'produto_variacao_itens')) {
                    $pdo->prepare('DELETE FROM produto_variacao_itens WHERE produto_variacao_id IN (' . $in . ')')->execute($ids);
                }
            }

            $stmtDel = $pdo->prepare('DELETE FROM produto_variacoes WHERE produto_id = :pid');
            $stmtDel->execute([':pid' => $produtoId]);

            $pdo->commit();
            $_SESSION['message'] = 'Variações removidas.';
            $_SESSION['message_type'] = 'success';
        } catch (\Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['message'] = 'Erro ao apagar variações.';
            $_SESSION['message_type'] = 'danger';
        }

        header('Location: /admin/produtos/editar/' . $produtoId);
        exit;
    }

    public function gerarVariacoes(Request $request, $id = null) {
        $produtoId = (int) ($id ?? $request->getParam('id'));
        $replace = (int) $request->getParam('replace', 0) === 1;

        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            if (!$this->tableExists($pdo, 'produto_variacoes') || !$this->tableExists($pdo, 'produto_variacao_itens')) {
                throw new \Exception('Tabelas de variações não encontradas');
            }

            $opcoesPorTipo = $this->getProdutoOpcoesUsadasPorTipo($pdo, $produtoId);
            $tipoIds = array_keys($opcoesPorTipo);

            $tiposValidos = [];
            foreach ($tipoIds as $tid) {
                $list = array_values(array_unique(array_map('intval', $opcoesPorTipo[$tid] ?? [])));
                if (!empty($list)) {
                    $tiposValidos[(int) $tid] = $list;
                }
            }

            if (empty($tiposValidos)) {
                $_SESSION['message'] = 'Selecione opções nos atributos antes de gerar variações.';
                $_SESSION['message_type'] = 'warning';
                header('Location: /admin/produtos/editar/' . $produtoId);
                exit;
            }

            $pdo->beginTransaction();

            if ($replace) {
                $stmtVarIds = $pdo->prepare('SELECT id FROM produto_variacoes WHERE produto_id = :pid');
                $stmtVarIds->execute([':pid' => $produtoId]);
                $ids = array_map('intval', $stmtVarIds->fetchAll(\PDO::FETCH_COLUMN) ?: []);
                if (!empty($ids)) {
                    $in = implode(',', array_fill(0, count($ids), '?'));
                    if ($this->tableExists($pdo, 'produto_variacao_fotos')) {
                        $pdo->prepare('DELETE FROM produto_variacao_fotos WHERE produto_variacao_id IN (' . $in . ')')->execute($ids);
                    }
                    $pdo->prepare('DELETE FROM produto_variacao_itens WHERE produto_variacao_id IN (' . $in . ')')->execute($ids);
                }
                $pdo->prepare('DELETE FROM produto_variacoes WHERE produto_id = :pid')->execute([':pid' => $produtoId]);
            }

            $existingSignatures = $this->getProdutoVariacoesSignatures($pdo, $produtoId);

            $combinacoes = [[]];
            foreach ($tiposValidos as $tid => $opcoesIds) {
                $new = [];
                foreach ($combinacoes as $c) {
                    foreach ($opcoesIds as $oid) {
                        $tmp = $c;
                        $tmp[(int) $tid] = (int) $oid;
                        $new[] = $tmp;
                    }
                }
                $combinacoes = $new;
            }

            $stmtInsVar = $pdo->prepare('INSERT INTO produto_variacoes (produto_id, sku, price_override, stock, ativo, created_at, updated_at) VALUES (:pid, NULL, NULL, :stock, 1, NOW(), NOW())');
            $stmtInsItem = $pdo->prepare('INSERT INTO produto_variacao_itens (produto_variacao_id, tipo_id, opcao_id, created_at, updated_at) VALUES (:pvi, :tid, :oid, NOW(), NOW())');

            $created = 0;
            foreach ($combinacoes as $comb) {
                ksort($comb);
                $parts = [];
                foreach ($comb as $tid => $oid) {
                    $parts[] = $tid . ':' . $oid;
                }
                $sig = implode('|', $parts);
                if (isset($existingSignatures[$sig])) {
                    continue;
                }

                $stmtInsVar->execute([':pid' => $produtoId, ':stock' => 0]);
                $varId = (int) $pdo->lastInsertId();
                foreach ($comb as $tid => $oid) {
                    $stmtInsItem->execute([':pvi' => $varId, ':tid' => (int) $tid, ':oid' => (int) $oid]);
                }
                $created++;
            }

            $pdo->commit();
            $_SESSION['message'] = 'Variações geradas: ' . (int) $created;
            $_SESSION['message_type'] = 'success';
        } catch (\Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['message'] = 'Erro ao gerar variações.';
            $_SESSION['message_type'] = 'danger';
        }

        header('Location: /admin/produtos/editar/' . $produtoId);
        exit;
    }

    public function criarVariacaoIndividual(Request $request, $id = null) {
        $produtoId = (int) ($id ?? $request->getParam('id'));
        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            if (!$this->tableExists($pdo, 'produto_variacoes')) {
                throw new \Exception('Tabelas de variações não encontradas');
            }

            $stock = (int) $request->getParam('stock', 0);
            $priceOverrideRaw = trim((string) $request->getParam('price_override', ''));
            $priceOverride = $priceOverrideRaw !== '' ? $this->parseMoneyToDb($priceOverrideRaw) : null;

            $stmt = $pdo->prepare('INSERT INTO produto_variacoes (produto_id, sku, price_override, stock, ativo, created_at, updated_at) VALUES (:pid, NULL, :po, :st, 1, NOW(), NOW())');
            $stmt->bindValue(':pid', $produtoId, \PDO::PARAM_INT);
            if ($priceOverride === null || $priceOverride === '') {
                $stmt->bindValue(':po', null, \PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':po', $priceOverride);
            }
            $stmt->bindValue(':st', $stock, \PDO::PARAM_INT);
            $stmt->execute();

            $_SESSION['message'] = 'Variação criada.';
            $_SESSION['message_type'] = 'success';
        } catch (\Exception $e) {
            $_SESSION['message'] = 'Erro ao criar variação.';
            $_SESSION['message_type'] = 'danger';
        }

        header('Location: /admin/produtos/editar/' . $produtoId);
        exit;
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

    private function getVariacaoTipos(\PDO $pdo): array {
        try {
            $stmt = $pdo->query('SELECT * FROM variacao_tipos WHERE ativo = 1 ORDER BY nome ASC');
            return $stmt ? ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getVariacaoOpcoesPorTipo(\PDO $pdo): array {
        $map = [];
        try {
            $stmt = $pdo->query('SELECT * FROM variacao_opcoes WHERE ativo = 1 ORDER BY tipo_id ASC, ordem ASC, valor ASC');
            $rows = $stmt ? ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];
            foreach ($rows as $r) {
                $tid = (int) ($r['tipo_id'] ?? 0);
                if ($tid <= 0) continue;
                if (!isset($map[$tid])) $map[$tid] = [];
                $map[$tid][] = $r;
            }
        } catch (\Exception $e) {
            $map = [];
        }
        return $map;
    }

    private function getProdutoAtributos(\PDO $pdo, int $produtoId): array {
        try {
            $stmt = $pdo->prepare('SELECT tipo_id FROM produto_atributos WHERE produto_id = :pid');
            $stmt->execute([':pid' => $produtoId]);
            $ids = $stmt->fetchAll(\PDO::FETCH_COLUMN) ?: [];
            return array_values(array_unique(array_map('intval', $ids)));
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getProdutoOpcoesUsadasPorTipo(\PDO $pdo, int $produtoId): array {
        $map = [];
        try {
            $stmt = $pdo->prepare('
                SELECT pvi.tipo_id, pvi.opcao_id
                FROM produto_variacao_itens pvi
                INNER JOIN produto_variacoes pv ON pv.id = pvi.produto_variacao_id
                WHERE pv.produto_id = :pid
            ');
            $stmt->execute([':pid' => $produtoId]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as $r) {
                $tid = (int) ($r['tipo_id'] ?? 0);
                $oid = (int) ($r['opcao_id'] ?? 0);
                if ($tid <= 0 || $oid <= 0) continue;
                if (!isset($map[$tid])) $map[$tid] = [];
                $map[$tid][$oid] = true;
            }
            foreach ($map as $tid => $set) {
                $map[$tid] = array_map('intval', array_keys($set));
            }
        } catch (\Exception $e) {
            $map = [];
        }
        return $map;
    }

    private function getProdutoVariacoesSignatures(\PDO $pdo, int $produtoId): array {
        $sigs = [];
        try {
            $stmt = $pdo->prepare('
                SELECT pv.id AS variacao_id, pvi.tipo_id, pvi.opcao_id
                FROM produto_variacoes pv
                LEFT JOIN produto_variacao_itens pvi ON pvi.produto_variacao_id = pv.id
                WHERE pv.produto_id = :pid
                ORDER BY pv.id ASC
            ');
            $stmt->execute([':pid' => $produtoId]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $tmp = [];
            foreach ($rows as $r) {
                $vid = (int) ($r['variacao_id'] ?? 0);
                $tid = (int) ($r['tipo_id'] ?? 0);
                $oid = (int) ($r['opcao_id'] ?? 0);
                if ($vid <= 0) continue;
                if (!isset($tmp[$vid])) $tmp[$vid] = [];
                if ($tid > 0 && $oid > 0) {
                    $tmp[$vid][$tid] = $oid;
                }
            }
            foreach ($tmp as $vid => $comb) {
                ksort($comb);
                $parts = [];
                foreach ($comb as $tid => $oid) {
                    $parts[] = $tid . ':' . $oid;
                }
                $sig = implode('|', $parts);
                if ($sig !== '') {
                    $sigs[$sig] = true;
                }
            }
        } catch (\Exception $e) {
            $sigs = [];
        }
        return $sigs;
    }

    private function getProdutoVariacoesComDescricao(\PDO $pdo, int $produtoId): array {
        try {
            $stmt = $pdo->prepare('SELECT * FROM produto_variacoes WHERE produto_id = :pid ORDER BY id ASC');
            $stmt->execute([':pid' => $produtoId]);
            $vars = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            if (empty($vars)) return [];

            $ids = array_map(fn($v) => (int) ($v['id'] ?? 0), $vars);
            $ids = array_values(array_filter($ids, fn($v) => $v > 0));
            if (empty($ids)) return $vars;

            $in = implode(',', array_fill(0, count($ids), '?'));
            $sql = '
                SELECT pvi.produto_variacao_id, vt.nome AS tipo_nome, vo.valor AS opcao_valor
                FROM produto_variacao_itens pvi
                INNER JOIN variacao_tipos vt ON vt.id = pvi.tipo_id
                INNER JOIN variacao_opcoes vo ON vo.id = pvi.opcao_id
                WHERE pvi.produto_variacao_id IN (' . $in . ')
                ORDER BY pvi.produto_variacao_id ASC, vt.nome ASC, vo.valor ASC
            ';
            $st = $pdo->prepare($sql);
            $st->execute($ids);
            $rows = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            $map = [];
            foreach ($rows as $r) {
                $vid = (int) ($r['produto_variacao_id'] ?? 0);
                $tn = (string) ($r['tipo_nome'] ?? '');
                $ov = (string) ($r['opcao_valor'] ?? '');
                if ($vid <= 0) continue;
                if (!isset($map[$vid])) $map[$vid] = [];
                $map[$vid][] = $tn . '=' . $ov;
            }

            foreach ($vars as &$v) {
                $vid = (int) ($v['id'] ?? 0);
                $desc = '';
                if ($vid > 0 && !empty($map[$vid])) {
                    $desc = implode(' / ', $map[$vid]);
                }
                $v['descricao'] = $desc;
            }
            unset($v);

            return $vars;
        } catch (\Exception $e) {
            return [];
        }
    }

    public function uploadCapa(Request $request, $id = null) {
        $id = (int) ($id ?? $request->getParam('id'));
        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            $pdo->beginTransaction();

            if (isset($_FILES['capa']) && !empty($_FILES['capa']['name']) && ($_FILES['capa']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $uploadDir = $this->getProdutoUploadsDir();
                $webDir = '/uploads/produtos/';
                $this->ensureDir($uploadDir);

                $name = $_FILES['capa']['name'];
                $fileName = preg_replace('/[^A-Za-z0-9\-_\.]/', '', $name);
                $fileName = time() . '_' . $fileName;
                $filePath = $uploadDir . $fileName;
                $webPath = $webDir . $fileName;

                if (!move_uploaded_file($_FILES['capa']['tmp_name'], $filePath)) {
                    throw new \Exception('Erro ao fazer upload da capa');
                }

                $stmtCover = $pdo->prepare('UPDATE produtos SET foto_principal = ? WHERE id = ?');
                $stmtCover->execute([$webPath, $id]);
            }

            $pdo->commit();
            $isAjax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') || (strpos((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false);
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'url' => isset($webPath) ? Url::absolute($webPath) : null]);
                exit;
            }

            header('Location: /admin/produtos/editar/' . $id);
            exit;
        } catch (\Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $isAjax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') || (strpos((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false);
            if ($isAjax) {
                http_response_code(400);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
                exit;
            }

            echo '<div class="alert alert-danger">Erro: ' . $e->getMessage() . '</div>';
            exit;
        }
    }

    public function uploadGaleria(Request $request, $id = null) {
        $id = (int) ($id ?? $request->getParam('id'));
        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            $pdo->beginTransaction();

            $inserted = [];

            if (isset($_FILES['imagens']) && !empty($_FILES['imagens']['name'][0])) {
                $uploadDir = $this->getProdutoUploadsDir();
                $webDir = '/uploads/produtos/';
                $this->ensureDir($uploadDir);

                foreach ($_FILES['imagens']['name'] as $key => $name) {
                    if (($_FILES['imagens']['error'][$key] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                        continue;
                    }

                    $fileName = preg_replace('/[^A-Za-z0-9\-_\.]/', '', (string) $name);
                    $fileName = time() . '_' . $fileName;
                    $filePath = $uploadDir . $fileName;
                    $webPath = $webDir . $fileName;

                    if (!move_uploaded_file($_FILES['imagens']['tmp_name'][$key], $filePath)) {
                        continue;
                    }

                    $stmt = $pdo->prepare('
                        INSERT INTO produto_fotos (produto_id, nome_arquivo, arquivo_original, principal, ordem)
                        VALUES (?, ?, ?, ?, ?)
                    ');
                    $stmt->execute([
                        $id,
                        $webPath,
                        $name,
                        0,
                        (int) $key
                    ]);

                    $insertId = (int) $pdo->lastInsertId();
                    $inserted[] = ['id' => $insertId, 'url' => Url::absolute($webPath)];
                }
            }

            $pdo->commit();
            $isAjax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') || (strpos((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false);
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'fotos' => $inserted]);
                exit;
            }

            header('Location: /admin/produtos/editar/' . $id);
            exit;
        } catch (\Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $isAjax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') || (strpos((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false);
            if ($isAjax) {
                http_response_code(400);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
                exit;
            }

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
        exit;
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
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? ('/admin/produtos/editar/' . $id)));
            exit;
        } catch (\Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
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

    private function parseMoneyToDb($value): string {
        $s = is_string($value) ? trim($value) : '';
        if ($s === '') {
            return '0';
        }

        $s = str_replace(['$', 'R$', ' '], '', $s);
        $hasComma = strpos($s, ',') !== false;
        $hasDot = strpos($s, '.') !== false;

        if ($hasComma && $hasDot) {
            // format like 15.000,00
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } elseif ($hasComma && !$hasDot) {
            // format like 15000,00
            $s = str_replace(',', '.', $s);
        } else {
            // format like 15000.00 or 15000
        }

        $s = preg_replace('/[^0-9.\-]/', '', $s);
        if ($s === '' || $s === '-' || $s === '.' || $s === '-.') {
            return '0';
        }

        return $s;
    }
}
