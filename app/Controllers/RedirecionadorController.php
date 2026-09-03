<?php
namespace App\Controllers;

use App\Core\Request;

/**
 * Página pública que explica o serviço de redirecionamento de pacotes
 * para quem tem interesse em se tornar um redirecionador parceiro.
 */
class RedirecionadorController extends Controller {
    public function index(Request $request) {
        $tabelaPesos = $this->getTabelaPesos();
        $this->view('redirecionador/index', ['tabelaPesos' => $tabelaPesos]);
    }

    /** Tabela de pesos e preços do redirecionamento (pública). */
    private function getTabelaPesos(): array
    {
        try {
            $pdo = \Config\Database::getConnection();
            $st = $pdo->query("SELECT peso_ate_kg, valor_usd FROM redirecionamento_tabela_pesos ORDER BY peso_ate_kg ASC");
            return $st ? ($st->fetchAll(\PDO::FETCH_ASSOC) ?: []) : [];
        } catch (\Exception $e) {
            return [];
        }
    }
}
