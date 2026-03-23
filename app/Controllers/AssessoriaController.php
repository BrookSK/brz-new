<?php
namespace App\Controllers;

use App\Core\Request;
use App\Models\Produto;
use App\Services\AuthService;
use App\Models\Usuario;
use App\Models\AssessoriaOrcamento;

class AssessoriaController extends Controller {

    private function slugify(string $value): string {
        $value = trim(mb_strtolower($value));
        $value = preg_replace('/[\s\_]+/u', '-', $value);
        $value = preg_replace('/[^a-z0-9\-]/', '', $value);
        $value = preg_replace('/\-+/', '-', $value);
        return trim($value, '-');
    }

    private function getOrCreateCategoriaAssessoriaId(): ?int {
        try {
            $db = \Config\Database::getConnection();

            $cols = [];
            try {
                $stmtCols = $db->query('DESCRIBE categorias');
                $cols = $stmtCols ? $stmtCols->fetchAll(\PDO::FETCH_COLUMN) : [];
            } catch (\Exception $e) {
                $cols = [];
            }

            $nameCol = null;
            foreach (['name', 'nome'] as $c) {
                if (in_array($c, $cols, true)) {
                    $nameCol = $c;
                    break;
                }
            }
            if ($nameCol === null) {
                return null;
            }

            $stmtFind = $db->prepare('SELECT id FROM categorias WHERE ' . $nameCol . ' = ? LIMIT 1');
            $stmtFind->execute(['Assessoria']);
            $existing = $stmtFind->fetchColumn();
            if ($existing) {
                return (int) $existing;
            }

            $fields = [$nameCol];
            $values = ['Assessoria'];
            if (in_array('slug', $cols, true)) {
                $fields[] = 'slug';
                $values[] = 'assessoria';
            }
            if (in_array('status', $cols, true)) {
                $fields[] = 'status';
                $values[] = 'ativo';
            }
            if (in_array('descricao', $cols, true)) {
                $fields[] = 'descricao';
                $values[] = 'Produtos gerados pela Assessoria';
            }
            if (in_array('created_at', $cols, true)) {
                $fields[] = 'created_at';
                $values[] = date('Y-m-d H:i:s');
            }
            if (in_array('updated_at', $cols, true)) {
                $fields[] = 'updated_at';
                $values[] = date('Y-m-d H:i:s');
            }

            $ph = rtrim(str_repeat('?,', count($fields)), ',');
            $sqlIns = 'INSERT INTO categorias (' . implode(', ', $fields) . ') VALUES (' . $ph . ')';
            $stmtIns = $db->prepare($sqlIns);
            $stmtIns->execute($values);
            $id = (int) $db->lastInsertId();
            return $id > 0 ? $id : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function ensureLojaFromUrl(?string $url): ?string {
        $url = trim((string) ($url ?? ''));
        if ($url === '') {
            return null;
        }

        $host = '';
        try {
            $parts = parse_url($url);
            if (is_array($parts) && !empty($parts['host'])) {
                $host = (string) $parts['host'];
            }
        } catch (\Exception $e) {
            $host = '';
        }

        $host = strtolower(trim($host));
        if ($host === '') {
            return null;
        }
        $host = preg_replace('/^www\./i', '', $host);

        $nome = $host;
        $slug = $this->slugify($host);
        if ($slug === '') {
            return null;
        }

        try {
            $db = \Config\Database::getConnection();

            try {
                $stmtFind = $db->prepare('SELECT id FROM lojas WHERE slug = ? LIMIT 1');
                $stmtFind->execute([$slug]);
                $existing = $stmtFind->fetchColumn();
                if ($existing) {
                    return $nome;
                }
            } catch (\Exception $e) {
            }

            try {
                $stmtIns = $db->prepare('INSERT INTO lojas (nome, slug, ativo, created_at) VALUES (?, ?, 1, NOW())');
                $stmtIns->execute([$nome, $slug]);
            } catch (\Exception $e) {
            }

            return $nome;
        } catch (\Exception $e) {
            return $nome;
        }
    }

    private function getConfigValue(string $chave, $default = null) {
        try {
            $db = \Config\Database::getConnection();
            $stmt = $db->prepare('SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1');
            $stmt->execute([$chave]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row && array_key_exists('valor', $row)) {
                return $row['valor'];
            }
        } catch (\Exception $e) {
        }
        return $default;
    }

    private function enviarWebhookAssessoria(string $tipo, array $payload): void {
        $url = '';
        if ($tipo === 'inicio') {
            $url = (string) $this->getConfigValue('assessoria_webhook_inicio_url', '');
        } elseif ($tipo === 'conclusao') {
            $url = (string) $this->getConfigValue('assessoria_webhook_conclusao_url', '');
        }
        $url = trim($url);
        if ($url === '' || !filter_var($url, FILTER_VALIDATE_URL)) {
            return;
        }

        $body = json_encode($payload);
        if ($body === false) {
            return;
        }

        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'User-Agent: brz-new/1.0']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_exec($ch);
            curl_close($ch);
            return;
        }

        @file_get_contents($url, false, stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/json\r\nUser-Agent: brz-new/1.0",
                'content' => $body,
                'timeout' => 15,
                'ignore_errors' => true,
            ]
        ]));
    }

    private function parseDbJson(?string $json): array {
        $json = (string) ($json ?? '');
        if (trim($json) === '') {
            return [];
        }
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function isOlderThanMinutes(?string $dt, int $minutes): bool {
        $dt = (string) ($dt ?? '');
        if ($dt === '') {
            return false;
        }
        try {
            $ts = strtotime($dt);
            if (!$ts) {
                return false;
            }
            return (time() - $ts) > ($minutes * 60);
        } catch (\Exception $e) {
            return false;
        }
    }

    public function reprocessarOrcamento(Request $request) {
        session_start();
        $orcamentoId = (int) $request->getParam('orcamento_id', 0);

        if ($orcamentoId <= 0) {
            $_SESSION['message'] = 'Orçamento inválido.';
            $_SESSION['message_type'] = 'warning';
            $this->redirect('/minha-conta');
            return;
        }

        try {
            $orcModel = new AssessoriaOrcamento();
            $row = $orcModel->find($orcamentoId);
            if (!is_array($row) || empty($row['id'])) {
                $_SESSION['message'] = 'Orçamento não encontrado.';
                $_SESSION['message_type'] = 'warning';
                $this->redirect('/minha-conta');
                return;
            }

            // Se já é pago, apenas abrir
            if (($row['status'] ?? '') === 'pago') {
                $this->redirect('/assessoria/orcamento?orcamento_id=' . $orcamentoId);
                return;
            }

            $links = $this->parseDbJson($row['links_json'] ?? null);
            if (empty($links)) {
                $_SESSION['message'] = 'Este orçamento não possui links para reprocessar.';
                $_SESSION['message_type'] = 'warning';
                $this->redirect('/assessoria/orcamento?orcamento_id=' . $orcamentoId);
                return;
            }

            // Não processar aqui. Apenas pré-preencher a tela original (/assessoria)
            // para o usuário clicar em "Gerar Orçamento" (rota original) e garantir efetividade.
            $_SESSION['assessoria_prefill_links'] = array_values($links);
            $_SESSION['assessoria_prefill_from_orcamento_id'] = $orcamentoId;

            // Evitar travar a aplicação por lock de sessão
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }

            $this->redirect('/assessoria');
            return;
        } catch (\Exception $e) {
            $_SESSION['message'] = 'Erro ao reprocessar orçamento: ' . $e->getMessage();
            $_SESSION['message_type'] = 'warning';
            $this->redirect('/assessoria/orcamento?orcamento_id=' . $orcamentoId);
            return;
        }
    }

    public function cronLimparTemporarios(Request $request) {
        header('Content-Type: application/json');

        $limitMinutes = 15;
        $out = [
            'success' => true,
            'limit_minutes' => $limitMinutes,
            'deleted_produtos' => 0,
            'deleted_produto_fotos' => 0,
            'deleted_carrinho_itens' => 0,
            'archived_produtos' => 0,
            'expired_orcamentos' => 0,
            'errors' => []
        ];

        try {
            $db = \Config\Database::getConnection();

            // Descobrir tabela de itens do pedido (pedido_itens vs pedido_items)
            $itensTable = null;
            try {
                $stmtT = $db->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
                $stmtT->execute(['pedido_itens']);
                if ((int) $stmtT->fetchColumn() > 0) {
                    $itensTable = 'pedido_itens';
                } else {
                    $stmtT->execute(['pedido_items']);
                    if ((int) $stmtT->fetchColumn() > 0) {
                        $itensTable = 'pedido_items';
                    }
                }
            } catch (\Exception $e) {
                $itensTable = null;
            }

            // Descobrir tabela de itens do carrinho (carrinho_itens vs carrinho_items)
            $carrinhoItensTable = null;
            try {
                $stmtCT = $db->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
                $stmtCT->execute(['carrinho_itens']);
                if ((int) $stmtCT->fetchColumn() > 0) {
                    $carrinhoItensTable = 'carrinho_itens';
                } else {
                    $stmtCT->execute(['carrinho_items']);
                    if ((int) $stmtCT->fetchColumn() > 0) {
                        $carrinhoItensTable = 'carrinho_items';
                    }
                }
            } catch (\Exception $e) {
                $carrinhoItensTable = null;
            }

            // Buscar produtos temporários vencidos
            $stmt = $db->prepare("\n                SELECT id\n                FROM produtos\n                WHERE (sku LIKE 'ASS-%' OR attributes LIKE '%\\\"fonte\\\":\\\"assessoria\\\"%')\n                AND COALESCE(created_at, updated_at, NOW()) < DATE_SUB(NOW(), INTERVAL {$limitMinutes} MINUTE)\n                LIMIT 200\n            ");
            $stmt->execute();
            $produtoIds = $stmt->fetchAll(\PDO::FETCH_COLUMN) ?: [];

            foreach ($produtoIds as $pidRaw) {
                $pid = (int) $pidRaw;
                if ($pid <= 0) {
                    continue;
                }

                $db->beginTransaction();

                // Se já virou compra concluída, não remover
                $isPaid = false;
                if ($itensTable !== null) {
                    try {
                        $sqlPaid = "SELECT 1 FROM {$itensTable} i INNER JOIN pedidos p ON p.id = i.pedido_id WHERE i.produto_id = ? AND p.status IN ('pago','paid','aprovado','approved','enviado','entregue') LIMIT 1";
                        $stPaid = $db->prepare($sqlPaid);
                        $stPaid->execute([$pid]);
                        $isPaid = $stPaid->fetchColumn() ? true : false;
                    } catch (\Exception $e) {
                        $isPaid = false;
                    }
                }
                if ($isPaid) {
                    $db->rollBack();
                    continue;
                }

                // Remover itens do carrinho que referenciam o produto (para evitar travar o DELETE)
                if ($carrinhoItensTable !== null) {
                    try {
                        $stDelC = $db->prepare("DELETE FROM {$carrinhoItensTable} WHERE produto_id = ?");
                        $stDelC->execute([$pid]);
                        $out['deleted_carrinho_itens'] += (int) $stDelC->rowCount();
                    } catch (\Exception $e) {
                        $out['errors'][] = 'Falha ao remover carrinho item produto_id=' . $pid . ': ' . $e->getMessage();
                    }
                }

                // Remover fotos do produto
                try {
                    $stDelF = $db->prepare('DELETE FROM produto_fotos WHERE produto_id = ?');
                    $stDelF->execute([$pid]);
                    $out['deleted_produto_fotos'] += (int) $stDelF->rowCount();
                } catch (\Exception $e) {
                    $out['errors'][] = 'Falha ao remover produto_fotos produto_id=' . $pid . ': ' . $e->getMessage();
                }

                // Remover produto
                try {
                    $stDelP = $db->prepare('DELETE FROM produtos WHERE id = ?');
                    $stDelP->execute([$pid]);
                    $out['deleted_produtos'] += (int) $stDelP->rowCount();
                    $db->commit();
                } catch (\Exception $e) {
                    // Fallback: arquivar/inativar caso exista vínculo (FK) impedindo o DELETE
                    try {
                        $stArch = $db->prepare("UPDATE produtos SET active = 0, status = 'archived', updated_at = NOW() WHERE id = ?");
                        $stArch->execute([$pid]);
                        if ($stArch->rowCount() > 0) {
                            $out['archived_produtos'] += 1;
                        }
                        $db->commit();
                    } catch (\Exception $e2) {
                        try {
                            $db->rollBack();
                        } catch (\Exception $e3) {
                        }
                        $out['errors'][] = 'Falha ao remover/arquivar produto_id=' . $pid . ': ' . $e->getMessage();
                    }
                }
            }

            // Expirar orçamentos antigos (limpa produtos/erros/totais) para exigir reprocessamento
            try {
                $stmtOrc = $db->prepare("SELECT id, last_processed_at FROM assessoria_orcamentos WHERE status <> 'pago' AND last_processed_at IS NOT NULL AND last_processed_at < DATE_SUB(NOW(), INTERVAL {$limitMinutes} MINUTE) LIMIT 200");
                $stmtOrc->execute();
                $orcRows = $stmtOrc->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                foreach ($orcRows as $r) {
                    $oid = (int) ($r['id'] ?? 0);
                    if ($oid <= 0) {
                        continue;
                    }
                    try {
                        $stmtUpd = $db->prepare("UPDATE assessoria_orcamentos SET produtos_json = NULL, erros_json = NULL, totais_json = NULL, updated_at = NOW() WHERE id = ?");
                        $stmtUpd->execute([$oid]);
                        if ($stmtUpd->rowCount() > 0) {
                            $out['expired_orcamentos']++;
                        }
                    } catch (\Exception $e) {
                    }
                }
            } catch (\Exception $e) {
            }

            echo json_encode($out);
            return;
        } catch (\Exception $e) {
            $out['success'] = false;
            $out['errors'][] = $e->getMessage();
            echo json_encode($out);
            return;
        }
    }
    
    /**
     * Exibe a página principal de Assessoria
     */
    public function index(Request $request) {
        $auth = new AuthService();
        $isLogged = $auth->estaLogado();
        $usuario = $isLogged ? $auth->getUsuarioLogado() : null;

        session_start();
        $prefillLinks = $_SESSION['assessoria_prefill_links'] ?? [];
        if (!is_array($prefillLinks)) {
            $prefillLinks = [];
        }
        // Consumir prefill para não ficar reaparecendo
        unset($_SESSION['assessoria_prefill_links']);
        unset($_SESSION['assessoria_prefill_from_orcamento_id']);

        // Exigir login (sem redirecionar imediatamente; UI mostra pop-up e botão de login)
        $acceptedAt = null;
        if ($isLogged && is_array($usuario)) {
            try {
                $userModel = new Usuario();
                $full = $userModel->find($usuario['id']);
                $acceptedAt = $full['assessoria_disclaimer_aceito_em'] ?? null;
            } catch (\Exception $e) {
                $acceptedAt = null;
            }
        }

        $this->view('assessoria/index', [
            'assessoria_logged_in' => $isLogged,
            'assessoria_disclaimer_accepted' => ($acceptedAt !== null && (string) $acceptedAt !== ''),
            'assessoria_prefill_links' => $prefillLinks,
        ]);
    }

    public function aceitarDisclaimer(Request $request) {
        header('Content-Type: application/json');

        $auth = new AuthService();
        if (!$auth->estaLogado()) {
            echo json_encode(['success' => false, 'message' => 'Login obrigatório']);
            return;
        }

        $usuario = $auth->getUsuarioLogado();
        if (!$usuario || !isset($usuario['id'])) {
            echo json_encode(['success' => false, 'message' => 'Usuário inválido']);
            return;
        }

        try {
            $db = \Config\Database::getConnection();
            $cols = [];
            try {
                $stmtCols = $db->query('DESCRIBE usuarios');
                $cols = $stmtCols->fetchAll(\PDO::FETCH_COLUMN);
            } catch (\Exception $e) {
                $cols = [];
            }

            if (is_array($cols) && in_array('assessoria_disclaimer_aceito_em', $cols, true)) {
                $stmt = $db->prepare('UPDATE usuarios SET assessoria_disclaimer_aceito_em = NOW() WHERE id = ?');
                $stmt->execute([(int) $usuario['id']]);
            }

            echo json_encode(['success' => true]);
            return;
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erro ao salvar aceite']);
            return;
        }
    }

    public function enfileirarLinks(Request $request) {
        header('Content-Type: application/json');
        session_start();

        $auth = new AuthService();
        if (!$auth->estaLogado()) {
            echo json_encode(['success' => false, 'message' => 'Login obrigatório', 'redirect' => '/login?redirect=/assessoria']);
            return;
        }

        try {
            $body = $request->getBody();
            $links = $body['links'] ?? [];
            if (!is_array($links) || empty($links)) {
                echo json_encode(['success' => false, 'message' => 'Nenhum link fornecido']);
                return;
            }

            $cleanLinks = [];
            foreach ($links as $l) {
                $l = trim((string) $l);
                if ($l === '' || !filter_var($l, FILTER_VALIDATE_URL)) {
                    continue;
                }
                $cleanLinks[] = $l;
            }

            if (empty($cleanLinks)) {
                echo json_encode(['success' => false, 'message' => 'Nenhum link válido fornecido']);
                return;
            }

            $_SESSION['assessoria_orcamento'] = [
                'produtos' => [],
                'erros' => [],
                'data_criacao' => date('Y-m-d H:i:s')
            ];

            $jobId = bin2hex(random_bytes(16));
            $_SESSION['assessoria_job_id'] = $jobId;

            $usuario = $auth->getUsuarioLogado();
            $usuarioId = (int) ($usuario['id'] ?? 0);
            $token = bin2hex(random_bytes(24));

            $orcamentoId = null;
            try {
                $orcamentoModel = new AssessoriaOrcamento();
                $orcamentoId = (int) $orcamentoModel->create([
                    'usuario_id' => $usuarioId,
                    'status' => 'rascunho',
                    'public_token' => $token,
                    'job_id' => $jobId,
                    'links_json' => json_encode($cleanLinks),
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s'),
                ]);
                $_SESSION['assessoria_orcamento_id'] = $orcamentoId;
                $_SESSION['assessoria_orcamento_token'] = $token;
            } catch (\Exception $e) {
                $orcamentoId = null;
            }

            $job = [
                'job_id' => $jobId,
                'status' => 'queued',
                'total' => count($cleanLinks),
                'processed' => 0,
                'links' => $cleanLinks,
                'produtos' => [],
                'erros' => [],
                'started_at' => null,
                'finished_at' => null
            ];
            $this->writeJobFile($jobId, $job);

            // Webhook de início (idempotente via coluna no orçamento)
            if (!empty($orcamentoId)) {
                try {
                    $db = \Config\Database::getConnection();
                    $stmt = $db->prepare('SELECT webhook_inicio_disparado_em FROM assessoria_orcamentos WHERE id = ? LIMIT 1');
                    $stmt->execute([(int) $orcamentoId]);
                    $sentAt = $stmt->fetchColumn();

                    if (empty($sentAt)) {
                        $payload = [
                            'evento' => 'assessoria_orcamento_inicio',
                            'orcamento_id' => (int) $orcamentoId,
                            'orcamento_token' => (string) ($token ?? ''),
                            'orcamento_url' => '/assessoria/orcamento?orcamento_id=' . (int) $orcamentoId,
                            'job_id' => $jobId,
                            'usuario_id' => $usuarioId,
                            'nome' => (string) ($usuario['nome'] ?? ''),
                            'telefone' => (string) ($usuario['telefone'] ?? ''),
                            'links' => $cleanLinks,
                            'data' => date('Y-m-d H:i:s'),
                        ];
                        $this->enviarWebhookAssessoria('inicio', $payload);

                        $stmtUpd = $db->prepare('UPDATE assessoria_orcamentos SET webhook_inicio_disparado_em = NOW(), updated_at = NOW() WHERE id = ?');
                        $stmtUpd->execute([(int) $orcamentoId]);
                    }
                } catch (\Exception $e) {
                }
            }

            session_write_close();

            echo json_encode([
                'success' => true,
                'data' => [
                    'job_id' => $jobId,
                    'total' => count($cleanLinks),
                    'orcamento_id' => $orcamentoId,
                    'orcamento_token' => $token,
                    'orcamento_url' => '/assessoria/orcamento?orcamento_id=' . (int) $orcamentoId
                ]
            ]);

            $spawned = $this->trySpawnJobWorker($jobId);
            $job['spawned'] = $spawned ? true : false;
            $this->writeJobFile($jobId, $job);
            if ($spawned) {
                return;
            }

            if (function_exists('fastcgi_finish_request')) {
                fastcgi_finish_request();
            }
            ignore_user_abort(true);
            @set_time_limit(0);

            $this->startBackgroundProcessing($cleanLinks, $jobId);
            return;
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Erro ao iniciar processamento: ' . $e->getMessage()]);
            return;
        }
    }

    public function statusJob(Request $request) {
        header('Content-Type: application/json');
        session_start();

        $jobId = (string) $request->getParam('job_id', '');
        if ($jobId === '') {
            $jobId = (string) ($_SESSION['assessoria_job_id'] ?? '');
        }

        if ($jobId === '') {
            echo json_encode(['success' => false, 'message' => 'job_id não informado']);
            return;
        }

        $job = $this->readJobFile($jobId);
        if ($job === null) {
            echo json_encode(['success' => false, 'message' => 'Job não encontrado']);
            return;
        }

        if (($job['status'] ?? '') === 'done') {
            if (!isset($_SESSION['assessoria_orcamento']) || !is_array($_SESSION['assessoria_orcamento'])) {
                $_SESSION['assessoria_orcamento'] = [
                    'produtos' => [],
                    'erros' => [],
                    'data_criacao' => date('Y-m-d H:i:s')
                ];
            }

            $_SESSION['assessoria_orcamento']['produtos'] = $job['produtos'] ?? [];
            $_SESSION['assessoria_orcamento']['erros'] = $job['erros'] ?? [];

            // Persistir no DB e disparar webhook de conclusão (idempotente)
            try {
                $orcamentoModel = new AssessoriaOrcamento();
                $row = $orcamentoModel->findByJobId((string) $jobId);
                if (is_array($row) && !empty($row['id'])) {
                    $orcId = (int) $row['id'];
                    $produtos = is_array($job['produtos'] ?? null) ? $job['produtos'] : [];
                    $erros = is_array($job['erros'] ?? null) ? $job['erros'] : [];
                    $totais = $this->calcularTotaisOrcamento($produtos);

                    $orcamentoModel->update($orcId, [
                        'produtos_json' => json_encode($produtos),
                        'erros_json' => json_encode($erros),
                        'totais_json' => json_encode($totais),
                        'last_processed_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s'),
                    ]);

                    $db = \Config\Database::getConnection();
                    $stmt = $db->prepare('SELECT webhook_conclusao_disparado_em, usuario_id, public_token, pedido_id FROM assessoria_orcamentos WHERE id = ? LIMIT 1');
                    $stmt->execute([$orcId]);
                    $row2 = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
                    $sentAt = $row2['webhook_conclusao_disparado_em'] ?? null;
                    if (empty($sentAt)) {
                        $userModel = new Usuario();
                        $u = $userModel->find((int) ($row2['usuario_id'] ?? 0)) ?: [];
                        $payload = [
                            'evento' => 'assessoria_orcamento_concluido',
                            'orcamento_id' => $orcId,
                            'orcamento_token' => (string) ($row2['public_token'] ?? ''),
                            'orcamento_url' => '/assessoria/orcamento?orcamento_id=' . $orcId,
                            'job_id' => $jobId,
                            'usuario_id' => (int) ($row2['usuario_id'] ?? 0),
                            'nome' => (string) ($u['nome'] ?? ($u['name'] ?? '')),
                            'telefone' => (string) ($u['telefone'] ?? ''),
                            'pedido_id' => (string) ($row2['pedido_id'] ?? ''),
                            'total_produtos' => is_array($produtos) ? count($produtos) : 0,
                            'total_erros' => is_array($erros) ? count($erros) : 0,
                            'totais' => $totais,
                            'data' => date('Y-m-d H:i:s'),
                        ];
                        $this->enviarWebhookAssessoria('conclusao', $payload);

                        $stmtUpd = $db->prepare('UPDATE assessoria_orcamentos SET webhook_conclusao_disparado_em = NOW(), updated_at = NOW() WHERE id = ?');
                        $stmtUpd->execute([$orcId]);
                    }
                }
            } catch (\Exception $e) {
            }
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'job_id' => $jobId,
                'status' => (string) ($job['status'] ?? ''),
                'total' => (int) ($job['total'] ?? 0),
                'processed' => (int) ($job['processed'] ?? 0),
                'total_produtos' => is_array($job['produtos'] ?? null) ? count($job['produtos']) : 0,
                'total_erros' => is_array($job['erros'] ?? null) ? count($job['erros']) : 0
            ]
        ]);
    }

    private function headerSafeValue($value, int $maxLen = 200): string {
        $v = (string) $value;
        $v = preg_replace('/[\r\n]+/', ' ', $v);
        $v = preg_replace('/[\x00-\x1F\x7F]+/', ' ', $v);
        $v = trim($v);
        if (strlen($v) > $maxLen) {
            $v = substr($v, 0, $maxLen);
        }
        return $v;
    }

    private function cleanJsonText(string $text): string {
        // Remove caracteres de controle que quebram json_decode
        return (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $text);
    }

    private function extractFirstJsonObject(string $text): ?string {
        $start = strpos($text, '{');
        $end = strrpos($text, '}');
        if ($start === false || $end === false || $end <= $start) {
            return null;
        }
        return substr($text, $start, $end - $start + 1);
    }

    private function normalizePossibleJson(string $text): string {
        $t = trim($text);

        // Remover code fences ```json ... ```
        $t = preg_replace('/^```(?:json)?\s*/i', '', $t);
        $t = preg_replace('/\s*```\s*$/', '', $t);

        // Aspas “inteligentes” que quebram JSON
        $t = str_replace(["\u{201C}", "\u{201D}", "\u{201E}", "\u{201F}", "\u{00AB}", "\u{00BB}"], '"', $t);
        $t = str_replace(["\u{2018}", "\u{2019}", "\u{201A}", "\u{201B}"], "'", $t);

        // Remover caracteres de controle
        $t = $this->cleanJsonText($t);

        // Pegar apenas o objeto JSON (se houver texto extra)
        $obj = $this->extractFirstJsonObject($t);
        if ($obj !== null) {
            $t = $obj;
        }

        // Remover vírgulas finais antes de } ou ]
        $t = preg_replace('/,\s*([}\]])/', '$1', $t);

        return trim($t);
    }

    private function decodeJsonResilient(string $raw): array {
        $raw = trim($raw);
        if ($raw === '') {
            throw new \Exception('Resposta vazia do ChatGPT');
        }

        $candidate = $this->normalizePossibleJson($raw);

        $data = json_decode($candidate, true);
        if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
            return $data;
        }

        // Caso: JSON veio como string escapada "{...}"
        $maybeString = json_decode($candidate, true);
        if (json_last_error() === JSON_ERROR_NONE && is_string($maybeString)) {
            $candidate2 = $this->normalizePossibleJson($maybeString);
            $data2 = json_decode($candidate2, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($data2)) {
                return $data2;
            }
        }

        // Tentar reparar JSON truncado (resposta cortada no meio)
        $repaired = $this->repairTruncatedJson($candidate);
        if ($repaired !== null) {
            $data3 = json_decode($repaired, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($data3)) {
                error_log('[ChatGPT] JSON reparado com sucesso (truncamento)');
                return $data3;
            }
        }

        $err = json_last_error_msg();
        throw new \Exception('ChatGPT não retornou JSON válido: ' . $err);
    }

    /**
     * Tenta reparar JSON truncado fechando brackets/braces abertos
     */
    private function repairTruncatedJson(string $json): ?string {
        $json = trim($json);
        if ($json === '' || $json[0] !== '{') {
            return null;
        }

        // Contar brackets abertos
        $stack = [];
        $inString = false;
        $escape = false;
        $len = strlen($json);

        for ($i = 0; $i < $len; $i++) {
            $ch = $json[$i];

            if ($escape) {
                $escape = false;
                continue;
            }

            if ($ch === '\\' && $inString) {
                $escape = true;
                continue;
            }

            if ($ch === '"') {
                $inString = !$inString;
                continue;
            }

            if ($inString) {
                continue;
            }

            if ($ch === '{' || $ch === '[') {
                $stack[] = $ch;
            } elseif ($ch === '}') {
                if (!empty($stack) && end($stack) === '{') {
                    array_pop($stack);
                }
            } elseif ($ch === ']') {
                if (!empty($stack) && end($stack) === '[') {
                    array_pop($stack);
                }
            }
        }

        if (empty($stack)) {
            return null; // Já está balanceado, o erro é outro
        }

        // Se estava no meio de uma string, fechar a string
        if ($inString) {
            $json .= '"';
        }

        // Remover última vírgula ou valor parcial antes de fechar
        $json = preg_replace('/,\s*"[^"]*$/', '', $json); // chave parcial
        $json = preg_replace('/,\s*\d+\.?\d*$/', '', $json); // número parcial
        $json = preg_replace('/,\s*$/', '', $json); // vírgula solta
        $json = preg_replace('/:\s*$/', ': null', $json); // valor faltando

        // Fechar brackets na ordem inversa
        while (!empty($stack)) {
            $open = array_pop($stack);
            $json .= ($open === '{') ? '}' : ']';
        }

        return $json;
    }

    private function truncateForPrompt($value, int $depth = 0) {
        if ($depth > 6) {
            return null;
        }

        if (is_array($value)) {
            $out = [];
            $i = 0;
            foreach ($value as $k => $v) {
                if ($i >= 60) {
                    break;
                }
                $out[$k] = $this->truncateForPrompt($v, $depth + 1);
                $i++;
            }
            return $out;
        }

        if (is_string($value)) {
            $v = $this->cleanJsonText($value);
            if (strlen($v) > 1200) {
                $v = substr($v, 0, 1200);
            }
            return $v;
        }

        if (is_bool($value) || is_int($value) || is_float($value) || $value === null) {
            return $value;
        }

        return (string) $value;
    }

    private function reduceScrapingBeePayload(array $dadosBrutos): array {
        $picked = [];
        $keys = [
            'title', 'name', 'product', 'product_name',
            'price', 'prices', 'images', 'image',
            'variants', 'variation', 'variations', 'offers',
            'options', 'availableVariations', 'variantCriteria',
            'url', 'description',
            // Campos de especificações e peso
            'specifications', 'specs', 'weight', 'shipping_weight',
            'product_weight', 'dimensions', 'details', 'features',
            'attributes', 'product_details', 'product_specifications',
            // Dados extras do scraping complementar
            'html_extra',
            // Dados de variações com preços do HTML
            'variant_offers', 'variant_prices_found', 'variant_price_data',
        ];
        foreach ($keys as $k) {
            if (array_key_exists($k, $dadosBrutos)) {
                $picked[$k] = $dadosBrutos[$k];
            }
        }
        // Busca recursiva rasa: se product é array, incluir sub-campos relevantes
        if (isset($dadosBrutos['product']) && is_array($dadosBrutos['product'])) {
            foreach (['specifications', 'specs', 'weight', 'details', 'features', 'attributes'] as $sk) {
                if (isset($dadosBrutos['product'][$sk]) && !isset($picked[$sk])) {
                    $picked['product_' . $sk] = $dadosBrutos['product'][$sk];
                }
            }
        }
        if (empty($picked)) {
            $picked = $dadosBrutos;
        }
        $result = (array) $this->truncateForPrompt($picked, 0);
        
        // Limitar tamanho total do payload para evitar truncamento do ChatGPT
        $jsonSize = strlen(json_encode($result));
        if ($jsonSize > 30000) {
            // Remover campos menos importantes para reduzir tamanho
            foreach (['description', 'details', 'features', 'images', 'image'] as $dropKey) {
                if (isset($result[$dropKey])) {
                    unset($result[$dropKey]);
                    $jsonSize = strlen(json_encode($result));
                    if ($jsonSize <= 30000) break;
                }
            }
            // Se ainda grande, truncar html_extra
            if ($jsonSize > 30000 && isset($result['html_extra']) && is_array($result['html_extra'])) {
                // Manter apenas campos essenciais do html_extra
                $essentialKeys = ['weights_found', 'specifications', 'variant_offers', 'variant_prices_found', 'variant_price_data', 'options_found', 'prices_found', 'jsonld_product'];
                $result['html_extra'] = array_intersect_key($result['html_extra'], array_flip($essentialKeys));
            }
        }
        
        return $result;
    }

    private function findFirstNumeric($value, int $depth = 0): ?float {
        if ($depth > 6) {
            return null;
        }
        if (is_numeric($value)) {
            $n = floatval($value);
            if ($n > 0) {
                return $n;
            }
        }
        if (is_string($value)) {
            $s = str_replace([',', '$', 'USD', 'usd'], ['.', '', '', ''], $value);
            if (preg_match('/(\d+(?:\.\d+)?)/', $s, $m)) {
                $n = floatval($m[1]);
                if ($n > 0) {
                    return $n;
                }
            }
        }
        if (is_array($value)) {
            foreach ($value as $v) {
                $found = $this->findFirstNumeric($v, $depth + 1);
                if ($found !== null) {
                    return $found;
                }
            }
        }
        return null;
    }

    private function extractNomeFromScrapingBee(array $dadosBrutos): ?string {
        foreach (['name', 'title', 'product_name'] as $k) {
            if (!empty($dadosBrutos[$k]) && is_string($dadosBrutos[$k])) {
                $v = trim($this->cleanJsonText((string) $dadosBrutos[$k]));
                if ($v !== '') {
                    return $v;
                }
            }
        }

        $queue = [$dadosBrutos];
        $max = 200;
        $seen = 0;
        while (!empty($queue) && $seen < $max) {
            $node = array_shift($queue);
            $seen++;
            if (!is_array($node)) {
                continue;
            }
            foreach ($node as $k => $v) {
                $ks = strtolower((string) $k);
                if ((strpos($ks, 'title') !== false || strpos($ks, 'name') !== false) && is_string($v)) {
                    $vv = trim($this->cleanJsonText($v));
                    if (strlen($vv) >= 3) {
                        return $vv;
                    }
                }
                if (is_array($v)) {
                    $queue[] = $v;
                }
            }
        }

        return null;
    }

    private function extractDescricaoFromScrapingBee(array $dadosBrutos): string {
        foreach (['description', 'descricao', 'product_description', 'details'] as $k) {
            if (!empty($dadosBrutos[$k])) {
                if (is_string($dadosBrutos[$k])) {
                    return trim($this->cleanJsonText((string) $dadosBrutos[$k]));
                }
                if (is_array($dadosBrutos[$k])) {
                    $txt = json_encode($this->truncateForPrompt($dadosBrutos[$k], 0));
                    return trim($this->cleanJsonText((string) $txt));
                }
            }
        }
        return '';
    }

    private function buildProdutoFallbackFromScrapingBee(array $dadosBrutos, string $urlOriginal): ?array {
        $nome = $this->extractNomeFromScrapingBee($dadosBrutos);
        $valor = $this->extractValorFromScrapingBee($dadosBrutos);
        $img = $this->extractFirstImageUrl($dadosBrutos);

        if ($nome === null || $nome === '' || $valor === null) {
            return null;
        }

        $descricao = $this->extractDescricaoFromScrapingBee($dadosBrutos);
        if (trim((string) $descricao) === '') {
            $descricao = 'Produto importado automaticamente: ' . $nome;
        }

        $variacoes = $this->extractVariacoesFromScrapingBee($dadosBrutos);

        if (headers_sent() === false) {
            $attrKeys = [];
            foreach ($variacoes as $v) {
                if (is_array($v) && isset($v['atributos']) && is_array($v['atributos'])) {
                    foreach (array_keys($v['atributos']) as $k) {
                        $attrKeys[(string) $k] = true;
                    }
                }
            }
            header('X-Assessoria-Variacoes-Fallback-Count: ' . count($variacoes));
            header('X-Assessoria-Variacoes-Fallback-Keys: ' . $this->headerSafeValue(implode(',', array_keys($attrKeys)), 200));
        }

        // Estimar peso baseado no nome (nunca usar 1kg genérico)
        $nomeLower = strtolower((string) $nome);
        $pesoEstimado = 2.0;
        if (preg_match('/mattress|colch[aã]o/', $nomeLower)) {
            $pesoEstimado = 35.0;
        } elseif (preg_match('/sofa|couch|sof[aá]/', $nomeLower)) {
            $pesoEstimado = 40.0;
        } elseif (preg_match('/blanket|comforter|cobertor|manta|duvet/', $nomeLower)) {
            $pesoEstimado = 3.5;
        } elseif (preg_match('/pillow|travesseiro/', $nomeLower)) {
            $pesoEstimado = 1.5;
        } elseif (preg_match('/tv|monitor|television/', $nomeLower)) {
            $pesoEstimado = 15.0;
        } elseif (preg_match('/laptop|notebook/', $nomeLower)) {
            $pesoEstimado = 2.5;
        } elseif (preg_match('/phone|celular|smartphone/', $nomeLower)) {
            $pesoEstimado = 0.3;
        } elseif (preg_match('/shoe|sapato|tênis|sneaker|boot/', $nomeLower)) {
            $pesoEstimado = 1.2;
        } elseif (preg_match('/shirt|camiseta|blouse|dress|vestido|jacket|jaqueta/', $nomeLower)) {
            $pesoEstimado = 0.5;
        } elseif (preg_match('/toy|brinquedo/', $nomeLower)) {
            $pesoEstimado = 1.5;
        } elseif (preg_match('/chair|cadeira/', $nomeLower)) {
            $pesoEstimado = 15.0;
        } elseif (preg_match('/table|mesa|desk/', $nomeLower)) {
            $pesoEstimado = 20.0;
        }

        return [
            'sku' => '',
            'nome' => $nome,
            'descricao' => $descricao,
            'valor' => floatval($valor),
            'peso' => round($pesoEstimado * 1.15, 2),
            'imagens' => $img ? [$img] : [],
            'variacoes' => $variacoes,
            'url_original' => $urlOriginal
        ];
    }

    private function stringifyVariationLabel(array $atributos): string {
        $parts = [];
        foreach ($atributos as $k => $v) {
            $k = trim((string) $k);
            $v = trim((string) $v);
            if ($k === '' || $v === '') {
                continue;
            }
            $parts[] = $k . ': ' . $v;
        }
        return implode(' | ', $parts);
    }

    private function normalizeVariacoes(array $variacoesBrutas, array $optionNames = []): array {
        $out = [];
        foreach ($variacoesBrutas as $v) {
            if (!is_array($v)) {
                continue;
            }

            $atributos = [];
            foreach (['attributes', 'atributos', 'options', 'option', 'selected_options'] as $ak) {
                if (isset($v[$ak]) && is_array($v[$ak])) {
                    $atributos = $v[$ak];
                    break;
                }
            }

            // Padrão Shopify/semelhantes: option1/option2/option3
            if (empty($atributos)) {
                $o1 = $v['option1'] ?? $v['option_1'] ?? null;
                $o2 = $v['option2'] ?? $v['option_2'] ?? null;
                $o3 = $v['option3'] ?? $v['option_3'] ?? null;

                $names = $optionNames;
                $n1 = (string) ($names[0] ?? 'Opção 1');
                $n2 = (string) ($names[1] ?? 'Opção 2');
                $n3 = (string) ($names[2] ?? 'Opção 3');

                if (is_string($o1) && trim($o1) !== '') {
                    $atributos[$n1] = trim($o1);
                }
                if (is_string($o2) && trim($o2) !== '') {
                    $atributos[$n2] = trim($o2);
                }
                if (is_string($o3) && trim($o3) !== '') {
                    $atributos[$n3] = trim($o3);
                }
            }

            // Alguns retornos vem como lista de pares
            if (!empty($atributos) && array_keys($atributos) === range(0, count($atributos) - 1)) {
                $attrs2 = [];
                foreach ($atributos as $pair) {
                    if (is_array($pair)) {
                        $name = $pair['name'] ?? $pair['key'] ?? null;
                        $value = $pair['value'] ?? $pair['val'] ?? null;
                        if (is_string($name) && $name !== '' && (is_string($value) || is_numeric($value))) {
                            $attrs2[(string) $name] = (string) $value;
                        }
                    }
                }
                $atributos = $attrs2;
            }

            $valor = null;
            foreach (['price', 'valor', 'amount', 'current_price', 'compare_at_price', 'sale_price', 'final_price', 'regular_price', 'unit_price', 'salePrice'] as $pk) {
                if (isset($v[$pk])) {
                    $valor = $this->findFirstNumeric($v[$pk]);
                    if ($valor !== null) {
                        break;
                    }
                }
            }

            // Alguns formatos tem price como array/objeto: {amount:..} ou {value:..}
            if ($valor === null && isset($v['price']) && is_array($v['price'])) {
                foreach (['amount', 'value', 'current', 'usd'] as $pk2) {
                    if (isset($v['price'][$pk2])) {
                        $valor = $this->findFirstNumeric($v['price'][$pk2]);
                        if ($valor !== null) {
                            break;
                        }
                    }
                }
            }

            $peso = null;
            foreach (['weight', 'peso'] as $wk) {
                if (isset($v[$wk])) {
                    $peso = $this->findFirstNumeric($v[$wk]);
                    if ($peso !== null) {
                        break;
                    }
                }
            }

            if ($peso !== null && floatval($peso) <= 0) {
                $peso = null; // herdar peso base do produto
            }

            $label = $this->stringifyVariationLabel(is_array($atributos) ? $atributos : []);
            if ($label === '') {
                $label = (string) ($v['name'] ?? $v['title'] ?? $v['sku'] ?? 'Variação');
            }

            $id = (string) ($v['id'] ?? $v['variation_id'] ?? $v['variant_id'] ?? $v['variantId'] ?? $v['item_id'] ?? $v['itemId'] ?? $v['sku'] ?? md5($label));
            $out[] = [
                'id' => $id,
                'label' => $label,
                'atributos' => is_array($atributos) ? $atributos : [],
                'valor' => $valor !== null ? floatval($valor) : null,
                'peso' => $peso !== null ? floatval($peso) : null
            ];
        }

        // Remover duplicados por id
        $unique = [];
        foreach ($out as $v) {
            $k = (string) ($v['id'] ?? '');
            if ($k === '') {
                $k = md5((string) ($v['label'] ?? ''));
            }
            if (!isset($unique[$k])) {
                $unique[$k] = $v;
                continue;
            }
            // Preferir variação que tenha preço
            if (($unique[$k]['valor'] ?? null) === null && ($v['valor'] ?? null) !== null) {
                $unique[$k] = $v;
            }
        }
        return array_values($unique);
    }

    private function normalizeVariacoesForOrcamento(array $variacoes, array $dadosBrutos): array {
        if (empty($variacoes)) {
            return [];
        }

        $optionNames = $this->extractOptionNamesFromScrapingBee($dadosBrutos);

        // Se já estiver no formato esperado (atributos), só garante defaults
        $hasAtributos = false;
        foreach ($variacoes as $v) {
            if (is_array($v) && (isset($v['atributos']) || isset($v['attributes']))) {
                $hasAtributos = true;
                break;
            }
        }

        if ($hasAtributos) {
            $out = [];
            foreach ($variacoes as $v) {
                if (!is_array($v)) {
                    continue;
                }
                if (!isset($v['atributos']) && isset($v['attributes']) && is_array($v['attributes'])) {
                    $v['atributos'] = $v['attributes'];
                }
                if (!isset($v['atributos']) || !is_array($v['atributos'])) {
                    $v['atributos'] = [];
                }
                if (!isset($v['id'])) {
                    $v['id'] = (string) ($v['sku'] ?? md5((string) ($v['label'] ?? '')));
                }
                if (!isset($v['label']) || (string) $v['label'] === '') {
                    $v['label'] = $this->stringifyVariationLabel($v['atributos']);
                }
                if (!array_key_exists('valor', $v)) {
                    $v['valor'] = null;
                }
                if (!isset($v['peso']) || floatval($v['peso']) <= 0) {
                    $v['peso'] = null; // herdar peso base do produto
                }
                $out[] = [
                    'id' => (string) ($v['id'] ?? ''),
                    'label' => (string) ($v['label'] ?? ''),
                    'atributos' => $v['atributos'],
                    'valor' => ($v['valor'] === null ? null : floatval($v['valor'])),
                    'peso' => ($v['peso'] === null ? null : floatval($v['peso']))
                ];
            }
            return $this->mergeNormalizedVariacoes($out);
        }

        // Caso bruto (option1/2/3, price etc.)
        return $this->normalizeVariacoes($variacoes, $optionNames);
    }

    private function mergeNormalizedVariacoes(array $variacoes): array {
        $unique = [];
        foreach ($variacoes as $v) {
            if (!is_array($v)) {
                continue;
            }
            $id = (string) ($v['id'] ?? '');
            $label = (string) ($v['label'] ?? '');
            $k = $id !== '' ? $id : md5($label);

            if (!isset($unique[$k])) {
                $unique[$k] = $v;
                continue;
            }

            // Preferir item que tenha preço
            $cur = $unique[$k];
            $curHasPrice = isset($cur['valor']) && $cur['valor'] !== null && floatval($cur['valor']) > 0;
            $newHasPrice = isset($v['valor']) && $v['valor'] !== null && floatval($v['valor']) > 0;
            if (!$curHasPrice && $newHasPrice) {
                $unique[$k] = $v;
                continue;
            }

            // Preferir item que tenha atributos
            $curHasAttrs = isset($cur['atributos']) && is_array($cur['atributos']) && !empty($cur['atributos']);
            $newHasAttrs = isset($v['atributos']) && is_array($v['atributos']) && !empty($v['atributos']);
            if (!$curHasAttrs && $newHasAttrs) {
                $unique[$k] = $v;
            }
        }
        return array_values($unique);
    }

    /**
     * Mescla dados de preço/peso por variação obtidos da segunda chamada ScrapingBee
     * diretamente nas variações existentes do produto (sem re-chamar ChatGPT).
     */
    private function mergeVariantPriceData(array $produto, array $variantData): array {
        if (!is_array($produto['variacoes']) || empty($produto['variacoes'])) {
            return $produto;
        }

        // Normalizar variantData: pode ser array de objetos ou objeto com chaves
        $priceMap = []; // label_lower => ['price' => float, 'weight_lbs' => float]
        
        // Se é array sequencial de objetos [{size: "Twin", price: 299.99, weight_lbs: 60}, ...]
        if (isset($variantData[0]) && is_array($variantData[0])) {
            foreach ($variantData as $item) {
                if (!is_array($item)) continue;
                $size = strtolower(trim((string)($item['size'] ?? $item['name'] ?? $item['variant'] ?? $item['label'] ?? '')));
                if ($size === '') continue;
                $priceMap[$size] = [
                    'price' => $this->findFirstNumeric($item['price'] ?? $item['valor'] ?? null),
                    'weight_lbs' => $this->findFirstNumeric($item['weight_lbs'] ?? $item['weight'] ?? $item['peso'] ?? null),
                ];
            }
        }
        // Se é objeto com chaves {Twin: {price: 299.99}, King: {price: 499.99}}
        elseif (!isset($variantData[0])) {
            foreach ($variantData as $key => $val) {
                $size = strtolower(trim((string)$key));
                if (is_array($val)) {
                    $priceMap[$size] = [
                        'price' => $this->findFirstNumeric($val['price'] ?? $val['valor'] ?? null),
                        'weight_lbs' => $this->findFirstNumeric($val['weight_lbs'] ?? $val['weight'] ?? $val['peso'] ?? null),
                    ];
                } elseif (is_numeric($val)) {
                    $priceMap[$size] = ['price' => floatval($val), 'weight_lbs' => null];
                }
            }
        }

        if (empty($priceMap)) {
            error_log('[mergeVariantPriceData] Nenhum dado de preço extraído do variantData');
            return $produto;
        }

        error_log('[mergeVariantPriceData] Price map: ' . json_encode($priceMap));

        $anyPriceChanged = false;
        $anyWeightChanged = false;

        foreach ($produto['variacoes'] as &$var) {
            if (!is_array($var)) continue;
            
            $label = strtolower(trim((string)($var['label'] ?? '')));
            $attrs = $var['atributos'] ?? [];
            $sizeVal = strtolower(trim((string)($attrs['Bed Size'] ?? $attrs['Size'] ?? $attrs['Tamanho'] ?? '')));
            
            // Tentar match por label ou atributo de tamanho
            $matched = null;
            foreach ($priceMap as $mapKey => $mapVal) {
                if ($mapKey === $label || $mapKey === $sizeVal
                    || stripos($label, $mapKey) !== false
                    || ($sizeVal !== '' && stripos($sizeVal, $mapKey) !== false)
                    || stripos($mapKey, $label) !== false
                    || ($sizeVal !== '' && stripos($mapKey, $sizeVal) !== false)) {
                    $matched = $mapVal;
                    break;
                }
            }

            if ($matched === null) continue;

            // Aplicar preço se encontrado e diferente
            if ($matched['price'] !== null && $matched['price'] > 0) {
                $var['valor'] = round($matched['price'], 2);
                $anyPriceChanged = true;
            }

            // Aplicar peso se encontrado (converter lbs para kg)
            if ($matched['weight_lbs'] !== null && $matched['weight_lbs'] > 0) {
                $var['peso'] = round($matched['weight_lbs'] * 0.4536, 2);
                $anyWeightChanged = true;
            }
        }
        unset($var);

        error_log('[mergeVariantPriceData] Preços alterados: ' . ($anyPriceChanged ? 'sim' : 'nao') . ' | Pesos alterados: ' . ($anyWeightChanged ? 'sim' : 'nao'));

        return $produto;
    }

    private function extractOptionNamesFromScrapingBee(array $dadosBrutos): array {
        $candidates = [];

        // Formatos comuns: options: [{name: "Size", values:[...]}, ...]
        foreach (['options', 'product_options'] as $k) {
            if (isset($dadosBrutos[$k]) && is_array($dadosBrutos[$k])) {
                $candidates[] = $dadosBrutos[$k];
            }
        }

        // Aninhado em product
        if (isset($dadosBrutos['product']) && is_array($dadosBrutos['product'])) {
            if (isset($dadosBrutos['product']['options']) && is_array($dadosBrutos['product']['options'])) {
                $candidates[] = $dadosBrutos['product']['options'];
            }
        }

        foreach ($candidates as $opts) {
            $names = [];
            foreach ($opts as $opt) {
                if (!is_array($opt)) {
                    continue;
                }
                $n = $opt['name'] ?? $opt['label'] ?? null;
                if (is_string($n) && trim($n) !== '') {
                    $names[] = trim($n);
                }
            }
            if (!empty($names)) {
                return array_values(array_slice($names, 0, 3));
            }
        }

        return [];
    }

    private function extractVariacoesFromScrapingBee(array $dadosBrutos): array {
        $optionNames = $this->extractOptionNamesFromScrapingBee($dadosBrutos);

        $all = [];
        $append = function(array $list) use (&$all) {
            foreach ($list as $it) {
                if (is_array($it)) {
                    $all[] = $it;
                }
            }
        };

        foreach (['variations', 'variants', 'variation', 'variant', 'offers'] as $k) {
            if (!empty($dadosBrutos[$k]) && is_array($dadosBrutos[$k])) {
                $append($this->normalizeVariacoes($dadosBrutos[$k], $optionNames));
            }
        }

        if (isset($dadosBrutos['product']) && is_array($dadosBrutos['product'])) {
            if (isset($dadosBrutos['product']['variants']) && is_array($dadosBrutos['product']['variants'])) {
                $append($this->normalizeVariacoes($dadosBrutos['product']['variants'], $optionNames));
            }
            if (isset($dadosBrutos['product']['offers']) && is_array($dadosBrutos['product']['offers'])) {
                $append($this->normalizeVariacoes($dadosBrutos['product']['offers'], $optionNames));
            }
        }

        // Busca recursiva leve (agrega)
        $queue = [$dadosBrutos];
        $max = 200;
        $seen = 0;
        while (!empty($queue) && $seen < $max) {
            $node = array_shift($queue);
            $seen++;
            if (!is_array($node)) {
                continue;
            }
            foreach ($node as $k => $v) {
                $ks = strtolower((string) $k);
                if (in_array($ks, ['variations', 'variants', 'offers'], true) && is_array($v)) {
                    $append($this->normalizeVariacoes($v, $optionNames));
                }
                if (is_array($v)) {
                    $queue[] = $v;
                }
            }
        }

        if (empty($all)) {
            return [];
        }

        // $all aqui já está normalizado; consolidar sem re-normalizar
        return $this->mergeNormalizedVariacoes($all);
    }

    private function extractValorFromScrapingBee(array $dadosBrutos): ?float {
        $queue = [$dadosBrutos];
        $patterns = ['price', 'amount', 'valor', 'current', 'sale', 'offer', 'low', 'high'];
        $max = 200;
        $seen = 0;

        while (!empty($queue) && $seen < $max) {
            $node = array_shift($queue);
            $seen++;

            if (!is_array($node)) {
                continue;
            }

            foreach ($node as $k => $v) {
                $ks = strtolower((string) $k);
                foreach ($patterns as $p) {
                    if (strpos($ks, $p) !== false) {
                        $n = $this->findFirstNumeric($v);
                        if ($n !== null) {
                            return $n;
                        }
                        break;
                    }
                }

                if (is_array($v)) {
                    $queue[] = $v;
                }
            }
        }

        return null;
    }

    private function extractFirstImageUrl(array $dadosBrutos): ?string {
        $queue = [$dadosBrutos];
        $max = 200;
        $seen = 0;

        while (!empty($queue) && $seen < $max) {
            $node = array_shift($queue);
            $seen++;
            if (!is_array($node)) {
                continue;
            }

            foreach ($node as $k => $v) {
                $ks = strtolower((string) $k);
                if (strpos($ks, 'image') !== false || strpos($ks, 'img') !== false) {
                    if (is_string($v)) {
                        $vv = trim($v);
                        if (preg_match('#^https?://#i', $vv)) {
                            return $vv;
                        }
                        if (strpos($vv, '//') === 0) {
                            return 'https:' . $vv;
                        }
                    }
                    if (is_array($v)) {
                        foreach ($v as $vv) {
                            if (is_string($vv)) {
                                $vvv = trim($vv);
                                if (preg_match('#^https?://#i', $vvv)) {
                                    return $vvv;
                                }
                                if (strpos($vvv, '//') === 0) {
                                    return 'https:' . $vvv;
                                }
                            }
                            if (is_array($vv)) {
                                foreach (['url', 'src', 'href'] as $ik) {
                                    if (!empty($vv[$ik]) && is_string($vv[$ik])) {
                                        $u = trim((string) $vv[$ik]);
                                        if (preg_match('#^https?://#i', $u)) {
                                            return $u;
                                        }
                                        if (strpos($u, '//') === 0) {
                                            return 'https:' . $u;
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                if (is_array($v)) {
                    $queue[] = $v;
                }
            }
        }

        return null;
    }

    private function buildChatPayload(string $prompt, string $apiKey, bool $jsonOnly): array {
        $payload = [
            'model' => 'gpt-3.5-turbo',
            'messages' => [
                [
                    'role' => 'system',
                    'content' => 'Retorne apenas JSON válido, sem comentários e sem marcações.'
                ],
                [
                    'role' => 'user',
                    'content' => $prompt
                ]
            ],
            'temperature' => 0.0,
            'max_tokens' => 800
        ];
        if ($jsonOnly) {
            $payload['response_format'] = ['type' => 'json_object'];
        }
        return $payload;
    }

    private function callChatGPT(string $chatGptApiKey, string $prompt, bool $jsonOnly): array {
        $payload = $this->buildChatPayload($prompt, $chatGptApiKey, $jsonOnly);

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => 'https://api.openai.com/v1/chat/completions',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $chatGptApiKey
            ],
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_TIMEOUT => 45,
            CURLOPT_CONNECTTIMEOUT => 10
        ]);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        return [$response, $httpCode, $curlError];
    }

    private function getAssessoriaJobsDir(): string {
        $base = rtrim((string) sys_get_temp_dir(), DIRECTORY_SEPARATOR);
        $dir = $base . DIRECTORY_SEPARATOR . 'assessoria_jobs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }
        return $dir;
    }

    private function getJobFilePath(string $jobId): string {
        $safe = preg_replace('/[^a-zA-Z0-9_-]/', '', $jobId);
        return $this->getAssessoriaJobsDir() . DIRECTORY_SEPARATOR . 'job_' . $safe . '.json';
    }

    private function writeJobFile(string $jobId, array $data): void {
        $path = $this->getJobFilePath($jobId);
        $fp = @fopen($path, 'c+');
        if ($fp === false) {
            return;
        }
        @flock($fp, LOCK_EX);
        ftruncate($fp, 0);
        fwrite($fp, json_encode($data));
        fflush($fp);
        @flock($fp, LOCK_UN);
        fclose($fp);
    }

    private function readJobFile(string $jobId): ?array {
        $path = $this->getJobFilePath($jobId);
        if (!file_exists($path)) {
            return null;
        }
        $raw = @file_get_contents($path);
        if ($raw === false || trim($raw) === '') {
            return null;
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return null;
        }
        return $data;
    }

    private function startBackgroundProcessing(array $links, string $jobId): void {
        $job = [
            'job_id' => $jobId,
            'status' => 'running',
            'total' => count($links),
            'processed' => 0,
            'links' => $links,
            'produtos' => [],
            'erros' => [],
            'started_at' => date('Y-m-d H:i:s'),
            'finished_at' => null
        ];
        $this->writeJobFile($jobId, $job);

        foreach ($links as $link) {
            try {
                $resultado = $this->processarLinkIndividual((string) $link);
                if (!empty($resultado['success'])) {
                    $job['produtos'][] = $resultado['data'];
                } else {
                    $job['erros'][] = [
                        'link' => (string) $link,
                        'error' => (string) ($resultado['error'] ?? 'Erro ao processar link')
                    ];
                }
            } catch (\Exception $e) {
                $job['erros'][] = [
                    'link' => (string) $link,
                    'error' => $e->getMessage()
                ];
            }

            $job['processed'] = (int) $job['processed'] + 1;
            $this->writeJobFile($jobId, $job);
        }

        $job['status'] = 'done';
        $job['finished_at'] = date('Y-m-d H:i:s');
        $this->writeJobFile($jobId, $job);
    }

    public function processarJobPorId(string $jobId): void {
        $job = $this->readJobFile($jobId);
        if ($job === null) {
            return;
        }
        $links = $job['links'] ?? [];
        if (!is_array($links) || empty($links)) {
            return;
        }
        $this->startBackgroundProcessing($links, $jobId);
    }

    private function trySpawnJobWorker(string $jobId): bool {
        $root = rtrim((string) dirname(__DIR__, 3), DIRECTORY_SEPARATOR);
        $worker = $root . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'assessoria_worker.php';
        if (!file_exists($worker)) {
            return false;
        }

        $php = defined('PHP_BINARY') && PHP_BINARY ? PHP_BINARY : 'php';
        $cmd = escapeshellcmd($php) . ' ' . escapeshellarg($worker) . ' ' . escapeshellarg($jobId);

        // Rodar em background (importante no Windows para não travar a request)
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $cmd = 'cmd /c start /B "assessoria_worker" ' . $cmd;
        } else {
            $cmd .= ' > /dev/null 2>&1 &';
        }

        try {
            if (function_exists('proc_open')) {
                $descriptorspec = [
                    0 => ['pipe', 'r'],
                    1 => ['pipe', 'w'],
                    2 => ['pipe', 'w']
                ];
                $process = @proc_open($cmd, $descriptorspec, $pipes, $root);
                if (is_resource($process)) {
                    foreach ($pipes as $p) {
                        @fclose($p);
                    }
                    @proc_close($process);
                    return true;
                }
            }

            @exec($cmd);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
    
    /**
     * Processa os links enviados via AJAX
     */
    public function processarLinks(Request $request) {
        header('Content-Type: application/json');
        
        try {
            $body = $request->getBody();
            $links = $body['links'] ?? [];
            
            if (empty($links)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Nenhum link fornecido'
                ]);
                return;
            }
            
            // Validação básica dos links
            foreach ($links as $link) {
                if (!filter_var($link, FILTER_VALIDATE_URL)) {
                    echo json_encode([
                        'success' => false,
                        'message' => "Link inválido: {$link}"
                    ]);
                    return;
                }
            }
            
            // Processa cada link separadamente
            $resultados = [];
            $erros = [];
            
            foreach ($links as $index => $link) {
                try {
                    $resultado = $this->processarLinkIndividual($link);
                    if ($resultado['success']) {
                        $resultados[] = $resultado['data'];
                    } else {
                        $erros[] = [
                            'link' => $link,
                            'error' => $resultado['error']
                        ];
                    }
                } catch (\Exception $e) {
                    $erros[] = [
                        'link' => $link,
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            // Armazena resultados na sessão para o checkout
            $_SESSION['assessoria_orcamento'] = [
                'produtos' => $resultados,
                'erros' => $erros,
                'data_criacao' => date('Y-m-d H:i:s')
            ];
            
            echo json_encode([
                'success' => true,
                'data' => [
                    'produtos' => $resultados,
                    'erros' => $erros,
                    'total_produtos' => count($resultados),
                    'total_erros' => count($erros)
                ]
            ]);
            
        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Erro ao processar links: ' . $e->getMessage()
            ]);
        }
    }

    public function processarLinkUnico(Request $request) {
        header('Content-Type: application/json');
        session_start();

        try {
            $body = $request->getBody();
            $link = (string) ($body['link'] ?? '');
            $reset = (bool) ($body['reset'] ?? false);

            if ($reset || !isset($_SESSION['assessoria_orcamento'])) {
                $_SESSION['assessoria_orcamento'] = [
                    'produtos' => [],
                    'erros' => [],
                    'data_criacao' => date('Y-m-d H:i:s')
                ];
            }

            $link = trim($link);
            if ($link === '' || !filter_var($link, FILTER_VALIDATE_URL)) {
                echo json_encode([
                    'success' => false,
                    'message' => 'Link inválido'
                ]);
                return;
            }

            $resultado = $this->processarLinkIndividual($link);
            if ($resultado['success']) {
                $_SESSION['assessoria_orcamento']['produtos'][] = $resultado['data'];
            } else {
                $_SESSION['assessoria_orcamento']['erros'][] = [
                    'link' => $link,
                    'error' => $resultado['error']
                ];
            }

            $produtos = $_SESSION['assessoria_orcamento']['produtos'] ?? [];
            $erros = $_SESSION['assessoria_orcamento']['erros'] ?? [];

            echo json_encode([
                'success' => true,
                'data' => [
                    'total_produtos' => count($produtos),
                    'total_erros' => count($erros)
                ]
            ]);
        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Erro ao processar link: ' . $e->getMessage()
            ]);
        }
    }
    
    /**
     * Processa um link individual via ScrapingBee
     */
    private function processarLinkIndividual(string $url): array {
        $scriptbeeApiKey = $this->getScriptBeeApiKey();
        
        if (!$scriptbeeApiKey) {
            if (headers_sent() === false) {
                header('X-ScrapingBee-Error: API Key not configured');
            }
            return [
                'success' => false,
                'error' => 'API Key do ScrapingBee não configurada'
            ];
        }
        
        $requestUrl = 'https://app.scrapingbee.com/api/v1';

        $buildUrl = function(array $override = []) use ($requestUrl, $scriptbeeApiKey, $url) {
            $params = array_merge([
                'api_key' => $scriptbeeApiKey,
                'url' => $url,
                'stealth_proxy' => 'true',
                'country_code' => 'us',
                'timeout' => '120000',
                // networkidle2 espera JS carregar (specs, variações dinâmicas)
                'wait_browser' => 'networkidle2',
                'block_ads' => 'true',
                'ai_query' => 'Extract ALL product data as JSON: name, images, base price, weight in lbs or kg from specifications, and EVERY variant with id, attributes, price. Include specifications table with weights per size.'
            ], $override);
            return $requestUrl . '?' . http_build_query($params);
        };

        $fullUrl = $buildUrl();
        
        // Log da requisição
        if (headers_sent() === false) {
            header('X-ScrapingBee-Request-URL: ' . $this->headerSafeValue(substr($fullUrl, 0, 200), 200));
        }
        
        // Resolver DNS de app.scrapingbee.com antecipadamente (evita "Could not resolve host")
        $resolvedIp = $this->resolverDnsScrapingBee();
        error_log('[ScrapingBee] DNS resolved IP: ' . ($resolvedIp ?: 'FAILED'));

        $doRequest = function(string $targetUrl, int $timeoutSeconds) use ($resolvedIp) {
            $ch = curl_init();
            $opts = [
                CURLOPT_URL => $targetUrl,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST => 'GET',
                CURLOPT_TIMEOUT => $timeoutSeconds,
                CURLOPT_CONNECTTIMEOUT => 15,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
            ];
            // Se conseguimos resolver o IP, forçar via CURLOPT_RESOLVE
            if ($resolvedIp) {
                $opts[CURLOPT_RESOLVE] = ['app.scrapingbee.com:443:' . $resolvedIp];
                error_log('[ScrapingBee] Using CURLOPT_RESOLVE: app.scrapingbee.com:443:' . $resolvedIp);
            }
            // Tentar CURLOPT_DNS_SERVERS (só funciona se cURL compilado com c-ares)
            @curl_setopt($ch, CURLOPT_DNS_SERVERS, '8.8.8.8,1.1.1.1');
            curl_setopt_array($ch, $opts);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErrno = curl_errno($ch);
            $curlError = curl_error($ch);
            curl_close($ch);

            return [$response, $httpCode, $curlErrno, $curlError];
        };

        // 1 tentativa (até 150s) por produto (cURL deve ser > timeout do ScrapingBee)
        [$response, $httpCode, $curlErrno, $curlError] = $doRequest($fullUrl, 150);

        // Se DNS falhou mesmo com CURLOPT_DNS_SERVERS, tentar resolver manualmente e refazer
        if ($curlErrno === 6 && !$resolvedIp) {
            // errno 6 = CURLE_COULDNT_RESOLVE_HOST
            $resolvedIp = $this->resolverDnsScrapingBee(true);
            if ($resolvedIp) {
                if (headers_sent() === false) {
                    header('X-ScrapingBee-DNS-Retry: ' . $resolvedIp);
                }
                $doRequest = function(string $targetUrl, int $timeoutSeconds) use ($resolvedIp) {
                    $ch = curl_init();
                    curl_setopt_array($ch, [
                        CURLOPT_URL => $targetUrl,
                        CURLOPT_RETURNTRANSFER => true,
                        CURLOPT_CUSTOMREQUEST => 'GET',
                        CURLOPT_TIMEOUT => $timeoutSeconds,
                        CURLOPT_CONNECTTIMEOUT => 15,
                        CURLOPT_SSL_VERIFYPEER => true,
                        CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
                        CURLOPT_RESOLVE => ['app.scrapingbee.com:443:' . $resolvedIp],
                    ]);
                    $response = curl_exec($ch);
                    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $curlErrno = curl_errno($ch);
                    $curlError = curl_error($ch);
                    curl_close($ch);
                    return [$response, $httpCode, $curlErrno, $curlError];
                };
                [$response, $httpCode, $curlErrno, $curlError] = $doRequest($fullUrl, 150);
            }
        }

        // Se ai_query falhar (tamanho/validação), refazer sem ai_query
        if (!$curlError && (int) $httpCode === 400 && is_string($response) && stripos($response, 'ai_query') !== false) {
            $retryUrl = $buildUrl([
                'ai_query' => null
            ]);
            // Remove parâmetros nulos (http_build_query inclui ai_query=)
            $retryUrl = preg_replace('/(&|\?)ai_query=(&|$)/', '$1', $retryUrl);
            $retryUrl = rtrim($retryUrl, '&?');

            if (headers_sent() === false) {
                header('X-ScrapingBee-Retry-No-AIQuery: true');
                header('X-ScrapingBee-Retry-No-AIQuery-URL: ' . $this->headerSafeValue(substr($retryUrl, 0, 200), 200));
            }

            [$response, $httpCode, $curlErrno, $curlError] = $doRequest($retryUrl, 150);
        }

        // Retry automático em caso de timeout (sites pesados / bloqueios)
        if ($curlErrno === 28 || (is_string($curlError) && stripos($curlError, 'timeout') !== false)) {
            $retryUrl = $buildUrl([
                // Mais tolerante para páginas pesadas
                'wait_browser' => 'networkidle2',
                // Se o site bloquear muito, o premium_proxy ajuda (se sua conta permitir)
                'premium_proxy' => 'true',
                // Aumenta o timeout do lado do ScrapingBee (máx 140000)
                'timeout' => '140000'
            ]);

            if (headers_sent() === false) {
                header('X-ScrapingBee-Retry: true');
                header('X-ScrapingBee-Retry-URL: ' . $this->headerSafeValue(substr($retryUrl, 0, 200), 200));
            }

            // cURL deve ser > timeout do ScrapingBee
            [$response, $httpCode, $curlErrno, $curlError] = $doRequest($retryUrl, 160);
        }

        // Retry automático em caso de bloqueio/indisponibilidade (HTML 503/429/403)
        if (!$curlError && in_array((int) $httpCode, [503, 429, 403], true)) {
            $retryUrl = $buildUrl([
                // Compatível e mais permissivo
                'wait_browser' => 'load',
                'premium_proxy' => 'true',
                'timeout' => '140000'
            ]);

            if (headers_sent() === false) {
                header('X-ScrapingBee-Retry-HTTP: true');
                header('X-ScrapingBee-Retry-HTTP-Code: ' . (int) $httpCode);
                header('X-ScrapingBee-Retry-HTTP-URL: ' . $this->headerSafeValue(substr($retryUrl, 0, 200), 200));
            }

            [$response, $httpCode, $curlErrno, $curlError] = $doRequest($retryUrl, 160);
        }
        
        // Log da resposta
        if (headers_sent() === false) {
            header('X-ScrapingBee-HTTP-Code: ' . $httpCode);
            header('X-ScrapingBee-Response-Length: ' . strlen($response));
            header('X-ScrapingBee-Response-Prefix: ' . $this->headerSafeValue(substr((string) $response, 0, 200), 200));
            header('X-ScrapingBee-CURL-Errno: ' . $curlErrno);
        }
        
        if ($curlError) {
            error_log('[ScrapingBee] cURL error: errno=' . $curlErrno . ' error=' . $curlError . ' resolvedIp=' . ($resolvedIp ?: 'null'));
            if (headers_sent() === false) {
                header('X-ScrapingBee-CURL-Error: ' . $curlError);
            }
            
            $errorMessage = 'Erro na requisição cURL: ' . $curlError;
            if ($curlErrno === 28 || strpos($curlError, 'timeout') !== false) {
                $errorMessage = 'Timeout ao processar este site. Tente novamente (1 link por vez) ou use outro link.';
            }
            
            return [
                'success' => false,
                'error' => $errorMessage
            ];
        }
        
        if ($httpCode !== 200) {
            if (headers_sent() === false) {
                header('X-ScrapingBee-HTTP-Error: ' . $this->headerSafeValue(substr((string) $response, 0, 500), 500));
            }

            if (in_array((int) $httpCode, [503, 429, 403], true)) {
                return [
                    'success' => false,
                    'error' => "Site bloqueou/limitou o acesso no momento (HTTP {$httpCode}). Tente novamente mais tarde ou use outro link."
                ];
            }
            return [
                'success' => false,
                'error' => "Erro HTTP {$httpCode}: " . substr($response, 0, 500)
            ];
        }
        
        if (empty($response)) {
            if (headers_sent() === false) {
                header('X-ScrapingBee-Empty-Response: true');
            }
            return [
                'success' => false,
                'error' => 'Resposta vazia da API'
            ];
        }
        
        // Tentar decodificar JSON
        $decodedResponse = json_decode($response, true);
        $jsonError = json_last_error();
        
        if ($jsonError !== JSON_ERROR_NONE) {
            if (headers_sent() === false) {
                header('X-ScrapingBee-JSON-Error: ' . json_last_error_msg());
                header('X-ScrapingBee-Response-Raw: ' . $this->headerSafeValue(substr((string) $response, 0, 500), 500));
            }
            return [
                'success' => false,
                'error' => 'Resposta não é JSON válido: ' . json_last_error_msg()
            ];
        }
        
        // Log da estrutura do JSON
        if (headers_sent() === false) {
            header('X-ScrapingBee-JSON-Keys: ' . json_encode(array_keys(is_array($decodedResponse) ? $decodedResponse : [])));
            header('X-ScrapingBee-JSON-Type: ' . gettype($decodedResponse));
        }
        // Log detalhado para debug de variações
        $varKeys = [];
        foreach (['variants', 'variations', 'offers', 'options', 'variantCriteria', 'availableVariations'] as $vk) {
            if (isset($decodedResponse[$vk])) {
                $varKeys[] = $vk . '(' . (is_array($decodedResponse[$vk]) ? count($decodedResponse[$vk]) : 'non-array') . ')';
            }
        }
        error_log('[ScrapingBee] Response keys: ' . json_encode(array_keys(is_array($decodedResponse) ? $decodedResponse : [])) . ' | Variation fields: ' . (empty($varKeys) ? 'NONE' : implode(', ', $varKeys)));
        
        // Log detalhado: primeiras variações do ScrapingBee para debug de preço/peso
        foreach (['variants', 'variations', 'offers'] as $vField) {
            if (isset($decodedResponse[$vField]) && is_array($decodedResponse[$vField])) {
                foreach (array_slice($decodedResponse[$vField], 0, 2) as $vi => $vv) {
                    if (is_array($vv)) {
                        error_log('[ScrapingBee] ' . $vField . '[' . $vi . ']: ' . substr(json_encode($vv), 0, 500));
                    }
                }
                break;
            }
        }
        
        try {
            // Usar ChatGPT para analisar os dados brutos
            $produto = $this->analisarComChatGPT($decodedResponse, $url);
            
            // Verificar se o resultado está incompleto (sem variações ou peso genérico)
            $varCount = is_array($produto['variacoes'] ?? null) ? count($produto['variacoes']) : 0;
            $pesoSuspeito = (floatval($produto['peso'] ?? 0) <= 1.5);
            $poucasVariacoes = ($varCount <= 1);
            
            // Verificar se todas as variações têm o mesmo preço (indica que preços individuais não foram extraídos)
            $variacoesComMesmoPreco = false;
            $variacoesComMesmoPeso = false;
            if ($varCount > 1) {
                $precos = [];
                $pesos = [];
                foreach ($produto['variacoes'] as $vv) {
                    if (is_array($vv)) {
                        $precos[] = floatval($vv['valor'] ?? $vv['price'] ?? 0);
                        $pesos[] = floatval($vv['peso'] ?? $vv['weight'] ?? 0);
                    }
                }
                $precosUnicos = array_unique($precos);
                $pesosUnicos = array_unique($pesos);
                $variacoesComMesmoPreco = (count($precosUnicos) <= 1);
                $variacoesComMesmoPeso = (count($pesosUnicos) <= 1);
            }
            
            if ($pesoSuspeito || $poucasVariacoes || $variacoesComMesmoPreco || $variacoesComMesmoPeso) {
                $reason = [];
                if ($pesoSuspeito) $reason[] = 'peso_suspeito';
                if ($poucasVariacoes) $reason[] = 'poucas_variacoes';
                if ($variacoesComMesmoPreco) $reason[] = 'mesmo_preco_todas_variacoes';
                if ($variacoesComMesmoPeso) $reason[] = 'mesmo_peso_todas_variacoes';
                error_log('[ScrapingBee] Resultado incompleto (' . implode(', ', $reason) . '): variacoes=' . $varCount . ' peso=' . ($produto['peso'] ?? 0) . ' — tentando scraping complementar');
                
                // Segunda chamada ScrapingBee com ai_query focado em preços/pesos por variação
                $priceUrl = $buildUrl([
                    'ai_query' => 'For each product size/variant (Twin, Full, Queen, King, etc), extract: size name, price in USD, weight in lbs. Return as JSON array with fields: size, price, weight_lbs.',
                    'wait_browser' => 'networkidle2',
                    'timeout' => '90000',
                ]);
                
                [$priceResp, $priceCode, $priceErrno, $priceErr] = $doRequest($priceUrl, 100);
                
                $variantPriceData = null;
                if (!$priceErr && $priceCode === 200 && !empty($priceResp)) {
                    $priceJson = json_decode($priceResp, true);
                    if (is_array($priceJson)) {
                        $variantPriceData = $priceJson;
                        error_log('[ScrapingBee] Dados de preço por variação: ' . substr(json_encode($priceJson), 0, 500));
                    }
                }
                
                // Tentar mesclar preços/pesos diretamente nas variações existentes (sem re-chamar ChatGPT)
                if ($variantPriceData !== null && is_array($produto['variacoes']) && count($produto['variacoes']) > 0) {
                    $produto = $this->mergeVariantPriceData($produto, $variantPriceData);
                } else {
                    // Fallback: tentar cURL direto para sites sem JS
                    $dadosExtras = $this->extrairDadosDoHtmlViaUrl($url);
                    if (!empty($dadosExtras)) {
                        // Mesclar dados extras com dados originais e re-analisar com ChatGPT
                        $dadosCombinados = array_merge($decodedResponse, ['html_extra' => $dadosExtras]);
                        try {
                            $produtoComplementado = $this->analisarComChatGPT($dadosCombinados, $url);
                            $varCountNovo = is_array($produtoComplementado['variacoes'] ?? null) ? count($produtoComplementado['variacoes']) : 0;
                            $pesoNovo = floatval($produtoComplementado['peso'] ?? 0);
                            
                            if ($varCountNovo > $varCount || $pesoNovo > floatval($produto['peso'] ?? 0)) {
                                $produto = $produtoComplementado;
                            }
                        } catch (\Exception $e) {
                            error_log('[ScrapingBee] Erro no complemento ChatGPT: ' . $e->getMessage());
                        }
                    }
                }
                
                // Se AINDA peso <= 1.5, aplicar estimativa baseada no nome
                if (floatval($produto['peso'] ?? 0) <= 1.5) {
                    $nomeLower = strtolower((string)($produto['nome'] ?? ''));
                    $pesoEstimado = 2.0;
                    if (preg_match('/mattress|colch[aã]o/', $nomeLower)) {
                        $pesoEstimado = 35.0;
                    } elseif (preg_match('/sofa|couch|sof[aá]/', $nomeLower)) {
                        $pesoEstimado = 40.0;
                    } elseif (preg_match('/blanket|comforter|cobertor|manta|duvet/', $nomeLower)) {
                        $pesoEstimado = 3.5;
                    } elseif (preg_match('/pillow|travesseiro/', $nomeLower)) {
                        $pesoEstimado = 1.5;
                    } elseif (preg_match('/tv|monitor|television/', $nomeLower)) {
                        $pesoEstimado = 15.0;
                    } elseif (preg_match('/laptop|notebook/', $nomeLower)) {
                        $pesoEstimado = 2.5;
                    } elseif (preg_match('/phone|celular|smartphone/', $nomeLower)) {
                        $pesoEstimado = 0.3;
                    } elseif (preg_match('/shoe|sapato|tênis|sneaker|boot/', $nomeLower)) {
                        $pesoEstimado = 1.2;
                    } elseif (preg_match('/shirt|camiseta|blouse|dress|vestido|jacket|jaqueta/', $nomeLower)) {
                        $pesoEstimado = 0.5;
                    } elseif (preg_match('/toy|brinquedo/', $nomeLower)) {
                        $pesoEstimado = 1.5;
                    } elseif (preg_match('/chair|cadeira/', $nomeLower)) {
                        $pesoEstimado = 15.0;
                    } elseif (preg_match('/table|mesa|desk/', $nomeLower)) {
                        $pesoEstimado = 20.0;
                    }
                    $produto['peso'] = round($pesoEstimado * 1.15, 2);
                    error_log('[ScrapingBee] Peso estimado pelo nome: ' . $produto['peso'] . 'kg para "' . ($produto['nome'] ?? '') . '"');
                }
            }
            
            return [
                'success' => true,
                'data' => $produto
            ];
        } catch (\Exception $e) {
            if (headers_sent() === false) {
                header('X-ScrapingBee-Normalization-Error: ' . $e->getMessage());
            }
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Normaliza os dados do produto vindos do ScrapingBee
     */
    private function normalizarDadosProduto(array $data, string $urlOriginal): array {
        // Extrai dados com fallbacks
        $titulo = $this->extrairCampo($data, ['title', 'name', 'product_name']);
        $preco = $this->extrairCampo($data, ['price', 'amount', 'value']);
        $imagem = $this->extrairCampo($data, ['image', 'images', 'picture', 'photo']);
        $descricao = $this->extrairCampo($data, ['description', 'details', 'summary']);
        $peso = $this->extrairCampo($data, ['weight', 'shipping_weight']);
        $sku = $this->extrairCampo($data, ['sku', 'model', 'item_number']);
        
        // Validações obrigatórias
        if (empty($titulo)) {
            throw new \Exception('Título do produto não encontrado');
        }
        
        if (empty($preco)) {
            throw new \Exception('Preço do produto não encontrado');
        }
        
        // Limpa e formata o preço
        $precoNumerico = $this->limparPreco($preco);
        if ($precoNumerico <= 0) {
            throw new \Exception('Preço inválido: ' . $preco);
        }
        
        // Gera SKU automático se não existir
        if (empty($sku)) {
            $sku = 'SCRAP-' . strtoupper(substr(md5($urlOriginal), 0, 8));
        }
        
        // Formata o peso (estimativa se não encontrado)
        $pesoNumerico = $this->extrairPesoNumerico($peso);
        if ($pesoNumerico <= 0) {
            $pesoNumerico = 0.5; // Padrão estimado
        }
        
        // Normaliza imagem para array
        $imagensArray = $this->normalizarImagens($imagem);
        
        return [
            'sku' => $sku,
            'nome' => trim($titulo),
            'descricao' => trim($descricao ?: 'Produto obtido via scraping'),
            'valor' => $precoNumerico,
            'moeda' => 'USD', // Padrão USD
            'peso' => $pesoNumerico,
            'imagens' => $imagensArray,
            'url_original' => $urlOriginal,
            'data_scraping' => date('Y-m-d H:i:s'),
            'fonte' => 'scrapingbee'
        ];
    }
    
    /**
     * Extrai campo de dados com múltiplos nomes possíveis
     */
    private function extrairCampo(array $data, array $possiveisNomes): ?string {
        foreach ($possiveisNomes as $nome) {
            if (isset($data[$nome])) {
                $valor = $data[$nome];
                if (is_array($valor)) {
                    // Pega o primeiro valor do array
                    $valor = reset($valor);
                }
                return is_string($valor) ? trim($valor) : (string) $valor;
            }
        }
        return null;
    }
    
    /**
     * Limpa e converte preço para número
     */
    private function limparPreco(string $preco): float {
        // Remove símbolos de moeda, espaços e formatação
        $precoLimpo = preg_replace('/[^0-9.,]/', '', $preco);
        $precoLimpo = str_replace(',', '.', preg_replace('/[,.](?=.*[,.])/', '', $precoLimpo));
        
        return floatval($precoLimpo);
    }
    
    /**
     * Extrai peso numérico de strings
     */
    private function extrairPesoNumerico(?string $peso): float {
        if (empty($peso)) {
            return 0;
        }
        
        // Procura por números seguidos de unidade (kg, g, lb, oz)
        if (preg_match('/(\d+\.?\d*)\s*(kg|g|lb|oz)/i', $peso, $matches)) {
            $valor = floatval($matches[1]);
            $unidade = strtolower($matches[2]);
            
            // Converte para kg
            switch ($unidade) {
                case 'g':
                    return $valor / 1000;
                case 'lb':
                    return $valor * 0.453592;
                case 'oz':
                    return $valor * 0.0283495;
                case 'kg':
                default:
                    return $valor;
            }
        }
        
        // Se não encontrar padrão, tenta extrair apenas números
        if (preg_match('/(\d+\.?\d*)/', $peso, $matches)) {
            return floatval($matches[1]);
        }
        
        return 0;
    }
    
    /**
     * Normaliza campo de imagens para array
     */
    private function normalizarImagens($imagem): array {
        if (empty($imagem)) {
            return [];
        }
        
        if (is_string($imagem)) {
            return [$imagem];
        }
        
        if (is_array($imagem)) {
            return array_values(array_filter($imagem));
        }
        
        return [];
    }
    
    /**
     * Fallback: scraping direto via cURL quando ScrapingBee está inacessível.
     * Extrai HTML, JSON-LD e meta tags, depois envia ao ChatGPT para análise.
     */
    private function processarLinkDireto(string $url): array {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_HTTPHEADER     => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.9,pt-BR;q=0.8',
            ],
            CURLOPT_ENCODING       => '', // aceita gzip
        ]);

        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr || !$html || $httpCode >= 400) {
            return [
                'success' => false,
                'error'   => 'Não foi possível acessar o site diretamente' . ($curlErr ? ': ' . $curlErr : " (HTTP {$httpCode})"),
            ];
        }

        // Extrair dados estruturados do HTML
        $extracted = $this->extrairDadosDoHtml($html, $url);

        if (empty($extracted)) {
            return [
                'success' => false,
                'error'   => 'Não foi possível extrair dados do produto neste site.',
            ];
        }

        // Enviar ao ChatGPT para análise (mesmo fluxo do ScrapingBee)
        try {
            $produto = $this->analisarComChatGPT($extracted, $url);
            return ['success' => true, 'data' => $produto];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Faz cURL direto na URL e extrai dados estruturados do HTML.
     */
    private function extrairDadosDoHtmlViaUrl(string $url): array {
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL            => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS      => 5,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
            CURLOPT_HTTPHEADER     => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.9',
            ],
            CURLOPT_ENCODING       => '',
        ]);
        $html = curl_exec($ch);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($err || !$html) {
            return [];
        }
        return $this->extrairDadosDoHtml($html, $url);
    }

    /**
     * Extrai dados estruturados de um HTML: JSON-LD, Open Graph, meta tags e texto visível.
     */
    private function extrairDadosDoHtml(string $html, string $url): array {
        $data = ['url' => $url];

        // 1) JSON-LD (schema.org Product)
        if (preg_match_all('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si', $html, $m)) {
            foreach ($m[1] as $blob) {
                $json = json_decode(trim($blob), true);
                if (!is_array($json)) continue;
                $items = isset($json['@type']) ? [$json] : (isset($json['@graph']) ? $json['@graph'] : (array_values($json) === $json ? $json : [$json]));
                foreach ($items as $item) {
                    if (!is_array($item)) continue;
                    $type = strtolower((string)($item['@type'] ?? ''));
                    if (in_array($type, ['product', 'indivproduct', 'productgroup'], true)) {
                        $data['jsonld_product'] = $item;
                        break 2;
                    }
                }
            }
        }

        // 2) Open Graph tags
        if (preg_match_all('/<meta\s+(?:property|name)=["\']og:([^"\']+)["\']\s+content=["\']([^"\']*)["\'][^>]*>/i', $html, $og, PREG_SET_ORDER)) {
            foreach ($og as $tag) {
                $data['og_' . $tag[1]] = html_entity_decode($tag[2], ENT_QUOTES, 'UTF-8');
            }
        }

        // 3) Title
        if (preg_match('/<title[^>]*>(.*?)<\/title>/si', $html, $tm)) {
            $data['title'] = html_entity_decode(trim($tm[1]), ENT_QUOTES, 'UTF-8');
        }

        // 4) Meta description
        if (preg_match('/<meta\s+name=["\']description["\']\s+content=["\']([^"\']*)["\'][^>]*>/i', $html, $dm)) {
            $data['description'] = html_entity_decode($dm[1], ENT_QUOTES, 'UTF-8');
        }

        // 5) Preço visível no HTML (padrões comuns)
        if (preg_match_all('/\$\s?(\d{1,6}[.,]\d{2})/', $html, $prices)) {
            $data['prices_found'] = array_unique(array_slice($prices[1], 0, 10));
        }

        // 6) Imagens de produto
        $images = [];
        if (!empty($data['og_image'])) {
            $images[] = $data['og_image'];
        }
        if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*/i', $html, $imgs)) {
            foreach ($imgs[1] as $src) {
                if (count($images) >= 8) break;
                if (preg_match('/\.(svg|gif|ico)(\?|$)/i', $src)) continue;
                if (stripos($src, 'icon') !== false || stripos($src, 'logo') !== false) continue;
                if (stripos($src, 'pixel') !== false || stripos($src, '1x1') !== false) continue;
                $images[] = $src;
            }
        }
        if (!empty($images)) {
            $data['images'] = array_values(array_unique($images));
        }

        // 7) Especificações / tabelas de specs (peso, dimensões, etc.)
        $specs = [];
        // Padrão: <th>Label</th><td>Value</td> ou <td>Label</td><td>Value</td>
        if (preg_match_all('/<t[hd][^>]*>(.*?)<\/t[hd]>\s*<td[^>]*>(.*?)<\/td>/si', $html, $specMatches, PREG_SET_ORDER)) {
            foreach ($specMatches as $sm) {
                $label = trim(strip_tags($sm[1]));
                $value = trim(strip_tags($sm[2]));
                if ($label !== '' && $value !== '' && strlen($label) < 100 && strlen($value) < 200) {
                    $specs[$label] = $value;
                }
            }
        }
        // Padrão alternativo: <dt>Label</dt><dd>Value</dd>
        if (preg_match_all('/<dt[^>]*>(.*?)<\/dt>\s*<dd[^>]*>(.*?)<\/dd>/si', $html, $dtMatches, PREG_SET_ORDER)) {
            foreach ($dtMatches as $dm2) {
                $label = trim(strip_tags($dm2[1]));
                $value = trim(strip_tags($dm2[2]));
                if ($label !== '' && $value !== '' && strlen($label) < 100 && strlen($value) < 200) {
                    $specs[$label] = $value;
                }
            }
        }
        if (!empty($specs)) {
            $data['specifications'] = $specs;
        }

        // 8) Pesos encontrados no HTML (padrões: XX lb, XX lbs, XX pounds, XX kg)
        $weights = [];
        if (preg_match_all('/(\d+(?:\.\d+)?)\s*(?:lb|lbs|pounds?)\b/i', $html, $wm)) {
            foreach ($wm[0] as $i => $full) {
                $weights[] = ['raw' => trim($full), 'value_lbs' => floatval($wm[1][$i]), 'value_kg' => round(floatval($wm[1][$i]) * 0.4536, 2)];
            }
        }
        if (preg_match_all('/(\d+(?:\.\d+)?)\s*kg\b/i', $html, $wkg)) {
            foreach ($wkg[0] as $i => $full) {
                $weights[] = ['raw' => trim($full), 'value_kg' => floatval($wkg[1][$i])];
            }
        }
        if (!empty($weights)) {
            $data['weights_found'] = array_values(array_unique($weights, SORT_REGULAR));
        }

        // 9) Variações/opções visíveis (botões, selects, etc.)
        $options = [];
        // Botões de variação (ex: Costco bed size buttons)
        if (preg_match_all('/<(?:button|a|span|div)[^>]*(?:data-(?:value|option|variant|size|color)|class=["\'][^"\']*(?:option|variant|swatch|size-btn)[^"\']*["\'])[^>]*>(.*?)<\/(?:button|a|span|div)>/si', $html, $optMatches)) {
            foreach ($optMatches[1] as $opt) {
                $val = trim(strip_tags($opt));
                if ($val !== '' && strlen($val) < 50) {
                    $options[] = $val;
                }
            }
        }
        // Select options
        if (preg_match_all('/<option[^>]+value=["\']([^"\']+)["\'][^>]*>(.*?)<\/option>/si', $html, $selMatches, PREG_SET_ORDER)) {
            foreach ($selMatches as $sel) {
                $val = trim(strip_tags($sel[2]));
                if ($val !== '' && strlen($val) < 50 && strtolower($val) !== 'select') {
                    $options[] = $val;
                }
            }
        }
        if (!empty($options)) {
            $data['options_found'] = array_values(array_unique($options));
        }

        // 10) JavaScript data (inline product JSON)
        if (preg_match_all('/(?:product|item|variant)(?:Data|Info|Details|Config)\s*[:=]\s*(\{[^;]{50,5000}\})/i', $html, $jsData)) {
            foreach ($jsData[1] as $jsBlob) {
                $parsed = @json_decode($jsBlob, true);
                if (is_array($parsed) && !empty($parsed)) {
                    $data['inline_product_json'] = $parsed;
                    break;
                }
            }
        }

        // 11) Tentar extrair JSON-LD offers com preços por variação
        if (isset($data['jsonld_product']) && is_array($data['jsonld_product'])) {
            $jld = $data['jsonld_product'];
            $offers = $jld['offers'] ?? $jld['hasVariant'] ?? [];
            if (is_array($offers) && !empty($offers)) {
                // Se offers é um único objeto, wrappear em array
                if (isset($offers['@type'])) {
                    $offers = [$offers];
                }
                $variantOffers = [];
                foreach ($offers as $offer) {
                    if (!is_array($offer)) continue;
                    $name = (string)($offer['name'] ?? $offer['sku'] ?? '');
                    $price = $offer['price'] ?? $offer['lowPrice'] ?? null;
                    $weight = $offer['weight'] ?? null;
                    if ($name !== '' || $price !== null) {
                        $entry = ['name' => $name];
                        if ($price !== null) $entry['price'] = $price;
                        if ($weight !== null) $entry['weight'] = $weight;
                        // Procurar atributos adicionais
                        foreach (['size', 'color', 'sku'] as $ak) {
                            if (isset($offer[$ak])) $entry[$ak] = $offer[$ak];
                        }
                        $variantOffers[] = $entry;
                    }
                }
                if (!empty($variantOffers)) {
                    $data['variant_offers'] = $variantOffers;
                }
            }
        }

        // 12) Tentar extrair preços associados a tamanhos/variações do HTML
        // Padrão: "Twin $299.99" ou "King - $499.99" ou data-price="499.99"
        $variantPrices = [];
        if (preg_match_all('/(?:Twin|Full|Queen|King|Cal King|Twin XL|Double|Single)[^$\n]{0,30}\$\s?(\d{1,6}[.,]\d{2})/i', $html, $vpMatches, PREG_SET_ORDER)) {
            foreach ($vpMatches as $vpm) {
                $variantPrices[] = ['context' => trim(substr($vpm[0], 0, 80)), 'price' => $vpm[1]];
            }
        }
        if (!empty($variantPrices)) {
            $data['variant_prices_found'] = $variantPrices;
        }

        return $data;
    }

    /**
     * Resolve o IP de app.scrapingbee.com usando DNS público (Google/Cloudflare).
     * Evita falhas quando o resolver DNS local do servidor está com problema.
     */
    private function resolverDnsScrapingBee(bool $forceExternal = false): ?string {
        $host = 'app.scrapingbee.com';

        // 1) Cache em arquivo (válido por 1h) — evita resolver DNS toda vez
        $cacheFile = sys_get_temp_dir() . '/scrapingbee_ip_cache.json';
        if (!$forceExternal && file_exists($cacheFile)) {
            $cache = @json_decode((string)@file_get_contents($cacheFile), true);
            if (is_array($cache) && !empty($cache['ip']) && ($cache['ts'] ?? 0) > (time() - 3600)) {
                return $cache['ip'];
            }
        }

        // 2) Tentar resolver via sistema operacional (rápido)
        if (!$forceExternal) {
            $ip = @gethostbyname($host);
            if ($ip !== $host && filter_var($ip, FILTER_VALIDATE_IP)) {
                @file_put_contents($cacheFile, json_encode(['ip' => $ip, 'ts' => time()]));
                return $ip;
            }
        }

        // 3) DNS-over-HTTPS usando IPs hardcoded (bypassa DNS local completamente)
        //    Google DNS: 8.8.8.8, Cloudflare DNS: 1.1.1.1
        $dohEndpoints = [
            ['ip' => '8.8.8.8', 'host' => 'dns.google', 'path' => '/resolve?name=' . urlencode($host) . '&type=A'],
            ['ip' => '1.1.1.1', 'host' => 'cloudflare-dns.com', 'path' => '/dns-query?name=' . urlencode($host) . '&type=A'],
        ];

        foreach ($dohEndpoints as $ep) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL            => 'https://' . $ep['host'] . $ep['path'],
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT        => 5,
                CURLOPT_CONNECTTIMEOUT => 3,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_HTTPHEADER     => ['Accept: application/dns-json'],
                // Forçar IP hardcoded para o servidor DoH (bypassa DNS local)
                CURLOPT_RESOLVE        => [$ep['host'] . ':443:' . $ep['ip']],
            ]);
            $resp = curl_exec($ch);
            $err  = curl_errno($ch);
            curl_close($ch);

            if ($err || !$resp) continue;

            $json = json_decode($resp, true);
            if (!is_array($json)) continue;

            $answers = $json['Answer'] ?? [];
            foreach ($answers as $ans) {
                $type = (int)($ans['type'] ?? 0);
                $data = (string)($ans['data'] ?? '');
                if ($type === 1 && filter_var($data, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                    @file_put_contents($cacheFile, json_encode(['ip' => $data, 'ts' => time()]));
                    return $data;
                }
            }
        }

        // 4) dns_get_record nativo do PHP
        try {
            $records = @dns_get_record($host, DNS_A);
            if (is_array($records)) {
                foreach ($records as $rec) {
                    if (!empty($rec['ip']) && filter_var($rec['ip'], FILTER_VALIDATE_IP)) {
                        @file_put_contents($cacheFile, json_encode(['ip' => $rec['ip'], 'ts' => time()]));
                        return $rec['ip'];
                    }
                }
            }
        } catch (\Throwable $e) {
        }

        return null;
    }


    /**
     * Obtém a API Key do ScrapingBee
     */
    private function getScriptBeeApiKey(): ?string {
        try {
            $db = \Config\Database::getConnection();
            $stmt = $db->prepare('SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1');
            $stmt->execute(['scrapingbee_api_key']);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            $apiKey = $row ? $row['valor'] : null;
            
            // Log no console via header (será capturado pelo JavaScript)
            if (headers_sent() === false) {
                header('X-ScrapingBee-Debug: API Key ' . ($apiKey ? 'found' : 'not found'));
                header('X-ScrapingBee-Data: ' . json_encode([
                    'api_key_found' => !empty($apiKey),
                    'api_key_length' => $apiKey ? strlen($apiKey) : 0
                ]));
            }
            
            return $apiKey;
        } catch (\Exception $e) {
            if (headers_sent() === false) {
                header('X-ScrapingBee-Error: ' . $e->getMessage());
            }
            return null;
        }
    }
    
    /**
     * Exibe a página de orçamento formalizado
     */
    public function orcamento(Request $request) {
        session_start();

        $orcamentoIdParam = (int) $request->getParam('orcamento_id', 0);
        $tokenParam = (string) $request->getParam('token', '');
        $dbOrcamento = null;

        if ($orcamentoIdParam > 0 || $tokenParam !== '') {
            try {
                $orcModel = new AssessoriaOrcamento();
                if ($orcamentoIdParam > 0) {
                    $dbOrcamento = $orcModel->find($orcamentoIdParam);
                } elseif ($tokenParam !== '') {
                    $dbOrcamento = $orcModel->findByToken($tokenParam);
                }
                if (is_array($dbOrcamento) && !empty($dbOrcamento['id'])) {
                    $_SESSION['assessoria_orcamento_id'] = (int) $dbOrcamento['id'];
                    $_SESSION['assessoria_orcamento_token'] = (string) ($dbOrcamento['public_token'] ?? '');
                    if (!empty($dbOrcamento['job_id'])) {
                        $_SESSION['assessoria_job_id'] = (string) $dbOrcamento['job_id'];
                    }
                }
            } catch (\Exception $e) {
                $dbOrcamento = null;
            }
        }

        $jobId = (string) $request->getParam('job_id', '');
        if ($jobId === '') {
            $jobId = (string) ($_SESSION['assessoria_job_id'] ?? '');
        }

        if (!isset($_SESSION['assessoria_orcamento']) && $jobId === '') {
            header('Location: /assessoria');
            exit;
        }

        $job = null;
        if ($jobId !== '') {
            $job = $this->readJobFile($jobId);
            if (is_array($job) && (($job['status'] ?? '') === 'done')) {
                if (!isset($_SESSION['assessoria_orcamento']) || !is_array($_SESSION['assessoria_orcamento'])) {
                    $_SESSION['assessoria_orcamento'] = [
                        'produtos' => [],
                        'erros' => [],
                        'data_criacao' => date('Y-m-d H:i:s')
                    ];
                }
                $_SESSION['assessoria_orcamento']['produtos'] = $job['produtos'] ?? [];
                $_SESSION['assessoria_orcamento']['erros'] = $job['erros'] ?? [];
            }
        }

        $orcamento = $_SESSION['assessoria_orcamento'] ?? ['produtos' => [], 'erros' => [], 'data_criacao' => date('Y-m-d H:i:s')];

        if (is_array($dbOrcamento) && !empty($dbOrcamento['id'])) {
            $prodDb = $this->parseDbJson($dbOrcamento['produtos_json'] ?? null);
            $errDb = $this->parseDbJson($dbOrcamento['erros_json'] ?? null);
            if (!empty($prodDb) || !empty($errDb)) {
                $orcamento['produtos'] = $prodDb;
                $orcamento['erros'] = $errDb;
                $orcamento['data_criacao'] = (string) ($dbOrcamento['created_at'] ?? $orcamento['data_criacao']);
            }
        }
        
        // Calcula totais usando taxas existentes
        $totais = $this->calcularTotaisOrcamento($orcamento['produtos']);
        
        $this->view('assessoria/orcamento', [
            'orcamento' => $orcamento,
            'totais' => $totais,
            'job_id' => $jobId,
            'job' => $job,
            'orcamento_id' => (is_array($dbOrcamento) && !empty($dbOrcamento['id'])) ? (int) $dbOrcamento['id'] : (int) ($_SESSION['assessoria_orcamento_id'] ?? 0)
        ]);
    }
    
    /**
     * Calcula totais do orçamento reutilizando taxas existentes
     */
    private function calcularTotaisOrcamento(array $produtos): array {
        $subtotal = 0;
        $pesoTotal = 0;
        
        foreach ($produtos as $produto) {
            $valor = floatval($produto['valor'] ?? 0);
            $peso = floatval($produto['peso'] ?? 0);

            // Se preço base é suspeitamente baixo e existem variações com preço real,
            // usar o menor preço real das variações como referência
            if ($valor < 2.0 && !empty($produto['variacoes']) && is_array($produto['variacoes'])) {
                $varPrecos = [];
                foreach ($produto['variacoes'] as $v) {
                    if (is_array($v) && isset($v['valor']) && floatval($v['valor']) >= 2.0) {
                        $varPrecos[] = floatval($v['valor']);
                    }
                }
                if (!empty($varPrecos)) {
                    $valor = min($varPrecos);
                }
            }

            $subtotal += $valor;
            $pesoTotal += $peso;
        }
        
        // Reutiliza funções de cálculo existentes
        $pesoArredondado = ceil((float) $pesoTotal);
        $taxaServico = $this->getTaxaServicoPorKg() * $pesoArredondado;
        $frete = $this->calcularFrete($subtotal, $pesoTotal);
        $impostos = $this->calcularImpostos($subtotal);

        $pixPct = $this->getPixDescontoTaxaServicoPercent();
        $taxaServicoPix = ($pixPct > 0) ? max(0.0, $taxaServico * (1.0 - ($pixPct / 100.0))) : $taxaServico;
        $total = $subtotal + $taxaServico + $frete + $impostos;
        $totalPix = $subtotal + $taxaServicoPix + $frete + $impostos;
        
        return [
            'subtotal' => $subtotal,
            'peso_total' => $pesoTotal,
            'taxa_servico' => $taxaServico,
            'pix_desconto_taxa_servico_percent' => $pixPct,
            'taxa_servico_pix' => $taxaServicoPix,
            'frete' => $frete,
            'impostos' => $impostos,
            'total' => $total,
            'total_pix' => $totalPix,
        ];
    }

    private function getPixDescontoTaxaServicoPercent(): float {
        try {
            $db = \Config\Database::getConnection();
            $stmt = $db->prepare('SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1');
            $stmt->execute(['pagamentos_pix_desconto_taxa_servico_percent']);
            $v = $stmt->fetchColumn();
            if ($v === false || $v === null || (string) $v === '') {
                return 0.0;
            }
            $p = (float) str_replace(',', '.', (string) $v);
            if ($p < 0) $p = 0.0;
            if ($p > 100) $p = 100.0;
            return $p;
        } catch (\Exception $e) {
            return 0.0;
        }
    }
    
    /**
     * Obtém taxa de serviço por kg (reutiliza lógica existente)
     */
    private function getTaxaServicoPorKg(): float {
        try {
            $db = \Config\Database::getConnection();
            $stmt = $db->prepare('SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1');
            $stmt->execute(['taxa_servico_usd_por_kg']);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            return floatval($row ? $row['valor'] : 39);
        } catch (\Exception $e) {
            return 39.0;
        }
    }
    
    /**
     * Calcula frete (reutiliza lógica do CarrinhoController)
     */
    private function calcularFrete(float $subtotal, float $pesoTotal): float {
        try {
            $db = \Config\Database::getConnection();
            
            // Verifica se cálculo automático está ativo
            $stmt = $db->prepare('SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1');
            $stmt->execute(['entrega_calcular_automatico']);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            $calcularAutomatico = ($row && ($row['valor'] === '1' || strtolower($row['valor']) === 'true'));
            
            if (!$calcularAutomatico) {
                return 0.0;
            }
            
            // Verifica frete grátis
            $stmt = $db->prepare('SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1');
            $stmt->execute(['entrega_frete_gratis_acima']);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            $freteGratisAcima = floatval($row ? $row['valor'] : 0);
            
            if ($freteGratisAcima > 0 && $subtotal >= $freteGratisAcima) {
                return 0.0;
            }
            
            // Calcula frete por kg
            $stmt = $db->prepare('SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1');
            $stmt->execute(['entrega_frete_padrao']);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            $fretePorKg = floatval($row ? $row['valor'] : 15);
            
            if ($fretePorKg <= 0) {
                return 0.0;
            }
            
            $pesoArredondado = ceil($pesoTotal);
            return $fretePorKg * $pesoArredondado;
            
        } catch (\Exception $e) {
            return 0.0;
        }
    }
    
    /**
     * Calcula impostos (reutiliza configurações existentes)
     */
    private function calcularImpostos(float $subtotal): float {
        try {
            $db = \Config\Database::getConnection();
            
            // ICMS
            $stmt = $db->prepare('SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1');
            $stmt->execute(['icms_aliquota']);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            $icms = floatval($row ? $row['valor'] : 60);
            
            // IPI
            $stmt = $db->prepare('SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1');
            $stmt->execute(['ipi_aliquota']);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            $ipi = floatval($row ? $row['valor'] : 20);
            
            return ($subtotal * $icms / 100) + ($subtotal * $ipi / 100);
            
        } catch (\Exception $e) {
            return 0.0;
        }
    }
    
    /**
     * Adiciona produtos do orçamento ao carrinho existente
     */
    public function adicionarAoCarrinho(Request $request) {
        header('Content-Type: application/json');
        
        session_start();

        $orcamentoId = (int) ($_SESSION['assessoria_orcamento_id'] ?? 0);
        try {
            $body0 = $request->getBody();
            if (is_array($body0) && isset($body0['orcamento_id'])) {
                $orcamentoId = (int) $body0['orcamento_id'];
            }
        } catch (\Exception $e) {
        }

        // Regra 15 minutos: se orçamento persistido estiver expirado, reprocessar antes de permitir carrinho
        if ($orcamentoId > 0) {
            try {
                $orcModel = new AssessoriaOrcamento();
                $row = $orcModel->find($orcamentoId);
                if (is_array($row) && !empty($row['id'])) {
                    $expired = $this->isOlderThanMinutes($row['last_processed_at'] ?? null, 15);
                    if ($expired) {
                        session_write_close();

                        echo json_encode([
                            'success' => false,
                            'message' => 'Orçamento expirado. Refaça o processamento para atualizar valores e variações.',
                            'redirect' => '/assessoria/reprocessar?orcamento_id=' . $orcamentoId
                        ]);
                        return;
                    }
                }
            } catch (\Exception $e) {
            }
        }
        
        if (!isset($_SESSION['assessoria_orcamento'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Orçamento não encontrado'
            ]);
            return;
        }
        
        $body = $request->getBody();
        $termosAceitos = $body['termos_aceitos'] ?? false;
        $produtosSelecionados = $body['produtos_selecionados'] ?? [];
        
        if (!$termosAceitos) {
            echo json_encode([
                'success' => false,
                'message' => 'É necessário aceitar os termos para prosseguir'
            ]);
            return;
        }
        
        if (empty($produtosSelecionados)) {
            echo json_encode([
                'success' => false,
                'message' => 'Nenhum produto selecionado'
            ]);
            return;
        }
        
        try {
            // Inicializa carrinho se não existir
            if (!isset($_SESSION['carrinho'])) {
                $_SESSION['carrinho'] = [];
            }
            
            $orcamento = $_SESSION['assessoria_orcamento'];
            $produtosAdicionados = 0;

            // Detectar se o usuário está logado para persistir no DB
            $auth = new AuthService();
            $uid = $auth->estaLogado() ? (int) ($auth->getUsuarioLogado()['id'] ?? 0) : 0;
            $cartId = 0;
            $carrinhoModel = null;
            if ($uid > 0) {
                try {
                    $carrinhoModel = new \App\Models\Carrinho();
                    $cart = $carrinhoModel->getOrCreateCarrinho($uid, null, 'BRL');
                    $cartId = is_array($cart) ? (int) ($cart['id'] ?? 0) : (int) $cart;
                } catch (\Throwable $e) {
                    $cartId = 0;
                }
            }
            
            foreach ($produtosSelecionados as $produtoIndex) {
                $index = null;
                $variacaoId = null;
                $quantidade = 1;
                if (is_array($produtoIndex)) {
                    $index = $produtoIndex['index'] ?? null;
                    $variacaoId = $produtoIndex['variacao_id'] ?? null;
                    $quantidade = (int) ($produtoIndex['quantidade'] ?? 1);
                } else {
                    $index = $produtoIndex;
                }

                if ($quantidade <= 0) {
                    $quantidade = 1;
                }

                if ($index !== null && isset($orcamento['produtos'][$index])) {
                    $produto = $orcamento['produtos'][$index];

                    // Aplicar variação escolhida (preço/peso) se existir
                    if ($variacaoId !== null && isset($produto['variacoes']) && is_array($produto['variacoes'])) {
                        foreach ($produto['variacoes'] as $v) {
                            if (is_array($v) && (string) ($v['id'] ?? '') === (string) $variacaoId) {
                                if (isset($v['valor']) && $v['valor'] !== null && floatval($v['valor']) >= 2.0) {
                                    $produto['valor'] = floatval($v['valor']);
                                }
                                if (isset($v['peso']) && floatval($v['peso']) > 0) {
                                    $produto['peso'] = floatval($v['peso']);
                                }

                                $label = (string) ($v['label'] ?? '');
                                if ($label !== '') {
                                    $produto['nome'] = (string) ($produto['nome'] ?? '') . ' (' . $label . ')';
                                }
                                $produto['variacao_selecionada'] = [
                                    'id' => (string) ($v['id'] ?? ''),
                                    'label' => $label,
                                    'atributos' => is_array($v['atributos'] ?? null) ? $v['atributos'] : []
                                ];
                                break;
                            }
                        }
                    }

                    $produtoId = $this->criarOuReutilizarProdutoNoSistema($produto);

                    $itemKey = (string) $produtoId;
                    if (!empty($produto['variacao_selecionada']['id'])) {
                        $itemKey = $itemKey . ':' . (string) $produto['variacao_selecionada']['id'];
                    }

                    // Persistir no DB para usuários logados
                    if ($cartId > 0 && $carrinhoModel !== null) {
                        try {
                            $varDesc = null;
                            if (!empty($produto['variacao_selecionada']['label'])) {
                                $varDesc = (string) $produto['variacao_selecionada']['label'];
                            }
                            $carrinhoModel->adicionarItem($cartId, (int) $produtoId, (int) $quantidade, null, $varDesc);
                        } catch (\Throwable $e) {
                            error_log('[Assessoria] Erro ao persistir item no carrinho DB: ' . $e->getMessage());
                        }
                    }

                    if (isset($_SESSION['carrinho'][$itemKey])) {
                        $_SESSION['carrinho'][$itemKey]['quantidade'] += $quantidade;
                        $preco = floatval($_SESSION['carrinho'][$itemKey]['preco_unitario'] ?? 0);
                        $_SESSION['carrinho'][$itemKey]['subtotal'] = $_SESSION['carrinho'][$itemKey]['quantidade'] * $preco;
                    } else {
                        $preco = floatval($produto['valor'] ?? 0);
                        $_SESSION['carrinho'][$itemKey] = [
                            'produto_id' => $produtoId,
                            'nome' => (string) ($produto['nome'] ?? ''),
                            'preco_unitario' => $preco,
                            'quantidade' => $quantidade,
                            'subtotal' => $quantidade * $preco
                        ];

                        // Persistir URL original informada pelo usuário
                        if (!empty($produto['url_original'])) {
                            $_SESSION['carrinho'][$itemKey]['url_original'] = (string) $produto['url_original'];
                        }

                        if (!empty($produto['variacao_selecionada'])) {
                            $_SESSION['carrinho'][$itemKey]['variacao'] = $produto['variacao_selecionada'];
                        }
                    }
                    
                    $produtosAdicionados++;
                }
            }
            
            // Limpa orçamento da sessão
            unset($_SESSION['assessoria_orcamento']);

            if ($orcamentoId > 0) {
                $_SESSION['checkout_assessoria_orcamento_id'] = $orcamentoId;
            }
            
            echo json_encode([
                'success' => true,
                'message' => "{$produtosAdicionados} produtos adicionados ao carrinho",
                'redirect' => '/carrinho'
            ]);
            
        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'message' => 'Erro ao adicionar produtos ao carrinho: ' . $e->getMessage()
            ]);
        }
    }

    private function criarOuReutilizarProdutoNoSistema(array $produto): int {
        $db = \Config\Database::getConnection();

        $nome = trim((string) ($produto['nome'] ?? ''));
        if ($nome === '') {
            throw new \Exception('Produto sem nome');
        }

        $sku = trim((string) ($produto['sku'] ?? ''));
        if ($sku === '') {
            $sku = 'ASS-' . substr(md5($nome . '|' . ((string) ($produto['url_original'] ?? ''))), 0, 10);
        }

        try {
            $stmt = $db->prepare('SELECT id FROM produtos WHERE sku = ? ORDER BY id DESC LIMIT 1');
            $stmt->execute([$sku]);
            $existingId = $stmt->fetchColumn();
            if ($existingId) {
                // Atualizar produto existente com dados novos (peso, preço, variações podem ter mudado)
                try {
                    $stmtUpd = $db->prepare('UPDATE produtos SET price = ?, weight = ?, description = ?, updated_at = NOW() WHERE id = ?');
                    $stmtUpd->execute([
                        floatval($produto['valor'] ?? 0),
                        floatval($produto['peso'] ?? 1.0),
                        (string) ($produto['descricao'] ?? ''),
                        (int) $existingId
                    ]);
                } catch (\Exception $e) {
                    error_log('[Assessoria] Erro ao atualizar produto existente #' . $existingId . ': ' . $e->getMessage());
                }
                return (int) $existingId;
            }
        } catch (\Exception $e) {
        }

        $preco = floatval($produto['valor'] ?? 0);
        $peso = floatval($produto['peso'] ?? 1.0);
        $descricao = (string) ($produto['descricao'] ?? '');

        $categoriaAssessoriaId = $this->getOrCreateCategoriaAssessoriaId();
        $lojaNome = $this->ensureLojaFromUrl((string) ($produto['url_original'] ?? ''));

        $produtoModel = new Produto();
        $newId = (int) $produtoModel->create([
            'name' => $nome,
            'sku' => $sku,
            'description' => $descricao,
            'price' => $preco,
            'cost_price' => $preco,
            'weight' => $peso,
            'status' => 'published',
            'stock' => 999999,
            'category_id' => $categoriaAssessoriaId,
            'images' => $produto['imagens'] ?? [],
            'variations' => $produto['variacoes'] ?? [],
            'attributes' => [
                'fonte' => 'assessoria',
                'url_original' => (string) ($produto['url_original'] ?? '')
            ]
        ]);

        if ($newId <= 0) {
            throw new \Exception('Falha ao criar produto no sistema');
        }

        // Preencher campo loja (string) quando existir na tabela
        if (!empty($lojaNome)) {
            try {
                $colsProd = [];
                try {
                    $stmtCols = $db->query('DESCRIBE produtos');
                    $colsProd = $stmtCols ? $stmtCols->fetchAll(\PDO::FETCH_COLUMN) : [];
                } catch (\Exception $e) {
                    $colsProd = [];
                }

                if (is_array($colsProd) && in_array('loja', $colsProd, true)) {
                    $stmtUpdLoja = $db->prepare('UPDATE produtos SET loja = ?, updated_at = NOW() WHERE id = ?');
                    $stmtUpdLoja->execute([(string) $lojaNome, (int) $newId]);
                }
            } catch (\Exception $e) {
            }
        }

        // Persistir imagens também em produto_fotos e definir foto_principal (admin/listagem usa isso)
        $imagens = $produto['imagens'] ?? [];
        if (is_array($imagens) && !empty($imagens)) {
            $imagens = array_values(array_filter($imagens, function($u) {
                return is_string($u) && trim($u) !== '';
            }));

            if (!empty($imagens)) {
                try {
                    // Definir capa do produto se o método existir
                    if (method_exists($produtoModel, 'updateFotoPrincipal')) {
                        $produtoModel->updateFotoPrincipal($newId, (string) $imagens[0]);
                    } else {
                        // Fallback: tentar update direto
                        $stmtCover = $db->prepare('UPDATE produtos SET foto_principal = ?, updated_at = NOW() WHERE id = ?');
                        $stmtCover->execute([(string) $imagens[0], $newId]);
                    }
                } catch (\Exception $e) {
                }

                try {
                    $fotoModel = new \App\Models\ProdutoFoto();
                    foreach ($imagens as $idx => $url) {
                        $isPrincipal = ($idx === 0);
                        $fotoModel->adicionarFoto($newId, (string) $url, (string) $url, null, $isPrincipal);
                    }
                } catch (\Exception $e) {
                }
            }
        }

        return $newId;
    }
    
    /**
     * Analisa dados brutos do ScrapingBee usando ChatGPT
     */
    private function analisarComChatGPT(array $dadosBrutos, string $urlOriginal): array {
        $chatGptApiKey = $this->getChatGPTApiKey();
        
        if (!$chatGptApiKey) {
            if (headers_sent() === false) {
                header('X-ChatGPT-Error: API Key not configured');
            }
            throw new \Exception('API Key do ChatGPT não configurada');
        }
        
        $reduced = $this->reduceScrapingBeePayload($dadosBrutos);
        error_log('[ChatGPT] Payload keys for prompt: ' . json_encode(array_keys($reduced)) . ' | Total size: ' . strlen(json_encode($reduced)));
        $prompt = $this->gerarPromptChatGPT($reduced, $urlOriginal);
        
        if (headers_sent() === false) {
            header('X-ChatGPT-Prompt-Length: ' . strlen($prompt));
        }
        
        // Tentativa 1: JSON-only (quando suportado)
        [$response, $httpCode, $curlError] = $this->callChatGPT($chatGptApiKey, $prompt, true);
        // Fallback: modelo não suportou response_format
        if ($httpCode === 400 && is_string($response) && (stripos($response, 'response_format') !== false || stripos($response, 'json_object') !== false)) {
            [$response, $httpCode, $curlError] = $this->callChatGPT($chatGptApiKey, $prompt, false);
        }
        
        if (headers_sent() === false) {
            header('X-ChatGPT-HTTP-Code: ' . $httpCode);
            header('X-ChatGPT-Response-Length: ' . strlen($response));
        }
        
        if ($curlError) {
            if (headers_sent() === false) {
                header('X-ChatGPT-CURL-Error: ' . $curlError);
            }
            
            // Mensagem amigável para timeout
            $errorMessage = 'Erro na requisição ChatGPT: ' . $curlError;
            if (strpos($curlError, 'timeout') !== false) {
                $errorMessage = 'O serviço de análise demorou muito para responder. Tente novamente.';
            }
            
            throw new \Exception($errorMessage);
        }
        
        if ($httpCode !== 200) {
            if (headers_sent() === false) {
                header('X-ChatGPT-HTTP-Error: ' . $this->headerSafeValue(substr((string) $response, 0, 500), 500));
            }
            throw new \Exception('Erro HTTP ChatGPT ' . $httpCode . ': ' . substr($response, 0, 500));
        }
        
        $decodedResponse = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            if (headers_sent() === false) {
                header('X-ChatGPT-JSON-Error: ' . json_last_error_msg());
            }
            throw new \Exception('Resposta ChatGPT não é JSON válido: ' . json_last_error_msg());
        }
        
        $content = $decodedResponse['choices'][0]['message']['content'] ?? '';
        
        if (headers_sent() === false) {
            header('X-ChatGPT-Content-Length: ' . strlen($content));
            header('X-ChatGPT-Content-Prefix: ' . $this->headerSafeValue(substr((string) $content, 0, 200), 200));
        }
        
        // Tentar fazer parse do JSON retornado pelo ChatGPT (com correções)
        try {
            $produtoData = $this->decodeJsonResilient((string) $content);
        } catch (\Exception $e) {
            error_log('[ChatGPT] JSON parse failed: ' . $e->getMessage() . ' | Content prefix: ' . substr((string) $content, 0, 200));
            
            // Retry 1: prompt menor para evitar truncamento / syntax error
            // Usar apenas dados essenciais (sem html_extra que pode ser muito grande)
            $retryData = $reduced;
            unset($retryData['html_extra'], $retryData['description'], $retryData['details'], $retryData['features']);
            $retryReduced = [
                'hint' => 'extract only essential fields: nome, valor, peso, variacoes. Keep response SHORT.',
                'url' => $urlOriginal,
                'data' => $retryData
            ];
            $retryPrompt = $this->gerarPromptChatGPT($retryReduced, $urlOriginal);
            [$resp2, $code2, $err2] = $this->callChatGPT($chatGptApiKey, $retryPrompt, true);
            if ($code2 === 400 && is_string($resp2) && (stripos($resp2, 'response_format') !== false || stripos($resp2, 'json_object') !== false)) {
                [$resp2, $code2, $err2] = $this->callChatGPT($chatGptApiKey, $retryPrompt, false);
            }
            if (!$err2 && $code2 === 200) {
                $decoded2 = json_decode($resp2, true);
                $content2 = $decoded2['choices'][0]['message']['content'] ?? '';
                try {
                    $produtoData = $this->decodeJsonResilient((string) $content2);
                } catch (\Exception $e2) {
                    if (headers_sent() === false) {
                        header('X-ChatGPT-Parse-Error: ' . $this->headerSafeValue($e2->getMessage(), 200));
                        header('X-ChatGPT-Raw-Content: ' . $this->headerSafeValue(substr((string) $content2, 0, 500), 500));
                    }
                    $fallback = $this->buildProdutoFallbackFromScrapingBee($dadosBrutos, $urlOriginal);
                    if ($fallback !== null) {
                        $produtoData = $fallback;
                    } else {
                        throw $e2;
                    }
                }
            } else {
                if (headers_sent() === false) {
                    header('X-ChatGPT-Parse-Error: ' . $this->headerSafeValue($e->getMessage(), 200));
                    header('X-ChatGPT-Raw-Content: ' . $this->headerSafeValue(substr((string) $content, 0, 500), 500));
                }
                $fallback = $this->buildProdutoFallbackFromScrapingBee($dadosBrutos, $urlOriginal);
                if ($fallback !== null) {
                    $produtoData = $fallback;
                } else {
                    throw $e;
                }
            }
        }
        
        // Validar campos obrigatórios
        $camposObrigatorios = ['nome', 'valor', 'peso', 'descricao', 'imagens'];
        foreach ($camposObrigatorios as $campo) {
            if (!isset($produtoData[$campo]) || empty($produtoData[$campo])) {
                if (headers_sent() === false) {
                    header('X-ChatGPT-Missing-Field: ' . $campo);
                }
                // Fallback específico para valor
                if ($campo === 'valor') {
                    $fallbackValor = $this->extractValorFromScrapingBee($dadosBrutos);
                    if ($fallbackValor !== null) {
                        $produtoData['valor'] = $fallbackValor;
                        continue;
                    }

                    // 2ª chamada ao ChatGPT apenas para retornar {"valor": number}
                    $p2 = "A partir dos dados abaixo, retorne APENAS JSON válido com o campo \"valor\" (number, USD). Sem texto.\n\nDADOS:\n" . json_encode($reduced);
                    [$r3, $c3, $e3] = $this->callChatGPT($chatGptApiKey, $p2, true);
                    if ($c3 === 400 && is_string($r3) && (stripos($r3, 'response_format') !== false || stripos($r3, 'json_object') !== false)) {
                        [$r3, $c3, $e3] = $this->callChatGPT($chatGptApiKey, $p2, false);
                    }
                    if (!$e3 && $c3 === 200) {
                        $d3 = json_decode($r3, true);
                        $c3t = $d3['choices'][0]['message']['content'] ?? '';
                        try {
                            $only = $this->decodeJsonResilient((string) $c3t);
                            if (isset($only['valor']) && floatval($only['valor']) > 0) {
                                $produtoData['valor'] = floatval($only['valor']);
                                continue;
                            }
                        } catch (\Exception $e4) {
                        }
                    }
                }

                // Fallback para imagens: tentar extrair 1 url
                if ($campo === 'imagens') {
                    $img = $this->extractFirstImageUrl($dadosBrutos);
                    if ($img) {
                        $produtoData['imagens'] = [$img];
                        continue;
                    }
                }

                // Fallback peso: estimar baseado no nome do produto (nunca usar 1kg genérico)
                if ($campo === 'peso') {
                    $nomeLower = strtolower((string)($produtoData['nome'] ?? ''));
                    $pesoEstimado = 2.0; // default genérico mínimo
                    if (preg_match('/mattress|colch[aã]o/', $nomeLower)) {
                        $pesoEstimado = 35.0;
                    } elseif (preg_match('/sofa|couch|sof[aá]/', $nomeLower)) {
                        $pesoEstimado = 40.0;
                    } elseif (preg_match('/blanket|comforter|cobertor|manta|duvet/', $nomeLower)) {
                        $pesoEstimado = 3.5;
                    } elseif (preg_match('/pillow|travesseiro/', $nomeLower)) {
                        $pesoEstimado = 1.5;
                    } elseif (preg_match('/tv|monitor|television/', $nomeLower)) {
                        $pesoEstimado = 15.0;
                    } elseif (preg_match('/laptop|notebook/', $nomeLower)) {
                        $pesoEstimado = 2.5;
                    } elseif (preg_match('/phone|celular|smartphone/', $nomeLower)) {
                        $pesoEstimado = 0.3;
                    } elseif (preg_match('/shoe|sapato|tênis|sneaker|boot/', $nomeLower)) {
                        $pesoEstimado = 1.2;
                    } elseif (preg_match('/shirt|camiseta|blouse|dress|vestido|jacket|jaqueta/', $nomeLower)) {
                        $pesoEstimado = 0.5;
                    } elseif (preg_match('/toy|brinquedo/', $nomeLower)) {
                        $pesoEstimado = 1.5;
                    } elseif (preg_match('/chair|cadeira/', $nomeLower)) {
                        $pesoEstimado = 15.0;
                    } elseif (preg_match('/table|mesa|desk/', $nomeLower)) {
                        $pesoEstimado = 20.0;
                    }
                    // Margem de 15%
                    $produtoData['peso'] = round($pesoEstimado * 1.15, 2);
                    continue;
                }

                // Fallback para descricao: tentar ScrapingBee e/ou gerar mínima baseada no nome
                if ($campo === 'descricao') {
                    $desc = $this->extractDescricaoFromScrapingBee($dadosBrutos);
                    if (trim((string) $desc) !== '') {
                        $produtoData['descricao'] = $desc;
                        continue;
                    }
                    if (!empty($produtoData['nome'])) {
                        $produtoData['descricao'] = 'Produto importado automaticamente: ' . (string) $produtoData['nome'];
                        continue;
                    }
                }

                throw new \Exception("Campo obrigatório '{$campo}' não encontrado ou vazio");
            }
        }

        // Garantir variacoes (se ChatGPT não trouxe)
        if (!isset($produtoData['variacoes']) || !is_array($produtoData['variacoes'])) {
            $produtoData['variacoes'] = $this->extractVariacoesFromScrapingBee($dadosBrutos);
        }

        // Log variações ANTES da normalização para debug
        if (is_array($produtoData['variacoes'])) {
            foreach (array_slice($produtoData['variacoes'], 0, 3) as $vi => $vv) {
                if (is_array($vv)) {
                    error_log('[ChatGPT] PRE-NORM Var[' . $vi . ']: ' . json_encode(array_intersect_key($vv, array_flip(['label', 'valor', 'peso', 'price', 'weight', 'id']))));
                }
            }
        }

        // Normalizar variacoes para o formato esperado pelo orçamento (atributos)
        if (isset($produtoData['variacoes']) && is_array($produtoData['variacoes'])) {
            $produtoData['variacoes'] = $this->normalizeVariacoesForOrcamento($produtoData['variacoes'], $dadosBrutos);
        }

        // Pós-processamento: se todas as variações têm o mesmo peso (ou null), estimar por tamanho
        if (is_array($produtoData['variacoes']) && count($produtoData['variacoes']) > 1) {
            $allSamePeso = true;
            $firstPeso = null;
            foreach ($produtoData['variacoes'] as $vv) {
                $vPeso = $vv['peso'] ?? null;
                if ($firstPeso === null) {
                    $firstPeso = $vPeso;
                } elseif ($vPeso !== $firstPeso) {
                    $allSamePeso = false;
                    break;
                }
            }
            
            if ($allSamePeso) {
                $nomeLower = strtolower((string)($produtoData['nome'] ?? ''));
                $isColchao = (bool) preg_match('/mattress|colch[aã]o/', $nomeLower);
                
                // Mapa de pesos estimados por tamanho de colchão (kg)
                $sizeWeights = [
                    'twin' => 27.0, 'twin xl' => 29.0,
                    'full' => 32.0, 'double' => 32.0,
                    'queen' => 38.0,
                    'king' => 45.0, 'cal king' => 45.0, 'california king' => 45.0,
                ];
                
                // Para produtos genéricos com tamanhos (S/M/L/XL), escalar proporcionalmente
                $genericSizeScale = [
                    'xs' => 0.8, 'extra small' => 0.8,
                    's' => 0.85, 'small' => 0.85,
                    'm' => 1.0, 'medium' => 1.0, 'regular' => 1.0,
                    'l' => 1.1, 'large' => 1.1,
                    'xl' => 1.2, 'extra large' => 1.2,
                    'xxl' => 1.3, '2xl' => 1.3,
                    'xxxl' => 1.4, '3xl' => 1.4,
                ];
                
                $basePeso = floatval($produtoData['peso'] ?? 0);
                $anyChanged = false;
                
                foreach ($produtoData['variacoes'] as &$vRef) {
                    $label = strtolower((string)($vRef['label'] ?? ''));
                    $attrs = $vRef['atributos'] ?? [];
                    $sizeVal = strtolower((string)($attrs['Bed Size'] ?? $attrs['Size'] ?? $attrs['Tamanho'] ?? $label));
                    
                    if ($isColchao) {
                        // Tentar encontrar peso estimado pelo tamanho de colchão
                        // Priorizar match mais longo primeiro (ex: "cal king" antes de "king")
                        $estimatedWeight = null;
                        $bestMatchLen = 0;
                        foreach ($sizeWeights as $sizeKey => $sizeKg) {
                            if ((stripos($sizeVal, $sizeKey) !== false || stripos($label, $sizeKey) !== false) && strlen($sizeKey) > $bestMatchLen) {
                                $estimatedWeight = $sizeKg;
                                $bestMatchLen = strlen($sizeKey);
                            }
                        }
                        
                        if ($estimatedWeight !== null) {
                            $vRef['peso'] = round($estimatedWeight * 1.15, 2); // +15% margem
                            $anyChanged = true;
                        }
                    } elseif ($basePeso > 0) {
                        // Para outros produtos, escalar pelo tamanho genérico
                        foreach ($genericSizeScale as $sizeKey => $scale) {
                            // Comparação exata ou como palavra inteira para evitar falsos positivos
                            $matched = ($sizeVal === $sizeKey);
                            if (!$matched && strlen($sizeKey) > 2) {
                                // Só usar stripos para chaves com mais de 2 chars (evitar 's', 'm', 'l')
                                $matched = (stripos($sizeVal, $sizeKey) !== false || stripos($label, $sizeKey) !== false);
                            }
                            if ($matched) {
                                $vRef['peso'] = round($basePeso * $scale, 2);
                                $anyChanged = true;
                                break;
                            }
                        }
                    }
                }
                unset($vRef);
                
                if ($anyChanged) {
                    error_log('[Assessoria] Pesos estimados por tamanho aplicados às variações');
                }
            }
        }

        if (headers_sent() === false) {
            $attrKeys = [];
            if (is_array($produtoData['variacoes'])) {
                foreach ($produtoData['variacoes'] as $v) {
                    if (is_array($v) && isset($v['atributos']) && is_array($v['atributos'])) {
                        foreach (array_keys($v['atributos']) as $k) {
                            $attrKeys[(string) $k] = true;
                        }
                    }
                }
            }
            header('X-Assessoria-Variacoes-Count: ' . (is_array($produtoData['variacoes']) ? count($produtoData['variacoes']) : 0));
            header('X-Assessoria-Variacoes-Keys: ' . $this->headerSafeValue(implode(',', array_keys($attrKeys)), 200));
        }

        // Garantir url_original e filtrar apenas campos essenciais para persistência
        if (!isset($produtoData['url_original']) || (string) $produtoData['url_original'] === '') {
            $produtoData['url_original'] = $urlOriginal;
        }
        if (!isset($produtoData['sku'])) {
            $produtoData['sku'] = '';
        }
        if (!isset($produtoData['imagens']) || !is_array($produtoData['imagens'])) {
            $produtoData['imagens'] = [];
        }

        $produtoData = [
            'sku' => (string) ($produtoData['sku'] ?? ''),
            'nome' => (string) ($produtoData['nome'] ?? ''),
            'descricao' => (string) ($produtoData['descricao'] ?? ''),
            'valor' => floatval($produtoData['valor'] ?? 0),
            'peso' => floatval($produtoData['peso'] ?? 0),
            'imagens' => $produtoData['imagens'] ?? [],
            'variacoes' => is_array($produtoData['variacoes'] ?? null) ? $produtoData['variacoes'] : [],
            'url_original' => (string) ($produtoData['url_original'] ?? $urlOriginal)
        ];

        // Se o preço base é suspeitamente baixo (< $2) e alguma variação tem preço real,
        // corrigir o preço base usando o menor preço real das variações
        $baseValor = $produtoData['valor'];
        $basePesoFinal = $produtoData['peso'];
        if ($baseValor < 2.0 && is_array($produtoData['variacoes']) && count($produtoData['variacoes']) > 0) {
            $varPrecos = [];
            foreach ($produtoData['variacoes'] as $vCheck) {
                if (is_array($vCheck) && isset($vCheck['valor']) && $vCheck['valor'] !== null && floatval($vCheck['valor']) >= 2.0) {
                    $varPrecos[] = floatval($vCheck['valor']);
                }
            }
            if (!empty($varPrecos)) {
                $produtoData['valor'] = min($varPrecos);
                $baseValor = $produtoData['valor'];
                error_log('[ChatGPT] Preço base corrigido de $' . number_format(floatval($produtoData['valor']), 2) . ' usando menor preço das variações: $' . number_format($baseValor, 2));
            }
        }

        // Preencher valor/peso null das variações com o valor/peso base do produto
        if (is_array($produtoData['variacoes'])) {
            foreach ($produtoData['variacoes'] as &$vFill) {
                if (!is_array($vFill)) continue;
                if (!isset($vFill['valor']) || $vFill['valor'] === null || floatval($vFill['valor']) < 2.0) {
                    $vFill['valor'] = $baseValor;
                }
                if (!isset($vFill['peso']) || $vFill['peso'] === null || floatval($vFill['peso']) <= 0) {
                    $vFill['peso'] = $basePesoFinal;
                }
            }
            unset($vFill);
            $produtoData['variacoes'] = array_values($produtoData['variacoes']);
        }        
        if (headers_sent() === false) {
            header('X-ChatGPT-Success: true');
            header('X-ChatGPT-Product-Data: ' . json_encode([
                'nome' => $produtoData['nome'],
                'valor' => $produtoData['valor'],
                'peso' => $produtoData['peso'],
                'descricao' => $produtoData['descricao'],
                'imagens_count' => is_array($produtoData['imagens']) ? count($produtoData['imagens']) : 0,
                'variacoes_count' => is_array($produtoData['variacoes']) ? count($produtoData['variacoes']) : 0
            ]));
        }
        error_log('[ChatGPT] Product extracted: ' . ($produtoData['nome'] ?? '?') . ' | Variations: ' . (is_array($produtoData['variacoes']) ? count($produtoData['variacoes']) : 0) . ' | Peso: ' . ($produtoData['peso'] ?? '?') . 'kg');
        
        // Log detalhado das variações para debug
        if (is_array($produtoData['variacoes']) && count($produtoData['variacoes']) > 0) {
            foreach (array_slice($produtoData['variacoes'], 0, 5) as $vi => $vv) {
                error_log('[ChatGPT] Var[' . $vi . ']: ' . ($vv['label'] ?? '?') . ' | valor=' . ($vv['valor'] ?? 'null') . ' | peso=' . ($vv['peso'] ?? 'null'));
            }
        }
        
        return $produtoData;
    }
    
    /**
     * Gera o prompt para o ChatGPT
     */
    private function gerarPromptChatGPT(array $dadosBrutos, string $urlOriginal): string {
        $hasHtmlExtra = isset($dadosBrutos['html_extra']) && is_array($dadosBrutos['html_extra']) && !empty($dadosBrutos['html_extra']);
        $extraInstructions = '';
        if ($hasHtmlExtra) {
            $extraInstructions = "
ATENÇÃO: O campo 'html_extra' contém dados complementares extraídos do HTML da página (especificações, pesos, opções de variação).
- 'weights_found': pesos encontrados no HTML (já convertidos para kg). USE ESTES VALORES para o peso de cada variação.
- 'specifications': tabela de especificações do produto. PROCURE peso/weight aqui.
- 'options_found': opções de variação encontradas (tamanhos, cores, etc.)
- 'jsonld_product': dados estruturados JSON-LD do produto.
- 'variant_offers': ofertas/variações com preços extraídos do JSON-LD. USE ESTES PREÇOS para cada variação.
- 'variant_prices_found': preços associados a tamanhos encontrados no HTML. USE ESTES PREÇOS.
- 'variant_price_data': dados de preço por variação extraídos via AI. USE ESTES PREÇOS E PESOS.
- 'prices_found': todos os preços encontrados no HTML.
PRIORIZE os dados de html_extra para preços e pesos por variação, pois são mais precisos que os dados do ai_query.
CADA VARIAÇÃO DEVE TER SEU PRÓPRIO PREÇO E PESO. NÃO USE O MESMO VALOR PARA TODAS.
";
        }

        return "Analise os dados brutos abaixo extraídos da URL: {$urlOriginal}

DADOS BRUTOS:
" . json_encode($dadosBrutos, JSON_PRETTY_PRINT) . "
{$extraInstructions}
EU PRECISO QUE VOCÊ EXTRAIA AS INFORMAÇÕES DO PRODUTO E RETORNE APENAS JSON VÁLIDO (SEM TEXTO, SEM MARKDOWN, SEM ```), COM ESTA ESTRUTURA EXATA E SOMENTE ESTES CAMPOS:

{
    \"sku\": \"SKU do produto ou código único (se não achar, pode retornar string vazia)\",
    \"nome\": \"Nome completo do produto\",
    \"descricao\": \"Descrição detalhada do produto\",
    \"valor\": 99.99,
    \"peso\": 52.16,
    \"imagens\": [\"url1\", \"url2\"],
    \"variacoes\": [{\"id\": \"var-1\", \"label\": \"Color: Gray | Size: King 90x100\", \"atributos\": {\"Color\": \"Gray\", \"Size\": \"King 90x100\"}, \"valor\": 45.99, \"peso\": 52.16}],
    \"url_original\": \"{$urlOriginal}\"
}

REGRAS ESPECÍFICAS:

1. CAMPOS OBRIGATÓRIOS: nome, imagem, valor, peso, descricao

2. VARIAÇÕES (MUITO IMPORTANTE — LEIA COM ATENÇÃO):
   - Analise TODOS os dados brutos cuidadosamente para encontrar variações/opções do produto
   - Variações podem estar em campos como: variants, variations, options, offers, availableVariations, variantCriteria, etc.
   - Cada COMBINAÇÃO de opções (ex: cada cor + cada tamanho) deve gerar uma entrada separada em \"variacoes\"
   - Se o produto tem 4 cores e 5 tamanhos, deve haver até 20 variações (4x5)
   - Cada variação DEVE ter: id (string), label (legível), atributos (mapa chave:valor), valor (preço USD), peso (kg)
   - PREÇO POR VARIAÇÃO: Se os dados contêm preços diferentes por variação/tamanho, CADA variação DEVE ter seu preço específico no campo \"valor\". NÃO use o mesmo preço para todas. Se Twin=$299 e King=$499, retorne esses valores específicos.
   - Se uma variação NÃO tem preço próprio nos dados, aí sim use o preço base do produto.
   - Se não existirem variações, retorne \"variacoes\": []
   - NÃO IGNORE variações. Se os dados brutos contêm opções/variantes, EXTRAIA TODAS.
   - PESO POR VARIAÇÃO: Se cada variação/tamanho tem peso diferente (ex: Twin=60 lbs, King=116 lbs), CADA variação DEVE ter seu peso ESPECÍFICO (convertido para kg). NÃO use o mesmo peso para todas.
   - Se não encontrar peso específico por variação, ESTIME baseado no tamanho (ex: Twin < Full < Queen < King < Cal King).

3. PESO (kg) — REGRA CRÍTICA:
   - O peso DEVE ser em quilogramas (kg). NUNCA retorne 1.0 kg como padrão sem verificar.
   - Se o peso estiver em LIBRAS (lbs/pounds), CONVERTA: 1 lb = 0.4536 kg. Ex: 115 lbs = 52.16 kg.
   - Se o peso estiver em ONÇAS (oz/ounces), CONVERTA: 1 oz = 0.02835 kg.
   - Procure o peso em: specifications, specs, weight, shipping weight, product weight, peso, libras, lbs, pounds, oz, html_extra.weights_found, html_extra.specifications.
   - Se cada variação/tamanho tem peso diferente (ex: Twin=79 lbs, King=116 lbs), use o peso ESPECÍFICO de cada variação.
   - O peso BASE do produto deve ser o peso da variação padrão ou o maior peso listado.
   - Se NÃO encontrar peso nos dados, ESTIME de forma REALISTA baseado no tipo de produto:
     * Colchão Twin: ~27 kg, Full: ~32 kg, Queen: ~38 kg, King: ~45 kg, Cal King: ~45 kg
     * Cobertor/manta: 2-5 kg
     * Roupas: 0.3-1 kg
     * Eletrônicos pequenos: 0.5-3 kg
     * Brinquedos: 0.5-3 kg
   - Adicione 15% de margem de segurança sobre o peso estimado.
   - NUNCA use 1.0 kg como peso padrão genérico. Sempre estime realisticamente.

4. DESCRIÇÃO:
   - Se não encontrar descrição detalhada, CRIE uma baseada no nome e características

5. IMAGEM:
   - Extraia todas as URLs de imagens disponíveis
   - Se não encontrar, use array vazio []

6. VALOR: Use número decimal com 2 casas (ex: 99.99). Este é o preço BASE.

7. NOME: Use o nome completo do produto

IMPORTANTE:
- Retorne APENAS o JSON, sem texto adicional
- CONVERTA libras para kg (1 lb = 0.4536 kg)
- Para descrição, CRIE uma se não encontrar
- Use kg para peso

RETORNE APENAS O JSON:";
    }
    
    /**
     * Obtém a API Key do ChatGPT
     */
    private function getChatGPTApiKey(): ?string {
        try {
            $db = \Config\Database::getConnection();
            $stmt = $db->prepare('SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1');
            $stmt->execute(['chatgpt_api_key']);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            
            $apiKey = $row ? $row['valor'] : null;
            
            // Log no console via header
            if (headers_sent() === false) {
                header('X-ChatGPT-Debug: API Key ' . ($apiKey ? 'found' : 'not found'));
                header('X-ChatGPT-Key-Length: ' . strlen($apiKey ?? ''));
            }
            
            return $apiKey;
        } catch (\Exception $e) {
            if (headers_sent() === false) {
                header('X-ChatGPT-Error: ' . $e->getMessage());
            }
            return null;
        }
    }
}
