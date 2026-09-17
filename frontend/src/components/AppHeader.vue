<template>
  <header
    class="sticky top-0 z-20 border-b border-gray-200/80 dark:border-gray-800/80
           bg-white/75 dark:bg-gray-950/75 backdrop-blur-md px-5 py-2.5 flex items-center gap-3"
  >
    <router-link to="/" class="flex items-center gap-2 shrink-0 group">
      <span class="relative flex items-center justify-center w-7 h-7 rounded-lg overflow-hidden shadow-soft transition-transform group-hover:scale-105">
        <img src="/favicon.svg" alt="" class="w-full h-full" />
      </span>
      <span class="text-[15px] font-semibold tracking-tight text-gray-900 dark:text-white">Flycatcher</span>
    </router-link>

    <slot name="badge" />

    <template v-if="crumb">
      <span class="text-gray-300 dark:text-gray-700">/</span>
      <span class="text-xs text-gray-500 dark:text-gray-400 truncate max-w-[16rem]">{{ crumb }}</span>
    </template>

    <div class="ml-auto flex items-center gap-3">
      <slot name="status" />
      <ThemeToggle />
      <slot name="actions" />
    </div>
  </header>
</template>

<script setup>
import ThemeToggle from '@/components/ThemeToggle.vue'

defineProps({
  crumb: { type: String, default: '' },
})
</script>
