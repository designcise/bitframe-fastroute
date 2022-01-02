# BitFrame\FastRoute

[![codecov](https://codecov.io/gh/designcise/bitframe-fastroute/branch/master/graph/badge.svg)](https://codecov.io/gh/designcise/bitframe-fastroute)
[![Build Status](https://travis-ci.com/designcise/bitframe-fastroute.svg?branch=master)](https://travis-ci.com/designcise/bitframe-fastroute)

FastRoute wrapper class to manage http routes as a middleware.

## Installation

Install using composer:

```
$ composer require designcise/bitframe-fastroute
```

Please note that this package requires PHP 8.1.0 or newer.

## Usage Example

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