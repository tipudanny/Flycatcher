<template>
  <div class="min-h-screen bg-gray-50 dark:bg-gray-950 flex flex-col">
    <AppHeader>
      <template #badge>
        <span class="badge-brand">Admin</span>
      </template>
      <template #actions>
        <button @click="router.push('/')" class="btn-ghost">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7" /></svg>
          Back to app
        </button>
      </template>
    </AppHeader>

    <main class="flex-1 max-w-6xl mx-auto w-full px-4 py-10 space-y-10">
      <!-- Stats cards -->
      <section>
        <h2 class="section-title mb-3">Overview</h2>
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
          <div v-for="card in statCards" :key="card.label" class="card p-4">
            <div :class="card.accent" class="w-7 h-7 rounded-lg flex items-center justify-center mb-2" v-html="card.icon"></div>
            <div class="text-xl font-semibold text-gray-900 dark:text-white tracking-tight tabular-nums">{{ card.value }}</div>
            <div class="text-xs text-gray-500 mt-0.5">{{ card.label }}</div>
          </div>
        </div>
      </section>

      <!-- Browser extension settings -->
      <section v-if="settings">
        <h2 class="section-title mb-3">Browser extension</h2>
        <div class="card p-5 space-y-4 max-w-2xl">
          <label class="flex items-center justify-between gap-4">
            <span class="text-sm text-gray-700 dark:text-gray-300">Extension enabled
              <span class="block text-xs text-gray-500 font-normal mt-0.5">Master switch — when off, the extension stops polling and clears its badge.</span>
            </span>
            <input type="checkbox" v-model="settings.extension_enabled" class="h-4 w-4 accent-brand-600 shrink-0" />
          </label>
          <label class="flex items-center justify-between gap-4">
            <span class="text-sm text-gray-700 dark:text-gray-300">Desktop notifications
              <span class="block text-xs text-gray-500 font-normal mt-0.5">Pop a notification when a new request arrives.</span>
            </span>
            <input type="checkbox" v-model="settings.extension_notifications" class="h-4 w-4 accent-brand-600 shrink-0" />
          </label>
          <label class="flex items-center justify-between gap-4">
            <span class="text-sm text-gray-700 dark:text-gray-300">Unread badge
              <span class="block text-xs text-gray-500 font-normal mt-0.5">Show a count of new requests on the extension icon.</span>
            </span>
            <input type="checkbox" v-model="settings.extension_badge" class="h-4 w-4 accent-brand-600 shrink-0" />
          </label>
          <label class="flex items-center justify-between gap-4">
            <span class="text-sm text-gray-700 dark:text-gray-300">Poll interval (seconds)
              <span class="block text-xs text-gray-500 font-normal mt-0.5">How often the extension checks for new requests. Chrome enforces a ~30s floor.</span>
            </span>
            <input type="number" min="15" max="3600" v-model.number="settings.extension_poll_interval" class="input w-24 !py-1 shrink-0" />
          </label>
          <label class="flex items-center justify-between gap-4">
            <span class="text-sm text-gray-700 dark:text-gray-300">App URL
              <span class="block text-xs text-gray-500 font-normal mt-0.5">Where the extension's “open in app” links point.</span>
            </span>
            <input type="url" v-model="settings.app_url" placeholder="http://localhost:5173" class="input w-64 !py-1 shrink-0" />
          </label>

          <div class="flex items-center gap-3 pt-2 border-t border-gray-100 dark:border-gray-800">
            <button @click="saveSettings" :disabled="savingSettings" class="btn-primary btn-sm">
              {{ savingSettings ? 'Saving…' : 'Save settings' }}
            </button>
            <span v-if="settingsSaved" class="badge-green">Saved</span>
            <span v-if="settingsError" class="text-xs text-red-500 dark:text-red-400">{{ settingsError }}</span>
          </div>
        </div>
      </section>

      <!-- Plan reference -->
      <section v-if="plans">
        <h2 class="section-title mb-3">Plans</h2>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
          <div v-for="(p, key) in plans" :key="key" class="card p-4 space-y-2">
            <div class="flex items-center justify-between">
              <span class="font-semibold text-gray-900 dark:text-white capitalize">{{ p.label || key }}</span>
              <span class="badge-neutral">{{ stats?.users_by_plan?.[key] ?? 0 }} users</span>
            </div>
            <ul class="text-xs text-gray-500 dark:text-gray-400 space-y-1">
              <li class="flex items-center gap-1.5"><span class="w-1 h-1 rounded-full bg-gray-300 dark:bg-gray-700"></span>{{ fmtLimit(p.max_endpoints) }} URLs</li>
              <li class="flex items-center gap-1.5"><span class="w-1 h-1 rounded-full bg-gray-300 dark:bg-gray-700"></span>{{ fmtLimit(p.request_limit) }} requests / URL</li>
              <li class="flex items-center gap-1.5"><span class="w-1 h-1 rounded-full bg-gray-300 dark:bg-gray-700"></span>{{ p.retention_days === null ? 'Forever' : p.retention_days + '-day' }} retention</li>
              <li class="flex items-center gap-1.5"><span class="w-1 h-1 rounded-full bg-gray-300 dark:bg-gray-700"></span>Custom responses: {{ p.custom_responses ? 'yes' : 'no' }}</li>
            </ul>
          </div>
        </div>
      </section>

      <!-- Users -->
      <section>
        <div class="flex flex-wrap items-center gap-2 justify-between mb-3">
          <h2 class="section-title">Users</h2>
          <div class="relative">
            <svg class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
            <input v-model="userQuery" @input="debouncedLoadUsers" type="search" placeholder="Search email…" class="input !py-1.5 pl-8 w-56 text-xs" />
          </div>
        </div>
        <div class="card overflow-hidden">
          <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="text-xs text-gray-500 border-b border-gray-200 dark:border-gray-800 bg-gray-50/60 dark:bg-white/[0.02]">
              <tr>
                <th class="text-left font-medium px-4 py-2.5">Email</th>
                <th class="text-left font-medium px-4 py-2.5">Plan</th>
                <th class="text-left font-medium px-4 py-2.5">Status</th>
                <th class="text-right font-medium px-4 py-2.5">URLs</th>
                <th class="text-right font-medium px-4 py-2.5">Joined</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="u in users" :key="u.id" class="border-b border-gray-100 dark:border-gray-800/60 last:border-0 hover:bg-gray-50/70 dark:hover:bg-white/[0.02] transition-colors">
                <td class="px-4 py-2.5 text-gray-900 dark:text-gray-200">
                  {{ u.email }}
                  <span v-if="u.is_admin" class="ml-1 badge-brand">admin</span>
                </td>
                <td class="px-4 py-2.5">
                  <select :value="u.plan" @change="changePlan(u, $event.target.value)" :disabled="savingId === u.id" class="input !py-1 !px-2 text-xs capitalize w-auto">
                    <option v-for="(p, key) in plans" :key="key" :value="key">{{ p.label || key }}</option>
                  </select>
                </td>
                <td class="px-4 py-2.5">
                  <button
                    @click="toggleStatus(u)"
                    :disabled="savingId === u.id"
                    :class="u.status === 'active' ? 'badge-green' : 'badge-red'"
                    class="hover:brightness-95 dark:hover:brightness-125 transition-all"
                    :title="u.status === 'active' ? 'Suspend this user — blocks login, endpoint access, and webhook capture' : 'Reactivate this user'"
                  >
                    {{ u.status === 'active' ? 'Active' : 'Suspended' }}
                  </button>
                </td>
                <td class="px-4 py-2.5 text-right text-gray-700 dark:text-gray-300 tabular-nums">{{ u.endpoints_count }}</td>
                <td class="px-4 py-2.5 text-right text-gray-500 text-xs">{{ fmtDate(u.created_at) }}</td>
              </tr>
              <tr v-if="!users.length">
                <td colspan="5" class="px-4 py-10 text-center text-gray-400 dark:text-gray-600 text-xs">No users found.</td>
              </tr>
            </tbody>
          </table>
          </div>
          <div v-if="usersMeta.total" class="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5 border-t border-gray-100 dark:border-gray-800 text-xs text-gray-500 dark:text-gray-400">
            <span>
              Showing <span class="font-medium text-gray-700 dark:text-gray-300">{{ usersMeta.from }}–{{ usersMeta.to }}</span>
              of <span class="font-medium text-gray-700 dark:text-gray-300">{{ usersMeta.total }}</span> users
              <span class="text-gray-300 dark:text-gray-700">·</span>
              page <span class="font-medium text-gray-700 dark:text-gray-300">{{ usersMeta.current_page }}</span> of {{ usersMeta.last_page }}
              <span class="text-gray-300 dark:text-gray-700">·</span>
              {{ usersMeta.per_page }} per page
            </span>
            <div class="flex items-center gap-2">
              <button @click="changeUsersPage(usersMeta.current_page - 1)" :disabled="usersMeta.current_page <= 1" class="btn-secondary btn-sm !px-2.5 !py-1">Prev</button>
              <button @click="changeUsersPage(usersMeta.current_page + 1)" :disabled="usersMeta.current_page >= usersMeta.last_page" class="btn-secondary btn-sm !px-2.5 !py-1">Next</button>
            </div>
          </div>
        </div>
      </section>

      <!-- Endpoints -->
      <section>
        <div class="flex flex-wrap items-center gap-2 justify-between mb-3">
          <h2 class="section-title">All endpoints</h2>
          <div class="relative">
            <svg class="w-3.5 h-3.5 absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 21l-4.35-4.35M17 10a7 7 0 11-14 0 7 7 0 0114 0z" /></svg>
            <input v-model="endpointQuery" @input="debouncedLoadEndpoints" type="search" placeholder="Search token / label…" class="input !py-1.5 pl-8 w-56 text-xs" />
          </div>
        </div>
        <div class="card overflow-hidden">
          <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead class="text-xs text-gray-500 border-b border-gray-200 dark:border-gray-800 bg-gray-50/60 dark:bg-white/[0.02]">
              <tr>
                <th class="text-left font-medium px-4 py-2.5">URL token</th>
                <th class="text-left font-medium px-4 py-2.5">Owner</th>
                <th class="text-left font-medium px-4 py-2.5">Type</th>
                <th class="text-right font-medium px-4 py-2.5">Requests</th>
                <th class="text-right font-medium px-4 py-2.5">Last activity</th>
                <th class="text-right font-medium px-4 py-2.5">Actions</th>
              </tr>
            </thead>
            <tbody>
              <template v-for="e in endpoints" :key="e.token">
                <tr v-if="editingToken !== e.token" class="border-b border-gray-100 dark:border-gray-800/60 last:border-0 hover:bg-gray-50/70 dark:hover:bg-white/[0.02] transition-colors">
                  <td class="px-4 py-2.5 font-mono text-xs text-gray-900 dark:text-gray-200">
                    {{ e.token }}
                    <span v-if="e.label" class="text-gray-400">· {{ e.label }}</span>
                  </td>
                  <td class="px-4 py-2.5 text-gray-700 dark:text-gray-300 text-xs">{{ e.owner_email || 'guest' }}</td>
                  <td class="px-4 py-2.5"><span class="badge-neutral capitalize">{{ e.type }}</span></td>
                  <td class="px-4 py-2.5 text-right text-gray-700 dark:text-gray-300 tabular-nums">{{ e.request_count }}</td>
                  <td class="px-4 py-2.5 text-right text-gray-500 text-xs">{{ e.last_activity_at ? fmtDate(e.last_activity_at) : '—' }}</td>
                  <td class="px-4 py-2.5">
                    <div class="flex items-center justify-end gap-1">
                      <button @click="router.push(`/endpoints/${e.token}`)" title="View" class="btn-ghost !px-2 !py-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 010-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>
                      </button>
                      <button @click="startEdit(e)" title="Edit" class="btn-ghost !px-2 !py-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.862 4.487l1.687-1.688a1.875 1.875 0 112.652 2.652L10.582 16.07a4.5 4.5 0 01-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 011.13-1.897l8.932-8.931z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 13.5v4.75A2.25 2.25 0 0117.25 20.5H5.75A2.25 2.25 0 013.5 18.25V6.75A2.25 2.25 0 015.75 4.5h4.75" /></svg>
                      </button>
                      <template v-if="confirmingDeleteToken === e.token">
                        <button @click="deleteEndpoint(e)" :disabled="deletingToken === e.token" class="text-xs px-2 py-1 rounded-md bg-red-600 hover:bg-red-500 text-white transition-colors">
                          {{ deletingToken === e.token ? 'Deleting…' : 'Confirm' }}
                        </button>
                        <button @click="confirmingDeleteToken = null" class="btn-ghost !px-2 !py-1 text-xs">Cancel</button>
                      </template>
                      <button v-else @click="confirmingDeleteToken = e.token" title="Delete" class="btn-danger-ghost !px-2 !py-1">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
                      </button>
                    </div>
                  </td>
                </tr>
                <tr v-else class="border-b border-gray-100 dark:border-gray-800/60 last:border-0 bg-brand-50/40 dark:bg-brand-500/[0.06]">
                  <td class="px-4 py-2.5" colspan="2">
                    <div class="flex items-center gap-2">
                      <input v-model="editForm.label" placeholder="Label" class="input !py-1 !px-2 text-xs w-32" />
                      <input v-model="editForm.token" placeholder="Custom slug" class="input !py-1 !px-2 text-xs font-mono w-40" />
                    </div>
                  </td>
                  <td class="px-4 py-2.5"><span class="badge-neutral capitalize">{{ e.type }}</span></td>
                  <td class="px-4 py-2.5 text-right text-gray-700 dark:text-gray-300 tabular-nums">{{ e.request_count }}</td>
                  <td class="px-4 py-2.5"></td>
                  <td class="px-4 py-2.5">
                    <div class="flex items-center justify-end gap-2">
                      <span v-if="editError" class="text-xs text-red-500 dark:text-red-400">{{ editError }}</span>
                      <button @click="cancelEdit" class="btn-ghost !px-2.5 !py-1">Cancel</button>
                      <button @click="saveEdit(e)" :disabled="savingEdit" class="btn-primary btn-sm">{{ savingEdit ? 'Saving…' : 'Save' }}</button>
                    </div>
                  </td>
                </tr>
              </template>
              <tr v-if="!endpoints.length">
                <td colspan="6" class="px-4 py-10 text-center text-gray-400 dark:text-gray-600 text-xs">No endpoints found.</td>
              </tr>
            </tbody>
          </table>
          </div>
          <div v-if="endpointsMeta.total" class="flex flex-wrap items-center justify-between gap-2 px-4 py-2.5 border-t border-gray-100 dark:border-gray-800 text-xs text-gray-500 dark:text-gray-400">
            <span>
              Showing <span class="font-medium text-gray-700 dark:text-gray-300">{{ endpointsMeta.from }}–{{ endpointsMeta.to }}</span>
              of <span class="font-medium text-gray-700 dark:text-gray-300">{{ endpointsMeta.total }}</span> endpoints
              <span class="text-gray-300 dark:text-gray-700">·</span>
              page <span class="font-medium text-gray-700 dark:text-gray-300">{{ endpointsMeta.current_page }}</span> of {{ endpointsMeta.last_page }}
              <span class="text-gray-300 dark:text-gray-700">·</span>
              {{ endpointsMeta.per_page }} per page
            </span>
            <div class="flex items-center gap-2">
              <button @click="changeEndpointsPage(endpointsMeta.current_page - 1)" :disabled="endpointsMeta.current_page <= 1" class="btn-secondary btn-sm !px-2.5 !py-1">Prev</button>
              <button @click="changeEndpointsPage(endpointsMeta.current_page + 1)" :disabled="endpointsMeta.current_page >= endpointsMeta.last_page" class="btn-secondary btn-sm !px-2.5 !py-1">Next</button>
            </div>
          </div>
        </div>
      </section>
    </main>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { adminApi } from '@/api/admin'
import AppHeader from '@/components/AppHeader.vue'

const router = useRouter()

const stats     = ref(null)
const plans     = ref(null)
const users     = ref([])
const endpoints = ref([])
const savingId  = ref(null)
const userQuery     = ref('')
const endpointQuery = ref('')

const emptyMeta = () => ({ current_page: 1, last_page: 1, per_page: 0, total: 0, from: null, to: null })
const usersMeta     = ref(emptyMeta())
const endpointsMeta = ref(emptyMeta())

const editingToken = ref(null)
const editForm     = ref({ label: '', token: '' })
const savingEdit   = ref(false)
const editError    = ref('')
const deletingToken = ref(null)
const confirmingDeleteToken = ref(null)

const settings       = ref(null)
const savingSettings = ref(false)
const settingsSaved  = ref(false)
const settingsError  = ref('')

const ICON = {
  users:     '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17 20h5v-2a4 4 0 00-3-3.87M9 20H4v-2a4 4 0 013-3.87m6-2.26a4 4 0 10-4-4 4 4 0 004 4zm6 0a4 4 0 10-4-4" /></svg>',
  suspended: '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M18.364 18.364A9 9 0 105.636 5.636a9 9 0 0012.728 12.728zM5.636 5.636l12.728 12.728" /></svg>',
  endpoints: '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 010 5.656l-3 3a4 4 0 01-5.656-5.656l1.5-1.5M10.172 13.828a4 4 0 010-5.656l3-3a4 4 0 015.656 5.656l-1.5 1.5" /></svg>',
  guest:     '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" /></svg>',
  total:     '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0l-2.5 5.5a2 2 0 01-1.8 1.5H8.3a2 2 0 01-1.8-1.5L4 13m16 0h-4l-1 2h-6l-1-2H4" /></svg>',
  today:     '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>',
}

const statCards = computed(() => stats.value ? [
  { label: 'Users',           value: stats.value.users_total,     icon: ICON.users,     accent: 'bg-brand-50 dark:bg-brand-500/10 text-brand-600 dark:text-brand-400' },
  { label: 'Suspended',       value: stats.value.users_suspended, icon: ICON.suspended, accent: 'bg-red-50 dark:bg-red-500/10 text-red-600 dark:text-red-400' },
  { label: 'Endpoints',       value: stats.value.endpoints_total, icon: ICON.endpoints, accent: 'bg-blue-50 dark:bg-blue-500/10 text-blue-600 dark:text-blue-400' },
  { label: 'Guest endpoints', value: stats.value.endpoints_guest, icon: ICON.guest,     accent: 'bg-purple-50 dark:bg-purple-500/10 text-purple-600 dark:text-purple-400' },
  { label: 'Requests total',  value: stats.value.requests_total,  icon: ICON.total,     accent: 'bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400' },
  { label: 'Requests today',  value: stats.value.requests_today,  icon: ICON.today,     accent: 'bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400' },
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

async function loadUsers(page = 1) {
  const res = await adminApi.users(userQuery.value, page)
  users.value = res.data.data
  usersMeta.value = res.data.meta
}
async function loadEndpoints(page = 1) {
  const res = await adminApi.endpoints(endpointQuery.value, page)
  endpoints.value = res.data.data
  endpointsMeta.value = res.data.meta
}

function changeUsersPage(page) {
  if (page < 1 || page > usersMeta.value.last_page) return
  loadUsers(page)
}
function changeEndpointsPage(page) {
  if (page < 1 || page > endpointsMeta.value.last_page) return
  loadEndpoints(page)
}

// A new search resets back to page 1 — the old page number rarely still
// makes sense against a different filtered result set.
let uTimer, eTimer
function debouncedLoadUsers()     { clearTimeout(uTimer); uTimer = setTimeout(() => loadUsers(1), 300) }
function debouncedLoadEndpoints() { clearTimeout(eTimer); eTimer = setTimeout(() => loadEndpoints(1), 300) }

// ── Endpoint management (view / edit / delete) ────────────────────────────────
function startEdit(endpoint) {
  editingToken.value = endpoint.token
  editForm.value = { label: endpoint.label || '', token: endpoint.token }
  editError.value = ''
  confirmingDeleteToken.value = null
}

function cancelEdit() {
  editingToken.value = null
  editError.value = ''
}

async function saveEdit(endpoint) {
  savingEdit.value = true
  editError.value  = ''
  try {
    const res = await adminApi.updateEndpoint(endpoint.token, {
      label: editForm.value.label || null,
      token: editForm.value.token,
    })
    Object.assign(endpoint, {
      token: res.data.data.token,
      label: res.data.data.label,
    })
    editingToken.value = null
  } catch (e) {
    const errors = e.response?.data?.errors
    editError.value = errors ? Object.values(errors).flat().join(' ') : (e.response?.data?.message || 'Could not save.')
  } finally {
    savingEdit.value = false
  }
}

async function deleteEndpoint(endpoint) {
  deletingToken.value = endpoint.token
  try {
    await adminApi.deleteEndpoint(endpoint.token)
    // Re-fetch this page rather than patching counts locally — deleting the
    // last row on a page shifts current_page/from/to/last_page too.
    const page = endpoints.value.length === 1 && endpointsMeta.value.current_page > 1
      ? endpointsMeta.value.current_page - 1
      : endpointsMeta.value.current_page
    await Promise.all([loadEndpoints(page), refreshStats()])
  } finally {
    deletingToken.value = null
    confirmingDeleteToken.value = null
  }
}

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
