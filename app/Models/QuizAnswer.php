<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuizAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'quiz_session_id', 'word_id', 'question_type', 'prompt', 'correct_answer',
        'given_answer', 'is_correct', 'answered_at',
    ];

    protected $casts = ['is_correct' => 'boolean', 'answered_at' => 'datetime'];

    public function session(): BelongsTo
    {
        return $this->belongsTo(QuizSession::class, 'quiz_session_id');
    }

    public function word(): BelongsTo
    {
        return $this->belongsTo(Word::class);
    }
}
