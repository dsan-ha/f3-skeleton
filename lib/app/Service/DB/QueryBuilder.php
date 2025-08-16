<?php

namespace App\Service\DB;

use App\Service\DB\SQL;
use App\Service\DB\Cursor;

final class QueryBuilder
{
    public function __construct(
        private SQL $db,                  // твой адаптер Fat-Free DB\SQL
        private string  $table,               // имя таблицы (с алиасом при желании)
        private array   $fieldsMap,           // карта полей (см. формат выше)
        private ?string $pk = null,           // имя PK (если null — найдём по map['pkey'])
        private ?HydratorInterface $hydrator = null
    ) {
        $this->pk ??= $this->detectPk($fieldsMap);
    }

    public function setHydrator(HydratorInterface $hydrator): void { $this->hydrator = $hydrator; }

    /** SELECT → Cursor<DTO|array> */
    public function find(array|string|null $filter=null, ?array $options=null): Cursor
    {
        [$sql, $args] = $this->buildSelect('*', $filter, $options);
        $rows = $this->db->exec($sql, $args);
        $out = [];
        foreach ($rows as $row) {
            $out[] = $this->hydrator?->fromArray($row) ?? $row;
        }
        return new Cursor($out);
    }

    public function count(array|string|null $filter=null, ?array $options=null): int
    {
        [$sql, $args] = $this->buildSelect('COUNT(*) AS cnt', $filter, $options, ignoreOrder:true, ignoreLimit:true);
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
        return $this->db->exec($sql, $args)->rowCount();
    }

    public function updateWhere(array|string $filter, array $changes): int
    {
        if (!$changes) return 0;
        [$sql, $args] = $this->buildUpdate($filter, $changes);
        return $this->db->exec($sql, $args)->rowCount();
    }

    public function erase(array|string $filter): int
    {
        [$where, $args] = $this->compileWhere($filter);
        $sql = "DELETE FROM {$this->table} $where";
        return $this->db->exec($sql, $args)->rowCount();
    }

    public function rowHydration($row){
        return $this->hydrator?->fromArray($row) ?? $row;
    }

    // ----------------- SQL билдеры (просто и читабельно) -----------------

    private function buildSelect(
        string $fields, array|string|null $filter, ?array $options,
        bool $ignoreOrder=false, bool $ignoreLimit=false
    ): array {
        [$where, $args] = $this->compileWhere($filter);

        $sql = "SELECT $fields FROM {$this->table} $where";

        if (!$ignoreOrder && !empty($options['order'])) {
            $sql .= " ORDER BY {$options['order']}";
        }
        if (!$ignoreLimit && !empty($options['limit'])) {
            $sql .= " LIMIT ".(int)$options['limit'];
            if (!empty($options['offset'])) $sql .= " OFFSET ".(int)$options['offset'];
        }
        return [$sql, $args];
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
        [$where, $wargs] = $this->compileWhere($filter);
        $sql = "UPDATE {$this->table} SET ".implode(',',$pairs)." $where";
        return [$sql, array_merge($args, $wargs)];
    }

    private function compileWhere(array|string|null $filter): array {
        if ($filter===null || $filter==='') return ['', []];
        if (is_string($filter)) return ["WHERE $filter", []];

        $parts = []; $args = [];
        foreach ($filter as $expr=>$val) {
            if (is_int($expr)) { $parts[]=$val; }
            else { $parts[]=$expr; $args[]=$val; } // ['id = ?' => 10]
        }
        return ['WHERE '.implode(' AND ',$parts), $args];
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
        if($keys > 1) throw new InvalidArgumentException("Больше одного primary key в fieldsMap ".self::class);
        return $pkey;
    }
}
