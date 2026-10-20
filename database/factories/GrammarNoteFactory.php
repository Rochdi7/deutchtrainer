<?php

namespace Database\Factories;

use App\Models\GrammarNote;
use App\Models\Lektion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GrammarNote> */
class GrammarNoteFactory extends Factory
{
    protected $model = GrammarNote::class;

    public function definition(): array
    {
        return [
            'lektion_id' => Lektion::factory(),
            'title' => $this->faker->sentence(3),
            'body_md' => '## '.$this->faker->sentence(2)."\n\n".$this->faker->paragraph(),
            'sort' => 0,
        ];
    }
}
