<?php
namespace App\Services;

use Config\Database;

class WordPressDbService {
    private const SOURCES = ['br', 'red', 'us'];

    private function getLocalPdo(): \PDO {
        return Database::getConnection();
    }

    private function getWpPdo(\PDO $localPdo, string $source = 'br'): array {
        $source = strtolower(trim($source));
        if (!in_array($source, self::SOURCES, true)) {
            $source = 'br';
        }

        $out = ['table_prefix' => 'wp_'];
        $cat = 'wordpress_' . $source;

        try {
            $st = $localPdo->prepare("SHOW TABLES LIKE 'configuracoes_sistema'");
            $st->execute();
            $has = (bool) $st->fetchColumn();
            if (!$has) {
                throw new \RuntimeException('Configurações do WordPress não encontradas.');
            }
        } catch (\Exception $e) {
            throw new \RuntimeException('Configure o banco WordPress em Admin > Configurações > WordPress');
        }

        $cols = [];
        try {
            $st = $localPdo->query('DESCRIBE configuracoes_sistema');
            $cols = $st ? ($st->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
        } catch (\Exception $e) {
            $cols = [];
        }

        $hasCategoria = in_array('categoria', $cols, true) && in_array('chave', $cols, true) && in_array('valor', $cols, true);
        if ($hasCategoria) {
            $st = $localPdo->prepare('SELECT valor FROM configuracoes_sistema WHERE categoria = ? AND chave = ? LIMIT 1');
            foreach (['db_host', 'db_name', 'db_user', 'db_pass', 'table_prefix'] as $k) {
                $val = null;
                $st->execute([$cat, $k]);
                $v1 = $st->fetchColumn();
                if ($v1 !== false && $v1 !== null) {
                    $val = $v1;
                } elseif ($source === 'br') {
                    $st->execute(['wordpress', $k]);
                    $v2 = $st->fetchColumn();
                    if ($v2 !== false && $v2 !== null) {
                        $val = $v2;
                    }
                }
                if ($val !== null) {
                    $out[$k] = (string) $val;
                }
            }
        } else {
            $st = $localPdo->prepare('SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1');
            foreach (['db_host', 'db_name', 'db_user', 'db_pass', 'table_prefix'] as $k) {
                $val = null;
                $st->execute([$cat . '_' . $k]);
                $v1 = $st->fetchColumn();
                if ($v1 !== false && $v1 !== null) {
                    $val = $v1;
                } elseif ($source === 'br') {
                    $st->execute(['wordpress_' . $k]);
                    $v2 = $st->fetchColumn();
                    if ($v2 !== false && $v2 !== null) {
                        $val = $v2;
                    }
                }
                if ($val !== null) {
                    $out[$k] = (string) $val;
                }
            }
        }

        $host = trim((string) ($out['db_host'] ?? ''));
        $dbname = trim((string) ($out['db_name'] ?? ''));
        $user = trim((string) ($out['db_user'] ?? ''));
        $pass = (string) ($out['db_pass'] ?? '');
        $prefix = trim((string) ($out['table_prefix'] ?? 'wp_'));
        if ($prefix === '') {
            $prefix = 'wp_';
        }

        $port = null;
        if ($host !== '' && strpos($host, ':') !== false) {
            $parts = explode(':', $host, 2);
            $host = trim((string) ($parts[0] ?? ''));
            $portPart = trim((string) ($parts[1] ?? ''));
            if ($portPart !== '' && ctype_digit($portPart)) {
                $port = (int) $portPart;
            }
        }

        if ($host === '' || $dbname === '' || $user === '') {
            throw new \RuntimeException('Configure o banco WordPress em Admin > Configurações > WordPress');
        }

        $dsn = 'mysql:host=' . $host . ';' . ($port ? ('port=' . $port . ';') : '') . 'dbname=' . $dbname . ';charset=utf8mb4';
        $pdo = new \PDO($dsn, $user, $pass, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);

        return ['pdo' => $pdo, 'prefix' => $prefix];
    }

    public function findUserByEmail(string $email, string $source = 'br'): ?array {
        $email = strtolower(trim((string) $email));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        $localPdo = $this->getLocalPdo();
        $wp = $this->getWpPdo($localPdo, $source);
        $pdo = $wp['pdo'];
        $prefix = $wp['prefix'];

        $st = $pdo->prepare("SELECT ID, user_email, user_login, display_name FROM {$prefix}users WHERE LOWER(TRIM(user_email)) = ? LIMIT 1");
        $st->execute([$email]);
        $u = $st->fetch(\PDO::FETCH_ASSOC);
        if (!$u) {
            return null;
        }

        return [
            'id' => (int) ($u['ID'] ?? 0),
            'email' => (string) ($u['user_email'] ?? ''),
            'login' => (string) ($u['user_login'] ?? ''),
            'display_name' => (string) ($u['display_name'] ?? ''),
            'source' => $source,
        ];
    }

    public function findUserByEmailPriority(string $email, array $sources = ['br', 'us']): ?array {
        foreach ($sources as $src) {
            $src = strtolower(trim((string) $src));
            if (!in_array($src, self::SOURCES, true)) {
                continue;
            }
            try {
                $u = $this->findUserByEmail($email, $src);
                if ($u) {
                    return $u;
                }
            } catch (\Exception $e) {
            }
        }
        return null;
    }
}
