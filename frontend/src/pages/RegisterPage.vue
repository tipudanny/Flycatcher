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
            Never miss a webhook again.
          </h1>
          <p class="mt-4 text-white/70 text-sm leading-relaxed">
            Create an account to unlock unlimited retention, custom vanity URLs,
            and a plan that scales with how many integrations you're debugging.
          </p>

          <div class="mt-8 grid grid-cols-3 gap-3">
            <div v-for="p in planPreview" :key="p.name" class="rounded-lg bg-white/10 border border-white/15 p-3">
              <div class="text-xs font-semibold">{{ p.name }}</div>
              <div class="text-[11px] text-white/60 mt-0.5">{{ p.detail }}</div>
            </div>
          </div>
        </div>

        <p class="text-xs text-white/50">Free forever plan available — upgrade anytime.</p>
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
          <h2 class="text-2xl font-semibold text-gray-900 dark:text-white tracking-tight">Create your account</h2>
          <p class="mt-1 text-gray-500 dark:text-gray-400 text-sm">Start inspecting webhooks instantly</p>
        </div>

        <form @submit.prevent="submit" class="space-y-4">
          <div>
            <label class="field-label">Email</label>
            <input v-model="form.email" type="email" required autofocus class="input" placeholder="you@example.com" />
          </div>
          <div>
            <label class="field-label">Password</label>
            <input v-model="form.password" type="password" required minlength="8" class="input" placeholder="At least 8 characters" />
          </div>
          <div>
            <label class="field-label">Confirm password</label>
            <input v-model="form.password_confirmation" type="password" required class="input" placeholder="••••••••" />
          </div>

          <p v-if="error" class="text-red-500 dark:text-red-400 text-xs">{{ error }}</p>

          <button type="submit" :disabled="loading" class="btn-primary w-full">
            {{ loading ? 'Creating account…' : 'Create account' }}
          </button>
        </form>

        <p class="text-center text-xs text-gray-500">
          Already have an account?
          <router-link to="/login" class="text-brand-600 hover:text-brand-500 dark:text-brand-400 dark:hover:text-brand-300 font-medium">Sign in</router-link>
        </p>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import ThemeToggle from '@/components/ThemeToggle.vue'

const auth   = useAuthStore()
const router = useRouter()

const form    = ref({ email: '', password: '', password_confirmation: '' })
const loading = ref(false)
const error   = ref('')

const planPreview = [
  { name: 'Free', detail: '3 URLs · 7d' },
  { name: 'Pro', detail: '25 URLs · 30d' },
  { name: 'Team', detail: '∞ URLs · ∞' },
]

async function submit() {
  error.value   = ''
  loading.value = true
  try {
    await auth.register(form.value.email, form.value.password, form.value.password_confirmation)
    router.push('/')
  } catch (e) {
    const errors = e.response?.data?.errors
    error.value = errors
      ? Object.values(errors).flat().join(' ')
      : (e.response?.data?.message || 'Registration failed.')
  } finally {
    loading.value = false
  }
}
</script>
