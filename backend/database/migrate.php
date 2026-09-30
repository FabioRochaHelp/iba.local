<?php

declare(strict_types=1);

/**
 * Runner de migrations e seeds (somente CLI).
 *
 *   php database/migrate.php            aplica migrations pendentes
 *   php database/migrate.php --seed     aplica migrations + dados iniciais
 *   php database/migrate.php --status   lista o que já foi aplicado
 *   php database/migrate.php --dump     gera database/schema.sql (para importar via phpMyAdmin)
 *
 * Em hospedagem sem SSH: agende uma única execução pelo "Cron Jobs" do
 * cPanel (ex.: /usr/local/bin/php ~/app/database/migrate.php --seed) ou
 * importe database/schema.sql pelo phpMyAdmin e rode o seed pelo cron.
 */

use App\Core\Config\Config;
use App\Core\Config\Env;
use App\Core\Database\Connection;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';
Env::load($root . '/.env');
$config = new Config(require $root . '/config/app.php');
date_default_timezone_set((string) $config->get('app.timezone'));

$args = array_slice($argv, 1);
$migrationsDir = __DIR__ . '/migrations';
$files = glob($migrationsDir . '/*.sql') ?: [];
sort($files);

if (in_array('--dump', $args, true)) {
    $out = "-- Schema completo gerado em " . date('Y-m-d H:i') . "\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS = 0;\n\n";
    foreach ($files as $file) {
        $out .= "-- " . basename($file) . "\n" . trim((string) file_get_contents($file)) . "\n\n";
    }
    $out .= "SET FOREIGN_KEY_CHECKS = 1;\n";
    file_put_contents(__DIR__ . '/schema.sql', $out);
    echo "Gerado database/schema.sql\n";
    exit(0);
}

$db = $config->get('database');
if ((string) Env::get('DB_MIGRATE_USERNAME', '') !== '') {
    $db['username'] = (string) Env::get('DB_MIGRATE_USERNAME');
    $db['password'] = (string) Env::get('DB_MIGRATE_PASSWORD', '');
}
$conn = new Connection($db);
$pdo = $conn->pdo();

$pdo->exec('CREATE TABLE IF NOT EXISTS schema_migrations (
    migration VARCHAR(190) NOT NULL PRIMARY KEY,
    applied_at DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');

$applied = array_column($conn->fetchAll('SELECT migration FROM schema_migrations'), 'migration');

if (in_array('--status', $args, true)) {
    foreach ($files as $file) {
        $name = basename($file);
        printf("[%s] %s\n", in_array($name, $applied, true) ? 'x' : ' ', $name);
    }
    exit(0);
}

$count = 0;
foreach ($files as $file) {
    $name = basename($file);
    if (in_array($name, $applied, true)) {
        continue;
    }

    echo "Aplicando {$name}... ";
    $sql = (string) file_get_contents($file);
    // Remove comentários de linha e divide por ';' no fim da linha.
    $sql = (string) preg_replace('/^\s*--.*$/m', '', $sql);
    $statements = array_filter(array_map('trim', preg_split('/;\s*$/m', $sql) ?: []));

    // DDL faz commit implícito no MySQL; aplicamos em sequência e registramos ao final.
    foreach ($statements as $statement) {
        $pdo->exec($statement);
    }
    $conn->insert('schema_migrations', ['migration' => $name, 'applied_at' => date('Y-m-d H:i:s')]);
    echo "ok\n";
    $count++;
}
echo $count === 0 ? "Nenhuma migration pendente.\n" : "{$count} migration(s) aplicada(s).\n";

if (in_array('--seed', $args, true)) {
    foreach (glob(__DIR__ . '/seeds/*.php') ?: [] as $seed) {
        echo 'Seed ' . basename($seed) . "... ";
        (require $seed)($conn);
        echo "ok\n";
    }
}
