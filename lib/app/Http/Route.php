<?php

namespace App\Http;

class Route{
    private $params;
    public $middleware;

    public function __construct($params) {
        $this->params = $params;
        $this->middleware = new MiddlewareDispatcher();
    }

    public function addMiddleware(callable $mw) {
        $this->middleware->add($mw);
        return $this;
    }

    public function getParams() {
        return $this->params;
    }
}