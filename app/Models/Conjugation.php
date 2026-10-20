<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Conjugation extends Model
{
    use HasFactory;

    public const PERSONS = ['ich', 'du', 'er_sie_es', 'wir', 'ihr', 'sie_Sie'];

    public const LABELS = [
        'ich' => 'ich',
        'du' => 'du',
        'er_sie_es' => 'er/sie/es',
        'wir' => 'wir',
        'ihr' => 'ihr',
        'sie_Sie' => 'sie/Sie',
    ];

    protected $fillable = ['word_id', 'ich', 'du', 'er_sie_es', 'wir', 'ihr', 'sie_Sie'];

    public function word(): BelongsTo
    {
        return $this->belongsTo(Word::class);
    }

    public function forms(): array
    {
        return array_combine(self::PERSONS, array_map(fn ($p) => $this->{$p}, self::PERSONS));
    }
}
