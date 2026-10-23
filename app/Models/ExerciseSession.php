<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExerciseSession extends Model
{
    public const MODES = ['set', 'test', 'practice', 'final', 'revision'];

    public const PASS_THRESHOLD = 0.8;

    protected $fillable = ['user_id', 'lektion_id', 'mode', 'label', 'section', 'items', 'total', 'correct', 'started_at', 'finished_at'];

    protected $casts = ['items' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lektion(): BelongsTo
    {
        return $this->belongsTo(Lektion::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ExerciseAttempt::class);
    }

    public function scorePercent(): int
    {
        return $this->total > 0 ? (int) round($this->correct / $this->total * 100) : 0;
    }

    public function isPassed(): bool
    {
        return $this->total > 0 && $this->correct / $this->total >= self::PASS_THRESHOLD;
    }
}
