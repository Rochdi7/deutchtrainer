<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class QuizSession extends Model
{
    use HasFactory;

    public const PASS_THRESHOLD = 0.8;

    protected $fillable = ['user_id', 'mode', 'lektion_id', 'total', 'correct', 'questions', 'started_at', 'finished_at'];

    protected $casts = [
        'questions' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lektion(): BelongsTo
    {
        return $this->belongsTo(Lektion::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(QuizAnswer::class);
    }

    public function scorePercent(): int
    {
        return $this->total > 0 ? (int) round($this->correct * 100 / $this->total) : 0;
    }

    public function isPassed(): bool
    {
        return $this->total > 0 && ($this->correct / $this->total) >= self::PASS_THRESHOLD;
    }
}
