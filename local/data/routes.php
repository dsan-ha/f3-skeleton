<?php if(!defined('SITE_ROOT')) exit();

use App\Controller\Base;

$f3=App\F4::instance();


$f3->route('GET /', [Base::class,'index']);
/*
$f3->route('GET /', function($_) {
	app()->setContent('body','pages/index.php');
	app()->render();
})->add(function ($req, $res, $params, $next) {
    //\App\Utils\Firewall::instance()->check();
    //echo 'local middleware work';
    return $next($f3);
});*/