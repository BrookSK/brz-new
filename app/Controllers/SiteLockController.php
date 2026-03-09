<?php
namespace App\Controllers;

use App\Core\Request;

class SiteLockController extends Controller {
    private function getConfig(string $categoria, string $chave, string $default = ''): string {
        try {
            $pdo = \Config\Database::getConnection();
            $tablesToTry = ['configuracoes_sistema', 'configuracoes', 'settings', 'config'];
            foreach ($tablesToTry as $t) {
                try {
                    $stmtT = $pdo->prepare('SHOW TABLES LIKE ?');
                    $stmtT->execute([$t]);
                    if (!$stmtT->fetchColumn()) {
                        continue;
                    }

                    $stmtCols = $pdo->query('DESCRIBE ' . $t);
                    $cols = $stmtCols ? ($stmtCols->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
                    if (!is_array($cols)) {
                        $cols = [];
                    }

                    if (in_array('categoria', $cols, true) && in_array('chave', $cols, true)) {
                        $valCol = in_array('valor', $cols, true) ? 'valor' : (in_array('value', $cols, true) ? 'value' : '');
                        if ($valCol !== '') {
                            $stmt = $pdo->prepare('SELECT ' . $valCol . ' FROM ' . $t . ' WHERE categoria = ? AND chave = ? LIMIT 1');
                            $stmt->execute([$categoria, $chave]);
                            $v = (string) ($stmt->fetchColumn() ?: '');
                            if ($v !== '') {
                                return $v;
                            }
                        }
                    }

                    $keyCol = '';
                    if (in_array('chave', $cols, true)) $keyCol = 'chave';
                    elseif (in_array('key', $cols, true)) $keyCol = 'key';
                    elseif (in_array('nome', $cols, true)) $keyCol = 'nome';
                    elseif (in_array('config_key', $cols, true)) $keyCol = 'config_key';
                    $valCol = '';
                    if (in_array('valor', $cols, true)) $valCol = 'valor';
                    elseif (in_array('value', $cols, true)) $valCol = 'value';
                    elseif (in_array('conteudo', $cols, true)) $valCol = 'conteudo';
                    if ($keyCol !== '' && $valCol !== '') {
                        $full = $categoria . '_' . $chave;
                        $stmt = $pdo->prepare('SELECT ' . $valCol . ' FROM ' . $t . ' WHERE ' . $keyCol . ' = ? LIMIT 1');
                        $stmt->execute([$full]);
                        $v = (string) ($stmt->fetchColumn() ?: '');
                        if ($v !== '') {
                            return $v;
                        }
                    }

                    $colDirect = $categoria . '_' . $chave;
                    if (in_array($colDirect, $cols, true)) {
                        $idCol = in_array('id', $cols, true) ? 'id' : (in_array('ID', $cols, true) ? 'ID' : 'id');
                        $stmt2 = $pdo->query('SELECT ' . $colDirect . ' AS valor FROM ' . $t . ' ORDER BY ' . $idCol . ' ASC LIMIT 1');
                        $v = (string) ($stmt2 ? ($stmt2->fetchColumn() ?: '') : '');
                        if ($v !== '') {
                            return $v;
                        }
                    }
                } catch (\Throwable $e) {
                }
            }
        } catch (\Throwable $e) {
        }
        return $default;
    }

    public function index(Request $request) {
        $logo = $this->getConfig('layout', 'logo', '');
        $next = (string) ($request->getParam('next') ?? '/');

        $this->view('site-lock/index', [
            'logo' => $logo,
            'next' => $next,
        ]);
    }

    public function unlock(Request $request) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $next = (string) ($request->getParam('next') ?? '/');
        $pwd = (string) ($request->getParam('password') ?? '');

        $enabled = trim((string) $this->getConfig('sistema', 'site_lock_enabled', '0'));
        $expected = (string) $this->getConfig('sistema', 'site_lock_password', '');
        $enabledBool = ($enabled === '1' || strtolower($enabled) === 'true');

        if (!$enabledBool || $expected === '') {
            $_SESSION['site_lock_ok'] = 1;
            $this->redirect($next !== '' ? $next : '/');
            return;
        }

        if (!hash_equals((string) $expected, (string) $pwd)) {
            $_SESSION['site_lock_ok'] = 0;
            $this->redirect('/site-lock?next=' . urlencode($next) . '&err=1');
            return;
        }

        $_SESSION['site_lock_ok'] = 1;
        $this->redirect($next !== '' ? $next : '/');
    }
}
