<?php

namespace Database\Factories;

use App\Models\QuizSession;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<QuizSession> */
class QuizSessionFactory extends Factory
{
    protected $model = QuizSession::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'mode' => 'global',
            'lektion_id' => null,
            'total' => 20,
            'correct' => 0,
            'questions' => [],
            'started_at' => now(),
            'finished_at' => null,
        ];
    }
}
