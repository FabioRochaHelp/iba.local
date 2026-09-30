<?php

declare(strict_types=1);

/**
 * Gera as mensalidades do mês (idempotente — pode rodar várias vezes).
 *
 *   php scripts/generate_invoices.php [--mes=AAAA-MM]
 *
 * Cron do cPanel sugerido (dia 1º às 6h):
 *   0 6 1 * *  /usr/local/bin/php ~/app/scripts/generate_invoices.php
 */

use App\Services\Finance\InvoiceGeneratorService;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$container = require dirname(__DIR__) . '/bootstrap/app.php';

$month = date('Y-m');
foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--mes=')) {
        $month = substr($arg, 6);
    }
}
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
    fwrite(STDERR, "Mês inválido. Use AAAA-MM.\n");
    exit(1);
}

$result = $container->get(InvoiceGeneratorService::class)->generate($month);
printf("[%s] Mensalidades %s: %d criadas, %d já existentes, total R$ %s\n", date('Y-m-d H:i'), $month, $result['created'], $result['skipped'], $result['total']);
