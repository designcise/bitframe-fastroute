<?php

/**
 * BitFrame Framework (https://www.bitframephp.com)
 *
 * @author    Daniyal Hamid
 * @copyright Copyright (c) 2017-2020 Daniyal Hamid (https://designcise.com)
 * @license   https://bitframephp.com/about/license MIT License
 */

declare(strict_types=1);

namespace BitFrame\FastRoute;

use ReflectionMethod;
use Psr\Http\Message\{ServerRequestInterface, ResponseInterface};
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use InvalidArgumentException;

use function array_shift;
use function sprintf;
use function is_object;
use function get_class;

/**
 * Creates a new Controller with specified arguments.
 */
class ControllerFactory
{
    public static function from(string $className, string $method, ...$args): callable
    {
        if (! method_exists($className, $method)) {
            throw new RuntimeException(sprintf(
                '"%s::%s()" does not exist', $className, $method
            ));
        }

        $reflection = new ReflectionMethod($className, $method);

        if ($reflection->isStatic()) {
            return self::fromCallable([$className, $method], $args);
        }

        return [new $className(...$args), $method];
    }

    public static function fromArray(array $controller): callable
    {
        if (! isset($controller[0], $controller[1])) {
            throw new InvalidArgumentException(
                "Array should have the class/object and method name as first arguments"
            );
        }

        $classOrObj = array_shift($controller);
        $method = array_shift($controller);

        if (! method_exists($classOrObj, $method)) {
            throw new RuntimeException(sprintf(
                '"%s::%s()" does not exist',
                (is_object($classOrObj)) ? get_class($classOrObj) : (string) $classOrObj,
                $method
            ));
        }

        return self::fromCallable([$classOrObj, $method], $controller);
    }

    public static function fromCallable(callable $controller, array $args = []): callable
    {
        if (empty($args)) {
            return $controller;
        }

        return fn (ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface => (
            $controller($request, $handler, ...$args)
        );
    }
}
