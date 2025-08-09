<?php

namespace App\Controller;

use App\App;
use App\F4;
use App\Utils\Template;

abstract class ControllerBase
{
    protected App $app; 
    protected F4 $f3;

    abstract protected function middleware(): void;
    abstract protected function afterRender(array $arParams): void;
    abstract protected function initController(): void;

    public function __construct()
    {
        $arParams = [];
        $this->f3 = F4::instance();
        $this->app = app();
        $this->initController();
        $this->middleware();
    }

    public function render(array $arParams = [])
    {
        echo $this->app->render($arParams);
        $this->afterRender($arParams);
    }
    
}