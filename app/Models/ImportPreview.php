<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ImportPreview extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id', 'lektion_number', 'title', 'pdf_path', 'status', 'raw_text', 'payload',
        'error', 'chunks_total', 'chunks_done', 'imported_at',
    ];

    protected $casts = ['payload' => 'array', 'imported_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
