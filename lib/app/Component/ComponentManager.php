<?php

namespace App\Component;

use App\F3;

class ComponentManager {
    protected F3 $f3;
    protected string $componentDir;
    protected string $uiDir;

    public function __construct(F3 $f3, string $uiDir = 'components/')
    {
        $this->f3 = $f3;
        $this->uiDir = trim($uiDir, '/') . '/';
    }

    public function run(string $name, string $template = '.default', array $params = []): string
    {
        $folder = '';
        $compName = '';
        if( strpos($name,':') !== false){
            list($folder,$compName) = explode(':', $name);
            if(empty($folder) || empty($compName)) throw new \RuntimeException("Error name component.");
        } else {
            throw new \RuntimeException("Component logic name - 'folder:my.component'.");
        }
        self::checkName($folder,$compName,$template);
        $nameClass = self::componentNameToClass($compName, 'Component');
        $nameFolderClass = self::componentNameToClass($folder);
        $templateFolder = $this->uiDir . $folder . '/' . $compName . '/' .  $template . '/';

        $className = '\\App\\Component\\' . $nameFolderClass . '\\' . $nameClass; 

        if (!class_exists($className)) {
            throw new \RuntimeException("Component class '$className' not defined.");
        }

        $component = new $className($this->f3, $template, $templateFolder, $params);
        $component->execute();

        return $component->render();
    }

    protected static function componentNameToClass(string $name, string $after = ''): string {
        $parts = explode('.', $name);
        $parts = array_map('ucfirst', $parts);
        return implode('', $parts) . $after;
    }

    protected static function checkName(string $folder, string $compName, string $template) {
        if (strlen($folder) > 20 || strlen($compName) > 40 || strlen($template) > 40) {
            throw new \InvalidArgumentException("Component name or template too long: max 100 characters");
        }
        if (empty($folder) || empty($compName)) {
            throw new \InvalidArgumentException("Component name empty string");
        }
        if (!preg_match('/^[a-z0-9\.]+$/', $folder) || !preg_match('/^[a-z0-9\.]+$/', $compName)) {
            throw new \InvalidArgumentException("Invalid component name or folder name: only lowercase letters, digits, dot allowed - example ds.form");
        }
        if (!preg_match('/^[a-zA-Z0-9_\.-]+$/', $template)) {
            throw new \InvalidArgumentException("Invalid component template: only letters, digits, dot, underscore and dash allowed");
        }
    }
}
