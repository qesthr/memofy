<script setup>
import { ref, onMounted } from 'vue'
import { Lock, Clock, Save, RefreshCw, Shield, AlertCircle, CheckCircle, ChevronDown, ChevronRight, UserCheck } from 'lucide-vue-next'
import api from '../../services/api'
import Swal from 'sweetalert2'
import { useAuth } from '@/composables/useAuth'

const { refreshSessionTimeout } = useAuth()

const loading = ref(true)
const saving = ref(false)
const testingConnection = ref(false)
const activeSection = ref('concurrency')

const toggleSection = (section) => {
  activeSection.value = activeSection.value === section ? null : section
}

const lockSettings = ref({
  minutes: 1,
  seconds: 50,
  isConfigured: false
})

// Lockout Settings (for brute-force account lockout)
const lockoutSettings = ref({
  minutes: 15
})
const originalLockoutSettings = ref({
  minutes: 15
})
const isSavingLockout = ref(false)

const securitySettings = ref({
  allowedDomains: [],
  newDomain: ''
})
const defaultDomains = ['buksu.edu.ph']
const isDomainSaving = ref(false)

// Session Timeout Settings
const sessionTimeoutSettings = ref({
  minutes: 30
})
const originalSessionTimeoutSettings = ref({
  minutes: 30
})
const isSavingSessionTimeout = ref(false)

const originalSettings = ref({
  minutes: 1,
  seconds: 50
})

const fetchSettings = async () => {
  loading.value = true
  try {
    const response = await api.get('/locks/settings')
    lockSettings.value = {
      minutes: response.data.lock_duration.minutes,
      seconds: response.data.lock_duration.seconds,
      isConfigured: true
    }
    originalSettings.value = {
      minutes: response.data.lock_duration.minutes,
      seconds: response.data.lock_duration.seconds
    }
    
    // Fetch system settings (domains + lockout)
    const sysResponse = await api.get('/system-settings')
    securitySettings.value.allowedDomains = sysResponse.data.allowed_email_domains || []
    
    // Lockout duration
    const lockoutMinutes = sysResponse.data.login_lockout_minutes || 15
    lockoutSettings.value.minutes = lockoutMinutes
    originalLockoutSettings.value.minutes = lockoutMinutes
    
    // Session timeout
    const sessionTimeoutMinutes = sysResponse.data.session_timeout_minutes || 30
    sessionTimeoutSettings.value.minutes = sessionTimeoutMinutes
    originalSessionTimeoutSettings.value.minutes = sessionTimeoutMinutes
  } catch (error) {
    console.error('Error fetching settings:', error)
    lockSettings.value = {
      minutes: 1,
      seconds: 50,
      isConfigured: false
    }
  } finally {
    loading.value = false
  }
}

const saveSettings = async () => {
  saving.value = true
  try {
    await api.put('/locks/settings', {
      minutes: lockSettings.value.minutes,
      seconds: lockSettings.value.seconds
    })

    originalSettings.value = {
      minutes: lockSettings.value.minutes,
      seconds: lockSettings.value.seconds
    }

    // Save system settings
    await api.put('/system-settings', {
      allowed_email_domains: securitySettings.value.allowedDomains
    })

    await Swal.fire({
      title: 'Settings Saved',
      text: 'Lock duration has been updated successfully. New locks will use the updated duration.',
      icon: 'success',
      confirmButtonText: 'OK',
      timer: 3000,
      timerProgressBar: true
    })
  } catch (error) {
    console.error('Error saving settings:', error)
    await Swal.fire({
      title: 'Error',
      text: 'Failed to save settings. Please try again.',
      icon: 'error',
      confirmButtonText: 'OK'
    })
  } finally {
    saving.value = false
  }
}

const resetToDefault = () => {
  lockSettings.value.minutes = 1
  lockSettings.value.seconds = 50
}

const saveLockoutSettings = async () => {
  isSavingLockout.value = true
  try {
    await api.put('/system-settings', {
      login_lockout_minutes: lockoutSettings.value.minutes
    })
    originalLockoutSettings.value.minutes = lockoutSettings.value.minutes
    await Swal.fire({
      title: 'Saved!',
      text: `Login lockout duration set to ${lockoutSettings.value.minutes} minute(s).`,
      icon: 'success',
      timer: 2000,
      showConfirmButton: false
    })
  } catch (error) {
    console.error('Error saving lockout settings:', error)
    Swal.fire({ title: 'Error', text: 'Failed to save lockout settings.', icon: 'error' })
  } finally {
    isSavingLockout.value = false
  }
}

const resetLockoutToDefault = () => {
  lockoutSettings.value.minutes = 15
}

const saveSessionTimeoutSettings = async () => {
  isSavingSessionTimeout.value = true
  try {
    await api.put('/system-settings', {
      session_timeout_minutes: sessionTimeoutSettings.value.minutes
    })
    originalSessionTimeoutSettings.value.minutes = sessionTimeoutSettings.value.minutes
    
    // Refresh session timeout for all active users
    await refreshSessionTimeout()
    
    await Swal.fire({
      title: 'Saved!',
      text: `Session timeout set to ${sessionTimeoutSettings.value.minutes} minute(s). Active sessions will use the new timeout.`,
      icon: 'success',
      timer: 2000,
      showConfirmButton: false
    })
  } catch (error) {
    console.error('Error saving session timeout settings:', error)
    Swal.fire({ title: 'Error', text: 'Failed to save session timeout settings.', icon: 'error' })
  } finally {
    isSavingSessionTimeout.value = false
  }
}

const resetSessionTimeoutToDefault = () => {
  sessionTimeoutSettings.value.minutes = 30
}

const formatDuration = (minutes, seconds) => {
  const mins = String(minutes).padStart(2, '0')
  const secs = String(seconds).padStart(2, '0')
  return `${mins}:${secs}`
}

const isDirty = () => {
  return lockSettings.value.minutes !== originalSettings.value.minutes ||
         lockSettings.value.seconds !== originalSettings.value.seconds
}

const addDomain = () => {
  const domain = securitySettings.value.newDomain.trim()
  if (domain && !securitySettings.value.allowedDomains.includes(domain)) {
    // Basic validation
    if (!domain.includes('.') || domain.includes('@')) {
      Swal.fire('Invalid Format', 'Please enter a valid domain (e.g., example.com)', 'warning')
      return
    }
    securitySettings.value.allowedDomains.push(domain)
    securitySettings.value.newDomain = ''
  }
}

const removeDomain = (index) => {
  const domain = securitySettings.value.allowedDomains[index]
  if (defaultDomains.includes(domain)) {
     Swal.fire('Default Domain', ' This is a required system domain and cannot be removed.', 'info')
     return
  }
  securitySettings.value.allowedDomains.splice(index, 1)
}

const saveDomainSettings = async () => {
  isDomainSaving.value = true
  try {
    await api.put('/system-settings', {
      allowed_email_domains: securitySettings.value.allowedDomains
    })

    await Swal.fire({
      title: 'Domains Saved',
      text: 'Allowed email domains have been updated successfully.',
      icon: 'success',
      timer: 2000,
      showConfirmButton: false
    })
    
    // Refresh to get potentially cleaned/re-ordered list from backend
    fetchSettings()
  } catch (error) {
    console.error('Error saving domains:', error)
     await Swal.fire({
      title: 'Error',
      text: 'Failed to save domain settings.',
      icon: 'error'
    })
  } finally {
    isDomainSaving.value = false
  }
}

onMounted(() => {
  fetchSettings()
})
</script>

<template>
  <div class="view-container">
    <div class="view-header">
      <h1 class="text-2xl font-bold">Settings</h1>
      <p class="text-base-content/60">System configuration and preferences</p>
    </div>

    <div v-if="loading" class="flex items-center justify-center py-20">
      <span class="loading loading-spinner loading-lg text-primary"></span>
    </div>

    <template v-else>
      <div class="space-y-4">
        <!-- Section 1: Concurrency Control -->
        <div class="bg-base-100 rounded-xl border border-base-200 overflow-hidden shadow-sm transition-all duration-300">
          <button 
            @click="toggleSection('concurrency')"
            class="w-full p-6 flex items-center justify-between hover:bg-base-50/50 transition-colors"
            :class="{ 'border-b border-base-200': activeSection === 'concurrency' }"
          >
            <div class="flex items-center gap-4">
              <div class="w-12 h-12 rounded-xl bg-primary/10 flex items-center justify-center">
                <Lock class="text-primary" :size="24" />
              </div>
              <div class="text-left">
                <h2 class="text-lg font-bold">Concurrency Control</h2>
                <p class="text-sm text-base-content/60">Configure lock timeouts for concurrent editing</p>
              </div>
            </div>
            <div class="transition-transform duration-300" :class="{ 'rotate-180': activeSection === 'concurrency' }">
              <ChevronDown :size="24" class="text-base-content/30" />
            </div>
          </button>

          <div v-show="activeSection === 'concurrency'" class="p-6 space-y-8 animate-in fade-in slide-in-from-top-2 duration-300">
            <div class="space-y-6">
              <div class="bg-blue-50/50 rounded-lg p-4 border border-blue-100">
                <div class="flex items-start gap-3">
                  <AlertCircle class="text-blue-500 mt-0.5" :size="18" />
                  <div>
                    <h3 class="font-semibold text-blue-700 text-sm">About Two-Phase Locking</h3>
                    <p class="text-sm text-blue-600 mt-1">
                      When an admin starts editing a user, a lock is acquired to prevent other admins from editing the same user simultaneously.
                      The lock automatically expires after the configured duration if not renewed.
                    </p>
                  </div>
                </div>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <!-- Lock settings inputs... (keep existing content but simplify if needed) -->
                <div class="form-control">
                  <label class="label py-1">
                    <span class="label-text font-semibold flex items-center gap-2">
                      <Clock :size="14" />
                      Minutes
                    </span>
                  </label>
                  <input
                    v-model.number="lockSettings.minutes"
                    type="number"
                    min="0"
                    max="60"
                    class="input input-bordered w-full focus:input-primary"
                    :class="{ 'input-error': lockSettings.minutes < 0 || lockSettings.minutes > 60 }"
                  />
                </div>

                <div class="form-control">
                  <label class="label py-1">
                    <span class="label-text font-semibold flex items-center gap-2">
                      <Clock :size="14" />
                      Seconds
                    </span>
                  </label>
                  <input
                    v-model.number="lockSettings.seconds"
                    type="number"
                    min="0"
                    max="59"
                    class="input input-bordered w-full focus:input-primary"
                    :class="{ 'input-error': lockSettings.seconds < 0 || lockSettings.seconds > 59 }"
                  />
                </div>

                <div class="form-control">
                  <label class="label py-1">
                    <span class="label-text font-semibold flex items-center gap-2">
                      <RefreshCw :size="14" />
                      Total Duration
                    </span>
                  </label>
                  <div class="input input-bordered w-full bg-base-200/30 flex items-center justify-center text-2xl font-mono font-bold text-primary">
                    {{ formatDuration(lockSettings.minutes, lockSettings.seconds) }}
                  </div>
                </div>
              </div>

              <div class="flex items-center justify-between pt-4 border-t border-base-200">
                <div class="flex items-center gap-2">
                  <CheckCircle v-if="lockSettings.isConfigured" class="text-success" :size="18" />
                  <span v-if="lockSettings.isConfigured" class="text-sm text-success font-medium">System configured</span>
                  <span v-else class="text-sm text-warning font-medium">Using default settings</span>
                </div>

                <div class="flex items-center gap-3">
                  <button
                    @click="resetToDefault"
                    class="btn btn-ghost btn-sm px-4"
                    :disabled="lockSettings.minutes === 1 && lockSettings.seconds === 50"
                  >
                    Reset Defaults
                  </button>
                  <button
                    @click="saveSettings"
                    class="btn btn-primary text-white btn-sm px-6 gap-2 shadow-lg shadow-primary/20"
                    :disabled="!isDirty() || saving || lockSettings.minutes < 0 || lockSettings.minutes > 60 || lockSettings.seconds < 0 || lockSettings.seconds > 59"
                  >
                    <Save v-if="!saving" :size="16" />
                    <span v-if="saving" class="loading loading-spinner loading-sm"></span>
                    {{ saving ? 'Saving...' : 'Update Settings' }}
                  </button>
                </div>
              </div>
            </div>

            <div class="divider"></div>

            <div class="space-y-4">
              <h3 class="text-sm font-bold uppercase tracking-wider text-base-content/40">Lock Behavior Summary</h3>
              <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div class="p-4 rounded-xl bg-base-50 border border-base-200/50">
                  <h4 class="font-bold text-sm mb-2 text-primary">Acquisition</h4>
                  <p class="text-xs text-base-content/60 leading-relaxed">Admin clicks edit, system acquires exclusive lock, owner recorded.</p>
                </div>
                <div class="p-4 rounded-xl bg-base-50 border border-base-200/50">
                  <h4 class="font-bold text-sm mb-2 text-primary">Prevention</h4>
                  <p class="text-xs text-base-content/60 leading-relaxed">Other admins see indicator and cannot edit same user simultaneously.</p>
                </div>
                <div class="p-4 rounded-xl bg-base-50 border border-base-200/50">
                  <h4 class="font-bold text-sm mb-2 text-primary">Renewal</h4>
                  <p class="text-xs text-base-content/60 leading-relaxed">Active editing triggers heartbeats to refresh and keep lock alive.</p>
                </div>
                <div class="p-4 rounded-xl bg-base-50 border border-base-200/50">
                  <h4 class="font-bold text-sm mb-2 text-primary">Release</h4>
                  <p class="text-xs text-base-content/60 leading-relaxed">Saved, canceled, navigation away, or timeout clears the lock.</p>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Section 2: Account Lockout Settings -->
        <div class="bg-base-100 rounded-xl border border-base-200 overflow-hidden shadow-sm transition-all duration-300">
          <button 
            @click="toggleSection('lockout')"
            class="w-full p-6 flex items-center justify-between hover:bg-base-50/50 transition-colors"
            :class="{ 'border-b border-base-200': activeSection === 'lockout' }"
          >
            <div class="flex items-center gap-4">
              <div class="w-12 h-12 rounded-xl bg-error/10 flex items-center justify-center">
                <Shield class="text-error" :size="24" />
              </div>
              <div class="text-left">
                <h2 class="text-lg font-bold">Account Lockout Settings</h2>
                <p class="text-sm text-base-content/60">Manage brute-force protection durations</p>
              </div>
            </div>
            <div class="transition-transform duration-300" :class="{ 'rotate-180': activeSection === 'lockout' }">
              <ChevronDown :size="24" class="text-base-content/30" />
            </div>
          </button>

          <div v-show="activeSection === 'lockout'" class="p-6 space-y-6 animate-in fade-in slide-in-from-top-2 duration-300">
            <div class="bg-red-50/50 rounded-lg p-4 border border-red-100">
              <div class="flex items-start gap-3">
                <AlertCircle class="text-red-500 mt-0.5" :size="18" />
                <div>
                  <h3 class="font-semibold text-red-700 text-sm">Brute-Force Protection</h3>
                  <p class="text-sm text-red-600 mt-1">
                    Accounts are automatically locked for the configured duration after 5 consecutive failed login attempts.
                    Only applies to manual login — not Google Sign-In.
                  </p>
                </div>
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div class="form-control">
                <label class="label py-1">
                  <span class="label-text font-semibold">Lockout Duration (Minutes)</span>
                </label>
                <input
                  v-model.number="lockoutSettings.minutes"
                  type="number"
                  min="1"
                  max="60"
                  class="input input-bordered w-full focus:input-error"
                  :class="{ 'input-error': lockoutSettings.minutes < 1 || lockoutSettings.minutes > 60 }"
                />
                <label class="label">
                  <span class="label-text-alt text-base-content/50">1–60 minutes (Default: 15)</span>
                </label>
              </div>

              <div class="form-control">
                <label class="label py-1">
                  <span class="label-text font-semibold">Current Setting</span>
                </label>
                <div class="input input-bordered w-full bg-base-200/30 flex items-center justify-center text-2xl font-mono font-bold text-error">
                  {{ String(lockoutSettings.minutes).padStart(2, '0') }}:00
                </div>
              </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-base-200">
              <button
                @click="resetLockoutToDefault"
                class="btn btn-ghost btn-sm"
                :disabled="lockoutSettings.minutes === 15"
              >
                Reset Default
              </button>
              <button
                @click="saveLockoutSettings"
                class="btn btn-error text-white btn-sm px-6 gap-2 shadow-lg shadow-error/20"
                :disabled="isSavingLockout || lockoutSettings.minutes < 1 || lockoutSettings.minutes > 60 || lockoutSettings.minutes === originalLockoutSettings.minutes"
              >
                <Save v-if="!isSavingLockout" :size="16" />
                <span v-if="isSavingLockout" class="loading loading-spinner loading-sm"></span>
                Save Duration
              </button>
            </div>
          </div>
        </div>

        <!-- Section 3: Session Timeout -->
        <div class="bg-base-100 rounded-xl border border-base-200 overflow-hidden shadow-sm transition-all duration-300">
          <button 
            @click="toggleSection('session')"
            class="w-full p-6 flex items-center justify-between hover:bg-base-50/50 transition-colors"
            :class="{ 'border-b border-base-200': activeSection === 'session' }"
          >
            <div class="flex items-center gap-4">
              <div class="w-12 h-12 rounded-xl bg-warning/10 flex items-center justify-center">
                <Clock class="text-warning" :size="24" />
              </div>
              <div class="text-left">
                <h2 class="text-lg font-bold">Session Timeout</h2>
                <p class="text-sm text-base-content/60">Configure automatic logout after inactivity</p>
              </div>
            </div>
            <div class="transition-transform duration-300" :class="{ 'rotate-180': activeSection === 'session' }">
              <ChevronDown :size="24" class="text-base-content/30" />
            </div>
          </button>

          <div v-show="activeSection === 'session'" class="p-6 space-y-6 animate-in fade-in slide-in-from-top-2 duration-300">
            <div class="bg-yellow-50/50 rounded-lg p-4 border border-yellow-100">
              <div class="flex items-start gap-3">
                <AlertCircle class="text-yellow-500 mt-0.5" :size="18" />
                <div>
                  <h3 class="font-semibold text-yellow-700 text-sm">Automatic Session Timeout</h3>
                  <p class="text-sm text-yellow-600 mt-1">
                    Users will be automatically logged out after the specified period of inactivity. This helps secure the application when users leave their devices unattended.
                  </p>
                </div>
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div class="form-control">
                <label class="label py-1">
                  <span class="label-text font-semibold">Timeout Duration (Minutes)</span>
                </label>
                <input
                  v-model.number="sessionTimeoutSettings.minutes"
                  type="number"
                  min="1"
                  max="1440"
                  class="input input-bordered w-full focus:input-warning"
                  :class="{ 'input-error': sessionTimeoutSettings.minutes < 1 || sessionTimeoutSettings.minutes > 1440 }"
                />
                <label class="label">
                  <span class="label-text-alt text-base-content/50">1–1440 minutes (Default: 30)</span>
                </label>
              </div>

              <div class="form-control">
                <label class="label py-1">
                  <span class="label-text font-semibold">Current Setting</span>
                </label>
                <div class="input input-bordered w-full bg-base-200/30 flex items-center justify-center text-2xl font-mono font-bold text-warning">
                  {{ sessionTimeoutSettings.minutes }} min
                </div>
              </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-base-200">
              <button
                @click="resetSessionTimeoutToDefault"
                class="btn btn-ghost btn-sm"
                :disabled="sessionTimeoutSettings.minutes === 30"
              >
                Reset Default
              </button>
              <button
                @click="saveSessionTimeoutSettings"
                class="btn btn-warning text-white btn-sm px-6 gap-2 shadow-lg shadow-warning/20"
                :disabled="isSavingSessionTimeout || sessionTimeoutSettings.minutes < 1 || sessionTimeoutSettings.minutes > 1440 || sessionTimeoutSettings.minutes === originalSessionTimeoutSettings.minutes"
              >
                <Save v-if="!isSavingSessionTimeout" :size="16" />
                <span v-if="isSavingSessionTimeout" class="loading loading-spinner loading-sm"></span>
                Save Duration
              </button>
            </div>
          </div>
        </div>

        <!-- Section 4: Access Control -->
        <div class="bg-base-100 rounded-xl border border-base-200 overflow-hidden shadow-sm transition-all duration-300">
          <button 
            @click="toggleSection('access')"
            class="w-full p-6 flex items-center justify-between hover:bg-base-50/50 transition-colors"
            :class="{ 'border-b border-base-200': activeSection === 'access' }"
          >
            <div class="flex items-center gap-4">
              <div class="w-12 h-12 rounded-xl bg-info/10 flex items-center justify-center">
                <UserCheck class="text-info" :size="24" />
              </div>
              <div class="text-left">
                <h2 class="text-lg font-bold">Access Control</h2>
                <p class="text-sm text-base-content/60">Manage allowed email domains for registrations</p>
              </div>
            </div>
            <div class="transition-transform duration-300" :class="{ 'rotate-180': activeSection === 'access' }">
              <ChevronDown :size="24" class="text-base-content/30" />
            </div>
          </button>

          <div v-show="activeSection === 'access'" class="p-6 space-y-6 animate-in fade-in slide-in-from-top-2 duration-300">
            <div class="form-control">
              <label class="label pt-0">
                <span class="label-text font-semibold">Allowed Email Domains</span>
              </label>
              <div class="flex gap-2 max-w-xl">
                <input 
                  v-model="securitySettings.newDomain"
                  @keyup.enter="addDomain"
                  type="text" 
                  placeholder="e.g. buksu.edu.ph" 
                  class="input input-bordered w-full focus:input-primary"
                />
                <button @click="addDomain" class="btn btn-primary text-white px-6">
                  Add
                </button>
              </div>
            </div>

            <div class="space-y-3">
              <h3 class="text-xs font-bold uppercase tracking-wider text-base-content/40">Registered Domains</h3>
              <div class="flex flex-wrap gap-2">
                <div 
                  v-for="(domain, index) in securitySettings.allowedDomains" 
                  :key="index"
                  class="badge badge-lg gap-2 py-5 px-4 bg-base-200 border-none rounded-lg"
                  :class="{ 'bg-primary/10 text-primary': defaultDomains.includes(domain) }"
                >
                  <Lock v-if="defaultDomains.includes(domain)" :size="12" />
                  <span class="font-medium font-mono">{{ domain }}</span>
                  <button 
                    v-if="!defaultDomains.includes(domain)" 
                    @click="removeDomain(index)" 
                    class="btn btn-ghost btn-xs btn-circle text-error hover:bg-error/10"
                  >
                    <X :size="14" />
                  </button>
                </div>
                <div v-if="securitySettings.allowedDomains.length === 0" class="text-sm text-base-content/40 italic py-2">
                  No custom domains configured.
                </div>
              </div>
            </div>
            
            <div class="flex justify-end pt-4 border-t border-base-200">
               <button 
                @click="saveDomainSettings" 
                class="btn btn-primary text-white px-8"
                :disabled="isDomainSaving"
              >
                <Save v-if="!isDomainSaving" :size="18" class="mr-2" />
                <span v-else class="loading loading-spinner loading-sm mr-2"></span>
                Save Domain Access
              </button>
            </div>
          </div>
        </div>
      </div>
    </template>

  </div>
</template>

<style scoped>
@reference "../../style.css";

.view-container {
  @apply p-0;
}

.view-header {
  @apply mb-6;
}
</style>
