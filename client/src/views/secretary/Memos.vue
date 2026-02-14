<script setup>
import { ref, onMounted, watch, computed } from 'vue'
import { useRoute } from 'vue-router'
import { Plus, Search, ChevronDown, Calendar, X, CheckCircle, Clock, Eye, Send, SlidersHorizontal } from 'lucide-vue-next'
import ComposeMemoModal from '@/components/memos/ComposeMemoModal.vue'
import MemoInboxCard from '@/components/memos/MemoInboxCard.vue'
import MemoDetailModal from '@/components/memos/MemoDetailModal.vue'
import api from '@/services/api'
import Swal from 'sweetalert2'

// Filter states
const departmentFilter = ref('All Departments')
const sortFilter = ref('Newest')
const dateFilter = ref('')
const activeTab = ref('all')

// Current user
const storedUser = JSON.parse(localStorage.getItem('user') || '{}')
const currentUserId = storedUser?.id || storedUser?._id || null

// Modal states
const showComposeModal = ref(false)
const selectedMemo = ref(null)
const showDetailModal = ref(false)

// Additional data
const departments = ref([])

// Memo inbox ref for refreshing
const memoInboxRef = ref(null)

// Scope mapping for tabs
const scopeMapping = {
  'all': '',
  'received': 'received',
  'sent': 'sent',
  'pending': 'pending'
}

// Additional params for secretary API
const additionalParams = computed(() => {
  const params = {
    department: departmentFilter.value !== 'All Departments' ? departmentFilter.value : undefined,
    sort: sortFilter.value === 'Oldest' ? 'asc' : 'desc',
    date: dateFilter.value || undefined
  }
  return params
})

const fetchDepartments = async () => {
  try {
    const response = await api.get('/departments')
    departments.value = response.data
  } catch (error) {
    console.error('Error fetching departments:', error)
  }
}

const handleSendMemo = async (result) => {
  try {
    await Swal.fire({
      title: 'Success!',
      text: result.message || 'Memo has been sent successfully.',
      icon: 'success',
      confirmButtonText: 'OK'
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
      selectedMemo.value.status = 'acknowledged'
    }
    if (memoInboxRef.value) {
      memoInboxRef.value.refresh()
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
      if (memoInboxRef.value) {
        memoInboxRef.value.refresh()
      }
    } catch (error) {
      console.error('Error archiving memo:', error)
      Swal.fire('Error', 'Failed to archive memo', 'error')
    }
  }
}

const handleAcknowledged = (memoId) => {
  if (selectedMemo.value?.id === memoId) {
    selectedMemo.value.status = 'acknowledged'
  }
  if (memoInboxRef.value) {
    memoInboxRef.value.refresh()
  }
}

const clearFilters = () => {
  departmentFilter.value = 'All Departments'
  sortFilter.value = 'Newest'
  dateFilter.value = ''
}

const hasActiveFilters = computed(() => {
  return departmentFilter.value !== 'All Departments' ||
         sortFilter.value !== 'Newest' ||
         dateFilter.value !== ''
})

const tabs = [
  { key: 'all', label: 'All', icon: null },
  { key: 'received', label: 'Received', icon: null },
  { key: 'pending', label: 'Pending', icon: null },
  { key: 'sent', label: 'Sent', icon: null }
]

const route = useRoute()

onMounted(async () => {
  fetchDepartments()
  
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
  <div class="memo-dashboard">
    <!-- Page Header -->
    <div class="memo-header">
      <div class="memo-header-left">
        <h1 class="memo-title">Memos</h1>
        <p class="memo-subtitle">Manage and distribute memos</p>
      </div>
      <div class="memo-header-right">
        <button @click="showComposeModal = true" class="memo-compose-btn">
          <Plus :size="18" :stroke-width="2.5" />
          <span>Compose</span>
        </button>
      </div>
    </div>

    <!-- Tabs + Filters Row -->
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

      <div class="memo-filter-group">
        <select v-model="departmentFilter" class="memo-filter-input memo-filter-select">
          <option value="All Departments">All Departments</option>
          <option v-for="dept in departments" :key="dept.id" :value="dept.name">
            {{ dept.name }}
          </option>
        </select>

        <select v-model="sortFilter" class="memo-filter-input memo-filter-select">
          <option value="Newest">Newest First</option>
          <option value="Oldest">Oldest First</option>
        </select>

        <div class="memo-filter-icon-wrapper">
          <Calendar :size="14" class="memo-filter-icon" />
          <input 
            v-model="dateFilter"
            type="date" 
            class="memo-filter-input memo-filter-date"
          />
        </div>

        <button 
          v-if="hasActiveFilters"
          @click="clearFilters"
          class="memo-clear-btn"
        >
          <X :size="14" />
          Clear
        </button>
      </div>
    </div>

    <!-- Memo Inbox Card -->
    <div class="memo-content">
      <MemoInboxCard 
        ref="memoInboxRef"
        :initial-scope="scopeMapping[activeTab]"
        api-endpoint="/secretary/memos"
        max-height="100%"
        :per-page="15"
        :custom-params="additionalParams"
        :current-user-id="currentUserId"
        @memo-click="viewMemo"
        @memo-acknowledge="handleAcknowledge"
        @memo-archive="handleArchive"
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
      user-role="secretary"
      @close="showDetailModal = false"
      @acknowledged="handleAcknowledged"
    />
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

/* Toolbar: Tabs + Filters inline */
.memo-toolbar {
  @apply flex flex-wrap items-center justify-between gap-3;
  padding: 12px 24px;
}

.memo-filter-group {
  @apply flex flex-wrap items-center gap-2;
}

.memo-filter-select {
  min-width: 140px;
  cursor: pointer;
  appearance: none;
  background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%239CA3AF' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
  background-position: right 8px center;
  background-repeat: no-repeat;
  background-size: 14px;
  padding-right: 28px;
  height: 34px;
  font-size: 12px;
}

.memo-filter-icon-wrapper {
  position: relative;
}

.memo-filter-icon {
  position: absolute;
  left: 10px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--color-memo-text-muted);
  pointer-events: none;
  z-index: 1;
}

.memo-filter-date {
  padding-left: 30px;
  min-width: 140px;
  height: 34px;
  font-size: 12px;
}

.memo-clear-btn {
  @apply inline-flex items-center gap-1;
  height: 34px;
  padding: 0 10px;
  border-radius: 8px;
  border: 1px solid var(--color-memo-border);
  background: var(--color-memo-surface);
  font-size: 12px;
  font-weight: 500;
  color: var(--color-memo-text-secondary);
  cursor: pointer;
  transition: all 0.2s ease;
}

.memo-clear-btn:hover {
  color: var(--color-memo-error);
  border-color: rgba(239, 68, 68, 0.3);
  background: rgba(239, 68, 68, 0.05);
}

/* Content — fills all remaining space */
.memo-content {
  @apply flex-1 min-h-0 overflow-hidden;
  padding: 0 24px 12px 24px;
}

.memo-content > * {
  height: 100%;
}

/* Responsive */
@media (max-width: 768px) {
  .memo-header {
    padding: 12px 12px 0 12px;
  }
  
  .memo-toolbar {
    padding: 8px 12px;
    @apply flex-col items-start;
  }

  .memo-pill-tabs {
    overflow-x: auto;
    width: 100%;
  }
  
  .memo-content {
    padding: 0 12px 8px 12px;
  }
  
  .memo-compose-btn {
    width: 100%;
    justify-content: center;
  }
  
  .memo-filter-group {
    @apply w-full;
  }
}
</style>
