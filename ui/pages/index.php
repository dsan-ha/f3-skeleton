<?php if(!defined('SITE_ROOT')) exit();?>
Hello world! <br>

<? $f3 = f3();
if(!$f3->cache_exists('rand_num',$rand_num)){
    $rand_num = rand();
    $f3->cache_set('rand_num',$rand_num,360);
}
echo 'rand: '.$rand_num;

echo app_component(
    'ds:test',
    'base',
    ['CACHE_TYPE' => 'Y','CACHE_TIME' => 360]
);?>