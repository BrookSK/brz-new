<?php
namespace App\Controllers;

use App\Core\Request;
use Config\Database;

class AdminCopilotoController extends Controller {

    /**
     * Página principal — Configurações do Co-Piloto
     */
    public function index(Request $request) {
        $pdo = Database::getConnection();
        $configs = $this->carregarConfigs($pdo);
        $stats = $this->carregarEstatisticas($pdo);

        $title = 'Co-Piloto Braziliana — Configurações';
        $sidebarActive = 'copiloto';
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';
        ob_start();
        include __DIR__ . '/../Views/admin/copiloto/index.php';
        $content = ob_get_clean();
        include __DIR__ . '/../Views/layouts/admin.php';
    }

    /**
     * Salvar configurações do Co-Piloto
     */
    public function salvar(Request $request) {
        $pdo = Database::getConnection();

        $campos = [
            'ativo' => $request->getParam('copiloto_ativo') ? '1' : '0',
            'modo' => in_array($request->getParam('copiloto_modo'), ['desativado', 'somente_admins', 'publico']) 
                ? $request->getParam('copiloto_modo') 
                : 'desativado',
            'api_key_claude' => trim((string) $request->getParam('api_key_claude', '')),
            'modelo_ia' => 'claude-sonnet-4-5', // Hardcoded — nunca substituir
            'backend_url' => trim((string) $request->getParam('backend_url', 'https://copiloto.braziliana.com.br')),
            'max_msgs_por_minuto' => (string) max(1, (int) $request->getParam('max_msgs_por_minuto', 20)),
            'timeout_claude_ms' => (string) max(1000, (int) $request->getParam('timeout_claude_ms', 15000)),
            'cambio_usd_brl' => (string) max(0.01, (float) $request->getParam('cambio_usd_brl', 5.80)),
            'gatilho_tempo_ms' => (string) max(5000, (int) $request->getParam('gatilho_tempo_ms', 30000)),
            'max_historico_enviado' => (string) max(1, (int) $request->getParam('max_historico_enviado', 10)),
            'qrcode_mensagem' => trim((string) $request->getParam('copiloto_qrcode_mensagem', '')),
        ];

        // Sincronizar 'ativo' com 'modo'
        if ($campos['modo'] === 'desativado') {
            $campos['ativo'] = '0';
        } else {
            $campos['ativo'] = '1';
        }

        foreach ($campos as $chave => $valor) {
            $this->salvarConfig($pdo, $chave, $valor);
        }

        $_SESSION['flash_success'] = 'Configurações do Co-Piloto salvas com sucesso.';
        header('Location: /admin/copiloto');
        exit;
    }

    /**
     * Aba de Aprendizado da IA
     */
    public function aprendizado(Request $request) {
        $pdo = Database::getConnection();
        $this->garantirTabela($pdo, 'copiloto_aprendizado');

        $status = $request->getParam('status', 'pendente');
        $pagina = max(1, (int) $request->getParam('page', 1));
        $porPagina = 20;
        $offset = ($pagina - 1) * $porPagina;

        $where = $status !== 'todos' ? "WHERE status = ?" : "";
        $params = $status !== 'todos' ? [$status] : [];

        $stTotal = $pdo->prepare("SELECT COUNT(*) FROM copiloto_aprendizado $where");
        $stTotal->execute($params);
        $total = (int) $stTotal->fetchColumn();

        $st = $pdo->prepare("SELECT * FROM copiloto_aprendizado $where ORDER BY 
            CASE impacto_estimado WHEN 'alto' THEN 1 WHEN 'medio' THEN 2 ELSE 3 END,
            frequencia DESC, criado_em DESC LIMIT $porPagina OFFSET $offset");
        $st->execute($params);
        $pendencias = $st->fetchAll(\PDO::FETCH_ASSOC);

        // Contadores
        $stCounts = $pdo->query("SELECT status, COUNT(*) as total FROM copiloto_aprendizado GROUP BY status");
        $contadores = [];
        while ($row = $stCounts->fetch(\PDO::FETCH_ASSOC)) {
            $contadores[$row['status']] = (int) $row['total'];
        }

        $title = 'Co-Piloto — Aprendizado da IA';
        $sidebarActive = 'copiloto';
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';
        ob_start();
        include __DIR__ . '/../Views/admin/copiloto/aprendizado.php';
        $content = ob_get_clean();
        include __DIR__ . '/../Views/layouts/admin.php';
    }

    /**
     * Aceitar pendência de aprendizado
     */
    public function aceitarPendencia(Request $request) {
        $id = (int) $request->getParam('id', 0);
        if ($id <= 0) {
            $_SESSION['flash_error'] = 'ID inválido.';
            header('Location: /admin/copiloto/aprendizado');
            exit;
        }

        $pdo = Database::getConnection();
        $st = $pdo->prepare("UPDATE copiloto_aprendizado SET status = 'aceita', atualizado_em = NOW() WHERE id = ?");
        $st->execute([$id]);

        $_SESSION['flash_success'] = 'Pendência aceita com sucesso.';
        header('Location: /admin/copiloto/aprendizado');
        exit;
    }

    /**
     * Recusar pendência de aprendizado
     */
    public function recusarPendencia(Request $request) {
        $id = (int) $request->getParam('id', 0);
        if ($id <= 0) {
            $_SESSION['flash_error'] = 'ID inválido.';
            header('Location: /admin/copiloto/aprendizado');
            exit;
        }

        $pdo = Database::getConnection();
        $st = $pdo->prepare("UPDATE copiloto_aprendizado SET status = 'recusada', atualizado_em = NOW() WHERE id = ?");
        $st->execute([$id]);

        $_SESSION['flash_success'] = 'Pendência recusada.';
        header('Location: /admin/copiloto/aprendizado');
        exit;
    }

    /**
     * Aba de Conteúdo de Referência
     */
    public function conteudo(Request $request) {
        $pdo = Database::getConnection();
        $this->garantirTabela($pdo, 'copiloto_conteudo');

        $st = $pdo->query("SELECT * FROM copiloto_conteudo ORDER BY criado_em DESC");
        $arquivos = $st->fetchAll(\PDO::FETCH_ASSOC);

        $title = 'Co-Piloto — Conteúdo de Referência';
        $sidebarActive = 'copiloto';
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';
        ob_start();
        include __DIR__ . '/../Views/admin/copiloto/conteudo.php';
        $content = ob_get_clean();
        include __DIR__ . '/../Views/layouts/admin.php';
    }

    /**
     * Upload de conteúdo de referência
     */
    public function conteudoUpload(Request $request) {
        $pdo = Database::getConnection();
        $this->garantirTabela($pdo, 'copiloto_conteudo');

        if (empty($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
            $_SESSION['flash_error'] = 'Erro no upload do arquivo.';
            header('Location: /admin/copiloto/conteudo');
            exit;
        }

        $file = $_FILES['arquivo'];
        $extensoesPermitidas = ['pdf', 'docx', 'txt', 'md'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $extensoesPermitidas)) {
            $_SESSION['flash_error'] = 'Formato não suportado. Use: PDF, DOCX, TXT ou MD.';
            header('Location: /admin/copiloto/conteudo');
            exit;
        }

        if ($file['size'] > 50 * 1024 * 1024) {
            $_SESSION['flash_error'] = 'Arquivo excede o limite de 50MB.';
            header('Location: /admin/copiloto/conteudo');
            exit;
        }

        $titulo = trim((string) $request->getParam('titulo', pathinfo($file['name'], PATHINFO_FILENAME)));
        $categoria = (string) $request->getParam('categoria', 'outro');
        $notasIa = trim((string) $request->getParam('notas_ia', ''));
        $ativarImediatamente = $request->getParam('ativar_imediatamente') ? 1 : 0;

        // Salvar arquivo
        $uploadDir = __DIR__ . '/../../public/uploads/copiloto/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $nomeArquivo = uniqid('cop_') . '.' . $ext;
        $caminhoFinal = $uploadDir . $nomeArquivo;
        move_uploaded_file($file['tmp_name'], $caminhoFinal);

        $st = $pdo->prepare("INSERT INTO copiloto_conteudo 
            (titulo, categoria, arquivo_nome, arquivo_path, arquivo_tamanho, arquivo_tipo, notas_ia, status, ativo) 
            VALUES (?, ?, ?, ?, ?, ?, ?, 'processando', ?)");
        $st->execute([
            $titulo,
            $categoria,
            $file['name'],
            '/uploads/copiloto/' . $nomeArquivo,
            $file['size'],
            $ext,
            $notasIa ?: null,
            $ativarImediatamente
        ]);

        $_SESSION['flash_success'] = 'Arquivo enviado. O processamento será feito pelo backend do Co-Piloto.';
        header('Location: /admin/copiloto/conteudo');
        exit;
    }

    /**
     * Remover conteúdo de referência
     */
    public function conteudoRemover(Request $request) {
        $id = (int) $request->getParam('id', 0);
        if ($id <= 0) {
            $_SESSION['flash_error'] = 'ID inválido.';
            header('Location: /admin/copiloto/conteudo');
            exit;
        }

        $pdo = Database::getConnection();

        // Buscar path do arquivo para deletar
        $st = $pdo->prepare("SELECT arquivo_path FROM copiloto_conteudo WHERE id = ?");
        $st->execute([$id]);
        $row = $st->fetch(\PDO::FETCH_ASSOC);
        if ($row && !empty($row['arquivo_path'])) {
            $fullPath = __DIR__ . '/../../public' . $row['arquivo_path'];
            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }
        }

        $pdo->prepare("DELETE FROM copiloto_conteudo WHERE id = ?")->execute([$id]);

        $_SESSION['flash_success'] = 'Conteúdo removido.';
        header('Location: /admin/copiloto/conteudo');
        exit;
    }

    /**
     * Toggle ativo/inativo de conteúdo
     */
    public function conteudoToggle(Request $request) {
        $id = (int) $request->getParam('id', 0);
        if ($id <= 0) {
            $_SESSION['flash_error'] = 'ID inválido.';
            header('Location: /admin/copiloto/conteudo');
            exit;
        }

        $pdo = Database::getConnection();
        $pdo->prepare("UPDATE copiloto_conteudo SET ativo = NOT ativo, atualizado_em = NOW() WHERE id = ?")->execute([$id]);

        $_SESSION['flash_success'] = 'Status atualizado.';
        header('Location: /admin/copiloto/conteudo');
        exit;
    }

    /**
     * Aba de Cancelamentos
     */
    public function cancelamentos(Request $request) {
        $pdo = Database::getConnection();
        $this->garantirTabela($pdo, 'copiloto_cancelamentos');

        $status = $request->getParam('status', 'aguardando_revisao');
        $where = $status !== 'todos' ? "WHERE status = ?" : "";
        $params = $status !== 'todos' ? [$status] : [];

        $st = $pdo->prepare("SELECT * FROM copiloto_cancelamentos $where ORDER BY solicitado_em DESC LIMIT 100");
        $st->execute($params);
        $cancelamentos = $st->fetchAll(\PDO::FETCH_ASSOC);

        $stCounts = $pdo->query("SELECT status, COUNT(*) as total FROM copiloto_cancelamentos GROUP BY status");
        $contadores = [];
        while ($row = $stCounts->fetch(\PDO::FETCH_ASSOC)) {
            $contadores[$row['status']] = (int) $row['total'];
        }

        $title = 'Co-Piloto — Cancelamentos';
        $sidebarActive = 'copiloto';
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';
        ob_start();
        include __DIR__ . '/../Views/admin/copiloto/cancelamentos.php';
        $content = ob_get_clean();
        include __DIR__ . '/../Views/layouts/admin.php';
    }

    /**
     * Autorizar cancelamento
     */
    public function autorizarCancelamento(Request $request) {
        $id = (int) $request->getParam('id', 0);
        $pdo = Database::getConnection();

        $st = $pdo->prepare("UPDATE copiloto_cancelamentos SET status = 'autorizado', processado_em = NOW() WHERE id = ? AND status = 'aguardando_revisao'");
        $st->execute([$id]);

        $_SESSION['flash_success'] = 'Cancelamento autorizado. O reembolso será processado pelo backend.';
        header('Location: /admin/copiloto/cancelamentos');
        exit;
    }

    /**
     * Recusar cancelamento
     */
    public function recusarCancelamento(Request $request) {
        $id = (int) $request->getParam('id', 0);
        $motivo = trim((string) $request->getParam('motivo', ''));
        $pdo = Database::getConnection();

        $st = $pdo->prepare("UPDATE copiloto_cancelamentos SET status = 'recusado', motivo_recusa = ?, processado_em = NOW() WHERE id = ? AND status = 'aguardando_revisao'");
        $st->execute([$motivo ?: null, $id]);

        $_SESSION['flash_success'] = 'Cancelamento recusado.';
        header('Location: /admin/copiloto/cancelamentos');
        exit;
    }

    // ========== API endpoints para o backend Node.js ==========

    /**
     * API: Retorna configurações do copiloto (chamado pelo backend Node.js)
     */
    public function apiConfig(Request $request) {
        header('Content-Type: application/json; charset=UTF-8');
        $pdo = Database::getConnection();
        $configs = $this->carregarConfigs($pdo);
        echo json_encode(['success' => true, 'config' => $configs]);
        exit;
    }

    /**
     * API: Verifica se copiloto está ativo e retorna config pública (para o widget)
     */
    public function apiStatus(Request $request) {
        header('Content-Type: application/json; charset=UTF-8');
        $pdo = Database::getConnection();
        $configs = $this->carregarConfigs($pdo);
        echo json_encode([
            'ativo' => ($configs['ativo'] ?? '0') === '1',
            'backend_url' => $configs['backend_url'] ?? '',
            'gatilho_tempo_ms' => (int) ($configs['gatilho_tempo_ms'] ?? 30000),
        ]);
        exit;
    }

    /**
     * API: Log de interação (chamado pelo backend Node.js)
     */
    public function apiLog(Request $request) {
        header('Content-Type: application/json; charset=UTF-8');
        try {
            $body = $request->getBody();
            $pdo = Database::getConnection();
            $this->garantirTabela($pdo, 'copiloto_mensagens');

            $sessaoId = (string) ($body['sessao_id'] ?? 'anon_' . substr(md5($_SERVER['REMOTE_ADDR'] ?? ''), 0, 12));

            // Log mensagem do usuário
            if (!empty($body['mensagem_usuario'])) {
                $st = $pdo->prepare("INSERT INTO copiloto_mensagens (sessao_id, role, conteudo, contexto_pagina) VALUES (?, 'user', ?, ?)");
                $st->execute([$sessaoId, $body['mensagem_usuario'], json_encode($body['contexto_pagina'] ?? null)]);
            }

            // Log resposta da Bri
            if (!empty($body['resposta_bri'])) {
                $st = $pdo->prepare("INSERT INTO copiloto_mensagens (sessao_id, role, conteudo, acao, parametros_acao, contexto_pagina, tokens_usados) VALUES (?, 'assistant', ?, ?, ?, ?, ?)");
                $st->execute([
                    $sessaoId,
                    $body['resposta_bri'],
                    $body['acao'] ?? null,
                    json_encode($body['parametros_acao'] ?? null),
                    json_encode($body['contexto_pagina'] ?? null),
                    (int) ($body['tokens_usados'] ?? 0)
                ]);
            }

            // Atualizar ou criar sessão
            $this->garantirTabela($pdo, 'copiloto_sessoes');
            $stCheck = $pdo->prepare("SELECT id FROM copiloto_sessoes WHERE sessao_id = ? LIMIT 1");
            $stCheck->execute([$sessaoId]);
            if ($stCheck->fetchColumn()) {
                $pdo->prepare("UPDATE copiloto_sessoes SET total_mensagens = total_mensagens + 1, ultima_interacao = NOW() WHERE sessao_id = ?")->execute([$sessaoId]);
            } else {
                $pdo->prepare("INSERT INTO copiloto_sessoes (sessao_id, usuario_id, pagina_origem, ip, total_mensagens) VALUES (?, ?, ?, ?, 1)")->execute([
                    $sessaoId,
                    $_SESSION['usuario_id'] ?? null,
                    $body['contexto_pagina']['url'] ?? null,
                    $_SERVER['REMOTE_ADDR'] ?? null
                ]);
            }

            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    /**
     * API: Receber pendência de aprendizado (chamado pelo backend Node.js)
     */
    public function apiAprendizado(Request $request) {
        header('Content-Type: application/json; charset=UTF-8');
        try {
            $body = $request->getBody();
            $pdo = Database::getConnection();
            $this->garantirTabela($pdo, 'copiloto_aprendizado');

            if (empty($body['gerar_pendencia'])) {
                echo json_encode(['success' => false, 'error' => 'gerar_pendencia ausente']);
                exit;
            }

            $st = $pdo->prepare("INSERT INTO copiloto_aprendizado 
                (tipos, resumo_problema, impacto_estimado, sessao_id, mensagem_usuario, resposta_bri, pagina_origem,
                 documento_afetado, topico_afetado, texto_sugerido, justificativa,
                 etapa_falhou, sugestao_melhoria, area_responsavel)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $st->execute([
                json_encode($body['tipos'] ?? []),
                $body['resumo_problema'] ?? 'Sem resumo',
                $body['impacto_estimado'] ?? 'medio',
                $body['sessao_id'] ?? null,
                $body['mensagem_usuario'] ?? null,
                $body['resposta_bri'] ?? null,
                $body['pagina_origem'] ?? null,
                $body['documento_afetado'] ?? null,
                $body['topico_afetado'] ?? null,
                $body['texto_sugerido'] ?? null,
                $body['justificativa_juridica'] ?? null,
                $body['etapa_processo_falhou'] ?? null,
                $body['sugestao_processo'] ?? null,
                $body['area_responsavel'] ?? null
            ]);

            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
        } catch (\Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }

    // ========== Helpers ==========

    private function carregarConfigs(\PDO $pdo): array {
        $configs = [];
        try {
            $st = $pdo->prepare("SELECT chave, valor FROM configuracoes_sistema WHERE chave LIKE 'copiloto_%'");
            $st->execute();
            while ($row = $st->fetch(\PDO::FETCH_ASSOC)) {
                // Remove prefixo 'copiloto_' para uso interno
                $key = preg_replace('/^copiloto_/', '', $row['chave']);
                $configs[$key] = $row['valor'];
            }
        } catch (\Exception $e) {
            // Tabela pode não existir ainda
        }
        return $configs;
    }

    private function salvarConfig(\PDO $pdo, string $chave, string $valor): void {
        $chaveCompleta = 'copiloto_' . $chave;
        $st = $pdo->prepare("SELECT COUNT(*) FROM configuracoes_sistema WHERE chave = ?");
        $st->execute([$chaveCompleta]);
        if ((int) $st->fetchColumn() > 0) {
            $pdo->prepare("UPDATE configuracoes_sistema SET valor = ? WHERE chave = ?")->execute([$valor, $chaveCompleta]);
        } else {
            $pdo->prepare("INSERT INTO configuracoes_sistema (chave, valor) VALUES (?, ?)")->execute([$chaveCompleta, $valor]);
        }
    }

    private function carregarEstatisticas(\PDO $pdo): array {
        $stats = [
            'total_sessoes_hoje' => 0,
            'total_mensagens_hoje' => 0,
            'total_pendencias' => 0,
            'total_cancelamentos_pendentes' => 0,
        ];
        try {
            $this->garantirTabela($pdo, 'copiloto_sessoes');
            $st = $pdo->query("SELECT COUNT(*) FROM copiloto_sessoes WHERE DATE(criado_em) = CURDATE()");
            $stats['total_sessoes_hoje'] = (int) $st->fetchColumn();

            $this->garantirTabela($pdo, 'copiloto_mensagens');
            $st = $pdo->query("SELECT COUNT(*) FROM copiloto_mensagens WHERE DATE(criado_em) = CURDATE()");
            $stats['total_mensagens_hoje'] = (int) $st->fetchColumn();

            $this->garantirTabela($pdo, 'copiloto_aprendizado');
            $st = $pdo->query("SELECT COUNT(*) FROM copiloto_aprendizado WHERE status = 'pendente'");
            $stats['total_pendencias'] = (int) $st->fetchColumn();

            $this->garantirTabela($pdo, 'copiloto_cancelamentos');
            $st = $pdo->query("SELECT COUNT(*) FROM copiloto_cancelamentos WHERE status = 'aguardando_revisao'");
            $stats['total_cancelamentos_pendentes'] = (int) $st->fetchColumn();
        } catch (\Exception $e) {}
        return $stats;
    }

    private function garantirTabela(\PDO $pdo, string $tabela): void {
        try {
            $st = $pdo->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
            $st->execute([$tabela]);
            if ((int) $st->fetchColumn() === 0) {
                // Tabela não existe — tentar rodar a migration
                $migrationFile = __DIR__ . '/../../database/migrations/120_create_copiloto_schema.sql';
                if (file_exists($migrationFile)) {
                    $sql = file_get_contents($migrationFile);
                    $pdo->exec($sql);
                }
            }
        } catch (\Exception $e) {}
    }
}
