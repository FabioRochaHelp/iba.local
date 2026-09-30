<?php

declare(strict_types=1);

namespace Tests\Integration;

use App\Core\Config\Env;
use App\Core\Database\Connection;
use PHPUnit\Framework\TestCase;
use Throwable;

/**
 * Testes contra o MySQL configurado no .env (pulados se indisponível).
 * Cada teste usa dados com prefixo próprio e limpa o que criou.
 */
abstract class DatabaseTestCase extends TestCase
{
    protected static ?Connection $db = null;

    protected function setUp(): void
    {
        if (self::$db === null) {
            Env::load(dirname(__DIR__, 2) . '/.env');
            $config = (require dirname(__DIR__, 2) . '/config/app.php')['database'];
            try {
                $conn = new Connection($config);
                $conn->pdo();
                self::$db = $conn;
            } catch (Throwable $e) {
                self::markTestSkipped('Banco indisponível: ' . $e->getMessage());
            }
        }
    }
}
