<?php

namespace App\Http;

class Response {
    private $body = '';
    private $status = 200;
    private $headers = [];

    public function write($data) { $this->body .= $data; return $this; }
    public function withStatus($code) { $this->status = $code; return $this; }
    public function withHeader($key, $value) { 
        $this->headers[$key] = $value; 
        return $this; 
    }

    public function send() {
        http_response_code($this->status);
        foreach ($this->headers as $k => $v) header("$k: $v");
        echo $this->body;
    }
}