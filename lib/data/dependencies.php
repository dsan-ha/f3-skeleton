<?php if(!defined('SITE_ROOT')) exit();
use App\Service;
use App\Utils\Cache\FileCacheAdapter;
use App\Base\ServiceLocator;
use Symfony\Component\Yaml\Yaml;
use DI\ContainerBuilder;

$f3 = App\F4::instance();
$f3->set('DB', null);

$containerBuilder = new ContainerBuilder();

// Загружаем все определения
$definitions = [];

$paths = [
    '/lib/data',   // ядро
    '/local/data', // локальные переопределения
];

$files = ['services.yaml', '.definitions.php']; //файлы с зависимостями

foreach ($paths as $dir) {
    foreach ($files as $fileName) {
        $fullPath = SITE_ROOT . "{$dir}/{$fileName}";
        if (!file_exists($fullPath)) {
            continue;
        }

        $ext = pathinfo($fullPath, PATHINFO_EXTENSION);

        if ($ext === 'yaml' || $ext === 'yml') {
            $yaml = Yaml::parseFile($fullPath);
            if (!empty($yaml['services'])) {
                $containerBuilder->addDefinitions($yaml['services']);
            }
        } elseif ($ext === 'php') {
            $definitions = require $fullPath;
            if (is_array($definitions)) {
                $containerBuilder->addDefinitions($definitions);
            }
        }
    }
}
$containerBuilder->useAutowiring(true);
$containerBuilder->addDefinitions($definitions);
ServiceLocator::setContainer($containerBuilder->build());
