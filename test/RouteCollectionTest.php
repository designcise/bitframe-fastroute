<?php

/**
 * BitFrame Framework (https://www.bitframephp.com)
 *
 * @author    Daniyal Hamid
 * @copyright Copyright (c) 2017-2019 Daniyal Hamid (https://designcise.com)
 * @license   https://bitframephp.com/about/license MIT License
 */

declare(strict_types=1);

namespace BitFrame\FastRoute\Test;

use PHPUnit\Framework\TestCase;
use BitFrame\Router\RouterInterface;
use BitFrame\FastRoute\RouteCollection;
use BitFrame\FastRoute\Exception\BadRouteException;

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
            'GET' => [['GET'], '/hello/world', '/hello/world'],
            'POST' => [['POST'], '/hello/world', '/hello/world'],
            'PUT' => [['PUT'], '/hello/world', '/hello/world'],
            'PATCH' => [['PATCH'], '/hello/world', '/hello/world'],
            'DELETE' => [['DELETE'], '/hello/world', '/hello/world'],
            'HEAD' => [['HEAD'], '/hello/world', '/hello/world'],
            'OPTIONS' => [['OPTIONS'], '/hello/world', '/hello/world'],
            'any' => [
                ['GET', 'POST', 'PUT', 'PATCH', 'DELETE', 'HEAD', 'OPTIONS'],
                '/hello/world',
                '/hello/world'
            ],
            'non-standard method' => [['test'], '/hello/world', '/hello/world'],
            'route with variable' => [['GET', 'POST'], '/hello/{id:\d+}', '/hello/1234'],
            'route with variable and non-standard method' => [['test'], '/hello/{id:\d+}', '/hello/1234'],
            'non-matching route with variable' => [['GET'], '/hello/{id:\d+}', '/hello/world', []],
            '2-level deep route with variable' => [['GET'], '/hello/{name}', '/hello/john'],
            'invalid requested path for 2-level deep route' => [['GET'], '/hello/{name}', '/hello/john/doe', []],
            'n-level deep route with variable' => [['GET'], '/hello/{name:.+}', '/hello/john/jane/doe'],
            'optional path requested with optional' => [['GET'], '/hello/{id:\d+}[/{name}]', '/hello/1234/john'],
            'optional path with omitted optional' => [['GET'], '/hello/{id:\d+}[/{name}]', '/hello/1234'],
            'nested optional path with 2-level omitted optional' => [
                ['GET'],
                '/hello[/{id:\d+}[/{name}]]',
                '/hello'
            ],
            'nested optional path with 1-level omitted optional' => [
                ['GET'],
                '/hello[/{id:\d+}[/{name}]]',
                '/hello/1234'
            ],
            'nested optional path with no omitted optional' => [
                ['GET'],
                '/hello[/{id:\d+}[/{name}]]',
                '/hello/1234/john'
            ],
        ];
    }

    /**
     * @dataProvider allowedMethodsProvider
     *
     * @param array $methods
     * @param string $path
     * @param string $requestedPath
     * @param array|null $expectedMethods
     */
    public function testGetAllowedMethods(
        array $methods,
        string $path,
        string $requestedPath,
        ?array $expectedMethods = null
    ): void {
        $handler = static fn ($request, $handler) => $handler->handle($request);
        $this->routeCollection->add($methods, $path, $handler);

        $allowedMethods = $this->routeCollection->getAllowedMethods($requestedPath);

        //echo print_r($allowedMethods, true);

        $this->assertSame($expectedMethods ?? $methods, $allowedMethods);
    }
}
