// Service worker: polls the Flycatcher API on a timer, raises desktop
// notifications for new requests, and keeps an unread-count badge.

importScripts('shared.js');

const ALARM = 'flycatcher-poll';

chrome.runtime.onInstalled.addListener(() => {
  setupAlarm();
  // Clicking the toolbar icon opens the side-panel inspector.
  chrome.sidePanel?.setPanelBehavior({ openPanelOnActionClick: true }).catch(() => {});
});
chrome.runtime.onStartup.addListener(setupAlarm);

// Re-arm whenever the session, configured URL, or watches change.
chrome.storage.onChanged.addListener((changes, area) => {
  if (area === 'local' && (changes.token || changes.apiBase || changes.watches)) setupAlarm();
});

chrome.alarms.onAlarm.addListener((a) => {
  if (a.name === ALARM) poll();
});

chrome.runtime.onMessage.addListener((msg, sender, reply) => {
  // Side panel signals it was opened → clear the unread badge.
  if (msg === 'reset-unread') {
    chrome.storage.local.set({ unread: 0 });
    clearBadge();
    reply?.({ ok: true });
  }
  // Content script detected a Flycatcher page → auto-configure the URL and
  // adopt the logged-in session, so the same browser shows the same data.
  if (msg && msg.type === 'flycatcher') {
    adoptFlycatcher(msg.origin, msg.token);
    reply?.({ ok: true });
  }
  return true;
});

async function adoptFlycatcher(origin, token) {
  const store = await chrome.storage.local.get(['apiBase', 'token']);
  let changed = false;

  // Auto-set the Flycatcher URL to wherever the user is actually browsing it.
  if (origin && origin !== store.apiBase) {
    await chrome.storage.local.set({ apiBase: origin });
    changed = true;
  }

  // Mirror a signed-in session (reset baselines so we don't notify history).
  // A logged-out web app sends token=null; the old token then 401s and clears
  // itself on the next poll.
  if (token && token !== store.token) {
    await chrome.storage.local.set({ token, seenCounts: {}, unread: 0 });
    changed = true;
  }

  if (changed) poll();
}

// Clicking a notification opens the app.
chrome.notifications.onClicked.addListener(async () => {
  const cfg = await fetchExtensionConfig();
  chrome.tabs.create({ url: cfg.app_url || 'http://localhost:5173' });
});

async function setupAlarm() {
  const cfg = await fetchExtensionConfig();
  // Chrome enforces a ~30s minimum alarm period regardless of the configured value.
  const minutes = Math.max(30, cfg.poll_interval || 30) / 60;
  chrome.alarms.create(ALARM, { periodInMinutes: minutes });
  poll();
}

async function poll() {
  const cfg = await fetchExtensionConfig();
  if (!cfg.enabled) return clearBadge();

  if (!(await hasAnySource())) return clearBadge(); // no account, no guest cookie

  // Account endpoints (if signed in) + this browser's guest URLs, merged.
  const endpoints = await collectEndpoints();

  const store = await chrome.storage.local.get(['seenCounts', 'unread']);
  const seen = store.seenCounts || {};
  let unread = store.unread || 0;
  const firstRun = Object.keys(seen).length === 0;
  const nextSeen = {};

  for (const e of endpoints) {
    nextSeen[e.token] = e.request_count;
    const prev = seen[e.token];
    if (firstRun || prev === undefined) continue; // baseline — never notify historical data
    const delta = e.request_count - prev;
    if (delta > 0) {
      unread += delta;
      if (cfg.notifications) notify(e, delta);
    }
  }

  await chrome.storage.local.set({ seenCounts: nextSeen, unread });
  cfg.badge ? setBadge(unread) : clearBadge();
}

function notify(endpoint, delta) {
  chrome.notifications.create(`fc-${endpoint.token}-${Date.now()}`, {
    type: 'basic',
    iconUrl: 'icons/icon128.png',
    title: 'Flycatcher',
    message: `${delta} new request${delta > 1 ? 's' : ''} on ${endpoint.label || endpoint.token}`,
    priority: 1,
  });
}

function setBadge(count) {
  chrome.action.setBadgeBackgroundColor({ color: '#4f46e5' });
  chrome.action.setBadgeText({ text: count > 0 ? (count > 99 ? '99+' : String(count)) : '' });
}

function clearBadge() {
  chrome.action.setBadgeText({ text: '' });
}
