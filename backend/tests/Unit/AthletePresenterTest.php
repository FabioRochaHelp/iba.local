<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\Role;
use App\Models\User;
use App\Services\Athletes\AthletePresenter;
use PHPUnit\Framework\TestCase;

final class AthletePresenterTest extends TestCase
{
    private function athlete(): array
    {
        return [
            'id' => 1, 'name' => 'Ana', 'birth_date' => '2015-01-01', 'status' => 'ativo',
            'guardian' => ['id' => 2, 'name' => 'Bia', 'cpf' => '52998224725', 'email' => 'b@x.com', 'phones' => []],
            'plan' => ['id' => 1, 'name' => '2x', 'days_per_week' => 2, 'monthly_fee' => '110.00', 'discount_type' => 'nenhum', 'discount_value' => '0.00'],
        ];
    }

    public function testProfessorDoesNotReceiveFinancialOrPersonalData(): void
    {
        $out = (new AthletePresenter())->present($this->athlete(), new User(5, 'P', 'p@x', Role::Professor, true, false), true);

        self::assertSame(['id' => 1, 'name' => '2x', 'days_per_week' => 2], $out['plan']);
        self::assertArrayNotHasKey('cpf', $out['guardian']);
        self::assertArrayNotHasKey('email', $out['guardian']);
        self::assertStringNotContainsString('110', json_encode($out));
    }

    public function testAdminReceivesEverythingWithMaskedCpf(): void
    {
        $out = (new AthletePresenter())->present($this->athlete(), new User(1, 'A', 'a@x', Role::Admin, true, false), true);

        self::assertSame('110.00', $out['plan']['monthly_fee']);
        self::assertSame('***.982.247-**', $out['guardian']['cpf_masked']);
        self::assertNotNull($out['category']);
    }
}
