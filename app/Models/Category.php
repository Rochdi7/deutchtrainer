<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Category extends Model
{
    use HasFactory;

    /** Canonical category order used by the importer. */
    public const CANONICAL = [
        'Formular',
        'Sprachen lernen',
        'Universität und Studium',
        'Arbeit und Beruf',
        'Freizeit/Kontinente',
        'Nomen',
        'Verben',
        'Adjektive',
        'Adverbien',
        'Fragewörter',
        'Präpositionen',
        'Partikeln',
        'Redemittel',
        'Länder-Sprachen-Nationalitäten',
        'Grammatik',
    ];

    protected $fillable = ['lektion_id', 'name', 'sort'];

    public function lektion(): BelongsTo
    {
        return $this->belongsTo(Lektion::class);
    }

    public function words(): HasMany
    {
        return $this->hasMany(Word::class);
    }

    public static function sortFor(string $name): int
    {
        $idx = array_search($name, self::CANONICAL, true);

        return $idx === false ? count(self::CANONICAL) + 1 : $idx;
    }
}
