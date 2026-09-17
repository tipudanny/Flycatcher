<template>
  <div class="min-h-screen bg-gray-50 dark:bg-gray-950 flex flex-col">
    <AppHeader>
      <template #actions>
        <router-link
          v-if="auth.user?.is_admin"
          to="/admin"
          class="badge-brand hover:brightness-95 dark:hover:brightness-125 transition-all"
        >
          Admin
        </router-link>
        <span class="text-gray-500 dark:text-gray-400 text-xs hidden sm:inline">{{ auth.user?.email }}</span>
        <button @click="auth.logout().then(() => router.push('/login'))" class="btn-ghost">
          Sign out
        </button>
      </template>
    </AppHeader>

    <main class="flex-1 max-w-3xl mx-auto w-full px-4 py-10 space-y-8">
      <!-- Email verification banner -->
      <div
        v-if="overview && overview.email_verified === false"
        class="panel border-amber-300/60 dark:border-amber-500/30 bg-amber-50 dark:bg-amber-500/10 rounded-xl px-4 py-3 flex items-center gap-3 flex-wrap"
      >
        <span class="text-sm text-amber-800 dark:text-amber-300">
          <strong>Verify your email</strong> to start creating webhook URLs — we sent a link to {{ auth.user.email }}.
        </span>
        <button
          @click="resend"
          :disabled="resending || resent"
          class="ml-auto text-xs text-amber-800 dark:text-amber-300 underline hover:no-underline disabled:opacity-60"
        >
          {{ resending ? 'Sending…' : resent ? 'Sent ✓' : 'Resend email' }}
        </button>
      </div>

      <!-- Account overview -->
      <section v-if="overview" class="animate-fade-in">
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
          <div v-for="s in statTiles" :key="s.label" class="card p-4">
            <div class="flex items-center gap-2 text-gray-400 dark:text-gray-600 mb-2" v-html="s.icon"></div>
            <div class="text-lg font-semibold text-gray-900 dark:text-white capitalize tracking-tight">{{ s.value }}</div>
            <div class="text-xs text-gray-500 mt-0.5">{{ s.label }}</div>
          </div>
        </div>
        <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">
          Your <span class="capitalize font-medium text-gray-700 dark:text-gray-300">{{ overview.plan_label }}</span> plan:
          {{ fmtLimit(overview.limits.request_limit) }} requests per URL ·
          {{ overview.limits.retention_days === null ? 'data kept forever' : overview.limits.retention_days + '-day retention' }} ·
          custom responses {{ overview.limits.custom_responses ? 'enabled' : 'off' }}.
        </p>
      </section>

      <!-- Header row -->
      <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white tracking-tight">My Endpoints</h2>
        <button @click="showForm = !showForm" class="btn-primary btn-sm">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" d="M12 5v14m-7-7h14" /></svg>
          {{ showForm ? 'Cancel' : 'New URL' }}
        </button>
      </div>

      <!-- Create form -->
      <form
        v-if="showForm"
        @submit.prevent="createEndpoint"
        class="card p-5 space-y-4 animate-slide-up"
      >
        <div>
          <label class="field-label">Label (optional)</label>
          <input v-model="form.label" type="text" maxlength="100" placeholder="e.g. GitHub pushes" class="input" />
        </div>
        <div>
          <label class="field-label">Custom URL (optional — random if left empty)</label>
          <input
            v-model="form.token"
            type="text"
            maxlength="64"
            placeholder="my-github-hook"
            pattern="[A-Za-z0-9][A-Za-z0-9_\-]*"
            title="Letters, numbers, dashes and underscores"
            class="input font-mono"
          />
          <p v-if="form.token" class="mt-1.5 text-xs text-gray-500 font-mono truncate">
            → {{ apiBase }}/event/hooks/{{ form.token }}
          </p>
        </div>

        <p v-if="formError" class="text-red-500 dark:text-red-400 text-xs">{{ formError }}</p>

        <button type="submit" :disabled="creating" class="btn-primary btn-sm">
          {{ creating ? 'Creating…' : 'Create URL' }}
        </button>
      </form>

      <!-- Loading -->
      <div v-if="store.loading" class="text-gray-500 text-sm text-center py-16">Loading…</div>

      <!-- Empty -->
      <div v-else-if="store.endpoints.length === 0" class="card border-dashed py-16 px-6 text-center space-y-3">
        <div class="w-12 h-12 mx-auto rounded-xl bg-brand-50 dark:bg-brand-500/10 flex items-center justify-center text-brand-500">
          <svg class="w-6 h-6" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" />
          </svg>
        </div>
        <p class="text-gray-500 dark:text-gray-400 text-sm">No endpoints yet.</p>
        <button @click="showForm = true" class="text-brand-600 hover:text-brand-500 dark:text-brand-400 dark:hover:text-brand-300 text-sm font-medium">
          Create your first webhook URL →
        </button>
      </div>

      <!-- List -->
      <ul v-else class="space-y-2.5">
        <li
          v-for="endpoint in store.endpoints"
          :key="endpoint.token"
          class="card-interactive p-4 flex items-center justify-between group"
          @click="router.push(`/endpoints/${endpoint.token}`)"
        >
          <div class="space-y-1 min-w-0">
            <div class="flex items-center gap-2">
              <span class="text-gray-900 dark:text-white text-sm font-medium truncate">
                {{ endpoint.label || endpoint.token }}
              </span>
              <span class="badge-neutral capitalize">{{ endpoint.type }}</span>
            </div>
            <div class="text-xs text-gray-500 font-mono truncate">
              {{ endpoint.capture_url }}
            </div>
          </div>
          <div class="flex items-center gap-3 shrink-0 ml-4">
            <div class="text-right">
              <div class="text-gray-900 dark:text-white text-sm font-semibold tabular-nums">{{ endpoint.request_count }}</div>
              <div class="text-xs text-gray-500">
                {{ endpoint.last_activity_at ? timeAgo(endpoint.last_activity_at) : 'no requests' }}
              </div>
            </div>
            <svg class="w-4 h-4 text-gray-300 dark:text-gray-700 group-hover:text-brand-500 group-hover:translate-x-0.5 transition-all" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
            </svg>
          </div>
        </li>
      </ul>
    </main>
  </div>
</template>

<script setup>
import { onMounted, ref, computed } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useEndpointsStore } from '@/stores/endpoints'
import api from '@/api/client'
import AppHeader from '@/components/AppHeader.vue'

const auth    = useAuthStore()
const store   = useEndpointsStore()
const router  = useRouter()
const creating = ref(false)
const showForm = ref(false)
const form     = ref({ label: '', token: '' })
const formError = ref('')

const overview  = ref(null)
const resending = ref(false)
const resent    = ref(false)

const ICONS = {
  plan: '<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 8V4m0 0L8 8m4-4l4 4M4 16l4-8 4 8m-8 0h8m4-8v8m0 0l-4-8m4 8h-4" /></svg>',
  urls: '<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.828 10.172a4 4 0 010 5.656l-3 3a4 4 0 01-5.656-5.656l1.5-1.5M10.172 13.828a4 4 0 010-5.656l3-3a4 4 0 015.656 5.656l-1.5 1.5" /></svg>',
  total: '<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0l-2.5 5.5a2 2 0 01-1.8 1.5H8.3a2 2 0 01-1.8-1.5L4 13m16 0h-4l-1 2h-6l-1-2H4" /></svg>',
  today: '<svg class="w-4 h-4" fill="none" stroke="currentColor" stroke-width="1.75" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13 10V3L4 14h7v7l9-11h-7z" /></svg>',
}

const statTiles = computed(() => overview.value ? [
  { label: 'Current plan', value: overview.value.plan_label, icon: ICONS.plan },
  { label: 'Webhook URLs', value: `${overview.value.usage.endpoints} / ${fmtLimit(overview.value.limits.max_endpoints)}`, icon: ICONS.urls },
  { label: 'Requests captured', value: overview.value.usage.requests_total.toLocaleString(), icon: ICONS.total },
  { label: 'Today', value: overview.value.usage.requests_today.toLocaleString(), icon: ICONS.today },
] : [])

// Base URL for the live preview of a custom slug, derived from any
// existing endpoint or falling back to the API host.
const apiBase = window.location.origin.replace(':5173', ':8000')

onMounted(async () => {
  store.fetchAll()
  try {
    const res = await api.get('/overview')
    overview.value = res.data.data
  } catch (e) { /* non-fatal */ }
})

async function resend() {
  resending.value = true
  try {
    await api.post('/email/resend')
    resent.value = true
  } finally {
    resending.value = false
  }
}

function fmtLimit(v) {
  return v === null || v === undefined ? '∞' : v.toLocaleString()
}

async function createEndpoint() {
  creating.value  = true
  formError.value = ''
  try {
    const ep = await store.create(form.value)
    showForm.value = false
    form.value = { label: '', token: '' }
    router.push(`/endpoints/${ep.token}`)
  } catch (e) {
    const errors = e.response?.data?.errors
    formError.value = errors
      ? Object.values(errors).flat().join(' ')
      : (e.response?.data?.message || 'Could not create the URL.')
  } finally {
    creating.value = false
  }
}

function timeAgo(iso) {
  const diff = Date.now() - new Date(iso).getTime()
  const s = Math.floor(diff / 1000)
  if (s < 60)   return `${s}s ago`
  if (s < 3600) return `${Math.floor(s / 60)}m ago`
  if (s < 86400) return `${Math.floor(s / 3600)}h ago`
  return `${Math.floor(s / 86400)}d ago`
}
</script>
