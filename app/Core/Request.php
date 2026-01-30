<?php
namespace App\Core;

class Request {
    private $params;
    private $method;
    private $path;

    public function __construct() {
        $this->method = $_SERVER['REQUEST_METHOD'];
        $this->path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $this->params = array_merge($_GET, $_POST);
    }

    public function getMethod() {
        return $this->method;
    }

    public function getPath() {
        return $this->path;
    }

    public function getParams() {
        return $this->params;
    }

    public function getParam($key, $default = null) {
        return $this->params[$key] ?? $default;
    }
    
    public function setParam($key, $value) {
        $this->params[$key] = $value;
    }
    
    public function getBody() {
        if ($this->method === 'POST') {
            // Para POST, verificar se tem JSON
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            if (strpos($contentType, 'application/json') !== false) {
                $json = file_get_contents('php://input');
                return json_decode($json, true) ?: [];
            }
            return $_POST;
        }
        
        // Para outros métodos, tentar ler o input
        $json = file_get_contents('php://input');
        if ($json) {
            $decoded = json_decode($json, true);
            if ($decoded !== null) {
                return $decoded;
            }
        }
        
        return [];
    }
}
