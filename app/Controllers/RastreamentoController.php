<?php
namespace App\Controllers;

use App\Core\Request;
use App\Models\Pedido;

class RastreamentoController extends Controller {
    private $pedidoModel;

    public function __construct() {
        $this->pedidoModel = new Pedido();
    }

    public function index(Request $request) {
        $pedidoId = $request->getParam('id');
        
        if (!$pedidoId) {
            $this->view('rastreamento/search');
            return;
        }
        
        $pedido = $this->pedidoModel->getComItems($pedidoId);
        
        if (!$pedido) {
            $this->view('rastreamento/not-found');
            return;
        }
        
        $rastreamento = $this->pedidoModel->getRastreamento($pedidoId);
        
        $this->view('rastreamento/index', [
            'pedido' => $pedido,
            'rastreamento' => $rastreamento
        ]);
    }
}
