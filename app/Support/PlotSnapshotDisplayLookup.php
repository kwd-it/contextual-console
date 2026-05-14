<?php

namespace App\Support;

/**
 * Resolves human-readable plot labels from snapshot payloads for dashboard display only.
 */
final class PlotSnapshotDisplayLookup
{
    /** @param  array<string, array{plot_label: ?string, development: ?string}>  $byCanonicalId */
    private function __construct(
        private array $byCanonicalId,
    ) {}

    public static function empty(): self
    {
        return new self([]);
    }

    /**
     * @param  array<int, mixed>|null  $currentPayload
     * @param  array<int, mixed>|null  $previousPayload
     */
    public static function fromPayloads(?array $currentPayload, ?array $previousPayload): self
    {
        $map = [];

        foreach (self::plotRows($currentPayload) as $plot) {
            $key = self::canonicalPlotKey($plot['id'] ?? null);
            if ($key === null) {
                continue;
            }
            $map[$key] = self::extractDisplay($plot);
        }

        foreach (self::plotRows($previousPayload) as $plot) {
            $key = self::canonicalPlotKey($plot['id'] ?? null);
            if ($key === null || array_key_exists($key, $map)) {
                continue;
            }
            $map[$key] = self::extractDisplay($plot);
        }

        return new self($map);
    }

    /**
     * @return array{plot_label: ?string, development: ?string}|null
     */
    public function forPlotEntity(?string $entityType, mixed $entityId): ?array
    {
        if ($entityType !== 'plot' || $entityId === null) {
            return null;
        }

        $key = self::canonicalPlotKey($entityId);

        if ($key === null) {
            return null;
        }

        return $this->byCanonicalId[$key] ?? null;
    }

    /**
     * @param  array<int, mixed>|null  $payload
     * @return iterable<int, array<string, mixed>>
     */
    private static function plotRows(?array $payload): iterable
    {
        if ($payload === null) {
            return [];
        }

        foreach ($payload as $item) {
            if (is_array($item)) {
                yield $item;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $plot
     * @return array{plot_label: ?string, development: ?string}
     */
    private static function extractDisplay(array $plot): array
    {
        return [
            'plot_label' => self::nonEmptyString($plot['title'] ?? null)
                ?? self::nonEmptyString($plot['name'] ?? null),
            'development' => self::nonEmptyString($plot['development'] ?? null)
                ?? self::nonEmptyString($plot['development_name'] ?? null),
        ];
    }

    /**
     * Normalises a payload string for dashboard display (trim + HTML entity decode; snapshot storage unchanged).
     */
    private static function nonEmptyString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $trimmed = trim($value);

        if ($trimmed === '') {
            return null;
        }

        return html_entity_decode($trimmed, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private static function canonicalPlotKey(mixed $id): ?string
    {
        if ($id === null) {
            return null;
        }

        if (is_string($id)) {
            $trimmed = trim($id);

            return $trimmed === '' ? null : $trimmed;
        }

        return (string) $id;
    }
}
