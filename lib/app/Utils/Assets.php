<?php

namespace App\Utils;

use App\Base\Prefab;

class Assets extends Prefab
{
    private $cssFiles = [];
    private $jsFiles = [];
    protected const SHOW_ERROR = true;

    // Предотвращаем клонирование
    private function __clone()
    {
    }

    // Предотвращаем десериализацию
    public function __wakeup()
    {
        throw new \Exception("Cannot unserialize singleton");
    }

    /**
     * Добавляет CSS файл в коллекцию
     * @param string $path - путь к файлу
     * @param int $priority - приоритет (чем меньше, тем раньше будет в объединенном файле)
     */
    public function addCss(string $path, array $params = []): void
    {
        $def = [
            'priority' => 10
        ];
        $params = array_merge($def, $params);
        $s = substr($path, 0, 1);
        $f_path = SITE_ROOT . $path;
        if ($s == '/' || $s == '.') {
            if (self::SHOW_ERROR && !is_file($f_path)) {
                throw new \Exception("Error not found file css " . $path, 1);
            }
            $params['data_update'] = filemtime($f_path);
        }
        $this->cssFiles[$path] = $params;
    }

    /**
     * Добавляет JS файл в коллекцию
     * @param string $path - путь к файлу
     * @param int $priority - приоритет (чем меньше, тем раньше будет в объединенном файле)
     * @param bool $inFooter - подключать в подвале страницы
     */
    public function addJs(string $path, array $params = []): void
    {
        $def = [
            'priority' => 10,
            'inFooter' => false
        ];
        $params = array_merge($def, $params);
        $s = substr($path, 0, 1);
        $f_path = SITE_ROOT . $path;
        if (($s == '/' || $s == '.')) {
            if (self::SHOW_ERROR && !is_file($f_path)) {
                throw new \Exception("Error not found file js " . $path, 1);
            }
            $params['data_update'] = filemtime($f_path);
        }

        $this->jsFiles[$path] = $params;
    }

    /**
     * Объединяет и минифицирует CSS файлы
     * @return string - минифицированный CSS код
     */
    public function processCss(): string
    {
        if (empty($this->cssFiles)) {
            return '';
        }

        // Сортируем файлы по приоритету
        asort($this->cssFiles);

        $combinedCss = '';

        foreach (array_keys($this->cssFiles) as $file) {
            if (file_exists($file)) {
                $content = file_get_contents($file);
                $combinedCss .= $this->minifyCss($content);
            }
        }

        return $combinedCss;
    }

    /**
     * Объединяет и минифицирует JS файлы
     * @param bool $inFooter - обрабатывать файлы для подвала
     * @return string - минифицированный JS код
     */
    public function processJs(bool $inFooter = false): string
    {
        $filteredFiles = array_filter($this->jsFiles, function ($item) use ($inFooter) {
            return $item['in_footer'] === $inFooter;
        });

        if (empty($filteredFiles)) {
            return '';
        }

        // Сортируем файлы по приоритету
        uasort($filteredFiles, function ($a, $b) {
            return $a['priority'] <=> $b['priority'];
        });

        $combinedJs = '';

        foreach (array_keys($filteredFiles) as $file) {
            if (file_exists($file)) {
                $content = file_get_contents($file);
                $combinedJs .= $this->minifyJs($content);
            }
        }

        return $combinedJs;
    }

    /**
     * Минифицирует CSS код
     * @param string $css - исходный CSS
     * @return string - минифицированный CSS
     */
    private function minifyCss(string $css): string
    {
        // Удаляем комментарии
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);

        // Удаляем пробелы, табы, переносы строк
        $css = str_replace(["\r\n", "\r", "\n", "\t", '  ', '    ', '    '], '', $css);

        // Удаляем ненужные пробелы
        $css = preg_replace('/\s*([{}|:;,])\s*/', '$1', $css);
        $css = preg_replace('/\s\s+(.*)/', '$1', $css);

        return trim($css);
    }

    /**
     * Минифицирует JS код (базовая реализация)
     * @param string $js - исходный JS
     * @return string - минифицированный JS
     */
    private function minifyJs(string $js): string
    {
        // Удаляем однострочные комментарии
        $js = preg_replace('/(\/\/.*$)/m', '', $js);

        // Удаляем многострочные комментарии
        $js = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $js);

        // Удаляем лишние пробелы и переносы строк
        $js = preg_replace('/\s+/', ' ', $js);

        return trim($js);
    }

    /**
     * Сохраняет объединенный CSS в файл
     * @param string $outputPath - путь для сохранения
     * @return bool - успех операции
     */
    public function saveCombinedCss(string $outputPath): bool
    {
        $css = $this->processCss();
        return file_put_contents($outputPath, $css) !== false;
    }

    /**
     * Сохраняет объединенный JS в файл
     * @param string $outputPath - путь для сохранения
     * @param bool $inFooter - для подвала страницы
     * @return bool - успех операции
     */
    public function saveCombinedJs(string $outputPath, bool $inFooter = false): bool
    {
        $js = $this->processJs($inFooter);
        return file_put_contents($outputPath, $js) !== false;
    }

    public function renderCss()
    {
        if (empty($this->cssFiles)) {
            return '';
        }

        // Сортируем файлы по приоритету
        uasort($this->cssFiles, function ($a, $b) {
            return $a['priority'] <=> $b['priority'];
        });
        $html = '';
        foreach ($this->cssFiles as $path => $p) {

            $url = $path;
            if (!empty($p['data_update'])) {
                $url .= '?v=' . $p['data_update'];
            }
            $html .= '<link rel="stylesheet" href="'.$url.'" type="text/css" />';

        }
        return $html;
    }

    public function renderJs()
    {
        if (empty($this->jsFiles)) {
            return '';
        }

        // Сортируем файлы по приоритету
        uasort($this->jsFiles, function ($a, $b) {
            return $a['priority'] <=> $b['priority'];
        });

        $combinedCss = '';
        $html = '';

        foreach ($this->jsFiles as $path => $p) {
            $url = $path;
            if (!empty($p['data_update'])) {
                $url .= '?v=' . $p['data_update'];
            }
            $html .= '<script src="' . $url . '"></script>';

        }
        return $html;
    }
}
