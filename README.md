# BitFrame\FastRoute

FastRoute wrapper class to manage http routes as a middleware.

## Installation

Install using composer:

```
$ composer require designcise/bitframe-fastroute
```

Please note that this package requires PHP 7.4.0 or newer.

## Usage Example

```
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

To execute the test suite, you will need [PHPUnit](https://phpunit.de/).

## Contributing

* File issues at https://github.com/designcise/bitframe-fastroute/issues
* Issue patches to https://github.com/designcise/bitframe-fastroute/pulls

## Documentation

Complete documentation for v2.0 will be available soon.

## License

Please see [License File](LICENSE.md) for licensing information.