<script setup>
import { ref, onMounted } from 'vue'
import { Search, CheckCircle, Clock, Eye, Archive, FileText } from 'lucide-vue-next'
import api from '@/services/api'
import Swal from 'sweetalert2'

// Filter states
const departmentFilter = ref('All Departments')
const priorityFilter = ref('All Priorities')
const sortFilter = ref('Newest')
const activeTab = ref('received')

// Modal states
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

const fetchMemos = async () => {
  try {
    loading.value = true
    const params = {
      scope: '', // Faculty only see received memos
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
  } catch (error) {
    console.error('Error acknowledging memo:', error)
  }
}

const archiveMemo = async (memoId) => {
  const result = await Swal.fire({
    title: 'Archive Memo?',
    text: "You can find this in your archive later.",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#3085d6',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Yes, archive it!'
  })

  if (result.isConfirmed) {
    try {
      await api.delete(`/memos/${memoId}`)
      Swal.fire('Archived!', 'Memo has been archived.', 'success')
      fetchMemos()
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
    normal: 'badge-info',
    low: 'badge-success'
  }
  return classes[priority] || 'badge-info'
}

onMounted(() => {
  fetchMemos()
})
</script>

<template>
  <div class="view-container">
    <!-- Page Header -->
    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 mb-6 p-4">
      <div>
        <h1 class="text-2xl font-bold text-base-content">My Memos</h1>
        <p class="text-sm text-base-content/60">View and acknowledge received memos</p>
      </div>
    </div>

    <!-- Toolbar -->
    <div class="flex flex-col md:flex-row items-center gap-4 mb-4 bg-base-100 p-2 rounded-xl border border-base-200 shadow-sm mx-4">
      <!-- Filters -->
      <div class="flex-1 flex flex-wrap items-center gap-2 w-full">
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
    <div class="px-4">
      <div v-if="loading" class="flex justify-center py-12">
        <span class="loading loading-spinner loading-lg text-primary"></span>
      </div>

      <div v-else-if="memos.length === 0" class="flex flex-col items-center justify-center py-20 bg-base-100 rounded-xl border border-base-200">
        <div class="text-6xl mb-4">📭</div>
        <p class="text-base-content/40 font-medium">No memos found</p>
        <p class="text-sm text-base-content/30 mt-1">Memos sent to you will appear here</p>
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
              <!-- Priority Indicator -->
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
                </div>
              </div>

              <!-- Actions -->
              <div class="flex items-center gap-3">
                <button 
                  v-if="memo.status === 'sent'"
                  @click.stop="acknowledgeMemo(memo.id)"
                  class="text-primary hover:text-primary-focus p-1"
                  title="Acknowledge"
                >
                  <CheckCircle :size="20" />
                </button>
                <button 
                  @click.stop="archiveMemo(memo.id)"
                  class="text-base-content/40 hover:text-error p-1"
                  title="Archive"
                >
                  <Archive :size="20" />
                </button>
                <Eye :size="20" class="opacity-40" />
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Pagination -->
    <div v-if="pagination.last_page > 1" class="flex justify-center mt-6 p-4">
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
        </div>
        
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
        
        <div class="modal-action">
          <button @click="showDetailModal = false" class="btn">Close</button>
          <button 
            v-if="selectedMemo.status === 'sent'"
            @click="acknowledgeMemo(selectedMemo.id); showDetailModal = false"
            class="btn btn-primary"
          >
            Acknowledge
          </button>
          <button 
            @click="archiveMemo(selectedMemo.id); showDetailModal = false"
            class="btn btn-ghost text-error"
          >
            Archive
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
