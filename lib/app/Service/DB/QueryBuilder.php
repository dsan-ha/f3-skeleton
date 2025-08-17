<?php

namespace App\Service\DB;

use App\Service\DB\SQL;
use App\Service\DB\Cursor;
use App\Service\Hydrator\HydratorInterface;

final class QueryBuilder
{
    public function __construct(
        private SQL $db,                  // твой адаптер Fat-Free DB\SQL
        private string  $table,               // имя таблицы (с алиасом при желании)
        private array   $fieldsMap,           // карта полей (см. формат выше)
        private ?string $pk = null,           // имя PK (если null — найдём по map['pkey'])
        private ?HydratorInterface $hydrator = null
    ) {
        $this->pk ??= $this->detectPk($this->fieldsMap);
    }

    public function setHydrator(HydratorInterface $hydrator): void { $this->hydrator = $hydrator; }

    /** SELECT → Cursor<DTO|array> */
    public function find(?array $options=null): Cursor
    {
        // autojoin
        if (!empty($options['with'])) {
            $with = (array)$options['with'];
            $options['select'] = $this->expandSelectForWith($options['select'] ?? ['*'], $with);
            $options['joins'] = array_merge($options['joins'] ?? [], $this->buildJoinsForWith($with));
        }
        [$sql, $args] = $this->buildSelect($options);
        $rows = $this->db->exec($sql, $args);
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->hydrator?->fromArray($row) ?? $row;
        }
        return new Cursor($out);
    }

    public function count(array|string|null $filter=null, ?array $options=null): int
    {
        $options = $options ?? [];
        unset($options['order'],$options['limit']);
        $options['select'] = ['COUNT(*) AS cnt'];
        [$sql, $args] = $this->buildSelect($options);
        $row = $this->db->exec($sql, $args)[0] ?? ['cnt'=>0];
        return (int)$row['cnt'];
    }

    public function insert(object|array $dtoOrArray): int
    {
        $data = is_array($dtoOrArray) ? $dtoOrArray : ($this->hydrator?->toArray($dtoOrArray) ?? get_object_vars($dtoOrArray));
        unset($data[$this->pk]); // auto
        [$sql, $args] = $this->buildInsert($data);
        $this->db->exec($sql, $args);
        return (int)$this->db->lastInsertId();
    }

    public function update(int|string $id, array $changes): int
    {
        if (!$changes) return 0;
        [$sql, $args] = $this->buildUpdate([$this->pk => $id], $changes);
        return (int)$this->db->exec($sql, $args);
    }

    public function updateWhere(array|string $filter, array $changes): int
    {
        if (!$changes) return 0;
        [$sql, $args] = $this->buildUpdate($filter, $changes);
        return (int)$this->db->exec($sql, $args);
    }

    public function erase(array|string $filter): int
    {
        $args = [];
        $where = $this->compileWhere($filter,$args);
        $sql = "DELETE FROM {$this->table} $where";
        return (int)$this->db->exec($sql, $args);
    }

    public function rowHydration($row){
        return $this->hydrator?->fromArray($row) ?? $row;
    }

    private function buildSelect(?array $options
    ): array {
        $args = [];
        $sql = $this->buildSelectMain($options);
        if(!empty($options['joins']))
            $sql .= $this->buildJoins($options);
        $sql .= $this->compileWhere($options['where'] ?? null, $args);
        if(!empty($options['group']))
            $sql .= $this->buildGroup($options);
        if(!empty($options['having']))
            $sql .= $this->buildHaving($options, $args);
        if (!empty($options['order'])) {
            if (is_string($options['order'])) {
                $sql .= ' ORDER BY '.$options['order'];
            } elseif(is_array($options['order'])) {
                // массив ['col1' => 'DESC', 'col2' => 'ASC']
                $clauses = [];
                foreach ($options['order'] as $field => $dir) {
                    $clauses[] = "$field ".strtoupper($dir ?: 'ASC');
                }
                $sql .= " ORDER BY ".implode(',',$clauses);
            }
            
        }
        if (!empty($options['limit']) && $options['limit'] > 0) {
            $sql .= " LIMIT ".(int)$options['limit'];
            if (!empty($options['offset'])) $sql .= " OFFSET ".(int)$options['offset'];
        }
        return [$sql, $args];
    }


    private function buildSelectMain(array $options): string {
        $fields = !empty($options['select'])?$options['select']:['*'];
        
        $fieldList = implode(', ', $fields);
        $aliasSql = !empty($options['alias']) ? " AS ".$options['alias'] : '';
        return "SELECT $fieldList FROM $this->table$aliasSql";
    }

    private function buildInsert(array $data): array {
        $cols = array_keys($data);
        $marks = implode(',', array_fill(0, count($cols), '?'));
        $sql = "INSERT INTO {$this->table} (".implode(',',$cols).") VALUES ($marks)";
        return [$sql, array_values($data)];
    }

    private function buildUpdate(array|string $filter, array $changes): array {
        $pairs = []; $args = [];
        foreach ($changes as $k=>$v) { $pairs[]="$k = ?"; $args[]=$v; }
        $where = $this->compileWhere($filter,$args);
        $sql = "UPDATE {$this->table} SET ".implode(',',$pairs)." $where";
        return [$sql, $args];
    }

    protected function buildJoins(array $options): string {
        $sql = '';
        foreach ($options['joins'] as $join) {
            if(empty($join['type']) || empty($join['on'])) continue;
            $type = strtoupper($join['type'] ?? 'INNER');
            $table = $join['table'];
            $on = $join['on'];
            $sql .= " $type JOIN $table ON $on";
        }
        return $sql;
    }

    protected function buildJoinsForWith(array $with): array
    {
        $joins = [];
        $with = array_unique($with);
        foreach ($with as $alias) {
            // найти ref по alias (или по имени поля, если alias не задан)
            foreach ($this->fieldsMap as $field => $rules) {
                if (empty($rules['ref'])) continue;
                $ref = $rules['ref'];
                $aliasName = $ref['alias'] ?? $field;
                if ($aliasName !== $alias) continue;
                $type    = strtoupper($ref['join'] ?? 'LEFT');
                $table   = $ref['table']   ?? null;
                $local   = $ref['local']   ?? $field;
                $foreign = $ref['foreign'] ?? 'id';
                if (!$table) continue;
                // Префиксуем алиас relation, чтобы различать поля
                $tblAlias = $aliasName;
                $joins[] = [
                    'type'  => $type,
                    'table' => "{$table} AS {$tblAlias}",
                    'on'    => "{$this->table}.{$local} = {$tblAlias}.{$foreign}",
                ];
            }
        }
        return $joins;
    }

    private function buildGroup(array $options): string {
        if (empty($options['group'])) return '';
        $group = (array)$options['group'];
        return ' GROUP BY '.implode(', ', $group);
    }

    private function buildHaving(array $options, array &$params): string {
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

    private function compileWhere(array|string|null $filter, array &$params): string {
        if ($filter===null || $filter==='') return '';
        if (is_string($filter)) return " WHERE $filter";

        $conditions = [];
        foreach ($filter as $expr=>$value) {
            // Оператор по умолчанию
            $operator = '=';

            // Разделение оператора и поля
            if (preg_match('/^(.+?)\s+(=|!=|<>|>=|<=|>|<|LIKE|IN|NOT IN|IS NULL|IS NOT NULL)$/i', $expr, $matches)) {
                $field = trim($matches[1]);
                $operator = strtoupper(trim($matches[2]));
            } else {
                $field = $expr;
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
        return $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';
    }

    private function detectPk(array $map): ?string {
        $keys = 0;
        $pkey = null;
        foreach ($map as $name => $meta) {
            if (!empty($meta['pkey'])) {
                $keys++;
                $pkey = $name;
            }
        }
        if($keys !== 1){
            if ($keys === 0) {
                throw new \InvalidArgumentException('Primary key not found');
            } else {
                throw new \InvalidArgumentException('Only single scalar PK is supported');
            }
        }
        
        return $pkey;
    }
}
