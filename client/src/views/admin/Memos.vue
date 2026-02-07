<script setup>
import { ref, onMounted } from 'vue'
import { Plus, Search, ChevronDown, Calendar, X, Settings2, CheckCircle, Clock, Eye, XCircle, Check } from 'lucide-vue-next'
import ComposeMemoModal from '@/components/memos/ComposeMemoModal.vue'
import CustomizeMemoModal from '@/components/memos/CustomizeMemoModal.vue'
import api from '@/services/api'
import Swal from 'sweetalert2'

// Filter states
const departmentFilter = ref('All Departments')
const priorityFilter = ref('All Priorities')
const sortFilter = ref('Newest')
const dateFilter = ref('mm/dd/yyyy')
const activeTab = ref('all') // all, pending, sent, drafts

// Modal states
const showComposeModal = ref(false)
const showCustomizeModal = ref(false)
const templateData = ref(null)
const selectedMemo = ref(null)
const showDetailModal = ref(false)
const showApprovalModal = ref(false)

// Data states
const memos = ref([])
const pendingApprovals = ref([])
const loading = ref(false)
const pagination = ref({
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0
})

// Stats
const stats = ref({
  all: 0,
  pending: 0,
  sent: 0,
  drafts: 0
})

const fetchMemos = async () => {
  try {
    loading.value = true
    const params = {
      scope: activeTab.value === 'all' ? '' : activeTab.value,
      page: pagination.value.current_page,
      per_page: pagination.value.per_page
    }
    
    const response = await api.get('/memos', { params })
    memos.value = response.data.data || []
    pagination.value = {
      current_page: response.data.current_page || 1,
      last_page: response.data.last_page || 1,
      per_page: response.data.per_page || 15,
      total: response.data.total || 0
    }
  } catch (error) {
    console.error('Error fetching memos:', error)
    Swal.fire('Error', 'Failed to load memos', 'error')
  } finally {
    loading.value = false
  }
}

const fetchPendingApprovals = async () => {
  try {
    const response = await api.get('/admin/memos/pending-approvals')
    pendingApprovals.value = response.data.data || response.data || []
  } catch (error) {
    console.error('Error fetching pending approvals:', error)
  }
}

const fetchStats = async () => {
  try {
    const response = await api.get('/admin/dashboard-stats')
    // Parse stats from dashboard response
    const dashboardStats = response.data
    stats.value = {
      all: dashboardStats[0]?.value || 0,
      pending: dashboardStats[1]?.value || 0,
      sent: parseInt(dashboardStats[0]?.value || 0) - parseInt(dashboardStats[1]?.value || 0),
      drafts: 0
    }
  } catch (error) {
    console.error('Error fetching stats:', error)
  }
}

const handleTemplateApply = (data) => {
  templateData.value = data
  showCustomizeModal.value = false
  showComposeModal.value = true
}

const handleSendMemo = async (memoData) => {
  try {
    const response = await api.post('/memos', memoData)
    
    await Swal.fire({
      title: 'Success!',
      text: 'Memo has been sent successfully.',
      icon: 'success',
      confirmButtonText: 'OK',
      customClass: {
        confirmButton: 'btn btn-primary'
      }
    })
    
    showComposeModal.value = false
    templateData.value = null
    fetchMemos()
    fetchStats()
  } catch (error) {
    console.error('Error sending memo:', error)
    Swal.fire('Error', error.response?.data?.message || 'Failed to send memo', 'error')
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
  try {
    const result = await Swal.fire({
      title: 'Approve Memo?',
      text: 'This will send the memo to all recipients.',
      icon: 'question',
      showCancelButton: true,
      confirmButtonText: 'Approve',
      cancelButtonText: 'Cancel'
    })

    if (result.isConfirmed) {
      await api.post(`/admin/memos/${memoId}/approve`)
      
      await Swal.fire({
        title: 'Approved!',
        text: 'Memo has been approved and sent to recipients.',
        icon: 'success',
        timer: 2000,
        showConfirmButton: false
      })
      
      showApprovalModal.value = false
      fetchPendingApprovals()
      fetchMemos()
    }
  } catch (error) {
    console.error('Error approving memo:', error)
    Swal.fire('Error', error.response?.data?.message || 'Failed to approve memo', 'error')
  }
}

const rejectMemo = async (memoId) => {
  try {
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
      fetchPendingApprovals()
    }
  } catch (error) {
    console.error('Error rejecting memo:', error)
    Swal.fire('Error', error.response?.data?.message || 'Failed to reject memo', 'error')
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
    normal: 'badge-info',
    low: 'badge-success'
  }
  return classes[priority] || 'badge-info'
}

const tabs = [
  { key: 'all', label: 'All Memos' },
  { key: 'pending', label: 'Pending Approvals', icon: 'clock' },
  { key: 'sent', label: 'Sent' },
  { key: 'drafts', label: 'Drafts' }
]

const hasPermission = (perm) => {
  const user = JSON.parse(localStorage.getItem('user') || '{}')
  const role = user.role?.name || user.role || ''
  if (role === 'admin' || role === 'super_admin') return true
  return user.permissions?.includes(perm) || false
}

onMounted(() => {
  fetchMemos()
  fetchPendingApprovals()
  fetchStats()
})
</script>

<template>
  <div class="view-container">
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

    <!-- Stats Cards -->
    <div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-6">
      <div 
        v-for="(count, key) in stats" 
        :key="key"
        @click="activeTab = key === 'all' ? '' : key"
        class="card bg-base-100 border border-base-200 cursor-pointer hover:border-primary/50 transition-all"
        :class="{ 'border-primary ring-2 ring-primary/20': activeTab === (key === 'all' ? '' : key) }"
      >
        <div class="card-body p-4">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold uppercase tracking-wider opacity-60">{{ key }}</span>
            <span class="badge badge-primary badge-sm">{{ count }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Pending Approvals Section (Admin Only) -->
    <div v-if="pendingApprovals.length > 0 && activeTab === 'pending'" class="mb-6">
      <div class="bg-warning/10 border border-warning rounded-xl p-4 mb-4">
        <div class="flex items-center gap-2 mb-4">
          <Clock class="text-warning" :size="20" />
          <h3 class="font-bold text-warning-content">Pending Approvals</h3>
          <span class="badge badge-warning">{{ pendingApprovals.length }}</span>
        </div>
        
        <div class="space-y-2">
          <div 
            v-for="memo in pendingApprovals" 
            :key="memo.id"
            @click="viewApprovalMemo(memo)"
            class="flex items-center justify-between bg-base-100 p-3 rounded-lg cursor-pointer hover:bg-base-200 transition-colors"
          >
            <div class="flex items-center gap-3">
              <div 
                class="w-2 h-2 rounded-full"
                :class="{
                  'bg-error': memo.priority === 'urgent',
                  'bg-warning': memo.priority === 'high',
                  'bg-info': memo.priority === 'normal',
                  'bg-success': memo.priority === 'low'
                }"
              ></div>
              <div>
                <p class="font-medium">{{ memo.subject }}</p>
                <p class="text-xs text-base-content/60">
                  From: {{ memo.sender?.first_name }} {{ memo.sender?.last_name }} • 
                  {{ formatDate(memo.created_at) }}
                </p>
              </div>
            </div>
            <div class="flex items-center gap-2">
              <span class="badge badge-sm" :class="getPriorityClass(memo.priority)">{{ memo.priority }}</span>
              <button class="btn btn-sm btn-ghost">Review</button>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Tabs -->
    <div class="tabs tabs-boxed bg-base-200/50 mb-4 p-1 w-fit">
      <button 
        v-for="tab in tabs" 
        :key="tab.key"
        @click="activeTab = tab.key === 'all' ? '' : tab.key"
        class="tab font-bold text-xs uppercase tracking-wider"
        :class="{ 'tab-active bg-primary text-white': activeTab === (tab.key === 'all' ? '' : tab.key) }"
      >
        {{ tab.label }}
        <span v-if="tab.key === 'pending' && pendingApprovals.length > 0" class="badge badge-error badge-xs ml-2">
          {{ pendingApprovals.length }}
        </span>
      </button>
    </div>

    <!-- Toolbar -->
    <div class="flex flex-col md:flex-row items-center gap-4 mb-4 bg-base-100 p-2 rounded-xl border border-base-200 shadow-sm">
      <div class="flex-1 flex flex-wrap items-center gap-2 w-full">
        <select v-model="departmentFilter" class="select select-sm select-bordered w-full md:w-auto bg-base-100">
          <option selected>All Departments</option>
          <option>Computer Science</option>
          <option>Information Technology</option>
        </select>
        
        <select v-model="priorityFilter" class="select select-sm select-bordered w-full md:w-auto bg-base-100">
          <option selected>All Priorities</option>
          <option>High</option>
          <option>Normal</option>
          <option>Low</option>
        </select>
        
        <select v-model="sortFilter" class="select select-sm select-bordered w-full md:w-auto bg-base-100">
          <option selected>Newest</option>
          <option>Oldest</option>
        </select>
        
        <div class="relative w-full md:w-auto">
          <input 
            type="text" 
            placeholder="mm/dd/yyyy" 
            class="input input-sm input-bordered w-full pr-8 bg-base-100" 
          />
          <button class="absolute right-2 top-1/2 -translate-y-1/2 text-base-content/40 hover:text-base-content">
            <X :size="14" />
          </button>
        </div>
      </div>

      <div class="relative w-full md:w-64">
        <input 
          type="text" 
          placeholder="Search memos..." 
          class="input input-sm input-bordered w-full pr-8 bg-base-100" 
        />
        <Search :size="14" class="absolute right-3 top-1/2 -translate-y-1/2 opacity-40" />
      </div>
    </div>

    <!-- Memos List -->
    <div v-if="loading" class="flex justify-center py-12">
      <span class="loading loading-spinner loading-lg text-primary"></span>
    </div>

    <div v-else-if="memos.length === 0" class="flex flex-col items-center justify-center py-20 bg-base-100 rounded-xl border border-base-200">
      <div class="text-6xl mb-4">📭</div>
      <p class="text-base-content/40 font-medium">No memos found</p>
    </div>

    <div v-else class="space-y-2">
      <div 
        v-for="memo in memos" 
        :key="memo.id"
        @click="viewMemo(memo)"
        class="card bg-base-100 border border-base-200 hover:border-primary/30 hover:shadow-md transition-all cursor-pointer"
      >
        <div class="card-body p-4">
          <div class="flex items-start gap-4">
            <div 
              class="w-3 h-3 rounded-full mt-1"
              :class="{
                'bg-error': memo.priority === 'urgent',
                'bg-warning': memo.priority === 'high',
                'bg-info': memo.priority === 'normal',
                'bg-success': memo.priority === 'low'
              }"
            ></div>
            
            <div class="flex-1 min-w-0">
              <div class="flex items-center justify-between gap-2">
                <h3 class="font-bold truncate">{{ memo.subject }}</h3>
                <span class="badge badge-sm" :class="getPriorityClass(memo.priority)">
                  {{ memo.priority }}
                </span>
              </div>
              
              <div class="flex items-center gap-4 mt-1 text-sm text-base-content/60">
                <span v-if="memo.sender">
                  From: {{ memo.sender.first_name }} {{ memo.sender.last_name }}
                </span>
                <span v-if="memo.recipient">
                  To: {{ memo.recipient.first_name }} {{ memo.recipient.last_name }}
                </span>
                <span>{{ formatDate(memo.created_at) }}</span>
              </div>
            </div>

            <Eye :size="18" class="opacity-40" />
          </div>
        </div>
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="pagination.last_page > 1" class="flex justify-center mt-6">
      <div class="join">
        <button 
          @click="pagination.current_page--; fetchMemos()"
          class="join-item btn btn-sm"
          :disabled="pagination.current_page === 1"
        >
          Previous
        </button>
        <button class="join-item btn btn-sm">Page {{ pagination.current_page }} of {{ pagination.last_page }}</button>
        <button 
          @click="pagination.current_page++; fetchMemos()"
          class="join-item btn btn-sm"
          :disabled="pagination.current_page === pagination.last_page"
        >
          Next
        </button>
      </div>
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
      <div class="modal-box max-w-3xl">
        <button @click="showDetailModal = false" class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
        
        <h3 class="font-bold text-lg mb-4">{{ selectedMemo.subject }}</h3>
        
        <div class="space-y-2 text-sm mb-4 pb-4 border-b border-base-200">
          <div class="flex justify-between">
            <span class="opacity-60">From:</span>
            <span class="font-medium">
              {{ selectedMemo.sender?.first_name }} {{ selectedMemo.sender?.last_name }}
            </span>
          </div>
          <div class="flex justify-between">
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
        
        <div class="prose prose-sm max-w-none">
          <p class="whitespace-pre-wrap">{{ selectedMemo.message }}</p>
        </div>
        
        <div class="modal-action">
          <button @click="showDetailModal = false" class="btn">Close</button>
        </div>
      </div>
      <div class="modal-backdrop" @click="showDetailModal = false"></div>
    </div>

    <!-- Approval Modal -->
    <div v-if="showApprovalModal && selectedMemo" class="modal modal-open z-50">
      <div class="modal-box max-w-3xl">
        <button @click="showApprovalModal = false" class="btn btn-sm btn-circle btn-ghost absolute right-2 top-2">✕</button>
        
        <div class="flex items-center gap-3 mb-4">
          <Clock class="text-warning" :size="24" />
          <h3 class="font-bold text-lg">Pending Approval</h3>
        </div>
        
        <div class="space-y-2 text-sm mb-4 pb-4 border-b border-base-200">
          <div class="flex justify-between">
            <span class="opacity-60">From:</span>
            <span class="font-medium">
              {{ selectedMemo.sender?.first_name }} {{ selectedMemo.sender?.last_name }}
              <span class="text-xs opacity-60">({{ selectedMemo.sender?.role }})</span>
            </span>
          </div>
          <div class="flex justify-between">
            <span class="opacity-60">Department:</span>
            <span class="font-medium">{{ selectedMemo.department?.name || 'N/A' }}</span>
          </div>
          <div class="flex justify-between">
            <span class="opacity-60">Submitted:</span>
            <span class="font-medium">{{ formatDate(selectedMemo.created_at) }} {{ formatTime(selectedMemo.created_at) }}</span>
          </div>
          <div class="flex justify-between">
            <span class="opacity-60">Priority:</span>
            <span class="badge badge-sm" :class="getPriorityClass(selectedMemo.priority)">
              {{ selectedMemo.priority }}
            </span>
          </div>
        </div>
        
        <h4 class="font-bold mb-2">Subject</h4>
        <p class="font-medium text-lg mb-4">{{ selectedMemo.subject }}</p>
        
        <h4 class="font-bold mb-2">Content</h4>
        <div class="prose prose-sm max-w-none bg-base-200/50 p-4 rounded-lg">
          <p class="whitespace-pre-wrap">{{ selectedMemo.message }}</p>
        </div>
        
        <div class="modal-action">
          <button @click="showApprovalModal = false" class="btn">Close</button>
          <button @click="rejectMemo(selectedMemo.id)" class="btn btn-error btn-outline">
            <XCircle :size="18" class="mr-2" /> Reject
          </button>
          <button @click="approveMemo(selectedMemo.id)" class="btn btn-success">
            <CheckCircle :size="18" class="mr-2" /> Approve & Send
          </button>
        </div>
      </div>
      <div class="modal-backdrop" @click="showApprovalModal = false"></div>
    </div>
  </div>
</template>

<style scoped>
.view-container {
  padding: 0;
}
</style>
