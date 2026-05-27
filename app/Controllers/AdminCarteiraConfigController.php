<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;

class AdminCarteiraConfigController extends Controller {

    public function index(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin']);

        $db = \Config\Database::getConnection();

        // Garantir que a tabela de histórico existe
        $db->exec("CREATE TABLE IF NOT EXISTS `carteira_cobertura_historico` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `modo_anterior` varchar(50) NOT NULL,
            `modo_novo` varchar(50) NOT NULL,
            `alterado_por` int(11) DEFAULT NULL,
            `alterado_por_nome` varchar(200) DEFAULT NULL,
            `motivo` text DEFAULT NULL,
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_created_at` (`created_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

        // Obter configuração atual
        $modoAtual = 'subtotal_taxa';
        try {
            $st = $db->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = 'carteira_cobertura_modo' LIMIT 1");
            $st->execute();
            $row = $st->fetch(\PDO::FETCH_ASSOC);
            if ($row && !empty($row['valor'])) {
                $modoAtual = (string) $row['valor'];
            }
        } catch (\Exception $e) {}

        // Obter histórico de alterações
        $historico = [];
        try {
            $st = $db->prepare('SELECT * FROM carteira_cobertura_historico ORDER BY created_at DESC LIMIT 50');
            $st->execute();
            $historico = $st->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {}

        // Mensagem de sucesso (flash)
        $successMsg = '';
        if (!empty($_SESSION['_flash_carteira_config_success'])) {
            $successMsg = $_SESSION['_flash_carteira_config_success'];
            unset($_SESSION['_flash_carteira_config_success']);
        }

        $this->view('admin/carteira-config/index', [
            'modo_atual' => $modoAtual,
            'historico' => $historico,
            'success_msg' => $successMsg,
        ]);
    }

    public function salvar(Request $request) {
        $auth = new AuthService();
        $auth->requerPerfis(['admin']);

        $dados = $request->getParams();
        $novoModo = strtolower(trim((string) ($dados['modo'] ?? '')));
        $motivo = trim((string) ($dados['motivo'] ?? ''));

        // Validar modo
        $modosValidos = ['subtotal_taxa', 'subtotal_taxa_impostos'];
        if (!in_array($novoModo, $modosValidos, true)) {
            $_SESSION['_flash_carteira_config_success'] = 'Modo inválido.';
            $this->redirect('/admin/carteira-config');
            return;
        }

        // Validar motivo obrigatório
        if ($motivo === '') {
            $_SESSION['_flash_carteira_config_success'] = 'O motivo da alteração é obrigatório.';
            $this->redirect('/admin/carteira-config');
            return;
        }

        $db = \Config\Database::getConnection();

        // Obter modo atual
        $modoAtual = 'subtotal_taxa';
        try {
            $st = $db->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = 'carteira_cobertura_modo' LIMIT 1");
            $st->execute();
            $row = $st->fetch(\PDO::FETCH_ASSOC);
            if ($row && !empty($row['valor'])) {
                $modoAtual = (string) $row['valor'];
            }
        } catch (\Exception $e) {}

        // Se não mudou, não fazer nada
        if ($novoModo === $modoAtual) {
            $_SESSION['_flash_carteira_config_success'] = 'Configuração já está nesse modo.';
            $this->redirect('/admin/carteira-config');
            return;
        }

        // Atualizar configuração
        try {
            // Verificar se a chave existe
            $st = $db->prepare("SELECT COUNT(*) FROM configuracoes_sistema WHERE chave = 'carteira_cobertura_modo'");
            $st->execute();
            $exists = (int) $st->fetchColumn() > 0;

            if ($exists) {
                $st = $db->prepare("UPDATE configuracoes_sistema SET valor = ? WHERE chave = 'carteira_cobertura_modo'");
                $st->execute([$novoModo]);
            } else {
                $st = $db->prepare("INSERT INTO configuracoes_sistema (chave, valor, descricao) VALUES ('carteira_cobertura_modo', ?, 'Modo de cobertura da carteira')");
                $st->execute([$novoModo]);
            }
        } catch (\Exception $e) {
            $_SESSION['_flash_carteira_config_success'] = 'Erro ao salvar: ' . $e->getMessage();
            $this->redirect('/admin/carteira-config');
            return;
        }

        // Registrar no histórico
        try {
            $usuario = $auth->getUsuarioLogado();
            $st = $db->prepare('INSERT INTO carteira_cobertura_historico (modo_anterior, modo_novo, alterado_por, alterado_por_nome, motivo) VALUES (?, ?, ?, ?, ?)');
            $st->execute([
                $modoAtual,
                $novoModo,
                (int) ($usuario['id'] ?? 0),
                (string) ($usuario['nome'] ?? ($usuario['email'] ?? 'Admin')),
                $motivo !== '' ? $motivo : null,
            ]);
        } catch (\Exception $e) {}

        $_SESSION['_flash_carteira_config_success'] = 'Configuração alterada com sucesso de "' . $this->getModoLabel($modoAtual) . '" para "' . $this->getModoLabel($novoModo) . '".';
        $this->redirect('/admin/carteira-config');
    }

    private function getModoLabel(string $modo): string {
        $labels = [
            'subtotal_taxa' => 'Subtotal + Taxa de Serviço',
            'subtotal_taxa_impostos' => 'Subtotal + Taxa + Impostos',
        ];
        return $labels[$modo] ?? $modo;
    }
}
