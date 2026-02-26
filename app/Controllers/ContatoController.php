<?php
namespace App\Controllers;

use App\Core\Request;

class ContatoController extends Controller {
    public function index(Request $request) {
        if (strtoupper((string) $request->getMethod()) === 'POST') {
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

            return $this->json(['success' => true]);
        }

        $this->view('contato/index');
    }
}
