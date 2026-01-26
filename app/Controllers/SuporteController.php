<?php
namespace App\Controllers;

use App\Core\Request;

class SuporteController extends Controller {
    public function index(Request $request) {
        $this->view('suporte/index');
    }
}
