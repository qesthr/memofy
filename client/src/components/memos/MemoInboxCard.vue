<script setup>
import { ref, onMounted, onUnmounted, computed, watch, nextTick } from 'vue'
import { Search, CheckCircle, Clock, Eye, Archive, FileText, Loader2, Inbox, X } from 'lucide-vue-next'
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
  customParams: {
    type: Object,
    default: () => ({})
  },
  currentUserId: {
    type: [String, Number],
    default: null
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
const priorityFilter = ref('all')
const sortFilter = ref('Newest')

// Sort order for priority
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
  
  if (priorityFilter.value !== 'all') {
    filtered = filtered.filter(memo => memo.priority === priorityFilter.value)
  }
  
  if (searchQuery.value) {
    const query = searchQuery.value.toLowerCase()
    filtered = filtered.filter(memo => 
      memo.subject?.toLowerCase().includes(query) ||
      memo.message?.toLowerCase().includes(query) ||
      memo.sender?.first_name?.toLowerCase().includes(query) ||
      memo.sender?.last_name?.toLowerCase().includes(query)
    )
  }
  
  filtered.sort((a, b) => {
    const priorityA = priorityOrder[a.priority] ?? 1
    const priorityB = priorityOrder[b.priority] ?? 1
    
    if (priorityA !== priorityB) {
      return priorityA - priorityB
    }
    
    const dateA = new Date(a.created_at || 0)
    const dateB = new Date(b.created_at || 0)
    
    return sortFilter.value === 'Newest' ? dateB - dateA : dateA - dateB
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
  
  loading.value = true
  
  try {
    const params = {
      scope: props.initialScope,
      page: pagination.value.current_page,
      per_page: props.perPage,
      ...props.customParams
    }
    
    Object.keys(params).forEach(key => {
      if (params[key] === undefined) delete params[key]
    })
    
    const response = await api.get(props.apiEndpoint, { params })
    
    const responseData = response.data.data || response.data || []
    const newMemos = Array.isArray(responseData) ? responseData : []
    
    if (reset) {
      memos.value = newMemos
    } else {
      memos.value = [...memos.value, ...newMemos]
    }
    
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
  const threshold = 100
  
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

const isSender = (memo) => {
  if (!props.currentUserId || !memo.sender) return false
  return String(memo.sender_id || memo.sender?.id) === String(props.currentUserId)
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
    confirmButtonText: 'Yes, archive it!',
    cancelButtonText: 'Cancel'
  })
  
  if (result.isConfirmed) {
    try {
      await api.delete(`/memos/${memoId}`)
      memos.value = memos.value.filter(m => m.id !== memoId)
      emit('memo-archive', memoId)
      Swal.fire({ title: 'Archived!', text: 'Memo has been archived.', icon: 'success', timer: 1500, showConfirmButton: false })
    } catch (error) {
      console.error('Error archiving memo:', error)
      Swal.fire('Error', 'Failed to archive memo', 'error')
    }
  }
}

const formatDate = (date) => {
  if (!date) return '-'
  const d = new Date(date)
  const now = new Date()
  const diff = now - d
  
  // Today
  if (diff < 86400000 && d.getDate() === now.getDate()) {
    return d.toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' })
  }
  // Yesterday
  const yesterday = new Date(now)
  yesterday.setDate(yesterday.getDate() - 1)
  if (d.getDate() === yesterday.getDate() && d.getMonth() === yesterday.getMonth()) {
    return 'Yesterday'
  }
  // This year
  if (d.getFullYear() === now.getFullYear()) {
    return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
  }
  return d.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' })
}

const getPriorityLabel = (priority) => {
  const labels = { urgent: 'Urgent', high: 'High', medium: 'Medium', normal: 'Medium', low: 'Low' }
  return labels[priority] || 'Medium'
}

const isUnread = (memo) => {
  return memo.status === 'sent' || memo.status === 'pending_approval'
}

const clearSearch = () => {
  searchQuery.value = ''
}

// Lifecycle
onMounted(async () => {
  await fetchMemos(true)
  await nextTick()
  if (inboxContainer.value) {
    inboxContainer.value.addEventListener('scroll', handleScroll)
  }
})

onUnmounted(() => {
  if (inboxContainer.value) {
    inboxContainer.value.removeEventListener('scroll', handleScroll)
  }
})

watch(() => props.initialScope, () => {
  fetchMemos(true)
})

defineExpose({
  refresh: () => fetchMemos(true)
})
</script>

<template>
  <div class="inbox-card memo-card">
    <!-- Search Bar -->
    <div class="inbox-search-bar">
      <div class="inbox-search-wrapper">
        <Search :size="16" class="inbox-search-icon" />
        <input 
          v-model="searchQuery"
          type="text" 
          placeholder="Search memos by subject, sender, or content..." 
          class="inbox-search-input"
        />
        <button v-if="searchQuery" @click="clearSearch" class="inbox-search-clear">
          <X :size="14" />
        </button>
      </div>
      
      <div class="inbox-meta">
        <span class="inbox-count">{{ sortedMemos.length }} memo{{ sortedMemos.length !== 1 ? 's' : '' }}</span>
      </div>
    </div>
    
    <!-- Memos List -->
    <div 
      ref="inboxContainer"
      class="inbox-list memo-scrollbar"
      :style="{ maxHeight: maxHeight }"
    >
      <!-- Skeleton Loading State -->
      <div v-if="loading" class="inbox-skeleton-list">
        <div v-for="i in 5" :key="i" class="inbox-skeleton-row">
          <div class="memo-skeleton" style="width:8px; height:8px; border-radius:50%; margin-top:6px;"></div>
          <div style="flex:1; display:flex; flex-direction:column; gap:8px;">
            <div class="memo-skeleton" style="height:14px; width:65%;"></div>
            <div style="display:flex; gap:12px;">
              <div class="memo-skeleton" style="height:10px; width:100px;"></div>
              <div class="memo-skeleton" style="height:10px; width:70px;"></div>
            </div>
          </div>
          <div class="memo-skeleton" style="height:10px; width:50px;"></div>
        </div>
      </div>
      
      <!-- Empty State -->
      <div v-else-if="sortedMemos.length === 0" class="inbox-empty">
        <div class="inbox-empty-icon">
          <Inbox :size="48" :stroke-width="1" />
        </div>
        <h3 class="inbox-empty-title">No memos yet</h3>
        <p class="inbox-empty-text">Start by composing a new memo.<br/>They will appear here once sent or received.</p>
      </div>
      
      <!-- Memo Rows -->
      <div v-else>
        <div 
          v-for="memo in sortedMemos" 
          :key="memo.id"
          class="memo-row"
          @click="viewMemo(memo)"
        >
          <!-- Priority Dot -->
          <div 
            class="priority-dot"
            :class="memo.priority"
            :title="getPriorityLabel(memo.priority)"
          ></div>
          
          <!-- Content -->
          <div class="memo-row-content">
            <div class="memo-row-top">
              <div class="memo-row-subject-line">
                <!-- Unread indicator -->
                <div v-if="isUnread(memo)" class="unread-dot" title="Unread"></div>
                <h3 class="memo-row-subject" :class="{ 'memo-row-read': !isUnread(memo) }">
                  {{ memo.subject }}
                </h3>
              </div>
              <span class="memo-row-date">{{ formatDate(memo.created_at) }}</span>
            </div>
            
            <div class="memo-row-bottom">
              <span v-if="memo.sender" class="memo-row-sender">
                {{ memo.sender.first_name }} {{ memo.sender.last_name }}
              </span>
              <span class="memo-status-badge" :class="memo.status">
                {{ memo.status === 'pending_approval' ? 'Pending' : memo.status }}
              </span>
            </div>
          </div>
          
          <!-- Actions (visible on hover) -->
          <div class="memo-actions">
            <button 
              v-if="memo.status === 'pending_approval'"
              @click.stop="reviewMemo(memo)"
              class="memo-action-btn memo-action-review"
              title="Review"
            >
              <Eye :size="15" />
            </button>
            <button 
              v-if="memo.status === 'sent' && !isSender(memo)"
              @click.stop="acknowledgeMemo(memo.id)"
              class="memo-action-btn memo-action-ack"
              title="Acknowledge"
            >
              <CheckCircle :size="15" />
            </button>
            <button 
              @click.stop="archiveMemo(memo.id)"
              class="memo-action-btn memo-action-archive"
              title="Archive"
            >
              <Archive :size="15" />
            </button>
          </div>
        </div>
      </div>
      
      <!-- Loading More -->
      <div v-if="loadingMore" class="inbox-loading-more">
        <Loader2 :size="18" class="animate-spin" />
        <span>Loading more…</span>
      </div>
      
      <!-- End of List -->
      <div v-if="!hasMore && sortedMemos.length > 0 && !loading" class="inbox-end-msg">
        All memos loaded
      </div>
    </div>
  </div>
</template>

<style scoped>
@reference "../../style.css";

.inbox-card {
  @apply flex flex-col overflow-hidden;
  height: 100%;
}

/* Search Bar */
.inbox-search-bar {
  @apply flex items-center justify-between gap-3;
  padding: 12px 16px;
  border-bottom: 1px solid var(--color-memo-border);
}

.inbox-search-wrapper {
  position: relative;
  flex: 1;
  max-width: 480px;
}

.inbox-search-icon {
  position: absolute;
  left: 14px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--color-memo-text-muted);
  pointer-events: none;
}

.inbox-search-input {
  width: 100%;
  height: 36px;
  padding: 0 36px 0 38px;
  border-radius: 10px;
  border: 1px solid var(--color-memo-border);
  background: var(--color-memo-bg);
  font-size: 13px;
  font-weight: 500;
  color: var(--color-memo-text-primary);
  outline: none;
  transition: all 0.2s ease;
}

.inbox-search-input:focus {
  border-color: var(--color-memo-indigo);
  box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.08);
  background: var(--color-memo-surface);
}

.inbox-search-input::placeholder {
  color: var(--color-memo-text-muted);
  font-weight: 400;
}

.inbox-search-clear {
  position: absolute;
  right: 10px;
  top: 50%;
  transform: translateY(-50%);
  padding: 4px;
  border-radius: 6px;
  border: none;
  background: transparent;
  color: var(--color-memo-text-muted);
  cursor: pointer;
  transition: all 0.15s ease;
}

.inbox-search-clear:hover {
  background: #F0EEEB;
  color: var(--color-memo-text-primary);
}

.inbox-meta {
  @apply flex items-center gap-3;
}

.inbox-count {
  font-size: 13px;
  font-weight: 500;
  color: var(--color-memo-text-muted);
  white-space: nowrap;
}

/* List Container */
.inbox-list {
  @apply flex-1 overflow-y-auto overflow-x-hidden;
}

/* Skeleton */
.inbox-skeleton-list {
  @apply flex flex-col;
}

.inbox-skeleton-row {
  @apply flex items-start gap-3;
  padding: 18px 20px;
  border-bottom: 1px solid #F0EEEB;
}

/* Empty State */
.inbox-empty {
  @apply flex flex-col items-center justify-center;
  padding: 40px 24px;
}

.inbox-empty-icon {
  @apply flex items-center justify-center;
  width: 64px;
  height: 64px;
  border-radius: 16px;
  background: var(--color-memo-indigo-light);
  color: var(--color-memo-indigo);
  margin-bottom: 14px;
}

.inbox-empty-title {
  font-size: 16px;
  font-weight: 700;
  color: var(--color-memo-text-primary);
  margin-bottom: 8px;
}

.inbox-empty-text {
  font-size: 14px;
  color: var(--color-memo-text-muted);
  text-align: center;
  line-height: 1.6;
}

/* Memo Row Content */
.memo-row-content {
  @apply flex-1 min-w-0;
}

.memo-row-top {
  @apply flex items-center justify-between gap-3;
}

.memo-row-subject-line {
  @apply flex items-center gap-2 min-w-0;
}

.memo-row-subject {
  font-size: 14px;
  font-weight: 600;
  color: var(--color-memo-text-primary);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.memo-row-subject.memo-row-read {
  font-weight: 500;
  color: var(--color-memo-text-secondary);
}

.memo-row-date {
  font-size: 12px;
  font-weight: 500;
  color: var(--color-memo-text-muted);
  white-space: nowrap;
  flex-shrink: 0;
}

.memo-row-bottom {
  @apply flex items-center gap-3;
  margin-top: 4px;
}

.memo-row-sender {
  font-size: 12px;
  font-weight: 500;
  color: var(--color-memo-text-secondary);
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* Action Buttons */
.memo-action-btn {
  @apply flex items-center justify-center;
  width: 32px;
  height: 32px;
  border-radius: 8px;
  border: none;
  background: transparent;
  cursor: pointer;
  transition: all 0.15s ease;
}

.memo-action-review {
  color: var(--color-memo-indigo);
}
.memo-action-review:hover {
  background: var(--color-memo-indigo-light);
}

.memo-action-ack {
  color: var(--color-memo-success);
}
.memo-action-ack:hover {
  background: #ECFDF5;
}

.memo-action-archive {
  color: var(--color-memo-text-muted);
}
.memo-action-archive:hover {
  color: var(--color-memo-error);
  background: #FEF2F2;
}

/* Loading More */
.inbox-loading-more {
  @apply flex items-center justify-center gap-2;
  padding: 16px;
  color: var(--color-memo-indigo);
  font-size: 13px;
  font-weight: 500;
}

/* End Message */
.inbox-end-msg {
  text-align: center;
  padding: 16px;
  font-size: 12px;
  color: var(--color-memo-text-muted);
  font-weight: 500;
}
</style>
