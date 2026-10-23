<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExerciseAttempt extends Model
{
    protected $fillable = ['user_id', 'exercise_session_id', 'exercise_id', 'word_id', 'item_id', 'type', 'given', 'is_correct', 'answered_at'];

    protected $casts = ['is_correct' => 'boolean', 'answered_at' => 'datetime'];

    public function session(): BelongsTo
    {
        return $this->belongsTo(ExerciseSession::class, 'exercise_session_id');
    }

    public function exercise(): BelongsTo
    {
        return $this->belongsTo(Exercise::class);
    }

    public function word(): BelongsTo
    {
        return $this->belongsTo(Word::class);
    }
}
