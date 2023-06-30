<?php

namespace BitFrame\FastRoute\Test\Asset;

use Psr\Http\Message\{ServerRequestInterface, ResponseInterface};
use Psr\Http\Server\RequestHandlerInterface;
use BitFrame\FastRoute\Route;

class Controller
{
    private string $foo;

    public function __construct(string $foo = 'bar')
    {
        $this->foo = $foo;
    }

    public function __invoke(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
        string $foo = 'bar'
    ): ResponseInterface {
        return self::staticAction($request, $handler, $foo);
    }

    #[Route(['GET', 'POST'], '/test')]
    #[Route('POST', '/test-2')]
    public function indexAction(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        echo $this->foo;
        return $handler->handle($request);
    }

    #[Route(['GET'], '/test2')]
    #[Route(['GET'], '/test/{param:\d+}')]
    public function methodAction(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
        string $foo = 'bar'
    ): ResponseInterface {
        return self::staticAction($request, $handler, $foo);
    }

    public static function staticAction(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
        string $foo = 'bar'
    ): ResponseInterface {
        echo $foo;
        return $handler->handle($request);
    }
}
