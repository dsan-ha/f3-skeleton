<?php
define('SITE_ROOT',dirname(__DIR__).'/');
require_once SITE_ROOT . 'lib/vendor/autoload.php';
$f3 = \App\F3::instance();
// Load configuration
$f3->config('lib/config.ini');
require_once(SITE_ROOT . 'lib/data/constants.php');
if(is_file(SITE_ROOT . 'local/data/constants.php')) require_once(SITE_ROOT . 'local/data/constants.php');

if ((float)PCRE_VERSION<7.9)
    trigger_error('PCRE version is out of date');

require_once(SITE_ROOT . 'lib/data/dependencies.php');
if(is_file(SITE_ROOT . 'local/data/dependencies.php')) require_once(SITE_ROOT . 'local/data/dependencies.php');
require_once(SITE_ROOT . 'lib/data/helpers.php');
if(is_file(SITE_ROOT . 'local/data/helpers.php')) require_once(SITE_ROOT . 'local/data/helpers.php');
