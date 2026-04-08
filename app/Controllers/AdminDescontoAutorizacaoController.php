<?php
namespace App\Controllers;

use App\Core\Request;
use App\Core\Services\AuthService;
use Config\Database;

class AdminDescontoAutorizacaoController
{
    private const SENHA_AUTORIZACAO = 'LRV#web#2026';

    private function getEmailsAutorizadores(): array
    {
        $emails = [];
        try {
            $pdo = Database::getConnection();
            $candidates = [
                "SELECT valor FROM configuracoes_sistema WHERE categoria = 'desconto' AND chave = 'emails_autorizadores' LIMIT 1",
                "SELECT valor FROM configuracoes_sistema WHERE chave = 'desconto_emails_autorizadores' LIMIT 1",
                "SELECT valor FROM configuracoes WHERE chave = 'desconto_emails_autorizadores' LIMIT 1",
            ];
            foreach ($candidates as $sql) {
                try {
                    $st = $pdo->query($sql);
                    if ($st) {
                        $v = $st->fetchColumn();
                        if ($v !== false && trim((string) $v) !== '') {
                            $lines = preg_split('/[\r\n,;]+/', (string) $v);
                            foreach ($lines as $line) {
                                $e = trim($line);
                                if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) {
                                    $emails[] = $e;
                                }
                            }
                            if (!empty($emails)) return array_unique($emails);
                        }
                    }
                } catch (\Exception $ex) {
                    continue;
                }
            }
        } catch (\Exception $e) {}
        return $emails;
    }

    private function garantirTabela(\PDO $pdo): void
    {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS `desconto_autorizacoes` (
                `id` INT(11) NOT NULL AUTO_INCREMENT,
                `token` VARCHAR(64) NOT NULL,
                `vendedor_id` INT(11) NOT NULL,
                `vendedor_nome` VARCHAR(191) DEFAULT NULL,
                `produto_id` INT(11) NOT NULL DEFAULT 0,
                `produto_nome` VARCHAR(255) DEFAULT NULL,
                `desconto_tipo` ENUM('percentual','fixo') NOT NULL DEFAULT 'percentual',
                `desconto_valor` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `preco_original` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `preco_final` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
                `moeda` VARCHAR(3) NOT NULL DEFAULT 'USD',
                `status` ENUM('pendente','aprovado','negado','expirado') NOT NULL DEFAULT 'pendente',
                `aprovado_por` VARCHAR(191) DEFAULT NULL,
                `motivo` VARCHAR(500) DEFAULT NULL,
                `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uk_token` (`token`),
                KEY `idx_vendedor` (`vendedor_id`),
                KEY `idx_status` (`status`),
                KEY `idx_created` (`created_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        } catch (\Exception $e) {}
    }

    /** API: vendedor solicita desconto */
    public function solicitar(Request $request)
    {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $auth = new AuthService();
            $auth->requerPerfis(['admin', 'vendedor']);
            $u = $auth->getUsuarioLogado();

            $pdo = Database::getConnection();
            $this->garantirTabela($pdo);

            $produtoId    = (int) $request->getParam('produto_id', 0);
            $produtoNome  = trim((string) $request->getParam('produto_nome', ''));
            $descontoTipo = trim((string) $request->getParam('desconto_tipo', 'percentual'));
            $descontoValor = (float) str_replace(',', '.', (string) $request->getParam('desconto_valor', '0'));
            $precoOriginal = (float) str_replace(',', '.', (string) $request->getParam('preco_original', '0'));
            $moeda         = strtoupper(trim((string) $request->getParam('moeda', 'USD')));

            if (!in_array($descontoTipo, ['percentual', 'fixo'], true)) {
                $descontoTipo = 'percentual';
            }
            if ($descontoValor <= 0) {
                echo json_encode(['ok' => false, 'error' => 'Valor do desconto deve ser maior que zero']);
                exit;
            }
            if ($precoOriginal <= 0) {
                echo json_encode(['ok' => false, 'error' => 'Preço original inválido']);
                exit;
            }

            if ($descontoTipo === 'percentual') {
                if ($descontoValor > 100) $descontoValor = 100;
                $precoFinal = round($precoOriginal * (1 - $descontoValor / 100), 2);
            } else {
                if ($descontoValor > $precoOriginal) $descontoValor = $precoOriginal;
                $precoFinal = round($precoOriginal - $descontoValor, 2);
            }
            if ($precoFinal < 0) $precoFinal = 0;

            $token = bin2hex(random_bytes(32));
            $vendedorId   = (int) ($u['id'] ?? 0);
            $vendedorNome = trim((string) ($u['nome'] ?? ($u['name'] ?? '')));

            $st = $pdo->prepare("INSERT INTO desconto_autorizacoes
                (token, vendedor_id, vendedor_nome, produto_id, produto_nome, desconto_tipo, desconto_valor, preco_original, preco_final, moeda, status)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pendente')");
            $st->execute([$token, $vendedorId, $vendedorNome, $produtoId, $produtoNome, $descontoTipo, $descontoValor, $precoOriginal, $precoFinal, $moeda]);

            // Enviar email para autorizadores
            $this->enviarEmailSolicitacao($token, $vendedorNome, $produtoNome, $descontoTipo, $descontoValor, $precoOriginal, $precoFinal, $moeda);

            echo json_encode(['ok' => true, 'token' => $token, 'preco_final' => $precoFinal]);
        } catch (\Exception $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** API: polling — vendedor verifica status */
    public function verificar(Request $request)
    {
        header('Content-Type: application/json; charset=utf-8');
        try {
            $auth = new AuthService();
            $auth->requerPerfis(['admin', 'vendedor']);

            $token = trim((string) $request->getParam('token', ''));
            if ($token === '') {
                echo json_encode(['ok' => false, 'error' => 'Token inválido']);
                exit;
            }

            $pdo = Database::getConnection();
            $this->garantirTabela($pdo);

            $st = $pdo->prepare("SELECT status, aprovado_por, motivo, preco_final FROM desconto_autorizacoes WHERE token = ? LIMIT 1");
            $st->execute([$token]);
            $row = $st->fetch(\PDO::FETCH_ASSOC);

            if (!$row) {
                echo json_encode(['ok' => false, 'error' => 'Solicitação não encontrada']);
                exit;
            }

            echo json_encode([
                'ok' => true,
                'status' => $row['status'],
                'aprovado_por' => $row['aprovado_por'] ?? '',
                'motivo' => $row['motivo'] ?? '',
                'preco_final' => (float) ($row['preco_final'] ?? 0),
            ]);
        } catch (\Exception $e) {
            echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /** Tela de autorização com senha */
    public function autorizarTela(Request $request)
    {
        $token = trim((string) $request->getParam('token', ''));
        $acao  = trim((string) $request->getParam('acao', ''));

        $pdo = Database::getConnection();
        $this->garantirTabela($pdo);

        $solicitacao = null;
        if ($token !== '') {
            $st = $pdo->prepare("SELECT * FROM desconto_autorizacoes WHERE token = ? LIMIT 1");
            $st->execute([$token]);
            $solicitacao = $st->fetch(\PDO::FETCH_ASSOC) ?: null;
        }

        $mensagem = '';
        $tipo = '';

        // Processar ação via POST
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && $solicitacao && $solicitacao['status'] === 'pendente') {
            $senha = trim((string) $request->getParam('senha', ''));
            if ($senha !== self::SENHA_AUTORIZACAO) {
                $mensagem = 'Senha incorreta.';
                $tipo = 'danger';
            } else {
                if ($acao === 'aprovar') {
                    $st = $pdo->prepare("UPDATE desconto_autorizacoes SET status = 'aprovado', aprovado_por = 'painel', updated_at = NOW() WHERE token = ? AND status = 'pendente'");
                    $st->execute([$token]);
                    $mensagem = 'Desconto aprovado com sucesso.';
                    $tipo = 'success';
                    $solicitacao['status'] = 'aprovado';
                } elseif ($acao === 'negar') {
                    $motivo = trim((string) $request->getParam('motivo', ''));
                    $st = $pdo->prepare("UPDATE desconto_autorizacoes SET status = 'negado', aprovado_por = 'painel', motivo = ?, updated_at = NOW() WHERE token = ? AND status = 'pendente'");
                    $st->execute([$motivo, $token]);
                    $mensagem = 'Desconto negado.';
                    $tipo = 'warning';
                    $solicitacao['status'] = 'negado';
                }
            }
        }

        $this->renderTela($solicitacao, $mensagem, $tipo, $token);
        exit;
    }

    /** Autorizar/negar via link do email (GET com token + ação) */
    public function autorizarEmail(Request $request)
    {
        $token = trim((string) $request->getParam('token', ''));
        $acao  = trim((string) $request->getParam('acao', ''));

        $pdo = Database::getConnection();
        $this->garantirTabela($pdo);

        $solicitacao = null;
        if ($token !== '') {
            $st = $pdo->prepare("SELECT * FROM desconto_autorizacoes WHERE token = ? LIMIT 1");
            $st->execute([$token]);
            $solicitacao = $st->fetch(\PDO::FETCH_ASSOC) ?: null;
        }

        $mensagem = '';
        $tipo = '';

        if ($solicitacao && $solicitacao['status'] === 'pendente') {
            if ($acao === 'aprovar') {
                $st = $pdo->prepare("UPDATE desconto_autorizacoes SET status = 'aprovado', aprovado_por = 'email', updated_at = NOW() WHERE token = ? AND status = 'pendente'");
                $st->execute([$token]);
                $mensagem = 'Desconto aprovado com sucesso via email.';
                $tipo = 'success';
                $solicitacao['status'] = 'aprovado';
            } elseif ($acao === 'negar') {
                $st = $pdo->prepare("UPDATE desconto_autorizacoes SET status = 'negado', aprovado_por = 'email', updated_at = NOW() WHERE token = ? AND status = 'pendente'");
                $st->execute([$token]);
                $mensagem = 'Desconto negado via email.';
                $tipo = 'warning';
                $solicitacao['status'] = 'negado';
            }
        } elseif ($solicitacao) {
            $mensagem = 'Esta solicitação já foi ' . ($solicitacao['status'] === 'aprovado' ? 'aprovada' : 'negada') . '.';
            $tipo = 'info';
        } else {
            $mensagem = 'Solicitação não encontrada.';
            $tipo = 'danger';
        }

        $this->renderTela($solicitacao, $mensagem, $tipo, $token);
        exit;
    }

    /** Configuração dos emails autorizadores (admin) */
    public function configuracao(Request $request)
    {
        $auth = new AuthService();
        $auth->requerPerfil('admin');

        $pdo = Database::getConnection();
        $mensagem = '';
        $tipo = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $emails = trim((string) $request->getParam('emails_autorizadores', ''));
            try {
                // Tentar salvar no formato categoria/chave
                $saved = false;
                try {
                    $st = $pdo->prepare("SELECT COUNT(*) FROM configuracoes_sistema WHERE categoria = 'desconto' AND chave = 'emails_autorizadores'");
                    $st->execute();
                    if ((int) $st->fetchColumn() > 0) {
                        $st = $pdo->prepare("UPDATE configuracoes_sistema SET valor = ?, updated_at = NOW() WHERE categoria = 'desconto' AND chave = 'emails_autorizadores'");
                        $st->execute([$emails]);
                    } else {
                        $st = $pdo->prepare("INSERT INTO configuracoes_sistema (categoria, chave, valor, updated_at) VALUES ('desconto', 'emails_autorizadores', ?, NOW())");
                        $st->execute([$emails]);
                    }
                    $saved = true;
                } catch (\Exception $e) {}

                if (!$saved) {
                    try {
                        $st = $pdo->prepare("SELECT COUNT(*) FROM configuracoes_sistema WHERE chave = 'desconto_emails_autorizadores'");
                        $st->execute();
                        if ((int) $st->fetchColumn() > 0) {
                            $st = $pdo->prepare("UPDATE configuracoes_sistema SET valor = ? WHERE chave = 'desconto_emails_autorizadores'");
                            $st->execute([$emails]);
                        } else {
                            $st = $pdo->prepare("INSERT INTO configuracoes_sistema (chave, valor) VALUES ('desconto_emails_autorizadores', ?)");
                            $st->execute([$emails]);
                        }
                    } catch (\Exception $e) {}
                }

                $mensagem = 'Emails salvos com sucesso.';
                $tipo = 'success';
            } catch (\Exception $e) {
                $mensagem = 'Erro ao salvar: ' . $e->getMessage();
                $tipo = 'danger';
            }
        }

        $emailsAtuais = implode("\n", $this->getEmailsAutorizadores());

        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';
        echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
        <title>Configuração de Desconto - Admin</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">';
        renderAdminSidebarStyles();
        echo '</head><body><div class="container-fluid"><div class="row">';
        renderAdminSidebar('configuracoes');
        echo '<main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="pt-3 pb-2 mb-3 border-bottom"><h1 class="h2">Configuração de Desconto com Autorização</h1></div>';
        if ($mensagem) echo '<div class="alert alert-' . $tipo . '">' . htmlspecialchars($mensagem) . '</div>';
        echo '<form method="POST"><div class="card"><div class="card-body">
            <div class="mb-3">
                <label class="form-label">Emails autorizadores (um por linha)</label>
                <textarea class="form-control" name="emails_autorizadores" rows="5" placeholder="admin@exemplo.com">' . htmlspecialchars($emailsAtuais) . '</textarea>
                <div class="form-text">Esses emails receberão solicitações de desconto com botões para aprovar/negar.</div>
            </div>
            <button type="submit" class="btn btn-primary"><i class="fas fa-save"></i> Salvar</button>
        </div></div></form></main></div></div></body></html>';
        exit;
    }

    private function enviarEmailSolicitacao(string $token, string $vendedor, string $produto, string $tipo, float $valor, float $original, float $final, string $moeda): void
    {
        $emails = $this->getEmailsAutorizadores();
        if (empty($emails)) return;

        try {
            $baseUrl = rtrim((string) ($_SERVER['REQUEST_SCHEME'] ?? 'https') . '://' . ($_SERVER['HTTP_HOST'] ?? ''), '/');
            $urlAprovar = $baseUrl . '/admin/desconto/email-autorizar?token=' . urlencode($token) . '&acao=aprovar';
            $urlNegar   = $baseUrl . '/admin/desconto/email-autorizar?token=' . urlencode($token) . '&acao=negar';
            $urlPainel  = $baseUrl . '/admin/desconto/autorizar?token=' . urlencode($token);

            $sym = $moeda === 'BRL' ? 'R$' : '$';
            $descontoLabel = $tipo === 'percentual' ? number_format($valor, 2, ',', '.') . '%' : $sym . ' ' . number_format($valor, 2, ',', '.');

            $subject = 'Solicitação de Desconto - ' . $produto;
            $html = '<div style="font-family:Arial,sans-serif;max-width:600px;margin:0 auto;padding:20px;">
                <h2 style="color:#333;">Solicitação de Desconto</h2>
                <p>O vendedor <strong>' . htmlspecialchars($vendedor) . '</strong> está solicitando autorização para aplicar um desconto:</p>
                <table style="width:100%;border-collapse:collapse;margin:15px 0;">
                    <tr><td style="padding:8px;border:1px solid #ddd;background:#f8f9fa;"><strong>Produto</strong></td><td style="padding:8px;border:1px solid #ddd;">' . htmlspecialchars($produto) . '</td></tr>
                    <tr><td style="padding:8px;border:1px solid #ddd;background:#f8f9fa;"><strong>Desconto</strong></td><td style="padding:8px;border:1px solid #ddd;">' . $descontoLabel . ' (' . $tipo . ')</td></tr>
                    <tr><td style="padding:8px;border:1px solid #ddd;background:#f8f9fa;"><strong>Preço original</strong></td><td style="padding:8px;border:1px solid #ddd;">' . $sym . ' ' . number_format($original, 2, ',', '.') . '</td></tr>
                    <tr><td style="padding:8px;border:1px solid #ddd;background:#f8f9fa;"><strong>Preço com desconto</strong></td><td style="padding:8px;border:1px solid #ddd;color:#28a745;font-weight:bold;">' . $sym . ' ' . number_format($final, 2, ',', '.') . '</td></tr>
                </table>
                <div style="margin:20px 0;text-align:center;">
                    <a href="' . htmlspecialchars($urlAprovar) . '" style="display:inline-block;padding:12px 30px;background:#28a745;color:#fff;text-decoration:none;border-radius:5px;margin:5px;font-weight:bold;">✅ Aprovar</a>
                    <a href="' . htmlspecialchars($urlNegar) . '" style="display:inline-block;padding:12px 30px;background:#dc3545;color:#fff;text-decoration:none;border-radius:5px;margin:5px;font-weight:bold;">❌ Negar</a>
                </div>
                <p style="color:#666;font-size:12px;">Ou acesse o painel: <a href="' . htmlspecialchars($urlPainel) . '">' . htmlspecialchars($urlPainel) . '</a></p>
            </div>';

            $emailService = new \App\Services\EmailService();
            foreach ($emails as $to) {
                $emailService->send($to, $subject, $html, 'desconto:' . $token . ':' . strtolower($to));
            }
        } catch (\Exception $e) {
            error_log('[DESCONTO] Falha ao enviar email: ' . $e->getMessage());
        }
    }

    private function renderTela(?array $sol, string $msg, string $tipo, string $token): void
    {
        $h = fn($v) => htmlspecialchars((string) ($v ?? ''), ENT_QUOTES, 'UTF-8');
        echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
        <title>Autorização de Desconto</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        </head><body class="bg-light"><div class="container py-5"><div class="row justify-content-center"><div class="col-md-6">';

        if ($msg) echo '<div class="alert alert-' . $tipo . '">' . $h($msg) . '</div>';

        if (!$sol) {
            echo '<div class="card"><div class="card-body text-center"><p class="text-muted">Solicitação não encontrada ou token inválido.</p></div></div>';
        } else {
            $sym = ($sol['moeda'] ?? 'USD') === 'BRL' ? 'R$' : '$';
            $statusBadge = match ($sol['status']) {
                'aprovado' => '<span class="badge bg-success">Aprovado</span>',
                'negado'   => '<span class="badge bg-danger">Negado</span>',
                'expirado' => '<span class="badge bg-secondary">Expirado</span>',
                default    => '<span class="badge bg-warning text-dark">Pendente</span>',
            };

            echo '<div class="card"><div class="card-header"><strong>Solicitação de Desconto</strong> ' . $statusBadge . '</div><div class="card-body">
                <table class="table table-sm mb-0">
                    <tr><th>Vendedor</th><td>' . $h($sol['vendedor_nome']) . '</td></tr>
                    <tr><th>Produto</th><td>' . $h($sol['produto_nome']) . '</td></tr>
                    <tr><th>Desconto</th><td>' . ($sol['desconto_tipo'] === 'percentual' ? number_format((float) $sol['desconto_valor'], 2, ',', '.') . '%' : $sym . ' ' . number_format((float) $sol['desconto_valor'], 2, ',', '.')) . ' (' . $h($sol['desconto_tipo']) . ')</td></tr>
                    <tr><th>Preço original</th><td>' . $sym . ' ' . number_format((float) $sol['preco_original'], 2, ',', '.') . '</td></tr>
                    <tr><th>Preço final</th><td class="text-success fw-bold">' . $sym . ' ' . number_format((float) $sol['preco_final'], 2, ',', '.') . '</td></tr>
                    <tr><th>Data</th><td>' . $h($sol['created_at']) . '</td></tr>
                </table></div>';

            if ($sol['status'] === 'pendente') {
                echo '<div class="card-footer">
                    <form method="POST" action="/admin/desconto/autorizar?token=' . $h($token) . '">
                        <div class="mb-3">
                            <label class="form-label fw-bold">Senha de autorização</label>
                            <input type="password" class="form-control" name="senha" required placeholder="Digite a senha...">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Motivo (opcional, para negação)</label>
                            <input type="text" class="form-control" name="motivo" placeholder="Ex: desconto muito alto">
                        </div>
                        <div class="d-flex gap-2">
                            <button type="submit" name="acao" value="aprovar" class="btn btn-success"><i class="fas fa-check"></i> Aprovar</button>
                            <button type="submit" name="acao" value="negar" class="btn btn-danger"><i class="fas fa-times"></i> Negar</button>
                        </div>
                    </form>
                </div>';
            }
            echo '</div>';
        }

        echo '</div></div></div></body></html>';
    }
}
