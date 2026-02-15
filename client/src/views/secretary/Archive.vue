<script setup>
import { ref, onMounted, computed } from 'vue'
import { Archive, RotateCcw, Search, Filter, FileText, Calendar, Trash2, Eye } from 'lucide-vue-next'
import api from '../../services/api'
import Swal from 'sweetalert2'
import ComposeMemoModal from '@/components/memos/ComposeMemoModal.vue'
import MemoDetailModal from '@/components/memos/MemoDetailModal.vue'

const loading = ref(true)
const searchQuery = ref('')
const activeFilter = ref('all')
const archivedItems = ref([])
const pagination = ref({
  current_page: 1,
  last_page: 1,
  total: 0
})
const counts = ref({
  memos: 0,
  events: 0,
  total: 0
})

// Current user
const storedUser = JSON.parse(localStorage.getItem('user') || '{}')
const currentUserId = storedUser?.id || storedUser?._id || null

// Preview states
const selectedMemo = ref(null)
const showDetailModal = ref(false)

const filters = [
  { id: 'all', label: 'All Items', icon: Filter, count: null },
  { id: 'memos', label: 'Memos', icon: FileText, count: null },
  { id: 'events', label: 'Events', icon: Calendar, count: null }
]

const fetchArchive = async () => {
  loading.value = true
  try {
    const params = {
      type: activeFilter.value,
      search: searchQuery.value,
      page: pagination.value.current_page,
      per_page: 20
    }

    const response = await api.get('/archive', { params })
    archivedItems.value = response.data.data || []
    pagination.value = response.data.pagination || {
      current_page: 1,
      last_page: 1,
      total: 0
    }
    counts.value = response.data.counts || {
      memos: 0,
      events: 0,
      total: 0
    }

    filters[0].count = counts.value.total
    filters[1].count = counts.value.memos
    filters[2].count = counts.value.events
  } catch (error) {
    console.error('Error fetching archive:', error)
  } finally {
    loading.value = false
  }
}

const handleFilterChange = () => {
  pagination.value.current_page = 1
  fetchArchive()
}

const handleSearch = () => {
  pagination.value.current_page = 1
  fetchArchive()
}

const clearSearch = () => {
  searchQuery.value = ''
  handleSearch()
}

const changePage = (page) => {
  if (page >= 1 && page <= pagination.value.last_page) {
    pagination.value.current_page = page
    fetchArchive()
  }
}

const getTypeIcon = (type) => {
  const icons = {
    memo: FileText,
    event: Calendar
  }
  return icons[type] || FileText
}

const getTypeColor = (type) => {
  const colors = {
    memo: 'bg-orange-100 text-orange-600',
    event: 'bg-green-100 text-green-600'
  }
  return colors[type] || 'bg-gray-100 text-gray-600'
}

const getTypeLabel = (type) => {
  const labels = {
    memo: 'Memo',
    event: 'Calendar Event'
  }
  return labels[type] || type
}

const formatDate = (dateStr) => {
  if (!dateStr) return '-'
  const date = new Date(dateStr)
  return date.toLocaleDateString('en-PH', {
    year: 'numeric',
    month: 'short',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const viewMemo = (item) => {
  if (item.type !== 'memo') return
  
  // Adapt payload for MemoDetailModal
  const memoData = { ...item.data }
  
  // Ensure basic structure exists
  if (!memoData.recipient_ids) memoData.recipient_ids = []
  if (!memoData.acknowledgments) memoData.acknowledgments = []
  
  selectedMemo.value = memoData
  showDetailModal.value = true
}

const restoreItem = async (item) => {
  const typeLabels = {
    memo: 'Memo',
    event: 'Calendar Event'
  }

  const result = await Swal.fire({
    icon: 'warning',
    title: 'Restore Item?',
    text: `Are you sure you want to restore this ${typeLabels[item.type]}?`,
    showCancelButton: true,
    confirmButtonColor: '#4285F4',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Yes, restore!'
  })

  if (!result.isConfirmed) return

  try {
    if (item.type === 'memo') {
      await api.post(`/archive/memos/restore/${item.id}`)
    } else if (item.type === 'event') {
      await api.post(`/archive/events/restore/${item.id}`)
    }

    await Swal.fire({
      icon: 'success',
      title: 'Restored!',
      text: 'Item has been restored successfully.',
      confirmButtonColor: '#4285F4',
      timer: 2000
    })

    fetchArchive()
  } catch (error) {
    console.error('Error restoring item:', error)
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: error.response?.data?.message || 'Failed to restore item',
      confirmButtonColor: '#4285F4'
    })
  }
}

const restoreAll = async () => {
  if (archivedItems.value.length === 0) return

  const result = await Swal.fire({
    icon: 'warning',
    title: 'Restore All Items?',
    text: `Are you sure you want to restore all archived items in this category? This will restore ${activeFilter.value === 'all' ? counts.value.total : counts.value[activeFilter.value]} items.`,
    showCancelButton: true,
    confirmButtonColor: '#4285F4',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Yes, restore all!'
  })

  if (!result.isConfirmed) return

  try {
    const response = await api.post('/archive/restore-all', {
      type: activeFilter.value
    })

    await Swal.fire({
      icon: 'success',
      title: 'Items Restored!',
      text: response.data.message,
      confirmButtonColor: '#4285F4',
      timer: 3000
    })

    fetchArchive()
  } catch (error) {
    console.error('Error restoring all items:', error)
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: error.response?.data?.message || 'Failed to restore items',
      confirmButtonColor: '#4285F4'
    })
  }
}

const permanentlyDelete = async (item) => {
  const typeLabels = {
    memo: 'Memo',
    event: 'Calendar Event'
  }

  const result = await Swal.fire({
    icon: 'error',
    title: 'Permanently Delete?',
    html: `
      <div class="text-left">
        <p class="mb-2">This will <strong>permanently delete</strong> this ${typeLabels[item.type]} and cannot be undone.</p>
        <p class="text-sm text-red-500 font-semibold">Caution: This action cannot be reversed!</p>
      </div>
    `,
    showCancelButton: true,
    confirmButtonColor: '#ef4444',
    cancelButtonColor: '#3b82f6',
    confirmButtonText: 'Yes, delete permanently!'
  })

  if (!result.isConfirmed) return

  try {
    if (item.type === 'memo') {
      await api.delete(`/archive/memos/force-delete/${item.id}`)
    } else if (item.type === 'event') {
      await api.delete(`/archive/events/force-delete/${item.id}`)
    }

    await Swal.fire({
      icon: 'success',
      title: 'Deleted!',
      text: 'Item has been permanently deleted.',
      confirmButtonColor: '#4285F4',
      timer: 2000
    })

    fetchArchive()
  } catch (error) {
    console.error('Error deleting item:', error)
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: error.response?.data?.message || 'Failed to delete item',
      confirmButtonColor: '#4285F4'
    })
  }
}

onMounted(() => {
  fetchArchive()
})
</script>

<template>
  <div class="view-container">
    <!-- Header -->
    <div class="flex items-center justify-between mb-8">
      <div class="flex items-center gap-4">
        <div class="w-10 h-10 rounded-lg bg-warning/10 flex items-center justify-center">
            <Archive class="text-warning" :size="24" />
        </div>
        <div>
          <h1 class="text-2xl font-bold">Archive</h1>
          <p class="text-sm text-base-content/60">Manage your deleted memos and calendar events</p>
        </div>
      </div>
      
      <div class="flex items-center gap-3">
        <div class="badge badge-lg badge-warning gap-1">
          <FileText :size="14" />
          {{ counts.memos }}
        </div>
        <div class="badge badge-lg badge-success gap-1">
          <Calendar :size="14" />
          {{ counts.events }}
        </div>
      </div>
    </div>

    <!-- Filters & Search -->
    <div class="bg-base-100 rounded-xl border border-base-200 p-6 mb-6">
      <div class="flex flex-col md:flex-row gap-4 justify-between items-center">
        <div class="flex flex-wrap gap-2">
          <button 
            v-for="filter in filters" 
            :key="filter.id"
            @click="activeFilter = filter.id; handleFilterChange()"
            class="btn btn-sm gap-2"
            :class="activeFilter === filter.id ? 'btn-primary text-white' : 'btn-ghost bg-base-200'"
          >
            <component :is="filter.icon" :size="14" />
            {{ filter.label }}
            <span v-if="filter.count !== null" class="badge badge-xs">
              {{ filter.count }}
            </span>
          </button>
        </div>

        <div class="flex items-center gap-3 w-full md:w-auto">
          <div class="relative flex-1 md:w-64">
            <Search :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-base-content/40" />
            <input 
              v-model="searchQuery"
              @keyup.enter="handleSearch"
              type="text" 
              placeholder="Search archives..." 
              class="input input-sm input-bordered pl-9 w-full"
            />
            <button v-if="searchQuery" @click="clearSearch" class="absolute right-2 top-1/2 -translate-y-1/2 text-base-content/40 hover:text-base-content">
              ×
            </button>
          </div>

          <button 
            v-if="archivedItems.length > 0"
            @click="restoreAll" 
            class="btn btn-success btn-sm gap-2 text-white"
          >
            <RotateCcw :size="16" />
            Restore All
          </button>
        </div>
      </div>
    </div>

    <!-- Main Content -->
    <div v-if="loading" class="flex flex-col items-center justify-center py-20 bg-base-100 rounded-xl border border-base-200">
      <span class="loading loading-spinner loading-lg text-primary mb-4"></span>
      <p class="text-base-content/60 font-medium">Fetching archived items...</p>
    </div>

    <div v-else-if="archivedItems.length === 0" class="bg-base-100 rounded-xl border border-base-200 p-16 text-center">
      <Archive :size="48" class="text-base-content/20 mx-auto mb-4" />
      <h3 class="text-xl font-semibold mb-2">Clean Dashboard!</h3>
      <p class="text-base-content/50 max-w-sm mx-auto text-sm">You don't have any archived items at the moment.</p>
    </div>

    <div v-else class="bg-base-100 rounded-xl border border-base-200 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="table w-full">
          <thead>
            <tr class="bg-base-100 border-b border-base-200 text-base-content/60">
              <th class="py-4 font-semibold pl-6 w-16">Type</th>
              <th class="py-4 font-semibold">Information</th>
              <th class="py-4 font-semibold">Archived Date</th>
              <th class="py-4 font-semibold pr-6 text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr 
              v-for="item in archivedItems" 
              :key="item.id" 
              class="hover:bg-slate-50/50 border-b border-base-100 last:border-0"
            >
              <td class="py-4 pl-6">
                <div class="w-8 h-8 rounded-lg flex items-center justify-center" :class="getTypeColor(item.type)">
                  <component :is="getTypeIcon(item.type)" :size="16" />
                </div>
              </td>
              <td class="py-4">
                <div class="font-semibold">{{ item.title }}</div>
                <div class="text-xs text-base-content/50">{{ item.subtitle }}</div>
                <div v-if="item.description" class="text-[10px] text-base-content/40 mt-1 italic line-clamp-1">{{ item.description }}</div>
              </td>
              <td class="py-4 text-sm text-base-content/60 font-mono">
                {{ formatDate(item.deleted_at) }}
              </td>
              <td class="py-4 pr-6">
                <div class="flex items-center justify-end gap-2">
                  <button 
                    v-if="item.type === 'memo'"
                    @click="viewMemo(item)"
                    class="btn btn-ghost btn-sm btn-square text-primary bg-primary/10 hover:bg-primary/20"
                    title="View Content"
                  >
                    <Eye :size="16" />
                  </button>
                  <button 
                    @click="restoreItem(item)"
                    class="btn btn-ghost btn-sm btn-square text-success bg-success/10 hover:bg-success/20"
                    title="Restore"
                  >
                    <RotateCcw :size="16" />
                  </button>
                  <button 
                    @click="permanentlyDelete(item)"
                    class="btn btn-ghost btn-sm btn-square text-error bg-error/10 hover:bg-error/20"
                    title="Permanently Delete"
                  >
                    <Trash2 :size="16" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination -->
      <div v-if="pagination.last_page > 1" class="p-4 border-t border-base-200 flex items-center justify-between">
        <span class="text-xs text-base-content/60">
            Page {{ pagination.current_page }} of {{ pagination.last_page }} ({{ pagination.total }} total)
        </span>
        <div class="join">
          <button 
            @click="changePage(pagination.current_page - 1)" 
            class="join-item btn btn-xs btn-ghost" 
            :disabled="pagination.current_page === 1"
          >
            Previous
          </button>
          <button 
            v-for="page in pagination.last_page" 
            :key="page"
            @click="changePage(page)"
            :class="['join-item btn btn-xs', pagination.current_page === page ? 'btn-active' : 'btn-ghost']"
          >
            {{ page }}
          </button>
          <button 
            @click="changePage(pagination.current_page + 1)" 
            class="join-item btn btn-xs btn-ghost" 
            :disabled="pagination.current_page === pagination.last_page"
          >
            Next
          </button>
        </div>
      </div>
    </div>

    <!-- Memo Detail Modal -->
    <MemoDetailModal
      v-if="showDetailModal && selectedMemo"
      :memo="selectedMemo"
      :is-open="showDetailModal"
      :current-user-id="currentUserId"
      user-role="secretary"
      @close="showDetailModal = false"
    />
  </div>
</template>

<style scoped>
@reference "../../style.css";

.view-container {
  @apply p-0;
}
</style>
