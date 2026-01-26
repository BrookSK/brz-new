<?php
namespace App\Controllers;

use App\Core\Request;

class PoliticaPrivacidadeController extends Controller {
    public function index(Request $request) {
        $this->view('politica-privacidade/index');
    }
}
