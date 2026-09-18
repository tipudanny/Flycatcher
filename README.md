# Flycatcher
webhooks fly in, you catch and examine them


A self-hosted webhook inspection tool — capture, inspect, live-tail, and replay any HTTP request.

**Stack:** Laravel 11 · Vue 3 + Vite · MySQL · Chrome extension (MV3)

---

## Features

### Capture & inspect
- **Universal capture endpoint** — `http://localhost:8000/event/hooks/{token}` accepts any HTTP method, arbitrary sub-paths (`/{token}/github/push`), and query strings.
- **Pure observer** — never parses or validates payloads (JSON, form-data, binary, anything). Body size-capped at 10 MB (oversized → `413`).
- **Two-pane inspector** — request list + full detail: method, path, timestamp, client IP, content-type, size. Collapses to a single pane on mobile (list ⇄ detail, with a back button).
- **Rich detail** — headers table, parsed query params, and body shown **pretty** (auto-formatted JSON) and **raw**. Click-to-copy on the received timestamp, client IP, and body.
- **List pagination** — "Load more" loads older requests beyond the first page instead of only ever showing the newest batch.

### Replay
- **Resend any captured request** to a real URL — same method, headers, and body, exactly as originally received. Useful for retesting a fix without waiting for the sender to fire the webhook again.
- **SSRF-safe by design**: only `http(s)` targets; the target host is DNS-resolved and rejected unless *every* resolved IP is publicly routable (blocks loopback, private ranges, and link-local — including the `169.254.169.254` cloud metadata endpoint); the connection is pinned to the validated IP (via `CURLOPT_RESOLVE`) so DNS can't be swapped between the check and the request; redirects are never followed; the response body is streamed and capped (default 64 KB) instead of buffered in full.
- **Its own rate limit**, separate from the general API limit, since each replay is an outbound request made on the caller's behalf.
- The result (status, duration, response body, truncation flag) is shown inline — nothing is persisted as a new capture.

### Real-time live-tail
- New requests appear **automatically** via lightweight polling (`useLiveTail` composable, ~4s interval) — no external service required, works out of the box on any self-hosted install. A green **Live** indicator shows it's active.
- MySQL is always the source of truth; live-tail just re-fetches and merges anything new.

### Guest mode (no signup)
- **Try as guest** mints a webhook URL instantly, tied to a browser cookie.
- **One URL per browser** — a fresh URL requires a different browser.
- **200-request cap per guest URL** — #201 returns `429` (not stored); captured data stays viewable. Usage bar + limit banner included.
- **2-day auto-deletion** — guest URLs and data are hard-deleted after 2 days (`endpoints:expire`, hourly).

### Accounts & URLs
- **Email/password auth** (Sanctum bearer tokens).
- **Claim-on-register** — guest URLs in the browser transfer to the new account and stop expiring.
- **Custom vanity URLs** — registered users pick their own slug (`/event/hooks/my-github-hook`), unique and **renamable** (old URL 404s immediately).
- **Dashboard** — create, list, open, and clear endpoints with counts and last-activity.

### Plans & limits — editable live from the admin panel
| | Free | Pro | Team |
|---|---|---|---|
| Max URLs | 3 | 25 | ∞ |
| Requests / URL | 500 | 10,000 | ∞ |
| Retention | 7 days | 30 days | forever |
| Custom responses | no | yes | yes |

The table above is just the shipped defaults (`config/plans.php`). An admin can edit any tier's label, limits, or custom-response flag from **Admin → Plans → Edit** — the change is stored as a DB override (`App\Support\Plans`, backed by the existing `settings` key-value store) and takes effect **immediately** for every user on that plan, no deploy or cache clear. Limits are enforced on creation (max URLs), on capture (per-URL request limit), and on retention (old requests pruned per plan, freeing quota).

### Admin dashboard / backoffice
- **Overview stats** — users, suspended, endpoints, guest endpoints, requests total/today.
- **User management** — change plan, suspend/activate. A suspended user is locked out three ways: can't log in, their endpoints stop accepting new webhooks (silent 404, same as a nonexistent endpoint), and they lose access to their own endpoint data immediately — even with a bearer token issued before the suspension.
- **Full endpoint management, for *any* endpoint** — admins can view, edit (label/slug), and delete every endpoint in the system, not just their own. This reuses the same owner-scoped routes everyone else uses (an admin bypass in `Endpoint::canBeViewedBy()`), so it's the exact same tested code path, not a parallel admin-only implementation.
- **Editable plan tiers** — see "Plans & limits" above.
- **Paginated tables** — Users and Endpoints tables are server-paginated with a selectable page size (10/25/50/100) and a "Showing X–Y of Z" summary, not just a raw list.
- **Search-as-you-type** on both tables.
- **Access control** — admin-only API (`401`/`403`), router guard, admin-only nav link.
- **First admin** — `php artisan user:make-admin {email}` (with `--revoke`).
- **Extension settings panel** — enable/disable, notifications, badge, poll interval, app URL.

### Chrome extension (Manifest V3)
- **Desktop notifications** + **badge counter** for new requests (background polling).
- **Side-panel inspector** — endpoints → requests → full detail (headers, query, pretty/raw body), live-refreshing, dark + light.
- **Create URLs** from the panel with **+ New**.
- **Zero-setup guest support** — reads the `guest_session_id` cookie to auto-discover the browser's guest URLs; registered users sign in with email/password.
- **Admin-controlled config** — behaviour driven by the backoffice settings. See [extension/README.md](extension/README.md).

### Design
- Self-hosted **Inter** (UI) + **JetBrains Mono** (code/data) fonts via `@fontsource` — no external font requests.
- A small shared component system (buttons, cards, badges, inputs, colored icon chips) rather than one-off utility classes per page.
- Split-screen auth pages, a two-column request-detail layout (body/headers as the main content, metadata/replay/query-params as a sidebar), and a responsive two-pane inspector that collapses cleanly on mobile.
- Dark/light theme, persisted, defaulting to OS preference.

### Security
- **Tenant isolation at the query layer** — every read scoped through an authorized endpoint (IDOR-guarded); 404 (not 403) to avoid leaking token existence.
- **High-entropy tokens** (128-bit); internal UUIDs never appear in URLs.
- **Per-token rate limiting** on ingestion; CSRF-exempt capture routes.
- **SSRF-hardened replay** — see "Replay" above.
- **Suspended-account enforcement at the data layer**, not just at login — see "Admin dashboard" above.

---

## Quick start

### 1. Clone & copy env files

```bash
cp backend/.env.example backend/.env
cp frontend/.env.example frontend/.env
```

### 2. Configure `backend/.env`

Point `DB_*` at a local MySQL instance and set `APP_KEY` (`php artisan key:generate`). That's it for live-tail — it's polling-based and needs no external service. Firebase env vars are still read if present (`FIREBASE_CREDENTIALS`, `FIREBASE_DATABASE_URL`) but are entirely optional legacy hooks; capture and live-tail both work fully without them.

### 3. Install & run

Either with Docker:

```bash
docker-compose up -d
docker-compose exec php php artisan key:generate
docker-compose exec php php artisan migrate
```

...or directly (what local development actually runs day to day):

```bash
# backend
cd backend && composer install && php artisan migrate
php artisan serve --port=8000

# frontend (separate terminal)
cd frontend && npm install && npm run dev
```

**URLs:**
| Service | URL |
|---------|-----|
| Vue dashboard | http://localhost:5173 |
| Laravel API | http://localhost:8000 |
| Capture endpoint | `http://localhost:8000/event/hooks/<token>` |

---

## Architecture

```
browser / 3rd-party service
       │
       ├── POST /event/hooks/<token>  ──▶  IngestController (no auth)
       │                              └── persist to MySQL (source of truth)
       │
       └── /api/*           ──▶  App API (Sanctum auth)
                                     ├── scoped reads: endpoint → requests
                                     ├── Replay: SSRF-checked outbound request
                                     └── MySQL

Vue frontend
  ├── Pinia stores (auth, endpoints, theme)
  ├── useLiveTail composable          ──▶  polls the API on an interval
  └── axios API client                ──▶  Laravel API
```

**Key design rules:**
- Isolation is enforced at the query layer, never in the UI. Every request read is scoped through an authorized `endpoint_id`.
- Tokens (in URLs) are high-entropy (128 bits). Internal UUIDs are never exposed in URLs.
- Ingestion never parses the body — it's a pure observer. Size-capped at 10 MB.
- Plan limits and admin-editable settings are read through `App\Support\Plans` / `App\Models\Setting`, never `config()` directly, so edits from the admin panel apply immediately with no deploy.

---

## Project structure

```
webhook inspector/
├── backend/                Laravel 11 API
│   ├── app/
│   │   ├── Console/Commands/
│   │   │   ├── ExpireEndpoints.php       guest deletion + per-plan request pruning
│   │   │   └── MakeAdmin.php             user:make-admin {email}
│   │   ├── Http/
│   │   │   ├── Controllers/
│   │   │   │   ├── Auth/AuthController.php   register, login, logout, claim
│   │   │   │   ├── IngestController.php      capture endpoint (no auth)
│   │   │   │   ├── EndpointController.php    CRUD for webhook URLs + limits
│   │   │   │   ├── RequestController.php     tenant-scoped reads + Replay
│   │   │   │   ├── AdminController.php       stats, users, endpoints, plans, settings
│   │   │   │   └── ExtensionController.php   public extension config
│   │   │   └── Middleware/EnsureAdmin.php
│   │   ├── Models/
│   │   │   ├── User.php                  plan/status/is_admin + plan helpers
│   │   │   ├── Endpoint.php              token gen, auth (incl. admin bypass) + limit helpers
│   │   │   ├── WebhookRequest.php        ULID PK, raw body, parsed helpers
│   │   │   └── Setting.php               key/value app settings (also backs plan overrides)
│   │   ├── Services/
│   │   │   ├── FirebaseService.php       optional legacy live-tail push (inert if unconfigured)
│   │   │   └── ReplayService.php         SSRF-safe outbound replay
│   │   └── Support/Plans.php             merges DB plan overrides over config/plans.php
│   ├── config/{plans.php, app.php, firebase.php}
│   ├── database/migrations/
│   └── routes/{api.php, web.php, console.php}
│
├── frontend/               Vue 3 + Vite SPA
│   └── src/
│       ├── composables/useLiveTail.js    polling-based live-tail
│       ├── stores/{auth, endpoints, theme}.js
│       ├── api/{client, endpoints, admin}.js
│       ├── pages/
│       │   ├── LoginPage.vue / RegisterPage.vue
│       │   ├── DashboardPage.vue         endpoint list + create form
│       │   ├── EndpointDetailPage.vue    two-pane inspector (auth)
│       │   ├── InspectPage.vue           public guest view
│       │   └── AdminPage.vue             admin dashboard / backoffice
│       └── components/{AppHeader, RequestDetail, ThemeToggle}.vue
│
├── extension/              Chrome extension (MV3)
│   ├── manifest.json
│   ├── background.js       service worker: poll, notify, badge
│   ├── sidepanel.{html,js} docked live inspector + create URL
│   ├── options.{html,js}   API base URL + account sign-in
│   ├── shared.js           API helpers (token + guest-session)
│   └── icons/
│
├── docker/{nginx, php}
└── docker-compose.yml      MySQL + PHP-FPM + Nginx + Vite
```

---

## Development workflow

```bash
# Watch Laravel logs
tail -f backend/storage/logs/laravel.log

# Run a migration
cd backend && php artisan migrate

# Rebuild the frontend for production
cd frontend && npm run build
```

If using Docker instead, the equivalent commands are `docker-compose exec php ...` / `docker-compose logs -f php`.

---

## Deployment

Production runs on Namecheap shared hosting (`flycatcher.site`), where shell access is disabled but SFTP/key-based SSH works. There's no CI pipeline — deploys are a manual two-part upload:

1. **Backend**: upload only the changed PHP files to their matching path under the remote Laravel root. `vendor/` only needs re-syncing if `composer.json`/`composer.lock` changed.
2. **Frontend**: `npm run build` locally, then upload `dist/` contents into the remote `public/` directory (alongside Laravel's `index.php` and `.htaccess`, which live there too).

No database migration or cache-clear step is needed for most deploys — the server has no `bootstrap/cache/config.php` or route cache, so PHP source changes take effect on the next request.

---

## Status

Implemented:

- [x] Rate limiting on ingestion, replay, and general API routes
- [x] Request retention (`endpoints:expire` — guest deletion + per-plan pruning, scheduled hourly)
- [x] Plan-based limits (max URLs, requests per URL, retention window, custom-response gating) — **editable live from the admin panel**
- [x] Guest mode (one URL/browser, 200-request cap, 2-day retention)
- [x] Custom vanity URLs for registered users
- [x] Per-endpoint custom response (status code, body, headers) — gated by plan, applied at ingest
- [x] Replay — resend a captured request to a real URL, SSRF-safe
- [x] Admin dashboard / backoffice (users, endpoints, plans, stats, settings) with full CRUD on any endpoint, paginated tables, and suspend enforcement at the data layer
- [x] Chrome extension (notifications, badge, side-panel inspector, create URL)
- [x] Dark / light theme, redesigned UI

Still open:

- [ ] Firebase security rules — only relevant if you opt back into `FIREBASE_CREDENTIALS`/`FIREBASE_DATABASE_URL`; unused by default now that live-tail is polling-based
- [ ] Extension scheduler note: run `php artisan schedule:work` (or a cron `schedule:run`) for retention to fire

> **Note:** the schedule for `endpoints:expire` only runs if Laravel's scheduler is active —
> locally `php artisan schedule:work`, in production the standard `* * * * * php artisan schedule:run` cron.
