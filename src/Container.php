<?php

namespace Webcomcafe\Service;

use Psr\Container\ContainerInterface;
use ReflectionParameter;
use ReflectionException;
use Closure;
use ReflectionFunction;
use Webcomcafe\Service\Exceptions\NotFoundException;

/**
 * Service Container para injeção de deped~encias
 *
 * @author Airton Lopes <airtonlopes_@hotmail.com>
 * @package Webcomcafe\Service
 */

abstract class Container implements ContainerInterface
{
    /**
     * Coleção de serviços resolvidos
     *
     * @var array $container
     */
    protected array $container = [];


    /**
     * Argumentos previamente definidos para uso no
     * instance em que um serviço for solicitado.
     *
     * @var array $arguments
     */
    private array $arguments = [];


    /**
     * Define um serviço a ser resolvido
     *
     * @param string $id
     * @param $resolve
     * @throws ReflectionException
     */
    public function bind(string $id, $resolve) : void
    {
        $this->set($id, $resolve);
    }


    /**
     * Define argumentos para métodos de classes
     *
     * @param string $id
     * @param array $arguments
     */
    public function args(string $id, array $arguments) : void
    {
        foreach ($arguments as $method => $args) {
            $current = $this->arguments[$id][$method] ?? [];
            $this->arguments[$id][$method] = array_merge($current, $args);
        }
    }


    /**
     * Constróe um serviço e o retorna com suas dependecias resolvidas
     *
     * @param string $id
     * @return mixed|object
     * @throws NotFoundException
     * @throws ReflectionException
     */
    public function get($id)
    {
        if( $this->has($id) )
            return $this->container[$id]($this);

        $ref = $this->reflection($id);
        $constructor = $ref->getConstructor();

        if( null == $constructor)
            return new $id();

        $arguments = $this->getArgs($id, '__construct');
        $resolvedArgs = $this->resolveArguments($constructor->getParameters(), $arguments);

        return $ref->newInstanceArgs($resolvedArgs);
    }


    /**
     * Constrói um serviço, mantém sua instância pronta, e retornando-a
     *
     * @param string $id
     * @param Closure|null $callable
     * @return object
     * @throws ReflectionException
     */
    public function make(string $id, $callable = null) : object
    {
        return $this->container[$id] = $callable ? $callable($this) : $this->get($id);
    }


    /**
     * Define uma instância a ser recuperada
     *
     * @param string $id
     * @param $resolve
     */
    private function set(string $id, $resolve) : void
    {
        $this->container[$id] = $this->makeClosure($resolve);
    }


    /**
     * Seta uma definição como permanente, fazendo com que seja sempre
     * retornada a mesma instância da classe definida
     *
     * @example Singleton
     *
     * @param string $id
     * @param $resolver
     */
    public function singleton(string $id, $resolver)
    {
        $service = $this->makeClosure($resolver);

        $this->container[$id] = function() use ($service) {
            static $instance;

            if( null === $instance )
                $instance = $service($this);

            return $instance;
        };
    }


    /**
     * Garante uma definição de closure
     *
     * @param $resolve
     * @return Closure
     */
    private function makeClosure($resolve) : Closure
    {
        return ($resolve instanceof Closure) ? $resolve : fn($c) => $c->get($resolve);
    }


    /**
     * Retorna argumentos de um método de uma determinada classe
     *
     * @param string $id
     * @param string $method
     * @return array|mixed
     */
    private function getArgs(string $id, string $method) : array
    {
        return $this->arguments[$id][$method] ?? [];
    }


    /**
     * Executa uma closure ou um método de um objeto, resolvendo as depedências
     * de seus parâmetros
     *
     * @param $callable
     * @param array $arguments
     * @return mixed
     * @throws NotFoundException
     * @throws ReflectionException
     */
    public function call($callable, array $arguments = [])
    {
        $class = $this->reflection(is_array($callable) ? $callable[0] : $callable);

        if( $class instanceof ReflectionFunction )
        {
            $resolvedArgs = $this->resolveArguments (
                $class->getParameters(),
                $arguments
            );

            return $callable(...$resolvedArgs);
        }

        [$id, $method] = $callable;
        $arguments = $arguments ? $arguments : $this->arguments[$id][$method] ?? [];

        $resolvedArgs = $this->resolveArguments (
            $class->getMethod($method)->getParameters(),
            $arguments
        );

        $instance = is_object($id) ? $id : $this->get($id);
        return $instance->$method(...$resolvedArgs);
    }


    /**
     * Verifica se um id foi definido
     *
     * @param $id
     * @return bool
     */
    public function has($id) : bool
    {
        return isset($this->container[$id]);
    }


    /**
     * Resolve dependências de argumentos
     *
     * @param ReflectionParameter[] $parameters
     * @param array $defaults
     * @return array
     * @throws NotFoundException
     * @throws ReflectionException
     */
    private function resolveArguments(array $parameters, array $defaults = []) : array
    {
        $callable = function(ReflectionParameter $parameter) use (&$defaults, &$count) {
            $name  = $parameter->getName();
            $class = ($c=$parameter->getClass()) ? $c->getName() : null;
            $order = $parameter->getPosition() - $count;

            if( null == $class )
            {
                if( $value = $this->array_shift($defaults, $order)) {
                    return $value;
                }

                if( $parameter->isDefaultValueAvailable() )
                    return $parameter->getDefaultValue();

                throw new NotFoundException('Parameter {'.$name.'} not found');
            }

            //
            // Seu uma classe for requisitada e se $defaults contiver
            // um objeto pronto na posição atual de $order, então este objeto é retornado.
            // isso faz com que o nosso container obrigue o método de destino receber exatamente
            // o tipo do objeto atual que está sendo retornado.
            //
            // Caso o valor da posição atual $order em $defaults não seja um objeto,
            // então esse valor será ignorado, e uma instancia de $class será retornada
            //
            if( isset( $defaults[$order] ) && is_object($defaults[$order])) {
                return $defaults[$order];
            }

            $count++;
            return $this->get($class);
        };

        $count = 0;
        return array_map($callable, $parameters);
    }

    /**
     * array_shift sem reordenar as chaves
     *
     * @param array $data
     * @param int $index
     * @return mixed
     */
    private function array_shift(array &$data, int $index)
    {
        if( isset($data[$index]) )
        {
            $value = $data[$index];
            unset($data[$index]);
            return $value;
        }
    }


    /**
     * Instancia uma reflection de classe ou closure e retorna
     *
     * @param $objectOrdClass
     * @return \ReflectionClass|\ReflectionFunction
     * @throws \ReflectionException
     */
    private function reflection($objectOrdClass)
    {
        if( $objectOrdClass instanceof \Closure) {
            return new ReflectionFunction($objectOrdClass);
        }
        return new \ReflectionClass($objectOrdClass);
    }
}