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
                `public_token` varchar(64) DEFAULT NULL,
                `pagador_nome` varchar(191) DEFAULT NULL,
                `pagador_email` varchar(191) DEFAULT NULL,
                `pagador_documento` varchar(30) DEFAULT NULL,
                `metodo` varchar(20) DEFAULT NULL,
                `usd_brl_rate` decimal(10,6) DEFAULT NULL,
                `valor_brl` decimal(10,2) DEFAULT NULL,
                `gateway` varchar(20) DEFAULT NULL,
                `payment_id` varchar(191) DEFAULT NULL,
                `invoice_url` text,
                `status` varchar(30) NOT NULL DEFAULT 'pending',
                `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                `paid_at` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_usuario_id` (`usuario_id`),
                KEY `idx_public_token` (`public_token`),
                KEY `idx_gateway_payment` (`gateway`, `payment_id`),
                KEY `idx_status` (`status`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (\Exception $e) {
        }

        try {
            $cols = [];
            try {
                $st = $db->query('DESCRIBE carteira_recargas');
                $cols = $st ? ($st->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
            } catch (\Exception $e) {
                $cols = [];
            }

            $toAdd = [
                'public_token' => "ALTER TABLE carteira_recargas ADD COLUMN public_token varchar(64) DEFAULT NULL",
                'pagador_nome' => "ALTER TABLE carteira_recargas ADD COLUMN pagador_nome varchar(191) DEFAULT NULL",
                'pagador_email' => "ALTER TABLE carteira_recargas ADD COLUMN pagador_email varchar(191) DEFAULT NULL",
                'pagador_documento' => "ALTER TABLE carteira_recargas ADD COLUMN pagador_documento varchar(30) DEFAULT NULL",
                'metodo' => "ALTER TABLE carteira_recargas ADD COLUMN metodo varchar(20) DEFAULT NULL",
                'usd_brl_rate' => "ALTER TABLE carteira_recargas ADD COLUMN usd_brl_rate decimal(10,6) DEFAULT NULL",
                'valor_brl' => "ALTER TABLE carteira_recargas ADD COLUMN valor_brl decimal(10,2) DEFAULT NULL",
            ];

            foreach ($toAdd as $c => $sql) {
                if (!is_array($cols) || !in_array($c, $cols, true)) {
                    try { $db->exec($sql); } catch (\Exception $e) {}
                }
            }

            try {
                $db->exec("CREATE INDEX idx_public_token ON carteira_recargas (public_token)");
            } catch (\Exception $e) {
            }
        } catch (\Exception $e) {
        }
    }

    private function generatePublicToken(): string {
        try {
            return bin2hex(random_bytes(16));
        } catch (\Exception $e) {
            return bin2hex((string) microtime(true) . (string) mt_rand());
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

    private function parseDateYmd(string $ymd): ?\DateTimeImmutable {
        $ymd = trim($ymd);
        if ($ymd === '') {
            return null;
        }
        $dt = \DateTimeImmutable::createFromFormat('Y-m-d', $ymd);
        if (!$dt) {
            return null;
        }
        $errs = \DateTimeImmutable::getLastErrors();
        if (is_array($errs) && (!empty($errs['warning_count']) || !empty($errs['error_count']))) {
            return null;
        }
        // Garantir que bate exatamente (ex: 2025-02-31 não passa)
        if ($dt->format('Y-m-d') !== $ymd) {
            return null;
        }
        return $dt;
    }

    private function validateAdultBirthDate(string $ymd, int $minAge = 18): array {
        $dt = $this->parseDateYmd($ymd);
        if (!$dt) {
            return ['ok' => false, 'error' => 'Data de nascimento inválida'];
        }

        $year = (int) $dt->format('Y');
        $now = new \DateTimeImmutable('now');

        // Limites para evitar datas surreais
        if ($year < 1900) {
            return ['ok' => false, 'error' => 'Data de nascimento inválida'];
        }
        if ($dt > $now) {
            return ['ok' => false, 'error' => 'Data de nascimento inválida'];
        }

        $age = (int) $dt->diff($now)->y;
        if ($age < $minAge) {
            return ['ok' => false, 'error' => 'Você precisa ter no mínimo ' . $minAge . ' anos'];
        }

        return ['ok' => true, 'date' => $dt];
    }

    private function isValidCpf(string $cpf): bool {
        $cpf = preg_replace('/\D+/', '', $cpf);
        if (strlen($cpf) !== 11) return false;
        if (preg_match('/^(\d)\1{10}$/', $cpf)) return false;
        $nums = array_map('intval', str_split($cpf));

        // 1º dígito
        $sum = 0;
        for ($i = 0, $w = 10; $i < 9; $i++, $w--) {
            $sum += $nums[$i] * $w;
        }
        $d1 = 11 - ($sum % 11);
        if ($d1 >= 10) $d1 = 0;
        if ($nums[9] !== $d1) return false;

        // 2º dígito
        $sum = 0;
        for ($i = 0, $w = 11; $i < 10; $i++, $w--) {
            $sum += $nums[$i] * $w;
        }
        $d2 = 11 - ($sum % 11);
        if ($d2 >= 10) $d2 = 0;
        return $nums[10] === $d2;
    }

    private function isValidCnpj(string $cnpj): bool {
        $cnpj = preg_replace('/\D+/', '', $cnpj);
        if (strlen($cnpj) !== 14) return false;
        if (preg_match('/^(\d)\1{13}$/', $cnpj)) return false;
        $nums = array_map('intval', str_split($cnpj));

        $weights1 = [5,4,3,2,9,8,7,6,5,4,3,2];
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += $nums[$i] * $weights1[$i];
        }
        $r = $sum % 11;
        $d1 = ($r < 2) ? 0 : (11 - $r);
        if ($nums[12] !== $d1) return false;

        $weights2 = [6,5,4,3,2,9,8,7,6,5,4,3,2];
        $sum = 0;
        for ($i = 0; $i < 13; $i++) {
            $sum += $nums[$i] * $weights2[$i];
        }
        $r = $sum % 11;
        $d2 = ($r < 2) ? 0 : (11 - $r);
        return $nums[13] === $d2;
    }

    private function validateCpfCnpj(string $docDigits): bool {
        $docDigits = preg_replace('/\D+/', '', $docDigits);
        if (strlen($docDigits) === 11) {
            return $this->isValidCpf($docDigits);
        }
        if (strlen($docDigits) === 14) {
            return $this->isValidCnpj($docDigits);
        }
        return false;
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

            if (!$this->validateCpfCnpj($doc)) {
                $this->json(['success' => false, 'error' => 'CPF/CNPJ inválido'], 400);
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

        $dobVal = $this->validateAdultBirthDate($dataNascimento, 18);
        if (empty($dobVal['ok'])) {
            $this->json(['success' => false, 'error' => (string) ($dobVal['error'] ?? 'Data de nascimento inválida')], 400);
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

            $publicToken = $this->generatePublicToken();

            $stmtIns = $db->prepare("INSERT INTO carteira_recargas (usuario_id, moeda, valor, public_token, pagador_nome, pagador_email, pagador_documento, metodo, usd_brl_rate, valor_brl, status, created_at, updated_at) VALUES (:uid, 'USD', :valor, :ptok, :pnome, :pemail, :pdoc, :metodo, :rate, :vbrl, 'pending', NOW(), NOW())");
            $stmtIns->execute([
                ':uid' => $usuarioId,
                ':valor' => $valorUsd,
                ':ptok' => $publicToken,
                ':pnome' => $nomeCompleto,
                ':pemail' => $email,
                ':pdoc' => $doc,
                ':metodo' => $metodo,
                ':rate' => $rate,
                ':vbrl' => $valorBrl,
            ]);
            $recargaId = (int) $db->lastInsertId();
            if ($recargaId <= 0) {
                $this->json(['success' => false, 'error' => 'Não foi possível criar recarga'], 500);
                return;
            }

            $descricao = 'Recarga Clube #' . $recargaId;

            $stripeCustomer = [
                'email' => $email,
                'name' => $nomeCompleto,
                'tax_id' => $doc,
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
                'public_token' => $publicToken,
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

    public function status(Request $request) {
        $recargaId = (int) ($request->getParam('recarga_id') ?? 0);
        $token = trim((string) ($request->getParam('token') ?? ''));
        if ($recargaId <= 0 || $token === '') {
            $this->json(['success' => false, 'error' => 'Parâmetros inválidos'], 400);
            return;
        }

        try {
            $db = \Config\Database::getConnection();
            $this->ensureCarteiraRecargasTable($db);

            $stmt = $db->prepare('SELECT id, public_token, status, paid_at, moeda, valor, metodo, usd_brl_rate, valor_brl, gateway, payment_id, pagador_nome, pagador_email, pagador_documento, created_at FROM carteira_recargas WHERE id = ? LIMIT 1');
            $stmt->execute([$recargaId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
            if (empty($row['id']) || !hash_equals((string) ($row['public_token'] ?? ''), $token)) {
                $this->json(['success' => false, 'error' => 'Recarga não encontrada'], 404);
                return;
            }

            $status = strtolower(trim((string) ($row['status'] ?? 'pending')));
            $paid = in_array($status, ['paid', 'approved', 'credited'], true);
            $this->json([
                'success' => true,
                'recarga_id' => (int) ($row['id'] ?? 0),
                'status' => $status,
                'is_paid' => $paid,
                'paid_at' => $row['paid_at'] ?? null,
                'gateway' => (string) ($row['gateway'] ?? ''),
                'payment_id' => (string) ($row['payment_id'] ?? ''),
                'moeda' => (string) ($row['moeda'] ?? 'USD'),
                'valor' => (float) ($row['valor'] ?? 0),
                'metodo' => (string) ($row['metodo'] ?? ''),
                'usd_brl_rate' => isset($row['usd_brl_rate']) ? (float) $row['usd_brl_rate'] : null,
                'valor_brl' => isset($row['valor_brl']) ? (float) $row['valor_brl'] : null,
                'pagador_nome' => (string) ($row['pagador_nome'] ?? ''),
                'pagador_email' => (string) ($row['pagador_email'] ?? ''),
                'pagador_documento' => (string) ($row['pagador_documento'] ?? ''),
                'created_at' => $row['created_at'] ?? null,
                'redirect_url' => $paid ? ('/clube/recarga/comprovante/' . (int) ($row['id'] ?? 0) . '?token=' . urlencode($token)) : null,
            ]);
        } catch (\Exception $e) {
            $this->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function comprovante(Request $request) {
        $recargaId = (int) ($request->getParam('id') ?? 0);
        $token = trim((string) ($request->getParam('token') ?? ''));
        if ($recargaId <= 0 || $token === '') {
            $this->redirect('/clube/recarga');
            return;
        }

        try {
            $db = \Config\Database::getConnection();
            $this->ensureCarteiraRecargasTable($db);

            $stmt = $db->prepare('SELECT * FROM carteira_recargas WHERE id = ? LIMIT 1');
            $stmt->execute([$recargaId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
            if (empty($row['id']) || !hash_equals((string) ($row['public_token'] ?? ''), $token)) {
                $this->redirect('/clube/recarga');
                return;
            }

            $status = strtolower(trim((string) ($row['status'] ?? 'pending')));
            $paid = in_array($status, ['paid', 'approved', 'credited'], true);

            $this->view('clube/recarga-comprovante', [
                'recarga' => $row,
                'is_paid' => $paid,
                'token' => $token,
            ]);
        } catch (\Exception $e) {
            $this->redirect('/clube/recarga');
        }
    }
}
