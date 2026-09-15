<?php

declare(strict_types=1);

namespace GB\Support;

use Closure;
use RuntimeException;

/**
 * Enrutador con parámetros de ruta y cadena de middleware.
 *
 * Las rutas se declaran en `config/routes.php`. Ejemplo:
 *
 *     $router->get('/cursos/{slug}', 'CourseController@show', ['auth'], 'courses.show');
 *     $router->post('/admin/bloques/{id}', 'Admin\BlockController@update', ['auth', 'role:SuperAdmin', 'csrf']);
 */
final class Router
{
    /**
     * @var array<int, array{
     *     method: string,
     *     path: string,
     *     regex: string,
     *     params: array<int, string>,
     *     handler: string,
     *     middleware: array<int, string>,
     *     name: ?string
     * }>
     */
    private array $routes = [];

    /** @var string[] nombre de ruta => ruta compilada */
    private array $namedRoutes = [];

    /** @var array<string, string> alias de middleware => clase */
    private array $middlewareAliases = [];

    /** @var array<int, string> middleware que se aplican a toda petición */
    private array $globalMiddleware = [];

    /** @var (Closure(Request): Response)|null */
    private ?Closure $fallback = null;

    public function __construct(private Container $container)
    {
    }

    public function alias(string $name, string $class): void
    {
        $this->middlewareAliases[$name] = $class;
    }

    /**
     * Middleware que se ejecutan antes que los declarados en cada ruta.
     *
     * @param array<int, string> $middleware
     */
    public function global(array $middleware): void
    {
        $this->globalMiddleware = $middleware;
    }

    /** @param (Closure(Request): Response) $handler */
    public function fallback(Closure $handler): void
    {
        $this->fallback = $handler;
    }

    /** @param array<int, string> $middleware */
    public function get(string $path, string $handler, array $middleware = [], ?string $name = null): void
    {
        $this->add('GET', $path, $handler, $middleware, $name);
    }

    /** @param array<int, string> $middleware */
    public function post(string $path, string $handler, array $middleware = [], ?string $name = null): void
    {
        $this->add('POST', $path, $handler, $middleware, $name);
    }

    /** @param array<int, string> $middleware */
    public function put(string $path, string $handler, array $middleware = [], ?string $name = null): void
    {
        $this->add('PUT', $path, $handler, $middleware, $name);
    }

    /** @param array<int, string> $middleware */
    public function patch(string $path, string $handler, array $middleware = [], ?string $name = null): void
    {
        $this->add('PATCH', $path, $handler, $middleware, $name);
    }

    /** @param array<int, string> $middleware */
    public function delete(string $path, string $handler, array $middleware = [], ?string $name = null): void
    {
        $this->add('DELETE', $path, $handler, $middleware, $name);
    }

    /** @param array<int, string> $middleware */
    public function add(
        string $method,
        string $path,
        string $handler,
        array $middleware = [],
        ?string $name = null
    ): void {
        [$regex, $params] = $this->compile($path);

        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $path,
            'regex' => $regex,
            'params' => $params,
            'handler' => $handler,
            'middleware' => $middleware,
            'name' => $name,
        ];

        if ($name !== null) {
            $this->namedRoutes[$name] = '/' . trim($path, '/');
        }
    }

    /** @param array<string, string|int> $params */
    public function url(string $name, array $params = []): string
    {
        $path = $this->namedRoutes[$name] ?? null;

        if ($path === null) {
            throw new RuntimeException(sprintf('No existe la ruta con nombre "%s".', $name));
        }

        foreach ($params as $key => $value) {
            $path = preg_replace('/\{' . preg_quote((string) $key, '/') . '\??\}/', rawurlencode((string) $value), $path) ?? $path;
        }

        return $path;
    }

    public function hasRoute(string $name): bool
    {
        return isset($this->namedRoutes[$name]);
    }

    public function dispatch(Request $request): Response
    {
        // La petición que se está atendiendo queda registrada en el contenedor.
        // Las vistas y los ayudantes la consultan para saber en qué pantalla
        // están (por ejemplo, la ayuda de cada pantalla del panel). Sin esto,
        // esa consulta devolvía una petición distinta —la primera que se creó— y
        // la información dependiente de la dirección actual se perdía.
        $this->container->set(Request::class, $request);

        $path = $request->path();
        $method = $request->method();
        $allowedMethods = [];

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $path, $matches) !== 1) {
                continue;
            }

            if ($route['method'] !== $method) {
                $allowedMethods[] = $route['method'];

                continue;
            }

            $params = [];

            foreach ($route['params'] as $param) {
                $params[$param] = isset($matches[$param]) && $matches[$param] !== ''
                    ? rawurldecode((string) $matches[$param])
                    : null;
            }

            $request->setRouteParams($params);
            $request->setRouteName($route['name']);

            $pipeline = array_merge($this->globalMiddleware, $route['middleware']);

            return $this->runPipeline($pipeline, $request, function (Request $current) use ($route): Response {
                return $this->callAction($route, $current);
            });
        }

        if ($allowedMethods !== []) {
            return $this->renderFallback($request, 405, array_values(array_unique($allowedMethods)));
        }

        return $this->renderFallback($request, 404);
    }

    /**
     * Compila una ruta declarativa a una expresión regular.
     *
     * @return array{0: string, 1: array<int, string>}
     */
    private function compile(string $path): array
    {
        $params = [];
        $regex = '#^';

        foreach (explode('/', trim($path, '/')) as $segment) {
            if ($segment === '') {
                continue;
            }

            $optional = str_ends_with($segment, '?}');

            if (preg_match('/^\{([A-Za-z_][A-Za-z0-9_]*)\??\}$/', $segment, $matches) === 1) {
                $params[] = $matches[1];
                $regex .= $optional ? '(?:/(?P<' . $matches[1] . '>[^/]+))?' : '/(?P<' . $matches[1] . '>[^/]+)';

                continue;
            }

            $regex .= '/' . preg_quote($segment, '#');
        }

        return [$regex . '/?$#', $params];
    }

    /**
     * @param array<int, string> $definitions
     * @param Closure(Request): Response $destination
     */
    private function runPipeline(array $definitions, Request $request, Closure $destination): Response
    {
        $middleware = array_map(fn (string $definition): Closure => $this->resolveMiddleware($definition), $definitions);

        $next = $destination;

        foreach (array_reverse($middleware) as $layer) {
            $previous = $next;
            $next = static fn (Request $current): Response => $layer($current, $previous);
        }

        return $next($request);
    }

    private function resolveMiddleware(string $definition): Closure
    {
        $parts = explode(':', $definition, 2);
        $name = trim($parts[0]);
        $args = isset($parts[1]) && $parts[1] !== '' ? explode(',', $parts[1]) : [];

        $class = $this->middlewareAliases[$name] ?? $name;

        if (!str_contains($class, '\\')) {
            $class = 'GB\\Middleware\\' . $class;
        }

        $instance = $this->container->build($class);

        if (!method_exists($instance, 'handle')) {
            throw new RuntimeException(sprintf('El middleware "%s" no implementa handle().', $class));
        }

        return static fn (Request $request, Closure $next): Response => $instance->handle($request, $next, $args);
    }

    /**
     * @param array{
     *     method: string,
     *     path: string,
     *     regex: string,
     *     params: array<int, string>,
     *     handler: string,
     *     middleware: array<int, string>,
     *     name: ?string
     * } $route
     */
    private function callAction(array $route, Request $request): Response
    {
        $parts = explode('@', $route['handler'], 2);
        $class = $parts[0];
        $action = $parts[1] ?? 'index';

        // Los controladores viven siempre bajo `GB\Controllers`. Se completa el
        // prefijo también cuando se indica un subespacio (`Admin\CursoController`),
        // que es como están declaradas las rutas del panel.
        if (!str_starts_with($class, 'GB\\')) {
            $class = 'GB\\Controllers\\' . ltrim($class, '\\');
        }

        $controller = $this->container->build($class);

        if (!method_exists($controller, $action)) {
            throw new RuntimeException(sprintf('El controlador "%s" no tiene la acción "%s".', $class, $action));
        }

        $result = $controller->{$action}($request);

        if ($result instanceof Response) {
            return $result;
        }

        return new Response((string) $result, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /** @param array<int, string> $allowed */
    private function renderFallback(Request $request, int $status, array $allowed = []): Response
    {
        if ($this->fallback === null) {
            return new Response(
                $status === 405 ? 'Método no permitido.' : 'Página no encontrada.',
                $status,
                ['Content-Type' => 'text/plain; charset=UTF-8']
            );
        }

        $response = ($this->fallback)($request);
        $headers = $allowed === [] ? [] : ['Allow' => implode(', ', $allowed)];

        return new Response($response->content(), $status, $response->headers() + $headers);
    }
}
