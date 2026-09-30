<?php

declare(strict_types=1);

/**
 * Limpeza diária (agende no cron do cPanel):
 *   0 3 * * *  /usr/local/bin/php ~/app/scripts/maintenance.php
 *
 * - sessões expiradas
 * - contadores de rate limit antigos
 * - tentativas de login com mais de 30 dias
 * - logs de aplicação com mais de 90 dias
 */

use App\Core\Config\Config;
use App\Core\Database\Connection;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$container = require dirname(__DIR__) . '/bootstrap/app.php';
$db = $container->get(Connection::class);
$config = $container->get(Config::class);

$absolute = (int) $config->get('session.absolute_hours', 8) * 3600;
$sessions = $db->execute('DELETE FROM sessions WHERE last_activity < ?', [time() - $absolute]);
$limits = $db->execute('DELETE FROM rate_limits WHERE window_start < ?', [time() - 86400]);
$attempts = $db->execute('DELETE FROM login_attempts WHERE attempted_at < ?', [date('Y-m-d H:i:s', time() - 30 * 86400)]);

$logs = 0;
foreach (glob((string) $config->get('paths.logs') . '/*.log') ?: [] as $file) {
    if (filemtime($file) < time() - 90 * 86400 && @unlink($file)) {
        $logs++;
    }
}

printf("[%s] Manutenção: %d sessões, %d rate limits, %d tentativas de login, %d logs removidos\n", date('Y-m-d H:i'), $sessions, $limits, $attempts, $logs);
