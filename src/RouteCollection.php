<?php

/**
 * BitFrame Framework (https://www.bitframephp.com)
 *
 * @author    Daniyal Hamid
 * @copyright Copyright (c) 2017-2019 Daniyal Hamid (https://designcise.com)
 * @license   https://bitframephp.com/about/license MIT License
 */

declare(strict_types=1);

namespace BitFrame\FastRoute;

use BitFrame\FastRoute\Exception\{
    MethodNotAllowedException,
    RouteNotFoundException,
    BadRouteException
};

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
     * The handler doesn't necessarily need to be a callable, it
     * can be arbitrary data that will be returned when the route
     * matches.
     *
     * @param array $methods
     * @param string $routePath
     * @param mixed $handler
     */
    public function add(array $methods, string $routePath, $handler)
    {
        $routePathTokens = $this->parsePath($routePath);

        foreach ($methods as $method) {
            foreach ($routePathTokens as $path) {
                if (\count($path) === 1 && \is_string($path[0])) {
                    $this->addStaticRoute($method, $path[0], $handler);
                } else {
                    $this->addVariableRoute($method, $path, $handler);
                }
            }
        }
    }

    /**
     * Parses a route path string into multiple route token arrays.
     *
     * @param string $route Route string to parse
     * @return mixed[][] Array of route data arrays
     */
    public function parsePath(string $route): array
    {
        $routeWithoutClosingOptionals = \rtrim($route, ']');
        $numOptionals = \strlen($route) - \strlen($routeWithoutClosingOptionals);

        // split on `[` while skipping placeholders
        $segments = \preg_split(
            '~' . self::PLACEHOLDER_REGEX . '(*SKIP)(*F) | \[~x', $routeWithoutClosingOptionals
        );

        if ($numOptionals !== \count($segments) - 1) {
            // if there are any `]` in the middle of the route, throw a more specific error message
            if (
                \preg_match('~' . self::PLACEHOLDER_REGEX . '(*SKIP)(*F) | \]~x', $routeWithoutClosingOptionals)
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
                throw new BadRouteException(
                    'Optional segments (i.e. parts enclosed within `[]`) cannot be empty'
                );
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

        if (isset($this->variableRoutes[$method])) {
            $result = self::getVariableRouteData($this->variableRoutes[$method], $method, $uri);
            if (! empty($result)) {
                return $result;
            }
        }

        // for `HEAD` requests, attempt fallback to `GET`
        if ($method === 'HEAD') {
            return $this->getRouteData('GET', $uri);
        }

        // if nothing else matches, try fallback routes
        if (isset($this->staticRoutes['*'][$uri])) {
            return [$this->staticRoutes['*'][$uri], []];
        }

        if (isset($this->variableRoutes['*'])) {
            $result = self::getVariableRouteData($this->variableRoutes['*'], '*', $uri);
            if (! empty($result)) {
                return $result;
            }
        }

        if (\count($this->getAllowedMethods($uri))) {
            throw new MethodNotAllowedException($method);
        }

        throw new RouteNotFoundException($uri);
    }

    /**
     * @param string $uri
     * 
     * @return array
     */
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
            throw new BadRouteException(\sprintf(
                'Cannot register two routes matching "%s" for method "%s"',
                $path, $method
            ));
        }

        if (isset($this->variableRoutes[$method])) {
            foreach ($this->variableRoutes[$method] as $route) {
                if (\preg_match('~^' . $route['regex'] . '$~', $path)) {
                    throw new BadRouteException(\sprintf(
                        'Static route "%s" is shadowed by previously defined variable route "%s" for method "%s"',
                        $path, $route['regex'], $method
                    ));
                }
            }
        }

        $this->staticRoutes[$method][$path] = $handler;

        if (! isset($this->allowedMethods[$path])) {
            $this->allowedMethods[$path] = [];
        }
        $this->allowedMethods[$path][] = $method;
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
            throw new BadRouteException(\sprintf(
                'Cannot register two routes matching "%s" for method "%s"',
                $regex, $method
            ));
        }

        $this->variableRoutes[$method][$regex] = [
            'method' => $method,
            'handler' => $handler,
            'regex' => $regex,
            'vars' => $vars
        ];
    }

    /**
     * @param string $regex
     * 
     * @return bool
     */
    private static function regexHasCapturingGroups(string $regex): bool
    {
        // needs to have at least a ( to contain a capturing group
        return \strpos($regex, '(') !== false 
            // semi-accurate detection for capturing groups
            && (bool) \preg_match(
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
            if (\is_string($pathSegment)) {
                $regex .= \preg_quote($pathSegment, '~');
                continue;
            }

            [$varName, $regexPart] = $pathSegment;

            if (isset($vars[$varName])) {
                throw new BadRouteException(\sprintf(
                    'Cannot use the same placeholder "%s" twice', $varName
                ));
            }

            if (self::regexHasCapturingGroups($regexPart)) {
                throw new BadRouteException(\sprintf(
                    'Regex "%s" for parameter "%s" contains a capturing group',
                    $regexPart, $varName
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
        // check if any placeholders (i.e. `{}`) exist
        if (! \preg_match_all(
            '~' . self::PLACEHOLDER_REGEX . '~x', $routePattern, $matches, PREG_OFFSET_CAPTURE | PREG_SET_ORDER
        )) {
            return [$routePattern];
        }

        $index = 0;
        $routeTokens = [];

        // placeholders exist?
        foreach ($matches as $match) {
            $offset = $match[0][1];

            if ($offset > $index) {
                $routeTokens[] = \substr($routePattern, $index, $offset - $index);
            }

            $routeTokens[] = [
                // path
                $match[1][0],
                // pattern
                isset($match[2][0]) ? \trim($match[2][0]) : '[^/]+',
            ];
            
            $index = $offset + \strlen($match[0][0]);
        }

        if ($index !== \strlen($routePattern)) {
            $routeTokens[] = \substr($routePattern, $index);
        }

        return $routeTokens;
    }

    /**
     * @param array $routeMap
     * 
     * @return array
     */
    private static function processRouteChunks(array $routeMap): array
    {
        $routeMapCollection = [];
        $regexes = [];
        $numGroups = 0;

        foreach ($routeMap as $regex => $route) {
            $numVariables = \count($route['vars']);
            $numGroups = \max($numGroups, $numVariables);

            $regexes[] = $regex . \str_repeat('()', $numGroups - $numVariables);
            $routeMapCollection[$numGroups + 1] = [
                'handler' => $route['handler'],
                'vars' => $route['vars']
            ];

            ++$numGroups;
        }

        return [
            'regex' => '~^(?|' . \implode('|', $regexes) . ')$~',
            'routeMap' => $routeMapCollection
        ];
    }

    /**
     * @param array $routeData
     * @param string $method
     *
     * @return array
     */
    private static function generateVariableRouteData(
        array $routeData,
        string $method
    ): array {
        $data = [];

        if (! empty($routeData)) {
            $approxChunkSize = 10;
            $count = \count($routeData);
            $numParts = \max(1, \round($count / $approxChunkSize));
            $chunkSize = (int) \ceil($count / $numParts);
            $chunks = \array_chunk($routeData, $chunkSize, true);

            $data[$method] = \array_map('self::processRouteChunks', $chunks);
        }

        return $data;
    }

    /**
     * @param array $routeData
     * @param string $method
     * @param string $uri
     * 
     * @return array
     */
    private static function getVariableRouteData(array $routeData, string $method, string $uri): array
    {
        $generatedRouteData = self::generateVariableRouteData($routeData, $method);

        if (isset($generatedRouteData[$method])) {
            foreach ($generatedRouteData[$method] as $data) {
                if (! \preg_match($data['regex'], $uri, $matches)) {
                    continue;
                }

                $routeMap = $data['routeMap'][\count($matches)];

                $vars = [];
                $i = 0;

                foreach ($routeMap['vars'] as $varName) {
                    $vars[$varName] = $matches[++$i];
                }
                
                return [$routeMap['handler'], $vars];
            }
        }

        return [];
    }
}
