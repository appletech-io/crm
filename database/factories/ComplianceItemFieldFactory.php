<?php

namespace Database\Factories;

use App\Enums\ComplianceItemDataType;
use App\Models\ComplianceItem;
use App\Models\ComplianceItemField;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ComplianceItemField>
 */
class ComplianceItemFieldFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'compliance_item_id' => ComplianceItem::factory(),
            'name' => fake()->words(2, true),
            'data_type' => fake()->randomElement(ComplianceItemDataType::cases())->value,
        ];
    }
}
