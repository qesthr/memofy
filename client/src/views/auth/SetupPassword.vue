<script setup>
import { ref, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Eye, EyeOff, CheckCircle } from 'lucide-vue-next'
import api from '@/services/api'
import Swal from 'sweetalert2'

const route = useRoute()
const router = useRouter()

const token = ref('')
const invitationData = ref(null)
const isVerifying = ref(true)
const isValid = ref(false)
const isLoading = ref(false)

const showPassword = ref(false)
const showConfirmPassword = ref(false)

const formData = ref({
  password: '',
  password_confirmation: ''
})

const togglePassword = () => {
  showPassword.value = !showPassword.value
}

const toggleConfirmPassword = () => {
  showConfirmPassword.value = !showConfirmPassword.value
}

const verifyToken = async () => {
  token.value = route.query.token
  
  if (!token.value) {
    Swal.fire({
      icon: 'error',
      title: 'Invalid Link',
      text: 'No invitation token found in the link',
      confirmButtonColor: '#4285F4'
    }).then(() => {
      router.push('/')
    })
    return
  }

  try {
    const response = await api.get(`/auth/verify-token/${token.value}`)
    invitationData.value = response.data.data
    isValid.value = true
  } catch (error) {
    Swal.fire({
      icon: 'error',
      title: 'Invalid or Expired Link',
      text: 'This invitation link is invalid or has expired',
      confirmButtonColor: '#4285F4'
    }).then(() => {
      router.push('/')
    })
  } finally {
    isVerifying.value = false
  }
}

const setupPassword = async () => {
  // Validation
  if (!formData.value.password || !formData.value.password_confirmation) {
    Swal.fire({
      icon: 'warning',
      title: 'Incomplete Form',
      text: 'Please fill in all fields',
      confirmButtonColor: '#4285F4'
    })
    return
  }

  if (formData.value.password.length < 8) {
    Swal.fire({
      icon: 'warning',
      title: 'Password Too Short',
      text: 'Password must be at least 8 characters long',
      confirmButtonColor: '#4285F4'
    })
    return
  }

  if (formData.value.password !== formData.value.password_confirmation) {
    Swal.fire({
      icon: 'error',
      title: 'Passwords Do Not Match',
      text: 'Please make sure both passwords match',
      confirmButtonColor: '#4285F4'
    })
    return
  }

  isLoading.value = true

  try {
    const response = await api.post('/auth/setup-password', {
      token: token.value,
      password: formData.value.password,
      password_confirmation: formData.value.password_confirmation
    })

    const { token: authToken, user, role } = response.data.data

    // Store auth data
    localStorage.setItem('token', authToken)
    localStorage.setItem('user', JSON.stringify(user))
    localStorage.setItem('role', role)

    // Success message
    await Swal.fire({
      icon: 'success',
      title: 'Account Created!',
      text: 'Your password has been set successfully. Redirecting to dashboard...',
      timer: 2000,
      showConfirmButton: false
    })

    // Redirect based on role
    if (role === 'admin' || role === 'super_admin') {
      router.push('/admin/dashboard')
    } else if (role === 'secretary') {
      router.push('/secretary/dashboard')
    } else {
      router.push('/faculty/dashboard')
    }

  } catch (error) {
    console.error('Password setup error:', error)
    const errorMessage = error.response?.data?.message || 'Failed to setup password'
    
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: errorMessage,
      confirmButtonColor: '#4285F4'
    })
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  verifyToken()
})
</script>

<template>
  <div class="min-h-screen flex bg-base-100">
    <!-- Left Side - Branding -->
    <div class="hidden lg:flex lg:w-1/2 relative bg-[#1e293b] overflow-hidden items-center justify-center">
      <div class="relative z-10 text-center px-12">
        <img src="../../assets/images/images/Buksu-Logo.png" alt="BukSU Logo" class="w-32 h-32 mx-auto mb-6" />
        <h1 class="text-4xl font-bold text-white mb-3">BUKSU</h1>
        <p class="text-[#FFD700] text-xl font-semibold tracking-wide">EDUCATE. INNOVATE. LEAD.</p>
      </div>
    </div>

    <!-- Right Side - Setup Form -->
    <div class="w-full lg:w-1/2 flex items-center justify-center p-8">
      <div class="w-full max-w-md">
        <!-- Loading State -->
        <div v-if="isVerifying" class="text-center">
          <div class="loading loading-spinner loading-lg text-primary"></div>
          <p class="mt-4 text-base-content/60">Verifying invitation...</p>
        </div>

        <!-- Setup Form -->
        <div v-else-if="isValid" class="space-y-6">
          <!-- Header -->
          <div class="text-center mb-8">
            <div class="flex items-center justify-center mb-4">
              <CheckCircle :size="48" class="text-success" />
            </div>
            <h2 class="text-2xl font-bold text-base-content mb-2">Welcome, {{ invitationData?.name }}!</h2>
            <p class="text-base-content/60">Set up your password to access the {{ invitationData?.role }} portal</p>
          </div>

          <!-- Invitation Info -->
          <div class="bg-blue-50 border border-blue-200 rounded-lg p-4 mb-6">
            <div class="space-y-2 text-sm">
              <div class="flex justify-between">
                <span class="text-base-content/60">Email:</span>
                <span class="font-medium">{{ invitationData?.email }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-base-content/60">Department:</span>
                <span class="font-medium">{{ invitationData?.department }}</span>
              </div>
              <div class="flex justify-between">
                <span class="text-base-content/60">Role:</span>
                <span class="badge badge-primary">{{ invitationData?.role }}</span>
              </div>
            </div>
          </div>

          <!-- Password Form -->
          <form @submit.prevent="setupPassword" class="space-y-4">
            <!-- Password -->
            <div class="form-control">
              <label class="label">
                <span class="label-text font-medium">Password <span class="text-error">*</span></span>
              </label>
              <div class="relative">
                <input 
                  v-model="formData.password"
                  :type="showPassword ? 'text' : 'password'"
                  placeholder="Enter your password" 
                  class="input input-bordered w-full pr-10"
                />
                <button
                  type="button"
                  @click="togglePassword"
                  class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 cursor-pointer"
                >
                  <component :is="showPassword ? EyeOff : Eye" :size="18" />
                </button>
              </div>
              <label class="label">
                <span class="label-text-alt text-base-content/60">Minimum 8 characters</span>
              </label>
            </div>

            <!-- Confirm Password -->
            <div class="form-control">
              <label class="label">
                <span class="label-text font-medium">Confirm Password <span class="text-error">*</span></span>
              </label>
              <div class="relative">
                <input 
                  v-model="formData.password_confirmation"
                  :type="showConfirmPassword ? 'text' : 'password'"
                  placeholder="Confirm your password" 
                  class="input input-bordered w-full pr-10"
                />
                <button
                  type="button"
                  @click="toggleConfirmPassword"
                  class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 cursor-pointer"
                >
                  <component :is="showConfirmPassword ? EyeOff : Eye" :size="18" />
                </button>
              </div>
            </div>

            <!-- Submit Button -->
            <button 
              type="submit"
              class="w-full bg-[#4285F4] hover:bg-[#3367D6] text-white font-medium py-2.5 rounded-lg transition-colors flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
              :disabled="isLoading"
            >
              <span v-if="isLoading" class="loading loading-spinner loading-sm"></span>
              <span v-else>Create Account</span>
            </button>
          </form>

          <div class="text-center text-sm text-base-content/60 mt-6">
            <p>Your email will be used as your username for future logins</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
/* No additional styles needed */
</style>
