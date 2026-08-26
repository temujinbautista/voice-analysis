<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VoiceAnalysis extends Model
{
    protected $fillable = [
        'user_id',
        'batch_id',
        'file_name',
        'storage_path',
        'status',
        'model_used',
        'result',
        'expected_result',
        'error',
    ];

    protected $casts = [
        'result' => 'array',
        'expected_result' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
