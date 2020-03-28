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
     * @dataProvider provideTestParse
     *
     * @param array<string|string[]> $expectedRouteTokens
     */
    public function testParsePath(string $routeString, array $expectedRouteTokens): void
    {
        $routeTokens = $this->routeCollection->parsePath($routeString);
        $this->assertSame($expectedRouteTokens, $routeTokens);
    }

    /**
     * @dataProvider provideTestParseError
     */
    public function testParseError(string $routeString, string $expectedExceptionMessage): void
    {
        $this->expectException(BadRouteException::class);
        $this->expectExceptionMessage($expectedExceptionMessage);
        
        $this->routeCollection->parsePath($routeString);
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
}