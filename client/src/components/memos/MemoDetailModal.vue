<script setup>
import { ref, computed, onMounted } from 'vue'
import { X, Paperclip, FileText, Download, ChevronDown, ChevronUp, CheckCircle, Archive, Loader2 } from 'lucide-vue-next'
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
  // Pass user role to determine available actions
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

// Check if user is recipient and can acknowledge
const canAcknowledge = computed(() => {
  return props.userRole === 'faculty' && 
         props.memo.status && 
         ['sent', 'read'].includes(props.memo.status)
})

// Check if user can archive
const canArchive = computed(() => {
  return props.userRole === 'faculty'
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
        
        <!-- Action Buttons Bar (Top) - For Faculty/Recipients -->
        <div v-if="userRole === 'faculty'" class="shrink-0 px-4 py-3 bg-gradient-to-r from-blue-50 to-indigo-50 border-b border-gray-200 flex justify-between items-center">
          <div class="flex items-center gap-2">
            <span class="text-xs font-semibold text-gray-600 uppercase tracking-wider">Actions:</span>
          </div>
          <div class="flex items-center gap-2">
            <!-- Acknowledge Button -->
            <button 
              v-if="canAcknowledge"
              @click="handleAcknowledge"
              :disabled="isAcknowledging"
              class="btn btn-success btn-sm text-white font-bold text-[10px] uppercase tracking-wider gap-2"
            >
              <Loader2 v-if="isAcknowledging" :size="14" class="animate-spin" />
              <CheckCircle v-else :size="14" />
              {{ isAcknowledging ? 'Acknowledging...' : 'Acknowledge' }}
            </button>
            
            <!-- Download PDF Button -->
            <button 
              @click="downloadAsPDF"
              :disabled="isDownloading"
              class="btn btn-primary btn-sm text-white font-bold text-[10px] uppercase tracking-wider gap-2"
            >
              <Loader2 v-if="isDownloading" :size="14" class="animate-spin" />
              <Download v-else :size="14" />
              {{ isDownloading ? 'Downloading...' : 'Download PDF' }}
            </button>
            
            <!-- Archive Button -->
            <button 
              v-if="canArchive"
              @click="handleArchive"
              :disabled="isArchiving"
              class="btn btn-warning btn-sm text-white font-bold text-[10px] uppercase tracking-wider gap-2"
            >
              <Loader2 v-if="isArchiving" :size="14" class="animate-spin" />
              <Archive v-else :size="14" />
              {{ isArchiving ? 'Archiving...' : 'Archive' }}
            </button>
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
