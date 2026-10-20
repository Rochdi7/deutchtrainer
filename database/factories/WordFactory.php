<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Lektion;
use App\Models\Word;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Word> */
class WordFactory extends Factory
{
    protected $model = Word::class;

    public function definition(): array
    {
        return [
            'lektion_id' => Lektion::factory(),
            'category_id' => null,
            'type' => 'noun',
            'german' => ucfirst($this->faker->unique()->lexify('??????')),
            'article' => $this->faker->randomElement(['der', 'die', 'das']),
            'plural' => ucfirst($this->faker->unique()->lexify('??????')).'en',
            'plural_suffix' => '-en',
            'arabic' => $this->faker->unique()->lexify('??????'),
            'example_de' => null,
            'example_ar' => null,
            'note' => null,
            'meta' => null,
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Word $word) {
            if ($word->category_id === null && $word->lektion_id) {
                $word->category_id = Category::firstOrCreate(
                    ['lektion_id' => $word->lektion_id, 'name' => 'Nomen'],
                    ['sort' => Category::sortFor('Nomen')]
                )->id;
            }
        });
    }

    public function noun(): static
    {
        return $this->state(fn () => ['type' => 'noun']);
    }

    public function verb(bool $irregular = false): static
    {
        return $this->state(fn () => [
            'type' => 'verb',
            'article' => null,
            'plural' => null,
            'plural_suffix' => null,
            'german' => $this->faker->unique()->lexify('????').'en',
            'meta' => ['irregular' => $irregular, 'stem_rule' => null, 'modal' => false],
        ]);
    }

    public function adjective(): static
    {
        return $this->state(fn () => ['type' => 'adjective', 'article' => null, 'plural' => null, 'plural_suffix' => null]);
    }

    public function ofType(string $type): static
    {
        return $this->state(fn () => [
            'type' => $type,
            'article' => $type === 'noun' ? $this->faker->randomElement(['der', 'die', 'das']) : null,
            'plural' => $type === 'noun' ? ucfirst($this->faker->unique()->lexify('??????')).'e' : null,
            'plural_suffix' => $type === 'noun' ? '-e' : null,
        ]);
    }
}
