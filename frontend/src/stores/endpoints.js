import { defineStore } from 'pinia'
import { ref } from 'vue'
import { endpointsApi } from '@/api/endpoints'

export const useEndpointsStore = defineStore('endpoints', () => {
  const endpoints = ref([])
  const loading   = ref(false)

  async function fetchAll() {
    loading.value = true
    try {
      const res = await endpointsApi.list()
      endpoints.value = res.data.data
    } finally {
      loading.value = false
    }
  }

  async function create({ label = '', token = '' } = {}) {
    const res = await endpointsApi.create({
      label: label || null,
      token: token || null, // custom URL slug — registered users only
    })
    endpoints.value.unshift(res.data.data)
    return res.data.data
  }

  async function remove(token) {
    await endpointsApi.remove(token)
    endpoints.value = endpoints.value.filter(e => e.token !== token)
  }

  return { endpoints, loading, fetchAll, create, remove }
})
