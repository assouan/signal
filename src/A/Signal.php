<?php

declare(strict_types=1);

namespace A;

class Signal
{
    protected array $statics = [];

    protected \WeakMap $dynamics;

    public function __construct()
    {
        $this->dynamics = new \WeakMap();
    }

    public function connect(callable $callable) : bool
    {
        if (is_array($callable) and is_object($callable[0]))
        {
            $this->dynamics[$callable[0]] = $callable[1];
        }
        else
        {
            $this->statics[static::callable_key($callable)] = $callable;
        }

        return true;
    }

    public function disconnect(callable $callable) : bool
    {
        if (is_array($callable) and is_object($callable[0]))
        {
            unset($this->dynamics[$callable[0]]);
        }
        else
        {
            unset($this->statics[static::callable_key($callable)]);
        }

        return true;
    }

    public function __invoke(mixed ...$args) : array
    {
        $promises = [];

        foreach ($this->statics as $callable)
        {
            $promises[] = async(static function () use ($callable, $args)
            {
                return $callable(...$args);
            });
        }

        foreach ($this->dynamics as $object => $method)
        {
            $promises[] = async(static function () use ($object, $method, $args)
            {
                return $object->$method(...$args);
            });
        }

        return $promises;
    }

    protected static function callable_key(callable $callable) : string
    {
        if (is_array($callable))
        {
            if (is_object($callable[0]))
            {
                return spl_object_id($callable[0]) . ':' . $callable[1];
            }

            return $callable[0] . ':' . $callable[1];
        }

        if (is_object($callable))
        {
            return (string)spl_object_id($callable);
        }

        if (is_string($callable))
        {
            return $callable;
        }

        throw new \InvalidArgumentException('Unsupported callable type');
    }
}
