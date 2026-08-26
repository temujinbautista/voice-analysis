<?php

namespace App\Jobs;

use App\Models\VoiceAnalysis;
use App\Services\Audio\SilenceDetector;
use App\Services\Gemini\GeminiCallAnalyzer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class AnalyzeVoiceCallJob implements ShouldQueue
{
    use Queueable;

    public $tries = 1;

    public $timeout = 120;

    public function __construct(private readonly int $voiceAnalysisId) {}

    public function handle(GeminiCallAnalyzer $analyzer, SilenceDetector $silenceDetector): void
    {
        $analysis = VoiceAnalysis::find($this->voiceAnalysisId);

        if (! $analysis) {
            return;
        }

        $analysis->update(['status' => 'processing']);

        $absolutePath = Storage::disk('local')->path($analysis->storage_path);
        $mimeType = mime_content_type($absolutePath) ?: 'audio/ogg';

        try {
            $analyzed = $analyzer->analyze($absolutePath, $mimeType);
            $result = $analyzed['data'];
            $result['long_silence_present'] = $this->detectLongSilence($silenceDetector, $absolutePath);

            $analysis->update([
                'status' => 'completed',
                'result' => $result,
                'model_used' => $analyzed['model_used'],
                'was_fallback' => $analyzed['was_fallback'],
            ]);
        } catch (RuntimeException $e) {
            $analysis->update(['status' => 'failed', 'error' => $e->getMessage()]);
        }
    }

    /**
     * Deterministic (non-LLM) long-silence detection via ffmpeg. Falls back
     * to false if ffmpeg isn't available rather than failing the whole
     * analysis over a secondary signal.
     */
    private function detectLongSilence(SilenceDetector $silenceDetector, string $audioPath): bool
    {
        try {
            return $silenceDetector->hasLongSilence($audioPath);
        } catch (Throwable $e) {
            Log::warning('Silence detection failed, defaulting to false', ['error' => $e->getMessage()]);

            return false;
        }
    }
}
