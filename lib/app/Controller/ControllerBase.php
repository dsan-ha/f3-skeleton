<?php

namespace App\Controller;

use App\App;
use App\F3;
use App\Utils\Template;

abstract class ControllerBase
{
    protected $app, $f3, $template;

    abstract protected function middleware(array $arParams): void;
    abstract protected function afterRender(array $arParams): void;

    public function __construct(array $arParams = [])
    {
        $this->f3 = F3::instance();
        $this->template = Template::instance();
        $this->app = App::instance();
        $this->middleware($arParams);
    }

    public function render(array $arParams = [])
    {
        echo $this->app->render($arParams);
        $this->afterRender($arParams);
    }
    
}