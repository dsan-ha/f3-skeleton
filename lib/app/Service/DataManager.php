<?php
namespace App\Service;

use App\F3;
use DB\SQL;
use DB\SQL\Mapper;
use App\Service\DataManagerProtector;

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
    protected F3 $f3;
    protected Mapper $mapper;
    protected DataManagerProtector $protector;

    /**
     * @param SQL $db экземпляр базы данных
     * @param F3 $f3 экземпляр фреймворка
     */
    public function __construct(SQL $db, F3 $f3) {
        $this->db = $db;
        $this->f3 = $f3;
        $this->protector = DataManagerProtector::instance();
        $this->mapper = new Mapper($db, static::getTableName());
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
            if (!empty($info['required']) && !array_key_exists($field, $data)) {
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
    public function getById(int $id): ?array {
        $result = $this->mapper->load(['id = ?', $id]);
        return $result ? $result->cast() : null;
    }

    /**
     * Основной метод выборки данных
     * @param array $filter фильтр вида ['age >' => 18]
     * @param array $order сортировка вида ['id' => 'DESC']
     * @param int $limit лимит
     * @param int $offset смещение
     * @param array $joins массив JOIN'ов: [['type' => 'LEFT', 'table' => '...', 'on' => '...']]
     * @param string $alias псевдоним основной таблицы
     * @param array $select список полей, например ['u.id', 'p.name']
     * @param array $useMapKeys ключи из getMap() для авто-JOIN'ов
     * @param array $group поля для GROUP BY
     * @param array $having условия HAVING
     * @return array[]
     */
    public function getList(
        array $filter = [],
        array $order = [],
        int $limit = 0,
        int $offset = 0,
        array $joins = [],
        string $alias = '',
        array $select = ['*'],
        array $useMapKeys = [],
        array $group = [],
        array $having = []
    ): array {
        $params = [];

        $sql  = $this->buildSelect($select, $alias);
        $sql .= $this->buildJoins(array_merge(
            $this->resolveMapJoins($useMapKeys),
            $joins
        ));
        $sql .= $this->buildWhere($filter, $params);
        $sql .= $this->buildGroup($group);
        $sql .= $this->buildHaving($having, $params);
        $sql .= $this->buildOrder($order);
        $sql .= $this->buildLimit($limit, $offset);

        return $this->db->exec($sql, $params);
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
        $this->mapper->reset();
        foreach (static::getFieldsMap() as $code => $field) {
            if (isset($data[$code])) {
                $this->mapper->$code = $data[$code];
            }
        }
        
        return $this->mapper->save()->get('_id');;
    }

    /**
     * Обновляет запись по ID
     * @param int $id
     * @param array $data данные для обновления
     * @return bool
     */
    public function update(int $id, array $data): array {
        $item = $this->mapper->load(['id = ?', $id]);
        if (!$item) return false;

        $this->validate($data);

        foreach (static::getFieldsMap() as $code => $field) {
            if (isset($data[$code])) {
                $item->$code = $data[$code];
            }
        }
        return $item->save()->find(['id = ?', $id]);
    }
    
    /**
     * Удаляет запись по ID
     * @param int $id
     * @return bool
     */
    public function delete(int $id): bool {
        $item = $this->mapper->load(['id = ?', $id]);
        if (!$item) return false;
        return $item->erase();
    }

    /**
     * Построить SELECT-часть запроса
     * @param string[] $fields
     * @param string $alias
     * @return string
     */
    protected function buildSelect(array $fields = ['*'], string $alias = ''): string {
        $this->protector->assertSafeIdentifiers($fields);
        $fieldList = implode(', ', $fields);
        $prefix = $this->f3->get('DB.prefix') ?: '';
        $table = static::getTableName();
        $aliasSql = $alias ? " AS $alias" : '';
        return "SELECT $fieldList FROM $table$aliasSql";
    }

    /**
     * Построить JOIN'ы
     * @param array $joins
     * @return string
     */
    protected function buildJoins(array $joins): string {
        if(empty($joins)) return '';
        $sql = '';
        $this->protector->assertSafeJoins($joins);
        foreach ($joins as $join) {
            $type = strtoupper($join['type'] ?? 'INNER');
            $table = $join['table'];
            $on = $join['on'];
            $sql .= " $type JOIN $table ON $on";
        }
        return $sql;
    }

    // Разрешить карту зависимых таблиц
    protected function resolveMapJoins(array $mapKeys): array {
        if (!method_exists($this, 'getMap')) return [];

        $map = static::getMap();
        $joins = [];

        foreach ($mapKeys as $key) {
            if (isset($map[$key])) {
                $joins[] = $map[$key];
            }
        }

        return $joins;
    }

    
    /**
     * Построить WHERE-часть
     * @param array $filter
     * @param array $params
     * @return string
     */
    protected function buildWhere(array $filter, array &$params): string {
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
    protected function buildGroup(array $group): string {
        if (empty($group)) return '';
        $this->protector->assertSafeGroup($group);
        return ' GROUP BY ' . implode(', ', $group);
    }
    
    /**
     * Построить HAVING фильтрует после группировки
     * @param array $having
     * @param array $params
     * @return string
     */
    protected function buildHaving(array $having, array &$params): string {
        if (empty($having)) return '';

        $conditions = [];

        foreach ($having as $key => $value) {
            $operator = '=';
            if (preg_match('/^(.+?)\s+(=|!=|<>|>=|<=|>|<|LIKE|IN|NOT IN)$/i', $key, $matches)) {
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
    protected function buildOrder(array $order): string {
        if (empty($order)) return '';
        $this->protector->assertSafeOrder($order);
        $clauses = [];
        foreach ($order as $field => $dir) {
            $clauses[] = "$field $dir";
        }
        return " ORDER BY " . implode(', ', $clauses);
    }
    
    /**
     * Построить LIMIT + OFFSET
     * @param int $limit
     * @param int $offset
     * @return string
     */
    protected function buildLimit(int $limit, int $offset = 0): string {
        if ($limit <= 0) return '';
        return " LIMIT $limit" . ($offset > 0 ? " OFFSET $offset" : '');
    }
}
