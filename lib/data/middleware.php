<?php if(!defined('SITE_ROOT')) exit();
$f3 = App\F3::instance();

$f3->addMiddleware(function () {
    //\App\Utils\Firewall::instance()->check();
});
$middleware_file = SITE_ROOT . 'local/data/middleware.php';
if(is_file($middleware_file)) require_once($middleware_file);