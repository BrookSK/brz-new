<?php
namespace App\Controllers;

use App\Core\Request;

class ContatoController extends Controller {
    public function index(Request $request) {
        if (strtoupper((string) $request->getMethod()) === 'POST') {
            if (session_status() !== PHP_SESSION_ACTIVE) {
                @session_start();
            }

            $token = (string) $request->getParam('contact_token', '');
            if ($token === '') {
                return $this->json(['success' => false, 'error' => 'Token inválido. Recarregue a página e tente novamente.'], 400);
            }

            $expected = (string) ($_SESSION['contact_token'] ?? '');
            if ($expected === '' || !hash_equals($expected, $token)) {
                return $this->json(['success' => false, 'error' => 'Token expirado. Recarregue a página e tente novamente.'], 400);
            }

            // Idempotência: se esse token já foi processado com sucesso, não reenviar.
            $lastSent = (string) ($_SESSION['contact_token_last_sent'] ?? '');
            if ($lastSent !== '' && hash_equals($lastSent, $token)) {
                return $this->json(['success' => true, 'duplicate' => true]);
            }

            $nome = trim((string) $request->getParam('nome', ''));
            $email = trim((string) $request->getParam('email', ''));
            $telefone = trim((string) $request->getParam('telefone', ''));
            $assunto = trim((string) $request->getParam('assunto', ''));
            $mensagem = trim((string) $request->getParam('mensagem', ''));

            if ($nome === '' || $email === '' || $telefone === '' || $assunto === '' || $mensagem === '') {
                return $this->json(['success' => false, 'error' => 'Preencha os campos obrigatórios.'], 400);
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return $this->json(['success' => false, 'error' => 'E-mail inválido.'], 400);
            }

            $to = 'contato@brazilianashop.com.br';

            $subject = 'Contato - ' . $assunto;
            $safeNome = htmlspecialchars($nome, ENT_QUOTES, 'UTF-8');
            $safeEmail = htmlspecialchars($email, ENT_QUOTES, 'UTF-8');
            $safeTelefone = htmlspecialchars($telefone, ENT_QUOTES, 'UTF-8');
            $safeAssunto = htmlspecialchars($assunto, ENT_QUOTES, 'UTF-8');
            $safeMensagem = nl2br(htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8'));

            $html = ''
                . '<h3>Novo contato pelo site</h3>'
                . '<p><strong>Nome:</strong> ' . $safeNome . '</p>'
                . '<p><strong>E-mail:</strong> ' . $safeEmail . '</p>'
                . '<p><strong>Telefone:</strong> ' . ($safeTelefone !== '' ? $safeTelefone : '-') . '</p>'
                . '<p><strong>Assunto:</strong> ' . $safeAssunto . '</p>'
                . '<p><strong>Mensagem:</strong><br>' . $safeMensagem . '</p>'
                . '<hr>'
                . '<p><small>Enviado em ' . date('d/m/Y H:i:s') . '</small></p>';

            $headers = [];
            $headers[] = 'MIME-Version: 1.0';
            $headers[] = 'Content-Type: text/html; charset=UTF-8';
            $headers[] = 'From: Braziliana <noreply@brazilianashop.com.br>';
            $headers[] = 'Reply-To: ' . $email;

            $ok = @mail($to, $subject, $html, implode("\r\n", $headers));
            if (!$ok) {
                return $this->json(['success' => false, 'error' => 'Não foi possível enviar sua mensagem agora. Tente novamente.'], 500);
            }

            $_SESSION['contact_token_last_sent'] = $token;
            try {
                $_SESSION['contact_token'] = bin2hex(random_bytes(16));
            } catch (\Throwable $e) {
                $_SESSION['contact_token'] = (string) mt_rand(100000, 999999) . (string) time();
            }

            return $this->json(['success' => true]);
        }

        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
        if (empty($_SESSION['contact_token'])) {
            try {
                $_SESSION['contact_token'] = bin2hex(random_bytes(16));
            } catch (\Throwable $e) {
                $_SESSION['contact_token'] = (string) mt_rand(100000, 999999) . (string) time();
            }
        }
        $this->view('contato/index', ['contactToken' => (string) $_SESSION['contact_token']]);
    }
}
