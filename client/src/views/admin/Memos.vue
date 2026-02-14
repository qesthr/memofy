<script setup>
import { ref, onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { Plus, Search, ChevronDown, Calendar, X, CheckCircle, Clock, Eye, XCircle, Check, FileText } from 'lucide-vue-next'
import ComposeMemoModal from '@/components/memos/ComposeMemoModal.vue'
import MemoInboxCard from '@/components/memos/MemoInboxCard.vue'
import MemoDetailModal from '@/components/memos/MemoDetailModal.vue'
import MemoPdfTemplate from '@/components/memos/MemoPdfTemplate.vue'
import api from '@/services/api'
import Swal from 'sweetalert2'
import html2pdf from 'html2pdf.js'

// Filter states
const activeTab = ref('all')

// Current user
const storedUser = JSON.parse(localStorage.getItem('user') || '{}')
const currentUserId = storedUser?.id || storedUser?._id || null

// Modal states
const showComposeModal = ref(false)
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
  'sent': 'sent'
}

const handleSendMemo = async (result) => {
  try {
    await Swal.fire({
      title: 'Success!',
      text: result.message || 'Memo has been sent successfully.',
      icon: 'success',
      confirmButtonText: 'OK',
      customClass: { confirmButton: 'btn btn-primary' }
    })
    
    showComposeModal.value = false
    
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

const downloadMemoAsPdf = async (memoId) => {
  if (!selectedMemo.value) return
  
  const opt = {
    margin: 0,
    filename: `Memo_${selectedMemo.value.subject || 'Untitled'}.pdf`,
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

  const element = document.querySelector('.approval-modal-body .memo-a4-page')?.parentElement || document.querySelector('.approval-modal-body')
  
  if (!element) {
    Swal.fire({ icon: 'error', title: 'Export Error', text: 'Could not find PDF content to export.' })
    return
  }

  try {
    await html2pdf().set(opt).from(element).save()
  } catch (error) {
    console.error('PDF Generation Error:', error)
  }
}

const formatDate = (date) => {
  if (!date) return '-'
  return new Date(date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
}

const formatTime = (date) => {
  if (!date) return '-'
  return new Date(date).toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })
}

const getPriorityClass = (priority) => {
  const classes = { urgent: 'badge-error', high: 'badge-warning', medium: 'badge-info', normal: 'badge-info', low: 'badge-success' }
  return classes[priority] || 'badge-info'
}

const tabs = [
  { key: 'all', label: 'All' },
  { key: 'pending', label: 'Pending' },
  { key: 'sent', label: 'Sent' }
]

const route = useRoute()

onMounted(async () => {
  const memoId = route.query.memoId
  if (memoId) {
    try {
      const response = await api.get(`/memos/${memoId}`)
      const memo = response.data
      if (memo.status === 'pending_approval') {
        viewApprovalMemo(memo)
      } else {
        viewMemo(memo)
      }
    } catch (error) {
      console.error('Failed to fetch deep-linked memo:', error)
    }
  }
})
</script>

<template>
  <div class="memo-dashboard">
    <!-- Page Header -->
    <div class="memo-header">
      <div class="memo-header-left">
        <h1 class="memo-title">Memos</h1>
        <p class="memo-subtitle">Manage and distribute memos across departments</p>
      </div>
      <div class="memo-header-right">
        <button @click="showComposeModal = true" class="memo-compose-btn">
          <Plus :size="18" :stroke-width="2.5" />
          <span>Compose</span>
        </button>
      </div>
    </div>

    <!-- Tabs Row -->
    <div class="memo-toolbar">
      <div class="memo-pill-tabs">
        <button 
          v-for="tab in tabs" 
          :key="tab.key"
          @click="activeTab = tab.key"
          class="memo-pill-tab"
          :class="{ 'active': activeTab === tab.key }"
        >
          {{ tab.label }}
        </button>
      </div>
    </div>

    <!-- Memo Inbox Card -->
    <div class="memo-content">
      <MemoInboxCard 
        ref="memoInboxRef"
        :initial-scope="scopeMapping[activeTab]"
        api-endpoint="/memos"
        max-height="100%"
        :current-user-id="currentUserId"
        @memo-click="viewMemo"
        @memo-review="viewApprovalMemo"
      />
    </div>

    <!-- Compose Memo Modal -->
    <ComposeMemoModal 
      :is-open="showComposeModal"
      :initial-data="null"
      @close="showComposeModal = false"
      @send="handleSendMemo"
    />

    <!-- Memo Detail Modal -->
    <MemoDetailModal
      v-if="showDetailModal && selectedMemo"
      :memo="selectedMemo"
      :is-open="showDetailModal"
      :current-user-id="currentUserId"
      @close="showDetailModal = false"
    />

    <!-- Approval Modal -->
    <Teleport to="body">
      <div v-if="showApprovalModal && selectedMemo" class="modal modal-open z-50">
        <div class="approval-modal-box">
          <!-- Modal Header -->
          <div class="approval-modal-header">
            <div>
              <h3 class="approval-modal-title">{{ selectedMemo.subject }}</h3>
              <div class="approval-modal-meta">
                <span>From: <strong>{{ selectedMemo.sender?.first_name }} {{ selectedMemo.sender?.last_name }}</strong></span>
                <span>{{ formatDate(selectedMemo.created_at) }} {{ formatTime(selectedMemo.created_at) }}</span>
                <span class="badge badge-sm" :class="getPriorityClass(selectedMemo.priority)">{{ selectedMemo.priority }}</span>
              </div>
            </div>
            <button @click="showApprovalModal = false" class="approval-close-btn">
              <X :size="18" />
            </button>
          </div>
          
          <!-- Modal Body - Scrollable -->
          <div class="approval-modal-body memo-scrollbar">
            <MemoPdfTemplate 
              :memo="selectedMemo" 
              :sender="selectedMemo.sender" 
              :isPreview="true" 
            />
          </div>
          
          <!-- Modal Footer -->
          <div class="approval-modal-footer">
            <button @click="showApprovalModal = false" class="approval-btn approval-btn-ghost">Close</button>
            <div class="approval-actions">
              <button @click="rejectMemo(selectedMemo.id)" class="approval-btn approval-btn-reject">
                <XCircle :size="16" /> Reject
              </button>
              <button @click="approveMemo(selectedMemo.id)" class="approval-btn approval-btn-approve">
                <CheckCircle :size="16" /> Approve
              </button>
              <button @click="downloadMemoAsPdf(selectedMemo.id)" class="approval-btn approval-btn-pdf">
                <FileText :size="16" /> PDF
              </button>
            </div>
          </div>
        </div>
        <div class="modal-backdrop bg-black/40 backdrop-blur-sm" @click="showApprovalModal = false"></div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
@reference "../../style.css";

.memo-dashboard {
  @apply h-screen overflow-hidden flex flex-col;
  background: var(--color-memo-bg);
}

.memo-header {
  @apply flex flex-col md:flex-row items-start md:items-center justify-between gap-2;
  padding: 16px 24px 0 24px;
}

.memo-header-left {
  @apply flex flex-col;
}

.memo-title {
  font-size: 24px;
  font-weight: 800;
  color: var(--color-memo-text-primary);
  letter-spacing: -0.02em;
  line-height: 1.2;
}

.memo-subtitle {
  font-size: 13px;
  font-weight: 500;
  color: var(--color-memo-text-secondary);
  margin-top: 2px;
}

.memo-header-right {
  @apply flex items-center gap-3;
}

/* Toolbar */
.memo-toolbar {
  @apply flex flex-wrap items-center gap-3;
  padding: 12px 24px;
}

/* Content — fills remaining space */
.memo-content {
  @apply flex-1 min-h-0 overflow-hidden;
  padding: 0 24px 12px 24px;
}

.memo-content > * {
  height: 100%;
}

/* Approval Modal */
.approval-modal-box {
  @apply relative flex flex-col;
  max-width: 52rem;
  width: 95vw;
  max-height: 85vh;
  background: var(--color-memo-surface);
  border-radius: 20px;
  box-shadow: 0 20px 60px rgba(0,0,0,0.15), 0 0 0 1px rgba(0,0,0,0.05);
  overflow: hidden;
  z-index: 51;
  animation: approval-modal-in 0.35s cubic-bezier(0.19, 1, 0.22, 1);
}

@keyframes approval-modal-in {
  0% { opacity: 0; transform: scale(0.96) translateY(12px); }
  100% { opacity: 1; transform: scale(1) translateY(0); }
}

.approval-modal-header {
  @apply flex items-start justify-between gap-4;
  padding: 24px 24px 16px;
  border-bottom: 1px solid var(--color-memo-border);
}

.approval-modal-title {
  font-size: 18px;
  font-weight: 700;
  color: var(--color-memo-text-primary);
}

.approval-modal-meta {
  @apply flex items-center gap-4 flex-wrap;
  margin-top: 8px;
  font-size: 13px;
  color: var(--color-memo-text-secondary);
}

.approval-close-btn {
  @apply flex items-center justify-center;
  width: 36px;
  height: 36px;
  border-radius: 10px;
  border: none;
  background: transparent;
  color: var(--color-memo-text-muted);
  cursor: pointer;
  transition: all 0.15s ease;
  flex-shrink: 0;
}

.approval-close-btn:hover {
  background: #F0EEEB;
  color: var(--color-memo-text-primary);
}

.approval-modal-body {
  @apply flex-1 overflow-y-auto;
  background: #F3F1ED;
  padding: 16px;
}

.approval-modal-footer {
  @apply flex items-center justify-between;
  padding: 16px 24px;
  border-top: 1px solid var(--color-memo-border);
}

.approval-actions {
  @apply flex items-center gap-2;
}

.approval-btn {
  @apply inline-flex items-center gap-2;
  height: 38px;
  padding: 0 16px;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 600;
  border: none;
  cursor: pointer;
  transition: all 0.2s ease;
}

.approval-btn-ghost {
  color: var(--color-memo-text-secondary);
  background: transparent;
}
.approval-btn-ghost:hover {
  background: #F0EEEB;
}

.approval-btn-reject {
  color: #ffffff;
  background: var(--color-memo-error);
}
.approval-btn-reject:hover {
  background: #DC2626;
}

.approval-btn-approve {
  color: #ffffff;
  background: var(--color-memo-success);
}
.approval-btn-approve:hover {
  background: #059669;
}

.approval-btn-pdf {
  color: #ffffff;
  background: var(--color-memo-indigo);
}
.approval-btn-pdf:hover {
  background: var(--color-memo-indigo-hover);
}

/* Responsive */
@media (max-width: 768px) {
  .memo-header {
    padding: 12px 12px 0 12px;
  }
  
  .memo-toolbar {
    padding: 8px 12px;
  }

  .memo-pill-tabs {
    overflow-x: auto;
  }
  
  .memo-content {
    padding: 0 12px 8px 12px;
  }
  
  .memo-compose-btn {
    width: 100%;
    justify-content: center;
  }
  
  .approval-modal-footer {
    @apply flex-col gap-3;
  }
  
  .approval-actions {
    @apply w-full justify-end;
  }
}
</style>
