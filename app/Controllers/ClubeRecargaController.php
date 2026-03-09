<?php
namespace App\Controllers;

use App\Core\Request;
use App\Models\Usuario;
use App\Services\PaymentService;
use App\Services\WordPressDbService;

class ClubeRecargaController extends Controller {
    private Usuario $usuarioModel;
    private PaymentService $paymentService;
    private WordPressDbService $wpDbService;

    public function __construct() {
        $this->usuarioModel = new Usuario();
        $this->paymentService = new PaymentService();
        $this->wpDbService = new WordPressDbService();
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

    private function ensureCarteiraRecargasTable(\PDO $db): void {
        try {
            $db->exec("CREATE TABLE IF NOT EXISTS `carteira_recargas` (
                `id` int(11) NOT NULL AUTO_INCREMENT,
                `usuario_id` int(11) NOT NULL,
                `moeda` varchar(3) NOT NULL DEFAULT 'USD',
                `valor` decimal(10,2) NOT NULL DEFAULT 0.00,
                `gateway` varchar(20) DEFAULT NULL,
                `payment_id` varchar(191) DEFAULT NULL,
                `invoice_url` text,
                `status` varchar(30) NOT NULL DEFAULT 'pending',
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                `paid_at` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_usuario_id` (`usuario_id`),
                KEY `idx_gateway_payment` (`gateway`, `payment_id`),
                KEY `idx_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (\Exception $e) {
        }
    }

    private function normalizePhone(?string $ddi, ?string $numero): string {
        $ddi = preg_replace('/\D+/', '', (string) $ddi);
        $numero = trim((string) $numero);
        if ($ddi === '') {
            return $numero;
        }
        return '+' . $ddi . ' ' . $numero;
    }

    private function lookupEmail(string $email): array {
        $email = strtolower(trim($email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['ok' => false, 'error' => 'E-mail inválido'];
        }

        $internal = null;
        try {
            $u = $this->usuarioModel->findByEmail($email);
            if (is_array($u) && !empty($u['id'])) {
                $internal = $u;
            }
        } catch (\Exception $e) {
            $internal = null;
        }

        $wpFound = [];
        foreach (['br', 'red', 'us'] as $src) {
            try {
                $wu = $this->wpDbService->findUserByEmail($email, $src);
                if (is_array($wu) && !empty($wu['id'])) {
                    $wpFound[] = $wu;
                }
            } catch (\Exception $e) {
            }
        }

        $wpPrimary = null;
        if (!empty($wpFound)) {
            $wpPrimary = $wpFound[0];
        }

        $wpProfile = [];
        if (is_array($wpPrimary) && !empty($wpPrimary['id'])) {
            try {
                $wpProfile = $this->wpDbService->getNormalizedUserProfile((int) $wpPrimary['id'], (string) ($wpPrimary['source'] ?? 'br'));
            } catch (\Exception $e) {
                $wpProfile = [];
            }
        }

        return [
            'ok' => true,
            'email' => $email,
            'internal_user' => $internal ? ['id' => (int) $internal['id']] : null,
            'wp_users' => $wpFound,
            'wp_profile' => $wpProfile,
        ];
    }

    public function index(Request $request) {
        $rate = $this->getUsdBrlRate();
        $this->view('clube/recarga', [
            'min_usd' => 39.0,
            'usd_brl_rate' => $rate,
            'stripe_enabled' => (bool) $this->paymentService->isStripeEnabled(),
            'stripe_publishable_key' => (string) $this->paymentService->getStripePublishableKey(),
        ]);
    }

    public function emailCheck(Request $request) {
        $email = (string) ($request->getParam('email') ?? '');
        $res = $this->lookupEmail($email);
        if (empty($res['ok'])) {
            $this->json(['success' => false, 'error' => (string) ($res['error'] ?? 'Falha')], 400);
            return;
        }

        $this->json([
            'success' => true,
            'email' => (string) ($res['email'] ?? ''),
            'has_internal_account' => !empty($res['internal_user']),
            'internal_user' => $res['internal_user'],
            'wp_users' => $res['wp_users'],
            'wp_profile' => $res['wp_profile'],
        ]);
    }

    public function criar(Request $request) {
        $raw = file_get_contents('php://input');
        $data = json_decode((string) $raw, true);
        if (!is_array($data)) {
            $data = [];
        }

        $email = strtolower(trim((string) ($data['email'] ?? '')));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->json(['success' => false, 'error' => 'E-mail inválido'], 400);
            return;
        }

        $valorUsd = (float) str_replace(',', '.', (string) ($data['valor_usd'] ?? '0'));
        if ($valorUsd + 0.00001 < 39.0) {
            $this->json(['success' => false, 'error' => 'Valor mínimo é $39.00'], 400);
            return;
        }

        $metodo = strtolower(trim((string) ($data['metodo'] ?? 'pix')));
        if (!in_array($metodo, ['pix', 'card'], true)) {
            $metodo = 'pix';
        }

        $aceitou = !empty($data['aceitou_termos']);
        if (!$aceitou) {
            $this->json(['success' => false, 'error' => 'Você precisa aceitar os termos para continuar'], 400);
            return;
        }

        $pais = strtoupper(trim((string) ($data['pais'] ?? 'BR')));
        if ($pais === '') {
            $pais = 'BR';
        }

        $doc = preg_replace('/\D+/', '', (string) ($data['documento'] ?? ''));
        if ($pais === 'BR') {
            if ($doc === '' || strlen($doc) < 11) {
                $this->json(['success' => false, 'error' => 'CPF/CNPJ é obrigatório para residentes no Brasil'], 400);
                return;
            }
        }

        $nome = trim((string) ($data['nome'] ?? ''));
        $sobrenome = trim((string) ($data['sobrenome'] ?? ''));
        $nomeCompleto = trim($nome . ' ' . $sobrenome);
        if ($nomeCompleto === '') {
            $this->json(['success' => false, 'error' => 'Nome é obrigatório'], 400);
            return;
        }

        $telefoneDdi = (string) ($data['telefone_ddi'] ?? '');
        $telefoneNumero = (string) ($data['telefone_numero'] ?? '');
        $telefone = $this->normalizePhone($telefoneDdi, $telefoneNumero);
        if (trim($telefone) === '') {
            $this->json(['success' => false, 'error' => 'Telefone é obrigatório'], 400);
            return;
        }

        $dataNascimento = trim((string) ($data['data_nascimento'] ?? ''));
        if ($dataNascimento === '') {
            $this->json(['success' => false, 'error' => 'Data de nascimento é obrigatória'], 400);
            return;
        }

        $senha = (string) ($data['senha'] ?? '');

        $lookup = $this->lookupEmail($email);
        if (empty($lookup['ok'])) {
            $this->json(['success' => false, 'error' => (string) ($lookup['error'] ?? 'Falha ao validar e-mail')], 400);
            return;
        }

        $usuarioId = 0;
        if (!empty($lookup['internal_user']['id'])) {
            $usuarioId = (int) $lookup['internal_user']['id'];
        }

        if ($usuarioId <= 0) {
            if (trim($senha) === '' || strlen(trim($senha)) < 6) {
                $this->json(['success' => false, 'error' => 'Senha obrigatória (mínimo 6 caracteres)'], 400);
                return;
            }

            try {
                $colsU = [];
                try {
                    $stmtColsU = $this->usuarioModel->getConnection()->query('DESCRIBE usuarios');
                    $colsU = $stmtColsU ? ($stmtColsU->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
                } catch (\Exception $e) {
                    $colsU = [];
                }

                $create = [
                    'nome' => $nomeCompleto,
                    'email' => $email,
                    'senha' => $senha,
                    'telefone' => $telefone,
                    'perfil' => 'cliente',
                ];

                if (in_array('documento', $colsU, true)) {
                    $create['documento'] = $doc !== '' ? $doc : null;
                }

                $usuarioId = (int) $this->usuarioModel->create($create);

                $upd = [];
                if (in_array('data_nascimento', $colsU, true)) {
                    $upd['data_nascimento'] = $dataNascimento;
                }
                if (in_array('pais_residencia', $colsU, true)) {
                    $upd['pais_residencia'] = substr($pais, 0, 2);
                }

                $wpPrimary = null;
                $wpUsers = $lookup['wp_users'] ?? [];
                if (is_array($wpUsers) && !empty($wpUsers)) {
                    $wpPrimary = $wpUsers[0];
                }
                if (is_array($wpPrimary) && !empty($wpPrimary['id'])) {
                    if (in_array('wp_origem', $colsU, true)) {
                        $upd['wp_origem'] = (string) ($wpPrimary['source'] ?? 'br');
                    }
                    if (in_array('wp_user_id', $colsU, true)) {
                        $upd['wp_user_id'] = (int) ($wpPrimary['id'] ?? 0);
                    }
                }

                if (in_array('termos_aceitos_em', $colsU, true)) {
                    $upd['termos_aceitos_em'] = date('Y-m-d H:i:s');
                }
                if (in_array('termos_aceitos_ip', $colsU, true)) {
                    $upd['termos_aceitos_ip'] = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
                }
                if (in_array('termos_versao', $colsU, true)) {
                    $upd['termos_versao'] = '1.0';
                }

                if (!empty($upd)) {
                    $this->usuarioModel->update($usuarioId, $upd);
                }
            } catch (\Exception $e) {
                $this->json(['success' => false, 'error' => 'Falha ao criar conta: ' . $e->getMessage()], 500);
                return;
            }
        }

        if ($usuarioId <= 0) {
            $this->json(['success' => false, 'error' => 'Não foi possível identificar/criar o usuário'], 500);
            return;
        }

        $rate = $this->getUsdBrlRate();
        $valorBrl = (float) ($valorUsd * $rate);
        if ($valorBrl <= 0) {
            $this->json(['success' => false, 'error' => 'Falha ao calcular valor em BRL'], 500);
            return;
        }

        $amountCents = (int) round($valorBrl * 100);
        if ($amountCents < 1) {
            $this->json(['success' => false, 'error' => 'Valor inválido'], 400);
            return;
        }

        try {
            $db = \Config\Database::getConnection();
            $this->ensureCarteiraRecargasTable($db);

            $stmtIns = $db->prepare("INSERT INTO carteira_recargas (usuario_id, moeda, valor, status, created_at, updated_at) VALUES (:uid, 'USD', :valor, 'pending', NOW(), NOW())");
            $stmtIns->execute([
                ':uid' => $usuarioId,
                ':valor' => $valorUsd,
            ]);
            $recargaId = (int) $db->lastInsertId();
            if ($recargaId <= 0) {
                $this->json(['success' => false, 'error' => 'Não foi possível criar recarga'], 500);
                return;
            }

            $descricao = 'Recarga Clube #' . $recargaId;

            $stripeCustomer = [
                'email' => $email,
                'metadata' => [
                    'usd_amount' => (string) $valorUsd,
                    'usd_brl_rate' => (string) $rate,
                    'brl_amount' => (string) $valorBrl,
                    'flow' => 'clube_quick_checkout',
                    'metodo' => $metodo,
                ],
            ];

            if ($metodo === 'card') {
                $pi = $this->paymentService->createStripePaymentIntentCarteiraRecargaCardBrl($recargaId, $valorBrl, $descricao, $stripeCustomer);
            } else {
                $pi = $this->paymentService->createStripePaymentIntentCarteiraRecargaPixBrl($recargaId, $valorBrl, $descricao, $stripeCustomer);
            }

            if (empty($pi['success'])) {
                $this->json(['success' => false, 'error' => (string) ($pi['error'] ?? 'Falha ao iniciar pagamento Stripe')], 500);
                return;
            }

            $paymentIntentId = (string) ($pi['payment_intent_id'] ?? '');
            if ($paymentIntentId !== '') {
                $stmtUp = $db->prepare("UPDATE carteira_recargas SET gateway = 'stripe', payment_id = :pid, status = 'pending', updated_at = NOW() WHERE id = :id");
                $stmtUp->execute([':pid' => $paymentIntentId, ':id' => $recargaId]);
            }

            $this->json([
                'success' => true,
                'recarga_id' => $recargaId,
                'usuario_id' => $usuarioId,
                'valor_usd' => $valorUsd,
                'usd_brl_rate' => $rate,
                'valor_brl' => $valorBrl,
                'metodo' => $metodo,
                'payment_intent_id' => $paymentIntentId,
                'client_secret' => (string) ($pi['client_secret'] ?? ''),
                'pix' => (isset($pi['pix']) && is_array($pi['pix'])) ? $pi['pix'] : null,
            ]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
}
