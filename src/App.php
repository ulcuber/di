<?php

namespace Di;

use Closure;
use ReflectionClass;
use ReflectionParameter;
use ReflectionFunctionAbstract;

class App
{
    private static $instance;

    /**
     * @var array
     */
    private $services = [];

    /**
     * @var array
     */
    private $instantiated = [];

    /**
     * @var array
     */
    private $shared = [];

    private function __construct()
    {
        $this->addInstance(App::class, $this);
    }

    public static function instance()
    {
        if (null === static::$instance) {
            static::$instance = new static();
        }

        return static::$instance;
    }

    /**
     * instead of supplying a class here, you could also store a service for an interface
     *
     * @param string $class
     * @param object $service
     * @param bool $share
     */
    public function addInstance(string $class, $service, bool $share = true)
    {
        $this->services[$class] = $service;
        $this->instantiated[$class] = $service;
        $this->shared[$class] = $share;

        return static::$instance;
    }

    /**
     * instead of supplying a class here, you could also store a service for an interface
     *
     * @param string $class
     * @param array $params
     * @param bool $share
     */
    public function addClass(string $class, array $params, bool $share = true)
    {
        $this->services[$class] = $params;
        $this->shared[$class] = $share;

        return static::$instance;
    }

    public function addClosure(string $class, Closure $closure, bool $share = true)
    {
        $this->services[$class] = $closure;
        $this->shared[$class] = $share;

        return static::$instance;
    }

    public function has(string $interface): bool
    {
        return isset($this->services[$interface]) || isset($this->instantiated[$interface]);
    }

    /**
     * @param string $class
     *
     * @return object
     */
    public function get(string $class)
    {
        if (isset($this->instantiated[$class]) && $this->shared[$class]) {
            return $this->instantiated[$class];
        }

        if (isset($this->services[$class])) {
            $services = $this->services[$class];
        } else {
            $services = [];
        }

        if ($services instanceof Closure) {
            $object = $services($this);
        } else {
            $reflector = new ReflectionClass($class);

            if (!$reflector->isInstantiable()) {
                return;
            }

            $constructor = $reflector->getConstructor();
            if (is_null($constructor)) {
                $object = new $class;
            } else {
                $object = $reflector->newInstanceArgs(
                    $this->resolveMethodDependencies($services, $constructor)
                );
            }
        }

        if (isset($this->shared[$class])) {
            if ($this->shared[$class]) {
                $this->instantiated[$class] = $object;
            }
        } else {
            $this->shared[$class] = true;
            $this->instantiated[$class] = $object;
        }

        return $object;
    }

    /**
     * Resolve the given method's type-hinted dependencies.
     *
     * @param  array  $parameters
     * @param  \ReflectionFunctionAbstract  $reflector
     * @return array
     */
    public function resolveMethodDependencies(array $parameters, ReflectionFunctionAbstract $reflector)
    {
        $instanceCount = 0;

        $values = array_values($parameters);

        foreach ($reflector->getParameters() as $key => $parameter) {
            $instance = $this->transformDependency(
                $parameter,
                $parameters
            );

            if (! is_null($instance)) {
                $instanceCount++;

                $this->spliceIntoParameters($parameters, $key, $instance);
            } elseif (! isset($values[$key - $instanceCount]) &&
                      $parameter->isDefaultValueAvailable()) {
                $this->spliceIntoParameters($parameters, $key, $parameter->getDefaultValue());
            }
        }

        return $parameters;
    }

    /**
     * Attempt to transform the given parameter into a class instance.
     *
     * @param  \ReflectionParameter  $parameter
     * @param  array  $parameters
     * @return mixed
     */
    private function transformDependency(ReflectionParameter $parameter, $parameters)
    {
        $class = $parameter->getClass();

        // If the parameter has a type-hinted class, we will check to see if it is already in
        // the list of parameters. If it is we will just skip it as it is probably a model
        // binding and we do not want to mess with those; otherwise, we resolve it here.
        if ($class && ! $this->alreadyInParameters($class->name, $parameters)) {
            return $parameter->isDefaultValueAvailable()
                ? $parameter->getDefaultValue()
                : $this->get($class->name);
        }
    }

    /**
     * Determine if an object of the given class is in a list of parameters.
     *
     * @param  string  $class
     * @param  array  $parameters
     * @return bool
     */
    protected function alreadyInParameters($class, array $parameters)
    {
        return in_array(
            true,
            array_map(function ($value) use ($class) {
                return $value instanceof $class;
            }, $parameters)
        );
    }

    /**
     * Splice the given value into the parameter list.
     *
     * @param  array  $parameters
     * @param  string  $offset
     * @param  mixed  $value
     * @return void
     */
    protected function spliceIntoParameters(array &$parameters, $offset, $value)
    {
        array_splice(
            $parameters,
            $offset,
            0,
            [$value]
        );
    }

    /**
     * Handle dynamic static method calls into the method.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return mixed
     */
    public static function __callStatic($method, $parameters)
    {
        return static::instance()->$method(...$parameters);
    }

    private function __clone()
    {
    }

    private function __wakeup()
    {
    }
}
