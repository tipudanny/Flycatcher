const els = {
  empty: document.getElementById('empty'),
  view: document.getElementById('view'),
  crumb: document.getElementById('crumb'),
  crumbTitle: document.getElementById('crumb-title'),
  back: document.getElementById('back'),
  live: document.getElementById('live'),
};

const state = { view: 'endpoints', endpoint: null, request: null, bodyView: 'pretty' };
let timer = null;
let appUrl = 'http://localhost:5173';

document.getElementById('settings').addEventListener('click', () => chrome.runtime.openOptionsPage());
document.getElementById('refresh').addEventListener('click', () => refresh());
els.back.addEventListener('click', goBack);

// ── Create URL ───────────────────────────────────────────────────────────────
const newbar = document.getElementById('newbar');
const newLabel = document.getElementById('new-label');
const newHint = document.getElementById('new-hint');
const newErr = document.getElementById('new-err');

document.getElementById('new').addEventListener('click', toggleNewbar);
document.getElementById('new-cancel').addEventListener('click', () => newbar.classList.remove('show'));
document.getElementById('new-create').addEventListener('click', submitNew);
newLabel.addEventListener('keydown', (e) => { if (e.key === 'Enter') submitNew(); });

async function toggleNewbar() {
  newbar.classList.toggle('show');
  if (!newbar.classList.contains('show')) return;
  newErr.textContent = '';
  newLabel.value = '';
  newLabel.focus();
  // Guests get one URL per browser — hint at it.
  const loggedIn = !!(await getToken());
  newHint.textContent = loggedIn
    ? 'Creates a new URL on your account.'
    : 'Guests get one URL per browser. Sign in (⚙) for unlimited.';
}

async function submitNew() {
  newErr.textContent = '';
  const btn = document.getElementById('new-create');
  btn.disabled = true;
  btn.textContent = 'Creating…';
  try {
    const ep = await createEndpoint(newLabel.value.trim());
    newbar.classList.remove('show');
    await showRequests(ep);   // jump straight to the new URL's requests
    startTimer();
  } catch (e) {
    newErr.textContent = e.message || 'Could not create a URL.';
  } finally {
    btn.disabled = false;
    btn.textContent = 'Create';
  }
}

init();

async function init() {
  chrome.runtime.sendMessage('reset-unread');
  const cfg = await fetchExtensionConfig();
  appUrl = cfg.app_url || appUrl;
  if (!cfg.enabled) return showEmpty('The extension has been disabled by the administrator.');
  await showEndpoints();
  startTimer();
}

// ── Polling ──────────────────────────────────────────────────────────────────
function startTimer() {
  stopTimer();
  timer = setInterval(refresh, 4000);
}
function stopTimer() {
  if (timer) clearInterval(timer);
  timer = null;
}
async function refresh() {
  if (state.view === 'endpoints') await showEndpoints(true);
  else if (state.view === 'requests') await showRequests(state.endpoint, true);
}

// ── Views ────────────────────────────────────────────────────────────────────
async function showEndpoints(isRefresh) {
  state.view = 'endpoints';
  state.endpoint = null;
  setCrumb(false);

  if (!(await hasAnySource())) return renderDisconnected();

  let endpoints;
  try {
    endpoints = await collectEndpoints();
  } catch (e) {
    if (!isRefresh) showEmpty('Could not reach the Flycatcher API.');
    return;
  }

  if (!endpoints.length) return showEmpty('No webhook URLs yet.\nCreate one in the app to see requests here.');

  setLive(true);
  hideEmpty();
  const frag = document.createDocumentFragment();
  for (const e of endpoints) frag.appendChild(endpointCard(e));
  els.view.replaceChildren(frag);
}

async function showRequests(endpoint, isRefresh) {
  state.view = 'requests';
  state.endpoint = endpoint;
  setCrumb(true, endpoint.label || endpoint.token);

  let res, data;
  try {
    res = await apiFetch(`/endpoints/${endpoint.token}/requests?per_page=50`);
    if (!res.ok) throw new Error();
    data = (await res.json()).data;
  } catch (e) {
    if (!isRefresh) showEmpty('Could not load requests.');
    return;
  }

  if (!data.length) return showEmpty('Waiting for requests…\nSend something to this URL.');

  setLive(true);
  hideEmpty();
  const frag = document.createDocumentFragment();
  for (const r of data) frag.appendChild(requestRow(endpoint, r));
  els.view.replaceChildren(frag);
}

async function showDetail(endpoint, summary) {
  state.view = 'detail';
  state.request = summary;
  state.bodyView = 'pretty';
  setCrumb(true, endpoint.label || endpoint.token);
  stopTimer(); // detail is a snapshot — no live refresh

  let res, d;
  try {
    res = await apiFetch(`/endpoints/${endpoint.token}/requests/${summary.id}`);
    if (!res.ok) throw new Error();
    d = (await res.json()).data;
  } catch (e) {
    return showEmpty('Could not load this request.');
  }

  hideEmpty();
  els.view.replaceChildren(detailNode(d));
}

function goBack() {
  if (state.view === 'detail') {
    showRequests(state.endpoint);
    startTimer();
  } else if (state.view === 'requests') {
    showEndpoints();
    startTimer();
  }
}

// ── Builders ─────────────────────────────────────────────────────────────────
function endpointCard(e) {
  const card = el('div', 'card');
  const top = el('div', 'top');
  top.append(el('span', 'label', e.label || e.token));
  top.append(el('span', 'count', String(e.request_count)));
  const url = el('div', 'url', e.capture_url);
  card.append(top, url);
  card.addEventListener('click', () => showRequests(e));
  return card;
}

function requestRow(endpoint, r) {
  const row = el('div', 'req');
  const line = el('div', 'line');
  line.append(badge(r.method));
  line.append(el('span', 'path', r.path || '/'));
  const sub = el('div', 'sub');
  sub.append(el('span', '', (r.content_type || '—').split(';')[0]));
  sub.append(el('span', '', '·'));
  sub.append(el('span', '', formatBytes(r.body_size)));
  sub.append(el('span', 'time', timeAgo(r.received_at)));
  row.append(line, sub);
  row.addEventListener('click', () => showDetail(endpoint, r));
  return row;
}

function detailNode(d) {
  const wrap = el('div', 'detail');

  const head = el('div', 'head');
  head.append(badge(d.method));
  head.append(el('span', 'path', (d.path || '/') + (d.query_string ? '?' + d.query_string : '')));
  wrap.append(head);

  const meta = el('div', 'meta');
  meta.append(metaItem('Received', formatDate(d.received_at)));
  meta.append(metaItem('IP', d.ip_address || '—'));
  meta.append(metaItem('Content-Type', d.content_type || '—'));
  meta.append(metaItem('Body size', formatBytes(d.body_size)));
  wrap.append(meta);

  if (d.user_agent) {
    const ua = el('div');
    ua.append(el('div', 'section-title', 'User-Agent'));
    const p = el('div'); p.style.fontSize = '11px'; p.style.color = 'var(--text-dim)'; p.style.wordBreak = 'break-all';
    p.textContent = d.user_agent;
    ua.append(p);
    wrap.append(ua);
  }

  if (d.headers && Object.keys(d.headers).length) {
    wrap.append(el('div', 'section-title', 'Headers'));
    wrap.append(kvBlock(d.headers, (v) => (Array.isArray(v) ? v.join(', ') : v)));
  }

  if (d.query_params && Object.keys(d.query_params).length) {
    wrap.append(el('div', 'section-title', 'Query Params'));
    wrap.append(kvBlock(d.query_params, (v) => String(v)));
  }

  // Body
  const raw = d.body_raw || '';
  const pretty = d.body_pretty || raw;
  const bodyHead = el('div', 'body-head');
  bodyHead.append(el('div', 'section-title', 'Body'));
  const toggle = el('div', 'toggle');
  const prettyBtn = el('button', 'active', 'Pretty');
  const rawBtn = el('button', '', 'Raw');
  toggle.append(prettyBtn, rawBtn);
  bodyHead.append(toggle);

  const pre = el('pre');
  pre.textContent = raw ? pretty : '(empty body)';
  prettyBtn.addEventListener('click', () => { pre.textContent = pretty || '(empty body)'; prettyBtn.classList.add('active'); rawBtn.classList.remove('active'); });
  rawBtn.addEventListener('click', () => { pre.textContent = raw || '(empty body)'; rawBtn.classList.add('active'); prettyBtn.classList.remove('active'); });

  wrap.append(bodyHead, pre);

  if (raw) {
    const copy = el('button', 'copy', 'Copy body');
    copy.addEventListener('click', () => { navigator.clipboard.writeText(raw); copy.textContent = 'Copied!'; setTimeout(() => (copy.textContent = 'Copy body'), 1500); });
    wrap.append(copy);
  }

  return wrap;
}

function kvBlock(obj, fmt) {
  const block = el('div', 'kv');
  for (const [k, v] of Object.entries(obj)) {
    const row = el('div', 'row');
    row.append(el('span', 'name', k));
    row.append(el('span', 'val', fmt(v)));
    block.append(row);
  }
  return block;
}

function metaItem(k, v) {
  const d = el('div');
  d.append(el('div', 'k', k));
  d.append(el('div', 'v', v));
  return d;
}

function badge(method) {
  return el('span', `badge method-${method}`, method);
}

function renderDisconnected() {
  setLive(false);
  showEmpty('No URLs yet.\nOpen Flycatcher and create a webhook URL — it appears here automatically.\nOr sign in via ⚙ Settings to watch your account.');
  const open = document.createElement('a');
  open.textContent = 'Open Flycatcher';
  open.style.cursor = 'pointer';
  open.addEventListener('click', () => chrome.tabs.create({ url: appUrl }));
  els.empty.append(document.createElement('br'), open);
}

// ── Helpers ──────────────────────────────────────────────────────────────────
function el(tag, cls, text) {
  const n = document.createElement(tag);
  if (cls) n.className = cls;
  if (text !== undefined) n.textContent = text;
  return n;
}
function setCrumb(show, title) {
  els.crumb.classList.toggle('show', !!show);
  if (show) els.crumbTitle.textContent = title || '';
}
function setLive(on) { els.live.classList.toggle('on', !!on); }
function showEmpty(text) {
  els.view.replaceChildren();
  els.empty.style.display = 'block';
  els.empty.textContent = text;
}
function hideEmpty() { els.empty.style.display = 'none'; }

function timeAgo(iso) {
  const s = Math.floor((Date.now() - new Date(iso)) / 1000);
  if (s < 60) return `${s}s`;
  if (s < 3600) return `${Math.floor(s / 60)}m`;
  if (s < 86400) return `${Math.floor(s / 3600)}h`;
  return `${Math.floor(s / 86400)}d`;
}
function formatBytes(b) {
  if (!b) return '0 B';
  if (b < 1024) return `${b} B`;
  return `${(b / 1024).toFixed(1)} KB`;
}
function formatDate(iso) { return new Date(iso).toLocaleString(); }
