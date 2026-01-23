<?php
namespace App\Controllers;

use App\Core\Request;

class HomeController extends Controller {
    public function index(Request $request) {
        $this->view('home/index', [
            'title' => 'Sistema de Logística Internacional',
            'description' => 'Gerencie suas importações com facilidade'
        ]);
    }
}
