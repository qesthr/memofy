<script setup>
import { ref, onMounted, onUnmounted, computed, watch } from 'vue'
import { Search, CheckCircle, Clock, Eye, Archive, FileText, ChevronDown, Loader2 } from 'lucide-vue-next'
import api from '@/services/api'
import Swal from 'sweetalert2'

const props = defineProps({
  initialScope: {
    type: String,
    default: 'received'
  },
  apiEndpoint: {
    type: String,
    default: '/memos'
  },
  maxHeight: {
    type: String,
    default: '500px'
  },
  perPage: {
    type: Number,
    default: 15
  },
  // Additional params from parent component
  customParams: {
    type: Object,
    default: () => ({})
  }
})

const emit = defineEmits(['memo-click', 'memo-acknowledge', 'memo-archive', 'memo-review'])

// Data states
const memos = ref([])
const loading = ref(false)
const loadingMore = ref(false)
const hasMore = ref(true)
const pagination = ref({
  current_page: 1,
  last_page: 1,
  per_page: props.perPage,
  total: 0
})

// Filter states
const searchQuery = ref('')
const priorityFilter = ref('All Priorities')
const sortFilter = ref('Newest')

// Sort order for priority (ascending: Low -> Medium -> High)
const priorityOrder = {
  'low': 0,
  'normal': 1,
  'medium': 1,
  'high': 2,
  'urgent': 3
}

// Computed sorted and filtered memos
const sortedMemos = computed(() => {
  let filtered = [...memos.value]
  
  // Apply priority filter
  if (priorityFilter.value !== 'All Priorities') {
    const filterPriority = priorityFilter.value.toLowerCase()
    filtered = filtered.filter(memo => memo.priority === filterPriority)
  }
  
  // Apply search filter
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase()
    filtered = filtered.filter(memo => 
      memo.subject?.toLowerCase().includes(query) ||
      memo.message?.toLowerCase().includes(query) ||
      memo.sender?.first_name?.toLowerCase().includes(query) ||
      memo.sender?.last_name?.toLowerCase().includes(query)
    )
  }
  
  // Sort by priority first, then by date
  filtered.sort((a, b) => {
    const priorityA = priorityOrder[a.priority] ?? 1
    const priorityB = priorityOrder[b.priority] ?? 1
    
    if (priorityA !== priorityB) {
      return priorityA - priorityB // Ascending: Low -> Medium -> High
    }
    
    // Secondary sort by date
    const dateA = new Date(a.created_at || 0)
    const dateB = new Date(b.created_at || 0)
    
    if (sortFilter.value === 'Newest') {
      return dateB - dateA
    } else {
      return dateA - dateB
    }
  })
  
  return filtered
})

const fetchMemos = async (reset = false) => {
  if (reset) {
    pagination.value.current_page = 1
    memos.value = []
    hasMore.value = true
  }
  
  if (loading.value || (!hasMore.value && !reset)) return
  
  loading.value = !reset
  
  try {
    // Merge base params with custom params from parent
    const params = {
      scope: props.initialScope,
      page: pagination.value.current_page,
      per_page: props.perPage,
      ...props.customParams
    }
    
    // Remove undefined values
    Object.keys(params).forEach(key => {
      if (params[key] === undefined) {
        delete params[key]
      }
    })
    
    const response = await api.get(props.apiEndpoint, { params })
    
    // Handle different API response structures
    const responseData = response.data.data || response.data || []
    const newMemos = Array.isArray(responseData) ? responseData : []
    
    if (reset) {
      memos.value = newMemos
    } else {
      memos.value = [...memos.value, ...newMemos]
    }
    
    // Update pagination
    if (response.data) {
      pagination.value = {
        current_page: response.data.current_page || 1,
        last_page: response.data.last_page || 1,
        per_page: response.data.per_page || props.perPage,
        total: response.data.total || 0
      }
      hasMore.value = pagination.value.current_page < pagination.value.last_page
    }
  } catch (error) {
    console.error('Error fetching memos:', error)
    Swal.fire('Error', 'Failed to load memos', 'error')
  } finally {
    loading.value = false
  }
}

const loadMore = async () => {
  if (loadingMore.value || !hasMore.value) return
  
  loadingMore.value = true
  pagination.value.current_page++
  
  try {
    const params = {
      scope: props.initialScope,
      page: pagination.value.current_page,
      per_page: pagination.value.per_page
    }
    
    const response = await api.get(props.apiEndpoint, { params })
    
    const responseData = response.data.data || response.data || []
    const newMemos = Array.isArray(responseData) ? responseData : []
    
    memos.value = [...memos.value, ...newMemos]
    
    if (response.data) {
      pagination.value.last_page = response.data.last_page || 1
      hasMore.value = pagination.value.current_page < pagination.value.last_page
    }
  } catch (error) {
    console.error('Error loading more memos:', error)
    pagination.value.current_page--
  } finally {
    loadingMore.value = false
  }
}

// Infinite scroll handler
const inboxContainer = ref(null)
const handleScroll = () => {
  if (!inboxContainer.value) return
  
  const { scrollTop, scrollHeight, clientHeight } = inboxContainer.value
  const threshold = 100 // Load more when within 100px of bottom
  
  if (scrollTop + clientHeight >= scrollHeight - threshold) {
    loadMore()
  }
}

const viewMemo = (memo) => {
  if (memo.status === 'pending_approval') {
    emit('memo-review', memo)
  } else {
    emit('memo-click', memo)
  }
}

const reviewMemo = (memo) => {
  emit('memo-review', memo)
}

const acknowledgeMemo = async (memoId) => {
  try {
    await api.post(`/memos/${memoId}/acknowledge`)
    const memo = memos.value.find(m => m.id === memoId)
    if (memo) memo.status = 'read'
    emit('memo-acknowledge', memoId)
    Swal.fire({
      title: 'Acknowledged!',
      text: 'Memo has been acknowledged.',
      icon: 'success',
      timer: 1500,
      showConfirmButton: false
    })
  } catch (error) {
    console.error('Error acknowledging memo:', error)
    Swal.fire('Error', 'Failed to acknowledge memo', 'error')
  }
}

const archiveMemo = async (memoId) => {
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
      memos.value = memos.value.filter(m => m.id !== memoId)
      emit('memo-archive', memoId)
      Swal.fire('Archived!', 'Memo has been archived.', 'success')
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

// Lifecycle
onMounted(() => {
  fetchMemos(true)
  
  // Add scroll listener to container
  if (inboxContainer.value) {
    inboxContainer.value.addEventListener('scroll', handleScroll)
  }
})

onUnmounted(() => {
  if (inboxContainer.value) {
    inboxContainer.value.removeEventListener('scroll', handleScroll)
  }
})

// Watch for filter changes to reset
const resetAndFetch = () => {
  fetchMemos(true)
}

watch(() => props.initialScope, () => {
  fetchMemos(true)
})

defineExpose({
  refresh: () => fetchMemos(true)
})
</script>

<template>
  <div class="memo-inbox-card card bg-base-100 border border-base-200 shadow-lg overflow-hidden">
    <!-- Card Header -->
    <div class="card-header p-4 border-b border-base-200 bg-base-50">
      <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
        <div>
          <h2 class="text-lg font-bold text-base-content">Memos Inbox</h2>
          <p class="text-xs text-base-content/60">
            {{ sortedMemos.length }} memo{{ sortedMemos.length !== 1 ? 's' : '' }}
          </p>
        </div>
        
        <!-- Filters -->
        <div class="flex flex-wrap items-center gap-2">
          <select v-model="priorityFilter" @change="resetAndFetch" class="select select-sm select-bordered bg-base-100">
            <option value="All Priorities">All Priorities</option>
            <option value="High">High</option>
            <option value="Medium">Medium</option>
            <option value="Low">Low</option>
          </select>
          
          <select v-model="sortFilter" @change="resetAndFetch" class="select select-sm select-bordered bg-base-100">
            <option value="Newest">Newest First</option>
            <option value="Oldest">Oldest First</option>
          </select>
        </div>
      </div>
      
      <!-- Search -->
      <div class="relative mt-3">
        <input 
          v-model="searchQuery"
          type="text" 
          placeholder="Search memos..." 
          class="input input-sm input-bordered w-full pr-8 bg-base-100" 
          @input="resetAndFetch"
        />
        <Search :size="14" class="absolute right-3 top-1/2 -translate-y-1/2 opacity-40" />
      </div>
    </div>
    
    <!-- Memos List - Fixed Height with Internal Scroll -->
    <div 
      ref="inboxContainer"
      class="memos-container overflow-y-auto"
      :style="{ maxHeight: maxHeight }"
    >
      <!-- Loading State -->
      <div v-if="loading" class="flex justify-center items-center py-12">
        <span class="loading loading-spinner loading-lg text-primary"></span>
      </div>
      
      <!-- Empty State -->
      <div v-else-if="sortedMemos.length === 0" class="flex flex-col items-center justify-center py-16 px-4">
        <div class="text-6xl mb-4">📭</div>
        <p class="text-base-content/40 font-medium text-center">No memos found</p>
        <p class="text-sm text-base-content/30 mt-1 text-center">Memos will appear here when received</p>
      </div>
      
      <!-- Memos List -->
      <div v-else class="divide-y divide-base-200">
        <div 
          v-for="memo in sortedMemos" 
          :key="memo.id"
          class="memo-item p-4 hover:bg-base-200/50 transition-all cursor-pointer"
          @click="viewMemo(memo)"
        >
          <div class="flex items-start gap-3">
            <!-- Priority Indicator -->
            <div class="flex flex-col items-center gap-1 pt-1">
              <div 
                class="w-2.5 h-2.5 rounded-full shrink-0"
                :class="getPriorityIconColor(memo.priority)"
              ></div>
            </div>
            
            <!-- Content -->
            <div class="flex-1 min-w-0">
              <div class="flex items-center justify-between gap-2">
                <h3 
                  class="font-semibold truncate text-sm"
                  :class="{ 'text-base-content/60': memo.status === 'read' }"
                >
                  {{ memo.subject }}
                </h3>
                <span 
                  class="badge badge-xs shrink-0" 
                  :class="getPriorityClass(memo.priority)"
                >
                  {{ memo.priority }}
                </span>
              </div>
              
              <div class="flex items-center gap-3 mt-1 text-xs text-base-content/60">
                <span v-if="memo.sender" class="truncate">
                  From: {{ memo.sender.first_name }} {{ memo.sender.last_name }}
                </span>
                <span>{{ formatDate(memo.created_at) }}</span>
              </div>
            </div>
            
            <!-- Actions -->
            <div class="flex items-center gap-1 shrink-0">
              <button 
                v-if="memo.status === 'pending_approval'"
                @click.stop="reviewMemo(memo)"
                class="btn btn-ghost btn-xs text-primary hover:text-primary-content hover:bg-primary/10"
                title="Review"
              >
                <Eye :size="16" />
                <span class="sr-only">Review</span>
              </button>
              <button 
                v-if="memo.status === 'sent'"
                @click.stop="acknowledgeMemo(memo.id)"
                class="btn btn-ghost btn-xs text-success hover:text-success-content hover:bg-success/10"
                title="Acknowledge"
              >
                <CheckCircle :size="16" />
              </button>
              <button 
                @click.stop="archiveMemo(memo.id)"
                class="btn btn-ghost btn-xs text-base-content/40 hover:text-error hover:bg-error/10"
                title="Archive"
              >
                <Archive :size="16" />
              </button>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Loading More -->
      <div v-if="loadingMore" class="flex justify-center items-center py-4">
        <Loader2 :size="20" class="animate-spin text-primary" />
        <span class="ml-2 text-sm text-base-content/60">Loading more...</span>
      </div>
      
      <!-- End of List -->
      <div v-if="!hasMore && sortedMemos.length > 0" class="text-center py-4 text-xs text-base-content/40">
        No more memos to load
      </div>
    </div>
  </div>
</template>

<style scoped>
.memo-inbox-card {
  height: auto;
  display: flex;
  flex-direction: column;
}

.memo-inbox-card .card-header {
  flex-shrink: 0;
}

.memos-container {
  flex: 1;
  overflow-y: auto;
  overflow-x: hidden;
}

.memo-item {
  transition: background-color 0.15s ease;
}

.memo-item:last-child {
  border-bottom: none;
}
</style>
