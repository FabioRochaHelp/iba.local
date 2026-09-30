<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

interface RateLimitRepositoryInterface
{
    /**
     * Incrementa o contador da chave na janela atual e retorna
     * [total de hits na janela, segundos até a janela reiniciar].
     *
     * @return array{0: int, 1: int}
     */
    public function hit(string $key, int $windowSeconds): array;
}
