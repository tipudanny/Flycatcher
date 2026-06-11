import { defineStore } from 'pinia'
import { ref } from 'vue'

const STORAGE_KEY = 'theme'

/**
 * Dark/light theme. Tailwind is configured with darkMode: 'class', so all
 * dark: variants activate when <html> has the "dark" class.
 *
 * Resolution order: saved choice → OS preference → dark (the app's original look).
 */
export const useThemeStore = defineStore('theme', () => {
  const saved = localStorage.getItem(STORAGE_KEY)
  const theme = ref(
    saved === 'light' || saved === 'dark'
      ? saved
      : (window.matchMedia?.('(prefers-color-scheme: light)').matches ? 'light' : 'dark')
  )

  function apply() {
    document.documentElement.classList.toggle('dark', theme.value === 'dark')
  }

  function toggle() {
    theme.value = theme.value === 'dark' ? 'light' : 'dark'
    localStorage.setItem(STORAGE_KEY, theme.value)
    apply()
  }

  apply()

  return { theme, toggle }
})
