<?php

namespace App\Services\Gemini;

use App\Models\GeminiUsageLog;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiCallAnalyzer
{
    private const ENUM_TONE = ['neutral', 'satisfied', 'frustrated', 'upset', 'distressed'];

    private const ENUM_INTENSITY = ['low', 'medium', 'high'];

    private const ENUM_NOISE_SEVERITY = ['none', 'low', 'medium', 'high'];

    private const ENUM_AUDIO_QUALITY = ['clear', 'slightly_impaired', 'severely_impaired'];

    public function __construct(
        private string $apiKey = '',
        private string $baseUrl = '',
        private string $model = '',
        private string $fallbackModel = '',
        private float $temperature = 0.2,
    ) {
        $this->apiKey = $apiKey ?: config('services.gemini.api_key');
        $this->baseUrl = $baseUrl ?: config('services.gemini.base_url');
        $this->model = $model ?: config('services.gemini.model');
        $this->fallbackModel = $fallbackModel ?: config('services.gemini.fallback_model');
        $this->temperature = $temperature ?: config('services.gemini.temperature');
    }

    /**
     * Analyze a single audio file and return the structured result matching
     * the required output schema, plus which model actually produced it.
     * Falls back to the secondary model if the primary model fails —
     * callers should surface `model_used`/`was_fallback` so a silent fallback
     * (e.g. the primary hitting a rate limit) is visible rather than
     * indistinguishable from a normal primary-model answer.
     *
     * @return array{data: array, model_used: string, was_fallback: bool}
     */
    public function analyze(string $audioPath, string $mimeType): array
    {
        try {
            $data = $this->callModel($this->model, $audioPath, $mimeType);

            return ['data' => $data, 'model_used' => $this->model, 'was_fallback' => false];
        } catch (RuntimeException $e) {
            if (! $this->fallbackModel || $this->fallbackModel === $this->model) {
                throw $e;
            }

            $data = $this->callModel($this->fallbackModel, $audioPath, $mimeType);

            return ['data' => $data, 'model_used' => $this->fallbackModel, 'was_fallback' => true];
        }
    }

    private function callModel(string $model, string $audioPath, string $mimeType): array
    {
        GeminiUsageLog::recordRequest($model);

        $audioB64 = base64_encode(file_get_contents($audioPath));

        $response = Http::timeout(60)->post(
            "{$this->baseUrl}/models/{$model}:generateContent?key={$this->apiKey}",
            [
                'contents' => [[
                    'parts' => [
                        ['inline_data' => ['mime_type' => $mimeType, 'data' => $audioB64]],
                        ['text' => $this->prompt()],
                    ],
                ]],
                'generationConfig' => [
                    'temperature' => $this->temperature,
                    'responseMimeType' => 'application/json',
                    'responseSchema' => $this->schema(),
                ],
            ],
        );

        if ($response->failed()) {
            throw new RuntimeException("Gemini request failed ({$model}): ".$response->body());
        }

        $text = data_get($response->json(), 'candidates.0.content.parts.0.text');

        if (! $text) {
            throw new RuntimeException("Gemini returned no content ({$model})");
        }

        $decoded = json_decode($text, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("Gemini returned malformed JSON ({$model}): {$text}");
        }

        return $decoded;
    }

    private function schema(): array
    {
        return [
            'type' => 'OBJECT',
            'properties' => [
                'emotional_tone' => ['type' => 'STRING', 'enum' => self::ENUM_TONE],
                'emotional_intensity' => ['type' => 'STRING', 'enum' => self::ENUM_INTENSITY],
                'background_noise_present' => ['type' => 'BOOLEAN'],
                'background_noise_type' => ['type' => 'STRING'],
                'background_noise_severity' => ['type' => 'STRING', 'enum' => self::ENUM_NOISE_SEVERITY],
                'audio_quality' => ['type' => 'STRING', 'enum' => self::ENUM_AUDIO_QUALITY],
                'speaker_overlap_present' => ['type' => 'BOOLEAN'],
                'confidence' => ['type' => 'NUMBER'],
            ],
            'required' => [
                'emotional_tone', 'emotional_intensity', 'background_noise_present',
                'background_noise_type', 'background_noise_severity', 'audio_quality',
                'speaker_overlap_present', 'confidence',
            ],
        ];
    }

    private function prompt(): string
    {
        return <<<'PROMPT'
        You are analyzing a customer service call recording. Classify the customer's emotional tone and any background noise/audio-quality characteristics.

        Listen closely to paralinguistic cues, not just words: vocal energy, pace/rushed speech, clipped or short responses, sighing, strained or tense voice quality, and pitch changes. A customer can be frustrated or upset while speaking at normal or even quiet volume — do not default to "neutral" just because the call isn't loud or dramatic. Reserve "neutral" only for genuinely flat, unremarkable interactions with no audible sign of strain or annoyance.

        emotional_tone: neutral (no clear emotion, flat/routine), satisfied (pleased/positive), frustrated (annoyed/impatient, tense but not strongly angry), upset (clearly angry/agitated), distressed (overwhelmed/panicked/crying).
        emotional_intensity: low/medium/high strength of that tone.
        background_noise_present: is meaningful non-speech sound audible.
        background_noise_type: short description (e.g. "TV", "office chatter"), empty string if none.
        background_noise_severity: none/low/medium/high impact on the call.
        audio_quality: clear/slightly_impaired/severely_impaired — technical quality only (distortion, static, muffling), independent of emotional tone.
        speaker_overlap_present: do two speakers talk over each other enough to matter.
        confidence: your confidence 0.0-1.0 in this overall assessment.

        Do not infer frustration/distress from loudness alone, but do weigh vocal tension/pace/energy as valid signals independent of volume. Do not infer background noise solely from poor audio quality.
        PROMPT;
    }
}
