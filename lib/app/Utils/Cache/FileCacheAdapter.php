<?php

namespace App\Utils\Cache;

use App\F4;

class FileCacheAdapter implements CacheInterface
{
    protected string $baseDir;

    public function __construct(string $baseDir)
    {
        if($baseDir){
            $this->baseDir = rtrim($baseDir,'/ ') . '/';
        } else {
           throw new \Exception("Don use folder cache"); 
        }
    }

    protected function getPath(string $key, string $folder): string
    {
        $safeFolder = preg_replace('/[^\/a-zA-Z0-9_\-]/', '', str_replace("\\",'/',$folder));
        $safeKey =  str_replace("\\",'/',preg_replace('/[^a-zA-Z0-9_\-]/', '', $key));
        $dir = $this->baseDir;
        if($safeFolder)
            $dir .= trim($safeFolder,'/') . '/';

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        return $dir . $safeKey . '.cache';
    }

    public function set(string $key, string $folder, $value, int $ttl = 0): bool
    {
        $path = $this->getPath($key, $folder);
        $data = serialize([
            'value' => $value,
            'time' => microtime(true),
            'ttl' => $ttl
        ]);
        return file_put_contents($path, $data) !== false;
    }

    public function exists(string $key, string $folder, &$value = null): bool
    {
        $path = $this->getPath($key, $folder);
        if (!file_exists($path)) {
            return false;
        }

        $data = @unserialize(file_get_contents($path));
        if (!is_array($data)) {
            return false;
        }

        $now = microtime(true);
        if ($data['ttl'] === 0 || $data['time'] + $data['ttl'] > $now) {
            $value = $data['value'];
            return true;
        }

        @unlink($path); // TTL истёк
        return false;
    }

    public function get(string $key, string $folder, $def = null)
    {
        return $this->exists($key, $folder, $value) ? $value : $def;
    }

    public function clear(string $key, string $folder): bool
    {
        $path = $this->getPath($key, $folder);
        return @unlink($path);
    }

    public function clearFolder(string $folder): void
    {
        $dir = $this->baseDir . preg_replace('/[^a-zA-Z0-9_\-]/', '', $folder) . '/';
        if (is_dir($dir)) {
            foreach (glob($dir . '*.cache') as $file) {
                @unlink($file);
            }
        }
    }
}
