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
    /**
     * @param string $message
     * @param int $code Status code
     */
    public function __construct(string $message, int $code = 500)
    {
        http_response_code($code);
        parent::__construct($message, $code);
    }
}
