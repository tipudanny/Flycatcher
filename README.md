# Flycatcher
webhooks fly in, you catch and examine them


A self-hosted webhook inspection tool — capture, inspect, and live-tail any HTTP request.

**Stack:** Laravel 11 · Vue 3 + Vite · MySQL · Firebase Realtime DB (live-tail only) · Chrome extension (MV3)

---

## Features

### Capture & inspect
- **Universal capture endpoint** — `http://localhost:8000/event/hooks/{token}` accepts any HTTP method, arbitrary sub-paths (`/{token}/github/push`), and query strings.
- **Pure observer** — never parses or validates payloads (JSON, form-data, binary, anything). Body size-capped at 10 MB (oversized → `413`).
- **Two-pane inspector** — request list + full detail: method, path, timestamp, client IP, content-type, size.
- **Rich detail** — headers table, parsed query params, and body shown **pretty** (auto-formatted JSON) and **raw**, with copy.

### Real-time live-tail
- New requests appear **instantly** with no refresh, via Firebase Realtime Database; a green **Live** indicator shows the connection.
- **Degrades gracefully** — without Firebase credentials, capture still works; only the auto-refresh is disabled. MySQL is always the source of truth.

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

### Plans & limits (`config/plans.php`)
| | Free | Pro | Team |
|---|---|---|---|
| Max URLs | 3 | 25 | ∞ |
| Requests / URL | 500 | 10,000 | ∞ |
| Retention | 7 days | 30 days | forever |
| Custom responses | no | yes | yes |

Enforced on creation (max URLs), on capture (per-URL request limit), and on retention (old requests pruned per plan, freeing quota).

### Admin dashboard / backoffice
- **Overview stats** — users, suspended, endpoints, guest endpoints, requests total/today.
- **User management** — change plan, suspend/activate (suspended users can't log in).
- **All-endpoints view** across users; both tables search-as-you-type.
- **Access control** — admin-only API (`401`/`403`), router guard, admin-only nav link.
- **First admin** — `php artisan user:make-admin {email}` (with `--revoke`).
- **Extension settings panel** — enable/disable, notifications, badge, poll interval, app URL.

### Chrome extension (Manifest V3)
- **Desktop notifications** + **badge counter** for new requests (background polling).
- **Side-panel inspector** — endpoints → requests → full detail (headers, query, pretty/raw body), live-refreshing, dark + light.
- **Create URLs** from the panel with **+ New**.
- **Zero-setup guest support** — reads the `guest_session_id` cookie to auto-discover the browser's guest URLs; registered users sign in with email/password.
- **Admin-controlled config** — behaviour driven by the backoffice settings. See [extension/README.md](extension/README.md).

### Theming & branding
- **Dark/light theme** toggle, persisted, defaulting to OS preference — across the app and extension.
- **Flycatcher branding** — custom swallow logo as app mark, favicon, and extension icon.

### Security
- **Tenant isolation at the query layer** — every read scoped through an authorized endpoint (IDOR-guarded); 404 (not 403) to avoid leaking token existence.
- **High-entropy tokens** (128-bit); internal UUIDs never appear in URLs.
- **Per-token rate limiting** on ingestion; CSRF-exempt capture routes.

---

## Quick start

### 1. Clone & copy env files

```bash
cp backend/.env.example backend/.env
cp frontend/.env.example frontend/.env
```

### 2. Set up Firebase

1. Create a project at [console.firebase.google.com](https://console.firebase.google.com)
2. Enable **Realtime Database** (Start in test mode → change rules later)
3. **Service account** (for Laravel backend):
   - Project Settings → Service Accounts → Generate new private key
   - Save the JSON as `backend/storage/firebase-credentials.json`
   - Add this path to `.gitignore` — **never commit credentials**
4. **Web app config** (for Vue frontend):
   - Project Settings → Your apps → Add web app (or use existing)
   - Copy the config values into `frontend/.env`
5. In `backend/.env`, set:
   ```
   FIREBASE_DATABASE_URL=https://your-project-default-rtdb.firebaseio.com
   ```

### 3. Configure `backend/.env`

```
APP_KEY=         ← fill in after step 4
DB_HOST=db
DB_DATABASE=webhook_inspector
DB_USERNAME=webhook
DB_PASSWORD=secret
FIREBASE_CREDENTIALS=storage/firebase-credentials.json
FIREBASE_DATABASE_URL=https://your-project-default-rtdb.firebaseio.com
```

### 4. Start everything

```bash
docker-compose up -d
```

Wait ~30 seconds for MySQL to initialise, then:

```bash
# Generate app key
docker-compose exec php php artisan key:generate

# Run migrations
docker-compose exec php php artisan migrate
```

**URLs:**
| Service | URL |
|---------|-----|
| Vue dashboard | http://localhost:5173 |
| Laravel API | http://localhost:8000 |
| Capture endpoint | `http://localhost:8000/event/hooks/<token>` |

---

## Firebase Security Rules

After testing, lock down the Realtime Database so only your backend can write
and clients cannot read arbitrary paths.

```json
{
  "rules": {
    "endpoints": {
      "$endpointId": {
        "latest": {
          ".read":  false,
          ".write": false
        }
      }
    }
  }
}
```

> The Vue app reads via `onValue()` which requires `.read` access on the path.
> For production, use Firebase App Check or restrict reads to authenticated
> Firebase users. For local dev, leaving rules open is fine.

---

## Architecture

```
browser / 3rd-party service
       │
       ├── POST /event/hooks/<token>  ──▶  IngestController (no auth)
       │                              │ persist to MySQL
       │                              └── push signal to Firebase /endpoints/{id}/latest
       │
       └── /api/*           ──▶  App API (Sanctum auth)
                                     │ scoped reads: endpoint → requests
                                     └── MySQL (source of truth)

Vue frontend
  ├── Pinia stores (auth, endpoints)
  ├── useFirebaseLiveTail composable  ──▶  Firebase Realtime DB (live events only)
  └── axios API client               ──▶  Laravel API
```

**Key design rules:**
- Isolation is enforced at the query layer, never in the UI. Every request read is scoped through an authorized `endpoint_id`.
- Tokens (in URLs) are high-entropy (128 bits). Internal UUIDs are never exposed in URLs.
- Firebase only carries a lightweight trigger signal. All data lives in MySQL.
- Ingestion never parses the body — it's a pure observer. Size-capped at 10 MB.

---

## Project structure

```
webhook-inspector/
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
│   │   │   │   ├── RequestController.php     tenant-scoped request reads
│   │   │   │   ├── AdminController.php       stats, users, endpoints, settings
│   │   │   │   └── ExtensionController.php   public extension config
│   │   │   └── Middleware/EnsureAdmin.php
│   │   ├── Models/
│   │   │   ├── User.php                  plan/status/is_admin + plan helpers
│   │   │   ├── Endpoint.php              token gen, auth + limit helpers
│   │   │   ├── WebhookRequest.php        ULID PK, raw body, parsed helpers
│   │   │   └── Setting.php               key/value app settings
│   │   └── Services/FirebaseService.php  pushNewRequest() → Realtime DB
│   ├── config/{plans.php, app.php, firebase.php}
│   ├── database/migrations/
│   └── routes/{api.php, web.php, console.php}
│
├── frontend/               Vue 3 + Vite SPA
│   └── src/
│       ├── composables/useFirebaseLiveTail.js   live-tail subscription
│       ├── stores/{auth, endpoints, theme}.js
│       ├── api/{client, endpoints, admin}.js
│       ├── pages/
│       │   ├── LoginPage.vue / RegisterPage.vue
│       │   ├── DashboardPage.vue         endpoint list + create form
│       │   ├── EndpointDetailPage.vue    two-pane inspector (auth)
│       │   ├── InspectPage.vue           public guest view
│       │   └── AdminPage.vue             admin dashboard / backoffice
│       └── components/{RequestDetail, ThemeToggle}.vue
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
docker-compose logs -f php

# Run a migration
docker-compose exec php php artisan migrate

# Tinker
docker-compose exec php php artisan tinker

# Rebuild PHP container after composer.json changes
docker-compose up -d --build php
```

---

## Status

Implemented:

- [x] Rate limiting on ingestion routes (`throttle:ingest` in `AppServiceProvider`)
- [x] Request retention (`endpoints:expire` — guest deletion + per-plan pruning, scheduled hourly)
- [x] Plan-based limits (max URLs, requests per URL, retention window, custom-response gating)
- [x] Guest mode (one URL/browser, 200-request cap, 2-day retention)
- [x] Custom vanity URLs for registered users
- [x] Admin dashboard / backoffice (users, plans, stats, settings)
- [x] Chrome extension (notifications, badge, side-panel inspector, create URL)
- [x] Dark / light theme

Still open:

- [ ] Per-endpoint custom response (status code, body, headers) — gating is in place; UI + ingest application pending
- [ ] Firebase security rules for production (see section above)
- [ ] Admin-editable plan tiers from the UI (currently `config/plans.php`)
- [ ] Extension scheduler note: run `php artisan schedule:work` (or a cron `schedule:run`) for retention to fire

> **Note:** the schedule for `endpoints:expire` only runs if Laravel's scheduler is active —
> locally `php artisan schedule:work`, in production the standard `* * * * * php artisan schedule:run` cron.
