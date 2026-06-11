<template>
  <div class="min-h-screen bg-gray-50 dark:bg-gray-950 flex flex-col">
    <!-- Nav -->
    <header class="border-b border-gray-200 dark:border-gray-800 px-6 py-3 flex items-center gap-4">
      <button @click="router.push('/')" class="text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white text-xs transition-colors">
        ← Dashboard
      </button>
      <span class="text-gray-400 dark:text-gray-600">|</span>
      <span class="text-gray-900 dark:text-white text-sm font-medium truncate">
        {{ endpoint?.label || endpoint?.token }}
      </span>
      <div class="ml-auto flex items-center gap-4">
        <span v-if="liveConnected" class="flex items-center gap-1.5 text-xs text-green-600 dark:text-green-400">
          <span class="w-1.5 h-1.5 rounded-full bg-green-500 dark:bg-green-400 animate-pulse"></span>
          Live
        </span>
        <ThemeToggle />
      </div>
    </header>

    <div v-if="endpoint" class="flex-1 flex flex-col">
      <!-- Capture URL bar -->
      <div class="border-b border-gray-200 dark:border-gray-800 px-6 py-3 flex items-center gap-3">
        <span class="text-xs text-gray-500 dark:text-gray-400 shrink-0">Capture URL</span>
        <code class="flex-1 text-xs text-indigo-600 dark:text-indigo-300 font-mono truncate bg-gray-100 dark:bg-gray-900 px-2 py-1 rounded">
          {{ endpoint.capture_url }}
        </code>
        <button @click="copyUrl" class="text-xs text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white shrink-0 transition-colors">
          {{ copied ? 'Copied!' : 'Copy' }}
        </button>
      </div>

      <!-- Two-pane layout -->
      <div class="flex-1 flex overflow-hidden">
        <!-- Left: request list -->
        <div class="w-80 border-r border-gray-200 dark:border-gray-800 flex flex-col overflow-hidden">
          <div class="px-4 py-2 flex items-center justify-between border-b border-gray-200 dark:border-gray-800">
            <span class="text-xs text-gray-500 dark:text-gray-400">Requests ({{ endpoint.request_count }})</span>
            <button
              v-if="requests.length"
              @click="clearAll"
              class="text-xs text-red-500 hover:text-red-600 dark:text-red-400 dark:hover:text-red-300 transition-colors"
            >
              Clear all
            </button>
          </div>

          <div class="flex-1 overflow-y-auto">
            <div
              v-for="req in requests"
              :key="req.id"
              @click="selectRequest(req)"
              :class="[
                'px-4 py-3 border-b border-gray-200 dark:border-gray-800 cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-900 transition-colors',
                selectedId === req.id ? 'bg-gray-100 dark:bg-gray-900 border-l-2 border-l-indigo-500' : ''
              ]"
            >
              <div class="flex items-center gap-2">
                <span :class="`method-${req.method} text-xs px-1.5 py-0.5 rounded font-bold`">
                  {{ req.method }}
                </span>
                <span class="text-gray-700 dark:text-gray-300 text-xs truncate">{{ req.path || '/' }}</span>
              </div>
              <div class="mt-1 flex items-center gap-2 text-xs text-gray-500">
                <span>{{ req.content_type?.split(';')[0] || '—' }}</span>
                <span>·</span>
                <span>{{ formatBytes(req.body_size) }}</span>
                <span class="ml-auto">{{ timeAgo(req.received_at) }}</span>
              </div>
            </div>

            <div v-if="!requests.length" class="p-6 text-center text-gray-400 dark:text-gray-600 text-xs">
              Waiting for requests…
            </div>
          </div>
        </div>

        <!-- Right: request detail -->
        <div class="flex-1 overflow-y-auto">
          <RequestDetail v-if="selectedRequest" :request="selectedRequest" />
          <div v-else class="flex items-center justify-center h-full text-gray-400 dark:text-gray-600 text-sm">
            Select a request to inspect
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
import { ref, onMounted, onUnmounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { endpointsApi } from '@/api/endpoints'
import { useFirebaseLiveTail } from '@/composables/useFirebaseLiveTail'
import RequestDetail from '@/components/RequestDetail.vue'
import ThemeToggle from '@/components/ThemeToggle.vue'

const route  = useRoute()
const router = useRouter()
const token  = route.params.token

const endpoint       = ref(null)
const requests       = ref([])
const selectedId     = ref(null)
const selectedRequest = ref(null)
const notFound       = ref(false)
const copied         = ref(false)

// ── Load endpoint + initial requests ──────────────────────────────────────────
onMounted(async () => {
  try {
    const [epRes, reqRes] = await Promise.all([
      endpointsApi.get(token),
      endpointsApi.listRequests(token),
    ])
    endpoint.value = epRes.data.data
    requests.value = reqRes.data.data
  } catch (e) {
    if (e.response?.status === 404) notFound.value = true
  }
})

// ── Firebase live-tail ─────────────────────────────────────────────────────────
// The composable receives the endpoint's internal ID (not token) — it's only
// used as a Firebase path key, never exposed in any URL.
const { connected: liveConnected, stop: stopLiveTail } = useFirebaseLiveTail(
  () => endpoint.value?.id ?? null,
  async (summary) => {
    // A new request arrived — prepend the summary to the list immediately,
    // then fetch full detail when the user selects it.
    const exists = requests.value.find(r => r.id === summary.id)
    if (!exists) {
      requests.value.unshift(summary)
      if (endpoint.value) endpoint.value.request_count++
    }
  }
)

onUnmounted(() => stopLiveTail())

// ── Request selection ──────────────────────────────────────────────────────────
async function selectRequest(summary) {
  selectedId.value = summary.id
  // Fetch full detail on demand
  const res = await endpointsApi.getRequest(token, summary.id)
  selectedRequest.value = res.data.data
}

// ── Actions ────────────────────────────────────────────────────────────────────
async function clearAll() {
  await endpointsApi.clearRequests(token)
  requests.value       = []
  selectedRequest.value = null
  selectedId.value      = null
  if (endpoint.value)   endpoint.value.request_count = 0
}

async function copyUrl() {
  await navigator.clipboard.writeText(endpoint.value.capture_url)
  copied.value = true
  setTimeout(() => (copied.value = false), 2000)
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
