<template>
  <div class="min-h-screen bg-gray-50 dark:bg-gray-950 flex flex-col">
    <!-- Nav -->
    <header class="border-b border-gray-200 dark:border-gray-800 px-6 py-3 flex items-center justify-between">
      <span class="flex items-center gap-2">
        <img src="/favicon.svg" alt="" class="w-6 h-6" />
        <span class="text-gray-900 dark:text-white font-semibold">Flycatcher</span>
        <span class="text-xs px-1.5 py-0.5 rounded bg-indigo-100 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300">admin</span>
      </span>
      <div class="flex items-center gap-4">
        <ThemeToggle />
        <button @click="router.push('/')" class="text-xs text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white transition-colors">
          ← Back to app
        </button>
      </div>
    </header>

    <main class="flex-1 max-w-6xl mx-auto w-full px-4 py-8 space-y-8">
      <!-- Stats cards -->
      <section>
        <h2 class="text-gray-900 dark:text-white font-medium mb-3">Overview</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
          <div v-for="card in statCards" :key="card.label" class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-lg p-4">
            <div class="text-2xl font-semibold text-gray-900 dark:text-white">{{ card.value }}</div>
            <div class="text-xs text-gray-500 mt-1">{{ card.label }}</div>
          </div>
        </div>
      </section>

      <!-- Browser extension settings -->
      <section v-if="settings">
        <h2 class="text-gray-900 dark:text-white font-medium mb-3">Browser extension</h2>
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-lg p-4 space-y-4 max-w-2xl">
          <label class="flex items-center justify-between gap-4">
            <span class="text-sm text-gray-700 dark:text-gray-300">Extension enabled
              <span class="block text-xs text-gray-500">Master switch — when off, the extension stops polling and clears its badge.</span>
            </span>
            <input type="checkbox" v-model="settings.extension_enabled" class="h-4 w-4 accent-indigo-600" />
          </label>
          <label class="flex items-center justify-between gap-4">
            <span class="text-sm text-gray-700 dark:text-gray-300">Desktop notifications
              <span class="block text-xs text-gray-500">Pop a notification when a new request arrives.</span>
            </span>
            <input type="checkbox" v-model="settings.extension_notifications" class="h-4 w-4 accent-indigo-600" />
          </label>
          <label class="flex items-center justify-between gap-4">
            <span class="text-sm text-gray-700 dark:text-gray-300">Unread badge
              <span class="block text-xs text-gray-500">Show a count of new requests on the extension icon.</span>
            </span>
            <input type="checkbox" v-model="settings.extension_badge" class="h-4 w-4 accent-indigo-600" />
          </label>
          <label class="flex items-center justify-between gap-4">
            <span class="text-sm text-gray-700 dark:text-gray-300">Poll interval (seconds)
              <span class="block text-xs text-gray-500">How often the extension checks for new requests. Chrome enforces a ~30s floor.</span>
            </span>
            <input type="number" min="15" max="3600" v-model.number="settings.extension_poll_interval"
              class="w-24 bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-700 rounded px-2 py-1 text-sm text-gray-900 dark:text-white focus:outline-none focus:border-indigo-500" />
          </label>
          <label class="flex items-center justify-between gap-4">
            <span class="text-sm text-gray-700 dark:text-gray-300">App URL
              <span class="block text-xs text-gray-500">Where the extension's “open in app” links point.</span>
            </span>
            <input type="url" v-model="settings.app_url" placeholder="http://localhost:5173"
              class="w-64 bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-700 rounded px-2 py-1 text-sm text-gray-900 dark:text-white focus:outline-none focus:border-indigo-500" />
          </label>

          <div class="flex items-center gap-3 pt-1">
            <button @click="saveSettings" :disabled="savingSettings"
              class="bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white text-xs px-3 py-1.5 rounded transition-colors">
              {{ savingSettings ? 'Saving…' : 'Save settings' }}
            </button>
            <span v-if="settingsSaved" class="text-xs text-green-600 dark:text-green-400">Saved ✓</span>
            <span v-if="settingsError" class="text-xs text-red-500 dark:text-red-400">{{ settingsError }}</span>
          </div>
        </div>
      </section>

      <!-- Plan reference -->
      <section v-if="plans">
        <h2 class="text-gray-900 dark:text-white font-medium mb-3">Plans</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
          <div v-for="(p, key) in plans" :key="key" class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-lg p-4 space-y-1.5">
            <div class="flex items-center justify-between">
              <span class="font-medium text-gray-900 dark:text-white capitalize">{{ p.label || key }}</span>
              <span class="text-xs text-gray-500">{{ stats?.users_by_plan?.[key] ?? 0 }} users</span>
            </div>
            <ul class="text-xs text-gray-500 dark:text-gray-400 space-y-0.5">
              <li>{{ fmtLimit(p.max_endpoints) }} URLs</li>
              <li>{{ fmtLimit(p.request_limit) }} requests / URL</li>
              <li>{{ p.retention_days === null ? 'Forever' : p.retention_days + '-day' }} retention</li>
              <li>Custom responses: {{ p.custom_responses ? 'yes' : 'no' }}</li>
            </ul>
          </div>
        </div>
      </section>

      <!-- Users -->
      <section>
        <div class="flex items-center justify-between mb-3">
          <h2 class="text-gray-900 dark:text-white font-medium">Users</h2>
          <input
            v-model="userQuery"
            @input="debouncedLoadUsers"
            type="search"
            placeholder="Search email…"
            class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded px-3 py-1.5 text-xs text-gray-900 dark:text-white w-56 focus:outline-none focus:border-indigo-500"
          />
        </div>
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-lg overflow-hidden">
          <table class="w-full text-sm">
            <thead class="text-xs text-gray-500 border-b border-gray-200 dark:border-gray-800">
              <tr>
                <th class="text-left font-medium px-4 py-2">Email</th>
                <th class="text-left font-medium px-4 py-2">Plan</th>
                <th class="text-left font-medium px-4 py-2">Status</th>
                <th class="text-right font-medium px-4 py-2">URLs</th>
                <th class="text-right font-medium px-4 py-2">Joined</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="u in users" :key="u.id" class="border-b border-gray-100 dark:border-gray-800/60 last:border-0">
                <td class="px-4 py-2.5 text-gray-900 dark:text-gray-200">
                  {{ u.email }}
                  <span v-if="u.is_admin" class="ml-1 text-xs px-1 py-0.5 rounded bg-indigo-100 text-indigo-700 dark:bg-indigo-500/20 dark:text-indigo-300">admin</span>
                </td>
                <td class="px-4 py-2.5">
                  <select
                    :value="u.plan"
                    @change="changePlan(u, $event.target.value)"
                    :disabled="savingId === u.id"
                    class="bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-700 rounded px-2 py-1 text-xs text-gray-900 dark:text-white capitalize focus:outline-none focus:border-indigo-500"
                  >
                    <option v-for="(p, key) in plans" :key="key" :value="key">{{ p.label || key }}</option>
                  </select>
                </td>
                <td class="px-4 py-2.5">
                  <button
                    @click="toggleStatus(u)"
                    :disabled="savingId === u.id"
                    :class="u.status === 'active'
                      ? 'bg-green-100 text-green-700 dark:bg-green-500/15 dark:text-green-300'
                      : 'bg-red-100 text-red-700 dark:bg-red-500/15 dark:text-red-300'"
                    class="text-xs px-2 py-1 rounded transition-colors"
                  >
                    {{ u.status === 'active' ? 'Active' : 'Suspended' }}
                  </button>
                </td>
                <td class="px-4 py-2.5 text-right text-gray-700 dark:text-gray-300">{{ u.endpoints_count }}</td>
                <td class="px-4 py-2.5 text-right text-gray-500 text-xs">{{ fmtDate(u.created_at) }}</td>
              </tr>
              <tr v-if="!users.length">
                <td colspan="5" class="px-4 py-8 text-center text-gray-400 dark:text-gray-600 text-xs">No users found.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- Endpoints -->
      <section>
        <div class="flex items-center justify-between mb-3">
          <h2 class="text-gray-900 dark:text-white font-medium">All endpoints</h2>
          <input
            v-model="endpointQuery"
            @input="debouncedLoadEndpoints"
            type="search"
            placeholder="Search token / label…"
            class="bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded px-3 py-1.5 text-xs text-gray-900 dark:text-white w-56 focus:outline-none focus:border-indigo-500"
          />
        </div>
        <div class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-lg overflow-hidden">
          <table class="w-full text-sm">
            <thead class="text-xs text-gray-500 border-b border-gray-200 dark:border-gray-800">
              <tr>
                <th class="text-left font-medium px-4 py-2">URL token</th>
                <th class="text-left font-medium px-4 py-2">Owner</th>
                <th class="text-left font-medium px-4 py-2">Type</th>
                <th class="text-right font-medium px-4 py-2">Requests</th>
                <th class="text-right font-medium px-4 py-2">Last activity</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="e in endpoints" :key="e.token" class="border-b border-gray-100 dark:border-gray-800/60 last:border-0">
                <td class="px-4 py-2.5 font-mono text-xs text-gray-900 dark:text-gray-200">
                  {{ e.token }}
                  <span v-if="e.label" class="text-gray-400">· {{ e.label }}</span>
                </td>
                <td class="px-4 py-2.5 text-gray-700 dark:text-gray-300 text-xs">{{ e.owner_email || 'guest' }}</td>
                <td class="px-4 py-2.5">
                  <span class="text-xs px-1.5 py-0.5 rounded bg-gray-200 text-gray-600 dark:bg-gray-800 dark:text-gray-400">{{ e.type }}</span>
                </td>
                <td class="px-4 py-2.5 text-right text-gray-700 dark:text-gray-300">{{ e.request_count }}</td>
                <td class="px-4 py-2.5 text-right text-gray-500 text-xs">{{ e.last_activity_at ? fmtDate(e.last_activity_at) : '—' }}</td>
              </tr>
              <tr v-if="!endpoints.length">
                <td colspan="5" class="px-4 py-8 text-center text-gray-400 dark:text-gray-600 text-xs">No endpoints found.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </main>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { adminApi } from '@/api/admin'
import ThemeToggle from '@/components/ThemeToggle.vue'

const router = useRouter()

const stats     = ref(null)
const plans     = ref(null)
const users     = ref([])
const endpoints = ref([])
const savingId  = ref(null)
const userQuery     = ref('')
const endpointQuery = ref('')

const settings       = ref(null)
const savingSettings = ref(false)
const settingsSaved  = ref(false)
const settingsError  = ref('')

const statCards = computed(() => stats.value ? [
  { label: 'Users',            value: stats.value.users_total },
  { label: 'Suspended',        value: stats.value.users_suspended },
  { label: 'Endpoints',        value: stats.value.endpoints_total },
  { label: 'Guest endpoints',  value: stats.value.endpoints_guest },
  { label: 'Requests total',   value: stats.value.requests_total },
  { label: 'Requests today',   value: stats.value.requests_today },
] : [])

onMounted(async () => {
  const [s, p, cfg] = await Promise.all([adminApi.stats(), adminApi.plans(), adminApi.settings()])
  stats.value = s.data.data
  plans.value = p.data.data
  settings.value = cfg.data.data
  await Promise.all([loadUsers(), loadEndpoints()])
})

async function saveSettings() {
  savingSettings.value = true
  settingsSaved.value  = false
  settingsError.value  = ''
  try {
    const res = await adminApi.updateSettings({
      extension_enabled:       settings.value.extension_enabled,
      extension_notifications: settings.value.extension_notifications,
      extension_badge:         settings.value.extension_badge,
      extension_poll_interval: settings.value.extension_poll_interval,
      app_url:                 settings.value.app_url || null,
    })
    settings.value = res.data.data
    settingsSaved.value = true
    setTimeout(() => (settingsSaved.value = false), 2500)
  } catch (e) {
    const errors = e.response?.data?.errors
    settingsError.value = errors ? Object.values(errors).flat().join(' ') : 'Could not save.'
  } finally {
    savingSettings.value = false
  }
}

async function loadUsers() {
  const res = await adminApi.users(userQuery.value)
  users.value = res.data.data
}
async function loadEndpoints() {
  const res = await adminApi.endpoints(endpointQuery.value)
  endpoints.value = res.data.data
}

let uTimer, eTimer
function debouncedLoadUsers()     { clearTimeout(uTimer); uTimer = setTimeout(loadUsers, 300) }
function debouncedLoadEndpoints() { clearTimeout(eTimer); eTimer = setTimeout(loadEndpoints, 300) }

async function changePlan(user, plan) {
  savingId.value = user.id
  try {
    const res = await adminApi.updateUser(user.id, { plan })
    Object.assign(user, res.data.data)
    await refreshStats()
  } finally {
    savingId.value = null
  }
}

async function toggleStatus(user) {
  savingId.value = user.id
  try {
    const status = user.status === 'active' ? 'suspended' : 'active'
    const res = await adminApi.updateUser(user.id, { status })
    Object.assign(user, res.data.data)
    await refreshStats()
  } finally {
    savingId.value = null
  }
}

async function refreshStats() {
  const s = await adminApi.stats()
  stats.value = s.data.data
}

function fmtLimit(v) { return v === null || v === undefined ? 'Unlimited' : v.toLocaleString() }
function fmtDate(iso) { return new Date(iso).toLocaleDateString() }
</script>
