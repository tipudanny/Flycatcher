<template>
  <div class="p-6 xl:p-8 max-w-6xl">
    <!-- Request line -->
    <div class="flex items-center gap-3 mb-6 pb-5 border-b border-gray-200/70 dark:border-gray-800/70">
      <span :class="`method-${request.method}`" class="!text-xs !px-2.5 !py-1">
        {{ request.method }}
      </span>
      <span class="text-gray-900 dark:text-white font-mono text-sm break-all">
        {{ request.path || '/' }}{{ request.query_string ? '?' + request.query_string : '' }}
      </span>
    </div>

    <!-- Wide screens: content + sidebar side by side. Narrower: single column. -->
    <div class="grid grid-cols-1 xl:grid-cols-3 gap-x-8 gap-y-7">
      <!-- Main column -->
      <div class="xl:col-span-2 space-y-7 min-w-0 xl:order-1">
        <!-- Headers -->
        <div>
          <div class="flex items-center gap-2 mb-2.5">
            <svg class="w-3.5 h-3.5 text-brand-500 dark:text-brand-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01" /></svg>
            <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Headers</h3>
          </div>
          <div class="panel divide-y divide-gray-200/70 dark:divide-gray-800/70 overflow-hidden">
            <div
              v-for="(values, name) in request.headers"
              :key="name"
              class="px-3.5 py-2 flex gap-3 text-xs font-mono hover:bg-gray-100/60 dark:hover:bg-white/[0.02] transition-colors"
            >
              <span class="text-gray-500 dark:text-gray-400 shrink-0 w-48 truncate">{{ name }}</span>
              <span class="text-gray-800 dark:text-gray-200 break-all">{{ Array.isArray(values) ? values.join(', ') : values }}</span>
            </div>
          </div>
        </div>

        <!-- Body -->
        <div v-if="request.body_raw">
          <div class="flex items-center justify-between mb-2.5">
            <div class="flex items-center gap-2">
              <svg class="w-3.5 h-3.5 text-emerald-500 dark:text-emerald-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" /></svg>
              <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Body</h3>
            </div>
            <div class="flex items-center gap-2">
              <button
                @click="copyField(displayedBody, 'body')"
                class="btn-ghost !px-2 !py-1"
                title="Copy body"
              >
                <svg v-if="copiedField !== 'body'" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
                <svg v-else class="w-3.5 h-3.5 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
              </button>
              <div class="flex items-center gap-0.5 panel !bg-gray-100 dark:!bg-gray-900 p-0.5">
                <button
                  @click="bodyView = 'pretty'"
                  :class="bodyView === 'pretty' ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-soft' : 'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'"
                  class="text-xs px-2.5 py-1 rounded-md transition-all"
                >Pretty</button>
                <button
                  @click="bodyView = 'raw'"
                  :class="bodyView === 'raw' ? 'bg-white dark:bg-gray-800 text-gray-900 dark:text-white shadow-soft' : 'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'"
                  class="text-xs px-2.5 py-1 rounded-md transition-all"
                >Raw</button>
              </div>
            </div>
          </div>
          <pre class="panel p-4 text-xs text-gray-800 dark:text-gray-200 font-mono overflow-x-auto whitespace-pre-wrap break-all max-h-[32rem]">{{ displayedBody }}</pre>
        </div>
        <div v-else class="panel p-4 text-xs text-gray-400 dark:text-gray-600 text-center">(empty body)</div>
      </div>

      <!-- Sidebar -->
      <div class="xl:col-span-1 space-y-7 min-w-0 xl:order-2">
        <!-- Meta -->
        <div class="flex flex-col gap-4 text-xs panel p-4">
          <button
            type="button"
            @click="copyField(formatDate(request.received_at), 'received')"
            class="group flex items-start gap-2.5 min-w-0 w-full text-left rounded-lg -mx-1.5 px-1.5 py-1 hover:bg-gray-100/70 dark:hover:bg-white/[0.04] transition-colors"
          >
            <span class="icon-chip-blue">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l2.5 2.5M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
            </span>
            <div class="min-w-0 pt-0.5 flex-1">
              <span class="text-gray-500 dark:text-gray-500 block">Received</span>
              <p class="text-gray-800 dark:text-gray-200 mt-0.5 font-medium">{{ formatDate(request.received_at) }}</p>
            </div>
            <span class="shrink-0 pt-1 text-gray-300 dark:text-gray-700 group-hover:text-gray-500 dark:group-hover:text-gray-400 transition-colors">
              <svg v-if="copiedField !== 'received'" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
              <svg v-else class="w-3.5 h-3.5 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
            </span>
          </button>
          <button
            type="button"
            @click="copyField(request.ip_address, 'ip')"
            class="group flex items-start gap-2.5 min-w-0 w-full text-left rounded-lg -mx-1.5 px-1.5 py-1 hover:bg-gray-100/70 dark:hover:bg-white/[0.04] transition-colors"
          >
            <span class="icon-chip-violet">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 21a9 9 0 100-18 9 9 0 000 18zM3.6 9h16.8M3.6 15h16.8M12 3c2.2 2.4 3.5 5.6 3.5 9s-1.3 6.6-3.5 9c-2.2-2.4-3.5-5.6-3.5-9s1.3-6.6 3.5-9z" /></svg>
            </span>
            <div class="min-w-0 pt-0.5 flex-1">
              <span class="text-gray-500 dark:text-gray-500 block">IP</span>
              <p class="text-gray-800 dark:text-gray-200 mt-0.5 font-mono truncate">{{ request.ip_address || '—' }}</p>
            </div>
            <span class="shrink-0 pt-1 text-gray-300 dark:text-gray-700 group-hover:text-gray-500 dark:group-hover:text-gray-400 transition-colors">
              <svg v-if="copiedField !== 'ip'" class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z" /></svg>
              <svg v-else class="w-3.5 h-3.5 text-emerald-500" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" /></svg>
            </span>
          </button>
          <div class="flex items-start gap-2.5 min-w-0">
            <span class="icon-chip-teal">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.169.659 1.591l9.581 9.581a2.25 2.25 0 003.182 0l4.318-4.318a2.25 2.25 0 000-3.182L11.16 3.66A2.25 2.25 0 009.568 3z" /><circle cx="7" cy="7.5" r="1" fill="currentColor" stroke="none" /></svg>
            </span>
            <div class="min-w-0 pt-0.5">
              <span class="text-gray-500 dark:text-gray-500 block">Content-Type</span>
              <p class="text-gray-800 dark:text-gray-200 mt-0.5 font-mono truncate">{{ request.content_type || '—' }}</p>
            </div>
          </div>
          <div class="flex items-start gap-2.5 min-w-0">
            <span class="icon-chip-amber">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-8.25-4.5-8.25 4.5m16.5 0l-8.25 4.5m8.25-4.5v9l-8.25 4.5m0-9L3.75 7.5m8.25 4.5v9m0-9L3.75 7.5m0 0v9l8.25 4.5" /></svg>
            </span>
            <div class="min-w-0 pt-0.5">
              <span class="text-gray-500 dark:text-gray-500 block">Body size</span>
              <p class="text-gray-800 dark:text-gray-200 mt-0.5 font-medium">{{ formatBytes(request.body_size) }}</p>
            </div>
          </div>
        </div>

        <!-- Replay -->
        <div>
          <div class="flex items-center gap-2 mb-2.5">
            <svg class="w-3.5 h-3.5 text-violet-500 dark:text-violet-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
            <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Replay</h3>
          </div>
          <div class="space-y-2">
            <input
              v-model="targetUrl"
              type="text"
              placeholder="https://your-app.example.com/webhook"
              class="input w-full font-mono !py-1.5 text-xs"
              @keyup.enter="doReplay"
            />
            <button @click="doReplay" :disabled="!targetUrl || replaying" class="btn-primary btn-sm w-full">
              {{ replaying ? 'Sending…' : 'Replay' }}
            </button>
          </div>

          <div v-if="replayResult" class="mt-2.5 panel p-3 text-xs font-mono animate-fade-in">
            <template v-if="replayResult.ok">
              <div class="flex items-center gap-2">
                <span :class="replayResult.status < 400 ? 'badge-green' : 'badge-red'" class="!font-bold">
                  {{ replayResult.status }}
                </span>
                <span class="text-gray-500">{{ replayResult.duration_ms }}ms</span>
              </div>
              <pre v-if="replayResult.body" class="mt-2.5 whitespace-pre-wrap break-all text-gray-700 dark:text-gray-300 max-h-48 overflow-y-auto">{{ replayResult.body }}</pre>
              <p v-if="replayResult.truncated" class="mt-1 text-gray-400 dark:text-gray-600">(response truncated)</p>
            </template>
            <p v-else class="text-red-500 dark:text-red-400 flex items-start gap-1.5">
              <svg class="w-3.5 h-3.5 shrink-0 mt-0.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
              {{ replayResult.error }}
            </p>
          </div>
        </div>

        <!-- Query Params -->
        <div v-if="request.query_string">
          <div class="flex items-center gap-2 mb-2.5">
            <svg class="w-3.5 h-3.5 text-sky-500 dark:text-sky-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 4.5h18M6 9h12M10 13.5h4" /></svg>
            <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Query Params</h3>
          </div>
          <div class="panel divide-y divide-gray-200/70 dark:divide-gray-800/70 overflow-hidden">
            <div
              v-for="(value, key) in request.query_params"
              :key="key"
              class="px-3.5 py-2 flex flex-col gap-0.5 text-xs font-mono hover:bg-gray-100/60 dark:hover:bg-white/[0.02] transition-colors"
            >
              <span class="text-gray-500 dark:text-gray-400 truncate">{{ key }}</span>
              <span class="text-gray-800 dark:text-gray-200 break-all">{{ value }}</span>
            </div>
          </div>
        </div>

        <!-- User Agent -->
        <div v-if="request.user_agent">
          <div class="flex items-center gap-2 mb-2.5">
            <svg class="w-3.5 h-3.5 text-gray-400 dark:text-gray-500" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 17.25v1.007a3 3 0 01-.879 2.122L7.5 21h9l-.621-.621A3 3 0 0115 18.257V17.25m6-12V15a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 15V5.25m18 0A2.25 2.25 0 0018.75 3H5.25A2.25 2.25 0 003 5.25m18 0V12a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 12V5.25" /></svg>
            <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">User-Agent</h3>
          </div>
          <p class="panel p-3 text-xs text-gray-600 dark:text-gray-400 break-all font-mono">{{ request.user_agent }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { endpointsApi } from '@/api/endpoints'

const props = defineProps({
  request: { type: Object, required: true },
  token: { type: String, required: true },
})

const bodyView = ref('pretty')

const displayedBody = computed(() =>
  bodyView.value === 'pretty' ? (props.request.body_pretty || props.request.body_raw) : props.request.body_raw
)

// Generic click-to-copy for Received / IP / Body — shows a brief checkmark
// on whichever field was just copied.
const copiedField = ref(null)
async function copyField(text, field) {
  if (!text) return
  await navigator.clipboard.writeText(String(text))
  copiedField.value = field
  setTimeout(() => {
    if (copiedField.value === field) copiedField.value = null
  }, 1500)
}

// Replayed target usually stays the same across requests for a given
// endpoint (typically "my local dev server"), so remember it per-endpoint.
const storageKey = `replay-target:${props.token}`
const targetUrl  = ref(localStorage.getItem(storageKey) || '')
const replaying  = ref(false)
const replayResult = ref(null)

// Selecting a different request shouldn't show the previous one's result.
watch(() => props.request.id, () => { replayResult.value = null })

async function doReplay() {
  if (!targetUrl.value || replaying.value) return
  localStorage.setItem(storageKey, targetUrl.value)
  replaying.value = true
  replayResult.value = null
  try {
    const res = await endpointsApi.replayRequest(props.token, props.request.id, targetUrl.value)
    replayResult.value = res.data.data
  } catch (e) {
    replayResult.value = { ok: false, error: e.response?.data?.message || 'Replay failed.' }
  } finally {
    replaying.value = false
  }
}

function formatDate(iso) {
  const d = new Date(iso)
  const pad = (n) => String(n).padStart(2, '0')
  return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`
}

function formatBytes(b) {
  if (!b) return '0 B'
  if (b < 1024) return `${b} B`
  return `${(b / 1024).toFixed(1)} KB`
}
</script>
