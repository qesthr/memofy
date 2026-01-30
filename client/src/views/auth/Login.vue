<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import api from '../../services/api'
import { Eye, EyeOff } from 'lucide-vue-next'

const router = useRouter()
const email = ref('')
const password = ref('')
const showPassword = ref(false)
const isLoading = ref(false)
const error = ref('')
const rememberMe = ref(false)

const togglePassword = () => {
  showPassword.value = !showPassword.value
}

const handleLogin = async () => {
  isLoading.value = true
  error.value = ''
  
  try {
    const response = await api.post('/login', {
      email: email.value,
      password: password.value
    })

    const { token, user, role } = response.data.data

    localStorage.setItem('token', token)
    localStorage.setItem('user', JSON.stringify(user))
    localStorage.setItem('role', role)

    if (role === 'admin' || role === 'super_admin') {
       router.push('/admin/dashboard')
    } else {
       router.push('/unauthorized')
    }

  } catch (err) {
    console.error('Login error:', err)
    error.value = err.response?.data?.message || 'Login failed. Please check your credentials.'
  } finally {
    isLoading.value = false
  }
}

// Google Sign-In Logic
const googleLoginUrl = `${import.meta.env.VITE_API_BASE_URL || '/api'}/auth/google` // Adjust based on actual backend route

const openGoogleLogin = () => {
  const width = 500
  const height = 600
  const left = (window.screen.width / 2) - (width / 2)
  const top = (window.screen.height / 2) - (height / 2)
  
  window.open(
    googleLoginUrl, 
    'google_login_popup', 
    `width=${width},height=${height},top=${top},left=${left}`
  )
}

// Listen for messages from popup
const handleMessage = (event) => {
  // Validate origin if possible, though strict validation might be tricky in dev
  // if (event.origin !== window.location.origin) return; 

  if (event.data.type === 'GOOGLE_LOGIN_SUCCESS') {
    const { token, user, role } = event.data.payload
    
    localStorage.setItem('token', token)
    localStorage.setItem('user', JSON.stringify(user))
    localStorage.setItem('role', role)

    if (role === 'admin' || role === 'super_admin') {
       router.push('/admin/dashboard')
    } else {
       router.push('/unauthorized')
    }
  } else if (event.data.type === 'GOOGLE_LOGIN_FAILURE') {
    error.value = event.data.error || 'Google login failed'
  }
}

onMounted(() => {
  window.addEventListener('message', handleMessage)
})

onUnmounted(() => {
  window.removeEventListener('message', handleMessage)
})
</script>

<template>
  <div class="min-h-screen flex bg-base-100">
    <!-- Left Side - Branding -->
    <div class="hidden lg:flex lg:w-1/2 relative bg-[#1e293b] overflow-hidden items-center justify-center">
      <!-- Background Image Overlay -->
      <div 
        class="absolute inset-0 bg-cover bg-center opacity-20"
        style="background-image: url('/src/assets/images/images/UNI.jpg')"
      ></div>
      
      <!-- Content -->
      <div class="relative z-10 flex flex-col items-center justify-center text-center p-12">
        <img src="../../assets/images/images/Buksu-Logo.png" alt="BukSU Logo" class="w-64 mb-8 drop-shadow-xl" />
        <h1 class="text-4xl font-bold text-white tracking-wide mb-2 uppercase">BukSU</h1>
        <p class="text-yellow-400 font-semibold text-lg tracking-wider uppercase">Educate. Innovate. Lead.</p>
      </div>

      <!-- Decorative Curves (CSS only approximation for the blue curves) -->
      <div class="absolute right-0 top-0 bottom-0 w-24 bg-[#0ea5e9] skew-x-[-10deg] translate-x-12"></div>
      <div class="absolute right-0 top-0 bottom-0 w-24 bg-[#0284c7] skew-x-[-10deg] translate-x-6 z-0"></div>
    </div>

    <!-- Right Side - Login Form -->
    <div class="w-full lg:w-1/2 flex items-center justify-center p-8 lg:p-16 bg-white relative">
      <!-- Blue curve overlap fix for right side to match design -->
       <div class="absolute left-0 top-0 bottom-0 w-4 bg-[#0ea5e9] lg:block hidden"></div>

      <div class="w-full max-w-md space-y-8">
        <div class="text-center">
          <div class="flex justify-center mb-4">
            <img src="../../assets/images/images/memofy-logo.png" alt="Memofy Logo" class="w-12 h-12" />
          </div>
          <h2 class="text-2xl font-bold text-gray-900">Login to your Account</h2>
          <p class="mt-2 text-sm text-gray-500">Welcome back! Please login to continue.</p>
        </div>

        <form class="mt-8 space-y-6" @submit.prevent="handleLogin">
          <div class="space-y-4">
            <!-- Email -->
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <img src="../../assets/images/images/email.png" class="h-5 w-5 opacity-40" />
              </div>
              <input
                v-model="email"
                type="email"
                required
                class="input input-bordered w-full pl-10 bg-gray-50"
                placeholder="admin@buksu.edu.ph"
              />
            </div>

            <!-- Password -->
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <img src="../../assets/images/images/padlock.png" class="h-5 w-5 opacity-40" />
              </div>
              <input
                v-model="password"
                :type="showPassword ? 'text' : 'password'"
                required
                class="input input-bordered w-full pl-10 pr-10 bg-gray-50"
                placeholder="••••••••"
              />
              <button 
                type="button"
                @click="togglePassword"
                class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 cursor-pointer"
              >
                <component :is="showPassword ? EyeOff : Eye" size="18" />
              </button>
            </div>
          </div>

          <button
            type="submit"
            class="hidden w-full btn btn-primary text-white normal-case text-base"
            ref="loginBtn"
          >
            Login
          </button>
          
           <!-- Custom Login Button -->
           <button 
            type="submit" 
            class="w-full bg-[#4285F4] hover:bg-[#3367D6] text-white font-medium py-2.5 rounded-lg transition-colors flex items-center justify-center gap-2"
            :disabled="isLoading"
          >
            <span v-if="isLoading" class="loading loading-spinner loading-sm"></span>
            <span v-else>Login</span>
          </button>

          <div class="flex items-center justify-center">
             <a href="#" class="text-sm font-medium text-gray-500 hover:text-gray-700">Forgot Password?</a>
          </div>

          <div class="relative my-6">
            <div class="absolute inset-0 flex items-center">
              <div class="w-full border-t border-gray-300"></div>
            </div>
            <div class="relative flex justify-center text-sm">
              <span class="px-2 bg-white text-gray-500">or</span>
            </div>
          </div>

          <!-- Google Sign In -->
          <button
            type="button"
            @click="openGoogleLogin"
            class="w-full btn btn-outline border-gray-300 hover:bg-gray-50 hover:border-gray-400 normal-case text-base font-medium text-gray-700 space-x-2"
          >
            <img src="../../assets/images/images/google.png" alt="Google" class="w-5 h-5" />
            <span>Sign in with Google</span>
          </button>

          <!-- Recaptcha Placeholder -->
          <div class="flex justify-center mt-6">
            <div class="bg-[#f9f9f9] border border-[#d3d3d3] rounded shadow-sm p-3 w-full max-w-[300px] flex items-center justify-between">
               <div class="flex items-center gap-3">
                 <input type="checkbox" class="checkbox checkbox-sm rounded-sm border-gray-400" />
                 <span class="text-sm text-gray-700 font-medium">I'm not a robot</span>
               </div>
               <div class="flex flex-col items-center">
                 <img src="https://www.gstatic.com/recaptcha/api2/logo_48.png" class="w-8 opacity-70" alt="reCAPTCHA" />
                 <span class="text-[10px] text-gray-500">reCAPTCHA</span>
                 <div class="text-[8px] text-gray-400 flex gap-1">
                   <span>Privacy</span> - <span>Terms</span>
                 </div>
               </div>
            </div>
          </div>
        </form>
        
        <div v-if="error" class="alert alert-error text-sm mt-4">
           <span>{{ error }}</span>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
@reference "../../style.css";
</style>
