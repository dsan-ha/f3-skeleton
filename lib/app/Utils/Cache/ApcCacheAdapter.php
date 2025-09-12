<?php

namespace App\Utils\Cache;

class ApcCacheAdapter implements CacheInterface
{
    protected bool $apcu;

    public function __construct()
    {
        if (extension_loaded('apcu') && ini_get('apc.enabled')) {
            $this->apcu = true;
        } elseif (extension_loaded('apc') && ini_get('apc.enabled')) {
            $this->apcu = false;
        } else {
            throw new \RuntimeException('Neither APC nor APCu extension is available or enabled.');
        }
    }

    protected function makeKey(string $key, string $folder): string
    {
        $safeFolder = preg_replace('/[^a-zA-Z0-9_\-]/', '', str_replace("\\/",'_',$folder));
        $safeKey = preg_replace('/[^a-zA-Z0-9_\-]/', '', $key);
        return $safeFolder . '.' . $safeKey;
    }

    public function set(string $key, string $folder, $value, int $ttl = 0): bool
    {
        $store = [
            'value' => $value,
            'time' => microtime(true),
            'ttl' => $ttl
        ];
        $k = $this->makeKey($key, $folder);

        return $this->apcu
            ? apcu_store($k, $store, $ttl)
            : apc_store($k, $store, $ttl);
    }

    public function exists(string $key, string $folder, &$value = null): bool
    {
        $k = $this->makeKey($key, $folder);

        $data = $this->apcu
            ? apcu_fetch($k)
            : apc_fetch($k);

        if (!is_array($data) || !isset($data['value'], $data['time'], $data['ttl'])) {
            return false;
        }

        $now = microtime(true);
        if ($data['ttl'] === 0 || $data['time'] + $data['ttl'] > $now) {
            $value = $data['value'];
            return true;
        }

        $this->clear($key, $folder); // TTL истёк
        return false;
    }

    public function get(string $key, string $folder, $def = null)
    {
        return $this->exists($key, $folder, $value) ? $value : $def;
    }

    public function clear(string $key, string $folder): bool
    {
        $k = $this->makeKey($key, $folder);

        return $this->apcu
            ? apcu_delete($k)
            : apc_delete($k);
    }

    public function clearFolder(string $folder): void
    {
        // Очистка по префиксу требует перебора всех ключей
        $pattern = '/^' . preg_quote($this->makeKey('', $folder)) . '/';

        if ($this->apcu) {
            $info = apcu_cache_info();
        } else {
            $info = apc_cache_info('user');
        }

        if (!empty($info['cache_list'])) {
            foreach ($info['cache_list'] as $entry) {
                $key = $entry['info'] ?? $entry['key'] ?? null;
                if ($key && preg_match($pattern, $key)) {
                    $this->apcu ? apcu_delete($key) : apc_delete($key);
                }
            }
        }
    }
}
