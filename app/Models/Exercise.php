<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Exercise extends Model
{
    /** Interactive exercise types rendered by Exercises/Play.vue. */
    public const TYPES = [
        'mc',          // multiple choice (options + answer)
        'article',     // der / die / das
        'gap',         // fill in the blank (accept[])
        'order',       // build the sentence from shuffled tokens
        'match',       // match pairs
        'translate',   // free text with accepted alternatives
        'plural',      // type the plural form
        'conjugation', // type / choose the verb form
        'dialog',      // choose the fitting reply
        'reading',     // passage + question (mc)
        'listen',      // TTS sentence + mc / gap
        'write',       // free writing with model answer (self-check)
    ];

    public const SECTIONS = ['vocab', 'grammar', 'dialog', 'reading', 'listening', 'writing', 'revision', 'test'];

    protected $fillable = ['lektion_id', 'key', 'section', 'type', 'prompt', 'data', 'difficulty', 'sort', 'source_page'];

    protected $casts = ['data' => 'array'];

    public function lektion(): BelongsTo
    {
        return $this->belongsTo(Lektion::class);
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(ExerciseAttempt::class);
    }

    public function isSelfChecked(): bool
    {
        return $this->type === 'write';
    }
}
