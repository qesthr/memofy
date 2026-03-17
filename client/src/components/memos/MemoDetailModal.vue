<script setup>
import { ref, computed, onMounted } from 'vue'
import { X, Paperclip, FileText, Download, ChevronDown, ChevronUp, CheckCircle, Archive, Loader2, Users, Check, Clock } from 'lucide-vue-next'
import api from '@/services/api'
import html2pdf from 'html2pdf.js'
import Swal from 'sweetalert2'
import MemoPdfTemplate from './MemoPdfTemplate.vue'

const props = defineProps({
  memo: {
    type: Object,
    required: true
  },
  isOpen: {
    type: Boolean,
    required: true
  },
  // Pass current user ID to determine relationship
  currentUserId: {
    type: [String, Number],
    default: null
  },
  // Pass user role as backup
  userRole: {
    type: String,
    default: 'faculty'
  }
})

const emit = defineEmits(['close', 'acknowledged', 'archived'])

const pdfContent = ref(null)
const isAcknowledging = ref(false)
const isArchiving = ref(false)
const isDownloading = ref(false)
const isSendingReminder = ref(false)

// Determine user relationship to memo
const isCreator = computed(() => {
  if (!props.currentUserId || !props.memo) return false
  return String(props.memo.sender_id) === String(props.currentUserId) || 
         String(props.memo.created_by) === String(props.currentUserId)
})

const isRecipient = computed(() => {
  return props.memo.recipient_ids.includes(props.currentUserId)
})

const isAdminOrSecretary = computed(() => {
  return ['admin', 'superadmin', 'secretary'].includes(props.userRole?.toLowerCase())
})

// Check if user is recipient and can acknowledge
const canAcknowledge = computed(() => {
  // If user is the creator, they cannot acknowledge their own memo
  if (isCreator.value) return false
  
  // Only recipients can acknowledge sent/read memos
  return props.memo.status && 
         ['sent', 'read'].includes(props.memo.status)
})

// PDF Download using html2pdf.js
const downloadAsPDF = async () => {
  isDownloading.value = true
  const element = pdfContent.value.$el || pdfContent.value
  const opt = {
    margin: 0,
    filename: `Memo_${props.memo.subject || 'Untitled'}.pdf`,
    image: { type: 'jpeg', quality: 0.98 },
    html2canvas: { 
      scale: 2, 
      useCORS: true,
      letterRendering: true,
      scrollY: 0
    },
    jsPDF: { unit: 'mm', format: 'a4', orientation: 'portrait' },
    pagebreak: { mode: ['avoid-all', 'css', 'legacy'] }
  }

  try {
    await html2pdf().set(opt).from(element).save()
  } catch (error) {
    console.error('PDF Generation Error:', error)
    Swal.fire('Error', 'Failed to generate PDF', 'error')
  } finally {
    isDownloading.value = false
  }
}

// Acknowledge memo
const handleAcknowledge = async () => {
  isAcknowledging.value = true
  try {
    await api.post(`/memos/${props.memo.id}/acknowledge`)
    await Swal.fire({
      title: 'Acknowledged!',
      text: 'Memo has been acknowledged successfully.',
      icon: 'success',
      timer: 1500,
      showConfirmButton: false
    })
    emit('acknowledged', props.memo.id)
  } catch (error) {
    console.error('Error acknowledging memo:', error)
    Swal.fire('Error', 'Failed to acknowledge memo', 'error')
  } finally {
    isAcknowledging.value = false
  }
}

// Archive memo
const handleArchive = async () => {
  const result = await Swal.fire({
    title: 'Archive Memo?',
    text: "This memo will be moved to your archive. You can restore it later if needed.",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes, archive it!',
    confirmButtonColor: '#d33'
  })
  
  if (result.isConfirmed) {
    isArchiving.value = true
    try {
      await api.delete(`/memos/${props.memo.id}`)
      await Swal.fire({
        title: 'Archived!',
        text: 'Memo has been archived.',
        icon: 'success',
        timer: 1500,
        showConfirmButton: false
      })
      emit('archived', props.memo.id)
      closeModal()
    } catch (error) {
      console.error('Error archiving memo:', error)
      Swal.fire('Error', 'Failed to archive memo', 'error')
    } finally {
      isArchiving.value = false
    }
  }
}

// Send reminders
const handleSendReminder = async () => {
  if (isSendingReminder.value) return
  
  const result = await Swal.fire({
    title: 'Send Reminders?',
    text: "This will send a follow-up notification to all recipients who haven't acknowledged this memo yet.",
    icon: 'info',
    showCancelButton: true,
    confirmButtonText: 'Yes, send reminders!',
    confirmButtonColor: '#3085d6'
  })
  
  if (result.isConfirmed) {
    isSendingReminder.value = true
    try {
      const response = await api.post(`/memos/${props.memo.id}/reminder`)
      await Swal.fire({
        title: 'Reminders Sent!',
        text: response.data.message || 'Reminders have been sent successfully.',
        icon: 'success',
        timer: 1500,
        showConfirmButton: false
      })
    } catch (error) {
      console.error('Error sending reminders:', error)
      const message = error.response?.data?.message || 'Failed to send reminders'
      Swal.fire('Error', message, 'error')
    } finally {
      isSendingReminder.value = false
    }
  }
}

// Helper to get acknowledgment status for a specific recipient
const getRecipientStatus = (recipientId) => {
  if (!props.memo.acknowledgments) return null
  return props.memo.acknowledgments.find(ack => 
    String(ack.recipient_id) === String(recipientId) && ack.is_acknowledged
  )
}

// Close modal
const closeModal = () => {
  emit('close')
}
</script>

<template>
  <Teleport to="body">
    <div v-if="isOpen" class="modal modal-open z-[99999]">
      <div class="modal-box max-w-4xl w-[95vw] bg-white p-0 rounded-none shadow-2xl flex flex-col relative overflow-hidden">
        <!-- Close Button -->
        <button @click="closeModal" class="absolute top-2 right-2 btn btn-error btn-circle btn-sm z-50 text-white hover:btn-error">
          <X :size="18" />
        </button>
        
        
        <!-- Scrollable Content -->
        <div class="flex-1 overflow-y-auto bg-gray-100 custom-scrollbar">
          <MemoPdfTemplate 
            ref="pdfContent"
            :memo="memo" 
            :sender="memo.sender" 
            :isPreview="false" 
          />
        </div>

        <!-- Bottom Action Bar - Close Button -->
        <div class="shrink-0 px-4 py-3 bg-white border-t border-gray-200 flex flex-col gap-3">
          <!-- Inline Status Tracking -->
          <div v-if="isAdminOrSecretary && memo.recipients_list?.length" class="flex items-center justify-between">
            <div class="flex items-center gap-3">
              <span class="text-[10px] font-bold text-gray-500 uppercase tracking-widest mr-2">Acknowledgment Status:</span>
              <div class="flex -space-x-2">
                <div 
                  v-for="recipient in memo.recipients_list" 
                  :key="recipient.id"
                  class="group relative"
                >
                  <div 
                    class="w-7 h-7 rounded-full border-2 transition-all duration-300"
                    :class="getRecipientStatus(recipient.id) ? 'border-success' : 'border-error'"
                  >
                    <div class="w-full h-full rounded-full bg-white overflow-hidden">
                      <img 
                        v-if="recipient.profile_picture" 
                        :src="recipient.profile_picture" 
                        :alt="recipient.first_name"
                        class="w-full h-full object-cover"
                      />
                      <div v-else class="w-full h-full flex items-center justify-center bg-gray-50 text-gray-400 font-bold text-[8px]">
                        {{ recipient.first_name.charAt(0) }}{{ recipient.last_name.charAt(0) }}
                      </div>
                    </div>
                  </div>
                  
                  <!-- Tiny overlay icon -->
                  <div class="absolute -bottom-0.5 -right-0.5 w-2.5 h-2.5 rounded-full flex items-center justify-center"
                       :class="getRecipientStatus(recipient.id) ? 'bg-success' : 'bg-error'">
                    <Check v-if="getRecipientStatus(recipient.id)" :size="6" class="text-white" />
                    <Clock v-else :size="6" class="text-white" />
                  </div>

                  <!-- Mini Hover Card -->
                  <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-1 p-1.5 bg-gray-900 text-white text-[8px] rounded whitespace-nowrap opacity-0 group-hover:opacity-100 transition-opacity z-50 pointer-events-none shadow-lg">
                    {{ recipient.first_name }} - {{ getRecipientStatus(recipient.id) ? 'Acknowledged' : 'Pending' }}
                  </div>
                </div>
              </div>
            </div>

            <div class="flex items-center gap-2">
              <button 
                v-if="memo.status === 'sent' || memo.status === 'read'"
                @click="handleSendReminder"
                class="btn btn-primary btn-xs gap-1 font-bold text-[8px] uppercase tracking-wider h-7"
                :disabled="isSendingReminder"
              >
                <Loader2 v-if="isSendingReminder" class="animate-spin" :size="10" />
                <Clock v-else :size="10" />
                Remind All
              </button>
            </div>
          </div>

          <div class="flex justify-between items-center pt-2 border-t border-gray-50">
            <div class="flex items-center gap-2">
              <span 
                v-if="memo.status === 'acknowledged'" 
                class="badge badge-success badge-sm gap-1 font-bold text-[10px]"
              >
                <CheckCircle :size="12" />
                ACKNOWLEDGED
              </span>
              <span 
                v-else-if="memo.status === 'read'" 
                class="badge badge-info badge-sm font-bold text-[10px]"
              >
                READ
              </span>
            </div>
            <button @click="closeModal" class="btn btn-error btn-sm font-bold text-[10px] uppercase tracking-wider text-white">Close</button>
          </div>
        </div>
      </div>
      <div class="modal-backdrop bg-black/40 backdrop-blur-sm" @click="closeModal"></div>
    </div>
  </Teleport>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background-color: rgba(0, 0, 0, 0.15);
  border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background-color: transparent;
}

.memo-a4-preview {
  width: 210mm;
  min-height: 297mm;
  max-width: 100%;
}

.memo-content {
  min-height: 700px;
}
</style>
