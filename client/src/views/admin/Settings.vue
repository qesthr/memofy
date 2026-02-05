<script setup>
import { ref, onMounted } from 'vue'
import { Lock, Clock, Save, RefreshCw, Shield, AlertCircle, CheckCircle } from 'lucide-vue-next'
import api from '../../services/api'
import Swal from 'sweetalert2'

const loading = ref(true)
const saving = ref(false)
const testingConnection = ref(false)

const lockSettings = ref({
  minutes: 1,
  seconds: 50,
  isConfigured: false
})

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

const formatDuration = (minutes, seconds) => {
  const mins = String(minutes).padStart(2, '0')
  const secs = String(seconds).padStart(2, '0')
  return `${mins}:${secs}`
}

const isDirty = () => {
  return lockSettings.value.minutes !== originalSettings.value.minutes ||
         lockSettings.value.seconds !== originalSettings.value.seconds
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
      <div class="bg-base-100 rounded-xl border border-base-200 overflow-hidden">
        <div class="p-6 border-b border-base-200">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-primary/10 flex items-center justify-center">
              <Lock class="text-primary" :size="20" />
            </div>
            <div>
              <h2 class="text-lg font-bold">Concurrency Control / Lock Settings</h2>
              <p class="text-sm text-base-content/60">Manage user edit lock timeout for concurrent editing prevention</p>
            </div>
          </div>
        </div>

        <div class="p-6 space-y-6">
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
                class="input input-bordered w-full"
                :class="{ 'input-error': lockSettings.minutes < 0 || lockSettings.minutes > 60 }"
              />
              <label class="label">
                <span class="label-text-alt text-base-content/50">Lock duration in minutes (0-60)</span>
              </label>
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
                class="input input-bordered w-full"
                :class="{ 'input-error': lockSettings.seconds < 0 || lockSettings.seconds > 59 }"
              />
              <label class="label">
                <span class="label-text-alt text-base-content/50">Lock duration in seconds (0-59)</span>
              </label>
            </div>

            <div class="form-control">
              <label class="label py-1">
                <span class="label-text font-semibold flex items-center gap-2">
                  <RefreshCw :size="14" />
                  Total Duration
                </span>
              </label>
              <div class="input input-bordered w-full bg-base-200/50 flex items-center justify-center text-2xl font-mono font-bold">
                {{ formatDuration(lockSettings.minutes, lockSettings.seconds) }}
              </div>
              <label class="label">
                <span class="label-text-alt text-base-content/50">Total lock timeout</span>
              </label>
            </div>
          </div>

          <div class="flex items-center justify-between pt-4 border-t border-base-200">
            <div class="flex items-center gap-2">
              <CheckCircle v-if="lockSettings.isConfigured" class="text-success" :size="18" />
              <span v-if="lockSettings.isConfigured" class="text-sm text-success">Settings configured</span>
              <span v-else class="text-sm text-warning">Using default settings</span>
            </div>

            <div class="flex items-center gap-3">
              <button
                @click="resetToDefault"
                class="btn btn-ghost btn-sm"
                :disabled="lockSettings.minutes === 1 && lockSettings.seconds === 50"
              >
                Reset to Default
              </button>
              <button
                @click="saveSettings"
                class="btn btn-primary text-white btn-sm gap-2"
                :disabled="!isDirty() || saving || lockSettings.minutes < 0 || lockSettings.minutes > 60 || lockSettings.seconds < 0 || lockSettings.seconds > 59"
              >
                <Save v-if="!saving" :size="16" />
                <span v-if="saving" class="loading loading-spinner loading-sm"></span>
                {{ saving ? 'Saving...' : 'Save Settings' }}
              </button>
            </div>
          </div>
        </div>
      </div>

      <div class="bg-base-100 rounded-xl border border-base-200 overflow-hidden mt-6">
        <div class="p-6 border-b border-base-200">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-success/10 flex items-center justify-center">
              <Shield class="text-success" :size="20" />
            </div>
            <div>
              <h2 class="text-lg font-bold">Lock Behavior Summary</h2>
              <p class="text-sm text-base-content/60">How the locking mechanism works</p>
            </div>
          </div>
        </div>

        <div class="p-6">
          <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="p-4 rounded-lg bg-base-200/50">
              <h3 class="font-semibold mb-2">Lock Acquisition</h3>
              <ul class="text-sm text-base-content/70 space-y-1">
                <li>• Admin clicks "Edit" on a user</li>
                <li>• System acquires exclusive write lock</li>
                <li>• Lock owner is recorded</li>
              </ul>
            </div>

            <div class="p-4 rounded-lg bg-base-200/50">
              <h3 class="font-semibold mb-2">Concurrent Prevention</h3>
              <ul class="text-sm text-base-content/70 space-y-1">
                <li>• Other admins see lock indicator</li>
                <li>• Cannot edit locked users</li>
                <li>• Can edit other users normally</li>
              </ul>
            </div>

            <div class="p-4 rounded-lg bg-base-200/50">
              <h3 class="font-semibold mb-2">Auto-Renewal</h3>
              <ul class="text-sm text-base-content/70 space-y-1">
                <li>• Heartbeat requests refresh lock</li>
                <li>• Prevents premature expiration</li>
                <li>• Active editing keeps lock alive</li>
              </ul>
            </div>

            <div class="p-4 rounded-lg bg-base-200/50">
              <h3 class="font-semibold mb-2">Lock Release</h3>
              <ul class="text-sm text-base-content/70 space-y-1">
                <li>• Save changes</li>
                <li>• Cancel editing</li>
                <li>• Navigate away</li>
                <li>• Timeout expires</li>
              </ul>
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
