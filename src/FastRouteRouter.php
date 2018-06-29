<?php

/**
 * BitFrame Framework (https://www.bitframephp.com)
 *
 * @author    Daniyal Hamid
 * @copyright Copyright (c) 2017-2018 Daniyal Hamid (https://designcise.com)
 *
 * @license   https://github.com/designcise/bitframe/blob/master/LICENSE.md MIT License
 */

namespace BitFrame\Router;

use \Psr\Http\Message\{ServerRequestInterface, ResponseInterface};
use \Psr\Http\Server\RequestHandlerInterface;

use \FastRoute\Dispatcher;
use \FastRoute\RouteCollector;

use BitFrame\Application;
use BitFrame\Delegate\CallableMiddlewareTrait;
use BitFrame\Dispatcher\DispatcherAwareTrait;
use BitFrame\Router\{Route, RouterTrait, RouterInterface, RouteCollection, RouteCollectionInterface};

/**
 * FastRoute wrapper class to manage http routes
 * as a middleware.
 */
class FastRouteRouter implements RouterInterface
{
    use RouterTrait;
    use CallableMiddlewareTrait;
    use DispatcherAwareTrait;
    
    /** @var \FastRoute\RouteCollector */
    private $routeCollector;

    
    public function __construct()
    {
        $this->response = null;
        
        $this->routeCollector = new RouteCollector(
            new \FastRoute\RouteParser\Std, new \FastRoute\DataGenerator\GroupCountBased
        );
    }
    
    /**
     * {@inheritdoc}
     *
     * @throws \FastRoute\BadRouteException
     */
    public function process(
        ServerRequestInterface $request, 
        RequestHandlerInterface $handler
    ): ResponseInterface 
    {
        // add routes (if any specified directly on router object)
        foreach ($this->getRouteCollection()->getData() as $route) {
            $this->routeCollector->addRoute($route->getMethods(), $route->getPath(), $route->getCallable());
        }
        
        // add routes (if any specified directly via the application object)
        if (! empty($appRoutes = $request->getAttribute(RouteCollectionInterface::class, []))) {
            // add routes (if any specified directly on router object)
            foreach ($appRoutes->getData() as $route) {
                $this->routeCollector->addRoute($route->getMethods(), $route->getPath(), $route->getCallable());
            }
        }
        
        // process routes
        $dispatcher = new \FastRoute\Dispatcher\GroupCountBased($this->routeCollector->getData());

        $reqUriPath = $request->getUri()->getPath();
        
        // workaround for when using subfolders as the root folder; this would make 
        // folder containing the main 'index.php' file the root, which is the expected
        // behavior
        if (($index = strpos($_SERVER['PHP_SELF'], '/index.php')) !== false && $index > 0) {
            $script_url = strtolower(substr($_SERVER['PHP_SELF'], 0, $index));
            $reqUriPath = '/' . trim(str_replace(['/index.php', $script_url], '', $reqUriPath), '/');
        }
        
        // dispatch request; returns:
            // 1: int $route[0] Route resolution status 
            // 2: callable $route[1] Route callback|array
            // 3: vars $routeInfo[2] Route params
        $route = $dispatcher->dispatch($request->getMethod(), $reqUriPath);
        
        // manage request/response
        if ($route[0] === Dispatcher::FOUND) {
            // serve route params as http request attributes
            foreach ($route[2] as $name => $value) {
                $request = $request->withAttribute($name, $value);
            }
            
            $dispatcher = $this->getDispatcher();
            
            // execute any/all router middleware queued up in 'dispatcher'
            $this->response = $dispatcher
                                // 1: first share application-level response (so far) with router
                                ->setResponse($handler->getResponse())
                                // 2: add route as middleware (to the front of middleware queue)
                                ->prependMiddleware($route[1])
                                // 3: then proceed to handling router-level middleware + route itself
                                ->handle($request);
            
            // 4: update handler's response to match response generated from router + middleware
            $handler->setResponse($this->response);
            
            return $handler->handle($request);
        }
        
        // delegate not found and method not allowed to middleware dispatcher
        return $this->getResponse()->withStatus(($route[0] === Dispatcher::METHOD_NOT_ALLOWED) ? Application::STATUS_METHOD_NOT_ALLOWED : Application::STATUS_NOT_FOUND);
    }
    
    /**
     * Get Router class.
     *
     * @return RouteCollector
     */
    public function getRouter(): RouteCollector
    {
        return $this->routeCollector;
    }
}