<?php
require_once 'prolog.php';
require_once(SITE_ROOT . '/lib/data/schedule.php');
$f3 = \App\F4::instance();

date_default_timezone_set($f3->get('TZ'));

$f3->runScheduledTasks();