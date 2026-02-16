<script setup>
import { ref, onMounted, computed } from 'vue'
import ComposeMemoModal from '@/components/memos/ComposeMemoModal.vue'
import MemoDetailModal from '@/components/memos/MemoDetailModal.vue'
import { Archive, RotateCcw, Search, Filter, FileText, Trash2, Eye } from 'lucide-vue-next'
import api from '../../services/api'
import Swal from 'sweetalert2'

const loading = ref(true)
const searchQuery = ref('')
const archivedItems = ref([])
const pagination = ref({
  current_page: 1,
  last_page: 1,
  total: 0
})
const counts = ref({
  memos: 0,
  total: 0
})

// Current user
const storedUser = JSON.parse(localStorage.getItem('user') || '{}')
const currentUserId = storedUser?.id || storedUser?._id || null

// Preview states
const selectedMemo = ref(null)
const showDetailModal = ref(false)

const fetchArchive = async () => {
  loading.value = true
  try {
    const params = {
      type: 'memos', // Faculty only shows memos
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
      total: 0
    }
  } catch (error) {
    console.error('Error fetching archive:', error)
  } finally {
    loading.value = false
  }
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
  const result = await Swal.fire({
    icon: 'warning',
    title: 'Restore Memo?',
    text: `Are you sure you want to restore this memo?`,
    showCancelButton: true,
    confirmButtonColor: '#4285F4',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Yes, restore!'
  })

  if (!result.isConfirmed) return

  try {
    await api.post(`/archive/memos/restore/${item.id}`)

    await Swal.fire({
      icon: 'success',
      title: 'Restored!',
      text: 'Memo has been restored successfully.',
      confirmButtonColor: '#4285F4',
      timer: 2000
    })

    fetchArchive()
  } catch (error) {
    console.error('Error restoring memo:', error)
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: error.response?.data?.message || 'Failed to restore memo',
      confirmButtonColor: '#4285F4'
    })
  }
}

const permanentlyDelete = async (item) => {
  const result = await Swal.fire({
    icon: 'error',
    title: 'Permanently Delete?',
    html: `
      <div class="text-left">
        <p class="mb-2">This will <strong>permanently delete</strong> this memo and cannot be undone.</p>
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
    await api.delete(`/archive/memos/force-delete/${item.id}`)

    await Swal.fire({
      icon: 'success',
      title: 'Deleted!',
      text: 'Memo has been permanently deleted.',
      confirmButtonColor: '#4285F4',
      timer: 2000
    })

    fetchArchive()
  } catch (error) {
    console.error('Error deleting memo:', error)
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: error.response?.data?.message || 'Failed to delete memo',
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
          <p class="text-sm text-base-content/60">Manage your archived memos</p>
        </div>
      </div>
      
      <div class="flex items-center gap-3">
        <div class="badge badge-lg badge-warning gap-1">
          <FileText :size="14" />
          {{ counts.memos }}
        </div>
      </div>
    </div>

    <!-- Search -->
    <div class="bg-base-100 rounded-xl border border-base-200 p-6 mb-6">
      <div class="flex flex-col md:flex-row gap-4 justify-between items-center">
        <div class="flex items-center gap-2 px-4 py-2 bg-base-200 rounded-lg text-sm font-semibold text-base-content/60">
            <Filter :size="14" />
            <span>Archived Memos Only</span>
        </div>

        <div class="flex items-center gap-3 w-full md:w-auto">
          <div class="relative flex-1 md:w-64">
            <Search :size="16" class="absolute left-3 top-1/2 -translate-y-1/2 text-base-content/40" />
            <input 
              v-model="searchQuery"
              @keyup.enter="handleSearch"
              type="text" 
              placeholder="Search memos..." 
              class="input input-sm input-bordered pl-9 w-full"
            />
            <button v-if="searchQuery" @click="clearSearch" class="absolute right-2 top-1/2 -translate-y-1/2 text-base-content/40 hover:text-base-content">
              ×
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Content -->
    <div v-if="loading" class="flex flex-col items-center justify-center py-20 bg-base-100 rounded-xl border border-base-200">
      <span class="loading loading-spinner loading-lg text-primary mb-4"></span>
      <p class="text-base-content/60 font-medium">Fetching archives...</p>
    </div>

    <div v-else-if="archivedItems.length === 0" class="empty-state bg-base-100 rounded-xl border border-base-200 p-16 text-center">
      <Archive :size="48" class="text-base-content/20 mx-auto mb-4" />
      <h3 class="text-xl font-semibold mb-2">No Archived Memos</h3>
      <p class="text-base-content/50 max-w-sm mx-auto text-sm">Memos you've deleted from your inbox will appear here.</p>
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
                <div class="w-8 h-8 rounded-lg flex items-center justify-center bg-orange-100 text-orange-600">
                  <FileText :size="16" />
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
            Showing <span class="font-bold">{{ archivedItems.length }}</span> of <span class="font-bold">{{ pagination.total }}</span> entries
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
      user-role="faculty"
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
