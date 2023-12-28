<?php

declare(strict_types=1);

namespace Spiral\Core\Internal\Proxy;

use Spiral\Core\ContainerScope;
use Spiral\Core\Exception\Container\ContainerException;

/**
 * @internal
 */
trait ProxyTrait
{
    private static string $__container_proxy_alias;
    private \Stringable|string|null $__container_proxy_context = null;

    public function __call(string $name, array $arguments)
    {
        return Resolver::resolve(static::$__container_proxy_alias, $this->__container_proxy_context)
            ->$name(...$arguments);
    }

    public static function __callStatic(string $name, array $arguments)
    {
        return Resolver::resolve(static::$__container_proxy_alias)
            ->$name(...$arguments);
    }
}
