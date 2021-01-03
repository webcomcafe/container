<?php

namespace Webcomcafe\Service\Exceptions;

use Psr\Container\ContainerExceptionInterface;

class ContainerException extends \Exception implements ContainerExceptionInterface
{
    /**
     * @var int $code
     */
    protected $code = 500;

    /**
     * @var string $message
     */
    protected $message = 'service can\'t resolved' ;
}