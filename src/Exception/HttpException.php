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

use RuntimeException;

use function http_response_code;

/**
 * Represents an HTTP error.
 */
class HttpException extends RuntimeException
{
    public function __construct(string $message, int $statusCode = 500)
    {
        http_response_code($statusCode);
        parent::__construct($message, $statusCode);
    }
}
