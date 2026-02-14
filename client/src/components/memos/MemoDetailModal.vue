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

// Determine user relationship to memo
const isCreator = computed(() => {
  if (!props.currentUserId || !props.memo) return false
  return String(props.memo.sender_id) === String(props.currentUserId) || 
         String(props.memo.created_by) === String(props.currentUserId)
})

const isRecipient = computed(() => {
  if (!props.currentUserId || !props.memo?.recipient_ids) return false
  return props.memo.recipient_ids.includes(props.currentUserId)
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
        <button @click="closeModal" class="absolute top-2 right-2 btn btn-ghost btn-circle btn-sm z-50 bg-white/80 hover:bg-white">
          <X :size="18" />
        </button>
        
        <!-- Recipient Status Section - Only for Creator -->
        <div v-if="isCreator && memo.recipients_list?.length" class="shrink-0 px-6 py-4 bg-white border-b border-gray-100">
          <div class="flex items-center justify-between mb-3">
            <h4 class="text-xs font-bold text-gray-400 uppercase tracking-widest flex items-center gap-2">
              <Users :size="14" />
              Recipient Status
            </h4>
            <div class="flex items-center gap-4 text-[10px] font-bold uppercase tracking-wider">
              <span class="flex items-center gap-1.5 text-success">
                <div class="w-1.5 h-1.5 rounded-full bg-success"></div>
                Acknowledged
              </span>
              <span class="flex items-center gap-1.5 text-gray-400">
                <div class="w-1.5 h-1.5 rounded-full bg-gray-300"></div>
                Pending
              </span>
            </div>
          </div>
          
          <div class="flex flex-wrap gap-4">
            <div 
              v-for="recipient in memo.recipients_list" 
              :key="recipient.id"
              class="group relative flex flex-col items-center gap-1.5"
            >
              <!-- Avatar with Status Ring -->
              <div class="relative">
                <div 
                  class="w-12 h-12 rounded-full p-0.5 transition-all duration-300"
                  :class="getRecipientStatus(recipient.id) ? 'bg-success shadow-lg shadow-success/20' : 'bg-gray-200'"
                >
                  <div class="w-full h-full rounded-full bg-white overflow-hidden border-2 border-white">
                    <img 
                      v-if="recipient.profile_picture" 
                      :src="recipient.profile_picture" 
                      :alt="recipient.first_name"
                      class="w-full h-full object-cover"
                    />
                    <div v-else class="w-full h-full flex items-center justify-center bg-gray-50 text-gray-400 font-bold text-sm">
                      {{ recipient.first_name.charAt(0) }}{{ recipient.last_name.charAt(0) }}
                    </div>
                  </div>
                </div>
                
                <!-- Status Icon Overlay -->
                <div 
                  v-if="getRecipientStatus(recipient.id)"
                  class="absolute -bottom-1 -right-1 w-5 h-5 rounded-full bg-success border-2 border-white flex items-center justify-center text-white shadow-sm"
                >
                  <Check :size="10" stroke-width="3" />
                </div>
              </div>

              <!-- Recipient Info (Tooltip Style) -->
              <div class="text-[10px] font-bold text-gray-600 max-w-[64px] truncate text-center">
                {{ recipient.first_name }}
              </div>

              <!-- Hover Card -->
              <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 p-2 bg-gray-900 text-white text-[10px] rounded-lg opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity duration-200 z-60 whitespace-nowrap shadow-xl">
                <div class="font-bold border-b border-gray-700 pb-1 mb-1">{{ recipient.first_name }} {{ recipient.last_name }}</div>
                <div class="text-gray-400">{{ recipient.email }}</div>
                <div v-if="getRecipientStatus(recipient.id)" class="text-success mt-1 flex items-center gap-1">
                  <Check :size="10" /> Acknowledged
                </div>
                <div v-else class="text-warning mt-1 flex items-center gap-1">
                  <Clock :size="10" /> Pending acknowledgment
                </div>
                <div class="absolute top-full left-1/2 -translate-x-1/2 border-8 border-transparent border-t-gray-900"></div>
              </div>
            </div>
          </div>
        </div>
        
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
        <div class="shrink-0 px-4 py-3 bg-white border-t border-gray-200 flex justify-between items-center">
          <div class="flex items-center gap-2">
            <span 
              v-if="memo.status === 'acknowledged'" 
              class="badge badge-success badge-sm gap-1"
            >
              <CheckCircle :size="12" />
              Acknowledged
            </span>
            <span 
              v-else-if="memo.status === 'read'" 
              class="badge badge-info badge-sm"
            >
              Read
            </span>
            <span 
              v-else-if="memo.status === 'sent'" 
              class="badge badge-warning badge-sm"
            >
              New
            </span>
          </div>
          <button @click="closeModal" class="btn btn-ghost btn-sm font-bold text-[10px] uppercase tracking-wider">Close</button>
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
