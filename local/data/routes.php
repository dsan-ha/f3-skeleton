<?php if(!defined('SITE_ROOT')) exit();

use App\Controller\Base;

$f3=App\F4::instance();


$f3->route('GET /', [Base::class,'index']);

//Работа групп в router
$g = $f3->group(['admin','v1']);       // groups += ['admin/v1']
$g->add(function($req,$res,$params){
    // Тут добавляем middleware для всех роутов группы
});
$f3->route(['GET /beer/','GET @beer_details: /beer/@id'], [Base::class,'index']);
// aliases['beer_details'] = ['url' => '/admin/v1/beer/@id', 'group' => 'admin/v1']
// + ['url' => '/admin/v1/beer/', 'group' => 'admin/v1']
$g->end();   

/*
$f3->route('GET /', function($_) {
	app()->setContent('body','pages/index.php');
	app()->render();
})->add(function ($req, $res, $params, $next) {
    //\App\Utils\Firewall::instance()->check();
    //echo 'local middleware work';
    return $next($f3);
});*/