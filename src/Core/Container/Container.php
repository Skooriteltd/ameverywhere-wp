<?php

namespace AmEveryWhere\Core\Container;

if (!defined('ABSPATH')) {
    exit;
}

class Container
{
    private array $instances = [];
    private array $bindings = [];

    public function singleton(string $abstract, $concrete = null): void
    {
        if ($concrete === null) {
            $concrete = $abstract;
        }

        if ($concrete instanceof \Closure) {
            $this->bindings[$abstract] = $concrete;
        } else {
            $this->bindings[$abstract] = function () use ($concrete) {
                return new $concrete();
            };
        }
    }

    public function get(string $abstract)
    {
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        if (isset($this->bindings[$abstract])) {
            $this->instances[$abstract] = $this->bindings[$abstract]();
            return $this->instances[$abstract];
        }

        throw new ContainerException("No binding found for {$abstract}");
    }
}
