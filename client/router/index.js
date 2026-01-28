import { createRouter, createWebHistory } from 'vue-router'
import { useAuth } from '@/composables/useAuth'

const routes = [
  {
    path: '/login',
    name: 'Login',
    component: () => import('@/views/Login.vue'),
    meta: { guest: true }
  },
  {
    path: '/dashboard',
    name: 'Dashboard',
    component: () => import('@/views/Dashboard.vue'),
    meta: { requiresAuth: true }
  },
  {
    path: '/admin/dashboard',
    name: 'AdminDashboard',
    component: () => import('@/views/admin/Dashboard.vue'),
    meta: { requiresAuth: true, role: 'admin' }
  },
  // Add more routes...
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

router.beforeEach((to, from, next) => {
  const { isAuthenticated, hasRole } = useAuth()

  if (to.meta.requiresAuth && !isAuthenticated()) {
    next('/login')
  } else if (to.meta.guest && isAuthenticated()) {
    next('/dashboard')
  } else if (to.meta.role && !hasRole(to.meta.role)) {
    next('/dashboard') // or 403 page
  } else {
    next()
  }
})

export default router