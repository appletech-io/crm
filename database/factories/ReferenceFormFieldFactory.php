<?php

namespace Database\Factories;

use App\Models\ReferenceForm;
use App\Models\ReferenceFormField;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReferenceFormField>
 */
class ReferenceFormFieldFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference_form_id' => ReferenceForm::factory(),
            'label' => fake()->sentence(4),
            'field_type' => 'text',
            'required' => true,
            'sort_order' => 0,
        ];
    }

    public function radio(): static
    {
        return $this->state(fn (): array => [
            'field_type' => 'radio',
            'options' => ['Yes', 'No'],
        ]);
    }
}
