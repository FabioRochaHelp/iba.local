<?php

declare(strict_types=1);

namespace Tests\Fakes;

use App\Core\Http\Request;

final class RequestFactory
{
    /**
     * @param array<string, mixed>  $body
     * @param array<string, string> $headers
     */
    public static function make(string $method, string $path, array $body = [], array $headers = [], array $query = []): Request
    {
        $headers = array_change_key_case($headers + ['user-agent' => 'PHPUnit'], CASE_LOWER);
        $raw = $body === [] ? '' : json_encode($body, JSON_THROW_ON_ERROR);
        if ($raw !== '' && !isset($headers['content-type'])) {
            $headers['content-type'] = 'application/json';
        }
        $request = new Request($method, $path, $query, $headers, $raw, ['REMOTE_ADDR' => '10.0.0.1']);
        if ($body !== []) {
            $request->setParsedBody($body);
        }

        return $request;
    }
}
