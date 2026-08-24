<?php

namespace App\Services\Payroll\Evertime;

use App\Enums\Integration;
use App\Models\Company;
use App\Services\Payroll\Exceptions\PayrollProviderException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Thin HTTP transport for Evertime's REST API — resolves this company's own
 * connection details and turns a 200-with-HasErrors response (Evertime's
 * way of reporting a rejected payload without an HTTP error status) into a
 * PayrollProviderException, so every Evertime\Requests\* class can treat an
 * HTTP 4xx/5xx and a 200 HasErrors the same way without repeating that
 * check itself.
 */
class EvertimeClient
{
    public function __construct(private readonly Company $company) {}

    /** @param  array<string, mixed>  $payload */
    public function post(string $path, array $payload): Response
    {
        return $this->send('post', $path, $payload);
    }

    /** @param  array<string, mixed>  $payload */
    public function put(string $path, array $payload): Response
    {
        return $this->send('put', $path, $payload);
    }

    /**
     * Deliberately doesn't throw() or check HasErrors like post()/put() —
     * callers of a GET (currently only looking up a consultant to verify a
     * manually-entered ID) treat "not found" as a normal, expected outcome
     * rather than an exceptional one.
     *
     * @param  array<string, mixed>  $query
     */
    public function get(string $path, array $query = []): Response
    {
        return $this->request()->get($path, $query);
    }

    /** @param  array<string, mixed>  $payload */
    private function send(string $method, string $path, array $payload): Response
    {
        $response = $this->request()->{$method}($path, $payload)->throw();

        if ($response->json('HasErrors')) {
            $errors = collect($response->json('Errors', []))
                ->pluck('ErrorMessage')
                ->filter()
                ->values()
                ->all();

            throw new PayrollProviderException(
                "Evertime API error on {$path}: ".implode('; ', $errors),
                $errors,
            );
        }

        return $response;
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl($this->apiUrl())
            ->withHeaders([
                'key' => $this->apiKey(),
                'Content-Type' => 'application/json',
            ]);
    }

    private function apiUrl(): string
    {
        return rtrim((string) $this->company->integrationSetting(Integration::Evertime, 'api_url'), '/');
    }

    private function apiKey(): ?string
    {
        return $this->company->integrationSetting(Integration::Evertime, 'api_key');
    }
}
