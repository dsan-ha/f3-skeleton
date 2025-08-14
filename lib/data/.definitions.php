<?php if(!defined('SITE_ROOT')) exit();
use App\Service;
use App\Utils\Cache\FileCacheAdapter;
use App\Component\ComponentManager;
use App\Http\Environment;
use function DI\autowire;
use function DI\create;
use function DI\get;

$f3 = App\F4::instance();


$UIpaths = $f3->g('UI','ui/');

return [
    App\F4::class => DI\factory(function () {
        return App\F4::instance();
    }),
    Environment::class => DI\factory(function () {
        return Environment::instance();
    }),
    App\Http\Response::class => create(App\Http\Response::class),
    App\Http\Request::class => DI\factory(fn() => Environment::instance()->getRequest()),
    App\Http\Router::class => create(App\Http\Router::class)->constructor(get(App\F4::class),get(App\Http\Request::class),get(App\Http\Response::class)),
    // App\Utils\Assets::instance()
    App\Utils\Assets::class => create(App\Utils\Assets::class),
    // f3_cache()
    App\Utils\Cache::class => DI\factory(function (App\F4 $f3) {
        $cache_folder = $f3->g('cache.folder','lib/tmp/cache/');
        $adapter = new FileCacheAdapter($cache_folder);
        $f3->set('CACHE_ADAPTER', $adapter);
        return new App\Utils\Cache($adapter);
    }),
    // template()->render()
    App\Utils\Template::class => create(App\Utils\Template::class)->constructor(get(App\F4::class),get(App\Utils\Cache::class), $UIpaths),
    // app()
    ComponentManager::class => create(ComponentManager::class)->constructor(get(App\F4::class),get(App\Utils\Assets::class)),
    // app()
    App\App::class => create(App\App::class)->constructor(get(App\F4::class),get(App\Utils\Assets::class), get(ComponentManager::class))
];





