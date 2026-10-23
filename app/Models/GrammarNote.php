<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GrammarNote extends Model
{
    use HasFactory;

    protected $fillable = ['lektion_id', 'key', 'title', 'body_md', 'body_ar_md', 'source_page', 'sort'];

    public function lektion(): BelongsTo
    {
        return $this->belongsTo(Lektion::class);
    }
}
