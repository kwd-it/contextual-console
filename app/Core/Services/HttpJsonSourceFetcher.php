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

        $paginationMode = trim((string) ($source->http_pagination_mode ?? ''));
        if ($paginationMode === '') {
            return $this->fetchAndExtractItems($source, $endpointUrl, $headers);
        }

        if ($paginationMode !== 'page_per_page') {
            throw new RuntimeException("Unsupported http_pagination_mode '{$paginationMode}' for monitored source '{$source->key}'.");
        }

        return $this->fetchAllPagesByPagePerPage($source, $endpointUrl, $headers);
    }

    /**
     * @param  array<string, string>  $headers
     * @return array<int, mixed>
     */
    private function fetchAllPagesByPagePerPage(MonitoredSource $source, string $endpointUrl, array $headers): array
    {
        $pageParam = trim((string) ($source->http_page_param ?? '')) ?: 'page';
        $perPageParam = trim((string) ($source->http_per_page_param ?? '')) ?: 'per_page';

        $perPage = (int) ($source->http_per_page ?? 100);
        if ($perPage <= 0) {
            $perPage = 100;
        }

        $maxPages = (int) ($source->http_max_pages ?? 20);
        if ($maxPages <= 0) {
            $maxPages = 20;
        }

        $all = [];

        for ($page = 1; $page <= $maxPages; $page++) {
            $url = $this->withMergedQueryParams($endpointUrl, [
                $pageParam => $page,
                $perPageParam => $perPage,
            ]);

            $items = $this->fetchAndExtractItems($source, $url, $headers);

            if (count($items) === 0) {
                break;
            }

            array_push($all, ...$items);

            if (count($items) < $perPage) {
                break;
            }
        }

        return $all;
    }

    /**
     * @param  array<string, string>  $headers
     * @return array<int, mixed>
     */
    private function fetchAndExtractItems(MonitoredSource $source, string $endpointUrl, array $headers): array
    {
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
     * Merge query params into a URL without losing existing query.
     *
     * @param  array<string, scalar|null>  $query
     */
    private function withMergedQueryParams(string $url, array $query): string
    {
        $parts = parse_url($url);
        if ($parts === false) {
            throw new RuntimeException("Invalid endpoint URL: {$url}");
        }

        $existing = [];
        if (isset($parts['query']) && is_string($parts['query']) && $parts['query'] !== '') {
            parse_str($parts['query'], $existing);
        }

        foreach ($query as $k => $v) {
            $existing[$k] = $v;
        }

        $scheme = $parts['scheme'] ?? null;
        $host = $parts['host'] ?? null;

        if (! is_string($scheme) || ! is_string($host) || $scheme === '' || $host === '') {
            throw new RuntimeException("Invalid endpoint URL: {$url}");
        }

        $rebuilt = $scheme.'://'.$host;

        if (isset($parts['port'])) {
            $rebuilt .= ':'.$parts['port'];
        }

        if (isset($parts['user']) && is_string($parts['user'])) {
            $rebuilt = $scheme.'://'.$parts['user'].(isset($parts['pass']) ? ':'.$parts['pass'] : '').'@'.$host;
            if (isset($parts['port'])) {
                $rebuilt .= ':'.$parts['port'];
            }
        }

        $rebuilt .= $parts['path'] ?? '';

        $qs = http_build_query($existing);
        if ($qs !== '') {
            $rebuilt .= '?'.$qs;
        }

        if (isset($parts['fragment']) && is_string($parts['fragment']) && $parts['fragment'] !== '') {
            $rebuilt .= '#'.$parts['fragment'];
        }

        return $rebuilt;
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
