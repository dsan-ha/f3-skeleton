<?

namespace App\Http;

class MiddlewareDispatcher {
    private $queue = [];

    public function add(callable $middleware) {
        $this->queue[] = $middleware;
        return $this;
    }

    public function dispatch($req, $res, array $params, callable $finalHandler) {
        $stack = array_reverse($this->queue);
        $next = $finalHandler;

        foreach ($stack as $middleware) {
            $next = function ($reqx, $resx, $paramsx) use ($middleware, $next) {
                return $middleware($reqx, $resx, $paramsx, $next);
            };
        }

        return $next($req, $res, $params);
    }

    public function getQueue(){
        return $this->queue;
    }
}