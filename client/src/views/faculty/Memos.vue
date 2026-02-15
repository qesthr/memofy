<script setup>
import { ref, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { CheckCircle, Archive, FileText } from 'lucide-vue-next'
import api from '@/services/api'
import Swal from 'sweetalert2'
import MemoInboxCard from '@/components/memos/MemoInboxCard.vue'
import MemoDetailModal from '@/components/memos/MemoDetailModal.vue'

// Modal states
const selectedMemo = ref(null)
const showDetailModal = ref(false)

// Current user
const storedUser = JSON.parse(localStorage.getItem('user') || '{}')
const currentUserId = storedUser?.id || storedUser?._id || null

const viewMemo = (memo) => {
  selectedMemo.value = memo
  showDetailModal.value = true
  
  // Mark as read if status is 'sent' and user is not the sender
  const isSender = String(memo.sender_id || memo.sender?.id) === String(currentUserId)
  if (memo.status === 'sent' && !isSender) {
    markAsRead(memo.id)
  }
}

const markAsRead = async (memoId) => {
  // Note: Viewing a memo no longer auto-acknowledges it.
  // Users must explicitly click the acknowledge button.
  // This prevents duplicate notifications to the sender.
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
      selectedMemo.value.status = 'acknowledged'
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

const route = useRoute()

onMounted(async () => {
  const memoId = route.query.memoId
  if (memoId) {
    try {
      const response = await api.get(`/memos/${memoId}`)
      viewMemo(response.data)
    } catch (error) {
      console.error('Failed to fetch deep-linked memo:', error)
    }
  }
})
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
        :current-user-id="currentUserId"
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
      :current-user-id="currentUserId"
      user-role="faculty"
      @close="closeModal"
      @acknowledged="handleAcknowledged"
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
