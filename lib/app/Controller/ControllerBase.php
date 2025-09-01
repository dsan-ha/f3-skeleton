<?php

namespace App\Controller;

use App\F4;
use App\Http\Response;

abstract class ControllerBase
{
    protected F4 $f3;

    public function __construct()
    {
        $this->f3 = F4::instance();
    }

    protected function beforeRoute($req, Response $res, array $params): bool 
    { 
        return true; //Возвращаем false если есть ошибка
    } 

    protected function render(Response $res, string $pageTemplate, array $arParams = [])
    {
        $app = app();
        $app->setContent('body',$pageTemplate);
        $html = $app->render($arParams);          
        return $res->withBody($html);  
    }

    protected function afterRoute($req, Response $res, array $params): void 
    {}
    
}