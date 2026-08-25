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

    private const CLUBE_CAP_BRL = 150000.00;
    private const CLUBE_LOCK_MONTHS = 6;

    public function __construct() {
        $this->usuarioModel = new Usuario();
        $this->paymentService = new PaymentService();
        $this->wpDbService = new WordPressDbService();
    }

    private function getTotalCaptadoPagoBrl(\PDO $db): float {
        try {
            $this->ensureCarteiraRecargasTable($db);
            $stmt = $db->prepare("SELECT COALESCE(SUM(COALESCE(valor_brl,0)),0) AS total
                FROM carteira_recargas
                WHERE origem = 'clube_quick_checkout'
                  AND LOWER(COALESCE(status,'')) IN ('paid','approved','credited')");
            $stmt->execute();
            return (float) ($stmt->fetchColumn() ?: 0);
        } catch (\Exception $e) {
            return 0.0;
        }
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
                `origem` varchar(40) DEFAULT NULL,
                `tipo_recarga` varchar(10) NOT NULL DEFAULT 'normal',
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
                `locked_until` timestamp NULL DEFAULT NULL,
                `unlocked_at` timestamp NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_usuario_id` (`usuario_id`),
                KEY `idx_public_token` (`public_token`),
                KEY `idx_gateway_payment` (`gateway`, `payment_id`),
                KEY `idx_status` (`status`),
                KEY `idx_tipo_recarga` (`tipo_recarga`)
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
                'origem' => "ALTER TABLE carteira_recargas ADD COLUMN origem varchar(40) DEFAULT NULL",
                'tipo_recarga' => "ALTER TABLE carteira_recargas ADD COLUMN tipo_recarga varchar(10) NOT NULL DEFAULT 'normal'",
                'public_token' => "ALTER TABLE carteira_recargas ADD COLUMN public_token varchar(64) DEFAULT NULL",
                'pagador_nome' => "ALTER TABLE carteira_recargas ADD COLUMN pagador_nome varchar(191) DEFAULT NULL",
                'pagador_email' => "ALTER TABLE carteira_recargas ADD COLUMN pagador_email varchar(191) DEFAULT NULL",
                'pagador_documento' => "ALTER TABLE carteira_recargas ADD COLUMN pagador_documento varchar(30) DEFAULT NULL",
                'metodo' => "ALTER TABLE carteira_recargas ADD COLUMN metodo varchar(20) DEFAULT NULL",
                'usd_brl_rate' => "ALTER TABLE carteira_recargas ADD COLUMN usd_brl_rate decimal(10,6) DEFAULT NULL",
                'valor_brl' => "ALTER TABLE carteira_recargas ADD COLUMN valor_brl decimal(10,2) DEFAULT NULL",
                'locked_until' => "ALTER TABLE carteira_recargas ADD COLUMN locked_until timestamp NULL DEFAULT NULL",
                'unlocked_at' => "ALTER TABLE carteira_recargas ADD COLUMN unlocked_at timestamp NULL DEFAULT NULL",
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

            // Migration: mark all existing paid recargas (from clube_quick_checkout with locked_until) as turbo
            try {
                $db->exec("UPDATE carteira_recargas
                    SET tipo_recarga = 'turbo'
                    WHERE origem = 'clube_quick_checkout'
                      AND tipo_recarga = 'normal'
                      AND locked_until IS NOT NULL
                      AND LOWER(COALESCE(status,'')) IN ('paid','approved','credited')");
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
        // Clube desativado: bloquear novas recargas, exibir aviso com contato WhatsApp
        if (!ClubeController::isClubeEnabled()) {
            $this->view('clube/recarga_disabled', [
                'clube_whatsapp' => ClubeController::CLUBE_WHATSAPP,
                'clube_whatsapp_label' => ClubeController::CLUBE_WHATSAPP_LABEL,
            ]);
            return;
        }

        $rate = $this->getUsdBrlRate();

        $capBrl = (float) self::CLUBE_CAP_BRL;
        $totalPagoBrl = 0.0;
        $capReached = false;
        try {
            $db = \Config\Database::getConnection();
            $totalPagoBrl = $this->getTotalCaptadoPagoBrl($db);
            $capReached = ($totalPagoBrl + 0.00001 >= $capBrl);
        } catch (\Exception $e) {
            $capReached = false;
        }

        $capUsdEquiv = $rate > 0 ? ($capBrl / $rate) : 0.0;

        // Detectar usuário logado e puxar dados
        $loggedUser = null;
        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $isLogged = !empty($_SESSION['logado']) && !empty($_SESSION['usuario_id']);
            if ($isLogged) {
                $uid = (int) $_SESSION['usuario_id'];
                $db = \Config\Database::getConnection();

                $stmtU = $db->prepare('SELECT * FROM usuarios WHERE id = ? LIMIT 1');
                $stmtU->execute([$uid]);
                $uRow = $stmtU->fetch(\PDO::FETCH_ASSOC);

                if (is_array($uRow) && !empty($uRow['id'])) {
                    $nome = '';
                    $sobrenome = '';
                    $nomeCompleto = trim((string) ($uRow['nome'] ?? $uRow['name'] ?? ''));
                    if ($nomeCompleto !== '') {
                        $parts = explode(' ', $nomeCompleto, 2);
                        $nome = $parts[0] ?? '';
                        $sobrenome = $parts[1] ?? '';
                    }

                    $email = trim((string) ($uRow['email'] ?? ''));
                    $telefone = trim((string) ($uRow['telefone'] ?? $uRow['phone'] ?? ''));
                    $documento = trim((string) ($uRow['documento'] ?? $uRow['cpf'] ?? ''));
                    $dataNascimento = trim((string) ($uRow['data_nascimento'] ?? ''));
                    $paisResidencia = strtoupper(trim((string) ($uRow['pais_residencia'] ?? '')));

                    // Tentar pegar país do endereço principal se não tiver no perfil
                    if ($paisResidencia === '') {
                        try {
                            $stmtEnd = $db->prepare('SELECT pais FROM enderecos WHERE usuario_id = ? ORDER BY principal DESC, id DESC LIMIT 1');
                            $stmtEnd->execute([$uid]);
                            $pEnd = $stmtEnd->fetchColumn();
                            if ($pEnd) {
                                $paisResidencia = strtoupper(trim((string) $pEnd));
                            }
                        } catch (\Exception $e) {}
                    }

                    $loggedUser = [
                        'id' => (int) $uRow['id'],
                        'nome' => $nome,
                        'sobrenome' => $sobrenome,
                        'email' => $email,
                        'telefone' => $telefone,
                        'documento' => $documento,
                        'data_nascimento' => $dataNascimento,
                        'pais_residencia' => $paisResidencia !== '' ? $paisResidencia : 'BR',
                    ];
                }
            }
        } catch (\Exception $e) {
            $loggedUser = null;
        }

        $this->view('clube/recarga', [
            'min_usd' => 39.0,
            'usd_brl_rate' => $rate,
            'stripe_enabled' => (bool) $this->paymentService->isStripeEnabled(),
            'stripe_publishable_key' => (string) $this->paymentService->getStripePublishableKey(),
            'clube_cap_brl' => $capBrl,
            'clube_cap_usd_equiv' => $capUsdEquiv,
            'clube_total_pago_brl' => $totalPagoBrl,
            'clube_cap_reached' => $capReached,
            'logged_user' => $loggedUser,
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
        // Clube desativado: bloquear criação de novas recargas
        if (!ClubeController::isClubeEnabled()) {
            $this->json(['success' => false, 'error' => 'As recargas do Clube estão temporariamente pausadas. Entre em contato pelo WhatsApp ' . ClubeController::CLUBE_WHATSAPP_LABEL . ' para mais detalhes.'], 403);
            return;
        }

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

        $tipoRecarga = (!empty($data['turbo'])) ? 'turbo' : 'normal';

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

        $serverRate = $this->getUsdBrlRate();
        $rate = $serverRate;
        $clientRateRaw = $data['usd_brl_rate'] ?? null;
        $clientRate = 0.0;
        if ($clientRateRaw !== null) {
            $clientRate = (float) str_replace(',', '.', (string) $clientRateRaw);
        }
        if ($clientRate > 0) {
            // Proteção anti-manipulação: só aceita a taxa do cliente se estiver próxima da taxa do servidor.
            // Isso garante que o BRL exibido no front e o BRL cobrado no Stripe batam.
            $diff = abs($clientRate - $serverRate);
            $rel = ($serverRate > 0) ? ($diff / $serverRate) : 0.0;
            if ($rel > 0.02 && $diff > 0.05) {
                $this->json([
                    'success' => false,
                    'error' => 'A taxa de câmbio foi atualizada. Recarregue a página para calcular o valor em BRL novamente.',
                    'server_usd_brl_rate' => $serverRate,
                ], 409);
                return;
            }
            $rate = $clientRate;
        }

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

            $capBrl = (float) self::CLUBE_CAP_BRL;
            $totalPagoBrl = $this->getTotalCaptadoPagoBrl($db);
            if ($totalPagoBrl + 0.00001 >= $capBrl) {
                $this->json([
                    'success' => false,
                    'error' => 'Limite de capta\u00e7\u00e3o do Clube atingido. Novas recargas est\u00e3o temporariamente suspensas.',
                    'cap_brl' => $capBrl,
                    'total_pago_brl' => $totalPagoBrl,
                ], 403);
                return;
            }
            if ($totalPagoBrl + $valorBrl > $capBrl + 0.00001) {
                $rateNow = $rate > 0 ? $rate : $this->getUsdBrlRate();
                $capUsdEquiv = $rateNow > 0 ? ($capBrl / $rateNow) : 0.0;
                $this->json([
                    'success' => false,
                    'error' => 'Esta recarga excede o teto do Clube. Ajuste o valor para caber no limite total.',
                    'cap_brl' => $capBrl,
                    'cap_usd_equiv' => $capUsdEquiv,
                    'total_pago_brl' => $totalPagoBrl,
                    'tentativa_brl' => $valorBrl,
                ], 403);
                return;
            }

            $publicToken = $this->generatePublicToken();

            $stmtIns = $db->prepare("INSERT INTO carteira_recargas (usuario_id, moeda, valor, origem, tipo_recarga, public_token, pagador_nome, pagador_email, pagador_documento, metodo, usd_brl_rate, valor_brl, status, created_at, updated_at) VALUES (:uid, 'USD', :valor, :origem, :tipo_recarga, :ptok, :pnome, :pemail, :pdoc, :metodo, :rate, :vbrl, 'pending', NOW(), NOW())");
            $stmtIns->execute([
                ':uid' => $usuarioId,
                ':valor' => $valorUsd,
                ':origem' => 'clube_quick_checkout',
                ':tipo_recarga' => $tipoRecarga,
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

            $descricao = 'Recarga Clube Braziliana #' . $recargaId . ' - ' . $nomeCompleto;

            $customerData = [
                'name' => $nomeCompleto,
                'email' => $email,
                'document' => $doc,
                'birth_date' => $dataNascimento,
                'phone' => $telefone,
                'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? '127.0.0.1'),
            ];

            // Buscar endereço do usuário para enviar ao Câmbio Real (obrigatório para PIX)
            try {
                $dbEnd = \Config\Database::getConnection();
                $endCols = [];
                try {
                    $stEC = $dbEnd->query('DESCRIBE enderecos');
                    $endCols = $stEC ? ($stEC->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
                } catch (\Throwable $e) { $endCols = []; }

                if (!empty($endCols)) {
                    $userCol = in_array('usuario_id', $endCols, true) ? 'usuario_id' : (in_array('user_id', $endCols, true) ? 'user_id' : '');
                    if ($userCol !== '') {
                        $stEnd = $dbEnd->prepare('SELECT * FROM enderecos WHERE ' . $userCol . ' = ? ORDER BY id DESC LIMIT 1');
                        $stEnd->execute([$usuarioId]);
                        $endereco = $stEnd->fetch(\PDO::FETCH_ASSOC) ?: [];

                        if (!empty($endereco)) {
                            $street = trim((string) ($endereco['endereco'] ?? ($endereco['logradouro'] ?? ($endereco['street'] ?? ''))));
                            $number = trim((string) ($endereco['numero'] ?? ($endereco['number'] ?? '')));
                            $district = trim((string) ($endereco['bairro'] ?? ($endereco['district'] ?? ($endereco['neighborhood'] ?? ''))));
                            $city = trim((string) ($endereco['cidade'] ?? ($endereco['city'] ?? '')));
                            $state = trim((string) ($endereco['estado'] ?? ($endereco['uf'] ?? ($endereco['state'] ?? ''))));
                            $zipCode = preg_replace('/\D+/', '', (string) ($endereco['cep'] ?? ($endereco['zip_code'] ?? ($endereco['zipcode'] ?? ''))));
                            $complement = trim((string) ($endereco['complemento'] ?? ($endereco['complement'] ?? '')));

                            if ($street !== '' && $city !== '' && $state !== '' && $zipCode !== '') {
                                $customerData['address'] = [
                                    'street' => $street,
                                    'number' => $number !== '' ? $number : 'S/N',
                                    'district' => $district !== '' ? $district : 'Centro',
                                    'city' => $city,
                                    'state' => $state,
                                    'zip_code' => $zipCode,
                                ];
                                if ($complement !== '') {
                                    $customerData['address']['complement'] = $complement;
                                }
                            }
                        }
                    }
                }
            } catch (\Throwable $e) {}

            // Fallback: se não encontrou endereço no banco, usar endereço genérico para não bloquear o pagamento
            if (empty($customerData['address'])) {
                // Tentar buscar da tabela usuarios (alguns sistemas guardam endereço direto no usuário)
                try {
                    $dbU = \Config\Database::getConnection();
                    $stU = $dbU->prepare('SELECT * FROM usuarios WHERE id = ? LIMIT 1');
                    $stU->execute([$usuarioId]);
                    $uRow = $stU->fetch(\PDO::FETCH_ASSOC) ?: [];

                    $street = trim((string) ($uRow['endereco'] ?? ($uRow['logradouro'] ?? ($uRow['street'] ?? ''))));
                    $number = trim((string) ($uRow['numero'] ?? ($uRow['number'] ?? '')));
                    $district = trim((string) ($uRow['bairro'] ?? ($uRow['district'] ?? '')));
                    $city = trim((string) ($uRow['cidade'] ?? ($uRow['city'] ?? '')));
                    $state = trim((string) ($uRow['estado'] ?? ($uRow['uf'] ?? ($uRow['state'] ?? ''))));
                    $zipCode = preg_replace('/\D+/', '', (string) ($uRow['cep'] ?? ($uRow['zip_code'] ?? '')));

                    if ($street !== '' && $city !== '' && $state !== '' && $zipCode !== '') {
                        $customerData['address'] = [
                            'street' => $street,
                            'number' => $number !== '' ? $number : 'S/N',
                            'district' => $district !== '' ? $district : 'Centro',
                            'city' => $city,
                            'state' => $state,
                            'zip_code' => $zipCode,
                        ];
                    }
                } catch (\Throwable $e) {}
            }

            if ($metodo === 'card') {
                // Cartão continua via Stripe
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
                $pi = $this->paymentService->createStripePaymentIntentCarteiraRecargaCardBrl($recargaId, $valorBrl, $descricao, $stripeCustomer);

                if (empty($pi['success'])) {
                    $this->json(['success' => false, 'error' => (string) ($pi['error'] ?? 'Falha ao iniciar pagamento')], 500);
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
                    'gateway' => 'stripe',
                    'payment_intent_id' => $paymentIntentId,
                    'client_secret' => (string) ($pi['client_secret'] ?? ''),
                    'pix' => null,
                ]);
            } else {
                // PIX via Câmbio Real (conta de produtos) — sem taxa adicional
                $pi = $this->paymentService->createCambioRealPixCarteiraRecarga($recargaId, $valorBrl, $descricao, $customerData);

                if (empty($pi['success'])) {
                    $this->json(['success' => false, 'error' => (string) ($pi['error'] ?? 'Falha ao gerar PIX')], 500);
                    return;
                }

                $paymentId = (string) ($pi['payment_id'] ?? '');
                if ($paymentId !== '') {
                    $stmtUp = $db->prepare("UPDATE carteira_recargas SET gateway = 'cambioreal', payment_id = :pid, invoice_url = :url, status = 'pending', updated_at = NOW() WHERE id = :id");
                    $stmtUp->execute([':pid' => $paymentId, ':url' => (string) ($pi['invoice_url'] ?? ''), ':id' => $recargaId]);
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
                    'gateway' => 'cambioreal',
                    'payment_intent_id' => $paymentId,
                    'client_secret' => '',
                    'pix' => (isset($pi['pix']) && is_array($pi['pix'])) ? $pi['pix'] : null,
                ]);
            }
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

            // Se gateway é Câmbio Real e status ainda pendente, verificar na API
            if ($status === 'pending' && strtolower(trim((string) ($row['gateway'] ?? ''))) === 'cambioreal') {
                $paymentId = (string) ($row['payment_id'] ?? '');
                if ($paymentId !== '') {
                    try {
                        $crStatus = $this->paymentService->checkCambioRealPaymentStatus($paymentId);
                        if (!empty($crStatus['paid'])) {
                            $status = 'paid';
                            $db->prepare("UPDATE carteira_recargas SET status = 'paid', paid_at = NOW(), updated_at = NOW() WHERE id = ? AND status = 'pending'")->execute([$recargaId]);
                            $row['paid_at'] = date('Y-m-d H:i:s');
                            // Creditar carteira
                            $this->creditarCarteira($db, $row);
                        }
                    } catch (\Exception $e) {
                        // Silenciar — polling vai tentar de novo
                    }
                }
            }

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

    /**
     * Credita a carteira do usuário quando o pagamento é confirmado via polling.
     */
    private function creditarCarteira(\PDO $db, array $row): void {
        try {
            $tipoRecarga = strtolower(trim((string) ($row['tipo_recarga'] ?? 'normal')));
            $recargaId = (int) ($row['id'] ?? 0);
            $usuarioId = (int) ($row['usuario_id'] ?? 0);
            $moeda = strtoupper(trim((string) ($row['moeda'] ?? 'USD')));
            $valor = (float) ($row['valor'] ?? 0);

            if ($recargaId <= 0 || $usuarioId <= 0 || $valor <= 0) {
                return;
            }
            if (!in_array($moeda, ['BRL', 'USD'], true)) {
                $moeda = 'USD';
            }

            $saldoCol = ($moeda === 'BRL') ? 'saldo_brl' : 'saldo_usd';
            $valorCol = ($moeda === 'BRL') ? 'valor_brl' : 'valor_usd';
            $saldoBloqCol = ($moeda === 'BRL') ? 'saldo_brl_bloqueado' : 'saldo_usd_bloqueado';
            $isTurbo = ($tipoRecarga === 'turbo');

            // Garantir que a carteira existe
            $stChkCart = $db->prepare('SELECT id FROM carteiras WHERE usuario_id = ? LIMIT 1');
            $stChkCart->execute([$usuarioId]);
            if (!$stChkCart->fetchColumn()) {
                $db->prepare('INSERT INTO carteiras (usuario_id, saldo_usd, saldo_brl, saldo_usd_bloqueado, saldo_brl_bloqueado, created_at, updated_at) VALUES (?, 0, 0, 0, 0, NOW(), NOW())')->execute([$usuarioId]);
            }

            // Verificar se já foi creditado (evitar duplicidade)
            $stmtChk = $db->prepare("SELECT id FROM transacoes_carteira WHERE usuario_id = :uid AND tipo = 'credito' AND descricao LIKE :desc LIMIT 1");
            $stmtChk->execute([
                ':uid' => $usuarioId,
                ':desc' => '%#' . $recargaId . '%',
            ]);
            if ((int) ($stmtChk->fetchColumn() ?: 0) > 0) {
                return; // Já creditado
            }

            // Creditar saldo
            $sqlUpd = 'UPDATE carteiras SET ' . $saldoCol . ' = ' . $saldoCol . ' + :valor';
            if ($isTurbo) {
                $sqlUpd .= ', ' . $saldoBloqCol . ' = ' . $saldoBloqCol . ' + :valor';
            }
            $sqlUpd .= ', updated_at = NOW() WHERE usuario_id = :uid';
            $stmtUpd = $db->prepare($sqlUpd);
            $stmtUpd->execute([':valor' => $valor, ':uid' => $usuarioId]);

            // Registrar transação
            $modalidadeLabel = $isTurbo ? 'Turbo' : 'Normal';
            $desc = 'Recarga Clube Braziliana — ' . $modalidadeLabel . ' #' . $recargaId;
            $txCols = 'usuario_id, tipo, ' . $valorCol . ', descricao, created_at';
            $txVals = ':uid, \'credito\', :valor, :desc, NOW()';
            $txParams = [':uid' => $usuarioId, ':valor' => $valor, ':desc' => $desc];

            try {
                $colsList = [];
                $stCols = $db->query('DESCRIBE transacoes_carteira');
                $colsList = $stCols ? ($stCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
                if (in_array('modalidade', $colsList, true)) {
                    $txCols .= ', modalidade';
                    $txVals .= ', :modalidade';
                    $txParams[':modalidade'] = $tipoRecarga;
                }
                if (in_array('recarga_id', $colsList, true)) {
                    $txCols .= ', recarga_id';
                    $txVals .= ', :recarga_id';
                    $txParams[':recarga_id'] = $recargaId;
                }
            } catch (\Exception $e) {}

            $stmtTx = $db->prepare('INSERT INTO transacoes_carteira (' . $txCols . ') VALUES (' . $txVals . ')');
            $stmtTx->execute($txParams);

            // Se é turbo, definir locked_until (6 meses)
            if ($isTurbo) {
                $db->prepare("UPDATE carteira_recargas SET locked_until = COALESCE(locked_until, DATE_ADD(NOW(), INTERVAL 6 MONTH)), updated_at = NOW() WHERE id = ?")->execute([$recargaId]);
            }
        } catch (\Exception $e) {
            error_log('[ClubeRecarga] Erro ao creditar carteira: ' . $e->getMessage());
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
