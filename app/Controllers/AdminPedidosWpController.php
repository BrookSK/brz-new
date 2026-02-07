<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;
use Config\Database;

class AdminPedidosWpController extends Controller {

    public function index(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);

        $busca = trim((string) ($request->getParam('busca') ?? ''));
        $page = (int) ($request->getParam('page') ?? 1);
        if ($page <= 0) $page = 1;
        $limite = (int) ($request->getParam('limit') ?? 50);
        if ($limite <= 0) $limite = 50;
        if ($limite > 200) $limite = 200;
        $offset = ($page - 1) * $limite;

        $pedidos = [];
        $total = 0;
        $erro = '';

        try {
            $localPdo = Database::getConnection();
            $wp = $this->getWpPdo($localPdo);

            $prefix = $wp['prefix'];
            $wpPdo = $wp['pdo'];

            $where = ["p.post_type = 'shop_order'", "p.post_status <> 'trash'"];
            $params = [];

            if ($busca !== '') {
                $where[] = '(CAST(p.ID AS CHAR) LIKE :busca OR p.post_title LIKE :busca OR pm_mail.meta_value LIKE :busca OR CONCAT(COALESCE(pm_fn.meta_value,\'\'),\' \',COALESCE(pm_ln.meta_value,\'\')) LIKE :busca)';
                $params[':busca'] = '%' . $busca . '%';
            }

            $sql = "SELECT
                p.ID AS id,
                p.post_date AS created_at,
                p.post_status AS status,
                p.post_title AS numero_pedido,
                pm_total.meta_value AS order_total,
                pm_curr.meta_value AS currency,
                pm_mail.meta_value AS billing_email,
                pm_fn.meta_value AS billing_first_name,
                pm_ln.meta_value AS billing_last_name
            FROM {$prefix}posts p
            LEFT JOIN {$prefix}postmeta pm_total ON pm_total.post_id = p.ID AND pm_total.meta_key = '_order_total'
            LEFT JOIN {$prefix}postmeta pm_curr ON pm_curr.post_id = p.ID AND pm_curr.meta_key = '_order_currency'
            LEFT JOIN {$prefix}postmeta pm_mail ON pm_mail.post_id = p.ID AND pm_mail.meta_key = '_billing_email'
            LEFT JOIN {$prefix}postmeta pm_fn ON pm_fn.post_id = p.ID AND pm_fn.meta_key = '_billing_first_name'
            LEFT JOIN {$prefix}postmeta pm_ln ON pm_ln.post_id = p.ID AND pm_ln.meta_key = '_billing_last_name'
            WHERE " . implode(' AND ', $where) . "
            ORDER BY p.post_date DESC
            LIMIT {$limite} OFFSET {$offset}";

            $st = $wpPdo->prepare($sql);
            foreach ($params as $k => $v) $st->bindValue($k, $v);
            $st->execute();
            $pedidos = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            $sqlCount = "SELECT COUNT(*)
            FROM {$prefix}posts p
            LEFT JOIN {$prefix}postmeta pm_mail ON pm_mail.post_id = p.ID AND pm_mail.meta_key = '_billing_email'
            LEFT JOIN {$prefix}postmeta pm_fn ON pm_fn.post_id = p.ID AND pm_fn.meta_key = '_billing_first_name'
            LEFT JOIN {$prefix}postmeta pm_ln ON pm_ln.post_id = p.ID AND pm_ln.meta_key = '_billing_last_name'
            WHERE " . implode(' AND ', $where);

            $stC = $wpPdo->prepare($sqlCount);
            foreach ($params as $k => $v) $stC->bindValue($k, $v);
            $stC->execute();
            $total = (int) ($stC->fetchColumn() ?: 0);
        } catch (\Exception $e) {
            $erro = $e->getMessage();
            $pedidos = [];
            $total = 0;
        }

        $sidebarActive = 'pedidos-wp';
        $title = 'Pedidos (WordPress) - Braziliana Shop Admin';

        ob_start();
        include __DIR__ . '/../Views/admin/pedidos_wp.php';
        $content = ob_get_clean();
        include __DIR__ . '/../Views/layouts/admin.php';
    }

    public function detalhes(Request $request, int $id) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);

        $pedido = null;
        $meta = [];
        $itens = [];
        $erro = '';

        try {
            $localPdo = Database::getConnection();
            $wp = $this->getWpPdo($localPdo);

            $prefix = $wp['prefix'];
            $wpPdo = $wp['pdo'];

            $stP = $wpPdo->prepare("SELECT ID, post_date, post_status, post_title FROM {$prefix}posts WHERE ID = ? AND post_type = 'shop_order' LIMIT 1");
            $stP->execute([(int) $id]);
            $pedido = $stP->fetch(\PDO::FETCH_ASSOC) ?: null;
            if (!$pedido) {
                throw new \RuntimeException('Pedido não encontrado no WordPress');
            }

            $stM = $wpPdo->prepare("SELECT meta_key, meta_value FROM {$prefix}postmeta WHERE post_id = ?");
            $stM->execute([(int) $id]);
            $rowsMeta = $stM->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            foreach ($rowsMeta as $r) {
                $k = (string) ($r['meta_key'] ?? '');
                if ($k === '') continue;
                $meta[$k] = $r['meta_value'] ?? '';
            }

            $stI = $wpPdo->prepare("SELECT order_item_id, order_item_name, order_item_type FROM {$prefix}woocommerce_order_items WHERE order_id = ? ORDER BY order_item_id ASC");
            $stI->execute([(int) $id]);
            $orderItems = $stI->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            $stIM = $wpPdo->prepare("SELECT meta_key, meta_value FROM {$prefix}woocommerce_order_itemmeta WHERE order_item_id = ?");

            foreach ($orderItems as $oi) {
                if (strtolower((string) ($oi['order_item_type'] ?? '')) !== 'line_item') {
                    continue;
                }

                $itemId = (int) ($oi['order_item_id'] ?? 0);
                if ($itemId <= 0) continue;

                $stIM->execute([$itemId]);
                $metaItemRows = $stIM->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                $m = [];
                foreach ($metaItemRows as $mr) {
                    $k = (string) ($mr['meta_key'] ?? '');
                    if ($k === '') continue;
                    $m[$k] = $mr['meta_value'] ?? '';
                }

                $produtoId = (int) ($m['_product_id'] ?? 0);
                $variacaoId = (int) ($m['_variation_id'] ?? 0);
                $qtd = (int) ($m['_qty'] ?? 0);
                $lineTotal = (float) ($m['_line_total'] ?? 0);
                $lineSubtotal = (float) ($m['_line_subtotal'] ?? 0);

                $unit = 0.0;
                if ($qtd > 0) {
                    $unit = round($lineTotal / $qtd, 2);
                }

                $sku = '';
                $ncm = '';

                $prodLookupId = $variacaoId > 0 ? $variacaoId : $produtoId;
                if ($prodLookupId > 0) {
                    $stSku = $wpPdo->prepare("SELECT meta_value FROM {$prefix}postmeta WHERE post_id = ? AND meta_key = '_sku' LIMIT 1");
                    $stSku->execute([(int) $prodLookupId]);
                    $sku = (string) ($stSku->fetchColumn() ?: '');

                    $ncmKeys = ['_ncm', 'ncm', '_woo_ncm', '_product_ncm', '_ncm_code'];
                    foreach ($ncmKeys as $nk) {
                        $stN = $wpPdo->prepare("SELECT meta_value FROM {$prefix}postmeta WHERE post_id = ? AND meta_key = ? LIMIT 1");
                        $stN->execute([(int) $prodLookupId, $nk]);
                        $n = (string) ($stN->fetchColumn() ?: '');
                        if (trim($n) !== '') {
                            $ncm = $n;
                            break;
                        }
                    }
                }

                $itens[] = [
                    'order_item_id' => $itemId,
                    'nome' => (string) ($oi['order_item_name'] ?? ''),
                    'produto_id' => $produtoId,
                    'variacao_id' => $variacaoId,
                    'sku' => $sku,
                    'ncm' => $ncm,
                    'quantidade' => $qtd,
                    'preco_unitario' => $unit,
                    'subtotal' => $lineSubtotal,
                    'total' => $lineTotal,
                ];
            }
        } catch (\Exception $e) {
            $erro = $e->getMessage();
        }

        $sidebarActive = 'pedidos-wp';
        $title = 'Pedido WP - Detalhes - Braziliana Shop Admin';

        ob_start();
        include __DIR__ . '/../Views/admin/pedidos_wp_detalhes.php';
        $content = ob_get_clean();
        include __DIR__ . '/../Views/layouts/admin.php';
    }

    private function getWpPdo(\PDO $localPdo): array {
        $cfg = $this->getWpConfig($localPdo);

        $host = (string) ($cfg['db_host'] ?? '');
        $dbname = (string) ($cfg['db_name'] ?? '');
        $user = (string) ($cfg['db_user'] ?? '');
        $pass = (string) ($cfg['db_pass'] ?? '');
        $prefix = (string) ($cfg['table_prefix'] ?? 'wp_');

        $host = trim($host);
        $dbname = trim($dbname);
        $user = trim($user);
        $prefix = trim($prefix);

        $port = null;
        if ($host !== '' && strpos($host, ':') !== false) {
            $parts = explode(':', $host, 2);
            $host = trim((string) ($parts[0] ?? ''));
            $portPart = trim((string) ($parts[1] ?? ''));
            if ($portPart !== '' && ctype_digit($portPart)) {
                $port = (int) $portPart;
            }
        }

        if ($prefix === '') $prefix = 'wp_';

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

    private function getWpConfig(\PDO $pdo): array {
        $out = ['table_prefix' => 'wp_'];

        // Este projeto normalmente salva configs em configuracoes_sistema.
        // Vamos ler de forma robusta tanto no formato categoria+chave quanto no formato chave/valor.
        try {
            $st = $pdo->prepare("SHOW TABLES LIKE 'configuracoes_sistema'");
            $st->execute();
            $has = (bool) $st->fetchColumn();
            if (!$has) {
                return $out;
            }
        } catch (\Exception $e) {
            return $out;
        }

        $cols = [];
        try {
            $st = $pdo->query('DESCRIBE configuracoes_sistema');
            $cols = $st ? ($st->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];
        } catch (\Exception $e) {
            $cols = [];
        }

        $hasCategoria = in_array('categoria', $cols, true) && in_array('chave', $cols, true) && in_array('valor', $cols, true);
        if ($hasCategoria) {
            try {
                $st = $pdo->prepare('SELECT valor FROM configuracoes_sistema WHERE categoria = ? AND chave = ? LIMIT 1');
                $map = [
                    'db_host' => 'db_host',
                    'db_name' => 'db_name',
                    'db_user' => 'db_user',
                    'db_pass' => 'db_pass',
                    'table_prefix' => 'table_prefix',
                ];
                foreach ($map as $outKey => $chave) {
                    $st->execute(['wordpress', $chave]);
                    $val = $st->fetchColumn();
                    if ($val !== false && $val !== null) {
                        $out[$outKey] = (string) $val;
                    }
                }
                return $out;
            } catch (\Exception $e) {
                // cai para tentativa chave/valor abaixo
            }
        }

        if (in_array('chave', $cols, true) && in_array('valor', $cols, true)) {
            try {
                $st = $pdo->prepare('SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1');
                $map = [
                    'db_host' => 'wordpress_db_host',
                    'db_name' => 'wordpress_db_name',
                    'db_user' => 'wordpress_db_user',
                    'db_pass' => 'wordpress_db_pass',
                    'table_prefix' => 'wordpress_table_prefix',
                ];
                foreach ($map as $outKey => $key) {
                    $st->execute([$key]);
                    $val = $st->fetchColumn();
                    if ($val !== false && $val !== null) {
                        $out[$outKey] = (string) $val;
                    }
                }
            } catch (\Exception $e) {
            }
        }

        // fallback: schema single-row (colunas diretas)
        if (!empty($cols) && in_array('id', $cols, true) && !in_array('chave', $cols, true) && !in_array('categoria', $cols, true)) {
            try {
                $st = $pdo->query('SELECT * FROM configuracoes_sistema ORDER BY id ASC LIMIT 1');
                $row = $st ? ($st->fetch(\PDO::FETCH_ASSOC) ?: []) : [];
                $out['db_host'] = (string) ($row['wordpress_db_host'] ?? '');
                $out['db_name'] = (string) ($row['wordpress_db_name'] ?? '');
                $out['db_user'] = (string) ($row['wordpress_db_user'] ?? '');
                $out['db_pass'] = (string) ($row['wordpress_db_pass'] ?? '');
                $out['table_prefix'] = (string) ($row['wordpress_table_prefix'] ?? 'wp_');
            } catch (\Exception $e) {
            }
        }

        return $out;
    }
}
