<?php

namespace App\Core\Services;

use App\Core\Models\MonitoredSource;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\RequestException;
use RuntimeException;

class HttpJsonSourceFetcher
{
    public function __construct(
        private HttpFactory $http,
    ) {}

    /**
     * @return array<int, mixed>
     */
    public function fetch(MonitoredSource $source): array
    {
        $endpointUrl = (string) ($source->endpoint_url ?? '');
        if (trim($endpointUrl) === '') {
            throw new RuntimeException("Monitored source '{$source->key}' is missing endpoint_url.");
        }

        $headers = [];

        $headerName = (string) ($source->auth_header_name ?? '');
        $tokenEnvKey = (string) ($source->auth_token_env_key ?? '');

        if ($headerName !== '' && $tokenEnvKey !== '') {
            $headers[$headerName] = $this->resolveAuthToken($tokenEnvKey);
        }

        try {
            $response = $this->http
                ->timeout(10)
                ->withHeaders($headers)
                ->get($endpointUrl)
                ->throw();
        } catch (RequestException $e) {
            $status = $e->response?->status();
            $message = $status === null
                ? "HTTP request failed for {$endpointUrl}."
                : "HTTP request failed for {$endpointUrl} with status {$status}.";

            throw new RuntimeException($message, previous: $e);
        }

        $body = $response->body();

        $decodedForType = json_decode($body);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Invalid JSON response: '.json_last_error_msg());
        }

        if (! is_array($decodedForType)) {
            throw new RuntimeException('Invalid JSON response: expected a JSON array at the top level.');
        }

        /** @var mixed $decoded */
        $decoded = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE || ! is_array($decoded)) {
            throw new RuntimeException('Invalid JSON response: expected a JSON array at the top level.');
        }

        /** @var array<int, mixed> $decoded */
        return $decoded;
    }

    private function resolveAuthToken(string $tokenEnvKey): string
    {
        $token = env($tokenEnvKey);
        if (! is_string($token) || trim($token) === '') {
            throw new RuntimeException("Missing required auth token env value for key '{$tokenEnvKey}'.");
        }

        return $token;
    }
}
