<template>
  <div class="min-h-screen flex bg-gray-50 dark:bg-gray-950">
    <!-- Branding panel -->
    <div class="hidden lg:flex lg:w-1/2 relative overflow-hidden bg-brand-gradient">
      <div class="absolute inset-0 bg-brand-radial"></div>
      <div class="absolute -bottom-24 -right-24 w-96 h-96 rounded-full bg-white/10 blur-3xl"></div>
      <div class="absolute -top-16 -left-16 w-72 h-72 rounded-full bg-white/10 blur-3xl"></div>

      <div class="relative z-10 flex flex-col justify-between p-12 text-white w-full">
        <div class="flex items-center gap-2.5">
          <img src="/favicon.svg" alt="" class="w-8 h-8 rounded-lg shadow-lift" />
          <span class="text-lg font-semibold tracking-tight">Flycatcher</span>
        </div>

        <div class="max-w-md">
          <h1 class="text-3xl font-semibold tracking-tight leading-tight">
            Webhooks fly in.<br />You catch and examine them.
          </h1>
          <p class="mt-4 text-white/70 text-sm leading-relaxed">
            A self-hosted inspector for every HTTP webhook — capture instantly,
            live-tail in real time, and replay to your local server without
            waiting for the real event to fire again.
          </p>

          <ul class="mt-8 space-y-3.5">
            <li v-for="f in features" :key="f" class="flex items-center gap-3 text-sm text-white/90">
              <span class="flex items-center justify-center w-5 h-5 rounded-full bg-white/15 shrink-0">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" stroke-width="3" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                </svg>
              </span>
              {{ f }}
            </li>
          </ul>
        </div>

        <p class="text-xs text-white/50">Laravel · Vue · self-hosted, no vendor lock-in</p>
      </div>
    </div>

    <!-- Form panel -->
    <div class="flex-1 flex items-center justify-center relative px-4 py-12">
      <div class="absolute top-4 right-4">
        <ThemeToggle />
      </div>

      <div class="w-full max-w-sm space-y-6 animate-slide-up">
        <div class="text-center lg:text-left">
          <div class="flex items-center justify-center lg:justify-start gap-2 lg:hidden mb-4">
            <img src="/favicon.svg" alt="" class="w-8 h-8" />
            <span class="text-xl font-semibold text-gray-900 dark:text-white">Flycatcher</span>
          </div>
          <h2 class="text-2xl font-semibold text-gray-900 dark:text-white tracking-tight">Welcome back</h2>
          <p class="mt-1 text-gray-500 dark:text-gray-400 text-sm">Sign in to your account</p>
        </div>

        <div v-if="verifiedNotice === '1'" class="badge-green rounded-lg px-3 py-2 w-full justify-center text-xs">
          Email verified — you can sign in now.
        </div>
        <div v-else-if="verifiedNotice === '0'" class="badge-red rounded-lg px-3 py-2 w-full justify-center text-xs text-center">
          That verification link was invalid or expired. Sign in and resend it from your dashboard.
        </div>

        <form @submit.prevent="submit" class="space-y-4">
          <div>
            <label class="field-label">Email</label>
            <input v-model="form.email" type="email" required autofocus class="input" placeholder="you@example.com" />
          </div>
          <div>
            <label class="field-label">Password</label>
            <input v-model="form.password" type="password" required class="input" placeholder="••••••••" />
          </div>

          <p v-if="error" class="text-red-500 dark:text-red-400 text-xs">{{ error }}</p>

          <button type="submit" :disabled="loading" class="btn-primary w-full">
            {{ loading ? 'Signing in…' : 'Sign in' }}
          </button>
        </form>

        <p class="text-center text-xs text-gray-500">
          No account?
          <router-link to="/register" class="text-brand-600 hover:text-brand-500 dark:text-brand-400 dark:hover:text-brand-300 font-medium">Register</router-link>
        </p>

        <div class="relative py-1">
          <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-gray-200 dark:border-gray-800"></div></div>
          <div class="relative flex justify-center"><span class="bg-gray-50 dark:bg-gray-950 px-3 text-xs text-gray-400 dark:text-gray-600">or</span></div>
        </div>

        <button @click="continueAsGuest" :disabled="guestLoading" class="btn-secondary w-full">
          {{ guestLoading ? 'Creating your URL…' : 'Try as guest — no account needed' }}
        </button>
        <p class="text-center text-xs text-gray-400 dark:text-gray-600">
          Guest URLs accept up to 200 requests and are deleted after 2 days.
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import { endpointsApi } from '@/api/endpoints'
import ThemeToggle from '@/components/ThemeToggle.vue'

const auth    = useAuthStore()
const router  = useRouter()
const route   = useRoute()

const form    = ref({ email: '', password: '' })
const loading = ref(false)
const error   = ref('')
const guestLoading = ref(false)

const features = [
  'Universal capture — any method, any payload',
  'Real-time live-tail, zero configuration',
  'One-click replay to your local server',
  'Custom vanity URLs & per-endpoint responses',
]

// Set by the email-verification redirect: ?verified=1 (ok) or ?verified=0 (bad link).
const verifiedNotice = route.query.verified !== undefined ? String(route.query.verified) : null

// Mints a guest endpoint (or returns this browser's existing one) and jumps
// straight to its inspect page. The guest session lives in an httpOnly cookie.
async function continueAsGuest() {
  guestLoading.value = true
  try {
    const res = await endpointsApi.create()
    router.push(`/inspect/${res.data.data.token}`)
  } catch (e) {
    error.value = 'Could not create a guest URL. Please try again.'
  } finally {
    guestLoading.value = false
  }
}

async function submit() {
  error.value   = ''
  loading.value = true
  try {
    await auth.login(form.value.email, form.value.password)
    router.push(route.query.redirect || '/')
  } catch (e) {
    error.value = e.response?.data?.message || 'Invalid credentials.'
  } finally {
    loading.value = false
  }
}
</script>
