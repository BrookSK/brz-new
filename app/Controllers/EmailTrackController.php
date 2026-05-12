<?php
namespace App\Controllers;

use App\Core\Request;
use Config\Database;

/**
 * Email Tracking Controller
 * Handles open tracking (pixel), click tracking (redirect), and conversion checking
 */
class EmailTrackController extends Controller {

    /**
     * OPEN TRACKING - 1x1 transparent pixel
     * URL: /email-track/open/{hash}
     */
    public function open(Request $request) {
        $hash = trim((string)$request->getParam('hash', ''));
        if ($hash === '') { $this->servePixel(); return; }

        try {
            $pdo = Database::getConnection();
            
            // Find the tracking record
            $st = $pdo->prepare("SELECT id, campanha_id, cliente_id FROM email_mkt_tracking WHERE hash = ? AND tipo = 'open' LIMIT 1");
            $st->execute([$hash]);
            $track = $st->fetch(\PDO::FETCH_ASSOC);

            if ($track) {
                $campId = (int)$track['campanha_id'];
                $clienteId = (int)$track['cliente_id'];

                // Update client status to 'aberto' (only if not already clicked/converted)
                $pdo->prepare("UPDATE email_mkt_campanha_clientes SET status = 'aberto', data_abertura = COALESCE(data_abertura, NOW()) WHERE campanha_id = ? AND cliente_id = ? AND status IN ('enviado','entregue')")->execute([$campId, $clienteId]);

                // Log the event
                $pdo->prepare("INSERT INTO email_mkt_logs (campanha_id, cliente_id, evento, detalhes) VALUES (?, ?, 'aberto', ?)")->execute([$campId, $clienteId, 'IP: ' . ($_SERVER['REMOTE_ADDR'] ?? '')]);

                // Update campaign totals
                $total = (int)$pdo->prepare("SELECT COUNT(*) FROM email_mkt_campanha_clientes WHERE campanha_id = ? AND status IN ('aberto','clicado','convertido')")->execute([$campId]) ? $pdo->query("SELECT FOUND_ROWS()")->fetchColumn() : 0;
                $st2 = $pdo->prepare("SELECT COUNT(*) FROM email_mkt_campanha_clientes WHERE campanha_id = ? AND data_abertura IS NOT NULL");
                $st2->execute([$campId]);
                $totalAberto = (int)$st2->fetchColumn();
                $pdo->prepare("UPDATE email_mkt_campanhas SET total_aberto = ? WHERE id = ?")->execute([$totalAberto, $campId]);
            }
        } catch (\Exception $e) {}

        $this->servePixel();
    }

    /**
     * CLICK TRACKING - Redirect through tracking
     * URL: /email-track/click/{hash}
     */
    public function click(Request $request) {
        $hash = trim((string)$request->getParam('hash', ''));
        $destUrl = trim((string)($_GET['url'] ?? ''));

        if ($hash !== '') {
            try {
                $pdo = Database::getConnection();

                $st = $pdo->prepare("SELECT id, campanha_id, cliente_id, url_destino FROM email_mkt_tracking WHERE hash = ? AND tipo = 'click' LIMIT 1");
                $st->execute([$hash]);
                $track = $st->fetch(\PDO::FETCH_ASSOC);

                if ($track) {
                    $campId = (int)$track['campanha_id'];
                    $clienteId = (int)$track['cliente_id'];
                    if ($destUrl === '') $destUrl = $track['url_destino'] ?? '';

                    // Update client status to 'clicado'
                    $pdo->prepare("UPDATE email_mkt_campanha_clientes SET status = 'clicado', data_clique = COALESCE(data_clique, NOW()), data_abertura = COALESCE(data_abertura, NOW()) WHERE campanha_id = ? AND cliente_id = ? AND status IN ('enviado','entregue','aberto')")->execute([$campId, $clienteId]);

                    // Log
                    $pdo->prepare("INSERT INTO email_mkt_logs (campanha_id, cliente_id, evento, detalhes) VALUES (?, ?, 'clicado', ?)")->execute([$campId, $clienteId, 'URL: ' . $destUrl]);

                    // Update totals
                    $st2 = $pdo->prepare("SELECT COUNT(*) FROM email_mkt_campanha_clientes WHERE campanha_id = ? AND data_clique IS NOT NULL");
                    $st2->execute([$campId]);
                    $totalClicado = (int)$st2->fetchColumn();
                    $pdo->prepare("UPDATE email_mkt_campanhas SET total_clicado = ? WHERE id = ?")->execute([$totalClicado, $campId]);
                }
            } catch (\Exception $e) {}
        }

        // Redirect to destination
        if ($destUrl === '' || $destUrl === '#') $destUrl = 'https://brazilianashop.com.br';
        header('Location: ' . $destUrl, true, 302);
        exit;
    }

    /**
     * CONVERSION CHECK - Called by cron
     * Checks if clients who clicked made a purchase within 7 days
     * URL: /email-track/check-conversions?token=...
     */
    public function checkConversions(Request $request) {
        $token = trim((string)$request->getParam('token', ''));
        if ($token !== 'padrao123456' && $token !== 'email_mkt_conv') {
            http_response_code(403);
            echo 'Forbidden';
            return;
        }

        header('Content-Type: application/json; charset=UTF-8');
        $pdo = Database::getConnection();
        $conversoes = 0;

        try {
            // Find clients who clicked but haven't been marked as converted
            // AND who made a purchase within 7 days of clicking
            $sql = "SELECT cc.id, cc.campanha_id, cc.cliente_id, cc.data_clique
                    FROM email_mkt_campanha_clientes cc
                    WHERE cc.status = 'clicado'
                    AND cc.data_clique IS NOT NULL
                    AND cc.data_clique > DATE_SUB(NOW(), INTERVAL 7 DAY)";
            $clicados = $pdo->query($sql)->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            foreach ($clicados as $cl) {
                $clienteId = (int)$cl['cliente_id'];
                $dataClique = $cl['data_clique'];

                // Check if this client made a paid order after the click
                $stPedido = $pdo->prepare("SELECT COUNT(*) FROM pedidos WHERE usuario_id = ? AND status IN ('pago','entregue','enviado') AND created_at > ? AND created_at <= DATE_ADD(?, INTERVAL 7 DAY)");
                $stPedido->execute([$clienteId, $dataClique, $dataClique]);
                $temPedido = (int)$stPedido->fetchColumn();

                if ($temPedido > 0) {
                    $campId = (int)$cl['campanha_id'];
                    $pdo->prepare("UPDATE email_mkt_campanha_clientes SET status = 'convertido', data_conversao = NOW() WHERE id = ?")->execute([(int)$cl['id']]);
                    $pdo->prepare("INSERT INTO email_mkt_logs (campanha_id, cliente_id, evento, detalhes) VALUES (?, ?, 'convertido', 'Pedido pago após clique')")->execute([$campId, $clienteId]);

                    // Update totals
                    $st2 = $pdo->prepare("SELECT COUNT(*) FROM email_mkt_campanha_clientes WHERE campanha_id = ? AND status = 'convertido'");
                    $st2->execute([$campId]);
                    $totalConv = (int)$st2->fetchColumn();
                    $pdo->prepare("UPDATE email_mkt_campanhas SET total_convertido = ? WHERE id = ?")->execute([$totalConv, $campId]);
                    $conversoes++;
                }
            }
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            return;
        }

        echo json_encode(['success' => true, 'conversoes' => $conversoes]);
    }

    private function servePixel(): void {
        header('Content-Type: image/gif');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('Pragma: no-cache');
        // 1x1 transparent GIF
        echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
        exit;
    }
}
