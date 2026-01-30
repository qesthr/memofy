
import { ref, reactive, computed } from 'vue'
import { useRouter } from 'vue-router'
import api from '../services/api'

// Global state
const user = ref(JSON.parse(localStorage.getItem('user')) || null)
const token = ref(localStorage.getItem('token') || null)
const isAuthenticated = computed(() => !!token.value)
const loading = ref(false)
const error = ref(null)

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
      
      // Persist to local storage
      localStorage.setItem('token', data.token)
      localStorage.setItem('user', JSON.stringify(data.user))
      
      // Redirect based on role
      if (data.role === 'admin') {
        router.push('/admin/dashboard')
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
      router.push('/login')
    }
  }

  return {
    user,
    token,
    isAuthenticated,
    loading,
    error,
    login,
    logout
  }
}
