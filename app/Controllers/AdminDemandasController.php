<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;

class AdminDemandasController extends Controller {
    private $db;

    public function __construct() {
        $this->db = \Config\Database::getConnection();
        $this->ensureTables();
    }

    public function painel(Request $request) {
        $auth = new AuthService(); $auth->requerPerfis(['admin','suporte']);
        // Verificar testes expirados ao carregar o painel
        $this->verificarTestesExpirados();
        $demandas = $this->listar();
        $title = 'Painel de Demandas'; $sidebarActive = 'demandas-painel';
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';
        ob_start(); require __DIR__ . '/../Views/admin/demandas/painel.php'; $content = ob_get_clean();
        include __DIR__ . '/../Views/layouts/admin.php';
    }

    public function nova(Request $request) {
        $auth = new AuthService(); $auth->requerPerfis(['admin','suporte']);
        // Pegar nome do usuário logado
        $nomeUsuario = '';
        try {
            $uid = $_SESSION['usuario_id'] ?? 0;
            if ($uid) { $st = $this->db->prepare("SELECT nome FROM usuarios WHERE id = ? LIMIT 1"); $st->execute([$uid]); $nomeUsuario = (string)($st->fetchColumn() ?: ''); }
        } catch (\Exception $e) {}
        $title = 'Nova Solicitação'; $sidebarActive = 'demandas-nova';
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';
        ob_start(); require __DIR__ . '/../Views/admin/demandas/nova.php'; $content = ob_get_clean();
        include __DIR__ . '/../Views/layouts/admin.php';
    }

    public function concluidos(Request $request) {
        $auth = new AuthService(); $auth->requerPerfis(['admin','suporte']);
        $demandas = $this->listar('concluido');
        $title = 'Demandas Concluídas'; $sidebarActive = 'demandas-concluidos';
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';
        ob_start(); require __DIR__ . '/../Views/admin/demandas/concluidos.php'; $content = ob_get_clean();
        include __DIR__ . '/../Views/layouts/admin.php';
    }

    public function criar(Request $request) {
        $auth = new AuthService(); $auth->requerPerfis(['admin','suporte']);
        $body = $_POST;
        $tipo = $body['tipo_solicitacao'] ?? 'funcao';

        $etapas = [];
        if ($tipo === 'funcao' && !empty($body['etapa_desc']) && is_array($body['etapa_desc'])) {
            foreach ($body['etapa_desc'] as $i => $desc) {
                if (trim($desc) !== '' && trim($body['etapa_custo'][$i] ?? '') !== '') {
                    $etapas[] = ['descricao' => trim($desc), 'custo' => trim($body['etapa_custo'][$i])];
                }
            }
        }

        // Para bugs, montar bloco2 e bloco3 com os dados do bug
        if ($tipo === 'bug') {
            $bugData = [
                'erro' => $body['bug_erro'] ?? '',
                'acao' => $body['bug_acao'] ?? '',
                'quando' => $body['bug_quando'] ?? '',
                'onde' => $body['bug_onde'] ?? '',
                'prints' => $body['bug_prints'] ?? '',
                'detalhes' => $body['bug_detalhes'] ?? '',
                'prioridade' => $body['bug_prioridade'] ?? 'media',
            ];
            $bloco2_problema = "ERRO: " . $bugData['erro'] . "\n\nO QUE FAZIA: " . $bugData['acao'];
            $bloco2_melhoria = "Corrigir o bug para que funcione corretamente.";
            $bloco2_consequencia = "QUANDO: " . $bugData['quando'] . "\nONDE: " . $bugData['onde'];
            $bloco3_financeiro = "Bug - Prioridade: " . strtoupper($bugData['prioridade']);
            $bloco3_jornada = "PRINTS/EVIDÊNCIAS: " . $bugData['prints'];
            $bloco3_detalhes = "DETALHES: " . $bugData['detalhes'];
        }

        $stmt = $this->db->prepare("INSERT INTO demandas (titulo, solicitante, solicitante_email, bloco1_solicitante, bloco1_titulo, bloco2_problema, bloco2_melhoria, bloco2_consequencia, bloco3_financeiro, bloco3_capital_giro, bloco3_custos_operacionais, bloco3_jornada_cliente, bloco3_equipe, bloco3_conflitos, bloco4_etapas, bloco5_novo_ou_existente, bloco5_ferramentas, bloco5_regras, bloco5_usuarios, criado_por) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)");

        // Buscar email do solicitante
        $emailSolicitante = '';
        try {
            $uid = $_SESSION['usuario_id'] ?? 0;
            if ($uid) { $stE = $this->db->prepare("SELECT email FROM usuarios WHERE id = ? LIMIT 1"); $stE->execute([$uid]); $emailSolicitante = (string)($stE->fetchColumn() ?: ''); }
        } catch (\Exception $e) {}

        $stmt->execute([
            ($tipo === 'bug' ? '[BUG] ' : '') . ($body['bloco1_titulo'] ?? ''),
            $body['bloco1_solicitante'] ?? '',
            $emailSolicitante,
            $body['bloco1_solicitante'] ?? '',
            ($tipo === 'bug' ? '[BUG] ' : '') . ($body['bloco1_titulo'] ?? ''),
            $tipo === 'bug' ? $bloco2_problema : ($body['bloco2_problema'] ?? ''),
            $tipo === 'bug' ? $bloco2_melhoria : ($body['bloco2_melhoria'] ?? ''),
            $tipo === 'bug' ? $bloco2_consequencia : ($body['bloco2_consequencia'] ?? ''),
            $tipo === 'bug' ? $bloco3_financeiro : ($body['bloco3_financeiro'] ?? ''),
            $tipo === 'bug' ? '' : ($body['bloco3_capital_giro'] ?? ''),
            $tipo === 'bug' ? $bloco3_detalhes : ($body['bloco3_custos_operacionais'] ?? ''),
            $tipo === 'bug' ? $bloco3_jornada : ($body['bloco3_jornada_cliente'] ?? ''),
            $tipo === 'bug' ? '' : ($body['bloco3_equipe'] ?? ''),
            $tipo === 'bug' ? '' : ($body['bloco3_conflitos'] ?? ''),
            json_encode($etapas, JSON_UNESCAPED_UNICODE),
            $tipo === 'bug' ? 'Bug/Correção' : ($body['bloco5_novo_ou_existente'] ?? ''),
            $tipo === 'bug' ? ($body['bug_onde'] ?? '') : ($body['bloco5_ferramentas'] ?? ''),
            $tipo === 'bug' ? '' : ($body['bloco5_regras'] ?? ''),
            $tipo === 'bug' ? '' : ($body['bloco5_usuarios'] ?? ''),
            $_SESSION['usuario_id'] ?? null,
        ]);

        $id = (int)$this->db->lastInsertId();
        $obs = $tipo === 'bug' ? 'Bug reportado - Prioridade: ' . strtoupper($body['bug_prioridade'] ?? 'media') : 'Demanda criada';
        $this->registrarHistorico($id, null, 'pendente', $obs);

        // Processar arquivos anexados (prints de bug, etc)
        $this->processarArquivosDemanda($id);

        $_SESSION['message'] = $tipo === 'bug'
            ? 'Bug reportado com prioridade ' . strtoupper($body['bug_prioridade'] ?? 'media') . '! Já aparece no Painel.'
            : 'Demanda registrada com sucesso! Ela já aparece no Painel de Demandas.';
        $_SESSION['message_type'] = 'success';
        $this->redirect('/admin/demandas/painel');
    }

    public function moverStatus(Request $request, $id) {
        $auth = new AuthService(); $auth->requerPerfis(['admin']);
        $novoStatus = $_POST['status'] ?? '';
        $nota = $_POST['nota'] ?? '';
        $id = (int)$id;

        $validStatuses = ['pendente','em_analise','em_execucao','em_teste','recusado','concluido'];
        if (!in_array($novoStatus, $validStatuses)) { $this->redirect('/admin/demandas/painel'); return; }

        // Regra: só 1 em execução por vez
        if ($novoStatus === 'em_execucao') {
            $st = $this->db->query("SELECT COUNT(*) FROM demandas WHERE status = 'em_execucao'");
            if ((int)$st->fetchColumn() > 0) {
                $_SESSION['message'] = 'Já existe uma demanda em execução. Conclua ou mova a demanda atual antes de iniciar uma nova.';
                $_SESSION['message_type'] = 'danger';
                $this->redirect('/admin/demandas/painel'); return;
            }
        }

        $atual = $this->db->prepare("SELECT * FROM demandas WHERE id = ?"); $atual->execute([$id]);
        $demanda = $atual->fetch(\PDO::FETCH_ASSOC);
        $statusAnterior = $demanda['status'] ?? '';

        $set = ['status = :st', 'updated_at = NOW()'];
        $params = [':st' => $novoStatus, ':id' => $id];

        if ($novoStatus === 'em_execucao') {
            $set[] = 'inicio_execucao = NOW()';
            // Prazo: 5 dias úteis
            $prazo = new \DateTime();
            $dias = 0;
            while ($dias < 5) { $prazo->modify('+1 day'); if ($prazo->format('N') < 6) $dias++; }
            $set[] = 'prazo_entrega = :prazo';
            $params[':prazo'] = $prazo->format('Y-m-d');
        }
        if ($novoStatus === 'em_teste') {
            $set[] = 'inicio_teste = NOW()';
            $set[] = 'teste_expirado = 0';
        }
        if ($novoStatus === 'concluido') { $set[] = 'concluido_em = NOW()'; }
        if ($novoStatus === 'recusado') {
            $set[] = 'motivo_recusa = :motivo';
            $params[':motivo'] = $nota;
        }
        if ($nota !== '') { $set[] = 'nota_admin = :nota'; $params[':nota'] = $nota; }

        $this->db->prepare("UPDATE demandas SET " . implode(', ', $set) . " WHERE id = :id")->execute($params);
        $this->registrarHistorico($id, $statusAnterior, $novoStatus, $nota ?: null);

        // Enviar email ao solicitante quando recusado
        if ($novoStatus === 'recusado') {
            $this->enviarEmailRecusa($demanda, $nota);
        }

        // Enviar email ao solicitante quando movido para teste (avisar do prazo de 24h úteis)
        if ($novoStatus === 'em_teste') {
            $this->enviarEmailTeste($demanda);
        }

        $_SESSION['message'] = 'Status atualizado para: ' . ucfirst(str_replace('_', ' ', $novoStatus));
        $_SESSION['message_type'] = 'success';
        $this->redirect('/admin/demandas/painel');
    }

    public function detalhe(Request $request, $id) {
        $auth = new AuthService(); $auth->requerPerfis(['admin','suporte']);
        $demanda = $this->getById((int)$id);
        if (!$demanda) { $_SESSION['message'] = 'Demanda não encontrada.'; $_SESSION['message_type'] = 'danger'; $this->redirect('/admin/demandas/painel'); return; }
        $historico = $this->getHistorico((int)$id);
        $mensagens = $this->getMensagens((int)$id);
        $arquivosBug = $this->getArquivosDemanda((int)$id);
        $title = 'Demanda #' . $id; $sidebarActive = 'demandas-painel';
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';
        ob_start(); require __DIR__ . '/../Views/admin/demandas/detalhe.php'; $content = ob_get_clean();
        include __DIR__ . '/../Views/layouts/admin.php';
    }

    public function pdf(Request $request, $id) {
        $auth = new AuthService(); $auth->requerPerfis(['admin','suporte']);
        $d = $this->getById((int)$id);
        if (!$d) { $this->redirect('/admin/demandas/concluidos'); return; }
        $etapas = json_decode($d['bloco4_etapas'] ?? '[]', true) ?: [];
        while (ob_get_level()) ob_end_clean();
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Demanda #' . $d['id'] . '</title><style>body{font-family:Arial,sans-serif;font-size:12px;margin:40px;color:#1e293b;}h1{font-size:18px;border-bottom:2px solid #1e293b;padding-bottom:8px;}h2{font-size:14px;margin-top:24px;color:#334155;border-bottom:1px solid #e2e8f0;padding-bottom:4px;}p{margin:4px 0;line-height:1.5;}table{width:100%;border-collapse:collapse;margin:8px 0;}th,td{border:1px solid #e2e8f0;padding:6px 8px;text-align:left;font-size:11px;}th{background:#f8fafc;}.footer{margin-top:40px;border-top:1px solid #e2e8f0;padding-top:10px;font-size:10px;color:#94a3b8;text-align:center;}@media print{body{margin:20px;}}</style></head><body>';
        echo '<h1>BRAZILIANA SHOP — Processo de Demanda</h1>';
        echo '<table style="border:none;"><tr style="border:none;"><td style="border:none;"><strong>Título:</strong> ' . htmlspecialchars($d['bloco1_titulo']) . '</td><td style="border:none;"><strong>Solicitante:</strong> ' . htmlspecialchars($d['bloco1_solicitante']) . '</td></tr><tr style="border:none;"><td style="border:none;"><strong>Envio:</strong> ' . date('d/m/Y H:i', strtotime($d['created_at'])) . '</td><td style="border:none;"><strong>Conclusão:</strong> ' . ($d['concluido_em'] ? date('d/m/Y H:i', strtotime($d['concluido_em'])) : 'N/A') . '</td></tr></table>';
        echo '<h2>1. Identificação</h2><p><strong>Solicitante:</strong> ' . htmlspecialchars($d['bloco1_solicitante']) . '</p><p><strong>Título:</strong> ' . htmlspecialchars($d['bloco1_titulo']) . '</p>';
        echo '<h2>2. Justificativa</h2><p><strong>Problema:</strong><br>' . nl2br(htmlspecialchars($d['bloco2_problema'])) . '</p><p><strong>Melhoria:</strong><br>' . nl2br(htmlspecialchars($d['bloco2_melhoria'])) . '</p><p><strong>Consequência:</strong><br>' . nl2br(htmlspecialchars($d['bloco2_consequencia'])) . '</p>';
        echo '<h2>3. Impactos</h2><p><strong>3.1 Financeiro:</strong><br>' . nl2br(htmlspecialchars($d['bloco3_financeiro'])) . '</p><p><strong>3.2 Capital de giro:</strong><br>' . nl2br(htmlspecialchars($d['bloco3_capital_giro'])) . '</p><p><strong>3.3 Custos operacionais:</strong><br>' . nl2br(htmlspecialchars($d['bloco3_custos_operacionais'])) . '</p><p><strong>3.4 Jornada do cliente:</strong><br>' . nl2br(htmlspecialchars($d['bloco3_jornada_cliente'])) . '</p><p><strong>3.5 Equipe:</strong><br>' . nl2br(htmlspecialchars($d['bloco3_equipe'])) . '</p><p><strong>3.6 Conflitos:</strong><br>' . nl2br(htmlspecialchars($d['bloco3_conflitos'])) . '</p>';
        echo '<h2>4. Etapas e Custos</h2><table><thead><tr><th>Etapa</th><th>Custo</th></tr></thead><tbody>';
        foreach ($etapas as $et) echo '<tr><td>' . htmlspecialchars($et['descricao'] ?? '') . '</td><td>' . htmlspecialchars($et['custo'] ?? '') . '</td></tr>';
        echo '</tbody></table>';
        echo '<h2>5. Execução</h2><p><strong>Novo/existente:</strong><br>' . nl2br(htmlspecialchars($d['bloco5_novo_ou_existente'])) . '</p><p><strong>Ferramentas:</strong><br>' . nl2br(htmlspecialchars($d['bloco5_ferramentas'])) . '</p><p><strong>Regras:</strong><br>' . nl2br(htmlspecialchars($d['bloco5_regras'])) . '</p><p><strong>Usuários:</strong><br>' . nl2br(htmlspecialchars($d['bloco5_usuarios'])) . '</p>';
        if ($d['nota_admin']) echo '<h2>Nota Final do Administrador</h2><p>' . nl2br(htmlspecialchars($d['nota_admin'])) . '</p>';
        echo '<div class="footer">Documento gerado em ' . date('d/m/Y H:i:s') . ' — Braziliana Shop</div>';
        echo '<script>window.print();</script></body></html>';
        exit;
    }

    /**
     * Enviar mensagem no chat da demanda (com suporte a arquivos)
     */
    public function enviarMensagem(Request $request, $id) {
        $auth = new AuthService(); $auth->requerPerfis(['admin','suporte']);
        $id = (int)$id;
        $mensagem = trim($_POST['mensagem'] ?? '');
        $uid = $_SESSION['usuario_id'] ?? 0;

        // Buscar nome do usuário
        $nomeUsuario = 'Sistema';
        try {
            if ($uid) { $st = $this->db->prepare("SELECT nome FROM usuarios WHERE id = ? LIMIT 1"); $st->execute([$uid]); $nomeUsuario = (string)($st->fetchColumn() ?: 'Usuário'); }
        } catch (\Exception $e) {}

        // Garantir tabelas
        $this->ensureChatTables();

        // Inserir mensagem (pode ser vazia se só tem arquivo)
        $msgId = null;
        if ($mensagem !== '' || !empty($_FILES['arquivos']['name'][0])) {
            $st = $this->db->prepare("INSERT INTO demanda_mensagens (demanda_id, usuario_id, usuario_nome, mensagem) VALUES (?, ?, ?, ?)");
            $st->execute([$id, $uid, $nomeUsuario, $mensagem ?: null]);
            $msgId = (int)$this->db->lastInsertId();
        }

        // Upload de arquivos
        if (!empty($_FILES['arquivos']['name'][0])) {
            $uploadDir = __DIR__ . '/../../storage/demandas/' . $id . '/';
            if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);

            foreach ($_FILES['arquivos']['name'] as $i => $nome) {
                if ($_FILES['arquivos']['error'][$i] !== UPLOAD_ERR_OK) continue;
                $nomeOriginal = basename($nome);
                $ext = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));
                $nomeArquivo = time() . '_' . $i . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $nomeOriginal);
                $destino = $uploadDir . $nomeArquivo;

                if (move_uploaded_file($_FILES['arquivos']['tmp_name'][$i], $destino)) {
                    $caminho = '/storage/demandas/' . $id . '/' . $nomeArquivo;
                    $tipo = $_FILES['arquivos']['type'][$i] ?? '';
                    $tamanho = (int)($_FILES['arquivos']['size'][$i] ?? 0);
                    $this->db->prepare("INSERT INTO demanda_arquivos (demanda_id, mensagem_id, usuario_id, nome_original, caminho, tipo, tamanho) VALUES (?,?,?,?,?,?,?)")
                        ->execute([$id, $msgId, $uid, $nomeOriginal, $caminho, $tipo, $tamanho]);
                }
            }
        }

        $_SESSION['message'] = 'Mensagem enviada!';
        $_SESSION['message_type'] = 'success';
        $this->redirect('/admin/demandas/detalhe/' . $id . '#chat');
    }

    /**
     * Upload de arquivos no formulário de criação (bug prints)
     */
    private function processarArquivosDemanda(int $demandaId): void {
        if (empty($_FILES['arquivos_bug']['name'][0])) return;

        $this->ensureChatTables();
        $uploadDir = __DIR__ . '/../../storage/demandas/' . $demandaId . '/';
        if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);

        foreach ($_FILES['arquivos_bug']['name'] as $i => $nome) {
            if ($_FILES['arquivos_bug']['error'][$i] !== UPLOAD_ERR_OK) continue;
            $nomeOriginal = basename($nome);
            $nomeArquivo = time() . '_' . $i . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $nomeOriginal);
            $destino = $uploadDir . $nomeArquivo;

            if (move_uploaded_file($_FILES['arquivos_bug']['tmp_name'][$i], $destino)) {
                $caminho = '/storage/demandas/' . $demandaId . '/' . $nomeArquivo;
                $tipo = $_FILES['arquivos_bug']['type'][$i] ?? '';
                $tamanho = (int)($_FILES['arquivos_bug']['size'][$i] ?? 0);
                $this->db->prepare("INSERT INTO demanda_arquivos (demanda_id, mensagem_id, usuario_id, nome_original, caminho, tipo, tamanho) VALUES (?,?,?,?,?,?,?)")
                    ->execute([$demandaId, null, $_SESSION['usuario_id'] ?? null, $nomeOriginal, $caminho, $tipo, $tamanho]);
            }
        }
    }

    private function getMensagens(int $demandaId): array {
        $this->ensureChatTables();
        try {
            $st = $this->db->prepare("SELECT m.*, GROUP_CONCAT(a.id) as arquivo_ids FROM demanda_mensagens m LEFT JOIN demanda_arquivos a ON a.mensagem_id = m.id WHERE m.demanda_id = ? GROUP BY m.id ORDER BY m.created_at ASC");
            $st->execute([$demandaId]);
            $msgs = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            // Buscar arquivos para cada mensagem
            foreach ($msgs as &$msg) {
                $msg['arquivos'] = [];
                if (!empty($msg['arquivo_ids'])) {
                    $ids = explode(',', $msg['arquivo_ids']);
                    $ph = implode(',', array_fill(0, count($ids), '?'));
                    $stA = $this->db->prepare("SELECT * FROM demanda_arquivos WHERE id IN ({$ph})");
                    $stA->execute($ids);
                    $msg['arquivos'] = $stA->fetchAll(\PDO::FETCH_ASSOC) ?: [];
                }
            }
            return $msgs;
        } catch (\Exception $e) { return []; }
    }

    private function getArquivosDemanda(int $demandaId): array {
        $this->ensureChatTables();
        try {
            $st = $this->db->prepare("SELECT * FROM demanda_arquivos WHERE demanda_id = ? AND mensagem_id IS NULL ORDER BY created_at ASC");
            $st->execute([$demandaId]);
            return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) { return []; }
    }

    private function ensureChatTables(): void {
        try { $this->db->query("SELECT 1 FROM demanda_mensagens LIMIT 1"); } catch (\Exception $e) {
            $f = __DIR__ . '/../../database/migrations/167_add_chat_arquivos_demandas.sql';
            if (file_exists($f)) { foreach (array_filter(array_map('trim', explode(';', file_get_contents($f)))) as $s) { if ($s && stripos($s,'--')!==0) try { $this->db->exec($s); } catch (\Exception $ex) {} } }
        }
    }

    /**
     * Verifica demandas em teste que expiraram (24h úteis) e fecha automaticamente.
     * Chamado via cron ou ao carregar o painel.
     */
    public function verificarTestesExpirados(): int {
        $fechados = 0;
        try {
            $st = $this->db->query("SELECT id, inicio_teste, solicitante, solicitante_email, bloco1_titulo FROM demandas WHERE status = 'em_teste' AND inicio_teste IS NOT NULL AND teste_expirado = 0");
            $demandas = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];

            foreach ($demandas as $d) {
                $inicio = new \DateTime($d['inicio_teste']);
                $agora = new \DateTime('now', new \DateTimeZone('America/Sao_Paulo'));

                // Calcular horas úteis decorridas (seg-sex, 8h-18h)
                $horasUteis = $this->calcularHorasUteis($inicio, $agora);

                if ($horasUteis >= 24) {
                    // Expirou — fechar demanda
                    $this->db->prepare("UPDATE demandas SET status = 'concluido', teste_expirado = 1, concluido_em = NOW(), nota_admin = CONCAT(COALESCE(nota_admin,''), '\n[AUTO] Teste expirado após 24h úteis sem parecer do solicitante.') WHERE id = ?")->execute([$d['id']]);
                    $this->registrarHistorico((int)$d['id'], 'em_teste', 'concluido', 'Teste expirado (24h úteis). Fechado automaticamente.');
                    $fechados++;
                }
            }
        } catch (\Exception $e) {
            error_log('[DEMANDAS] Erro ao verificar testes expirados: ' . $e->getMessage());
        }
        return $fechados;
    }

    /**
     * Calcula horas úteis entre duas datas (seg-sex, horário comercial 8h-18h)
     */
    private function calcularHorasUteis(\DateTime $inicio, \DateTime $fim): float {
        $horas = 0;
        $cursor = clone $inicio;

        while ($cursor < $fim) {
            $diaSemana = (int)$cursor->format('N'); // 1=seg, 7=dom
            $hora = (int)$cursor->format('G');

            // Só conta seg-sex
            if ($diaSemana <= 5) {
                // Conta a hora inteira se estiver no horário comercial (8h-18h)
                if ($hora >= 8 && $hora < 18) {
                    $horas++;
                }
            }

            $cursor->modify('+1 hour');
        }

        return $horas;
    }

    /**
     * Envia email ao solicitante quando demanda é recusada
     */
    private function enviarEmailRecusa(array $demanda, string $motivo): void {
        try {
            $email = $this->getEmailSolicitante($demanda);
            if (!$email) return;

            $titulo = $demanda['bloco1_titulo'] ?? $demanda['titulo'] ?? 'Demanda';
            $solicitante = $demanda['solicitante'] ?? '';

            $assunto = 'Demanda Recusada: ' . $titulo;
            $corpo = "Olá {$solicitante},\n\n";
            $corpo .= "Sua demanda \"{$titulo}\" foi analisada e infelizmente foi recusada.\n\n";
            $corpo .= "Motivo: " . ($motivo ?: 'Não informado') . "\n\n";
            $corpo .= "Se discordar da decisão ou tiver novas informações, você pode abrir uma nova solicitação com os ajustes necessários.\n\n";
            $corpo .= "Atenciosamente,\nEquipe Braziliana";

            $this->enviarEmailSimples($email, $assunto, $corpo);
        } catch (\Exception $e) {
            error_log('[DEMANDAS] Erro ao enviar email de recusa: ' . $e->getMessage());
        }
    }

    /**
     * Envia email ao solicitante quando demanda vai para teste (aviso de 24h úteis)
     */
    private function enviarEmailTeste(array $demanda): void {
        try {
            $email = $this->getEmailSolicitante($demanda);
            if (!$email) return;

            $titulo = $demanda['bloco1_titulo'] ?? $demanda['titulo'] ?? 'Demanda';
            $solicitante = $demanda['solicitante'] ?? '';

            $assunto = 'Demanda Pronta para Teste: ' . $titulo;
            $corpo = "Olá {$solicitante},\n\n";
            $corpo .= "Sua demanda \"{$titulo}\" foi concluída pelo TI e está pronta para teste!\n\n";
            $corpo .= "⚠️ IMPORTANTE: Você tem 24 horas úteis (dias úteis, horário comercial) para testar e dar seu parecer.\n\n";
            $corpo .= "Se não testar dentro do prazo, a demanda será automaticamente fechada como concluída e você precisará abrir uma nova solicitação caso encontre problemas.\n\n";
            $corpo .= "Acesse o painel de demandas para testar: https://brazilianashop.com.br/admin/demandas/painel\n\n";
            $corpo .= "Atenciosamente,\nEquipe TI Braziliana";

            $this->enviarEmailSimples($email, $assunto, $corpo);
        } catch (\Exception $e) {
            error_log('[DEMANDAS] Erro ao enviar email de teste: ' . $e->getMessage());
        }
    }

    /**
     * Busca email do solicitante
     */
    private function getEmailSolicitante(array $demanda): ?string {
        // Primeiro tenta campo direto
        $email = trim((string)($demanda['solicitante_email'] ?? ''));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) return $email;

        // Tenta buscar pelo criado_por
        $uid = (int)($demanda['criado_por'] ?? 0);
        if ($uid > 0) {
            try {
                $st = $this->db->prepare("SELECT email FROM usuarios WHERE id = ? LIMIT 1");
                $st->execute([$uid]);
                $e = trim((string)($st->fetchColumn() ?: ''));
                if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) return $e;
            } catch (\Exception $e) {}
        }

        // Tenta buscar pelo nome do solicitante
        $nome = trim((string)($demanda['solicitante'] ?? ''));
        if ($nome !== '') {
            try {
                $st = $this->db->prepare("SELECT email FROM usuarios WHERE nome = ? LIMIT 1");
                $st->execute([$nome]);
                $e = trim((string)($st->fetchColumn() ?: ''));
                if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) return $e;
            } catch (\Exception $e) {}
        }

        return null;
    }

    /**
     * Envia email simples usando o mailer do sistema
     */
    private function enviarEmailSimples(string $to, string $subject, string $body): void {
        try {
            // Tentar usar PHPMailer se disponível
            if (class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
                $cfg = [];
                try {
                    $st = $this->db->prepare("SELECT chave, valor FROM configuracoes_sistema WHERE grupo = 'email'");
                    $st->execute();
                    foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $r) $cfg[$r['chave']] = $r['valor'];
                } catch (\Exception $e) {}

                $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
                $mail->isSMTP();
                $mail->Host = $cfg['host'] ?? '';
                $mail->Port = (int)($cfg['port'] ?? 587);
                $mail->SMTPAuth = true;
                $mail->Username = $cfg['username'] ?? '';
                $mail->Password = $cfg['password'] ?? '';
                $mail->SMTPSecure = $cfg['encryption'] ?? 'tls';
                $mail->setFrom($cfg['from'] ?? $cfg['username'] ?? 'noreply@brazilianashop.com.br', $cfg['from_name'] ?? 'Braziliana Shop');
                $mail->addAddress($to);
                $mail->Subject = $subject;
                $mail->Body = $body;
                $mail->CharSet = 'UTF-8';
                $mail->send();
            } else {
                // Fallback: mail() nativo
                $headers = "From: Braziliana Shop <noreply@brazilianashop.com.br>\r\nContent-Type: text/plain; charset=UTF-8\r\n";
                mail($to, $subject, $body, $headers);
            }
        } catch (\Exception $e) {
            error_log('[DEMANDAS] Falha ao enviar email para ' . $to . ': ' . $e->getMessage());
        }
    }

    // === PRIVATE ===
    private function listar($status = null) {
        $sql = "SELECT * FROM demandas" . ($status ? " WHERE status = ?" : "") . " ORDER BY created_at DESC";
        $st = $this->db->prepare($sql); $st->execute($status ? [$status] : []); return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }
    private function getById($id) { $st = $this->db->prepare("SELECT * FROM demandas WHERE id = ?"); $st->execute([$id]); return $st->fetch(\PDO::FETCH_ASSOC) ?: null; }
    private function getHistorico($id) { $st = $this->db->prepare("SELECT * FROM demanda_historico WHERE demanda_id = ? ORDER BY created_at ASC"); $st->execute([$id]); return $st->fetchAll(\PDO::FETCH_ASSOC) ?: []; }
    private function registrarHistorico($id, $anterior, $novo, $obs = null) { $this->db->prepare("INSERT INTO demanda_historico (demanda_id, status_anterior, status_novo, usuario_id, observacao) VALUES (?,?,?,?,?)")->execute([$id, $anterior, $novo, $_SESSION['usuario_id'] ?? null, $obs]); }
    private function ensureTables() { try { $this->db->query("SELECT 1 FROM demandas LIMIT 1"); } catch (\Exception $e) { $f = __DIR__ . '/../../database/migrations/165_create_demandas_schema.sql'; if (file_exists($f)) { foreach (array_filter(array_map('trim', explode(';', file_get_contents($f)))) as $s) { if ($s && stripos($s,'--')!==0) try { $this->db->exec($s); } catch (\Exception $ex) {} } } } }
}
