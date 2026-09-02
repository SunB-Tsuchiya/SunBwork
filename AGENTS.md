# AGENTS.md - Codex Rules for SunBWork

This file contains workspace-specific instructions for Codex. Read it before making changes in this repository.

## First Principles

- Before changing code, inspect the related implementation and follow existing patterns.
- Protect user and Claude changes. Do not revert or overwrite unrelated work.
- Keep edits scoped to the requested task.
- If requirements are ambiguous or risky, ask one concise question before editing.
- For non-trivial work, summarize the approach, affected files, and verification plan before implementation.
- `CLAUDE.md` is maintained for Claude. Do not edit it unless the user explicitly asks.

## Project Overview

SunBWork is an internal member management site for a printing/typesetting company. Core features include project job assignment, progress management, diaries, workload analysis, messages, files, and calendars.

- Stack: Laravel 11 / Vue 3 / Inertia.js / Vite / Tailwind CSS
- Production: Sakura rental server, `https://sun-brain.co.jp/members`
- App name: `SB`
- Database: MySQL
- Auth: Sanctum + cookie SPA auth
- Realtime: Laravel Echo / WebSocket

Roles:

- `SuperAdmin`, `Admin`: system and user management
- `Coordinator`: project owner and job assignment
- `Leader`: department lead, reads projects and manages department users
- `Clerk`: office/accounting, similar authority to Coordinator
- `User`: worker, receives jobs, enters diaries and work time

Important directories:

```text
app/Http/Controllers/   Admin/ Coordinator/ Leader/ ProjectJobs/ User/ Bot/ Chat/ Diaries/
app/Models/
resources/js/
  Pages/                Inertia pages, organized by role
  Components/           Project-specific components, uppercase names
  components/ui/        shadcn/ui-style components, lowercase names
  layouts/AppLayout.vue Main shared layout
routes/web.php          SPA routes; do not put these in api.php
z_instructions/         Detailed project docs; ignore backups/ unless needed
```

## Required References

- Before creating a new page or component, read `z_instructions/CONSOLIDATED_01_layout_and_ui.md`.
- Before date, time, or calendar work, read the `UTC / JST Mixed Rules` section below and usually `z_instructions/CONSOLIDATED_05_calendar_and_jobbox.md`.
- For Sakura deployment, follow `z_instructions/DEPLOY_SAKURA.md`.
- Use detailed docs in `z_instructions/CONSOLIDATED_*.md`; skip `z_instructions/backups/` unless historical context is required.

Reference map:

| File | Topic |
| --- | --- |
| `z_instructions/CONSOLIDATED_01_layout_and_ui.md` | UI and layout |
| `z_instructions/CONSOLIDATED_02_security_and_sessions.md` | Security and sessions |
| `z_instructions/CONSOLIDATED_03_auth_and_cors.md` | Auth and CORS |
| `z_instructions/CONSOLIDATED_04_ai_and_chat.md` | AI and chat |
| `z_instructions/CONSOLIDATED_05_calendar_and_jobbox.md` | Calendar and JobBox |
| `z_instructions/CONSOLIDATED_06_messages_and_files.md` | Messages and files |
| `z_instructions/CONSOLIDATED_07_workload_and_handover.md` | Workload analysis |
| `z_instructions/CONSOLIDATED_08_attachment.md` | Attachments |
| `z_instructions/CONSOLIDATED_09_domain_rules.md` | Domain rules |
| `z_instructions/DEPLOY_SAKURA.md` | Sakura deployment |

## Commands and Environment

- Run `npm run build` from `/home/tchirosb/SunBWork` after changing Vue or JS files.
- Run Artisan inside the container:

```bash
docker compose exec laravel bash -lc "php artisan ..."
```

- After config changes, clear Laravel caches:

```bash
docker compose exec laravel bash -lc "php artisan config:clear && php artisan cache:clear"
```

- If `public/build` has EACCES errors, ask before using sudo, then run:

```bash
sudo chown -R $USER:$USER public/build/
sudo chmod -R 755 public/build/assets
```

## Git Pull / Local Sync

When the user asks to pull, sync, align with remote, or update to latest, do not trust `git status` before fetch. Use this order:

```bash
git fetch origin
git log --oneline HEAD..origin/main
git pull origin main
docker compose exec laravel bash -lc "composer install --no-interaction"
docker compose exec laravel bash -lc "php artisan migrate --force"
docker compose exec laravel bash -lc "php artisan config:clear && php artisan cache:clear && php artisan route:clear && php artisan view:clear"
npm install
npm run build
```

Do not run `npm run build` before pulling. Building old sources and then pushing can overwrite the latest Sakura build.

## Sakura Production Rules

- Production `.env`: `APP_URL` and `ASSET_URL` are `https://sun-brain.co.jp/members`; `VITE_APP_BASE_PATH=/members`.
- Local `.env`: `APP_URL=http://localhost:8000`; `VITE_APP_BASE_PATH=` is empty.
- Navigation must use `route()`, not hard-coded paths, because production runs under `/members`.
- CSRF token must come from the meta tag. Sakura may not issue an `XSRF-TOKEN` cookie.
- Do not forget production migrations.
- Sakura `sed -i` is BSD-style and needs `-i ''`.
- SSH details are in `z_instructions/DEPLOY_SAKURA.md`.
- Before running SSH commands, show the exact command to the user and get confirmation.
- In production one-liners, `php artisan migrate` and `php artisan db:seed` must use `--force`.

Examples:

```js
// Bad
window.location.href = `/events/${id}`;

// Good
router.get(route('events.show', { event: id }));
```

```js
// Bad
document.cookie.match(/XSRF-TOKEN=([^;]+)/);

// Good
document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
```

## UI and Layout Quick Reference

`AppLayout` already provides `py-12 > max-w-7xl`. Page components should put their card/content directly into the default slot.

```vue
<AppLayout title="Page title">
  <template #header><h2>Heading</h2></template>
  <div class="rounded bg-white p-6 shadow">
    <!-- content -->
  </div>
</AppLayout>
```

Avoid duplicate layout wrappers such as:

- page-level `<main>`
- duplicate `py-2` or `py-12`
- duplicate `mx-auto max-w-7xl`

`AppLayout` slots:

- `#header`
- `#headerExtras`
- `#tabs`
- default slot

`AppLayout` provides:

- `authUser`: logged-in user
- `user`: page `user` prop

`ToastUnified` is already global inside `AppLayout`; do not duplicate it in pages.

Role colors:

- SuperAdmin: yellow
- Admin: red
- Leader: orange
- Coordinator: green
- Clerk: purple
- User: blue

When using Ziggy `route()`, pass named parameters as an object:

```js
route('coordinator.project_jobs.show', { projectJob: job.id });
```

## UTC / JST Mixed Rules

This project runs in JST, `Asia/Tokyo`:

```php
// config/app.php
'timezone' => env('APP_TIMEZONE', 'Asia/Tokyo')
```

Date, time, and calendar work must account for UTC/JST conversion bugs.

### Eloquent Date Casts

For date-only columns, use `date:Y-m-d`, not plain `date`.

```php
// Bad: serializes through UTC and can shift one day in Vue
protected function casts(): array
{
    return ['held_at' => 'date'];
}

// Good
protected function casts(): array
{
    return ['held_at' => 'date:Y-m-d'];
}
```

### Vue Date Handling

Do not use `toISOString()` for local JST dates.

```js
// Bad: UTC date may differ from JST date
const today = new Date().toISOString().slice(0, 10);

// Good: local YYYY-MM-DD
const today = new Date().toLocaleDateString('sv-SE');
```

### Events Table

`events.starts_at` and `events.ends_at` have mixed storage formats:

| Event type | Stored format | Rule |
| --- | --- | --- |
| Normal internal/outside events | JST string | Can be parsed as JST |
| Proof job events, `job_type='proof'` | UTC string | Direct parse shifts 9 hours |

Rules:

- Do not directly use `Carbon::parse($event->starts_at)` for event calculations.
- Use `app/Http/Controllers/Concerns/CalculatesEventTime.php`.
- In controllers that can use the trait, declare `use CalculatesEventTime;`.
- Eager load `projectJobAssignment:id,job_type` before resolving event time.
- Use `resolveJstCarbon($event, 'starts_at')` and `resolveJstCarbon($event, 'ends_at')`.
- For lunch break calculation, use `computeLunchMinutes($evStart, $evEnd, $userId, $cache)`.

```php
// Bad
$start = Carbon::parse($event->starts_at);

// Good
$event->load('projectJobAssignment:id,job_type');
$start = $this->resolveJstCarbon($event, 'starts_at');
```

## Data and Domain Rules

`project_job_assignments` is the single source table for both JobBox and MyJobBox.

- `sender_id = user_id`: self-assignment, MyJobBox
- `sender_id != user_id` or `NULL`: Coordinator assignment, JobBox
- `desired_start_date` does not exist; use `desired_end_date` for period filters.

Continuation job foreign keys:

- `source_assignment_id`: chain
- `supersedes_assignment_id`: replacement of requested job

Do not confuse these two.

ProjectJob completion route:

- Use `coordinator.project_jobs.complete`
- Do not use `project_jobs.complete`

`project_jobs.schedule` does not exist in Sakura production. Before `update()`, remove it from data with `Arr::pull($data, 'schedule')`.

## CSV Import Rules

All CSV imports must support Shift-JIS, CRLF, and BOM.

Use `app/Http/Controllers/Concerns/NormalizesCsvEncoding.php` and declare `use NormalizesCsvEncoding;` in controllers.

Methods:

| Method | Use |
| --- | --- |
| `$this->normalizeCsvStoredFile($storagePath)` | Normalize a stored file in place |
| `$this->normalizeCsvToTemp($file)` | Return normalized temporary file path; `@unlink` after use |
| `$this->normalizeCsvContent($raw)` | Return normalized UTF-8 string |

Stored-file flow:

```php
$path = $file->store('temp_csv', 'local');
$this->normalizeCsvStoredFile($path);
$handle = fopen(Storage::path($path), 'r');
```

Uploaded-file temporary flow:

```php
$tmpPath = $this->normalizeCsvToTemp($file);
$handle = fopen($tmpPath, 'r');
// ...
fclose($handle);
@unlink($tmpPath);
```

## Sales Data Confidentiality

- Production sales data uses the dedicated `sales` database connection.
- Never inspect production sales records through SSH, SQL, Artisan Tinker,
  database clients, dumps, logs, or ad-hoc scripts.
- Do not copy production sales data into the local environment.
- Use only synthetic fixtures and `z_instructions/sanbrain_meisai_sample.xlsx`
  for development and review.
- Schema/migration inspection is allowed from repository files. Do not query
  production schema if doing so risks returning record data.
- Never print sales DB credentials or backup contents.

## Large Work Protocol

For large new features or repairs involving multiple phases or roughly five or more changed files, create these files under `z_instructions/` before implementation and ask for user confirmation:

| File pattern | Purpose |
| --- | --- |
| `{PREFIX}_PLAN{N}.md` | Detailed spec, DB design, phases, affected files |
| `{PREFIX}_MANAGER{N}.md` | Progress tracking, workflow, checklist, work log |
| `{PREFIX}{N}_PROMPT.md` | Restart prompt and design summary |

Examples:

- `GHOST_PLAN1.md`
- `GHOST_MANAGER1.md`
- `GHOST1_PROMPT.md`

When work of this size is complete:

- Add a change log entry to `ChangelogSeeder` with `updateOrCreate(['version' => ...], $entry)`.
- Run `docker compose exec laravel bash -lc "php artisan db:seed --class=ChangelogSeeder --force"` when appropriate.
- Update the relevant `CONSOLIDATED_*.md`.
- Move completed PLAN/MANAGER/PROMPT files to `z_instructions/archived/`.
