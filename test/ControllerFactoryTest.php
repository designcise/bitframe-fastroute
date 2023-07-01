<?php

/**
 * BitFrame Framework (https://www.bitframephp.com)
 *
 * @author    Daniyal Hamid
 * @copyright Copyright (c) 2017-2023 Daniyal Hamid (https://designcise.com)
 * @license   https://bitframephp.com/about/license MIT License
 */

declare(strict_types=1);

namespace BitFrame\FastRoute\Test;

use PHPUnit\Framework\TestCase;
use Psr\Http\Message\{ServerRequestInterface, ResponseInterface};
use Psr\Http\Server\RequestHandlerInterface;
use BitFrame\FastRoute\Test\Asset\Controller;
use BitFrame\FastRoute\ControllerFactory;
use RuntimeException;
use InvalidArgumentException;

/**
 * @covers \BitFrame\FastRoute\ControllerFactory
 */
class ControllerFactoryTest extends TestCase
{
    public function testCreate(): void
    {
        $request = $this->getMockedRequest();
        $response = $this->getMockedResponse();
        $handler = $this->getMockedHandler($response);

        $diArg = 'test';

        $instance = ControllerFactory::create(Controller::class, $diArg);

        $this->expectOutputString($diArg);

        $controllerResponse = $instance->indexAction($request, $handler);

        $this->assertSame($controllerResponse, $response);
    }

    public function testFromShouldThrowExceptionWhenMethodDoesNotExist(): void
    {
        $this->expectException(RuntimeException::class);
        ControllerFactory::from(Controller::class, 'nonExistentMethod', 'whatever');
    }

    public function testFromShouldThrowExceptionWhenClassDoesNotExist(): void
    {
        $this->expectException(RuntimeException::class);
        ControllerFactory::from('nonExistentClass', 'nonExistentMethod', 'whatever');
    }

    public function testFromCanPassAdditionalArgsToStaticMethod(): void
    {
        $request = $this->getMockedRequest();
        $response = $this->getMockedResponse();
        $handler = $this->getMockedHandler($response);

        $diArg = 'testing123';

        $decoratedController = ControllerFactory::from(Controller::class, 'staticAction', $diArg);

        $this->expectOutputString($diArg);

        $controllerResponse = $decoratedController($request, $handler);

        $this->assertSame($controllerResponse, $response);
    }

    public function callableWithArgsProvider(): array
    {
        return [
            'DI to instantiated object method' => [
                [new Controller(), 'methodAction']
            ],
            'DI to instantiated object method given string class' => [
                [Controller::class, 'methodAction']
            ],
            'DI to static method' => [
                [Controller::class, 'staticAction']
            ],
            'DI to static method in instantiated object' => [
                [new Controller(), 'staticAction']
            ],
        ];
    }

    /**
     * @dataProvider callableWithArgsProvider
     *
     * @param array $args
     */
    public function testFromArray(array $args): void
    {
        $request = $this->getMockedRequest();
        $response = $this->getMockedResponse();
        $handler = $this->getMockedHandler($response);

        $args[] = 'testing123';

        $decoratedController = ControllerFactory::fromArray($args);

        $this->expectOutputString('testing123');

        $controllerResponse = $decoratedController($request, $handler);

        $this->assertSame($controllerResponse, $response);
    }

    public function invalidCallableWithArgsProvider(): array
    {
        return [
            'empty' => [
                []
            ],
            'no method specified' => [
                [new Controller()]
            ],
            'no class specified' => [
                [1 => 'someAction']
            ],
            'non-numeric array specified' => [
                ['controller' => new Controller(), 'method' => 'indexAction']
            ],
            'array arguments do not start from zero' => [
                [2 => new Controller(), 3 => 'indexAction']
            ],
        ];
    }

    /**
     * @dataProvider invalidCallableWithArgsProvider
     * @param array $args
     */
    public function testFromArrayShouldThrowExceptionWhenInvalidCallable(array $args): void
    {
        $this->expectException(InvalidArgumentException::class);

        ControllerFactory::fromArray($args);
    }

    public function testFromArrayShouldThrowExceptionWhenMethodDoesNotExist(): void
    {
        $this->expectException(RuntimeException::class);

        ControllerFactory::fromArray([new Controller(), 'nonExistent', 'whatever']);
    }

    public function testFromArrayShouldThrowExceptionWhenClassDoesNotExist(): void
    {
        $this->expectException(RuntimeException::class);

        ControllerFactory::fromArray(['nonExistent', 'nonExistent', 'whatever']);
    }

    public function testFromCanCreateNewControllerInstanceWithDI(): void
    {
        $request = $this->getMockedRequest();
        $response = $this->getMockedResponse();
        $handler = $this->getMockedHandler($response);

        $diArg = 'test';

        $decoratedController = ControllerFactory::from(Controller::class, 'indexAction', $diArg);

        $this->expectOutputString($diArg);

        $controllerResponse = $decoratedController($request, $handler);

        $this->assertSame($controllerResponse, $response);
    }

    public function callableProvider(): array
    {
        return [
            'function' => [
                static function (
                    ServerRequestInterface $request,
                    RequestHandlerInterface$handler,
                    string $foo = 'bar'
                ): ResponseInterface {
                    echo $foo;
                    return $handler->handle($request);
                }
            ],
            'invokable class' => [
                new Controller()
            ],
            'static method array' => [
                [Controller::class, 'staticAction']
            ],
            'static method string' => [
                Controller::class . '::staticAction'
            ],
            'class method' => [
                [new Controller(), 'methodAction']
            ],
        ];
    }

    /**
     * @dataProvider callableProvider
     *
     * @param callable $callable
     */
    public function testFromCallable(callable $callable): void
    {
        $request = $this->getMockedRequest();
        $response = $this->getMockedResponse();
        $handler = $this->getMockedHandler($response);

        $diArg = ['testing123'];

        $decoratedController = ControllerFactory::fromCallable($callable, $diArg);

        $this->expectOutputString($diArg[0]);

        $controllerResponse = $decoratedController($request, $handler);

        $this->assertSame($controllerResponse, $response);
    }

    /**
     * @dataProvider callableProvider
     *
     * @param callable $callable
     */
    public function testFromCallableWithoutArgsShouldNotWrapCallable(callable $callable): void
    {
        $this->assertSame($callable, ControllerFactory::fromCallable($callable));
    }

    /**
     * @return \PHPUnit\Framework\MockObject\MockObject|ServerRequestInterface
     */
    private function getMockedRequest()
    {
        return $this->getMockBuilder(ServerRequestInterface::class)
            ->getMockForAbstractClass();
    }

    /**
     * @param \PHPUnit\Framework\MockObject\MockObject|ResponseInterface $response
     *
     * @return \PHPUnit\Framework\MockObject\MockObject|RequestHandlerInterface
     */
    private function getMockedHandler($response)
    {
        $handler = $this->getMockBuilder(RequestHandlerInterface::class)
            ->onlyMethods(['handle'])
            ->getMockForAbstractClass();

        $handler->method('handle')->willReturn($response);

        return $handler;
    }

    /**
     * @return \PHPUnit\Framework\MockObject\MockObject|ResponseInterface
     */
    private function getMockedResponse()
    {
        return $this->getMockBuilder(ResponseInterface::class)
            ->getMockForAbstractClass();
    }
}
