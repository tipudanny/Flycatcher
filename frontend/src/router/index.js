import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const routes = [
  {
    path: '/login',
    name: 'login',
    component: () => import('@/pages/LoginPage.vue'),
    meta: { guest: true },
  },
  {
    path: '/register',
    name: 'register',
    component: () => import('@/pages/RegisterPage.vue'),
    meta: { guest: true },
  },
  {
    path: '/',
    name: 'dashboard',
    component: () => import('@/pages/DashboardPage.vue'),
    meta: { requiresAuth: true },
  },
  {
    // Auth-gated detail view — same component as /inspect/:token but with full controls
    path: '/endpoints/:token',
    name: 'endpoint-detail',
    component: () => import('@/pages/EndpointDetailPage.vue'),
    meta: { requiresAuth: true },
  },
  {
    // Public guest/shared view — accessible by anyone who has the token
    path: '/inspect/:token',
    name: 'inspect',
    component: () => import('@/pages/InspectPage.vue'),
  },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  // Hydrate user on first navigation
  if (!auth.user && auth.token) {
    await auth.fetchMe()
  }

  if (to.meta.requiresAuth && !auth.isAuthenticated) {
    return { name: 'login', query: { redirect: to.fullPath } }
  }

  if (to.meta.guest && auth.isAuthenticated) {
    return { name: 'dashboard' }
  }
})

export default router
