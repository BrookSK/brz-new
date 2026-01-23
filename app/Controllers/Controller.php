<?php
namespace App\Controllers;

abstract class Controller {
    protected function view($view, $data = []) {
        extract($data);
        $viewPath = "../app/Views/{$view}.php";
        
        if (file_exists($viewPath)) {
            require $viewPath;
        } else {
            echo "View não encontrada: {$view}";
        }
    }

    protected function json($data, $statusCode = 200) {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit;
    }

    protected function redirect($url) {
        header("Location: {$url}");
        exit;
    }
}
