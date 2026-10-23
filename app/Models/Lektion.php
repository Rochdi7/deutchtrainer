<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lektion extends Model
{
    use HasFactory;

    protected $table = 'lektionen';

    protected $fillable = ['level', 'number', 'slug', 'title', 'subtitle', 'objectives', 'source_pages', 'revision_md', 'revision_ar_md', 'sort', 'source_pdf_path', 'imported_at'];

    protected $casts = ['imported_at' => 'datetime', 'objectives' => 'array', 'source_pages' => 'array'];

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class)->orderBy('sort');
    }

    public function words(): HasMany
    {
        return $this->hasMany(Word::class);
    }

    public function dialogLines(): HasMany
    {
        return $this->hasMany(DialogLine::class)->orderBy('sort');
    }

    public function grammarNotes(): HasMany
    {
        return $this->hasMany(GrammarNote::class)->orderBy('sort');
    }

    public function quizSessions(): HasMany
    {
        return $this->hasMany(QuizSession::class);
    }

    public function readings(): HasMany
    {
        return $this->hasMany(Reading::class)->orderBy('sort');
    }

    public function exercises(): HasMany
    {
        return $this->hasMany(Exercise::class)->orderBy('sort');
    }

    public function exerciseSessions(): HasMany
    {
        return $this->hasMany(ExerciseSession::class);
    }

    public function progress(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }

    /** Lektionen of a level in course order. */
    public function scopeLevel($query, string $level = 'A1')
    {
        return $query->where('level', $level)->orderBy('sort')->orderBy('number');
    }
}
