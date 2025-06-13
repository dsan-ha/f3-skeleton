<?php if(!defined('SITE_ROOT')) exit();
$f3=App\F3::instance();

$f3->route('GET /',
	function($_) {
		app()->setContent('body','pages/index.php');
		app()->render();
	}
);