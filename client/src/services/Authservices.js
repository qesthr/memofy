// composables/useAuth.js
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import api from './api'

const user = ref(null)
const role = ref(null)
const token = ref(localStorage.getItem('token') || null)

export function useAuth() {
  const router = useRouter()

  const login = async (credentials) => {
    try {
      const response = await api.post('/login', credentials)
      
      const { user: userData, role: userRole, token: authToken } = response.data.data
      
      // Store in state
      user.value = userData
      role.value = userRole
      token.value = authToken
      
      // Store token in localStorage
      localStorage.setItem('token', authToken)
      localStorage.setItem('user', JSON.stringify(userData))
      localStorage.setItem('role', userRole)
      
      // Redirect based on role
      if (userRole === 'admin') {
        router.push('/admin/dashboard')
      } else if (userRole === 'secretary') {
        router.push('/secretary/dashboard')
      } else {
        router.push('/dashboard')
      }
      
      return response.data
    } catch (error) {
      throw error
    }
  }

  const logout = async () => {
    try {
      await api.post('/logout')
    } catch (error) {
      console.error('Logout error:', error)
    } finally {
      // Clear state
      user.value = null
      role.value = null
      token.value = null
      
      // Clear localStorage
      localStorage.removeItem('token')
      localStorage.removeItem('user')
      localStorage.removeItem('role')
      
      router.push('/login')
    }
  }

  const getUser = async () => {
    try {
      const response = await api.get('/me')
      user.value = response.data.data.user
      role.value = response.data.data.role
      return response.data.data
    } catch (error) {
      await logout()
      throw error
    }
  }

  const isAuthenticated = () => {
    return !!token.value
  }

  const hasRole = (requiredRole) => {
    return role.value === requiredRole
  }

  return {
    user,
    role,
    token,
    login,
    logout,
    getUser,
    isAuthenticated,
    hasRole
  }
}
