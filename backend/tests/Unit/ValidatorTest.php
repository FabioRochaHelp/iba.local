<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Exceptions\ValidationException;
use App\Core\Validation\Validator;
use PHPUnit\Framework\TestCase;

final class ValidatorTest extends TestCase
{
    private Validator $v;

    protected function setUp(): void
    {
        $this->v = new Validator();
    }

    public function testDiscardsUndeclaredFieldsPreventingMassAssignment(): void
    {
        $data = $this->v->validate(['name' => 'Ana', 'role' => 'admin', 'id' => 99], ['name' => 'required|string']);

        self::assertSame(['name' => 'Ana'], $data);
    }

    public function testStripsHtmlTagsFromStrings(): void
    {
        $data = $this->v->validate(
            ['name' => '<script>alert(1)</script>João <b>Silva</b>', 'bio' => "<img src=x onerror=alert(1)>linha1\nlinha2"],
            ['name' => 'required|string', 'bio' => 'nullable|text'],
        );

        self::assertSame('alert(1)João Silva', $data['name']);
        self::assertSame("linha1\nlinha2", $data['bio']);
        self::assertStringNotContainsString('<', $data['name'] . $data['bio']);
    }

    public function testRawKeepsPasswordUntouched(): void
    {
        $data = $this->v->validate(['password' => ' <a>Senha123 '], ['password' => 'required|raw']);

        self::assertSame(' <a>Senha123 ', $data['password']);
    }

    public function testRequiredAndTypeErrors(): void
    {
        try {
            $this->v->validate(
                ['age' => 'abc', 'email' => 'x', 'fee' => '10.555', 'status' => 'hacker', 'date' => '2024-02-30'],
                [
                    'name' => 'required|string',
                    'age' => 'int',
                    'email' => 'email',
                    'fee' => 'money',
                    'status' => 'in:ativo,inativo',
                    'date' => 'date',
                ],
            );
            self::fail('Esperava ValidationException');
        } catch (ValidationException $e) {
            self::assertSame(['name', 'age', 'email', 'fee', 'status', 'date'], array_keys($e->errors()));
        }
    }

    public function testMoneyNormalizesCommaAndDecimals(): void
    {
        $data = $this->v->validate(['fee' => '60,5'], ['fee' => 'money']);

        self::assertSame('60.50', $data['fee']);
    }

    public function testDefaultMaxLengthIsEnforced(): void
    {
        $this->expectException(ValidationException::class);
        $this->v->validate(['name' => str_repeat('a', 256)], ['name' => 'string']);
    }

    public function testSqlInjectionPayloadIsJustAString(): void
    {
        $data = $this->v->validate(['search' => "' OR 1=1 --"], ['search' => 'string']);

        self::assertSame("' OR 1=1 --", $data['search']);
    }

    public function testPasswordPolicy(): void
    {
        $this->expectException(ValidationException::class);
        $this->v->validate(['p' => 'curta1'], ['p' => 'raw|password']);
    }

    public function testIdsRule(): void
    {
        self::assertSame(['ids' => [1, 2]], $this->v->validate(['ids' => [1, '2', 2]], ['ids' => 'ids']));

        $this->expectException(ValidationException::class);
        $this->v->validate(['ids' => [1, '0 OR 1=1']], ['ids' => 'ids']);
    }

    public function testObjectRule(): void
    {
        self::assertSame(['g' => ['name' => 'A']], $this->v->validate(['g' => ['name' => 'A']], ['g' => 'object']));

        $this->expectException(ValidationException::class);
        $this->v->validate(['g' => ['a', 'b']], ['g' => 'object']);
    }
}
