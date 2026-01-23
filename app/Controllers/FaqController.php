<?php
namespace App\Controllers;

use App\Core\Request;

class FaqController extends Controller {
    public function index(Request $request) {
        $this->view('faq/index');
    }
}
