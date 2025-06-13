<?php

namespace App\Controllers;

use App\App;
use App\F3;

class ControllerBase
{
	protected $app, $f3;

    public function __construct()
    {
        $this->app = App::instance();
        $this->f3 = F3::instance();
    }

	public function render()
    {
        echo $this->app->render();
    }
    
}