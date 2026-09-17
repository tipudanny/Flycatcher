<template>
  <div class="h-screen bg-gray-50 dark:bg-gray-950 flex flex-col overflow-hidden">
    <AppHeader :crumb="endpoint?.label || endpoint?.token">
      <template #status>
        <span v-if="liveConnected" class="flex items-center gap-1.5 text-xs text-emerald-600 dark:text-emerald-400">
          <span class="relative flex w-1.5 h-1.5">
            <span class="absolute inline-flex h-full w-full rounded-full bg-emerald-500 animate-ping opacity-75"></span>
            <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-emerald-500"></span>
          </span>
          <span class="hidden sm:inline">Live</span>
        </span>
      </template>
      <template #actions>
        <button @click="router.push('/')" class="btn-ghost">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
          <span class="hidden sm:inline">Dashboard</span>
        </button>
      </template>
    </AppHeader>

    <div v-if="endpoint" class="flex-1 flex flex-col min-h-0">
      <!-- Capture URL — compact, click-to-copy pill -->
      <div class="border-b border-gray-200 dark:border-gray-800 px-4 sm:px-5 py-2.5 flex items-center gap-2 bg-white/50 dark:bg-white/[0.02]">
        <span class="text-xs text-gray-500 dark:text-gray-400 shrink-0 hidden sm:inline">Capture URL</span>
        <button
          @click="copyUrl"
          :title="endpoint.capture_url"
          class="group relative inline-flex items-center gap-1.5 max-w-full bg-brand-50 hover:bg-brand-100 dark:bg-brand-500/10 dark:hover:bg-brand-500/[0.18] border border-brand-200/60 dark:border-brand-500/20 rounded-full pl-3 pr-1 py-1 transition-colors"
        >
          <code class="text-xs text-brand-700 dark:text-brand-300 font-mono truncate max-w-[45vw] sm:max-w-xs">{{ endpoint.capture_url }}</code>
          <span class="flex items-center justify-center w-6 h-6 rounded-full bg-white dark:bg-gray-900 text-brand-600 dark:text-brand-300 shrink-0 shadow-soft transition-transform group-active:scale-90">
            <svg v-if="!copied" class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" />
            </svg>
            <svg v-else class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
          </span>
        </button>
        <span v-if="copied" class="text-xs text-emerald-600 dark:text-emerald-400 animate-fade-in">Copied to clipboard</span>
      </div>

      <!-- Two-pane layout — collapses to a single pane (list ⇄ detail) below md -->
      <div class="flex-1 flex overflow-hidden min-h-0">
        <!-- Left: request list -->
        <div
          :class="[
            'w-full md:w-80 border-r border-gray-200 dark:border-gray-800 flex-col overflow-hidden bg-white/40 dark:bg-white/[0.015] shrink-0 min-h-0',
            selectedRequest ? 'hidden md:flex' : 'flex'
          ]"
        >
          <div class="px-4 py-2.5 flex items-center gap-2 border-b border-gray-200 dark:border-gray-800 shrink-0">
            <input
              v-if="requests.length"
              type="checkbox"
              :checked="allSelected"
              @change="toggleSelectAll"
              title="Select all"
              class="h-3.5 w-3.5 accent-brand-600 cursor-pointer rounded"
            />
            <span class="text-xs font-medium text-gray-500 dark:text-gray-400">Requests <span class="text-gray-400 dark:text-gray-600">({{ meta.total }})</span></span>
            <button v-if="selectedIds.length" @click="deleteSelected" class="ml-auto text-xs text-red-500 hover:text-red-600 dark:text-red-400 dark:hover:text-red-300 transition-colors font-medium">
              Delete ({{ selectedIds.length }})
            </button>
            <button v-else-if="requests.length" @click="clearAll" class="ml-auto text-xs text-red-500 hover:text-red-600 dark:text-red-400 dark:hover:text-red-300 transition-colors font-medium">
              Clear all
            </button>
          </div>

          <div class="flex-1 overflow-y-auto min-h-0">
            <div
              v-for="req in requests"
              :key="req.id"
              @click="selectRequest(req)"
              :class="[
                'group relative px-4 py-3 border-b border-gray-100 dark:border-gray-800/70 cursor-pointer transition-colors',
                selectedId === req.id
                  ? 'bg-brand-100/80 dark:bg-brand-500/[0.18] border-l-[3px] border-l-brand-600 dark:border-l-brand-400 ring-1 ring-inset ring-brand-500/15 -ml-px'
                  : 'hover:bg-gray-100/70 dark:hover:bg-white/[0.03] border-l-[3px] border-l-transparent'
              ]"
            >
              <div class="flex items-center gap-2">
                <input
                  type="checkbox"
                  :checked="isSelected(req.id)"
                  @click.stop
                  @change="toggleSelect(req.id)"
                  class="h-3.5 w-3.5 accent-brand-600 shrink-0 cursor-pointer rounded"
                />
                <span :class="`method-${req.method}`">{{ req.method }}</span>
                <span :class="['text-xs truncate font-mono', selectedId === req.id ? 'font-semibold text-gray-900 dark:text-white' : 'text-gray-700 dark:text-gray-300']">{{ req.path || '/' }}</span>
                <button
                  @click.stop="deleteOne(req)"
                  title="Delete request"
                  class="ml-auto shrink-0 text-gray-400 hover:text-red-500 dark:hover:text-red-400 opacity-0 group-hover:opacity-100 focus:opacity-100 transition-opacity"
                >
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                  </svg>
                </button>
              </div>
              <div class="mt-1.5 flex items-center gap-2 text-[11px] text-gray-500 pl-[22px]">
                <span>{{ req.content_type?.split(';')[0] || '—' }}</span>
                <span class="text-gray-300 dark:text-gray-700">·</span>
                <span>{{ formatBytes(req.body_size) }}</span>
                <span class="ml-auto tabular-nums">{{ timeAgo(req.received_at) }}</span>
              </div>
            </div>

            <!-- Pagination: load older requests beyond the first page -->
            <div v-if="requests.length && meta.current_page < meta.last_page" class="p-3 text-center">
              <button @click="loadMore" :disabled="loadingMore" class="btn-secondary btn-sm w-full">
                {{ loadingMore ? 'Loading…' : `Load more (${meta.total - requests.length} older)` }}
              </button>
            </div>
            <div v-else-if="requests.length" class="py-3 text-center text-[11px] text-gray-400 dark:text-gray-600">
              — end of requests —
            </div>

            <div v-if="!requests.length" class="p-8 text-center">
              <div class="w-9 h-9 mx-auto rounded-lg bg-gray-100 dark:bg-gray-900 flex items-center justify-center text-gray-400 dark:text-gray-600 mb-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" /></svg>
              </div>
              <p class="text-gray-400 dark:text-gray-600 text-xs">Waiting for requests…</p>
            </div>
          </div>
        </div>

        <!-- Right: request detail -->
        <div :class="['flex-1 overflow-y-auto min-h-0', selectedRequest ? 'block' : 'hidden md:block']">
          <template v-if="selectedRequest">
            <button @click="backToList" class="md:hidden btn-ghost m-3 mb-0">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
              All requests
            </button>
            <RequestDetail :request="selectedRequest" :token="token" />
          </template>
          <div v-else class="hidden md:flex flex-col items-center justify-center h-full px-8 text-center">
            <div class="w-14 h-14 rounded-2xl bg-brand-gradient flex items-center justify-center text-white shadow-lift mb-4">
              <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
              </svg>
            </div>
            <h3 class="text-base font-semibold text-gray-900 dark:text-white mb-1">
              {{ requests.length ? 'Select a request to inspect' : 'Waiting for your first request' }}
            </h3>
            <p class="text-sm text-gray-500 dark:text-gray-400 max-w-sm mb-6">
              Send any HTTP request to your capture URL above and it'll show up here instantly.
            </p>

            <div class="w-full max-w-lg text-left">
              <div class="flex items-center justify-between mb-1.5">
                <span class="text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">Try it now</span>
                <button @click="copyCurl" class="text-xs text-brand-600 hover:text-brand-500 dark:text-brand-400 dark:hover:text-brand-300 font-medium transition-colors">
                  {{ curlCopied ? 'Copied!' : 'Copy' }}
                </button>
              </div>
              <pre class="panel p-3.5 text-xs font-mono text-gray-700 dark:text-gray-300 overflow-x-auto text-left">curl -X POST {{ endpoint.capture_url }} \
  -H "Content-Type: application/json" \
  -d '{"hello":"world"}'</pre>
            </div>
          </div>
        </div>
      </div>
    </div>

    <div v-else-if="notFound" class="flex-1 flex items-center justify-center">
      <p class="text-gray-500">Endpoint not found.</p>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { endpointsApi } from '@/api/endpoints'
import { useLiveTail } from '@/composables/useLiveTail'
import RequestDetail from '@/components/RequestDetail.vue'
import AppHeader from '@/components/AppHeader.vue'

const route  = useRoute()
const router = useRouter()
const token  = route.params.token

const PER_PAGE = 25

const endpoint       = ref(null)
const requests       = ref([])
const meta           = ref({ current_page: 1, last_page: 1, total: 0 })
const loadingMore    = ref(false)
const selectedId     = ref(null)
const selectedRequest = ref(null)
const notFound       = ref(false)
const copied         = ref(false)
const selectedIds    = ref([])

// ── Load endpoint + initial requests ──────────────────────────────────────────
onMounted(async () => {
  try {
    const [epRes, reqRes] = await Promise.all([
      endpointsApi.get(token),
      endpointsApi.listRequests(token),
    ])
    endpoint.value = epRes.data.data
    requests.value = reqRes.data.data
    meta.value     = reqRes.data.meta
  } catch (e) {
    if (e.response?.status === 404) notFound.value = true
  }
})

// ── Pagination — the list only ever holds the first page until asked for more ──
async function loadMore() {
  if (loadingMore.value || meta.value.current_page >= meta.value.last_page) return
  loadingMore.value = true
  try {
    const res = await endpointsApi.listRequests(token, meta.value.current_page + 1)
    requests.value = [...requests.value, ...res.data.data]
    meta.value = res.data.meta
  } finally {
    loadingMore.value = false
  }
}

// ── Live-tail (polling) ────────────────────────────────────────────────────────
// Re-fetches the newest page on a timer and prepends anything not seen yet.
const { connected: liveConnected, stop: stopLiveTail } = useLiveTail(async () => {
  if (!endpoint.value) return
  const res = await endpointsApi.listRequests(token)
  const fresh = res.data.data
  const seen = new Set(requests.value.map(r => r.id))
  const incoming = fresh.filter(r => !seen.has(r.id))
  if (incoming.length) {
    requests.value = [...incoming, ...requests.value]
  }
  if (res.data.meta) {
    // Keep the running total/page-count fresh without resetting our own
    // pagination position (current_page is what WE loaded up to).
    meta.value = { ...meta.value, total: res.data.meta.total, last_page: res.data.meta.last_page }
    if (endpoint.value) endpoint.value.request_count = res.data.meta.total
  }
})

onUnmounted(() => stopLiveTail())

// ── Request selection ──────────────────────────────────────────────────────────
async function selectRequest(summary) {
  selectedId.value = summary.id
  // Fetch full detail on demand
  const res = await endpointsApi.getRequest(token, summary.id)
  selectedRequest.value = res.data.data
}

function backToList() {
  selectedId.value      = null
  selectedRequest.value = null
}

// ── Selection ──────────────────────────────────────────────────────────────────
const allSelected = computed(() =>
  requests.value.length > 0 && selectedIds.value.length === requests.value.length
)
function isSelected(id) {
  return selectedIds.value.includes(id)
}
function toggleSelect(id) {
  selectedIds.value = isSelected(id)
    ? selectedIds.value.filter(x => x !== id)
    : [...selectedIds.value, id]
}
function toggleSelectAll() {
  selectedIds.value = allSelected.value ? [] : requests.value.map(r => r.id)
}

// ── Delete actions ───────────────────────────────────────────────────────────────
async function deleteOne(req) {
  await endpointsApi.deleteRequest(token, req.id)
  removeLocally([req.id])
}

async function deleteSelected() {
  if (!selectedIds.value.length) return
  const ids = [...selectedIds.value]
  await endpointsApi.deleteRequests(token, ids)
  removeLocally(ids)
}

async function clearAll() {
  await endpointsApi.clearRequests(token)
  requests.value        = []
  meta.value             = { current_page: 1, last_page: 1, total: 0 }
  selectedIds.value     = []
  selectedRequest.value = null
  selectedId.value      = null
  if (endpoint.value)   endpoint.value.request_count = 0
}

// Drop deleted requests from the list + selection, keep counters in sync.
function removeLocally(ids) {
  const set = new Set(ids)
  requests.value    = requests.value.filter(r => !set.has(r.id))
  selectedIds.value = selectedIds.value.filter(id => !set.has(id))
  if (selectedId.value && set.has(selectedId.value)) {
    selectedId.value      = null
    selectedRequest.value = null
  }
  meta.value.total = Math.max(0, meta.value.total - ids.length)
  meta.value.last_page = Math.max(1, Math.ceil(meta.value.total / PER_PAGE))
  if (endpoint.value) {
    endpoint.value.request_count = meta.value.total
  }
}

async function copyUrl() {
  await navigator.clipboard.writeText(endpoint.value.capture_url)
  copied.value = true
  setTimeout(() => (copied.value = false), 2000)
}

const curlCopied = ref(false)
async function copyCurl() {
  const cmd = `curl -X POST ${endpoint.value.capture_url} \\\n  -H "Content-Type: application/json" \\\n  -d '{"hello":"world"}'`
  await navigator.clipboard.writeText(cmd)
  curlCopied.value = true
  setTimeout(() => (curlCopied.value = false), 2000)
}

// ── Helpers ────────────────────────────────────────────────────────────────────
function timeAgo(iso) {
  const s = Math.floor((Date.now() - new Date(iso)) / 1000)
  if (s < 60)    return `${s}s`
  if (s < 3600)  return `${Math.floor(s / 60)}m`
  return `${Math.floor(s / 3600)}h`
}

function formatBytes(b) {
  if (!b) return '0 B'
  if (b < 1024) return `${b} B`
  return `${(b / 1024).toFixed(1)} KB`
}
</script>
