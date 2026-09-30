<?php

declare(strict_types=1);

/**
 * Front controller da API.
 *
 * Em desenvolvimento: php -S localhost:8765 public/index.php
 * Em produção (hospedagem compartilhada): este arquivo fica em
 * public_html/api/ e o código da aplicação em ~/app (fora do web root).
 */

use App\Core\Http\ExceptionHandler;
use App\Core\Http\Kernel;
use App\Core\Http\Request;

$candidates = [
    dirname(__DIR__),                 // desenvolvimento: backend/public -> backend
    dirname(__DIR__, 2) . '/app',     // produção: public_html/api -> ~/app
];
$appRoot = null;
foreach ($candidates as $candidate) {
    if (is_file($candidate . '/bootstrap/app.php')) {
        $appRoot = $candidate;
        break;
    }
}
if ($appRoot === null) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo '{"error":{"message":"Aplicação não configurada."}}';
    exit;
}

/** @var \App\Core\Container\Container $container */
$container = require $appRoot . '/bootstrap/app.php';

$request = Request::fromGlobals();
try {
    $response = $container->get(Kernel::class)->handle($request);
} catch (Throwable $e) {
    $response = $container->get(ExceptionHandler::class)->render($e, $request);
}
$response->send();
