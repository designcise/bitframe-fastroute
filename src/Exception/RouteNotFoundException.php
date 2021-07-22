<?php

/**
 * BitFrame Framework (https://www.bitframephp.com)
 *
 * @author    Daniyal Hamid
 * @copyright Copyright (c) 2017-2020 Daniyal Hamid (https://designcise.com)
 * @license   https://bitframephp.com/about/license MIT License
 */

declare(strict_types=1);

namespace BitFrame\FastRoute\Exception;

use function sprintf;

/**
 * Represents a route not found error.
 */
class RouteNotFoundException extends HttpException
{
    public function __construct(string $type)
    {
        parent::__construct(sprintf('Route "%s" cannot be found', $type), 404);
    }
}
