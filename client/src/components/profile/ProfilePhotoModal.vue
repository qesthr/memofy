<script setup>
import { ref, onMounted, onUnmounted, nextTick } from 'vue'
import Croppie from 'croppie'
import 'croppie/croppie.css'
import { X, Upload, ZoomIn, ZoomOut, RotateCw, Save } from 'lucide-vue-next'
import api from '@/services/api'
import Swal from 'sweetalert2'
import { useAuth } from '@/composables/useAuth'

const props = defineProps({
  isOpen: Boolean
})

const emit = defineEmits(['close', 'updated'])

const { user, fetchUser } = useAuth()
const fileInput = ref(null)
const croppieContainer = ref(null)
const croppieInstance = ref(null)
const selectedImage = ref(null)
const isProcessing = ref(false)

const handleFileSelect = (event) => {
  const file = event.target.files[0]
  if (!file) return

  if (!file.type.match('image.*')) {
    Swal.fire('Error', 'Please select an image file', 'error')
    return
  }

  const reader = new FileReader()
  reader.onload = (e) => {
    selectedImage.value = e.target.result
    initCroppie()
  }
  reader.readAsDataURL(file)
}

const initCroppie = () => {
  if (croppieInstance.value) {
    croppieInstance.value.destroy()
  }

  nextTick(() => {
    croppieInstance.value = new Croppie(croppieContainer.value, {
      viewport: { width: 200, height: 200, type: 'circle' },
      boundary: { width: 300, height: 300 },
      showZoomer: false,
      enableOrientation: true
    })
    croppieInstance.value.bind({
      url: selectedImage.value
    })
  })
}

const rotate = () => {
  if (croppieInstance.value) {
    croppieInstance.value.rotate(90)
  }
}

const updateZoom = (event) => {
  if (croppieInstance.value) {
    croppieInstance.value.setZoom(event.target.value)
  }
}

const saveImage = async () => {
  if (!croppieInstance.value) return

  isProcessing.value = true
  try {
    const croppedImage = await croppieInstance.value.result({
      type: 'base64',
      size: 'viewport',
      format: 'jpeg',
      quality: 1,
      circle: false
    })

    const response = await api.post('/me/profile-picture', {
      image: croppedImage
    })

    if (response.data.success) {
      await fetchUser() // Refresh user data globally
      Swal.fire({
        title: 'Success!',
        text: 'Profile photo updated successfully',
        icon: 'success',
        timer: 1500,
        showConfirmButton: false
      })
      emit('updated')
      close()
    }
  } catch (error) {
    console.error('Error saving profile photo:', error)
    Swal.fire('Error', 'Failed to update profile photo', 'error')
  } finally {
    isProcessing.value = false
  }
}

const close = () => {
  if (croppieInstance.value) {
    croppieInstance.value.destroy()
    croppieInstance.value = null
  }
  selectedImage.value = null
  emit('close')
}

onUnmounted(() => {
  if (croppieInstance.value) {
    croppieInstance.value.destroy()
  }
})
</script>

<template>
  <Teleport to="body">
    <div v-if="isOpen" class="modal modal-open z-[100]">
      <div class="modal-box max-w-md p-0 overflow-hidden">
        <div class="p-6 border-b border-base-200 flex items-center justify-between">
          <h3 class="font-bold text-lg">Update Profile Photo</h3>
          <button @click="close" class="btn btn-sm btn-circle btn-ghost"><X :size="20" /></button>
        </div>

        <div class="p-6 flex flex-col items-center">
          <!-- Current Photo or Preview -->
          <div v-if="!selectedImage" class="flex flex-col items-center gap-6">
            <div class="avatar">
              <div class="w-32 h-32 rounded-full ring ring-primary ring-offset-base-100 ring-offset-2">
                <img v-if="user?.profile_picture" :src="user.profile_picture" alt="Profile" />
                <div v-else class="bg-base-300 w-full h-full flex items-center justify-center text-4xl font-bold opacity-30">
                  {{ user?.first_name?.[0] }}{{ user?.last_name?.[0] }}
                </div>
              </div>
            </div>
            
            <div class="text-center">
              <p class="text-sm text-base-content/60 mb-4">Upload a new photo to change your profile picture</p>
              <button @click="fileInput.click()" class="btn btn-primary gap-2">
                <Upload :size="18" /> Select Photo
              </button>
            </div>
          </div>

          <!-- Cropping Interface -->
          <div v-else class="w-full flex flex-col items-center">
            <div ref="croppieContainer" class="mb-4"></div>
            
            <div class="w-full space-y-4 px-4">
               <div class="flex items-center gap-4">
                 <ZoomOut :size="16" class="opacity-40" />
                 <input type="range" min="0" max="1.5" step="0.01" value="1" @input="updateZoom" class="range range-xs range-primary flex-1" />
                 <ZoomIn :size="16" class="opacity-40" />
               </div>
               
               <div class="flex justify-center gap-2">
                 <button @click="rotate" class="btn btn-sm btn-ghost gap-2">
                   <RotateCw :size="16" /> Rotate
                 </button>
                 <button @click="selectedImage = null" class="btn btn-sm btn-ghost text-error">
                  Cancel
                 </button>
               </div>
            </div>
          </div>
        </div>

        <input 
          ref="fileInput" 
          type="file" 
          class="hidden" 
          accept="image/*" 
          @change="handleFileSelect" 
        />

        <div class="p-6 bg-base-200/50 flex justify-end gap-2">
          <button @click="close" class="btn btn-ghost" :disabled="isProcessing">Close</button>
          <button 
            v-if="selectedImage" 
            @click="saveImage" 
            class="btn btn-primary min-w-[120px]" 
            :disabled="isProcessing"
          >
            <span v-if="isProcessing" class="loading loading-spinner loading-sm"></span>
            <span v-else class="flex items-center gap-2"><Save :size="18" /> Save Photo</span>
          </button>
        </div>
      </div>
      <div class="modal-backdrop" @click="close"></div>
    </div>
  </Teleport>
</template>

<style>
/* Croppie Circle Viewport override if needed */
.cr-viewport.cr-vp-circle {
  border-radius: 50% !important;
}
</style>
