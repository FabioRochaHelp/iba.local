<?php

declare(strict_types=1);

use App\Core\Config\Env;
use App\Core\Database\Connection;
use App\Services\Security\NativePasswordHasher;

/**
 * Dados iniciais (idempotente): posições, planos da planilha,
 * itens de uniforme e o usuário administrador.
 */
return static function (Connection $db): void {
    $positions = [
        ['Goleiro', 'GOL'], ['Zagueiro', 'ZAG'], ['Lateral Direito', 'LD'], ['Lateral Esquerdo', 'LE'],
        ['Volante', 'VOL'], ['Meia', 'MEI'], ['Meia Direita', 'MD'], ['Meia Esquerda', 'ME'], ['Atacante', 'ATA'],
    ];
    foreach ($positions as $i => [$name, $short]) {
        $db->execute(
            'INSERT INTO positions (name, short_name, sort_order) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE short_name = VALUES(short_name), sort_order = VALUES(sort_order)',
            [$name, $short, $i + 1],
        );
    }

    $plans = [
        ['1x por semana', '1 dia por semana - segunda ou quarta', 1, '60.00'],
        ['2x por semana', '2 dias por semana - segunda e quarta', 2, '110.00'],
    ];
    foreach ($plans as [$name, $description, $days, $fee]) {
        $db->execute(
            'INSERT IGNORE INTO plans (name, description, days_per_week, monthly_fee) VALUES (?, ?, ?, ?)',
            [$name, $description, $days, $fee],
        );
    }

    foreach ([['Kit completo', '120.00'], ['Camisa', '60.00'], ['Short', '40.00'], ['Meião', '25.00']] as [$name, $price]) {
        $db->execute('INSERT IGNORE INTO uniform_items (name, price) VALUES (?, ?)', [$name, $price]);
    }

    $email = mb_strtolower((string) Env::get('ADMIN_EMAIL', 'admin@irmaosdabola.com.br'));
    if ($db->fetchValue('SELECT 1 FROM users WHERE email = ?', [$email]) === null) {
        $password = (string) Env::get('ADMIN_PASSWORD', '');
        $generated = $password === '';
        if ($generated) {
            $password = rtrim(strtr(base64_encode(random_bytes(12)), '+/', 'Ab'), '=') . '9';
        }

        $db->insert('users', [
            'name' => (string) Env::get('ADMIN_NAME', 'Administrador'),
            'email' => $email,
            'password_hash' => (new NativePasswordHasher())->hash($password),
            'role' => 'admin',
            'active' => 1,
            'must_change_password' => 1,
        ]);

        echo "\n  Admin criado: {$email}" . ($generated ? "\n  Senha temporária: {$password}" : '') .
            "\n  (troca de senha obrigatória no primeiro acesso)\n";
    }
};
