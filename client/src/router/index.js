import { createRouter, createWebHistory } from 'vue-router'
import { useAuth } from '@/composables/useAuth'
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
    path: '/forgot-password',
    name: 'ForgotPassword',
    component: () => import('../views/auth/ForgotPassword.vue')
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
        component: Dashboard,
        meta: {}
      },
      {
        path: 'users',
        name: 'Users',
        component: () => import('../views/admin/Users.vue'),
        meta: { permission: 'nav.users' }
      },
      {
        path: 'roles',
        name: 'Roles',
        component: () => import('../views/admin/Roles.vue'),
        meta: { permission: 'nav.roles' }
      },
      {
        path: 'memos',
        name: 'Memos',
        component: () => import('../views/admin/Memos.vue'),
        meta: { permission: 'nav.memos' }
      },
      {
        path: 'archive',
        name: 'Archive',
        component: () => import('../views/admin/Archive.vue'),
        meta: { permission: 'nav.archive' }
      },
      {
        path: 'calendar',
        name: 'Calendar',
        component: () => import('../views/admin/Calendar.vue'),
        meta: { permission: 'nav.calendar' }
      },
      {
        path: 'report',
        name: 'Report',
        component: () => import('../views/admin/Report.vue'),
        meta: { permission: 'nav.reports' }
      },
      {
        path: 'activity-logs',
        name: 'ActivityLogs',
        component: () => import('../views/admin/ActivityLogs.vue'),
        meta: { permission: 'nav.activity_logs' }
      },
      {
        path: 'settings',
        name: 'Settings',
        component: () => import('../views/admin/Settings.vue'),
        meta: { permission: 'nav.settings' }
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
        component: () => import('../views/secretary/Dashboard.vue'),
        meta: {}
      },
      {
        path: 'memos',
        name: 'SecretaryMemos',
        component: () => import('../views/secretary/Memos.vue'),
        meta: { permission: 'nav.memos' }
      },
      {
        path: 'faculty',
        name: 'SecretaryFaculty',
        component: () => import('../views/secretary/Faculty.vue'),
        meta: { permission: 'nav.faculty' }
      },
      {
        path: 'archive',
        name: 'SecretaryArchive',
        component: () => import('../views/secretary/Archive.vue'),
        meta: { permission: 'nav.archive' }
      },
      {
        path: 'calendar',
        name: 'SecretaryCalendar',
        component: () => import('../views/secretary/Calendar.vue'),
        meta: { permission: 'nav.calendar' }
      },
      {
        path: 'settings',
        name: 'SecretarySettings',
        component: () => import('../views/secretary/Settings.vue'),
        meta: { permission: 'nav.settings' }
      },
      {
        path: '',
        redirect: '/secretary/dashboard'
      }
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
        component: () => import('../views/faculty/Dashboard.vue'),
        meta: {}
      },
      {
        path: 'memos',
        name: 'FacultyMemos',
        component: () => import('../views/faculty/Memos.vue'),
        meta: { permission: 'nav.memos' }
      },
      {
        path: 'archive',
        name: 'FacultyArchive',
        component: () => import('../views/faculty/Archive.vue'),
        meta: { permission: 'nav.archive' }
      },
      {
        path: 'calendar',
        name: 'FacultyCalendar',
        component: () => import('../views/faculty/Calendar.vue'),
        meta: { permission: 'nav.calendar' }
      },
      {
        path: 'settings',
        name: 'FacultySettings',
        component: () => import('../views/faculty/Settings.vue'),
        meta: { permission: 'nav.settings' }
      },
      {
        path: '',
        redirect: '/faculty/dashboard'
      }
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

router.beforeEach(async (to, from, next) => {
  const { fetchUser, can } = useAuth()

  // Refresh user data silently to get latest permissions
  const token = localStorage.getItem('token')
  if (token) {
    await fetchUser()
  }

  const role = localStorage.getItem('role')

  if (to.meta.requiresAuth) {
    if (!token) {
      return next({ name: 'Login' })
    }

    if (to.meta.role) {
      const userRole = (role || '').toLowerCase()
      const requiredRole = to.meta.role.toLowerCase()

      if ((userRole === 'admin' || userRole === 'super_admin') && requiredRole === 'admin') {
      } else if (requiredRole !== userRole) {
        return next({ name: 'Unauthorized' })
      }
    }

    if (to.meta.permission && !can(to.meta.permission)) {
      return next({ name: 'Unauthorized' })
    }

    next()
  } else {
    if (to.name === 'Login' && token) {
      if (role === 'admin') return next('/admin/dashboard')
      if (role === 'secretary') return next('/secretary/dashboard')
      if (role === 'faculty') return next('/faculty/dashboard')
    }

    next()
  }
})

export default router
