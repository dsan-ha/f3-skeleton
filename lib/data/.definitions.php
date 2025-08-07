<?php if(!defined('SITE_ROOT')) exit();
use App\Service;
use App\Utils\Cache\FileCacheAdapter;
use function DI\autowire;
use function DI\create;
use function DI\get;

$f3 = App\F3::instance();

//Инициализирую кэш, чтоб быстро к нему обращаться
$cache_folder = $f3->g('cache.folder','lib/tmp/cache/');
$adapter = new FileCacheAdapter($cache_folder);
$f3->set('CACHE_ADAPTER',$adapter);

return [
    App\F3::class => autowire(App\F3::class),
    // App\Utils\Assets::instance()
    App\Utils\Assets::class => autowire(App\Utils\Assets::class),
    // template()->render()
    App\Utils\Template::class => autowire(App\Utils\Template::class),
    // f3_cache()
    App\Utils\Cache::class => create(App\Utils\Cache::class)->constructor($adapter),
    // app()
    App\App::class => autowire(App\App::class)
];





