<script setup>
import { ref, reactive } from 'vue'
import { X, User, Lock, FileText, Save } from 'lucide-vue-next'
import api from '@/services/api'
import Swal from 'sweetalert2'
import { useAuth } from '@/composables/useAuth'

const props = defineProps({
  isOpen: Boolean
})

const emit = defineEmits(['close'])

const { user, fetchUser } = useAuth()
const activeTab = ref('profile') // profile, password
const isSavingProfile = ref(false)
const isSavingPassword = ref(false)

const profileForm = reactive({
  first_name: user.value?.first_name || '',
  last_name: user.value?.last_name || '',
  bio: user.value?.bio || ''
})

const passwordForm = reactive({
  current_password: '',
  new_password: '',
  new_password_confirmation: ''
})

const updateProfile = async () => {
  isSavingProfile.value = true
  try {
    const response = await api.put('/me', {
       first_name: profileForm.first_name,
       last_name: profileForm.last_name,
       bio: profileForm.bio
    })
    
    if (response.data.success) {
      await fetchUser()
      Swal.fire({
        title: 'Success!',
        text: 'Profile updated successfully',
        icon: 'success',
        timer: 1500,
        showConfirmButton: false
      })
    }
  } catch (error) {
    console.error('Error updating profile:', error)
    Swal.fire('Error', error.response?.data?.message || 'Failed to update profile', 'error')
  } finally {
    isSavingProfile.value = false
  }
}

const updatePassword = async () => {
  if (passwordForm.new_password !== passwordForm.new_password_confirmation) {
    Swal.fire('Error', 'Passwords do not match', 'error')
    return
  }

  isSavingPassword.value = true
  try {
    const response = await api.put('/me/password', passwordForm)
    if (response.data.success) {
      Swal.fire({
        title: 'Success!',
        text: 'Password updated successfully',
        icon: 'success',
        timer: 1500,
        showConfirmButton: false
      })
      // Clear form
      passwordForm.current_password = ''
      passwordForm.new_password = ''
      passwordForm.new_password_confirmation = ''
    }
  } catch (error) {
    console.error('Error updating password:', error)
    Swal.fire('Error', error.response?.data?.message || 'Failed to update password', 'error')
  } finally {
    isSavingPassword.value = false
  }
}

const close = () => {
  emit('close')
}
</script>

<template>
  <Teleport to="body">
    <div v-if="isOpen" class="modal modal-open z-[100]">
      <div class="modal-box max-w-2xl p-0 overflow-hidden">
        <!-- Header -->
        <div class="p-6 border-b border-base-200 flex items-center justify-between">
          <h3 class="font-bold text-lg">My Account</h3>
          <button @click="close" class="btn btn-sm btn-circle btn-ghost"><X :size="20" /></button>
        </div>

        <div class="flex flex-col md:flex-row min-h-[400px]">
          <!-- Sidebar Tabs -->
          <div class="w-full md:w-48 bg-base-200/50 p-2 space-y-1">
            <button 
              @click="activeTab = 'profile'"
              class="btn btn-ghost btn-block justify-start gap-2"
              :class="{ 'btn-active bg-base-100 shadow-sm': activeTab === 'profile' }"
            >
              <User :size="18" /> Profile Info
            </button>
            <button 
              @click="activeTab = 'password'"
              class="btn btn-ghost btn-block justify-start gap-2"
              :class="{ 'btn-active bg-base-100 shadow-sm': activeTab === 'password' }"
            >
              <Lock :size="18" /> Password
            </button>
          </div>

          <!-- Content Area -->
          <div class="flex-1 p-6 overflow-y-auto">
            <!-- Profile Tab -->
            <div v-if="activeTab === 'profile'" class="space-y-6">
              <div class="grid grid-cols-2 gap-4">
                <div class="form-control w-full">
                  <label class="label">
                    <span class="label-text font-semibold">First Name</span>
                  </label>
                  <input v-model="profileForm.first_name" type="text" class="input input-bordered w-full" />
                </div>
                <div class="form-control w-full">
                  <label class="label">
                    <span class="label-text font-semibold">Last Name</span>
                  </label>
                  <input v-model="profileForm.last_name" type="text" class="input input-bordered w-full" />
                </div>
              </div>

              <div class="form-control w-full">
                <label class="label">
                  <span class="label-text font-semibold">Email (Read-only)</span>
                </label>
                <input :value="user?.email" type="email" class="input input-bordered w-full bg-base-200" readonly />
              </div>

              <div class="form-control w-full">
                <label class="label">
                  <span class="label-text font-semibold flex items-center gap-2"><FileText :size="16" /> About Me</span>
                </label>
                <textarea 
                  v-model="profileForm.bio" 
                  class="textarea textarea-bordered h-32 w-full" 
                  placeholder="Tell others about yourself..."
                ></textarea>
              </div>

              <div class="flex justify-end">
                <button @click="updateProfile" class="btn btn-primary gap-2" :disabled="isSavingProfile">
                  <span v-if="isSavingProfile" class="loading loading-spinner loading-sm"></span>
                  <Save v-else :size="18" /> Save Changes
                </button>
              </div>
            </div>

            <!-- Password Tab -->
            <div v-if="activeTab === 'password'" class="space-y-4">
              <div class="form-control w-full">
                <label class="label">
                  <span class="label-text font-semibold">Current Password</span>
                </label>
                <input v-model="passwordForm.current_password" type="password" class="input input-bordered w-full" />
              </div>

              <div class="divider"></div>

              <div class="form-control w-full">
                <label class="label">
                  <span class="label-text font-semibold">New Password</span>
                </label>
                <input v-model="passwordForm.new_password" type="password" class="input input-bordered w-full" />
                <label class="label">
                  <span class="label-text-alt opacity-50">Minimum 8 characters</span>
                </label>
              </div>

              <div class="form-control w-full">
                <label class="label">
                  <span class="label-text font-semibold">Confirm New Password</span>
                </label>
                <input v-model="passwordForm.new_password_confirmation" type="password" class="input input-bordered w-full" />
              </div>

              <div class="flex justify-end pt-4">
                <button @click="updatePassword" class="btn btn-primary gap-2" :disabled="isSavingPassword">
                  <span v-if="isSavingPassword" class="loading loading-spinner loading-sm"></span>
                  <Save v-else :size="18" /> Update Password
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="modal-backdrop" @click="close"></div>
    </div>
  </Teleport>
</template>
