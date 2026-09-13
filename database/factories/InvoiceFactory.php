<?php

namespace Database\Factories;

use App\Enums\InvoiceType;
use App\Models\Client;
use App\Models\Company;
use App\Models\Invoice;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Plain pounds — Invoice's Money cast converts to/from pence
        // transparently on write/read, same as Booking's rate columns.
        $subtotal = fake()->numberBetween(500, 5000);
        $vat = round($subtotal * 0.2, 2);

        return [
            'company_id' => Company::factory(),
            'type' => InvoiceType::Client,
            'invoiceable_type' => Client::class,
            'invoiceable_id' => Client::factory(),
            'number' => 'INV-'.fake()->unique()->numerify('######'),
            'period_start' => now()->startOfWeek()->toDateString(),
            'period_end' => now()->endOfWeek()->toDateString(),
            'subtotal' => $subtotal,
            'vat_amount' => $vat,
            'total' => $subtotal + $vat,
            'generated_at' => now(),
        ];
    }
}
