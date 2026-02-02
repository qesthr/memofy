import { useRouter } from 'vue-router'
import api from '@/services/api'
import Swal from 'sweetalert2'
import memofyLogo from '@/assets/images/images/memofy-logo.png'

export function useLogout() {
    const router = useRouter()

    const logout = async () => {
        const result = await Swal.fire({
            title: 'Are you sure you want to logout?',
            imageUrl: memofyLogo,
            imageWidth: 80,
            imageHeight: 80,
            imageAlt: 'Memofy Logo',
            showCancelButton: true,
            confirmButtonText: 'Yes, Logout',
            confirmButtonColor: '#0ea5e9', // Primary blue
            cancelButtonColor: '#d33',
            reverseButtons: true,
            customClass: {
                popup: 'rounded-xl',
                title: 'text-xl font-bold text-gray-800',
                confirmButton: 'btn btn-primary text-white normal-case',
                cancelButton: 'btn btn-ghost normal-case'
            }
        })

        if (result.isConfirmed) {
            try {
                // Show loading state while logging out
                Swal.fire({
                    title: 'Logging out...',
                    allowOutsideClick: false,
                    didOpen: () => {
                        Swal.showLoading()
                    }
                })

                await api.post('/logout')
            } catch (error) {
                console.error('Logout error:', error)
            } finally {
                localStorage.removeItem('token')
                localStorage.removeItem('user')
                localStorage.removeItem('role')

                Swal.close() // Close loading
                router.push('/login')
            }
        }
    }

    return {
        logout
    }
}
