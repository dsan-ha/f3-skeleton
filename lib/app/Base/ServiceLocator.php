<?php

namespace App\Base;

use DI\Container;
use DI\ContainerBuilder;

class ServiceLocator {
    protected static ?Container $container = null;

    public static function getContainer(): Container {
        if (!self::$container) {
            $builder = new ContainerBuilder();
            self::$container = $builder->build();
        }
        return self::$container;
    }

    public static function setContainer(Container $container): void {
        self::$container = $container;
    }

    public static function get(string $id): mixed {
        return self::getContainer()->get($id);
    }

    public static function has($className): mixed {
        $continer = self::getContainer();
        return $continer->has($className);
    }
}