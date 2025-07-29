<?php

namespace App\Service;

use App\F3;
use App\Base\Prefab;
use DB\SQL;
use ReflectionClass;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RegexIterator;

class DataManagerRegistry extends Prefab
{
    protected array $managers = [];
    protected SQL $db;
    protected F3 $f3;

    public function __construct()
    {
        $f3 = F3::instance();
        $this->f3 = $f3;
        $this->db = $f3->get('DB');
    }

    /**
     * Получить экземпляр DataManager-а
     * @template T of DataManager
     * @param class-string<T> $className
     * @return T
     */
    public static function get(string $className): DataManager
    {
        $dm = DataManagerRegistry::instance();
        if (!isset($dm->managers[$className])) {
            $dm->managers[$className] = new $className($dm->db, $dm->f3);
            if(empty($dm->managers[$className]))
                throw new \RuntimeException("DataManager '" . $className . "' не зарегистрирован. Проверь наличие файла в App/Service/Data.");
        }

        return $dm->managers[$className];
    }

    /**
     * Получить все зарегистрированные DataManager-ы
     * @return array<string, DataManager>
     */
    public static function all(): array
    {
        $dm = DataManagerRegistry::instance();
        return $dm->managers;
    }
}
