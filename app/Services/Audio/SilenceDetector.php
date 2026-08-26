<?php

namespace App\Services\Audio;

use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Deterministic (non-LLM) detection of unusually long dead-air silence,
 * using ffmpeg's `silencedetect` audio filter.
 *
 * Calibration note: against the 3 provided labeled samples, the longest
 * silence gap found in any clip labeled `long_silence_present: false` was
 * ~7.36s (call_003) — a normal conversational pause, not a call-flow
 * problem. No positive example was available to calibrate the true
 * threshold, so this is set conservatively above that observed maximum.
 * Revisit if labeled examples with real long-silence incidents become
 * available.
 */
class SilenceDetector
{
    public function __construct(
        private string $ffmpegPath = '',
        private readonly float $noiseFloorDb = -35.0,
        private readonly float $minGapSeconds = 0.5,
        private readonly float $longSilenceThresholdSeconds = 10.0,
    ) {
        $this->ffmpegPath = $ffmpegPath ?: config('services.ffmpeg.path');
    }

    /**
     * True if the audio contains at least one silence gap at or beyond
     * the "unusually long" threshold.
     */
    public function hasLongSilence(string $audioPath): bool
    {
        foreach ($this->detectSilenceGaps($audioPath) as $duration) {
            if ($duration >= $this->longSilenceThresholdSeconds) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<float> durations (seconds) of every detected quiet gap
     */
    public function detectSilenceGaps(string $audioPath): array
    {
        $process = new Process([
            $this->ffmpegPath,
            '-i', $audioPath,
            '-af', "silencedetect=noise={$this->noiseFloorDb}dB:d={$this->minGapSeconds}",
            '-f', 'null',
            '-',
        ]);
        $process->setTimeout(60);
        $process->run();

        if (! $process->isSuccessful() && $process->getErrorOutput() === '') {
            throw new RuntimeException('ffmpeg silence detection failed to run: '.$process->getExitCodeText());
        }

        preg_match_all('/silence_duration:\s*([\d.]+)/', $process->getErrorOutput(), $matches);

        return array_map('floatval', $matches[1] ?? []);
    }
}
