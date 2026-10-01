#!/usr/bin/env php
<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Route and controller smoke test
|--------------------------------------------------------------------------
|
| Walks the registered routes and, for every controller-backed route, asks the
| container to build the controller. That single step catches the failures
| that syntax linting cannot:
|
|   * a missing `use` import (class not found)
|   * an unsatisfiable constructor dependency (DI misconfiguration)
|   * a controller that cannot be instantiated at all
|
| It also prints the middleware stack, so the role separation between buyer,
| seller and admin areas can be verified at a glance.
|
| Run: php tools/route-check.php
|
*/

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Routing\Route;

$root = dirname(__DIR__);

require $root.'/vendor/autoload.php';

$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$router = $app->make('router');

$failures = 0;
$checked = 0;
$controllers = [];

/** @var Route $route */
foreach ($router->getRoutes() as $route) {
    $uri = $route->uri();

    if (! str_starts_with($uri, 'api/')) {
        continue;
    }

    $checked++;

    $methods = implode('|', array_values(array_diff($route->methods(), ['HEAD'])));

    // Resolve the controller class, if this route uses one.
    $action = $route->getAction('uses');

    if (is_string($action) && str_contains($action, '@')) {
        [$class] = explode('@', $action, 2);

        $controllers[$class] = true;
    }

    try {
        $middleware = $route->gatherMiddleware();
    } catch (Throwable $e) {
        $failures++;
        printf("  FAIL  %-6s %-52s %s\n", $methods, '/'.$uri, $e->getMessage());

        continue;
    }

    // Show only the meaningful guards, not framework plumbing.
    $guards = array_values(array_filter($middleware, static function ($m): bool {
        return is_string($m) && (
            str_contains($m, 'auth') ||
            str_contains($m, 'role:') ||
            str_contains($m, 'throttle:') ||
            str_contains($m, 'active')
        );
    }));

    $guards = array_map(
        static fn (string $m): string => str_replace(
            ['Illuminate\\Routing\\Middleware\\', 'App\\Http\\Middleware\\'],
            '',
            $m,
        ),
        $guards,
    );

    printf(
        "  %-6s %-52s %s\n",
        $methods,
        '/'.$uri,
        $guards === [] ? '-' : implode(', ', $guards),
    );
}

printf("\n%d routes; %d distinct controllers\n", $checked, count($controllers));

// Instantiating each controller proves every dependency is resolvable.
foreach (array_keys($controllers) as $class) {
    try {
        $app->make($class);
    } catch (Throwable $e) {
        $failures++;
        printf("  FAIL  cannot resolve %s: %s\n", $class, $e->getMessage());
    }
}

if ($failures === 0) {
    printf("OK  all %d controllers resolved\n\n", count($controllers));
} else {
    printf("\n%d FAILURES\n\n", $failures);
}

exit($failures === 0 ? 0 : 1);
