<?php

declare(strict_types=1);

/**
 * Carga da planilha "CONTROLE MENSALIDADE.xlsx" (abas "CONTROLE DE ALUNOS" e "Mensalidades 2026").
 *
 *   php scripts/import_controle_mensalidade.php <arquivo.xlsx> [--dry-run]
 *
 * ATENÇÃO: a planilha é a fonte da verdade do financeiro de abr–set/2026 —
 * mensalidades e pagamentos desse período são APAGADOS e recriados.
 * Faça backup do banco antes e rode primeiro com --dry-run.
 */

use App\Services\Import\ControleMensalidadeImportService;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$container = require dirname(__DIR__) . '/bootstrap/app.php';

$file = null;
$dryRun = false;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--dry-run') {
        $dryRun = true;
    } elseif (!str_starts_with($arg, '--')) {
        $file = $arg;
    }
}
if ($file === null || !is_file($file)) {
    fwrite(STDERR, "Uso: php scripts/import_controle_mensalidade.php <arquivo.xlsx> [--dry-run]\n");
    exit(1);
}

try {
    $r = $container->get(ControleMensalidadeImportService::class)->import($file, $dryRun);
} catch (Throwable $e) {
    fwrite(STDERR, 'Erro: ' . $e->getMessage() . "\n");
    exit(1);
}

$list = static function (string $title, array $items): void {
    if ($items === []) {
        return;
    }
    echo "\n{$title} (" . count($items) . "):\n";
    foreach ($items as $item) {
        echo '  • ' . (is_array($item) ? "{$item['nome']} → {$item['casou_com']} [{$item['regra']}, {$item['planilha']}]" : $item) . "\n";
    }
};

echo "\n=== " . ($r['dry_run'] ? 'SIMULAÇÃO (nada foi gravado)' : 'CARGA CONCLUÍDA') . " ===\n";
printf("\nAtletas na planilha ........ %d\n", $r['people_in_sheet']);
printf("  novos .................... %d\n", $r['athletes_created']);
printf("  já cadastrados, completados %d\n", $r['athletes_updated']);
printf("  já cadastrados, sem mudança %d\n", $r['athletes_unchanged']);
printf("Responsáveis novos ......... %d (reaproveitados: %d)\n", $r['guardians_created'], $r['guardians_reused']);
printf("\nFinanceiro abr–set/2026 substituído:\n");
printf("  removidos ................ %d mensalidades, %d pagamentos\n", $r['finance']['invoices_removed'], $r['finance']['payments_removed']);
printf("  criados .................. %d mensalidades, %d pagamentos, %d cortesias\n", $r['finance']['invoices_created'], $r['finance']['payments_created'], $r['finance']['courtesies']);

echo "\nTotais por mês (planilha sem linhas repetidas × gravado):\n";
$ok = true;
foreach ($r['totals'] as $ym => $t) {
    $same = $t['planilha'] === $t['gravado'];
    $ok = $ok && $same;
    printf("  %s  R$ %10s  ×  R$ %10s  %s\n", $ym, number_format((float) $t['planilha'], 2, ',', '.'), number_format((float) $t['gravado'], 2, ',', '.'), $same ? 'OK' : 'DIFERENTE');
}
echo $ok ? "  ✔ todos os meses conferem\n" : "  ✘ há meses divergentes — verifique\n";

$list('Nomes casados por aproximação (confira)', $r['name_matches']);
$list('Casamentos ambíguos', $r['ambiguous']);
$list('Linhas repetidas na aba de mensalidades', $r['duplicates']);
$list('Só na aba de mensalidades (sem cadastro completo)', $r['only_payments_sheet']);
$list('Sem nenhum pagamento (cadastrados como ativos)', $r['without_payments']);
$list('Cadastrados no sistema mas fora da planilha (mantidos)', $r['db_not_in_sheet']);
$list('Dados completados em atletas existentes', $r['updated_details']);
$list('Valores acima do plano (pagos, conferir)', $r['above_plan']);
$list('Uniformes lançados', $r['uniforms_created']);
$list('Patrocínios lançados', $r['sponsorships_created']);
$list('Pontos de atenção', $r['attention']);
echo $r['dry_run'] ? "\nRode sem --dry-run para gravar (faça backup antes).\n" : "\n";
