<template>
  <div class="p-6 space-y-6">
    <!-- Request line -->
    <div class="flex items-center gap-3">
      <span :class="`method-${request.method} text-sm px-2 py-1 rounded font-bold`">
        {{ request.method }}
      </span>
      <span class="text-gray-900 dark:text-white font-mono text-sm break-all">
        {{ request.path || '/' }}{{ request.query_string ? '?' + request.query_string : '' }}
      </span>
    </div>

    <!-- Meta -->
    <div class="grid grid-cols-2 gap-3 text-xs">
      <div>
        <span class="text-gray-500">Received</span>
        <p class="text-gray-800 dark:text-gray-200 mt-0.5">{{ formatDate(request.received_at) }}</p>
      </div>
      <div>
        <span class="text-gray-500">IP</span>
        <p class="text-gray-800 dark:text-gray-200 mt-0.5 font-mono">{{ request.ip_address || '—' }}</p>
      </div>
      <div>
        <span class="text-gray-500">Content-Type</span>
        <p class="text-gray-800 dark:text-gray-200 mt-0.5 font-mono">{{ request.content_type || '—' }}</p>
      </div>
      <div>
        <span class="text-gray-500">Body size</span>
        <p class="text-gray-800 dark:text-gray-200 mt-0.5">{{ formatBytes(request.body_size) }}</p>
      </div>
    </div>

    <!-- User Agent -->
    <div v-if="request.user_agent" class="text-xs">
      <span class="text-gray-500">User-Agent</span>
      <p class="text-gray-500 dark:text-gray-400 mt-0.5 break-all">{{ request.user_agent }}</p>
    </div>

    <!-- Headers -->
    <div>
      <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Headers</h3>
      <div class="bg-gray-100 dark:bg-gray-900 rounded divide-y divide-gray-200 dark:divide-gray-800">
        <div
          v-for="(values, name) in request.headers"
          :key="name"
          class="px-3 py-2 flex gap-3 text-xs font-mono"
        >
          <span class="text-gray-500 dark:text-gray-400 shrink-0 w-48 truncate">{{ name }}</span>
          <span class="text-gray-800 dark:text-gray-200 break-all">{{ Array.isArray(values) ? values.join(', ') : values }}</span>
        </div>
      </div>
    </div>

    <!-- Query Params -->
    <div v-if="request.query_string">
      <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider mb-2">Query Params</h3>
      <div class="bg-gray-100 dark:bg-gray-900 rounded divide-y divide-gray-200 dark:divide-gray-800">
        <div
          v-for="(value, key) in request.query_params"
          :key="key"
          class="px-3 py-2 flex gap-3 text-xs font-mono"
        >
          <span class="text-gray-500 dark:text-gray-400 shrink-0 w-48 truncate">{{ key }}</span>
          <span class="text-gray-800 dark:text-gray-200 break-all">{{ value }}</span>
        </div>
      </div>
    </div>

    <!-- Body -->
    <div v-if="request.body_raw">
      <div class="flex items-center justify-between mb-2">
        <h3 class="text-xs font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider">Body</h3>
        <div class="flex items-center gap-2">
          <button
            @click="bodyView = 'pretty'"
            :class="bodyView === 'pretty' ? 'text-gray-900 dark:text-white' : 'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'"
            class="text-xs transition-colors"
          >Pretty</button>
          <span class="text-gray-300 dark:text-gray-700">|</span>
          <button
            @click="bodyView = 'raw'"
            :class="bodyView === 'raw' ? 'text-gray-900 dark:text-white' : 'text-gray-500 hover:text-gray-700 dark:hover:text-gray-300'"
            class="text-xs transition-colors"
          >Raw</button>
        </div>
      </div>
      <pre class="bg-gray-100 dark:bg-gray-900 rounded p-4 text-xs text-gray-800 dark:text-gray-200 font-mono overflow-x-auto whitespace-pre-wrap break-all max-h-96">{{ bodyView === 'pretty' ? (request.body_pretty || request.body_raw) : request.body_raw }}</pre>
    </div>
    <div v-else class="text-xs text-gray-400 dark:text-gray-600">(empty body)</div>
  </div>
</template>

<script setup>
import { ref } from 'vue'

const props = defineProps({
  request: { type: Object, required: true },
})

const bodyView = ref('pretty')

function formatDate(iso) {
  return new Date(iso).toLocaleString()
}

function formatBytes(b) {
  if (!b) return '0 B'
  if (b < 1024) return `${b} B`
  return `${(b / 1024).toFixed(1)} KB`
}
</script>
