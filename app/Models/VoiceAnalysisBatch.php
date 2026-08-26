<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class VoiceAnalysisBatch extends Model
{
    protected $fillable = [
        'user_id',
        'batch_id',
        'original_filename',
        'missing_files',
        'unmatched_files',
    ];

    protected $casts = [
        'missing_files' => 'array',
        'unmatched_files' => 'array',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function analyses(): HasMany
    {
        return $this->hasMany(VoiceAnalysis::class, 'batch_id', 'batch_id');
    }
}
