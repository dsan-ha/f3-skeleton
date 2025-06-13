<?php
// App/Component/BaseComponent.php

namespace App\Component;

use App\F3;
use App\Utils\Assets;
use Template;

abstract class BaseComponent {
    protected F3 $f3;
    protected string $folder;
    protected string $templateName;
    protected array $arParams = [];
    protected array $arResult = [];

    public function __construct(F3 $f3, string $templateName, string $folder, array $arParams = [])
    {
        $this->f3 = $f3;
        $this->templateName = $templateName;
        $this->folder = $folder;
        $this->arParams = $arParams;
    }

    // Метод для переопределения: логика компонента
    abstract public function execute(): void;

    protected function includeStyleScript(): void {
        $assets = Assets::instance();
        $stylePath = $this->getUIPath($this->folder.'style.css', true);
        $scriptPath = $this->getUIPath($this->folder.'script.js', true);
        if(!empty($stylePath))
            $assets->addCss($stylePath);
        if(!empty($scriptPath))
            $assets->addJs($scriptPath);
    }

    // Метод рендеринга шаблона
    public function render(array $arParams = []): string
    {
        $template = template();
        foreach ($this->arParams as $key => $val) {
            $arParams[$key] = $val;
        }
        $templatePath = $this->folder.'template.php';
        $template->set('arResult',$this->arResult);
        $template->set('templateFolder',$this->getUIPath($this->folder, true));
        $template->set('templateName',$this->templateName);
        $template->set('component',$this);
        $this->includeStyleScript();
        return $template->render($templatePath, $arParams);
    }

    protected function getUIPath($path, bool $uri = false){
        $hive = $this->f3->get('UI');
        $roots = $hive ? explode(',', $hive) : [];

        foreach ($roots as $root) {
            $uriPath =  trim($root, '/\\') . '/' . ltrim($path, '/\\');
            $path = SITE_ROOT . $uriPath;
            if (is_file($path) || is_dir($path)) {
                return $uri?('/'.$uriPath):$path;
            }
        }
        return '';
    }
}
