<?php

namespace App\Core;

class Url {
    public static function base(): string {
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);

        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            $isHttps = strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https';
        }

        $scheme = $isHttps ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';

        return $scheme . '://' . $host;
    }

    public static function absolute(string $path): string {
        if ($path === '') {
            return self::base();
        }

        if (preg_match('#^https?://#i', $path)) {
            return $path;
        }

        $normalized = '/' . ltrim($path, '/');
        return self::base() . $normalized;
    }
}
