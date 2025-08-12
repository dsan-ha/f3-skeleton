<?

namespace App\Http;

class MiddlewareDispatcher {
    private $queue = [];

    public function add(callable $middleware) {
        $this->queue[] = $middleware;
        return $this;
    }

    public function dispatch($f3, callable $finalHandler) {
        $stack = array_reverse($this->queue);
        $next = $finalHandler;

        foreach ($stack as $middleware) {
            $next = function () use ($middleware, $f3, $next) {
                return $middleware($f3, $next);
            };
        }

        return $next($f3);
    }

    public function getQueue(){
        return $this->queue;
    }
}