<?php

declare(strict_types=1);

use App\Core\Database\Connection;

/** Critérios padrão de avaliação (idempotente). */
return static function (Connection $db): void {
    $criteria = [
        ['Passe', 'tecnico'], ['Domínio e recepção', 'tecnico'], ['Condução e drible', 'tecnico'],
        ['Finalização', 'tecnico'], ['Cabeceio', 'tecnico'],
        ['Posicionamento', 'tatico'], ['Marcação', 'tatico'], ['Visão de jogo', 'tatico'],
        ['Velocidade', 'fisico'], ['Resistência', 'fisico'], ['Coordenação', 'fisico'],
        ['Disciplina', 'comportamental'], ['Trabalho em equipe', 'comportamental'], ['Dedicação nos treinos', 'comportamental'],
    ];
    foreach ($criteria as $i => [$name, $category]) {
        $db->execute(
            'INSERT INTO evaluation_criteria (name, category, sort_order) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE category = VALUES(category)',
            [$name, $category, ($i + 1) * 10],
        );
    }
};
