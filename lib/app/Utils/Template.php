<?php

namespace App\Utils;

use App\F4;
use App\Utils\Cache;

/**
 * Class Template
 * Расширение F4 View с поддержкой параметров по аналогии с Bitrix.
 * Определение директорий шаблонов происходит через Hive-переменную UI, как в самой F4.
 */
class Template
{
    /** @var array Глобальные параметры шаблона */
    protected array $params = [];
    protected F4 $f3;
    protected Cache $cache;
    protected array $trigger = [];
    protected int $level = 0;
    protected array $stack = [];
    protected string $uiPaths = '';

    /**
     * Конструктор.
     * @param string|string[]|null $uiPaths Путь или массив путей к папке(ам) шаблонов. Как в F4, разделитель — запятая.
     */
    public function __construct(F4 $f3, Cache $cache, string|array $uiPaths = null)
    {
        $this->f3 = $f3;
        $this->cache = $cache;
        if ($uiPaths !== null) {
            // Приводим к массиву
            $paths = is_array($uiPaths) ? $uiPaths : [$uiPaths];
            $normalized = array_map(fn($p) => rtrim($p, '/\\') . '/', $paths);
            $uiPaths = implode(',', $normalized);
        }
        if(empty($uiPaths)) throw new \Exception("Not found path UI.");
        $this->uiPaths = $uiPaths;
    }

    /**
     * Устанавливает одиночный параметр (как Bitrix $arParams['KEY'] = VALUE)
     */
    public function set(string $key, mixed $value): self
    {
        $this->params[$key] = $value;
        return $this;
    }

    public function addParams(array $arParams): self
    {
        $this->params = array_merge($this->params, $arParams);
        return $this;
    }

    /**
     * Рендерит шаблон, передавая все параметры во View через $hive.
     * @param string $template Имя файла шаблона (относительно UI путей)
     * @param array  $arParams Локальные параметры для данного рендера
     * @return string
     */
    public function render(string $template, array $arParams = [], $cache_time=0, $mime='text/html'): string
    {
        $params = $this->params;
        $params['arParams'] = $arParams;
        $fw = $this->f3;
        $cache = $this->cache;
        $file = $this->resolveTemplatePath($template);

        $hash = $fw->hash($file);
        if ($cache->exists($hash, 'templates', $data))
            return $data;

        if (is_file($fw->fixslashes($file))) {
            if (isset($_COOKIE[session_name()]) &&
                !headers_sent() && session_status() != PHP_SESSION_ACTIVE)
                session_start();
            $fw->sync('SESSION');
            $data = $this->sandbox($file, $mime, $params);

            if (!empty($this->trigger['afterrender']['all'])){ // для всех шаблонов
                foreach($this->trigger['afterrender']['all'] as $func)
                    $data = $fw->call($func, [$data, $file]);
            }
            if (!empty($this->trigger['afterrender'][$template])){ // для текущего шаблона
                foreach($this->trigger['afterrender'][$template] as $func)
                    $data = $fw->call($func, [$data, $file]);
            }

            if ($cache_time)
                $cache->set($hash, 'templates', $data, $cache_time);

            return $data;
        }

        user_error(sprintf(\Base::E_Open, $file), E_USER_ERROR);
    }

    /**
     * Выводит шаблон непосредственно
     */
    public function output(string $template, array $arParams = [], $cache_time=0, $mime='text/html'): void
    {
        echo $this->render($template, $arParams, $cache_time, $mime);
    }

    /**
     * Алиас для вставки частичного шаблона
     */
    public function partial(string $template, array $arParams = []): void
    {
        $this->output($template, $arParams);
    }

    /**
     * Находит файл шаблона на основе UI путей.
     * @throws \RuntimeException если файл не найден.
     */
    protected function resolveTemplatePath(string $template): string
    {
        $hive = $this->uiPaths;
        $roots = $hive ? explode(',', $hive) : [];

        foreach ($roots as $root) {
            $file = SITE_ROOT . '/' . trim($root, '/\\') . '/' . ltrim($template, '/\\');
            if (is_file($file)) {
                return $file;
            } else {
                echo $file;
            }
        }

        throw new \RuntimeException("Template not found: {$template}");
    }

     /**
     * Включает файл в изолированном пространстве и возвращает буфер.
     * Обрезает путь через realpath для безопасности.
     */
    protected function sandbox(string $file, $mime = null, array $params = [])
    {
        $fw = $this->f3;
        $real = realpath($file);
        $paths = explode(',',$this->uiPaths);
        $uri_file = substr($file, strlen(SITE_ROOT));
        $find = false;
        if($real !== false){
            foreach ($paths as $key => $path) {
                $p = SITE_ROOT . '/' . ltrim($path, '/\\');
                if(str_starts_with($file, $p)){
                    $find = true;
                } 
            }
        }
        
        if (!$find) {
            throw new \RuntimeException("Invalid template path: {$uri_file}");
        }

        // Проверка на рекурсию
        if (in_array($real, $this->stack)) {
            throw new \RuntimeException("Recursive template inclusion detected: {$uri_file}");
        }
        $this->stack[] = $real;

        if ($this->level < 1) {
            if (!$fw->get('CLI') && $mime && !headers_sent() &&
                !preg_grep('/^Content-Type:/', headers_list()))
                header('Content-Type: '.$mime.'; charset='.$fw->get('ENCODING'));
        }

        extract($params, EXTR_SKIP);

        ++$this->level;
        ob_start();
        try {
            require($real);
        } finally {
            --$this->level;
            array_pop($this->stack);
        }
        return ob_get_clean();
    }

    /**
    *   post rendering handler
    *   @param $func callback
    */
    public function afterrender($func, string $template = 'all') {
        if(empty($template)) return false;
        $this->trigger['afterrender'][$template][]=$func;
    }
}