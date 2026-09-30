<?php

declare(strict_types=1);

use App\Core\Config\Env;

$appUrl = (string) Env::get('APP_URL', 'http://localhost:5173');

return [
    'app' => [
        'env' => Env::get('APP_ENV', 'production'),
        'debug' => (bool) Env::get('APP_DEBUG', false),
        'url' => $appUrl,
        'timezone' => Env::get('APP_TIMEZONE', 'America/Sao_Paulo'),
        // Origens aceitas no header Origin/Referer (anti-CSRF). Separe por vírgula.
        'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', $appUrl)))),
    ],
    'database' => [
        'host' => Env::get('DB_HOST', '127.0.0.1'),
        'port' => Env::get('DB_PORT', '3306'),
        'database' => Env::get('DB_DATABASE', 'iba'),
        'username' => Env::get('DB_USERNAME', 'root'),
        'password' => (string) Env::get('DB_PASSWORD', ''),
        'charset' => 'utf8mb4',
    ],
    'session' => [
        'name' => Env::get('SESSION_NAME', 'IBASESSID'),
        'secure' => (bool) Env::get('SESSION_SECURE', true),
        'idle_minutes' => (int) Env::get('SESSION_IDLE_MINUTES', 30),
        'absolute_hours' => (int) Env::get('SESSION_ABSOLUTE_HOURS', 8),
    ],
    'finance' => [
        // Dia do mês em que a mensalidade vence.
        'due_day' => max(1, min(28, (int) Env::get('INVOICE_DUE_DAY', 10))),
    ],
    'paths' => [
        'logs' => dirname(__DIR__) . '/storage/logs',
    ],
];
