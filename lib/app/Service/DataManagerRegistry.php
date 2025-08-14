<?php

namespace App\Service;

use App\F4;
use App\Base\DB\SQL;
use ReflectionClass;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

class DataManagerRegistry
{
    protected array $managers = [];
    protected SQL $db;
    protected F4 $f3;

    public function __construct(SQL $db, F4 $f3)
    {
        $this->db = $db;
        $this->f3 = $f3;
    }

    /**
     * Получить экземпляр DataManager-а
     * @template T of DataManager
     * @param class-string<T> $className
     * @return T
     */
    public function get(string $className): DataManager
    {
        if (!isset($this->managers[$className])) {
            $this->managers[$className] = new $className($this->db, $this->f3);
        }

        return $this->managers[$className];
    }

    /**
     * Получить все зарегистрированные DataManager-ы
     * @return array<string, DataManager>
     */
    public function all(): array
    {
        return $dm->managers;
    }
}
