<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;

class AdminMarketingCalendarController extends Controller {

    private $db;

    public function __construct() {
        $this->db = \Config\Database::getConnection();
    }

    private function ensureTable(): void {
        try {
            $this->db->exec("CREATE TABLE IF NOT EXISTS marketing_calendario (
                id INT AUTO_INCREMENT PRIMARY KEY,
                titulo VARCHAR(255) NOT NULL,
                descricao TEXT NULL,
                data_evento DATE NOT NULL,
                pais VARCHAR(5) NOT NULL DEFAULT 'BR',
                emoji VARCHAR(10) NULL DEFAULT '📅',
                cor VARCHAR(7) NULL DEFAULT '#3b82f6',
                categoria VARCHAR(50) NULL DEFAULT 'comemorativa',
                ativo TINYINT(1) NOT NULL DEFAULT 1,
                origem ENUM('manual','ia','sistema') NOT NULL DEFAULT 'manual',
                criado_por INT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_data_evento (data_evento),
                INDEX idx_pais (pais),
                INDEX idx_ativo (ativo)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        } catch (\Exception $e) {}
    }

    private function getChatGPTApiKey(): ?string {
        try {
            $st = $this->db->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1");
            $st->execute(['chatgpt_api_key']);
            $v = (string) ($st->fetchColumn() ?: '');
            return $v !== '' ? $v : null;
        } catch (\Exception $e) { return null; }
    }

    private function getChatGPTModel(): string {
        try {
            $st = $this->db->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1");
            $st->execute(['chatgpt_model']);
            $v = trim((string) ($st->fetchColumn() ?: ''));
            return $v !== '' ? $v : 'gpt-4o-mini';
        } catch (\Exception $e) { return 'gpt-4o-mini'; }
    }

    // ==================== PAGE ====================

    public function index(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin']);

        $this->ensureTable();

        $ano = (int) $request->getParam('ano', date('Y'));
        $mes = (int) $request->getParam('mes', 0); // 0 = todos
        $pais = $request->getParam('pais', '');

        $where = ['1=1'];
        $params = [];

        if ($ano > 0) {
            $where[] = "YEAR(data_evento) = :ano";
            $params[':ano'] = $ano;
        }
        if ($mes > 0 && $mes <= 12) {
            $where[] = "MONTH(data_evento) = :mes";
            $params[':mes'] = $mes;
        }
        if ($pais !== '') {
            $where[] = "pais = :pais";
            $params[':pais'] = strtoupper($pais);
        }

        $sql = "SELECT * FROM marketing_calendario WHERE " . implode(' AND ', $where) . " ORDER BY data_evento ASC";
        $st = $this->db->prepare($sql);
        $st->execute($params);
        $eventos = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $data = compact('eventos', 'ano', 'mes', 'pais');

        extract($data);
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';
        ob_start();
        require __DIR__ . '/../Views/admin/marketing/calendario.php';
        $content = ob_get_clean();
        $title = __('admin.marketing_calendar.page_title', 'Calendário de Marketing');
        include __DIR__ . '/../Views/layouts/admin.php';
    }

    // ==================== CRUD ====================

    public function salvar(Request $request) {
        header('Content-Type: application/json; charset=utf-8');
        $auth = new AuthService();
        $auth->requerPerfis(['admin']);
        $this->ensureTable();

        $id = (int) $request->getParam('id', 0);
        $titulo = trim((string) $request->getParam('titulo', ''));
        $descricao = trim((string) $request->getParam('descricao', ''));
        $dataEvento = trim((string) $request->getParam('data_evento', ''));
        $pais = strtoupper(trim((string) $request->getParam('pais', 'BR')));
        $emoji = trim((string) $request->getParam('emoji', '📅'));
        $cor = trim((string) $request->getParam('cor', '#3b82f6'));
        $categoria = trim((string) $request->getParam('categoria', 'comemorativa'));

        if ($titulo === '' || $dataEvento === '') {
            echo json_encode(['ok' => false, 'error' => __('admin.marketing_calendar.title_date_required', 'Título e data são obrigatórios')]);
            exit;
        }

        if (!in_array($pais, ['BR', 'US', 'GLOBAL'], true)) {
            $pais = 'BR';
        }

        $uid = (int) ($_SESSION['usuario_id'] ?? 0);

        if ($id > 0) {
            // Update
            $sql = "UPDATE marketing_calendario SET titulo = :titulo, descricao = :descricao, data_evento = :data, pais = :pais, emoji = :emoji, cor = :cor, categoria = :cat WHERE id = :id";
            $st = $this->db->prepare($sql);
            $st->execute([':titulo' => $titulo, ':descricao' => $descricao, ':data' => $dataEvento, ':pais' => $pais, ':emoji' => $emoji, ':cor' => $cor, ':cat' => $categoria, ':id' => $id]);
        } else {
            // Insert
            $sql = "INSERT INTO marketing_calendario (titulo, descricao, data_evento, pais, emoji, cor, categoria, origem, criado_por) VALUES (:titulo, :descricao, :data, :pais, :emoji, :cor, :cat, 'manual', :uid)";
            $st = $this->db->prepare($sql);
            $st->execute([':titulo' => $titulo, ':descricao' => $descricao, ':data' => $dataEvento, ':pais' => $pais, ':emoji' => $emoji, ':cor' => $cor, ':cat' => $categoria, ':uid' => $uid]);
            $id = (int) $this->db->lastInsertId();
        }

        echo json_encode(['ok' => true, 'id' => $id]);
        exit;
    }

    public function excluir(Request $request) {
        header('Content-Type: application/json; charset=utf-8');
        $auth = new AuthService();
        $auth->requerPerfis(['admin']);
        $this->ensureTable();

        $id = (int) $request->getParam('id', 0);
        if ($id <= 0) {
            echo json_encode(['ok' => false, 'error' => __('admin.marketing_calendar.invalid_id', 'ID inválido')]);
            exit;
        }

        $this->db->prepare("DELETE FROM marketing_calendario WHERE id = ?")->execute([$id]);
        echo json_encode(['ok' => true]);
        exit;
    }

    public function toggle(Request $request) {
        header('Content-Type: application/json; charset=utf-8');
        $auth = new AuthService();
        $auth->requerPerfis(['admin']);
        $this->ensureTable();

        $id = (int) $request->getParam('id', 0);
        if ($id <= 0) {
            echo json_encode(['ok' => false, 'error' => __('admin.marketing_calendar.invalid_id', 'ID inválido')]);
            exit;
        }

        $this->db->prepare("UPDATE marketing_calendario SET ativo = NOT ativo WHERE id = ?")->execute([$id]);
        echo json_encode(['ok' => true]);
        exit;
    }

    // ==================== AI GENERATION ====================

    public function gerarIA(Request $request) {
        header('Content-Type: application/json; charset=utf-8');
        $auth = new AuthService();
        $auth->requerPerfis(['admin']);
        $this->ensureTable();

        $apiKey = $this->getChatGPTApiKey();
        if (!$apiKey) {
            echo json_encode(['ok' => false, 'error' => __('admin.marketing_calendar.api_key_missing', 'API Key do ChatGPT não configurada. Vá em Configurações > Integrações.')]);
            exit;
        }

        $ano = (int) $request->getParam('ano', date('Y'));
        $pais = strtoupper(trim((string) $request->getParam('pais', '')));
        $model = $this->getChatGPTModel();

        $paisLabel = '';
        if ($pais === 'BR') $paisLabel = 'Brasil';
        elseif ($pais === 'US') $paisLabel = 'Estados Unidos';
        else $paisLabel = 'Brasil e Estados Unidos';

        $prompt = "Gere uma lista de datas comemorativas e oportunidades de marketing para {$paisLabel} no ano {$ano}. "
            . "Foque em datas relevantes para e-commerce de moda, acessórios e cosméticos. "
            . "Para cada data inclua: titulo (nome da data), data_evento (formato YYYY-MM-DD), pais (BR ou US), emoji (um emoji), categoria (comemorativa, promocional ou sazonal), descricao (breve dica de marketing, max 100 chars). "
            . "Retorne um JSON com a chave \"eventos\" contendo um array de objetos. Gere pelo menos 20 eventos.";

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => 'Você é um assistente especializado em marketing e e-commerce. Responda apenas com JSON válido, sem markdown.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.4,
            'max_tokens' => 3000,
        ];

        if (strpos($model, 'gpt-4') !== false || strpos($model, 'gpt-3.5') !== false) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://api.openai.com/v1/chat/completions',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 60,
        ]);

        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$resp) {
            echo json_encode(['ok' => false, 'error' => __('admin.marketing_calendar.api_error', 'Erro ao comunicar com a API. HTTP {code}', ['code' => $httpCode])]);
            exit;
        }

        $result = json_decode($resp, true);
        $content = $result['choices'][0]['message']['content'] ?? '';
        $data = json_decode($content, true);

        if (!$data || !isset($data['eventos'])) {
            echo json_encode(['ok' => false, 'error' => __('admin.marketing_calendar.ai_bad_format', 'Resposta da IA não contém formato esperado.'), 'raw' => $content]);
            exit;
        }

        $uid = (int) ($_SESSION['usuario_id'] ?? 0);
        $inseridos = 0;

        foreach ($data['eventos'] as $ev) {
            $titulo = trim((string) ($ev['titulo'] ?? ''));
            $dataEvento = trim((string) ($ev['data_evento'] ?? ''));
            if ($titulo === '' || $dataEvento === '') continue;

            $evPais = strtoupper(trim((string) ($ev['pais'] ?? 'BR')));
            if (!in_array($evPais, ['BR', 'US', 'GLOBAL'], true)) $evPais = 'BR';

            // Evitar duplicatas (mesmo título + mesma data)
            $stCheck = $this->db->prepare("SELECT id FROM marketing_calendario WHERE titulo = :t AND data_evento = :d LIMIT 1");
            $stCheck->execute([':t' => $titulo, ':d' => $dataEvento]);
            if ($stCheck->fetchColumn()) continue;

            $st = $this->db->prepare("INSERT INTO marketing_calendario (titulo, descricao, data_evento, pais, emoji, cor, categoria, origem, criado_por) VALUES (:titulo, :desc, :data, :pais, :emoji, :cor, :cat, 'ia', :uid)");
            $st->execute([
                ':titulo' => $titulo,
                ':desc' => trim((string) ($ev['descricao'] ?? '')),
                ':data' => $dataEvento,
                ':pais' => $evPais,
                ':emoji' => trim((string) ($ev['emoji'] ?? '📅')),
                ':cor' => '#3b82f6',
                ':cat' => trim((string) ($ev['categoria'] ?? 'comemorativa')),
                ':uid' => $uid,
            ]);
            $inseridos++;
        }

        echo json_encode(['ok' => true, 'inseridos' => $inseridos, 'total_gerados' => count($data['eventos'])]);
        exit;
    }

    // ==================== DASHBOARD API (existing) ====================

    public function getDates(Request $request) {
        header('Content-Type: application/json; charset=UTF-8');

        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);

        $this->ensureTable();

        // Tentar buscar do banco primeiro
        $today = date('Y-m-d');
        $stDb = $this->db->prepare("SELECT titulo AS name, data_evento AS date, emoji, pais FROM marketing_calendario WHERE ativo = 1 AND data_evento >= :today ORDER BY data_evento ASC LIMIT 10");
        $stDb->execute([':today' => $today]);
        $dbEvents = $stDb->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        if (!empty($dbEvents)) {
            $result = ['usa' => [], 'brazil' => []];
            foreach ($dbEvents as $ev) {
                $item = ['date' => $ev['date'], 'name' => $ev['name'], 'emoji' => $ev['emoji'] ?: '📅'];
                $diff = (new \DateTime($ev['date']))->diff(new \DateTime($today));
                $item['days_until'] = (int) $diff->days;

                if ($ev['pais'] === 'US') $result['usa'][] = $item;
                else $result['brazil'][] = $item;
            }
            // Limitar a 2 por país
            $result['usa'] = array_slice($result['usa'], 0, 2);
            $result['brazil'] = array_slice($result['brazil'], 0, 2);

            echo json_encode(['ok' => true, 'data' => $result, 'source' => 'database']);
            exit;
        }

        // Fallback: ChatGPT ou datas estáticas
        $cached = $this->getCachedDates();
        if ($cached) {
            echo json_encode(['ok' => true, 'data' => $this->filterUpcoming($cached)]);
            exit;
        }

        $apiKey = $this->getChatGPTApiKey();
        if (!$apiKey) {
            $fallback = $this->getStaticDates();
            echo json_encode(['ok' => true, 'data' => $this->filterUpcoming($fallback), 'source' => 'static']);
            exit;
        }

        $model = $this->getChatGPTModel();
        $currentYear = date('Y');

        $prompt = "Retorne um JSON com as principais datas comemorativas de marketing para o ano {$currentYear}, separadas por país (EUA e Brasil). "
            . "Para cada data, inclua: date (formato YYYY-MM-DD), name (nome da data comemorativa), emoji (um emoji representativo). "
            . "Inclua pelo menos 15 datas para cada país. Foque em datas relevantes para e-commerce e marketing. "
            . "Formato esperado: {\"usa\": [{\"date\": \"YYYY-MM-DD\", \"name\": \"...\", \"emoji\": \"...\"}], \"brazil\": [{\"date\": \"YYYY-MM-DD\", \"name\": \"...\", \"emoji\": \"...\"}]}";

        $payload = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => 'Você é um assistente especializado em marketing e e-commerce. Responda apenas com JSON válido, sem markdown.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.3,
            'max_tokens' => 2000,
        ];

        if (strpos($model, 'gpt-4') !== false || strpos($model, 'gpt-3.5') !== false) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://api.openai.com/v1/chat/completions',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 30,
        ]);

        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200 || !$resp) {
            $fallback = $this->getStaticDates();
            echo json_encode(['ok' => true, 'data' => $this->filterUpcoming($fallback), 'source' => 'static']);
            exit;
        }

        $result = json_decode($resp, true);
        $content = $result['choices'][0]['message']['content'] ?? '';
        $dates = json_decode($content, true);

        if (!$dates || (!isset($dates['usa']) && !isset($dates['brazil']))) {
            $fallback = $this->getStaticDates();
            echo json_encode(['ok' => true, 'data' => $this->filterUpcoming($fallback), 'source' => 'static']);
            exit;
        }

        $this->saveCacheDates($dates);
        echo json_encode(['ok' => true, 'data' => $this->filterUpcoming($dates), 'source' => 'chatgpt']);
        exit;
    }

    // ==================== HELPERS ====================

    private function getCacheFilePath(): string {
        $cacheDir = __DIR__ . '/../../storage/cache';
        if (!is_dir($cacheDir)) @mkdir($cacheDir, 0755, true);
        if (!is_writable($cacheDir)) $cacheDir = sys_get_temp_dir();
        return $cacheDir . '/marketing_calendar_' . date('Y-m') . '.json';
    }

    private function getCachedDates(): ?array {
        $file = $this->getCacheFilePath();
        if (!file_exists($file)) return null;
        $data = json_decode(file_get_contents($file), true);
        if (!$data || !isset($data['timestamp']) || time() - $data['timestamp'] > 86400) return null;
        return $data['dates'] ?? null;
    }

    private function saveCacheDates(array $dates): void {
        file_put_contents($this->getCacheFilePath(), json_encode(['timestamp' => time(), 'dates' => $dates], JSON_UNESCAPED_UNICODE));
    }

    private function filterUpcoming(array $dates): array {
        $today = date('Y-m-d');
        $result = ['usa' => [], 'brazil' => []];
        foreach (['usa', 'brazil'] as $country) {
            if (!isset($dates[$country])) continue;
            $upcoming = array_filter($dates[$country], function($d) use ($today) { return isset($d['date']) && $d['date'] >= $today; });
            usort($upcoming, function($a, $b) { return strcmp($a['date'], $b['date']); });
            $result[$country] = array_slice($upcoming, 0, 2);
            foreach ($result[$country] as &$item) {
                $item['days_until'] = (int) (new \DateTime($item['date']))->diff(new \DateTime($today))->days;
            }
        }
        return $result;
    }

    private function getStaticDates(): array {
        $year = date('Y');
        return [
            'usa' => [
                ['date' => "{$year}-01-01", 'name' => "New Year's Day", 'emoji' => '🎆'],
                ['date' => "{$year}-02-14", 'name' => "Valentine's Day", 'emoji' => '❤️'],
                ['date' => "{$year}-05-11", 'name' => "Mother's Day", 'emoji' => '👩'],
                ['date' => "{$year}-06-15", 'name' => "Father's Day", 'emoji' => '👨'],
                ['date' => "{$year}-07-04", 'name' => "Independence Day", 'emoji' => '🎇'],
                ['date' => "{$year}-10-31", 'name' => "Halloween", 'emoji' => '🎃'],
                ['date' => "{$year}-11-28", 'name' => "Black Friday", 'emoji' => '🛍️'],
                ['date' => "{$year}-12-25", 'name' => "Christmas", 'emoji' => '🎄'],
            ],
            'brazil' => [
                ['date' => "{$year}-02-28", 'name' => "Carnaval", 'emoji' => '🎭'],
                ['date' => "{$year}-03-08", 'name' => "Dia da Mulher", 'emoji' => '👩'],
                ['date' => "{$year}-03-15", 'name' => "Dia do Consumidor", 'emoji' => '🛒'],
                ['date' => "{$year}-05-11", 'name' => "Dia das Mães", 'emoji' => '👩‍👧'],
                ['date' => "{$year}-06-12", 'name' => "Dia dos Namorados", 'emoji' => '❤️'],
                ['date' => "{$year}-08-10", 'name' => "Dia dos Pais", 'emoji' => '👨‍👧'],
                ['date' => "{$year}-09-07", 'name' => "Independência do Brasil", 'emoji' => '🇧🇷'],
                ['date' => "{$year}-10-12", 'name' => "Dia das Crianças", 'emoji' => '🧒'],
                ['date' => "{$year}-11-28", 'name' => "Black Friday", 'emoji' => '🛍️'],
                ['date' => "{$year}-12-25", 'name' => "Natal", 'emoji' => '🎄'],
            ]
        ];
    }
}
