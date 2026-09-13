<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\InvoiceLine;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<InvoiceLine>
 */
class InvoiceLineFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = fake()->randomFloat(2, 1, 5);
        $unitRate = fake()->numberBetween(80, 250);

        return [
            'invoice_id' => Invoice::factory(),
            'description' => fake()->words(3, true),
            'quantity' => $quantity,
            'unit_rate' => $unitRate,
            'amount' => round($quantity * $unitRate, 2),
        ];
    }
}
