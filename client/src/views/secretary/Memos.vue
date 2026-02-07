<script setup>
import { ref, onMounted, computed } from 'vue'
import { Plus, Search, ChevronDown, Calendar, X, Settings2, CheckCircle, Clock, Eye, Send } from 'lucide-vue-next'
import ComposeMemoModal from '@/components/memos/ComposeMemoModal.vue'
import CustomizeMemoModal from '@/components/memos/CustomizeMemoModal.vue'
import api from '@/services/api'
import Swal from 'sweetalert2'

// Filter states
const departmentFilter = ref('All Departments')
const priorityFilter = ref('All Priorities')
const sortFilter = ref('Newest')
const dateFilter = ref('mm/dd/yyyy')
const activeTab = ref('received') // received, sent, pending, drafts

// Modal states
const showComposeModal = ref(false)
const showCustomizeModal = ref(false)
const templateData = ref(null)
const selectedMemo = ref(null)
const showDetailModal = ref(false)

// Data states
const memos = ref([])
const loading = ref(false)
const pagination = ref({
  current_page: 1,
  last_page: 1,
  per_page: 15,
  total: 0
})

// Stats
const stats = ref({
  received: 0,
  sent: 0,
  pending: 0,
  drafts: 0
})

const fetchMemos = async () => {
  try {
    loading.value = true
    const params = {
      scope: activeTab.value === 'received' ? '' : activeTab.value,
      page: pagination.value.current_page,
      per_page: pagination.value.per_page
    }
    
    const response = await api.get('/secretary/memos', { params })
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

const fetchStats = async () => {
  try {
    const response = await api.get('/secretary/memos/stats')
    stats.value = response.data
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
    // For secretaries, memos go to pending approval first
    await api.post('/secretary/memos/submit-for-approval', memoData)
    
    await Swal.fire({
      title: 'Submitted for Approval!',
      text: 'Your memo has been submitted to Admin for approval before distribution.',
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
    Swal.fire('Error', error.response?.data?.message || 'Failed to submit memo for approval', 'error')
  }
}

const viewMemo = (memo) => {
  selectedMemo.value = memo
  showDetailModal.value = true
  
  // Mark as read if received
  if (activeTab.value === 'received' && memo.status === 'sent') {
    markAsRead(memo.id)
  }
}

const markAsRead = async (memoId) => {
  try {
    await api.post(`/memos/${memoId}/acknowledge`)
    const memo = memos.value.find(m => m.id === memoId)
    if (memo) memo.status = 'read'
  } catch (error) {
    console.error('Error marking as read:', error)
  }
}

const acknowledgeMemo = async (memoId) => {
  try {
    await api.post(`/memos/${memoId}/acknowledge`)
    await Swal.fire({
      title: 'Acknowledged!',
      text: 'Memo has been acknowledged.',
      icon: 'success',
      timer: 1500,
      showConfirmButton: false
    })
    fetchMemos()
    fetchStats()
  } catch (error) {
    console.error('Error acknowledging memo:', error)
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

const getStatusIcon = (status) => {
  const icons = {
    sent: Send,
    read: CheckCircle,
    pending: Clock
  }
  return icons[status] || Send
}

const tabs = [
  { key: 'received', label: 'Received', icon: 'inbox' },
  { key: 'sent', label: 'Sent', icon: 'send' },
  { key: 'pending', label: 'Pending Approval', icon: 'clock' },
  { key: 'drafts', label: 'Drafts', icon: 'file' }
]

onMounted(() => {
  fetchMemos()
  fetchStats()
})
</script>

<template>
  <div class="view-container">
    <!-- Page Header -->
    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 mb-6">
      <div>
        <h1 class="text-2xl font-bold text-base-content">Memos</h1>
        <p class="text-sm text-base-content/60">Manage memos for your department</p>
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
        @click="activeTab = key === 'received' ? '' : key"
        class="card bg-base-100 border border-base-200 cursor-pointer hover:border-primary/50 transition-all"
        :class="{ 'border-primary ring-2 ring-primary/20': activeTab === (key === 'received' ? '' : key) }"
      >
        <div class="card-body p-4">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold uppercase tracking-wider opacity-60">{{ key === 'received' ? 'Received' : key }}</span>
            <span class="badge badge-primary badge-sm">{{ count }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Tabs -->
    <div class="tabs tabs-boxed bg-base-200/50 mb-4 p-1 w-fit">
      <button 
        v-for="tab in tabs" 
        :key="tab.key"
        @click="activeTab = tab.key === 'received' ? '' : tab.key"
        class="tab font-bold text-xs uppercase tracking-wider"
        :class="{ 'tab-active bg-primary text-white': activeTab === (tab.key === 'received' ? '' : tab.key) }"
      >
        {{ tab.label }}
      </button>
    </div>

    <!-- Toolbar -->
    <div class="flex flex-col md:flex-row items-center gap-4 mb-4 bg-base-100 p-2 rounded-xl border border-base-200 shadow-sm">
      <!-- Filters -->
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
        
        <!-- Date Picker Placeholder -->
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

      <!-- Search -->
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
      <p class="text-sm text-base-content/30 mt-1">Memos will appear here when received or sent</p>
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
            <!-- Priority Badge -->
            <div class="flex flex-col items-center gap-1">
              <div 
                class="w-3 h-3 rounded-full"
                :class="{
                  'bg-error': memo.priority === 'urgent',
                  'bg-warning': memo.priority === 'high',
                  'bg-info': memo.priority === 'normal',
                  'bg-success': memo.priority === 'low'
                }"
              ></div>
            </div>
            
            <!-- Content -->
            <div class="flex-1 min-w-0">
              <div class="flex items-center justify-between gap-2">
                <h3 class="font-bold truncate" :class="{ 'text-base-content/60': memo.status === 'read' }">
                  {{ memo.subject }}
                </h3>
                <span class="badge badge-sm" :class="getPriorityClass(memo.priority)">
                  {{ memo.priority }}
                </span>
              </div>
              
              <div class="flex items-center gap-4 mt-1 text-sm text-base-content/60">
                <span v-if="memo.sender">
                  From: {{ memo.sender.first_name }} {{ memo.sender.last_name }}
                </span>
                <span>{{ formatDate(memo.created_at) }}</span>
                <span v-if="memo.status === 'pending'" class="text-warning flex items-center gap-1">
                  <Clock :size="12" /> Pending Approval
                </span>
              </div>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-2">
              <button 
                v-if="activeTab === 'received' && memo.status === 'sent'"
                @click.stop="acknowledgeMemo(memo.id)"
                class="btn btn-sm btn-primary btn-outline"
              >
                Acknowledge
              </button>
              <Eye :size="18" class="opacity-40" />
            </div>
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
          <div v-if="selectedMemo.status === 'pending'" class="flex justify-between">
            <span class="opacity-60">Status:</span>
            <span class="badge badge-warning">Pending Approval</span>
          </div>
        </div>
        
        <div class="prose prose-sm max-w-none">
          <p class="whitespace-pre-wrap">{{ selectedMemo.message }}</p>
        </div>
        
        <div v-if="selectedMemo.attachments?.length" class="mt-4 pt-4 border-t border-base-200">
          <p class="text-sm opacity-60 mb-2">Attachments:</p>
          <div class="flex flex-wrap gap-2">
            <a 
              v-for="attachment in selectedMemo.attachments" 
              :key="attachment.name"
              :href="attachment.url"
              target="_blank"
              class="btn btn-sm btn-outline"
            >
              📎 {{ attachment.name }}
            </a>
          </div>
        </div>
        
        <div class="modal-action">
          <button @click="showDetailModal = false" class="btn">Close</button>
          <button 
            v-if="activeTab === 'received' && selectedMemo.status === 'sent'"
            @click="acknowledgeMemo(selectedMemo.id); showDetailModal = false"
            class="btn btn-primary"
          >
            Acknowledge
          </button>
        </div>
      </div>
      <div class="modal-backdrop" @click="showDetailModal = false"></div>
    </div>
  </div>
</template>

<style scoped>
.view-container {
  padding: 0;
}
</style>
