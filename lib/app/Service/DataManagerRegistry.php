<?php

namespace App\Service;

use \App\F3;
use \DB\SQL;
use ReflectionClass;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

class DataManagerRegistry
{
    protected static array $instances = [];
    protected static SQL $db;
    protected static F3 $f3;

    public static function init(SQL $db, F3 $f3): void
    {
        self::$db = $db;
        self::$f3 = $f3;
        self::loadDataManagers();
    }

    /**
     * Автоматически регистрирует все DataManager-классы из App/Service/Data/
     */
    protected static function loadDataManagers(): void
    {
        $baseDir = __DIR__ . '/Data';
        $namespace = 'App\\Service\\Data\\';

        $iterator = new RegexIterator(
            new RecursiveIteratorIterator(new RecursiveDirectoryIterator($baseDir)),
            '/^.+\\.php$/i',
            \RecursiveRegexIterator::GET_MATCH
        );

        foreach ($iterator as $files) {

            foreach ($files as $file) {
        
                $relative = substr(str_replace([$baseDir, '.php'], '', $file), 1);
                $class = $namespace . str_replace('/', '\\', $relative);
                if (!class_exists($class)) {
                    require_once $file;
                }
                if (is_subclass_of($class, DataManager::class)) {
                    self::$instances[$class] = new $class(self::$db, self::$f3);
                }
            }
        }
    }

    /**
     * Получить экземпляр DataManager-а
     * @template T of DataManager
     * @param class-string<T> $className
     * @return T
     */
    public static function get(string $className): DataManager
    {
        if (!isset(self::$instances[$className])) {
            throw new \RuntimeException("DataManager '" . $className . "' не зарегистрирован. Проверь наличие файла в App/Service/Data.");
        }

        return self::$instances[$className];
    }

    /**
     * Получить все зарегистрированные DataManager-ы
     * @return array<string, DataManager>
     */
    public static function all(): array
    {
        return self::$instances;
    }
}
