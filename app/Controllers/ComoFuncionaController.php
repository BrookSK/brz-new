<?php
namespace App\Controllers;

use App\Core\Request;

class ComoFuncionaController extends Controller {
    public function index(Request $request) {
        $this->view('como-funciona/index');
    }
}
