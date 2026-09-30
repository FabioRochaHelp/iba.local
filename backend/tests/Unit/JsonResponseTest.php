<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Http\JsonResponse;
use PHPUnit\Framework\TestCase;

final class JsonResponseTest extends TestCase
{
    public function testHtmlCharactersAreHexEscaped(): void
    {
        $res = JsonResponse::data(['name' => '<script>alert("x")</script>&\'']);

        self::assertStringNotContainsString('<script>', $res->body());
        self::assertStringContainsString('\\u003Cscript\\u003E', $res->body());
        self::assertSame('application/json; charset=utf-8', $res->header('Content-Type'));
    }
}
