<script setup>
import { ref, onMounted, onUnmounted, getCurrentInstance, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import { Eye, EyeOff, X, ShieldCheck, AlertCircle } from 'lucide-vue-next'
import api from '@/services/api'
import Swal from 'sweetalert2'
import { useTheme } from '@/composables/useTheme'
import { useAuth } from '@/composables/useAuth'

// Make recaptcha site key available globally in instance
const app = getCurrentInstance()
if (app) {
  app.appContext.config.globalProperties.$recaptchaSiteKey = import.meta.env.VITE_RECAPTCHA_SITE_KEY
}

const router = useRouter()
const email = ref('')
const password = ref('')
const showPassword = ref(false)
const isLoading = ref(false)
const error = ref('')
const rememberMe = ref(false)
const recaptchaVerified = ref(false)
const recaptchaToken = ref('')
const isDev = import.meta.env.MODE === 'development'
const isRecaptchaLoaded = ref(false)
const recaptchaWidgetId = ref(null)

// Modal state
const showRecaptchaModal = ref(false)
const isVerifyingRecaptcha = ref(false)
const recaptchaError = ref('')
const loginFormRef = ref(null)

const { user: authUser, token: authTokenRef } = useAuth()

const togglePassword = () => {
  showPassword.value = !showPassword.value
}

// Open reCAPTCHA modal
const openRecaptchaModal = async () => {
  // Validate form first
  if (!email.value || !password.value) {
    Swal.fire({
      icon: 'warning',
      title: 'Missing Credentials',
      text: 'Please enter your email and password before logging in.'
    })
    return false
  }
  
  recaptchaError.value = ''
  showRecaptchaModal.value = true
  
  // Wait for modal to be in DOM
  await nextTick()
  
  // Load reCAPTCHA script if not loaded
  if (!window.grecaptcha) {
    loadRecaptchaScript()
  } else {
    isRecaptchaLoaded.value = true
    renderRecaptcha()
  }
  
  return true
}

// Close reCAPTCHA modal
const closeRecaptchaModal = () => {
  showRecaptchaModal.value = false
  recaptchaError.value = ''
  
  // Reset reCAPTCHA if it was rendered
  if (window.grecaptcha && recaptchaWidgetId.value !== null) {
    try {
      window.grecaptcha.reset(recaptchaWidgetId.value)
    } catch (e) {
      console.warn('Could not reset reCAPTCHA:', e)
    }
    recaptchaWidgetId.value = null
    recaptchaVerified.value = false
    recaptchaToken.value = ''
  }
}

// Render reCAPTCHA in modal
const renderRecaptcha = () => {
  if (!window.grecaptcha) {
    recaptchaError.value = 'Verification service not loaded. Please try again.'
    return
  }
  
  const container = document.getElementById('recaptcha-container-modal')
  if (!container) return
  
  // Clear any existing content
  container.innerHTML = ''
  
  // Reset widget ID
  recaptchaWidgetId.value = null
  
  // Wait for grecaptcha to be fully ready (for reCAPTCHA v3)
  const tryRender = () => {
    if (typeof window.grecaptcha.render === 'function') {
      try {
        recaptchaWidgetId.value = window.grecaptcha.render(container, {
          sitekey: app.appContext.config.globalProperties.$recaptchaSiteKey || '6LeBIdwrAAAAAOIONOkF3vk31VJTzoN1ElEUOhBV',
          theme: 'light',
          size: 'normal',
          callback: onRecaptchaVerify,
          'expired-callback': onRecaptchaExpired,
          'error-callback': onRecaptchaError
        })
      } catch (e) {
        console.error('Failed to render reCAPTCHA:', e)
        recaptchaError.value = 'Failed to load verification. Please try again.'
      }
    } else {
      // Retry in 100ms if render not yet available
      setTimeout(tryRender, 100)
    }
  }
  
  tryRender()
}

// Load reCAPTCHA script
const loadRecaptchaScript = () => {
  if (isRecaptchaLoaded.value || document.querySelector('script[src*="recaptcha/api.js"]')) {
    isRecaptchaLoaded.value = true
    if (showRecaptchaModal.value) {
      setTimeout(renderRecaptcha, 100)
    }
    return
  }
  
  const script = document.createElement('script')
  script.src = 'https://www.google.com/recaptcha/api.js'
  script.async = true
  script.defer = true
  script.onload = () => {
    isRecaptchaLoaded.value = true
    if (showRecaptchaModal.value) {
      setTimeout(renderRecaptcha, 100)
    }
  }
  script.onerror = () => {
    recaptchaError.value = 'Failed to load verification service. Please check your connection.'
    isRecaptchaLoaded.value = false
  }
  document.head.appendChild(script)
}

// reCAPTCHA callbacks
const onRecaptchaVerify = (token) => {
  recaptchaVerified.value = true
  recaptchaToken.value = token
  recaptchaError.value = ''
  
  // Close modal and submit form after a short delay
  setTimeout(() => {
    showRecaptchaModal.value = false
    submitLoginForm()
  }, 300)
}

const onRecaptchaExpired = () => {
  recaptchaVerified.value = false
  recaptchaToken.value = ''
}

const onRecaptchaError = (error) => {
  console.error('reCAPTCHA error:', error)
  recaptchaError.value = 'Verification failed. Please try again.'
  recaptchaVerified.value = false
  recaptchaToken.value = ''
}

// Main login handler - now just opens the modal
const handleLogin = async () => {
  await openRecaptchaModal()
}

// Submit the actual login form with credentials and token
const submitLoginForm = async () => {
  // Show Loading Alert
  Swal.fire({
    title: 'Logging in...',
    text: 'Please wait while we verify your credentials.',
    allowOutsideClick: false,
    didOpen: () => {
      Swal.showLoading()
    }
  })

  isLoading.value = true
  error.value = ''
  
  try {
    const response = await api.post('/login', {
      email: email.value,
      password: password.value,
      recaptcha_token: recaptchaToken.value
    })

    const { token, user } = response.data
    
    // Robust role extraction
    const role = (user.role && typeof user.role === 'object') ? user.role.name : user.role

    localStorage.setItem('token', token)
    localStorage.setItem('user', JSON.stringify(user))
    localStorage.setItem('role', role)

    // Sync with useAuth global state
    if (authUser) authUser.value = user
    if (authTokenRef) authTokenRef.value = token

    if (user.theme) {
       const { setTheme } = useTheme()
       await setTheme(user.theme, false)
    }

    await Swal.fire({
      icon: 'success',
      title: 'Login Successful',
      text: `Welcome back, ${user.first_name}!`,
      timer: 1500,
      showConfirmButton: false
    })

    if (role === 'admin' || role === 'super_admin') {
       router.push('/admin/dashboard')
    } else if (role === 'secretary') {
       router.push('/secretary/dashboard')
    } else if (role === 'faculty') {
       router.push('/faculty/dashboard')
    } else {
       router.push('/unauthorized')
    }

  } catch (err) {
    console.error('Login error:', err)
    
    // Reset reCAPTCHA on failure
    if (window.grecaptcha && recaptchaWidgetId.value !== null) {
      try {
        window.grecaptcha.reset(recaptchaWidgetId.value)
      } catch (e) {
        console.warn('Could not reset reCAPTCHA:', e)
      }
    }
    recaptchaVerified.value = false
    recaptchaToken.value = ''
    recaptchaWidgetId.value = null

    let errorMsg = 'An unexpected error occurred. Please try again later.'
    let errorTitle = 'Error'

    if (err.response) {
      const data = err.response.data
      errorMsg = data.message || 'Login failed. Please check your credentials.'
      
      if (err.response.status === 401) {
        errorTitle = 'Authentication Failed'
        if (data.attempts_left !== undefined) {
          if (data.attempts_left > 0) {
            errorMsg = `Incorrect username or password. You have ${data.attempts_left} attempts remaining.`
          } else {
            errorMsg = `Incorrect username or password. Your account has been locked.`
          }
        }
      } else if (err.response.status === 422) {
        errorTitle = 'Verification Failed'
      } else if (err.response.status === 423) {
        errorTitle = 'Account Locked'
        const rawSeconds = Math.max(0, Math.floor(data.lock_seconds_remaining || 0))
        const mins = Math.floor(rawSeconds / 60).toString().padStart(2, '0')
        const secs = (rawSeconds % 60).toString().padStart(2, '0')
        errorMsg = `Account is temporarily locked. Wait until time is end to login again. Try again in ${mins}:${secs}.`
      }
    } else if (err.request) {
      errorMsg = 'Cannot connect to the server. Please check your internet connection.'
    }

    error.value = errorMsg
    
    Swal.fire({
      icon: 'error',
      title: errorTitle,
      text: errorMsg,
      confirmButtonColor: '#4285F4'
    })
  } finally {
    isLoading.value = false
  }
}

// Google Sign-In Logic
const googleLoginUrl = `${import.meta.env.VITE_API_BASE_URL || '/api'}/auth/google`

const googleLoginSuccess = ref(false)

// Listen for messages from popup
const handleMessage = (event) => {
  if (event.data.type === 'GOOGLE_LOGIN_SUCCESS') {
    googleLoginSuccess.value = true
    const { token, user, role: rawRole } = event.data.payload
    const role = (rawRole && typeof rawRole === 'object') ? rawRole.name : rawRole
    
    localStorage.setItem('token', token)
    localStorage.setItem('user', JSON.stringify(user))
    localStorage.setItem('role', role)

    // Sync with useAuth global state
    if (authUser) authUser.value = user
    if (authTokenRef) authTokenRef.value = token

    if (window.googleLoginTimer) {
      clearInterval(window.googleLoginTimer)
      window.googleLoginTimer = null
    }

    Swal.fire({
      icon: 'success',
      title: 'Login Successful',
      text: `Welcome back, ${user.first_name}!`,
      timer: 1500,
      showConfirmButton: false
    }).then(() => {
      if (role === 'admin' || role === 'super_admin') {
         router.push('/admin/dashboard')
      } else if (role === 'secretary') {
         router.push('/secretary/dashboard')
      } else if (role === 'faculty') {
         router.push('/faculty/dashboard')
      } else {
         router.push('/unauthorized')
      }
    })
  }
}

const openGoogleLogin = () => {
  googleLoginSuccess.value = false
  const width = 500
  const height = 600
  const left = (window.screen.width / 2) - (width / 2)
  const top = (window.screen.height / 2) - (height / 2)
  
  const popup = window.open(
    googleLoginUrl, 
    'google_login_popup', 
    `width=${width},height=${height},top=${top},left=${left}`
  )

  if (window.googleLoginTimer) clearInterval(window.googleLoginTimer)
  
  window.googleLoginTimer = setInterval(() => {
    if (popup.closed) {
      clearInterval(window.googleLoginTimer)
      window.googleLoginTimer = null
      
      if (!googleLoginSuccess.value) {
        Swal.fire({
          icon: 'error',
          title: 'Access Denied',
          text: 'Access Denied: Only emails registered by an administrator can log in using Google. Please contact your admin for assistance.',
          confirmButtonColor: '#4285F4'
        })
      }
    }
  }, 1000)
}

onMounted(() => {
  // Clear any existing auth data to ensure clean state
  localStorage.removeItem('token')
  localStorage.removeItem('user')
  localStorage.removeItem('role')
  
  window.addEventListener('message', handleMessage)
})

onUnmounted(() => {
  window.removeEventListener('message', handleMessage)
})
</script>

<template>
  <div class="min-h-screen flex bg-base-100" data-theme="light">
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

        <form class="mt-8 space-y-6" @submit.prevent ref="loginFormRef">
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
                class="input input-bordered w-full pl-10 bg-gray-50 text-gray-900"
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
                class="input input-bordered w-full pl-10 pr-10 bg-gray-50 text-gray-900"
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

          <!-- Custom Login Button -->
          <button 
            type="button"
            @click="handleLogin"
            class="w-full bg-[#4285F4] hover:bg-[#3367D6] text-white font-medium py-2.5 rounded-lg transition-colors flex items-center justify-center gap-2 disabled:opacity-50 disabled:cursor-not-allowed"
            :disabled="isLoading"
          >
            <span v-if="isLoading" class="loading loading-spinner loading-sm"></span>
            <span v-else>Login</span>
          </button>

          <div class="flex items-center justify-center">
             <router-link to="/forgot-password" class="text-sm font-medium text-gray-500 hover:text-gray-700">Forgot Password?</router-link>
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
            class="w-full btn btn-outline border-gray-300 hover:bg-gray-50 hover:border-gray-400 normal-case text-base font-medium text-gray-700 space-x-2 disabled:opacity-50 disabled:cursor-not-allowed"
            :disabled="isLoading"
          >
            <img src="../../assets/images/images/google.png" alt="Google" class="w-5 h-5" />
            <span>Sign in with Google</span>
          </button>
        </form>
        
        <div v-if="error" class="alert alert-error text-sm mt-4">
           <span>{{ error }}</span>
        </div>
      </div>
    </div>

    <!-- reCAPTCHA Modal -->
    <Teleport to="body">
      <div v-if="showRecaptchaModal" class="fixed inset-0 z-50 flex items-center justify-center">
        <!-- Backdrop -->
        <div 
          class="absolute inset-0 bg-black/50 backdrop-blur-sm transition-opacity"
          @click="closeRecaptchaModal"
        ></div>
        
        <!-- Modal Content -->
        <div class="relative bg-white rounded-xl shadow-2xl max-w-md w-full mx-4 transform transition-all">
          <!-- Modal Header -->
          <div class="flex items-center justify-between p-4 border-b">
            <div class="flex items-center gap-2">
              <ShieldCheck class="w-6 h-6 text-blue-500" />
              <h3 class="text-lg font-semibold text-gray-900">Verification Required</h3>
            </div>
            <button 
              @click="closeRecaptchaModal"
              class="p-1 rounded-full hover:bg-gray-100 transition-colors"
            >
              <X class="w-5 h-5 text-gray-500" />
            </button>
          </div>
          
          <!-- Modal Body -->
          <div class="p-6">
            <p class="text-sm text-gray-600 mb-4">
              Please complete the verification below to confirm you're human before logging in.
            </p>
            
            <!-- Error Message -->
            <div v-if="recaptchaError" class="mb-4 p-3 bg-red-50 border border-red-200 rounded-lg flex items-start gap-2">
              <AlertCircle class="w-5 h-5 text-red-500 flex-shrink-0 mt-0.5" />
              <p class="text-sm text-red-600">{{ recaptchaError }}</p>
            </div>
            
            <!-- reCAPTCHA Container -->
            <div 
              id="recaptcha-container-modal"
              class="flex justify-center min-h-[78px]"
            ></div>
            
            <!-- Loading State -->
            <div v-if="!isRecaptchaLoaded && !recaptchaError" class="flex justify-center py-8">
              <div class="flex flex-col items-center gap-2">
                <div class="loading loading-spinner loading-md text-blue-500"></div>
                <p class="text-sm text-gray-500">Loading verification...</p>
              </div>
            </div>
          </div>
          
          <!-- Modal Footer -->
          <div class="px-6 py-4 bg-gray-50 rounded-b-xl border-t">
            <p class="text-xs text-gray-500 text-center">
              This verification helps protect your account from automated attacks.
            </p>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
@reference "../../style.css";
</style>
