<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Url;
use App\Services\AuthService;

class AdminProdutosController extends Controller {

    private function assocGetAny(array $row, array $keys): string {
        if (empty($row)) return '';
        $lower = [];
        foreach ($row as $k => $v) {
            $lk = strtolower(trim((string) $k));
            if ($lk === '') continue;
            if (!array_key_exists($lk, $lower)) {
                $lower[$lk] = trim((string) $v);
            }
        }
        foreach ($keys as $k) {
            $lk = strtolower(trim((string) $k));
            if ($lk === '') continue;
            if (array_key_exists($lk, $lower)) {
                return (string) $lower[$lk];
            }
        }
        return '';
    }

    public function importarProdutosModelo(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte', 'representante']);

        $headers = explode("\t", trim("ID\tTitle\tContent\tExcerpt\tDate\tPost Type\tPermalink\tParent Product ID\tSku\tPrice\tRegular Price\tSale Price\tStock Status\tStock\tExternal Product URL\tTotal Sales\tAttribute Name (pa_cores)\tAttribute Value (pa_cores)\tAttribute In Variations (pa_cores)\tAttribute Is Visible (pa_cores)\tAttribute Is Taxonomy (pa_cores)\tAttribute Name (pa_fragrancia)\tAttribute Value (pa_fragrancia)\tAttribute In Variations (pa_fragrancia)\tAttribute Is Visible (pa_fragrancia)\tAttribute Is Taxonomy (pa_fragrancia)\tAttribute Name (pa_modelo)\tAttribute Value (pa_modelo)\tAttribute In Variations (pa_modelo)\tAttribute Is Visible (pa_modelo)\tAttribute Is Taxonomy (pa_modelo)\tAttribute Name (pa_sabor)\tAttribute Value (pa_sabor)\tAttribute In Variations (pa_sabor)\tAttribute Is Visible (pa_sabor)\tAttribute Is Taxonomy (pa_sabor)\tAttribute Name (pa_tamanho)\tAttribute Value (pa_tamanho)\tAttribute In Variations (pa_tamanho)\tAttribute Is Visible (pa_tamanho)\tAttribute Is Taxonomy (pa_tamanho)\tAttribute Name (Cor)\tAttribute Value (Cor)\tAttribute In Variations (Cor)\tAttribute Is Visible (Cor)\tAttribute Is Taxonomy (Cor)\tAttribute Name (Cores)\tAttribute Value (Cores)\tAttribute In Variations (Cores)\tAttribute Is Visible (Cores)\tAttribute Is Taxonomy (Cores)\tAttribute Name (Estampa)\tAttribute Value (Estampa)\tAttribute In Variations (Estampa)\tAttribute Is Visible (Estampa)\tAttribute Is Taxonomy (Estampa)\tAttribute Name (Fragance)\tAttribute Value (Fragance)\tAttribute In Variations (Fragance)\tAttribute Is Visible (Fragance)\tAttribute Is Taxonomy (Fragance)\tAttribute Name (Fragrance)\tAttribute Value (Fragrance)\tAttribute In Variations (Fragrance)\tAttribute Is Visible (Fragrance)\tAttribute Is Taxonomy (Fragrance)\tAttribute Name (Fragrancia)\tAttribute Value (Fragrancia)\tAttribute In Variations (Fragrancia)\tAttribute Is Visible (Fragrancia)\tAttribute Is Taxonomy (Fragrancia)\tAttribute Name (Funcionalidade)\tAttribute Value (Funcionalidade)\tAttribute In Variations (Funcionalidade)\tAttribute Is Visible (Funcionalidade)\tAttribute Is Taxonomy (Funcionalidade)\tAttribute Name (Modelo)\tAttribute Value (Modelo)\tAttribute In Variations (Modelo)\tAttribute Is Visible (Modelo)\tAttribute Is Taxonomy (Modelo)\tAttribute Name (Quantidade)\tAttribute Value (Quantidade)\tAttribute In Variations (Quantidade)\tAttribute Is Visible (Quantidade)\tAttribute Is Taxonomy (Quantidade)\tAttribute Name (Sabor)\tAttribute Value (Sabor)\tAttribute In Variations (Sabor)\tAttribute Is Visible (Sabor)\tAttribute Is Taxonomy (Sabor)\tAttribute Name (Sabores)\tAttribute Value (Sabores)\tAttribute In Variations (Sabores)\tAttribute Is Visible (Sabores)\tAttribute Is Taxonomy (Sabores)\tAttribute Name (Tamanho)\tAttribute Value (Tamanho)\tAttribute In Variations (Tamanho)\tAttribute Is Visible (Tamanho)\tAttribute Is Taxonomy (Tamanho)\tAttribute Name (Tipo-de-manchas)\tAttribute Value (Tipo-de-manchas)\tAttribute In Variations (Tipo-de-manchas)\tAttribute Is Visible (Tipo-de-manchas)\tAttribute Is Taxonomy (Tipo-de-manchas)\tAttribute Name (Tipos)\tAttribute Value (Tipos)\tAttribute In Variations (Tipos)\tAttribute Is Visible (Tipos)\tAttribute Is Taxonomy (Tipos)\tAttribute Name (Unidades)\tAttribute Value (Unidades)\tAttribute In Variations (Unidades)\tAttribute Is Visible (Unidades)\tAttribute Is Taxonomy (Unidades)\tAttribute Name (Versao)\tAttribute Value (Versao)\tAttribute In Variations (Versao)\tAttribute Is Visible (Versao)\tAttribute Is Taxonomy (Versao)\tShipping Class\tURL\tTitle\tDescription\tFeatured\tURL\tProduct Type\tProduct visibility\tProduct Categories\tProduct Tags\tProduct Visibility\tStatus\tSlug\tTemplate\tParent Slug\tOrder\tWeight\tLength\tWidth\tHeight\tManage Stock\tButton Text\tBackorders\tTax Status\tTax Class\tProduct Image Gallery\tDefault Attributes\tProduct Attributes\tProduct Version\tVariation Description\tChildren"));

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="import_produtos_modelo.csv"');
        echo "\xEF\xBB\xBF";
        $out = fopen('php://output', 'w');
        fputcsv($out, $headers);
        fclose($out);
        exit;
    }

    public function importarProdutosIniciar(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte', 'representante']);

        header('Content-Type: application/json; charset=UTF-8');

        @ini_set('max_execution_time', '0');
        @set_time_limit(0);
        @ini_set('memory_limit', '-1');
        if (function_exists('ignore_user_abort')) {
            @ignore_user_abort(true);
        }
        if (function_exists('session_write_close')) {
            @session_write_close();
        }

        if (!isset($_FILES['produtos_import_csv']) || empty($_FILES['produtos_import_csv']['tmp_name'])) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Arquivo CSV não enviado.']);
            exit;
        }
        if (!empty($_FILES['produtos_import_csv']['error']) && $_FILES['produtos_import_csv']['error'] !== UPLOAD_ERR_OK) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Falha no upload do CSV.']);
            exit;
        }

        $tmpUpload = (string) $_FILES['produtos_import_csv']['tmp_name'];
        $token = bin2hex(random_bytes(16));
        $csvPath = rtrim((string) sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'produtos_import_' . $token . '.csv';
        $statePath = rtrim((string) sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'produtos_import_' . $token . '.json';

        if (!@move_uploaded_file($tmpUpload, $csvPath)) {
            if (!@copy($tmpUpload, $csvPath)) {
                http_response_code(500);
                echo json_encode(['ok' => false, 'error' => 'Não foi possível salvar o arquivo no servidor.']);
                exit;
            }
        }

        $scan = $this->scanProdutosCsv($csvPath);
        if (!($scan['ok'] ?? false)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => (string) ($scan['error'] ?? 'CSV inválido')]);
            exit;
        }

        $state = [
            'token' => $token,
            'csv' => $csvPath,
            'delimiter' => (string) ($scan['delimiter'] ?? ','),
            'hasHeader' => (bool) ($scan['hasHeader'] ?? true),
            'header' => (is_array($scan['header'] ?? null) ? ($scan['header'] ?? null) : null),
            'total' => (int) ($scan['total'] ?? 0),
            'offset' => 0,
            'okCount' => 0,
            'failCount' => 0,
            'done' => false,
            'createdAt' => date('c'),
        ];
        @file_put_contents($statePath, json_encode($state));

        echo json_encode([
            'ok' => true,
            'token' => $token,
            'total' => $state['total'],
            'processed' => 0,
            'okCount' => 0,
            'failCount' => 0,
            'done' => false,
        ]);
        exit;
    }

    public function importarProdutosProcessar(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte', 'representante']);

        header('Content-Type: application/json; charset=UTF-8');

        @ini_set('max_execution_time', '0');
        @set_time_limit(0);

        $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
        $token = trim((string) ($request->getParam('token') ?? ''));
        $batchSize = (int) ($request->getParam('batch') ?? 300);
        if ($batchSize <= 0) $batchSize = 300;
        if ($batchSize > 1000) $batchSize = 1000;

        if ($token === '' || !preg_match('/^[a-f0-9]{32}$/', $token)) {
            http_response_code(400);
            echo json_encode(['ok' => false, 'error' => 'Token inválido.']);
            exit;
        }

        $statePath = rtrim((string) sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'produtos_import_' . $token . '.json';
        if (!is_file($statePath)) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Importação não encontrada (expirada).']);
            exit;
        }

        $stateRaw = @file_get_contents($statePath);
        $state = is_string($stateRaw) ? json_decode($stateRaw, true) : null;
        if (!is_array($state)) {
            http_response_code(500);
            echo json_encode(['ok' => false, 'error' => 'Estado da importação corrompido.']);
            exit;
        }

        if (!empty($state['done'])) {
            echo json_encode([
                'ok' => true,
                'token' => $token,
                'total' => (int) ($state['total'] ?? 0),
                'processed' => (int) ($state['offset'] ?? 0),
                'okCount' => (int) ($state['okCount'] ?? 0),
                'failCount' => (int) ($state['failCount'] ?? 0),
                'done' => true,
            ]);
            exit;
        }

        $csvPath = (string) ($state['csv'] ?? '');
        if ($csvPath === '' || !is_file($csvPath)) {
            http_response_code(404);
            echo json_encode(['ok' => false, 'error' => 'Arquivo CSV não encontrado no servidor.']);
            exit;
        }

        $delimiter = (string) ($state['delimiter'] ?? ',');
        $hasHeader = (bool) ($state['hasHeader'] ?? true);
        $header = (is_array($state['header'] ?? null) ? ($state['header'] ?? null) : null);
        $offset = (int) ($state['offset'] ?? 0);
        if ($offset < 0) $offset = 0;

        $res = $this->processProdutosCsvBatch($pdo, $state, $csvPath, $delimiter, $hasHeader, $header, $offset, $batchSize);

        $state['offset'] = $offset + (int) ($res['processedNow'] ?? 0);
        $state['okCount'] = (int) ($state['okCount'] ?? 0) + (int) ($res['okNow'] ?? 0);
        $state['failCount'] = (int) ($state['failCount'] ?? 0) + (int) ($res['failNow'] ?? 0);
        $total = (int) ($state['total'] ?? 0);
        $processed = (int) ($state['offset'] ?? 0);
        $state['done'] = ($total > 0 && $processed >= $total) || (int) ($res['processedNow'] ?? 0) === 0;

        @file_put_contents($statePath, json_encode($state));

        if (!empty($state['done'])) {
            try { @unlink($csvPath); } catch (\Exception $e) {}
            try { @unlink($statePath); } catch (\Exception $e) {}
        }

        echo json_encode([
            'ok' => true,
            'token' => $token,
            'total' => $total,
            'processed' => $processed,
            'okCount' => (int) ($state['okCount'] ?? 0),
            'failCount' => (int) ($state['failCount'] ?? 0),
            'done' => (bool) ($state['done'] ?? false),
        ]);
        exit;
    }

    private function scanProdutosCsv(string $csvPath): array {
        $fh = @fopen($csvPath, 'r');
        if (!$fh) {
            return ['ok' => false, 'error' => 'Não foi possível ler o CSV.'];
        }

        $candidates = [',', ';', "\t"];
        $best = null;
        $bestDelim = ',';
        $bestCount = 0;
        foreach ($candidates as $d) {
            rewind($fh);
            $row = fgetcsv($fh, 0, $d);
            $cnt = is_array($row) ? count($row) : 0;
            if ($cnt > $bestCount) {
                $bestCount = $cnt;
                $best = $row;
                $bestDelim = $d;
            }
        }
        $first = is_array($best) ? $best : [];
        $delimiter = $bestDelim;

        $normalizeHeader = function($v) {
            $s = trim((string) $v);
            $s = preg_replace('/\s+/', ' ', $s);
            return $s;
        };

        $header = is_array($first) ? array_map($normalizeHeader, $first) : [];
        $hasHeader = !empty($header);
        if ($hasHeader) {
            $joined = strtolower(implode('|', $header));
            $hasSku = (strpos($joined, 'sku') !== false);
            $hasTitleOrName = (strpos($joined, 'title') !== false) || (strpos($joined, 'name') !== false) || (strpos($joined, 'nome') !== false);
            $hasId = (strpos($joined, 'id') !== false);
            if (!$hasSku || (!$hasTitleOrName && !$hasId)) {
                $hasHeader = false;
                $header = null;
                rewind($fh);
            }
        } else {
            rewind($fh);
        }

        $total = 0;
        while (($row = fgetcsv($fh, 0, $delimiter)) !== false) {
            if (!is_array($row) || count($row) < 2) {
                continue;
            }
            $total++;
        }
        fclose($fh);
        return ['ok' => true, 'delimiter' => $delimiter, 'hasHeader' => (bool) $hasHeader, 'header' => $header, 'total' => $total];
    }

    private function processProdutosCsvBatch(\PDO $pdo, array &$state, string $csvPath, string $delimiter, bool $hasHeader, ?array $header, int $offset, int $limit): array {
        $fh = @fopen($csvPath, 'r');
        if (!$fh) {
            return ['processedNow' => 0, 'okNow' => 0, 'failNow' => 0];
        }

        $normalizeHeader = function($v) {
            $s = trim((string) $v);
            $s = preg_replace('/\s+/', ' ', $s);
            return $s;
        };

        if ($hasHeader) {
            $hdrRow = fgetcsv($fh, 0, $delimiter);
            if ($header === null && is_array($hdrRow)) {
                $header = array_map($normalizeHeader, $hdrRow);
            }
        }

        $skipped = 0;
        while ($skipped < $offset && ($rowSkip = fgetcsv($fh, 0, $delimiter)) !== false) {
            $skipped++;
        }

        $processedNow = 0;
        $okNow = 0;
        $failNow = 0;

        $this->ensureImportRowStatusTable($pdo);

        // Tentar reprocessar variações pendentes de batches anteriores
        if (isset($state['pendingVariations']) && is_array($state['pendingVariations']) && !empty($state['pendingVariations'])) {
            $pending = $state['pendingVariations'];
            $state['pendingVariations'] = [];
            foreach ($pending as $rowKey => $assoc) {
                if (!is_array($assoc)) continue;
                try {
                    $this->processProdutoAssocRow($pdo, $assoc);
                    if (is_string($rowKey) && $rowKey !== '') {
                        $this->markImportRowOk($pdo, 'produtos', $rowKey);
                    }
                    $okNow++;
                } catch (\Exception $e) {
                    // Continua pendente
                    if (is_string($rowKey) && $rowKey !== '') {
                        $state['pendingVariations'][$rowKey] = $assoc;
                    }
                }
            }
        }

        while ($processedNow < $limit && ($row = fgetcsv($fh, 0, $delimiter)) !== false) {
            if (!is_array($row) || count($row) < 2) {
                continue;
            }

            $assoc = [];
            if ($hasHeader && is_array($header)) {
                foreach ($header as $i => $k) {
                    if ($k === '') continue;
                    $assoc[$k] = array_key_exists($i, $row) ? (string) $row[$i] : '';
                }
            } else {
                foreach ($row as $i => $v) {
                    $assoc[(string) $i] = (string) $v;
                }
            }

            $isRowEmpty = true;
            foreach ($assoc as $v) {
                if (trim((string) $v) !== '') {
                    $isRowEmpty = false;
                    break;
                }
            }
            if ($isRowEmpty) {
                // Linha vazia no CSV: não é erro, apenas pular
                $processedNow++;
                continue;
            }

            $rowKey = $this->getProdutoImportRowKey($assoc);
            if ($rowKey !== '' && $this->isImportRowOk($pdo, 'produtos', $rowKey)) {
                $okNow++;
                $processedNow++;
                continue;
            }
            try {
                $this->processProdutoAssocRow($pdo, $assoc);
                if ($rowKey !== '') {
                    $this->markImportRowOk($pdo, 'produtos', $rowKey);
                }
                $okNow++;
            } catch (\Exception $e) {
                $msg = $e->getMessage();
                $isPendingParent = (strpos($msg, 'Variação sem vínculo do produto pai') !== false);

                if ($isPendingParent && $rowKey !== '') {
                    // Guardar para tentar novamente em próximos batches (pai pode vir depois)
                    if (!isset($state['pendingVariations']) || !is_array($state['pendingVariations'])) {
                        $state['pendingVariations'] = [];
                    }
                    $state['pendingVariations'][$rowKey] = $assoc;
                } else {
                    if ($rowKey !== '') {
                        $this->markImportRowFail($pdo, 'produtos', $rowKey, $msg);
                    }
                    $failNow++;
                }
            }
            $processedNow++;
        }

        // Se acabou o arquivo (último batch), tentar reprocessar pendências uma última vez.
        // NOTA: aqui não sabemos com certeza se é o último batch, mas se processou menos que o limite,
        // geralmente significa EOF.
        if ($processedNow < $limit && isset($state['pendingVariations']) && is_array($state['pendingVariations']) && !empty($state['pendingVariations'])) {
            $pending = $state['pendingVariations'];
            $state['pendingVariations'] = [];
            foreach ($pending as $rowKey => $assoc) {
                if (!is_array($assoc)) continue;
                try {
                    $this->processProdutoAssocRow($pdo, $assoc);
                    if (is_string($rowKey) && $rowKey !== '') {
                        $this->markImportRowOk($pdo, 'produtos', $rowKey);
                    }
                    $okNow++;
                } catch (\Exception $e) {
                    // Agora sim: marca como falha definitiva
                    if (is_string($rowKey) && $rowKey !== '') {
                        $this->markImportRowFail($pdo, 'produtos', $rowKey, $e->getMessage());
                    }
                    $failNow++;
                }
            }
        }

        fclose($fh);
        return ['processedNow' => $processedNow, 'okNow' => $okNow, 'failNow' => $failNow];
    }

    private function getProdutoImportRowKey(array $assoc): string {
        $sku = strtolower(trim((string) $this->assocGetAny($assoc, ['Sku', 'SKU', 'sku'])));
        $idExt = trim((string) $this->assocGetAny($assoc, ['ID', 'Id', 'id', 'product_id', 'produto_id']));
        $slug = strtolower(trim((string) $this->assocGetAny($assoc, ['Slug', 'slug', 'post_name'])));

        if ($sku !== '') return 'sku:' . $sku;
        if ($idExt !== '') return 'id:' . $idExt;
        if ($slug !== '') return 'slug:' . $slug;
        return '';
    }

    private function ensureImportRowStatusTable(\PDO $pdo): void {
        try {
            $st = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1');
            $st->execute(['import_row_status']);
            $ok = (bool) $st->fetchColumn();
            if ($ok) return;
        } catch (\Exception $e) {
        }

        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS import_row_status (
                id INT AUTO_INCREMENT PRIMARY KEY,
                import_type VARCHAR(40) NOT NULL,
                row_key VARCHAR(191) NOT NULL,
                status VARCHAR(10) NOT NULL,
                attempts INT NOT NULL DEFAULT 0,
                last_error TEXT NULL,
                ok_at DATETIME NULL,
                fail_at DATETIME NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uk_import_row (import_type, row_key),
                KEY idx_import_type_status (import_type, status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (\Exception $e) {
        }
    }

    private function isImportRowOk(\PDO $pdo, string $type, string $rowKey): bool {
        try {
            $st = $pdo->prepare('SELECT status FROM import_row_status WHERE import_type = :t AND row_key = :k LIMIT 1');
            $st->execute([':t' => $type, ':k' => $rowKey]);
            $s = strtolower((string) ($st->fetchColumn() ?: ''));
            return $s === 'ok';
        } catch (\Exception $e) {
            return false;
        }
    }

    private function markImportRowOk(\PDO $pdo, string $type, string $rowKey): void {
        try {
            $st = $pdo->prepare('INSERT INTO import_row_status (import_type, row_key, status, attempts, ok_at) VALUES (:t,:k,\'ok\',1,NOW()) ON DUPLICATE KEY UPDATE status=\'ok\', attempts=attempts+1, last_error=NULL, ok_at=NOW(), updated_at=NOW()');
            $st->execute([':t' => $type, ':k' => $rowKey]);
        } catch (\Exception $e) {
        }
    }

    private function markImportRowFail(\PDO $pdo, string $type, string $rowKey, string $error): void {
        $error = trim((string) $error);
        if (strlen($error) > 2000) {
            $error = substr($error, 0, 2000);
        }
        try {
            $st = $pdo->prepare('INSERT INTO import_row_status (import_type, row_key, status, attempts, last_error, fail_at) VALUES (:t,:k,\'fail\',1,:e,NOW()) ON DUPLICATE KEY UPDATE status=\'fail\', attempts=attempts+1, last_error=:e, fail_at=NOW(), updated_at=NOW()');
            $st->execute([':t' => $type, ':k' => $rowKey, ':e' => $error]);
        } catch (\Exception $e) {
        }
    }

    private function parseMoneyCsv(string $raw): float {
        $raw = trim((string) $raw);
        if ($raw === '') return 0.0;
        $num = str_replace(['R$', 'USD', 'BRL', '$'], '', $raw);
        $num = preg_replace('/\s+/', '', (string) $num);
        if (strpos($num, ',') !== false && strpos($num, '.') !== false) {
            $num = str_replace('.', '', $num);
            $num = str_replace(',', '.', $num);
        } elseif (strpos($num, ',') !== false) {
            $num = str_replace(',', '.', $num);
        }
        return is_numeric($num) ? (float) $num : 0.0;
    }

    private function processProdutoAssocRow(\PDO $pdo, array $row): void {
        $getAny = function(array $keys) use ($row) {
            return $this->assocGetAny($row, $keys);
        };

        $idExt = $getAny(['ID', 'Id', 'id', 'product_id', 'produto_id']);
        $typeRaw = strtolower(trim((string) $getAny(['Product Type', 'product_type', 'Type', 'type', 'tipo'])));
        $sku = $getAny(['Sku', 'SKU', 'sku']);
        $title = $getAny(['Title', 'title', 'Name', 'name', 'nome', 'product_name']);
        $publishedRaw = strtolower(trim((string) $getAny(['Published', 'published'])));
        $statusFromCsv = strtolower(trim((string) $getAny(['Status', 'status'])));
        $manageStockRaw = strtolower(trim((string) $getAny(['Manage Stock', 'manage_stock', 'controla_estoque'])));
        $dateRaw = trim((string) $getAny(['Date', 'date', 'created_at', 'published_at']));
        $excerpt = $getAny(['Excerpt', 'excerpt', 'Short description', 'short_description', 'Descricao curta', 'descricao_curta']);
        $content = $getAny(['Content', 'content', 'Description', 'description', 'Descricao', 'descricao']);
        $slug = $getAny(['Slug', 'slug', 'post_name']);

        $regularPrice = $this->parseMoneyCsv($getAny(['Regular Price', 'Regular price', 'regular_price', 'preco_regular', 'Price', 'price', 'preco', 'valor']));
        $salePrice = $this->parseMoneyCsv($getAny(['Sale Price', 'Sale price', 'sale_price', 'preco_promocao']));
        $price = ($regularPrice > 0 ? $regularPrice : ($salePrice > 0 ? $salePrice : 0));

        $stockRaw = $getAny(['Stock', 'stock', 'Estoque', 'estoque']);
        $stock = (int) ($stockRaw !== '' ? $stockRaw : 0);
        $stockStatus = strtolower($getAny(['Stock Status', 'stock_status']));
        $weight = (float) str_replace(',', '.', $getAny(['Weight (kg)', 'Weight', 'weight', 'peso']));
        $length = (float) str_replace(',', '.', $getAny(['Length (cm)', 'Length', 'length', 'comprimento']));
        $width = (float) str_replace(',', '.', $getAny(['Width (cm)', 'Width', 'width', 'largura']));
        $height = (float) str_replace(',', '.', $getAny(['Height (cm)', 'Height', 'height', 'altura']));
        $statusRaw = ($statusFromCsv !== '' ? $statusFromCsv : $publishedRaw);

        $imagesRaw = $getAny(['URL', 'url', 'Product Image Gallery', 'product_image_gallery', 'Images', 'images', 'Product Image', 'product_image']);
        $tagsRaw = $getAny(['Product Tags', 'product_tags', 'Tags', 'tags']);
        $catsRaw = $getAny(['Product Categories', 'product_categories', 'Categories', 'categories', 'Categoria', 'categoria']);
        $attrsRaw = $getAny(['Product Attributes', 'product_attributes', 'Attributes', 'attributes', 'Default Attributes', 'default_attributes']);
        $childrenRaw = $getAny(['Children', 'children', 'Variations', 'variations', 'Variation Description', 'variation_description']);

        $parentSku = trim((string) $getAny(['Parent SKU', 'parent_sku', 'Parent', 'parent', 'Parent product SKU', 'parent_product_sku']));
        $parentIdExt = trim((string) $getAny(['Parent product ID', 'parent_product_id', 'Parent ID', 'parent_id', 'Parent Product ID']));

        if ($parentSku === '0') {
            $parentSku = '';
        }
        if ($parentIdExt !== '' && ctype_digit($parentIdExt) && (int) $parentIdExt === 0) {
            $parentIdExt = '';
        }

        $toJsonList = function($raw): string {
            $raw = trim((string) $raw);
            if ($raw === '') return '';
            if ($raw[0] === '[' || $raw[0] === '{') {
                return $raw;
            }
            $parts = preg_split('/[\|,]/', $raw);
            $items = [];
            foreach (($parts ?: []) as $p) {
                $p = trim((string) $p);
                if ($p === '') continue;
                $items[] = $p;
            }
            if (empty($items)) return '';
            $items = array_values(array_unique($items));
            return json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        };

        if (trim((string) $tagsRaw) !== '') {
            $tagsRaw = $toJsonList($tagsRaw);
        }
        if (trim((string) $imagesRaw) !== '') {
            $imagesRaw = $toJsonList($imagesRaw);
        }
        if (trim((string) $childrenRaw) !== '') {
            $childrenRaw = $toJsonList($childrenRaw);
        }

        $extractFirstImageUrl = function($rawOrJson): string {
            $s = trim((string) $rawOrJson);
            if ($s === '') return '';

            if ($s[0] === '[' || $s[0] === '{') {
                $decoded = json_decode($s, true);
                if (is_array($decoded)) {
                    if (array_is_list($decoded)) {
                        $first = (string) ($decoded[0] ?? '');
                        $first = trim($first);
                        return $first;
                    }
                    $first = (string) (reset($decoded) ?: '');
                    $first = trim($first);
                    return $first;
                }
            }

            $first = trim((string) preg_split('/[\|,]/', $s)[0]);
            $first = trim($first, " \t\n\r\0\x0B\"'[]");
            return $first;
        };

        // Campo Product Attributes do Woo pode vir como PHP serialized (a:...), que NÃO é JSON.
        // Para evitar erro 4025 em colunas JSON, sempre preferir gerar JSON válido a partir das colunas Attribute Name/Value.
        $attrsRawTrim = trim((string) $attrsRaw);
        $attrsRawIsJsonish = ($attrsRawTrim !== '' && ($attrsRawTrim[0] === '[' || $attrsRawTrim[0] === '{'));
        if ($attrsRawTrim === '' || !$attrsRawIsJsonish) {
            $attrs = [];
            foreach ($row as $k => $v) {
                $k = trim((string) $k);
                if ($k === '') continue;
                if (!preg_match('/^Attribute Name\s*\((.+)\)$/i', $k, $m)) {
                    continue;
                }
                $code = trim((string) ($m[1] ?? ''));
                if ($code === '') continue;

                $name = trim((string) $v);
                if ($name === '') {
                    $name = $code;
                }

                $value = (string) $this->assocGetAny($row, [
                    'Attribute Value (' . $code . ')',
                    'Attribute value (' . $code . ')',
                ]);
                $value = trim($value);

                $inVar = strtolower(trim((string) $this->assocGetAny($row, [
                    'Attribute In Variations (' . $code . ')',
                    'Attribute in variations (' . $code . ')',
                ])));
                $isVisible = strtolower(trim((string) $this->assocGetAny($row, [
                    'Attribute Is Visible (' . $code . ')',
                    'Attribute is visible (' . $code . ')',
                ])));
                $isTax = strtolower(trim((string) $this->assocGetAny($row, [
                    'Attribute Is Taxonomy (' . $code . ')',
                    'Attribute is taxonomy (' . $code . ')',
                ])));

                $attrs[] = [
                    'code' => $code,
                    'name' => $name,
                    'value' => $value,
                    'in_variations' => ($inVar === '1' || $inVar === 'yes' || $inVar === 'true'),
                    'visible' => ($isVisible === '1' || $isVisible === 'yes' || $isVisible === 'true'),
                    'taxonomy' => ($isTax === '1' || $isTax === 'yes' || $isTax === 'true'),
                ];
            }

            if (!empty($attrs)) {
                $attrsRaw = json_encode($attrs, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            } else {
                $attrsRaw = '';
            }
        }

        $catsRaw = trim((string) $catsRaw);
        if ($catsRaw !== '') {
            $parts = preg_split('/\|/', $catsRaw);
            $normalized = [];
            foreach (($parts ?: []) as $p) {
                $p = trim((string) $p);
                if ($p === '') continue;
                $levels = preg_split('/\s*>\s*/', $p);
                $last = trim((string) ($levels ? end($levels) : $p));
                if ($last !== '') {
                    $normalized[] = $last;
                }
            }
            if (!empty($normalized)) {
                $catsRaw = implode('|', $normalized);
            }
        }

        if ($sku === '' && $title === '' && $idExt === '') {
            throw new \RuntimeException('Linha vazia');
        }

        $cols = $this->getTableColumns($pdo, 'produtos');
        if (empty($cols)) {
            throw new \RuntimeException('Tabela produtos não encontrada');
        }

        $pickCol = function(array $cands) use ($cols): string {
            foreach ($cands as $c) {
                if (in_array($c, $cols, true)) return $c;
            }
            return '';
        };

        $colName = $pickCol(['name', 'nome', 'titulo']);
        $colSku = $pickCol(['sku']);
        $colSlug = $pickCol(['slug']);
        $colDesc = $pickCol(['description', 'descricao', 'content']);
        $colShort = $pickCol(['short_description', 'descricao_curta', 'excerpt']);
        $colPrice = $pickCol(['price', 'preco', 'valor']);
        $colRegular = $pickCol(['regular_price']);
        $colSale = $pickCol(['sale_price', 'preco_promocao']);
        $colStock = $pickCol(['stock', 'estoque']);
        $colActive = $pickCol(['active', 'ativo']);
        $colFeatured = '';
        $colWeight = $pickCol(['weight', 'peso']);
        $colLength = $pickCol(['length', 'comprimento']);
        $colWidth = $pickCol(['width', 'largura']);
        $colHeight = $pickCol(['height', 'altura']);
        $colImages = $pickCol(['images']);
        $colTags = $pickCol(['tags']);
        $colAttributes = $pickCol(['attributes']);
        $colVariations = $pickCol(['variations']);
        $colStatus = $pickCol(['status']);
        $colType = $pickCol(['type', 'tipo']);

        $colControlaEstoque = $pickCol(['controla_estoque', 'manage_stock']);
        $colCreatedAt = $pickCol(['created_at']);
        $colPublishedAt = $pickCol(['published_at']);

        $manageStock = null;
        if ($colControlaEstoque !== '' && $manageStockRaw !== '') {
            if ($manageStockRaw === '1' || $manageStockRaw === 'yes' || $manageStockRaw === 'true' || $manageStockRaw === 'sim') {
                $manageStock = 1;
            } elseif ($manageStockRaw === '0' || $manageStockRaw === 'no' || $manageStockRaw === 'false' || $manageStockRaw === 'nao' || $manageStockRaw === 'não') {
                $manageStock = 0;
            }
        }

        $dateSql = '';
        if ($dateRaw !== '') {
            $ts = strtotime($dateRaw);
            if ($ts !== false) {
                $dateSql = date('Y-m-d H:i:s', $ts);
            }
        }

        $colCategoriaId = $pickCol(['categoria_id', 'category_id']);
        $colCategoria = $pickCol(['categoria', 'category']);
        $colFoto = $pickCol(['foto_principal', 'image', 'imagem', 'featured_image']);

        if ($slug === '' && $title !== '') {
            $slug = strtolower(trim((string) $title));
            $slug = preg_replace('/\s+/', '-', $slug);
            $slug = preg_replace('/[^a-z0-9\-]/', '', $slug);
        }

        $isParentRow = ($parentSku === '' && $parentIdExt === '');
        if ($isParentRow) {
            $newPrice = ($price > 0 ? $price : ($regularPrice > 0 ? $regularPrice : ($salePrice > 0 ? $salePrice : 0)));
            if ($newPrice <= 0) {
                throw new \RuntimeException('Preço zero (produto ignorado)');
            }
        }

        if (trim((string) $sku) === '') {
            if (trim((string) $idExt) !== '') {
                $sku = 'imp-' . preg_replace('/\s+/', '', (string) $idExt);
            } elseif (trim((string) $slug) !== '') {
                $sku = 'imp-' . (string) $slug;
            } elseif (trim((string) $title) !== '') {
                $sku = 'imp-' . substr(md5((string) $title), 0, 12);
            }
            $sku = substr((string) $sku, 0, 100);
        }

        $categoriaId = 0;
        if ($colCategoriaId !== '' && $catsRaw !== '') {
            // Categorias podem vir como lista (|) e/ou hierarquia (>)
            $firstCat = trim((string) preg_split('/[\|,]/', (string) $catsRaw)[0]);
            if ($firstCat !== '') {
                try {
                    $st = $pdo->query('SHOW TABLES LIKE "categorias"');
                    $hasCatTable = (bool) ($st && $st->fetchColumn());
                } catch (\Exception $e) {
                    $hasCatTable = false;
                }
                if (!empty($hasCatTable)) {
                    $catSlug = strtolower(trim((string) $firstCat));
                    $catSlug = preg_replace('/\s+/', '-', $catSlug);
                    $catSlug = preg_replace('/[^a-z0-9\-]/', '', $catSlug);
                    try {
                        $stFind = $pdo->prepare('SELECT id FROM categorias WHERE LOWER(slug) = LOWER(?) OR LOWER(nome) = LOWER(?) ORDER BY id DESC LIMIT 1');
                        $stFind->execute([(string) $catSlug, (string) $firstCat]);
                        $categoriaId = (int) ($stFind->fetchColumn() ?: 0);
                    } catch (\Exception $e) {
                        $categoriaId = 0;
                    }
                }
            }
        }

        $produtoId = 0;
        try {
            if ($sku !== '' && $colSku !== '') {
                $st = $pdo->prepare('SELECT id FROM produtos WHERE ' . $colSku . ' = :sku LIMIT 1');
                $st->execute([':sku' => $sku]);
                $produtoId = (int) ($st->fetchColumn() ?: 0);
            }
            if ($produtoId <= 0 && $idExt !== '' && ctype_digit($idExt)) {
                $st = $pdo->prepare('SELECT id FROM produtos WHERE id = :id LIMIT 1');
                $st->execute([':id' => (int) $idExt]);
                $produtoId = (int) ($st->fetchColumn() ?: 0);
            }
        } catch (\Exception $e) {
            $produtoId = 0;
        }

        $active = null;
        if ($colActive !== '') {
            if ($publishedRaw === '1' || $publishedRaw === 'yes' || $publishedRaw === 'true' || $publishedRaw === 'publish' || $publishedRaw === 'published' || $publishedRaw === 'ativo' || $publishedRaw === 'active') {
                $active = 1;
            } elseif ($publishedRaw === '0' || $publishedRaw === 'no' || $publishedRaw === 'false' || $publishedRaw === 'draft' || $publishedRaw === 'rascunho' || $publishedRaw === 'inativo' || $publishedRaw === 'inactive') {
                $active = 0;
            }
        }

        if ($statusRaw === '') {
            $statusRaw = $publishedRaw;
        }
        if (($statusRaw === '1' || $statusRaw === '0' || $statusRaw === 'yes' || $statusRaw === 'no' || $statusRaw === 'true' || $statusRaw === 'false') && $active !== null) {
            $statusRaw = ($active === 1) ? 'publish' : 'draft';
        }

        if ($stockStatus === 'outofstock' && $stock <= 0) {
            $stock = 0;
        }

        $featured = null;

        $isVariationRow = ($typeRaw === 'variation' || $typeRaw === 'variacao' || $typeRaw === 'variação');
        if (!$isVariationRow && ($parentSku !== '' || ($parentIdExt !== '' && ctype_digit($parentIdExt)))) {
            $isVariationRow = true;
        }

        if ($isVariationRow) {
            if (!$this->tableExists($pdo, 'produto_variacoes')) {
                throw new \RuntimeException('CSV possui Type=variation, mas schema de variações (produto_variacoes) não existe');
            }

            $parentProdutoId = 0;
            if ($parentSku !== '') {
                $stP = $pdo->prepare('SELECT id FROM produtos WHERE sku = :sku LIMIT 1');
                $stP->execute([':sku' => $parentSku]);
                $parentProdutoId = (int) ($stP->fetchColumn() ?: 0);
            }
            if ($parentProdutoId <= 0 && $parentIdExt !== '' && ctype_digit($parentIdExt)) {
                $stP = $pdo->prepare('SELECT id FROM produtos WHERE id = :id LIMIT 1');
                $stP->execute([':id' => (int) $parentIdExt]);
                $parentProdutoId = (int) ($stP->fetchColumn() ?: 0);
            }

            if ($parentProdutoId <= 0 && $parentIdExt !== '' && ctype_digit($parentIdExt)) {
                // Parent Product ID do Woo não é o mesmo ID interno do nosso banco.
                // Resolver via produto_meta (onde salvamos o CSV inteiro, incluindo coluna ID).
                if ($this->tableExists($pdo, 'produto_meta')) {
                    try {
                        $stPM = $pdo->prepare("SELECT produto_id FROM produto_meta WHERE meta_key IN ('ID','Id','id','product_id','produto_id','woo_id','external_id') AND meta_value = :v ORDER BY produto_id DESC LIMIT 1");
                        $stPM->execute([':v' => (string) $parentIdExt]);
                        $parentProdutoId = (int) ($stPM->fetchColumn() ?: 0);
                    } catch (\Exception $e) {
                        $parentProdutoId = 0;
                    }
                }
            }

            if ($parentProdutoId <= 0) {
                $findParents = function(string $sql, array $params) use ($pdo): array {
                    $st = $pdo->prepare($sql);
                    $st->execute($params);
                    return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                };

                $typeCol = $colType;
                $typeWhere = ($typeCol !== '' ? (' AND LOWER(COALESCE(p.' . $typeCol . ", '')) = 'variable' ") : '');

                $skuPrefix = '';
                $skuTrim = trim((string) $sku);
                if ($skuTrim !== '') {
                    $parts = preg_split('/[-_\s]+/', $skuTrim);
                    $skuPrefix = trim((string) ($parts[0] ?? ''));
                }

                $namePrefix = '';
                $t = trim((string) $title);
                if ($t !== '') {
                    $namePrefix = (string) preg_split('/\s[-–—]\s|\s:\s/', $t)[0];
                    $namePrefix = trim($namePrefix);
                }

                $cands = [];
                if ($skuPrefix !== '') {
                    $sql = 'SELECT p.id, p.sku, ' . ($colName !== '' ? ('p.' . $colName . ' AS nome') : "'' AS nome")
                        . ' FROM produtos p WHERE 1=1'
                        . $typeWhere
                        . ' AND p.sku LIKE :pref ORDER BY LENGTH(p.sku) DESC, p.id DESC LIMIT 5';
                    $cands = $findParents($sql, [':pref' => $skuPrefix . '%']);
                }

                if (count($cands) !== 1 && $namePrefix !== '') {
                    $sql = 'SELECT p.id, p.sku, ' . ($colName !== '' ? ('p.' . $colName . ' AS nome') : "'' AS nome")
                        . ' FROM produtos p WHERE 1=1'
                        . $typeWhere
                        . ($colName !== '' ? (' AND p.' . $colName . ' LIKE :nm ') : ' AND 1=0 ')
                        . ' ORDER BY p.id DESC LIMIT 5';
                    $cands = $findParents($sql, [':nm' => $namePrefix . '%']);
                }

                if (count($cands) === 1) {
                    $parentProdutoId = (int) ($cands[0]['id'] ?? 0);
                } elseif (count($cands) > 1) {
                    $hint = [];
                    foreach ($cands as $c) {
                        $hint[] = 'id=' . (int) ($c['id'] ?? 0) . ' sku=' . (string) ($c['sku'] ?? '') . ' nome=' . (string) ($c['nome'] ?? '');
                    }
                    throw new \RuntimeException('Variação ambígua: não foi possível identificar produto pai. Candidatos: ' . implode(' | ', $hint));
                }
            }

            if ($parentProdutoId <= 0) {
                throw new \RuntimeException('Variação sem vínculo do produto pai (faltando coluna Parent SKU/Parent product ID no CSV)');
            }

            $variationAttrs = [];
            foreach ($row as $k => $v) {
                $k = trim((string) $k);
                if ($k === '') continue;
                if (!preg_match('/^Attribute Value\s*\((.+)\)$/i', $k, $m)) {
                    continue;
                }
                $code = trim((string) ($m[1] ?? ''));
                if ($code === '') continue;

                $inVar = strtolower(trim((string) $this->assocGetAny($row, [
                    'Attribute In Variations (' . $code . ')',
                    'Attribute in variations (' . $code . ')',
                ])));
                $isInVar = ($inVar === '1' || $inVar === 'yes' || $inVar === 'true' || $inVar === 'sim');
                if (!$isInVar) continue;

                $val = trim((string) $v);
                if ($val === '' || $val === 'no') continue;

                $name = trim((string) $this->assocGetAny($row, [
                    'Attribute Name (' . $code . ')',
                    'Attribute name (' . $code . ')',
                ]));
                if ($name === '') {
                    $name = $code;
                }

                $variationAttrs[] = [
                    'code' => $code,
                    'name' => $name,
                    'value' => $val,
                ];
            }

            $skuTrimVar = trim((string) $sku);
            $skuLooksInvalid = false;
            if ($skuTrimVar === '') {
                $skuLooksInvalid = true;
            } elseif (preg_match('/\s/', $skuTrimVar)) {
                // SKU com espaço costuma ser atributo/nome, não um SKU real
                $skuLooksInvalid = true;
            } elseif (preg_match('/^\d+$/', $skuTrimVar) && strlen($skuTrimVar) <= 3) {
                // Valores muito curtos só numéricos (ex: "9") tendem a ser coluna deslocada
                $skuLooksInvalid = true;
            } elseif (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]{1,}$/', $skuTrimVar)) {
                // Regras mínimas de SKU: sem espaços e com chars comuns
                $skuLooksInvalid = true;
            }

            if ($skuLooksInvalid) {
                $seed = (string) $parentProdutoId
                    . '|' . trim((string) $idExt)
                    . '|' . trim((string) $title)
                    . '|' . (string) $regularPrice
                    . '|' . (string) $salePrice
                    . '|' . (string) $stock
                    . '|' . trim((string) $imagesRaw);
                $sku = 'var-' . (int) $parentProdutoId . '-' . substr(md5($seed), 0, 12);
                $sku = substr((string) $sku, 0, 120);
            }

            $stV = $pdo->prepare('SELECT id FROM produto_variacoes WHERE produto_id = :pid AND sku = :sku LIMIT 1');
            $stV->execute([':pid' => (int) $parentProdutoId, ':sku' => (string) $sku]);
            $varId = (int) ($stV->fetchColumn() ?: 0);

            $sqlCols = $this->getTableColumns($pdo, 'produto_variacoes');
            $set = [];
            $paramsV = [':pid' => (int) $parentProdutoId, ':sku' => (string) $sku];
            if (in_array('price_override', $sqlCols, true)) { $set[] = 'price_override = :po'; $paramsV[':po'] = ($price > 0 ? $price : null); }
            if (in_array('stock', $sqlCols, true)) { $set[] = 'stock = :st'; $paramsV[':st'] = (int) $stock; }
            if (in_array('ativo', $sqlCols, true)) { $set[] = 'ativo = :at'; $paramsV[':at'] = ($active !== null ? (int) $active : 1); }
            if (in_array('updated_at', $sqlCols, true)) { $set[] = 'updated_at = NOW()'; }

            if ($varId > 0) {
                if (!empty($set)) {
                    $stUpV = $pdo->prepare('UPDATE produto_variacoes SET ' . implode(', ', $set) . ' WHERE id = :id');
                    $paramsV[':id'] = (int) $varId;
                    $stUpV->execute($paramsV);
                }
            } else {
                $colsInsV = ['produto_id', 'sku'];
                $valsInsV = [':pid', ':sku'];
                if (in_array('price_override', $sqlCols, true)) { $colsInsV[] = 'price_override'; $valsInsV[] = ':po'; }
                if (in_array('stock', $sqlCols, true)) { $colsInsV[] = 'stock'; $valsInsV[] = ':st'; }
                if (in_array('ativo', $sqlCols, true)) { $colsInsV[] = 'ativo'; $valsInsV[] = ':at'; }
                if (in_array('created_at', $sqlCols, true)) { $colsInsV[] = 'created_at'; $valsInsV[] = 'NOW()'; }
                if (in_array('updated_at', $sqlCols, true)) { $colsInsV[] = 'updated_at'; $valsInsV[] = 'NOW()'; }

                $stInV = $pdo->prepare('INSERT INTO produto_variacoes (' . implode(', ', $colsInsV) . ') VALUES (' . implode(', ', $valsInsV) . ')');
                $paramsV[':po'] = ($price > 0 ? $price : null);
                $paramsV[':st'] = (int) $stock;
                $paramsV[':at'] = ($active !== null ? (int) $active : 1);
                $stInV->execute($paramsV);
                $varId = (int) $pdo->lastInsertId();
            }

            if ($varId > 0 && !empty($variationAttrs)) {
                if ($this->tableExists($pdo, 'variacao_tipos') && $this->tableExists($pdo, 'variacao_opcoes') && $this->tableExists($pdo, 'produto_atributos') && $this->tableExists($pdo, 'produto_variacao_itens')) {
                    foreach ($variationAttrs as $a) {
                        $tipoNome = (string) ($a['name'] ?? '');
                        $tipoSlug = strtolower(trim((string) ($a['code'] ?? $tipoNome)));
                        $tipoSlug = preg_replace('/\s+/', '-', $tipoSlug);
                        $tipoSlug = preg_replace('/[^a-z0-9\-]/', '', $tipoSlug);
                        if ($tipoSlug === '') continue;

                        $stT = $pdo->prepare('SELECT id FROM variacao_tipos WHERE slug = ? LIMIT 1');
                        $stT->execute([$tipoSlug]);
                        $tipoId = (int) ($stT->fetchColumn() ?: 0);
                        if ($tipoId <= 0) {
                            $stInsT = $pdo->prepare('INSERT INTO variacao_tipos (nome, slug, ativo, created_at, updated_at) VALUES (?, ?, 1, NOW(), NOW())');
                            $stInsT->execute([$tipoNome !== '' ? $tipoNome : $tipoSlug, $tipoSlug]);
                            $tipoId = (int) $pdo->lastInsertId();
                        }

                        if ($tipoId > 0) {
                            $stPA = $pdo->prepare('INSERT IGNORE INTO produto_atributos (produto_id, tipo_id, created_at, updated_at) VALUES (?, ?, NOW(), NOW())');
                            $stPA->execute([(int) $parentProdutoId, (int) $tipoId]);

                            $optVal = trim((string) ($a['value'] ?? ''));
                            $optSlug = strtolower($optVal);
                            $optSlug = preg_replace('/\s+/', '-', $optSlug);
                            $optSlug = preg_replace('/[^a-z0-9\-]/', '', $optSlug);
                            if ($optSlug === '') {
                                $optSlug = substr(md5($optVal), 0, 12);
                            }

                            $stO = $pdo->prepare('SELECT id FROM variacao_opcoes WHERE tipo_id = ? AND slug = ? LIMIT 1');
                            $stO->execute([(int) $tipoId, (string) $optSlug]);
                            $opcaoId = (int) ($stO->fetchColumn() ?: 0);
                            if ($opcaoId <= 0) {
                                $stInsO = $pdo->prepare('INSERT INTO variacao_opcoes (tipo_id, valor, slug, ordem, ativo, created_at, updated_at) VALUES (?, ?, ?, 0, 1, NOW(), NOW())');
                                $stInsO->execute([(int) $tipoId, $optVal !== '' ? $optVal : $optSlug, (string) $optSlug]);
                                $opcaoId = (int) $pdo->lastInsertId();
                            }

                            if ($opcaoId > 0) {
                                $stVI = $pdo->prepare('INSERT INTO produto_variacao_itens (produto_variacao_id, tipo_id, opcao_id, created_at, updated_at) VALUES (?, ?, ?, NOW(), NOW()) ON DUPLICATE KEY UPDATE opcao_id = VALUES(opcao_id), updated_at = NOW()');
                                $stVI->execute([(int) $varId, (int) $tipoId, (int) $opcaoId]);
                            }
                        }
                    }
                }
            }
            return;
        }

        if ($produtoId > 0) {
            $existing = [];
            try {
                $stCur = $pdo->prepare('SELECT * FROM produtos WHERE id = :id LIMIT 1');
                $stCur->execute([':id' => (int) $produtoId]);
                $existing = $stCur->fetch(\PDO::FETCH_ASSOC) ?: [];
            } catch (\Exception $e) {
                $existing = [];
            }

            $isEmpty = function($v): bool {
                if ($v === null) return true;
                if (is_string($v)) return trim($v) === '';
                return false;
            };

            $isZeroish = function($v): bool {
                if ($v === null) return false;
                if (is_string($v)) {
                    $vv = trim($v);
                    if ($vv === '') return false;
                    if (!is_numeric($vv)) return false;
                    return ((float) $vv) == 0.0;
                }
                if (is_int($v) || is_float($v)) {
                    return ((float) $v) == 0.0;
                }
                return false;
            };

            $set = [];
            $params = [':id' => (int) $produtoId];

            if ($colName !== '' && $title !== '' && (!array_key_exists($colName, $existing) || $isEmpty($existing[$colName] ?? null))) { $set[] = $colName . ' = :name'; $params[':name'] = $title; }
            if ($colSku !== '' && $sku !== '' && (!array_key_exists($colSku, $existing) || $isEmpty($existing[$colSku] ?? null))) { $set[] = $colSku . ' = :sku'; $params[':sku'] = $sku; }
            if ($colSlug !== '' && $slug !== '' && (!array_key_exists($colSlug, $existing) || $isEmpty($existing[$colSlug] ?? null))) { $set[] = $colSlug . ' = :slug'; $params[':slug'] = $slug; }
            if ($colDesc !== '' && $content !== '' && (!array_key_exists($colDesc, $existing) || $isEmpty($existing[$colDesc] ?? null))) { $set[] = $colDesc . ' = :desc'; $params[':desc'] = $content; }
            if ($colShort !== '' && $excerpt !== '' && (!array_key_exists($colShort, $existing) || $isEmpty($existing[$colShort] ?? null))) { $set[] = $colShort . ' = :short'; $params[':short'] = $excerpt; }
            if ($colPrice !== '') {
                $newPrice = ($price > 0 ? $price : ($regularPrice > 0 ? $regularPrice : 0));
                $curr = $existing[$colPrice] ?? null;
                if ($newPrice > 0 && (!array_key_exists($colPrice, $existing) || $isEmpty($curr) || $isZeroish($curr))) { $set[] = $colPrice . ' = :price'; $params[':price'] = $newPrice; }
            }
            if ($colRegular !== '' && $regularPrice > 0) {
                $curr = $existing[$colRegular] ?? null;
                if (!array_key_exists($colRegular, $existing) || $isEmpty($curr) || $isZeroish($curr)) { $set[] = $colRegular . ' = :rp'; $params[':rp'] = $regularPrice; }
            }
            if ($colSale !== '' && $salePrice > 0) {
                $curr = $existing[$colSale] ?? null;
                if (!array_key_exists($colSale, $existing) || $isEmpty($curr) || $isZeroish($curr)) { $set[] = $colSale . ' = :sp'; $params[':sp'] = $salePrice; }
            }
            if ($colStock !== '') {
                $curr = $existing[$colStock] ?? null;
                if ($stockRaw !== '' && (!array_key_exists($colStock, $existing) || $isEmpty($curr) || $isZeroish($curr))) { $set[] = $colStock . ' = :st'; $params[':st'] = (int) $stock; }
            }
            if ($colActive !== '' && $active !== null && (!array_key_exists($colActive, $existing) || $isEmpty($existing[$colActive] ?? null))) { $set[] = $colActive . ' = :ac'; $params[':ac'] = (int) $active; }
            
            if ($colWeight !== '' && $weight > 0) { $curr = $existing[$colWeight] ?? null; if (!array_key_exists($colWeight, $existing) || $isEmpty($curr) || $isZeroish($curr)) { $set[] = $colWeight . ' = :w'; $params[':w'] = $weight; } }
            if ($colLength !== '' && $length > 0) { $curr = $existing[$colLength] ?? null; if (!array_key_exists($colLength, $existing) || $isEmpty($curr) || $isZeroish($curr)) { $set[] = $colLength . ' = :l'; $params[':l'] = $length; } }
            if ($colWidth !== '' && $width > 0) { $curr = $existing[$colWidth] ?? null; if (!array_key_exists($colWidth, $existing) || $isEmpty($curr) || $isZeroish($curr)) { $set[] = $colWidth . ' = :wd'; $params[':wd'] = $width; } }
            if ($colHeight !== '' && $height > 0) { $curr = $existing[$colHeight] ?? null; if (!array_key_exists($colHeight, $existing) || $isEmpty($curr) || $isZeroish($curr)) { $set[] = $colHeight . ' = :h'; $params[':h'] = $height; } }
            if ($colStatus !== '' && $statusRaw !== '' && (!array_key_exists($colStatus, $existing) || $isEmpty($existing[$colStatus] ?? null))) { $set[] = $colStatus . ' = :sts'; $params[':sts'] = $statusRaw; }
            if ($colImages !== '' && trim((string) $imagesRaw) !== '' && (!array_key_exists($colImages, $existing) || $isEmpty($existing[$colImages] ?? null))) { $set[] = $colImages . ' = :imgs'; $params[':imgs'] = $imagesRaw; }
            if ($colTags !== '' && trim((string) $tagsRaw) !== '' && (!array_key_exists($colTags, $existing) || $isEmpty($existing[$colTags] ?? null))) { $set[] = $colTags . ' = :tags'; $params[':tags'] = $tagsRaw; }
            if ($colAttributes !== '' && trim((string) $attrsRaw) !== '' && (!array_key_exists($colAttributes, $existing) || $isEmpty($existing[$colAttributes] ?? null))) { $set[] = $colAttributes . ' = :attrs'; $params[':attrs'] = $attrsRaw; }
            if ($colVariations !== '' && trim((string) $childrenRaw) !== '' && (!array_key_exists($colVariations, $existing) || $isEmpty($existing[$colVariations] ?? null))) { $set[] = $colVariations . ' = :vars'; $params[':vars'] = $childrenRaw; }
            if ($colCategoriaId !== '' && $categoriaId > 0 && (!array_key_exists($colCategoriaId, $existing) || $isEmpty($existing[$colCategoriaId] ?? null))) { $set[] = $colCategoriaId . ' = :cid'; $params[':cid'] = (int) $categoriaId; }
            if ($colCategoria !== '' && trim((string) $catsRaw) !== '' && (!array_key_exists($colCategoria, $existing) || $isEmpty($existing[$colCategoria] ?? null))) { $set[] = $colCategoria . ' = :cat'; $params[':cat'] = $catsRaw; }
            if ($colFoto !== '' && trim((string) $imagesRaw) !== '' && (!array_key_exists($colFoto, $existing) || $isEmpty($existing[$colFoto] ?? null))) {
                $firstImg = $extractFirstImageUrl($imagesRaw);
                if ($firstImg !== '') { $set[] = $colFoto . ' = :foto'; $params[':foto'] = $firstImg; }
            }
            if ($colControlaEstoque !== '' && $manageStock !== null && (!array_key_exists($colControlaEstoque, $existing) || $isEmpty($existing[$colControlaEstoque] ?? null) || $isZeroish($existing[$colControlaEstoque] ?? null))) {
                $set[] = $colControlaEstoque . ' = :mstock';
                $params[':mstock'] = (int) $manageStock;
            }
            if ($colCreatedAt !== '' && $dateSql !== '' && (!array_key_exists($colCreatedAt, $existing) || $isEmpty($existing[$colCreatedAt] ?? null))) {
                $set[] = $colCreatedAt . ' = :cat';
                $params[':cat'] = $dateSql;
            }
            if ($colPublishedAt !== '' && $dateSql !== '' && (!array_key_exists($colPublishedAt, $existing) || $isEmpty($existing[$colPublishedAt] ?? null))) {
                $set[] = $colPublishedAt . ' = :pat';
                $params[':pat'] = $dateSql;
            }
            if ($colType !== '') {
                $type = $typeRaw;
                if ($type !== '' && (!array_key_exists($colType, $existing) || $isEmpty($existing[$colType] ?? null))) { $set[] = $colType . ' = :tp'; $params[':tp'] = $type; }
            }

            if (in_array('updated_at', $cols, true)) {
                $set[] = 'updated_at = NOW()';
            }

            if (!empty($set)) {
                $sqlUp = 'UPDATE produtos SET ' . implode(', ', $set) . ' WHERE id = :id';
                $st = $pdo->prepare($sqlUp);
                $st->execute($params);
            }
        } else {
            $colsIns = [];
            $valsIns = [];
            $params = [];

            if ($colName !== '') { $colsIns[] = $colName; $valsIns[] = ':name'; $params[':name'] = ($title !== '' ? $title : ($sku !== '' ? $sku : 'Produto')); }
            if ($colSku !== '' && $sku !== '') { $colsIns[] = $colSku; $valsIns[] = ':sku'; $params[':sku'] = $sku; }
            if ($colSlug !== '' && $slug !== '') { $colsIns[] = $colSlug; $valsIns[] = ':slug'; $params[':slug'] = $slug; }
            if ($colDesc !== '' && $content !== '') { $colsIns[] = $colDesc; $valsIns[] = ':desc'; $params[':desc'] = $content; }
            if ($colShort !== '' && $excerpt !== '') { $colsIns[] = $colShort; $valsIns[] = ':short'; $params[':short'] = $excerpt; }
            if ($colPrice !== '') { $colsIns[] = $colPrice; $valsIns[] = ':price'; $params[':price'] = ($price > 0 ? $price : ($regularPrice > 0 ? $regularPrice : 0)); }
            if ($colRegular !== '' && $regularPrice > 0) { $colsIns[] = $colRegular; $valsIns[] = ':rp'; $params[':rp'] = $regularPrice; }
            if ($colSale !== '' && $salePrice > 0) { $colsIns[] = $colSale; $valsIns[] = ':sp'; $params[':sp'] = $salePrice; }
            if ($colStock !== '') { $colsIns[] = $colStock; $valsIns[] = ':st'; $params[':st'] = (int) $stock; }
            if ($colActive !== '' && $active !== null) { $colsIns[] = $colActive; $valsIns[] = ':ac'; $params[':ac'] = (int) $active; }
            
            if ($colWeight !== '' && $weight > 0) { $colsIns[] = $colWeight; $valsIns[] = ':w'; $params[':w'] = $weight; }
            if ($colLength !== '' && $length > 0) { $colsIns[] = $colLength; $valsIns[] = ':l'; $params[':l'] = $length; }
            if ($colWidth !== '' && $width > 0) { $colsIns[] = $colWidth; $valsIns[] = ':wd'; $params[':wd'] = $width; }
            if ($colHeight !== '' && $height > 0) { $colsIns[] = $colHeight; $valsIns[] = ':h'; $params[':h'] = $height; }
            if ($colStatus !== '' && $statusRaw !== '') { $colsIns[] = $colStatus; $valsIns[] = ':sts'; $params[':sts'] = $statusRaw; }
            if ($colImages !== '' && trim((string) $imagesRaw) !== '') { $colsIns[] = $colImages; $valsIns[] = ':imgs'; $params[':imgs'] = $imagesRaw; }
            if ($colTags !== '' && trim((string) $tagsRaw) !== '') { $colsIns[] = $colTags; $valsIns[] = ':tags'; $params[':tags'] = $tagsRaw; }
            if ($colAttributes !== '' && trim((string) $attrsRaw) !== '') { $colsIns[] = $colAttributes; $valsIns[] = ':attrs'; $params[':attrs'] = $attrsRaw; }
            if ($colVariations !== '' && trim((string) $childrenRaw) !== '') { $colsIns[] = $colVariations; $valsIns[] = ':vars'; $params[':vars'] = $childrenRaw; }
            if ($colCategoriaId !== '' && $categoriaId > 0) { $colsIns[] = $colCategoriaId; $valsIns[] = ':cid'; $params[':cid'] = (int) $categoriaId; }
            if ($colCategoria !== '' && trim((string) $catsRaw) !== '') { $colsIns[] = $colCategoria; $valsIns[] = ':cat'; $params[':cat'] = $catsRaw; }
            if ($colFoto !== '' && trim((string) $imagesRaw) !== '') {
                $firstImg = $extractFirstImageUrl($imagesRaw);
                if ($firstImg !== '') { $colsIns[] = $colFoto; $valsIns[] = ':foto'; $params[':foto'] = $firstImg; }
            }

            if ($colType !== '' && $typeRaw !== '') { $colsIns[] = $colType; $valsIns[] = ':tp'; $params[':tp'] = $typeRaw; }

            if ($colControlaEstoque !== '' && $manageStock !== null) { $colsIns[] = $colControlaEstoque; $valsIns[] = ':mstock'; $params[':mstock'] = (int) $manageStock; }
            if ($colCreatedAt !== '' && $dateSql !== '') { $colsIns[] = $colCreatedAt; $valsIns[] = ':cat'; $params[':cat'] = $dateSql; }
            if ($colPublishedAt !== '' && $dateSql !== '') { $colsIns[] = $colPublishedAt; $valsIns[] = ':pat'; $params[':pat'] = $dateSql; }

            if (empty($colsIns)) {
                throw new \RuntimeException('Nenhuma coluna compatível para inserir produto');
            }

            $sqlIn = 'INSERT INTO produtos (' . implode(', ', $colsIns) . ') VALUES (' . implode(', ', $valsIns) . ')';
            $st = $pdo->prepare($sqlIn);
            $st->execute($params);
            $produtoId = (int) $pdo->lastInsertId();
        }

        if ($produtoId <= 0) {
            throw new \RuntimeException('Falha ao persistir produto');
        }

        $this->ensureProdutoMetaTable($pdo);

        foreach ($row as $k => $v) {
            $k = trim((string) $k);
            if ($k === '') continue;
            $this->upsertProdutoMeta($pdo, $produtoId, $k, (string) $v);
        }
    }

    private function ensureProdutoMetaTable(\PDO $pdo): void {
        try {
            $st = $pdo->prepare('SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ? LIMIT 1');
            $st->execute(['produto_meta']);
            $ok = (bool) $st->fetchColumn();
            if ($ok) {
                return;
            }
        } catch (\Exception $e) {
        }

        $pdo->exec("CREATE TABLE IF NOT EXISTS produto_meta (
            id INT AUTO_INCREMENT PRIMARY KEY,
            produto_id INT NOT NULL,
            meta_key VARCHAR(191) NOT NULL,
            meta_value LONGTEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uk_produto_meta (produto_id, meta_key),
            KEY idx_meta_key (meta_key),
            KEY idx_produto_id (produto_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    }

    private function upsertProdutoMeta(\PDO $pdo, int $produtoId, string $key, string $value): void {
        $key = trim($key);
        if ($key === '') return;
        try {
            $stSel = $pdo->prepare('SELECT meta_value FROM produto_meta WHERE produto_id = :pid AND meta_key = :k LIMIT 1');
            $stSel->execute([':pid' => (int) $produtoId, ':k' => $key]);
            $curr = $stSel->fetchColumn();

            $isEmpty = function($v): bool {
                if ($v === null) return true;
                if (is_string($v)) return trim($v) === '';
                return false;
            };

            if ($curr === false) {
                $st = $pdo->prepare('INSERT INTO produto_meta (produto_id, meta_key, meta_value) VALUES (:pid, :k, :v)');
                $st->execute([':pid' => (int) $produtoId, ':k' => $key, ':v' => $value]);
                return;
            }

            if ($isEmpty($curr) && trim((string) $value) !== '') {
                $stUp = $pdo->prepare('UPDATE produto_meta SET meta_value = :v, updated_at = NOW() WHERE produto_id = :pid AND meta_key = :k');
                $stUp->execute([':pid' => (int) $produtoId, ':k' => $key, ':v' => $value]);
            }
        } catch (\Exception $e) {
        }
    }

    private function getSessionPerfil(): string {
        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
        } catch (\Throwable $e) {
        }
        return strtolower(trim((string) ($_SESSION['usuario_perfil'] ?? '')));
    }

    private function getSessionUserId(): int {
        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
        } catch (\Throwable $e) {
        }
        return (int) ($_SESSION['usuario_id'] ?? 0);
    }

    private function getSessionUserEmail(): string {
        try {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
        } catch (\Throwable $e) {
        }
        return (string) ($_SESSION['usuario_email'] ?? '');
    }

    private function requireProdutoOwnerIfRepresentante(\PDO $pdo, int $produtoId): void {
        $perfil = $this->getSessionPerfil();
        if ($perfil !== 'representante') {
            return;
        }
        $uid = $this->getSessionUserId();
        if ($uid <= 0) {
            throw new \Exception('Sessão inválida.');
        }

        $cols = $this->getTableColumns($pdo, 'produtos');
        if (!in_array('representante_id', $cols, true)) {
            throw new \Exception('Schema não possui representante_id em produtos (rodar migration 071).');
        }

        $st = $pdo->prepare('SELECT id FROM produtos WHERE id = ? AND representante_id = ? LIMIT 1');
        $st->execute([(int) $produtoId, (int) $uid]);
        if (!$st->fetch()) {
            throw new \Exception('Acesso negado: produto não pertence ao representante.');
        }
    }

    public function togglePublicacao(Request $request, $id = null) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte', 'representante']);
        $id = (int) ($id ?? $request->getParam('id'));

        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            $this->requireProdutoOwnerIfRepresentante($pdo, (int) $id);

            $cols = $this->getTableColumns($pdo, 'produtos');
            $colActive = null;
            if (in_array('active', $cols, true)) {
                $colActive = 'active';
            } elseif (in_array('ativo', $cols, true)) {
                $colActive = 'ativo';
            }
            if ($colActive === null) {
                throw new \Exception('Campo de publicação não encontrado no schema de produtos.');
            }

            $st = $pdo->prepare('SELECT ' . $colActive . ' FROM produtos WHERE id = ? LIMIT 1');
            $st->execute([$id]);
            $curr = $st->fetchColumn();
            $currInt = (int) ($curr ?? 0);
            $novo = $currInt ? 0 : 1;

            $stUp = $pdo->prepare('UPDATE produtos SET ' . $colActive . ' = ? WHERE id = ?');
            $stUp->execute([$novo, $id]);

            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/admin/representante/produtos'));
            exit;
        } catch (\Exception $e) {
            echo '<div class="alert alert-danger">Erro: ' . $e->getMessage() . '</div>';
            exit;
        }
    }

    private function requireVariacaoOwnerIfRepresentante(\PDO $pdo, int $variacaoId): void {
        $perfil = $this->getSessionPerfil();
        if ($perfil !== 'representante') {
            return;
        }
        $uid = $this->getSessionUserId();
        if ($uid <= 0) {
            throw new \Exception('Sessão inválida.');
        }

        if (!$this->tableExists($pdo, 'produto_variacoes')) {
            throw new \Exception('Schema de variações não encontrado.');
        }

        $colsProd = $this->getTableColumns($pdo, 'produtos');
        if (!in_array('representante_id', $colsProd, true)) {
            throw new \Exception('Schema não possui representante_id em produtos (rodar migration 071).');
        }

        $st = $pdo->prepare('SELECT pr.id FROM produto_variacoes pv INNER JOIN produtos pr ON pr.id = pv.produto_id WHERE pv.id = ? AND pr.representante_id = ? LIMIT 1');
        $st->execute([(int) $variacaoId, (int) $uid]);
        if (!$st->fetch()) {
            throw new \Exception('Acesso negado: variação não pertence ao representante.');
        }
    }

    private function fetchLojasSafe(): array {
        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            $stmt = $pdo->query("SHOW TABLES LIKE 'lojas'");
            $exists = $stmt->fetchColumn();
            if (!$exists) {
                return [];
            }

            $stmtLojas = $pdo->query("SELECT id, nome, slug, ativo FROM lojas ORDER BY nome ASC");
            $rows = $stmtLojas->fetchAll(\PDO::FETCH_ASSOC);
            return $rows ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getProdutoUploadsDir(): string {
        $docRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
        $candidates = [
            $docRoot . '/public/uploads/produtos/',
            $docRoot . '/uploads/produtos/',
        ];

        foreach ($candidates as $dir) {
            if (is_dir($dir)) {
                return rtrim($dir, '/\\') . '/';
            }
        }

        // Prefer the path that matches .htaccess rewrite (/uploads -> public/uploads)
        return rtrim($candidates[0], '/\\') . '/';
    }

    private function ensureDir(string $dir): void {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }

    private function resolveUploadsPublicPath(string $urlPath): ?string {
        $normalized = $this->normalizeUploadsWebPath($urlPath);
        if ($normalized === null) {
            return null;
        }

        $docRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
        $candidates = [
            $docRoot . '/public' . $normalized,
            $docRoot . $normalized,
        ];

        foreach ($candidates as $path) {
            $path = str_replace('\\', '/', $path);
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    private function normalizeUploadsWebPath(string $path): ?string {
        $path = is_string($path) ? trim($path) : '';
        if ($path === '') {
            return null;
        }

        // If a full URL was stored, keep only the path part
        if (preg_match('#^https?://#i', $path)) {
            $parsed = parse_url($path);
            $path = (string) ($parsed['path'] ?? '');
        }

        if ($path === '') {
            return null;
        }

        if ($path[0] !== '/') {
            $path = '/' . $path;
        }

        if (strpos($path, '/uploads/') === 0) {
            return $path;
        }

        return '/uploads/produtos/' . ltrim($path, '/');
    }

    private function isAjaxRequest(): bool {
        return (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
    }

    private function getTableColumns(\PDO $pdo, string $table): array {
        $cols = [];
        try {
            $stmt = $pdo->query('SHOW COLUMNS FROM ' . $table);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);
            foreach ($rows as $r) {
                if (!empty($r['Field'])) {
                    $cols[] = $r['Field'];
                }
            }
        } catch (\Exception $e) {
        }

        return $cols;
    }

    private function getNcmOptions(): array {
        return [
            '09019000' => 'Cafés',
            '09024000' => 'Chá Preto',
            '17041000' => 'Chicletes',
            '17049020' => 'Balas, Confeitos, Pastilhas, Pirulitos',
            '18063110' => 'Chocolates',
            '19012090' => 'Massas para Bolos, Panquecas, Paes',
            '19041000' => 'Pipocas e Cereais',
            '19049000' => 'Salgadinhos e Snacks',
            '19059090' => 'Bolachas e Biscoitos',
            '21011110' => 'Cafés Soluveis',
            '21039021' => 'Temperos e Preparações',
            '21039099' => 'Molhos (geral)',
            '21069030' => 'Suplementos',
            '23091000' => 'Petisco para caes e gatos',
            '30051090' => 'Curativos',
            '33030010' => 'Perfumes',
            '33041000' => 'Maquiagem para os Labios',
            '33042090' => 'Maquiagem para os Olhos',
            '33043000' => 'Manicure e Pedicure',
            '33049910' => 'Cremes de Beleza, Tonicos',
            '33049990' => 'Protetor Solar e Bronzeadores',
            '33051000' => 'Shampoos',
            '33059000' => 'Produtos p Cabelo (geral)',
            '33061000' => 'Creme Dental',
            '33062000' => 'Fio Dental',
            '33069000' => 'Enxaguante Bucal (outros)',
            '33071000' => 'Creme de Barbear',
            '33072090' => 'Desodorantes',
            '33073000' => 'Sais de Banho e outras Preparações',
            '33074900' => 'Cheirinho de Carro e Casa',
            '34011190' => 'Lenços Umedecidos',
            '34013000' => 'Sabonete Liquido, Detergente',
            '34024190' => 'Produtos de Limpeza',
            '34060000' => 'Velas e Pavis',
            '38089199' => 'Repelentes para Corpo',
            '38099190' => 'Lenços Para Secadora',
            '38229000' => 'NIMA, Fita Teste e outros medidores',
            '39204390' => 'Plastico Filme e Semelhantes',
            '39232190' => 'Sacos de Lixo/ Sacos Ziplock',
            '39241000' => 'Utensilios de Cozinha, Banheiro e Geral (PLÁSTICO)',
            '39264000' => 'Decorações e Estatuetas',
            '40149090' => 'Chupetas',
            '42029900' => 'Bolsas (geral)',
            '42010090' => 'Coleiras de Cachorro',
            '48182000' => 'Lenços, lenços (toalhitas) demaquilantes e toalhas de mão',
            '48201000' => 'Post It, Papel para Cartas e Agendas',
            '48202000' => 'Cadernos',
            '48236900' => 'Forro de AirFryer (papel)',
            '49019900' => 'Livros',
            '48025610' => 'Folha Sulfite',
            '62099090' => 'Roupas Bebe (geral)',
            '62121000' => 'Sutias e Topes',
            '63022900' => 'Roupas de Cama',
            '63026000' => 'Toalhas de Banho',
            '63071000' => 'Pano de Chão, Pano Prato, Esponja de Louça',
            '63079090' => 'Brinquedo Pelucia Pet',
            '63080000' => 'Capas de Tecido, Tapetes, Toalhas de Mesa, Guardanapos, Cestos',
            '63090090' => 'Roupas (geral)',
            '64059000' => 'Sapatos em Geral',
            '67049000' => 'Cílios Postiços, Perucas',
            '69119000' => 'Utensilios de Cozinha, Banheiro e Geral (PORCELANA)',
            '70109090' => 'Recipientes de Vidro',
            '70134210' => 'Cafeteiras e Chaleiras (VIDRO)',
            '70134900' => 'Utensilios de Cozinha, Banheiro e Geral  (VIDRO)',
            '71179000' => 'Bijuterias',
            '73102190' => 'Latas (Lavanderia)',
            '76151000' => 'Utensilios de Cozinha (Panelas), Banheiro e Geral (ALUMINIO)',
            '82059000' => 'Ferramentas Manuais',
            '82119290' => 'Facas de Cozinha',
            '82130000' => 'Tesouras',
            '82159990' => 'Talheres (Aço)',
            '84141000' => 'Bombas de Leite Materno',
            '84145990' => 'Ventiladores',
            '84148019' => 'Compressores de Ar',
            '84433240' => 'Impressoras a folhas',
            '84672100' => 'Furadeiras',
            '84716052' => 'Teclados',
            '84716053' => 'Mouses e Canetas Digitais',
            '85044010' => 'Carregadores (geral)',
            '85068090' => 'Pilhas e Baterias',
            '85086000' => 'Aspiradores',
            '85094010' => 'Liquidificadores',
            '85094020' => 'Batedeiras',
            '85094040' => 'Extratores de Suco e Polpas',
            '85094050' => 'Processadores de Alimentos',
            '85098090' => 'Esfregões e Escovas Elétricas de Limpeza',
            '85101000' => 'Aparelhos de Barbear',
            '85102000' => 'Máquinas de Cortar Cabelo',
            '85103000' => 'Aparelhos de Depilar',
            '85163100' => 'Secadores de Cabelo',
            '85163200' => 'Outros Aparelhos para Arranjo de Cabelo',
            '85164000' => 'Ferros de Passar Roupas',
            '85166000' => 'Fornos, Grelhas e Assadeiras',
            '85167100' => 'Aparelhos para fazer chás e cafés',
            '85167990' => 'Dash, Panela Ninja',
            '85171300' => 'Celulares',
            '85183000' => 'Fones de Ouvido',
            '85235190' => 'Cartão de Memória',
            '85258929' => 'Cameras e Baba Eletronicas',
            '85269200' => 'Controles Remotos',
            '85393120' => 'Lampadas',
            '85444200' => 'Fios, Cabos e Outros Condutores',
            '87150000' => 'Carrinhos de Bebê e Suas Partes',
            '90191000' => 'Massageadores',
            '90251990' => 'Termometro Digital',
            '94012000' => 'Cadeirinhas e Assentos para Carros',
            '94017100' => 'Cadeiras de Alimentação',
            '94049000' => 'Travesseiros, Almofadas, Puffes e Edredões',
            '94052900' => 'Luminária e Abajures Elétricos',
            '94055000' => 'Luminarias não eletricas',
            '95030022' => 'Bonecos',
            '95030099' => 'Brinquedos em Geral',
            '95045000' => 'Video Games',
            '95069100' => 'Produtos de Ginastica e Esportes',
            '96032100' => 'Escova de Dentes',
            '96033000' => 'Pincéis de Maquiagem/ Pincéis Artista',
            '96081000' => 'Canetas Esferograficas',
            '96082000' => 'Canetas e Marcadores',
            '96084000' => 'Lapiseiras',
            '96159000' => 'Pentes, Presilhas, Grampos',
            '96162000' => 'Esponja de Maquiagem',
            '96170010' => 'Garrafas e Recipientes Termicos',
            '96190000' => 'Absorventes, Tampões e Fraldas',
            '96200000' => 'Monopés, bipés, tripés e artigos semelhantes.',
        ];
    }

    public function cadastroRapido(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);
        if (!$this->ensureCadastroRapidoAccess($request)) {
            return;
        }
        $this->renderCadastroRapido(null, null);
    }

    public function cadastroRapidoSalvar(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);
        if (!$this->ensureCadastroRapidoAccess($request)) {
            return;
        }
        try {
            $created = $this->salvarCadastroRapido($request);
            $this->renderCadastroRapido($created, null);
        } catch (\Exception $e) {
            $this->renderCadastroRapido(null, $e->getMessage());
        }
    }

    private function ensureCadastroRapidoAccess(Request $request): bool {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!empty($_SESSION['cadastro_rapido_autorizado'])) {
            return true;
        }

        $senha = (string) ($request->getParam('senha', '') ?? '');
        if ($request->getMethod() === 'POST' && $senha !== '') {
            if (hash_equals('sonhodafabi', $senha)) {
                $_SESSION['cadastro_rapido_autorizado'] = true;
                return true;
            }
            $this->renderCadastroRapidoSenha('Senha inválida');
            return false;
        }

        $this->renderCadastroRapidoSenha(null);
        return false;
    }

    private function renderCadastroRapidoSenha(?string $error): void {
        $errorHtml = '';
        if (!empty($error)) {
            $errorHtml = '<div class="alert alert-danger" style="border-radius:14px;">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</div>';
        }

        echo <<<'HTML'
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acesso - Cadastro Rápido</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #0b1f3a;
            --bg: #f6f8fb;
            --radius: 18px;
            --shadow: 0 12px 34px rgba(15, 23, 42, 0.10);
        }
        body { background: var(--bg); }
        .topbar {
            background: radial-gradient(1200px 260px at 50% -60%, rgba(11,31,58,0.18), rgba(11,31,58,0)) ,
                        linear-gradient(180deg, rgba(11,31,58,0.06), rgba(11,31,58,0));
            padding: 16px 0 10px;
        }
        .page-title { color: var(--primary); font-weight: 800; letter-spacing: -0.02em; }
        .subtle { color: rgba(15, 23, 42, 0.62); }
        .glass {
            background: rgba(255,255,255,0.86);
            border: 1px solid rgba(148,163,184,0.24);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            backdrop-filter: blur(10px);
        }
        .form-control, .btn { border-radius: 14px; }
        .btn-primary { background: var(--primary); border-color: var(--primary); }
        .btn-outline-primary { border-color: rgba(11,31,58,0.35); color: var(--primary); }
    </style>
</head>
<body>
    <div class="topbar">
        <div class="container" style="max-width: 560px;">
            <div class="d-flex align-items-center justify-content-between">
                <a href="/" class="btn btn-outline-secondary btn-sm" style="border-radius: 999px;">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div class="text-center">
                    <div class="page-title">Acesso ao cadastro rápido</div>
                    <div class="small subtle">Digite a senha para continuar</div>
                </div>
                <span style="width: 40px;"></span>
            </div>
        </div>
    </div>

    <div class="container pb-4" style="max-width: 560px;">
HTML;

        echo $errorHtml;

        echo <<<'HTML'
        <div class="glass p-3 p-sm-4">
            <form method="POST" action="/admin/produtos/cadastro-rapido">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Senha</label>
                    <input type="password" class="form-control form-control-lg" name="senha" required autocomplete="current-password" placeholder="Digite a senha">
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-lock-open me-2"></i>Entrar
                    </button>
                </div>

                <div class="small subtle mt-3">Após validar, você poderá acessar e salvar produtos sem login (neste navegador).</div>
            </form>
        </div>
    </div>
</body>
</html>
HTML;

        exit;
    }

    public function uploadFotosVariacao(Request $request, $id = null) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte', 'representante']);
        $varId = (int) ($id ?? $request->getParam('id'));
        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            $this->requireVariacaoOwnerIfRepresentante($pdo, (int) $varId);
            $pdo->beginTransaction();

            if (!$this->tableExists($pdo, 'produto_variacao_fotos')) {
                throw new \Exception('Tabela produto_variacao_fotos não encontrada');
            }

            $hasLegenda = false;
            try {
                $stCol = $pdo->prepare("SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = 'produto_variacao_fotos' AND column_name = 'legenda'");
                $stCol->execute();
                $hasLegenda = ((int) $stCol->fetchColumn()) > 0;
            } catch (\Throwable $e) {
                $hasLegenda = false;
            }

            $inserted = [];
            if (isset($_FILES['imagens']) && !empty($_FILES['imagens']['name'][0])) {
                $uploadDir = $this->getProdutoUploadsDir();
                $webDir = '/uploads/produtos/';
                $this->ensureDir($uploadDir);

                // ordem base
                $ordBase = 0;
                try {
                    $stMax = $pdo->prepare('SELECT COALESCE(MAX(ordem),0) FROM produto_variacao_fotos WHERE produto_variacao_id = ?');
                    $stMax->execute([$varId]);
                    $ordBase = (int) ($stMax->fetchColumn() ?: 0);
                    $ordBase++;
                } catch (\Exception $e) {
                    $ordBase = 0;
                }

                foreach ($_FILES['imagens']['name'] as $key => $name) {
                    if (($_FILES['imagens']['error'][$key] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;

                    $fileName = preg_replace('/[^A-Za-z0-9\-_\.]/', '', (string) $name);
                    $fileName = time() . '_' . $varId . '_' . $fileName;
                    $filePath = $uploadDir . $fileName;
                    $webPath = $webDir . $fileName;

                    if (!move_uploaded_file($_FILES['imagens']['tmp_name'][$key], $filePath)) {
                        continue;
                    }

                    if ($hasLegenda) {
                        $stmt = $pdo->prepare('INSERT INTO produto_variacao_fotos (produto_variacao_id, nome_arquivo, arquivo_original, legenda, ordem) VALUES (?, ?, ?, ?, ?)');
                        $stmt->execute([$varId, $webPath, $name, null, $ordBase + (int) $key]);
                    } else {
                        $stmt = $pdo->prepare('INSERT INTO produto_variacao_fotos (produto_variacao_id, nome_arquivo, arquivo_original, ordem) VALUES (?, ?, ?, ?)');
                        $stmt->execute([$varId, $webPath, $name, $ordBase + (int) $key]);
                    }
                    $insertId = (int) $pdo->lastInsertId();
                    $inserted[] = ['id' => $insertId, 'url' => Url::absolute($webPath)];
                }
            }

            $pdo->commit();
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => true, 'fotos' => $inserted]);
            exit;
        } catch (\Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
            exit;
        }
    }

    public function removerFotoVariacao(Request $request, $id = null) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte', 'representante']);
        $fotoId = (int) ($id ?? $request->getParam('id'));
        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            if (!$this->tableExists($pdo, 'produto_variacao_fotos')) {
                throw new \Exception('Tabela produto_variacao_fotos não encontrada');
            }

            $stmt = $pdo->prepare('SELECT nome_arquivo, produto_variacao_id FROM produto_variacao_fotos WHERE id = ?');
            $stmt->execute([$fotoId]);
            $foto = $stmt->fetch(\PDO::FETCH_ASSOC);
            if (!$foto) {
                header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/admin/produtos'));
                exit;
            }

            $this->requireVariacaoOwnerIfRepresentante($pdo, (int) ($foto['produto_variacao_id'] ?? 0));

            $path = (string) ($foto['nome_arquivo'] ?? '');
            $filePath = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($path, '/');
            if ($path !== '' && file_exists($filePath)) {
                @unlink($filePath);
            }

            $stmtD = $pdo->prepare('DELETE FROM produto_variacao_fotos WHERE id = ?');
            $stmtD->execute([$fotoId]);

            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/admin/produtos'));
            exit;
        } catch (\Exception $e) {
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/admin/produtos'));
            exit;
        }
    }

    public function salvarOrdemFotosVariacao(Request $request, $id = null) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte', 'representante']);
        $varId = (int) ($id ?? $request->getParam('id'));
        $ordens = $request->getParam('ordens_variacao', []);
        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            $this->requireVariacaoOwnerIfRepresentante($pdo, (int) $varId);
            if (!$this->tableExists($pdo, 'produto_variacao_fotos')) {
                throw new \Exception('Tabela produto_variacao_fotos não encontrada');
            }

            $pdo->beginTransaction();
            if (is_array($ordens)) {
                foreach ($ordens as $fotoId => $ordem) {
                    $fotoId = (int) $fotoId;
                    $ordem = (int) $ordem;
                    if ($fotoId <= 0) continue;
                    $st = $pdo->prepare('UPDATE produto_variacao_fotos SET ordem = ? WHERE id = ? AND produto_variacao_id = ?');
                    $st->execute([$ordem, $fotoId, $varId]);
                }
            }
            $pdo->commit();
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/admin/produtos'));
            exit;
        } catch (\Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/admin/produtos'));
            exit;
        }
    }

    private function salvarCadastroRapido(Request $request): array {
        $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
        $pdo->beginTransaction();

        $cols = $this->getTableColumns($pdo, 'produtos');

        $name = (string) $request->getParam('name');
        $price = $this->parseMoneyToDb($request->getParam('price'));
        $weight = str_replace([','], ['.'], (string) $request->getParam('weight'));
        $stock = (int) $request->getParam('stock', 999);
        $featured = $request->getParam('featured') ? 1 : 0;

        if (trim($name) === '') {
            throw new \Exception('Nome é obrigatório');
        }

        $data = [];
        if (in_array('name', $cols, true)) {
            $data['name'] = $name;
        } elseif (in_array('nome', $cols, true)) {
            $data['nome'] = $name;
        }

        if (in_array('price', $cols, true)) $data['price'] = $price;
        if (in_array('valor', $cols, true) && !isset($data['price'])) $data['valor'] = $price;

        if (in_array('weight', $cols, true)) $data['weight'] = $weight;
        if (in_array('peso', $cols, true) && !isset($data['weight'])) $data['peso'] = $weight;

        if (in_array('stock', $cols, true)) $data['stock'] = $stock;
        if (in_array('estoque', $cols, true) && !isset($data['stock'])) $data['estoque'] = $stock;

        if (in_array('status', $cols, true)) $data['status'] = 'published';
        if (in_array('active', $cols, true)) $data['active'] = 1;
        if (in_array('ativo', $cols, true) && !isset($data['active'])) $data['ativo'] = 1;
        if (in_array('featured', $cols, true)) $data['featured'] = $featured;

        if (in_array('created_at', $cols, true)) $data['created_at'] = date('Y-m-d H:i:s');
        if (in_array('updated_at', $cols, true)) $data['updated_at'] = date('Y-m-d H:i:s');

        $columnsSql = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        $stmt = $pdo->prepare("INSERT INTO produtos ({$columnsSql}) VALUES ({$placeholders})");
        foreach ($data as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->execute();

        $produtoId = (int) $pdo->lastInsertId();
        $fotoWebPath = '';

        if (isset($_FILES['capa']) && !empty($_FILES['capa']['name']) && ($_FILES['capa']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $uploadDir = $this->getProdutoUploadsDir();
            $webDir = '/uploads/produtos/';
            $this->ensureDir($uploadDir);

            $orig = (string) $_FILES['capa']['name'];
            $fileName = preg_replace('/[^A-Za-z0-9\-_\.]/', '', $orig);
            $fileName = time() . '_' . $fileName;
            $filePath = $uploadDir . $fileName;
            $fotoWebPath = $webDir . $fileName;

            if (move_uploaded_file($_FILES['capa']['tmp_name'], $filePath)) {
                if (in_array('foto_principal', $cols, true)) {
                    $stmtCover = $pdo->prepare('UPDATE produtos SET foto_principal = ? WHERE id = ?');
                    $stmtCover->execute([$fotoWebPath, $produtoId]);
                }
            }
        }

        $pdo->commit();

        return [
            'id' => $produtoId,
            'name' => $name,
            'price' => $price,
            'weight' => $weight,
            'stock' => $stock,
            'featured' => $featured,
            'foto_principal' => $fotoWebPath,
        ];
    }

    private function renderCadastroRapido(?array $created, ?string $error): void {
        $successHtml = '';
        if (!empty($error)) {
            $successHtml = '<div class="alert alert-danger" style="border-radius:14px;">' . htmlspecialchars($error, ENT_QUOTES, 'UTF-8') . '</div>';
        } elseif (is_array($created) && !empty($created['id'])) {
            $id = (int) $created['id'];
            $nome = htmlspecialchars((string) ($created['name'] ?? ''), ENT_QUOTES, 'UTF-8');
            $foto = (string) ($created['foto_principal'] ?? '');
            $valor = htmlspecialchars((string) ($created['price'] ?? ''), ENT_QUOTES, 'UTF-8');
            $peso = htmlspecialchars((string) ($created['weight'] ?? ''), ENT_QUOTES, 'UTF-8');
            $estoque = htmlspecialchars((string) ($created['stock'] ?? ''), ENT_QUOTES, 'UTF-8');
            $destaque = !empty($created['featured']) ? 'Sim' : 'Não';
            if ($foto === '') {
                $foto = '/uploads/produtos/placeholder.jpg';
            }
            $fotoEsc = htmlspecialchars($foto, ENT_QUOTES, 'UTF-8');
            $link = '/produto/detalhes/' . $id;
            $linkEsc = htmlspecialchars($link, ENT_QUOTES, 'UTF-8');

            $successHtml = '<div class="alert alert-success d-flex align-items-start gap-2" role="alert" style="border-radius: 14px;">'
                . '<i class="fas fa-check-circle mt-1"></i>'
                . '<div><div class="fw-bold">Produto salvo com sucesso.</div><div class="small">Se estiver como destaque, ele deve aparecer na Home.</div></div>'
                . '</div>'
                . '<div class="card border-0 shadow-sm mb-3" style="border-radius: 18px; overflow: hidden;">'
                . '<div class="row g-0">'
                . '<div class="col-4" style="min-height: 92px;"><img src="' . $fotoEsc . '" alt="' . $nome . '" style="width:100%;height:100%;object-fit:cover;min-height:92px;"></div>'
                . '<div class="col-8"><div class="card-body py-3">'
                . '<div class="fw-bold" style="line-height:1.2">' . $nome . '</div>'
                . '<div class="small text-muted">Link do produto</div>'
                . '<div class="small" style="word-break: break-all;"><a href="' . $linkEsc . '" target="_blank">' . $linkEsc . '</a></div>'
                . '<div class="small text-muted mt-2">Detalhes</div>'
                . '<div class="small">Valor (USD): <span class="fw-semibold">$ ' . $valor . '</span></div>'
                . '<div class="small">Peso (kg): <span class="fw-semibold">' . $peso . '</span></div>'
                . '<div class="small">Estoque: <span class="fw-semibold">' . $estoque . '</span></div>'
                . '<div class="small">Destaque: <span class="fw-semibold">' . htmlspecialchars($destaque, ENT_QUOTES, 'UTF-8') . '</span></div>'
                . '<div class="d-grid gap-2 mt-2">'
                . '<a class="btn btn-outline-primary" href="' . $linkEsc . '" target="_blank"><i class="fas fa-external-link-alt me-2"></i>Abrir produto</a>'
                . '<a class="btn btn-primary" href="/admin/produtos/cadastro-rapido"><i class="fas fa-plus me-2"></i>Novo envio</a>'
                . '</div>'
                . '</div></div>'
                . '</div>'
                . '</div>';
        }

        echo <<<'HTML'
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro Rápido - Produtos</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #0b1f3a;
            --bg: #f6f8fb;
            --radius: 18px;
            --shadow: 0 12px 34px rgba(15, 23, 42, 0.10);
        }
        body { background: var(--bg); }
        .topbar {
            background: radial-gradient(1200px 260px at 50% -60%, rgba(11,31,58,0.18), rgba(11,31,58,0)) ,
                        linear-gradient(180deg, rgba(11,31,58,0.06), rgba(11,31,58,0));
            padding: 16px 0 10px;
        }
        .page-title { color: var(--primary); font-weight: 800; letter-spacing: -0.02em; }
        .subtle { color: rgba(15, 23, 42, 0.62); }
        .glass {
            background: rgba(255,255,255,0.86);
            border: 1px solid rgba(148,163,184,0.24);
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            backdrop-filter: blur(10px);
        }
        .form-control, .input-group-text, .btn { border-radius: 14px; }
        .input-group .input-group-text { border-top-right-radius: 0; border-bottom-right-radius: 0; }
        .input-group .form-control { border-top-left-radius: 0; border-bottom-left-radius: 0; }
        .btn-primary { background: var(--primary); border-color: var(--primary); }
        .btn-outline-primary { border-color: rgba(11,31,58,0.35); color: var(--primary); }
    </style>
</head>
<body>
    <div class="topbar">
        <div class="container" style="max-width: 560px;">
            <div class="d-flex align-items-center justify-content-between">
                <a href="/admin/produtos" class="btn btn-outline-secondary btn-sm" style="border-radius: 999px;">
                    <i class="fas fa-arrow-left"></i>
                </a>
                <div class="text-center">
                    <div class="page-title">Cadastro rápido</div>
                    <div class="small subtle">Mobile-first, envio rápido para Home</div>
                </div>
                <span style="width: 40px;"></span>
            </div>
        </div>
    </div>

    <div class="container pb-4" style="max-width: 560px;">
HTML;

        echo $successHtml;

        echo <<<'HTML'
        <div class="glass p-3 p-sm-4">
            <form method="POST" action="/admin/produtos/cadastro-rapido/salvar" enctype="multipart/form-data">
                <div class="mb-3">
                    <label class="form-label fw-semibold">Foto do produto</label>
                    <input type="file" class="form-control" name="capa" accept="image/*" id="capaInput">
                    <div id="capaPreview" class="mt-3"></div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Nome</label>
                    <input type="text" class="form-control form-control-lg" name="name" required autocomplete="off" placeholder="Ex: iPhone 15 Pro">
                </div>

                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label fw-semibold">Valor (USD)</label>
                        <div class="input-group input-group-lg">
                            <span class="input-group-text">$</span>
                            <input type="text" class="form-control" name="price" required inputmode="decimal" placeholder="0,00">
                        </div>
                    </div>
                    <div class="col-6">
                        <label class="form-label fw-semibold">Peso (kg)</label>
                        <input type="text" class="form-control form-control-lg" name="weight" required inputmode="decimal" placeholder="0,000">
                    </div>
                </div>

                <div class="mt-3">
                    <label class="form-label fw-semibold">Estoque</label>
                    <input type="number" class="form-control form-control-lg" name="stock" value="999" min="0" step="1">
                    <div class="small subtle mt-1">Pré-preenchido com 999 para garantir disponibilidade.</div>
                </div>

                <div class="form-check form-switch mt-3">
                    <input class="form-check-input" type="checkbox" role="switch" id="featuredSwitch" name="featured" value="1" checked>
                    <label class="form-check-label fw-semibold" for="featuredSwitch">Destaque (aparece na Home)</label>
                </div>

                <div class="d-grid mt-4">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-bolt me-2"></i>Salvar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        var capaInput = document.getElementById('capaInput');
        if (capaInput) {
            capaInput.addEventListener('change', function(e) {
                const preview = document.getElementById('capaPreview');
                if (!preview) return;
                preview.innerHTML = '';

                const file = (e.target.files || [])[0];
                if (file && file.type && file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(ev) {
                        const img = document.createElement('img');
                        img.src = ev.target.result;
                        img.style.width = '100%';
                        img.style.maxHeight = '240px';
                        img.style.objectFit = 'cover';
                        img.style.borderRadius = '16px';
                        img.style.boxShadow = '0 14px 36px rgba(15, 23, 42, 0.14)';
                        preview.appendChild(img);
                    };
                    reader.readAsDataURL(file);
                }
            });
        }
    </script>
</body>
</html>
HTML;

            exit;

        exit;
    }

    public function index(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte', 'representante']);

        $perfil = $this->getSessionPerfil();
        $repId = $this->getSessionUserId();

        $isRepresentante = ($perfil === 'representante');
        $sidebarActive = $isRepresentante ? 'rep_produtos' : 'produtos';

        $pagina = (int) $request->getParam('pagina', 1);
        if ($pagina <= 0) $pagina = 1;
        $limite = 20;
        $offset = ($pagina - 1) * $limite;
        $busca = (string) $request->getParam('busca', '');

        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');

            $params = [];
            $where = ' WHERE 1=1 ';
            $where .= " AND NOT (LOWER(COALESCE(p.status,'')) = 'archived' OR p.active = 0) ";
            if ($perfil === 'representante') {
                if ($repId <= 0) {
                    header('Location: /login');
                    exit;
                }
                $where .= ' AND p.representante_id = :rep_id ';
                $params[':rep_id'] = $repId;
            }
            if (trim($busca) !== '') {
                $where .= ' AND (p.name LIKE :busca OR p.sku LIKE :busca) ';
                $params[':busca'] = '%' . $busca . '%';
            }

            $sql = 'SELECT p.* FROM produtos p' . $where . ' ORDER BY p.name ASC, p.id ASC LIMIT :limite OFFSET :offset';
            $stmt = $pdo->prepare($sql);
            foreach ($params as $k => $v) {
                if ($k === ':rep_id') {
                    $stmt->bindValue($k, (int) $v, \PDO::PARAM_INT);
                } else {
                    $stmt->bindValue($k, (string) $v);
                }
            }
            $stmt->bindValue(':limite', (int) $limite, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', (int) $offset, \PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            $sqlTotal = 'SELECT COUNT(*) FROM produtos p' . $where;
            $stmtT = $pdo->prepare($sqlTotal);
            foreach ($params as $k => $v) {
                if ($k === ':rep_id') {
                    $stmtT->bindValue($k, (int) $v, \PDO::PARAM_INT);
                } else {
                    $stmtT->bindValue($k, (string) $v);
                }
            }
            $stmtT->execute();
            $total = (int) ($stmtT->fetchColumn() ?: 0);
            $totalPaginas = (int) ceil($total / $limite);

            $produtos = [];
            foreach ($rows as $r) {
                $id = (int) ($r['id'] ?? 0);
                $img = (string) ($r['foto_principal'] ?? '');
                if ($img === '') {
                    $img = '/uploads/produtos/placeholder.jpg';
                }
                $produtos[] = [
                    'id' => $id,
                    'name' => (string) ($r['name'] ?? ($r['nome'] ?? '')),
                    'sku' => (string) ($r['sku'] ?? ''),
                    'price' => (float) ($r['price'] ?? ($r['preco'] ?? ($r['valor'] ?? 0))),
                    'active' => (int) ($r['active'] ?? ($r['ativo'] ?? 1)),
                    'imagem' => $img,
                ];
            }

        } catch (\Exception $e) {
            echo '<div class="alert alert-danger">Erro: ' . $e->getMessage() . '</div>';
            exit;
        }

        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        ob_start();

        $urlNovo = $isRepresentante ? '/admin/produtos/cadastro-representante' : '/admin/produtos/novo';
        $urlCadastroRapido = $isRepresentante ? '/admin/produtos/cadastro-representante' : '/admin/produtos/cadastro-rapido';

        echo '<div class="pt-3">'
            . '<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center mb-4 border-bottom" style="padding-bottom: 12px;">'
            . '<h1 class="h2">Produtos (' . (int) $total . ')</h1>'
            . '<div class="d-flex gap-2">'
            . '<a href="/admin/produtos/arquivados" class="btn btn-outline-dark"><i class="fas fa-archive"></i> Arquivados</a>'
            . '<a href="' . htmlspecialchars($urlCadastroRapido, ENT_QUOTES, 'UTF-8') . '" class="btn btn-outline-primary"><i class="fas fa-bolt"></i> Cadastro rápido</a>'
            . '<a href="' . htmlspecialchars($urlNovo, ENT_QUOTES, 'UTF-8') . '" class="btn btn-primary"><i class="fas fa-plus"></i> Novo</a>'
            . '</div>'
            . '</div>';

        echo '<form method="GET" class="row g-3 mb-4">'
            . '<div class="col-md-8">'
            . '<input type="text" class="form-control" name="busca" placeholder="Buscar produto..." value="' . htmlspecialchars($busca, ENT_QUOTES, 'UTF-8') . '">' 
            . '</div>'
            . '<div class="col-md-4">'
            . '<button type="submit" class="btn btn-outline-primary"><i class="fas fa-search"></i> Buscar</button>'
            . '</div>'
            . '</form>';

        echo '<div class="table-responsive">'
            . '<table class="table table-hover align-middle">'
            . '<thead><tr>'
            . '<th style="width:72px">Imagem</th>'
            . '<th>Nome</th>'
            . '<th style="width:160px">SKU</th>'
            . '<th style="width:140px">Preço</th>'
            . '<th style="width:110px">Status</th>'
            . '<th style="width:150px">Ações</th>'
            . '</tr></thead><tbody>';

        foreach ($produtos as $produto) {
            $urlEditar = $isRepresentante ? ('/admin/representante/produtos/editar/' . (int) $produto['id']) : ('/admin/produtos/editar/' . (int) $produto['id']);
            $img = htmlspecialchars((string) $produto['imagem'], ENT_QUOTES, 'UTF-8');
            $nome = htmlspecialchars((string) $produto['name'], ENT_QUOTES, 'UTF-8');
            $sku = htmlspecialchars((string) $produto['sku'], ENT_QUOTES, 'UTF-8');
            $preco = '$' . number_format((float) $produto['price'], 2, '.', ',');
            $badge = ((int) $produto['active'] ? 'bg-success' : 'bg-danger');
            $label = ((int) $produto['active'] ? 'Ativo' : 'Inativo');

            echo '<tr>'
                . '<td><img src="' . $img . '" alt="' . $nome . '" style="width:100px;height:100px;object-fit:cover;border-radius:12px;border:1px solid rgba(0,0,0,0.06);"></td>'
                . '<td><div class="fw-bold" style="font-size: 1.05rem;">' . $nome . '</div><div class="text-muted small">#' . (int) $produto['id'] . '</div></td>'
                . '<td><span class="text-muted small">' . $sku . '</span></td>'
                . '<td><span class="fw-semibold">' . htmlspecialchars($preco, ENT_QUOTES, 'UTF-8') . '</span></td>'
                . '<td><span class="badge ' . $badge . '">' . $label . '</span></td>'
                . '<td>'
                . '<div class="btn-group btn-group-sm" role="group">'
                . '<a href="' . htmlspecialchars($urlEditar, ENT_QUOTES, 'UTF-8') . '" class="btn btn-outline-warning" title="Editar"><i class="fas fa-edit"></i></a>'
                . '<form method="POST" action="/admin/produtos/excluir/' . (int) $produto['id'] . '" style="display:inline;">'
                . '<button type="submit" onclick="return confirm(\'Tem certeza?\')" class="btn btn-outline-danger" title="Excluir"><i class="fas fa-trash"></i></button>'
                . '</form>'
                . '</div>'
                . '</td>'
                . '</tr>';
        }

        echo '</tbody></table></div>';

        if ($totalPaginas > 1) {
            $base = $isRepresentante ? '/admin/representante/produtos' : '/admin/produtos';
            $mkUrl = function(int $p) use ($base, $busca): string {
                return $base . "?pagina={$p}" . (trim($busca) !== '' ? "&busca=" . urlencode($busca) : "");
            };

            $start = max(1, $pagina - 1);
            $end = min($totalPaginas, $pagina + 1);
            if (($end - $start + 1) < 3) {
                if ($start === 1) {
                    $end = min($totalPaginas, $start + 2);
                } elseif ($end === $totalPaginas) {
                    $start = max(1, $end - 2);
                }
            }

            echo '<nav class="mt-4"><ul class="pagination justify-content-center">';

            $prev = max(1, $pagina - 1);
            $prevDisabled = ($pagina <= 1);
            echo '<li class="page-item ' . ($prevDisabled ? 'disabled' : '') . '">' 
                . '<a class="page-link" href="' . htmlspecialchars($mkUrl($prev), ENT_QUOTES, 'UTF-8') . '" tabindex="-1">Anterior</a>'
                . '</li>';

            for ($i = $start; $i <= $end; $i++) {
                echo '<li class="page-item ' . ($i == $pagina ? 'active' : '') . '">' 
                    . '<a class="page-link" href="' . htmlspecialchars($mkUrl($i), ENT_QUOTES, 'UTF-8') . '">' . (int) $i . '</a>'
                    . '</li>';
            }

            $next = min($totalPaginas, $pagina + 1);
            $nextDisabled = ($pagina >= $totalPaginas);
            echo '<li class="page-item ' . ($nextDisabled ? 'disabled' : '') . '">' 
                . '<a class="page-link" href="' . htmlspecialchars($mkUrl($next), ENT_QUOTES, 'UTF-8') . '">Próximo</a>'
                . '</li>';

            echo '</ul></nav>';
        }

        echo '</div>';

        $content = ob_get_clean();
        $title = 'Produtos - Braziliana Admin';
        include __DIR__ . '/../Views/layouts/admin.php';
        exit;
    }

    public function arquivados(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte', 'representante']);

        $perfil = $this->getSessionPerfil();
        $repId = $this->getSessionUserId();

        $isRepresentante = ($perfil === 'representante');
        $sidebarActive = $isRepresentante ? 'rep_produtos' : 'produtos';

        $pagina = (int) $request->getParam('pagina', 1);
        if ($pagina <= 0) $pagina = 1;
        $limite = 20;
        $offset = ($pagina - 1) * $limite;
        $busca = (string) $request->getParam('busca', '');

        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');

            $params = [];
            $where = ' WHERE (LOWER(COALESCE(p.status,\'\')) = \'archived\' OR p.active = 0) ';
            if ($perfil === 'representante') {
                if ($repId <= 0) {
                    header('Location: /login');
                    exit;
                }
                $where .= ' AND p.representante_id = :rep_id ';
                $params[':rep_id'] = $repId;
            }
            if (trim($busca) !== '') {
                $where .= ' AND (p.name LIKE :busca OR p.sku LIKE :busca) ';
                $params[':busca'] = '%' . $busca . '%';
            }

            $sql = 'SELECT p.* FROM produtos p' . $where . ' ORDER BY p.name ASC, p.id ASC LIMIT :limite OFFSET :offset';
            $stmt = $pdo->prepare($sql);
            foreach ($params as $k => $v) {
                if ($k === ':rep_id') {
                    $stmt->bindValue($k, (int) $v, \PDO::PARAM_INT);
                } else {
                    $stmt->bindValue($k, (string) $v);
                }
            }
            $stmt->bindValue(':limite', (int) $limite, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', (int) $offset, \PDO::PARAM_INT);
            $stmt->execute();
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            $sqlTotal = 'SELECT COUNT(*) FROM produtos p' . $where;
            $stmtT = $pdo->prepare($sqlTotal);
            foreach ($params as $k => $v) {
                if ($k === ':rep_id') {
                    $stmtT->bindValue($k, (int) $v, \PDO::PARAM_INT);
                } else {
                    $stmtT->bindValue($k, (string) $v);
                }
            }
            $stmtT->execute();
            $total = (int) ($stmtT->fetchColumn() ?: 0);
            $totalPaginas = (int) ceil($total / $limite);

            $produtos = [];
            foreach ($rows as $r) {
                $id = (int) ($r['id'] ?? 0);
                $img = (string) ($r['foto_principal'] ?? '');
                if ($img === '') {
                    $img = '/uploads/produtos/placeholder.jpg';
                }
                $produtos[] = [
                    'id' => $id,
                    'name' => (string) ($r['name'] ?? ($r['nome'] ?? '')),
                    'sku' => (string) ($r['sku'] ?? ''),
                    'price' => (float) ($r['price'] ?? ($r['preco'] ?? ($r['valor'] ?? 0))),
                    'active' => (int) ($r['active'] ?? ($r['ativo'] ?? 1)),
                    'imagem' => $img,
                ];
            }
        } catch (\Exception $e) {
            echo '<div class="alert alert-danger">Erro: ' . $e->getMessage() . '</div>';
            exit;
        }

        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        ob_start();

        $urlNovo = $isRepresentante ? '/admin/produtos/cadastro-representante' : '/admin/produtos/novo';
        $urlCadastroRapido = $isRepresentante ? '/admin/produtos/cadastro-representante' : '/admin/produtos/cadastro-rapido';

        echo '<div class="pt-3">'
            . '<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center mb-4 border-bottom" style="padding-bottom: 12px;">'
            . '<h1 class="h2">Arquivados (' . (int) $total . ')</h1>'
            . '<div class="d-flex gap-2">'
            . '<a href="/admin/produtos" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>'
            . '<a href="' . htmlspecialchars($urlCadastroRapido, ENT_QUOTES, 'UTF-8') . '" class="btn btn-outline-primary"><i class="fas fa-bolt"></i> Cadastro rápido</a>'
            . '<a href="' . htmlspecialchars($urlNovo, ENT_QUOTES, 'UTF-8') . '" class="btn btn-primary"><i class="fas fa-plus"></i> Novo</a>'
            . '</div>'
            . '</div>';

        echo '<form method="GET" class="row g-3 mb-4">'
            . '<div class="col-md-8">'
            . '<input type="text" class="form-control" name="busca" placeholder="Buscar produto..." value="' . htmlspecialchars($busca, ENT_QUOTES, 'UTF-8') . '">' 
            . '</div>'
            . '<div class="col-md-4">'
            . '<button type="submit" class="btn btn-outline-primary"><i class="fas fa-search"></i> Buscar</button>'
            . '</div>'
            . '</form>';

        echo '<div class="table-responsive">'
            . '<table class="table table-hover align-middle">'
            . '<thead><tr>'
            . '<th style="width:72px">Imagem</th>'
            . '<th>Nome</th>'
            . '<th style="width:160px">SKU</th>'
            . '<th style="width:140px">Preço</th>'
            . '<th style="width:110px">Status</th>'
            . '<th style="width:90px">Ações</th>'
            . '</tr></thead><tbody>';

        foreach ($produtos as $produto) {
            $urlEditar = $isRepresentante ? ('/admin/representante/produtos/editar/' . (int) $produto['id']) : ('/admin/produtos/editar/' . (int) $produto['id']);
            $img = htmlspecialchars((string) $produto['imagem'], ENT_QUOTES, 'UTF-8');
            $nome = htmlspecialchars((string) $produto['name'], ENT_QUOTES, 'UTF-8');
            $sku = htmlspecialchars((string) $produto['sku'], ENT_QUOTES, 'UTF-8');
            $preco = '$' . number_format((float) $produto['price'], 2, '.', ',');
            $badge = ((int) $produto['active'] ? 'bg-success' : 'bg-danger');
            $label = ((int) $produto['active'] ? 'Ativo' : 'Inativo');

            echo '<tr>'
                . '<td><img src="' . $img . '" alt="' . $nome . '" style="width:100px;height:100px;object-fit:cover;border-radius:12px;border:1px solid rgba(0,0,0,0.06);"></td>'
                . '<td><div class="fw-bold" style="font-size: 1.05rem;">' . $nome . '</div><div class="text-muted small">#' . (int) $produto['id'] . '</div></td>'
                . '<td><span class="text-muted small">' . $sku . '</span></td>'
                . '<td><span class="fw-semibold">' . htmlspecialchars($preco, ENT_QUOTES, 'UTF-8') . '</span></td>'
                . '<td><span class="badge ' . $badge . '">' . $label . '</span></td>'
                . '<td>'
                . '<a href="' . htmlspecialchars($urlEditar, ENT_QUOTES, 'UTF-8') . '" class="btn btn-sm btn-outline-warning" title="Editar"><i class="fas fa-edit"></i></a>'
                . '</td>'
                . '</tr>';
        }

        echo '</tbody></table></div>';

        if ($totalPaginas > 1) {
            $base = $isRepresentante ? '/admin/representante/produtos/arquivados' : '/admin/produtos/arquivados';
            $mkUrl = function(int $p) use ($base, $busca): string {
                return $base . "?pagina={$p}" . (trim($busca) !== '' ? "&busca=" . urlencode($busca) : "");
            };

            $start = max(1, $pagina - 1);
            $end = min($totalPaginas, $pagina + 1);
            if (($end - $start + 1) < 3) {
                if ($start === 1) {
                    $end = min($totalPaginas, $start + 2);
                } elseif ($end === $totalPaginas) {
                    $start = max(1, $end - 2);
                }
            }

            echo '<nav class="mt-4"><ul class="pagination justify-content-center">';

            $prev = max(1, $pagina - 1);
            $prevDisabled = ($pagina <= 1);
            echo '<li class="page-item ' . ($prevDisabled ? 'disabled' : '') . '">' 
                . '<a class="page-link" href="' . htmlspecialchars($mkUrl($prev), ENT_QUOTES, 'UTF-8') . '" tabindex="-1">Anterior</a>'
                . '</li>';

            for ($i = $start; $i <= $end; $i++) {
                echo '<li class="page-item ' . ($i == $pagina ? 'active' : '') . '">' 
                    . '<a class="page-link" href="' . htmlspecialchars($mkUrl($i), ENT_QUOTES, 'UTF-8') . '">' . (int) $i . '</a>'
                    . '</li>';
            }

            $next = min($totalPaginas, $pagina + 1);
            $nextDisabled = ($pagina >= $totalPaginas);
            echo '<li class="page-item ' . ($nextDisabled ? 'disabled' : '') . '">' 
                . '<a class="page-link" href="' . htmlspecialchars($mkUrl($next), ENT_QUOTES, 'UTF-8') . '">Próximo</a>'
                . '</li>';

            echo '</ul></nav>';
        }

        echo '</div>';

        $content = ob_get_clean();
        $title = 'Produtos Arquivados - Braziliana Admin';
        include __DIR__ . '/../Views/layouts/admin.php';
        exit;
    }

    private function getProdutosImportJS(): string {
        return <<<'JS'
<script>
document.addEventListener('DOMContentLoaded', function(){
    const btn = document.getElementById('btnImportarProdutosCsv');
    const fileInput = document.getElementById('produtos_import_csv');
    const wrap = document.getElementById('produtosImportProgressWrap');
    const bar = document.getElementById('produtosImportProgressBar');
    const percentEl = document.getElementById('produtosImportProgressPercent');
    const labelEl = document.getElementById('produtosImportProgressLabel');
    const statsEl = document.getElementById('produtosImportProgressStats');

    if (!btn || !fileInput || !wrap || !bar || !percentEl || !labelEl || !statsEl) return;

    let running = false;

    function setProgress(processed, total, okCount, failCount, label){
        const t = (typeof total === 'number' && total > 0) ? total : 0;
        const p = (typeof processed === 'number' && processed > 0) ? processed : 0;
        let pct = (t > 0) ? Math.floor((p / t) * 100) : 0;
        if (pct < 0) pct = 0;
        if (pct > 100) pct = 100;
        bar.style.width = pct + '%';
        bar.textContent = pct + '%';
        percentEl.textContent = pct + '%';
        labelEl.textContent = label || 'Processando...';
        statsEl.textContent = 'Processados: ' + p + ' / ' + t + ' | OK: ' + (okCount||0) + ' | Falhas: ' + (failCount||0);
    }

    async function iniciarImportacao(file){
        const fd = new FormData();
        fd.append('produtos_import_csv', file);
        const resp = await fetch('/admin/produtos/importar/iniciar', { method: 'POST', body: fd });
        const json = await resp.json().catch(() => null);
        if (!resp.ok || !json || !json.ok) {
            throw new Error((json && json.error) ? json.error : 'Falha ao iniciar a importação.');
        }
        return json;
    }

    async function processarLote(token, batchSize){
        const fd = new URLSearchParams();
        fd.set('token', token);
        fd.set('batch', String(batchSize || 200));
        const resp = await fetch('/admin/produtos/importar/processar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
            body: fd.toString()
        });
        const json = await resp.json().catch(() => null);
        if (!resp.ok || !json || !json.ok) {
            throw new Error((json && json.error) ? json.error : 'Falha ao processar lote.');
        }
        return json;
    }

    btn.addEventListener('click', async function(){
        if (running) return;
        const file = fileInput.files && fileInput.files[0];
        if (!file) {
            alert('Selecione um arquivo CSV primeiro.');
            return;
        }

        running = true;
        btn.disabled = true;
        wrap.style.display = '';
        setProgress(0, 0, 0, 0, 'Enviando arquivo...');

        try {
            const init = await iniciarImportacao(file);
            const token = init.token;
            const total = init.total || 0;
            let last = init;

            setProgress(0, total, 0, 0, 'Importação iniciada...');

            while (!last.done) {
                last = await processarLote(token, 200);
                setProgress(last.processed || 0, last.total || total, last.okCount || 0, last.failCount || 0, 'Processando em lotes...');
            }

            setProgress(last.processed || total, last.total || total, last.okCount || 0, last.failCount || 0, 'Finalizado');
        } catch (e) {
            alert(e && e.message ? e.message : 'Erro na importação.');
            labelEl.textContent = 'Erro';
        } finally {
            running = false;
            btn.disabled = false;
        }
    });
});
</script>
JS;
    }
    public function novo(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte', 'representante']);
        // Buscar categorias
        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            $stmtCats = $pdo->query("SELECT * FROM categorias ORDER BY name ASC");
            $categorias = $stmtCats->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            $categorias = [];
        }

        $lojas = $this->fetchLojasSafe();
        $ncmOptions = $this->getNcmOptions();

        ob_start();

        echo <<<HTML
                <div class="pt-3">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center mb-4 border-bottom" style="padding-bottom: 12px;">
                    <h1 class="h2">Novo Produto</h1>
                    <a href="/admin/produtos" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
                </div>

                <div class="alert alert-info">
                    Para cadastrar variações (cor/tamanho etc.), primeiro salve o produto. Depois entre em <strong>Editar</strong> e use a seção <strong>Variações</strong>.
                </div>
                
                <form method="POST" action="/admin/produtos/salvar" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="card mb-4">
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Nome *</label>
                                        <input type="text" class="form-control" name="name" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">SKU *</label>
                                        <input type="text" class="form-control" name="sku" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Loja *</label>
                                        <select class="form-select" name="loja" required>
                                            <option value="">Selecione...</option>
HTML;

        if (!empty($lojas)) {
            foreach ($lojas as $l) {
                echo '<option value="' . htmlspecialchars($l['id']) . '">' . htmlspecialchars($l['nome']) . '</option>';
            }
        } else {
            echo '<option value="sams">Sams</option>';
            echo '<option value="costco">Costco</option>';
            echo '<option value="outro">Outro</option>';
        }

        echo <<<HTML
                                        </select>
                                        <small class="text-muted">Selecione a loja onde este produto está disponível</small>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">NCM</label>
                                        <input type="text" class="form-control" id="ncmSearch" placeholder="Pesquisar NCM...">
                                        <select class="form-select mt-2" name="ncm" id="ncmSelect">
                                            <option value="">Selecione...</option>
HTML;

        foreach ($ncmOptions as $code => $label) {
            echo '<option value="' . htmlspecialchars($code) . '">' . htmlspecialchars($code . ' - ' . $label) . '</option>';
        }

        echo <<<HTML
                                        </select>
                                        <small class="text-muted">Opcional</small>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Descrição Curta</label>
                                        <textarea class="form-control" name="short_description" rows="3"></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Descrição Completa</label>
                                        <textarea class="form-control" name="description" rows="5"></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Categoria</label>
                                        <select class="form-select" name="category_id">
                                            <option value="">Selecione...</option>
HTML;

        foreach ($categorias as $cat) {
            $catName = $cat['name'] ?? $cat['nome'] ?? '';
            echo '<option value="' . $cat['id'] . '">' . htmlspecialchars($catName) . '</option>';
        }

        echo <<<HTML
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Foto de Capa</label>
                                        <input type="file" class="form-control" name="capa" accept="image/*" id="capaInput">
                                        <div id="capaPreview" class="row mt-3"></div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Galeria de Fotos</label>
                                        <input type="file" class="form-control" name="imagens[]" multiple accept="image/*" id="imagensInput">
                                        <div id="imagePreview" class="row mt-3"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card mb-4">
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Preço (USD) *</label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="text" class="form-control" name="price" required>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Preço de Custo (USD)</label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="text" class="form-control" name="cost_price">
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Preço Promocional (USD)</label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="text" class="form-control" name="sale_price">
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Peso (kg)</label>
                                        <input type="text" class="form-control" name="weight">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Estoque</label>
                                        <input type="number" class="form-control" name="stock">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Estoque Mínimo</label>
                                        <input type="number" class="form-control" name="min_stock">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Status</label>
                                        <select class="form-select" name="status">
                                            <option value="draft">Rascunho</option>
                                            <option value="published" selected>Publicado</option>
                                            <option value="archived">Arquivado</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Ativo</label>
                                        <select class="form-select" name="active">
                                            <option value="1" selected>Ativo</option>
                                            <option value="0">Inativo</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Destaque</label>
                                        <select class="form-select" name="featured">
                                            <option value="0" selected>Não</option>
                                            <option value="1">Sim</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Clube Ativo</label>
                                        <select class="form-select" name="clube_ativo">
                                            <option value="0" selected>Não</option>
                                            <option value="1">Sim</option>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-save"></i> Salvar</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
    <script>
        // Preview de capa ao selecionar
        var capaInput = document.getElementById('capaInput');
        if (capaInput) {
            capaInput.addEventListener('change', function(e) {
                const preview = document.getElementById('capaPreview');
                if (!preview) return;
                preview.innerHTML = '';

                const file = (e.target.files || [])[0];
                if (file && file.type.startsWith('image/')) {
                    const reader = new FileReader();
                    reader.onload = function(ev) {
                        const div = document.createElement('div');
                        div.className = 'col-md-4 mb-2';
                        div.innerHTML = '<img src="' + ev.target.result + '" class="img-thumbnail" style="width: 100%; height: 200px; object-fit: cover;">';
                        preview.appendChild(div);
                    };
                    reader.readAsDataURL(file);
                }
            });
        }

        // Preview de imagens ao selecionar
        var imagensInput = document.getElementById('imagensInput');
        if (imagensInput) {
            imagensInput.addEventListener('change', function(e) {
                const preview = document.getElementById('imagePreview');
                if (!preview) return;
                preview.innerHTML = '';

                Array.from(e.target.files || []).forEach((file) => {
                    if (!file.type || !file.type.startsWith('image/')) return;
                    const reader = new FileReader();
                    reader.onload = function(ev) {
                        const div = document.createElement('div');
                        div.className = 'col-md-3 mb-2';
                        div.innerHTML = '<img src="' + ev.target.result + '" class="img-thumbnail" style="width: 100%; height: 150px; object-fit: cover;">';
                        preview.appendChild(div);
                    };
                    reader.readAsDataURL(file);
                });
            });
        }

        // Busca no select de NCM
        (function() {
            const input = document.getElementById('ncmSearch');
            const select = document.getElementById('ncmSelect');
            if (!input || !select) return;

            input.addEventListener('input', function() {
                const q = (input.value || '').toLowerCase().trim();
                Array.from(select.options).forEach((opt) => {
                    if (opt.value === '') return;
                    const text = (opt.text || '').toLowerCase();
                    opt.hidden = q !== '' && !text.includes(q);
                });
            });
        })();
    </script>
HTML;

        echo '</div>';

        $content = ob_get_clean();
        $title = 'Novo Produto - Braziliana Admin';
        include __DIR__ . '/../Views/layouts/admin.php';
        exit;
    }
    
    public function salvar(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte', 'representante']);
        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            $pdo->beginTransaction();
            
            $cols = $this->getTableColumns($pdo, 'produtos');

            $perfil = $this->getSessionPerfil();
            $repId = $this->getSessionUserId();
            $repEmail = $this->getSessionUserEmail();

            $price = str_replace(['$', '.', ','], ['', '', '.'], (string) $request->getParam('price'));
            $costPrice = str_replace(['$', '.', ','], ['', '', '.'], (string) $request->getParam('cost_price'));
            $salePrice = str_replace(['$', '.', ','], ['', '', '.'], (string) $request->getParam('sale_price'));

            if ($perfil === 'representante' && trim((string) $costPrice) === '') {
                throw new \Exception('Preço de custo (USD) é obrigatório para representante.');
            }
            
            // Validar categoria se fornecida
            $categoryParam = $request->getParam('category_id');
            $categoryId = null;
            if (!empty($categoryParam)) {
                $stmtCat = $pdo->prepare("SELECT id FROM categorias WHERE id = ?");
                $stmtCat->execute([$categoryParam]);
                if (!$stmtCat->fetch()) {
                    throw new \Exception("Categoria selecionada não existe");
                }
                $categoryId = $categoryParam;
            }

            $data = [];
            if (in_array('name', $cols, true)) {
                $data['name'] = $request->getParam('name');
            } elseif (in_array('nome', $cols, true)) {
                $data['nome'] = $request->getParam('name');
            }

            if (in_array('sku', $cols, true)) $data['sku'] = $request->getParam('sku');
            $lojaParam = $request->getParam('loja');
            $lojaId = is_numeric($lojaParam) ? (int) $lojaParam : 0;
            if (in_array('loja_id', $cols, true) && $lojaId > 0) {
                $data['loja_id'] = $lojaId;
            }
            if (in_array('loja', $cols, true)) {
                // manter compatibilidade: salvar slug também quando possível
                $lojaSlug = null;
                if ($lojaId > 0) {
                    try {
                        $stmtT = $pdo->query("SHOW TABLES LIKE 'lojas'");
                        if ($stmtT && $stmtT->fetchColumn()) {
                            $stmtL = $pdo->prepare('SELECT slug FROM lojas WHERE id = :id LIMIT 1');
                            $stmtL->execute([':id' => $lojaId]);
                            $lojaSlug = $stmtL->fetchColumn();
                        }
                    } catch (\Exception $e) {
                    }
                }

                if ($lojaSlug !== null && $lojaSlug !== false && (string) $lojaSlug !== '') {
                    $data['loja'] = (string) $lojaSlug;
                } else {
                    $data['loja'] = $lojaParam;
                }
            }
            if (in_array('ncm', $cols, true)) $data['ncm'] = $request->getParam('ncm');
            if (in_array('short_description', $cols, true)) $data['short_description'] = $request->getParam('short_description');
            if (in_array('description', $cols, true)) $data['description'] = $request->getParam('description');

            if (in_array('category_id', $cols, true)) {
                $data['category_id'] = $categoryId;
            } elseif (in_array('categoria_id', $cols, true)) {
                $data['categoria_id'] = $categoryId;
            }

            if (in_array('price', $cols, true)) $data['price'] = $price;
            if (in_array('cost_price', $cols, true) && $costPrice !== '') $data['cost_price'] = $costPrice;
            if (in_array('sale_price', $cols, true) && $salePrice !== '') $data['sale_price'] = $salePrice;

            if ($perfil === 'representante') {
                if (in_array('moeda', $cols, true)) $data['moeda'] = 'USD';
                if (in_array('currency', $cols, true)) $data['currency'] = 'USD';
                if (in_array('representante_id', $cols, true)) $data['representante_id'] = ($repId > 0 ? $repId : null);
                if (in_array('representante_email', $cols, true)) $data['representante_email'] = ($repEmail !== '' ? $repEmail : null);
            }

            if (in_array('weight', $cols, true)) $data['weight'] = $request->getParam('weight') ?: 0;
            if (in_array('stock', $cols, true)) $data['stock'] = $request->getParam('stock') ?: 0;
            if (in_array('min_stock', $cols, true)) $data['min_stock'] = $request->getParam('min_stock') ?: 0;

            if (in_array('status', $cols, true)) $data['status'] = $request->getParam('status') ?: 'published';
            if (in_array('active', $cols, true)) $data['active'] = $request->getParam('active') ?: 1;
            if (in_array('status', $cols, true) && in_array('active', $cols, true)) {
                if (strtolower(trim((string) ($data['status'] ?? ''))) === 'archived') {
                    $data['active'] = 0;
                }
            }
            if (in_array('featured', $cols, true)) $data['featured'] = $request->getParam('featured') ?: 0;
            if (in_array('clube_ativo', $cols, true)) $data['clube_ativo'] = $request->getParam('clube_ativo') ?: 0;

            if (in_array('created_at', $cols, true) && empty($data['created_at'])) {
                $data['created_at'] = date('Y-m-d H:i:s');
            }
            if (in_array('updated_at', $cols, true) && empty($data['updated_at'])) {
                $data['updated_at'] = date('Y-m-d H:i:s');
            }

            $columnsSql = implode(', ', array_keys($data));
            $placeholders = ':' . implode(', :', array_keys($data));
            $stmt = $pdo->prepare("INSERT INTO produtos ({$columnsSql}) VALUES ({$placeholders})");
            foreach ($data as $k => $v) {
                $stmt->bindValue(':' . $k, $v);
            }
            $stmt->execute();
            
            $produto_id = $pdo->lastInsertId();

            // Processar foto de capa
            if (isset($_FILES['capa']) && !empty($_FILES['capa']['name']) && ($_FILES['capa']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $uploadDir = $this->getProdutoUploadsDir();
                $webDir = '/uploads/produtos/';
                $this->ensureDir($uploadDir);

                $name = $_FILES['capa']['name'];
                $fileName = preg_replace('/[^A-Za-z0-9\-_\.]/', '', $name);
                $fileName = time() . '_' . $fileName;
                $filePath = $uploadDir . $fileName;
                $webPath = $webDir . $fileName;

                if (move_uploaded_file($_FILES['capa']['tmp_name'], $filePath)) {
                    if (in_array('foto_principal', $cols, true)) {
                        $stmtCover = $pdo->prepare('UPDATE produtos SET foto_principal = ? WHERE id = ?');
                        $stmtCover->execute([$webPath, $produto_id]);
                    }
                }
            }
            
            // Processar galeria de imagens
            if (isset($_FILES['imagens']) && !empty($_FILES['imagens']['name'][0])) {
                $uploadDir = $this->getProdutoUploadsDir();
                $webDir = '/uploads/produtos/';
                $this->ensureDir($uploadDir);
                
                foreach ($_FILES['imagens']['name'] as $key => $name) {
                    if ($_FILES['imagens']['error'][$key] === UPLOAD_ERR_OK) {
                        // Limpar nome do arquivo
                        $fileName = preg_replace('/[^A-Za-z0-9\-_\.]/', '', $name);
                        $fileName = time() . '_' . $fileName;
                        
                        $filePath = $uploadDir . $fileName;
                        $webPath = $webDir . $fileName;
                        
                        if (move_uploaded_file($_FILES['imagens']['tmp_name'][$key], $filePath)) {
                            $stmt = $pdo->prepare("
                                INSERT INTO produto_fotos (produto_id, nome_arquivo, arquivo_original, principal, ordem, created_at, updated_at)
                                VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                            ");
                            $stmt->execute([
                                $produto_id,
                                $webPath,
                                $name,
                                0,
                                $key
                            ]);
                            
                            error_log('✅ [ADMIN-PRODUTO] Foto salva: ' . $webPath);
                        } else {
                            error_log('❌ [ADMIN-PRODUTO] Erro ao salvar foto: ' . $name);
                        }
                    }
                }
            } else {
                error_log('⚠️ [ADMIN-PRODUTO] Nenhuma imagem enviada');
            }
            
            $pdo->commit();
            if ($perfil === 'representante') {
                header('Location: /admin/representante/produtos?success=1');
            } else {
                header('Location: /admin/produtos?success=1');
            }
            exit;
            
        } catch (\Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo '<div class="alert alert-danger">Erro: ' . $e->getMessage() . '</div>';
            exit;
        }
    }
    
    public function editar(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte', 'representante']);
        $id = (int) $request->getParam('id');

        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');

            $this->requireProdutoOwnerIfRepresentante($pdo, (int) $id);

            $stmtProduto = $pdo->prepare('SELECT * FROM produtos WHERE id = ?');
            $stmtProduto->execute([$id]);
            $produto = $stmtProduto->fetch(\PDO::FETCH_ASSOC);
            if (!$produto) {
                throw new \Exception('Produto não encontrado');
            }

            $stmtCat = $pdo->query('SELECT * FROM categorias ORDER BY name ASC');
            $categorias = $stmtCat->fetchAll(\PDO::FETCH_ASSOC);

            $stmtFotos = $pdo->prepare('SELECT * FROM produto_fotos WHERE produto_id = ? ORDER BY principal DESC, id ASC');
            $stmtFotos->execute([$id]);
            $fotos = $stmtFotos->fetchAll(\PDO::FETCH_ASSOC);

            $lojas = $this->fetchLojasSafe();
            $ncmOptions = $this->getNcmOptions();

            $variacoesSchemaOk = $this->tableExists($pdo, 'variacao_tipos')
                && $this->tableExists($pdo, 'variacao_opcoes')
                && $this->tableExists($pdo, 'produto_atributos')
                && $this->tableExists($pdo, 'produto_variacoes')
                && $this->tableExists($pdo, 'produto_variacao_itens');

            $variacaoTipos = $variacoesSchemaOk ? $this->getVariacaoTipos($pdo) : [];
            $variacaoOpcoesPorTipo = $variacoesSchemaOk ? $this->getVariacaoOpcoesPorTipo($pdo) : [];
            $produtoTipoIds = $variacoesSchemaOk ? $this->getProdutoAtributos($pdo, (int) $id) : [];
            $produtoOpcoesPorTipo = $variacoesSchemaOk ? $this->getProdutoOpcoesUsadasPorTipo($pdo, (int) $id) : [];
            $produtoVariacoes = $variacoesSchemaOk ? $this->getProdutoVariacoesComDescricao($pdo, (int) $id) : [];

            $fotosPorVariacao = [];
            if ($variacoesSchemaOk && $this->tableExists($pdo, 'produto_variacao_fotos') && !empty($produtoVariacoes)) {
                $varIds = [];
                foreach ($produtoVariacoes as $vv) {
                    $vId = (int) ($vv['id'] ?? 0);
                    if ($vId > 0) $varIds[] = $vId;
                }
                $varIds = array_values(array_unique($varIds));
                if (!empty($varIds)) {
                    $in = implode(',', array_fill(0, count($varIds), '?'));
                    $sql = 'SELECT * FROM produto_variacao_fotos WHERE produto_variacao_id IN (' . $in . ') ORDER BY produto_variacao_id ASC, ordem ASC, id ASC';
                    $st = $pdo->prepare($sql);
                    $st->execute($varIds);
                    $rows = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                    foreach ($rows as $r) {
                        $pvId = (int) ($r['produto_variacao_id'] ?? 0);
                        if ($pvId <= 0) continue;
                        if (!isset($fotosPorVariacao[$pvId])) $fotosPorVariacao[$pvId] = [];
                        $webPath = $this->normalizeUploadsWebPath((string) ($r['nome_arquivo'] ?? ''));
                        $filePath = !empty($webPath) ? $this->resolveUploadsPublicPath($webPath) : null;
                        $url = (!empty($webPath) && !empty($filePath)) ? Url::absolute($webPath) : Url::absolute('/uploads/produtos/placeholder.jpg');
                        $fotosPorVariacao[$pvId][] = [
                            'id' => (int) ($r['id'] ?? 0),
                            'nome_arquivo' => $webPath,
                            'url' => $url,
                            'ordem' => (int) ($r['ordem'] ?? 0),
                        ];
                    }
                }
            }
        } catch (\Exception $e) {
            echo '<div class="alert alert-danger">Erro: ' . $e->getMessage() . '</div>';
            exit;
        }

        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';

        if ($request->getParam('debug_loja')) {
            echo '<pre style="padding:12px;background:#fff;border:1px solid #ddd;max-width:100%;overflow:auto">';
            var_dump([
                'produto_id' => $id,
                'produto_loja' => $produto['loja'] ?? null,
                'lojas_0' => $lojas[0] ?? null,
            ]);
            echo '</pre>';
        }

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Produto - Braziliana Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';

        renderAdminSidebarStyles();

        echo '</head>
<body>
    <div class="container-fluid">
        <div class="row">';

        renderAdminSidebar('produtos');

        $fotoCapa = $produto['foto_principal'] ?? null;
        $fotoCapaPath = null;
        $fotoCapaUrl = null;
        if (!empty($fotoCapa)) {
            $fotoCapaWeb = $this->normalizeUploadsWebPath((string) $fotoCapa);
            if (!empty($fotoCapaWeb)) {
                $fotoCapaPath = $this->resolveUploadsPublicPath((string) $fotoCapaWeb);
                if (!empty($fotoCapaPath)) {
                    $fotoCapaUrl = Url::absolute((string) $fotoCapaWeb);
                }
            }
        }

        $debugSuffix = $request->getParam('debug_loja') ? '?debug_loja=1' : '';

        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Editar Produto</h1>
                    <a href="/admin/produtos" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Voltar</a>
                </div>

                <form method="POST" action="/admin/produtos/atualizar/' . $id . $debugSuffix . '" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-8">
                            <div class="card mb-4">
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Nome *</label>
                                        <input type="text" class="form-control" name="name" value="' . htmlspecialchars($produto['name']) . '" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">SKU *</label>
                                        <input type="text" class="form-control" name="sku" value="' . htmlspecialchars($produto['sku']) . '" required>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Loja *</label>
                                        <select class="form-select" name="loja" required>
                                            <option value="">Selecione...</option>';

        if (!empty($lojas)) {
            $produtoLojaId = (int) ($produto['loja_id'] ?? 0);
            $produtoLoja = trim((string) ($produto['loja'] ?? ''));
            $produtoLojaNorm = strtolower($produtoLoja);
            foreach ($lojas as $l) {
                $lojaSlug = (string) ($l['slug'] ?? '');
                $lojaId = (string) ($l['id'] ?? '');
                $lojaNome = (string) ($l['nome'] ?? '');

                $lojaSlugNorm = strtolower(trim($lojaSlug));
                $lojaIdNorm = strtolower(trim($lojaId));
                $lojaNomeNorm = strtolower(trim($lojaNome));

                $selected = ($produtoLojaId > 0 && (string) $produtoLojaId === (string) $l['id']) ? 'selected' : '';
                if ($selected === '') {
                    $selected = ($produtoLojaNorm !== '' && (
                        $produtoLojaNorm === $lojaSlugNorm ||
                        $produtoLojaNorm === $lojaIdNorm ||
                        $produtoLojaNorm === $lojaNomeNorm
                    )) ? 'selected' : '';
                }
                echo '<option value="' . htmlspecialchars($l['id']) . '" ' . $selected . '>' . htmlspecialchars($l['nome']) . '</option>';
            }
        } else {
            echo '<option value="sams" ' . (($produto['loja'] ?? '') === 'sams' ? 'selected' : '') . '>Sams</option>';
            echo '<option value="costco" ' . (($produto['loja'] ?? '') === 'costco' ? 'selected' : '') . '>Costco</option>';
            echo '<option value="outro" ' . (($produto['loja'] ?? '') === 'outro' ? 'selected' : '') . '>Outro</option>';
        }

        echo '</select>
                                        <small class="text-muted">Selecione a loja onde este produto está disponível</small>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">NCM</label>
                                        <input type="text" class="form-control" id="ncmSearch" placeholder="Pesquisar NCM...">
                                        <select class="form-select mt-2" name="ncm" id="ncmSelect">
                                            <option value="">Selecione...</option>';

        $currentNcm = preg_replace('/\D+/', '', (string) ($produto['ncm'] ?? ''));
        foreach ($ncmOptions as $code => $label) {
            $selected = ($currentNcm !== '' && $currentNcm === (string) $code) ? 'selected' : '';
            echo '<option value="' . htmlspecialchars($code) . '" ' . $selected . '>' . htmlspecialchars($code . ' - ' . $label) . '</option>';
        }

        echo '                        </select>
                                        <small class="text-muted">Opcional</small>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Descrição Curta</label>
                                        <textarea class="form-control" name="short_description" rows="3">' . htmlspecialchars($produto['short_description'] ?? '') . '</textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Descrição Completa</label>
                                        <textarea class="form-control" name="description" rows="5">' . htmlspecialchars($produto['description'] ?? '') . '</textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Categoria</label>
                                        <select class="form-select" name="category_id">
                                            <option value="">Selecione...</option>';

        foreach ($categorias as $cat) {
            $selected = ((string) ($cat['id'] ?? '') === (string) ($produto['category_id'] ?? '')) ? 'selected' : '';
            echo '<option value="' . (int) $cat['id'] . '" ' . $selected . '>' . htmlspecialchars($cat['name']) . '</option>';
        }

        echo '                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Foto de Capa</label>
                                        <div class="row mb-3">';

        if (!empty($fotoCapaUrl)) {
            echo '<div class="col-6 col-md-3 mb-2">
                    <a href="' . $fotoCapaUrl . '" target="_blank">
                        <img id="capaImg" data-placeholder="' . Url::absolute('/uploads/produtos/placeholder.jpg') . '" src="' . $fotoCapaUrl . '" alt="Capa" class="img-thumbnail" style="width: 100%; height: 140px; object-fit: cover;">
                    </a>
                </div>';
        } else {
            echo '<div class="col-6 col-md-3 mb-2">
                    <img id="capaImg" data-placeholder="' . Url::absolute('/uploads/produtos/placeholder.jpg') . '" src="' . Url::absolute('/uploads/produtos/placeholder.jpg') . '" alt="Sem capa" class="img-thumbnail" style="width: 100%; height: 140px; object-fit: cover;">
                </div>';
        }

        echo '</div>
                                        <div class="d-flex gap-2 align-items-center">
                                            <input id="capaFile" type="file" class="form-control" name="capa" accept="image/*">
                                            <button type="button" id="btnUploadCapa" class="btn btn-outline-primary" data-url="/admin/produtos/upload-capa/' . (int) $id . '">Enviar capa</button>
                                            <button type="button" id="btnRemoverCapa" class="btn btn-outline-danger" data-url="/admin/produtos/remover-capa/' . (int) $id . '" ' . (!empty($fotoCapaUrl) ? '' : 'disabled') . '>Remover capa</button>
                                        </div>
                                        <small class="text-muted">A foto de capa é usada como imagem principal do produto</small>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Galeria de Fotos</label>
                                        <div id="galeriaRow" class="row mb-3">';

        foreach ($fotos as $foto) {
            $webPath = $this->normalizeUploadsWebPath((string) ($foto['nome_arquivo'] ?? ''));
            $filePath = !empty($webPath) ? $this->resolveUploadsPublicPath($webPath) : null;
            $imageUrl = (!empty($webPath) && !empty($filePath)) ? Url::absolute($webPath) : Url::absolute('/uploads/produtos/placeholder.jpg');
            $fotoId = (int) ($foto['id'] ?? 0);
            $ordem = (int) ($foto['ordem'] ?? 0);
            echo '<div class="col-6 col-md-2 mb-2">
                    <a href="' . $imageUrl . '" target="_blank">
                        <img src="' . $imageUrl . '" alt="Foto" class="img-thumbnail" style="width: 100%; height: 100px; object-fit: cover;">
                    </a>
                    <div class="mt-2">
                        <input type="number" class="form-control form-control-sm" name="ordens[' . $fotoId . ']" value="' . $ordem . '" min="0">
                    </div>
                    <div class="mt-2">
                        <button type="submit" class="btn btn-sm btn-outline-danger w-100" formaction="/admin/produtos/remover-foto/' . $fotoId . '" formmethod="POST" formnovalidate onclick="return confirm(\'Remover esta foto?\')">Remover</button>
                    </div>
                </div>';
        }

        echo '                        </div>
                                        <div class="d-flex gap-2 align-items-center">
                                            <input id="galeriaFiles" type="file" class="form-control" name="imagens[]" multiple accept="image/*">
                                            <button type="button" id="btnUploadGaleria" class="btn btn-outline-primary" data-url="/admin/produtos/upload-galeria/' . (int) $id . '">Enviar fotos</button>
                                            <button type="submit" class="btn btn-outline-primary" formaction="/admin/produtos/galeria/ordem/' . (int) $id . '" formmethod="POST" formnovalidate>Salvar ordem</button>
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label class="form-label">Variações</label>';

        if (empty($variacoesSchemaOk)) {
            echo '<div class="alert alert-warning mb-0">Para habilitar variações, rode a migration <strong>061_create_produto_variacoes_schema.sql</strong> no banco.</div>';
        } else {
            echo '<div class="alert alert-info">Use atributos e opções para gerar variações simples ou compostas. Você pode gerar todas, apagar e também criar variações individuais.</div>';

            echo '<div class="card mb-3">
                    <div class="card-body">
                        <div>
                            <div class="row g-2">';

            foreach ($variacaoTipos as $t) {
                $tid = (int) ($t['id'] ?? 0);
                $tnome = (string) ($t['nome'] ?? '');
                $checked = in_array($tid, $produtoTipoIds, true) ? 'checked' : '';

                echo '<div class="col-12">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="tipo_ids[]" value="' . $tid . '" id="tipo_' . $tid . '" ' . $checked . '>
                            <label class="form-check-label" for="tipo_' . $tid . '">' . htmlspecialchars($tnome, ENT_QUOTES, 'UTF-8') . '</label>
                        </div>
                      </div>';

                $opcoes = $variacaoOpcoesPorTipo[$tid] ?? [];
                if (!empty($opcoes)) {
                    echo '<div class="col-12 ms-4">
                            <div class="row g-2">';
                    foreach ($opcoes as $o) {
                        $oid = (int) ($o['id'] ?? 0);
                        $ovalor = (string) ($o['valor'] ?? '');
                        $oChecked = (!empty($produtoOpcoesPorTipo[$tid]) && in_array($oid, $produtoOpcoesPorTipo[$tid], true)) ? 'checked' : '';
                        echo '<div class="col-6 col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="opcoes[' . $tid . '][]" value="' . $oid . '" id="opt_' . $tid . '_' . $oid . '" ' . $oChecked . '>
                                    <label class="form-check-label" for="opt_' . $tid . '_' . $oid . '">' . htmlspecialchars($ovalor, ENT_QUOTES, 'UTF-8') . '</label>
                                </div>
                              </div>';
                    }
                    echo '      </div>
                          </div>';
                }
            }

            echo '          <div class="col-12">
                                <div class="d-flex flex-wrap gap-2">
                                    <button type="button" class="btn btn-outline-secondary" id="btnDesmarcarOpcoes"><i class="fas fa-eraser"></i> Desmarcar todas as opções</button>
                                    <button type="submit" class="btn btn-outline-primary" formaction="/admin/produtos/' . (int) $id . '/variacoes/atributos" formmethod="POST" formnovalidate>Salvar atributos/opções</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>';

            echo '<div class="d-flex flex-wrap gap-2 mb-3">
                    <button type="submit" class="btn btn-primary" name="replace" value="0" formaction="/admin/produtos/' . (int) $id . '/variacoes/gerar" formmethod="POST" formnovalidate onclick="return confirm(\'Gerar variações com base nas opções selecionadas?\')"><i class="fas fa-cogs"></i> Gerar todas</button>
                    <button type="submit" class="btn btn-outline-primary" name="replace" value="1" formaction="/admin/produtos/' . (int) $id . '/variacoes/gerar" formmethod="POST" formnovalidate onclick="return confirm(\'Isso vai apagar e recriar as variações. Continuar?\')"><i class="fas fa-redo"></i> Apagar e gerar</button>
                    <button type="submit" class="btn btn-outline-danger" formaction="/admin/produtos/' . (int) $id . '/variacoes/apagar" formmethod="POST" formnovalidate onclick="return confirm(\'Apagar todas as variações deste produto?\')"><i class="fas fa-trash"></i> Apagar todas</button>
                  </div>';

            echo '<div class="card">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <strong>Variações cadastradas</strong>
                        <div class="d-flex gap-2">
                            <input type="number" name="stock" class="form-control form-control-sm" style="width:120px" placeholder="Estoque" value="0" min="0">
                            <input type="text" name="price_override" class="form-control form-control-sm" style="width:160px" placeholder="Preço variação">
                            <button class="btn btn-sm btn-outline-primary" type="submit" formaction="/admin/produtos/' . (int) $id . '/variacoes/criar" formmethod="POST" formnovalidate><i class="fas fa-plus"></i> Criar individual</button>
                        </div>
                    </div>
                    <div class="card-body">';

            if (empty($produtoVariacoes)) {
                echo '<div class="text-muted">Nenhuma variação criada ainda.</div>';
            } else {
                echo '<div class="table-responsive">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th>Variação</th>
                                    <th style="width:180px">Preço (override)</th>
                                    <th style="width:140px">Estoque</th>
                                    <th style="width:140px">Ativa</th>
                                </tr>
                            </thead>
                            <tbody>';
                foreach ($produtoVariacoes as $v) {
                    $vId = (int) ($v['id'] ?? 0);
                    $desc = (string) ($v['descricao'] ?? '');
                    $priceOv = $v['price_override'] ?? null;
                    $stockV = (int) ($v['stock'] ?? 0);
                    $ativoV = (int) ($v['ativo'] ?? 0);
                    $priceUi = ($priceOv === null || $priceOv === '') ? '' : (string) $priceOv;
                    echo '<tr>
                            <td>
                                <div class="fw-semibold">#' . $vId . '</div>
                                <div class="text-muted small">' . htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') . '</div>
                            </td>
                            <td><input type="text" class="form-control form-control-sm" name="variacao_price_override[' . $vId . ']" value="' . htmlspecialchars($priceUi, ENT_QUOTES, 'UTF-8') . '" placeholder="Ex: 19,90"></td>
                            <td><input type="number" class="form-control form-control-sm" name="variacao_stock[' . $vId . ']" value="' . (int) $stockV . '" min="0"></td>
                            <td class="text-center"><input type="hidden" name="variacao_ativo[' . $vId . ']" value="0"><input type="checkbox" class="form-check-input" name="variacao_ativo[' . $vId . ']" value="1" ' . ($ativoV ? 'checked' : '') . '></td>
                          </tr>';
                }
                echo '      </tbody>
                        </table>
                    </div>';

                echo '<div class="d-flex justify-content-end mt-2">
                        <button type="submit" class="btn btn-outline-primary" formaction="/admin/produtos/' . (int) $id . '/variacoes/salvar" formmethod="POST" formnovalidate><i class="fas fa-save"></i> Salvar variações</button>
                      </div>';
            }

            echo '      </div>
                </div>';

            echo '<div class="card mt-3">
                    <div class="card-header bg-white">
                        <strong>Galeria por variação (SKU)</strong>
                        <div class="text-muted small">Cada variação pode ter sua própria galeria. Essas fotos serão exibidas na página do produto quando o cliente selecionar a variação.</div>
                    </div>
                    <div class="card-body">';

            if (empty($produtoVariacoes)) {
                echo '<div class="text-muted">Nenhuma variação criada ainda.</div>';
            } else {
                echo '<div class="accordion" id="accVarFotos">';
                foreach ($produtoVariacoes as $idx => $v) {
                    $vId = (int) ($v['id'] ?? 0);
                    if ($vId <= 0) continue;
                    $desc = (string) ($v['descricao'] ?? '');
                    $headingId = 'varFotosHeading_' . $vId;
                    $collapseId = 'varFotosCollapse_' . $vId;

                    echo '<div class="accordion-item">
                            <h2 class="accordion-header" id="' . $headingId . '">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#' . $collapseId . '" aria-expanded="false" aria-controls="' . $collapseId . '">
                                    <span class="fw-semibold">#' . $vId . '</span>
                                    <span class="text-muted ms-2 small">' . htmlspecialchars($desc, ENT_QUOTES, 'UTF-8') . '</span>
                                </button>
                            </h2>
                            <div id="' . $collapseId . '" class="accordion-collapse collapse" aria-labelledby="' . $headingId . '" data-bs-parent="#accVarFotos">
                                <div class="accordion-body">';

                    echo '<div id="varGaleriaRow_' . $vId . '" class="row mb-3">';
                    $fotosV = $fotosPorVariacao[$vId] ?? [];
                    foreach ($fotosV as $foto) {
                        $fotoId = (int) ($foto['id'] ?? 0);
                        $url = (string) ($foto['url'] ?? '');
                        $ordem = (int) ($foto['ordem'] ?? 0);
                        echo '<div class="col-6 col-md-2 mb-2">
                                <a href="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" target="_blank">
                                    <img src="' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '" alt="Foto" class="img-thumbnail" style="width: 100%; height: 100px; object-fit: cover;">
                                </a>
                                <div class="mt-2">
                                    <input type="number" class="form-control form-control-sm" name="ordens_variacao[' . $fotoId . ']" value="' . $ordem . '" min="0">
                                </div>
                                <div class="mt-2">
                                    <button type="submit" class="btn btn-sm btn-outline-danger w-100" formaction="/admin/produtos/variacoes/fotos/remover/' . $fotoId . '" formmethod="POST" formnovalidate onclick="return confirm(\'Remover esta foto da variação?\')">Remover</button>
                                </div>
                              </div>';
                    }
                    echo '</div>';

                    echo '<div class="d-flex gap-2 align-items-center flex-wrap">
                            <input id="varGaleriaFiles_' . $vId . '" type="file" class="form-control" style="max-width: 520px;" multiple accept="image/*">
                            <button type="button" class="btn btn-outline-primary btnUploadVarGaleria" data-var-id="' . $vId . '" data-url="/admin/produtos/variacoes/' . $vId . '/fotos/upload">Enviar fotos</button>
                            <button type="submit" class="btn btn-outline-primary" formaction="/admin/produtos/variacoes/' . $vId . '/fotos/ordem" formmethod="POST" formnovalidate>Salvar ordem</button>
                          </div>';

                    echo '            </div>
                            </div>
                        </div>';
                }
                echo '</div>';
            }

            echo '    </div>
                </div>';
        }

        echo '                        </div>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card mb-4">
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label class="form-label">Preço (USD) *</label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="text" class="form-control" name="price" value="' . htmlspecialchars($produto['price'] ?? '') . '" required>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Preço de Custo (USD)</label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="text" class="form-control" name="cost_price" value="' . htmlspecialchars($produto['cost_price'] ?? '') . '">
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Preço Promocional (USD)</label>
                                        <div class="input-group">
                                            <span class="input-group-text">$</span>
                                            <input type="text" class="form-control" name="sale_price" value="' . htmlspecialchars($produto['sale_price'] ?? '') . '">
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Estoque</label>
                                        <input type="number" class="form-control" name="stock" value="' . htmlspecialchars($produto['stock'] ?? 0) . '">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Estoque Mínimo</label>
                                        <input type="number" class="form-control" name="min_stock" value="' . htmlspecialchars($produto['min_stock'] ?? 0) . '">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Peso (kg)</label>
                                        <input type="text" class="form-control" name="weight" value="' . htmlspecialchars($produto['weight'] ?? 0) . '">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Status</label>
                                        <select class="form-select" name="status">
                                            <option value="draft" ' . (($produto['status'] ?? '') === 'draft' ? 'selected' : '') . '>Rascunho</option>
                                            <option value="published" ' . (($produto['status'] ?? '') === 'published' ? 'selected' : '') . '>Publicado</option>
                                            <option value="archived" ' . (($produto['status'] ?? '') === 'archived' ? 'selected' : '') . '>Arquivado</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Ativo</label>
                                        <select class="form-select" name="active">
                                            <option value="1" ' . (!empty($produto['active']) ? 'selected' : '') . '>Ativo</option>
                                            <option value="0" ' . (empty($produto['active']) ? 'selected' : '') . '>Inativo</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Destaque</label>
                                        <select class="form-select" name="featured">
                                            <option value="1" ' . (!empty($produto['featured']) ? 'selected' : '') . '>Sim</option>
                                            <option value="0" ' . (empty($produto['featured']) ? 'selected' : '') . '>Não</option>
                                        </select>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Clube Ativo</label>
                                        <select class="form-select" name="clube_ativo">
                                            <option value="1" ' . (!empty($produto['clube_ativo']) ? 'selected' : '') . '>Sim</option>
                                            <option value="0" ' . (empty($produto['clube_ativo']) ? 'selected' : '') . '>Não</option>
                                        </select>
                                    </div>
                                    <button type="submit" class="btn btn-primary w-100"><i class="fas fa-save"></i> Salvar Alterações</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </main>
        </div>
    </div>
    ';

        renderAdminScripts();

        echo <<<'HTMLSCRIPT'
<script>
        // Busca no select de NCM
        (function() {
            const input = document.getElementById("ncmSearch");
            const select = document.getElementById("ncmSelect");
            if (!input || !select) return;

            input.addEventListener("input", function() {
                const q = (input.value || "").toLowerCase().trim();
                Array.from(select.options).forEach((opt) => {
                    if (opt.value === "") return;
                    const text = (opt.text || "").toLowerCase();
                    opt.hidden = q !== "" && !text.includes(q);
                });
            });
        })();

        (function() {
            const btnCapa = document.getElementById('btnUploadCapa');
            const capaFile = document.getElementById('capaFile');
            const capaImg = document.getElementById('capaImg');

            const btnGaleria = document.getElementById('btnUploadGaleria');
            const galeriaFiles = document.getElementById('galeriaFiles');
            const galeriaRow = document.getElementById('galeriaRow');

            async function postFormData(url, formData) {
                const res = await fetch(url, {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                });
                const text = await res.text();
                let data;
                try { data = JSON.parse(text); } catch (e) { data = { ok: false, message: text }; }
                if (!res.ok || !data || data.ok !== true) {
                    throw new Error((data && data.message) ? data.message : 'Falha no upload');
                }
                return data;
            }

            if (btnCapa && capaFile && capaImg) {
                btnCapa.addEventListener('click', async function() {
                    if (!capaFile.files || !capaFile.files[0]) return;
                    const url = btnCapa.getAttribute('data-url');
                    const fd = new FormData();
                    fd.append('capa', capaFile.files[0]);
                    btnCapa.disabled = true;
                    try {
                        const data = await postFormData(url, fd);
                        if (data && data.url) {
                            capaImg.src = data.url;
                            const parentLink = capaImg.closest('a');
                            if (parentLink) parentLink.href = data.url;
                        }
                        capaFile.value = '';
                    } catch (e) {
                        alert(e.message || 'Erro ao enviar capa');
                    } finally {
                        btnCapa.disabled = false;
                    }
                });
            }

            const btnRemoverCapa = document.getElementById('btnRemoverCapa');
            if (btnRemoverCapa && capaImg) {
                btnRemoverCapa.addEventListener('click', async function() {
                    try {
                        if (btnRemoverCapa.disabled) return;
                        if (!confirm('Remover a foto de capa deste produto?')) return;
                        const url = btnRemoverCapa.getAttribute('data-url');
                        if (!url) return;
                        btnRemoverCapa.disabled = true;
                        const res = await fetch(url, {
                            method: 'POST',
                            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
                        });
                        const text = await res.text();
                        let data;
                        try { data = JSON.parse(text); } catch (e) { data = { ok: false, message: text }; }
                        if (!res.ok || !data || data.ok !== true) {
                            throw new Error((data && data.message) ? data.message : 'Falha ao remover capa');
                        }

                        const placeholder = capaImg.getAttribute('data-placeholder') || '/uploads/produtos/placeholder.jpg';
                        capaImg.src = placeholder;
                        const parentLink = capaImg.closest('a');
                        if (parentLink) {
                            parentLink.href = placeholder;
                        }
                    } catch (e) {
                        alert(e.message || 'Erro ao remover capa');
                        btnRemoverCapa.disabled = false;
                    }
                });
            }

            if (btnGaleria && galeriaFiles && galeriaRow) {
                btnGaleria.addEventListener('click', async function() {
                    if (!galeriaFiles.files || galeriaFiles.files.length === 0) return;
                    const url = btnGaleria.getAttribute('data-url');
                    const fd = new FormData();
                    for (const f of galeriaFiles.files) fd.append('imagens[]', f);
                    btnGaleria.disabled = true;
                    try {
                        const data = await postFormData(url, fd);
                        const fotos = (data && data.fotos) ? data.fotos : [];
                        fotos.forEach(function(item) {
                            const col = document.createElement('div');
                            col.className = 'col-6 col-md-2 mb-2';
                            const fotoId = item && item.id ? item.id : 0;
                            const url = item && item.url ? item.url : '';
                            col.innerHTML =
                                '<a href="' + url + '" target="_blank">' +
                                '<img src="' + url + '" alt="Foto" class="img-thumbnail" style="width: 100%; height: 100px; object-fit: cover;">' +
                                '</a>' +
                                '<div class="mt-2">' +
                                '<input type="number" class="form-control form-control-sm" name="ordens[' + fotoId + ']" value="0" min="0">' +
                                '</div>' +
                                '<div class="mt-2">' +
                                '<button type="submit" class="btn btn-sm btn-outline-danger w-100" formaction="/admin/produtos/remover-foto/' + fotoId + '" formmethod="POST" formnovalidate onclick="return confirm(\'Remover esta foto?\')">Remover</button>' +
                                '</div>';
                            galeriaRow.appendChild(col);
                        });
                        galeriaFiles.value = '';
                    } catch (e) {
                        alert(e.message || 'Erro ao enviar fotos');
                    } finally {
                        btnGaleria.disabled = false;
                    }
                });
            }

            const btnDesmarcarOpcoes = document.getElementById('btnDesmarcarOpcoes');
            if (btnDesmarcarOpcoes) {
                btnDesmarcarOpcoes.addEventListener('click', function() {
                    try {
                        const form = btnDesmarcarOpcoes.closest('form') || document;
                        const checks = form.querySelectorAll('input[type="checkbox"]');
                        checks.forEach((el) => {
                            const name = (el.getAttribute('name') || '');
                            const id = (el.getAttribute('id') || '');
                            const isTipo = (name === 'tipo_ids[]') || (id.startsWith('tipo_'));
                            const isOpcao = name.startsWith('opcoes[');
                            const isVariacaoAtiva = name.startsWith('variacao_ativo[');
                            if (isTipo || isOpcao || isVariacaoAtiva) {
                                el.checked = false;
                            }
                        });
                    } catch (e) {
                    }
                });
            }

            // Upload de galeria por variação
            document.querySelectorAll('.btnUploadVarGaleria').forEach((btn) => {
                btn.addEventListener('click', async function() {
                    const varId = btn.getAttribute('data-var-id');
                    const input = document.getElementById('varGaleriaFiles_' + varId);
                    const row = document.getElementById('varGaleriaRow_' + varId);
                    if (!varId || !input || !row) return;
                    if (!input.files || input.files.length === 0) return;
                    const url = btn.getAttribute('data-url');
                    const fd = new FormData();
                    for (const f of input.files) fd.append('imagens[]', f);
                    btn.disabled = true;
                    try {
                        const data = await postFormData(url, fd);
                        const fotos = (data && data.fotos) ? data.fotos : [];
                        fotos.forEach(function(item) {
                            const col = document.createElement('div');
                            col.className = 'col-6 col-md-2 mb-2';
                            const fotoId = item && item.id ? item.id : 0;
                            const url = item && item.url ? item.url : '';
                            col.innerHTML =
                                '<a href="' + url + '" target="_blank">' +
                                '<img src="' + url + '" alt="Foto" class="img-thumbnail" style="width: 100%; height: 100px; object-fit: cover;">' +
                                '</a>' +
                                '<div class="mt-2">' +
                                '<input type="number" class="form-control form-control-sm" name="ordens_variacao[' + fotoId + ']" value="0" min="0">' +
                                '</div>' +
                                '<div class="mt-2">' +
                                '<button type="submit" class="btn btn-sm btn-outline-danger w-100" formaction="/admin/produtos/variacoes/fotos/remover/' + fotoId + '" formmethod="POST" formnovalidate onclick="return confirm(\'Remover esta foto da variação?\')">Remover</button>' +
                                '</div>';
                            row.appendChild(col);
                        });
                        input.value = '';
                    } catch (e) {
                        alert(e.message || 'Erro ao enviar fotos da variação');
                    } finally {
                        btn.disabled = false;
                    }
                });
            });
        })();
</script>
HTMLSCRIPT;

        echo '
</body>
</html>';
        exit;
    }
    
    public function atualizar(Request $request, $id = null) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte', 'representante']);
        $id = $id ?? $request->getParam('id');
        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            $pdo->beginTransaction();
            $cols = $this->getTableColumns($pdo, 'produtos');

            $perfil = $this->getSessionPerfil();
            $repId = $this->getSessionUserId();
            $repEmail = $this->getSessionUserEmail();

            $this->requireProdutoOwnerIfRepresentante($pdo, (int) $id);
            
            $price = $this->parseMoneyToDb($request->getParam('price'));
            $costPrice = $this->parseMoneyToDb($request->getParam('cost_price'));
            $salePrice = $this->parseMoneyToDb($request->getParam('sale_price'));

            if ($perfil === 'representante' && trim((string) $costPrice) === '') {
                throw new \Exception('Preço de custo (USD) é obrigatório para representante.');
            }
            
            // Validar categoria se fornecida
            $categoryId = $request->getParam('category_id');
            if (!empty($categoryId)) {
                $stmtCat = $pdo->prepare("SELECT id FROM categorias WHERE id = ?");
                $stmtCat->execute([$categoryId]);
                if (!$stmtCat->fetch()) {
                    throw new \Exception("Categoria selecionada não existe");
                }
            } else {
                $categoryId = null;
            }
            
            $lojaParam = $request->getParam('loja');
            $lojaId = is_numeric($lojaParam) ? (int) $lojaParam : 0;
            $lojaSlug = $lojaParam;
            if ($lojaId > 0) {
                try {
                    $stmtT = $pdo->query("SHOW TABLES LIKE 'lojas'");
                    if ($stmtT && $stmtT->fetchColumn()) {
                        $stmtL = $pdo->prepare('SELECT slug FROM lojas WHERE id = :id LIMIT 1');
                        $stmtL->execute([':id' => $lojaId]);
                        $tmpSlug = $stmtL->fetchColumn();
                        if ($tmpSlug !== false && (string) $tmpSlug !== '') {
                            $lojaSlug = (string) $tmpSlug;
                        }
                    }
                } catch (\Exception $e) {
                }
            }

            $stmt = $pdo->prepare("
                UPDATE produtos SET 
                    name = ?, sku = ?, loja = ?, ncm = ?, description = ?, short_description = ?, category_id = ?, 
                    price = ?, cost_price = ?, sale_price = ?, stock = ?, min_stock = ?, weight = ?, 
                    status = ?, active = ?, featured = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $request->getParam('name'),
                $request->getParam('sku'),
                $lojaSlug,
                $request->getParam('ncm'),
                $request->getParam('description'),
                $request->getParam('short_description'),
                $categoryId,
                $price,
                $costPrice,
                $salePrice,
                $request->getParam('stock') ?: 0,
                $request->getParam('min_stock') ?: 0,
                $request->getParam('weight') ?: 0,
                $request->getParam('status'),
                (strtolower(trim((string) $request->getParam('status'))) === 'archived' ? 0 : ($request->getParam('active') ?: 0)),
                $request->getParam('featured') ?: 0,
                $id
            ]);

            if (in_array('loja_id', $cols, true) && $lojaId > 0) {
                $stmtLojaId = $pdo->prepare('UPDATE produtos SET loja_id = ? WHERE id = ?');
                $stmtLojaId->execute([$lojaId, $id]);
            }

            if (in_array('clube_ativo', $cols, true)) {
                $stmtClube = $pdo->prepare('UPDATE produtos SET clube_ativo = ? WHERE id = ?');
                $stmtClube->execute([(int) ($request->getParam('clube_ativo') ?: 0), (int) $id]);
            }

            if ($perfil === 'representante') {
                if (in_array('moeda', $cols, true)) {
                    $pdo->prepare('UPDATE produtos SET moeda = ? WHERE id = ?')->execute(['USD', (int) $id]);
                }
                if (in_array('currency', $cols, true)) {
                    $pdo->prepare('UPDATE produtos SET currency = ? WHERE id = ?')->execute(['USD', (int) $id]);
                }
                if (in_array('representante_id', $cols, true)) {
                    $pdo->prepare('UPDATE produtos SET representante_id = ? WHERE id = ?')->execute([(int) $repId, (int) $id]);
                }
                if (in_array('representante_email', $cols, true)) {
                    $pdo->prepare('UPDATE produtos SET representante_email = ? WHERE id = ?')->execute([(string) $repEmail, (int) $id]);
                }
            }

            $rowsUpdated = $stmt->rowCount();
            $stmtWarnings = $pdo->query('SHOW WARNINGS');
            $warnings = $stmtWarnings ? $stmtWarnings->fetchAll(\PDO::FETCH_ASSOC) : [];

            // Atualizar foto de capa (se enviada)
            if (isset($_FILES['capa']) && !empty($_FILES['capa']['name']) && ($_FILES['capa']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $uploadDir = $this->getProdutoUploadsDir();
                $webDir = '/uploads/produtos/';
                $this->ensureDir($uploadDir);

                $name = $_FILES['capa']['name'];
                $fileName = preg_replace('/[^A-Za-z0-9\-_\.]/', '', $name);
                $fileName = time() . '_' . $fileName;
                $filePath = $uploadDir . $fileName;
                $webPath = $webDir . $fileName;

                if (move_uploaded_file($_FILES['capa']['tmp_name'], $filePath)) {
                    $stmtCover = $pdo->prepare('UPDATE produtos SET foto_principal = ? WHERE id = ?');
                    $stmtCover->execute([$webPath, $id]);
                }
            }
            
            // Processar novas imagens
            if (isset($_FILES['imagens'])) {
                $uploadDir = $this->getProdutoUploadsDir();
                $webDir = '/uploads/produtos/';
                $this->ensureDir($uploadDir);
                
                foreach ($_FILES['imagens']['name'] as $key => $name) {
                    if ($_FILES['imagens']['error'][$key] === 0) {
                        // Limpar nome do arquivo
                        $fileName = preg_replace('/[^A-Za-z0-9\-_\.]/', '', $name);
                        $fileName = time() . '_' . $fileName;
                        
                        $filePath = $uploadDir . $fileName;
                        $webPath = $webDir . $fileName;
                        
                        if (move_uploaded_file($_FILES['imagens']['tmp_name'][$key], $filePath)) {
                            $stmt = $pdo->prepare("
                                INSERT INTO produto_fotos (produto_id, nome_arquivo, arquivo_original, principal, ordem)
                                VALUES (?, ?, ?, ?, ?)
                            ");
                            $stmt->execute([
                                $id,
                                $webPath,
                                $name,
                                0, // Galeria: não é principal
                                $key
                            ]);
                        }
                    }
                }
            }
            
            $pdo->commit();

            if ($request->getParam('debug_loja')) {
                $stmtCol = $pdo->query("SHOW COLUMNS FROM produtos LIKE 'loja'");
                $colInfo = $stmtCol ? $stmtCol->fetch(\PDO::FETCH_ASSOC) : null;

                $stmtCheck = $pdo->prepare('SELECT loja FROM produtos WHERE id = ?');
                $stmtCheck->execute([$id]);
                $dbRow = $stmtCheck->fetch(\PDO::FETCH_ASSOC);

                echo '<pre style="padding:12px;background:#fff;border:1px solid #ddd;max-width:100%;overflow:auto">';
                var_dump([
                    'produto_id' => (int) $id,
                    'loja_post' => $request->getParam('loja'),
                    'update_rowCount' => (int) $rowsUpdated,
                    'loja_db' => $dbRow['loja'] ?? null,
                    'loja_column' => $colInfo,
                    'sql_warnings' => $warnings,
                ]);
                echo '</pre>';
                exit;
            }

            if ($perfil === 'representante') {
                header('Location: /admin/representante/produtos?success=2');
            } else {
                header('Location: /admin/produtos?success=2');
            }
            exit;
            
        } catch (\Exception $e) {
            if (isset($pdo)) $pdo->rollBack();
            echo '<div class="alert alert-danger">Erro: ' . $e->getMessage() . '</div>';
            exit;
        }
    }

    public function salvarAtributosVariacoes(Request $request, $id = null) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte', 'representante']);
        $produtoId = (int) ($id ?? $request->getParam('id'));
        if ($produtoId <= 0) {
            header('Location: /admin/produtos');
            exit;
        }

        $tipoIds = $request->getParam('tipo_ids', []);
        if (!is_array($tipoIds)) $tipoIds = [];
        $tipoIds = array_values(array_unique(array_map('intval', $tipoIds)));

        $opcoes = $request->getParam('opcoes', []);
        if (!is_array($opcoes)) $opcoes = [];

        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');

            $this->requireProdutoOwnerIfRepresentante($pdo, (int) $produtoId);
            if (!$this->tableExists($pdo, 'produto_atributos')) {
                $_SESSION['message'] = 'Tabelas de variações não encontradas. Rode a migration 061.';
                $_SESSION['message_type'] = 'warning';
                header('Location: /admin/produtos/editar/' . $produtoId);
                exit;
            }

            $pdo->beginTransaction();
            $stmtDel = $pdo->prepare('DELETE FROM produto_atributos WHERE produto_id = :pid');
            $stmtDel->execute([':pid' => $produtoId]);

            if ($this->tableExists($pdo, 'produto_atributo_opcoes')) {
                $pdo->prepare('DELETE FROM produto_atributo_opcoes WHERE produto_id = :pid')->execute([':pid' => $produtoId]);
            }

            if (!empty($tipoIds)) {
                $stmtIns = $pdo->prepare('INSERT INTO produto_atributos (produto_id, tipo_id, created_at, updated_at) VALUES (:pid, :tid, NOW(), NOW())');
                foreach ($tipoIds as $tid) {
                    if ($tid <= 0) continue;
                    $stmtIns->execute([':pid' => $produtoId, ':tid' => $tid]);
                }
            }

            if ($this->tableExists($pdo, 'produto_atributo_opcoes') && !empty($opcoes)) {
                $stmtInsOp = $pdo->prepare('INSERT IGNORE INTO produto_atributo_opcoes (produto_id, tipo_id, opcao_id, created_at) VALUES (:pid, :tid, :oid, NOW())');
                foreach ($opcoes as $tid => $list) {
                    $tid = (int) $tid;
                    if ($tid <= 0) continue;
                    if (!is_array($list)) continue;
                    foreach ($list as $oid) {
                        $oid = (int) $oid;
                        if ($oid <= 0) continue;
                        $stmtInsOp->execute([':pid' => $produtoId, ':tid' => $tid, ':oid' => $oid]);
                    }
                }
            }

            $pdo->commit();
            $_SESSION['message'] = 'Atributos/Opções salvos.';
            $_SESSION['message_type'] = 'success';
        } catch (\Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['message'] = 'Erro ao salvar atributos.';
            $_SESSION['message_type'] = 'danger';
        }

        header('Location: /admin/produtos/editar/' . $produtoId);
        exit;
    }

    public function salvarVariacoes(Request $request, $id = null) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte', 'representante']);
        $produtoId = (int) ($id ?? $request->getParam('id'));
        if ($produtoId <= 0) {
            header('Location: /admin/produtos');
            exit;
        }

        $stocks = $request->getParam('variacao_stock', []);
        if (!is_array($stocks)) $stocks = [];

        $prices = $request->getParam('variacao_price_override', []);
        if (!is_array($prices)) $prices = [];

        $ativos = $request->getParam('variacao_ativo', []);
        if (!is_array($ativos)) $ativos = [];

        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            $this->requireProdutoOwnerIfRepresentante($pdo, (int) $produtoId);
            if (!$this->tableExists($pdo, 'produto_variacoes')) {
                throw new \Exception('Tabelas de variações não encontradas');
            }

            $pdo->beginTransaction();
            $stmt = $pdo->prepare('UPDATE produto_variacoes SET stock = :st, price_override = :po, ativo = :at, updated_at = NOW() WHERE id = :vid AND produto_id = :pid');

            $varIds = [];
            foreach ($stocks as $k => $_) {
                $vid = (int) $k;
                if ($vid > 0) $varIds[$vid] = true;
            }
            foreach ($prices as $k => $_) {
                $vid = (int) $k;
                if ($vid > 0) $varIds[$vid] = true;
            }
            foreach ($ativos as $k => $_) {
                $vid = (int) $k;
                if ($vid > 0) $varIds[$vid] = true;
            }

            foreach (array_keys($varIds) as $vid) {
                $stock = (int) ($stocks[$vid] ?? 0);
                $poRaw = trim((string) ($prices[$vid] ?? ''));
                $po = ($poRaw !== '') ? $this->parseMoneyToDb($poRaw) : null;

                $ativo = 0;
                if (array_key_exists($vid, $ativos)) {
                    $ativo = ((string) $ativos[$vid] === '1' || (int) $ativos[$vid] === 1) ? 1 : 0;
                }

                $stmt->bindValue(':st', $stock, \PDO::PARAM_INT);
                if ($po === null) {
                    $stmt->bindValue(':po', null, \PDO::PARAM_NULL);
                } else {
                    $stmt->bindValue(':po', $po);
                }
                $stmt->bindValue(':at', $ativo, \PDO::PARAM_INT);
                $stmt->bindValue(':vid', $vid, \PDO::PARAM_INT);
                $stmt->bindValue(':pid', $produtoId, \PDO::PARAM_INT);
                $stmt->execute();
            }

            $pdo->commit();
            $_SESSION['message'] = 'Variações atualizadas.';
            $_SESSION['message_type'] = 'success';
        } catch (\Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['message'] = 'Erro ao atualizar variações.';
            $_SESSION['message_type'] = 'danger';
        }

        header('Location: /admin/produtos/editar/' . $produtoId);
        exit;
    }

    public function apagarVariacoes(Request $request, $id = null) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte', 'representante']);
        $produtoId = (int) ($id ?? $request->getParam('id'));
        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            $this->requireProdutoOwnerIfRepresentante($pdo, (int) $produtoId);
            $pdo->beginTransaction();

            if (!$this->tableExists($pdo, 'produto_variacoes')) {
                throw new \Exception('Tabelas de variações não encontradas');
            }

            $stmtVarIds = $pdo->prepare('SELECT id FROM produto_variacoes WHERE produto_id = :pid');
            $stmtVarIds->execute([':pid' => $produtoId]);
            $ids = array_map('intval', $stmtVarIds->fetchAll(\PDO::FETCH_COLUMN) ?: []);
            if (!empty($ids)) {
                $in = implode(',', array_fill(0, count($ids), '?'));
                if ($this->tableExists($pdo, 'produto_variacao_fotos')) {
                    $pdo->prepare('DELETE FROM produto_variacao_fotos WHERE produto_variacao_id IN (' . $in . ')')->execute($ids);
                }
                if ($this->tableExists($pdo, 'produto_variacao_itens')) {
                    $pdo->prepare('DELETE FROM produto_variacao_itens WHERE produto_variacao_id IN (' . $in . ')')->execute($ids);
                }
            }

            $stmtDel = $pdo->prepare('DELETE FROM produto_variacoes WHERE produto_id = :pid');
            $stmtDel->execute([':pid' => $produtoId]);

            $pdo->commit();
            $_SESSION['message'] = 'Variações removidas.';
            $_SESSION['message_type'] = 'success';
        } catch (\Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['message'] = 'Erro ao apagar variações.';
            $_SESSION['message_type'] = 'danger';
        }

        header('Location: /admin/produtos/editar/' . $produtoId);
        exit;
    }

    public function gerarVariacoes(Request $request, $id = null) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte', 'representante']);
        $produtoId = (int) ($id ?? $request->getParam('id'));
        $replace = (int) $request->getParam('replace', 0) === 1;

        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            $this->requireProdutoOwnerIfRepresentante($pdo, (int) $produtoId);
            if (!$this->tableExists($pdo, 'produto_variacoes') || !$this->tableExists($pdo, 'produto_variacao_itens')) {
                throw new \Exception('Tabelas de variações não encontradas');
            }

            $opcoesPorTipo = $this->getProdutoOpcoesUsadasPorTipo($pdo, $produtoId);
            $tipoIds = array_keys($opcoesPorTipo);

            $tiposValidos = [];
            foreach ($tipoIds as $tid) {
                $list = array_values(array_unique(array_map('intval', $opcoesPorTipo[$tid] ?? [])));
                if (!empty($list)) {
                    $tiposValidos[(int) $tid] = $list;
                }
            }

            if (empty($tiposValidos)) {
                $_SESSION['message'] = 'Selecione opções nos atributos antes de gerar variações.';
                $_SESSION['message_type'] = 'warning';
                header('Location: /admin/produtos/editar/' . $produtoId);
                exit;
            }

            $pdo->beginTransaction();

            if ($replace) {
                $stmtVarIds = $pdo->prepare('SELECT id FROM produto_variacoes WHERE produto_id = :pid');
                $stmtVarIds->execute([':pid' => $produtoId]);
                $ids = array_map('intval', $stmtVarIds->fetchAll(\PDO::FETCH_COLUMN) ?: []);
                if (!empty($ids)) {
                    $in = implode(',', array_fill(0, count($ids), '?'));
                    if ($this->tableExists($pdo, 'produto_variacao_fotos')) {
                        $pdo->prepare('DELETE FROM produto_variacao_fotos WHERE produto_variacao_id IN (' . $in . ')')->execute($ids);
                    }
                    $pdo->prepare('DELETE FROM produto_variacao_itens WHERE produto_variacao_id IN (' . $in . ')')->execute($ids);
                }
                $pdo->prepare('DELETE FROM produto_variacoes WHERE produto_id = :pid')->execute([':pid' => $produtoId]);
            }

            $existingSignatures = $this->getProdutoVariacoesSignatures($pdo, $produtoId);

            $combinacoes = [[]];
            foreach ($tiposValidos as $tid => $opcoesIds) {
                $new = [];
                foreach ($combinacoes as $c) {
                    foreach ($opcoesIds as $oid) {
                        $tmp = $c;
                        $tmp[(int) $tid] = (int) $oid;
                        $new[] = $tmp;
                    }
                }
                $combinacoes = $new;
            }

            $stmtInsVar = $pdo->prepare('INSERT INTO produto_variacoes (produto_id, sku, price_override, stock, ativo, created_at, updated_at) VALUES (:pid, NULL, NULL, :stock, 1, NOW(), NOW())');
            $stmtInsItem = $pdo->prepare('INSERT INTO produto_variacao_itens (produto_variacao_id, tipo_id, opcao_id, created_at, updated_at) VALUES (:pvi, :tid, :oid, NOW(), NOW())');

            $created = 0;
            foreach ($combinacoes as $comb) {
                ksort($comb);
                $parts = [];
                foreach ($comb as $tid => $oid) {
                    $parts[] = $tid . ':' . $oid;
                }
                $sig = implode('|', $parts);
                if (isset($existingSignatures[$sig])) {
                    continue;
                }

                $stmtInsVar->execute([':pid' => $produtoId, ':stock' => 0]);
                $varId = (int) $pdo->lastInsertId();
                foreach ($comb as $tid => $oid) {
                    $stmtInsItem->execute([':pvi' => $varId, ':tid' => (int) $tid, ':oid' => (int) $oid]);
                }
                $created++;
            }

            $pdo->commit();
            $_SESSION['message'] = 'Variações geradas: ' . (int) $created;
            $_SESSION['message_type'] = 'success';
        } catch (\Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
            $_SESSION['message'] = 'Erro ao gerar variações.';
            $_SESSION['message_type'] = 'danger';
        }

        header('Location: /admin/produtos/editar/' . $produtoId);
        exit;
    }

    public function criarVariacaoIndividual(Request $request, $id = null) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte', 'representante']);
        $produtoId = (int) ($id ?? $request->getParam('id'));
        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            $this->requireProdutoOwnerIfRepresentante($pdo, (int) $produtoId);
            if (!$this->tableExists($pdo, 'produto_variacoes')) {
                throw new \Exception('Tabelas de variações não encontradas');
            }

            $stock = (int) $request->getParam('stock', 0);
            $priceOverrideRaw = trim((string) $request->getParam('price_override', ''));
            $priceOverride = $priceOverrideRaw !== '' ? $this->parseMoneyToDb($priceOverrideRaw) : null;

            $stmt = $pdo->prepare('INSERT INTO produto_variacoes (produto_id, sku, price_override, stock, ativo, created_at, updated_at) VALUES (:pid, NULL, :po, :st, 1, NOW(), NOW())');
            $stmt->bindValue(':pid', $produtoId, \PDO::PARAM_INT);
            if ($priceOverride === null || $priceOverride === '') {
                $stmt->bindValue(':po', null, \PDO::PARAM_NULL);
            } else {
                $stmt->bindValue(':po', $priceOverride);
            }
            $stmt->bindValue(':st', $stock, \PDO::PARAM_INT);
            $stmt->execute();

            $_SESSION['message'] = 'Variação criada.';
            $_SESSION['message_type'] = 'success';
        } catch (\Exception $e) {
            $_SESSION['message'] = 'Erro ao criar variação.';
            $_SESSION['message_type'] = 'danger';
        }

        header('Location: /admin/produtos/editar/' . $produtoId);
        exit;
    }

    private function tableExists(\PDO $pdo, string $table): bool {
        try {
            $st = $pdo->prepare('SHOW TABLES LIKE ?');
            $st->execute([$table]);
            return (bool) $st->fetchColumn();
        } catch (\Exception $e) {
            return false;
        }
    }

    private function getVariacaoTipos(\PDO $pdo): array {
        try {
            $stmt = $pdo->query('SELECT * FROM variacao_tipos WHERE ativo = 1 ORDER BY nome ASC');
            return $stmt ? ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getVariacaoOpcoesPorTipo(\PDO $pdo): array {
        $map = [];
        try {
            $stmt = $pdo->query('SELECT * FROM variacao_opcoes WHERE ativo = 1 ORDER BY tipo_id ASC, ordem ASC, valor ASC');
            $rows = $stmt ? ($stmt->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];
            foreach ($rows as $r) {
                $tid = (int) ($r['tipo_id'] ?? 0);
                if ($tid <= 0) continue;
                if (!isset($map[$tid])) $map[$tid] = [];
                $map[$tid][] = $r;
            }
        } catch (\Exception $e) {
            $map = [];
        }
        return $map;
    }

    private function getProdutoAtributos(\PDO $pdo, int $produtoId): array {
        try {
            $stmt = $pdo->prepare('SELECT tipo_id FROM produto_atributos WHERE produto_id = :pid');
            $stmt->execute([':pid' => $produtoId]);
            $ids = $stmt->fetchAll(\PDO::FETCH_COLUMN) ?: [];
            return array_values(array_unique(array_map('intval', $ids)));
        } catch (\Exception $e) {
            return [];
        }
    }

    private function getProdutoOpcoesUsadasPorTipo(\PDO $pdo, int $produtoId): array {
        $map = [];
        try {
            if ($this->tableExists($pdo, 'produto_atributo_opcoes')) {
                $stmt = $pdo->prepare('SELECT tipo_id, opcao_id FROM produto_atributo_opcoes WHERE produto_id = :pid');
                $stmt->execute([':pid' => $produtoId]);
                $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                foreach ($rows as $r) {
                    $tid = (int) ($r['tipo_id'] ?? 0);
                    $oid = (int) ($r['opcao_id'] ?? 0);
                    if ($tid <= 0 || $oid <= 0) continue;
                    if (!isset($map[$tid])) $map[$tid] = [];
                    $map[$tid][$oid] = true;
                }
                foreach ($map as $tid => $set) {
                    $map[$tid] = array_map('intval', array_keys($set));
                }
                if (!empty($map)) {
                    return $map;
                }
            }

            // Fallback: deduzir opções a partir das variações existentes
            $stmt = $pdo->prepare('
                SELECT pvi.tipo_id, pvi.opcao_id
                FROM produto_variacao_itens pvi
                INNER JOIN produto_variacoes pv ON pv.id = pvi.produto_variacao_id
                WHERE pv.produto_id = :pid
            ');
            $stmt->execute([':pid' => $produtoId]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as $r) {
                $tid = (int) ($r['tipo_id'] ?? 0);
                $oid = (int) ($r['opcao_id'] ?? 0);
                if ($tid <= 0 || $oid <= 0) continue;
                if (!isset($map[$tid])) $map[$tid] = [];
                $map[$tid][$oid] = true;
            }
            foreach ($map as $tid => $set) {
                $map[$tid] = array_map('intval', array_keys($set));
            }
        } catch (\Exception $e) {
            $map = [];
        }
        return $map;
    }

    private function getProdutoVariacoesSignatures(\PDO $pdo, int $produtoId): array {
        $sigs = [];
        try {
            $stmt = $pdo->prepare('
                SELECT pv.id AS variacao_id, pvi.tipo_id, pvi.opcao_id
                FROM produto_variacoes pv
                LEFT JOIN produto_variacao_itens pvi ON pvi.produto_variacao_id = pv.id
                WHERE pv.produto_id = :pid
                ORDER BY pv.id ASC
            ');
            $stmt->execute([':pid' => $produtoId]);
            $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            $tmp = [];
            foreach ($rows as $r) {
                $vid = (int) ($r['variacao_id'] ?? 0);
                $tid = (int) ($r['tipo_id'] ?? 0);
                $oid = (int) ($r['opcao_id'] ?? 0);
                if ($vid <= 0) continue;
                if (!isset($tmp[$vid])) $tmp[$vid] = [];
                if ($tid > 0 && $oid > 0) {
                    $tmp[$vid][$tid] = $oid;
                }
            }
            foreach ($tmp as $vid => $comb) {
                ksort($comb);
                $parts = [];
                foreach ($comb as $tid => $oid) {
                    $parts[] = $tid . ':' . $oid;
                }
                $sig = implode('|', $parts);
                if ($sig !== '') {
                    $sigs[$sig] = true;
                }
            }
        } catch (\Exception $e) {
            $sigs = [];
        }
        return $sigs;
    }

    private function getProdutoVariacoesComDescricao(\PDO $pdo, int $produtoId): array {
        try {
            $stmt = $pdo->prepare('SELECT * FROM produto_variacoes WHERE produto_id = :pid ORDER BY id ASC');
            $stmt->execute([':pid' => $produtoId]);
            $vars = $stmt->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            if (empty($vars)) return [];

            $ids = array_map(fn($v) => (int) ($v['id'] ?? 0), $vars);
            $ids = array_values(array_filter($ids, fn($v) => $v > 0));
            if (empty($ids)) return $vars;

            $in = implode(',', array_fill(0, count($ids), '?'));
            $sql = '
                SELECT pvi.produto_variacao_id, vt.nome AS tipo_nome, vo.valor AS opcao_valor
                FROM produto_variacao_itens pvi
                INNER JOIN variacao_tipos vt ON vt.id = pvi.tipo_id
                INNER JOIN variacao_opcoes vo ON vo.id = pvi.opcao_id
                WHERE pvi.produto_variacao_id IN (' . $in . ')
                ORDER BY pvi.produto_variacao_id ASC, vt.nome ASC, vo.valor ASC
            ';
            $st = $pdo->prepare($sql);
            $st->execute($ids);
            $rows = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            $map = [];
            foreach ($rows as $r) {
                $vid = (int) ($r['produto_variacao_id'] ?? 0);
                $tn = (string) ($r['tipo_nome'] ?? '');
                $ov = (string) ($r['opcao_valor'] ?? '');
                if ($vid <= 0) continue;
                if (!isset($map[$vid])) $map[$vid] = [];
                $map[$vid][] = $tn . '=' . $ov;
            }

            foreach ($vars as &$v) {
                $vid = (int) ($v['id'] ?? 0);
                $desc = '';
                if ($vid > 0 && !empty($map[$vid])) {
                    $desc = implode(' / ', $map[$vid]);
                }
                $v['descricao'] = $desc;
            }
            unset($v);

            return $vars;
        } catch (\Exception $e) {
            return [];
        }
    }

    public function uploadCapa(Request $request, $id = null) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte', 'representante']);
        $id = (int) ($id ?? $request->getParam('id'));
        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            $this->requireProdutoOwnerIfRepresentante($pdo, (int) $id);
            $pdo->beginTransaction();

            if (isset($_FILES['capa']) && !empty($_FILES['capa']['name']) && ($_FILES['capa']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
                $uploadDir = $this->getProdutoUploadsDir();
                $webDir = '/uploads/produtos/';
                $this->ensureDir($uploadDir);

                $name = $_FILES['capa']['name'];
                $fileName = preg_replace('/[^A-Za-z0-9\-_\.]/', '', $name);
                $fileName = time() . '_' . $fileName;
                $filePath = $uploadDir . $fileName;
                $webPath = $webDir . $fileName;

                if (!move_uploaded_file($_FILES['capa']['tmp_name'], $filePath)) {
                    throw new \Exception('Erro ao fazer upload da capa');
                }

                $stmtCover = $pdo->prepare('UPDATE produtos SET foto_principal = ? WHERE id = ?');
                $stmtCover->execute([$webPath, $id]);
            }

            $pdo->commit();
            $isAjax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') || (strpos((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false);
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'url' => isset($webPath) ? Url::absolute($webPath) : null]);
                exit;
            }

            header('Location: /admin/produtos/editar/' . $id);
            exit;
        } catch (\Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $isAjax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') || (strpos((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false);
            if ($isAjax) {
                http_response_code(400);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
                exit;
            }

            echo '<div class="alert alert-danger">Erro: ' . $e->getMessage() . '</div>';
            exit;
        }
    }

    public function uploadGaleria(Request $request, $id = null) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte', 'representante']);
        $id = (int) ($id ?? $request->getParam('id'));
        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            $this->requireProdutoOwnerIfRepresentante($pdo, (int) $id);
            $pdo->beginTransaction();

            $inserted = [];

            if (isset($_FILES['imagens']) && !empty($_FILES['imagens']['name'][0])) {
                $uploadDir = $this->getProdutoUploadsDir();
                $webDir = '/uploads/produtos/';
                $this->ensureDir($uploadDir);

                foreach ($_FILES['imagens']['name'] as $key => $name) {
                    if (($_FILES['imagens']['error'][$key] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
                        continue;
                    }

                    $fileName = preg_replace('/[^A-Za-z0-9\-_\.]/', '', (string) $name);
                    $fileName = time() . '_' . $fileName;
                    $filePath = $uploadDir . $fileName;
                    $webPath = $webDir . $fileName;

                    if (!move_uploaded_file($_FILES['imagens']['tmp_name'][$key], $filePath)) {
                        continue;
                    }

                    $stmt = $pdo->prepare('
                        INSERT INTO produto_fotos (produto_id, nome_arquivo, arquivo_original, principal, ordem)
                        VALUES (?, ?, ?, ?, ?)
                    ');
                    $stmt->execute([
                        $id,
                        $webPath,
                        $name,
                        0,
                        (int) $key
                    ]);

                    $insertId = (int) $pdo->lastInsertId();
                    $inserted[] = ['id' => $insertId, 'url' => Url::absolute($webPath)];
                }
            }

            $pdo->commit();
            $isAjax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') || (strpos((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false);
            if ($isAjax) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => true, 'fotos' => $inserted]);
                exit;
            }

            header('Location: /admin/produtos/editar/' . $id);
            exit;
        } catch (\Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $isAjax = (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest') || (strpos((string) ($_SERVER['HTTP_ACCEPT'] ?? ''), 'application/json') !== false);
            if ($isAjax) {
                http_response_code(400);
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
                exit;
            }

            echo '<div class="alert alert-danger">Erro: ' . $e->getMessage() . '</div>';
            exit;
        }
    }
    
    public function removerFoto(Request $request, $fotoId = null) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte', 'representante']);
        $fotoId = $fotoId ?? $request->getParam('id');
        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            
            $stmt = $pdo->prepare("SELECT id, produto_id, nome_arquivo FROM produto_fotos WHERE id = ? LIMIT 1");
            $stmt->execute([$fotoId]);
            $foto = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if ($foto) {
                $this->requireProdutoOwnerIfRepresentante($pdo, (int) ($foto['produto_id'] ?? 0));
                // Remover arquivo físico
                $filePath = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($foto['nome_arquivo'], '/');
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
                
                // Remover do banco
                $stmt = $pdo->prepare("DELETE FROM produto_fotos WHERE id = ?");
                $stmt->execute([$fotoId]);
                
                if ($this->isAjaxRequest()) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => true]);
                } else {
                    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/admin/produtos'));
                }
            } else {
                if ($this->isAjaxRequest()) {
                    header('Content-Type: application/json');
                    echo json_encode(['success' => false, 'message' => 'Foto não encontrada']);
                } else {
                    header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/admin/produtos'));
                }
            }
        } catch (\Exception $e) {
            if ($this->isAjaxRequest()) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => $e->getMessage()]);
            } else {
                header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? '/admin/produtos'));
            }
        }
        exit;
    }

    public function removerCapa(Request $request, $id = null) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte', 'representante']);
        $id = (int) ($id ?? $request->getParam('id'));
        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            $this->requireProdutoOwnerIfRepresentante($pdo, (int) $id);
            $stmt = $pdo->prepare('SELECT foto_principal FROM produtos WHERE id = ?');
            $stmt->execute([$id]);
            $produto = $stmt->fetch(\PDO::FETCH_ASSOC);

            if ($produto && !empty($produto['foto_principal'])) {
                $filePath = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim((string) $produto['foto_principal'], '/');
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }

            $stmt = $pdo->prepare('UPDATE produtos SET foto_principal = NULL WHERE id = ?');
            $stmt->execute([$id]);

            if ($this->isAjaxRequest()) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => true]);
                exit;
            }

            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? ('/admin/produtos/editar/' . $id)));
            exit;
        } catch (\Exception $e) {
            if ($this->isAjaxRequest()) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
                exit;
            }

            echo '<div class="alert alert-danger">Erro: ' . $e->getMessage() . '</div>';
            exit;
        }
    }

    public function salvarOrdemGaleria(Request $request, $id = null) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte', 'representante']);
        $id = (int) ($id ?? $request->getParam('id'));
        $ordens = $request->getParam('ordens', []);
        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            $this->requireProdutoOwnerIfRepresentante($pdo, (int) $id);
            $pdo->beginTransaction();

            if (is_array($ordens)) {
                foreach ($ordens as $fotoId => $ordem) {
                    $fotoId = (int) $fotoId;
                    $ordem = (int) $ordem;
                    if ($fotoId <= 0) {
                        continue;
                    }
                    $stmt = $pdo->prepare('UPDATE produto_fotos SET ordem = ? WHERE id = ? AND produto_id = ?');
                    $stmt->execute([$ordem, $fotoId, $id]);
                }
            }

            $pdo->commit();
            header('Location: ' . ($_SERVER['HTTP_REFERER'] ?? ('/admin/produtos/editar/' . $id)));
            exit;
        } catch (\Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo '<div class="alert alert-danger">Erro: ' . $e->getMessage() . '</div>';
            exit;
        }
    }
    
    public function excluir(Request $request, $id = null) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte', 'representante']);
        $id = $id ?? $request->getParam('id');
        try {
            $pdo = new \PDO('mysql:host=localhost;dbname=novobr', 'novobr', '33537095Ab12$');
            $this->requireProdutoOwnerIfRepresentante($pdo, (int) $id);

            if ($this->produtoTemVendas($pdo, (int) $id)) {
                throw new \Exception('Não é possível excluir este produto porque ele já possui vendas. Você pode despublicar o produto.');
            }

            $pdo->beginTransaction();
            
            // Buscar produto para obter imagens
            $stmt = $pdo->prepare("SELECT * FROM produtos WHERE id = ?");
            $stmt->execute([$id]);
            $produto = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            if (!$produto) {
                throw new \Exception("Produto não encontrado");
            }
            
            // Remover imagens físicas
            $stmtFotos = $pdo->prepare("SELECT nome_arquivo FROM produto_fotos WHERE produto_id = ?");
            $stmtFotos->execute([$id]);
            $fotos = $stmtFotos->fetchAll(\PDO::FETCH_ASSOC);
            
            foreach ($fotos as $foto) {
                $filePath = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($foto['nome_arquivo'], '/');
                if (file_exists($filePath)) {
                    unlink($filePath);
                }
            }
            
            // Remover fotos do banco
            $stmt = $pdo->prepare("DELETE FROM produto_fotos WHERE produto_id = ?");
            $stmt->execute([$id]);
            
            // Remover produto
            $stmt = $pdo->prepare("DELETE FROM produtos WHERE id = ?");
            $stmt->execute([$id]);
            
            $pdo->commit();
            $perfil = $this->getSessionPerfil();
            header('Location: ' . ($perfil === 'representante' ? '/admin/representante/produtos?success=3' : '/admin/produtos?success=3'));
            exit;
            
        } catch (\Exception $e) {
            if (isset($pdo) && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            echo '<div class="alert alert-danger">Erro: ' . $e->getMessage() . '</div>';
            exit;
        }
    }

    private function parseMoneyToDb($value): string {
        $s = is_string($value) ? trim($value) : '';
        if ($s === '') {
            return '0';
        }

        $s = str_replace(['$', 'R$', ' '], '', $s);
        $hasComma = strpos($s, ',') !== false;
        $hasDot = strpos($s, '.') !== false;

        if ($hasComma && $hasDot) {
            // format like 15.000,00
            $s = str_replace('.', '', $s);
            $s = str_replace(',', '.', $s);
        } elseif ($hasComma && !$hasDot) {
            // format like 15000,00
            $s = str_replace(',', '.', $s);
        } else {
            // format like 15000.00 or 15000
        }

        $s = preg_replace('/[^0-9.\-]/', '', $s);
        if ($s === '' || $s === '-' || $s === '.' || $s === '-.') {
            return '0';
        }

        return $s;
    }

    private function detectPedidoItensTable(\PDO $pdo): ?string {
        foreach (['pedido_itens', 'pedido_items', 'itens_pedido'] as $t) {
            if ($this->tableExists($pdo, $t)) {
                return $t;
            }
        }
        return null;
    }

    private function getFirstExistingColumn(array $cols, array $candidates): ?string {
        foreach ($candidates as $c) {
            if (in_array($c, $cols, true)) {
                return $c;
            }
        }
        return null;
    }

    private function produtoTemVendas(\PDO $pdo, int $produtoId): bool {
        if ($produtoId <= 0) {
            return false;
        }

        $itensTable = $this->detectPedidoItensTable($pdo);
        if (!$itensTable) {
            return false;
        }

        $colsItens = $this->getTableColumns($pdo, $itensTable);
        $colProdutoId = $this->getFirstExistingColumn($colsItens, ['produto_id', 'product_id']);
        if (!$colProdutoId) {
            return false;
        }

        try {
            $st = $pdo->prepare('SELECT 1 FROM ' . $itensTable . ' WHERE ' . $colProdutoId . ' = ? LIMIT 1');
            $st->execute([$produtoId]);
            return (bool) $st->fetchColumn();
        } catch (\Exception $e) {
            return false;
        }
    }
}
