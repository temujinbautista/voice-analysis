<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

class GeminiUsageLog extends Model
{
    protected $fillable = [
        'model',
        'usage_date',
        'request_count',
    ];

    protected $casts = [
        // Explicit storage format matters: the bare 'date' cast still saves
        // through the connection's full datetime format (e.g. "2026-08-26
        // 00:00:00"), not a plain "2026-08-26" — MySQL's real DATE column
        // type silently truncates that back to date-only on insert, masking
        // it, but SQLite has no such column type and stores it verbatim,
        // so every WHERE on a plain toDateString() value matched nothing.
        'usage_date' => 'date:Y-m-d',
    ];

    /**
     * Record one Gemini API request attempt against today's count for the given model.
     *
     * firstOrNew()+save() has a race: with more than one concurrent worker,
     * two requests can both find "no row yet" for the same (model, date) and
     * both try to insert, tripping the unique constraint. Instead, optimistically
     * insert first; if that fails on the unique constraint (SQLSTATE 23000 on
     * both MySQL and SQLite), another request already created today's row in
     * the meantime, so fall back to an atomic increment instead. increment()
     * compiles to `SET request_count = request_count + 1`, which the database
     * itself serializes correctly even under concurrent writers.
     */
    public static function recordRequest(string $model): void
    {
        $today = Carbon::today()->toDateString();

        try {
            static::create(['model' => $model, 'usage_date' => $today, 'request_count' => 1]);
        } catch (QueryException $e) {
            if ($e->getCode() !== '23000') {
                throw $e;
            }

            static::query()->where('model', $model)->where('usage_date', $today)->increment('request_count');
        }
    }

    public static function usedToday(string $model): int
    {
        return static::query()
            ->where('model', $model)
            ->where('usage_date', Carbon::today()->toDateString())
            ->value('request_count') ?? 0;
    }
}
