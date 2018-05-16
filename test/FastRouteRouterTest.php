<?php

/**
 * BitFrame Framework (https://www.bitframephp.com)
 *
 * @author    Daniyal Hamid
 * @copyright Copyright (c) 2017-2018 Daniyal Hamid (https://designcise.com)
 *
 * @license   https://github.com/designcise/bitframe/blob/master/LICENSE.md MIT License
 */

namespace BitFrame\Test;

use \PHPUnit\Framework\TestCase;

use \Psr\Http\Message\{ServerRequestInterface, ResponseInterface};

use \BitFrame\Factory\{HttpMessageFactory, RouteCollectionFactory};
use \BitFrame\Router\RouteCollection;
use \BitFrame\EventManager\Event;

/**
 * @covers \BitFrame\Router\FastRouteRouter
 */
class FastRouteRouterTest extends TestCase
{
    /** @var \Psr\Http\Message\ServerRequestInterface */
    private $request;
    
    /** @var \BitFrame\Router\FastRouteRouter */
    private $router;
    
    public function setUp()
    {
        $this->request = HttpMessageFactory::createServerRequestFromArray();
        $this->router = new \BitFrame\Router\FastRouteRouter();
    }
    
    public function testApplicationAndDirectlyAssignedDuplicateRoutes()
    {
        $collection = RouteCollectionFactory::createRouteCollection();
        
        $collection->get('/', function (ServerRequestInterface $request, ResponseInterface $response) {
            $response->getBody()->write('Response #1');
            return $response;
        });
        
        $router = $this->router;
        
        $router->get('/', function (ServerRequestInterface $request, ResponseInterface $response) {
            $response->getBody()->write('Response #2');
            return $response;
        });
        
        $this->expectException(\FastRoute\BadRouteException::class);
        
        $response = HttpMessageFactory::createResponse();
        $handler = $this->getMockBuilder(\Psr\Http\Server\RequestHandlerInterface::class)->setMethods(['handle', 'getResponse', 'setResponse'])->getMock();
        
        $handler->method('setResponse')->willReturn(null);
        $handler->method('getResponse')->willReturn($response);
        $handler->method('handle')->willReturn($response);
        
        $router->process($this->request->withAttribute(\BitFrame\Router\RouteCollectionInterface::class, $collection), $handler);
    }
    
    public function testDuplicateRoutes()
    {
        $router = $this->router;
        
        $router->get('/', function (ServerRequestInterface $request, ResponseInterface $response) {
            $response->getBody()->write('Response #1');
            return $response;
        });
        
        $router->get('/', function (ServerRequestInterface $request, ResponseInterface $response) {
            $response->getBody()->write('Response #2');
            return $response;
        });
        
        $this->expectException(\FastRoute\BadRouteException::class);
        
        $response = HttpMessageFactory::createResponse();
        $handler = $this->getMockBuilder(\Psr\Http\Server\RequestHandlerInterface::class)->setMethods(['handle', 'getResponse', 'setResponse'])->getMock();
        
        $handler->method('setResponse')->willReturn(null);
        $handler->method('getResponse')->willReturn($response);
        $handler->method('handle')->willReturn($response);
        
        $router->process($this->request, $handler);
    }
    
    public function testProcessRoutes()
    {
        $router = $this->router;
        
        $router->get('/', function (ServerRequestInterface $request, ResponseInterface $response) {
            $response->getBody()->write('Hello World!');
            return $response;
        });
        
        $response = HttpMessageFactory::createResponse();
        $handler = $this->getMockBuilder(\Psr\Http\Server\RequestHandlerInterface::class)->setMethods(['handle', 'getResponse', 'setResponse'])->getMock();
        
        $handler->method('setResponse')->willReturn(null);
        $handler->method('getResponse')->willReturn($response);
        $handler->method('handle')->willReturn($response);
        
        $response = $router->process($this->request, $handler);
        $output = $this->readStream($response->getBody());
        
        $this->assertSame('Hello World!', $output);
    }
    
    
    public function testRouterMiddlewareAndEvents()
    {
        $router = $this->router;
        
        $router->get('/', function (ServerRequestInterface $request, ResponseInterface $response, callable $next) {
            $response->getBody()->write('[Main]');
            return $next($request, $response);
        });
        
        $router
            ->attach('before.dispatch', function ($event) {
                $this->assertInstanceOf(Event::class, $event);

                $data = $event->getParams();

                $this->assertInstanceOf(ResponseInterface::class, $data['response']);
                
                $data['response']->getBody()->write('Before');
            })
            ->attach('after.dispatch', function ($event) {
                $this->assertInstanceOf(Event::class, $event);

                $data = $event->getParams();

                $this->assertInstanceOf(ResponseInterface::class, $data['response']);
                
                $data['response']->getBody()->write('After;');
            })
            ->attach('done.dispatch', function ($event) {
                $this->assertInstanceOf(Event::class, $event);

                $data = $event->getParams();

                $this->assertInstanceOf(ResponseInterface::class, $data['response']);
                
                $data['response']->getBody()->write(';Done');
            })
            ->addMiddleware([
                function (ServerRequestInterface $request, ResponseInterface $response, callable $next) {
                    $response->getBody()->write('[Child #1]');

                    return $next($request, $response);
                },
                function (ServerRequestInterface $request, ResponseInterface $response, callable $next) {
                    $response->getBody()->write('[Child #2]');

                    return $next($request, $response);
                }
            ]);
        
        $response = HttpMessageFactory::createResponse();
        $handler = $this->getMockBuilder(\Psr\Http\Server\RequestHandlerInterface::class)->setMethods(['handle', 'getResponse', 'setResponse'])->getMock();
        
        $handler->method('setResponse')->willReturn(null);
        $handler->method('getResponse')->willReturn($response);
        $handler->method('handle')->willReturn($response);
        
        $response = $router->process($this->request, $handler);
        $output = $this->readStream($response->getBody());
        
        $this->assertSame('Before[Main]After;Before[Child #1]After;Before[Child #2]After;;Done;Done;Done', $output);
    }
    
    
    public function testMethodNotAllowedException()
    {
        $router = $this->router;
        
        $router->map('GET', '/', function (ServerRequestInterface $request, ResponseInterface $response) {
            return $response->withStatus(405);
        });
        
        $this->expectException(\BitFrame\Exception\MethodNotAllowedException::class);
        
        $response = HttpMessageFactory::createResponse();
        $handler = $this->getMockBuilder(\Psr\Http\Server\RequestHandlerInterface::class)->setMethods(['handle', 'getResponse', 'setResponse'])->getMock();
        
        $handler->method('setResponse')->willReturn(null);
        $handler->method('getResponse')->willReturn($response);
        $handler->method('handle')->willReturn($response);
        
        $router->process($this->request, $handler);
    }
    
    /**
     * @runInSeparateProcess
     */
    public function testRouteNotFoundException()
    {
        $router = $this->router;
        
        $router->map('GET', '/', function (ServerRequestInterface $request, ResponseInterface $response) {
            return $response->withStatus(404);
        });
        
        $this->expectException(\BitFrame\Exception\RouteNotFoundException::class);
        
        $response = HttpMessageFactory::createResponse();
        $handler = $this->getMockBuilder(\Psr\Http\Server\RequestHandlerInterface::class)->setMethods(['handle', 'getResponse', 'setResponse'])->getMock();
        
        $handler->method('setResponse')->willReturn(null);
        $handler->method('getResponse')->willReturn($response);
        $handler->method('handle')->willReturn($response);
        
        $router->process($this->request, $handler);
    }
    
    private function readStream($stream)
    {
        if ($stream->isSeekable()) {
            $stream->rewind();
        }
        
        $output = '';

        // no readable data in stream?
        if (! $stream->isReadable()) {
            $output = $stream;
        } else {
            // read data till end of stream is reached...
            while (! $stream->eof()) {
                // read 8mb (max buffer length) of binary data at a time and output it
                $output .= $stream->read(1024 * 8);
            }
        }
        
        return $output;
    }
}