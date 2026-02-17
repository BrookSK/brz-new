<?php
namespace App\Core;

class I18n {
    private static string $locale = 'pt-BR';
    private static array $dict = [];

    public static function boot(): void {
        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }

        $locale = '';
        try {
            $locale = (string) ($_SESSION['locale'] ?? '');
        } catch (\Throwable $e) {
            $locale = '';
        }

        if ($locale === '') {
            try {
                $cookieLocale = (string) ($_COOKIE['locale'] ?? '');
                if ($cookieLocale !== '') {
                    $locale = $cookieLocale;
                }
            } catch (\Throwable $e) {
            }
        }

        $locale = self::normalizeLocale($locale);
        self::setLocale($locale);
    }

    public static function normalizeLocale(string $locale): string {
        $v = strtolower(trim($locale));
        if ($v === 'en' || $v === 'en-us' || $v === 'en_us' || $v === 'en-gb' || $v === 'en_gb') {
            return 'en';
        }
        return 'pt-BR';
    }

    public static function setLocale(string $locale): void {
        $locale = self::normalizeLocale($locale);
        self::$locale = $locale;

        $dict = [];
        try {
            $langFile = __DIR__ . '/../lang/' . ($locale === 'en' ? 'en.php' : 'pt-BR.php');
            if (file_exists($langFile)) {
                $loaded = require $langFile;
                if (is_array($loaded)) {
                    $dict = $loaded;
                }
            }
        } catch (\Throwable $e) {
            $dict = [];
        }
        self::$dict = $dict;

        try {
            $_SESSION['locale'] = $locale;
        } catch (\Throwable $e) {
        }

        try {
            $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
            setcookie('locale', $locale, [
                'expires' => time() + (60 * 60 * 24 * 365),
                'path' => '/',
                'secure' => $secure,
                'httponly' => false,
                'samesite' => 'Lax',
            ]);
        } catch (\Throwable $e) {
        }
    }

    public static function getLocale(): string {
        return self::$locale;
    }

    public static function getLocaleHtml(): string {
        return self::$locale === 'en' ? 'en' : 'pt-BR';
    }

    public static function t(string $key, ?string $fallback = null, array $params = []): string {
        $txt = '';
        if (array_key_exists($key, self::$dict)) {
            $txt = (string) self::$dict[$key];
        } else {
            $txt = $fallback !== null ? $fallback : $key;
        }

        if (!empty($params)) {
            foreach ($params as $k => $v) {
                $txt = str_replace('{' . (string) $k . '}', (string) $v, $txt);
            }
        }
        return $txt;
    }
}
