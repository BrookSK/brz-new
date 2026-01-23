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
}
