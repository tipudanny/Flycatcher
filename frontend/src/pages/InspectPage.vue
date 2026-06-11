<template>
  <div class="min-h-screen bg-gray-50 dark:bg-gray-950 flex flex-col">
    <!-- Nav -->
    <header class="border-b border-gray-200 dark:border-gray-800 px-6 py-3 flex items-center justify-between">
      <span class="text-gray-900 dark:text-white font-semibold text-sm">Webhook Inspector</span>
      <div class="flex items-center gap-3">
        <span v-if="liveConnected" class="flex items-center gap-1.5 text-xs text-green-600 dark:text-green-400">
          <span class="w-1.5 h-1.5 rounded-full bg-green-500 dark:bg-green-400 animate-pulse"></span>
          Live
        </span>
        <ThemeToggle />
        <router-link
          v-if="!auth.isAuthenticated"
          to="/register"
          class="text-xs bg-indigo-600 hover:bg-indigo-500 text-white px-3 py-1.5 rounded transition-colors"
        >
          Claim this URL →
        </router-link>
        <router-link
          v-if="!auth.isAuthenticated"
          to="/login"
          class="text-xs border border-gray-300 dark:border-gray-700 hover:border-indigo-500 dark:hover:border-indigo-500 text-gray-700 dark:text-gray-300 px-3 py-1.5 rounded transition-colors"
        >
          Login
        </router-link>
      </div>
    </header>

    <div v-if="endpoint" class="flex-1 flex flex-col">
      <!-- Capture URL bar -->
      <div class="border-b border-gray-200 dark:border-gray-800 px-6 py-3 flex items-center gap-3">
        <span class="text-xs text-gray-500 dark:text-gray-400 shrink-0">Your webhook URL</span>
        <code class="flex-1 text-xs text-indigo-600 dark:text-indigo-300 font-mono truncate bg-gray-100 dark:bg-gray-900 px-2 py-1 rounded">
          {{ endpoint.capture_url }}
        </code>
        <button @click="copyUrl" class="text-xs text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white shrink-0 transition-colors">
          {{ copied ? 'Copied!' : 'Copy' }}
        </button>
      </div>

      <!-- Guest quota / retention bar -->
      <div
        v-if="endpoint.type === 'guest' && !limitReached"
        class="border-b border-gray-200 dark:border-gray-800 px-6 py-2 flex items-center gap-3 text-xs text-gray-500 dark:text-gray-400"
      >
        <span>{{ endpoint.request_count }} / {{ endpoint.request_limit }} requests used</span>
        <span>·</span>
        <span v-if="endpoint.expires_at">URL and data auto-delete {{ expiresIn(endpoint.expires_at) }}</span>
        <router-link to="/register" class="ml-auto text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300">
          Register to remove limits →
        </router-link>
      </div>

      <!-- Limit reached banner -->
      <div
        v-if="limitReached"
        class="border-b border-amber-300 dark:border-amber-500/30 bg-amber-50 dark:bg-amber-500/10 px-6 py-2.5 text-xs text-amber-800 dark:text-amber-300"
      >
        <strong>Request limit reached ({{ endpoint.request_limit }}).</strong>
        This URL no longer accepts new requests — captured data stays viewable here.
        <router-link to="/register" class="underline hover:no-underline">Register to keep this data and get unlimited URLs</router-link>,
        or open the app in a different browser for a fresh guest URL.
      </div>

      <!-- Two-pane layout -->
      <div class="flex-1 flex overflow-hidden">
        <div class="w-80 border-r border-gray-200 dark:border-gray-800 flex flex-col overflow-hidden">
          <div class="px-4 py-2 border-b border-gray-200 dark:border-gray-800">
            <span class="text-xs text-gray-500 dark:text-gray-400">{{ requests.length }} request{{ requests.length !== 1 ? 's' : '' }}</span>
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
                <span :class="`method-${req.method} text-xs px-1.5 py-0.5 rounded font-bold`">{{ req.method }}</span>
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
              Waiting for requests… Send something to the URL above.
            </div>
          </div>
        </div>

        <div class="flex-1 overflow-y-auto">
          <RequestDetail v-if="selectedRequest" :request="selectedRequest" />
          <div v-else class="flex items-center justify-center h-full text-gray-400 dark:text-gray-600 text-sm">
            Select a request
          </div>
        </div>
      </div>
    </div>

    <div v-else-if="notFound" class="flex-1 flex items-center justify-center">
      <p class="text-gray-500">This URL has expired or doesn't exist.</p>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useRoute } from 'vue-router'
import { endpointsApi } from '@/api/endpoints'
import { useAuthStore } from '@/stores/auth'
import { useFirebaseLiveTail } from '@/composables/useFirebaseLiveTail'
import RequestDetail from '@/components/RequestDetail.vue'
import ThemeToggle from '@/components/ThemeToggle.vue'

const route   = useRoute()
const auth    = useAuthStore()
const token   = route.params.token

const endpoint        = ref(null)
const requests        = ref([])
const selectedId      = ref(null)
const selectedRequest = ref(null)
const notFound        = ref(false)
const copied          = ref(false)

onMounted(async () => {
  try {
    const [epRes, reqRes] = await Promise.all([
      endpointsApi.get(token),
      endpointsApi.listRequests(token),
    ])
    endpoint.value = epRes.data.data
    requests.value = reqRes.data.data
  } catch {
    notFound.value = true
  }
})

const limitReached = computed(() =>
  endpoint.value?.request_limit != null &&
  endpoint.value.request_count >= endpoint.value.request_limit
)

const { connected: liveConnected, stop: stopLiveTail } = useFirebaseLiveTail(
  () => endpoint.value?.id ?? null,
  (summary) => {
    if (!requests.value.find(r => r.id === summary.id)) {
      requests.value.unshift(summary)
      if (endpoint.value) endpoint.value.request_count++
    }
  }
)

onUnmounted(() => stopLiveTail())

async function selectRequest(summary) {
  selectedId.value = summary.id
  const res = await endpointsApi.getRequest(token, summary.id)
  selectedRequest.value = res.data.data
}

async function copyUrl() {
  await navigator.clipboard.writeText(endpoint.value.capture_url)
  copied.value = true
  setTimeout(() => (copied.value = false), 2000)
}

function timeAgo(iso) {
  const s = Math.floor((Date.now() - new Date(iso)) / 1000)
  if (s < 60)   return `${s}s`
  if (s < 3600) return `${Math.floor(s / 60)}m`
  return `${Math.floor(s / 3600)}h`
}

function expiresIn(iso) {
  const h = Math.max(0, Math.round((new Date(iso) - Date.now()) / 3600000))
  if (h < 1)  return 'within the hour'
  if (h < 48) return `in ${h}h`
  return `in ${Math.round(h / 24)} days`
}

function formatBytes(b) {
  if (!b) return '0 B'
  if (b < 1024) return `${b} B`
  return `${(b / 1024).toFixed(1)} KB`
}
</script>
