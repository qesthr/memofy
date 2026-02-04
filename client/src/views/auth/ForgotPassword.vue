<script setup>
import { ref, onMounted, onUnmounted, getCurrentInstance } from 'vue'
import { useRouter } from 'vue-router'
import { Mail, KeyRound, Eye, EyeOff, ArrowLeft, CheckCircle2 } from 'lucide-vue-next'
import api from '@/services/api'
import Swal from 'sweetalert2'

const router = useRouter()

// Make recaptcha site key available globally in instance
const app = getCurrentInstance()
if (app) {
  app.appContext.config.globalProperties.$recaptchaSiteKey = import.meta.env.VITE_RECAPTCHA_SITE_KEY || '6LeBIdwrAAAAAOIONOkF3vk31VJTzoN1ElEUOhBV'
}

const step = ref(1) // 1: Request, 2: Reset
const isLoading = ref(false)
const recaptchaVerified = ref(false)
const recaptchaToken = ref('')

// Step 1: Request
const email = ref('')

// Step 2: Reset
const code = ref('')
const password = ref('')
const password_confirmation = ref('')
const showPassword = ref(false)

const togglePassword = () => {
  showPassword.value = !showPassword.value
}

// reCAPTCHA Logic
const loadRecaptchaScript = () => {
  const script = document.createElement('script')
  script.src = 'https://www.google.com/recaptcha/api.js'
  script.async = true
  script.defer = true
  document.head.appendChild(script)
}

const onRecaptchaVerify = (token) => {
  recaptchaVerified.value = true
  recaptchaToken.value = token
}

const onRecaptchaExpired = () => {
  recaptchaVerified.value = false
  recaptchaToken.value = ''
}

const resetRecaptcha = () => {
  if (window.grecaptcha) {
    window.grecaptcha.reset()
    recaptchaVerified.value = false
    recaptchaToken.value = ''
  }
}

const handleRequestCode = async () => {
  if (!recaptchaVerified.value) {
    Swal.fire({
      icon: 'error',
      title: 'Verification Required',
      text: 'Please verify that you are not a robot before continuing.'
    })
    return
  }

  isLoading.value = true
  
  try {
    const response = await api.post('/forgot-password', {
      email: email.value,
      recaptcha_token: recaptchaToken.value
    })

    await Swal.fire({
      icon: 'success',
      title: 'Code Sent',
      text: response.data.message,
      timer: 2000,
      showConfirmButton: false
    })

    step.value = 2
    resetRecaptcha()
  } catch (err) {
    console.error('Request code error:', err)
    resetRecaptcha()
    
    let errorMsg = 'An unexpected error occurred. Please try again later.'
    if (err.response) {
      errorMsg = err.response.data.message
    }

    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: errorMsg
    })
  } finally {
    isLoading.value = false
  }
}

const handleResetPassword = async () => {
  if (!recaptchaVerified.value) {
    Swal.fire({
      icon: 'error',
      title: 'Verification Required',
      text: 'Please verify that you are not a robot before continuing.'
    })
    return
  }

  if (password.value !== password_confirmation.value) {
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: 'Passwords do not match.'
    })
    return
  }

  isLoading.value = true
  
  try {
    const response = await api.post('/reset-password', {
      email: email.value,
      code: code.value,
      password: password.value,
      password_confirmation: password_confirmation.value,
      recaptcha_token: recaptchaToken.value
    })

    await Swal.fire({
      icon: 'success',
      title: 'Password Reset Successful',
      text: response.data.message,
      timer: 2000,
      showConfirmButton: false
    })

    router.push('/login')
  } catch (err) {
    console.error('Reset password error:', err)
    resetRecaptcha()
    
    let errorMsg = 'Unable to reset password. Please try again.'
    if (err.response) {
      errorMsg = err.response.data.message
    }

    Swal.fire({
      icon: 'error',
      title: 'Reset Failed',
      text: errorMsg
    })
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  loadRecaptchaScript()
  window.onRecaptchaVerify = onRecaptchaVerify
  window.onRecaptchaExpired = onRecaptchaExpired
})

onUnmounted(() => {
  delete window.onRecaptchaVerify
  delete window.onRecaptchaExpired
})
</script>

<template>
  <div class="min-h-screen flex bg-base-100" data-theme="light">
    <!-- Left Side - Branding (Shared with Login) -->
    <div class="hidden lg:flex lg:w-1/2 relative bg-[#1e293b] overflow-hidden items-center justify-center">
      <div 
        class="absolute inset-0 bg-cover bg-center opacity-20"
        style="background-image: url('/src/assets/images/images/UNI.jpg')"
      ></div>
      <div class="relative z-10 flex flex-col items-center justify-center text-center p-12">
        <img src="../../assets/images/images/Buksu-Logo.png" alt="BukSU Logo" class="w-64 mb-8 drop-shadow-xl" />
        <h1 class="text-4xl font-bold text-white tracking-wide mb-2 uppercase">BukSU</h1>
        <p class="text-yellow-400 font-semibold text-lg tracking-wider uppercase">Educate. Innovate. Lead.</p>
      </div>
      <div class="absolute right-0 top-0 bottom-0 w-24 bg-[#0ea5e9] skew-x-[-10deg] translate-x-12"></div>
      <div class="absolute right-0 top-0 bottom-0 w-24 bg-[#0284c7] skew-x-[-10deg] translate-x-6 z-0"></div>
    </div>

    <!-- Right Side - Forgot Password Form -->
    <div class="w-full lg:w-1/2 flex items-center justify-center p-8 lg:p-16 bg-white relative">
      <div class="absolute left-0 top-0 bottom-0 w-4 bg-[#0ea5e9] lg:block hidden"></div>

      <div class="w-full max-w-md space-y-8">
        <div class="text-center">
          <div class="flex justify-center mb-4">
             <div class="p-3 bg-blue-100 rounded-full">
                <KeyRound class="w-8 h-8 text-blue-600" />
             </div>
          </div>
          <h2 class="text-3xl font-bold text-gray-900">Forgot Password?</h2>
          <p class="mt-2 text-sm text-gray-500">
            {{ step === 1 ? "Enter your email and we'll send you a reset code." : "Enter the 6-digit code sent to your email." }}
          </p>
        </div>

        <!-- Step 1: Request Code -->
        <form v-if="step === 1" @submit.prevent="handleRequestCode" class="mt-8 space-y-6">
          <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
              <Mail class="h-5 w-5 text-gray-400" />
            </div>
            <input
              v-model="email"
              type="email"
              required
              class="input input-bordered w-full pl-10 bg-gray-50 text-gray-900 border-gray-200 focus:border-blue-500 focus:ring-blue-500"
              placeholder="Enter your email"
            />
          </div>

          <!-- reCAPTCHA -->
          <div class="flex justify-center">
            <div 
              class="g-recaptcha" 
              :data-sitekey="$recaptchaSiteKey"
              data-callback="onRecaptchaVerify"
              data-expired-callback="onRecaptchaExpired"
            ></div>
          </div>

          <div class="space-y-4">
             <button
                type="submit"
                :disabled="isLoading || !recaptchaVerified"
                class="btn btn-primary w-full bg-blue-600 hover:bg-blue-700 border-none text-white font-bold py-3 px-4 rounded-lg flex items-center justify-center gap-2"
              >
                <span v-if="isLoading" class="loading loading-spinner loading-sm"></span>
                {{ isLoading ? 'Processing...' : 'Send Reset Code' }}
              </button>

              <router-link to="/login" class="flex items-center justify-center gap-2 text-sm text-gray-500 hover:text-blue-600 transition-colors">
                <ArrowLeft class="w-4 h-4" />
                Back to Login
              </router-link>
          </div>
        </form>

        <!-- Step 2: Verification and Reset -->
        <form v-else @submit.prevent="handleResetPassword" class="mt-8 space-y-6">
           <div class="space-y-4">
              <!-- Code Input -->
              <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                  <CheckCircle2 class="h-5 w-5 text-gray-400" />
                </div>
                <input
                  v-model="code"
                  type="text"
                  required
                  maxlength="6"
                  class="input input-bordered w-full pl-10 bg-gray-50 text-gray-900"
                  placeholder="6-digit reset code"
                />
              </div>

              <!-- New Password -->
              <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                   <KeyRound class="h-5 w-5 text-gray-400" />
                </div>
                <input
                  v-model="password"
                  :type="showPassword ? 'text' : 'password'"
                  required
                  class="input input-bordered w-full pl-10 pr-10 bg-gray-50 text-gray-900"
                  placeholder="New Password"
                />
                <button 
                  type="button"
                  @click="togglePassword"
                  class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600 cursor-pointer"
                >
                  <component :is="showPassword ? EyeOff : Eye" size="18" />
                </button>
              </div>

              <!-- Confirm Password -->
              <div class="relative">
                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                   <KeyRound class="h-5 w-5 text-gray-400" />
                </div>
                <input
                  v-model="password_confirmation"
                  :type="showPassword ? 'text' : 'password'"
                  required
                  class="input input-bordered w-full pl-10 bg-gray-50 text-gray-900"
                  placeholder="Confirm New Password"
                />
              </div>
           </div>

           <!-- reCAPTCHA -->
           <div class="flex justify-center">
            <div 
              class="g-recaptcha" 
              :data-sitekey="$recaptchaSiteKey"
              data-callback="onRecaptchaVerify"
              data-expired-callback="onRecaptchaExpired"
            ></div>
          </div>

          <div class="space-y-4">
             <button
                type="submit"
                :disabled="isLoading || !recaptchaVerified"
                class="btn btn-primary w-full bg-blue-600 hover:bg-blue-700 border-none text-white font-bold py-3 px-4 rounded-lg shadow-lg shadow-blue-200"
              >
                <span v-if="isLoading" class="loading loading-spinner loading-sm"></span>
                {{ isLoading ? 'Resetting Password...' : 'Reset Password' }}
              </button>

              <button 
                type="button"
                @click="step = 1; resetRecaptcha()" 
                class="flex items-center justify-center gap-2 text-sm text-gray-500 hover:text-blue-600 transition-colors w-full"
              >
                <ArrowLeft class="w-4 h-4" />
                Resend code
              </button>
          </div>
        </form>
      </div>
    </div>
  </div>
</template>

<style scoped>
@reference "../../style.css";

.input:focus {
  @apply ring-2 ring-blue-500/20 border-blue-500;
}
</style>
