<?php if(!defined('SITE_ROOT')) exit();
use App\Service;
$f3 = App\F3::instance();
$db = new DB\SQL(
    $f3->get('sql_dns'),
    $f3->get('sql_login'),
    $f3->get('sql_pass')
);
$f3->set('DB', $db);
Service\DataManagerRegistry::init($db, $f3);