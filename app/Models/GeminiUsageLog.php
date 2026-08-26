<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class GeminiUsageLog extends Model
{
    protected $fillable = [
        'model',
        'usage_date',
        'request_count',
    ];

    protected $casts = [
        'usage_date' => 'date',
    ];

    /**
     * Record one Gemini API request attempt against today's count for the given model.
     */
    public static function recordRequest(string $model): void
    {
        $log = static::firstOrNew([
            'model' => $model,
            'usage_date' => Carbon::today()->toDateString(),
        ]);

        $log->request_count = ($log->request_count ?? 0) + 1;
        $log->save();
    }

    public static function usedToday(string $model): int
    {
        return static::query()
            ->where('model', $model)
            ->where('usage_date', Carbon::today()->toDateString())
            ->value('request_count') ?? 0;
    }
}
