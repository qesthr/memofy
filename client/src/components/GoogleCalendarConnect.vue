<script setup>
import { ref, onMounted, onUnmounted } from 'vue'
import api from '@/services/api'
import Swal from 'sweetalert2'

const props = defineProps({
  connected: {
    type: Boolean,
    default: false
  }
})

const emit = defineEmits(['update'])

const isLoading = ref(false)

const connect = async () => {
  isLoading.value = true
  try {
    const response = await api.post('/calendar/connect')
    const url = response.data.url
    
    const width = 500
    const height = 600
    const left = (window.screen.width / 2) - (width / 2)
    const top = (window.screen.height / 2) - (height / 2)
    
    window.open(url, 'google_calendar_connect', `width=${width},height=${height},top=${top},left=${left}`)
  } catch (error) {
    console.error('Connection error:', error)
    Swal.fire({
      icon: 'error',
      title: 'Connection Failed',
      text: 'Could not initiate Google Calendar connection.'
    })
    isLoading.value = false
  }
}

const disconnect = async () => {
  try {
    const result = await Swal.fire({
      title: 'Disconnect Calendar?',
      text: "You will stop seeing your Google Calendar events.",
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
      confirmButtonText: 'Yes, disconnect'
    })

    if (result.isConfirmed) {
      await api.post('/calendar/disconnect')
      emit('update')
      Swal.fire(
        'Disconnected!',
        'Your calendar has been disconnected.',
        'success'
      )
    }
  } catch (error) {
    console.error('Disconnect error:', error)
  }
}

const handleMessage = (event) => {
  if (event.data.type === 'GOOGLE_CALENDAR_CONNECTED') {
    isLoading.value = false
    emit('update')
    Swal.fire({
      icon: 'success',
      title: 'Connected!',
      text: 'Your Google Calendar has been successfully connected.',
      timer: 2000
    })
  } else if (event.data.type === 'GOOGLE_CALENDAR_FAILURE') {
    isLoading.value = false
    Swal.fire({
      icon: 'error',
      title: 'Connection Failed',
      text: event.data.error || 'Unknown error occurred.'
    })
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
  <div class="flex items-center">
    <button 
      v-if="!connected" 
      @click="connect" 
      :disabled="isLoading" 
      class="btn btn-sm btn-outline gap-2 bg-white hover:bg-gray-50 text-gray-700"
    >
      <img src="@/assets/images/images/google.png" alt="Google" class="w-4 h-4" />
      <span v-if="isLoading">Connecting...</span>
      <span v-else>Connect Google Calendar</span>
    </button>
    
    <div v-else class="flex items-center gap-2">
      <button 
        @click="disconnect" 
        class="btn btn-sm btn-outline btn-error gap-2 bg-white hover:bg-error/10"
      >
        <img src="@/assets/images/images/google.png" alt="Google" class="w-4 h-4" />
        Disconnect Google Calendar
      </button>
    </div>
  </div>
</template>
