<?php

use App\Models\User;
use App\Support\DailySummarySubscriptionWarning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('reports no warning when at least one user is subscribed', function () {
    config()->set('contextual_console.daily_summary_to', null);

    User::factory()->create(['daily_summary_enabled' => true]);

    expect(DailySummarySubscriptionWarning::forUi())->toBeNull();
});

it('returns a fallback warning when no users are subscribed but fallback is configured', function () {
    config()->set('contextual_console.daily_summary_to', 'ops@example.test');

    User::factory()->create(['daily_summary_enabled' => false]);

    $warning = DailySummarySubscriptionWarning::forUi();

    expect($warning)->not->toBeNull()
        ->and($warning['severity'])->toBe(DailySummarySubscriptionWarning::SEVERITY_FALLBACK)
        ->and($warning['message'])->toContain('fallback recipient');
});

it('returns a critical warning when no users are subscribed and no fallback is configured', function () {
    config()->set('contextual_console.daily_summary_to', null);

    User::factory()->create(['daily_summary_enabled' => false]);

    $warning = DailySummarySubscriptionWarning::forUi();

    expect($warning)->not->toBeNull()
        ->and($warning['severity'])->toBe(DailySummarySubscriptionWarning::SEVERITY_NONE)
        ->and($warning['message'])->toContain('will not be sent');
});
