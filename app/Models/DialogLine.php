<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DialogLine extends Model
{
    use HasFactory;

    protected $fillable = ['lektion_id', 'topic', 'question_formal', 'question_informal', 'answer', 'arabic', 'sort'];

    public function lektion(): BelongsTo
    {
        return $this->belongsTo(Lektion::class);
    }
}
