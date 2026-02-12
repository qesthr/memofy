<script setup>
import { ref, onMounted } from 'vue'
import { CheckCircle, Archive, FileText } from 'lucide-vue-next'
import api from '@/services/api'
import Swal from 'sweetalert2'
import MemoInboxCard from '@/components/memos/MemoInboxCard.vue'
import MemoDetailModal from '@/components/memos/MemoDetailModal.vue'

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

// Handle acknowledged event from modal
const handleAcknowledged = (memoId) => {
  if (selectedMemo.value?.id === memoId) {
    selectedMemo.value.status = 'acknowledged'
  }
  // Refresh the memo list would happen here if we had a ref to MemoInboxCard
}

// Handle archived event from modal
const handleArchived = (memoId) => {
  showDetailModal.value = false
  selectedMemo.value = null
  // The MemoInboxCard should refresh automatically
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

    <!-- Memo Detail Modal -->
    <MemoDetailModal
      v-if="showDetailModal && selectedMemo"
      :memo="selectedMemo"
      :is-open="showDetailModal"
      user-role="faculty"
      @close="closeModal"
      @acknowledged="handleAcknowledged"
      @archived="handleArchived"
    />
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
