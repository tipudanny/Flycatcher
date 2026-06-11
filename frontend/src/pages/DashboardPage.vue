<template>
  <div class="min-h-screen bg-gray-50 dark:bg-gray-950 flex flex-col">
    <!-- Nav -->
    <header class="border-b border-gray-200 dark:border-gray-800 px-6 py-3 flex items-center justify-between">
      <span class="text-gray-900 dark:text-white font-semibold">Webhook Inspector</span>
      <div class="flex items-center gap-4">
        <ThemeToggle />
        <span class="text-gray-500 dark:text-gray-400 text-xs">{{ auth.user?.email }}</span>
        <button @click="auth.logout().then(() => router.push('/login'))"
          class="text-xs text-gray-500 hover:text-gray-900 dark:text-gray-400 dark:hover:text-white transition-colors">
          Sign out
        </button>
      </div>
    </header>

    <main class="flex-1 max-w-3xl mx-auto w-full px-4 py-8 space-y-6">
      <!-- Header row -->
      <div class="flex items-center justify-between">
        <h2 class="text-gray-900 dark:text-white font-medium">My Endpoints</h2>
        <button
          @click="showForm = !showForm"
          class="bg-indigo-600 hover:bg-indigo-500 text-white text-xs px-3 py-1.5 rounded transition-colors"
        >
          {{ showForm ? 'Cancel' : '+ New URL' }}
        </button>
      </div>

      <!-- Create form -->
      <form
        v-if="showForm"
        @submit.prevent="createEndpoint"
        class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-lg p-4 space-y-3"
      >
        <div>
          <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Label (optional)</label>
          <input
            v-model="form.label"
            type="text"
            maxlength="100"
            placeholder="e.g. GitHub pushes"
            class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-700 rounded px-3 py-2 text-sm text-gray-900 dark:text-white focus:outline-none focus:border-indigo-500"
          />
        </div>
        <div>
          <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Custom URL (optional — random if left empty)</label>
          <input
            v-model="form.token"
            type="text"
            maxlength="64"
            placeholder="my-github-hook"
            pattern="[A-Za-z0-9][A-Za-z0-9_\-]*"
            title="Letters, numbers, dashes and underscores"
            class="w-full bg-gray-50 dark:bg-gray-950 border border-gray-300 dark:border-gray-700 rounded px-3 py-2 text-sm text-gray-900 dark:text-white font-mono focus:outline-none focus:border-indigo-500"
          />
          <p v-if="form.token" class="mt-1 text-xs text-gray-500 font-mono truncate">
            → {{ apiBase }}/event/hooks/{{ form.token }}
          </p>
        </div>

        <p v-if="formError" class="text-red-500 dark:text-red-400 text-xs">{{ formError }}</p>

        <button
          type="submit"
          :disabled="creating"
          class="bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white text-xs px-3 py-1.5 rounded transition-colors"
        >
          {{ creating ? 'Creating…' : 'Create URL' }}
        </button>
      </form>

      <!-- Loading -->
      <div v-if="store.loading" class="text-gray-500 text-sm text-center py-12">Loading…</div>

      <!-- Empty -->
      <div v-else-if="store.endpoints.length === 0" class="text-center py-16 space-y-3">
        <p class="text-gray-500 dark:text-gray-400">No endpoints yet.</p>
        <button @click="showForm = true" class="text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300 text-sm">
          Create your first webhook URL →
        </button>
      </div>

      <!-- List -->
      <ul v-else class="space-y-2">
        <li
          v-for="endpoint in store.endpoints"
          :key="endpoint.token"
          class="bg-white dark:bg-gray-900 border border-gray-200 dark:border-gray-800 rounded-lg p-4 flex items-center justify-between group cursor-pointer hover:border-gray-400 dark:hover:border-gray-600 transition-colors"
          @click="router.push(`/endpoints/${endpoint.token}`)"
        >
          <div class="space-y-1 min-w-0">
            <div class="flex items-center gap-2">
              <span class="text-gray-900 dark:text-white text-sm font-medium truncate">
                {{ endpoint.label || endpoint.token }}
              </span>
              <span class="text-xs px-1.5 py-0.5 rounded bg-gray-200 text-gray-600 dark:bg-gray-800 dark:text-gray-400">
                {{ endpoint.type }}
              </span>
            </div>
            <div class="text-xs text-gray-500 font-mono truncate">
              {{ endpoint.capture_url }}
            </div>
          </div>
          <div class="text-right shrink-0 ml-4 space-y-1">
            <div class="text-gray-900 dark:text-white text-sm font-medium">{{ endpoint.request_count }}</div>
            <div class="text-xs text-gray-500">
              {{ endpoint.last_activity_at ? timeAgo(endpoint.last_activity_at) : 'no requests' }}
            </div>
          </div>
        </li>
      </ul>
    </main>
  </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { useEndpointsStore } from '@/stores/endpoints'
import ThemeToggle from '@/components/ThemeToggle.vue'

const auth    = useAuthStore()
const store   = useEndpointsStore()
const router  = useRouter()
const creating = ref(false)
const showForm = ref(false)
const form     = ref({ label: '', token: '' })
const formError = ref('')

// Base URL for the live preview of a custom slug, derived from any
// existing endpoint or falling back to the API host.
const apiBase = window.location.origin.replace(':5173', ':8000')

onMounted(() => store.fetchAll())

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
