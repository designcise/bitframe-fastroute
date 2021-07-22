<?php

/**
 * BitFrame Framework (https://www.bitframephp.com)
 *
 * @author    Daniyal Hamid
 * @copyright Copyright (c) 2017-2020 Daniyal Hamid (https://designcise.com)
 * @license   https://bitframephp.com/about/license MIT License
 */

declare(strict_types=1);

namespace BitFrame\FastRoute;

use BitFrame\FastRoute\Exception\{
    MethodNotAllowedException,
    RouteNotFoundException,
    BadRouteException
};

use function strlen;
use function strpos;
use function trim;
use function rtrim;
use function is_string;
use function substr;
use function implode;
use function array_map;
use function array_chunk;
use function count;
use function round;
use function ceil;
use function max;
use function sprintf;
use function str_repeat;
use function preg_split;
use function preg_quote;
use function preg_match;
use function preg_match_all;

use const PREG_OFFSET_CAPTURE;
use const PREG_SET_ORDER;

/**
 * Stores parsed routes.
 */
class RouteCollection
{
    private const PLACEHOLDER_REGEX = <<<REGEX
\{
    \s* ([a-zA-Z_][a-zA-Z0-9_-]*) \s*
    (?:
        : \s* ([^{}]*(?:\{(?-1)\}[^{}]*)*)
    )?
\}
REGEX;

    private array $staticRoutes = [];

    private array $variableRoutes = [];

    private array $allowedMethods = [];

    /**
     * Adds a route to the data generator.
     *
     * @param array $methods
     * @param string $routePath
     * @param mixed $handler
     */
    public function add(array $methods, string $routePath, $handler): void
    {
        $routePathTokens = $this->parsePath($routePath);

        foreach ($methods as $method) {
            foreach ($routePathTokens as $path) {
                if (count($path) === 1 && is_string($path[0])) {
                    $this->addStaticRoute($method, $path[0], $handler);
                    continue;
                }

                $this->addVariableRoute($method, $path, $handler);
            }
        }
    }

    /**
     * Parses a route path string into multiple route token arrays.
     *
     * @param string $route Route string to parse
     *
     * @return mixed[][] Array of route data arrays
     */
    public function parsePath(string $route): array
    {
        $noClosingOptionals = rtrim($route, ']');
        $numOptionals = strlen($route) - strlen($noClosingOptionals);

        // split on `[` while skipping placeholders
        $pattern = '~' . self::PLACEHOLDER_REGEX . '(*SKIP)(*F) | \[~x';
        $segments = preg_split($pattern, $noClosingOptionals);

        if ($numOptionals !== count($segments) - 1) {
            // if there are any `]` in the middle of the route, throw a more specific error message
            if (
                preg_match('~' . self::PLACEHOLDER_REGEX . '(*SKIP)(*F) | \]~x', $noClosingOptionals)
            ) {
                throw new BadRouteException(
                    'Optional segments (i.e. parts enclosed within `[]`) can only occur at the end of a route'
                );
            }

            throw new BadRouteException("Number of opening '[' and closing ']' do not match");
        }

        $currentRoute = '';
        $routeTokens = [];

        foreach ($segments as $key => $segment) {
            if ($segment === '' && $key !== 0) {
                throw new BadRouteException('Optional segments (i.e. parts enclosed within `[]`) cannot be empty');
            }

            $currentRoute .= $segment;
            $routeTokens[] = self::parseRouteTokens($currentRoute);
        }

        return $routeTokens;
    }

    /**
     * @param string $method
     * @param string $uri
     *
     * @return array
     *
     * @throws MethodNotAllowedException
     * @throws RouteNotFoundException
     */
    public function getRouteData(string $method, string $uri): array
    {
        if (isset($this->staticRoutes[$method][$uri])) {
            return [$this->staticRoutes[$method][$uri], []];
        }

        $routeData = self::getVariableRouteData($this->variableRoutes[$method] ?? [], $method, $uri);
        if (! empty($routeData)) {
            return $routeData;
        }

        // for `HEAD` requests, attempt fallback to `GET`
        if ($method === 'HEAD') {
            return $this->getRouteData('GET', $uri);
        }

        // if nothing else matches, try fallback routes
        if (isset($this->staticRoutes['*'][$uri])) {
            return [$this->staticRoutes['*'][$uri], []];
        }

        $routeData = self::getVariableRouteData($this->variableRoutes['*'] ?? [], '*', $uri);
        if (! empty($routeData)) {
            return $routeData;
        }

        if (count($this->getAllowedMethods($uri))) {
            throw new MethodNotAllowedException($method);
        }

        throw new RouteNotFoundException($uri);
    }

    public function getAllowedMethods(string $uri): array
    {
        if (isset($this->allowedMethods[$uri])) {
            return $this->allowedMethods[$uri];
        }

        $this->allowedMethods[$uri] = [];

        foreach ($this->variableRoutes as $method => $routeData) {
            $result = self::getVariableRouteData($routeData, $method, $uri);
            if (! empty($result)) {
                $this->allowedMethods[$uri][] = $method;
            }
        }

        return $this->allowedMethods[$uri];
    }

    /**
     * Add a static route (i.e. route with no variables).
     *
     * @param string $method
     * @param string $path
     * @param mixed $handler
     *
     * @throws BadRouteException
     */
    private function addStaticRoute(string $method, string $path, $handler): void
    {
        if (isset($this->staticRoutes[$method][$path])) {
            throw new BadRouteException(sprintf(
                'Cannot register two routes matching "%s" for method "%s"',
                $path,
                $method
            ));
        }

        $variableRoutes = $this->variableRoutes[$method] ?? [];

        foreach ($variableRoutes as $route) {
            if (preg_match('~^' . $route['regex'] . '$~', $path)) {
                throw new BadRouteException(sprintf(
                    'Static route "%s" is shadowed by previously defined variable route "%s" for method "%s"',
                    $path,
                    $route['regex'],
                    $method
                ));
            }
        }

        $this->staticRoutes[$method][$path] = $handler;
        $this->allowedMethods[$path] = [...$this->allowedMethods[$path] ?? [], $method];
    }

    /**
     * Add route that consists of variables.
     *
     * @param string $method
     * @param array $pathData
     * @param mixed $handler
     *
     * @throws BadRouteException
     */
    private function addVariableRoute(string $method, array $pathData, $handler): void
    {
        [$regex, $vars] = self::buildRegexForRoute($pathData);

        if (isset($this->variableRoutes[$method][$regex])) {
            throw new BadRouteException(sprintf(
                'Cannot register two routes matching "%s" for method "%s"',
                $regex,
                $method
            ));
        }

        $this->variableRoutes[$method][$regex] = [
            'method' => $method,
            'handler' => $handler,
            'regex' => $regex,
            'vars' => $vars
        ];
    }

    private static function regexHasCapturingGroups(string $regex): bool
    {
        // needs to have at least a ( to contain a capturing group
        return strpos($regex, '(') !== false
            // semi-accurate detection for capturing groups
            && (bool) preg_match(
                '~
                    (?:
                        \(\?\(
                    | \[ [^\]\\\\]* (?: \\\\ . [^\]\\\\]* )* \]
                    | \\\\ .
                    ) (*SKIP)(*FAIL) |
                    \(
                    (?!
                        \? (?! <(?![!=]) | P< | \' )
                    | \*
                    )
                ~x',
                $regex
            );
    }

    /**
     * @param array $routeData
     *
     * @return array
     *
     * @throws BadRouteException
     */
    private static function buildRegexForRoute(array $routeData): array
    {
        $regex = '';
        $vars = [];

        foreach ($routeData as $pathSegment) {
            if (is_string($pathSegment)) {
                $regex .= preg_quote($pathSegment, '~');
                continue;
            }

            [$varName, $regexPart] = $pathSegment;

            if (isset($vars[$varName])) {
                throw new BadRouteException(sprintf(
                    'Cannot use the same placeholder "%s" twice',
                    $varName
                ));
            }

            if (self::regexHasCapturingGroups($regexPart)) {
                throw new BadRouteException(sprintf(
                    'Regex "%s" for parameter "%s" contains a capturing group',
                    $regexPart,
                    $varName
                ));
            }

            $vars[$varName] = $varName;
            $regex .= '(' . $regexPart . ')';
        }

        return [$regex, $vars];
    }

    /**
     * Parse a route string that does not contain optional segments.
     *
     * @param string $routePattern A route pattern with no optional segments.
     *
     * @return array
     */
    private static function parseRouteTokens(string $routePattern): array
    {
        $pattern = '~' . self::PLACEHOLDER_REGEX . '~x';
        // check if any placeholders (i.e. `{}`) exist
        if (! preg_match_all($pattern, $routePattern, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER)) {
            return [$routePattern];
        }

        $index = 0;
        $routeTokens = [];

        // placeholders exist?
        foreach ($matches as $match) {
            $offset = $match[0][1];

            if ($offset > $index) {
                $routeTokens[] = substr($routePattern, $index, $offset - $index);
            }

            $routeTokens[] = [
                // path
                $match[1][0],
                // pattern
                isset($match[2][0]) ? trim($match[2][0]) : '[^/]+',
            ];

            $index = $offset + strlen($match[0][0]);
        }

        if ($index !== strlen($routePattern)) {
            $routeTokens[] = substr($routePattern, $index);
        }

        return $routeTokens;
    }

    private static function processRouteChunks(array $routeMap): array
    {
        $routeMapCollection = [];
        $regexes = [];
        $numGroups = 0;

        foreach ($routeMap as $regex => $route) {
            $numVariables = count($route['vars']);
            $numGroups = max($numGroups, $numVariables);

            $regexes[] = $regex . str_repeat('()', $numGroups - $numVariables);
            $routeMapCollection[$numGroups + 1] = [
                'handler' => $route['handler'],
                'vars' => $route['vars']
            ];

            ++$numGroups;
        }

        return [
            'regex' => '~^(?|' . implode('|', $regexes) . ')$~',
            'routeMap' => $routeMapCollection
        ];
    }

    private static function generateVariableRouteData(
        array $routeData,
        string $method,
    ): array {
        if (empty($routeData)) {
            return [];
        }

        $approxChunkSize = 10;
        $count = count($routeData);
        $numParts = max(1, round($count / $approxChunkSize));
        $chunkSize = (int) ceil($count / $numParts);
        $chunks = array_chunk($routeData, $chunkSize, true);

        $data[$method] = array_map('self::processRouteChunks', $chunks);

        return $data;
    }

    private static function getVariableRouteData(
        array $routeData,
        string $method,
        string $uri,
    ): array {
        $generatedRouteData = self::generateVariableRouteData($routeData, $method);
        $routeMethodData = $generatedRouteData[$method] ?? [];

        foreach ($routeMethodData as $data) {
            if (! preg_match($data['regex'], $uri, $matches)) {
                continue;
            }

            $routeMap = $data['routeMap'][count($matches)];

            $vars = [];
            $i = 0;

            foreach ($routeMap['vars'] as $varName) {
                $vars[$varName] = $matches[++$i];
            }

            return [$routeMap['handler'], $vars];
        }

        return [];
    }
}
