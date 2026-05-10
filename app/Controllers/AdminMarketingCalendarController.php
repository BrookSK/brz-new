<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;

class AdminMarketingCalendarController extends Controller {

    private function getChatGPTApiKey(): ?string {
        try {
            $pdo = \Config\Database::getConnection();
            $st = $pdo->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1");
            $st->execute(['chatgpt_api_key']);
            $v = (string) ($st->fetchColumn() ?: '');
            return $v !== '' ? $v : null;
        } catch (\Exception $e) { return null; }
    }

    private function getChatGPTModel(): string {
        try {
            $pdo = \Config\Database::getConnection();
            $st = $pdo->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1");
            $st->execute(['chatgpt_model']);
            $v = trim((string) ($st->fetchColumn() ?: ''));
            return $v !== '' ? $v : 'gpt-4o-mini';
        } catch (\Exception $e) { return 'gpt-4o-mini'; }
    }

    private function getCacheFilePath(): string {
        $cacheDir = __DIR__ . '/../../storage/cache';
        if (!is_dir($cacheDir)) {
            @mkdir($cacheDir, 0755, true);
        }
        // Fallback para /tmp se não tiver permissão de escrita
        if (!is_writable($cacheDir)) {
            $cacheDir = sys_get_temp_dir();
        }
        $month = date('Y-m');
        return $cacheDir . '/marketing_calendar_' . $month . '.json';
    }

    private function getCachedDates(): ?array {
        $file = $this->getCacheFilePath();
        if (!file_exists($file)) {
            return null;
        }
        $data = json_decode(file_get_contents($file), true);
        if (!$data || !isset($data['timestamp'])) {
            return null;
        }
        // Cache válido por 24 horas
        if (time() - $data['timestamp'] > 86400) {
            return null;
        }
        return $data['dates'] ?? null;
    }

    private function saveCacheDates(array $dates): void {
        $file = $this->getCacheFilePath();
        file_put_contents($file, json_encode([
            'timestamp' => time(),
            'dates' => $dates
        ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    }

    public function getDates(Request $request) {
        header('Content-Type: application/json; charset=UTF-8');

        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);

        // Tentar cache primeiro
        $cached = $this->getCachedDates();
        if ($cached) {
            echo json_encode(['ok' => true, 'data' => $this->filterUpcoming($cached)]);
            exit;
        }

        $apiKey = $this->getChatGPTApiKey();
        if (!$apiKey) {
            // Fallback: datas estáticas se não tiver API key
            $fallback = $this->getStaticDates();
            echo json_encode(['ok' => true, 'data' => $this->filterUpcoming($fallback), 'source' => 'static']);
            exit;
        }

        $model = $this->getChatGPTModel();
        $currentYear = date('Y');
        $currentDate = date('Y-m-d');

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
            // Fallback para datas estáticas
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

        // Salvar no cache
        $this->saveCacheDates($dates);

        echo json_encode(['ok' => true, 'data' => $this->filterUpcoming($dates), 'source' => 'chatgpt']);
        exit;
    }

    private function filterUpcoming(array $dates): array {
        $today = date('Y-m-d');
        $result = ['usa' => [], 'brazil' => []];

        foreach (['usa', 'brazil'] as $country) {
            if (!isset($dates[$country])) continue;
            
            // Filtrar apenas datas futuras e ordenar
            $upcoming = array_filter($dates[$country], function($d) use ($today) {
                return isset($d['date']) && $d['date'] >= $today;
            });
            
            usort($upcoming, function($a, $b) {
                return strcmp($a['date'], $b['date']);
            });

            // Pegar as 2 mais próximas
            $result[$country] = array_slice($upcoming, 0, 2);

            // Calcular dias restantes
            foreach ($result[$country] as &$item) {
                $diff = (new \DateTime($item['date']))->diff(new \DateTime($today));
                $item['days_until'] = (int) $diff->days;
            }
        }

        return $result;
    }

    private function getStaticDates(): array {
        $year = date('Y');
        return [
            'usa' => [
                ['date' => "{$year}-01-01", 'name' => "New Year's Day", 'emoji' => '🎆'],
                ['date' => "{$year}-01-15", 'name' => "Martin Luther King Jr. Day", 'emoji' => '✊'],
                ['date' => "{$year}-02-14", 'name' => "Valentine's Day", 'emoji' => '❤️'],
                ['date' => "{$year}-03-17", 'name' => "St. Patrick's Day", 'emoji' => '☘️'],
                ['date' => "{$year}-04-20", 'name' => "Easter", 'emoji' => '🐣'],
                ['date' => "{$year}-05-11", 'name' => "Mother's Day", 'emoji' => '👩'],
                ['date' => "{$year}-05-26", 'name' => "Memorial Day", 'emoji' => '🇺🇸'],
                ['date' => "{$year}-06-15", 'name' => "Father's Day", 'emoji' => '👨'],
                ['date' => "{$year}-07-04", 'name' => "Independence Day", 'emoji' => '🎇'],
                ['date' => "{$year}-09-01", 'name' => "Labor Day", 'emoji' => '💪'],
                ['date' => "{$year}-10-31", 'name' => "Halloween", 'emoji' => '🎃'],
                ['date' => "{$year}-11-27", 'name' => "Thanksgiving", 'emoji' => '🦃'],
                ['date' => "{$year}-11-28", 'name' => "Black Friday", 'emoji' => '🛍️'],
                ['date' => "{$year}-12-01", 'name' => "Cyber Monday", 'emoji' => '💻'],
                ['date' => "{$year}-12-25", 'name' => "Christmas", 'emoji' => '🎄'],
            ],
            'brazil' => [
                ['date' => "{$year}-01-01", 'name' => "Ano Novo", 'emoji' => '🎆'],
                ['date' => "{$year}-02-28", 'name' => "Carnaval", 'emoji' => '🎭'],
                ['date' => "{$year}-03-08", 'name' => "Dia da Mulher", 'emoji' => '👩'],
                ['date' => "{$year}-03-15", 'name' => "Dia do Consumidor", 'emoji' => '🛒'],
                ['date' => "{$year}-04-21", 'name' => "Tiradentes", 'emoji' => '🇧🇷'],
                ['date' => "{$year}-05-01", 'name' => "Dia do Trabalho", 'emoji' => '💪'],
                ['date' => "{$year}-05-11", 'name' => "Dia das Mães", 'emoji' => '👩‍👧'],
                ['date' => "{$year}-06-12", 'name' => "Dia dos Namorados", 'emoji' => '❤️'],
                ['date' => "{$year}-06-15", 'name' => "Dia do Frete Grátis", 'emoji' => '📦'],
                ['date' => "{$year}-08-10", 'name' => "Dia dos Pais", 'emoji' => '👨‍👧'],
                ['date' => "{$year}-09-07", 'name' => "Independência do Brasil", 'emoji' => '🇧🇷'],
                ['date' => "{$year}-10-12", 'name' => "Dia das Crianças", 'emoji' => '🧒'],
                ['date' => "{$year}-10-15", 'name' => "Dia do Professor", 'emoji' => '📚'],
                ['date' => "{$year}-11-28", 'name' => "Black Friday", 'emoji' => '🛍️'],
                ['date' => "{$year}-12-25", 'name' => "Natal", 'emoji' => '🎄'],
            ]
        ];
    }
}
