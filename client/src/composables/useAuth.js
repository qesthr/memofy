
import { ref, reactive, computed } from 'vue'
import { useRouter } from 'vue-router'
import api from '../services/api'
import Swal from 'sweetalert2'

// Safe JSON parse helper to prevent crash on null/"undefined"/malformed values
const safeParse = (str) => {
  try {
    if (!str || str === 'undefined' || str === 'null') return null
    return JSON.parse(str)
  } catch {
    return null
  }
}

// Global state
const user = ref(safeParse(localStorage.getItem('user')))
const token = ref(localStorage.getItem('token') || null)
const isAuthenticated = computed(() => !!token.value)
const loading = ref(false)
const error = ref(null)

// Session timeout handling
let sessionTimeoutTimer = null
let sessionWarningTimer = null
const sessionTimeoutMinutes = ref(30)
const showSessionWarning = ref(false)

const fetchSessionTimeout = async () => {
  try {
    const response = await api.get('/system-settings')
    const timeout = response.data.session_timeout_minutes || 30
    sessionTimeoutMinutes.value = timeout
    localStorage.setItem('session_timeout_minutes', timeout)
    return timeout
  } catch (error) {
    console.error('Failed to fetch session timeout:', error)
    return 30
  }
}

// Refresh session timeout from server (called after admin updates settings)
const refreshSessionTimeout = async () => {
  const timeout = await fetchSessionTimeout()
  // Update the timers with new timeout
  if (token.value) {
    resetSessionTimer()
  }
  return timeout
}

const resetSessionTimer = () => {
  if (sessionTimeoutTimer) {
    clearTimeout(sessionTimeoutTimer)
  }
  if (sessionWarningTimer) {
    clearTimeout(sessionWarningTimer)
  }
  showSessionWarning.value = false

  if (!token.value) return

  const timeoutMs = sessionTimeoutMinutes.value * 60 * 1000
  const warningMs = Math.max(0, timeoutMs - 60000) // Show warning 1 minute before timeout

  // Set warning timer
  sessionWarningTimer = setTimeout(() => {
    showSessionWarning.value = true
  }, warningMs)

  // Set logout timer
  sessionTimeoutTimer = setTimeout(() => {
    logout(true)
  }, timeoutMs)
}

// Setup activity listeners
const setupActivityListeners = () => {
  const events = ['mousedown', 'keydown', 'scroll', 'touchstart', 'mousemove']
  events.forEach(event => {
    document.removeEventListener(event, resetSessionTimer)
    document.addEventListener(event, resetSessionTimer, { passive: true })
  })
}

const login = async (email, password, router) => {
  loading.value = true
  error.value = null
  try {
    const response = await api.post('/login', { email, password })
    const { data } = response.data

    token.value = data.token
    const userData = {
      ...data.user,
      permissions: data.permissions || []
    }
    user.value = userData

    const roleName = (userData.role && typeof userData.role === 'object') ? userData.role.name : userData.role

    localStorage.setItem('token', data.token)
    localStorage.setItem('user', JSON.stringify(userData))
    localStorage.setItem('role', roleName)

    if (roleName === 'admin') {
      router.push('/admin/dashboard')
    } else if (roleName === 'secretary') {
      router.push('/secretary/dashboard')
    } else if (roleName === 'faculty') {
      router.push('/faculty/dashboard')
    } else {
      router.push('/unauthorized')
    }

    initSessionTimeout()
    return true
  } catch (err) {
    error.value = err.response?.data?.message || 'Login failed'
    console.error('Login Error:', err)
    return false
  } finally {
    loading.value = false
  }
}

const logout = async (isSessionTimeout = false) => {
  if (sessionTimeoutTimer) {
    clearTimeout(sessionTimeoutTimer)
  }
  if (sessionWarningTimer) {
    clearTimeout(sessionWarningTimer)
  }
  showSessionWarning.value = false

  token.value = null
  user.value = null
  localStorage.removeItem('token')
  localStorage.removeItem('user')
  localStorage.removeItem('role')

  if (isSessionTimeout) {
    await Swal.fire({
      icon: 'warning',
      title: 'Session Expired',
      text: 'Your session has expired. Please login again.',
      confirmButtonText: 'OK',
      confirmButtonColor: '#3b82f6',
      allowOutsideClick: false,
      allowEscapeKey: false
    })
    window.location.href = '/login'
    return
  }

  try {
    await api.post('/logout')
  } catch (err) {
    console.error('Logout error', err)
  } finally {
    window.location.href = '/login'
  }
}

const fetchUser = async () => {
  if (!token.value) return null
  try {
    const response = await api.get('/current-user')
    const refreshedUser = response.data.user
    const permissions = response.data.permissions || refreshedUser.permissions || []

    user.value = { ...refreshedUser, permissions }
    localStorage.setItem('user', JSON.stringify({ ...refreshedUser, permissions }))

    const roleName = (refreshedUser.role && typeof refreshedUser.role === 'object')
      ? refreshedUser.role.name
      : refreshedUser.role
    localStorage.setItem('role', roleName)

    return refreshedUser
  } catch (err) {
    console.error('Fetch user error:', err)
    if (err.response?.status === 401) {
      logout()
    }
    return null
  }
}

// Initialize session timeout
const initSessionTimeout = async () => {
  // Always fetch the latest timeout from server to ensure we have the current value
  await fetchSessionTimeout()

  if (token.value) {
    resetSessionTimer()
    setupActivityListeners()
  }
}

const can = (permission) => {
  if (!user.value) return false
  const userRole = (user.value.role && typeof user.value.role === 'object') ? user.value.role.name : user.value.role

  // Admin bypass
  if (userRole === 'admin' || userRole === 'superadmin' || userRole === 'super_admin') return true

  // Use permissions from login response or compute from role
  const permissions = user.value.permissions || user.value.permission_ids || []

  // If no permissions set, check if we have a role and return true for basic access
  // This is a fallback - in production, roles should have permissions
  if (permissions.length === 0 && userRole) {
    // For secretary and faculty, allow basic access by default
    if (userRole === 'secretary' || userRole === 'faculty') {
      return true
    }
  }

  return permissions.includes(permission)
}

export function useAuth() {
  const router = useRouter()

  return {
    user,
    token,
    isAuthenticated,
    loading,
    error,
    can,
    login: (email, password) => login(email, password, router),
    logout,
    fetchUser,
    fetchSessionTimeout,
    refreshSessionTimeout,
    initSessionTimeout,
    sessionTimeoutMinutes,
    showSessionWarning,
    resetSessionTimer
  }
}
