# BitFrame\FastRoute

[![CI](https://github.com/designcise/bitframe-fastroute/actions/workflows/ci.yml/badge.svg)](https://github.com/designcise/bitframe-fastroute/actions/workflows/ci.yml)
[![Maintainability](https://api.codeclimate.com/v1/badges/b4f08707fc26da971047/maintainability)](https://codeclimate.com/github/designcise/bitframe-fastroute/maintainability)
[![Test Coverage](https://api.codeclimate.com/v1/badges/b4f08707fc26da971047/test_coverage)](https://codeclimate.com/github/designcise/bitframe-fastroute/test_coverage)

FastRoute wrapper class to manage http routes as a middleware.

## Installation

Install using composer:

```
$ composer require designcise/bitframe-fastroute
```

Please note that this package requires PHP 8.2.0 or newer.

## Examples

### Using Attributes for Route Declaration

```php
class SomeController
{
    #[Route(['GET'], '/hello/123')]
    public function indexAction(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler,
    ): ResponseInterface {
        $response = $handler->handle($request);
        $response->getBody()->write(
            "BitFramePHP - 👋 Build Something Amazing Today!"
        );

        return $response;
    }
}
```

```php
use BitFrame\App;
use BitFrame\Emitter\SapiEmitter;
use BitFrame\FastRoute\Router;
use SomeController;

require 'vendor/autoload.php';

$app = new App();
$router = new Router();

$router->registerControllers([
    new SomeController(),
]);

$app->run([
    SapiEmitter::class,
    $router,
    // ...
]);
```

### Using Inline Callback to Handle Route

```php
use BitFrame\App;
use BitFrame\Emitter\SapiEmitter;
use BitFrame\FastRoute\Router;

require 'vendor/autoload.php';

$app = new App();
$router = new Router();

$router->map(['GET', 'POST'], '/test', function ($request, $handler) {
    $response = $handler->handle($request);
    $response->getBody()->write('Test Page');
    return $response;
});

$app->run([
    SapiEmitter::class,
    $router,
    // ...
]);
```

## Tests

To run the tests you can use the following commands:

| Command          | Type            |
| ---------------- |:---------------:|
| `composer test`  | PHPUnit tests   |
| `composer style` | CodeSniffer     |
| `composer md`    | MessDetector    |
| `composer check` | PHPStan         |

## Contributing

* File issues at https://github.com/designcise/bitframe-fastroute/issues
* Issue patches to https://github.com/designcise/bitframe-fastroute/pulls

## License

Please see [License File](LICENSE.md) for licensing information.