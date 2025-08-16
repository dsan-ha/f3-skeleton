<?php
namespace App\Service;

use App\F4;
use App\Service\DataManagerProtector;
use App\Service\DB\SQL;
use App\Service\DB\Cursor;
use App\Service\DB\QueryBuilder;
use App\Service\Hydrator\HydratorInterface;

/**
 * Абстрактный класс для работы с таблицами через SQL или Mapper (Fat-Free Framework).
 *
 * Методы:
 * - getList() — построение запроса с фильтрацией, сортировкой, группировкой и join'ами
 * - getRaw() — ручной SQL
 * - add(), update(), delete() — работа с DB\SQL\Mapper
 */
abstract class DataManager {
    protected SQL $db;
    protected F4 $f3;
    protected QueryBuilder $qb;
    protected DataManagerProtector $protector;

    /**
     * @param SQL $db экземпляр базы данных
     * @param F4 $f3 экземпляр фреймворка
     */
    public function __construct(SQL $db, F4 $f3, HydratorInterface $hydrator) {
        $this->db = $db;
        $this->f3 = $f3;
        $this->protector = $f3->getDI(DataManagerProtector::class);
        $fieldsMap = static::getFieldsMap();
        $table     = static::getTableName();
        $this->qb = new QueryBuilder($db, $table, $fieldsMap, pk: null, hydrator: $this->hydrator);
    }

    /**
     * Возвращает имя таблицы (обязательный метод в потомке)
     * @return string
     */
    abstract public static function getTableName(): string;

    /**
     * Возвращает список доступных для записи полей
     * @return string[]
     */
    abstract public static function getFieldsMap(): array;

    protected function validateRequiredFields(array $data): array
    {
        $missing = [];
        foreach (static::getFieldsMap() as $field => $info) {
            $required = $info['required'] ?? (!($info['nullable'] ?? true) && !($info['auto'] ?? false));
            if ($required && !array_key_exists($field, $data)) {
                $missing[] = $field;
            }
        }
        return $missing;
    }

    protected function validate(array $data): void
    {
        $missing = $this->validateRequiredFields($data);
        if (!empty($missing)) {
            throw new \InvalidArgumentException("Missing required fields: " . implode(', ', $missing));
        }

        // Здесь можно добавить дополнительные проверки в будущем
    }


    /**
     * Получить запись по ID
     * @param int $id
     * @return array|null
     */
    public function getById(int $id): object|array|null {
        $cursor = $this->getList(['id = ?' => $id], ['limit' => 1]);
        return $cursor->first(); // DTO или массив — зависит от getDtoClass()
    }

    /**
     * Основной метод выборки данных
     * @param array $options: ['where'=>['age >' => 18]'joins'=>[], 'group'=>'','select'=>[], 'having'=>'', 'order'=>'col DESC', 'limit'=>N, 'offset'=>M]
     * @return array[]

     */
    public function getList(
        array $options = []
    ): Cursor {
        $params = [];

        $sql  = $this->buildSelect($options);
        if(!empty($options['joins']))
            $sql .= $this->buildJoins($options);
        if(!empty($options['where']))
            $sql .= $this->buildWhere($options['where'], $params);
        if(!empty($options['group']))
            $sql .= $this->buildGroup($options);
        if(!empty($options['having']))
            $sql .= $this->buildHaving($options, $params);
        if(!empty($options['order']))
            $sql .= $this->buildOrder($options);
        if(!empty($options['limit']) || !empty($options['offset']))
            $sql .= $this->buildLimit($options);

        $rows = $this->db->exec($sql, $params);
        foreach ($rows as &$row) { $row = $this->qb->rowHydration($row); }
        return new Cursor($rows);
    }

    /**
     * Выполняет произвольный SQL-запрос
     * @param string $sql
     * @param array $params параметры для плейсхолдеров ?
     * @return array[]
     */
    public function getRaw(string $sql, array $params = []): array {
        $this->protector->assertReadOnlyQuery($sql);
        return $this->db->exec($sql, $params);
    }

    /**
     * Добавляет новую запись
     * @param array $data ассоциативный массив [поле => значение]
     * @return bool
     */
    public function add(array $data): ?int {
        $this->validate($data);
        // фильтруем только известные поля по карте
        $allowed = array_intersect_key($data, static::getFieldsMap());
        if (!$allowed) return null;
        return $this->qb->insert($allowed); // вернёт lastInsertId
    }

    /**
     * Обновляет запись по ID
     * @param int $id
     * @param array $data данные для обновления
     * @return bool
     */
    public function update(int $id, array $data): int {
        $this->validate($data);
        // обновляем только известные поля
        $changes = array_intersect_key($data, static::getFieldsMap());
        if (!$changes) return 0;
        return $this->qb->update($id, $changes); // affected rows
    }
    
    /**
     * Удаляет запись по ID
     * @param int $id
     * @return bool
     */
    public function delete(int $id,string $key = 'id'): bool {
        $key += ' = ?';
        return $this->qb->erase([$key => $id]) > 0;
    }

    /**
     * Построить SELECT-часть запроса
     * @param string[] $fields
     * @param string $alias
     * @return string
     */
    protected function buildSelect(array $options): string {
        $fields = !empty($options['select'])?$options['select']:['*'];
        $this->protector->assertSafeIdentifiers($fields);
        $fieldList = implode(', ', $fields);
        $table = static::getTableName();
        $aliasSql = !empty($options['alias']) ? " AS ".$options['alias'] : '';
        return "SELECT $fieldList FROM $table$aliasSql";
    }

    /**
     * Построить JOIN'ы
     * @param array $joins
     * @return string
     */
    protected function buildJoins(array $options): string {
        $sql = '';
        $this->protector->assertSafeJoins($options['joins']);
        foreach ($options['joins'] as $join) {
            $type = strtoupper($join['type'] ?? 'INNER');
            $table = $join['table'];
            $on = $join['on'];
            $sql .= " $type JOIN $table ON $on";
        }
        return $sql;
    }

    
    /**
     * Построить WHERE-часть
     * @param array $filter
     * @param array $params
     * @return string
     */
    protected function buildWhere(array|string|null $filter, array &$params): string {
        if(!$filter) return '';
        if (is_string($filter)) return " WHERE $filter";
        $conditions = [];
        foreach ($filter as $key => $value) {
            // Оператор по умолчанию
            $operator = '=';

            // Разделение оператора и поля
            if (preg_match('/^(.+?)\s+(=|!=|<>|>=|<=|>|<|LIKE|IN|NOT IN|IS NULL|IS NOT NULL)$/i', $key, $matches)) {
                $field = trim($matches[1]);
                $operator = strtoupper(trim($matches[2]));
            } else {
                $field = $key;
            }

            // Построение условия
            switch ($operator) {
                case 'IN':
                case 'NOT IN':
                    if (!is_array($value) || empty($value)) break;
                    $placeholders = implode(', ', array_fill(0, count($value), '?'));
                    $conditions[] = "$field $operator ($placeholders)";
                    $params = array_merge($params, $value);
                    break;

                case 'IS NULL':
                case 'IS NOT NULL':
                    $conditions[] = "$field $operator";
                    break;

                default:
                    $conditions[] = "$field $operator ?";
                    $params[] = $value;
            }
        }

        return $conditions ? " WHERE " . implode(" AND ", $conditions) : '';
    }

    /**
     * Построить GROUP BY
     * @param array $group
     * @return string
     */
    protected function buildGroup(array $options): string {
        if (empty($options['group'])) return '';
        $group = (array)$options['group'];
        $this->protector->assertSafeGroup($group);
        return ' GROUP BY '.implode(', ', $group);
    }
    
    /**
     * Построить HAVING фильтрует после группировки
     * @param array $having
     * @param array $params
     * @return string
     */
    protected function buildHaving(array $options, array &$params): string {
        $conditions = [];

        foreach ($options['having'] as $key => $value) {
            $operator = '=';
            if (preg_match('/^(.+?)\s+(=|!=|<>|>=|<=|>|<|LIKE|IN|NOT IN|IS NULL|IS NOT NULL)$/i', $key, $matches)) {
                $field = trim($matches[1]);
                $operator = strtoupper(trim($matches[2]));
            } else {
                $field = $key;
            }

            switch ($operator) {
                case 'IN':
                case 'NOT IN':
                    if (!is_array($value) || empty($value)) break;
                    $placeholders = implode(', ', array_fill(0, count($value), '?'));
                    $conditions[] = "$field $operator ($placeholders)";
                    $params = array_merge($params, $value);
                    break;
                default:
                    $conditions[] = "$field $operator ?";
                    $params[] = $value;
            }
        }

        return $conditions ? ' HAVING ' . implode(' AND ', $conditions) : '';
    }     

    /**
     * Построить ORDER BY
     * @param array $order
     * @return string
     */
    protected function buildOrder(array $options): string {
        if (empty($options['order'])) return '';
        if (is_string($options['order'])) {
            $this->protector->assertSafeOrder($options['order']);
            return ' ORDER BY '.$options['order'];
        }
        // массив ['col1' => 'DESC', 'col2' => 'ASC']
        $this->protector->assertSafeOrder($options['order']);
        $clauses = [];
        foreach ($options['order'] as $field => $dir) {
            $clauses[] = "$field ".strtoupper($dir ?: 'ASC');
        }
        return ' ORDER BY '.implode(', ', $clauses);
    }
    
    /**
     * Построить LIMIT + OFFSET
     * @param int $limit
     * @param int $offset
     * @return string
     */
    protected function buildLimit(array $options): string {
        if ($options['limit'] <= 0) return '';
        $limit = (int)$options['limit'];
        $offset = !empty($options['offset']) ? (int)$options['offset'] : 0;
        return " LIMIT $limit" . ($offset > 0 ? " OFFSET $offset" : '');
    }

    /**
     * **Транзакции на уровне менеджера**  
     * Вспомогательный хелпер:
     */
    public function tx(callable $fn): mixed {
        $this->db->begin();
        try { $res = $fn($this); $this->db->commit(); return $res; }
        catch (\Throwable $e) { $this->db->rollback(); throw $e; }
    }
}
