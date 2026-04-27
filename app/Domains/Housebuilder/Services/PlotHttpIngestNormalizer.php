<?php

namespace App\Domains\Housebuilder\Services;

use App\Core\Models\MonitoredSource;

class PlotHttpIngestNormalizer
{
    public const ADAPTER_CONTEXTUALWP_LIST_CONTEXTS = 'contextualwp_list_contexts';

    /**
     * @param  array<int, mixed>  $records
     * @return array<int, mixed>
     */
    public function normalize(MonitoredSource $source, array $records): array
    {
        $adapter = (string) ($source->http_plot_payload_adapter ?? '');
        if ($adapter !== self::ADAPTER_CONTEXTUALWP_LIST_CONTEXTS) {
            return $records;
        }

        $out = [];
        foreach ($records as $item) {
            $out[] = $this->normalizeContextualWpListContextsItem($item);
        }

        return $out;
    }

    private function normalizeContextualWpListContextsItem(mixed $item): mixed
    {
        if (! is_array($item)) {
            return $item;
        }

        $id = $item['id'] ?? $item['post_id'] ?? $item['ID'] ?? null;
        if ($id === null && isset($item['context_id'])) {
            $id = $item['context_id'];
        }

        if ($id !== null) {
            $item['id'] = is_string($id) ? trim($id) : $id;
        }

        $price = $item['price']
            ?? data_get($item, 'acf.price')
            ?? data_get($item, 'fields.price')
            ?? data_get($item, 'meta.price');

        if ($price !== null && (! array_key_exists('price', $item) || $this->isEffectivelyEmpty($item['price']))) {
            $item['price'] = $price;
        }

        $status = $this->resolveContextualWpStatusRaw($item);

        if ($status !== null && (! array_key_exists('status', $item) || $this->isEffectivelyEmpty($item['status']))) {
            $item['status'] = $this->normalisePlotStatusString($status);
        }

        return $item;
    }

    /**
     * Resolve a display/raw status string. Order matches typical ACF select shapes first.
     */
    private function resolveContextualWpStatusRaw(array $item): ?string
    {
        foreach ([
            'acf.status.value',
            'acf.status.label',
            'acf.plot_status.value',
            'acf.plot_status.label',
        ] as $path) {
            $s = $this->coerceStatusScalar(data_get($item, $path));
            if ($s !== null) {
                return $s;
            }
        }

        foreach ([
            $item['status'] ?? null,
            data_get($item, 'acf.status'),
            data_get($item, 'acf.plot_status'),
            data_get($item, 'fields.status'),
            data_get($item, 'plot_status'),
            data_get($item, 'availability'),
        ] as $raw) {
            $s = $this->coerceStatusScalar($raw);
            if ($s !== null) {
                return $s;
            }
        }

        return null;
    }

    /**
     * @return non-empty-string|null
     */
    private function coerceStatusScalar(mixed $raw): ?string
    {
        if ($raw === null) {
            return null;
        }

        if (is_string($raw)) {
            $t = trim($raw);

            return $t === '' ? null : $t;
        }

        if (is_int($raw) || is_float($raw)) {
            return (string) $raw;
        }

        if (is_array($raw)) {
            foreach (['value', 'label'] as $key) {
                if (! array_key_exists($key, $raw)) {
                    continue;
                }

                $inner = $this->coerceStatusScalar($raw[$key]);
                if ($inner !== null) {
                    return $inner;
                }
            }
        }

        return null;
    }

    /**
     * PlotDatasetIssueDetector lowercases before validating against available|reserved|sold.
     */
    private function normalisePlotStatusString(string $status): string
    {
        return strtolower(trim($status));
    }

    private function isEffectivelyEmpty(mixed $value): bool
    {
        if ($value === null) {
            return true;
        }

        return is_string($value) && trim($value) === '';
    }
}
