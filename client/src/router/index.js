import { createRouter, createWebHistory } from 'vue-router'
import AdminLayout from '../views/admin/components/AdminLayout.vue'
import Dashboard from '../views/admin/Dashboard.vue'
import Login from '../views/auth/Login.vue'

const routes = [
  {
    path: '/login',
    name: 'Login',
    component: Login
  },
  {
    path: '/setup-password',
    name: 'SetupPassword',
    component: () => import('../views/auth/SetupPassword.vue')
  },
  {
    path: '/unauthorized',
    name: 'Unauthorized',
    component: () => import('../views/unauthorized.vue')
  },
  {
    path: '/admin',
    component: AdminLayout,
    meta: { requiresAuth: true, role: 'admin' },
    children: [
      {
        path: 'dashboard',
        name: 'Dashboard',
        component: Dashboard
      },
      {
        path: 'users',
        name: 'Users',
        component: () => import('../views/admin/Users.vue')
      },
      {
        path: 'memos',
        name: 'Memos',
        component: () => import('../views/admin/Memos.vue')
      },
      {
        path: 'archive',
        name: 'Archive',
        component: () => import('../views/admin/Archive.vue')
      },
      {
        path: 'calendar',
        name: 'Calendar',
        component: () => import('../views/admin/Calendar.vue')
      },
      {
        path: 'report',
        name: 'Report',
        component: () => import('../views/admin/Report.vue')
      },
      {
        path: 'activity-logs',
        name: 'ActivityLogs',
        component: () => import('../views/admin/ActivityLogs.vue')
      },
      {
        path: 'settings',
        name: 'Settings',
        component: () => import('../views/admin/Settings.vue')
      },
      {
        path: '',
        redirect: '/admin/dashboard'
      }
    ]
  },
  {
    path: '/secretary',
    component: () => import('../views/secretary/components/SecretaryLayout.vue'),
    meta: { requiresAuth: true, role: 'secretary' },
    children: [
      {
        path: 'dashboard',
        name: 'SecretaryDashboard',
        component: () => import('../views/secretary/Dashboard.vue')
      },
      // Add other secretary routes here
    ]
  },
  {
    path: '/faculty',
    component: () => import('../views/faculty/components/FacultyLayout.vue'),
    meta: { requiresAuth: true, role: 'faculty' },
    children: [
      {
        path: 'dashboard',
        name: 'FacultyDashboard',
        component: () => import('../views/faculty/Dashboard.vue')
      },
      // Add other faculty routes here
    ]
  },
  {
    path: '/',
    redirect: '/login'
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

router.beforeEach((to, from, next) => {
  const token = localStorage.getItem('token')
  const role = localStorage.getItem('role')

  // 1. Check if route requires authentication
  if (to.meta.requiresAuth) {
    if (!token) {
      // Not logged in, redirect into login
      return next({ name: 'Login' })
    }

    // 2. Check Role permissions
    if (to.meta.role) {
      // Allow super_admin to access admin routes (or all routes)
      if (role === 'super_admin' && to.meta.role === 'admin') {
        return next()
      }

      if (to.meta.role !== role) {
        // Logged in but wrong role
        return next({ name: 'Unauthorized' })
      }
    }

    // Auth & Role OK
    next()
  } else {
    // Public route
    if (to.name === 'Login' && token) {
      // If already logged in and trying to go to login, redirect to their dashboard
      if (role === 'admin') return next('/admin/dashboard')
      if (role === 'secretary') return next('/secretary/dashboard')
      if (role === 'faculty') return next('/faculty/dashboard')
    }

    next()
  }
})

export default router
