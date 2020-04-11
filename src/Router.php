<?php

/**
 * BitFrame Framework (https://www.bitframephp.com)
 *
 * @author    Daniyal Hamid
 * @copyright Copyright (c) 2017-2019 Daniyal Hamid (https://designcise.com)
 * @license   https://bitframephp.com/about/license MIT License
 */

declare(strict_types=1);

namespace BitFrame\FastRoute;

use Psr\Http\Message\{ServerRequestInterface, ResponseInterface};
use Psr\Http\Server\{MiddlewareInterface, RequestHandlerInterface};
use BitFrame\Router\AbstractRouter;

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

        if ($this->isClassName($route[0])) {
            $route[0] = $this->addControllerActionFromPath($route[0], $path);
        }

        $routeAsMiddleware = $this->getDecoratedMiddleware($route[0]);
        return $routeAsMiddleware->process($request, $handler);
    }

    /**
     * @return RouteCollection
     */
    public function getRouteCollection(): RouteCollection
    {
        return $this->routeCollection;
    }

    /**
     * @param mixed $routeHandler
     *
     * @return boolean
     */
    protected function isClassName($routeHandler): bool
    {
        return is_string($routeHandler)
            && strpos($routeHandler, '::') === false
            && class_exists($routeHandler);
    }
}