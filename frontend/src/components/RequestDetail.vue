<template>
  <div class="p-6 xl:p-8 max-w-6xl">
    <!-- Request line -->
    <div class="flex items-center gap-3 mb-6">
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
          <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2.5">Headers</h3>
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
            <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Body</h3>
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
          <pre class="panel p-4 text-xs text-gray-800 dark:text-gray-200 font-mono overflow-x-auto whitespace-pre-wrap break-all max-h-[32rem]">{{ bodyView === 'pretty' ? (request.body_pretty || request.body_raw) : request.body_raw }}</pre>
        </div>
        <div v-else class="panel p-4 text-xs text-gray-400 dark:text-gray-600 text-center">(empty body)</div>
      </div>

      <!-- Sidebar -->
      <div class="xl:col-span-1 space-y-7 min-w-0 xl:order-2">
        <!-- Meta -->
        <div class="grid grid-cols-2 gap-4 text-xs panel p-4">
          <div>
            <span class="text-gray-500 dark:text-gray-500">Received</span>
            <p class="text-gray-800 dark:text-gray-200 mt-1 font-medium">{{ formatDate(request.received_at) }}</p>
          </div>
          <div>
            <span class="text-gray-500 dark:text-gray-500">IP</span>
            <p class="text-gray-800 dark:text-gray-200 mt-1 font-mono truncate">{{ request.ip_address || '—' }}</p>
          </div>
          <div>
            <span class="text-gray-500 dark:text-gray-500">Content-Type</span>
            <p class="text-gray-800 dark:text-gray-200 mt-1 font-mono truncate">{{ request.content_type || '—' }}</p>
          </div>
          <div>
            <span class="text-gray-500 dark:text-gray-500">Body size</span>
            <p class="text-gray-800 dark:text-gray-200 mt-1 font-medium">{{ formatBytes(request.body_size) }}</p>
          </div>
        </div>

        <!-- Replay -->
        <div>
          <div class="flex items-center gap-2 mb-2.5">
            <svg class="w-3.5 h-3.5 text-gray-400 dark:text-gray-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
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
          <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2.5">Query Params</h3>
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
          <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2.5">User-Agent</h3>
          <p class="panel p-3 text-xs text-gray-600 dark:text-gray-400 break-all font-mono">{{ request.user_agent }}</p>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { endpointsApi } from '@/api/endpoints'

const props = defineProps({
  request: { type: Object, required: true },
  token: { type: String, required: true },
})

const bodyView = ref('pretty')

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
  return new Date(iso).toLocaleString()
}

function formatBytes(b) {
  if (!b) return '0 B'
  if (b < 1024) return `${b} B`
  return `${(b / 1024).toFixed(1)} KB`
}
</script>
