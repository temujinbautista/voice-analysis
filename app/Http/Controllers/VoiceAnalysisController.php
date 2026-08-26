<?php

namespace App\Http\Controllers;

use App\Jobs\AnalyzeVoiceCallJob;
use App\Models\GeminiUsageLog;
use App\Models\VoiceAnalysis;
use App\Models\VoiceAnalysisBatch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipArchive;

class VoiceAnalysisController extends Controller
{
    private const AUDIO_EXTENSIONS = ['wav', 'mp3', 'ogg', 'flac', 'aac'];

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'archive' => ['required', 'file', 'max:512000'],
        ]);

        $file = $request->file('archive');
        $extension = strtolower($file->getClientOriginalExtension());

        if ($extension === 'zip') {
            return $this->storeZipBatch($request);
        }

        if (in_array($extension, self::AUDIO_EXTENSIONS)) {
            return $this->storeSingleFile($request);
        }

        return response()->json([
            'message' => 'Unsupported file type. Upload a .zip evaluation batch (audio files + labels.csv) or a single audio file ('.
                implode(', ', self::AUDIO_EXTENSIONS).').',
        ], 422);
    }

    private function storeZipBatch(Request $request): JsonResponse
    {
        $file = $request->file('archive');
        $batchId = (string) Str::uuid();
        $extractDir = storage_path("app/private/voice_uploads/{$batchId}");

        $zip = new ZipArchive();
        $opened = $zip->open($file->getRealPath());

        if ($opened !== true) {
            return response()->json(['message' => 'The uploaded file is not a valid ZIP archive.'], 422);
        }

        $zip->extractTo($extractDir);
        $zip->close();

        $relativePrefix = "voice_uploads/{$batchId}";
        $entries = $this->listEntries($extractDir);

        // Some zip tools wrap all contents in a single top-level folder — flatten if so.
        if (count($entries) === 1 && is_dir("{$extractDir}/{$entries[0]}")) {
            $relativePrefix .= "/{$entries[0]}";
            $extractDir .= "/{$entries[0]}";
            $entries = $this->listEntries($extractDir);
        }

        $csvFiles = array_values(array_filter($entries, fn ($f) => str_ends_with(strtolower($f), '.csv')));
        $audioFiles = array_values(array_filter($entries, fn ($f) => in_array(strtolower(pathinfo($f, PATHINFO_EXTENSION)), self::AUDIO_EXTENSIONS)));

        if (count($csvFiles) !== 1) {
            return response()->json([
                'message' => 'The archive must contain exactly one CSV manifest at its root (found '.count($csvFiles).').',
            ], 422);
        }

        $manifest = $this->parseManifest("{$extractDir}/{$csvFiles[0]}");

        $missingFiles = array_values(array_diff(array_keys($manifest), $audioFiles));
        $unmatchedFiles = array_values(array_diff($audioFiles, array_keys($manifest)));

        VoiceAnalysisBatch::create([
            'user_id' => $request->user()->id,
            'batch_id' => $batchId,
            'original_filename' => $file->getClientOriginalName(),
            'missing_files' => $missingFiles,
            'unmatched_files' => $unmatchedFiles,
        ]);

        foreach ($audioFiles as $name) {
            $analysis = VoiceAnalysis::create([
                'user_id' => $request->user()->id,
                'batch_id' => $batchId,
                'file_name' => $name,
                'storage_path' => "{$relativePrefix}/{$name}",
                'status' => 'pending',
                'expected_result' => $manifest[$name] ?? null,
            ]);

            AnalyzeVoiceCallJob::dispatch($analysis->id);
        }

        return response()->json([
            'batchId' => $batchId,
            'missingFiles' => $missingFiles,
            'unmatchedFiles' => $unmatchedFiles,
            'processedCount' => count($audioFiles),
        ]);
    }

    private function storeSingleFile(Request $request): JsonResponse
    {
        $file = $request->file('archive');
        $mimeType = $file->getMimeType() ?: '';

        if (! str_starts_with($mimeType, 'audio/')) {
            return response()->json([
                'message' => "The uploaded file doesn't look like a valid audio file (detected type: {$mimeType}).",
            ], 422);
        }

        $batchId = (string) Str::uuid();
        $name = $file->getClientOriginalName();
        $storedPath = $file->storeAs("voice_uploads/{$batchId}", $name, 'local');

        VoiceAnalysisBatch::create([
            'user_id' => $request->user()->id,
            'batch_id' => $batchId,
            'original_filename' => $name,
            'missing_files' => [],
            'unmatched_files' => [],
        ]);

        $analysis = VoiceAnalysis::create([
            'user_id' => $request->user()->id,
            'batch_id' => $batchId,
            'file_name' => $name,
            'storage_path' => $storedPath,
            'status' => 'pending',
            'expected_result' => null,
        ]);

        AnalyzeVoiceCallJob::dispatch($analysis->id);

        return response()->json([
            'batchId' => $batchId,
            'missingFiles' => [],
            'unmatchedFiles' => [],
            'processedCount' => 1,
        ]);
    }

    public function status(Request $request, string $batchId)
    {
        $analyses = VoiceAnalysis::where('batch_id', $batchId)
            ->where('user_id', $request->user()->id)
            ->orderBy('id')
            ->get();

        $primaryModel = config('services.gemini.model');

        return response()->json([
            'results' => $analyses->map(fn (VoiceAnalysis $a) => [
                'name' => $a->file_name,
                'status' => $a->status,
                'prediction' => $a->result,
                'expected' => $a->expected_result,
                'error' => $a->error,
                'modelUsed' => $a->model_used,
                'wasFallback' => $a->model_used !== null && $a->model_used !== $primaryModel,
            ]),
        ]);
    }

    public function audio(Request $request, string $batchId, string $filename): StreamedResponse
    {
        $analysis = VoiceAnalysis::where('batch_id', $batchId)
            ->where('user_id', $request->user()->id)
            ->where('file_name', $filename)
            ->firstOrFail();

        abort_unless(Storage::disk('local')->exists($analysis->storage_path), 404);

        return Storage::disk('local')->response($analysis->storage_path, $filename);
    }

    public function usage(): JsonResponse
    {
        $limits = config('services.gemini.daily_limits', []);
        $primaryModel = config('services.gemini.model');
        $models = [$primaryModel, config('services.gemini.fallback_model')];

        $usage = collect($models)
            ->unique()
            ->filter()
            ->map(function (string $model) use ($limits, $primaryModel) {
                $limit = $limits[$model] ?? null;
                $used = GeminiUsageLog::usedToday($model);

                return [
                    'model' => $model,
                    'isPrimary' => $model === $primaryModel,
                    'used' => $used,
                    'limit' => $limit,
                    'remaining' => $limit !== null ? max(0, $limit - $used) : null,
                ];
            })
            ->values();

        return response()->json(['usage' => $usage]);
    }

    public function batches(Request $request)
    {
        $batches = VoiceAnalysisBatch::where('user_id', $request->user()->id)
            ->withCount('analyses')
            ->orderByDesc('id')
            ->get();

        return response()->json([
            'batches' => $batches->map(fn (VoiceAnalysisBatch $b) => [
                'batchId' => $b->batch_id,
                'originalFilename' => $b->original_filename,
                'fileCount' => $b->analyses_count,
                'missingFiles' => $b->missing_files ?? [],
                'unmatchedFiles' => $b->unmatched_files ?? [],
                'createdAt' => $b->created_at->toIso8601String(),
            ]),
        ]);
    }

    /**
     * @return list<string>
     */
    private function listEntries(string $dir): array
    {
        return array_values(array_filter(
            scandir($dir) ?: [],
            fn ($f) => ! in_array($f, ['.', '..', '__MACOSX']) && ! str_starts_with($f, '.'),
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function parseManifest(string $csvPath): array
    {
        $manifest = [];
        $handle = fopen($csvPath, 'r');

        if ($handle === false) {
            return $manifest;
        }

        $header = fgetcsv($handle);
        $nameIndex = $header ? array_search('name', $header) : false;
        $resultIndex = $header ? array_search('result_json', $header) : false;

        if ($nameIndex !== false) {
            while (($row = fgetcsv($handle)) !== false) {
                $name = $row[$nameIndex] ?? null;

                if (! $name) {
                    continue;
                }

                $resultJson = $resultIndex !== false ? ($row[$resultIndex] ?? '') : '';
                $manifest[$name] = $resultJson !== '' ? json_decode($resultJson, true) : null;
            }
        }

        fclose($handle);

        return $manifest;
    }
}
