<?php
namespace App\Controllers;

use App\Core\Request;

class TermosUsoController extends Controller {
    public function index(Request $request) {
        $this->view('termos-uso/index');
    }
}
