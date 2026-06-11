import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import api from '@/api/client'

export const useAuthStore = defineStore('auth', () => {
  const user  = ref(null)
  const token = ref(localStorage.getItem('auth_token'))

  const isAuthenticated = computed(() => !!token.value)

  async function register(email, password, passwordConfirmation) {
    const res = await api.post('/auth/register', {
      email,
      password,
      password_confirmation: passwordConfirmation,
    })
    _setSession(res.data)
  }

  async function login(email, password) {
    const res = await api.post('/auth/login', { email, password })
    _setSession(res.data)
  }

  async function logout() {
    try {
      await api.post('/auth/logout')
    } finally {
      _clearSession()
    }
  }

  async function fetchMe() {
    if (!token.value) return
    try {
      const res = await api.get('/auth/me')
      user.value = res.data.user
    } catch {
      _clearSession()
    }
  }

  function _setSession(data) {
    token.value = data.token
    user.value  = data.user
    localStorage.setItem('auth_token', data.token)
  }

  function _clearSession() {
    token.value = null
    user.value  = null
    localStorage.removeItem('auth_token')
  }

  return { user, token, isAuthenticated, register, login, logout, fetchMe }
})
