<?php
namespace App\Controllers;

use App\Core\Request;

class ContatoController extends Controller {
    public function index(Request $request) {
        $this->view('contato/index');
    }
}
