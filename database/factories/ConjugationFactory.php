<?php

namespace Database\Factories;

use App\Models\Conjugation;
use App\Models\Word;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Conjugation> */
class ConjugationFactory extends Factory
{
    protected $model = Conjugation::class;

    public function definition(): array
    {
        $stem = $this->faker->unique()->lexify('????');

        return [
            'word_id' => Word::factory()->verb(),
            'ich' => $stem.'e',
            'du' => $stem.'st',
            'er_sie_es' => $stem.'t',
            'wir' => $stem.'en',
            'ihr' => $stem.'t',
            'sie_Sie' => $stem.'en',
        ];
    }
}
