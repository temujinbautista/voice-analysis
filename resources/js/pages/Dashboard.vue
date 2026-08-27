<template>
    <Head title="Dashboard" />

    <v-toolbar color="#111827" flat class="mb-8">
        <a href="https://portfolio.temujinbautista.workers.dev/" target="_blank" rel="noopener noreferrer" class="ml-4 flex items-center">
            <img src="/images/TB_Logo_transparent.png" alt="Temujin Bautista logo" class="h-8 w-auto object-contain" />
        </a>
        <v-toolbar-title> Temujin Bautista | Technical Trial Project </v-toolbar-title>
        <v-btn variant="outlined" class="mr-5" color="#FFFFFF" @click="logout">Logout</v-btn>
    </v-toolbar>

    <div class="mx-10 p-3">
        <v-row>
            <v-col cols="12" md="4">
                <v-card>
                    <v-toolbar color="secondary" flat>
                        <v-toolbar-title>Voice Analysis</v-toolbar-title>

                        <div v-if="primaryUsage" class="mr-4 w-[260px] text-xs text-white">
                            <div class="mb-1 flex items-center justify-between">
                                <span>
                                    <!-- {{ primaryUsage.model }}  -->
                                    Daily Free Tier Token Limit
                                </span>
                                <span v-if="primaryUsage.limit !== null"> {{ primaryUsage.used }} / {{ primaryUsage.limit }} </span>
                                <span v-else> {{ primaryUsage.used }} request(s) today (no published limit) </span>
                            </div>
                            <v-progress-linear
                                v-if="primaryUsage.limit !== null"
                                :model-value="(primaryUsage.used / (primaryUsage.limit ?? 1)) * 100"
                                :color="usageColor"
                                height="6"
                                rounded
                            />
                        </div>

                        <v-tooltip location="bottom end" max-width="380">
                            <template #activator="{ props: tooltipProps }">
                                <v-icon icon="mdi-help-circle-outline" class="mr-5" size="24" style="cursor: default" v-bind="tooltipProps" />
                            </template>
                            <div class="body-1">
                                Voice analysis is performed by Google's Gemini API &mdash; model
                                <strong class="text-cyan-400">gemini-3-flash-preview</strong>, used for both the primary and fallback attempt
                                (some alternative models weren't reachable with this API key/project, e.g. a 404 on gemini-2.5-flash).
                                <br />
                                <br />
                                Pricing (standard tier, per Google's published rates): $0.50 / $3.00 per 1M text input/output tokens
                                (<strong class="text-cyan-400">$1.00 per 1 Mil audio input tokens</strong>, output includes thinking tokens).
                                <br />
                                <br />
                                <strong class="text-cyan-400">Measured cost per audio minute</strong> (from real test calls, standard/real-time
                                pricing &mdash; what this dashboard actually uses):
                                <table class="my-2 mb-2 w-full text-left text-xs">
                                    <thead>
                                        <tr class="border-b border-white/30">
                                            <th class="pr-3 font-semibold">Clip</th>
                                            <th class="font-semibold">Cost/min</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr class="border-b border-white/10">
                                            <td class="py-1 pr-3 text-cyan-400">call_001 (30.9s)</td>
                                            <td class="py-1 text-cyan-400">~$0.00654</td>
                                        </tr>
                                        <tr class="border-b border-white/10">
                                            <td class="py-1 pr-3 text-cyan-400">call_002 (35s)</td>
                                            <td class="py-1 text-cyan-400">~$0.00565</td>
                                        </tr>
                                        <tr>
                                            <td class="py-1 pr-3 text-cyan-400">call_003 (2.9min)</td>
                                            <td class="py-1 text-cyan-400">~$0.00237</td>
                                        </tr>
                                    </tbody>
                                </table>
                                Blended average <strong class="text-cyan-400">~$0.0034/min</strong> across these 3 clips &mdash; this is above
                                AutoAce's $0.003/minute ceiling on shorter clips specifically.
                                Cost/accuracy tradeoffs versus the cheaper gemini-3.1-flash-lite/gemini-3.5-flash-lite pairing are documented in
                                the technical memo.
                                <br />
                                <br />
                                This website is hosted for free on Render using it's <strong> FREE TIER </strong>, so latency may be higher than a
                                paid hosting service. The Gemini API is also a paid service, but for trial purposes we are using the
                                <strong> FREE TIER </strong>. If you exceed the free tier limit, you will receive an error message and will not be
                                able to analyze any more audio until the next day.
                            </div>
                        </v-tooltip>
                    </v-toolbar>
                    <v-card-text>
                        <div class="flex flex-wrap items-end gap-4">
                            <v-file-input
                                v-model="archiveFile"
                                label="Upload evaluation batch or single file"
                                accept=".zip,audio/*"
                                prepend-icon="mdi-folder-zip-outline"
                                :error-messages="fileErrors"
                                :disabled="uploading || isPolling"
                                show-size
                                variant="outlined"
                                density="compact"
                                hide-details="auto"
                                persistent-hint
                                hint="(.zip: audio files + labels.csv) or a single audio file"
                            />
                            <v-btn color="primary" class="mb-6" :loading="processing" :disabled="!archiveFile" @click="submit"> Analyze </v-btn>
                        </div>

                        <v-alert v-if="missingFiles.length" type="warning" class="mt-4" variant="tonal">
                            {{ missingFiles.length }} file(s) listed in the manifest were not found in the archive:
                            {{ missingFiles.join(', ') }}
                        </v-alert>

                        <v-alert v-if="unmatchedFiles.length" type="warning" class="mt-4" variant="tonal">
                            {{ unmatchedFiles.length }} audio file(s) in the archive were not listed in the manifest (processed anyway):
                            {{ unmatchedFiles.join(', ') }}
                        </v-alert>

                        <v-divider class="mt-5" style="--v-border-opacity: 1" />
                        <h2 class="mt-4 text-lg font-semibold">Previous Batches</h2>
                        <v-data-table
                            v-model:sort-by="sortBy"
                            :headers="batchHeaders"
                            :items="batches"
                            item-value="batchId"
                            style="cursor: pointer"
                            @click:row="(_e: Event, { item }: { item: BatchSummary }) => viewBatch(item.batchId)"
                        >
                            <template #item.createdAt="{ item }">
                                {{ new Date(item.createdAt).toLocaleString() }}
                            </template>
                            <template #item.missingFiles="{ item }">
                                {{ item.missingFiles.length }}
                            </template>
                            <template #item.unmatchedFiles="{ item }">
                                {{ item.unmatchedFiles.length }}
                            </template>
                            <template #item.actions="{ item }">
                                <v-btn size="small" variant="outlined" :disabled="uploading || isPolling" @click="viewBatch(item.batchId)">View</v-btn>
                            </template>
                        </v-data-table>
                    </v-card-text>
                </v-card>
            </v-col>

            <v-col cols="12" md="8">
                <v-card>
                    <v-toolbar color="secondary" flat>
                        <v-toolbar-title>Results</v-toolbar-title>
                        <v-btn
                            size="small"
                            class="float-right mr-3"
                            variant="outlined"
                            :disabled="isPolling || results.length === 0"
                            @click="downloadResults"
                        >
                            Download JSON
                        </v-btn>
                    </v-toolbar>
                    <v-card-text>
                        <v-progress-linear
                            v-if="isPolling || uploading"
                            :indeterminate="results.length === 0"
                            :model-value="progressPercent"
                            class="mb-4"
                            color="primary"
                            height="6"
                        />

                        <v-alert v-if="isPolling || uploading" type="info" class="mb-4" variant="tonal">
                            <template v-if="results.length">Processing {{ completedCount }} of {{ results.length }} file(s)&hellip;</template>
                            <template v-else>Uploading and analyzing your batch&hellip;</template>
                        </v-alert>

                        <v-alert v-else-if="hasErrors" type="warning" class="mb-4" variant="tonal">
                            {{ errorCount }} of {{ results.length }} file(s) failed to process. See the Error column below.
                        </v-alert>

                        <v-data-table :headers="headers" :items="tableItems" item-value="name">
                            <template #item="{ item, columns }">
                                <tr>
                                    <td>{{ item.name }}</td>
                                    <td>{{ item.status }}</td>
                                    <td
                                        v-if="item.status === 'pending' || item.status === 'processing'"
                                        :colspan="columns.length - 4"
                                        class="text-gray-500"
                                    >
                                        <v-progress-linear indeterminate height="6" color="primary" rounded />
                                    </td>
                                    <td v-else-if="item.error" :colspan="columns.length - 4" class="text-red-600">{{ item.error }}</td>
                                    <template v-else>
                                        <td>{{ item.tone }}</td>
                                        <td>{{ item.intensity }}</td>
                                        <td>{{ item.noise }}</td>
                                        <td>{{ item.noiseType }}</td>
                                        <td>{{ item.noiseSeverity }}</td>
                                        <td>{{ item.audioQuality }}</td>
                                        <td>{{ item.overlap }}</td>
                                        <td>{{ item.silence }}</td>
                                        <td>{{ item.confidence }}</td>
                                    </template>
                                    <td>
                                        <v-chip v-if="item.modelUsed" size="small" :color="item.wasFallback ? 'warning' : undefined" variant="tonal">
                                            {{ item.modelUsed }}
                                            <v-tooltip v-if="item.wasFallback" activator="parent" location="top">
                                                Primary model was unavailable (e.g. rate limit) — this result came from the fallback model.
                                            </v-tooltip>
                                        </v-chip>
                                    </td>
                                    <td>
                                        <v-btn
                                            :icon="playingFile === item.name ? 'mdi-pause' : 'mdi-play'"
                                            size="small"
                                            rounded
                                            :color="playingFile != item.name ? 'primary' : 'error'"
                                            @click="togglePlay(item.name)"
                                        />
                                    </td>
                                </tr>
                            </template>
                        </v-data-table>
                    </v-card-text>
                </v-card>
            </v-col>
        </v-row>
    </div>
</template>

<script setup lang="ts">
import http from '@/lib/axios';
import { Head, router } from '@inertiajs/vue3';
import axios from 'axios';
import { computed, onMounted, onUnmounted, ref } from 'vue';

interface Prediction {
    emotional_tone: string;
    emotional_intensity: string;
    background_noise_present: boolean;
    background_noise_type: string;
    background_noise_severity: string;
    audio_quality: string;
    speaker_overlap_present: boolean;
    long_silence_present: boolean;
    confidence: number;
}

type AnalysisStatus = 'pending' | 'processing' | 'completed' | 'failed';

interface Result {
    name: string;
    status: AnalysisStatus;
    prediction: Prediction | null;
    expected: Prediction | null;
    error: string | null;
    modelUsed: string | null;
    wasFallback: boolean;
}

interface BatchSummary {
    batchId: string;
    originalFilename: string;
    fileCount: number;
    missingFiles: string[];
    unmatchedFiles: string[];
    createdAt: string;
}

interface UsageEntry {
    model: string;
    isPrimary: boolean;
    used: number;
    limit: number | null;
    remaining: number | null;
}

const archiveFile = ref<File | null>(null);
const fileErrors = ref<string[]>([]);
const processing = ref(false);
const uploading = ref(false);

const results = ref<Result[]>([]);
const missingFiles = ref<string[]>([]);
const unmatchedFiles = ref<string[]>([]);
const isPolling = ref(false);
const currentBatchId = ref<string | null>(null);
let pollHandle: ReturnType<typeof setInterval> | null = null;

const batches = ref<BatchSummary[]>([]);
const geminiUsage = ref<UsageEntry[]>([]);
const sortBy = ref([{ key: 'createdAt', order: 'desc' }]);

const completedCount = computed(() => results.value.filter((r) => r.status === 'completed' || r.status === 'failed').length);
const progressPercent = computed(() => (results.value.length ? (completedCount.value / results.value.length) * 100 : 0));
const hasErrors = computed(() => results.value.some((r) => r.status === 'failed'));
const errorCount = computed(() => results.value.filter((r) => r.status === 'failed').length);

const primaryUsage = computed(() => geminiUsage.value.find((u) => u.isPrimary) ?? null);
const usageColor = computed(() => {
    if (!primaryUsage.value || !primaryUsage.value.limit) return 'primary';
    const pct = primaryUsage.value.used / primaryUsage.value.limit;
    if (pct >= 0.9) return 'error';
    if (pct >= 0.7) return 'warning';
    return 'primary';
});

const headers = [
    { title: 'File', key: 'name' },
    { title: 'Status', key: 'status' },
    { title: 'Tone', key: 'tone' },
    { title: 'Intensity', key: 'intensity' },
    { title: 'Noise', key: 'noise' },
    { title: 'Noise Type', key: 'noiseType' },
    { title: 'Noise Severity', key: 'noiseSeverity' },
    { title: 'Audio Quality', key: 'audioQuality' },
    { title: 'Overlap', key: 'overlap' },
    { title: 'Silence', key: 'silence' },
    { title: 'Confidence', key: 'confidence' },
    { title: 'Model', key: 'modelUsed', sortable: false },
    { title: 'Play', key: 'play', sortable: false },
];

const batchHeaders = [
    { title: 'Filename', key: 'originalFilename' },
    { title: 'Uploaded', key: 'createdAt' },
    { title: 'Files', key: 'fileCount' },
    { title: 'Missing', key: 'missingFiles' },
    { title: 'Unmatched', key: 'unmatchedFiles' },
    { title: '', key: 'actions', sortable: false },
];

const tableItems = computed(() =>
    results.value.map((r) => ({
        name: r.name,
        status: r.status,
        error: r.error,
        tone: r.prediction?.emotional_tone ?? '',
        intensity: r.prediction?.emotional_intensity ?? '',
        noise: r.prediction?.background_noise_present ?? '',
        noiseType: r.prediction?.background_noise_type ?? '',
        noiseSeverity: r.prediction?.background_noise_severity ?? '',
        audioQuality: r.prediction?.audio_quality ?? '',
        overlap: r.prediction?.speaker_overlap_present ?? '',
        silence: r.prediction?.long_silence_present ?? '',
        confidence: r.prediction?.confidence ?? '',
        modelUsed: r.modelUsed,
        wasFallback: r.wasFallback,
    })),
);

function stopPolling() {
    if (pollHandle) {
        clearInterval(pollHandle);
        pollHandle = null;
    }
    isPolling.value = false;
}

async function pollStatus(batchId: string) {
    const response = await fetch(route('dashboard.analyze.status', { batchId }));
    const data = await response.json();
    results.value = data.results;
    loadUsage();

    if (results.value.every((r) => r.status === 'completed' || r.status === 'failed')) {
        stopPolling();
        archiveFile.value = null;
    }
}

async function loadUsage() {
    const { data } = await http.get<{ usage: UsageEntry[] }>(route('dashboard.usage'));
    geminiUsage.value = data.usage;
}

function startPolling(batchId: string) {
    stopPolling();
    currentBatchId.value = batchId;
    isPolling.value = true;
    pollStatus(batchId);
    pollHandle = setInterval(() => pollStatus(batchId), 1500);
}

async function loadBatches() {
    const { data } = await http.get<{ batches: BatchSummary[] }>(route('dashboard.batches'));
    batches.value = data.batches;
}

function viewBatch(batchId: string) {
    if (uploading.value || isPolling.value) return;

    audioEl?.pause();
    playingFile.value = null;
    missingFiles.value = [];
    unmatchedFiles.value = [];
    startPolling(batchId);
}

onMounted(() => {
    loadBatches();
    loadUsage();
});

onUnmounted(() => {
    stopPolling();
    audioEl?.pause();
});

const playingFile = ref<string | null>(null);
let audioEl: HTMLAudioElement | null = null;

function togglePlay(name: string) {
    if (playingFile.value === name) {
        audioEl?.pause();
        playingFile.value = null;
        return;
    }

    audioEl?.pause();

    if (!currentBatchId.value) return;

    const url = route('dashboard.analyze.audio', { batchId: currentBatchId.value, filename: name });
    audioEl = new Audio(url);
    audioEl.addEventListener('ended', () => {
        if (playingFile.value === name) playingFile.value = null;
    });
    audioEl.play();
    playingFile.value = name;
}

async function submit() {
    if (!archiveFile.value) return;

    fileErrors.value = [];
    missingFiles.value = [];
    unmatchedFiles.value = [];
    processing.value = true;
    uploading.value = true;
    stopPolling();
    results.value = [];

    const formData = new FormData();
    formData.append('archive', archiveFile.value);

    try {
        const { data } = await http.post<{ batchId: string; missingFiles: string[]; unmatchedFiles: string[] }>(
            route('dashboard.analyze'),
            formData,
            { headers: { 'Content-Type': 'multipart/form-data' } },
        );
        missingFiles.value = data.missingFiles;
        unmatchedFiles.value = data.unmatchedFiles;
        startPolling(data.batchId);
        loadBatches();
    } catch (e) {
        if (axios.isAxiosError(e) && e.response?.status === 422) {
            fileErrors.value = Object.values(e.response.data.errors ?? { archive: [e.response.data.message] }).flat() as string[];
        } else {
            fileErrors.value = ['Upload failed. Please try again.'];
        }
    } finally {
        processing.value = false;
        uploading.value = false;
    }
}

function downloadResults() {
    const blob = new Blob([JSON.stringify(results.value, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'voice_analysis_results.json';
    a.click();
    URL.revokeObjectURL(url);
}

function logout() {
    router.post(route('logout'));
}
</script>
