<?php

declare(strict_types=1);

/**
 * Gera uma nova senha temporária para um usuário (troca obrigatória no
 * próximo acesso) e encerra as sessões abertas dele.
 *
 *   php scripts/reset_password.php email@dominio
 */

use App\Core\Database\Connection;
use App\Core\Session\SessionRevoker;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Services\Audit\AuditLogger;
use App\Services\Security\PasswordHasherInterface;
use App\Services\Users\UserService;

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$email = $argv[1] ?? '';
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Uso: php scripts/reset_password.php email@dominio\n");
    exit(1);
}

$c = require dirname(__DIR__) . '/bootstrap/app.php';
$users = $c->get(UserRepositoryInterface::class);
$user = $users->findByEmail($email);
if ($user === null) {
    fwrite(STDERR, "Usuário não encontrado: {$email}\n");
    exit(1);
}

$password = UserService::temporaryPassword();
$users->updatePassword($user->id, $c->get(PasswordHasherInterface::class)->hash($password), true);
$c->get(SessionRevoker::class)->revokeUser($user->id);
$c->get(Connection::class)->execute('DELETE FROM login_attempts WHERE email = ?', [$user->email]);
$c->get(AuditLogger::class)->log(null, 'user_password_reset', 'user', $user->id, ['via' => 'cli']);

echo "Login: {$user->email}\nSenha temporária: {$password}\n(troca obrigatória no próximo acesso; sessões abertas encerradas)\n";
