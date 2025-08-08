<?php if(!defined('SITE_ROOT')) exit();
use App\Service;
use App\Utils\Cache\FileCacheAdapter;
use App\Component\ComponentManager;
use function DI\autowire;
use function DI\create;
use function DI\get;

$f3 = App\F4::instance();

//Инициализирую кэш, чтоб быстро к нему обращаться
$cache_folder = $f3->g('cache.folder','lib/tmp/cache/');
$adapter = new FileCacheAdapter($cache_folder);
$f3->set('CACHE_ADAPTER',$adapter);

$UIpaths = $f3->g('UI','ui/');

return [
    App\F4::class => autowire(App\F4::class),
    // App\Utils\Assets::instance()
    App\Utils\Assets::class => autowire(App\Utils\Assets::class),
    // template()->render()
    App\Utils\Template::class => create(App\Utils\Template::class)->constructor($f3, $UIpaths),
    // f3_cache()
    App\Utils\Cache::class => create(App\Utils\Cache::class)->constructor($adapter),
    // app()
    ComponentManager::class => autowire(ComponentManager::class),
    // app()
    App\App::class => autowire(App\App::class)
];





