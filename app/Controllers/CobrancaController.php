<?php
namespace App\Controllers;

use App\Core\Request;
use App\Models\Pedido;
use App\Models\Cliente;
use App\Models\Produto;

class CobrancaController extends Controller {
    private $pedidoModel;
    private $clienteModel;
    private $produtoModel;

    public function __construct() {
        $this->pedidoModel = new Pedido();
        $this->clienteModel = new Cliente();
        $this->produtoModel = new Produto();
    }

    public function index(Request $request) {
        session_start();
        
        if (!isset($_SESSION['carrinho']) || empty($_SESSION['carrinho'])) {
            $this->redirect('/produtos');
        }
        
        $this->view('cobranca/index', [
            'carrinho' => $_SESSION['carrinho'],
            'subtotal' => array_sum(array_column($_SESSION['carrinho'], 'subtotal'))
        ]);
    }

    public function calcular(Request $request) {
        session_start();
        
        if (!isset($_SESSION['carrinho']) || empty($_SESSION['carrinho'])) {
            $this->json(['error' => 'Carrinho vazio'], 400);
        }
        
        $servicosSelecionados = $request->getParam('servicos', []);
        $clienteData = $request->getParams();
        
        $subtotal = array_sum(array_column($_SESSION['carrinho'], 'subtotal'));
        
        $servicosDisponiveis = [
            'despacho' => 150.00,
            'translado' => 350.00,
            'armazenamento' => 50.00,
            'envio' => 25.00
        ];
        
        $totalServicos = 0;
        foreach ($servicosSelecionados as $servico) {
            if (isset($servicosDisponiveis[$servico])) {
                $totalServicos += $servicosDisponiveis[$servico];
            }
        }
        
        $impostosPercentual = 60.00 + 17.00 + 0.65 + 3.00;
        $totalImpostos = ($subtotal + $totalServicos) * ($impostosPercentual / 100);
        
        $total = $subtotal + $totalServicos + $totalImpostos;
        
        $_SESSION['calculo'] = [
            'subtotal' => $subtotal,
            'servicos' => $totalServicos,
            'impostos' => $totalImpostos,
            'total' => $total,
            'servicos_selecionados' => $servicosSelecionados,
            'cliente_data' => $clienteData
        ];
        
        $this->json([
            'success' => true,
            'subtotal' => number_format($subtotal, 2, ',', '.'),
            'servicos' => number_format($totalServicos, 2, ',', '.'),
            'impostos' => number_format($totalImpostos, 2, ',', '.'),
            'total' => number_format($total, 2, ',', '.'),
            'impostos_percentual' => $impostosPercentual
        ]);
    }
}
