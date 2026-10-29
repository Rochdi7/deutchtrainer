<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Latest checked result of one Grammatik-aktiv exercise (unit number × exercise key) per user. */
class GrammarResult extends Model
{
    protected $fillable = ['user_id', 'unit', 'exercise', 'correct', 'total'];

    protected $casts = ['unit' => 'integer', 'correct' => 'integer', 'total' => 'integer'];
}
