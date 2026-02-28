<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Url;
use App\Services\AuthService;

class MercadoPagoOAuthController extends Controller {
    private function getConfigFromKeyValueTable(\PDO $pdo, string $table, string $categoria, string $chave): ?string {
        try {
            $st = $pdo->prepare("SELECT valor FROM {$table} WHERE categoria = ? AND chave = ? ORDER BY id DESC LIMIT 1");
            $st->execute([(string) $categoria, (string) $chave]);
            $v = $st->fetchColumn();
            if ($v === false || $v === null) {
                return null;
            }
            return (string) $v;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function saveConfigToKeyValueTable(\PDO $pdo, string $table, string $categoria, string $chave, string $valor): void {
        try {
            $st = $pdo->prepare("SELECT id FROM {$table} WHERE categoria = ? AND chave = ? ORDER BY id DESC LIMIT 1");
            $st->execute([(string) $categoria, (string) $chave]);
            $id = (int) ($st->fetchColumn() ?: 0);

            if ($id > 0) {
                $stUp = $pdo->prepare("UPDATE {$table} SET valor = ?, updated_at = NOW() WHERE id = ?");
                $stUp->execute([(string) $valor, $id]);
                return;
            }

            $stIns = $pdo->prepare("INSERT INTO {$table} (categoria, chave, valor, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW())");
            $stIns->execute([(string) $categoria, (string) $chave, (string) $valor]);
        } catch (\Exception $e) {
        }
    }

    private function getConfigTable(\PDO $pdo): ?string {
        try {
            $st = $pdo->prepare('SHOW TABLES LIKE ?');
            $st->execute(['configuracoes_sistema']);
            if ($st->fetchColumn()) {
                return 'configuracoes_sistema';
            }
        } catch (\Exception $e) {
        }

        try {
            $st = $pdo->prepare('SHOW TABLES LIKE ?');
            $st->execute(['configuracoes']);
            if ($st->fetchColumn()) {
                return 'configuracoes';
            }
        } catch (\Exception $e) {
        }

        return null;
    }

    public function start(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfil('admin');

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['mp_oauth_state'] = bin2hex(random_bytes(16));

        $pdo = \Config\Database::getConnection();
        $table = $this->getConfigTable($pdo);
        if ($table === null) {
            $this->redirect('/admin/configuracoes?mp_oauth_error=config_table');
        }

        $clientId = (string) ($this->getConfigFromKeyValueTable($pdo, $table, 'pagamentos', 'mercadopago_client_id') ?? '');
        if ($clientId === '') {
            $this->redirect('/admin/configuracoes?mp_oauth_error=client_id');
        }

        $redirectUri = Url::absolute('/mercadopago/oauth/callback');

        $query = http_build_query([
            'client_id' => $clientId,
            'response_type' => 'code',
            'platform_id' => 'mp',
            'redirect_uri' => $redirectUri,
            'state' => (string) $_SESSION['mp_oauth_state'],
        ]);

        $this->redirect('https://auth.mercadopago.com.br/authorization?' . $query);
    }

    public function callback(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfil('admin');

        $code = trim((string) $request->getParam('code', ''));
        $state = trim((string) $request->getParam('state', ''));

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $expectedState = (string) ($_SESSION['mp_oauth_state'] ?? '');
        unset($_SESSION['mp_oauth_state']);

        if ($code === '') {
            $this->redirect('/admin/configuracoes?mp_oauth_error=missing_code');
        }
        if ($expectedState !== '' && $state !== '' && !hash_equals($expectedState, $state)) {
            $this->redirect('/admin/configuracoes?mp_oauth_error=invalid_state');
        }

        $pdo = \Config\Database::getConnection();
        $table = $this->getConfigTable($pdo);
        if ($table === null) {
            $this->redirect('/admin/configuracoes?mp_oauth_error=config_table');
        }

        $clientId = (string) ($this->getConfigFromKeyValueTable($pdo, $table, 'pagamentos', 'mercadopago_client_id') ?? '');
        $clientSecret = (string) ($this->getConfigFromKeyValueTable($pdo, $table, 'pagamentos', 'mercadopago_client_secret') ?? '');
        if ($clientId === '' || $clientSecret === '') {
            $this->redirect('/admin/configuracoes?mp_oauth_error=missing_client');
        }

        $redirectUri = Url::absolute('/mercadopago/oauth/callback');

        $tokenResp = $this->oauthTokenRequest([
            'grant_type' => 'authorization_code',
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
            'code' => $code,
            'redirect_uri' => $redirectUri,
        ]);

        $accessToken = (string) ($tokenResp['access_token'] ?? '');
        $refreshToken = (string) ($tokenResp['refresh_token'] ?? '');
        $userId = (string) ($tokenResp['user_id'] ?? '');
        $publicKey = (string) ($tokenResp['public_key'] ?? '');

        if ($accessToken === '') {
            $this->redirect('/admin/configuracoes?mp_oauth_error=token_exchange');
        }

        // Salvar tokens do vendedor (conta do produto)
        $this->saveConfigToKeyValueTable($pdo, $table, 'pagamentos', 'mercadopago_seller_access_token', $accessToken);
        if ($refreshToken !== '') {
            $this->saveConfigToKeyValueTable($pdo, $table, 'pagamentos', 'mercadopago_seller_refresh_token', $refreshToken);
        }
        if ($userId !== '') {
            $this->saveConfigToKeyValueTable($pdo, $table, 'pagamentos', 'mercadopago_seller_user_id', $userId);
        }
        if ($publicKey !== '') {
            $this->saveConfigToKeyValueTable($pdo, $table, 'pagamentos', 'mercadopago_seller_public_key', $publicKey);
        }

        $this->redirect('/admin/configuracoes?mp_oauth=success');
    }

    private function oauthTokenRequest(array $body): array {
        $url = 'https://api.mercadopago.com/oauth/token';
        $payload = http_build_query($body);

        $headers = [
            'Accept: application/json',
            'Content-Type: application/x-www-form-urlencoded',
        ];

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            $respBody = curl_exec($ch);
            $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $err = curl_error($ch);
            curl_close($ch);

            if (!empty($err)) {
                return [];
            }
            $decoded = json_decode((string) $respBody, true);
            if ($httpCode < 200 || $httpCode >= 300) {
                return is_array($decoded) ? $decoded : [];
            }
            return is_array($decoded) ? $decoded : [];
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => implode("\r\n", $headers),
                'content' => $payload,
                'ignore_errors' => true,
            ]
        ]);

        $respBody = @file_get_contents($url, false, $context);
        $decoded = json_decode((string) $respBody, true);
        return is_array($decoded) ? $decoded : [];
    }
}
