<?php
namespace App\Controllers;

use App\Core\Request;

class StatusPedidosController extends Controller {
    public function index(Request $request) {
        $this->view('status-pedidos/index');
    }
}
