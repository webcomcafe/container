<?php

namespace Webcomcafe\Container;

use Psr\Container\ContainerInterface;
use ReflectionClass;
use Webcomcafe\Container\Exceptions\ContainerException;
use Webcomcafe\Container\Exceptions\NotFoundException;
use Closure;

/**
 * Service Container para resoluão automática de depdências
 *
 * Class Container
 *
 * @author Airton Lopes
 * @package Webcomcafe\Container
 */

abstract class Container implements ContainerInterface
{
    /**
     * Lista de definições a serem resolvidas
     *
     * @var array $container
     */
    protected array $container = [];

    /**
     * Argumentos explicitamente definidos, para serem buscados quando
     * suas respectivas depedências forem resolvidas
     *
     * @var array $arguments
     */
    protected array $arguments = [];

    /**
     * Define uma resolução
     *
     * @param string $key
     * @param Closure|string $resolver
     */
    public function bind(string $key, $resolver)
    {
        $this->container[$key] = $resolver;
    }

    /**
     * Define argumentos de uma determinada classe,
     * geralmente valores primitivos presentes no construtor
     *
     * @param string $key
     * @param array $args
     */
    public function arg(string $key, array $args)
    {
        $this->arguments[$key] = $args;
    }

    /**
     * Verifica se uma definição foi setada
     *
     * @param string $key
     * @return bool
     */
    public function has($key)
    {
        return isset($this->container[$key]);
    }

    /**
     * Recupera um objeto de uma determinada classe,
     * resolvendo todas as suas dependencias
     *
     * @param string $key
     * @param array|Closure $args
     * @return mixed|object
     * @throws ContainerException
     * @throws NotFoundException
     * @throws \ReflectionException
     */
    public function get($key, $args = null)
    {
        if( is_array($args) )
            $this->arg($key, $args);

        if( $this->has($key) )
        {
            $service = $this->container[$key];
            return is_object($service) ? $service($this) : $this->get($service);
        }

        $reflection = new ReflectionClass($key);
        $constructor = $reflection->getConstructor();

        if( null === $constructor )
            return new $key();

        /**
         * Resolve as depdências de cada parâmetro do construtor
         *
         * @param \ReflectionParameter $parameter
         * @return mixed|object
         */
        $resolveParameters = function(\ReflectionParameter $parameter) use ($key)
        {
            $name  = $parameter->getName();
            $class = ($c=$parameter->getClass()) ? $c->getName() : null;
            $value = null;

            if( $parameter->isDefaultValueAvailable() ) {
                $value = $parameter->getDefaultValue();
            }

            if( $explicitValue = $this->getExplicitArg($key, $name)) {
                $value = $explicitValue;
            }

            if( $class ) {
                if( !class_exists($class) )
                    throw new NotFoundException();

                $value = $this->get($class);
            }

            if( null === $value )
                throw new ContainerException('Argument "' . $name . '" no provided to "' .$key. '" instance');

            return $value;
        };

        $args = array_map($resolveParameters, $constructor->getParameters());

        return $reflection->newInstanceArgs($args);
    }

    /**
     * Seta uma definição como permanente, fazendo com que seja sempre
     * retornada a mesma instância da classe definida
     *
     * @example Singleton
     *
     * @param string $key
     * @param $resolver
     */
    public function singleton(string $key, $resolver)
    {
        $this->container[$key] = function() use ($resolver) {
            static $instance;

            if( null === $instance )
                $instance = $resolver($this);

            return $instance;
        };
    }

    /**
     * Recupera um argumento explicitamente definido para uma classe
     *
     * @param string $key
     * @param string $name
     * @return mixed
     */
    protected function getExplicitArg(string $key, string $name)
    {
        $args = $this->arguments[$key] ?? [];
        if( array_key_exists($name, $args) ) {
            return $args[$name];
        }
    }
}