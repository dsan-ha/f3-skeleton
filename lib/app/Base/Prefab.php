<?php

namespace App\Base;

abstract class Prefab {
    protected static array $instances = [];

    public static function instance(): static {
        $class = static::class;
        if (!isset(self::$instances[$class])) {
            // Используем DI-контейнер для создания класса
            self::$instances[$class] = \App\Base\ServiceLocator::get($class);
        }

        return self::$instances[$class];
    }
}