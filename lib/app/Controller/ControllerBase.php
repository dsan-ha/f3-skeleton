<?php

namespace App\Controller;

use App\App;
use App\F3;
use App\Utils\Template;

abstract class ControllerBase
{
    protected App $app; 
    protected F3 $f3;
    protected Template $template;

    abstract protected function middleware(): void;
    abstract protected function afterRender(array $arParams): void;
    abstract protected function initController(): void;

    public function __construct()
    {
        $arParams = [];
        $this->f3 = F3::instance();
        $this->template = Template::instance();
        $this->app = App::instance();
        $this->initController();
        $this->middleware();
    }

    public function render(array $arParams = [])
    {
        echo $this->app->render($arParams);
        $this->afterRender($arParams);
    }
    
}