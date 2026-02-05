<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Url;
use App\Models\Produto;
use App\Models\ProdutoFoto;
use App\Services\AuthService;
use App\Services\PaymentService;

class ClubeController extends Controller {
    private AuthService $authService;
    private Produto $produtoModel;
    private ProdutoFoto $produtoFotoModel;
    private PaymentService $paymentService;

    public function __construct() {
        $this->authService = new AuthService();
        $this->produtoModel = new Produto();
        $this->produtoFotoModel = new ProdutoFoto();
        $this->paymentService = new PaymentService();
    }

    public function comoFunciona(Request $request) {
        $this->view('clube/como-funciona');
    }

    private function getUsdBrlRate(): float {
        $rate = 5.5;
        try {
            $db = \Config\Database::getConnection();

            try {
                $stmtRate = $db->prepare('SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1');
                foreach (['sistema_usd_brl_rate', 'usd_brl_rate'] as $k) {
                    try {
                        $stmtRate->execute([$k]);
                        $val = $stmtRate->fetchColumn();
                        $v = (float) str_replace(',', '.', trim((string) ($val ?? '')));
                        if ($v > 0) {
                            $rate = $v;
                            break;
                        }
                    } catch (\Exception $e) {
                    }
                }
            } catch (\Exception $e) {
            }

            if ($rate <= 0) {
                $rate = 5.5;
            }

            if ($rate <= 0 || $rate === 5.5) {
                try {
                    $stmtTx = $db->query("SELECT taxa_conversao FROM configuracoes_moeda WHERE moeda_origem = 'USD' AND moeda_destino = 'BRL' ORDER BY id DESC LIMIT 1");
                    $r = $stmtTx ? $stmtTx->fetch(\PDO::FETCH_ASSOC) : null;
                    if (is_array($r) && isset($r['taxa_conversao'])) {
                        $v = (float) $r['taxa_conversao'];
                        if ($v > 0) {
                            $rate = $v;
                        }
                    }
                } catch (\Exception $e) {
                }
            }
        } catch (\Exception $e) {
            $rate = 5.5;
        }

        if ($rate <= 0) {
            $rate = 5.5;
        }

        return (float) $rate;
    }

    private function getCarteiraUsdEquivalente(int $usuarioId): array {
        $saldoUsd = 0.0;
        $saldoBrl = 0.0;
        $rate = $this->getUsdBrlRate();

        try {
            $db = \Config\Database::getConnection();
            $stmt = $db->prepare('SELECT saldo_usd, saldo_brl FROM carteiras WHERE usuario_id = ? LIMIT 1');
            $stmt->execute([(int) $usuarioId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
            $saldoUsd = (float) ($row['saldo_usd'] ?? 0);
            $saldoBrl = (float) ($row['saldo_brl'] ?? 0);
        } catch (\Exception $e) {
            $saldoUsd = 0.0;
            $saldoBrl = 0.0;
        }

        $equivUsd = (float) $saldoUsd;
        if ($saldoBrl > 0 && $rate > 0) {
            $equivUsd = (float) ($saldoUsd + ($saldoBrl / $rate));
        }

        return [
            'saldo_usd' => (float) $saldoUsd,
            'saldo_brl' => (float) $saldoBrl,
            'usd_brl_rate' => (float) $rate,
            'saldo_usd_equiv' => (float) $equivUsd,
        ];
    }

    private function produtoImagemExiste(string $path): bool {
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

    private function normalizeProdutoImagemPath($path): ?string {
        if (!is_string($path)) {
            return null;
        }

        $path = trim($path);
        if ($path === '') {
            return null;
        }

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

    public function produtosClube(Request $request) {
        $this->authService->requerAutenticacao();
        $usuario = $this->authService->getUsuarioLogado();
        $usuarioId = (int) ($usuario['id'] ?? 0);
        if ($usuarioId <= 0) {
            $this->redirect('/login');
        }

        $wallet = $this->getCarteiraUsdEquivalente($usuarioId);
        $minUsd = 39.0;
        if (((float) ($wallet['saldo_usd_equiv'] ?? 0)) + 0.00001 < $minUsd) {
            $this->view('clube/bloqueio', [
                'min_usd' => (float) $minUsd,
                'wallet' => $wallet,
            ]);
            return;
        }

        $produtos = [];
        try {
            $pdo = $this->produtoModel->getConnection();
            $cols = [];
            try {
                $stmtCols = $pdo->query('DESCRIBE produtos');
                $cols = $stmtCols ? ($stmtCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            } catch (\Exception $e) {
                $cols = [];
            }

            if (!is_array($cols) || !in_array('clube_ativo', $cols, true)) {
                $produtos = [];
            } else {
                $where = ["p.clube_ativo = 1"];
                if (in_array('active', $cols, true)) {
                    $where[] = 'p.active = 1';
                }
                if (in_array('status', $cols, true)) {
                    $where[] = "LOWER(COALESCE(p.status,'')) IN ('published','ativo','active')";
                }
                $where[] = "(p.sku IS NULL OR p.sku NOT LIKE 'ASS-%')";
                if (in_array('attributes', $cols, true)) {
                    $where[] = "(p.attributes IS NULL OR p.attributes NOT LIKE '%\"fonte\":\"assessoria\"%')";
                }

                $sql = "SELECT p.*, c.name as categoria\n                        FROM produtos p\n                        LEFT JOIN categorias c ON p.category_id = c.id\n                        WHERE " . implode(' AND ', $where) . "\n                        ORDER BY p.featured DESC, p.name ASC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute();
                $produtos = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

                foreach ($produtos as &$produto) {
                    $capa = $this->normalizeProdutoImagemPath($produto['foto_principal'] ?? null);
                    if (!empty($capa) && $this->produtoImagemExiste($capa)) {
                        $produto['foto_principal'] = Url::absolute($capa);
                        continue;
                    }

                    $fotoGaleria = $this->produtoFotoModel->getFotoPrincipal((int) ($produto['id'] ?? 0));
                    if ($fotoGaleria && !empty($fotoGaleria['nome_arquivo'])) {
                        $fotoUrl = $this->normalizeProdutoImagemPath($fotoGaleria['nome_arquivo']);
                        $produto['foto_principal'] = ($fotoUrl && $this->produtoImagemExiste($fotoUrl)) ? Url::absolute($fotoUrl) : null;
                    } else {
                        $produto['foto_principal'] = null;
                    }
                }
                unset($produto);

                foreach ($produtos as &$p) {
                    $p['is_variavel'] = false;
                }
                unset($p);
            }
        } catch (\Exception $e) {
            $produtos = [];
        }

        $this->view('clube/produtos', [
            'produtos' => $produtos,
        ]);
    }

    public function cronRendimento(Request $request) {
        try {
            $result = $this->paymentService->processarRendimentoClube();
            $this->json($result);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
