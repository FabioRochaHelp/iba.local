<?php

declare(strict_types=1);

/**
 * Verifica se a instalação está pronta para produção.
 *   php scripts/check_install.php
 * Pode ser executado uma vez pelo cron do cPanel (a saída chega por e-mail).
 */

use App\Core\Config\Config;
use App\Core\Database\Connection;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$root = dirname(__DIR__);
$results = [];
$check = static function (string $label, bool $ok, string $hint = '', bool $critical = true) use (&$results): void {
    $results[] = [$ok ? 'OK ' : ($critical ? 'ERRO' : 'AVISO'), $label, $ok ? '' : $hint];
};

$check('PHP >= 8.1 (atual ' . PHP_VERSION . ')', version_compare(PHP_VERSION, '8.1.0', '>='), 'Selecione PHP 8.1+ no cPanel (MultiPHP Manager).');
foreach (['pdo_mysql', 'mbstring', 'json', 'zip', 'simplexml'] as $ext) {
    $check("Extensão {$ext}", extension_loaded($ext), "Ative a extensão {$ext} no cPanel (Select PHP Version).", in_array($ext, ['pdo_mysql', 'mbstring', 'json'], true));
}
$check('Argon2id disponível', defined('PASSWORD_ARGON2ID'), 'Sem Argon2id o sistema usa bcrypt (seguro, apenas informativo).', false);
$check('Arquivo .env existe', is_file($root . '/.env'), 'Copie .env.example para .env e preencha.');
$check('vendor/ instalado', is_file($root . '/vendor/autoload.php'), 'Envie a pasta vendor/ gerada pelo build.');

if (!is_file($root . '/vendor/autoload.php')) {
    foreach ($results as [$s, $l, $h]) {
        printf("[%s] %s%s\n", $s, $l, $h ? " — {$h}" : '');
    }
    exit(1);
}

$container = require $root . '/bootstrap/app.php';
$config = $container->get(Config::class);

$check('APP_ENV=production', $config->get('app.env') === 'production', 'Defina APP_ENV=production.');
$check('APP_DEBUG desligado', !$config->get('app.debug'), 'Defina APP_DEBUG=false (senão erros internos podem vazar).');
$check('APP_URL com https://', str_starts_with((string) $config->get('app.url'), 'https://'), 'Use o endereço https:// do site em APP_URL.');
$check('SESSION_SECURE=true', (bool) $config->get('session.secure'), 'Defina SESSION_SECURE=true (cookie só via HTTPS).');
$check('ADMIN_PASSWORD vazio no .env', (string) \App\Core\Config\Env::get('ADMIN_PASSWORD', '') === '', 'Após criar o admin, apague ADMIN_PASSWORD do .env.', false);
$logs = (string) $config->get('paths.logs');
$check('storage/logs gravável', is_dir($logs) && is_writable($logs), "Dê permissão de escrita em {$logs} (755).");
$envPerms = is_file($root . '/.env') ? substr(sprintf('%o', fileperms($root . '/.env')), -3) : '---';
$check(".env com permissão restrita ({$envPerms})", in_array($envPerms, ['600', '640', '400', '440'], true), 'Ajuste para 600 no Gerenciador de Arquivos.', false);
$check('Pasta app fora do public_html', !str_contains($root, 'public_html'), 'A pasta app/ não pode ficar dentro de public_html.');

try {
    $db = $container->get(Connection::class);
    $db->pdo();
    $check('Conexão com o banco', true);
    $tables = array_column($db->fetchAll('SHOW TABLES'), array_key_first($db->fetchAll('SHOW TABLES')[0] ?? ['x' => 1]));
    foreach (['users', 'athletes', 'invoices', 'sessions', 'classes'] as $t) {
        $check("Tabela {$t}", in_array($t, $tables, true), 'Rode as migrations (database/migrate.php) ou importe database/schema.sql.');
    }
    $check('Existe administrador ativo', (int) $db->fetchValue("SELECT COUNT(*) FROM users WHERE role = 'admin' AND active = 1") > 0, 'Rode: php database/migrate.php --seed');
    $grants = implode(' ', array_column($db->fetchAll('SHOW GRANTS'), array_key_first($db->fetchAll('SHOW GRANTS')[0])));
    $check('Usuário do banco sem privilégio de DDL', !preg_match('/\b(ALL PRIVILEGES|DROP|ALTER|CREATE)\b/', $grants), 'O usuário da aplicação deveria ter só SELECT/INSERT/UPDATE/DELETE.', false);
} catch (Throwable $e) {
    $check('Conexão com o banco', false, 'Confira DB_* no .env: ' . $e->getMessage());
}

$errors = 0;
foreach ($results as [$status, $label, $hint]) {
    printf("[%s] %s%s\n", $status, $label, $hint ? " — {$hint}" : '');
    $errors += $status === 'ERRO' ? 1 : 0;
}
echo $errors ? "\n{$errors} problema(s) crítico(s).\n" : "\nInstalação pronta.\n";
exit($errors ? 1 : 0);
