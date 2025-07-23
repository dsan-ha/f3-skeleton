<?php
namespace App\Service;

use InvalidArgumentException;
use App\Base\Prefab;

/**
 * Класс-"щит" для защиты DataManager от SQL-инъекций и других угроз.
 * Используется как вспомогательный компонент в методах DataManager.
 */
class DataManagerProtector extends Prefab
{
    /**
     * Разрешённые символы для алиасов, имён полей и таблиц
     */
    protected string $safePattern = '/^[\*a-zA-Z0-9_\.]+$/';

    /**
     * Проверка, что строка не содержит SQL-инъекций.
     * Используется для проверки алиасов, имён таблиц и полей.
     */
    public function assertSafeIdentifier(string $identifier, string $context = 'identifier'): void
    {
        if (!preg_match($this->safePattern, $identifier)) {
            throw new InvalidArgumentException("Invalid $context: $identifier");
        }
    }

    /**
     * Проверка массива имён полей (например, select или order by)
     */
    public function assertSafeIdentifiers(array $fields, string $context = 'field list'): void
    {
        foreach ($fields as $field) {
            // Возможен alias через "field AS alias"
            $parts = preg_split('/\s+AS\s+/i', $field);
            foreach ($parts as $part) {
                $this->assertSafeIdentifier(trim($part), $context);
            }
        }
    }

    /**
     * Проверка JOIN-таблиц и ON-условий
     */
    public function assertSafeJoins(array $joins): void
    {
        foreach ($joins as $join) {
            if (!empty($join['table'])) {
                $this->assertSafeIdentifier($join['table'], 'join table');
            }
            if (!empty($join['on']) && preg_match('/[^a-zA-Z0-9_\.=\s]/', $join['on'])) {
                throw new InvalidArgumentException("Suspicious JOIN ON clause: {$join['on']}");
            }
        }
    }

    /**
     * Проверка ORDER BY массива
     */
    public function assertSafeOrder(array $order): void
    {
        foreach ($order as $field => $direction) {
            $this->assertSafeIdentifier($field, 'order field');
            $dir = strtoupper($direction);
            if (!in_array($dir, ['ASC', 'DESC'])) {
                throw new InvalidArgumentException("Invalid order direction: $dir");
            }
        }
    }

    /**
     * Проверка GROUP BY массива
     */
    public function assertSafeGroup(array $group): void
    {
        foreach ($group as $field) {
            $this->assertSafeIdentifier($field, 'group field');
        }
    }

    /**
     * Безопасный режим для getRaw: блокирует выполнение DML-запросов
     */
    public function assertReadOnlyQuery(string $sql): void
    {
        $firstWord = strtoupper(strtok(trim($sql), " "));
        if (!in_array($firstWord, ['SELECT', 'WITH'])) {
            throw new InvalidArgumentException("Only read-only queries are allowed in getRaw(): $firstWord detected");
        }
    }
}
