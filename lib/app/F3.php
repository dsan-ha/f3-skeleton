<?php

namespace App;

use App\Base\Prefab;

class F3
{
    /**
     * @var Base|null
     */
    protected static $fw = null;

    /**
     * @var F3|null — синглтон-обёртка
     */
    protected static $instance = null;

    /**
     * @var array — Лог ошибок middleware
     */
    protected static $middlewareErrors = [];

    /**
     * Возвращает ядро F3
     * @return Base
     */
    protected static function fw()
    {
        if (!self::$fw) {
            self::$fw = \Base::instance();
        }
        return self::$fw;
    }

    /**
     * Статический вызов
     */
    public static function __callStatic($method, $args)
    {
        if (method_exists(__CLASS__, $method)) {
            return forward_static_call_array([__CLASS__, $method], $args);
        }

        $fw = self::fw();

        if (method_exists($fw, $method)) {
            return call_user_func_array([$fw, $method], $args);
        }

        throw new \BadMethodCallException("Static method $method not found.");
    }

    /**
     * Возвращает инстанс F3-обёртки (синглтон)
     * @return F3
     */
    public static function instance()
    {
        if (!self::$instance) {
            $class = static::class;
            self::$instance = \App\Base\ServiceLocator::get($class);
            self::init_f3();
        }
        return self::$instance;
    }

    /**
     * Инит обёртки
     * @return F3
     */
    public static function init_f3()
    {
        self::fw()->set('MIDDLEWARE', []);
        self::$middlewareErrors = [];
    }

    /**
     * Нестатический вызов
     */
    public function __call($method, $args)
    {
        if (method_exists($this, $method)) {
            return call_user_func_array([$this, $method], $args);
        }

        $fw = self::fw();

        if (method_exists($fw, $method)) {
            return call_user_func_array([$fw, $method], $args);
        }

        throw new \BadMethodCallException("Method $method not found in instance.");
    }

    /**
     * Добавляет middleware
     * @param callable $handler
     * @return void
     */
    public function addMiddleware(callable $handler)
    {
        $fw = self::fw();
        $middleware = $fw->get('MIDDLEWARE');
        $middleware[] = $handler;
        $fw->set('MIDDLEWARE', $middleware);
    }

    /**
     * Выполняет все зарегистрированные middleware
     * @return bool Возвращает false если какой-то middleware прервал выполнение
     */
    public function middleware()
    {
        $fw = self::fw();
        $middleware = $fw->get('MIDDLEWARE');

        foreach ($middleware as $index => $handler) {
            try {
                $result = call_user_func($handler, $fw);
                if ($result === false) {
                    self::$middlewareErrors[] = "Middleware #$index прервал выполнение";
                    return false;
                }
            } catch (\Throwable $e) {
                self::$middlewareErrors[] = sprintf(
                    "Middleware #%d ошибка: %s (%s:%d)",
                    $index,
                    $e->getMessage(),
                    $e->getFile(),
                    $e->getLine()
                );
                return false;
            }
        }
        return true;
    }

    /**
    *   Retrieve contents of hive key
    *   @return void
    **/
    public function middlewareError()
    {
        $fw = self::fw();
        $textError = 'Middleware Error';
        if ($fw->get('DEBUG') >= 1) {
            $textError = implode("<br>", self::$middlewareErrors);
        }
        // Можно выполнить редирект или показать страницу ошибки
        $fw->error(500, $textError);
    }

    /**
    *   Добавить событие в планировщик
    *   @return void
    **/
    public function schedule(callable $callback, string $expression = '* * * * *'): void
    {
        $scheduler = $this->g('Scheduler',new TaskScheduler());
        $scheduler->add($callback, $expression);
    }

    /**
    *   Обработка всех событий планировщика
    *   @return void
    **/
    public function runScheduledTasks(): void
    {
        $scheduler = $this->get('Scheduler');
        if (!empty($scheduler)) {
            $scheduler->run();
        }
    }

    /**
    *   Retrieve contents of hive key
    *   @return mixed
    *   @param $key string
    *   @param $args string|array
    **/
    public function g($key, $def = null)
    {
        $fw = self::fw();
        $val = $fw->ref($key, false);
        if (is_null($val)) {
            if (!is_null($def)) {
                return $def;
            } elseif (Cache::instance()->exists($this->hash($key).'.var', $data)) {
                return $data;
            }
        }
        return $val;
    }
}
