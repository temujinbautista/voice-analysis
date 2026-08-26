# Deployment (Render, free tier)

This deploys the app as a single Docker web service on Render's free tier. Since the free tier has no persistent disk and no background-worker instance type, this setup uses a synchronous queue (each upload processes inline during the request) and accepts that the SQLite database and any uploaded audio reset whenever the container restarts or redeploys. See docs/TECHNICAL_MEMO.md for why.

## One-time setup

1. Go to [render.com](https://render.com) and sign up / log in (GitHub login is easiest since the repo is already on GitHub).
2. Click **New +** → **Blueprint**.
3. Connect the `temujinbautista/voice-analysis` GitHub repo. Render will detect `render.yaml` at the repo root and propose the service defined in it.
4. Before clicking deploy, Render will prompt for the env vars marked `sync: false` in `render.yaml` — fill in:
   - `APP_KEY` — generate one locally first: `php artisan key:generate --show`, then paste the full `base64:...` value.
   - `APP_URL` — leave blank for now; Render assigns the service a URL like `https://auto-ace.onrender.com` once it's created. Come back after the first deploy, set this to that exact URL, and redeploy (Manual Deploy → Deploy latest commit) so Laravel generates correct absolute URLs/CSRF cookies.
   - `GEMINI_API_KEY` — your Gemini API key.
   - `ADMIN_SEED_EMAIL` / `ADMIN_SEED_PASSWORD` — see below; this is what actually lets you log in on a fresh/reset database.
5. Click **Apply**/**Deploy**. The first build takes a few minutes (installs PHP extensions, ffmpeg, Composer deps, builds the Vite assets).

## After the first deploy

- Visit the assigned URL and log in with whatever you set `ADMIN_SEED_EMAIL`/`ADMIN_SEED_PASSWORD` to.
- If you changed `APP_URL` after setup, trigger a redeploy so it takes effect.

## What resets on restart/redeploy

The free tier has no persistent disk, so a new container starts from a clean image every time. The entrypoint (`docker/entrypoint.sh`) re-creates the SQLite file and re-runs migrations **and seeders** on every boot, which means:

- Batch history and analysis results are wiped whenever the service restarts (including Render's automatic spin-down after ~15 minutes of no traffic, and every redeploy).
- Uploaded audio files are wiped the same way.
- **The one login account is not lost**, because `database/seeders/DatabaseSeeder.php` re-creates (or updates) a user matching `ADMIN_SEED_EMAIL`/`ADMIN_SEED_PASSWORD` every time the container boots. Since registration itself is locked to that same email (see `RegisteredUserController::ALLOWED_EMAIL`), without this seeding step a wiped database would be a genuine lockout — there'd be no way to create the first account at all, since registering requires already being logged in. To change the password, just update `ADMIN_SEED_PASSWORD` in Render's Environment tab and redeploy.

Everything else being wiped is fine for "try it out in one sitting" testing, not for anything meant to persist. If that becomes a real requirement, the fix is a free external Postgres (Neon/Supabase) for the DB and an external object store (Cloudflare R2) for uploaded audio — deliberately not done here to keep this deploy simple, see docs/TECHNICAL_MEMO.md.

## Known limitations of this deploy specifically

- **Cold starts**: after 15 minutes idle, Render spins the container down; the next request takes ~30-60s while it spins back up.
- **Synchronous processing**: `QUEUE_CONNECTION=sync` means each file in a batch is analyzed one at a time during the upload request itself (no background worker on the free tier). A large batch will make the request take a while — this is a deliberate tradeoff, not a bug.
- **No custom domain / TLS is Render's**, which is fine for a trial/demo but worth knowing.
