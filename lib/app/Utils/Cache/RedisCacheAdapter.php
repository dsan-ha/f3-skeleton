<?php

namespace App\Utils\Cache;

use App\F4;

class RedisCacheAdapter implements CacheInterface
{
    protected \Redis $redis;

    public function __construct()
    {
        $f3 = F4::instance();
        $host = $f3->g('cache.redis_host','127.0.0.1');
        $port = $f3->g('cache.redis_port',6379);
        $db = $f3->g('cache.redis_db',0);
        $this->redis = new \Redis();
        $this->redis->connect($host, $port);
        $this->redis->select($db);
    }

    protected function makeKey(string $key, string $folder): string
    {
        return preg_replace('/[^a-zA-Z0-9_\-]/', '', $folder) . '.' . preg_replace('/[^a-zA-Z0-9_\-]/', '', $key);
    }

    public function set(string $key, string $folder, $value, int $ttl = 0): bool
    {
        $k = $this->makeKey($key, $folder);
        $data = serialize([
            'value' => $value,
            'time' => microtime(true),
            'ttl' => $ttl
        ]);
        return $ttl > 0
            ? $this->redis->setex($k, $ttl, $data)
            : $this->redis->set($k, $data);
    }

    public function exists(string $key, string $folder, &$value = null): bool
    {
        $k = $this->makeKey($key, $folder);
        $raw = $this->redis->get($k);
        $data = @unserialize($raw);

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
        return (bool)$this->redis->del($this->makeKey($key, $folder));
    }

    public function clearFolder(string $folder): void
    {
        $pattern = $this->makeKey('*', $folder);
        $it = null;
        while ($keys = $this->redis->scan($it, $pattern)) {
            foreach ($keys as $key) {
                $this->redis->del($key);
            }
        }
    }
}
