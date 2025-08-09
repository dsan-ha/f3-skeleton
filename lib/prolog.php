<?php
define('SITE_ROOT',dirname(__DIR__));
require_once SITE_ROOT . '/lib/vendor/autoload.php';
$f3 = \App\F4::instance();

// Load configuration
$f3->config('lib/config.yaml');

if ((float)PCRE_VERSION<7.9)
    trigger_error('PCRE version is out of date');

App\Base\DataLoader::loadOrdered();