# Flycatcher Chrome Extension

Live desktop notifications, an unread-count badge, and a full side-panel
inspector for webhooks captured by your [Flycatcher](../README.md) instance —
browse requests and read headers/bodies without leaving the page you're on.

## Features

- **Desktop notifications** when a new request hits any of your webhook URLs
- **Badge counter** on the toolbar icon showing unseen requests (cleared when you open the panel)
- **Side-panel inspector** — click the toolbar icon to open a docked panel that browses endpoints → requests → full request detail (headers, query, pretty/raw body), refreshing live
- **Create URLs** — a **+ New** button mints a webhook URL right from the panel (a new one for registered users; a guest's single browser URL otherwise)
- **Same browser, same data** — set your Flycatcher URL once, and whatever you use in that browser shows up in the extension automatically: guest URLs (via the session cookie) **and** your signed-in account's URLs + history (a content script on your Flycatcher domain forwards the web app's login token). No separate sign-in needed.
- **Sign in from another browser (optional)** — to get notifications where you're *not* using the web app, sign in via the extension's Settings.
- **Admin-controlled config** — poll interval, notification/badge toggles, and the app URL are all set from the Flycatcher backoffice (Admin → Browser extension) and fetched from `GET /api/extension/config`

## Install (load unpacked)

1. Make sure the Flycatcher backend is running (default `http://localhost:8000`).
2. Open `chrome://extensions` in Chrome.
3. Toggle **Developer mode** on (top-right).
4. Click **Load unpacked** and select this `extension/` folder.
5. Click the Flycatcher icon → **Settings**, set the **Flycatcher URL** (e.g.
   `https://flycatcher.site` or `http://localhost:8000`), and click **Save**.
6. Now just use Flycatcher in this browser. Whether you sign in or use a guest URL,
   your URLs and request history appear in the extension automatically — in sync
   with the web app. (Optional: to watch from a browser where you're *not* using
   the web app, sign in from the Settings page.)

That's it — you'll get a notification and badge whenever a webhook arrives.

## How it works

- A Manifest V3 service worker (`background.js`) polls `GET /api/endpoints` on a
  `chrome.alarms` timer, compares each endpoint's `request_count` against the last
  seen value, and raises a notification + badge for any increase.
- A small content script (`content.js`), registered only on your Flycatcher
  domain, forwards the web app's login token (`auth_token` from `localStorage`)
  to the extension — so the same browser shows the same signed-in account, no
  separate sign-in. It reads only that one key.
- Registered users authenticate with a Sanctum bearer token from `POST /api/auth/login`,
  stored in `chrome.storage.local`. Guests are recognised by reading the
  `guest_session_id` cookie (via the privileged `chrome.cookies` API) and passing it
  as an `X-Guest-Session` header — the same session the web app uses.
- The poll interval and toggles come from the admin-managed config endpoint, so
  you can tune behaviour for everyone from the backoffice without reshipping the
  extension.

## Notes & limits

- Chrome enforces a **~30-second minimum** alarm period, so sub-30s poll intervals
  are clamped. For truly instant updates, the service worker could subscribe to the
  same Firebase live-tail the web app uses — a natural future upgrade.
- `host_permissions` currently allows `localhost` and any `https://` origin so you
  can point it at a deployed backend. Tighten this to your exact domain before
  publishing to the Chrome Web Store.
