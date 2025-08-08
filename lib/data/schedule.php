<?php if(!defined('SITE_ROOT')) exit();
$f3 = \App\F4::instance();

$f3->schedule(function () {
    // Очистка бана
    //\App\Utils\Firewall::cronCleanup();
}, '0 * * * *'); // каждый час

$f3->schedule(function () {
    // Ротация логов
    //\App\Utils\Log\LogRotator::rotateDirectory();
}, '0 * * * *'); // каждый час