<?php if(!defined('SITE_ROOT')) exit();
use App\Service;
use App\Service\DB\SQL;
use function DI\autowire;
use function DI\create;
use function DI\get;

$f3 = App\F4::instance();
$dsn  = $f3->get('db.dsn');
$user = $f3->get('db.login');
$pass = $f3->get('db.pass');

return [
    SQL::class => create(SQL::class)->constructor($dsn, $user, $pass),
];





