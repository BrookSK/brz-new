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

        // Verificar senha do painel
        if (!$this->verificarSenhaPainel()) return;

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

    /**
     * Minhas Solicitações — lista demandas do usuário logado
     */
    public function minhasSolicitacoes(Request $request) {
        $auth = new AuthService(); $auth->requerPerfis(['admin','suporte','vendedor']);
        $uid = $_SESSION['usuario_id'] ?? 0;

        $demandas = [];
        try {
            $st = $this->db->prepare("SELECT * FROM demandas WHERE criado_por = ? ORDER BY created_at DESC");
            $st->execute([$uid]);
            $demandas = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {}

        $statusLabels = ['pendente'=>'Pendente','em_analise'=>'Em Análise','em_execucao'=>'Em Execução','em_teste'=>'Em Teste','recusado'=>'Recusado','concluido'=>'Concluído'];
        $statusCores = ['pendente'=>'secondary','em_analise'=>'primary','em_execucao'=>'warning','em_teste'=>'info','recusado'=>'danger','concluido'=>'success'];

        $title = 'Minhas Solicitações'; $sidebarActive = 'demandas-minhas';
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';
        ob_start();
        echo '<div class="container-fluid py-3">';
        echo '<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">';
        echo '<h1 class="page-title">Minhas Solicitações</h1>';
        echo '<a href="/admin/demandas/nova" class="btn btn-dark btn-sm rounded-pill px-3"><i class="fas fa-plus me-1"></i>Nova Solicitação</a>';
        echo '</div>';

        if (empty($demandas)) {
            echo '<div class="card border-0 shadow-sm"><div class="card-body text-center py-5"><i class="fas fa-inbox fs-1 text-muted d-block mb-3 opacity-50"></i><h5 class="text-muted">Nenhuma solicitação ainda</h5><p class="text-muted small">Clique em "Nova Solicitação" para registrar uma demanda.</p></div></div>';
        } else {
            foreach ($demandas as $d) {
                $st = $statusLabels[$d['status']] ?? $d['status'];
                $cor = $statusCores[$d['status']] ?? 'secondary';
                $data = date('d/m/Y H:i', strtotime($d['created_at']));
                $titulo = htmlspecialchars($d['bloco1_titulo'] ?? $d['titulo'] ?? '');
                echo '<a href="/admin/demandas/minha/' . (int)$d['id'] . '" class="card border-0 shadow-sm mb-3 text-decoration-none" style="transition:transform .2s;" onmouseover="this.style.transform=\'translateY(-2px)\'" onmouseout="this.style.transform=\'\'">';
                echo '<div class="card-body d-flex align-items-center gap-3">';
                echo '<div class="flex-grow-1" style="min-width:0;">';
                echo '<div class="fw-bold text-dark text-truncate">' . $titulo . '</div>';
                echo '<div class="text-muted small">' . $data . '</div>';
                echo '</div>';
                echo '<span class="badge bg-' . $cor . ' flex-shrink-0">' . $st . '</span>';
                echo '</div></a>';
            }
        }
        echo '</div>';
        $content = ob_get_clean();
        include __DIR__ . '/../Views/layouts/admin.php';
    }

    /**
     * Detalhe da minha solicitação (com chat)
     */
    public function minhaDetalhe(Request $request, $id) {
        $auth = new AuthService(); $auth->requerPerfis(['admin','suporte','vendedor']);
        $uid = $_SESSION['usuario_id'] ?? 0;
        $id = (int)$id;

        // Buscar demanda (só se é do usuário logado)
        $demanda = null;
        try {
            $st = $this->db->prepare("SELECT * FROM demandas WHERE id = ? AND criado_por = ?");
            $st->execute([$id, $uid]);
            $demanda = $st->fetch(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {}

        if (!$demanda) {
            $_SESSION['message'] = 'Solicitação não encontrada.';
            $_SESSION['message_type'] = 'danger';
            $this->redirect('/admin/demandas/minhas');
            return;
        }

        $mensagens = $this->getMensagens($id);
        $arquivosBug = $this->getArquivosDemanda($id);
        $historico = $this->getHistorico($id);

        $statusLabels = ['pendente'=>'Pendente','em_analise'=>'Em Análise','em_execucao'=>'Em Execução','em_teste'=>'Em Teste','recusado'=>'Recusado','concluido'=>'Concluído'];
        $statusCores = ['pendente'=>'secondary','em_analise'=>'primary','em_execucao'=>'warning','em_teste'=>'info','recusado'=>'danger','concluido'=>'success'];

        $title = 'Solicitação #' . $id; $sidebarActive = 'demandas-minhas';
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';
        ob_start();
        echo '<div class="container-fluid py-3">';
        echo '<a href="/admin/demandas/minhas" class="btn btn-sm btn-secondary mb-3"><i class="fas fa-arrow-left me-1"></i>Voltar</a>';

        // Header
        echo '<div class="card border-0 shadow-sm mb-4"><div class="card-body">';
        echo '<div class="d-flex justify-content-between align-items-start flex-wrap gap-2">';
        echo '<div><h5 class="fw-bold mb-1">' . htmlspecialchars($demanda['bloco1_titulo']) . '</h5>';
        echo '<div class="text-muted small">Criada em ' . date('d/m/Y H:i', strtotime($demanda['created_at'])) . '</div></div>';
        echo '<span class="badge bg-' . ($statusCores[$demanda['status']] ?? 'secondary') . ' fs-6">' . ($statusLabels[$demanda['status']] ?? $demanda['status']) . '</span>';
        echo '</div>';

        // Motivo recusa
        if ($demanda['status'] === 'recusado' && !empty($demanda['motivo_recusa'])) {
            echo '<div class="alert alert-danger mt-3 mb-0 small"><i class="fas fa-ban me-1"></i><strong>Motivo da recusa:</strong> ' . nl2br(htmlspecialchars($demanda['motivo_recusa'])) . '</div>';
        }

        // Aviso teste
        if ($demanda['status'] === 'em_teste') {
            echo '<div class="alert alert-warning mt-3 mb-0 small"><i class="fas fa-stopwatch me-1"></i><strong>Em teste!</strong> Você tem 24h úteis para testar e dar seu parecer. Caso contrário, será fechada automaticamente.</div>';
        }

        echo '</div></div>';

        // Arquivos
        if (!empty($arquivosBug)) {
            echo '<div class="card border-0 shadow-sm mb-4"><div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold small"><i class="fas fa-paperclip me-1"></i>Arquivos Anexados</h6></div><div class="card-body"><div class="row g-2">';
            foreach ($arquivosBug as $arq) {
                $isImg = str_starts_with($arq['tipo'] ?? '', 'image/');
                echo '<div class="col-md-3 col-6"><div class="border rounded p-2 text-center">';
                if ($isImg) echo '<a href="' . htmlspecialchars($arq['caminho']) . '" target="_blank"><img src="' . htmlspecialchars($arq['caminho']) . '" class="img-fluid rounded mb-1" style="max-height:100px;object-fit:cover;"></a>';
                else echo '<a href="' . htmlspecialchars($arq['caminho']) . '" target="_blank" class="d-block py-2"><i class="fas fa-file fs-3 text-muted"></i></a>';
                echo '<div class="text-truncate small text-muted">' . htmlspecialchars($arq['nome_original']) . '</div>';
                echo '</div></div>';
            }
            echo '</div></div></div>';
        }

        // Histórico resumido
        echo '<div class="card border-0 shadow-sm mb-4"><div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold small"><i class="fas fa-history me-1"></i>Histórico</h6></div><div class="card-body p-0"><ul class="list-group list-group-flush">';
        foreach ($historico as $h) {
            echo '<li class="list-group-item small"><strong>' . date('d/m H:i', strtotime($h['created_at'])) . '</strong> — ' . ucfirst(str_replace('_', ' ', $h['status_novo']));
            if ($h['observacao']) echo '<br><span class="text-muted">' . htmlspecialchars($h['observacao']) . '</span>';
            echo '</li>';
        }
        echo '</ul></div></div>';

        // Chat
        echo '<div class="card border-0 shadow-sm mb-4" id="chat"><div class="card-header bg-white border-0 pt-3 d-flex justify-content-between align-items-center"><h6 class="fw-bold small mb-0"><i class="fas fa-comments me-1"></i>Comunicação com o TI</h6><span class="badge bg-secondary">' . count($mensagens) . '</span></div>';
        echo '<div class="card-body" style="max-height:400px;overflow-y:auto;">';
        if (empty($mensagens)) {
            echo '<div class="text-center text-muted small py-3"><i class="fas fa-inbox d-block mb-1 fs-4 opacity-50"></i>Nenhuma mensagem ainda.</div>';
        } else {
            foreach ($mensagens as $msg) {
                $isMeu = ((int)($msg['usuario_id'] ?? 0) === $uid);
                echo '<div class="mb-3 d-flex ' . ($isMeu ? 'justify-content-end' : 'justify-content-start') . '">';
                echo '<div class="' . ($isMeu ? 'bg-primary bg-opacity-10 border-primary' : 'bg-light') . ' border rounded p-2" style="max-width:80%;">';
                echo '<div class="d-flex justify-content-between align-items-center mb-1"><span class="fw-semibold" style="font-size:11px;">' . htmlspecialchars($msg['usuario_nome']) . '</span><span class="text-muted" style="font-size:10px;">' . date('d/m H:i', strtotime($msg['created_at'])) . '</span></div>';
                if (!empty($msg['mensagem'])) echo '<div class="small">' . nl2br(htmlspecialchars($msg['mensagem'])) . '</div>';
                if (!empty($msg['arquivos'])) {
                    echo '<div class="d-flex flex-wrap gap-2 mt-2">';
                    foreach ($msg['arquivos'] as $arq) {
                        if (str_starts_with($arq['tipo'] ?? '', 'image/')) echo '<a href="' . htmlspecialchars($arq['caminho']) . '" target="_blank"><img src="' . htmlspecialchars($arq['caminho']) . '" class="rounded border" style="max-height:60px;"></a>';
                        else echo '<a href="' . htmlspecialchars($arq['caminho']) . '" target="_blank" class="btn btn-sm btn-outline-secondary py-0 px-2"><i class="fas fa-download me-1"></i>' . htmlspecialchars($arq['nome_original']) . '</a>';
                    }
                    echo '</div>';
                }
                echo '</div></div>';
            }
        }
        echo '</div>';

        // Form enviar mensagem
        echo '<div class="card-footer bg-white border-top"><form method="POST" action="/admin/demandas/minha/' . $id . '/mensagem" enctype="multipart/form-data">';
        echo '<div class="d-flex gap-2"><div class="flex-grow-1"><textarea name="mensagem" class="form-control form-control-sm" rows="2" placeholder="Escreva uma mensagem..."></textarea></div></div>';
        echo '<div class="d-flex justify-content-between align-items-center mt-2"><div><label class="btn btn-sm btn-outline-secondary mb-0" style="cursor:pointer;"><i class="fas fa-paperclip me-1"></i>Anexar<input type="file" name="arquivos[]" multiple class="d-none" accept="image/*,video/*,.pdf,.doc,.docx,.zip"></label></div>';
        echo '<button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-paper-plane me-1"></i>Enviar</button></div>';
        echo '</form></div></div>';

        echo '</div>';
        $content = ob_get_clean();
        include __DIR__ . '/../Views/layouts/admin.php';
    }

    /**
     * Enviar mensagem como solicitante
     */
    public function enviarMensagemSolicitante(Request $request, $id) {
        $auth = new AuthService(); $auth->requerPerfis(['admin','suporte','vendedor']);
        $id = (int)$id;
        $uid = $_SESSION['usuario_id'] ?? 0;

        // Verificar se a demanda é do usuário
        try {
            $st = $this->db->prepare("SELECT id FROM demandas WHERE id = ? AND criado_por = ?");
            $st->execute([$id, $uid]);
            if (!$st->fetchColumn()) { $this->redirect('/admin/demandas/minhas'); return; }
        } catch (\Exception $e) { $this->redirect('/admin/demandas/minhas'); return; }

        $mensagem = trim($_POST['mensagem'] ?? '');
        $nomeUsuario = 'Usuário';
        try { if ($uid) { $st = $this->db->prepare("SELECT nome FROM usuarios WHERE id = ? LIMIT 1"); $st->execute([$uid]); $nomeUsuario = (string)($st->fetchColumn() ?: 'Usuário'); } } catch (\Exception $e) {}

        $this->ensureChatTables();
        $msgId = null;
        if ($mensagem !== '' || !empty($_FILES['arquivos']['name'][0])) {
            $st = $this->db->prepare("INSERT INTO demanda_mensagens (demanda_id, usuario_id, usuario_nome, mensagem) VALUES (?, ?, ?, ?)");
            $st->execute([$id, $uid, $nomeUsuario, $mensagem ?: null]);
            $msgId = (int)$this->db->lastInsertId();
        }

        if (!empty($_FILES['arquivos']['name'][0])) {
            $uploadDir = __DIR__ . '/../../public/uploads/demandas/' . $id . '/';
            if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);
            foreach ($_FILES['arquivos']['name'] as $i => $nome) {
                if ($_FILES['arquivos']['error'][$i] !== UPLOAD_ERR_OK) continue;
                $nomeOriginal = basename($nome);
                $nomeArquivo = time() . '_' . $i . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $nomeOriginal);
                $destino = $uploadDir . $nomeArquivo;
                if (move_uploaded_file($_FILES['arquivos']['tmp_name'][$i], $destino)) {
                    $caminho = '/uploads/demandas/' . $id . '/' . $nomeArquivo;
                    $this->db->prepare("INSERT INTO demanda_arquivos (demanda_id, mensagem_id, usuario_id, nome_original, caminho, tipo, tamanho) VALUES (?,?,?,?,?,?,?)")
                        ->execute([$id, $msgId, $uid, $nomeOriginal, $caminho, $_FILES['arquivos']['type'][$i] ?? '', (int)($_FILES['arquivos']['size'][$i] ?? 0)]);
                }
            }
        }

        $this->redirect('/admin/demandas/minha/' . $id . '#chat');
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

        // Notificar devs (email + webhook + push)
        $this->notificarNovaDemanda($id, ($tipo === 'bug' ? '[BUG] ' : '') . ($body['bloco1_titulo'] ?? ''), $body['bloco1_solicitante'] ?? '', $tipo);

        $_SESSION['message'] = $tipo === 'bug'
            ? 'Bug reportado com prioridade ' . strtoupper($body['bug_prioridade'] ?? 'media') . '! Já aparece no Painel.'
            : 'Demanda registrada com sucesso! Ela já aparece no Painel de Demandas.';
        $_SESSION['message_type'] = 'success';
        $this->redirect('/admin/demandas/minhas');
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
            $uploadDir = __DIR__ . '/../../public/uploads/demandas/' . $id . '/';
            if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);

            foreach ($_FILES['arquivos']['name'] as $i => $nome) {
                if ($_FILES['arquivos']['error'][$i] !== UPLOAD_ERR_OK) continue;
                $nomeOriginal = basename($nome);
                $ext = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));
                $nomeArquivo = time() . '_' . $i . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $nomeOriginal);
                $destino = $uploadDir . $nomeArquivo;

                if (move_uploaded_file($_FILES['arquivos']['tmp_name'][$i], $destino)) {
                    $caminho = '/uploads/demandas/' . $id . '/' . $nomeArquivo;
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
        $uploadDir = __DIR__ . '/../../public/uploads/demandas/' . $demandaId . '/';
        if (!is_dir($uploadDir)) @mkdir($uploadDir, 0755, true);

        foreach ($_FILES['arquivos_bug']['name'] as $i => $nome) {
            if ($_FILES['arquivos_bug']['error'][$i] !== UPLOAD_ERR_OK) continue;
            $nomeOriginal = basename($nome);
            $nomeArquivo = time() . '_' . $i . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $nomeOriginal);
            $destino = $uploadDir . $nomeArquivo;

            if (move_uploaded_file($_FILES['arquivos_bug']['tmp_name'][$i], $destino)) {
                $caminho = '/uploads/demandas/' . $demandaId . '/' . $nomeArquivo;
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
        error_log('[DEMANDAS] getEmailSolicitante: solicitante_email=' . ($demanda['solicitante_email'] ?? 'NULL') . ' criado_por=' . ($demanda['criado_por'] ?? 'NULL') . ' solicitante=' . ($demanda['solicitante'] ?? 'NULL'));

        // Primeiro tenta campo direto
        $email = trim((string)($demanda['solicitante_email'] ?? ''));
        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) { error_log('[DEMANDAS] Email encontrado via campo direto: ' . $email); return $email; }

        // Tenta buscar pelo criado_por
        $uid = (int)($demanda['criado_por'] ?? 0);
        if ($uid > 0) {
            try {
                $st = $this->db->prepare("SELECT email FROM usuarios WHERE id = ? LIMIT 1");
                $st->execute([$uid]);
                $e = trim((string)($st->fetchColumn() ?: ''));
                if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) { error_log('[DEMANDAS] Email encontrado via criado_por: ' . $e); return $e; }
            } catch (\Exception $e) { error_log('[DEMANDAS] Erro busca por criado_por: ' . $e->getMessage()); }
        }

        // Tenta buscar pelo nome do solicitante (exato ou LIKE)
        $nome = trim((string)($demanda['solicitante'] ?? ''));
        if ($nome !== '') {
            try {
                $st = $this->db->prepare("SELECT email FROM usuarios WHERE nome = ? OR nome LIKE ? LIMIT 1");
                $st->execute([$nome, '%' . $nome . '%']);
                $e = trim((string)($st->fetchColumn() ?: ''));
                if ($e !== '' && filter_var($e, FILTER_VALIDATE_EMAIL)) { error_log('[DEMANDAS] Email encontrado via nome: ' . $e); return $e; }
            } catch (\Exception $e) { error_log('[DEMANDAS] Erro busca por nome: ' . $e->getMessage()); }
        }

        error_log('[DEMANDAS] Email do solicitante NÃO encontrado para demanda #' . ($demanda['id'] ?? '?'));
        return null;
    }

    /**
     * Envia email simples usando o mailer do sistema
     */
    private function enviarEmailSimples(string $to, string $subject, string $body): void {
        try {
            $cfg = [];
            try {
                $st = $this->db->prepare("SELECT chave, valor FROM configuracoes_sistema WHERE chave LIKE 'email_%'");
                $st->execute();
                foreach ($st->fetchAll(\PDO::FETCH_ASSOC) as $r) $cfg[$r['chave']] = $r['valor'];
            } catch (\Exception $e) {}

            $host = $cfg['email_host'] ?? '';
            $port = (int)($cfg['email_port'] ?? 465);
            $username = $cfg['email_username'] ?? '';
            $password = $cfg['email_password'] ?? '';
            $encryption = strtolower($cfg['email_encryption'] ?? 'ssl');
            $from = $cfg['email_from'] ?? $username;
            $fromName = $cfg['email_from_name'] ?? 'Braziliana Shop';

            if ($host === '' || $username === '') {
                error_log('[DEMANDAS] Email não configurado (host/username vazio)');
                return;
            }

            // Enviar via SMTP direto (sem PHPMailer)
            $prefix = ($encryption === 'ssl') ? 'ssl://' : '';
            $smtp = @fsockopen($prefix . $host, $port, $errno, $errstr, 10);
            if (!$smtp) {
                error_log("[DEMANDAS] SMTP conexão falhou: {$errstr} ({$errno})");
                return;
            }

            $this->smtpRead($smtp);
            $this->smtpCmd($smtp, "EHLO brazilianashop.com.br");
            if ($encryption === 'tls') {
                $this->smtpCmd($smtp, "STARTTLS");
                stream_socket_enable_crypto($smtp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                $this->smtpCmd($smtp, "EHLO brazilianashop.com.br");
            }
            $this->smtpCmd($smtp, "AUTH LOGIN");
            $this->smtpCmd($smtp, base64_encode($username));
            $this->smtpCmd($smtp, base64_encode($password));
            $this->smtpCmd($smtp, "MAIL FROM:<{$from}>");
            $this->smtpCmd($smtp, "RCPT TO:<{$to}>");
            $this->smtpCmd($smtp, "DATA");

            $headers = "From: {$fromName} <{$from}>\r\n";
            $headers .= "To: {$to}\r\n";
            $headers .= "Subject: {$subject}\r\n";
            $headers .= "MIME-Version: 1.0\r\n";
            $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
            $headers .= "Date: " . date('r') . "\r\n";
            $headers .= "\r\n";

            fwrite($smtp, $headers . $body . "\r\n.\r\n");
            $this->smtpRead($smtp);
            $this->smtpCmd($smtp, "QUIT");
            fclose($smtp);

            error_log('[DEMANDAS] Email SMTP enviado para ' . $to . ': ' . $subject);
        } catch (\Exception $e) {
            error_log('[DEMANDAS] Falha ao enviar email para ' . $to . ': ' . $e->getMessage());
        }
    }

    private function smtpCmd($smtp, string $cmd): string {
        fwrite($smtp, $cmd . "\r\n");
        return $this->smtpRead($smtp);
    }

    private function smtpRead($smtp): string {
        $response = '';
        while ($line = fgets($smtp, 515)) {
            $response .= $line;
            if (substr($line, 3, 1) === ' ') break;
        }
        return $response;
    }

    /**
     * API: Retorna notificações não lidas para o usuário logado (chamado via AJAX)
     */
    public function notificacoes(Request $request) {
        header('Content-Type: application/json; charset=UTF-8');
        $uid = $_SESSION['usuario_id'] ?? 0;
        if (!$uid) { echo json_encode(['notificacoes' => []]); exit; }

        try {
            $this->ensureNotificacoesTable();
            $st = $this->db->prepare("SELECT * FROM admin_notificacoes WHERE usuario_id = ? AND lida = 0 ORDER BY created_at DESC LIMIT 20");
            $st->execute([$uid]);
            $notifs = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            echo json_encode(['notificacoes' => $notifs]);
        } catch (\Exception $e) {
            echo json_encode(['notificacoes' => []]);
        }
        exit;
    }

    /**
     * API: Marcar notificação como lida
     */
    public function marcarLida(Request $request, $id) {
        header('Content-Type: application/json; charset=UTF-8');
        $uid = $_SESSION['usuario_id'] ?? 0;
        try {
            $this->db->prepare("UPDATE admin_notificacoes SET lida = 1 WHERE id = ? AND usuario_id = ?")->execute([(int)$id, $uid]);
        } catch (\Exception $e) {}
        echo json_encode(['success' => true]);
        exit;
    }

    /**
     * Tela de configurações de demandas (só admin)
     */
    public function configuracoes(Request $request) {
        $auth = new AuthService(); $auth->requerPerfis(['admin']);
        $config = [
            'demandas_senha_painel' => $this->getConfig('demandas_senha_painel'),
            'demandas_emails_notificacao' => $this->getConfig('demandas_emails_notificacao'),
            'demandas_webhook_url' => $this->getConfig('demandas_webhook_url'),
            'demandas_usuarios_notificacao' => $this->getConfig('demandas_usuarios_notificacao'),
        ];

        // Buscar lista de usuários admin/suporte para o select
        $usuarios = [];
        try {
            $st = $this->db->query("SELECT id, nome, email FROM usuarios WHERE perfil IN ('admin','suporte') ORDER BY nome");
            $usuarios = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {}

        $title = 'Configurações de Demandas'; $sidebarActive = 'configuracoes';
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';
        ob_start();
        echo '<div class="container-fluid py-3"><div class="row justify-content-center"><div class="col-lg-8">';
        echo '<div class="d-flex justify-content-between align-items-center mb-4"><h1 class="page-title">Configurações de Demandas</h1><a href="/admin/configuracoes" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left me-1"></i>Voltar</a></div>';

        if (!empty($_SESSION['message'])) {
            echo '<div class="alert alert-' . ($_SESSION['message_type'] ?? 'info') . ' alert-dismissible fade show">' . htmlspecialchars($_SESSION['message']) . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
            unset($_SESSION['message'], $_SESSION['message_type']);
        }

        echo '<form method="POST" action="/admin/demandas/configuracoes">';
        echo '<div class="card border-0 shadow-sm mb-4"><div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold small"><i class="fas fa-lock me-2"></i>Acesso ao Painel</h6></div><div class="card-body">';
        echo '<div class="mb-3"><label class="form-label fw-semibold small">Senha do Painel de Demandas</label><input type="text" name="demandas_senha_painel" class="form-control" value="' . htmlspecialchars($config['demandas_senha_painel']) . '" placeholder="Deixe vazio para desativar"><small class="text-muted">Se preenchida, será exigida ao acessar o painel de demandas.</small></div>';
        echo '</div></div>';

        echo '<div class="card border-0 shadow-sm mb-4"><div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold small"><i class="fas fa-envelope me-2"></i>Notificações por Email</h6></div><div class="card-body">';
        echo '<div class="mb-3"><label class="form-label fw-semibold small">Emails que recebem novas solicitações</label><textarea name="demandas_emails_notificacao" class="form-control" rows="3" placeholder="email1@exemplo.com, email2@exemplo.com">' . htmlspecialchars($config['demandas_emails_notificacao']) . '</textarea><small class="text-muted">Separados por vírgula. Toda nova demanda (bug ou função) será enviada para esses emails.</small></div>';
        echo '</div></div>';

        echo '<div class="card border-0 shadow-sm mb-4"><div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold small"><i class="fas fa-plug me-2"></i>Webhook</h6></div><div class="card-body">';
        echo '<div class="mb-3"><label class="form-label fw-semibold small">URL do Webhook</label><input type="url" name="demandas_webhook_url" class="form-control" value="' . htmlspecialchars($config['demandas_webhook_url']) . '" placeholder="https://hooks.slack.com/..."><small class="text-muted">Recebe POST JSON com dados da nova solicitação. Compatível com Slack, Discord, etc.</small></div>';
        echo '</div></div>';

        echo '<div class="card border-0 shadow-sm mb-4"><div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold small"><i class="fas fa-bell me-2"></i>Notificações Push (no Admin)</h6></div><div class="card-body">';
        echo '<div class="mb-3"><label class="form-label fw-semibold small">Usuários que recebem notificações</label><select name="demandas_usuarios_notificacao[]" class="form-select" multiple size="6">';
        $idsNotif = array_filter(array_map('intval', explode(',', $config['demandas_usuarios_notificacao'])));
        foreach ($usuarios as $u) {
            $sel = in_array((int)$u['id'], $idsNotif) ? ' selected' : '';
            echo '<option value="' . (int)$u['id'] . '"' . $sel . '>' . htmlspecialchars($u['nome']) . ' (' . htmlspecialchars($u['email']) . ')</option>';
        }
        echo '</select><small class="text-muted">Segure Ctrl/Cmd para selecionar múltiplos. Esses usuários verão notificações em tempo real no painel admin.</small></div>';
        echo '</div></div>';

        echo '<button type="submit" class="btn btn-dark w-100 mb-4"><i class="fas fa-save me-1"></i>Salvar Configurações</button>';
        echo '</form></div></div></div>';
        $content = ob_get_clean();
        include __DIR__ . '/../Views/layouts/admin.php';
    }

    /**
     * Salvar configurações de demandas
     */
    public function salvarConfiguracoes(Request $request) {
        $auth = new AuthService(); $auth->requerPerfis(['admin']);

        $campos = ['demandas_senha_painel', 'demandas_emails_notificacao', 'demandas_webhook_url'];
        foreach ($campos as $campo) {
            $valor = trim($_POST[$campo] ?? '');
            $this->setConfig($campo, $valor);
        }

        // Usuários notificação (vem como array)
        $usuarios = $_POST['demandas_usuarios_notificacao'] ?? [];
        if (is_array($usuarios)) {
            $this->setConfig('demandas_usuarios_notificacao', implode(',', array_filter(array_map('intval', $usuarios))));
        }

        $_SESSION['message'] = 'Configurações salvas com sucesso!';
        $_SESSION['message_type'] = 'success';
        $this->redirect('/admin/demandas/configuracoes');
    }

    private function setConfig(string $chave, string $valor): void {
        try {
            $st = $this->db->prepare("SELECT COUNT(*) FROM configuracoes_sistema WHERE chave = ?");
            $st->execute([$chave]);
            if ((int)$st->fetchColumn() > 0) {
                $this->db->prepare("UPDATE configuracoes_sistema SET valor = ? WHERE chave = ?")->execute([$valor, $chave]);
            } else {
                $this->db->prepare("INSERT INTO configuracoes_sistema (chave, valor) VALUES (?, ?)")->execute([$chave, $valor]);
            }
        } catch (\Exception $e) {
            error_log('[DEMANDAS] Erro ao salvar config ' . $chave . ': ' . $e->getMessage());
        }
    }

    /**
     * Verificar senha do painel de demandas
     */
    private function verificarSenhaPainel(): bool {
        $senhaConfig = $this->getConfig('demandas_senha_painel');
        if ($senhaConfig === '' || $senhaConfig === null) return true; // Sem senha configurada

        // Verificar se já autenticou nesta sessão
        if (!empty($_SESSION['demandas_painel_auth']) && $_SESSION['demandas_painel_auth'] === true) return true;

        // Se veio POST com senha, validar
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['senha_painel'])) {
            if ($_POST['senha_painel'] === $senhaConfig) {
                $_SESSION['demandas_painel_auth'] = true;
                return true;
            } else {
                $_SESSION['message'] = 'Senha incorreta.';
                $_SESSION['message_type'] = 'danger';
            }
        }

        // Mostrar tela de senha
        $title = 'Painel de Demandas - Acesso Restrito'; $sidebarActive = 'demandas-painel';
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';
        ob_start();
        echo '<div class="container-fluid py-5"><div class="row justify-content-center"><div class="col-md-4">';
        echo '<div class="card border-0 shadow-sm"><div class="card-body text-center py-5">';
        echo '<i class="fas fa-lock fs-1 text-muted mb-3 d-block"></i>';
        echo '<h5 class="fw-bold mb-3">Acesso Restrito</h5>';
        echo '<p class="text-muted small mb-4">O painel de demandas requer autenticação adicional.</p>';
        if (!empty($_SESSION['message'])) {
            echo '<div class="alert alert-' . ($_SESSION['message_type'] ?? 'info') . ' small">' . htmlspecialchars($_SESSION['message']) . '</div>';
            unset($_SESSION['message'], $_SESSION['message_type']);
        }
        echo '<form method="POST" action="/admin/demandas/painel">';
        echo '<div class="mb-3"><input type="password" name="senha_painel" class="form-control text-center" placeholder="Digite a senha" autofocus required></div>';
        echo '<button type="submit" class="btn btn-dark w-100"><i class="fas fa-unlock me-1"></i>Acessar</button>';
        echo '</form>';
        echo '</div></div></div></div></div>';
        $content = ob_get_clean();
        include __DIR__ . '/../Views/layouts/admin.php';
        return false;
    }

    /**
     * Notificar devs sobre nova demanda (email + webhook + push)
     */
    private function notificarNovaDemanda(int $demandaId, string $titulo, string $solicitante, string $tipo): void {
        // 1. Email para devs configurados
        $emails = $this->getConfig('demandas_emails_notificacao');
        if ($emails !== '') {
            $listaEmails = array_filter(array_map('trim', explode(',', $emails)));
            $assunto = ($tipo === 'bug' ? '🐛 ' : '🚀 ') . 'Nova Solicitação: ' . $titulo;
            $corpo = "Nova solicitação de demanda registrada:\n\n";
            $corpo .= "Título: {$titulo}\n";
            $corpo .= "Solicitante: {$solicitante}\n";
            $corpo .= "Tipo: " . ($tipo === 'bug' ? 'Bug/Erro' : 'Nova Função') . "\n\n";
            $corpo .= "Acesse o painel: https://brazilianashop.com.br/admin/demandas/painel\n";
            $corpo .= "Detalhe: https://brazilianashop.com.br/admin/demandas/detalhe/{$demandaId}\n";

            foreach ($listaEmails as $email) {
                if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    try { $this->enviarEmailSimples($email, $assunto, $corpo); } catch (\Exception $e) {}
                }
            }
        }

        // 2. Webhook
        $webhookUrl = $this->getConfig('demandas_webhook_url');
        if ($webhookUrl !== '') {
            try {
                $payload = json_encode([
                    'event' => 'nova_demanda',
                    'id' => $demandaId,
                    'titulo' => $titulo,
                    'solicitante' => $solicitante,
                    'tipo' => $tipo,
                    'url' => 'https://brazilianashop.com.br/admin/demandas/detalhe/' . $demandaId,
                    'created_at' => date('Y-m-d H:i:s'),
                ], JSON_UNESCAPED_UNICODE);

                $ch = curl_init($webhookUrl);
                curl_setopt_array($ch, [
                    CURLOPT_POST => true,
                    CURLOPT_POSTFIELDS => $payload,
                    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => 5,
                ]);
                curl_exec($ch);
                curl_close($ch);
            } catch (\Exception $e) {
                error_log('[DEMANDAS] Webhook erro: ' . $e->getMessage());
            }
        }

        // 3. Notificação push (salvar no banco para usuários configurados)
        $usuariosNotif = $this->getConfig('demandas_usuarios_notificacao');
        if ($usuariosNotif !== '') {
            $this->ensureNotificacoesTable();
            $ids = array_filter(array_map('intval', explode(',', $usuariosNotif)));
            $tituloNotif = ($tipo === 'bug' ? '🐛 Bug: ' : '🚀 Nova: ') . $titulo;
            $msgNotif = 'Solicitante: ' . $solicitante;
            $link = '/admin/demandas/detalhe/' . $demandaId;

            foreach ($ids as $uid) {
                if ($uid > 0) {
                    try {
                        $this->db->prepare("INSERT INTO admin_notificacoes (usuario_id, tipo, titulo, mensagem, link) VALUES (?,?,?,?,?)")
                            ->execute([$uid, 'demanda', $tituloNotif, $msgNotif, $link]);
                    } catch (\Exception $e) {}
                }
            }
        }
    }

    private function getConfig(string $chave): string {
        try {
            $st = $this->db->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1");
            $st->execute([$chave]);
            return trim((string)($st->fetchColumn() ?: ''));
        } catch (\Exception $e) { return ''; }
    }

    private function ensureNotificacoesTable(): void {
        try { $this->db->query("SELECT 1 FROM admin_notificacoes LIMIT 1"); } catch (\Exception $e) {
            try { $this->db->exec("CREATE TABLE IF NOT EXISTS admin_notificacoes (id INT AUTO_INCREMENT PRIMARY KEY, usuario_id INT NOT NULL, tipo VARCHAR(50) NOT NULL DEFAULT 'demanda', titulo VARCHAR(500) NOT NULL, mensagem TEXT NULL, link VARCHAR(1000) NULL, lida TINYINT(1) NOT NULL DEFAULT 0, created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP, INDEX idx_usuario_lida (usuario_id, lida))"); } catch (\Exception $ex) {}
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
