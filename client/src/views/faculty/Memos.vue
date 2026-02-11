<script setup>
import { ref, onMounted } from 'vue'
import { CheckCircle, Archive, FileText } from 'lucide-vue-next'
import api from '@/services/api'
import Swal from 'sweetalert2'
import MemoInboxCard from '@/components/memos/MemoInboxCard.vue'

// Modal states
const selectedMemo = ref(null)
const showDetailModal = ref(false)

const viewMemo = (memo) => {
  selectedMemo.value = memo
  showDetailModal.value = true
  
  // Mark as read if status is 'sent'
  if (memo.status === 'sent') {
    markAsRead(memo.id)
  }
}

const markAsRead = async (memoId) => {
  try {
    await api.post(`/memos/${memoId}/acknowledge`)
  } catch (error) {
    console.error('Error marking as read:', error)
  }
}

const handleAcknowledge = async (memoId) => {
  try {
    await api.post(`/memos/${memoId}/acknowledge`)
    await Swal.fire({
      title: 'Acknowledged!',
      text: 'Memo has been acknowledged.',
      icon: 'success',
      timer: 1500,
      showConfirmButton: false
    })
    if (selectedMemo.value?.id === memoId) {
      selectedMemo.value.status = 'read'
    }
  } catch (error) {
    console.error('Error acknowledging memo:', error)
    Swal.fire('Error', 'Failed to acknowledge memo', 'error')
  }
}

const handleArchive = async (memoId) => {
  const result = await Swal.fire({
    title: 'Archive Memo?',
    text: "You can find this in your archive later.",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes, archive it!'
  })
  
  if (result.isConfirmed) {
    try {
      await api.delete(`/memos/${memoId}`)
      Swal.fire('Archived!', 'Memo has been archived.', 'success')
      showDetailModal.value = false
    } catch (error) {
      console.error('Error archiving memo:', error)
      Swal.fire('Error', 'Failed to archive memo', 'error')
    }
  }
}

const formatDate = (date) => {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric'
  })
}

const formatTime = (date) => {
  if (!date) return '-'
  return new Date(date).toLocaleTimeString('en-US', {
    hour: '2-digit',
    minute: '2-digit'
  })
}

const getPriorityClass = (priority) => {
  const classes = {
    urgent: 'badge-error',
    high: 'badge-warning',
    medium: 'badge-info',
    normal: 'badge-info',
    low: 'badge-success'
  }
  return classes[priority] || 'badge-info'
}

const closeModal = () => {
  showDetailModal.value = false
  selectedMemo.value = null
}
</script>

<template>
  <div class="view-container no-scroll">
    <!-- Page Header -->
    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 mb-6 p-4">
      <div>
        <h1 class="text-2xl font-bold text-base-content">My Memos</h1>
        <p class="text-sm text-base-content/60">View and acknowledge received memos</p>
      </div>
    </div>

    <!-- Memo Inbox Card -->
    <div class="px-4">
      <MemoInboxCard 
        initial-scope="received"
        api-endpoint="/memos"
        :max-height="'calc(100vh - 200px)'"
        @memo-click="viewMemo"
        @memo-acknowledge="handleAcknowledge"
        @memo-archive="handleArchive"
      />
    </div>

    <!-- Memo Detail Modal - Non-scrollable -->
    <div v-if="showDetailModal && selectedMemo" class="modal modal-open z-50">
      <div class="modal-box max-w-3xl max-h-[80vh] flex flex-col overflow-hidden">
        <!-- Modal Header -->
        <div class="flex-shrink-0">
          <button @click="closeModal" class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
          <h3 class="font-bold text-lg mb-4">{{ selectedMemo.subject }}</h3>
          
          <div class="space-y-2 text-sm pb-4 border-b border-base-200">
            <div class="flex justify-between">
              <span class="opacity-60">From:</span>
              <span class="font-medium">
                {{ selectedMemo.sender?.first_name }} {{ selectedMemo.sender?.last_name }}
                <span class="text-xs opacity-60">({{ selectedMemo.sender?.role }})</span>
              </span>
            </div>
            <div class="flex justify-between">
              <span class="opacity-60">Date:</span>
              <span class="font-medium">{{ formatDate(selectedMemo.created_at) }} {{ formatTime(selectedMemo.created_at) }}</span>
            </div>
            <div class="flex justify-between">
              <span class="opacity-60">Priority:</span>
              <span class="badge badge-sm" :class="getPriorityClass(selectedMemo.priority)">
                {{ selectedMemo.priority }}
              </span>
            </div>
          </div>
        </div>
        
        <!-- Modal Body - Scrollable Content -->
        <div class="flex-1 overflow-y-auto my-4">
          <div class="prose prose-sm max-w-none">
            <p class="whitespace-pre-wrap">{{ selectedMemo.message }}</p>
          </div>
          
          <div v-if="selectedMemo.attachments?.length" class="mt-4 pt-4 border-t border-base-200">
            <p class="text-sm opacity-60 mb-2 font-bold uppercase tracking-wider text-[10px]">Attachments:</p>
            <div class="flex flex-wrap gap-2">
              <a 
                v-for="attachment in selectedMemo.attachments" 
                :key="attachment.path || attachment.name"
                :href="attachment.url"
                target="_blank"
                class="btn btn-sm btn-ghost bg-base-200 hover:bg-base-300 gap-2 font-bold text-[10px] uppercase group"
              >
                <FileText :size="14" class="opacity-60 group-hover:opacity-100" />
                {{ attachment.name }}
              </a>
            </div>
          </div>
        </div>
        
        <!-- Modal Footer - Fixed at bottom -->
        <div class="flex-shrink-0 pt-4 border-t border-base-200 modal-action-wrapper">
          <button @click="closeModal" class="btn">Close</button>
          <button 
            v-if="selectedMemo.status === 'sent'"
            @click="handleAcknowledge(selectedMemo.id); closeModal()"
            class="btn btn-primary"
          >
            Acknowledge
          </button>
          <button 
            @click="handleArchive(selectedMemo.id)"
            class="btn btn-ghost text-error"
          >
            Archive
          </button>
        </div>
      </div>
      <div class="modal-backdrop" @click="closeModal"></div>
    </div>
  </div>
</template>

<style scoped>
.view-container.no-scroll {
  height: 100vh;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.view-container.no-scroll > *:not(.modal) {
  flex-shrink: 0;
}

.modal-box {
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.modal-box .modal-body {
  flex: 1;
  overflow-y: auto;
  overflow-x: hidden;
}

.modal-action-wrapper {
  flex-shrink: 0;
}
</style>
