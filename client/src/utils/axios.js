import axios from 'axios'
import Swal from 'sweetalert2'

axios.interceptors.response.use(
    response => response,
    error => {
        if (error.response && error.response.status === 401) {
            // Avoid infinite redirects if already on login
            if (!window.location.pathname.includes('/login')) {
                Swal.fire({
                    title: 'Session Expired',
                    text: 'You have been logged out. Please log in again to continue.',
                    icon: 'warning',
                    confirmButtonText: 'Log In'
                }).then(() => {
                    window.location.href = '/login'
                })
            }
        }
        return Promise.reject(error)
    }
)

export default axios
