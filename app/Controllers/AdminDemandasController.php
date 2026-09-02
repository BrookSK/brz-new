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
        $title = __('admin.demands.panel_title', 'Painel de Demandas'); $sidebarActive = 'demandas-painel';
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
        $title = __('admin.demands.new_request', 'Nova Solicitação'); $sidebarActive = 'demandas-nova';
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';
        ob_start(); require __DIR__ . '/../Views/admin/demandas/nova.php'; $content = ob_get_clean();
        include __DIR__ . '/../Views/layouts/admin.php';
    }

    public function concluidos(Request $request) {
        $auth = new AuthService(); $auth->requerPerfis(['admin','suporte']);
        $demandas = $this->listar('concluido');
        $title = __('admin.demands.completed_title', 'Demandas Concluídas'); $sidebarActive = 'demandas-concluidos';
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

        $statusLabels = ['pendente'=>__('admin.demands.status_pending','Pendente'),'em_analise'=>__('admin.demands.status_analyzing','Em Análise'),'em_execucao'=>__('admin.demands.status_in_progress','Em Execução'),'em_teste'=>__('admin.demands.status_testing','Em Teste'),'recusado'=>__('admin.demands.status_rejected','Recusado'),'concluido'=>__('admin.demands.status_completed','Concluído')];
        $statusCores = ['pendente'=>'secondary','em_analise'=>'primary','em_execucao'=>'warning','em_teste'=>'info','recusado'=>'danger','concluido'=>'success'];

        $title = __('admin.demands.my_requests', 'Minhas Solicitações'); $sidebarActive = 'demandas-minhas';
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';
        ob_start();
        echo '<div class="container-fluid py-3">';
        echo '<div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-4">';
        echo '<h1 class="page-title">' . __('admin.demands.my_requests', 'Minhas Solicitações') . '</h1>';
        echo '<a href="/admin/demandas/nova" class="btn btn-dark btn-sm rounded-pill px-3"><i class="fas fa-plus me-1"></i>' . __('admin.demands.new_request', 'Nova Solicitação') . '</a>';
        echo '</div>';

        if (empty($demandas)) {
            echo '<div class="card border-0 shadow-sm"><div class="card-body text-center py-5"><i class="fas fa-inbox fs-1 text-muted d-block mb-3 opacity-50"></i><h5 class="text-muted">' . __('admin.demands.no_requests_yet', 'Nenhuma solicitação ainda') . '</h5><p class="text-muted small">' . __('admin.demands.no_requests_hint', 'Clique em "Nova Solicitação" para registrar uma demanda.') . '</p></div></div>';
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
            $_SESSION['message'] = __('admin.demands.request_not_found', 'Solicitação não encontrada.');
            $_SESSION['message_type'] = 'danger';
            $this->redirect('/admin/demandas/minhas');
            return;
        }

        $mensagens = $this->getMensagens($id);
        $arquivosBug = $this->getArquivosDemanda($id);
        $historico = $this->getHistorico($id);

        $statusLabels = ['pendente'=>__('admin.demands.status_pending','Pendente'),'em_analise'=>__('admin.demands.status_analyzing','Em Análise'),'em_execucao'=>__('admin.demands.status_in_progress','Em Execução'),'em_teste'=>__('admin.demands.status_testing','Em Teste'),'recusado'=>__('admin.demands.status_rejected','Recusado'),'concluido'=>__('admin.demands.status_completed','Concluído')];
        $statusCores = ['pendente'=>'secondary','em_analise'=>'primary','em_execucao'=>'warning','em_teste'=>'info','recusado'=>'danger','concluido'=>'success'];

        $title = __('admin.demands.request_number', 'Solicitação #{n}', ['n'=>$id]); $sidebarActive = 'demandas-minhas';
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';
        ob_start();
        echo '<div class="container-fluid py-3">';
        echo '<a href="/admin/demandas/minhas" class="btn btn-sm btn-secondary mb-3"><i class="fas fa-arrow-left me-1"></i>' . __('admin.demands.back', 'Voltar') . '</a>';

        // Header
        echo '<div class="card border-0 shadow-sm mb-4"><div class="card-body">';
        echo '<div class="d-flex justify-content-between align-items-start flex-wrap gap-2">';
        echo '<div><h5 class="fw-bold mb-1">' . htmlspecialchars($demanda['bloco1_titulo']) . '</h5>';
        echo '<div class="text-muted small">' . __('admin.demands.created_on', 'Criada em') . ' ' . date('d/m/Y H:i', strtotime($demanda['created_at'])) . '</div></div>';
        echo '<span class="badge bg-' . ($statusCores[$demanda['status']] ?? 'secondary') . ' fs-6">' . ($statusLabels[$demanda['status']] ?? $demanda['status']) . '</span>';
        echo '</div>';

        // Motivo recusa
        if ($demanda['status'] === 'recusado' && !empty($demanda['motivo_recusa'])) {
            echo '<div class="alert alert-danger mt-3 mb-0 small"><i class="fas fa-ban me-1"></i><strong>' . __('admin.demands.rejection_reason', 'Motivo da recusa:') . '</strong> ' . nl2br(htmlspecialchars($demanda['motivo_recusa'])) . '</div>';
        }

        // Aviso teste
        if ($demanda['status'] === 'em_teste') {
            echo '<div class="alert alert-warning mt-3 mb-0 small"><i class="fas fa-stopwatch me-1"></i><strong>' . __('admin.demands.in_testing_label', 'Em teste!') . '</strong> ' . __('admin.demands.in_testing_hint', 'Você tem 24h úteis para testar e dar seu parecer. Caso contrário, será fechada automaticamente.') . '</div>';
        }

        echo '</div></div>';

        // Arquivos
        if (!empty($arquivosBug)) {
            echo '<div class="card border-0 shadow-sm mb-4"><div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold small"><i class="fas fa-paperclip me-1"></i>' . __('admin.demands.attached_files', 'Arquivos Anexados') . '</h6></div><div class="card-body"><div class="row g-2">';
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
        echo '<div class="card border-0 shadow-sm mb-4"><div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold small"><i class="fas fa-history me-1"></i>' . __('admin.demands.history', 'Histórico') . '</h6></div><div class="card-body p-0"><ul class="list-group list-group-flush">';
        foreach ($historico as $h) {
            echo '<li class="list-group-item small"><strong>' . date('d/m H:i', strtotime($h['created_at'])) . '</strong> — ' . ucfirst(str_replace('_', ' ', $h['status_novo']));
            if ($h['observacao']) echo '<br><span class="text-muted">' . htmlspecialchars($h['observacao']) . '</span>';
            echo '</li>';
        }
        echo '</ul></div></div>';

        // Chat
        echo '<div class="card border-0 shadow-sm mb-4" id="chat"><div class="card-header bg-white border-0 pt-3 d-flex justify-content-between align-items-center"><h6 class="fw-bold small mb-0"><i class="fas fa-comments me-1"></i>' . __('admin.demands.communication_with_it', 'Comunicação com o TI') . '</h6><span class="badge bg-secondary">' . count($mensagens) . '</span></div>';
        echo '<div class="card-body" style="max-height:400px;overflow-y:auto;">';
        if (empty($mensagens)) {
            echo '<div class="text-center text-muted small py-3"><i class="fas fa-inbox d-block mb-1 fs-4 opacity-50"></i>' . __('admin.demands.no_messages_yet', 'Nenhuma mensagem ainda.') . '</div>';
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
        echo '<div class="d-flex gap-2"><div class="flex-grow-1"><textarea name="mensagem" class="form-control form-control-sm" rows="2" placeholder="' . htmlspecialchars(__('admin.demands.write_message_placeholder', 'Escreva uma mensagem...'), ENT_QUOTES, 'UTF-8') . '"></textarea></div></div>';
        echo '<div class="d-flex justify-content-between align-items-center mt-2"><div><label class="btn btn-sm btn-outline-secondary mb-0" style="cursor:pointer;"><i class="fas fa-paperclip me-1"></i>' . __('admin.demands.attach', 'Anexar') . '<input type="file" name="arquivos[]" multiple class="d-none" accept="image/*,video/*,.pdf,.doc,.docx,.zip"></label></div>';
        echo '<button type="submit" class="btn btn-sm btn-primary"><i class="fas fa-paper-plane me-1"></i>' . __('admin.demands.send', 'Enviar') . '</button></div>';
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
        $nomeUsuario = __('admin.demands.default_user', 'Usuário');
        try { if ($uid) { $st = $this->db->prepare("SELECT nome FROM usuarios WHERE id = ? LIMIT 1"); $st->execute([$uid]); $nomeUsuario = (string)($st->fetchColumn() ?: __('admin.demands.default_user', 'Usuário')); } } catch (\Exception $e) {}

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

        // Notificar devs/admins por email sobre nova mensagem do solicitante
        if ($msgId) {
            $this->notificarMensagemChat($id, $nomeUsuario, $mensagem, 'solicitante_para_admin');
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
            $bloco2_problema = __('admin.demands.bug_error_label', 'ERRO:') . " " . $bugData['erro'] . "\n\n" . __('admin.demands.bug_action_label', 'O QUE FAZIA:') . " " . $bugData['acao'];
            $bloco2_melhoria = __('admin.demands.bug_fix_goal', 'Corrigir o bug para que funcione corretamente.');
            $bloco2_consequencia = __('admin.demands.bug_when_label', 'QUANDO:') . " " . $bugData['quando'] . "\n" . __('admin.demands.bug_where_label', 'ONDE:') . " " . $bugData['onde'];
            $bloco3_financeiro = __('admin.demands.bug_priority_label', 'Bug - Prioridade:') . " " . strtoupper($bugData['prioridade']);
            $bloco3_jornada = __('admin.demands.bug_evidence_label', 'PRINTS/EVIDÊNCIAS:') . " " . $bugData['prints'];
            $bloco3_detalhes = __('admin.demands.bug_details_label', 'DETALHES:') . " " . $bugData['detalhes'];
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
            $tipo === 'bug' ? __('admin.demands.bug_fix_type', 'Bug/Correção') : ($body['bloco5_novo_ou_existente'] ?? ''),
            $tipo === 'bug' ? ($body['bug_onde'] ?? '') : ($body['bloco5_ferramentas'] ?? ''),
            $tipo === 'bug' ? '' : ($body['bloco5_regras'] ?? ''),
            $tipo === 'bug' ? '' : ($body['bloco5_usuarios'] ?? ''),
            $_SESSION['usuario_id'] ?? null,
        ]);

        $id = (int)$this->db->lastInsertId();
        $obs = $tipo === 'bug' ? __('admin.demands.bug_reported_priority', 'Bug reportado - Prioridade:') . ' ' . strtoupper($body['bug_prioridade'] ?? 'media') : __('admin.demands.demand_created', 'Demanda criada');
        $this->registrarHistorico($id, null, 'pendente', $obs);

        // Processar arquivos anexados (prints de bug, etc)
        $this->processarArquivosDemanda($id);

        // Notificar devs (email + webhook + push)
        $this->notificarNovaDemanda($id, ($tipo === 'bug' ? '[BUG] ' : '') . ($body['bloco1_titulo'] ?? ''), $body['bloco1_solicitante'] ?? '', $tipo);

        $_SESSION['message'] = $tipo === 'bug'
            ? __('admin.demands.bug_reported_success', 'Bug reportado com prioridade {n}! Já aparece no Painel.', ['n'=>strtoupper($body['bug_prioridade'] ?? 'media')])
            : __('admin.demands.demand_registered_success', 'Demanda registrada com sucesso! Ela já aparece no Painel de Demandas.');
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
                $_SESSION['message'] = __('admin.demands.already_in_progress', 'Já existe uma demanda em execução. Conclua ou mova a demanda atual antes de iniciar uma nova.');
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

        $_SESSION['message'] = __('admin.demands.status_updated_to', 'Status atualizado para: {n}', ['n'=>ucfirst(str_replace('_', ' ', $novoStatus))]);
        $_SESSION['message_type'] = 'success';
        $this->redirect('/admin/demandas/painel');
    }

    public function detalhe(Request $request, $id) {
        $auth = new AuthService(); $auth->requerPerfis(['admin','suporte']);
        $demanda = $this->getById((int)$id);
        if (!$demanda) { $_SESSION['message'] = __('admin.demands.demand_not_found', 'Demanda não encontrada.'); $_SESSION['message_type'] = 'danger'; $this->redirect('/admin/demandas/painel'); return; }
        $historico = $this->getHistorico((int)$id);
        $mensagens = $this->getMensagens((int)$id);
        $arquivosBug = $this->getArquivosDemanda((int)$id);
        $title = __('admin.demands.demand_number', 'Demanda #{n}', ['n'=>$id]); $sidebarActive = 'demandas-painel';
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
        echo '<!DOCTYPE html><html lang="' . \App\Core\I18n::getLocaleHtml() . '"><head><meta charset="utf-8"><title>' . htmlspecialchars(__('admin.demands.demand_number', 'Demanda #{n}', ['n'=>$d['id']]), ENT_QUOTES, 'UTF-8') . '</title><style>body{font-family:Arial,sans-serif;font-size:12px;margin:40px;color:#1e293b;}h1{font-size:18px;border-bottom:2px solid #1e293b;padding-bottom:8px;}h2{font-size:14px;margin-top:24px;color:#334155;border-bottom:1px solid #e2e8f0;padding-bottom:4px;}p{margin:4px 0;line-height:1.5;}table{width:100%;border-collapse:collapse;margin:8px 0;}th,td{border:1px solid #e2e8f0;padding:6px 8px;text-align:left;font-size:11px;}th{background:#f8fafc;}.footer{margin-top:40px;border-top:1px solid #e2e8f0;padding-top:10px;font-size:10px;color:#94a3b8;text-align:center;}@media print{body{margin:20px;}}</style></head><body>';
        echo '<h1>BRAZILIANA SHOP — ' . __('admin.demands.pdf_process_title', 'Processo de Demanda') . '</h1>';
        echo '<table style="border:none;"><tr style="border:none;"><td style="border:none;"><strong>' . __('admin.demands.pdf_title_label', 'Título:') . '</strong> ' . htmlspecialchars($d['bloco1_titulo']) . '</td><td style="border:none;"><strong>' . __('admin.demands.pdf_requester_label', 'Solicitante:') . '</strong> ' . htmlspecialchars($d['bloco1_solicitante']) . '</td></tr><tr style="border:none;"><td style="border:none;"><strong>' . __('admin.demands.pdf_sent_label', 'Envio:') . '</strong> ' . date('d/m/Y H:i', strtotime($d['created_at'])) . '</td><td style="border:none;"><strong>' . __('admin.demands.pdf_completion_label', 'Conclusão:') . '</strong> ' . ($d['concluido_em'] ? date('d/m/Y H:i', strtotime($d['concluido_em'])) : 'N/A') . '</td></tr></table>';
        echo '<h2>' . __('admin.demands.pdf_section_identification', '1. Identificação') . '</h2><p><strong>' . __('admin.demands.pdf_requester_label', 'Solicitante:') . '</strong> ' . htmlspecialchars($d['bloco1_solicitante']) . '</p><p><strong>' . __('admin.demands.pdf_title_label', 'Título:') . '</strong> ' . htmlspecialchars($d['bloco1_titulo']) . '</p>';
        echo '<h2>' . __('admin.demands.pdf_section_justification', '2. Justificativa') . '</h2><p><strong>' . __('admin.demands.pdf_problem_label', 'Problema:') . '</strong><br>' . nl2br(htmlspecialchars($d['bloco2_problema'])) . '</p><p><strong>' . __('admin.demands.pdf_improvement_label', 'Melhoria:') . '</strong><br>' . nl2br(htmlspecialchars($d['bloco2_melhoria'])) . '</p><p><strong>' . __('admin.demands.pdf_consequence_label', 'Consequência:') . '</strong><br>' . nl2br(htmlspecialchars($d['bloco2_consequencia'])) . '</p>';
        echo '<h2>' . __('admin.demands.pdf_section_impacts', '3. Impactos') . '</h2><p><strong>' . __('admin.demands.pdf_financial_label', '3.1 Financeiro:') . '</strong><br>' . nl2br(htmlspecialchars($d['bloco3_financeiro'])) . '</p><p><strong>' . __('admin.demands.pdf_working_capital_label', '3.2 Capital de giro:') . '</strong><br>' . nl2br(htmlspecialchars($d['bloco3_capital_giro'])) . '</p><p><strong>' . __('admin.demands.pdf_operational_costs_label', '3.3 Custos operacionais:') . '</strong><br>' . nl2br(htmlspecialchars($d['bloco3_custos_operacionais'])) . '</p><p><strong>' . __('admin.demands.pdf_customer_journey_label', '3.4 Jornada do cliente:') . '</strong><br>' . nl2br(htmlspecialchars($d['bloco3_jornada_cliente'])) . '</p><p><strong>' . __('admin.demands.pdf_team_label', '3.5 Equipe:') . '</strong><br>' . nl2br(htmlspecialchars($d['bloco3_equipe'])) . '</p><p><strong>' . __('admin.demands.pdf_conflicts_label', '3.6 Conflitos:') . '</strong><br>' . nl2br(htmlspecialchars($d['bloco3_conflitos'])) . '</p>';
        echo '<h2>' . __('admin.demands.pdf_section_steps', '4. Etapas e Custos') . '</h2><table><thead><tr><th>' . __('admin.demands.pdf_step_col', 'Etapa') . '</th><th>' . __('admin.demands.pdf_cost_col', 'Custo') . '</th></tr></thead><tbody>';
        foreach ($etapas as $et) echo '<tr><td>' . htmlspecialchars($et['descricao'] ?? '') . '</td><td>' . htmlspecialchars($et['custo'] ?? '') . '</td></tr>';
        echo '</tbody></table>';
        echo '<h2>' . __('admin.demands.pdf_section_execution', '5. Execução') . '</h2><p><strong>' . __('admin.demands.pdf_new_or_existing_label', 'Novo/existente:') . '</strong><br>' . nl2br(htmlspecialchars($d['bloco5_novo_ou_existente'])) . '</p><p><strong>' . __('admin.demands.pdf_tools_label', 'Ferramentas:') . '</strong><br>' . nl2br(htmlspecialchars($d['bloco5_ferramentas'])) . '</p><p><strong>' . __('admin.demands.pdf_rules_label', 'Regras:') . '</strong><br>' . nl2br(htmlspecialchars($d['bloco5_regras'])) . '</p><p><strong>' . __('admin.demands.pdf_users_label', 'Usuários:') . '</strong><br>' . nl2br(htmlspecialchars($d['bloco5_usuarios'])) . '</p>';
        if ($d['nota_admin']) echo '<h2>' . __('admin.demands.pdf_admin_final_note', 'Nota Final do Administrador') . '</h2><p>' . nl2br(htmlspecialchars($d['nota_admin'])) . '</p>';
        echo '<div class="footer">' . __('admin.demands.pdf_generated_on', 'Documento gerado em') . ' ' . date('d/m/Y H:i:s') . ' — Braziliana Shop</div>';
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
        $nomeUsuario = __('admin.demands.default_system', 'Sistema');
        try {
            if ($uid) { $st = $this->db->prepare("SELECT nome FROM usuarios WHERE id = ? LIMIT 1"); $st->execute([$uid]); $nomeUsuario = (string)($st->fetchColumn() ?: __('admin.demands.default_user', 'Usuário')); }
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

        // Notificar solicitante por email sobre nova mensagem do admin/dev
        if ($msgId) {
            $this->notificarMensagemChat($id, $nomeUsuario, $mensagem, 'admin_para_solicitante');
        }

        $_SESSION['message'] = __('admin.demands.message_sent', 'Mensagem enviada!');
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
                    $this->registrarHistorico((int)$d['id'], 'em_teste', 'concluido', __('admin.demands.test_expired_note', 'Teste expirado (24h úteis). Fechado automaticamente.'));
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

            $titulo = $demanda['bloco1_titulo'] ?? $demanda['titulo'] ?? __('admin.demands.default_demand', 'Demanda');
            $solicitante = $demanda['solicitante'] ?? '';

            $assunto = __('admin.demands.email_rejected_subject', 'Demanda Recusada: {n}', ['n'=>$titulo]);
            $corpo = __('admin.demands.email_greeting', 'Olá {n},', ['n'=>$solicitante]) . "\n\n";
            $corpo .= __('admin.demands.email_rejected_body1', 'Sua demanda "{n}" foi analisada e infelizmente foi recusada.', ['n'=>$titulo]) . "\n\n";
            $corpo .= __('admin.demands.email_reason_label', 'Motivo:') . " " . ($motivo ?: __('admin.demands.not_informed', 'Não informado')) . "\n\n";
            $corpo .= __('admin.demands.email_rejected_body2', 'Se discordar da decisão ou tiver novas informações, você pode abrir uma nova solicitação com os ajustes necessários.') . "\n\n";
            $corpo .= __('admin.demands.email_signature_team', 'Atenciosamente,') . "\n" . __('admin.demands.email_signature_braziliana', 'Equipe Braziliana');

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

            $titulo = $demanda['bloco1_titulo'] ?? $demanda['titulo'] ?? __('admin.demands.default_demand', 'Demanda');
            $solicitante = $demanda['solicitante'] ?? '';

            $assunto = __('admin.demands.email_test_subject', 'Demanda Pronta para Teste: {n}', ['n'=>$titulo]);
            $corpo = __('admin.demands.email_greeting', 'Olá {n},', ['n'=>$solicitante]) . "\n\n";
            $corpo .= __('admin.demands.email_test_body1', 'Sua demanda "{n}" foi concluída pelo TI e está pronta para teste!', ['n'=>$titulo]) . "\n\n";
            $corpo .= __('admin.demands.email_test_body2', '⚠️ IMPORTANTE: Você tem 24 horas úteis (dias úteis, horário comercial) para testar e dar seu parecer.') . "\n\n";
            $corpo .= __('admin.demands.email_test_body3', 'Se não testar dentro do prazo, a demanda será automaticamente fechada como concluída e você precisará abrir uma nova solicitação caso encontre problemas.') . "\n\n";
            $corpo .= __('admin.demands.email_test_body4', 'Acesse o painel de demandas para testar: https://brazilianashop.com.br/admin/demandas/painel') . "\n\n";
            $corpo .= __('admin.demands.email_signature_team', 'Atenciosamente,') . "\n" . __('admin.demands.email_signature_it_braziliana', 'Equipe TI Braziliana');

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

        $title = __('admin.demands.settings_title', 'Configurações de Demandas'); $sidebarActive = 'configuracoes';
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';
        ob_start();
        echo '<div class="container-fluid py-3"><div class="row justify-content-center"><div class="col-lg-8">';
        echo '<div class="d-flex justify-content-between align-items-center mb-4"><h1 class="page-title">' . __('admin.demands.settings_title', 'Configurações de Demandas') . '</h1><a href="/admin/configuracoes" class="btn btn-sm btn-secondary"><i class="fas fa-arrow-left me-1"></i>' . __('admin.demands.back', 'Voltar') . '</a></div>';

        if (!empty($_SESSION['message'])) {
            echo '<div class="alert alert-' . ($_SESSION['message_type'] ?? 'info') . ' alert-dismissible fade show">' . htmlspecialchars($_SESSION['message']) . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
            unset($_SESSION['message'], $_SESSION['message_type']);
        }

        echo '<form method="POST" action="/admin/demandas/configuracoes">';
        echo '<div class="card border-0 shadow-sm mb-4"><div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold small"><i class="fas fa-lock me-2"></i>' . __('admin.demands.panel_access', 'Acesso ao Painel') . '</h6></div><div class="card-body">';
        echo '<div class="mb-3"><label class="form-label fw-semibold small">' . __('admin.demands.panel_password', 'Senha do Painel de Demandas') . '</label><input type="text" name="demandas_senha_painel" class="form-control" value="' . htmlspecialchars($config['demandas_senha_painel']) . '" placeholder="' . htmlspecialchars(__('admin.demands.leave_empty_disable', 'Deixe vazio para desativar'), ENT_QUOTES, 'UTF-8') . '"><small class="text-muted">' . __('admin.demands.panel_password_hint', 'Se preenchida, será exigida ao acessar o painel de demandas.') . '</small></div>';
        echo '</div></div>';

        echo '<div class="card border-0 shadow-sm mb-4"><div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold small"><i class="fas fa-envelope me-2"></i>' . __('admin.demands.email_notifications', 'Notificações por Email') . '</h6></div><div class="card-body">';
        echo '<div class="mb-3"><label class="form-label fw-semibold small">' . __('admin.demands.emails_receiving_requests', 'Emails que recebem novas solicitações') . '</label><textarea name="demandas_emails_notificacao" class="form-control" rows="3" placeholder="email1@exemplo.com, email2@exemplo.com">' . htmlspecialchars($config['demandas_emails_notificacao']) . '</textarea><small class="text-muted">' . __('admin.demands.emails_hint', 'Separados por vírgula. Toda nova demanda (bug ou função) será enviada para esses emails.') . '</small></div>';
        echo '</div></div>';

        echo '<div class="card border-0 shadow-sm mb-4"><div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold small"><i class="fas fa-plug me-2"></i>Webhook</h6></div><div class="card-body">';
        echo '<div class="mb-3"><label class="form-label fw-semibold small">' . __('admin.demands.webhook_url', 'URL do Webhook') . '</label><input type="url" name="demandas_webhook_url" class="form-control" value="' . htmlspecialchars($config['demandas_webhook_url']) . '" placeholder="https://hooks.slack.com/..."><small class="text-muted">' . __('admin.demands.webhook_hint', 'Recebe POST JSON com dados da nova solicitação. Compatível com Slack, Discord, etc.') . '</small></div>';
        echo '</div></div>';

        echo '<div class="card border-0 shadow-sm mb-4"><div class="card-header bg-white border-0 pt-3"><h6 class="fw-bold small"><i class="fas fa-bell me-2"></i>' . __('admin.demands.push_notifications', 'Notificações Push (no Admin)') . '</h6></div><div class="card-body">';
        echo '<div class="mb-3"><label class="form-label fw-semibold small">' . __('admin.demands.users_receiving_notifications', 'Usuários que recebem notificações') . '</label><select name="demandas_usuarios_notificacao[]" class="form-select" multiple size="6">';
        $idsNotif = array_filter(array_map('intval', explode(',', $config['demandas_usuarios_notificacao'])));
        foreach ($usuarios as $u) {
            $sel = in_array((int)$u['id'], $idsNotif) ? ' selected' : '';
            echo '<option value="' . (int)$u['id'] . '"' . $sel . '>' . htmlspecialchars($u['nome']) . ' (' . htmlspecialchars($u['email']) . ')</option>';
        }
        echo '</select><small class="text-muted">' . __('admin.demands.users_notif_hint', 'Segure Ctrl/Cmd para selecionar múltiplos. Esses usuários verão notificações em tempo real no painel admin.') . '</small></div>';
        echo '</div></div>';

        echo '<button type="submit" class="btn btn-dark w-100 mb-4"><i class="fas fa-save me-1"></i>' . __('admin.demands.save_settings', 'Salvar Configurações') . '</button>';
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

        $_SESSION['message'] = __('admin.demands.settings_saved', 'Configurações salvas com sucesso!');
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
                $_SESSION['message'] = __('admin.demands.wrong_password', 'Senha incorreta.');
                $_SESSION['message_type'] = 'danger';
            }
        }

        // Mostrar tela de senha
        $title = __('admin.demands.panel_restricted_title', 'Painel de Demandas - Acesso Restrito'); $sidebarActive = 'demandas-painel';
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';
        ob_start();
        echo '<div class="container-fluid py-5"><div class="row justify-content-center"><div class="col-md-4">';
        echo '<div class="card border-0 shadow-sm"><div class="card-body text-center py-5">';
        echo '<i class="fas fa-lock fs-1 text-muted mb-3 d-block"></i>';
        echo '<h5 class="fw-bold mb-3">' . __('admin.demands.restricted_access', 'Acesso Restrito') . '</h5>';
        echo '<p class="text-muted small mb-4">' . __('admin.demands.restricted_access_hint', 'O painel de demandas requer autenticação adicional.') . '</p>';
        if (!empty($_SESSION['message'])) {
            echo '<div class="alert alert-' . ($_SESSION['message_type'] ?? 'info') . ' small">' . htmlspecialchars($_SESSION['message']) . '</div>';
            unset($_SESSION['message'], $_SESSION['message_type']);
        }
        echo '<form method="POST" action="/admin/demandas/painel">';
        echo '<div class="mb-3"><input type="password" name="senha_painel" class="form-control text-center" placeholder="' . htmlspecialchars(__('admin.demands.enter_password', 'Digite a senha'), ENT_QUOTES, 'UTF-8') . '" autofocus required></div>';
        echo '<button type="submit" class="btn btn-dark w-100"><i class="fas fa-unlock me-1"></i>' . __('admin.demands.access', 'Acessar') . '</button>';
        echo '</form>';
        echo '</div></div></div></div></div>';
        $content = ob_get_clean();
        include __DIR__ . '/../Views/layouts/admin.php';
        return false;
    }

    /**
     * Notificar por email quando uma nova mensagem é enviada no chat da demanda
     */
    private function notificarMensagemChat(int $demandaId, string $remetente, string $mensagem, string $direcao): void {
        try {
            // Buscar dados da demanda
            $st = $this->db->prepare("SELECT * FROM demandas WHERE id = ? LIMIT 1");
            $st->execute([$demandaId]);
            $demanda = $st->fetch(\PDO::FETCH_ASSOC);
            if (!$demanda) return;

            $titulo = $demanda['bloco1_titulo'] ?? $demanda['titulo'] ?? __('admin.demands.demand_number', 'Demanda #{n}', ['n'=>$demandaId]);
            $preview = mb_substr($mensagem, 0, 200);
            if ($preview === '') $preview = __('admin.demands.attached_file_preview', '(arquivo anexado)');

            if ($direcao === 'admin_para_solicitante') {
                // Admin/dev enviou mensagem → notificar o solicitante (email + push)
                $email = $this->getEmailSolicitante($demanda);
                $criadorId = (int)($demanda['criado_por'] ?? 0);

                // Email para o solicitante (apenas se a demanda não está concluída/arquivada)
                $statusDemanda = strtolower(trim((string)($demanda['status'] ?? '')));
                $arquivado = (int)($demanda['arquivado'] ?? 0);
                if ($email && $statusDemanda !== 'concluido' && !$arquivado) {
                    $assunto = __('admin.demands.email_new_msg_subject', '💬 Nova mensagem na sua demanda: {n}', ['n'=>$titulo]);
                    $corpo = __('admin.demands.email_greeting', 'Olá {n},', ['n'=>($demanda['solicitante'] ?? $demanda['bloco1_solicitante'] ?? '')]) . "\n\n";
                    $corpo .= __('admin.demands.email_new_msg_body', 'Você recebeu uma nova mensagem na sua demanda "{n}".', ['n'=>$titulo]) . "\n\n";
                    $corpo .= __('admin.demands.email_from_label', 'De:') . " {$remetente}\n";
                    $corpo .= __('admin.demands.email_message_label', 'Mensagem:') . " {$preview}\n\n";
                    $corpo .= __('admin.demands.email_reply_link', 'Acesse para responder: https://brazilianashop.com.br/admin/demandas/minha/{n}#chat', ['n'=>$demandaId]) . "\n\n";
                    $corpo .= __('admin.demands.email_signature_team', 'Atenciosamente,') . "\n" . __('admin.demands.email_signature_it_braziliana', 'Equipe TI Braziliana');
                    try { $this->enviarEmailSimples($email, $assunto, $corpo); } catch (\Exception $e) {}
                }

                // Notificação push (sino) para o criador da demanda
                if ($criadorId > 0) {
                    $this->ensureNotificacoesTable();
                    $link = '/admin/demandas/minha/' . $demandaId . '#chat';
                    try {
                        $this->db->prepare("INSERT INTO admin_notificacoes (usuario_id, tipo, titulo, mensagem, link) VALUES (?,?,?,?,?)")
                            ->execute([$criadorId, 'demanda_mensagem', '💬 ' . __('admin.demands.notif_replied', '{n} respondeu', ['n'=>$remetente]), $preview, $link]);
                    } catch (\Exception $e) {}
                }

            } elseif ($direcao === 'solicitante_para_admin') {
                // Solicitante enviou mensagem → notificar devs/admins configurados
                $statusDemanda = strtolower(trim((string)($demanda['status'] ?? '')));
                $arquivado = (int)($demanda['arquivado'] ?? 0);

                // Email: apenas se demanda não está concluída/arquivada
                if ($statusDemanda !== 'concluido' && !$arquivado) {
                    $emails = $this->getConfig('demandas_emails_notificacao');
                    if ($emails !== '') {
                        $listaEmails = array_filter(array_map('trim', explode(',', $emails)));
                        $assunto = __('admin.demands.email_client_msg_subject', '💬 Nova mensagem do cliente na demanda: {n}', ['n'=>$titulo]);
                        $corpo = __('admin.demands.email_client_msg_body', 'Nova mensagem recebida na demanda "{n}".', ['n'=>$titulo]) . "\n\n";
                        $corpo .= __('admin.demands.email_from_label', 'De:') . " {$remetente}\n";
                        $corpo .= __('admin.demands.email_message_label', 'Mensagem:') . " {$preview}\n\n";
                        $corpo .= __('admin.demands.email_reply_link_admin', 'Acesse para responder: https://brazilianashop.com.br/admin/demandas/detalhe/{n}#chat', ['n'=>$demandaId]) . "\n\n";
                        $corpo .= __('admin.demands.email_signature_team', 'Atenciosamente,') . "\n" . __('admin.demands.email_signature_system_braziliana', 'Sistema Braziliana');

                        foreach ($listaEmails as $email) {
                            if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                                try { $this->enviarEmailSimples($email, $assunto, $corpo); } catch (\Exception $e) {}
                            }
                        }
                    }
                }

                // Notificação push para admins configurados (sempre, independente do status)
                $usuariosNotif = $this->getConfig('demandas_usuarios_notificacao');
                if ($usuariosNotif !== '') {
                    $this->ensureNotificacoesTable();
                    $ids = array_filter(array_map('intval', explode(',', $usuariosNotif)));
                    $link = '/admin/demandas/detalhe/' . $demandaId . '#chat';
                    foreach ($ids as $uid) {
                        if ($uid > 0) {
                            try {
                                $this->db->prepare("INSERT INTO admin_notificacoes (usuario_id, tipo, titulo, mensagem, link) VALUES (?,?,?,?,?)")
                                    ->execute([$uid, 'demanda_mensagem', '💬 ' . __('admin.demands.notif_replied', '{n} respondeu', ['n'=>$remetente]), $preview, $link]);
                            } catch (\Exception $e) {}
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            error_log('[DEMANDAS] Erro ao notificar mensagem chat: ' . $e->getMessage());
        }
    }

    /**
     * Notificar devs sobre nova demanda (email + webhook + push)
     */
    private function notificarNovaDemanda(int $demandaId, string $titulo, string $solicitante, string $tipo): void {
        // 1. Email para devs configurados
        $emails = $this->getConfig('demandas_emails_notificacao');
        if ($emails !== '') {
            $listaEmails = array_filter(array_map('trim', explode(',', $emails)));
            $assunto = ($tipo === 'bug' ? '🐛 ' : '🚀 ') . __('admin.demands.email_new_request_subject', 'Nova Solicitação: {n}', ['n'=>$titulo]);
            $corpo = __('admin.demands.email_new_request_body', 'Nova solicitação de demanda registrada:') . "\n\n";
            $corpo .= __('admin.demands.email_title_label', 'Título:') . " {$titulo}\n";
            $corpo .= __('admin.demands.email_requester_label', 'Solicitante:') . " {$solicitante}\n";
            $corpo .= __('admin.demands.email_type_label', 'Tipo:') . " " . ($tipo === 'bug' ? __('admin.demands.type_bug', 'Bug/Erro') : __('admin.demands.type_new_feature', 'Nova Função')) . "\n\n";
            $corpo .= __('admin.demands.email_panel_link', 'Acesse o painel: https://brazilianashop.com.br/admin/demandas/painel') . "\n";
            $corpo .= __('admin.demands.email_detail_link', 'Detalhe: https://brazilianashop.com.br/admin/demandas/detalhe/{n}', ['n'=>$demandaId]) . "\n";

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
            $tituloNotif = ($tipo === 'bug' ? '🐛 ' . __('admin.demands.notif_bug_prefix', 'Bug:') . ' ' : '🚀 ' . __('admin.demands.notif_new_prefix', 'Nova:') . ' ') . $titulo;
            $msgNotif = __('admin.demands.email_requester_label', 'Solicitante:') . ' ' . $solicitante;
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

    /**
     * Arquivar/desarquivar demanda
     */
    public function arquivar(Request $request, $id) {
        $auth = new AuthService(); $auth->requerPerfis(['admin']);
        $id = (int)$id;
        $arquivar = (int)($_POST['arquivar'] ?? 1);

        $this->ensureColumnArquivado();
        $this->db->prepare("UPDATE demandas SET arquivado = ? WHERE id = ?")->execute([$arquivar, $id]);

        $_SESSION['message'] = $arquivar ? __('admin.demands.demand_archived', 'Demanda arquivada.') : __('admin.demands.demand_unarchived', 'Demanda desarquivada.');
        $_SESSION['message_type'] = 'success';
        $this->redirect('/admin/demandas/painel');
    }

    /**
     * Lista demandas arquivadas
     */
    public function arquivados(Request $request) {
        $auth = new AuthService(); $auth->requerPerfis(['admin','suporte']);
        $this->ensureColumnArquivado();

        $st = $this->db->query("SELECT * FROM demandas WHERE arquivado = 1 ORDER BY updated_at DESC");
        $demandas = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];

        $statusLabels = ['pendente'=>__('admin.demands.status_pending','Pendente'),'em_analise'=>__('admin.demands.status_analyzing','Em Análise'),'em_execucao'=>__('admin.demands.status_in_progress','Em Execução'),'em_teste'=>__('admin.demands.status_testing','Em Teste'),'recusado'=>__('admin.demands.status_rejected','Recusado'),'concluido'=>__('admin.demands.status_completed','Concluído')];
        $statusCores = ['pendente'=>'secondary','em_analise'=>'primary','em_execucao'=>'warning','em_teste'=>'info','recusado'=>'danger','concluido'=>'success'];

        $title = __('admin.demands.archived_title', 'Demandas Arquivadas'); $sidebarActive = 'demandas-painel';
        include_once __DIR__ . '/../Views/partials/admin_sidebar.php';
        ob_start(); require __DIR__ . '/../Views/admin/demandas/arquivados.php'; $content = ob_get_clean();
        include __DIR__ . '/../Views/layouts/admin.php';
    }

    // === PRIVATE ===
    private function listar($status = null) {
        $this->ensureColumnArquivado();
        if ($status) {
            $sql = "SELECT * FROM demandas WHERE status = ? AND arquivado = 0 ORDER BY created_at DESC";
            $st = $this->db->prepare($sql); $st->execute([$status]);
        } else {
            $sql = "SELECT * FROM demandas WHERE arquivado = 0 ORDER BY created_at DESC";
            $st = $this->db->prepare($sql); $st->execute();
        }
        return $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
    }
    private function getById($id) { $st = $this->db->prepare("SELECT * FROM demandas WHERE id = ?"); $st->execute([$id]); return $st->fetch(\PDO::FETCH_ASSOC) ?: null; }
    private function getHistorico($id) { $st = $this->db->prepare("SELECT * FROM demanda_historico WHERE demanda_id = ? ORDER BY created_at ASC"); $st->execute([$id]); return $st->fetchAll(\PDO::FETCH_ASSOC) ?: []; }
    private function registrarHistorico($id, $anterior, $novo, $obs = null) { $this->db->prepare("INSERT INTO demanda_historico (demanda_id, status_anterior, status_novo, usuario_id, observacao) VALUES (?,?,?,?,?)")->execute([$id, $anterior, $novo, $_SESSION['usuario_id'] ?? null, $obs]); }
    private function ensureTables() { try { $this->db->query("SELECT 1 FROM demandas LIMIT 1"); } catch (\Exception $e) { $f = __DIR__ . '/../../database/migrations/165_create_demandas_schema.sql'; if (file_exists($f)) { foreach (array_filter(array_map('trim', explode(';', file_get_contents($f)))) as $s) { if ($s && stripos($s,'--')!==0) try { $this->db->exec($s); } catch (\Exception $ex) {} } } } }

    private function ensureColumnArquivado(): void {
        try { $this->db->query("SELECT arquivado FROM demandas LIMIT 1"); } catch (\Exception $e) {
            try { $this->db->exec("ALTER TABLE demandas ADD COLUMN arquivado TINYINT(1) NOT NULL DEFAULT 0 AFTER teste_expirado"); } catch (\Exception $ex) {}
        }
    }
}
