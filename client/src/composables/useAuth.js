
import { ref, reactive, computed } from 'vue'
import { useRouter } from 'vue-router'
import api from '../services/api'

// Global state
const user = ref(JSON.parse(localStorage.getItem('user')) || null)
const token = ref(localStorage.getItem('token') || null)
const isAuthenticated = computed(() => !!token.value)
const loading = ref(false)
const error = ref(null)

const can = (permission) => {
  if (!user.value) return false
  const userRole = (user.value.role && typeof user.value.role === 'object') ? user.value.role.name : user.value.role
  if (userRole === 'admin') return true
  if (!user.value.permissions) return false
  return user.value.permissions.includes(permission)
}

export function useAuth() {
  const router = useRouter()

  const login = async (email, password) => {
    loading.value = true
    error.value = null
    try {
      const response = await api.post('/login', { email, password })

      const { data } = response.data

      // Update state
      token.value = data.token
      user.value = data.user

      const roleName = (data.user.role && typeof data.user.role === 'object') ? data.user.role.name : data.user.role

      // Persist to local storage
      localStorage.setItem('token', data.token)
      localStorage.setItem('user', JSON.stringify(data.user))
      localStorage.setItem('role', roleName)

      // Redirect based on role
      if (roleName === 'admin') {
        router.push('/admin/dashboard')
      } else if (roleName === 'secretary') {
        router.push('/secretary/dashboard')
      } else if (roleName === 'faculty') {
        router.push('/faculty/dashboard')
      } else {
        router.push('/unauthorized')
      }

      return true
    } catch (err) {
      error.value = err.response?.data?.message || 'Login failed'
      console.error('Login Error:', err)
      return false
    } finally {
      loading.value = false
    }
  }

  const logout = async () => {
    try {
      await api.post('/logout')
    } catch (err) {
      console.error('Logout error', err)
    } finally {
      // Clear state
      token.value = null
      user.value = null
      localStorage.removeItem('token')
      localStorage.removeItem('user')
      localStorage.removeItem('role')
      router.push('/login')
    }
  }

  return {
    user,
    token,
    isAuthenticated,
    loading,
    error,
    can,
    login,
    logout
  }
}
