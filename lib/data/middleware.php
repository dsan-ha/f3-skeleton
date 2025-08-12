<?php if(!defined('SITE_ROOT')) exit();
$f3 = App\F4::instance();

$f3->add(function ($f3, $next) {
    //\App\Utils\Firewall::instance()->check();
    return $next($f3);
});