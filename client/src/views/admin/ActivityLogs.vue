<script setup>
import { ref, onMounted, computed } from 'vue'
import { Download, Search, Users, Shield, Calendar as CalendarIcon, FileText, Filter } from 'lucide-vue-next'
import api from '../../services/api'

const logs = ref([])
const loading = ref(true)
const pagination = ref({
  current_page: 1,
  last_page: 1,
  total: 0
})

const filters = ref({
  search: '',
  action_type: '',
  actor_role: '',
  start_date: '',
  end_date: ''
})

const actionTypes = [
  { value: '', label: 'All Actions' },
  { value: 'login_success', label: 'Login' },
  { value: 'login_failed', label: 'Failed Login' },
  { value: 'logout', label: 'Logout' },
  { value: 'google_login', label: 'Google Login' },
  { value: 'create_user', label: 'Create User' },
  { value: 'invite_user', label: 'Invite User' },
  { value: 'update_user', label: 'Update User' },
  { value: 'activate_user', label: 'Activate User' },
  { value: 'deactivate_user', label: 'Deactivate User' },
  { value: 'bulk_restore_users', label: 'Restore Users' },
  { value: 'create_memo', label: 'Create Memo' },
  { value: 'create_draft_memo', label: 'Create Draft Memo' },
  { value: 'update_memo', label: 'Update Memo' },
  { value: 'delete_memo', label: 'Delete Memo' },
  { value: 'acknowledge_memo', label: 'Acknowledge Memo' },
  { value: 'create_calendar_event', label: 'Create Event' },
  { value: 'update_calendar_event', label: 'Update Event' },
  { value: 'delete_calendar_event', label: 'Delete Event' },
  { value: 'respond_calendar_invitation', label: 'Respond to Invitation' }
]

const roles = [
  { value: '', label: 'All Roles' },
  { value: 'admin', label: 'Admin' },
  { value: 'secretary', label: 'Secretary' },
  { value: 'faculty', label: 'Faculty' }
]

const fetchLogs = async () => {
  loading.value = true
  try {
    const params = new URLSearchParams()
    if (filters.value.search) params.append('search', filters.value.search)
    if (filters.value.action_type) params.append('action_type', filters.value.action_type)
    if (filters.value.start_date) params.append('start_date', filters.value.start_date)
    if (filters.value.end_date) params.append('end_date', filters.value.end_date)
    params.append('page', pagination.value.current_page)

    const response = await api.get(`/activity-logs?${params.toString()}`)
    logs.value = response.data.data || []
    pagination.value = {
      current_page: response.data.current_page,
      last_page: response.data.last_page,
      total: response.data.total
    }
  } catch (error) {
    console.error('Error fetching logs:', error)
  } finally {
    loading.value = false
  }
}

const applyFilters = () => {
  pagination.value.current_page = 1
  fetchLogs()
}

const clearFilters = () => {
  filters.value = {
    search: '',
    action_type: '',
    actor_role: '',
    start_date: '',
    end_date: ''
  }
  pagination.value.current_page = 1
  fetchLogs()
}

const changePage = (page) => {
  if (page >= 1 && page <= pagination.value.last_page) {
    pagination.value.current_page = page
    fetchLogs()
  }
}

const formatDate = (dateStr) => {
  if (!dateStr) return '-'
  const date = new Date(dateStr)
  return date.toLocaleString('en-PH', {
    year: 'numeric',
    month: 'short',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const getActionBadgeClass = (action) => {
  if (action.includes('login') && !action.includes('failed')) return 'badge-success'
  if (action.includes('failed') || action.includes('delete')) return 'badge-error'
  if (action.includes('create') || action.includes('invite')) return 'badge-primary'
  if (action.includes('update') || action.includes('acknowledge')) return 'badge-info'
  if (action.includes('logout')) return 'badge-warning'
  return 'badge-ghost'
}

const getRoleIcon = (role) => {
  if (role === 'admin') return Shield
  return Users
}

const getRoleBadgeClass = (role) => {
  if (role === 'admin') return 'bg-purple-100 text-purple-700'
  if (role === 'secretary') return 'bg-blue-100 text-blue-700'
  return 'bg-gray-100 text-gray-700'
}

const getActionLabel = (action) => {
  return action.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())
}

const exportCSV = () => {
  const headers = ['Timestamp', 'Actor', 'Email', 'Role', 'Action', 'Description', 'Target', 'IP Address']
  const rows = logs.value.map(log => [
    formatDate(log.created_at),
    log.actor?.full_name || log.actor_email,
    log.actor_email,
    log.actor_role,
    log.action,
    log.description,
    log.target,
    log.ip_address || '-'
  ])

  const csvContent = [
    headers.join(','),
    ...rows.map(row => row.map(cell => `"${cell}"`).join(','))
  ].join('\n')

  const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' })
  const link = document.createElement('a')
  link.href = URL.createObjectURL(blob)
  link.download = `activity_logs_${new Date().toISOString().split('T')[0]}.csv`
  link.click()
}

onMounted(() => {
  fetchLogs()
})
</script>

<template>
  <div class="view-container">
    <div class="flex items-center justify-between mb-6">
      <div>
        <h1 class="text-2xl font-bold">Activity Logs</h1>
        <p class="text-base-content/60 text-sm">Track all user actions across the system</p>
      </div>
      <button @click="exportCSV" class="btn btn-primary text-white btn-sm gap-2">
        <Download :size="16" />
        Export CSV
      </button>
    </div>

    <div class="bg-base-100 rounded-xl border border-base-200 p-6 mb-6">
      <div class="flex items-center gap-2 mb-4">
        <Filter :size="16" class="text-base-content/60" />
        <span class="font-semibold text-sm">Filters</span>
      </div>
      
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
        <div class="form-control">
          <label class="label py-1">
            <span class="label-text text-xs font-semibold">Search</span>
          </label>
          <input 
            v-model="filters.search" 
            @keyup.enter="applyFilters"
            type="text" 
            placeholder="Search activities..." 
            class="input input-sm input-bordered w-full" 
          />
        </div>
        
        <div class="form-control">
          <label class="label py-1">
            <span class="label-text text-xs font-semibold">Action Type</span>
          </label>
          <select v-model="filters.action_type" class="select select-sm select-bordered w-full font-normal">
            <option v-for="action in actionTypes" :key="action.value" :value="action.value">
              {{ action.label }}
            </option>
          </select>
        </div>

        <div class="form-control">
          <label class="label py-1">
            <span class="label-text text-xs font-semibold">Start Date</span>
          </label>
          <input v-model="filters.start_date" type="date" class="input input-sm input-bordered w-full font-normal" />
        </div>

        <div class="form-control">
          <label class="label py-1">
            <span class="label-text text-xs font-semibold">End Date</span>
          </label>
          <input v-model="filters.end_date" type="date" class="input input-sm input-bordered w-full font-normal" />
        </div>
      </div>

      <div class="flex gap-2">
        <button @click="applyFilters" class="btn btn-primary btn-sm text-white px-6">
          Apply Filters
        </button>
        <button @click="clearFilters" class="btn btn-ghost btn-sm bg-base-200">
          Clear
        </button>
      </div>
    </div>

    <div v-if="loading" class="flex items-center justify-center py-20">
      <span class="loading loading-spinner loading-lg text-primary"></span>
    </div>

    <div v-else-if="logs.length === 0" class="bg-base-100 rounded-xl border border-base-200 p-12 text-center">
      <FileText :size="48" class="text-base-content/20 mx-auto mb-4" />
      <p class="text-base-content/60 font-medium">No activity logs found</p>
      <p class="text-base-content/40 text-sm">Try adjusting your filters</p>
    </div>

    <div v-else class="bg-base-100 rounded-xl border border-base-200 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="table w-full">
          <thead>
            <tr class="text-xs uppercase bg-base-100 border-b border-base-200 text-base-content/60">
              <th class="py-4 font-semibold pl-6">Timestamp</th>
              <th class="py-4 font-semibold">Actor</th>
              <th class="py-4 font-semibold">Action</th>
              <th class="py-4 font-semibold">Description</th>
              <th class="py-4 font-semibold pr-6">Target</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="log in logs" :key="log._id" class="hover:bg-slate-50/50 border-b border-base-100 last:border-0 text-sm">
              <td class="py-4 pl-6 text-base-content/60 font-mono text-xs whitespace-nowrap">
                {{ formatDate(log.created_at) }}
              </td>
              <td class="py-4">
                <div class="flex items-center gap-3">
                  <div class="avatar placeholder">
                    <div :class="getRoleBadgeClass(log.actor_role)" class="rounded-full w-8 h-8 flex items-center justify-center">
                      <component :is="getRoleIcon(log.actor_role)" :size="14" />
                    </div>
                  </div>
                  <div>
                    <div class="font-semibold text-xs">{{ log.actor?.full_name || 'Unknown' }}</div>
                    <div class="text-[10px] text-base-content/60">{{ log.actor_email }}</div>
                  </div>
                </div>
              </td>
              <td class="py-4">
                <span :class="['badge badge-sm border-none font-medium px-2.5 py-2 text-xs', getActionBadgeClass(log.action)]">
                  {{ getActionLabel(log.action) }}
                </span>
              </td>
              <td class="py-4 text-base-content/70 max-w-xs truncate">{{ log.description || '-' }}</td>
              <td class="py-4 pr-6 text-base-content/50 text-xs whitespace-nowrap">
                {{ log.target || '-' }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      
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
  </div>
</template>

<style scoped>
@reference "../../style.css";

.view-container {
  @apply p-0;
}
</style>
