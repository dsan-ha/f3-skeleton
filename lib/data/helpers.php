<?php if(!defined('SITE_ROOT')) exit();

/**
 * f3_cache - для вызова кэша
**/

function f3(){
    return \App\F3::instance();
}
function app(){
    return \App\App::instance();
}
function template(){
    return \App\Utils\Template::instance();
}
function app_component(string $componentName, string $componentTemplate, array $arParams = []){
    return app()->component->run($componentName, $componentTemplate, $arParams);
}
function ds(){
    return \App\DS::instance();
}
function f3_cache(){
    return App\Utils\Cache::instance(); //Если не инициализирован кэш то выдаст ошибку
}


function dd($var, $pretty = true){
	
    $backtrace = debug_backtrace();
    echo "\n<pre>\n";
    if (isset($backtrace[0]['file'])) {
        echo $backtrace[0]['file'] . "\n\n";
    }
    echo "Type: " . gettype($var) . "\n";
    echo "Time: " . date('c') . "\n";
    echo "---------------------------------\n\n";
    ($pretty) ? print_r($var) : var_dump($var);
    echo "</pre>\n";
    die;
}