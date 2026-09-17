import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import router from './router'
import '@fontsource-variable/inter'
import '@fontsource-variable/jetbrains-mono'
import './style.css'

import { useThemeStore } from './stores/theme'

const app = createApp(App)

app.use(createPinia())
useThemeStore() // applies the saved/system theme before first paint
app.use(router)

app.mount('#app')
