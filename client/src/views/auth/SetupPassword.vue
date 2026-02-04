<script setup>
import { ref, onMounted, onUnmounted, getCurrentInstance } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { Eye, EyeOff, CheckCircle, KeyRound, User } from 'lucide-vue-next'
import api from '@/services/api'
import Swal from 'sweetalert2'

const route = useRoute()
const router = useRouter()

const token = ref('')
const invitationData = ref(null)
const isVerifying = ref(true)
const isValid = ref(false)
const isLoading = ref(false)
const recaptchaVerified = ref(false)
const recaptchaToken = ref('')

const showPassword = ref(false)
const showConfirmPassword = ref(false)

const formData = ref({
  name: '',
  password: '',
  password_confirmation: ''
})

const togglePassword = () => {
  showPassword.value = !showPassword.value
}

const toggleConfirmPassword = () => {
  showConfirmPassword.value = !showConfirmPassword.value
}

// reCAPTCHA Logic
const loadRecaptchaScript = () => {
  const script = document.createElement('script')
  script.src = 'https://www.google.com/recaptcha/api.js'
  script.async = true
  script.defer = true
  document.head.appendChild(script)
}

const onRecaptchaVerify = (t) => {
  recaptchaVerified.value = true
  recaptchaToken.value = t
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

const verifyToken = async () => {
  token.value = route.query.token
  
  if (!token.value) {
    Swal.fire({
      icon: 'error',
      title: 'Invalid Link',
      text: 'No invitation token found in the link'
    }).then(() => {
      router.push('/login')
    })
    return
  }

  try {
    const response = await api.get(`/auth/verify-token/${token.value}`)
    const { invitation, user_name } = response.data.data
    invitationData.value = invitation
    formData.value.name = user_name || invitation.email.split('@')[0]
    isValid.value = true
  } catch (error) {
    console.error('Verify token error:', error)
    Swal.fire({
      icon: 'error',
      title: 'Invalid or Expired Link',
      text: error.response?.data?.message || 'This invitation link is invalid or has expired'
    }).then(() => {
      router.push('/login')
    })
  } finally {
    isVerifying.value = false
  }
}

const setupPassword = async () => {
  // reCAPTCHA validation
  if (!recaptchaVerified.value) {
    Swal.fire({
      icon: 'error',
      title: 'Verification Required',
      text: 'Please verify that you are not a robot.'
    })
    return
  }

  // Validation
  if (!formData.value.name || !formData.value.password || !formData.value.password_confirmation) {
    Swal.fire({
      icon: 'warning',
      title: 'Incomplete Form',
      text: 'Please fill in all fields'
    })
    return
  }

  if (formData.value.password.length < 8) {
    Swal.fire({
      icon: 'warning',
      title: 'Password Too Short',
      text: 'Password must be at least 8 characters long'
    })
    return
  }

  if (formData.value.password !== formData.value.password_confirmation) {
    Swal.fire({
      icon: 'error',
      title: 'Passwords Do Not Match',
      text: 'Please make sure both passwords match'
    })
    return
  }

  isLoading.value = true

  try {
    const response = await api.post('/auth/setup-password', {
      token: token.value,
      name: formData.value.name,
      password: formData.value.password,
      password_confirmation: formData.value.password_confirmation,
      recaptcha_token: recaptchaToken.value
    })

    const { token: authToken, user, role } = response.data.data

    localStorage.setItem('token', authToken)
    localStorage.setItem('user', JSON.stringify(user))
    localStorage.setItem('role', role)

    await Swal.fire({
      icon: 'success',
      title: 'Account Activated!',
      text: 'Your password has been set successfully.',
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
    resetRecaptcha()
    const errorMessage = error.response?.data?.message || 'Failed to setup password'
    
    Swal.fire({
      icon: 'error',
      title: 'Activation Failed',
      text: errorMessage
    })
  } finally {
    isLoading.value = false
  }
}

const app = getCurrentInstance()
onMounted(() => {
  if (app) {
    app.appContext.config.globalProperties.$recaptchaSiteKey = import.meta.env.VITE_RECAPTCHA_SITE_KEY || '6LeBIdwrAAAAAOIONOkF3vk31VJTzoN1ElEUOhBV'
  }
  loadRecaptchaScript()
  window.onRecaptchaVerify = onRecaptchaVerify
  window.onRecaptchaExpired = onRecaptchaExpired
  verifyToken()
})

onUnmounted(() => {
  delete window.onRecaptchaVerify
  delete window.onRecaptchaExpired
})
</script>

<template>
  <div class="min-h-screen flex bg-base-100" data-theme="light">
    <!-- Left Side - Branding -->
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

    <!-- Right Side - Setup Form -->
    <div class="w-full lg:w-1/2 flex items-center justify-center p-8 lg:p-16 bg-white relative">
      <div class="absolute left-0 top-0 bottom-0 w-4 bg-[#0ea5e9] lg:block hidden"></div>
      
      <div class="w-full max-w-md space-y-8">
        <!-- Loading State -->
        <div v-if="isVerifying" class="text-center">
          <span class="loading loading-spinner loading-lg text-blue-600"></span>
          <p class="mt-4 text-gray-500">Verifying invitation...</p>
        </div>

        <!-- Setup Form -->
        <div v-else-if="isValid" class="space-y-6">
          <div class="text-center">
            <div class="flex justify-center mb-4">
               <div class="p-3 bg-green-100 rounded-full">
                  <CheckCircle class="w-8 h-8 text-green-600" />
               </div>
            </div>
            <h2 class="text-3xl font-bold text-gray-900">Activate Account</h2>
            <p class="mt-2 text-sm text-gray-500">
              Welcome! Set up your password to access the {{ invitationData?.role }} portal.
            </p>
          </div>

          <!-- Invitation Info Badge -->
          <div class="bg-gray-50 rounded-xl p-5 border border-gray-100 space-y-3">
            <div class="flex justify-between items-center text-sm">
              <span class="text-gray-500">Email Address</span>
              <span class="font-semibold text-gray-900">{{ invitationData?.email }}</span>
            </div>
            <div class="flex justify-between items-center text-sm">
              <span class="text-gray-500">Department</span>
              <span class="font-semibold text-gray-900">{{ invitationData?.department }}</span>
            </div>
            <div class="flex justify-between items-center text-sm">
              <span class="text-gray-500">Assigned Role</span>
              <span class="badge badge-primary px-4 py-3 capitalize">{{ invitationData?.role }}</span>
            </div>
          </div>

          <form @submit.prevent="setupPassword" class="space-y-5">
            <!-- Name -->
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <User class="h-5 w-5 text-gray-400" />
              </div>
              <input 
                v-model="formData.name"
                type="text"
                required
                class="input input-bordered w-full pl-10 bg-gray-50 text-gray-900 border-gray-200 focus:border-blue-500 focus:ring-blue-500"
                placeholder="Confirm your full name"
              />
            </div>

            <!-- Password -->
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                <KeyRound class="h-5 w-5 text-gray-400" />
              </div>
              <input 
                v-model="formData.password"
                :type="showPassword ? 'text' : 'password'"
                required
                class="input input-bordered w-full pl-10 pr-10 bg-gray-50 text-gray-900 border-gray-200"
                placeholder="Initial Password (min 8 characters)"
              />
              <button
                type="button"
                @click="togglePassword"
                class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600"
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
                v-model="formData.password_confirmation"
                :type="showConfirmPassword ? 'text' : 'password'"
                required
                class="input input-bordered w-full pl-10 pr-10 bg-gray-50 text-gray-900 border-gray-200"
                placeholder="Confirm Password"
              />
              <button
                type="button"
                @click="toggleConfirmPassword"
                class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600"
              >
                <component :is="showConfirmPassword ? EyeOff : Eye" size="18" />
              </button>
            </div>

            <!-- reCAPTCHA -->
            <div class="flex justify-center py-2">
              <div 
                class="g-recaptcha" 
                :data-sitekey="$recaptchaSiteKey"
                data-callback="onRecaptchaVerify"
                data-expired-callback="onRecaptchaExpired"
              ></div>
            </div>

            <!-- Submit Button -->
            <button 
              type="submit"
              class="btn btn-primary w-full bg-blue-600 hover:bg-blue-700 border-none text-white font-bold h-12 shadow-lg shadow-blue-200 disabled:opacity-50"
              :disabled="isLoading || !recaptchaVerified"
            >
              <span v-if="isLoading" class="loading loading-spinner loading-sm"></span>
              <span v-else>Activate & Continue</span>
            </button>
          </form>

          <p class="text-center text-xs text-gray-400 pt-4">
            By activating your account, you agree to the BukSU Memofy Portal terms and conditions.
          </p>
        </div>
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
