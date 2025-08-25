<?php
// App/Component/BaseComponent.php

namespace App\Component;

use App\F4;
use App\Utils\Assets;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

abstract class BaseComponent {
    protected F4 $f3;
    protected Assets $assets;
    protected string $folder;
    protected string $templateName;
    protected array $arParams = [];
    protected array $arResult = [];

    public function __construct(F4 $f3, Assets $assets, string $templateName, string $folder, array $arParams = [])
    {
        $this->f3 = $f3;
        $this->assets = $assets;
        $this->templateName = $templateName;
        $this->folder = $folder;
        $this->arParams = $arParams;
    }

    // Метод для переопределения: логика компонента
    abstract public function execute(): void;

    protected function includeStyleScript(): void {
        $assets = $this->assets;
        $stylePath = $this->getUIPath($this->folder.'style.css', true);
        $scriptPath = $this->getUIPath($this->folder.'script.js', true);
        if(!empty($stylePath))
            $assets->addCss($stylePath);
        if(!empty($scriptPath))
            $assets->addJs($scriptPath);
    }

    public function getDefaultParams($params_format = true): array
    {
        $blueprint = $this->getUIPath($this->folder . 'blueprint.yaml', false);
        $arParams = [];
        if (file_exists($blueprint)) {
            $raw = Yaml::parseFile($blueprint);
            if (is_array($raw)) {
                foreach ($raw as $key => $meta) {
                    if (is_array($meta) && array_key_exists('def', $meta)) {
                        $arParams[$key] = $params_format?$meta['def']:$meta;
                    }
                }
            }
        }
        
        if(method_exists($this,'prepareDefaults') && $params_format)
            $arParams = $this->prepareDefaults($params);

        return $arParams;
    }

    // Метод рендеринга шаблона
    public function render(): string
    {
        $template = template();
        $arParams = $this->getDefaultParams();

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
