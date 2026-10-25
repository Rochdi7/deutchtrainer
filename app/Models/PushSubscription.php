<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A browser's Web Push endpoint (one per device / browser profile). */
class PushSubscription extends Model
{
    protected $fillable = ['user_id', 'endpoint', 'public_key', 'auth_token', 'content_encoding'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
