<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reading extends Model
{
    protected $fillable = ['lektion_id', 'key', 'title', 'text_md', 'translation_md', 'translation_ar_md', 'questions', 'source_page', 'sort'];

    protected $casts = ['questions' => 'array'];

    public function lektion(): BelongsTo
    {
        return $this->belongsTo(Lektion::class);
    }
}
