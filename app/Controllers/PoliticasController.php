<?php
namespace App\Controllers;

use App\Core\Request;

class PoliticasController extends Controller {
    public function index(Request $request) {
        $this->view('politicas/index');
    }
}
