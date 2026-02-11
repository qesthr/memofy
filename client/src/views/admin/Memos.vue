<script setup>
import { ref, onMounted, watch } from 'vue'
import { Plus, Search, ChevronDown, Calendar, X, Settings2, CheckCircle, Clock, Eye, XCircle, Check, FileText } from 'lucide-vue-next'
import ComposeMemoModal from '@/components/memos/ComposeMemoModal.vue'
import CustomizeMemoModal from '@/components/memos/CustomizeMemoModal.vue'
import MemoInboxCard from '@/components/memos/MemoInboxCard.vue'
import api from '@/services/api'
import Swal from 'sweetalert2'

// Filter states
const activeTab = ref('all') // all, sent, drafts

// Modal states
const showComposeModal = ref(false)
const showCustomizeModal = ref(false)
const templateData = ref(null)
const selectedMemo = ref(null)
const showDetailModal = ref(false)
const showApprovalModal = ref(false)

// Data states
const loading = ref(false)

// Memo inbox ref for refreshing
const memoInboxRef = ref(null)

// Scope mapping for tabs
const scopeMapping = {
  'all': '',
  'pending': 'pending',
  'sent': 'sent',
  'drafts': 'drafts'
}



const handleTemplateApply = (data) => {
  templateData.value = data
  showCustomizeModal.value = false
  showComposeModal.value = true
}

const handleSendMemo = async (result) => {
  try {
    await Swal.fire({
      title: 'Success!',
      text: result.message || 'Memo has been sent successfully.',
      icon: 'success',
      confirmButtonText: 'OK',
      customClass: {
        confirmButton: 'btn btn-primary'
      }
    })
    
    showComposeModal.value = false
    templateData.value = null
    
    // Refresh memo inbox
    if (memoInboxRef.value) {
      memoInboxRef.value.refresh()
    }
  } catch (error) {
    console.error('Error handling sent memo:', error)
  }
}

const viewMemo = (memo) => {
  selectedMemo.value = memo
  showDetailModal.value = true
}

const viewApprovalMemo = (memo) => {
  selectedMemo.value = memo
  showApprovalModal.value = true
}

const approveMemo = async (memoId) => {
  const result = await Swal.fire({
    title: 'Approve Memo?',
    text: 'This will send the memo to all recipients.',
    icon: 'question',
    showCancelButton: true,
    confirmButtonText: 'Approve',
    cancelButtonText: 'Cancel'
  })

  if (result.isConfirmed) {
    try {
      await api.post(`/admin/memos/${memoId}/approve`)
      
      await Swal.fire({
        title: 'Approved!',
        text: 'Memo has been approved and sent to recipients.',
        icon: 'success',
        timer: 2000,
        showConfirmButton: false
      })
      
      showApprovalModal.value = false
      
      // Refresh memo inbox
      if (memoInboxRef.value) {
        memoInboxRef.value.refresh()
      }
    } catch (error) {
      console.error('Error approving memo:', error)
      Swal.fire('Error', error.response?.data?.message || 'Failed to approve memo', 'error')
    }
  }
}

const rejectMemo = async (memoId) => {
  const { value: rejectionReason } = await Swal.fire({
    title: 'Reject Memo',
    text: 'Please provide a reason for rejection (optional)',
    input: 'textarea',
    inputPlaceholder: 'Enter rejection reason...',
    showCancelButton: true,
    confirmButtonText: 'Reject',
    cancelButtonText: 'Cancel'
  })

  if (rejectionReason !== undefined) {
    try {
      await api.post(`/admin/memos/${memoId}/reject`, {
        rejection_reason: rejectionReason
      })
      
      await Swal.fire({
        title: 'Rejected!',
        text: 'Memo has been rejected.',
        icon: 'info',
        timer: 2000,
        showConfirmButton: false
      })
      
      showApprovalModal.value = false
    } catch (error) {
      console.error('Error rejecting memo:', error)
      Swal.fire('Error', error.response?.data?.message || 'Failed to reject memo', 'error')
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

const getPriorityIconColor = (priority) => {
  const colors = {
    urgent: 'bg-error',
    high: 'bg-warning',
    medium: 'bg-info',
    normal: 'bg-info',
    low: 'bg-success'
  }
  return colors[priority] || 'bg-info'
}

// Watch tab changes to refresh memo inbox
watch(activeTab, () => {
  if (memoInboxRef.value) {
    memoInboxRef.value.refresh()
  }
})

const tabs = [
  { key: 'all', label: 'ALL' },
  { key: 'pending', label: 'PENDING' },
  { key: 'sent', label: 'SENT' },
  { key: 'drafts', label: 'DRAFTS' }
]

// Watch tab changes to refresh memo inbox
watch(activeTab, () => {
  if (memoInboxRef.value) {
    memoInboxRef.value.refresh()
  }
})

onMounted(() => {
})
</script>

<template>
  <div class="view-container no-scroll">
    <!-- Page Header -->
    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 mb-6">
      <div>
        <h1 class="text-2xl font-bold text-base-content">Memos</h1>
        <p class="text-sm text-base-content/60">Manage and distribute memos across departments</p>
      </div>
      <div class="flex gap-2">
        <button @click="showCustomizeModal = true" class="btn btn-ghost btn-sm border border-base-300 px-4 hover:bg-base-200">
          <Settings2 :size="16" class="mr-2" /> Template
        </button>
        <button @click="showComposeModal = true; templateData = null" class="btn btn-primary btn-sm text-white px-6">
          <span class="mr-1">✎</span> Compose
        </button>
      </div>
    </div>



    <!-- Tabs -->
    <div class="tabs tabs-boxed bg-base-200/50 mb-4 p-1 w-fit ml-4">
      <button 
        v-for="tab in tabs" 
        :key="tab.key"
        @click="activeTab = tab.key"
        class="tab font-bold text-xs uppercase tracking-wider"
        :class="{ 'tab-active bg-primary text-white': activeTab === tab.key }"
      >
        {{ tab.label }}
      </button>
    </div>

    <!-- Memo Inbox Card -->
    <div class="px-4">
      <MemoInboxCard 
        ref="memoInboxRef"
        :initial-scope="scopeMapping[activeTab]"
        :api-endpoint="activeTab === 'pending' ? '/admin/memos/pending-approvals' : '/memos'"
        :max-height="'calc(100vh - 240px)'"
        @memo-click="viewMemo"
        @memo-review="viewApprovalMemo"
      />
    </div>

    <!-- Customize Memo Modal -->
    <CustomizeMemoModal
      :is-open="showCustomizeModal"
      @close="showCustomizeModal = false"
      @apply="handleTemplateApply"
    />

    <!-- Compose Memo Modal -->
    <ComposeMemoModal 
      :is-open="showComposeModal"
      :initial-data="templateData"
      @close="showComposeModal = false"
      @send="handleSendMemo"
    />

    <!-- Memo Detail Modal -->
    <div v-if="showDetailModal && selectedMemo" class="modal modal-open z-50">
      <div class="modal-box max-w-3xl max-h-[80vh] flex flex-col overflow-hidden">
        <!-- Modal Header -->
        <div class="flex-shrink-0">
          <button @click="showDetailModal = false" class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
          
          <h3 class="font-bold text-lg mb-4">{{ selectedMemo.subject }}</h3>
          
          <div class="space-y-2 text-sm pb-4 border-b border-base-200">
            <div class="flex justify-between">
              <span class="opacity-60">From:</span>
              <span class="font-medium">
                {{ selectedMemo.sender?.first_name }} {{ selectedMemo.sender?.last_name }}
              </span>
            </div>
            <div v-if="selectedMemo.recipient" class="flex justify-between">
              <span class="opacity-60">To:</span>
              <span class="font-medium">
                {{ selectedMemo.recipient?.first_name }} {{ selectedMemo.recipient?.last_name }}
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
        
        <!-- Modal Body - Scrollable -->
        <div class="flex-1 overflow-y-auto my-4">
          <div class="prose prose-sm max-w-none">
            <p class="whitespace-pre-wrap">{{ selectedMemo.message }}</p>
          </div>
        </div>
        
        <!-- Modal Footer -->
        <div class="flex-shrink-0 pt-4 border-t border-base-200 modal-action-wrapper">
          <button @click="showDetailModal = false" class="btn">Close</button>
        </div>
      </div>
      <div class="modal-backdrop" @click="showDetailModal = false"></div>
    </div>

    <!-- Approval Modal -->
    <div v-if="showApprovalModal && selectedMemo" class="modal modal-open z-50">
      <div class="modal-box max-w-3xl max-h-[80vh] flex flex-col overflow-hidden">
        <!-- Modal Header -->
        <div class="flex-shrink-0">
          <button @click="showApprovalModal = false" class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
          
          <h3 class="font-bold text-lg mb-4">{{ selectedMemo.subject }}</h3>
          
          <div class="space-y-2 text-sm pb-4 border-b border-base-200">
            <div class="flex justify-between">
              <span class="opacity-60">From:</span>
              <span class="font-medium">
                {{ selectedMemo.sender?.first_name }} {{ selectedMemo.sender?.last_name }}
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
        
        <!-- Modal Body - Scrollable -->
        <div class="flex-1 overflow-y-auto my-4">
          <div class="prose prose-sm max-w-none">
            <p class="whitespace-pre-wrap">{{ selectedMemo.message }}</p>
          </div>
        </div>
        
        <!-- Modal Footer -->
        <div class="flex-shrink-0 pt-4 border-t border-base-200 modal-action-wrapper flex justify-between">
          <div>
            <button @click="showApprovalModal = false" class="btn btn-ghost">Close</button>
          </div>
          <div class="flex gap-2">
            <button @click="rejectMemo(selectedMemo.id)" class="btn btn-error">
              <XCircle :size="16" class="mr-1" /> Reject
            </button>
            <button @click="approveMemo(selectedMemo.id)" class="btn btn-success">
              <CheckCircle :size="16" class="mr-1" /> Approve
            </button>
          </div>
        </div>
      </div>
      <div class="modal-backdrop" @click="showApprovalModal = false"></div>
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

.modal-action-wrapper {
  flex-shrink: 0;
}
</style>
