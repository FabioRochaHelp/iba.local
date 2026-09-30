<?php

declare(strict_types=1);

use App\Core\Config\Config;
use App\Core\Config\Env;
use App\Core\Container\Container;
use App\Core\Http\Kernel;
use App\Core\Routing\Router;

/**
 * Monta a aplicação e devolve o Container pronto.
 */

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

Env::load($root . '/.env');
$config = new Config(require $root . '/config/app.php');

date_default_timezone_set((string) $config->get('app.timezone'));
mb_internal_encoding('UTF-8');

ini_set('display_errors', $config->get('app.debug') && PHP_SAPI === 'cli' ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', $config->get('paths.logs') . '/php-error.log');
ini_set('expose_php', '0');
error_reporting(E_ALL);

// Converte warnings/notices em exceções (falha cedo, sem saída acidental).
set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});

$container = new Container();
(require $root . '/config/container.php')($container, $config);

$container->singleton(Router::class, static function () use ($root): Router {
    $router = new Router();
    (require $root . '/config/routes.php')($router);

    return $router;
});

$container->singleton(Kernel::class, static function (Container $c) use ($root): Kernel {
    $middleware = require $root . '/config/middleware.php';

    return new Kernel($c, $c->get(Router::class), $middleware['global'], $middleware['aliases']);
});

return $container;
