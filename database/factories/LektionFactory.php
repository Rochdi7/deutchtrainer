<?php

namespace Database\Factories;

use App\Models\Lektion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Lektion> */
class LektionFactory extends Factory
{
    protected $model = Lektion::class;

    public function definition(): array
    {
        return [
            'number' => $this->faker->unique()->numberBetween(1, 500),
            'title' => 'Lektion '.$this->faker->word(),
            'source_pdf_path' => null,
            'imported_at' => now(),
        ];
    }
}
