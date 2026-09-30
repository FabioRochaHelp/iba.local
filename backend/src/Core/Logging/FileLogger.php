<?php

declare(strict_types=1);

namespace App\Core\Logging;

/**
 * Logger em arquivo diário (storage/logs, fora do web root).
 * Chaves sensíveis são mascaradas antes de gravar.
 */
final class FileLogger implements LoggerInterface
{
    private const SENSITIVE = ['password', 'senha', 'token', 'csrf', 'cpf', 'secret', 'authorization', 'cookie', 'current_password', 'new_password'];

    public function __construct(private string $directory)
    {
    }

    public function error(string $message, array $context = []): void
    {
        $this->write('ERROR', $message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->write('WARNING', $message, $context);
    }

    public function info(string $message, array $context = []): void
    {
        $this->write('INFO', $message, $context);
    }

    /** @param array<string, mixed> $context */
    private function write(string $level, string $message, array $context): void
    {
        if (!is_dir($this->directory)) {
            @mkdir($this->directory, 0750, true);
        }

        $line = sprintf(
            "[%s] %s: %s %s\n",
            date('Y-m-d H:i:s'),
            $level,
            str_replace(["\r", "\n"], ' ', $message),
            $context === [] ? '' : json_encode($this->redact($context), JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR),
        );

        @file_put_contents($this->directory . '/app-' . date('Y-m-d') . '.log', $line, FILE_APPEND | LOCK_EX);
    }

    /**
     * @param array<mixed> $data
     * @return array<mixed>
     */
    private function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key)) {
                foreach (self::SENSITIVE as $needle) {
                    if (str_contains(strtolower($key), $needle)) {
                        $data[$key] = '[redacted]';
                        continue 2;
                    }
                }
            }
            if (is_array($value)) {
                $data[$key] = $this->redact($value);
            }
        }

        return $data;
    }
}
