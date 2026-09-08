<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    /**
     * Any HTTP request a test does not explicitly fake raises a
     * StrayRequestException instead of reaching the real internet. Without
     * this, an unfaked call is invisible: the test still passes, but it has
     * quietly hit a live third-party API — which is slow, makes the suite
     * fail whenever the network hiccups, and spends real quota (the Google
     * geocoding calls fired by ClientObserver used to do exactly that,
     * across 137 test files, with a production API key).
     *
     * This is a guard, not a stub: it registers no response, so a test's
     * own Http::fake() is unaffected.
     */
    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
