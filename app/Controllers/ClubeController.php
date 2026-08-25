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

    public function comoFuncionaClube(Request $request) {
        $this->view('clube/como-funciona-clube', [
            'clube_enabled' => self::isClubeEnabled(),
            'clube_whatsapp' => self::CLUBE_WHATSAPP,
            'clube_whatsapp_label' => self::CLUBE_WHATSAPP_LABEL,
        ]);
    }

    /**
     * WhatsApp de contato para o Clube (usado quando o clube está desativado).
     */
    public const CLUBE_WHATSAPP = '13053638204';
    public const CLUBE_WHATSAPP_LABEL = '+1 305-363-8204';

    /**
     * Lê a flag global clube_enabled em configuracoes_sistema.
     * Segue o mesmo padrão de assessoria_enabled.
     * Retorna true (ativado) por padrão quando a chave não existe.
     */
    public static function isClubeEnabled(): bool {
        $enabled = true;
        try {
            $pdo = \Config\Database::getConnection();
            $st = $pdo->prepare("SELECT valor FROM configuracoes_sistema WHERE chave IN ('clube_enabled', 'sistema_clube_enabled') ORDER BY chave DESC LIMIT 1");
            $st->execute();
            $val = $st->fetchColumn();
            if ($val !== false) {
                $enabled = in_array(strtolower(trim((string) $val)), ['1', 'true', 'sim', 'yes', 'on'], true);
            }
        } catch (\Exception $e) {}
        return $enabled;
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
                if (in_array('oculto', $cols, true)) {
                    $where[] = "(p.oculto IS NULL OR p.oculto = 0)";
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

    private function getCronSecretFromConfig(): ?string {
        try {
            $db = \Config\Database::getConnection();

            // Schema categoria+chave
            try {
                $stmt = $db->prepare("SELECT valor FROM configuracoes_sistema WHERE categoria = ? AND chave = ? LIMIT 1");
                $stmt->execute(['clube', 'cron_secret']);
                $val = $stmt->fetchColumn();
                if (is_string($val) && trim($val) !== '') {
                    return trim($val);
                }
            } catch (\Exception $e) {
            }

            // Schema chave única
            try {
                $stmt = $db->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1");
                $stmt->execute(['clube_cron_secret']);
                $val = $stmt->fetchColumn();
                if (is_string($val) && trim($val) !== '') {
                    return trim($val);
                }
            } catch (\Exception $e) {
            }
        } catch (\Exception $e) {
        }

        return null;
    }

    public function cronRendimento(Request $request) {
        try {
            $secret = null;
            if (isset($_ENV['CRON_SECRET'])) {
                $secret = (string) $_ENV['CRON_SECRET'];
            } elseif (isset($_SERVER['CRON_SECRET'])) {
                $secret = (string) $_SERVER['CRON_SECRET'];
            }

            if ($secret === null || trim($secret) === '') {
                $secret = $this->getCronSecretFromConfig();
            }

            if ($secret !== null && trim($secret) !== '') {
                $token = (string) ($request->getParam('token') ?? '');
                if ($token === '' && isset($_SERVER['HTTP_X_CRON_TOKEN'])) {
                    $token = (string) $_SERVER['HTTP_X_CRON_TOKEN'];
                }
                if (!hash_equals(trim($secret), trim($token))) {
                    $this->json(['success' => false, 'error' => 'Unauthorized'], 403);
                    return;
                }
            }

            $result = $this->paymentService->processarRendimentoClube();

            // Process grace period expirations
            $graceResult = $this->processarGracePeriodExpirados();
            $result['grace_expired'] = $graceResult;

            $this->json($result);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    private function processarGracePeriodExpirados(): array {
        $out = ['processed' => 0, 'deactivated' => 0];
        try {
            $db = \Config\Database::getConnection();

            // Check if clube_status table exists
            try {
                $st = $db->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1');
                $st->execute(['clube_status']);
                if (!$st->fetchColumn()) return $out;
            } catch (\Exception $e) { return $out; }

            // Find users in grace period whose 48h expired
            $stmt = $db->prepare("SELECT usuario_id FROM clube_status WHERE status = 'grace_period' AND grace_until IS NOT NULL AND grace_until <= NOW()");
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            foreach ($rows as $r) {
                $out['processed']++;
                $uid = (int) ($r['usuario_id'] ?? 0);
                if ($uid <= 0) continue;

                // Check if user recharged during grace period
                $stBal = $db->prepare('SELECT COALESCE(saldo_usd, 0) - COALESCE(saldo_usd_bloqueado, 0) AS disp FROM carteiras WHERE usuario_id = ? LIMIT 1');
                $stBal->execute([$uid]);
                $disp = (float) ($stBal->fetchColumn() ?: 0);

                if ($disp + 0.00001 >= 39.0) {
                    // User recharged, reactivate
                    $stReact = $db->prepare("UPDATE clube_status SET status = 'ativo', grace_until = NULL, motivo = 'Reativado por recarga durante período de carência', updated_at = NOW() WHERE usuario_id = :uid");
                    $stReact->execute([':uid' => $uid]);

                    try {
                        $db->prepare("INSERT INTO transacoes_carteira (usuario_id, tipo, valor_usd, descricao, modalidade, created_at) VALUES (:uid, 'credito', 0, :desc, 'normal', NOW())")
                            ->execute([':uid' => $uid, ':desc' => 'Reativação do Clube Braziliana']);
                    } catch (\Exception $e) {}
                } else {
                    // Deactivate benefits
                    $stDeact = $db->prepare("UPDATE clube_status SET status = 'inativo', grace_until = NULL, motivo = 'Desativado por saldo abaixo de US\$ 39 após 48h', updated_at = NOW() WHERE usuario_id = :uid");
                    $stDeact->execute([':uid' => $uid]);
                    $out['deactivated']++;

                    try {
                        $db->prepare("INSERT INTO transacoes_carteira (usuario_id, tipo, valor_usd, descricao, modalidade, created_at) VALUES (:uid, 'credito', 0, :desc, 'normal', NOW())")
                            ->execute([':uid' => $uid, ':desc' => 'Desativação por saldo abaixo do mínimo — Seu saldo do Clube Braziliana ficou abaixo de US$ 39 e os benefícios foram desativados.']);
                    } catch (\Exception $e) {}
                }
            }
        } catch (\Exception $e) {}
        return $out;
    }
}
