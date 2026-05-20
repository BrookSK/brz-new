<?php
namespace App\Controllers;

use App\Core\Request;

/**
 * Endpoint de webhook para criação de tickets via WhatsApp.
 * 
 * POST /webhook/criar-ticket
 * 
 * Payload esperado (JSON):
 * {
 *   "suite": "16013",          // Suite do cliente (prioridade)
 *   "email": "cliente@email.com", // Email (fallback se suite não encontrar)
 *   "mensagem": "Descrição do problema",
 *   "assunto": "Problema com pedido",  // Opcional (default: "Suporte via WhatsApp")
 *   "telefone": "5519998980873",       // Opcional
 *   "nome": "Nome do cliente"          // Opcional
 * }
 * 
 * Resposta (JSON):
 * Sucesso: {"success": true, "ticket_id": 123, "message": "Ticket criado com sucesso"}
 * Erro:    {"success": false, "error": "usuario_nao_encontrado", "message": "Nenhum usuário encontrado com essa suite"}
 */
class WebhookTicketController {

    private $db;

    public function __construct() {
        $this->db = \Config\Database::getConnection();
    }

    public function criarTicket(Request $request) {
        header('Content-Type: application/json; charset=utf-8');

        // Ler payload JSON
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);

        if (!is_array($data)) {
            echo json_encode(['success' => false, 'error' => 'payload_invalido', 'message' => 'Payload JSON inválido']);
            return;
        }

        $suite = trim((string)($data['suite'] ?? ''));
        $email = trim((string)($data['email'] ?? ''));
        $mensagem = trim((string)($data['mensagem'] ?? ''));
        $assunto = trim((string)($data['assunto'] ?? ''));
        $telefone = trim((string)($data['telefone'] ?? ''));
        $nome = trim((string)($data['nome'] ?? ''));

        if ($assunto === '') {
            $assunto = 'Suporte via WhatsApp';
        }

        if ($mensagem === '') {
            echo json_encode(['success' => false, 'error' => 'mensagem_vazia', 'message' => 'A mensagem é obrigatória']);
            return;
        }

        if ($suite === '' && $email === '') {
            echo json_encode(['success' => false, 'error' => 'identificacao_ausente', 'message' => 'Informe a suite ou o email do cliente']);
            return;
        }

        // Buscar usuário
        $usuario = null;

        // 1. Tentar por suite
        if ($suite !== '') {
            $usuario = $this->buscarUsuarioPorSuite($suite);
        }

        // 2. Fallback por email
        if (!$usuario && $email !== '') {
            $usuario = $this->buscarUsuarioPorEmail($email);
        }

        if (!$usuario) {
            $motivo = $suite !== '' ? 'Nenhum usuário encontrado com a suite ' . $suite : 'Nenhum usuário encontrado com o email ' . $email;
            echo json_encode([
                'success' => false,
                'error' => 'usuario_nao_encontrado',
                'message' => $motivo,
                'suite_informada' => $suite,
                'email_informado' => $email,
            ]);
            return;
        }

        // Criar ticket
        try {
            $usuarioId = (int)$usuario['id'];

            // Verificar colunas disponíveis
            $colsT = [];
            try { $st = $this->db->query('DESCRIBE support_tickets'); $colsT = $st ? $st->fetchAll(\PDO::FETCH_COLUMN) : []; } catch (\Exception $e) {}

            $cols = ['usuario_id', 'assunto', 'status'];
            $vals = ['?', '?', '?'];
            $params = [$usuarioId, $assunto, 'open'];

            if (in_array('motivo', $colsT, true)) {
                $cols[] = 'motivo';
                $vals[] = '?';
                $params[] = $mensagem;
            }
            if (in_array('origem', $colsT, true)) {
                $cols[] = 'origem';
                $vals[] = '?';
                $params[] = 'whatsapp';
            }
            if (in_array('telefone_contato', $colsT, true) && $telefone !== '') {
                $cols[] = 'telefone_contato';
                $vals[] = '?';
                $params[] = $telefone;
            }

            $sql = 'INSERT INTO support_tickets (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ')';
            $st = $this->db->prepare($sql);
            $st->execute($params);
            $ticketId = (int)$this->db->lastInsertId();

            // Criar primeira mensagem do ticket
            if ($ticketId > 0) {
                $stMsg = $this->db->prepare("INSERT INTO support_ticket_messages (ticket_id, autor_tipo, autor_usuario_id, mensagem) VALUES (?, 'cliente', ?, ?)");
                $stMsg->execute([$ticketId, $usuarioId, $mensagem]);
            }

            echo json_encode([
                'success' => true,
                'ticket_id' => $ticketId,
                'message' => 'Ticket criado com sucesso',
                'usuario_id' => $usuarioId,
                'usuario_nome' => (string)($usuario['nome'] ?? ''),
                'usuario_email' => (string)($usuario['email'] ?? ''),
            ]);

        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => 'erro_interno',
                'message' => 'Erro ao criar ticket: ' . $e->getMessage(),
            ]);
        }
    }

    private function buscarUsuarioPorSuite(string $suite): ?array {
        try {
            // Verificar se coluna suite existe
            $cols = [];
            try { $st = $this->db->query('DESCRIBE usuarios'); $cols = $st ? $st->fetchAll(\PDO::FETCH_COLUMN) : []; } catch (\Exception $e) {}

            if (!in_array('suite', $cols, true)) {
                return null;
            }

            $st = $this->db->prepare("SELECT id, nome, email, suite, telefone FROM usuarios WHERE suite = ? LIMIT 1");
            $st->execute([$suite]);
            $row = $st->fetch(\PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }

    private function buscarUsuarioPorEmail(string $email): ?array {
        try {
            $st = $this->db->prepare("SELECT id, nome, email, suite, telefone FROM usuarios WHERE email = ? LIMIT 1");
            $st->execute([$email]);
            $row = $st->fetch(\PDO::FETCH_ASSOC);
            return $row ?: null;
        } catch (\Exception $e) {
            return null;
        }
    }
}
