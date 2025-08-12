<?php

namespace App\Http;

class Request {
    public function getMethod() { return $_SERVER['REQUEST_METHOD']; }
    public function getUri() { return $_SERVER['REQUEST_URI']; }
    public function getBody() { return file_get_contents('php://input'); }
}