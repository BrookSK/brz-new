<?php
namespace App\Controllers;

use App\Core\Request;

/**
 * Página pública que explica o serviço de redirecionamento de pacotes
 * para quem tem interesse em se tornar um redirecionador parceiro.
 */
class RedirecionadorController extends Controller {
    public function index(Request $request) {
        // Endereço de recebimento (sede nos EUA) — configurável no banco, com fallback fixo.
        $enderecoSede = $this->getEnderecoSede();
        $this->view('redirecionador/index', ['enderecoSede' => $enderecoSede]);
    }

    private function getEnderecoSede(): string
    {
        $fallback = "1227 W Broad St, Saint Pauls, NC 28384";
        try {
            $pdo = \Config\Database::getConnection();
            $st = $pdo->prepare("SELECT valor FROM configuracoes_sistema WHERE chave = 'redirecionamento_endereco_sede' LIMIT 1");
            $st->execute();
            $val = trim((string) ($st->fetchColumn() ?: ''));
            return $val !== '' ? $val : $fallback;
        } catch (\Exception $e) {
            return $fallback;
        }
    }
}
