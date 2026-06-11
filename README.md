# Flycatcher
webhooks fly in, you catch and examine them


A self-hosted webhook inspection tool — capture, inspect, and live-tail any HTTP request.

**Stack:** Laravel 11 · Vue 3 + Vite · MySQL · Firebase Realtime DB (live-tail only)

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
│   │   ├── Http/Controllers/
│   │   │   ├── Auth/AuthController.php   register, login, logout
│   │   │   ├── IngestController.php      capture endpoint (no auth)
│   │   │   ├── EndpointController.php    CRUD for webhook URLs
│   │   │   └── RequestController.php     tenant-scoped request reads
│   │   ├── Models/
│   │   │   ├── User.php
│   │   │   ├── Endpoint.php              token generation, auth helpers
│   │   │   └── WebhookRequest.php        ULID PK, raw body, parsed helpers
│   │   └── Services/
│   │       └── FirebaseService.php       pushNewRequest() → Realtime DB
│   ├── database/migrations/
│   └── routes/
│       ├── api.php                       auth + tenant-scoped API
│       └── web.php                       ingestion routes (/event/hooks/{token})
│
├── frontend/               Vue 3 + Vite SPA
│   └── src/
│       ├── composables/useFirebaseLiveTail.js   live-tail subscription
│       ├── stores/auth.js                        Sanctum token management
│       ├── stores/endpoints.js
│       ├── pages/
│       │   ├── LoginPage.vue
│       │   ├── RegisterPage.vue
│       │   ├── DashboardPage.vue         endpoint list
│       │   ├── EndpointDetailPage.vue    two-pane inspector (auth)
│       │   └── InspectPage.vue           public guest view
│       └── components/RequestDetail.vue  headers/body/meta panel
│
├── docker/
│   ├── nginx/default.conf
│   └── php/Dockerfile
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

## Next steps

- [ ] Rate limiting on ingestion routes (`throttle:ingest` config in `AppServiceProvider`)
- [ ] Per-endpoint custom response (status code, body, headers) via `settings` JSON
- [ ] Request retention cron (`endpoints:expire` artisan command)
- [ ] Firebase security rules for production
- [ ] Plan-based limits (max endpoints, max requests, retention window)
