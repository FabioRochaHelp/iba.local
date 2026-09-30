<?php

declare(strict_types=1);

/**
 * Importa a planilha de cadastro de atletas (CSV ou XLSX).
 *
 *   php scripts/import_planilha.php <arquivo> [--mes=AAAA-MM] [--dry-run] [--criar-abertas]
 *
 *   --dry-run        simula tudo e mostra o relatório, sem gravar nada
 *   --mes=AAAA-MM    mês da coluna "Mensalidade" (padrão: mês do cabeçalho no ano atual)
 *   --criar-abertas  cria mensalidade "aberta" para quem está sem pagamento na planilha
 */

use App\Services\Import\AthleteImportService;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$container = require dirname(__DIR__) . '/bootstrap/app.php';

$args = array_slice($argv, 1);
$file = null;
$options = ['dry_run' => false, 'open_unpaid' => false, 'month' => null];
foreach ($args as $arg) {
    if ($arg === '--dry-run') {
        $options['dry_run'] = true;
    } elseif ($arg === '--criar-abertas') {
        $options['open_unpaid'] = true;
    } elseif (str_starts_with($arg, '--mes=')) {
        $options['month'] = substr($arg, 6);
        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $options['month'])) {
            fwrite(STDERR, "Mês inválido. Use AAAA-MM.\n");
            exit(1);
        }
    } elseif (!str_starts_with($arg, '--')) {
        $file = $arg;
    }
}

if ($file === null || !is_file($file)) {
    fwrite(STDERR, "Uso: php scripts/import_planilha.php <arquivo.csv|xlsx> [--mes=AAAA-MM] [--dry-run] [--criar-abertas]\n");
    exit(1);
}

try {
    $report = $container->get(AthleteImportService::class)->importFile($file, $options);
} catch (Throwable $e) {
    fwrite(STDERR, 'Erro: ' . $e->getMessage() . "\n");
    exit(1);
}

$title = $report['dry_run'] ? 'SIMULAÇÃO (nada foi gravado)' : 'IMPORTAÇÃO CONCLUÍDA';
echo "\n=== {$title} ===\n";
echo 'Mês da mensalidade: ' . ($report['month'] ?? 'não identificado') . "\n\n";

foreach ($report['lines'] as $line) {
    printf("Linha %3d  %-45s %s\n", $line['line'], mb_strimwidth((string) $line['name'], 0, 45, '…'), $line['status']);
    foreach ($line['issues'] as $issue) {
        echo "           ⚠ {$issue}\n";
    }
}

echo "\nResumo:\n";
printf("  Atletas criados ........ %d\n", $report['athletes_created']);
printf("  Atletas já existentes .. %d\n", $report['athletes_skipped']);
printf("  Responsáveis novos ..... %d (reaproveitados: %d)\n", $report['guardians_created'], $report['guardians_reused']);
printf("  Mensalidades ........... %d (cortesias: %d, parciais: %d)\n", $report['invoices'], $report['courtesies'], count($report['partial_payments']));
printf("  Pagamentos ............. %d\n", $report['payments']);
printf("  Pedidos de uniforme .... %d\n", $report['uniform_orders']);
printf("  Patrocínios ............ %d\n", $report['sponsorships']);
echo $report['dry_run'] ? "\nRode sem --dry-run para gravar.\n" : "\n";
