<?php

/**
 * BitFrame Framework (https://www.bitframephp.com)
 *
 * @author    Daniyal Hamid
 * @copyright Copyright (c) 2017-2020 Daniyal Hamid (https://designcise.com)
 * @license   https://bitframephp.com/about/license MIT License
 */

declare(strict_types=1);

namespace BitFrame\FastRoute\Test;

use PHPUnit\Framework\TestCase;
use BitFrame\FastRoute\RouteCollection;
use BitFrame\FastRoute\Exception\{
    MethodNotAllowedException,
    RouteNotFoundException,
    BadRouteException
};

/**
 * @covers \BitFrame\FastRoute\RouteCollection
 */
class RouteCollectionTest extends TestCase
{
    /** @var RouteCollection */
    private $routeCollection;

    public function setUp(): void
    {
        $this->routeCollection = new RouteCollection;
    }

    /**
     * @return mixed[]
     */
    public function provideTestParse(): array
    {
        return [
            [
                '/test',
                [
                    ['/test'],
                ],
            ],
            [
                '/test/{param}',
                [
                    ['/test/', ['param', '[^/]+']],
                ],
            ],
            [
                '/te{ param }st',
                [
                    ['/te', ['param', '[^/]+'], 'st'],
                ],
            ],
            [
                '/test/{param1}/test2/{param2}',
                [
                    ['/test/', ['param1', '[^/]+'], '/test2/', ['param2', '[^/]+']],
                ],
            ],
            [
                '/test/{param:\d+}',
                [
                    ['/test/', ['param', '\d+']],
                ],
            ],
            [
                '/test/{ param : \d{1,9} }',
                [
                    ['/test/', ['param', '\d{1,9}']],
                ],
            ],
            [
                '/test[opt]',
                [
                    ['/test'],
                    ['/testopt'],
                ],
            ],
            [
                '/test[/{param}]',
                [
                    ['/test'],
                    ['/test/', ['param', '[^/]+']],
                ],
            ],
            [
                '/{param}[opt]',
                [
                    ['/', ['param', '[^/]+']],
                    ['/', ['param', '[^/]+'], 'opt'],
                ],
            ],
            [
                '/test[/{name}[/{id:[0-9]+}]]',
                [
                    ['/test'],
                    ['/test/', ['name', '[^/]+']],
                    ['/test/', ['name', '[^/]+'], '/', ['id', '[0-9]+']],
                ],
            ],
            [
                '',
                [
                    [''],
                ],
            ],
            [
                '[test]',
                [
                    [''],
                    ['test'],
                ],
            ],
            [
                '/{foo-bar}',
                [
                    ['/', ['foo-bar', '[^/]+']],
                ],
            ],
            [
                '/{_foo:.*}',
                [
                    ['/', ['_foo', '.*']],
                ],
            ],
        ];
    }

    /**
     * @dataProvider provideTestParse
     *
     * @param string $routeString
     * @param array $expectedRouteTokens
     */
    public function testParsePath(string $routeString, array $expectedRouteTokens): void
    {
        $routeTokens = $this->routeCollection->parsePath($routeString);
        $this->assertSame($expectedRouteTokens, $routeTokens);
    }

    /**
     * @return string[][]
     */
    public function provideTestParseError(): array
    {
        return [
            [
                '/test[opt',
                "Number of opening '[' and closing ']' do not match",
            ],
            [
                '/test[opt[opt2]',
                "Number of opening '[' and closing ']' do not match",
            ],
            [
                '/testopt]',
                "Number of opening '[' and closing ']' do not match",
            ],
            [
                '/test[]',
                'Optional segments (i.e. parts enclosed within `[]`) cannot be empty',
            ],
            [
                '/test[[opt]]',
                'Optional segments (i.e. parts enclosed within `[]`) cannot be empty',
            ],
            [
                '[[test]]',
                'Optional segments (i.e. parts enclosed within `[]`) cannot be empty',
            ],
            [
                '/test[/opt]/required',
                'Optional segments (i.e. parts enclosed within `[]`) can only occur at the end of a route',
            ],
        ];
    }

    /**
     * @dataProvider provideTestParseError
     *
     * @param string $routeString
     * @param string $expectedExceptionMessage
     */
    public function testParseError(string $routeString, string $expectedExceptionMessage): void
    {
        $this->expectException(BadRouteException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);
        
        $this->routeCollection->parsePath($routeString);
    }

    public function allowedMethodsProvider(): array
    {
        return [
            'GET' => [
                [['GET'], '/hello/world'],
                '/hello/world'
            ],
            'POST' => [
                [['POST'], '/hello/world'],
                '/hello/world'
            ],
            'PUT' => [
                [['PUT'], '/hello/world'],
                '/hello/world'
            ],
            'PATCH' => [
                [['PATCH'], '/hello/world'],
                '/hello/world'
            ],
            'DELETE' => [
                [['DELETE'], '/hello/world'],
                '/hello/world'
            ],
            'HEAD' => [
                [['HEAD'], '/hello/world'],
                '/hello/world'
            ],
            'OPTIONS' => [
                [['OPTIONS'], '/hello/world'],
                '/hello/world'
            ],
            'any' => [
                [['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'], '/hello/world'],
                '/hello/world'
            ],
            'non-standard method' => [
                [['test'], '/hello/world'],
                '/hello/world'
            ],
            'route with numeric-only variable' => [
                [['GET', 'POST'], '/hello/{id:\d+}'],
                '/hello/1234'
            ],
            'route with variable and non-standard method' => [
                [['test'], '/hello/{id:\d+}'],
                '/hello/1234'
            ],
            'non-matching route with variable' => [
                [['GET'], '/hello/{id:\d+}'],
                '/hello/world',
                []
            ],
            '2-level deep route with variable' => [
                [['GET'], '/hello/{name}'],
                '/hello/john'
            ],
            'invalid requested path for 2-level deep route' => [
                [['GET'], '/hello/{name}'],
                '/hello/john/doe',
                []
            ],
            'n-level deep route with variable' => [
                [['GET'], '/hello/{name:.+}'],
                '/hello/john/jane/doe'
            ],
            'optional path requested with optional provided' => [
                [['GET'], '/hello/{id:\d+}[/{name}]'],
                '/hello/1234/john'
            ],
            'optional path with omitted optional' => [
                [['GET'], '/hello/{id:\d+}[/{name}]'],
                '/hello/1234'
            ],
            'nested optional path with 2-level omitted optional' => [
                [['GET'], '/hello[/{id:\d+}[/{name}]]'],
                '/hello'
            ],
            'nested optional path with 1-level omitted optional' => [
                [['GET'], '/hello[/{id:\d+}[/{name}]]'],
                '/hello/1234'
            ],
            'nested optional path with no omitted optional' => [
                [['GET'], '/hello[/{id:\d+}[/{name}]]'],
                '/hello/1234/john'
            ],
        ];
    }

    /**
     * @dataProvider allowedMethodsProvider
     *
     * @param array $storedRoute
     * @param string $requestedPath
     * @param array|null $expectedMethods
     */
    public function testGetAllowedMethods(
        array $storedRoute,
        string $requestedPath,
        ?array $expectedMethods = null,
    ): void {
        $storedRoute[] = static fn ($request, $handler) => $handler->handle($request);
        $this->routeCollection->add(...$storedRoute);

        $allowedMethods = $this->routeCollection->getAllowedMethods($requestedPath);

        $this->assertSame($expectedMethods ?? $storedRoute[0], $allowedMethods);
    }

    public function routeDataProvider(): array
    {
        return [
            'static path with single method' => [
                [['GET'], '/hello/world'],
                ['GET', '/hello/world']
            ],
            'static path with multiple methods' => [
                [['GET', 'POST'], '/hello/world'],
                ['GET', '/hello/world']
            ],
            'route with numeric-only variable' => [
                [['GET', 'POST'], '/hello/{id:\d+}'],
                ['GET', '/hello/1234'],
                ['id' => '1234']
            ],
            'route with non-capturing group' => [
                [['GET'], '/foobar/{lang:(?:en|de)}'],
                ['GET', '/foobar/de'],
                ['lang' => 'de']
            ],
            'route with specific options' => [
                [['GET'], '/foobar/{lang:en|de}'],
                ['GET', '/foobar/de'],
                ['lang' => 'de']
            ],
            'route with partial optional #1' => [
                [['GET'], '/hello/foo[bar]'],
                ['GET', '/hello/foo']
            ],
            'route with partial optional #2' => [
                [['GET'], '/hello/foo[bar]'],
                ['GET', '/hello/foobar']
            ],
            'HEAD request' => [
                [['HEAD'], '/hello/{id:\d+}'],
                ['HEAD', '/hello/1234'],
                ['id' => '1234']
            ],
            'fallback on fallback routes (if available) when nothing matches' => [
                [['*'], '/hello/{id:\d+}'],
                ['GET', '/hello/1234'],
                ['id' => '1234']
            ],
            'HEAD request falls back to GET if no HEAD data found' => [
                [['GET', 'POST'], '/hello/{id:\d+}'],
                ['HEAD', '/hello/1234'],
                ['id' => '1234']
            ],
            '2-level deep route with variable' => [
                [['PUT'], '/hello/{name}'],
                ['PUT', '/hello/john'],
                ['name' => 'john']
            ],
            'fallback routes (when nothing matches) for 2-level deep route with variable' => [
                [['*'], '/hello/{name}'],
                ['PUT', '/hello/john'],
                ['name' => 'john']
            ],
            'HEAD fallsback on GET for 2-level deep route with variable' => [
                [['GET'], '/hello/{name}'],
                ['HEAD', '/hello/john'],
                ['name' => 'john']
            ],
            'n-level deep route with variable' => [
                [['GET', 'POST'], '/hello/{name:.+}'],
                ['POST', '/hello/john/jane/doe'],
                ['name' => 'john/jane/doe']
            ],
            'fallback routes (when nothing matches) for n-level deep route with variable' => [
                [['*'], '/hello/{name:.+}'],
                ['POST', '/hello/john/jane/doe'],
                ['name' => 'john/jane/doe']
            ],
            'HEAD fallback on GET for n-level deep route with variable' => [
                [['GET', 'POST'], '/hello/{name:.+}'],
                ['HEAD', '/hello/john/jane/doe'],
                ['name' => 'john/jane/doe']
            ],
            'optional path requested with optional provided' => [
                [['DELETE'], '/hello/{id:\d+}[/{name}]'],
                ['DELETE', '/hello/1234/john'],
                ['id' => '1234', 'name' => 'john']
            ],
            'fallback routes (when nothing matches) for optional path requested with optional provided' => [
                [['*'], '/hello/{id:\d+}[/{name}]'],
                ['DELETE', '/hello/1234/john'],
                ['id' => '1234', 'name' => 'john']
            ],
            'HEAD fallback on GET for optional path requested with optional provided' => [
                [['GET'], '/hello/{id:\d+}[/{name}]'],
                ['HEAD', '/hello/1234/john'],
                ['id' => '1234', 'name' => 'john']
            ],
            'optional path with omitted optional' => [
                [['GET'], '/hello/{id:\d+}[/{name}]'],
                ['GET', '/hello/1234'],
                ['id' => '1234']
            ],
            'fallback routes (when nothing matches) for optional path with omitted optional' => [
                [['*'], '/hello/{id:\d+}[/{name}]'],
                ['GET', '/hello/1234'],
                ['id' => '1234']
            ],
            'HEAD falls back on GET for optional path with omitted optional' => [
                [['GET'], '/hello/{id:\d+}[/{name}]'],
                ['GET', '/hello/1234'],
                ['id' => '1234']
            ],
            'nested optional path with 2-level omitted optional' => [
                [['GET'], '/hello[/{id:\d+}[/{name}]]'],
                ['GET', '/hello']
            ],
            'fallback routes (when nothing matches) for nested optional path with 2-level omitted optional' => [
                [['*'], '/hello[/{id:\d+}[/{name}]]'],
                ['GET', '/hello']
            ],
            'HEAD fallsback on GET for nested optional path with 2-level omitted optional' => [
                [['GET'], '/hello[/{id:\d+}[/{name}]]'],
                ['HEAD', '/hello']
            ],
            'nested optional path with 1-level omitted optional' => [
                [['GET', 'POST', 'PUT', 'HEAD', 'OPTIONS'], '/hello[/{id:\d+}[/{name}]]'],
                ['PUT', '/hello/1234'],
                ['id' => '1234']
            ],
            'fallback routes (when nothing matches) for nested optional path with 1-level omitted optional' => [
                [['*'], '/hello[/{id:\d+}[/{name}]]'],
                ['PUT', '/hello/1234'],
                ['id' => '1234']
            ],
            'HEAD fallsback on GET for nested optional path with 1-level omitted optional' => [
                [['GET', 'POST', 'PUT', 'HEAD', 'OPTIONS'], '/hello[/{id:\d+}[/{name}]]'],
                ['HEAD', '/hello/1234'],
                ['id' => '1234']
            ],
            'nested optional path with no omitted optional' => [
                [['GET'], '/hello[/{id:\d+}[/{name}]]'],
                ['GET', '/hello/1234/john'],
                ['id' => '1234', 'name' => 'john']
            ],
            'fallback routes (when nothing matches) for nested optional path with no omitted optional' => [
                [['*'], '/hello[/{id:\d+}[/{name}]]'],
                ['GET', '/hello/1234/john'],
                ['id' => '1234', 'name' => 'john']
            ],
            'HEAD fallsback on GET for nested optional path with no omitted optional' => [
                [['GET'], '/hello[/{id:\d+}[/{name}]]'],
                ['HEAD', '/hello/1234/john'],
                ['id' => '1234', 'name' => 'john']
            ],
        ];
    }

    /**
     * @dataProvider routeDataProvider
     *
     * @param array $storedRoute
     * @param array $requestedRoute
     * @param array $expectedVars
     */
    public function testGetRouteData(
        array $storedRoute,
        array $requestedRoute,
        array $expectedVars = [],
    ): void {
        $storedHandler = static fn ($request, $handler) => $handler->handle($request);
        $storedRoute[] = $storedHandler;
        $this->routeCollection->add(...$storedRoute);

        [$handler, $vars] = $this->routeCollection->getRouteData(...$requestedRoute);

        $this->assertSame($storedHandler, $handler);
        $this->assertSame($expectedVars, $vars);
    }

    public function invalidRoutePathProvider(): array
    {
        return [
            'static path with differing trailing slashes' => [
                [['GET'], '/hello/world'],
                ['HEAD', '/hello/world/']
            ],
            'static path with differing leading slashes' => [
                [['GET'], '/hello/world'],
                ['GET', 'hello/world/']
            ],
            'static path with mismatching case path #1' => [
                [['GET'], '/hello/world'],
                ['GET', '/hello/WoRlD']
            ],
            'static path with mismatching case path #2' => [
                [['GET'], '/hello/WoRlD'],
                ['GET', '/hello/world']
            ],
            'route with non-capturing group when nothing matches' => [
                [['GET'], '/foobar/{lang:(?:en|de)}'],
                ['GET', '/foobar/fr']
            ],
            'route with specific options' => [
                [['GET'], '/foobar/{lang:en|de}'],
                ['GET', '/foobar/fr']
            ],
            'route with partial optional #1' => [
                [['GET'], '/hello/foo[bar]'],
                ['GET', '/hello/foobaz']
            ],
            'route with partial optional #2' => [
                [['GET'], '/hello/foo[bar]'],
                ['GET', '/hello/bar']
            ],
            'route with partial optional #3' => [
                [['GET'], '/hello/foo[bar]'],
                ['GET', '/hello/fooba']
            ],
            'route with partial optional #4' => [
                [['GET'], '/foo[/bar]'],
                ['GET', '/foo/']
            ],
            'route with partial optional #5' => [
                [['GET'], '/foo/[bar]'],
                ['GET', '/foo']
            ],
            'route with partial optional #6' => [
                [['GET'], '/foo/[bar]'],
                ['GET', '/foo/bar/']
            ],
            'invalid requested path for 2-level deep route' => [
                [['GET'], '/hello/{name}'],
                ['GET', '/hello/john/doe'],
            ],
            'route with numeric-only variable with invalid requested path' => [
                [['GET', 'POST'], '/hello/{id:\d+}'],
                ['GET', '/hello/abcd']
            ],
            '2-level deep route with variable and trailing slash' => [
                [['GET', 'POST'], '/hello/{name}'],
                ['POST', '/hello/john/']
            ],
            'route with numeric-only variable and trailing slash' => [
                [['GET'], '/hello/{id:\d+}/'],
                ['GET', '/hello/1234']
            ],
            'nested optional path with 2-level omitted optional without slashes' => [
                [['GET'], '/hello[/{id:\d+}[/{name}]]'],
                ['GET', 'hello']
            ],
        ];
    }

    /**
     * @dataProvider invalidRoutePathProvider
     *
     * @param array $storedRoute
     * @param array $requestedRoute
     */
    public function testGetRouteDataDoesThrowRouteNotFoundException(
        array $storedRoute,
        array $requestedRoute,
    ): void {
        $storedHandler = static fn ($request, $handler) => $handler->handle($request);
        $storedRoute[] = $storedHandler;
        $this->routeCollection->add(...$storedRoute);

        $this->expectException(RouteNotFoundException::class);

        $this->routeCollection->getRouteData(...$requestedRoute);
    }

    public function invalidRouteMethodProvider(): array
    {
        return [
            'access via non-allowed method for static route' => [
                [['GET'], '/hello/world'],
                ['POST', '/hello/world'],
            ],
            'access via non-allowed method for variable route' => [
                [['GET'], '/hello/{name}'],
                ['POST', '/hello/john'],
            ],
            'access via non-allowed method for static route with multi-methods' => [
                [['GET', 'DELETE', 'PATCH', 'OPTIONS'], '/hello/world'],
                ['POST', '/hello/world'],
            ],
            'access via non-allowed method for variable route with multi-methods' => [
                [['GET', 'DELETE', 'PATCH', 'OPTIONS'], '/hello/{name}'],
                ['POST', '/hello/john'],
            ],
        ];
    }

    /**
     * @dataProvider invalidRouteMethodProvider
     *
     * @param array $storedRoute
     * @param array $requestedRoute
     */
    public function testGetRouteDataDoesThrowMethodNotAllowedException(
        array $storedRoute,
        array $requestedRoute,
    ): void {
        $storedHandler = static fn ($request, $handler) => $handler->handle($request);
        $storedRoute[] = $storedHandler;
        $this->routeCollection->add(...$storedRoute);

        $this->expectException(MethodNotAllowedException::class);

        $this->routeCollection->getRouteData(...$requestedRoute);
    }

    public function duplicatePathProvider(): array
    {
        return [
            'empty path' => ['/', '/'],
            'simple static path' => ['/foo/bar', '/foo/bar'],
            'variable route' => ['/foo/{bar}', '/foo/{bar}'],
            'optional route' => ['/foo/[bar]', '/foo/[bar]'],
            'route with numeric-only variable' => ['/hello/{id:\d+}', '/hello/1234'],
            '2-level deep route with variable' => ['/hello/{name}', '/hello/john'],
            'n-level deep route with variable' => ['/hello/{name:.+}', '/hello/john/jane/doe'],
            'n-level deep optional route with one optional path provided' => ['/hello/{name:.+}', '/hello/doe'],
            'optional path with omitted optional' => ['/hello/{id:\d+}[/{name}]', '/hello/1234'],
            'nested optional path with 2-level omitted optional' => ['/hello[/{id:\d+}[/{name}]]', '/hello'],
            'nested optional path with 1-level omitted optional' => ['/hello[/{id:\d+}[/{name}]]', '/hello/1234'],
            'nested optional path with no omitted optional' => ['/hello[/{id:\d+}[/{name}]]', '/hello/1234/john'],
        ];
    }

    /**
     * @dataProvider duplicatePathProvider
     *
     * @param string $storedPath
     * @param string $requestedPath
     */
    public function testShouldThrowExceptionWhenUsingVariableTwice(
        string $storedPath,
        string $requestedPath,
    ): void {
        $handler = static fn ($request, $handler) => $handler->handle($request);
        $this->routeCollection->add(['GET'], $storedPath, $handler);

        $this->expectException(BadRouteException::class);

        $this->routeCollection->add(['GET'], $requestedPath, $handler);
    }

    public function capturingGroupRouteProvider(): array
    {
        return [
            'placeholder with optionals and capturing group' => [
                '/foobar/{lang:(en|de)}'
            ],
            'placeholder with nested optional and capturing group' => [
                '/hello[/{id:(\d+)}[/{name}]]'
            ],
        ];
    }

    /**
     * @dataProvider capturingGroupRouteProvider
     *
     * @param string $path
     */
    public function testShouldThrowExceptionWhenPathPlaceholderHasCapturingGroup(string $path): void
    {
        $this->expectException(BadRouteException::class);
        $handler = static fn ($request, $handler) => $handler->handle($request);
        $this->routeCollection->add(['GET'], $path, $handler);
    }

    public function testShouldThrowExceptionWhenSamePlaceholderUsedTwice(): void
    {
        $this->expectException(BadRouteException::class);
        $handler = static fn ($request, $handler) => $handler->handle($request);
        $this->routeCollection->add(
            ['GET'],
            '/{foo:\w+}|/{lang:en|de}/{foo:\w+}',
            $handler
        );
    }
}
