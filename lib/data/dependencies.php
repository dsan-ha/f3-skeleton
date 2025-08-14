<?php if(!defined('SITE_ROOT')) exit();
use App\Service;
use App\Utils\Cache\FileCacheAdapter;
use App\Base\ServiceLocator;
use Symfony\Component\Yaml\Yaml;
use DI\ContainerBuilder;

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
$f3->set('CONTAINER',function($className, $args = null){
    if (ServiceLocator::has($className)) {
        return ServiceLocator::get($className);
    }
    
    user_error("Service '{$className}' not found in container", E_USER_ERROR);
});

$router = ServiceLocator::get(App\Http\Router::class);
$f3->initRouter($router);
