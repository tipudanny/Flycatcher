<template>
  <div class="min-h-screen flex items-center justify-center bg-gray-50 dark:bg-gray-950">
    <div class="absolute top-4 right-4">
      <ThemeToggle />
    </div>
    <div class="w-full max-w-sm space-y-6 px-4">
      <div class="text-center">
        <div class="flex items-center justify-center gap-2">
          <img src="/favicon.svg" alt="" class="w-8 h-8" />
          <h1 class="text-2xl font-semibold text-gray-900 dark:text-white">Flycatcher</h1>
        </div>
        <p class="mt-1 text-gray-500 dark:text-gray-400 text-sm">Sign in to your account</p>
      </div>

      <form @submit.prevent="submit" class="space-y-4">
        <div>
          <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Email</label>
          <input
            v-model="form.email"
            type="email"
            required
            class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded px-3 py-2 text-sm text-gray-900 dark:text-white focus:outline-none focus:border-indigo-500"
          />
        </div>
        <div>
          <label class="block text-xs text-gray-500 dark:text-gray-400 mb-1">Password</label>
          <input
            v-model="form.password"
            type="password"
            required
            class="w-full bg-white dark:bg-gray-900 border border-gray-300 dark:border-gray-700 rounded px-3 py-2 text-sm text-gray-900 dark:text-white focus:outline-none focus:border-indigo-500"
          />
        </div>

        <p v-if="error" class="text-red-500 dark:text-red-400 text-xs">{{ error }}</p>

        <button
          type="submit"
          :disabled="loading"
          class="w-full bg-indigo-600 hover:bg-indigo-500 disabled:opacity-50 text-white rounded px-4 py-2 text-sm font-medium transition-colors"
        >
          {{ loading ? 'Signing in…' : 'Sign in' }}
        </button>
      </form>

      <p class="text-center text-xs text-gray-500">
        No account?
        <router-link to="/register" class="text-indigo-600 hover:text-indigo-500 dark:text-indigo-400 dark:hover:text-indigo-300">Register</router-link>
      </p>

      <div class="relative">
        <div class="absolute inset-0 flex items-center"><div class="w-full border-t border-gray-200 dark:border-gray-800"></div></div>
        <div class="relative flex justify-center"><span class="bg-gray-50 dark:bg-gray-950 px-2 text-xs text-gray-400 dark:text-gray-600">or</span></div>
      </div>

      <button
        @click="continueAsGuest"
        :disabled="guestLoading"
        class="w-full border border-gray-300 dark:border-gray-700 hover:border-indigo-500 dark:hover:border-indigo-500 disabled:opacity-50 text-gray-700 dark:text-gray-300 rounded px-4 py-2 text-sm transition-colors"
      >
        {{ guestLoading ? 'Creating your URL…' : 'Try as guest — no account needed' }}
      </button>
      <p class="text-center text-xs text-gray-400 dark:text-gray-600">
        Guest URLs accept up to 200 requests and are deleted after 2 days.
      </p>
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
