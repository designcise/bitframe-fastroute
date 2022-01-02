<?php

/**
 * BitFrame Framework (https://www.bitframephp.com)
 *
 * @author    Daniyal Hamid
 * @copyright Copyright (c) 2017-2022 Daniyal Hamid (https://designcise.com)
 * @license   https://bitframephp.com/about/license MIT License
 */

declare(strict_types=1);

namespace BitFrame\FastRoute\Exception;

use function sprintf;

/**
 * Represents an HTTP 405 error.
 */
class MethodNotAllowedException extends HttpException
{
    public function __construct(string $method)
    {
        parent::__construct(sprintf('Method "%s" Not Allowed', $method), 405);
    }
}
