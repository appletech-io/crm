<?php

use App\Services\Reporting\KpiStatus;

test('green when actual meets or exceeds target', function () {
    expect(KpiStatus::for(100, 100))->toBe('success')
        ->and(KpiStatus::for(150, 100))->toBe('success');
});

test('amber right at the 80% cutoff, and anywhere above it up to the target', function () {
    expect(KpiStatus::for(80, 100))->toBe('warning')
        ->and(KpiStatus::for(99, 100))->toBe('warning');
});

test('red just under the 80% cutoff', function () {
    expect(KpiStatus::for(79.9, 100))->toBe('danger')
        ->and(KpiStatus::for(0, 100))->toBe('danger');
});

test('null when no target has been set', function () {
    expect(KpiStatus::for(100, null))->toBeNull()
        ->and(KpiStatus::for(100, 0))->toBeNull();
});

test('null when there is no actual figure to compare, even with a target set', function () {
    expect(KpiStatus::for(null, 100))->toBeNull();
});
