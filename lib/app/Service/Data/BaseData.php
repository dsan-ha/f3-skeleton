<?php

namespace App\Service\Data;

use App\Service\DataManager;

class BaseData extends DataManager
{
    public static function getTableName(): string
    {
        return 'base_table';
    }

    public static function getFieldsMap(): array
    {
        return [
            'username' => ['type' => 'string','required' => true], 
            'first_name' => ['type' => 'string'], 
            'last_name' => ['type' => 'string'], 
        ];
    }
}