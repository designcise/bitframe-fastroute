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
use BitFrame\Router\AbstractRouter;
use BitFrame\FastRoute\Router;
use BitFrame\FastRoute\Exception\{
    RouteNotFoundException,
    MethodNotAllowedException,
    BadRouteException
};

/**
 * @covers \BitFrame\FastRoute\Router
 */
class RouterTest extends TestCase
{
    private Router $router;

    public function setUp(): void
    {
        $this->router = new Router();
    }

    public function testDuplicateVariableNameError(): void
    {
        $this->expectException(BadRouteException::class);
        $this->expectExceptionMessage('Cannot use the same placeholder "test" twice');
        $this->router->get('/foo/{test}/{test:\d+}', 'handler0');
    }

    public function testDuplicateVariableRoute(): void
    {
        $this->expectException(BadRouteException::class);
        $this->expectExceptionMessage('Cannot register two routes matching "/user/([^/]+)" for method "GET"');
        $this->router->get('/user/{id}', 'handler0');
        $this->router->get('/user/{name}', 'handler1');
    }

    public function testShadowedStaticRoute(): void
    {
        $this->expectException(BadRouteException::class);
        $this->expectExceptionMessage('Static route "/user/fastroute" is shadowed by previously defined variable route "/user/([^/]+)" for method "GET"');
        $this->router->get('/user/{name}', 'handler0');
        $this->router->get('/user/fastroute', 'handler1');
    }

    public function testCapturing(): void
    {
        $this->expectException(BadRouteException::class);
        $this->expectExceptionMessage('Regex "(en|de)" for parameter "lang" contains a capturing group');
        $this->router->get('/{lang:(en|de)}', 'handler0');
    }

    public function foundDispatchCasesProvider(): array
    {
        $cases = [];

        // 0 -------------------------------------------------------------------------------------->
        $callback = function (AbstractRouter $r) {
            $r->get('/resource/123/456', 'handler0');
        };
        $method = 'GET';
        $uri = '/resource/123/456';
        $handler = 'handler0';
        $argDict = [];
        $cases[] = [$method, $uri, $callback, $handler, $argDict];

        // 1 -------------------------------------------------------------------------------------->
        $callback = function (AbstractRouter $r) {
            $r->get('/handler0', 'handler0');
            $r->get('/handler1', 'handler1');
            $r->get('/handler2', 'handler2');
        };
        $method = 'GET';
        $uri = '/handler2';
        $handler = 'handler2';
        $argDict = [];
        $cases[] = [$method, $uri, $callback, $handler, $argDict];

        // 2 -------------------------------------------------------------------------------------->
        $callback = function (AbstractRouter $r) {
            $r->get('/user/{name}/{id:[0-9]+}', 'handler0');
            $r->get('/user/{id:[0-9]+}', 'handler1');
            $r->get('/user/{name}', 'handler2');
        };
        $method = 'GET';
        $uri = '/user/rdlowrey';
        $handler = 'handler2';
        $argDict = ['name' => 'rdlowrey'];
        $cases[] = [$method, $uri, $callback, $handler, $argDict];

        // 3 -------------------------------------------------------------------------------------->
        // reuse $callback from #2
        $method = 'GET';
        $uri = '/user/12345';
        $handler = 'handler1';
        $argDict = ['id' => '12345'];
        $cases[] = [$method, $uri, $callback, $handler, $argDict];

        // 4 -------------------------------------------------------------------------------------->
        // reuse $callback from #3
        $method = 'GET';
        $uri = '/user/NaN';
        $handler = 'handler2';
        $argDict = ['name' => 'NaN'];
        $cases[] = [$method, $uri, $callback, $handler, $argDict];

        // 5 -------------------------------------------------------------------------------------->
        // reuse $callback from #4
        $method = 'GET';
        $uri = '/user/rdlowrey/12345';
        $handler = 'handler0';
        $argDict = ['name' => 'rdlowrey', 'id' => '12345'];
        $cases[] = [$method, $uri, $callback, $handler, $argDict];

        // 6 -------------------------------------------------------------------------------------->
        $callback = static function (AbstractRouter $r): void {
            $r->get('/user/{id:[0-9]+}', 'handler0');
            $r->get('/user/12345/extension', 'handler1');
            $r->get('/user/{id:[0-9]+}.{extension}', 'handler2');
        };
        $method = 'GET';
        $uri = '/user/12345.svg';
        $handler = 'handler2';
        $argDict = ['id' => '12345', 'extension' => 'svg'];
        $cases[] = [$method, $uri, $callback, $handler, $argDict];

        // 7 ----- Test GET method fallback on HEAD route miss ------------------------------------>
        $callback = static function (AbstractRouter $r): void {
            $r->get('/user/{name}', 'handler0');
            $r->get('/user/{name}/{id:[0-9]+}', 'handler1');
            $r->get('/static0', 'handler2');
            $r->get('/static1', 'handler3');
            $r->head('/static1', 'handler4');
        };
        $method = 'HEAD';
        $uri = '/user/rdlowrey';
        $handler = 'handler0';
        $argDict = ['name' => 'rdlowrey'];
        $cases[] = [$method, $uri, $callback, $handler, $argDict];

        // 8 ----- Test GET method fallback on HEAD route miss ------------------------------------>
        // reuse $callback from #7
        $method = 'HEAD';
        $uri = '/user/rdlowrey/1234';
        $handler = 'handler1';
        $argDict = ['name' => 'rdlowrey', 'id' => '1234'];
        $cases[] = [$method, $uri, $callback, $handler, $argDict];

        // 9 ----- Test GET method fallback on HEAD route miss ------------------------------------>
        // reuse $callback from #8
        $method = 'HEAD';
        $uri = '/static0';
        $handler = 'handler2';
        $argDict = [];
        $cases[] = [$method, $uri, $callback, $handler, $argDict];

        // 10 ---- Test existing HEAD route used if available (no fallback) ----------------------->
        // reuse $callback from #9
        $method = 'HEAD';
        $uri = '/static1';
        $handler = 'handler4';
        $argDict = [];
        $cases[] = [$method, $uri, $callback, $handler, $argDict];

        // 11 ---- More specified routes are not shadowed by less specific of another method ------>
        $callback = static function (AbstractRouter $r): void {
            $r->get('/user/{name}', 'handler0');
            $r->post('/user/{name:[a-z]+}', 'handler1');
        };
        $method = 'POST';
        $uri = '/user/rdlowrey';
        $handler = 'handler1';
        $argDict = ['name' => 'rdlowrey'];
        $cases[] = [$method, $uri, $callback, $handler, $argDict];

        // 12 ---- Handler of more specific routes is used, if it occurs first -------------------->
        $callback = static function (AbstractRouter $r): void {
            $r->get('/user/{name}', 'handler0');
            $r->post('/user/{name:[a-z]+}', 'handler1');
            $r->post('/user/{name}', 'handler2');
        };
        $method = 'POST';
        $uri = '/user/rdlowrey';
        $handler = 'handler1';
        $argDict = ['name' => 'rdlowrey'];
        $cases[] = [$method, $uri, $callback, $handler, $argDict];

        // 13 ---- Route with constant suffix ----------------------------------------------------->
        $callback = static function (AbstractRouter $r): void {
            $r->get('/user/{name}', 'handler0');
            $r->get('/user/{name}/edit', 'handler1');
        };
        $method = 'GET';
        $uri = '/user/rdlowrey/edit';
        $handler = 'handler1';
        $argDict = ['name' => 'rdlowrey'];
        $cases[] = [$method, $uri, $callback, $handler, $argDict];

        // 14 ---- Handle multiple methods with the same handler ---------------------------------->
        $callback = static function (AbstractRouter $r): void {
            $r->map(['GET', 'POST'], '/user', 'handlerGetPost');
            $r->map(['DELETE'], '/user', 'handlerDelete');
            $r->map([], '/user', 'handlerNone');
        };
        $argDict = [];
        $cases[] = ['GET', '/user', $callback, 'handlerGetPost', $argDict];
        $cases[] = ['POST', '/user', $callback, 'handlerGetPost', $argDict];
        $cases[] = ['DELETE', '/user', $callback, 'handlerDelete', $argDict];

        // 17 ----
        $callback = static function (AbstractRouter $r): void {
            $r->post('/user.json', 'handler0');
            $r->get('/{entity}.json', 'handler1');
        };
        $cases[] = ['GET', '/user.json', $callback, 'handler1', ['entity' => 'user']];

        // 18 ----
        $callback = static function (AbstractRouter $r): void {
            $r->get('', 'handler0');
        };
        $cases[] = ['GET', '', $callback, 'handler0', []];

        // 19 ----
        $callback = static function (AbstractRouter $r): void {
            $r->head('/a/{foo}', 'handler0');
            $r->get('/b/{foo}', 'handler1');
        };
        $cases[] = ['HEAD', '/b/bar', $callback, 'handler1', ['foo' => 'bar']];

        // 20 ----
        $callback = static function (AbstractRouter $r): void {
            $r->head('/a', 'handler0');
            $r->get('/b', 'handler1');
        };
        $cases[] = ['HEAD', '/b', $callback, 'handler1', []];

        // 21 ----
        $callback = static function (AbstractRouter $r): void {
            $r->get('/foo', 'handler0');
            $r->head('/{bar}', 'handler1');
        };
        $cases[] = ['HEAD', '/foo', $callback, 'handler1', ['bar' => 'foo']];

        // 22 ----
        $callback = static function (AbstractRouter $r): void {
            $r->map('*', '/user', 'handler0');
            $r->map('*', '/{user}', 'handler1');
            $r->get('/user', 'handler2');
        };
        $cases[] = ['GET', '/user', $callback, 'handler2', []];

        // 23 ----
        $callback = static function (AbstractRouter $r): void {
            $r->map('*', '/user', 'handler0');
            $r->get('/user', 'handler1');
        };
        $cases[] = ['POST', '/user', $callback, 'handler0', []];

        // 24 ----
        $cases[] = ['HEAD', '/user', $callback, 'handler1', []];

        // 25 ----
        $callback = static function (AbstractRouter $r): void {
            $r->get('/{bar}', 'handler0');
            $r->map('*', '/foo', 'handler1');
        };
        $cases[] = ['GET', '/foo', $callback, 'handler0', ['bar' => 'foo']];

        // 26 ----
        $callback = static function (AbstractRouter $r): void {
            $r->get('/user', 'handler0');
            $r->map('*', '/{foo:.*}', 'handler1');
        };
        $cases[] = ['POST', '/bar', $callback, 'handler1', ['foo' => 'bar']];

        // 27 ----
        $callback = static function (AbstractRouter $r): void {
            $r->options('/about', 'handler0');
        };
        $cases[] = ['OPTIONS', '/about', $callback, 'handler0', []];
        // x -------------------------------------------------------------------------------------->

        return $cases;
    }

    /**
     * @dataProvider foundDispatchCasesProvider
     *
     * @param string $method
     * @param string $uri
     * @param callable $callback
     * @param string $handler
     * @param array $argDict
     */
    public function testFoundDispatches(
        string $method,
        string $uri,
        callable $callback,
        string $handler,
        array $argDict
    ): void {
        $callback($this->router);
        $results = $this->router->getRouteCollection()->getRouteData($method, $uri);
        $this->assertSame($handler, $results[0]);
        $this->assertSame($argDict, $results[1]);
    }

    /**
     * @return mixed[]
     */
    public function notFoundDispatchCasesProvider(): array
    {
        $cases = [];

        // 0 -------------------------------------------------------------------------------------->
        $callback = static function (AbstractRouter $r): void {
            $r->get('/resource/123/456', 'handler0');
        };
        $method = 'GET';
        $uri = '/not-found';
        $cases[] = [$method, $uri, $callback];

        // 1 -------------------------------------------------------------------------------------->
        // reuse callback from #0
        $method = 'POST';
        $uri = '/not-found';
        $cases[] = [$method, $uri, $callback];

        // 2 -------------------------------------------------------------------------------------->
        // reuse callback from #1
        $method = 'PUT';
        $uri = '/not-found';
        $cases[] = [$method, $uri, $callback];

        // 3 -------------------------------------------------------------------------------------->
        $callback = static function (AbstractRouter $r): void {
            $r->get('/handler0', 'handler0');
            $r->get('/handler1', 'handler1');
            $r->get('/handler2', 'handler2');
        };
        $method = 'GET';
        $uri = '/not-found';
        $cases[] = [$method, $uri, $callback];

        // 4 -------------------------------------------------------------------------------------->
        $callback = static function (AbstractRouter $r): void {
            $r->get('/user/{name}/{id:[0-9]+}', 'handler0');
            $r->get('/user/{id:[0-9]+}', 'handler1');
            $r->get('/user/{name}', 'handler2');
        };
        $method = 'GET';
        $uri = '/not-found';
        $cases[] = [$method, $uri, $callback];

        // 5 -------------------------------------------------------------------------------------->
        // reuse callback from #4
        $method = 'GET';
        $uri = '/user/rdlowrey/12345/not-found';
        $cases[] = [$method, $uri, $callback];

        // 6 -------------------------------------------------------------------------------------->
        // reuse callback from #5
        $method = 'HEAD';
        $cases[] = [$method, $uri, $callback];
        // x -------------------------------------------------------------------------------------->

        return $cases;
    }

    /**
     * @dataProvider notFoundDispatchCasesProvider
     *
     * @param string $method
     * @param string $uri
     * @param callable $callback
     */
    public function testNotFoundDispatches(
        string $method,
        string $uri,
        callable $callback
    ): void {
        $this->expectException(RouteNotFoundException::class);

        $callback($this->router);
        $this->router->getRouteCollection()->getRouteData($method, $uri);
    }

    /**
     * @return mixed[]
     */
    public function methodNotAllowedDispatchCasesProvider(): array
    {
        $cases = [];

        // 0 -------------------------------------------------------------------------------------->
        $callback = function (AbstractRouter $r) {
            $r->get('/resource/123/456', 'handler0');
        };
        $method = 'POST';
        $uri = '/resource/123/456';
        $cases[] = [$method, $uri, $callback];

        // 1 -------------------------------------------------------------------------------------->
        $callback = function (AbstractRouter $r) {
            $r->get('/resource/123/456', 'handler0');
            $r->post('/resource/123/456', 'handler1');
            $r->put('/resource/123/456', 'handler2');
            $r->map('*', '/', 'handler3');
        };
        $method = 'DELETE';
        $uri = '/resource/123/456';
        $allowedMethods = ['GET', 'POST', 'PUT'];
        $cases[] = [$method, $uri, $callback, $allowedMethods];

        // 2 -------------------------------------------------------------------------------------->
        $callback = function (AbstractRouter $r) {
            $r->get('/user/{name}/{id:[0-9]+}', 'handler0');
            $r->post('/user/{name}/{id:[0-9]+}', 'handler1');
            $r->put('/user/{name}/{id:[0-9]+}', 'handler2');
            $r->patch('/user/{name}/{id:[0-9]+}', 'handler3');
        };
        $method = 'DELETE';
        $uri = '/user/rdlowrey/42';
        $allowedMethods = ['GET', 'POST', 'PUT', 'PATCH'];
        $cases[] = [$method, $uri, $callback, $allowedMethods];

        // 3 -------------------------------------------------------------------------------------->
        $callback = function (AbstractRouter $r) {
            $r->post('/user/{name}', 'handler1');
            $r->put('/user/{name:[a-z]+}', 'handler2');
            $r->patch('/user/{name:[a-z]+}', 'handler3');
        };
        $method = 'GET';
        $uri = '/user/rdlowrey';
        $allowedMethods = ['POST', 'PUT', 'PATCH'];
        $cases[] = [$method, $uri, $callback, $allowedMethods];

        // 4 -------------------------------------------------------------------------------------->
        $callback = function (AbstractRouter $r) {
            $r->map(['GET', 'POST'], '/user', 'handlerGetPost');
            $r->map(['DELETE'], '/user', 'handlerDelete');
            $r->map([], '/user', 'handlerNone');
        };
        $cases[] = ['PUT', '/user', $callback, ['GET', 'POST', 'DELETE']];

        // 5
        $callback = function (AbstractRouter $r) {
            $r->post('/user.json', 'handler0');
            $r->get('/{entity}.json', 'handler1');
        };
        $cases[] = ['PUT', '/user.json', $callback, ['POST', 'GET']];
        // x -------------------------------------------------------------------------------------->

        return $cases;
    }

    /**
     * @dataProvider methodNotAllowedDispatchCasesProvider
     *
     * @param string $method
     * @param string $uri
     * @param callable $callback
     */
    public function testMethodNotAllowedDispatches(
        string $method,
        string $uri,
        callable $callback
    ): void {
        $this->expectException(MethodNotAllowedException::class);

        $callback($this->router);
        $this->router->getRouteCollection()->getRouteData($method, $uri);
    }
}
