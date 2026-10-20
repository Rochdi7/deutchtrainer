<?php

namespace Database\Factories;

use App\Models\User;
use App\Models\UserWordProgress;
use App\Models\Word;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<UserWordProgress> */
class UserWordProgressFactory extends Factory
{
    protected $model = UserWordProgress::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'word_id' => Word::factory(),
            'level' => 0,
            'correct_count' => 0,
            'wrong_count' => 0,
            'streak' => 0,
            'last_seen_at' => null,
            'next_review_at' => null,
        ];
    }
}
