# BitFrame\Router\FastRouteRouter

FastRoute wrapper class to manage http routes as a middleware.

### Installation

See [installation docs](https://www.bitframephp.com/middleware/router/fastroute) for instructions on installing and using this middleware.

### Usage Example

```
use \BitFrame\Router\FastRouteRouter;

require 'vendor/autoload.php';

$app = new \BitFrame\Application;

$app->map(['GET', 'POST'], '/test', function ($request, $response, $next) {
    $response->getBody()->write('Test Page');
    
    return $response;
});

$app->run([
    /* In order to output response from the router (or router middleware), 
     * make sure you include a response emitter middleware, for example:
     * \BitFrame\Message\DiactorosResponseEmitter::class, */
    // router should normally be the last middleware to run
    FastRouteRouter::class
]);
```

### Tests

To execute the test suite, you will need [PHPUnit](https://phpunit.de/).

### Contributing

* File issues at https://github.com/designcise/bitframe-fastroute/issues
* Issue patches to https://github.com/designcise/bitframe-fastroute/pulls

### Documentation

Documentation is available at:

* https://www.bitframephp.com/middleware/router/fastroute/

### License

Please see [License File](LICENSE.md) for licensing information.