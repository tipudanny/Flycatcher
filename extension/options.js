const baseEl = document.getElementById('base');
const saveMsgEl = document.getElementById('saveMsg');
const emailEl = document.getElementById('email');
const passEl = document.getElementById('password');
const msgEl = document.getElementById('msg');
const connectedEl = document.getElementById('connected');
const whoEl = document.getElementById('who');

async function load() {
  const { apiBase, token, userEmail } = await chrome.storage.local.get(['apiBase', 'token', 'userEmail']);
  baseEl.value = apiBase || 'http://localhost:8000';
  if (token) showConnected(userEmail);
}

// Manual override — normally the URL is detected automatically when you open
// Flycatcher in this browser.
document.getElementById('save').addEventListener('click', async () => {
  const base = baseEl.value.trim().replace(/\/+$/, '');
  if (!base) { saveMsgEl.textContent = 'Enter your Flycatcher URL.'; return; }
  await chrome.storage.local.set({ apiBase: base });
  saveMsgEl.textContent = 'Saved.';
  setTimeout(() => (saveMsgEl.textContent = ''), 3000);
});

// Keep the URL field in sync if auto-detection updates it while open.
chrome.storage.onChanged.addListener((changes, area) => {
  if (area === 'local' && changes.apiBase) baseEl.value = changes.apiBase.newValue || '';
});

function showConnected(email) {
  connectedEl.style.display = 'block';
  whoEl.textContent = email || 'this account';
}

document.getElementById('connect').addEventListener('click', async () => {
  msgEl.textContent = 'Connecting…';
  const base = baseEl.value.trim().replace(/\/+$/, '');
  await chrome.storage.local.set({ apiBase: base });

  try {
    const res = await fetch(`${base}/api/auth/login`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
      body: JSON.stringify({ email: emailEl.value, password: passEl.value }),
    });
    const data = await res.json();
    if (!res.ok) {
      msgEl.textContent = data.message || 'Login failed — check your credentials.';
      return;
    }
    // Reset baselines so we don't notify for already-captured requests.
    await chrome.storage.local.set({
      token: data.token,
      userEmail: data.user.email,
      seenCounts: {},
      unread: 0,
    });
    passEl.value = '';
    msgEl.textContent = 'Connected!';
    showConnected(data.user.email);
  } catch (e) {
    msgEl.textContent = 'Could not reach the API at that URL.';
  }
});

document.getElementById('disconnect').addEventListener('click', async () => {
  await chrome.storage.local.remove(['token', 'userEmail', 'seenCounts', 'unread']);
  chrome.action.setBadgeText({ text: '' });
  connectedEl.style.display = 'none';
  msgEl.textContent = 'Disconnected.';
});

load();
