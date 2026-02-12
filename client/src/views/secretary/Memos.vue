<script setup>
import { ref, onMounted, watch, computed } from 'vue'
import { Plus, Search, ChevronDown, Calendar, X, Settings2, CheckCircle, Clock, Eye, Send } from 'lucide-vue-next'
import ComposeMemoModal from '@/components/memos/ComposeMemoModal.vue'
import CustomizeMemoModal from '@/components/memos/CustomizeMemoModal.vue'
import MemoInboxCard from '@/components/memos/MemoInboxCard.vue'
import MemoDetailModal from '@/components/memos/MemoDetailModal.vue'
import api from '@/services/api'
import Swal from 'sweetalert2'

// Filter states
const departmentFilter = ref('All Departments')
const sortFilter = ref('Newest')
const dateFilter = ref('')
const activeTab = ref('all') // all, sent, pending, drafts

// Modal states
const showComposeModal = ref(false)
const showCustomizeModal = ref(false)
const templateData = ref(null)
const selectedMemo = ref(null)
const showDetailModal = ref(false)

// Additional data
const departments = ref([])

// Memo inbox ref for refreshing
const memoInboxRef = ref(null)

// Scope mapping for tabs
const scopeMapping = {
  'all': '',
  'sent': 'sent',
  'pending': 'pending',
  'drafts': 'drafts'
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
      confirmButtonText: 'OK'
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

// Custom params handler for secretary API
const getParams = () => {
  const baseParams = {
    scope: scopeMapping[activeTab.value] || '',
    per_page: 15
  }
  return { ...baseParams, ...additionalParams.value }
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

onMounted(() => {
  fetchDepartments()
})
</script>

<template>
  <div class="view-container no-scroll">
    <!-- Page Header -->
    <div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-4 mb-6 p-4">
      <div>
        <h1 class="text-2xl font-bold text-base-content">Memos</h1>
        <p class="text-sm text-base-content/60">Manage and distribute memos</p>
      </div>
      <div class="flex gap-2">
        <button @click="showCustomizeModal = true" class="btn btn-ghost btn-sm border border-base-300 px-4 hover:bg-base-200">
          <Settings2 :size="16" class="mr-2" /> Template
        </button>
        <button @click="showComposeModal = true; templateData = null" class="btn btn-primary btn-sm text-white px-6">
          <Plus :size="16" class="mr-1" /> Compose
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

    <!-- Additional Filters for Secretary -->
    <div class="flex flex-wrap items-center gap-3 px-4 mb-4">
      <select v-model="departmentFilter" class="select select-sm select-bordered bg-base-100">
        <option value="All Departments">All Departments</option>
        <option 
          v-for="dept in departments" 
          :key="dept.id" 
          :value="dept.name"
        >
          {{ dept.name }}
        </option>
      </select>
      
      <select v-model="sortFilter" class="select select-sm select-bordered bg-base-100">
        <option value="Newest">Newest First</option>
        <option value="Oldest">Oldest First</option>
      </select>
      
      <input 
        v-model="dateFilter"
        type="date" 
        class="input input-sm input-bordered bg-base-100"
        placeholder="Filter by date"
      />
    </div>

    <!-- Memo Inbox Card -->
    <div class="px-4">
      <MemoInboxCard 
        ref="memoInboxRef"
        :initial-scope="scopeMapping[activeTab]"
        api-endpoint="/secretary/memos"
        :max-height="'calc(100vh - 240px)'"
        :per-page="15"
        :custom-params="additionalParams"
        @memo-click="viewMemo"
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
    <MemoDetailModal
      v-if="showDetailModal && selectedMemo"
      :memo="selectedMemo"
      :is-open="showDetailModal"
      @close="showDetailModal = false"
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
</style>
