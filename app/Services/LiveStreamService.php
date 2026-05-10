<?php
namespace App\Services;

use Config\Database;

/**
 * Integração com Cloudflare Stream API
 * Gerencia criação de live inputs, playback tokens, gravações
 */
class LiveStreamService {
    private $pdo;

    public function __construct() {
        $this->pdo = Database::getConnection();
    }

    /**
     * Obtém credenciais descriptografadas do Cloudflare
     */
    public function getCredentials(): array {
        $stmt = $this->pdo->prepare(
            "SELECT chave, valor FROM configuracoes_sistema WHERE chave IN ('lives_cf_account_id', 'lives_cf_api_token', 'lives_cf_stream_subdomain')"
        );
        $stmt->execute();
        $rows = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

        $accountId = $this->decrypt($rows['lives_cf_account_id'] ?? '');
        $apiToken = $this->decrypt($rows['lives_cf_api_token'] ?? '');
        $subdomain = $rows['lives_cf_stream_subdomain'] ?? '';

        return [
            'account_id' => $accountId,
            'api_token' => $apiToken,
            'subdomain' => $subdomain,
        ];
    }

    /**
     * Salva credenciais criptografadas
     */
    public function saveCredentials(string $accountId, string $apiToken, string $subdomain): bool {
        $encAccountId = $this->encrypt($accountId);
        $encApiToken = $this->encrypt($apiToken);

        $updates = [
            'lives_cf_account_id' => $encAccountId,
            'lives_cf_api_token' => $encApiToken,
            'lives_cf_stream_subdomain' => $subdomain,
        ];

        foreach ($updates as $chave => $valor) {
            $stmt = $this->pdo->prepare(
                "UPDATE configuracoes_sistema SET valor = :valor WHERE chave = :chave"
            );
            $stmt->execute([':valor' => $valor, ':chave' => $chave]);
        }

        return true;
    }

    /**
     * Cria um Live Input no Cloudflare Stream
     */
    public function createLiveInput(int $liveId, string $title): ?array {
        $creds = $this->getCredentials();
        if (empty($creds['account_id']) || empty($creds['api_token'])) {
            return null;
        }

        $url = "https://api.cloudflare.com/client/v4/accounts/{$creds['account_id']}/stream/live_inputs";
        
        $payload = json_encode([
            'meta' => ['name' => "Live #{$liveId} - {$title}"],
            'recording' => ['mode' => 'automatic'],
        ]);

        $response = $this->cfRequest('POST', $url, $payload, $creds['api_token']);

        if (!$response || !isset($response['result'])) {
            return null;
        }

        $result = $response['result'];
        return [
            'uid' => $result['uid'] ?? null,
            'rtmps_url' => $result['rtmps']['url'] ?? null,
            'rtmps_key' => $result['rtmps']['streamKey'] ?? null,
            'webrtc_url' => $result['webRTC']['url'] ?? null,
            'webrtc_playback_url' => $result['webRTCPlayback']['url'] ?? null,
            'playback_hls' => $result['playback']['hls'] ?? null,
        ];
    }

    /**
     * Encerra um Live Input
     */
    public function stopLiveInput(string $cfInputId): bool {
        $creds = $this->getCredentials();
        if (empty($creds['account_id']) || empty($creds['api_token'])) {
            return false;
        }

        // Cloudflare não tem endpoint explícito de "stop" — a live para quando o ingest para.
        // Podemos deletar o input ou apenas deixar ele parar naturalmente.
        // Para manter a gravação, não deletamos aqui.
        return true;
    }

    /**
     * Verifica status da gravação
     */
    public function getRecordingStatus(string $cfInputId): ?array {
        $creds = $this->getCredentials();
        if (empty($creds['account_id']) || empty($creds['api_token'])) {
            return null;
        }

        $url = "https://api.cloudflare.com/client/v4/accounts/{$creds['account_id']}/stream/live_inputs/{$cfInputId}/videos";
        $response = $this->cfRequest('GET', $url, null, $creds['api_token']);

        if (!$response || !isset($response['result'])) {
            return null;
        }

        $videos = $response['result'];
        if (empty($videos)) {
            return ['status' => 'pending', 'videos' => []];
        }

        // Pegar o vídeo mais recente
        $latest = $videos[0];
        return [
            'status' => $latest['status']['state'] ?? 'unknown',
            'video_uid' => $latest['uid'] ?? null,
            'playback_url' => $latest['playback']['hls'] ?? null,
            'download_url' => $latest['playback']['hls'] ?? null, // CF não oferece download direto via API pública
            'duration' => $latest['duration'] ?? 0,
        ];
    }

    /**
     * Deleta um Live Input do Cloudflare
     */
    public function deleteLiveInput(string $cfInputId): bool {
        $creds = $this->getCredentials();
        if (empty($creds['account_id']) || empty($creds['api_token'])) {
            return false;
        }

        $url = "https://api.cloudflare.com/client/v4/accounts/{$creds['account_id']}/stream/live_inputs/{$cfInputId}";
        $response = $this->cfRequest('DELETE', $url, null, $creds['api_token']);

        return $response !== null && ($response['success'] ?? false);
    }

    /**
     * Gera URL de playback com token (signed URL)
     */
    public function generateSignedPlaybackUrl(string $playbackUrl): string {
        // Para Cloudflare Stream, o playback HLS já é público por padrão.
        // Se quiser restringir, usar Signed URLs com chave RSA configurada no CF.
        // Por ora, retornar a URL direta (pode ser melhorado com signed tokens).
        return $playbackUrl;
    }

    /**
     * Testa conexão com a API do Cloudflare
     */
    public function testConnection(): array {
        $creds = $this->getCredentials();
        if (empty($creds['account_id']) || empty($creds['api_token'])) {
            return ['success' => false, 'message' => 'Credenciais não configuradas'];
        }

        $url = "https://api.cloudflare.com/client/v4/accounts/{$creds['account_id']}/stream/live_inputs";
        $response = $this->cfRequest('GET', $url, null, $creds['api_token']);

        if ($response === null) {
            return ['success' => false, 'message' => 'Erro de conexão com a API'];
        }

        if (!($response['success'] ?? false)) {
            $errors = $response['errors'] ?? [];
            $msg = !empty($errors) ? ($errors[0]['message'] ?? 'Erro desconhecido') : 'Erro desconhecido';
            return ['success' => false, 'message' => $msg];
        }

        return ['success' => true, 'message' => 'Conexão OK'];
    }

    /**
     * Faz requisição HTTP para a API do Cloudflare
     */
    private function cfRequest(string $method, string $url, ?string $body, string $apiToken): ?array {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTPHEADER => [
                'Authorization: Bearer ' . $apiToken,
                'Content-Type: application/json',
            ],
        ]);

        switch (strtoupper($method)) {
            case 'POST':
                curl_setopt($ch, CURLOPT_POST, true);
                if ($body) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
                break;
            case 'PUT':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
                if ($body) curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
                break;
            case 'DELETE':
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
                break;
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode >= 500) {
            return null;
        }

        return json_decode($response, true) ?: null;
    }

    /**
     * Criptografa valor com libsodium
     */
    private function encrypt(string $value): string {
        if (empty($value)) return '';
        
        $key = $this->getEncryptionKey();
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = sodium_crypto_secretbox($value, $nonce, $key);
        
        return base64_encode($nonce . $cipher);
    }

    /**
     * Descriptografa valor com libsodium
     */
    private function decrypt(string $encoded): string {
        if (empty($encoded)) return '';

        $key = $this->getEncryptionKey();
        $decoded = base64_decode($encoded);
        
        if ($decoded === false || strlen($decoded) < SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            // Pode ser valor não criptografado (migração)
            return $encoded;
        }

        $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipher = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $plain = sodium_crypto_secretbox_open($cipher, $nonce, $key);
        if ($plain === false) {
            // Falha na descriptografia — retornar vazio
            return '';
        }

        return $plain;
    }

    /**
     * Obtém chave de criptografia (derivada de APP_KEY ou constante)
     */
    private function getEncryptionKey(): string {
        // Usar APP_KEY do ambiente ou gerar uma fixa baseada em constante do projeto
        $appKey = getenv('APP_KEY') ?: ($_ENV['APP_KEY'] ?? '');
        
        if (empty($appKey)) {
            // Fallback: usar hash do document root como seed (não ideal, mas funcional)
            $appKey = 'braziliana-live-shopping-key-' . md5($_SERVER['DOCUMENT_ROOT'] ?? __DIR__);
        }

        // Derivar chave de 32 bytes
        return sodium_crypto_generichash($appKey, '', SODIUM_CRYPTO_SECRETBOX_KEYBYTES);
    }
}
