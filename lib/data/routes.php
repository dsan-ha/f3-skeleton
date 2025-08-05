<?php if(!defined('SITE_ROOT')) exit();

$routes_file = SITE_ROOT . 'local/data/routes.php';
if(is_file($routes_file)) require_once($routes_file);