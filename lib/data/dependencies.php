<?php if(!defined('SITE_ROOT')) exit();
use App\Service;
use App\Utils\Cache\FileCacheAdapter;
$f3 = App\F3::instance();
$f3->set('DB', null);

//Инициализирую кэш, чтоб быстро к нему обращаться
$cache_folder = $f3->g('cache.folder','lib/tmp/cache/');
$adapter = new FileCacheAdapter($cache_folder);
$f3->set('CACHE_ADAPTER',$adapter);