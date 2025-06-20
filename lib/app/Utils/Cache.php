<?php

namespace App\Utils;

use App\Utils\Cache\CacheInterface;
use App\Base\Prefab;
use App\F3;

class Cache extends Prefab implements CacheInterface
{
    protected CacheInterface $adapter;

    public function __construct()
    {
        $f3 = F3::instance();
        $cache_folder = $f3->g('cache.folder','lib/tmp/cache/');
        $adapter = $f3->get('CACHE_ADAPTER');
        if(empty($adapter)) throw new \Exception("Cache adapter not found");
        $this->adapter = $adapter;
    }

    public function set(string $key, string $folder, $value, int $ttl = 0): bool
    {
        return $this->adapter->set($key, $folder, $value, $ttl);
    }

    public function get(string $key, string $folder, $def = null)
    {
        return $this->adapter->get($key, $folder, $def);
    }

    public function exists(string $key, string $folder, &$value = null): bool
    {
        return $this->adapter->exists($key, $folder, $value);
    }

    public function clear(string $key, string $folder): bool
    {
        return $this->adapter->clear($key, $folder);
    }

    public function clearFolder(string $folder): void
    {
        $this->adapter->clearFolder($folder);
    }

    public function adapterName(): string
    {
        return $this->adapter::class;
    }
}
