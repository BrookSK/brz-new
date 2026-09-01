<?php
namespace App\Controllers;

use App\Models\PacoteRecebido;
use App\Services\AuthService;
use App\Services\EmailService;
use App\Core\Request;

class AdminPacotesRecebidosController extends Controller {
    private $model;
    private $connection;

    public function __construct() {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'vendedor', 'conferente']);
        $this->model = new PacoteRecebido();
        $this->connection = \Config\Database::getConnection();
    }

    /**
     * Lista de pacotes recebidos com filtros e paginação
     */
    public function index(Request $request): void {
        $filtros = [
            'suite' => $request->getParams()['suite'] ?? '',
            'status' => $request->getParams()['status'] ?? '',
            'data_inicio' => $request->getParams()['data_inicio'] ?? '',
            'data_fim' => $request->getParams()['data_fim'] ?? '',
            'busca' => $request->getParams()['busca'] ?? '',
        ];
        $pagina = (int) ($request->getParams()['pagina'] ?? 1);
        if ($pagina < 1) $pagina = 1;

        $resultado = $this->model->listar($filtros, $pagina);

        $statusList = self::getStatusList();
        $ncmOptions = PacoteRecebido::getNcmOptions();

        // Extrair dados para a view
        $pacotes = $resultado['registros'];
        $total = $resultado['total'];
        $totalPaginas = $resultado['total_paginas'];

        $suite = $filtros['suite'];
        $status = $filtros['status'];
        $data_inicio = $filtros['data_inicio'];
        $data_fim = $filtros['data_fim'];
        $busca = $filtros['busca'];

        $this->view('admin.pacotes-recebidos.index', compact(
            'pacotes', 'total', 'totalPaginas', 'pagina',
            'suite', 'status', 'data_inicio', 'data_fim', 'busca',
            'statusList', 'ncmOptions'
        ));
    }

    /**
     * Formulário de novo pacote
     */
    public function novo(Request $request): void {
        $ncmOptions = PacoteRecebido::getNcmOptions();
        $pacote = null;
        $editavel = true;

        $this->view('admin.pacotes-recebidos.form', compact('ncmOptions', 'pacote', 'editavel'));
    }

    /**
     * Formulário de edição
     */
    public function editar(Request $request): void {
        $id = (int) ($request->getParams()['id'] ?? 0);
        if ($id <= 0) {
            $this->redirect('/admin/pacotes-recebidos');
            return;
        }

        $pacote = $this->model->find($id);
        if (!$pacote) {
            $this->redirect('/admin/pacotes-recebidos');
            return;
        }

        $ncmOptions = PacoteRecebido::getNcmOptions();
        $editavel = ($pacote['status'] === 'pendente');

        $this->view('admin.pacotes-recebidos.form', compact('ncmOptions', 'pacote', 'editavel'));
    }

    /**
     * Salvar pacote (novo ou edição)
     */
    public function salvar(Request $request): void {
        $params = $request->getParams();
        $id = (int) ($params['id'] ?? 0);

        // Verificar se é edição e se está bloqueado
        if ($id > 0) {
            $existente = $this->model->find($id);
            if (!$existente) {
                $this->setFlash(__('admin.received_packages.not_found', 'Pacote não encontrado.'), 'danger');
                $this->redirect('/admin/pacotes-recebidos');
                return;
            }
            if ($existente['status'] !== 'pendente') {
                $this->setFlash(__('admin.received_packages.cannot_edit', 'Este pacote não pode mais ser editado.'), 'danger');
                $this->redirect('/admin/pacotes-recebidos/' . $id);
                return;
            }
        }

        // Validação
        $suite = (int) ($params['numero_suite'] ?? 0);
        $nome = trim($params['nome'] ?? '');
        $fornecedor = trim($params['fornecedor'] ?? '');
        $ncm = trim($params['ncm'] ?? '');
        $dataRecebimento = trim($params['data_recebimento'] ?? date('Y-m-d'));
        $pesoKg = (float) ($params['peso_kg'] ?? 0);
        $quantidade = (int) ($params['quantidade'] ?? 1);
        $descricao = trim($params['descricao'] ?? '');

        if ($suite <= 0 || $nome === '' || $fornecedor === '' || $pesoKg <= 0) {
            $this->setFlash(__('admin.received_packages.fill_required_fields', 'Preencha todos os campos obrigatórios.'), 'danger');
            $this->redirect($id > 0 ? '/admin/pacotes-recebidos/' . $id : '/admin/pacotes-recebidos/novo');
            return;
        }

        // Validar se existe usuario com essa suite
        $usuario = $this->model->buscarUsuarioPorSuite($suite);
        if (!$usuario) {
            $this->setFlash(__('admin.received_packages.no_client_for_suite', 'Nenhum cliente encontrado com a suite') . ' ' . $suite . '.', 'danger');
            $this->redirect($id > 0 ? '/admin/pacotes-recebidos/' . $id : '/admin/pacotes-recebidos/novo');
            return;
        }

        // Upload de foto
        $fotoUrl = $params['foto_url_existente'] ?? ($existente['foto_url'] ?? null);
        if (!empty($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $fotoUrl = $this->handleUpload($_FILES['foto']);
        }

        $data = [
            'numero_suite' => $suite,
            'usuario_id' => (int) $usuario['id'],
            'nome' => $nome,
            'descricao' => $descricao,
            'fornecedor' => $fornecedor,
            'ncm' => $ncm,
            'data_recebimento' => $dataRecebimento,
            'peso_kg' => $pesoKg,
            'quantidade' => $quantidade,
            'foto_url' => $fotoUrl,
        ];

        if ($id > 0) {
            $this->model->update($id, $data);
            $this->setFlash(__('admin.received_packages.updated_success', 'Pacote atualizado com sucesso.'), 'success');
        } else {
            $data['status'] = 'pendente';
            $data['dias_armazenamento'] = 0;
            $novoId = $this->model->create($data);

            // Enviar e-mail ao cliente
            $this->enviarEmailNovoPacote($usuario, $data);

            $this->setFlash(__('admin.received_packages.created_success', 'Pacote cadastrado com sucesso. E-mail enviado ao cliente.'), 'success');
            $id = $novoId;
        }

        $this->redirect('/admin/pacotes-recebidos');
    }

    /**
     * Excluir pacote (somente se pendente)
     */
    public function excluir(Request $request): void {
        $id = (int) ($request->getParams()['id'] ?? 0);
        if ($id <= 0) {
            $this->redirect('/admin/pacotes-recebidos');
            return;
        }

        $pacote = $this->model->find($id);
        if (!$pacote || $pacote['status'] !== 'pendente') {
            $this->setFlash(__('admin.received_packages.cannot_delete', 'Pacote não pode ser excluído.'), 'danger');
            $this->redirect('/admin/pacotes-recebidos');
            return;
        }

        $this->model->delete($id);
        $this->setFlash(__('admin.received_packages.deleted_success', 'Pacote excluído com sucesso.'), 'success');
        $this->redirect('/admin/pacotes-recebidos');
    }

    /**
     * API: Buscar usuario por suite ou nome (AJAX)
     */
    public function buscarSuite(Request $request): void {
        $suite = (int) ($request->getParams()['suite'] ?? 0);
        $busca = trim($request->getParams()['busca'] ?? '');

        // Se veio 'busca', pode ser suite (número) ou nome (texto)
        if ($busca !== '' && $suite <= 0) {
            if (is_numeric($busca)) {
                $suite = (int) $busca;
            }
        }

        // Buscar por suite
        if ($suite > 0) {
            $usuario = $this->model->buscarUsuarioPorSuite($suite);
            if ($usuario) {
                $this->json([
                    'success' => true,
                    'usuario' => [
                        'id' => $usuario['id'],
                        'nome' => $usuario['nome'],
                        'email' => $usuario['email'],
                        'suite' => $usuario['suite'],
                    ],
                ]);
                return;
            }
        }

        // Buscar por nome
        if ($busca !== '' && !is_numeric($busca)) {
            try {
                $db = \Config\Database::getConnection();
                $st = $db->prepare("SELECT id, nome, email, suite FROM usuarios WHERE nome LIKE ? AND suite IS NOT NULL AND suite > 0 ORDER BY nome ASC LIMIT 1");
                $st->execute(['%' . $busca . '%']);
                $usuario = $st->fetch(\PDO::FETCH_ASSOC);
                if ($usuario) {
                    $this->json([
                        'success' => true,
                        'usuario' => [
                            'id' => $usuario['id'],
                            'nome' => $usuario['nome'],
                            'email' => $usuario['email'],
                            'suite' => $usuario['suite'],
                        ],
                    ]);
                    return;
                }
            } catch (\Throwable $e) {}
        }

        $this->json(['success' => false, 'message' => __('admin.received_packages.no_client_found', 'Nenhum cliente encontrado com') . ' "' . htmlspecialchars($busca ?: (string) $suite) . '"'], 404);
    }

    /**
     * Página de configurações de taxas de pacotes
     */
    public function configuracoes(Request $request): void {
        $configs = $this->getConfigs();
        $this->view('admin.pacotes-recebidos.configuracoes', compact('configs'));
    }

    /**
     * Salvar configurações
     */
    public function salvarConfiguracoes(Request $request): void {
        $params = $request->getParams();

        $chaves = [
            'pacote_dias_multa_inicio',
            'pacote_multa_valor_dia_usd',
            'pacote_dias_descarte',
            'pacote_lembrete_intervalo_dias',
            'pacote_taxa_seguro_percentual',
        ];

        foreach ($chaves as $chave) {
            if (isset($params[$chave])) {
                $valor = trim($params[$chave]);
                $stmt = $this->connection->prepare(
                    "INSERT INTO configuracoes_sistema (chave, valor) VALUES (:chave, :valor)
                     ON DUPLICATE KEY UPDATE valor = :valor2"
                );
                $stmt->execute([':chave' => $chave, ':valor' => $valor, ':valor2' => $valor]);
            }
        }

        $this->setFlash(__('admin.received_packages.settings_saved', 'Configurações salvas com sucesso.'), 'success');
        $this->redirect('/admin/pacotes-recebidos/configuracoes');
    }

    // ==================== Métodos privados ====================

    /**
     * Lista de status possíveis com labels
     */
    public static function getStatusList(): array {
        return [
            'pendente' => __('admin.received_packages.status_pending', 'Pendente'),
            'pedido_criado' => __('admin.received_packages.status_order_created', 'Pedido Criado'),
            'invoice_liberado' => __('admin.received_packages.status_invoice_released', 'Invoice Liberado'),
            'invoice_confirmado' => __('admin.received_packages.status_invoice_confirmed', 'Invoice Confirmado'),
            'invoice_contestado' => __('admin.received_packages.status_invoice_disputed', 'Invoice Contestado'),
            'enviado' => __('admin.received_packages.status_shipped', 'Enviado'),
            'fatura_pendente' => __('admin.received_packages.status_invoice_pending', 'Fatura Pendente'),
            'fatura_paga' => __('admin.received_packages.status_invoice_paid', 'Fatura Paga'),
            'descartado' => __('admin.received_packages.status_discarded', 'Descartado'),
        ];
    }

    /**
     * Upload de imagem do pacote
     */
    private function handleUpload(array $file): ?string {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
        if (!in_array($file['type'], $allowedTypes, true)) {
            return null;
        }

        // Usar mesmo padrão dos uploads de produtos: $_SERVER['DOCUMENT_ROOT']/uploads/pacotes/
        $docRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
        $uploadDir = $docRoot . '/uploads/pacotes/';
        if (!is_dir($uploadDir)) {
            // Fallback: tentar public/uploads/pacotes/
            $uploadDir = $docRoot . '/public/uploads/pacotes/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0775, true);
            }
        }

        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'pacote_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $destino = $uploadDir . $filename;

        if (move_uploaded_file($file['tmp_name'], $destino)) {
            return '/uploads/pacotes/' . $filename;
        }

        return null;
    }

    /**
     * Enviar e-mail informando cliente sobre novo pacote
     */
    private function enviarEmailNovoPacote(array $usuario, array $pacote): void {
        try {
            $emailService = new EmailService();
            $html = $this->buildEmailNovoPacote($usuario, $pacote);
            $emailSubject = __('admin.received_packages.email_subject', 'Seu produto foi cadastrado!');
            $emailService->send(
                $usuario['email'],
                $emailSubject,
                $html,
                'pacote_novo_' . ($pacote['numero_suite'] ?? '') . '_' . date('Ymd_His'),
                [
                    'evento' => 'pacote_recebido',
                    'to_email' => $usuario['email'],
                    'subject' => $emailSubject,
                    'usuario_id' => $usuario['id'],
                ]
            );
        } catch (\Throwable $e) {
            // Log silencioso - não impedir o cadastro
            error_log('[PacotesRecebidos] Erro ao enviar e-mail: ' . $e->getMessage());
        }
    }

    /**
     * Montar HTML do e-mail de novo pacote
     */
    private function buildEmailNovoPacote(array $usuario, array $pacote): string {
        $nome = htmlspecialchars($usuario['nome'] ?? 'Cliente');
        $prodNome = htmlspecialchars($pacote['nome'] ?? '');
        $fornecedor = htmlspecialchars($pacote['fornecedor'] ?? '');
        $peso = number_format((float) ($pacote['peso_kg'] ?? 0), 3, ',', '.');
        $qtd = (int) ($pacote['quantidade'] ?? 1);
        $data = date('d/m/Y', strtotime($pacote['data_recebimento'] ?? 'now'));

        $tTitle = __('admin.received_packages.email_subject', 'Seu produto foi cadastrado!');
        $tHello = __('admin.received_packages.email_hello', 'Olá');
        $tIntro = __('admin.received_packages.email_intro', 'Informamos que recebemos um novo produto em nosso armazém vinculado à sua suite:');
        $tProduct = __('admin.received_packages.email_product', 'Produto');
        $tSupplier = __('admin.received_packages.email_supplier', 'Fornecedor');
        $tWeight = __('admin.received_packages.email_weight', 'Peso');
        $tQuantity = __('admin.received_packages.email_quantity', 'Quantidade');
        $tReceiptDate = __('admin.received_packages.email_receipt_date', 'Data de Recebimento');
        $tAttention = __('admin.received_packages.email_attention', 'Atenção:');
        $tDeadline = __('admin.received_packages.email_deadline', 'Você tem até <strong>30 dias</strong> para concluir suas compras e solicitar o envio.');
        $tFee = __('admin.received_packages.email_fee', 'Após 15 dias, será cobrada uma taxa de armazenamento de US$ 2,00/dia.');
        $tDiscard = __('admin.received_packages.email_discard', 'Após 42 dias, o produto será descartado.');
        $tViewCart = __('admin.received_packages.email_view_cart', 'Ver meu Carrinho');
        $tFooter = __('admin.received_packages.email_footer', 'Braziliana - Seu parceiro em compras internacionais');

        return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; color: #333; max-width: 600px; margin: 0 auto;">
    <div style="background: #1a3a5c; padding: 20px; text-align: center;">
        <h1 style="color: #fff; margin: 0; font-size: 22px;">Braziliana</h1>
    </div>
    <div style="padding: 30px 20px;">
        <h2 style="color: #1a3a5c;">{$tTitle}</h2>
        <p>{$tHello}, <strong>{$nome}</strong>!</p>
        <p>{$tIntro}</p>
        
        <table style="width: 100%; border-collapse: collapse; margin: 20px 0;">
            <tr style="background: #f5f5f5;">
                <td style="padding: 10px; border: 1px solid #ddd;"><strong>{$tProduct}</strong></td>
                <td style="padding: 10px; border: 1px solid #ddd;">{$prodNome}</td>
            </tr>
            <tr>
                <td style="padding: 10px; border: 1px solid #ddd;"><strong>{$tSupplier}</strong></td>
                <td style="padding: 10px; border: 1px solid #ddd;">{$fornecedor}</td>
            </tr>
            <tr style="background: #f5f5f5;">
                <td style="padding: 10px; border: 1px solid #ddd;"><strong>{$tWeight}</strong></td>
                <td style="padding: 10px; border: 1px solid #ddd;">{$peso} kg</td>
            </tr>
            <tr>
                <td style="padding: 10px; border: 1px solid #ddd;"><strong>{$tQuantity}</strong></td>
                <td style="padding: 10px; border: 1px solid #ddd;">{$qtd}</td>
            </tr>
            <tr style="background: #f5f5f5;">
                <td style="padding: 10px; border: 1px solid #ddd;"><strong>{$tReceiptDate}</strong></td>
                <td style="padding: 10px; border: 1px solid #ddd;">{$data}</td>
            </tr>
        </table>

        <div style="background: #fff3cd; border: 1px solid #ffc107; border-radius: 5px; padding: 15px; margin: 20px 0;">
            <strong>⚠️ {$tAttention}</strong><br>
            {$tDeadline}<br>
            {$tFee}<br>
            {$tDiscard}
        </div>

        <p style="text-align: center; margin-top: 30px;">
            <a href="https://brazilianashop.com.br/carrinho" style="background: #1a3a5c; color: #fff; text-decoration: none; padding: 12px 30px; border-radius: 5px; display: inline-block;">
                {$tViewCart}
            </a>
        </p>
    </div>
    <div style="background: #f5f5f5; padding: 15px; text-align: center; font-size: 12px; color: #666;">
        {$tFooter}
    </div>
</body>
</html>
HTML;
    }

    /**
     * Buscar configurações do sistema de pacotes
     */
    private function getConfigs(): array {
        $chaves = [
            'pacote_dias_multa_inicio',
            'pacote_multa_valor_dia_usd',
            'pacote_dias_descarte',
            'pacote_lembrete_intervalo_dias',
            'pacote_taxa_seguro_percentual',
        ];

        $configs = [];
        foreach ($chaves as $chave) {
            try {
                $stmt = $this->connection->prepare('SELECT valor FROM configuracoes_sistema WHERE chave = ? LIMIT 1');
                $stmt->execute([$chave]);
                $configs[$chave] = $stmt->fetchColumn() ?: '';
            } catch (\Throwable $e) {
                $configs[$chave] = '';
            }
        }
        return $configs;
    }

    /**
     * Helper para mensagem flash na session
     */
    private function setFlash(string $message, string $type = 'info'): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $_SESSION['message'] = $message;
        $_SESSION['message_type'] = $type;
    }
}
