<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Lektion;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Category> */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $name = $this->faker->randomElement(Category::CANONICAL);

        return [
            'lektion_id' => Lektion::factory(),
            'name' => $name,
            'sort' => Category::sortFor($name),
        ];
    }
}
