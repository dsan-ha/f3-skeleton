<?php

namespace App\Service\Data;

use App\Service\DataManager;
use App\Service\DataEntityInterface;

class BaseData extends DataManager implements DataEntityInterface
{
    public static function getTableName(): string
    {
        return 'base_table';
    }

    public static function getFieldsMap(): array
    {
        return [
            'id'    => ['type'=>'int',  'pkey'=>true, 'required'=>true,  'auto'=>true, 'nullable'=>false],
            'name'  => ['type'=>'string', 'required'=>true,   'nullable'=>false, 'len'=>200],
            'email' => ['type'=>'string',   'nullable'=>false, 'len'=>200],
            'age'   => ['type'=>'int',      'nullable'=>true],
            'data'  => ['type'=>'json',     'nullable'=>true],
            // adhoc/вычисляемые можно объявлять как:
            // 'orders_cnt' => ['type'=>'int', 'virtual'=>true]
        ];
    }

    public static function getDtoClass(): ?string
    {
        return null; // либо null → массивы
    }
}