<script setup>
import { ref, onMounted, computed } from 'vue'
import { Archive, RotateCcw, Search, Filter, FileText, Calendar, Trash2 } from 'lucide-vue-next'
import api from '../../services/api'
import Swal from 'sweetalert2'

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
  <div class="archive-container p-6">
    <!-- Header -->
    <div class="flex items-center justify-between mb-8">
      <div class="flex items-center gap-4">
        <div class="header-icon-wrapper">
          <Archive class="text-indigo-600" :size="24" />
        </div>
        <div>
          <h1 class="text-2xl font-bold text-gray-900 leading-tight">Archive</h1>
          <p class="text-sm text-gray-500">Manage your deleted memos and calendar events</p>
        </div>
      </div>
      
      <div class="flex items-center gap-2">
        <div class="stat-badge bg-orange-50 text-orange-700">
          <FileText :size="14" />
          <span>{{ counts.memos }} Memos</span>
        </div>
        <div class="stat-badge bg-green-50 text-green-700">
          <Calendar :size="14" />
          <span>{{ counts.events }} Events</span>
        </div>
      </div>
    </div>

    <!-- Filters & Search -->
    <div class="glass-shadow rounded-2xl p-4 mb-6 bg-white/80 backdrop-blur-sm border border-gray-100">
      <div class="flex flex-col md:flex-row gap-4 justify-between items-center">
        <div class="flex items-center gap-1 bg-gray-50 p-1 rounded-xl">
          <button 
            v-for="filter in filters" 
            :key="filter.id"
            @click="activeFilter = filter.id; handleFilterChange()"
            class="filter-btn"
            :class="activeFilter === filter.id ? 'active' : ''"
          >
            <component :is="filter.icon" :size="14" />
            <span>{{ filter.label }}</span>
            <span v-if="filter.count !== null" class="count-badge">
              {{ filter.count }}
            </span>
          </button>
        </div>

        <div class="flex items-center gap-3 w-full md:w-auto">
          <div class="search-wrapper relative flex-1 md:w-72">
            <Search :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" />
            <input 
              v-model="searchQuery"
              @keyup.enter="handleSearch"
              type="text" 
              placeholder="Search archives..." 
              class="search-input"
            />
            <button v-if="searchQuery" @click="clearSearch" class="clear-search-btn">
              <span class="text-lg">×</span>
            </button>
          </div>

          <button 
            v-if="archivedItems.length > 0"
            @click="restoreAll" 
            class="action-btn-primary"
          >
            <RotateCcw :size="16" />
            <span>Restore All</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Main Content -->
    <div v-if="loading" class="flex flex-col items-center justify-center py-20 bg-white glass-shadow rounded-2xl border border-gray-100">
      <div class="loading-spinner mb-4"></div>
      <p class="text-gray-500 font-medium">Fetching archived items...</p>
    </div>

    <div v-else-if="archivedItems.length === 0" class="empty-state bg-white glass-shadow rounded-2xl border border-gray-100 p-16 text-center">
      <div class="empty-icon-wrapper mb-6">
        <Archive :size="48" class="text-gray-200" />
      </div>
      <h3 class="text-xl font-semibold text-gray-900 mb-2">Clean Dashboard!</h3>
      <p class="text-gray-500 max-w-sm mx-auto">You don't have any archived items at the moment. Deleted memos and events will appear here.</p>
    </div>

    <div v-else class="table-card glass-shadow bg-white rounded-2xl border border-gray-100 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="w-full text-left">
          <thead>
            <tr class="bg-gray-50/50 border-b border-gray-100">
              <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider w-16">Type</th>
              <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Information</th>
              <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Details</th>
              <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider">Archived Date</th>
              <th class="px-6 py-4 text-xs font-bold text-gray-500 uppercase tracking-wider text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <tr 
              v-for="item in archivedItems" 
              :key="item.id" 
              class="memo-row transition-all hover:bg-indigo-50/30 group"
            >
              <td class="px-6 py-5">
                <div class="type-icon-box" :class="getTypeColor(item.type)">
                  <component :is="getTypeIcon(item.type)" :size="16" />
                </div>
              </td>
              <td class="px-6 py-5">
                <div class="font-semibold text-gray-900 group-hover:text-indigo-600 transition-colors">{{ item.title }}</div>
                <div class="text-xs text-gray-400 mt-0.5 flex items-center gap-1.5 font-medium">
                   <span>{{ item.subtitle }}</span>
                </div>
              </td>
              <td class="px-6 py-5">
                <span class="text-sm text-gray-600 font-medium">{{ item.description }}</span>
                <div class="mt-1">
                   <span class="badge-mini" :class="item.type === 'memo' ? 'badge-memo' : 'badge-event'">
                     {{ getTypeLabel(item.type) }}
                   </span>
                </div>
              </td>
              <td class="px-6 py-5">
                <div class="text-sm text-gray-500 font-medium">{{ formatDate(item.deleted_at) }}</div>
              </td>
              <td class="px-6 py-5">
                <div class="flex items-center justify-end gap-2 opacity-0 group-hover:opacity-100 transition-opacity">
                  <button 
                    @click="restoreItem(item)"
                    class="btn-icon text-green-600 bg-green-50 hover:bg-green-100"
                    title="Restore"
                  >
                    <RotateCcw :size="16" />
                  </button>
                  <button 
                    @click="permanentlyDelete(item)"
                    class="btn-icon text-red-600 bg-red-50 hover:bg-red-100"
                    title="Delete Forever"
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
      <div v-if="pagination.last_page > 1" class="px-6 py-4 bg-gray-50/50 border-t border-gray-100 flex items-center justify-between">
        <span class="text-xs font-medium text-gray-500">
          Showing <span class="text-gray-900">{{ archivedItems.length }}</span> of <span class="text-gray-900">{{ pagination.total }}</span> entries
        </span>
        <div class="flex items-center gap-1">
          <button 
            @click="changePage(pagination.current_page - 1)" 
            class="pagination-btn" 
            :disabled="pagination.current_page === 1"
          >
            Previous
          </button>
          <div class="flex gap-1 mx-2">
            <button 
              v-for="page in pagination.last_page" 
              :key="page"
              @click="changePage(page)"
              class="pagination-num-btn"
              :class="pagination.current_page === page ? 'active' : ''"
            >
              {{ page }}
            </button>
          </div>
          <button 
            @click="changePage(pagination.current_page + 1)" 
            class="pagination-btn" 
            :disabled="pagination.current_page === pagination.last_page"
          >
            Next
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
@reference "../../style.css";

.archive-container {
  min-height: 100vh;
  background-color: transparent;
}

.header-icon-wrapper {
  @apply w-12 h-12 rounded-2xl bg-indigo-50 flex items-center justify-center border border-indigo-100 shadow-sm shadow-indigo-100/50;
}

.glass-shadow {
  box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
}

.filter-btn {
  @apply flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-semibold transition-all duration-200;
  color: var(--color-memo-text-muted);
}

.filter-btn:hover {
  @apply bg-white text-indigo-600;
}

.filter-btn.active {
  @apply bg-white text-indigo-600 shadow-sm border border-gray-100;
}

.count-badge {
  @apply text-[10px] font-bold bg-indigo-50 text-indigo-600 px-1.5 py-0.5 rounded-full min-w-[20px] text-center;
}

.search-input {
  @apply w-full bg-gray-50 border border-gray-200 rounded-xl py-2 pl-10 pr-10 text-sm font-medium transition-all;
  @apply focus:bg-white focus:outline-none focus:ring-4 focus:ring-indigo-500/10 focus:border-indigo-500;
}

.clear-search-btn {
  @apply absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600;
}

.action-btn-primary {
  @apply flex items-center gap-2 bg-indigo-600 text-white px-5 py-2 rounded-xl text-sm font-bold shadow-md shadow-indigo-200/50 hover:bg-indigo-700 hover:-translate-y-0.5 active:translate-y-0 transition-all;
}

.stat-badge {
  @apply flex items-center gap-2 px-3 py-1.5 rounded-lg text-xs font-bold border border-current/10;
}

.loading-spinner {
  @apply w-10 h-10 border-4 border-indigo-100 border-t-indigo-600 rounded-full animate-spin;
}

.empty-icon-wrapper {
  @apply w-20 h-20 bg-gray-50 rounded-full flex items-center justify-center mx-auto border border-gray-100;
}

.type-icon-box {
  @apply w-9 h-9 rounded-xl flex items-center justify-center shadow-sm;
}

.btn-icon {
  @apply w-8 h-8 rounded-lg flex items-center justify-center transition-all hover:scale-110 active:scale-95;
}

.pagination-btn {
  @apply px-4 py-1.5 text-xs font-bold rounded-lg transition-all border border-gray-200 text-gray-600 hover:bg-gray-50 disabled:opacity-50 disabled:cursor-not-allowed;
}

.pagination-num-btn {
  @apply w-8 h-8 flex items-center justify-center text-xs font-bold rounded-lg transition-all text-gray-500 hover:bg-gray-100;
}

.pagination-num-btn.active {
  @apply bg-indigo-600 text-white shadow-md shadow-indigo-100;
}

.badge-mini {
  @apply text-[10px] uppercase tracking-wider font-bold px-2 py-0.5 rounded-md;
}

.badge-memo { @apply bg-orange-50 text-orange-600; }
.badge-event { @apply bg-green-50 text-green-600; }
</style>
