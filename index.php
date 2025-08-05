<?
require_once($_SERVER['DOCUMENT_ROOT'].'/lib/prolog.php');
$f3 = \App\F3::instance();

require_once(SITE_ROOT . '/lib/data/middleware.php');
require_once(SITE_ROOT . '/lib/data/routes.php');

$f3->run();