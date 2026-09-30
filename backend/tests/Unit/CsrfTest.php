<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Config\Config;
use App\Core\Exceptions\CsrfException;
use App\Core\Exceptions\ForbiddenException;
use App\Core\Http\JsonResponse;
use App\Core\Security\CsrfTokenManager;
use App\Core\Session\ArraySession;
use App\Middleware\CsrfMiddleware;
use PHPUnit\Framework\TestCase;
use Tests\Fakes\RequestFactory;

final class CsrfTest extends TestCase
{
    private CsrfTokenManager $csrf;
    private CsrfMiddleware $mw;

    protected function setUp(): void
    {
        $this->csrf = new CsrfTokenManager(new ArraySession());
        $this->mw = new CsrfMiddleware($this->csrf, new Config(['app' => ['allowed_origins' => ['https://iba.com.br']]]));
    }

    private function next(): callable
    {
        return static fn () => JsonResponse::data('ok');
    }

    public function testTokenIsRandom64HexAndStable(): void
    {
        $token = $this->csrf->token();

        self::assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $token);
        self::assertSame($token, $this->csrf->token());
        self::assertNotSame($token, $this->csrf->regenerate());
    }

    public function testGetRequestsPassWithoutToken(): void
    {
        $res = $this->mw->process(RequestFactory::make('GET', '/api/x'), $this->next());

        self::assertSame(200, $res->status());
    }

    public function testWriteWithoutTokenIsRejected(): void
    {
        $this->csrf->token();
        $this->expectException(CsrfException::class);
        $this->mw->process(RequestFactory::make('POST', '/api/x', ['a' => 1], ['origin' => 'https://iba.com.br']), $this->next());
    }

    public function testWriteWithWrongTokenIsRejected(): void
    {
        $this->csrf->token();
        $this->expectException(CsrfException::class);
        $this->mw->process(
            RequestFactory::make('POST', '/api/x', ['a' => 1], ['x-csrf-token' => str_repeat('a', 64)]),
            $this->next(),
        );
    }

    public function testForeignOriginIsRejectedEvenWithValidToken(): void
    {
        $token = $this->csrf->token();
        $this->expectException(ForbiddenException::class);
        $this->mw->process(
            RequestFactory::make('POST', '/api/x', ['a' => 1], ['x-csrf-token' => $token, 'origin' => 'https://evil.example']),
            $this->next(),
        );
    }

    public function testForeignRefererIsRejected(): void
    {
        $token = $this->csrf->token();
        $this->expectException(ForbiddenException::class);
        $this->mw->process(
            RequestFactory::make('DELETE', '/api/x', [], ['x-csrf-token' => $token, 'referer' => 'https://iba.com.br.evil.example/page']),
            $this->next(),
        );
    }

    public function testValidTokenAndOriginPass(): void
    {
        $token = $this->csrf->token();
        $res = $this->mw->process(
            RequestFactory::make('PUT', '/api/x', ['a' => 1], ['x-csrf-token' => $token, 'origin' => 'https://iba.com.br']),
            $this->next(),
        );

        self::assertSame(200, $res->status());
    }
}
