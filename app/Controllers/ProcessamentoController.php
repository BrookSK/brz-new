<?php
namespace App\Controllers;

use App\Core\Request;
use App\Models\Pedido;
use App\Models\Cliente;

class ProcessamentoController extends Controller {
    private $pedidoModel;
    private $clienteModel;

    public function __construct() {
        $this->pedidoModel = new Pedido();
        $this->clienteModel = new Cliente();
    }

    public function processar(Request $request) {
        session_start();
        
        if (!isset($_SESSION['carrinho']) || empty($_SESSION['carrinho'])) {
            $this->json(['error' => 'Carrinho vazio'], 400);
        }
        
        if (!isset($_SESSION['calculo'])) {
            $this->json(['error' => 'Cálculo não realizado'], 400);
        }
        
        $calculo = $_SESSION['calculo'];
        $carrinho = $_SESSION['carrinho'];
        
        try {
            $clienteId = $this->clienteModel->createIfNotExists($calculo['cliente_data']);
            
            $pedidoId = $this->pedidoModel->createWithItems(
                $clienteId,
                $carrinho,
                $calculo['servicos_selecionados'],
                80.65
            );
            
            $this->pedidoModel->addRastreamento($pedidoId, 'Pedido Criado', 'Pedido registrado com sucesso');
            
            unset($_SESSION['carrinho']);
            unset($_SESSION['calculo']);
            
            $this->json([
                'success' => true,
                'message' => 'Pedido processado com sucesso',
                'pedido_id' => $pedidoId,
                'redirect' => "/rastreamento?id={$pedidoId}"
            ]);
            
        } catch (\Exception $e) {
            $this->json(['error' => 'Erro ao processar pedido: ' . $e->getMessage()], 500);
        }
    }
}
