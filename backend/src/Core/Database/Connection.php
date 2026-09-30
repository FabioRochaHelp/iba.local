<?php

declare(strict_types=1);

namespace App\Core\Database;

use PDO;
use PDOStatement;
use Throwable;

/**
 * Wrapper de PDO que só executa SQL por prepared statements com
 * parâmetros vinculados (prevenção de SQL Injection).
 *
 * Regra do projeto: NUNCA concatenar valores vindos do usuário no SQL.
 * Identificadores dinâmicos (colunas de ordenação) passam por whitelist
 * nos repositórios — ver Repositories\Pdo\Concerns\Sortable.
 */
final class Connection
{
    private ?PDO $pdo = null;

    private int $transactionLevel = 0;

    /**
     * @param array{host: string, port: int|string, database: string, username: string, password: string, charset?: string} $config
     */
    public function __construct(private array $config)
    {
    }

    public static function fromPdo(PDO $pdo): self
    {
        $conn = new self(['host' => '', 'port' => 0, 'database' => '', 'username' => '', 'password' => '']);
        $conn->pdo = $pdo;

        return $conn;
    }

    public function pdo(): PDO
    {
        if ($this->pdo === null) {
            $charset = $this->config['charset'] ?? 'utf8mb4';
            $dsn = sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=%s',
                $this->config['host'],
                $this->config['port'],
                $this->config['database'],
                $charset,
            );

            $this->pdo = new PDO($dsn, $this->config['username'], $this->config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                // Prepared statements reais no servidor (não emulados).
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
                PDO::MYSQL_ATTR_MULTI_STATEMENTS => false,
            ]);
            $this->pdo->exec("SET time_zone = '" . (new \DateTime())->format('P') . "'");
            $this->pdo->exec("SET SESSION sql_mode = 'STRICT_ALL_TABLES,NO_ZERO_DATE,NO_ZERO_IN_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION'");
        }

        return $this->pdo;
    }

    /** @param array<string|int, mixed> $params */
    public function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = $this->pdo()->prepare($sql);
        foreach ($params as $key => $value) {
            $name = is_int($key) ? $key + 1 : (str_starts_with($key, ':') ? $key : ':' . $key);
            $stmt->bindValue($name, $value, match (true) {
                is_int($value) => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                $value === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            });
        }
        $stmt->execute();

        return $stmt;
    }

    /**
     * @param array<string|int, mixed> $params
     * @return list<array<string, mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->run($sql, $params)->fetchAll();
    }

    /**
     * @param array<string|int, mixed> $params
     * @return array<string, mixed>|null
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $row = $this->run($sql, $params)->fetch();

        return $row === false ? null : $row;
    }

    /** @param array<string|int, mixed> $params */
    public function fetchValue(string $sql, array $params = []): mixed
    {
        $value = $this->run($sql, $params)->fetchColumn();

        return $value === false ? null : $value;
    }

    /** @param array<string|int, mixed> $params */
    public function execute(string $sql, array $params = []): int
    {
        return $this->run($sql, $params)->rowCount();
    }

    /**
     * Insere uma linha. As chaves de $data são nomes de coluna definidos
     * pelo código (nunca pelo usuário) e são validadas por regex.
     *
     * @param array<string, mixed> $data
     */
    public function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $this->assertIdentifiers([$table, ...$columns]);

        $sql = sprintf(
            'INSERT INTO `%s` (%s) VALUES (%s)',
            $table,
            implode(', ', array_map(static fn ($c) => "`{$c}`", $columns)),
            implode(', ', array_map(static fn ($c) => ':' . $c, $columns)),
        );
        $this->run($sql, $data);

        return (int) $this->pdo()->lastInsertId();
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed> $where  coluna => valor (igualdade, AND)
     */
    public function update(string $table, array $data, array $where): int
    {
        if ($data === [] || $where === []) {
            return 0;
        }
        $this->assertIdentifiers([$table, ...array_keys($data), ...array_keys($where)]);

        $params = [];
        $set = [];
        foreach ($data as $column => $value) {
            $set[] = "`{$column}` = :s_{$column}";
            $params['s_' . $column] = $value;
        }
        $conditions = [];
        foreach ($where as $column => $value) {
            $conditions[] = "`{$column}` = :w_{$column}";
            $params['w_' . $column] = $value;
        }

        return $this->execute(
            sprintf('UPDATE `%s` SET %s WHERE %s', $table, implode(', ', $set), implode(' AND ', $conditions)),
            $params,
        );
    }

    /**
     * Executa o callback dentro de uma transação. Chamadas aninhadas usam
     * SAVEPOINT: uma falha interna desfaz só a parte interna e a exceção
     * segue para quem chamou decidir.
     *
     * @template T
     * @param callable(self): T $callback
     * @return T
     */
    public function transaction(callable $callback): mixed
    {
        $pdo = $this->pdo();
        $level = $this->transactionLevel;

        if ($level === 0) {
            $pdo->beginTransaction();
        } else {
            $pdo->exec('SAVEPOINT sp_' . $level);
        }
        $this->transactionLevel++;

        try {
            $result = $callback($this);
            $this->transactionLevel--;
            if ($level === 0) {
                $pdo->commit();
            } else {
                $pdo->exec('RELEASE SAVEPOINT sp_' . $level);
            }

            return $result;
        } catch (Throwable $e) {
            $this->transactionLevel = $level;
            if ($level === 0) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            } elseif ($pdo->inTransaction()) {
                $pdo->exec('ROLLBACK TO SAVEPOINT sp_' . $level);
            }
            throw $e;
        }
    }

    /** @param list<string> $identifiers */
    private function assertIdentifiers(array $identifiers): void
    {
        foreach ($identifiers as $identifier) {
            if (!preg_match('/^[a-zA-Z_][a-zA-Z0-9_]{0,63}$/', $identifier)) {
                throw new \InvalidArgumentException('Identificador SQL inválido.');
            }
        }
    }
}
