<?php

namespace Database\Factories;

use App\Models\DialogLine;
use App\Models\Lektion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<DialogLine> */
class DialogLineFactory extends Factory
{
    protected $model = DialogLine::class;

    public function definition(): array
    {
        return [
            'lektion_id' => Lektion::factory(),
            'topic' => $this->faker->word(),
            'question_formal' => 'Wie '.$this->faker->word().' Sie?',
            'question_informal' => 'Wie '.$this->faker->word().' du?',
            'answer' => 'Ich '.$this->faker->unique()->word().'.',
            'arabic' => $this->faker->unique()->lexify('??????'),
            'sort' => 0,
        ];
    }
}
