<?php
namespace App\Controllers;

use App\Core\Request;

class AdminPreferencesController extends Controller {

    private $db;

    public function __construct() {
        $this->db = \Config\Database::getConnection();
        $this->ensureTable();
    }

    private function ensureTable(): void {
        try {
            $this->db->query("SELECT 1 FROM admin_user_preferences LIMIT 1");
        } catch (\Exception $e) {
            try {
                $this->db->exec("CREATE TABLE IF NOT EXISTS admin_user_preferences (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    usuario_id INT NOT NULL,
                    idioma VARCHAR(10) NOT NULL DEFAULT 'pt-BR',
                    moeda VARCHAR(3) NOT NULL DEFAULT 'USD',
                    configurado TINYINT(1) NOT NULL DEFAULT 0,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uk_usuario (usuario_id),
                    INDEX idx_usuario (usuario_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
            } catch (\Exception $ex) {}
        }
    }

    /**
     * Salvar preferências (chamado via AJAX)
     */
    public function salvar(Request $request) {
        header('Content-Type: application/json; charset=utf-8');

        if (session_status() === PHP_SESSION_NONE) session_start();
        $uid = (int) ($_SESSION['usuario_id'] ?? 0);
        if ($uid <= 0) {
            echo json_encode(['ok' => false, 'error' => 'Não autenticado']);
            exit;
        }

        $idioma = trim((string) $request->getParam('idioma', 'pt-BR'));
        $moeda = strtoupper(trim((string) $request->getParam('moeda', 'USD')));

        // Validar
        if (!in_array($idioma, ['pt-BR', 'en'], true)) $idioma = 'pt-BR';
        if (!in_array($moeda, ['USD', 'BRL'], true)) $moeda = 'USD';

        // Upsert
        $st = $this->db->prepare("SELECT id FROM admin_user_preferences WHERE usuario_id = ? LIMIT 1");
        $st->execute([$uid]);
        $exists = $st->fetchColumn();

        if ($exists) {
            $this->db->prepare("UPDATE admin_user_preferences SET idioma = ?, moeda = ?, configurado = 1 WHERE usuario_id = ?")->execute([$idioma, $moeda, $uid]);
        } else {
            $this->db->prepare("INSERT INTO admin_user_preferences (usuario_id, idioma, moeda, configurado) VALUES (?, ?, ?, 1)")->execute([$uid, $idioma, $moeda]);
        }

        // Atualizar sessão
        $_SESSION['admin_pref_idioma'] = $idioma;
        $_SESSION['admin_pref_moeda'] = $moeda;
        $_SESSION['admin_pref_configurado'] = 1;

        // Atualizar locale do I18n
        if (class_exists('\\App\\Core\\I18n')) {
            \App\Core\I18n::setLocale($idioma === 'en' ? 'en' : 'pt-BR');
        }

        echo json_encode(['ok' => true]);
        exit;
    }

    /**
     * Buscar preferências do usuário logado (AJAX)
     */
    public function get(Request $request) {
        header('Content-Type: application/json; charset=utf-8');

        if (session_status() === PHP_SESSION_NONE) session_start();
        $uid = (int) ($_SESSION['usuario_id'] ?? 0);
        if ($uid <= 0) {
            echo json_encode(['ok' => false]);
            exit;
        }

        $prefs = $this->getPreferences($uid);
        echo json_encode(['ok' => true, 'prefs' => $prefs]);
        exit;
    }

    /**
     * Buscar preferências de um usuário do banco
     */
    public static function getPreferences(int $userId): array {
        $defaults = ['idioma' => 'pt-BR', 'moeda' => 'USD', 'configurado' => 0];

        try {
            $db = \Config\Database::getConnection();
            $st = $db->prepare("SELECT idioma, moeda, configurado FROM admin_user_preferences WHERE usuario_id = ? LIMIT 1");
            $st->execute([$userId]);
            $row = $st->fetch(\PDO::FETCH_ASSOC);
            if ($row) {
                return [
                    'idioma' => $row['idioma'] ?? 'pt-BR',
                    'moeda' => $row['moeda'] ?? 'USD',
                    'configurado' => (int) ($row['configurado'] ?? 0),
                ];
            }
        } catch (\Exception $e) {}

        return $defaults;
    }

    /**
     * Carregar preferências na sessão (chamado após login)
     */
    public static function loadIntoSession(int $userId): void {
        $prefs = self::getPreferences($userId);
        $_SESSION['admin_pref_idioma'] = $prefs['idioma'];
        $_SESSION['admin_pref_moeda'] = $prefs['moeda'];
        $_SESSION['admin_pref_configurado'] = $prefs['configurado'];

        // Aplicar idioma
        if (class_exists('\\App\\Core\\I18n')) {
            \App\Core\I18n::setLocale($prefs['idioma'] === 'en' ? 'en' : 'pt-BR');
        }
    }
}
