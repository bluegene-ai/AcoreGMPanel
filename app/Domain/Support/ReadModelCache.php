<?php

declare(strict_types=1);

namespace Acme\Panel\Domain\Support;

use Acme\Panel\Support\TransientCache;

final class ReadModelCache
{
    private array $requestCache = [];

    public function __construct(private string $scope)
    {
    }

    public function remember(string $namespace, string $key, int $ttlSeconds, callable $resolver)
    {
        $requestKey = $this->requestKey($namespace, $key);
        if (array_key_exists($requestKey, $this->requestCache))
            return $this->requestCache[$requestKey];

        $value = TransientCache::remember(
            $this->persistentNamespace($namespace),
            $key,
            $ttlSeconds,
            $resolver
        );

        $this->requestCache[$requestKey] = $value;

        return $value;
    }

    public function get(string $namespace, string $key)
    {
        $requestKey = $this->requestKey($namespace, $key);
        if (array_key_exists($requestKey, $this->requestCache))
            return $this->requestCache[$requestKey];

        $value = TransientCache::get($this->persistentNamespace($namespace), $key);
        $this->requestCache[$requestKey] = $value;

        return $value;
    }

    public function set(string $namespace, string $key, $value, int $ttlSeconds): void
    {
        $this->requestCache[$this->requestKey($namespace, $key)] = $value;
        TransientCache::set($this->persistentNamespace($namespace), $key, $value, $ttlSeconds);
    }

    public function forget(string $namespace, string $key): void
    {
        unset($this->requestCache[$this->requestKey($namespace, $key)]);
        TransientCache::delete($this->persistentNamespace($namespace), $key);
    }

    public function clearNamespace(string $namespace): void
    {
        $prefix = $namespace . ':';
        foreach (array_keys($this->requestCache) as $requestKey) {
            if (str_starts_with($requestKey, $prefix))
                unset($this->requestCache[$requestKey]);
        }

        TransientCache::clearNamespace($this->persistentNamespace($namespace));
    }

    private function persistentNamespace(string $namespace): string
    {
        return $this->scope . '_' . $namespace;
    }

    private function requestKey(string $namespace, string $key): string
    {
        return $namespace . ':' . $key;
    }
}