<?php
namespace App\Controllers;

use App\Core\Request;

class FaqController extends Controller {
    public function index(Request $request) {
        $faqItems = [];
        try {
            $db = \Config\Database::getConnection();
            // Verificar se a tabela existe
            $st = $db->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'faq_items'");
            $st->execute();
            if ((int) ($st->fetchColumn() ?: 0) > 0) {
                $st2 = $db->query("SELECT id, titulo, conteudo, ordem FROM faq_items WHERE ativo = 1 ORDER BY ordem ASC, id ASC");
                $faqItems = $st2->fetchAll(\PDO::FETCH_ASSOC) ?: [];
            }
        } catch (\Exception $e) {
            $faqItems = [];
        }

        // Se não tem itens no banco, fallback para o arquivo estático
        if (empty($faqItems)) {
            $this->view('faq/index');
            return;
        }

        $this->view('faq/index_dynamic', ['faqItems' => $faqItems]);
    }
}
