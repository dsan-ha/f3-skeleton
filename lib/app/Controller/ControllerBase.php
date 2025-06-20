<?php

namespace App\Controller;

use App\App;
use App\F3;

abstract class ControllerBase
{
	protected $app, $f3;

    abstract protected function middleware(array $arParams): void;
    abstract protected function afterRender(array $arParams): void;

    public function __construct(array $arParams = [])
    {
        $this->app = App::instance();
        $this->f3 = F3::instance();
        $this->middleware($arParams);
    }

	public function render(array $arParams = [])
    {
        echo $this->app->render($arParams);
        $this->afterRender($arParams);
    }
    
}