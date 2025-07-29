<?php if(!defined('SITE_ROOT')) exit();
use App\Service;
use App\Utils\Cache\FileCacheAdapter;
$f3 = App\F3::instance();
$db = new DB\SQL(
    $f3->get('sql_dns'),
    $f3->get('sql_login'),
    $f3->get('sql_pass')
);
$f3->set('DB', $db);
//Инициализирую кэш, чтоб быстро к нему обращаться
$cache_folder = $f3->g('cache.folder','lib/tmp/cache/');
$adapter = new FileCacheAdapter($cache_folder);
$f3->set('CACHE_ADAPTER',$adapter);