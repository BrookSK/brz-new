<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;
use App\Services\CpfValidator;
use App\Services\EmailService;
use App\Services\WordPressDbService;
use App\Models\Usuario;

class AuthController extends Controller {
    private $authService;
    private $usuarioModel;
    private $wpDbService;
    
    public function __construct() {
        $this->authService = new AuthService();
        $this->usuarioModel = new Usuario();
        $this->wpDbService = new WordPressDbService();
    }

    private function getEmailSenderConfig(): array {
        $fromEmail = 'noreply@brazilianashop.com.br';
        $fromName = 'Braziliana';

        try {
            $pdo = $this->usuarioModel->getConnection();

            $stmt = $pdo->prepare("SHOW TABLES LIKE 'configuracoes_sistema'");
            $stmt->execute();
            if (!$stmt->fetchColumn()) {
                return ['fromEmail' => $fromEmail, 'fromName' => $fromName];
            }

            $stmtCols = $pdo->query('DESCRIBE configuracoes_sistema');
            $cols = $stmtCols ? ($stmtCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];

            if (is_array($cols) && in_array('categoria', $cols, true) && in_array('chave', $cols, true) && in_array('valor', $cols, true)) {
                $st = $pdo->prepare('SELECT valor FROM configuracoes_sistema WHERE categoria = ? AND chave = ? LIMIT 1');
                $st->execute(['email', 'from']);
                $v = $st->fetchColumn();
                if (is_string($v) && trim($v) !== '' && filter_var(trim($v), FILTER_VALIDATE_EMAIL)) {
                    $fromEmail = trim($v);
                }
                $st->execute(['email', 'from_name']);
                $v = $st->fetchColumn();
                if (is_string($v) && trim($v) !== '') {
                    $fromName = trim($v);
                }
            } else {
                $st = $pdo->prepare('SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1');
                $st->execute(['email_from']);
                $v = $st->fetchColumn();
                if (is_string($v) && trim($v) !== '' && filter_var(trim($v), FILTER_VALIDATE_EMAIL)) {
                    $fromEmail = trim($v);
                }
                $st->execute(['email_from_name']);
                $v = $st->fetchColumn();
                if (is_string($v) && trim($v) !== '') {
                    $fromName = trim($v);
                }
            }
        } catch (\Exception $e) {
        }

        return ['fromEmail' => $fromEmail, 'fromName' => $fromName];
    }

    private function getEmailConfig(): array {
        $cfg = [
            'driver' => 'mail',
            'host' => '',
            'port' => '587',
            'username' => '',
            'password' => '',
            'encryption' => 'tls',
        ];

        try {
            $pdo = $this->usuarioModel->getConnection();
            $stmt = $pdo->prepare("SHOW TABLES LIKE 'configuracoes_sistema'");
            $stmt->execute();
            if (!$stmt->fetchColumn()) {
                return $cfg;
            }

            $stmtCols = $pdo->query('DESCRIBE configuracoes_sistema');
            $cols = $stmtCols ? ($stmtCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];

            if (is_array($cols) && in_array('categoria', $cols, true) && in_array('chave', $cols, true) && in_array('valor', $cols, true)) {
                $st = $pdo->prepare('SELECT valor FROM configuracoes_sistema WHERE categoria = ? AND chave = ? LIMIT 1');
                foreach (['driver', 'host', 'port', 'username', 'password', 'encryption'] as $k) {
                    $st->execute(['email', $k]);
                    $v = $st->fetchColumn();
                    if ($v !== false && $v !== null && trim((string) $v) !== '') {
                        $cfg[$k] = (string) $v;
                    }
                }
            } else {
                $st = $pdo->prepare('SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1');
                $map = [
                    'driver' => ['email_driver'],
                    'host' => ['email_host', 'smtp_host'],
                    'port' => ['email_port', 'smtp_port'],
                    'username' => ['email_username', 'smtp_usuario', 'smtp_user', 'smtp_username'],
                    'password' => ['email_password', 'smtp_senha', 'smtp_pass', 'smtp_password'],
                    'encryption' => ['email_encryption', 'smtp_criptografia', 'smtp_secure', 'smtp_encryption'],
                ];
                foreach ($map as $k => $cands) {
                    foreach ($cands as $cand) {
                        $st->execute([$cand]);
                        $v = $st->fetchColumn();
                        if ($v !== false && $v !== null && trim((string) $v) !== '') {
                            $cfg[$k] = (string) $v;
                            break;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
        }

        return $cfg;
    }

    private function smtpReadLine($fp): string {
        $data = '';
        while (!feof($fp)) {
            $line = fgets($fp, 515);
            if ($line === false) {
                break;
            }
            $data .= $line;
            if (strlen($line) < 4) {
                break;
            }
            if (preg_match('/^\d{3} /', $line)) {
                break;
            }
        }
        return $data;
    }

    private function smtpExpect($fp, array $codes): string {
        $resp = $this->smtpReadLine($fp);
        $code = (int) substr(trim($resp), 0, 3);
        if (!in_array($code, $codes, true)) {
            throw new \Exception('SMTP resposta inesperada: ' . trim($resp));
        }
        return $resp;
    }

    private function smtpCmd($fp, string $cmd, array $okCodes): string {
        fwrite($fp, $cmd . "\r\n");
        return $this->smtpExpect($fp, $okCodes);
    }

    private function sendSmtpEmail(array $cfg, string $to, string $subject, string $html, string $fromEmail, string $fromName): void {
        $host = (string) ($cfg['host'] ?? '');
        $port = (int) ((string) ($cfg['port'] ?? '587'));
        $user = (string) ($cfg['username'] ?? '');
        $pass = (string) ($cfg['password'] ?? '');
        $enc = strtolower(trim((string) ($cfg['encryption'] ?? 'tls')));

        if ($host === '' || $port <= 0) {
            throw new \Exception('SMTP host/porta não configurados');
        }

        $remote = $host;
        $crypto = false;
        if ($enc === 'ssl') {
            $remote = 'ssl://' . $host;
            $crypto = true;
        }

        $fp = @fsockopen($remote, $port, $errno, $errstr, 15);
        if (!$fp) {
            throw new \Exception('Falha ao conectar no SMTP: ' . $errstr . ' (' . $errno . ')');
        }
        stream_set_timeout($fp, 15);

        try {
            $this->smtpExpect($fp, [220]);

            $localhost = 'localhost';
            $this->smtpCmd($fp, 'EHLO ' . $localhost, [250]);

            if ($enc === 'tls' && !$crypto) {
                $this->smtpCmd($fp, 'STARTTLS', [220]);
                $ok = @stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                if ($ok !== true) {
                    throw new \Exception('Falha ao iniciar STARTTLS');
                }
                $this->smtpCmd($fp, 'EHLO ' . $localhost, [250]);
            }

            if ($user !== '') {
                $this->smtpCmd($fp, 'AUTH LOGIN', [334]);
                $this->smtpCmd($fp, base64_encode($user), [334]);
                $this->smtpCmd($fp, base64_encode($pass), [235]);
            }

            $this->smtpCmd($fp, 'MAIL FROM:<' . $fromEmail . '>', [250]);
            $this->smtpCmd($fp, 'RCPT TO:<' . $to . '>', [250, 251]);
            $this->smtpCmd($fp, 'DATA', [354]);

            $headers = [];
            $headers[] = 'From: ' . $this->encodeEmailHeaderName($fromName) . ' <' . $fromEmail . '>';
            $headers[] = 'To: <' . $to . '>';
            $headers[] = 'Subject: ' . $this->encodeEmailHeaderName($subject);
            $headers[] = 'MIME-Version: 1.0';
            $headers[] = 'Content-Type: text/html; charset=UTF-8';

            $data = implode("\r\n", $headers) . "\r\n\r\n" . $html;
            $data = str_replace("\r\n.", "\r\n..", $data);

            fwrite($fp, $data . "\r\n.\r\n");
            $this->smtpExpect($fp, [250]);
            $this->smtpCmd($fp, 'QUIT', [221]);
        } finally {
            fclose($fp);
        }
    }

    private function sendEmail(string $to, string $subject, string $html): void {
        $to = trim($to);
        if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            return;
        }

        $sender = $this->getEmailSenderConfig();
        $fromEmail = (string) ($sender['fromEmail'] ?? 'noreply@brazilianashop.com.br');
        $fromName = (string) ($sender['fromName'] ?? 'Braziliana');

        $cfg = $this->getEmailConfig();
        $driver = strtolower(trim((string) ($cfg['driver'] ?? 'mail')));

        if ($driver === 'smtp') {
            $this->sendSmtpEmail($cfg, $to, $subject, $html, $fromEmail, $fromName);
            return;
        }

        $headers = [];
        $headers[] = 'MIME-Version: 1.0';
        $headers[] = 'Content-Type: text/html; charset=UTF-8';
        $headers[] = 'From: ' . $this->encodeEmailHeaderName($fromName) . ' <' . $fromEmail . '>';
        $headers[] = 'Reply-To: ' . $fromEmail;
        @mail($to, $subject, $html, implode("\r\n", $headers));
    }

    private function getConfigValue(string $categoria, string $chave, string $default = ''): string {
        $out = $default;
        try {
            $pdo = $this->usuarioModel->getConnection();
            $stmt = $pdo->prepare("SHOW TABLES LIKE 'configuracoes_sistema'");
            $stmt->execute();
            if (!$stmt->fetchColumn()) {
                return $out;
            }

            $stmtCols = $pdo->query('DESCRIBE configuracoes_sistema');
            $cols = $stmtCols ? ($stmtCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];

            if (is_array($cols) && in_array('categoria', $cols, true) && in_array('chave', $cols, true) && in_array('valor', $cols, true)) {
                $st = $pdo->prepare('SELECT valor FROM configuracoes_sistema WHERE categoria = ? AND chave = ? LIMIT 1');
                $st->execute([$categoria, $chave]);
                $v = $st->fetchColumn();
                if ($v !== false && $v !== null) {
                    $out = (string) $v;
                }
            } else {
                $fullKey = $categoria . '_' . $chave;
                $st = $pdo->prepare('SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1');
                $st->execute([$fullKey]);
                $v = $st->fetchColumn();
                if ($v !== false && $v !== null) {
                    $out = (string) $v;
                }
            }
        } catch (\Exception $e) {
        }

        $out = trim((string) $out);
        return $out !== '' ? $out : $default;
    }

    private function getSiteBranding(): array {
        $siteName = $this->getConfigValue('loja', 'nome', 'Braziliana');
        $logoUrl = $this->getConfigValue('layout', 'logo', '');

        $host = trim((string) ($_SERVER['HTTP_HOST'] ?? ''));
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $siteId = $host !== '' ? ($scheme . '://' . $host) : '';

        return [
            'site_name' => $siteName,
            'logo_url' => $logoUrl,
            'site_id' => $siteId,
        ];
    }

    private function buildBrandedEmailHtml(string $title, string $bodyHtml, string $ctaText, string $ctaUrl, string $note): string {
        $b = $this->getSiteBranding();
        $siteName = htmlspecialchars((string) ($b['site_name'] ?? 'Braziliana'), ENT_QUOTES, 'UTF-8');
        $logoUrl = trim((string) ($b['logo_url'] ?? ''));
        $safeLogoUrl = $logoUrl !== '' ? htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8') : '';
        $siteId = trim((string) ($b['site_id'] ?? ''));
        $safeSiteId = $siteId !== '' ? htmlspecialchars($siteId, ENT_QUOTES, 'UTF-8') : '';

        $safeTitle = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
        $safeCtaText = htmlspecialchars($ctaText, ENT_QUOTES, 'UTF-8');
        $safeCtaUrl = htmlspecialchars($ctaUrl, ENT_QUOTES, 'UTF-8');
        $safeNote = htmlspecialchars($note, ENT_QUOTES, 'UTF-8');

        $logoBlock = '';
        if ($safeLogoUrl !== '') {
            $logoBlock = '<img src="' . $safeLogoUrl . '" alt="' . $siteName . '" style="display:block;max-width:160px;max-height:48px;height:auto;width:auto;">';
        } else {
            $logoBlock = '<div style="font-size:18px;font-weight:700;color:#0b1f3a;">' . $siteName . '</div>';
        }

        $siteIdBlock = $safeSiteId !== '' ? ('<div style="margin-top:4px;font-size:12px;color:#6c757d;">' . $safeSiteId . '</div>') : '';

        return '<!doctype html>'
            . '<html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>' . $safeTitle . '</title></head>'
            . '<body style="margin:0;padding:0;background:#f6f7fb;font-family:Arial,Helvetica,sans-serif;">'
            . '<div style="width:100%;padding:24px 12px;">'
            . '<div style="max-width:640px;margin:0 auto;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e9ecef;">'
            . '<div style="padding:20px 24px;background:#ffffff;border-bottom:1px solid #eef1f4;">'
            . $logoBlock
            . $siteIdBlock
            . '</div>'
            . '<div style="padding:24px;">'
            . '<h1 style="margin:0 0 12px 0;font-size:20px;color:#0b1f3a;">' . $safeTitle . '</h1>'
            . '<div style="font-size:14px;line-height:1.6;color:#212529;">' . $bodyHtml . '</div>'
            . '<div style="margin:18px 0 10px 0;">'
            . '<a href="' . $safeCtaUrl . '" style="display:inline-block;background:#0b1f3a;color:#ffffff;text-decoration:none;padding:12px 16px;border-radius:10px;font-weight:700;">' . $safeCtaText . '</a>'
            . '</div>'
            . '<div style="font-size:12px;color:#6c757d;line-height:1.5;margin-top:10px;">'
            . $safeNote
            . '<br>Se o botão não funcionar, copie e cole este link no navegador:<br>'
            . '<a href="' . $safeCtaUrl . '" style="color:#0b1f3a;">' . $safeCtaUrl . '</a>'
            . '</div>'
            . '</div>'
            . '<div style="padding:14px 24px;background:#f8f9fa;border-top:1px solid #eef1f4;font-size:12px;color:#6c757d;">'
            . '© ' . date('Y') . ' ' . $siteName
            . '</div>'
            . '</div>'
            . '</div>'
            . '</body></html>';
    }

    private function encodeEmailHeaderName(string $name): string {
        $n = trim($name);
        if ($n === '') {
            return $n;
        }
        return '=?UTF-8?B?' . base64_encode($n) . '?=';
    }

    private function tryWpRecadastroFlow(string $email, bool $isAjax = false): bool {
        $email = strtolower(trim((string) $email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return false;
        }

        $cols = [];
        try {
            $stmtCols = $this->usuarioModel->getConnection()->query('DESCRIBE usuarios');
            $cols = $stmtCols ? $stmtCols->fetchAll(\PDO::FETCH_COLUMN) : [];
        } catch (\Exception $e) {
            $cols = [];
        }

        $local = null;
        try {
            $local = $this->usuarioModel->findByEmail($email);
        } catch (\Exception $e) {
            return false;
        }

        // Se o usuário já existe localmente, só roda o fluxo de migração se ainda não fez o primeiro acesso
        // (ex.: nunca logou / precisa recadastro). Caso contrário, mantém comportamento padrão (senha incorreta).
        if (is_array($local) && !empty($local)) {
            $precisaRecadastro = !empty($local['precisa_recadastro']);
            $hasUltimoLogin = is_array($cols) && in_array('ultimo_login', $cols, true);
            $ultimoLogin = $hasUltimoLogin ? trim((string) ($local['ultimo_login'] ?? '')) : '';
            $nuncaLogou = $hasUltimoLogin ? ($ultimoLogin === '' || $ultimoLogin === '0000-00-00 00:00:00') : $precisaRecadastro;

            if (!$precisaRecadastro && !$nuncaLogou) {
                return false;
            }
        }

        $wpUser = null;
        try {
            $wpUser = $this->wpDbService->findUserByEmailPriority($email, ['br', 'red', 'us']);
        } catch (\Exception $e) {
            $wpUser = null;
        }

        if (!is_array($wpUser) || empty($wpUser['id'])) {
            return false;
        }

        $wpProfile = [];
        try {
            $wpProfile = $this->wpDbService->getNormalizedUserProfile((int) ($wpUser['id'] ?? 0), (string) ($wpUser['source'] ?? 'br'));
        } catch (\Exception $e) {
            $wpProfile = [];
        }

        $nome = trim((string) ($wpUser['display_name'] ?? ''));
        if ($nome === '') {
            $nome = trim((string) ($wpUser['login'] ?? ''));
        }
        if ($nome === '') {
            $nome = 'Cliente';
        }


        $userId = 0;

        // Se já existe localmente, não cria outro usuário: apenas atualiza flags/origem.
        if (is_array($local) && !empty($local['id'])) {
            $userId = (int) $local['id'];
            $upd = [];
            if (is_array($cols) && in_array('precisa_recadastro', $cols, true)) {
                $upd['precisa_recadastro'] = 1;
            }
            if (is_array($cols) && in_array('wp_origem', $cols, true)) {
                $upd['wp_origem'] = (string) ($wpUser['source'] ?? 'br');
            }
            if (is_array($cols) && in_array('wp_user_id', $cols, true)) {
                $upd['wp_user_id'] = (int) ($wpUser['id'] ?? 0);
            }

            // Preencher dados do WP apenas se o campo local estiver vazio
            if (is_array($wpProfile) && !empty($wpProfile)) {
                $get = static function(string $k) use ($wpProfile): string {
                    $v = $wpProfile[$k] ?? '';
                    return is_string($v) ? trim($v) : '';
                };

                $first = $get('first_name');
                $last = $get('last_name');
                $fullName = trim($first . ' ' . $last);
                if (is_array($cols) && in_array('nome', $cols, true)) {
                    $cur = trim((string) ($local['nome'] ?? ''));
                    if ($cur === '' || strtolower($cur) === 'cliente') {
                        if ($fullName !== '') {
                            $upd['nome'] = $fullName;
                        } else {
                            $fallback = trim((string) ($wpUser['display_name'] ?? ''));
                            if ($fallback === '') {
                                $fallback = trim((string) ($wpUser['login'] ?? ''));
                            }
                            if ($fallback !== '') {
                                $upd['nome'] = $fallback;
                            }
                        }
                    }
                }

                $doc = preg_replace('/\D+/', '', $get('cpf'));
                if ($doc !== '' && is_array($cols) && in_array('documento', $cols, true)) {
                    $cur = preg_replace('/\D+/', '', (string) ($local['documento'] ?? ''));
                    if ($cur === '') {
                        $upd['documento'] = $doc;
                    }
                }

                $phone = $get('phone');
                if ($phone !== '' && is_array($cols) && in_array('telefone', $cols, true)) {
                    $cur = trim((string) ($local['telefone'] ?? ''));
                    if ($cur === '') {
                        $upd['telefone'] = $phone;
                    }
                }

                $birth = $get('birth_date');
                if ($birth !== '' && is_array($cols) && in_array('data_nascimento', $cols, true)) {
                    $cur = trim((string) ($local['data_nascimento'] ?? ''));
                    if ($cur === '' || $cur === '0000-00-00') {
                        $norm = '';
                        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth)) {
                            $norm = $birth;
                        } elseif (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $birth, $m)) {
                            $norm = $m[3] . '-' . $m[2] . '-' . $m[1];
                        }
                        if ($norm !== '') {
                            $upd['data_nascimento'] = $norm;
                        }
                    }
                }

                $country = strtoupper($get('country'));
                if ($country !== '' && is_array($cols) && in_array('pais_residencia', $cols, true)) {
                    $cur = strtoupper(trim((string) ($local['pais_residencia'] ?? '')));
                    if ($cur === '') {
                        $upd['pais_residencia'] = substr($country, 0, 2);
                    }
                }

                $postcode = preg_replace('/\D+/', '', $get('postcode'));
                $addr1 = $get('address_1');
                $num = $get('number');
                $addr2 = $get('address_2');
                $bairro = $get('neighborhood');
                $city = $get('city');
                $state = $get('state');

                if ($postcode !== '' && is_array($cols) && in_array('cep', $cols, true)) {
                    $cur = preg_replace('/\D+/', '', (string) ($local['cep'] ?? ''));
                    if ($cur === '') {
                        $upd['cep'] = $postcode;
                    }
                }
                if ($addr1 !== '' && is_array($cols) && in_array('endereco', $cols, true)) {
                    $cur = trim((string) ($local['endereco'] ?? ''));
                    if ($cur === '') {
                        $upd['endereco'] = $addr1;
                    }
                }
                if ($num !== '' && is_array($cols) && in_array('numero', $cols, true)) {
                    $cur = trim((string) ($local['numero'] ?? ''));
                    if ($cur === '') {
                        $upd['numero'] = $num;
                    }
                }
                if ($addr2 !== '' && is_array($cols) && in_array('complemento', $cols, true)) {
                    $cur = trim((string) ($local['complemento'] ?? ''));
                    if ($cur === '') {
                        $upd['complemento'] = $addr2;
                    }
                }
                if ($bairro !== '' && is_array($cols) && in_array('bairro', $cols, true)) {
                    $cur = trim((string) ($local['bairro'] ?? ''));
                    if ($cur === '') {
                        $upd['bairro'] = $bairro;
                    }
                }
                if ($city !== '' && is_array($cols) && in_array('cidade', $cols, true)) {
                    $cur = trim((string) ($local['cidade'] ?? ''));
                    if ($cur === '') {
                        $upd['cidade'] = $city;
                    }
                }
                if ($state !== '' && is_array($cols) && in_array('estado', $cols, true)) {
                    $cur = trim((string) ($local['estado'] ?? ''));
                    if ($cur === '') {
                        $upd['estado'] = $state;
                    }
                }
            }

            if (!empty($upd)) {
                try {
                    $this->usuarioModel->update($userId, $upd);
                } catch (\Exception $e) {
                }
            }
        } else {
            $data = [
                'nome' => $nome,
                'email' => $email,
                'perfil' => 'cliente',
                'senha' => bin2hex(random_bytes(16)),
            ];

            // Dados vindos do WP (somente se existirem colunas locais)
            if (is_array($wpProfile) && !empty($wpProfile) && is_array($cols)) {
                $get = static function(string $k) use ($wpProfile): string {
                    $v = $wpProfile[$k] ?? '';
                    return is_string($v) ? trim($v) : '';
                };

                $first = $get('first_name');
                $last = $get('last_name');
                $fullName = trim($first . ' ' . $last);
                if (in_array('nome', $cols, true)) {
                    if ($fullName !== '') {
                        $data['nome'] = $fullName;
                    } else {
                        $fallback = trim((string) ($wpUser['display_name'] ?? ''));
                        if ($fallback === '') {
                            $fallback = trim((string) ($wpUser['login'] ?? ''));
                        }
                        if ($fallback !== '') {
                            $data['nome'] = $fallback;
                        }
                    }
                }

                $doc = preg_replace('/\D+/', '', $get('cpf'));
                if ($doc !== '' && in_array('documento', $cols, true)) {
                    $data['documento'] = $doc;
                }

                $phone = $get('phone');
                if ($phone !== '' && in_array('telefone', $cols, true)) {
                    $data['telefone'] = $phone;
                }

                $birth = $get('birth_date');
                if ($birth !== '' && in_array('data_nascimento', $cols, true)) {
                    $norm = '';
                    if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $birth)) {
                        $norm = $birth;
                    } elseif (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $birth, $m)) {
                        $norm = $m[3] . '-' . $m[2] . '-' . $m[1];
                    }
                    if ($norm !== '') {
                        $data['data_nascimento'] = $norm;
                    }
                }

                $country = strtoupper($get('country'));
                if ($country !== '' && in_array('pais_residencia', $cols, true)) {
                    $data['pais_residencia'] = substr($country, 0, 2);
                }

                $postcode = preg_replace('/\D+/', '', $get('postcode'));
                $addr1 = $get('address_1');
                $num = $get('number');
                $addr2 = $get('address_2');
                $bairro = $get('neighborhood');
                $city = $get('city');
                $state = $get('state');

                if ($postcode !== '' && in_array('cep', $cols, true)) {
                    $data['cep'] = $postcode;
                }
                if ($addr1 !== '' && in_array('endereco', $cols, true)) {
                    $data['endereco'] = $addr1;
                }
                if ($num !== '' && in_array('numero', $cols, true)) {
                    $data['numero'] = $num;
                }
                if ($addr2 !== '' && in_array('complemento', $cols, true)) {
                    $data['complemento'] = $addr2;
                }
                if ($bairro !== '' && in_array('bairro', $cols, true)) {
                    $data['bairro'] = $bairro;
                }
                if ($city !== '' && in_array('cidade', $cols, true)) {
                    $data['cidade'] = $city;
                }
                if ($state !== '' && in_array('estado', $cols, true)) {
                    $data['estado'] = $state;
                }
            }

            if (is_array($cols) && in_array('precisa_recadastro', $cols, true)) {
                $data['precisa_recadastro'] = 1;
            }
            if (is_array($cols) && in_array('wp_origem', $cols, true)) {
                $data['wp_origem'] = (string) ($wpUser['source'] ?? 'br');
            }
            if (is_array($cols) && in_array('wp_user_id', $cols, true)) {
                $data['wp_user_id'] = (int) ($wpUser['id'] ?? 0);
            }

            try {
                $userId = (int) $this->usuarioModel->create($data);
            } catch (\Exception $e) {
                $userId = 0;
            }
        }

        if ($userId <= 0) {
            return false;
        }

        $token = '';
        try {
            $token = $this->usuarioModel->requestPasswordResetToken($email);
        } catch (\Exception $e) {
            $token = '';
        }
        if ($token === '') {
            return false;
        }

        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
        $base = $host !== '' ? ($scheme . '://' . $host) : '';
        $link = ($base !== '' ? $base : '') . '/redefinir-senha/' . rawurlencode($token);

        $subject = 'Atualização cadastral - Braziliana';
        $body = '<p style="margin:0 0 14px 0;">Olá,</p>'
            . '<p style="margin:0 0 14px 0;">A <strong>Braziliana</strong> está de cara nova — com novidades e melhorias para sua experiência de compra.</p>'
            . '<p style="margin:0 0 14px 0;">Para manter sua conta ativa e garantir mais segurança, precisamos que você finalize a <strong>atualização cadastral</strong> e crie uma nova senha de acesso.</p>'
            . '<p style="margin:0;">Clique no botão abaixo para definir sua nova senha e entrar no sistema.</p>';
        $html = $this->buildBrandedEmailHtml(
            'Atualização cadastral',
            $body,
            'Definir nova senha',
            $link,
            'Este link expira em 1 hora.'
        );

        try {
            $this->sendEmail($email, $subject, $html);
        } catch (\Exception $e) {
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $recoveryUrl = '/recuperar-senha?email=' . rawurlencode($email);
        $_SESSION['message'] = 'Encontramos seu cadastro no site antigo. Enviamos um e-mail para você definir uma nova senha e atualizar seus dados.'
            . ' <a href="' . htmlspecialchars($recoveryUrl, ENT_QUOTES, 'UTF-8') . '">Não recebeu? Enviar novamente</a>';
        $_SESSION['message_type'] = 'warning';

        if ($isAjax) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error' => $_SESSION['message'],
                'action' => 'password_reset_sent',
                'recovery_url' => $recoveryUrl,
            ]);
            return true;
        }

        $this->redirect('/login');
        return true;
    }

    private function normalizeInternalRedirect(string $candidate): string {
        $candidate = trim($candidate);
        if ($candidate === '') {
            return '';
        }
        if ($candidate[0] !== '/') {
            return '';
        }
        if (strpos($candidate, '//') === 0) {
            return '';
        }
        if (stripos($candidate, 'http://') === 0 || stripos($candidate, 'https://') === 0) {
            return '';
        }
        return $candidate;
    }
    
    public function login(Request $request) {
        $redirectTo = (string) ($request->getParam('redirect', '') ?? '');
        if ($redirectTo === '') {
            try {
                $redirectTo = (string) ($_GET['redirect'] ?? '');
            } catch (\Exception $e) {
            }
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if ($redirectTo === '' && isset($_SESSION['redirect_after_login']) && is_string($_SESSION['redirect_after_login'])) {
            $redirectTo = (string) $_SESSION['redirect_after_login'];
        }
        $redirectTo = $this->normalizeInternalRedirect($redirectTo);

        if ($this->authService->estaLogado()) {
            $usuario = $this->authService->getUsuarioLogado();
            $perfil = strtolower(trim((string) ($usuario['perfil'] ?? '')));
            if ($perfil === 'representante') {
                $this->redirect('/meu-painel');
            }
            if ($this->authService->podeAcessarPainelAdmin()) {
                $this->redirect('/admin/dashboard');
            }
            if (isset($_SESSION['redirect_after_login'])) {
                unset($_SESSION['redirect_after_login']);
            }
            $this->redirect($redirectTo !== '' ? $redirectTo : '/minha-conta');
            return;
        }
        
        if ($request->getMethod() === 'POST') {
            $email = $request->getParam('email');
            $senha = $request->getParam('senha');
            $isAdmin = $request->getParam('admin_login') === '1';
            $aceiteTermos = (string) ($request->getParam('consentimento_legal', '') ?? '');
            if ($redirectTo === '') {
                $redirectTo = (string) ($request->getParam('redirect', '') ?? '');
            }
            if ($redirectTo === '' && isset($_SESSION['redirect_after_login']) && is_string($_SESSION['redirect_after_login'])) {
                $redirectTo = (string) $_SESSION['redirect_after_login'];
            }
            $redirectTo = $this->normalizeInternalRedirect((string) $redirectTo);
            $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
            
            try {
                $usuario = $this->authService->autenticar($email, $senha);
                
                if ($usuario) {
                    // Verificar se é login admin
                    if ($isAdmin && !$this->authService->podeAcessarPainelAdmin()) {
                        $_SESSION['message'] = 'Acesso administrativo negado. Usuário não tem permissão de administrador.';
                        $_SESSION['message_type'] = 'danger';
                        $this->redirect('/login');
                        return;
                    }
                    
                    // Redirecionar baseado no perfil
                    if ($isAdmin || $this->authService->podeAcessarPainelAdmin()) {
                        $_SESSION['message'] = 'Bem-vindo, ' . $usuario['nome'] . '!';
                        $_SESSION['message_type'] = 'success';
                        $adminTarget = '/admin/dashboard';
                        if (isset($_SESSION['redirect_after_login'])) {
                            unset($_SESSION['redirect_after_login']);
                        }
                        if ($isAjax) {
                            header('Content-Type: application/json; charset=utf-8');
                            echo json_encode(['success' => true, 'redirect' => $adminTarget]);
                            return;
                        }
                        $this->redirect($adminTarget);
                    } else {
                        $perfil = strtolower(trim((string) ($usuario['perfil'] ?? '')));
                        if ($perfil === 'representante') {
                            if ($isAjax) {
                                header('Content-Type: application/json; charset=utf-8');
                                echo json_encode(['success' => true, 'redirect' => '/meu-painel']);
                                return;
                            }
                            $this->redirect('/meu-painel');
                        }
                        $usuarioCompleto = null;
                        try {
                            $usuarioCompleto = $this->usuarioModel->find((int) ($usuario['id'] ?? 0));
                        } catch (\Exception $e) {
                            $usuarioCompleto = null;
                        }

                        if (is_array($usuarioCompleto) && !empty($usuarioCompleto)) {
                            $precisaSalvarTermos = !$this->usuarioModel->hasAcceptedTerms($usuarioCompleto);
                            if ($precisaSalvarTermos) {
                                if ($aceiteTermos === '' || $aceiteTermos === '0') {
                                    $_SESSION['message'] = 'É necessário aceitar os termos e condições para entrar.';
                                    $_SESSION['message_type'] = 'warning';
                                    if ($isAjax) {
                                        header('Content-Type: application/json; charset=utf-8');
                                        echo json_encode(['success' => false, 'error' => $_SESSION['message']]);
                                        return;
                                    }
                                    $this->redirect('/login');
                                    return;
                                }

                                $colsU = [];
                                try {
                                    $stmtColsU = $this->usuarioModel->getConnection()->query('DESCRIBE usuarios');
                                    $colsU = $stmtColsU ? $stmtColsU->fetchAll(\PDO::FETCH_COLUMN) : [];
                                } catch (\Exception $e) {
                                    $colsU = [];
                                }
                                $upd = [];
                                if (is_array($colsU) && in_array('termos_aceitos_em', $colsU, true)) {
                                    $upd['termos_aceitos_em'] = date('Y-m-d H:i:s');
                                }
                                if (is_array($colsU) && in_array('termos_aceitos_ip', $colsU, true)) {
                                    $upd['termos_aceitos_ip'] = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
                                }
                                if (is_array($colsU) && in_array('termos_versao', $colsU, true)) {
                                    $upd['termos_versao'] = '1.0';
                                }
                                if (!empty($upd)) {
                                    $this->usuarioModel->update((int) $usuarioCompleto['id'], $upd);
                                }
                            }

                            $faltando = $this->usuarioModel->getMissingRequiredFields($usuarioCompleto);
                            if (!empty($faltando)) {
                                $_SESSION['message'] = 'Complete seus dados obrigatórios para continuar: ' . implode(', ', $faltando);
                                $_SESSION['message_type'] = 'warning';
                                $target = '/meus-dados';
                            } else {
                                $target = $redirectTo !== '' ? $redirectTo : '/minha-conta';
                            }
                        } else {
                            $target = $redirectTo !== '' ? $redirectTo : '/minha-conta';
                        }

                        $_SESSION['message'] = 'Bem-vendo de volta, ' . $usuario['nome'] . '!';
                        $_SESSION['message_type'] = 'success';
                        if (isset($_SESSION['redirect_after_login'])) {
                            unset($_SESSION['redirect_after_login']);
                        }
                        if ($isAjax) {
                            header('Content-Type: application/json; charset=utf-8');
                            echo json_encode(['success' => true, 'redirect' => $target]);
                            return;
                        }
                        $this->redirect($target);
                    }
                    return;
                } else {
                    if ($this->tryWpRecadastroFlow((string) $email, $isAjax)) {
                        return;
                    }
                    $_SESSION['message'] = 'E-mail ou senha incorretos';
                    $_SESSION['message_type'] = 'danger';
                    if ($isAjax) {
                        header('Content-Type: application/json; charset=utf-8');
                        echo json_encode(['success' => false, 'error' => $_SESSION['message']]);
                        return;
                    }
                }
            } catch (\Exception $e) {
                $_SESSION['message'] = 'Erro ao fazer login: ' . $e->getMessage();
                $_SESSION['message_type'] = 'danger';
                if ($isAjax) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['success' => false, 'error' => $_SESSION['message']]);
                    return;
                }
            }
            
            $this->redirect('/login');
            return;
        }
        
        $this->view('auth/login');
    }
    
    public function loginAdmin(Request $request) {
        $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

        $redirectTo = (string) ($request->getParam('redirect', '') ?? '');
        if ($redirectTo === '') {
            try {
                $redirectTo = (string) ($_GET['redirect'] ?? '');
            } catch (\Exception $e) {
            }
        }
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if ($redirectTo === '' && isset($_SESSION['redirect_after_login']) && is_string($_SESSION['redirect_after_login'])) {
            $redirectTo = (string) $_SESSION['redirect_after_login'];
        }
        $redirectTo = $this->normalizeInternalRedirect($redirectTo);

        if ($this->authService->estaLogado()) {
            $usuario = $this->authService->getUsuarioLogado();
            if ($this->authService->podeAcessarPainelAdmin()) {
                if (isset($_SESSION['redirect_after_login'])) {
                    unset($_SESSION['redirect_after_login']);
                }
                if ($isAjax) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['success' => true, 'redirect' => '/admin/dashboard']);
                    return;
                }

                $this->redirect('/admin/dashboard');
            } else {
                $_SESSION['message'] = 'Acesso administrativo negado. Usuário não tem permissão de administrador.';
                $_SESSION['message_type'] = 'danger';

                if ($isAjax) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['success' => false, 'error' => $_SESSION['message']]);
                    return;
                }

                $this->redirect('/loginadmin');
            }
            return;
        }
        
        if ($request->getMethod() === 'POST') {
            $email = $request->getParam('email');
            $senha = $request->getParam('senha');
            
            try {
                $usuario = $this->authService->autenticar($email, $senha);
                
                if ($usuario) {
                    // Verificar se é admin
                    if (!$this->authService->podeAcessarPainelAdmin()) {
                        $_SESSION['message'] = 'Acesso administrativo negado. Usuário não tem permissão de administrador.';
                        $_SESSION['message_type'] = 'danger';

                        if ($isAjax) {
                            header('Content-Type: application/json; charset=utf-8');
                            echo json_encode(['success' => false, 'error' => $_SESSION['message']]);
                            return;
                        }

                        $this->redirect('/loginadmin');
                        return;
                    }
                    
                    $_SESSION['message'] = 'Bem-vindo, ' . $usuario['nome'] . '! Acesso administrativo.';
                    $_SESSION['message_type'] = 'success';

                    $adminTarget = $redirectTo !== '' ? $redirectTo : '/admin/dashboard';
                    if (isset($_SESSION['redirect_after_login'])) {
                        unset($_SESSION['redirect_after_login']);
                    }

                    if ($isAjax) {
                        header('Content-Type: application/json; charset=utf-8');
                        echo json_encode(['success' => true, 'redirect' => $adminTarget]);
                        return;
                    }

                    $this->redirect($adminTarget);
                    return;
                } else {
                    $_SESSION['message'] = 'E-mail ou senha incorretos';
                    $_SESSION['message_type'] = 'danger';

                    if ($isAjax) {
                        header('Content-Type: application/json; charset=utf-8');
                        echo json_encode(['success' => false, 'error' => $_SESSION['message']]);
                        return;
                    }
                }
            } catch (\Exception $e) {
                $_SESSION['message'] = 'Erro ao fazer login: ' . $e->getMessage();
                $_SESSION['message_type'] = 'danger';

                if ($isAjax) {
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(['success' => false, 'error' => $_SESSION['message']]);
                    return;
                }
            }
            
            $this->redirect('/loginadmin');
            return;
        }
        
        $this->view('auth/loginadmin');
    }

    public function recuperarSenha(Request $request) {
        if ($this->authService->estaLogado()) {
            $usuario = $this->authService->getUsuarioLogado();
            if (($usuario['perfil'] ?? '') === 'admin') {
                $this->redirect('/admin/dashboard');
            }
            $this->redirect('/minha-conta');
            return;
        }

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if ($request->getMethod() === 'POST') {
            $email = trim((string) ($request->getParam('email') ?? ''));
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $_SESSION['message'] = 'Informe um e-mail válido.';
                $_SESSION['message_type'] = 'danger';
                $this->redirect('/recuperar-senha');
                return;
            }

            $token = '';
            try {
                $token = $this->usuarioModel->requestPasswordResetToken($email);
            } catch (\Exception $e) {
                $token = '';
            }

            if ($token !== '') {
                $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
                $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
                $base = $host !== '' ? ($scheme . '://' . $host) : '';
                $link = ($base !== '' ? $base : '') . '/redefinir-senha/' . rawurlencode($token);

                $subject = 'Recuperação de senha - Braziliana';
                $body = 'Olá,<br><br>'
                    . 'Recebemos uma solicitação para redefinir sua senha.<br><br>'
                    . 'Clique no botão abaixo para criar uma nova senha.';
                $html = $this->buildBrandedEmailHtml(
                    'Recuperação de senha',
                    $body,
                    'Redefinir senha',
                    $link,
                    'Este link expira em 1 hora. Se você não solicitou isso, ignore este e-mail.'
                );

                try {
                    $emailSvc = new EmailService();
                    $tpl = $emailSvc->getTemplate('recuperar_senha');
                    $vars = [
                        'nome' => '',
                        'email' => $email,
                        'token' => $token,
                        'link' => $link,
                        'data' => date('Y-m-d H:i:s'),

                        'usuario_nome' => '',
                        'usuario_email' => $email,
                        'token_reset' => $token,
                        'data_solicitacao' => date('Y-m-d H:i:s'),
                    ];
                    $subjectTpl = $emailSvc->renderTemplate((string) ($tpl['assunto'] ?? ''), $vars);
                    $htmlTpl = $emailSvc->renderTemplate((string) ($tpl['corpo_html'] ?? ''), $vars);

                    $finalSubject = trim($subjectTpl) !== '' ? $subjectTpl : $subject;
                    $finalHtml = trim($htmlTpl) !== '' ? $htmlTpl : $html;
                    $dedupeKey = 'user_password_reset:' . strtolower($email) . ':' . $token;
                    $emailSvc->send($email, $finalSubject, $finalHtml, $dedupeKey, [
                        'evento' => 'recuperar_senha',
                    ]);
                } catch (\Exception $e) {
                    error_log('[EMAIL][RECUPERAR_SENHA] Falha ao enviar: ' . $e->getMessage());
                }
            }

            // Por segurança, mesma mensagem para email existente ou não
            $_SESSION['message'] = 'Se o e-mail estiver cadastrado, você receberá instruções para recuperar sua senha.';
            $_SESSION['message_type'] = 'success';
            $this->redirect('/login');
            return;
        }

        $prefillEmail = trim((string) ($request->getParam('email', '') ?? ''));
        if ($prefillEmail !== '' && !filter_var($prefillEmail, FILTER_VALIDATE_EMAIL)) {
            $prefillEmail = '';
        }

        $this->view('auth/recuperar-senha', ['email' => $prefillEmail]);
    }

    public function redefinirSenha(Request $request) {
        $token = (string) ($request->getParam('token') ?? '');
        $token = trim($token);

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (isset($_SESSION['message']) && is_string($_SESSION['message']) && stripos($_SESSION['message'], 'cadastro no site antigo') !== false) {
            unset($_SESSION['message'], $_SESSION['message_type']);
        }

        if ($token === '') {
            $_SESSION['message'] = 'Link inválido.';
            $_SESSION['message_type'] = 'danger';
            $this->redirect('/recuperar-senha');
            return;
        }

        if ($request->getMethod() === 'POST') {
            $senha = (string) ($request->getParam('senha') ?? '');
            $confirm = (string) ($request->getParam('senha_confirmacao') ?? '');
            if (trim($senha) === '' || strlen($senha) < 6) {
                $_SESSION['message'] = 'A nova senha deve ter pelo menos 6 caracteres.';
                $_SESSION['message_type'] = 'danger';
                $this->redirect('/redefinir-senha/' . rawurlencode($token));
                return;
            }
            if ($senha !== $confirm) {
                $_SESSION['message'] = 'As senhas não conferem.';
                $_SESSION['message_type'] = 'danger';
                $this->redirect('/redefinir-senha/' . rawurlencode($token));
                return;
            }

            $ok = false;
            try {
                $ok = $this->usuarioModel->resetPasswordByToken($token, $senha);
            } catch (\Exception $e) {
                $ok = false;
            }

            if ($ok) {
                $_SESSION['message'] = 'Senha redefinida com sucesso. Você já pode fazer login.';
                $_SESSION['message_type'] = 'success';
                $this->redirect('/login');
                return;
            }

            $_SESSION['message'] = 'Link inválido ou expirado. Solicite a recuperação novamente.';
            $_SESSION['message_type'] = 'danger';
            $this->redirect('/recuperar-senha');
            return;
        }

        $this->view('auth/redefinir-senha', ['token' => $token]);
    }

    public function logout(Request $request) {
        try {
            $this->authService->logout();

            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            $_SESSION['message'] = 'Logout realizado com sucesso.';
            $_SESSION['message_type'] = 'success';
        } catch (\Exception $e) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }

            $_SESSION['message'] = 'Erro ao fazer logout.';
            $_SESSION['message_type'] = 'danger';
        }

        $this->redirect('/login');
    }
    
    public function register(Request $request) {
        if ($this->authService->estaLogado()) {
            $usuario = $this->authService->getUsuarioLogado();
            if ($usuario['perfil'] === 'admin') {
                $this->redirect('/admin/dashboard');
            } else {
                $this->redirect('/minha-conta');
            }
            return;
        }
        
        if ($request->getMethod() === 'POST') {
            $isAjax = isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
            $dados = $request->getParams();
            
            // Validar dados
            $erros = $this->validarRegistro($dados);
            
            if (empty($erros)) {
                try {
                    // Verificar se e-mail já existe
                    if ($this->usuarioModel->findByEmail($dados['email'])) {
                        $erros[] = 'E-mail já cadastrado';
                    }
                    
                    // Verificar se CPF já existe (somente se informado)
                    $docCheck = preg_replace('/\D+/', '', (string) ($dados['documento'] ?? ''));
                    if ($docCheck !== '' && $this->usuarioModel->findByDocumento($docCheck)) {
                        $erros[] = 'CPF já cadastrado';
                    }
                    
                    if (empty($erros)) {
                        $docToSave = preg_replace('/\D+/', '', (string) ($dados['documento'] ?? ''));
                        $usuarioId = $this->usuarioModel->create([
                            'nome' => $dados['nome'],
                            'email' => $dados['email'],
                            'senha' => $dados['senha'],
                            'telefone' => $dados['telefone'],
                            'documento' => $docToSave,
                            'perfil' => 'cliente'
                        ]);

                        if ($usuarioId) {
                            $colsU = [];
                            try {
                                $stmtColsU = $this->usuarioModel->getConnection()->query('DESCRIBE usuarios');
                                $colsU = $stmtColsU ? $stmtColsU->fetchAll(\PDO::FETCH_COLUMN) : [];
                            } catch (\Exception $e) {
                                $colsU = [];
                            }

                            $upd = [];
                            $map = [
                                'cep' => 'cep',
                                'endereco' => 'endereco',
                                'numero' => 'numero',
                                'complemento' => 'complemento',
                                'bairro' => 'bairro',
                                'cidade' => 'cidade',
                                'estado' => 'estado',
                                'data_nascimento' => 'data_nascimento',
                                'pais_residencia' => 'pais_residencia',
                            ];
                            foreach ($map as $k => $col) {
                                if (is_array($colsU) && in_array($col, $colsU, true) && isset($dados[$k])) {
                                    $upd[$col] = $dados[$k];
                                }
                            }

                            if (is_array($colsU) && in_array('termos_aceitos_em', $colsU, true)) {
                                $upd['termos_aceitos_em'] = date('Y-m-d H:i:s');
                            }
                            if (is_array($colsU) && in_array('termos_aceitos_ip', $colsU, true)) {
                                $upd['termos_aceitos_ip'] = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
                            }
                            if (is_array($colsU) && in_array('termos_versao', $colsU, true)) {
                                $upd['termos_versao'] = '1.0';
                            }

                            if (!empty($upd)) {
                                $this->usuarioModel->update((int) $usuarioId, $upd);
                            }
                        }
                        
                        if ($usuarioId) {
                            try {
                                $emailSvc = new EmailService();
                                $tpl = $emailSvc->getTemplate('welcome');
                                $vars = [
                                    'nome' => (string) ($dados['nome'] ?? ''),
                                    'email' => (string) ($dados['email'] ?? ''),
                                    'data' => date('Y-m-d H:i:s'),
                                ];
                                $subject = $emailSvc->renderTemplate((string) ($tpl['assunto'] ?? ''), $vars);
                                $html = $emailSvc->renderTemplate((string) ($tpl['corpo_html'] ?? ''), $vars);
                                if (trim($subject) === '') {
                                    $subject = 'Bem-vindo(a) à Braziliana';
                                }
                                if (trim($html) === '') {
                                    $html = '<p style="margin:0 0 12px 0;">Olá ' . htmlspecialchars((string) ($vars['nome'] ?? ''), ENT_QUOTES, 'UTF-8') . ',</p>'
                                        . '<p style="margin:0;">Seu cadastro foi realizado com sucesso.</p>';
                                }
                                $dedupeKey = 'user_welcome:' . (int) $usuarioId;
                                $emailSvc->send((string) ($dados['email'] ?? ''), $subject, $html, $dedupeKey, [
                                    'evento' => 'welcome',
                                    'usuario_id' => (int) $usuarioId,
                                ]);
                            } catch (\Exception $e) {
                                error_log('[EMAIL][WELCOME] Falha ao enviar: ' . $e->getMessage());
                            }

                            // Fazer login automático
                            $usuario = $this->authService->login($dados['email'], $dados['senha']);
                            if ($isAjax) {
                                header('Content-Type: application/json; charset=utf-8');
                                echo json_encode(['success' => true, 'redirect' => '/meus-dados']);
                                return;
                            }
                            $this->redirect('/meus-dados');
                            return;
                        } else {
                            $erros[] = 'Erro ao criar conta';
                        }
                    }
                } catch (\Exception $e) {
                    $msg = (string) $e->getMessage();
                    if (stripos($msg, 'Duplicate entry') !== false && stripos($msg, 'documento') !== false) {
                        $erros[] = 'CPF já cadastrado';
                    } elseif (stripos($msg, 'Duplicate entry') !== false && stripos($msg, 'email') !== false) {
                        $erros[] = 'E-mail já cadastrado';
                    } else {
                        $erros[] = 'Erro ao criar conta: ' . $msg;
                    }
                }
            }

            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode([
                    'success' => false,
                    'error' => $erros[0] ?? 'Erro ao criar conta'
                ]);
                return;
            }
            
            $this->view('auth/register', [
                'erros' => $erros,
                'dados' => $dados
            ]);
        } else {
            $this->view('auth/register');
        }
    }
    
    private function validarRegistro($dados) {
        $erros = [];
        
        if (empty($dados['nome'])) {
            $erros[] = 'Nome é obrigatório';
        } else {
            $nome = trim((string) $dados['nome']);
            $parts = preg_split('/\s+/', $nome) ?: [];
            $parts = array_values(array_filter($parts, static fn($p) => is_string($p) && mb_strlen(trim($p)) >= 2));
            if (count($parts) < 2) {
                $erros[] = 'Informe nome e sobrenome';
            }
        }
        
        if (empty($dados['email'])) {
            $erros[] = 'E-mail é obrigatório';
        } elseif (!filter_var($dados['email'], FILTER_VALIDATE_EMAIL)) {
            $erros[] = 'E-mail inválido';
        }
        
        if (empty($dados['senha'])) {
            $erros[] = 'Senha é obrigatória';
        } elseif (strlen($dados['senha']) < 6) {
            $erros[] = 'Senha deve ter pelo menos 6 caracteres';
        }
        
        if (empty($dados['senha_confirmacao'])) {
            $erros[] = 'Confirmação de senha é obrigatória';
        } elseif ($dados['senha'] !== $dados['senha_confirmacao']) {
            $erros[] = 'Senhas não conferem';
        }
        
        $pais = strtoupper(trim((string) ($dados['pais_residencia'] ?? 'BR')));
        if ($pais === '') {
            $pais = 'BR';
        }

        // CPF: obrigatório somente para residentes no Brasil
        $doc = CpfValidator::onlyDigits((string) ($dados['documento'] ?? ''));
        if ($pais === 'BR') {
            if ($doc === '' || strlen($doc) < 11) {
                $erros[] = 'CPF é obrigatório para residentes no Brasil';
            } elseif (strlen($doc) === 11 && !CpfValidator::isValid($doc)) {
                $erros[] = 'CPF inválido';
            }
        } else {
            if ($doc !== '' && strlen($doc) === 11 && !CpfValidator::isValid($doc)) {
                $erros[] = 'CPF inválido';
            }
        }
        
        if (empty($dados['telefone'])) {
            $erros[] = 'Telefone é obrigatório';
        }

        if (empty($dados['data_nascimento'])) {
            $erros[] = 'Data de nascimento é obrigatória';
        } else {
            $rawBirth = trim((string) ($dados['data_nascimento'] ?? ''));
            $birth = \DateTime::createFromFormat('Y-m-d', $rawBirth);
            if (!$birth) {
                $birth = \DateTime::createFromFormat('d/m/Y', $rawBirth);
            }
            if (!$birth) {
                $erros[] = 'Data de nascimento inválida';
            } else {
                $birth->setTime(0, 0, 0);
                $today = new \DateTime('today');
                if ($birth > $today) {
                    $erros[] = 'Data de nascimento não pode ser no futuro';
                }
            }
        }

        if (empty($dados['pais_residencia'])) {
            $erros[] = 'País de residência é obrigatório';
        }

        if (empty($dados['cep'])) $erros[] = 'CEP é obrigatório';
        if (empty($dados['endereco'])) $erros[] = 'Endereço é obrigatório';
        if ($pais === 'BR' && empty($dados['numero'])) $erros[] = 'Número é obrigatório';
        if ($pais === 'BR' && empty($dados['bairro'])) $erros[] = 'Bairro é obrigatório';
        if (empty($dados['cidade'])) $erros[] = 'Cidade é obrigatório';
        if (empty($dados['estado'])) $erros[] = 'Estado é obrigatório';

        if (empty($dados['termos'])) {
            $erros[] = 'Você precisa aceitar os termos';
        }
        
        return $erros;
    }
    
    public function perfil(Request $request) {
        $this->authService->requerAutenticacao();
        
        $usuario = $this->authService->getUsuarioLogado();
        $usuarioCompleto = $this->usuarioModel->find($usuario['id']);
        
        if ($request->getMethod() === 'POST') {
            $dados = $request->getParams();
            
            // Validar dados
            $erros = $this->validarPerfil($dados);
            
            if (empty($erros)) {
                try {
                    $updateData = [
                        'nome' => $dados['nome'],
                        'telefone' => $dados['telefone']
                    ];
                    
                    if (!empty($dados['senha_atual'])) {
                        if (!$this->usuarioModel->authenticate($usuarioCompleto['email'], $dados['senha_atual'])) {
                            $erros[] = 'Senha atual incorreta';
                        } elseif (!empty($dados['nova_senha'])) {
                            if (strlen($dados['nova_senha']) < 6) {
                                $erros[] = 'Nova senha deve ter pelo menos 6 caracteres';
                            } elseif ($dados['nova_senha'] !== $dados['nova_senha_confirmacao']) {
                                $erros[] = 'Novas senhas não conferem';
                            } else {
                                $this->usuarioModel->updatePassword($usuario['id'], $dados['nova_senha']);
                            }
                        }
                    }
                    
                    if (empty($erros)) {
                        $this->usuarioModel->update($usuario['id'], $updateData);
                        
                        // Atualizar sessão
                        $_SESSION['usuario_nome'] = $dados['nome'];
                        
                        $this->view('auth/perfil', [
                            'success' => 'Perfil atualizado com sucesso',
                            'usuario' => $this->usuarioModel->find($usuario['id'])
                        ]);
                        return;
                    }
                } catch (\Exception $e) {
                    $erros[] = 'Erro ao atualizar perfil: ' . $e->getMessage();
                }
            }
            
            $this->view('auth/perfil', [
                'erros' => $erros,
                'usuario' => array_merge($usuarioCompleto, $dados)
            ]);
        } else {
            $this->view('auth/perfil', [
                'usuario' => $usuarioCompleto
            ]);
        }
    }
    
    private function validarPerfil($dados) {
        $erros = [];
        
        if (empty($dados['nome'])) {
            $erros[] = 'Nome é obrigatório';
        }
        
        if (empty($dados['telefone'])) {
            $erros[] = 'Telefone é obrigatório';
        }
        
        return $erros;
    }
}
