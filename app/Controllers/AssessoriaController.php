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

        $err = json_last_error_msg();
        throw new \Exception('ChatGPT não retornou JSON válido: ' . $err);
    }

    private function truncateForPrompt($value, int $depth = 0) {
        if ($depth > 4) {
            return null;
        }

        if (is_array($value)) {
            $out = [];
            $i = 0;
            foreach ($value as $k => $v) {
                if ($i >= 40) {
                    break;
                }
                $out[$k] = $this->truncateForPrompt($v, $depth + 1);
                $i++;
            }
            return $out;
        }

        if (is_string($value)) {
            $v = $this->cleanJsonText($value);
            if (strlen($v) > 800) {
                $v = substr($v, 0, 800);
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
        foreach ([
            'title', 'name', 'product', 'product_name',
            'price', 'prices', 'pricing',
            'images', 'image',
            'variants', 'variation', 'variations', 'offers',
            'url',
            // Campos de peso / especificações
            'weight', 'weights_found', 'shipping_weight', 'specifications', 'specs',
            'product_weight', 'item_weight', 'weight_lbs',
            // Campos de disponibilidade / estoque
            'availability', 'stock', 'in_stock', 'out_of_stock', 'inventory',
            'available', 'is_available', 'stock_status',
            // Campos de opções (Walmart, Costco, etc.)
            'options', 'product_options', 'selected_options',
            // Campos de SKU / itens filhos (Costco, etc.)
            'skus', 'items', 'children', 'child_items', 'sku_list',
            'variant_pricing', 'option_prices', 'price_map',
            // Campos do ai_extract_rules do ScrapingBee
            'base_price', 'description', 'sku',
        ] as $k) {
            if (array_key_exists($k, $dadosBrutos)) {
                $picked[$k] = $dadosBrutos[$k];
            }
        }
        // Também incluir chaves aninhadas em 'product' se existir
        if (isset($dadosBrutos['product']) && is_array($dadosBrutos['product'])) {
            foreach (['weights_found', 'weight', 'shipping_weight', 'specifications', 'specs',
                       'availability', 'stock', 'in_stock', 'out_of_stock', 'inventory',
                       'options', 'product_options', 'variants', 'variations', 'offers',
                       'skus', 'items', 'children', 'pricing', 'prices'] as $pk) {
                if (array_key_exists($pk, $dadosBrutos['product'])) {
                    $picked['product_' . $pk] = $dadosBrutos['product'][$pk];
                }
            }
        }
        if (empty($picked)) {
            $picked = $dadosBrutos;
        }
        return (array) $this->truncateForPrompt($picked, 0);
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

        return [
            'sku' => '',
            'nome' => $nome,
            'descricao' => $descricao,
            'valor' => floatval($valor),
            // Regra: se não encontrar peso, usar sempre 1kg
            'peso' => 1.0,
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

            if ($peso === null || floatval($peso) <= 0) {
                $peso = 1.0;
            }

            $label = $this->stringifyVariationLabel(is_array($atributos) ? $atributos : []);
            if ($label === '') {
                $label = (string) ($v['name'] ?? $v['title'] ?? $v['sku'] ?? 'Variação');
            }

            // Detectar disponibilidade (out of stock)
            $outOfStock = false;
            foreach (['out_of_stock', 'outOfStock', 'is_out_of_stock'] as $osk) {
                if (isset($v[$osk])) {
                    $osVal = $v[$osk];
                    if ($osVal === true || $osVal === 'true' || $osVal === 1 || $osVal === '1') {
                        $outOfStock = true;
                    }
                    break;
                }
            }
            if (!$outOfStock && isset($v['availability'])) {
                $avail = strtolower(trim((string) $v['availability']));
                if (in_array($avail, ['out_of_stock', 'outofstock', 'out of stock', 'unavailable', 'sold_out', 'soldout'], true)) {
                    $outOfStock = true;
                }
            }
            if (!$outOfStock && isset($v['in_stock'])) {
                $inStock = $v['in_stock'];
                if ($inStock === false || $inStock === 'false' || $inStock === 0 || $inStock === '0') {
                    $outOfStock = true;
                }
            }
            if (!$outOfStock && isset($v['available'])) {
                $avVal = $v['available'];
                if ($avVal === false || $avVal === 'false' || $avVal === 0 || $avVal === '0') {
                    $outOfStock = true;
                }
            }
            if (!$outOfStock && isset($v['stock_status'])) {
                $ss = strtolower(trim((string) $v['stock_status']));
                if (in_array($ss, ['out_of_stock', 'outofstock', 'out of stock', 'unavailable', 'sold_out'], true)) {
                    $outOfStock = true;
                }
            }

            $id = (string) ($v['id'] ?? $v['variation_id'] ?? $v['variant_id'] ?? $v['variantId'] ?? $v['item_id'] ?? $v['itemId'] ?? $v['sku'] ?? md5($label));
            $out[] = [
                'id' => $id,
                'label' => $label,
                'atributos' => is_array($atributos) ? $atributos : [],
                'valor' => $valor !== null ? floatval($valor) : null,
                'peso' => floatval($peso),
                'out_of_stock' => $outOfStock
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
                    $v['peso'] = 1.0;
                }
                // Preservar out_of_stock
                $oos = false;
                if (isset($v['out_of_stock'])) {
                    $oosVal = $v['out_of_stock'];
                    $oos = ($oosVal === true || $oosVal === 'true' || $oosVal === 1 || $oosVal === '1');
                }
                if (!$oos && isset($v['availability'])) {
                    $avail = strtolower(trim((string) $v['availability']));
                    if (in_array($avail, ['out_of_stock', 'outofstock', 'out of stock', 'unavailable', 'sold_out', 'soldout'], true)) {
                        $oos = true;
                    }
                }
                if (!$oos && isset($v['in_stock'])) {
                    $inStock = $v['in_stock'];
                    if ($inStock === false || $inStock === 'false' || $inStock === 0 || $inStock === '0') {
                        $oos = true;
                    }
                }
                $out[] = [
                    'id' => (string) ($v['id'] ?? ''),
                    'label' => (string) ($v['label'] ?? ''),
                    'atributos' => $v['atributos'],
                    'valor' => ($v['valor'] === null ? null : floatval($v['valor'])),
                    'peso' => floatval($v['peso']),
                    'out_of_stock' => $oos
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
                // Preservar out_of_stock do existente se o novo não tem
                if (!isset($v['out_of_stock']) && isset($cur['out_of_stock'])) {
                    $v['out_of_stock'] = $cur['out_of_stock'];
                }
                $unique[$k] = $v;
                continue;
            }

            // Preferir item que tenha atributos
            $curHasAttrs = isset($cur['atributos']) && is_array($cur['atributos']) && !empty($cur['atributos']);
            $newHasAttrs = isset($v['atributos']) && is_array($v['atributos']) && !empty($v['atributos']);
            if (!$curHasAttrs && $newHasAttrs) {
                if (!isset($v['out_of_stock']) && isset($cur['out_of_stock'])) {
                    $v['out_of_stock'] = $cur['out_of_stock'];
                }
                $unique[$k] = $v;
                continue;
            }

            // Propagar out_of_stock se o novo trouxer
            if (isset($v['out_of_stock']) && $v['out_of_stock'] && !($cur['out_of_stock'] ?? false)) {
                $unique[$k]['out_of_stock'] = true;
            }
        }
        return array_values($unique);
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

        // Suporte ao formato do ai_extract_rules do ScrapingBee
        // Formato: variants: [{option_name, option_value, price, weight_lbs, in_stock}, ...]
        foreach (['variants', 'variations'] as $vk) {
            if (!empty($dadosBrutos[$vk]) && is_array($dadosBrutos[$vk])) {
                $first = reset($dadosBrutos[$vk]);
                if (is_array($first) && (isset($first['option_name']) || isset($first['option_value']))) {
                    // Agrupar por combinação de atributos para criar variações multi-atributo
                    // Primeiro, agrupar por option_value para criar variações individuais
                    $aiVariants = [];
                    foreach ($dadosBrutos[$vk] as $aiV) {
                        if (!is_array($aiV)) continue;
                        $optName = trim((string) ($aiV['option_name'] ?? 'Option'));
                        $optValue = trim((string) ($aiV['option_value'] ?? ''));
                        if ($optValue === '') continue;

                        $price = null;
                        if (isset($aiV['price'])) {
                            $price = $this->findFirstNumeric($aiV['price']);
                        }

                        $weightLbs = null;
                        if (isset($aiV['weight_lbs'])) {
                            $weightLbs = $this->findFirstNumeric($aiV['weight_lbs']);
                        }
                        $weightKg = $weightLbs !== null ? round($weightLbs * 0.4536, 2) : null;

                        $inStock = true;
                        if (isset($aiV['in_stock'])) {
                            $isVal = $aiV['in_stock'];
                            if ($isVal === false || $isVal === 'false' || $isVal === 0 || $isVal === '0' || $isVal === 'no') {
                                $inStock = false;
                            }
                        }

                        $label = $optName . ': ' . $optValue;
                        $aiVariants[] = [
                            'id' => md5($label),
                            'label' => $label,
                            'atributos' => [$optName => $optValue],
                            'valor' => $price,
                            'peso' => $weightKg !== null && $weightKg > 0 ? $weightKg : 1.0,
                            'out_of_stock' => !$inStock
                        ];
                    }
                    if (!empty($aiVariants)) {
                        $append($aiVariants);
                        // Se encontramos variações no formato ai_extract_rules, não precisamos buscar mais
                        if (!empty($all)) {
                            return $this->mergeNormalizedVariacoes($all);
                        }
                    }
                }
            }
        }

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
                'wait_browser' => 'domcontentloaded',
                'block_ads' => 'true',
                'ai_query' => 'Return product name, images, base price and ALL variant combinations (size/color/style/fit). For each variant return id/sku, attributes map and price (USD). Missing values: null.'
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
        if (!$curlError && (int) $httpCode === 400 && is_string($response) && (stripos($response, 'ai_query') !== false || stripos($response, 'ai_extract_rules') !== false || stripos($response, 'extract_rules') !== false)) {
            $retryUrl = $buildUrl([
                'ai_query' => null
            ]);
            // Remove parâmetros nulos
            $retryUrl = preg_replace('/(&|\?)ai_query=[^&]*(&|$)/', '$1', $retryUrl);
            $retryUrl = rtrim($retryUrl, '&?');

            if (headers_sent() === false) {
                header('X-ScrapingBee-Retry-No-AIQuery: true');
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
        
        try {
            // Usar ChatGPT para analisar os dados brutos
            $produto = $this->analisarComChatGPT($decodedResponse, $url);

            // Pós-processamento: se variações têm todas o mesmo preço, tentar enriquecer via json_response
            if ($this->variacoesTemMesmoPreco($produto) && $scriptbeeApiKey) {
                $produto = $this->tentarEnriquecerVariacoes($produto, $url, $scriptbeeApiKey, $doRequest);
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
     * Verifica se todas as variações têm o mesmo preço (indicando que preços por variação não foram capturados)
     */
    private function variacoesTemMesmoPreco(array $produto): bool {
        $variacoes = $produto['variacoes'] ?? [];
        if (count($variacoes) < 2) {
            return false;
        }

        $precos = [];
        foreach ($variacoes as $v) {
            if (!is_array($v)) continue;
            $p = $v['valor'] ?? null;
            if ($p !== null && floatval($p) > 0) {
                $precos[] = round(floatval($p), 2);
            }
        }

        if (count($precos) < 2) {
            return false;
        }

        // Se todos os preços são iguais, retorna true
        return count(array_unique($precos)) === 1;
    }

    /**
     * Tenta enriquecer variações com preços individuais usando uma segunda chamada ao ScrapingBee
     * Estratégia: pegar HTML renderizado + metadata e usar ChatGPT para extrair preços por variação
     */
    private function tentarEnriquecerVariacoes(array $produto, string $url, string $apiKey, callable $doRequest): array {
        $variacoes = $produto['variacoes'] ?? [];
        if (empty($variacoes)) {
            return $produto;
        }

        error_log('[Assessoria] Variações com mesmo preço detectadas, tentando enriquecer para: ' . $url);

        // Chamada com json_response para capturar metadata JSON-LD, XHR e HTML body
        $enrichUrl = 'https://app.scrapingbee.com/api/v1?' . http_build_query([
            'api_key' => $apiKey,
            'url' => $url,
            'stealth_proxy' => 'true',
            'country_code' => 'us',
            'timeout' => '60000',
            'wait_browser' => 'load',
            'wait' => '3000',
            'block_ads' => 'true',
            'json_response' => 'true',
        ]);

        [$enrichResp, $enrichCode, $enrichErrno, $enrichError] = $doRequest($enrichUrl, 70);

        if ($enrichError || $enrichCode !== 200 || empty($enrichResp)) {
            error_log('[Assessoria] Enriquecimento falhou: code=' . $enrichCode . ' error=' . ($enrichError ?: 'none'));
            return $produto;
        }

        $enrichData = json_decode($enrichResp, true);
        if (!is_array($enrichData)) {
            return $produto;
        }

        // 1) Tentar extrair preços do metadata JSON-LD
        if (isset($enrichData['metadata']) && is_array($enrichData['metadata'])) {
            $this->extractPricesFromMetadata($enrichData['metadata'], $variacoes, $produto);
            if (!$this->variacoesTemMesmoPreco($produto)) {
                error_log('[Assessoria] Preços enriquecidos via metadata JSON-LD');
                return $produto;
            }
        }

        // 2) Tentar extrair preços dos XHR interceptados
        $xhrPrices = [];
        if (isset($enrichData['xhr']) && is_array($enrichData['xhr'])) {
            foreach ($enrichData['xhr'] as $xhr) {
                if (!is_array($xhr)) continue;
                $body = $xhr['body'] ?? '';
                if (is_string($body) && $body !== '') {
                    $xhrJson = json_decode($body, true);
                    if (is_array($xhrJson)) {
                        $this->extractPricesFromXhr($xhrJson, $xhrPrices);
                    }
                }
            }
            if (!empty($xhrPrices)) {
                $uniquePrices = array_values(array_unique($xhrPrices));
                $basePrice = floatval($produto['valor'] ?? 0);
                // Validar que preços são razoáveis
                $reasonable = true;
                if ($basePrice > 0) {
                    foreach ($uniquePrices as $xp) {
                        if ($xp < $basePrice * 0.1 || $xp > $basePrice * 5) {
                            $reasonable = false;
                            break;
                        }
                    }
                }
                if ($reasonable && count($uniquePrices) > 1 && count($uniquePrices) <= count($variacoes) * 2) {
                    sort($uniquePrices);
                    $i = 0;
                    foreach ($produto['variacoes'] as &$vEnrich) {
                        if ($i < count($uniquePrices)) {
                            $vEnrich['valor'] = $uniquePrices[$i];
                        }
                        $i++;
                    }
                    unset($vEnrich);
                    if (!$this->variacoesTemMesmoPreco($produto)) {
                        error_log('[Assessoria] Preços enriquecidos via XHR');
                        return $produto;
                    }
                }
            }
        }

        // 3) Usar o HTML body + ChatGPT para extrair preços por variação
        $htmlBody = $enrichData['body'] ?? '';
        if (is_string($htmlBody) && strlen($htmlBody) > 200) {
            $chatGptApiKey = $this->getChatGPTApiKey();
            if ($chatGptApiKey) {
                $relevantHtml = $this->extractRelevantHtmlForPrices($htmlBody);

                if (strlen($relevantHtml) > 50) {
                    $varLabels = [];
                    foreach ($variacoes as $v) {
                        if (is_array($v) && isset($v['atributos']) && is_array($v['atributos'])) {
                            foreach ($v['atributos'] as $ak => $av) {
                                $varLabels[] = (string) $av;
                            }
                        }
                    }

                    $basePrice = floatval($produto['valor'] ?? 0);
                    $pricePrompt = "From the HTML snippets below, extract the SPECIFIC PRICE (in USD) for each product variant/option.\nThe variants are: " . implode(', ', $varLabels) . "\nThe base product price is approximately $" . number_format($basePrice, 2) . " USD.\nPrices should be in a similar range to the base price.\n\nReturn ONLY valid JSON array like: [{\"option\": \"variant value\", \"price\": 99.99}]\nNo text, no markdown.\n\nHTML:\n" . $relevantHtml;

                    [$priceResp, $priceCode, $priceErr] = $this->callChatGPT($chatGptApiKey, $pricePrompt, true);
                    if ($priceCode === 400) {
                        [$priceResp, $priceCode, $priceErr] = $this->callChatGPT($chatGptApiKey, $pricePrompt, false);
                    }

                    if (!$priceErr && $priceCode === 200) {
                        $priceDecoded = json_decode($priceResp, true);
                        $priceContent = $priceDecoded['choices'][0]['message']['content'] ?? '';
                        try {
                            $priceData = $this->decodeJsonResilient((string) $priceContent);
                            if (is_array($priceData) && !empty($priceData)) {
                                // Validar que os preços extraídos são razoáveis (dentro de 5x do preço base)
                                $allReasonable = true;
                                foreach ($priceData as $pd) {
                                    if (!is_array($pd)) continue;
                                    $pdPrice = $this->findFirstNumeric($pd['price'] ?? null);
                                    if ($pdPrice !== null && $pdPrice > 0 && $basePrice > 0) {
                                        if ($pdPrice < $basePrice * 0.1 || $pdPrice > $basePrice * 5) {
                                            $allReasonable = false;
                                            error_log('[Assessoria] Preço enriquecido descartado (fora do range): $' . $pdPrice . ' vs base $' . $basePrice);
                                            break;
                                        }
                                    }
                                }

                                if ($allReasonable) {
                                    foreach ($produto['variacoes'] as &$vPrice) {
                                        if (!is_array($vPrice)) continue;
                                        $attrs = $vPrice['atributos'] ?? [];
                                        foreach ($priceData as $pd) {
                                            if (!is_array($pd)) continue;
                                            $pdOption = strtolower(trim((string) ($pd['option'] ?? '')));
                                            $pdPrice = $this->findFirstNumeric($pd['price'] ?? null);
                                            if ($pdOption === '' || $pdPrice === null || $pdPrice <= 0) continue;

                                            foreach ($attrs as $av) {
                                                $avLower = strtolower(trim((string) $av));
                                                if ($avLower !== '' && ($pdOption === $avLower || stripos($pdOption, $avLower) !== false || stripos($avLower, $pdOption) !== false)) {
                                                    $vPrice['valor'] = $pdPrice;
                                                    break 2;
                                                }
                                            }
                                        }
                                    }
                                    unset($vPrice);

                                    if (!$this->variacoesTemMesmoPreco($produto)) {
                                        error_log('[Assessoria] Preços enriquecidos via ChatGPT + HTML');
                                        return $produto;
                                    }
                                }
                            }
                        } catch (\Exception $e) {
                            error_log('[Assessoria] Erro ao parsear preços do ChatGPT: ' . $e->getMessage());
                        }
                    }
                }
            }
        }

        error_log('[Assessoria] Não foi possível enriquecer preços das variações via metadata/XHR/HTML');

        // 4) Estratégias adicionais desabilitadas - preços por variação dependem do ChatGPT
        return $produto;
    }

    /**
     * Extrai a parte relevante do HTML que contém botões de variação e preços
     */
    private function extractRelevantHtmlForPrices(string $html): string {
        // Remover scripts, styles, header, footer, nav
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/si', '', $html);
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/si', '', $html);
        $html = preg_replace('/<!--.*?-->/s', '', $html);
        $html = preg_replace('/<header\b[^>]*>.*?<\/header>/si', '', $html);
        $html = preg_replace('/<footer\b[^>]*>.*?<\/footer>/si', '', $html);
        $html = preg_replace('/<nav\b[^>]*>.*?<\/nav>/si', '', $html);

        // Extrair linhas de texto que contêm preços
        $text = strip_tags($html);
        $lines = preg_split('/[\n\r]+/', $text);
        $priceLines = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '' && preg_match('/\$[\d,]+\.?\d*/', $line)) {
                $priceLines[] = $line;
            }
        }

        $relevant = implode("\n", array_slice($priceLines, 0, 60));
        if (strlen($relevant) > 6000) {
            $relevant = substr($relevant, 0, 6000);
        }
        return $relevant;
    }

    /**
     * Extrai preços recursivamente de dados XHR
     */
    private function extractPricesFromXhr(array $data, array &$prices, int $depth = 0): void {
        if ($depth > 5) return;

        foreach ($data as $k => $v) {
            $ks = strtolower((string) $k);
            if (in_array($ks, ['price', 'saleprice', 'sale_price', 'current_price', 'finalprice', 'final_price', 'amount', 'deliveredprice', 'onlineprice', 'offerprice'], true)) {
                $n = $this->findFirstNumeric($v);
                if ($n !== null && $n > 1) {
                    $prices[] = $n;
                }
            }
            if (is_array($v)) {
                $this->extractPricesFromXhr($v, $prices, $depth + 1);
            }
        }
    }

    /**
     * Extrai preços de metadata JSON-LD e tenta associar às variações
     */
    private function extractPricesFromMetadata(array $metadata, array $variacoes, array &$produto): void {
        // JSON-LD geralmente tem offers com preços
        $jsonLd = $metadata['json-ld'] ?? [];
        if (!is_array($jsonLd)) return;

        $offers = [];
        $queue = [$jsonLd];
        $seen = 0;
        while (!empty($queue) && $seen < 100) {
            $node = array_shift($queue);
            $seen++;
            if (!is_array($node)) continue;

            // Procurar offers
            if (isset($node['offers']) && is_array($node['offers'])) {
                foreach ($node['offers'] as $offer) {
                    if (is_array($offer) && isset($offer['price'])) {
                        $price = $this->findFirstNumeric($offer['price']);
                        $name = $offer['name'] ?? $offer['sku'] ?? '';
                        if ($price !== null && $price > 1) {
                            $offers[] = ['price' => $price, 'name' => (string) $name];
                        }
                    }
                }
            }

            foreach ($node as $v) {
                if (is_array($v)) {
                    $queue[] = $v;
                }
            }
        }

        if (count($offers) >= 2 && count($offers) <= count($variacoes) * 2) {
            // Tentar associar offers às variações por nome
            $matched = false;
            foreach ($produto['variacoes'] as &$vMeta) {
                if (!is_array($vMeta)) continue;
                $label = strtolower((string) ($vMeta['label'] ?? ''));
                $attrs = $vMeta['atributos'] ?? [];
                foreach ($offers as $offer) {
                    $offerName = strtolower((string) $offer['name']);
                    if ($offerName === '') continue;
                    // Verificar se algum atributo da variação está no nome da offer
                    foreach ($attrs as $av) {
                        if (stripos($offerName, (string) $av) !== false) {
                            $vMeta['valor'] = $offer['price'];
                            $matched = true;
                            break 2;
                        }
                    }
                }
            }
            unset($vMeta);

            // Se não conseguiu associar por nome, associar por ordem
            if (!$matched && count($offers) === count($produto['variacoes'])) {
                $i = 0;
                foreach ($produto['variacoes'] as &$vMeta2) {
                    if (isset($offers[$i])) {
                        $vMeta2['valor'] = $offers[$i]['price'];
                    }
                    $i++;
                }
                unset($vMeta2);
            }
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
     * Extrai dados estruturados de um HTML: JSON-LD, Open Graph, meta tags e texto visível.
     */
    private function extrairDadosDoHtml(string $html, string $url): array {
        $data = ['url' => $url];

        // 1) JSON-LD (schema.org Product)
        if (preg_match_all('/<script[^>]*type=["\']application\/ld\+json["\'][^>]*>(.*?)<\/script>/si', $html, $m)) {
            foreach ($m[1] as $blob) {
                $json = json_decode(trim($blob), true);
                if (!is_array($json)) continue;
                // Pode ser array de schemas
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

        // 6) Imagens de produto (og:image ou primeiras imagens grandes)
        $images = [];
        if (!empty($data['og_image'])) {
            $images[] = $data['og_image'];
        }
        if (preg_match_all('/<img[^>]+src=["\']([^"\']+)["\'][^>]*/i', $html, $imgs)) {
            foreach ($imgs[1] as $src) {
                if (count($images) >= 8) break;
                // Filtrar imagens pequenas/icons
                if (preg_match('/\.(svg|gif|ico)(\?|$)/i', $src)) continue;
                if (stripos($src, 'icon') !== false || stripos($src, 'logo') !== false) continue;
                if (stripos($src, 'pixel') !== false || stripos($src, '1x1') !== false) continue;
                $images[] = $src;
            }
        }
        if (!empty($images)) {
            $data['images'] = array_values(array_unique($images));
        }

        // 7) Pesos encontrados no HTML (lbs, kg, oz)
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

        // 8) Especificações / tabelas de specs (peso, dimensões, etc.)
        $specs = [];
        if (preg_match_all('/<t[hd][^>]*>(.*?)<\/t[hd]>\s*<td[^>]*>(.*?)<\/td>/si', $html, $specMatches, PREG_SET_ORDER)) {
            foreach ($specMatches as $sm) {
                $label = trim(strip_tags($sm[1]));
                $value = trim(strip_tags($sm[2]));
                if ($label !== '' && $value !== '' && strlen($label) < 100 && strlen($value) < 200) {
                    $specs[$label] = $value;
                }
            }
        }
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

        // 9) Preços associados a tamanhos/variações no HTML
        $variantPrices = [];
        if (preg_match_all('/(?:Split California King|Split Cal King|California King|Cal King|Twin XL|Split King|Twin|Full|Queen|King|Double|Single)[^$\n]{0,30}\$\s?(\d{1,6}[.,]\d{2})/i', $html, $vpMatches, PREG_SET_ORDER)) {
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
            $subtotal += $produto['valor'];
            $pesoTotal += $produto['peso'];
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
                                if (isset($v['valor']) && $v['valor'] !== null && floatval($v['valor']) > 0) {
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
            // Retry 1: prompt menor para evitar truncamento / syntax error
            $retryReduced = [
                'hint' => 'extract only essential fields',
                'url' => $urlOriginal,
                'data' => $reduced
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

                // Regra: se não encontrar peso, usar sempre 1kg
                if ($campo === 'peso') {
                    $produtoData['peso'] = 1.0;
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

        // Normalizar variacoes para o formato esperado pelo orçamento (atributos)
        if (isset($produtoData['variacoes']) && is_array($produtoData['variacoes'])) {
            $produtoData['variacoes'] = $this->normalizeVariacoesForOrcamento($produtoData['variacoes'], $dadosBrutos);
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

        // Truncar descrição se muito longa (evitar lixo de cookies/policies)
        if (mb_strlen($produtoData['descricao']) > 500) {
            $produtoData['descricao'] = mb_substr($produtoData['descricao'], 0, 497) . '...';
        }
        
        if (headers_sent() === false) {
            header('X-ChatGPT-Success: true');
            header('X-ChatGPT-Product-Data: ' . json_encode([
                'nome' => $produtoData['nome'],
                'valor' => $produtoData['valor'],
                'peso' => $produtoData['peso'],
                'descricao' => $produtoData['descricao'],
                'imagens_count' => is_array($produtoData['imagens']) ? count($produtoData['imagens']) : 0
            ]));
        }

        // Pós-processamento: se peso é suspeitamente baixo, estimar pelo tipo de produto
        if (floatval($produtoData['peso']) <= 1.5) {
            $nomeLower = strtolower((string) $produtoData['nome']);
            $pesoEstimado = null;
            if (preg_match('/mattress|colch[aã]o/', $nomeLower)) {
                $pesoEstimado = 38.0;
            } elseif (preg_match('/sofa|couch|sof[aá]/', $nomeLower)) {
                $pesoEstimado = 40.0;
            } elseif (preg_match('/blanket|comforter|cobertor|manta|duvet/', $nomeLower)) {
                $pesoEstimado = 3.5;
            } elseif (preg_match('/tv|monitor|television/', $nomeLower)) {
                $pesoEstimado = 15.0;
            } elseif (preg_match('/laptop|notebook/', $nomeLower)) {
                $pesoEstimado = 2.5;
            } elseif (preg_match('/chair|cadeira/', $nomeLower)) {
                $pesoEstimado = 15.0;
            } elseif (preg_match('/table|mesa|desk/', $nomeLower)) {
                $pesoEstimado = 20.0;
            } elseif (preg_match('/refrigerator|geladeira|fridge/', $nomeLower)) {
                $pesoEstimado = 70.0;
            } elseif (preg_match('/washer|dryer|lavadora|secadora/', $nomeLower)) {
                $pesoEstimado = 60.0;
            }
            if ($pesoEstimado !== null) {
                $produtoData['peso'] = round($pesoEstimado * 1.15, 2); // +15% margem
                error_log('[Assessoria] Peso corrigido de <=1.5kg para ' . $produtoData['peso'] . 'kg baseado no nome: ' . $produtoData['nome']);
            }
        }

        // Pós-processamento: preencher peso null das variações com peso base
        if (is_array($produtoData['variacoes'])) {
            foreach ($produtoData['variacoes'] as &$vFill) {
                if (!is_array($vFill)) continue;
                if (!isset($vFill['peso']) || $vFill['peso'] === null || floatval($vFill['peso']) <= 0) {
                    $vFill['peso'] = $produtoData['peso'];
                }
                // Garantir que out_of_stock existe
                if (!isset($vFill['out_of_stock'])) {
                    $vFill['out_of_stock'] = false;
                }
            }
            unset($vFill);
        }

        // Pós-processamento: detectar se pesos estão em libras e converter para kg
        // Heurística: se os dados brutos contêm indicação de "lbs"/"pounds"/"lb" nos campos de peso,
        // ou se o peso é suspeitamente alto (ex: mattress 149 "kg" quando deveria ser ~67 kg),
        // converter de lbs para kg.
        $lbsDetected = false;
        // Verificar nos dados brutos se há indicação de libras
        $rawJson = json_encode($dadosBrutos);
        // Procurar padrões como "149 lbs", "weight: 149 lb", "pounds" perto de números de peso
        if (preg_match('/\b(lbs?|pounds?|libras?)\b/i', $rawJson)) {
            // Verificar se o peso base ou das variações parece estar em libras (não convertido)
            // Se o ChatGPT retornou peso > 2.2x do que seria razoável em kg, provavelmente está em lbs
            $pesosVariacoes = [];
            if (is_array($produtoData['variacoes'])) {
                foreach ($produtoData['variacoes'] as $vw) {
                    if (is_array($vw) && isset($vw['peso']) && floatval($vw['peso']) > 0) {
                        $pesosVariacoes[] = floatval($vw['peso']);
                    }
                }
            }
            if (floatval($produtoData['peso']) > 0) {
                $pesosVariacoes[] = floatval($produtoData['peso']);
            }

            if (!empty($pesosVariacoes)) {
                $maxPeso = max($pesosVariacoes);
                // Se o peso máximo é > 50 e os dados brutos mencionam lbs, provavelmente não foi convertido
                // (um colchão de 149 lbs = 67.6 kg; se veio 149 "kg" é claramente lbs não convertido)
                // Regra: se peso > 45 e dados mencionam lbs, converter
                if ($maxPeso > 45) {
                    $lbsDetected = true;
                }
            }
        }

        if ($lbsDetected) {
            $lbsToKg = 0.4536;
            if (floatval($produtoData['peso']) > 45) {
                $produtoData['peso'] = round(floatval($produtoData['peso']) * $lbsToKg, 2);
                error_log('[Assessoria] Peso base convertido de lbs para kg: ' . $produtoData['peso'] . 'kg');
            }
            if (is_array($produtoData['variacoes'])) {
                foreach ($produtoData['variacoes'] as &$vLbs) {
                    if (!is_array($vLbs)) continue;
                    if (isset($vLbs['peso']) && floatval($vLbs['peso']) > 45) {
                        $vLbs['peso'] = round(floatval($vLbs['peso']) * $lbsToKg, 2);
                    }
                }
                unset($vLbs);
            }
        }

        // Pós-processamento: remover atributos que têm apenas 1 valor único (especificações fixas, não variações)
        if (is_array($produtoData['variacoes']) && count($produtoData['variacoes']) > 0) {
            error_log('[Assessoria] Variacoes antes do pos-processamento: ' . count($produtoData['variacoes']));
            // Coletar todos os valores por chave de atributo
            $attrValueSets = [];
            foreach ($produtoData['variacoes'] as $vCheck) {
                if (!is_array($vCheck) || !isset($vCheck['atributos']) || !is_array($vCheck['atributos'])) continue;
                foreach ($vCheck['atributos'] as $ak => $av) {
                    $ak = trim((string) $ak);
                    if ($ak === '') continue;
                    if (!isset($attrValueSets[$ak])) $attrValueSets[$ak] = [];
                    $attrValueSets[$ak][trim((string) $av)] = true;
                }
            }
            // Identificar chaves com apenas 1 valor (especificação fixa)
            $singleValueKeys = [];
            foreach ($attrValueSets as $ak => $vals) {
                if (count($vals) <= 1) {
                    $singleValueKeys[] = $ak;
                }
            }
            // Remover essas chaves dos atributos de todas as variações
            if (!empty($singleValueKeys)) {
                error_log('[Assessoria] Removendo atributos de valor unico: ' . implode(', ', $singleValueKeys));
                foreach ($produtoData['variacoes'] as &$vClean) {
                    if (!is_array($vClean) || !isset($vClean['atributos']) || !is_array($vClean['atributos'])) continue;
                    foreach ($singleValueKeys as $sk) {
                        unset($vClean['atributos'][$sk]);
                    }
                    // Atualizar label
                    $vClean['label'] = $this->stringifyVariationLabel($vClean['atributos']);
                }
                unset($vClean);

                // Após remover atributos de valor único, pode haver variações duplicadas - deduplicar
                $produtoData['variacoes'] = $this->mergeNormalizedVariacoes($produtoData['variacoes']);

                // Se todas as variações ficaram sem atributos, limpar variacoes
                $hasAnyAttrs = false;
                foreach ($produtoData['variacoes'] as $vTest) {
                    if (is_array($vTest) && isset($vTest['atributos']) && is_array($vTest['atributos']) && !empty($vTest['atributos'])) {
                        $hasAnyAttrs = true;
                        break;
                    }
                }
                if (!$hasAnyAttrs) {
                    error_log('[Assessoria] TODAS as variacoes ficaram sem atributos - limpando variacoes');
                    $produtoData['variacoes'] = [];
                }
            }
        }
        
        return $produtoData;
    }
    
    /**
     * Gera o prompt para o ChatGPT
     */
    private function gerarPromptChatGPT(array $dadosBrutos, string $urlOriginal): string {
        return "Analise os dados brutos abaixo extraídos da URL: {$urlOriginal}

DADOS BRUTOS:
" . json_encode($dadosBrutos, JSON_PRETTY_PRINT) . "

EU PRECISO QUE VOCÊ EXTRAIA AS INFORMAÇÕES DO PRODUTO E RETORNE APENAS JSON VÁLIDO (SEM TEXTO, SEM MARKDOWN, SEM ```), COM ESTA ESTRUTURA EXATA E SOMENTE ESTES CAMPOS:

{
    \"sku\": \"SKU do produto ou código único (se não achar, pode retornar string vazia)\",
    \"nome\": \"Nome completo do produto\",
    \"descricao\": \"Descrição detalhada do produto\",
    \"valor\": 99.99,
    \"peso\": 1.5,
    \"imagens\": [\"url1\", \"url2\"],
    \"variacoes\": [{\"id\": \"opcional\", \"label\": \"Ex: Size: 10x12x8.4 ft\", \"atributos\": {\"Size\": \"10x12x8.4 ft\"}, \"valor\": 1299.99, \"peso\": 50.0, \"out_of_stock\": false}],
    \"url_original\": \"{$urlOriginal}\"
}

REGRAS CRÍTICAS:

1. CAMPOS OBRIGATÓRIOS: nome, imagem, valor, peso, descricao

2. VARIAÇÕES - O QUE É E O QUE NÃO É VARIAÇÃO:
   - VARIAÇÃO = opção que o COMPRADOR ESCOLHE e que MUDA o produto (ex: tamanho/size, cor/color, Bed Size).
   - NÃO É VARIAÇÃO = especificação fixa do produto que não muda (ex: material, firmness, mattress type, mattress composition, mattress thickness quando há apenas 1 opção). Se existe apenas UM valor possível para um atributo, NÃO é variação.
   - SOMENTE inclua como variação atributos que têm MÚLTIPLAS opções selecionáveis pelo comprador.
   - Exemplo Walmart: \"Size\" com opções 10x12x8.4 ft, 10x14x8.4 ft, 10x18x8.4 ft, 14x9.5x9 FT = VARIAÇÃO.
   - Exemplo Walmart: \"Color: Black\" quando só existe Black = NÃO É VARIAÇÃO, é especificação fixa.
   - Exemplo Costco: \"Bed Size\" com opções Queen, King, California King = VARIAÇÃO.
   - Exemplo Costco: \"Mattress Thickness: 12 Inch\", \"Firmness: Medium\", \"Composition: Hybrid\" = NÃO SÃO VARIAÇÕES (valor único).

3. PREÇO POR VARIAÇÃO (MUITO IMPORTANTE):
   - Se os dados contêm preços diferentes por variação, CADA variação DEVE ter seu preço específico.
   - NÃO copie o mesmo preço para todas as variações.
   - Use SEMPRE o preço FINAL/SALE/CURRENT (o preço que o cliente realmente paga). NÃO use o preço \"was\"/\"original\"/\"list\"/\"compare_at\" (preço antigo riscado).
   - Se houver desconto/promoção, use o preço COM desconto (deliveredPrice, salePrice, finalPrice), NÃO o preço sem desconto.
   - Procure preços em: offers, variants, variations, price maps, ou qualquer estrutura que associe opção a preço.
   - Se não encontrar preço específico para uma variação, use o preço base do produto.

4. PESO POR VARIAÇÃO:
   - Se os dados contêm pesos diferentes por variação (ex: weights_found, specifications, shipping weight), converta de lbs para kg (1 lb = 0.4536 kg) e atribua a cada variação.
   - Se houver pesos diferentes por tamanho/opção, CADA variação DEVE ter seu peso específico.

5. OUT OF STOCK POR VARIAÇÃO (MUITO IMPORTANTE):
   - Analise a disponibilidade de CADA variação INDIVIDUALMENTE.
   - Se UMA variação específica está \"Out of stock\"/\"Unavailable\"/\"Sold out\", SOMENTE essa variação deve ter \"out_of_stock\": true.
   - As OUTRAS variações que estão disponíveis DEVEM ter \"out_of_stock\": false.
   - NÃO marque todas como out_of_stock só porque uma está indisponível.
   - Procure campos como: availability, in_stock, stock_status, availableQuantity, \"Out of stock\" nos dados de cada variação.

6. PESO (kg):
   - Se o peso estiver em LIBRAS (lbs/pounds), CONVERTA: 1 lb = 0.4536 kg.
   - Procure peso em: specifications, weights_found, shipping weight, product weight, item weight.
   - Se não encontrar o peso exato, ESTIME com base no tipo de produto.
   - Adicione 15% de margem de segurança sobre o peso estimado.

7. DESCRIÇÃO: Se não encontrar descrição detalhada, CRIE uma baseada no nome e características. Máximo 300 caracteres. NÃO inclua textos de cookies, políticas de privacidade, termos de uso ou qualquer conteúdo que não seja sobre o produto.

8. IMAGEM: Extraia todas as URLs de imagens disponíveis. Se não encontrar, use array vazio [].

9. VALOR: Use número decimal com 2 casas (ex: 99.99). Preço em USD. Use SEMPRE o preço FINAL que o cliente paga (com desconto/promoção aplicado). NÃO use o preço \"was\"/\"original\" riscado.

10. NOME: Use o nome completo do produto.

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
