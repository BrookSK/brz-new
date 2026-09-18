<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;
use Config\Database;

/**
 * Controller auxiliar para download de etiqueta PDF dos pacotes WordPress.
 * Criado como arquivo separado para contornar OPcache persistente.
 */
class AdminPacotesWpController2 extends Controller {

    public function etiquetaPdf(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'suporte']);

        $id = (int) $request->getParam('id');
        if ($id <= 0 && isset($_GET['id'])) {
            $id = (int) $_GET['id'];
        }
        if ($id <= 0) {
            header('Content-Type: text/plain; charset=utf-8');
            http_response_code(400);
            echo __('admin.wp_packages2.invalid_label_id', 'ID de etiqueta inválido.');
            exit;
        }

        $pdo = Database::getConnection();

        // Garantir tabela existe
        try {
            $st = $pdo->prepare('SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?');
            $st->execute(['wp_packet_etiquetas']);
            if (((int) $st->fetchColumn()) === 0) {
                header('Content-Type: text/plain; charset=utf-8');
                http_response_code(404);
                echo __('admin.wp_packages2.table_missing', 'Tabela wp_packet_etiquetas não existe. Sincronize os pacotes primeiro.');
                exit;
            }
        } catch (\Exception $e) {
            header('Content-Type: text/plain; charset=utf-8');
            http_response_code(500);
            echo __('admin.wp_packages2.table_check_error', 'Erro ao verificar tabela: ') . $e->getMessage();
            exit;
        }

        $st = $pdo->prepare("SELECT * FROM wp_packet_etiquetas WHERE id = ? LIMIT 1");
        $st->execute([$id]);
        $etiqueta = $st->fetch(\PDO::FETCH_ASSOC);

        if (!$etiqueta) {
            header('Content-Type: text/plain; charset=utf-8');
            http_response_code(404);
            echo __('admin.wp_packages2.label_not_found', 'Etiqueta #{id} não encontrada.', ['id' => $id]);
            exit;
        }

        $origem = (string) ($etiqueta['origem'] ?? 'br');
        $wpPostId = (int) ($etiqueta['wp_post_id'] ?? 0);
        $tracking = (string) ($etiqueta['tracking_number'] ?? '');

        if ($tracking === '') {
            header('Content-Type: text/plain; charset=utf-8');
            http_response_code(404);
            echo __('admin.wp_packages2.label_no_tracking', 'Etiqueta #{id} não possui tracking number.', ['id' => $id]);
            exit;
        }

        // Conectar ao WordPress e buscar o PDF
        try {
            $wpConn = $this->getWpConnection($pdo, $origem);
            $wpPdo = $wpConn['pdo'];
            $prefix = $wpConn['prefix'];

            // Buscar _label_data
            $stLabel = $wpPdo->prepare("SELECT meta_value FROM {$prefix}postmeta WHERE post_id = ? AND meta_key = '_label_data' LIMIT 1");
            $stLabel->execute([$wpPostId]);
            $labelData = (string) ($stLabel->fetchColumn() ?: '');

            if ($labelData !== '') {
                $pdfContent = base64_decode($labelData);
                if ($pdfContent !== false && strlen($pdfContent) > 100) {
                    header('Content-Type: application/pdf');
                    header('Content-Disposition: inline; filename="etiqueta-' . $tracking . '.pdf"');
                    header('Content-Length: ' . strlen($pdfContent));
                    echo $pdfContent;
                    exit;
                }
            }

            // Tentar _correios_label_data
            $stLabel2 = $wpPdo->prepare("SELECT meta_value FROM {$prefix}postmeta WHERE post_id = ? AND meta_key = '_correios_label_data' LIMIT 1");
            $stLabel2->execute([$wpPostId]);
            $labelData2 = (string) ($stLabel2->fetchColumn() ?: '');

            if ($labelData2 !== '') {
                $pdfContent = base64_decode($labelData2);
                if ($pdfContent !== false && strlen($pdfContent) > 100) {
                    header('Content-Type: application/pdf');
                    header('Content-Disposition: inline; filename="etiqueta-' . $tracking . '.pdf"');
                    header('Content-Length: ' . strlen($pdfContent));
                    echo $pdfContent;
                    exit;
                }
            }
        } catch (\Exception $e) {
            // Falha ao conectar no WP
        }

        // Fallback: meta_json local
        $meta = json_decode((string) ($etiqueta['meta_json'] ?? '{}'), true) ?: [];
        $labelData = $meta['_label_data'] ?? ($meta['_correios_label_data'] ?? '');

        if ($labelData !== '') {
            $pdfContent = base64_decode($labelData);
            if ($pdfContent !== false && strlen($pdfContent) > 100) {
                header('Content-Type: application/pdf');
                header('Content-Disposition: inline; filename="etiqueta-' . $tracking . '.pdf"');
                header('Content-Length: ' . strlen($pdfContent));
                echo $pdfContent;
                exit;
            }
        }

        header('Content-Type: text/plain; charset=utf-8');
        http_response_code(404);
        echo __('admin.wp_packages2.pdf_unavailable', 'PDF não disponível para tracking {tracking}. Verifique se a etiqueta foi gerada no WordPress.', ['tracking' => $tracking]);
        exit;
    }

    private function getWpConnection(\PDO $pdo, string $source): array {
        $source = strtolower(trim($source));
        if (!in_array($source, ['br', 'red', 'us'], true)) {
            $source = 'br';
        }

        $cat = 'wordpress_' . $source;
        $out = ['table_prefix' => 'wp_'];

        $cols = [];
        $st = $pdo->query('DESCRIBE configuracoes_sistema');
        $cols = $st ? ($st->fetchAll(\PDO::FETCH_COLUMN) ?: []) : [];

        $hasCategoria = in_array('categoria', $cols, true) && in_array('chave', $cols, true) && in_array('valor', $cols, true);

        if ($hasCategoria) {
            $st = $pdo->prepare('SELECT valor FROM configuracoes_sistema WHERE categoria = ? AND chave = ? LIMIT 1');
            foreach (['db_host', 'db_name', 'db_user', 'db_pass', 'table_prefix'] as $k) {
                $st->execute([$cat, $k]);
                $v = $st->fetchColumn();
                if ($v !== false && $v !== null) {
                    $out[$k] = (string) $v;
                } elseif ($source === 'br') {
                    $st->execute(['wordpress', $k]);
                    $v2 = $st->fetchColumn();
                    if ($v2 !== false && $v2 !== null) {
                        $out[$k] = (string) $v2;
                    }
                }
            }
        } else {
            $st = $pdo->prepare('SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1');
            foreach (['db_host', 'db_name', 'db_user', 'db_pass', 'table_prefix'] as $k) {
                $st->execute([$cat . '_' . $k]);
                $v = $st->fetchColumn();
                if ($v !== false && $v !== null) {
                    $out[$k] = (string) $v;
                } elseif ($source === 'br') {
                    $st->execute(['wordpress_' . $k]);
                    $v2 = $st->fetchColumn();
                    if ($v2 !== false && $v2 !== null) {
                        $out[$k] = (string) $v2;
                    }
                }
            }
        }

        $host = trim((string) ($out['db_host'] ?? ''));
        $dbname = trim((string) ($out['db_name'] ?? ''));
        $user = trim((string) ($out['db_user'] ?? ''));
        $pass = (string) ($out['db_pass'] ?? '');
        $prefix = trim((string) ($out['table_prefix'] ?? 'wp_'));
        if ($prefix === '') $prefix = 'wp_';

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
            throw new \RuntimeException("WordPress ({$source}) não configurado.");
        }

        $dsn = 'mysql:host=' . $host . ';' . ($port ? ('port=' . $port . ';') : '') . 'dbname=' . $dbname . ';charset=utf8mb4';
        $wpPdo = new \PDO($dsn, $user, $pass, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);

        return ['pdo' => $wpPdo, 'prefix' => $prefix];
    }
}
