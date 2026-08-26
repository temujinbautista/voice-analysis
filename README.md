# AutoAce Voice Tone / Background Noise Dashboard

A Laravel + Vue 3 (Inertia) + Vuetify dashboard that classifies call-center audio for emotional tone/intensity, background noise, audio quality, speaker overlap, and long silence — built for the AutoAce AI technical trial. See [docs/TECHNICAL_MEMO.md](docs/TECHNICAL_MEMO.md) for the full write-up: approach, validation results/confusion matrix, cost analysis, latency analysis, and known limitations/next steps.

## Requirements

- PHP 8.2+ with Composer
- Node.js 18+ with npm
- SQLite (bundled with PHP's `pdo_sqlite` extension — no separate DB server needed)
- [ffmpeg](https://www.gyan.dev/ffmpeg/builds/) on `PATH`, or set `FFMPEG_PATH` to its absolute location
- A Google Gemini API key ([aistudio.google.com/apikey](https://aistudio.google.com/apikey)) — the free tier is enough to run this

## Setup

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate
```

Edit `.env` and set:

```
GEMINI_API_KEY=your-key-here
```

(`GEMINI_MODEL`, `GEMINI_FALLBACK_MODEL`, and `FFMPEG_PATH` already have sensible defaults in `.env.example` — only override `FFMPEG_PATH` if ffmpeg isn't on your system `PATH`.)

```bash
touch database/database.sqlite   # on Windows: New-Item database/database.sqlite
php artisan migrate
```

## Running

Three processes need to run together:

```bash
php artisan serve          # app server
php artisan queue:work --tries=1   # processes voice analysis jobs
npm run dev                 # Vite dev server (frontend)
```

**Important:** `queue:work` boots once and keeps running — it does **not** pick up code or `.env` changes automatically. Restart it after editing anything under `app/Jobs`, `app/Services`, or `.env`.

Visit `http://localhost:8000` (or whatever `APP_URL`/port you're using), register/log in, then use the dashboard to upload either:
- a single audio file (`.wav`, `.mp3`, `.ogg`, `.flac`, `.aac`), or
- a `.zip` evaluation batch containing audio files plus one `labels.csv` manifest at its root, with columns `name` (filename) and optionally `result_json` (a JSON string of the expected/ground-truth result, used to show pass/fail against known labels).

## Tests

```bash
php artisan test
```
