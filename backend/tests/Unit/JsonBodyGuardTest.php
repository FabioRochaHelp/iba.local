<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Exceptions\BadRequestException;
use App\Core\Exceptions\UnsupportedMediaTypeException;
use App\Core\Http\JsonResponse;
use App\Core\Http\Request;
use App\Middleware\JsonBodyGuardMiddleware;
use PHPUnit\Framework\TestCase;

final class JsonBodyGuardTest extends TestCase
{
    private function request(string $method, string $body, string $type): Request
    {
        return new Request($method, '/api/x', [], ['content-type' => $type], $body, []);
    }

    public function testRejectsHtmlFormPost(): void
    {
        $this->expectException(UnsupportedMediaTypeException::class);
        (new JsonBodyGuardMiddleware())->process(
            $this->request('POST', 'email=a@b.c', 'application/x-www-form-urlencoded'),
            static fn () => JsonResponse::data('ok'),
        );
    }

    public function testRejectsMalformedJson(): void
    {
        $this->expectException(BadRequestException::class);
        (new JsonBodyGuardMiddleware())->process($this->request('POST', '{bad', 'application/json'), static fn () => JsonResponse::data('ok'));
    }

    public function testParsesValidJson(): void
    {
        $req = $this->request('POST', '{"name":"Ana"}', 'application/json; charset=utf-8');
        (new JsonBodyGuardMiddleware())->process($req, static fn () => JsonResponse::data('ok'));

        self::assertSame(['name' => 'Ana'], $req->body());
    }

    public function testBodylessPostIsAllowedWithoutContentType(): void
    {
        $res = (new JsonBodyGuardMiddleware())->process(
            new Request('POST', '/api/auth/logout', [], [], '', []),
            static fn () => JsonResponse::data('ok'),
        );

        self::assertSame(200, $res->status());
    }

    public function testFormBodyWithoutContentTypeIsRejected(): void
    {
        $this->expectException(UnsupportedMediaTypeException::class);
        (new JsonBodyGuardMiddleware())->process(
            new Request('POST', '/api/x', [], [], 'email=a', []),
            static fn () => JsonResponse::data('ok'),
        );
    }
}
