
import axios from 'axios'

const api = axios.create({
  baseURL: import.meta.env.VITE_API_BASE_URL || '/api',
  headers: {
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  }
})

// Request interceptor to attach token
api.interceptors.request.use(config => {
  const token = localStorage.getItem('token')
  if (token) {
    config.headers.Authorization = `Bearer ${token}`
  }
  return config
})

// Response interceptor to handle auth errors
api.interceptors.response.use(
  response => response,
  async error => {
    if (error.response) {
      if (error.response.status === 401) {
        if (!window.location.pathname.includes('/login')) {
          const Swal = (await import('sweetalert2')).default
          Swal.fire({
            title: 'Session Expired',
            text: 'You have been logged out. Please log in again to continue.',
            icon: 'warning',
            confirmButtonText: 'Log In'
          }).then(() => {
            window.location.href = '/login'
          })
        }
      } else if (error.response.status === 403) {
        const Swal = (await import('sweetalert2')).default
        Swal.fire({
          title: 'Access Denied',
          text: error.response.data.message || 'You do not have permission to perform this action.',
          icon: 'error',
          confirmButtonColor: '#3b82f6',
          confirmButtonText: 'Understood'
        })
      }
    }
    return Promise.reject(error)
  }
)

export default api
