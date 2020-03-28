<?php

/**
 * BitFrame Framework (https://www.bitframephp.com)
 *
 * @author    Daniyal Hamid
 * @copyright Copyright (c) 2017-2019 Daniyal Hamid (https://designcise.com)
 * @license   https://bitframephp.com/about/license MIT License
 */

declare(strict_types=1);

namespace BitFrame\FastRoute\Exception;

/**
 * Represents an HTTP 405 error.
 */
class MethodNotAllowedException extends HttpException
{
    /**
     * @param string $method Name of the method
     */
    public function __construct(string $method)
    {
        parent::__construct(\sprintf('Method "%s" Not Allowed', $method), 405);
    }
}