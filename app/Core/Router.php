<?php
/**
 * File: app/Core/Router.php
 * Purpose: Defines class Router for the app/Core module.
 * Classes:
 *   - Router
 * Functions:
 *   - get()
 *   - post()
 *   - group()
 *   - dispatch()
 *   - loadAndDispatch()
 *   - add()
 */

declare(strict_types=1);

namespace Acme\Panel\Core;

class Router
{



	private array $routes = [];




	private array $groupMiddlewareStack = [];

	public function get(string $path, array $handler): self
	{
		return $this->add(['GET'], $path, $handler);
	}

	public function post(string $path, array $handler): self
	{
		return $this->add(['POST'], $path, $handler);
	}

	public function match(array $methods, string $path, array $handler): self
	{
		return $this->add($methods, $path, $handler);
	}

	public function group(array $middleware, callable $callback): void
	{
		$this->groupMiddlewareStack[] = $middleware;
		$callback($this);
		array_pop($this->groupMiddlewareStack);
	}

	public function dispatch(Request $request): Response
	{
		$uri = rtrim($request->uri, '/') ?: '/';

		foreach ($this->routes as $route) {
			if (!in_array($request->method, $route['methods'], true)) {
				continue;
			}

			if ($route['path'] !== $uri) {
				continue;
			}

			[$class, $method] = $route['handler'];

			if (!class_exists($class)) {
				return new Response('<h1>500 Controller Missing</h1>', 500);
			}

			$controller = new $class();
			$core = static fn (Request $req) => $controller->{$method}($req);

			$pipeline = array_reverse($route['middleware']);

			$runner = array_reduce(
				$pipeline,
				static function (callable $next, string $middlewareClass): callable {
					return static function (Request $req) use ($next, $middlewareClass) {
						$middleware = new $middlewareClass();

						return $middleware->handle($req, $next);
					};
				},
				$core
			);

			return $runner($request);
		}

		return new Response('<h1>404 Not Found</h1>', 404);
	}

	public static function loadAndDispatch(Request $request): Response
	{
		$router = new self();
		$routesFile = __DIR__ . '/../../routes/web.php';

		if (is_file($routesFile)) {
			$registrar = require $routesFile;
			$registrar($router);
		}

		return $router->dispatch($request);
	}

	private function add(array $methods, string $path, array $handler): self
	{
		$middleware = [];

		foreach ($this->groupMiddlewareStack as $layer) {
			$middleware = array_merge($middleware, $layer);
		}

		$this->routes[] = [
			'methods' => $methods,
			'path' => rtrim($path, '/') ?: '/',
			'handler' => $handler,
			'middleware' => $middleware,
		];

		return $this;
	}
}

