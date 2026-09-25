<?php
namespace App\Services;

/**
 * Rate limiter simples por chave (tipicamente IP + rota).
 *
 * Objetivo: conter floods/ataques de repetição contra endpoints caros
 * (que abrem sessão e consultam o banco) ANTES de pagar esse custo.
 *
 * Estratégia de armazenamento:
 *  - Usa APCu quando disponível (memória compartilhada, sem I/O de disco).
 *  - Caso contrário, cai para arquivos em sys_get_temp_dir() com contagem
 *    por janela fixa de tempo.
 *
 * O limiter é deliberadamente "fail-open": se algo der errado ao contabilizar,
 * ele permite o request (nunca derruba o site legítimo por causa do limiter).
 */
class RateLimiter {

    /**
     * Verifica e contabiliza um acesso. Retorna true se estiver DENTRO do limite,
     * false se o limite foi excedido (deve bloquear).
     *
     * @param string $key    Identificador do balde (ex.: "cartrec:detalhes:1.2.3.4")
     * @param int    $limit  Número máximo de requests permitidos na janela
     * @param int    $window Tamanho da janela em segundos
     */
    public static function allow(string $key, int $limit, int $window): bool {
        if ($limit <= 0 || $window <= 0) {
            return true;
        }

        $bucket = 'rl:' . sha1($key) . ':' . (int) floor(time() / $window);

        try {
            if (function_exists('apcu_enabled') && apcu_enabled()) {
                return self::allowApcu($bucket, $limit, $window);
            }
        } catch (\Throwable $e) {
            // cai para o fallback de arquivo
        }

        try {
            return self::allowFile($bucket, $limit, $window);
        } catch (\Throwable $e) {
            // fail-open
            return true;
        }
    }

    private static function allowApcu(string $bucket, int $limit, int $window): bool {
        $new = apcu_inc($bucket, 1, $success, $window);
        if (!$success) {
            // chave ainda não existia
            apcu_store($bucket, 1, $window);
            $new = 1;
        }
        return $new <= $limit;
    }

    private static function allowFile(string $bucket, int $limit, int $window): bool {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'brz_ratelimit';
        if (!is_dir($dir)) {
            @mkdir($dir, 0700, true);
        }
        $file = $dir . DIRECTORY_SEPARATOR . $bucket . '.cnt';

        $fh = @fopen($file, 'c+');
        if ($fh === false) {
            return true; // fail-open
        }

        try {
            if (!flock($fh, LOCK_EX)) {
                return true; // fail-open
            }
            $raw = stream_get_contents($fh);
            $count = (int) trim((string) $raw);
            $count++;
            rewind($fh);
            ftruncate($fh, 0);
            fwrite($fh, (string) $count);
            fflush($fh);
            flock($fh, LOCK_UN);

            // Limpeza oportunista: remove arquivos de janelas antigas de vez em quando.
            if (mt_rand(1, 50) === 1) {
                self::gc($dir, $window);
            }

            return $count <= $limit;
        } finally {
            fclose($fh);
        }
    }

    private static function gc(string $dir, int $window): void {
        try {
            $cutoff = time() - max($window * 2, 120);
            foreach (glob($dir . DIRECTORY_SEPARATOR . '*.cnt') ?: [] as $f) {
                if (@filemtime($f) < $cutoff) {
                    @unlink($f);
                }
            }
        } catch (\Throwable $e) {
        }
    }

    /**
     * Descobre o IP do cliente respeitando proxies conhecidos.
     * Usa apenas o primeiro IP de X-Forwarded-For quando presente.
     */
    public static function clientIp(): string {
        $xff = (string) ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? '');
        if ($xff !== '') {
            $parts = explode(',', $xff);
            $ip = trim($parts[0]);
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        return $ip !== '' ? $ip : 'unknown';
    }

    /**
     * Emite resposta 429 e encerra o request.
     */
    public static function reject(int $retryAfter = 30): void {
        if (!headers_sent()) {
            http_response_code(429);
            header('Retry-After: ' . max(1, $retryAfter));
            header('Content-Type: application/json; charset=UTF-8');
        }
        echo json_encode(['success' => false, 'error' => 'too_many_requests']);
        exit;
    }
}
