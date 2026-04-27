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
            $headers[$headerName] = $this->resolveAuthHeaderValue($tokenEnvKey);
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

        /** @var mixed $decoded */
        $decoded = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Invalid JSON response: '.json_last_error_msg());
        }

        if (! is_array($decoded)) {
            throw new RuntimeException('Invalid JSON response: expected a JSON object or array.');
        }

        /** @var array<int|string, mixed> $decoded */
        return $this->extractPlotListItems($source, $decoded);
    }

    /**
     * @param  array<int|string, mixed>  $decoded
     * @return array<int, mixed>
     */
    private function extractPlotListItems(MonitoredSource $source, array $decoded): array
    {
        $itemsKey = trim((string) ($source->http_json_items_key ?? ''));
        if ($itemsKey === '' && (string) ($source->http_plot_payload_adapter ?? '') === 'contextualwp_list_contexts') {
            $itemsKey = 'contexts';
        }

        if ($itemsKey === '') {
            if (! array_is_list($decoded)) {
                throw new RuntimeException(
                    'Invalid JSON response: expected a JSON array at the top level. '.
                    'For wrapped list responses (for example ContextualWP list_contexts), set http_json_items_key on the monitored source, '.
                    'or set http_plot_payload_adapter to contextualwp_list_contexts to read the default contexts array.',
                );
            }

            /** @var array<int, mixed> */
            return $decoded;
        }

        if (array_is_list($decoded)) {
            throw new RuntimeException(
                "Invalid JSON response: expected a JSON object at the top level when http_json_items_key is '{$itemsKey}'.",
            );
        }

        if (! array_key_exists($itemsKey, $decoded)) {
            throw new RuntimeException("Invalid JSON response: missing key '{$itemsKey}'.");
        }

        $items = $decoded[$itemsKey];
        if (! is_array($items) || ! array_is_list($items)) {
            throw new RuntimeException("Invalid JSON response: value at '{$itemsKey}' must be a JSON array.");
        }

        /** @var array<int, mixed> $items */
        return $items;
    }

    private function resolveAuthHeaderValue(string $tokenEnvKey): string
    {
        $value = env($tokenEnvKey);
        if (! is_string($value) || trim($value) === '') {
            throw new RuntimeException(
                "Missing required auth header env value for key '{$tokenEnvKey}'. ".
                'Set that variable in your environment (for example .env); the full header value is read from env, not from the database.',
            );
        }

        return $value;
    }
}
