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

use Psr\Http\Message\{ServerRequestInterface, ResponseInterface};
use Psr\Http\Server\{MiddlewareInterface, RequestHandlerInterface};
use BitFrame\Router\AbstractRouter;
use TypeError;
use RuntimeException;

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
    public function map($methods, string $path, $handler)
    {
        $this->routeCollection->add((array) $methods, $path, $handler);
    }

    /**
     * {@inheritdoc}
     */
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $method = $request->getMethod();
        $path = $request->getUri()->getPath();

        $route = $this->routeCollection->getRouteData($method, $path);

        // serve route params as http request attributes
        foreach ($route[1] as $name => $value) {
            $request = $request->withAttribute($name, $value);
        }

        try {
            $routeAsMiddleware = $this->getDecoratedMiddleware($route[0]);
        } catch (TypeError $e) {
            throw new RuntimeException('Route controller is invalid or does not exist');
        }

        return $routeAsMiddleware->process($request, $handler);
    }

    /**
     * @return RouteCollection
     */
    public function getRouteCollection(): RouteCollection
    {
        return $this->routeCollection;
    }
}
