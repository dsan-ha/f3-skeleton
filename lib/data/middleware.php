<?php if(!defined('SITE_ROOT')) exit();
$f3 = App\F4::instance();

$f3->addMiddleware(function () {
    //\App\Utils\Firewall::instance()->check();
});