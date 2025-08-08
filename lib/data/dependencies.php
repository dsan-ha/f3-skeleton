<?php if(!defined('SITE_ROOT')) exit();
use App\Service;
use App\Utils\Cache\FileCacheAdapter;
use App\Base\ServiceLocator;
use DI\ContainerBuilder;

$f3 = App\F4::instance();
$f3->set('DB', null);

$builder = new ContainerBuilder();

// Загружаем все определения
$definitions = [];

foreach (['lib/data/.definitions.php', 'local/data/.definitions.php'] as $file) {
    $full_path = SITE_ROOT.$file;
    if (file_exists($full_path)) {
        $defs = require $full_path;
        $definitions = array_merge($definitions, $defs);
    }
}

$builder->addDefinitions($definitions);
ServiceLocator::setContainer($builder->build());
