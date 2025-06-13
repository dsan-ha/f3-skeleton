<?php if(!defined('SITE_ROOT')) exit();

use App\Utils\Assets;

$f3 = App\F3::instance();
$app = App\App::instance();
$assets = Assets::instance();
$assets->addCss('/ui/css/normalize.min.css');
$assets->addCss('/ui/css/fontawesome.min.css');
$assets->addCss('/ui/css/pure.css');
$assets->addCss('/ui/css/base.css');
$assets->addCss('/ui/css/code.css');
$assets->addCss('/ui/css/style.css');
$assets->addJs('/ui/js/jquery-3.4.1.min.js');
$assets->addJs('/ui/js/main.js');?>
<!DOCTYPE html>
<html lang="ru">
	<head>
		<meta charset="<?=$f3->get('ENCODING'); ?>" />
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<meta name="color-scheme" content="light dark">
		<? $app->showHead();?>
	</head>

  	<body>
        <?= $this->render($app->content('header'),$arParams);?>

        <!-- Main -->
        <main class="container">
        <?=$this->render($app->content('body'),$arParams); ?>
        </main>
        <?= $this->render($app->content('footer'),$arParams);?>       
        <? $app->showFooter();?>
	</body>
</html>
