<?php
namespace App\Base;

use App\F3;
use Symfony\Component\Yaml\Yaml;           // YAML-парсер из composer
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;


class DataLoader
{
    private static array  $order = [
        'constants.php',
        'dependencies.php',
        'helpers.php',
        'middleware.php',
        'routes.php'
    ];

    private static function full(string $relPath): string
    {
        return SITE_ROOT.$relPath;
    }

    public static function loadOrdered(array $exclude = []): void
    {
        if(!defined('SITE_ROOT')) throw new \Exception("Main path Defines don't define");
        foreach (['lib/data', 'local/data'] as $relPath) {
            foreach (self::$order as $file) {
                $path = self::full("$relPath/$file");
                if (is_file($path) && !in_array($file, $exclude)) require_once $path;
            }
        }
    }
}
