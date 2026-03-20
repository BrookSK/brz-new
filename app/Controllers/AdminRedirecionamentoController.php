<?php
namespace App\Controllers;

use App\Core\Request;
use App\Services\AuthService;

/**
 * Módulo "Redirecionamento" (estrutura inicial: rotas + controllers + views).
 * Implementações operacionais (DB/Stripe/etiquetas) virão em commits posteriores.
 */
class AdminRedirecionamentoController extends Controller {

    private function requireAcesso(): void {
        $auth = new AuthService();
        $auth->requerPerfis(['admin', 'suporte', 'redirecionador']);
    }

    public function dashboard(Request $request) {
        $this->requireAcesso();

        $kpis = [
            'total_envios' => 0,
            'pendentes_pagamento' => 0,
            'aguardando_coleta' => 0,
            'divergencias_peso' => 0,
            'valores_a_receber' => 0.0,
            'valores_a_devolver' => 0.0,
        ];

        $this->view('admin/redirecionamento/dashboard', [
            'kpis' => $kpis,
        ]);
    }

    public function redirecionadores(Request $request) {
        $this->requireAcesso();

        // Placeholder (CRUD ainda não implementado).
        $redirecionadores = [];
        $this->view('admin/redirecionamento/redirecionadores', [
            'redirecionadores' => $redirecionadores,
        ]);
    }

    public function envios(Request $request) {
        $this->requireAcesso();

        $envios = [];
        $this->view('admin/redirecionamento/envios', [
            'envios' => $envios,
        ]);
    }

    public function divergencias(Request $request) {
        $this->requireAcesso();

        $divergencias = [];
        $this->view('admin/redirecionamento/divergencias', [
            'divergencias' => $divergencias,
        ]);
    }

    public function clientes(Request $request) {
        $this->requireAcesso();

        $clientes = [];
        $this->view('admin/redirecionamento/clientes', [
            'clientes' => $clientes,
        ]);
    }

    public function tabelaPesos(Request $request) {
        $this->requireAcesso();

        // Placeholder: a tabela real vai ser carregada do banco/configurações.
        $tabela = [
            ['peso_min_kg' => 0.5, 'peso_max_kg' => 0.999, 'valor_usd' => 10.76],
            ['peso_min_kg' => 1.0, 'peso_max_kg' => 1.999, 'valor_usd' => 15.33],
        ];

        $this->view('admin/redirecionamento/tabela-pesos', [
            'tabela' => $tabela,
        ]);
    }

    public function pagamentos(Request $request) {
        $this->requireAcesso();

        $pagamentos = [];
        $this->view('admin/redirecionamento/pagamentos', [
            'pagamentos' => $pagamentos,
        ]);
    }

    public function comprovantes(Request $request) {
        $this->requireAcesso();

        $comprovantes = [];
        $this->view('admin/redirecionamento/comprovantes', [
            'comprovantes' => $comprovantes,
        ]);
    }

    public function coletas(Request $request) {
        $this->requireAcesso();

        $coletas = [];
        $this->view('admin/redirecionamento/coletas', [
            'coletas' => $coletas,
        ]);
    }
}

