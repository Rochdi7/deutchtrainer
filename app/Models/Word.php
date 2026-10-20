<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Word extends Model
{
    use HasFactory;

    public const TYPES = [
        'noun', 'verb', 'adjective', 'adverb', 'question_word', 'preposition',
        'particle', 'expression', 'country', 'language', 'nationality',
    ];

    protected $fillable = [
        'lektion_id', 'category_id', 'type', 'german', 'article', 'plural', 'plural_suffix',
        'arabic', 'example_de', 'example_ar', 'note', 'meta',
    ];

    protected $casts = ['meta' => 'array'];

    protected $appends = ['display'];

    public function lektion(): BelongsTo
    {
        return $this->belongsTo(Lektion::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function conjugation(): HasOne
    {
        return $this->hasOne(Conjugation::class);
    }

    public function progress(): HasMany
    {
        return $this->hasMany(UserWordProgress::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(QuizAnswer::class);
    }

    /** "die Handynummer" or "wohnen" */
    public function getDisplayAttribute(): string
    {
        return trim(($this->article ? $this->article.' ' : '').$this->german);
    }

    public function isIrregularVerb(): bool
    {
        return $this->type === 'verb' && (bool) ($this->meta['irregular'] ?? false);
    }

    public function hasValidArticle(): bool
    {
        return in_array($this->article, ['der', 'die', 'das'], true);
    }
}
