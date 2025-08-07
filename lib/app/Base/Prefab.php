<?php

namespace App\Base;

abstract class Prefab {
    public static function instance(): static {
        $class = static::class;
        return \App\Base\ServiceLocator::get($class);
    }
}