<?php if(!defined('SITE_ROOT')) exit();
use App\F3;
$f3=F3::instance();

define('SITE_UI',SITE_ROOT.$f3->g('UI','ui')); 
define('UPLOAD_DIR',SITE_ROOT.'upload/'); 
