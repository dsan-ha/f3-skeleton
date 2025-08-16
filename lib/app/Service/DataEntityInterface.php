<?php
namespace App\Service;

/**
 * Любая «табличная сущность» (BaseData) должна реализовать этот интерфейс.
 * Статические методы — ОК для интерфейса (PHP 8+).
 */
interface DataEntityInterface
{
    /** Имя таблицы, можно с алиасом */
    public static function getTableName(): string;

    /**
     * Карта полей для авто-гидратора.
     * Формат рекомендуемый:
     * [
     *   'id'    => ['type'=>'int','pkey'=>true,'auto'=>true,'nullable'=>false],
     *   'email' => ['type'=>'string','nullable'=>false,'len'=>200],
     *   'meta'  => ['type'=>'json','nullable'=>true],
     * ]
     */
    public static function getFieldsMap(): array;

    /**
     * (Необязательно) Класс DTO, если хочешь возвращать объекты вместо массивов.
     * Верни null, если достаточно ассоц-массива.
     */
    public static function getDtoClass(): ?string;
}
