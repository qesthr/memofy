import { createRouter, createWebHistory } from 'vue-router'
import AdminLayout from '../components/AdminLayout.vue'
import Dashboard from '../views/admin/Dashboard.vue'
import Login from '../views/auth/Login.vue'

const routes = [
  {
    path: '/login',
    name: 'Login',
    component: Login
  },
  {
    path: '/unauthorized',
    name: 'Unauthorized',
    component: () => import('../views/unauthorized.vue')
  },
  {
    path: '/admin',
    component: AdminLayout,
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
    path: '/',
    redirect: '/admin/dashboard'
  }
]

const router = createRouter({
  history: createWebHistory(),
  routes
})

export default router
