<?php

namespace App;

use App\Utils\Scheduler;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;


class F4
{
    /**
     * @var Base|null
     */
    protected static $fw = null;

    /**
     * @var F4|null — синглтон-обёртка
     */
    protected static $instance = null;

    /**
     * @var array — Лог ошибок middleware
     */
    protected static $middlewareErrors = [];

    /**
     * Статический вызов
     */
    public static function __callStatic($method, $args)
    {
        if (method_exists(__CLASS__, $method)) {
            return forward_static_call_array([__CLASS__, $method], $args);
        }

        $fw = self::$fw;

        if (method_exists($fw, $method)) {
            return call_user_func_array([$fw, $method], $args);
        }

        throw new \BadMethodCallException("Static method $method not found.");
    }

    /**
     * Возвращает инстанс F4-обёртки (синглтон)
     * @return F4
     */
    public static function instance()
    {
        if (self::$instance === null) {
            $f3 = new self();
            self::$instance = $f3;
            $f3::init_f3();
        }
        return self::$instance;
    }

    /**
     * Инит обёртки
     * @return F4
     */
    public static function init_f3()
    {
        self::$fw = \Base::instance();
        self::$fw->set('MIDDLEWARE', []);
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

        $fw = self::$fw;

        if (method_exists($fw, $method)) {
            return call_user_func_array([$fw, $method], $args);
        }

        throw new \BadMethodCallException("Method $method not found in instance.");
    }

    /**
     * 
     */
    public function run()
    {
        $fw = self::$fw;
        if ($this->middleware()) {
            $fw->run();
        } else {
            $this->middlewareError();
        }
    }

    /**
     * Load config from YAML or delegate to Base::config()
     */
    public static function config(string $file): void
    {
        $fw = self::$fw;
        
        if (!file_exists($file)) {
            $fw->error(500, "Config file not found: $file");
        }

        $ext = pathinfo($file, PATHINFO_EXTENSION);

        switch (strtolower($ext)) {
            case 'yaml':
            case 'yml':
                try {
                    $config = Yaml::parseFile($file);
                    if (!is_array($config)) {
                        throw new \UnexpectedValueException("Invalid YAML config format: $file");
                    }

                    foreach ($config as $key => $val) {
                        // Совместимо с set() и mset()
                        if (is_array($val)) {
                            $fw->mset($val, $key . '.'); // вложенные ключи
                        } else {
                            $fw->set($key, $val);
                        }
                    }
                } catch (\Exception $e) {
                    $fw->error(500, "YAML parse error: " . $e->getMessage());
                }
                break;

            case 'php':
            case 'ini':
                // Делегировать в оригинальный Base::config()
                $fw->config($file);
                break;

            default:
                $fw->error(500, "Unsupported config file extension: $ext");
        }
    }

    /**
     * Добавляет middleware
     * @param callable $handler
     * @return void
     */
    public function addMiddleware(callable $handler)
    {
        $fw = self::$fw;
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
        $fw = self::$fw;
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
        $fw = self::$fw;
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
        $scheduler = $this->g('Scheduler',new Scheduler());
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
        $fw = self::$fw;
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

    /**
    *   json answer
    *   @return void
    **/
    public function json($answer, int $statusCode = 200, array $headers = [], int $jsonOptions = JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');
        if(!empty($headers)){
            foreach ($headers as $h) {
                header($h);
            }
        }
        
        echo json_encode($answer, $jsonOptions);
        exit();
    }
}
