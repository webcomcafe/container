<?php

namespace Webcomcafe\Container\Exceptions;

class NotFoundException extends ContainerException
{
    /**
     * @var int $code
     */
    protected $code = 500;

    /**
     * @var string $message
     */
    protected $message = 'Service not found';
}