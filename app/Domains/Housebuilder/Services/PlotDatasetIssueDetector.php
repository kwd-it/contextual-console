<?php

namespace App\Domains\Housebuilder\Services;

class PlotDatasetIssueDetector
{
    public const ENTITY_TYPE = 'plot';

    public const SEVERITY_ERROR = 'error';
    public const SEVERITY_WARNING = 'warning';

    public const ISSUE_TYPE_MISSING_REQUIRED_FIELD = 'missing_required_field';
    public const ISSUE_TYPE_DUPLICATE_VALUE = 'duplicate_value';
    public const ISSUE_TYPE_INVALID_VALUE = 'invalid_value';
    public const ISSUE_TYPE_INVALID_RECORD = 'invalid_record';

    /** @var array<int, string> */
    private const ALLOWED_STATUSES = ['available', 'coming_soon', 'reserved', 'sold'];

    private const COMPLETENESS_STATUS_MISSING = 'missing';

    private const FIELD_FLOOR_PLAN_COMPLETENESS_STATUS = 'floor_plan_completeness_status';
    private const FIELD_FLOOR_PLAN_REQUIRED = 'floor_plan_required';
    private const FIELD_INTRO_MEDIA_COMPLETENESS_STATUS = 'intro_media_completeness_status';

    /**
     * @param  array<int, mixed>  $payload
     * @return array<int, array{
     *   entity_type: string|null,
     *   entity_id: string|null,
     *   field: string|null,
     *   issue_type: string,
     *   severity: string,
     *   message: string,
     *   context?: array<string, mixed>|null
     * }>
     */
    public function detect(array $payload): array
    {
        $issues = [];

        /** @var array<string, int> $firstIndexById */
        $firstIndexById = [];

        foreach ($payload as $index => $plot) {
            if (! is_array($plot)) {
                $issues[] = $this->issue(
                    entityId: null,
                    field: null,
                    issueType: self::ISSUE_TYPE_INVALID_RECORD,
                    severity: self::SEVERITY_ERROR,
                    message: 'Plot payload item must be an object/array.',
                    context: ['index' => $index, 'received_type' => gettype($plot)],
                );

                continue;
            }

            $id = $this->canonicalId($plot['id'] ?? null);

            if ($this->isMissingRequired($plot, 'id')) {
                $issues[] = $this->issue(
                    entityId: null,
                    field: 'id',
                    issueType: self::ISSUE_TYPE_MISSING_REQUIRED_FIELD,
                    severity: self::SEVERITY_ERROR,
                    message: 'Plot is missing a required id field.',
                    context: ['index' => $index],
                );
            } elseif ($id !== null) {
                if (array_key_exists($id, $firstIndexById)) {
                    $issues[] = $this->issue(
                        entityId: $id,
                        field: 'id',
                        issueType: self::ISSUE_TYPE_DUPLICATE_VALUE,
                        severity: self::SEVERITY_ERROR,
                        message: 'Plot id is duplicated.',
                        context: ['index' => $index, 'original_index' => $firstIndexById[$id]],
                    );
                } else {
                    $firstIndexById[$id] = $index;
                }
            }

            if ($this->shouldRequirePrice($plot) && $this->isMissingRequired($plot, 'price')) {
                $issues[] = $this->issue(
                    entityId: $id,
                    field: 'price',
                    issueType: self::ISSUE_TYPE_MISSING_REQUIRED_FIELD,
                    severity: self::SEVERITY_WARNING,
                    message: 'Plot is missing a price.',
                    context: ['index' => $index],
                );
            } elseif (array_key_exists('price', $plot) && ! $this->isMissingRequired($plot, 'price')) {
                $price = $plot['price'];
                if (! is_numeric($price) || (float) $price < 0) {
                    $issues[] = $this->issue(
                        entityId: $id,
                        field: 'price',
                        issueType: self::ISSUE_TYPE_INVALID_VALUE,
                        severity: self::SEVERITY_WARNING,
                        message: 'Plot price must be numeric and greater than or equal to zero.',
                        context: ['index' => $index, 'received' => $price],
                    );
                }
            }

            if ($this->isMissingRequired($plot, 'status')) {
                $issues[] = $this->issue(
                    entityId: $id,
                    field: 'status',
                    issueType: self::ISSUE_TYPE_MISSING_REQUIRED_FIELD,
                    severity: self::SEVERITY_WARNING,
                    message: 'Plot is missing a status.',
                    context: ['index' => $index],
                );
            } elseif (array_key_exists('status', $plot)) {
                $statusRaw = $plot['status'];
                $status = is_string($statusRaw) ? strtolower(trim($statusRaw)) : null;

                if ($status === null || $status === '' || ! in_array($status, self::ALLOWED_STATUSES, true)) {
                    $issues[] = $this->issue(
                        entityId: $id,
                        field: 'status',
                        issueType: self::ISSUE_TYPE_INVALID_VALUE,
                        severity: self::SEVERITY_WARNING,
                        message: 'Plot status is invalid.',
                        context: [
                            'index' => $index,
                            'received' => $statusRaw,
                            'allowed' => self::ALLOWED_STATUSES,
                        ],
                    );
                }
            }

            $this->detectAssetCompletenessIssues($issues, $plot, $id, $index);
        }

        return $issues;
    }

    /**
     * @param  array<int, array<string, mixed>>  $issues
     * @param  array<string, mixed>  $plot
     */
    private function detectAssetCompletenessIssues(array &$issues, array $plot, ?string $id, int $index): void
    {
        if ($this->shouldWarnMissingFloorPlan($plot)) {
            $issues[] = $this->issue(
                entityId: $id,
                field: self::FIELD_FLOOR_PLAN_COMPLETENESS_STATUS,
                issueType: self::ISSUE_TYPE_MISSING_REQUIRED_FIELD,
                severity: self::SEVERITY_WARNING,
                message: 'Required floor plan is missing.',
                context: [
                    'index' => $index,
                    self::FIELD_FLOOR_PLAN_REQUIRED => $plot[self::FIELD_FLOOR_PLAN_REQUIRED] ?? null,
                    self::FIELD_FLOOR_PLAN_COMPLETENESS_STATUS => $plot[self::FIELD_FLOOR_PLAN_COMPLETENESS_STATUS] ?? null,
                ],
            );
        }

        if ($this->shouldWarnMissingIntroMedia($plot)) {
            $issues[] = $this->issue(
                entityId: $id,
                field: self::FIELD_INTRO_MEDIA_COMPLETENESS_STATUS,
                issueType: self::ISSUE_TYPE_MISSING_REQUIRED_FIELD,
                severity: self::SEVERITY_WARNING,
                message: 'Linked development intro media is missing.',
                context: [
                    'index' => $index,
                    self::FIELD_INTRO_MEDIA_COMPLETENESS_STATUS => $plot[self::FIELD_INTRO_MEDIA_COMPLETENESS_STATUS] ?? null,
                ],
            );
        }
    }

    /**
     * @param  array<string, mixed>  $plot
     */
    private function shouldWarnMissingFloorPlan(array $plot): bool
    {
        if (($plot[self::FIELD_FLOOR_PLAN_REQUIRED] ?? null) !== true) {
            return false;
        }

        return $this->completenessStatus($plot, self::FIELD_FLOOR_PLAN_COMPLETENESS_STATUS) === self::COMPLETENESS_STATUS_MISSING;
    }

    /**
     * @param  array<string, mixed>  $plot
     */
    private function shouldWarnMissingIntroMedia(array $plot): bool
    {
        return $this->completenessStatus($plot, self::FIELD_INTRO_MEDIA_COMPLETENESS_STATUS) === self::COMPLETENESS_STATUS_MISSING;
    }

    /**
     * @param  array<string, mixed>  $plot
     */
    private function completenessStatus(array $plot, string $field): ?string
    {
        if (! array_key_exists($field, $plot)) {
            return null;
        }

        $value = $plot[$field];
        if (! is_string($value)) {
            return null;
        }

        $normalized = strtolower(trim($value));

        return $normalized === '' ? null : $normalized;
    }

    /**
     * Price is required only for plots whose status is present, valid, and `available`.
     *
     * @param  array<string, mixed>  $plot
     */
    private function shouldRequirePrice(array $plot): bool
    {
        if ($this->isMissingRequired($plot, 'status')) {
            return false;
        }

        $statusRaw = $plot['status'];
        $status = is_string($statusRaw) ? strtolower(trim($statusRaw)) : null;

        if ($status === null || $status === '' || ! in_array($status, self::ALLOWED_STATUSES, true)) {
            return false;
        }

        return $status === 'available';
    }

    /**
     * @param  array<string, mixed>  $plot
     */
    private function isMissingRequired(array $plot, string $field): bool
    {
        if (! array_key_exists($field, $plot)) {
            return true;
        }

        $value = $plot[$field];

        if ($value === null) {
            return true;
        }

        if (is_string($value) && trim($value) === '') {
            return true;
        }

        return false;
    }

    private function canonicalId(mixed $id): ?string
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

    /**
     * @param  array<string, mixed>|null  $context
     * @return array{
     *   entity_type: string,
     *   entity_id: string|null,
     *   field: string|null,
     *   issue_type: string,
     *   severity: string,
     *   message: string,
     *   context?: array<string, mixed>|null
     * }
     */
    private function issue(
        ?string $entityId,
        ?string $field,
        string $issueType,
        string $severity,
        string $message,
        ?array $context = null,
    ): array {
        $issue = [
            'entity_type' => self::ENTITY_TYPE,
            'entity_id' => $entityId,
            'field' => $field,
            'issue_type' => $issueType,
            'severity' => $severity,
            'message' => $message,
        ];

        if ($context !== null) {
            $issue['context'] = $context;
        }

        return $issue;
    }
}
