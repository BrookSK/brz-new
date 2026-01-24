<?php
namespace App\Controllers;

use App\Core\Request;

class AdminController extends Controller {
    
    public function dashboard(Request $request) {
        echo '<h1>Dashboard do Controller</h1><p>Controller funcionando!</p>';
        exit;
    }
    
    public function produtos(Request $request) {
        echo '<h1>Produtos do Controller</h1><p>Controller funcionando!</p>';
        exit;
    }
}