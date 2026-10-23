<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserWordProgress extends Model
{
    use HasFactory;

    protected $table = 'user_word_progress';

    public const MAX_LEVEL = 5;

    public const MASTERED_LEVEL = 4;

    /** Days until next review, indexed by the level reached after a correct answer. */
    public const INTERVALS = [1 => 1, 2 => 3, 3 => 7, 4 => 14, 5 => 30];

    protected $fillable = [
        'user_id', 'word_id', 'level', 'correct_count', 'wrong_count', 'streak', 'is_difficult', 'last_seen_at', 'next_review_at',
    ];

    protected $casts = ['last_seen_at' => 'datetime', 'next_review_at' => 'datetime', 'is_difficult' => 'boolean'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function word(): BelongsTo
    {
        return $this->belongsTo(Word::class);
    }

    public function isMastered(): bool
    {
        return $this->level >= self::MASTERED_LEVEL;
    }

    public function isWeak(): bool
    {
        return $this->wrong_count > $this->correct_count;
    }
}
