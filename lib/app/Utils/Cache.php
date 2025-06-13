<?php

namespace App\Utils;

use App\Utils\Cache\CacheInterface;

class Cache extends \Prefab implements CacheInterface 
{
    protected CacheInterface $adapter;

    public function __construct(CacheInterface $adapter)
    {
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
