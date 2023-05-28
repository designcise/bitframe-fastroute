<?php

/**
 * BitFrame Framework (https://www.bitframephp.com)
 *
 * @author    Daniyal Hamid
 * @copyright Copyright (c) 2017-2023 Daniyal Hamid (https://designcise.com)
 * @license   https://bitframephp.com/about/license MIT License
 */

declare(strict_types=1);

namespace BitFrame\FastRoute;

use Attribute;
use BitFrame\FastRoute\Test\Asset\Controller;
use ReflectionClass;
use ReflectionMethod;
use Psr\Http\Message\{ServerRequestInterface, ResponseInterface};
use Psr\Http\Server\{MiddlewareInterface, RequestHandlerInterface};
use BitFrame\Router\AbstractRouter;
use TypeError;
use RuntimeException;

use function is_array;

/**
 * FastRoute router to manage http routes as a middleware.
 */
class Router extends AbstractRouter implements MiddlewareInterface
{
    private RouteCollection $routeCollection;

    public function __construct()
    {
        $this->routeCollection = new RouteCollection();
    }

    /**
     * {@inheritdoc}
     *
     * @throws Exception\BadRouteException
     */
    public function map(
        array|string $methods,
        string $path,
        callable|string|array|MiddlewareInterface $handler,
    ): void {
        $handler = (is_array($handler) && isset($handler[0], $handler[1], $handler[2]))
            ? ControllerFactory::fromArray($handler)
            : $handler;

        $this->routeCollection->add((array) $methods, $path, $handler);
    }

    /**
     * {@inheritdoc}
     */
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $method = $request->getMethod();
        $path = $request->getUri()->getPath();

        $route = $this->routeCollection->getRouteData($method, $path);

        // serve route params as http request attributes
        foreach ($route[1] as $name => $value) {
            $request = $request->withAttribute($name, $value);
        }

        try {
            $routeAsMiddleware = $this->createDecoratedMiddleware($route[0]);
        } catch (TypeError) {
            throw new RuntimeException('Route controller is invalid or does not exist');
        }

        return $routeAsMiddleware->process($request, $handler);
    }

    /**
     * @throws \ReflectionException
     */
    public function addRoutes(array $controllers)
    {
        foreach ($controllers as $controller) {
            $reflectionClass = new ReflectionClass($controller);
            $reflectionMethods = $reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC);

            foreach ($reflectionMethods as $method) {
                $attributes = $method->getAttributes(Route::class);

                foreach ($attributes as $attribute) {
                    /** @var Route $route */
                    $route = $attribute->newInstance();
                    $this->map(
                        $route->getMethods(),
                        $route->getPath(),
                        [$controller, $method->getName()],
                    );
                }
            }
        }
    }

    /**
     * @return RouteCollection
     */
    public function getRouteCollection(): RouteCollection
    {
        return $this->routeCollection;
    }
}
