<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Url;
use App\Models\Produto;
use App\Models\ProdutoFoto;

class OutletController extends Controller {
    private $produtoModel;
    private $produtoFotoModel;

    public function __construct() {
        $this->produtoModel = new Produto();
        $this->produtoFotoModel = new ProdutoFoto();
    }

    private function getDirectPdo(): \PDO {
        $pdo = new \PDO('mysql:host=127.0.0.1;dbname=novobr', 'novobr', '33537095Ab12$');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        return $pdo;
    }

    public function index(Request $request) {
        $pdo = $this->getDirectPdo();

        // Verificar se a coluna outlet existe
        $cols = [];
        try {
            $st = $pdo->query('DESCRIBE produtos');
            $cols = $st ? $st->fetchAll(\PDO::FETCH_COLUMN) : [];
        } catch (\Exception $e) { $cols = []; }

        if (!in_array('outlet', $cols, true)) {
            // Se a coluna não existe ainda, mostrar vazio
            $produtos = [];
        } else {
            // Buscar produtos marcados como outlet
            $nameCol = in_array('name', $cols, true) ? 'name' : (in_array('nome', $cols, true) ? 'nome' : 'name');
            $priceCol = in_array('price', $cols, true) ? 'price' : (in_array('valor', $cols, true) ? 'valor' : 'price');
            $salePriceCol = in_array('sale_price', $cols, true) ? 'sale_price' : null;
            $stockCol = in_array('stock', $cols, true) ? 'stock' : (in_array('estoque', $cols, true) ? 'estoque' : 'stock');
            $featuredCol = in_array('featured', $cols, true) ? 'featured' : null;
            $fotoCol = in_array('foto_principal', $cols, true) ? 'foto_principal' : (in_array('image', $cols, true) ? 'image' : null);

            $select = ['id', $nameCol . ' AS name', $priceCol . ' AS price', $stockCol . ' AS stock'];
            if ($salePriceCol) $select[] = $salePriceCol . ' AS sale_price';
            if ($featuredCol) $select[] = $featuredCol . ' AS featured';
            if ($fotoCol) $select[] = $fotoCol . ' AS foto_principal';

            $sql = 'SELECT ' . implode(', ', $select) . ' FROM produtos WHERE outlet = 1 AND active = 1';
            if (in_array('oculto', $cols, true)) {
                $sql .= ' AND (oculto = 0 OR oculto IS NULL)';
            }
            $sql .= " AND LOWER(COALESCE(status,'')) != 'archived'";
            $sql .= ' ORDER BY id DESC';

            try {
                $st = $pdo->query($sql);
                $produtos = $st ? ($st->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];
            } catch (\Exception $e) {
                $produtos = [];
            }
        }

        // Resolver imagens
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

        $this->view('outlet/index', [
            'produtos' => $produtos,
            'title' => 'Braziliana Outlet'
        ]);
    }

    private function normalizeProdutoImagemPath(?string $path): ?string {
        if ($path === null || trim($path) === '') return null;
        $path = trim($path);
        if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) return $path;
        if ($path[0] !== '/') $path = '/' . $path;
        return $path;
    }

    private function produtoImagemExiste(?string $path): bool {
        if ($path === null || trim($path) === '') return false;
        if (strpos($path, 'http://') === 0 || strpos($path, 'https://') === 0) return true;
        $realPath = rtrim((string) realpath(__DIR__ . '/../../public'), '/\\') . $path;
        if (file_exists($realPath)) return true;
        $altPath = rtrim((string) realpath(__DIR__ . '/../..'), '/\\') . $path;
        return file_exists($altPath);
    }
}
