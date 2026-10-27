<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One row per user × Lektion × section ("objectives", "vocab", "grammar", "dialog", "reading", "writing", "exercises", "revision", "test"). */
class LessonProgress extends Model
{
    public const SECTIONS = ['objectives', 'vocab', 'grammar', 'dialog', 'reading', 'writing', 'exercises', 'revision', 'test'];

    protected $table = 'lesson_progress';

    protected $fillable = ['user_id', 'lektion_id', 'section', 'data', 'completed_at'];

    protected $casts = ['data' => 'array', 'completed_at' => 'datetime'];

    public function lektion(): BelongsTo
    {
        return $this->belongsTo(Lektion::class);
    }
}
