// Shared helpers used by the service worker, side panel, and options page.
// Loaded via importScripts() in the worker and <script src> in the HTML pages.

const DEFAULT_API = 'http://localhost:8000';

const FALLBACK_CONFIG = {
  brand: 'Flycatcher',
  enabled: true,
  notifications: true,
  badge: true,
  poll_interval: 30,
  app_url: 'http://localhost:5173',
};

async function getApiBase() {
  const { apiBase } = await chrome.storage.local.get('apiBase');
  return apiBase || DEFAULT_API;
}

async function getToken() {
  const { token } = await chrome.storage.local.get('token');
  return token || null;
}

// Call the Flycatcher API with the stored base URL, attaching whatever
// credentials we have: a bearer token (registered) and/or the guest session
// header (guest). The backend authorizes endpoint reads against either.
async function apiFetch(path, opts = {}) {
  const base = await getApiBase();
  const token = await getToken();
  const session = await getGuestSession();
  const headers = Object.assign({ Accept: 'application/json' }, opts.headers || {});
  if (token) headers.Authorization = `Bearer ${token}`;
  if (session) headers['X-Guest-Session'] = session;
  return fetch(`${base}/api${path}`, Object.assign({}, opts, { headers }));
}

// Read the guest session id straight from the browser's cookie jar. The
// chrome.cookies API can read httpOnly cookies (page JS can't), so the
// extension auto-discovers the same guest URLs the web app sees — no login,
// no pairing code. Requires the "cookies" permission + host access.
async function getGuestSession() {
  const base = await getApiBase();
  // Prefer the web app's cookie so the extension stays aligned with the app.
  try {
    const cookie = await chrome.cookies.get({ url: base, name: 'guest_session_id' });
    if (cookie?.value) return cookie.value;
  } catch (e) {
    /* no cookies access */
  }
  // Fall back to a session the extension minted itself (created a URL from here
  // before ever opening the app).
  const { guestSession } = await chrome.storage.local.get('guestSession');
  return guestSession || null;
}

// Create a webhook URL from the extension. Registered users get an owned URL;
// guests get (or reuse) their single browser URL, and we persist the returned
// session so later reads work via the X-Guest-Session header.
async function createEndpoint(label) {
  const res = await apiFetch('/endpoints', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify(label ? { label } : {}),
  });
  if (!res.ok) {
    let message = 'Could not create a URL.';
    try {
      const body = await res.json();
      message = body.message || message;
    } catch (e) { /* ignore */ }
    throw new Error(message);
  }
  const json = await res.json();
  if (json.guest_session) {
    await chrome.storage.local.set({ guestSession: json.guest_session, seenCounts: {}, unread: 0 });
  }
  return json.data;
}

// Guest URLs for this browser, fetched by passing the session id as a header
// (SameSite=Lax stops the cookie itself riding along on a cross-site fetch).
async function fetchGuestEndpoints() {
  const session = await getGuestSession();
  if (!session) return [];
  const base = await getApiBase();
  try {
    const res = await fetch(`${base}/api/endpoints`, {
      headers: { Accept: 'application/json', 'X-Guest-Session': session },
    });
    if (!res.ok) return [];
    const { data } = await res.json();
    return data;
  } catch (e) {
    return [];
  }
}

// Everything to watch: the signed-in account's endpoints (if any) plus this
// browser's guest URLs, de-duplicated by token.
async function collectEndpoints() {
  const all = [];

  const token = await getToken();
  if (token) {
    try {
      const res = await apiFetch('/endpoints');
      if (res.status === 401) {
        await chrome.storage.local.remove('token');
      } else if (res.ok) {
        const { data } = await res.json();
        all.push(...data);
      }
    } catch (e) {
      /* network blip */
    }
  }

  all.push(...(await fetchGuestEndpoints()));

  const byToken = new Map();
  for (const e of all) byToken.set(e.token, e);
  return [...byToken.values()];
}

// Is anything connected at all (account token or a guest cookie)?
async function hasAnySource() {
  if (await getToken()) return true;
  return !!(await getGuestSession());
}

// Read the admin-managed extension config (poll interval, toggles, app URL).
// Cached in storage so we still work briefly if the API is unreachable.
async function fetchExtensionConfig() {
  try {
    const res = await apiFetch('/extension/config');
    if (!res.ok) throw new Error('config request failed');
    const { data } = await res.json();
    await chrome.storage.local.set({ config: data });
    return data;
  } catch (e) {
    const { config } = await chrome.storage.local.get('config');
    return config || FALLBACK_CONFIG;
  }
}
