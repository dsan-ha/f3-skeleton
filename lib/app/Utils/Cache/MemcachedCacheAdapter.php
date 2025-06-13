<?php

namespace App\Utils\Cache;

use App\F3;

class MemcachedCacheAdapter implements CacheInterface
{
    protected \Memcached $client;

    public function __construct()
    {
        $f3 = F3::instance();
        $host = $f3->g('cache.memcached_host','127.0.0.1');
        $port = $f3->g('cache.memcached_port',11211);
        $this->client = new \Memcached();
        foreach ($servers as $server) {
            $this->client->addServer($host, $port);
        }
    }

    protected function makeKey(string $key, string $folder): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-]/', '', $folder) . '.' . preg_replace('/[^a-zA-Z0-9_\-]/', '', $key);
    }

    public function set(string $key, string $folder, $value, int $ttl = 0): bool
    {
        $data = [
            'value' => $value,
            'time' => microtime(true),
            'ttl' => $ttl
        ];
        return $this->client->set($this->makeKey($key, $folder), $data, $ttl);
    }

    public function exists(string $key, string $folder, &$value = null): bool
    {
        $data = $this->client->get($this->makeKey($key, $folder));
        if (!is_array($data) || !isset($data['value'], $data['time'], $data['ttl'])) {
            return false;
        }

        if ($data['ttl'] === 0 || $data['time'] + $data['ttl'] > microtime(true)) {
            $value = $data['value'];
            return true;
        }

        $this->clear($key, $folder);
        return false;
    }

    public function get(string $key, string $folder, $def = null)
    {
        return $this->exists($key, $folder, $value) ? $value : $def;
    }

    public function clear(string $key, string $folder): bool
    {
        return $this->client->delete($this->makeKey($key, $folder));
    }

    public function clearFolder(string $folder): void
    {
        // Memcached не поддерживает списки ключей по маске — сбрасываем всё
        $this->client->flush();
    }
}
