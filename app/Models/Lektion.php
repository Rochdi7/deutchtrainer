<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Lektion extends Model
{
    use HasFactory;

    protected $table = 'lektionen';

    protected $fillable = ['number', 'title', 'source_pdf_path', 'imported_at'];

    protected $casts = ['imported_at' => 'datetime'];

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
}
