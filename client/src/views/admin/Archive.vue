<script setup>
import { ref, onMounted, watch } from 'vue'
import { Archive, RotateCcw } from 'lucide-vue-next'
import api from '../../services/api'
import Swal from 'sweetalert2'

const activeFilter = ref('all')
const users = ref([])

const filters = [
  { id: 'all', label: 'All' },
  { id: 'admin', label: 'Admin' },
  { id: 'secretary', label: 'Secretary' },
  { id: 'faculty', label: 'Faculty' }
]

const fetchUsers = async () => {
  try {
    const params = {
      status: 'archived'
    }
    
    if (activeFilter.value !== 'all') {
      params.role = activeFilter.value
    }

    const response = await api.get('/users', { params })
    const usersData = response.data.data || response.data
    
    // Transform backend data
    users.value = usersData.map(user => ({
      ...user,
      name: user.name || `${user.first_name || ''} ${user.last_name || ''}`.trim(),
      roleColor: getRoleColor(user.role)
    }))
  } catch (error) {
    console.error('Error fetching users:', error)
  }
}

// Watch filters to trigger fetch
watch(activeFilter, () => {
  fetchUsers()
})

const restoreUser = async (user) => {
  const result = await Swal.fire({
    icon: 'warning',
    title: 'Restore User?',
    text: `Are you sure you want to restore ${user.name}? They will be able to log in again.`,
    showCancelButton: true,
    confirmButtonColor: '#4285F4',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Yes, restore user!'
  })

  if (!result.isConfirmed) return

  try {
    await api.patch(`/users/${user.id}/toggle-active`)
    
    Swal.fire({
      icon: 'success',
      title: 'User Restored!',
      text: `${user.name} has been restored successfully.`,
      confirmButtonColor: '#4285F4',
      timer: 2000
    })
    
    fetchUsers() // Refresh list (user should disappear)
  } catch (error) {
    console.error('Error restoring user:', error)
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: error.response?.data?.message || 'Failed to restore user',
      confirmButtonColor: '#4285F4'
    })
  }
}

const restoreAll = async () => {
  if (users.value.length === 0) return

  const result = await Swal.fire({
    icon: 'warning',
    title: 'Restore All Users?',
    text: `Are you sure you want to restore all archived users?`,
    showCancelButton: true,
    confirmButtonColor: '#4285F4',
    cancelButtonColor: '#d33',
    confirmButtonText: 'Yes, restore all!'
  })

  if (!result.isConfirmed) return

  try {
    const response = await api.post('/users/restore-all')
    
    Swal.fire({
      icon: 'success',
      title: 'Users Restored!',
      text: response.data.message,
      confirmButtonColor: '#4285F4',
      timer: 2000
    })
    
    fetchUsers()
  } catch (error) {
    console.error('Error restoring users:', error)
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: error.response?.data?.message || 'Failed to restore users',
      confirmButtonColor: '#4285F4'
    })
  }
}

const getRoleColor = (role) => {
  const colors = {
    'admin': 'bg-primary/10 text-primary',
    'secretary': 'bg-info/10 text-info',
    'faculty': 'bg-secondary/10 text-secondary'
  }
  return colors[role] || 'bg-base-300 text-base-content'
}

onMounted(() => {
  fetchUsers()
})
</script>

<template>
  <div class="view-container">
    <!-- Header -->
    <div class="flex items-center justify-between mb-8">
      <div class="flex items-center gap-2">
        <Archive :size="24" class="text-primary" />
        <h1 class="text-2xl font-bold">Archived Users</h1>
      </div>
      
      <!-- Restore All Button -->
      <button 
        v-if="users.length > 0"
        @click="restoreAll" 
        class="btn btn-success gap-2 text-white"
      >
        <RotateCcw :size="20" />
        Restore All
      </button>
    </div>

    <!-- Filters -->
    <div class="flex gap-2 mb-6">
      <button 
        v-for="filter in filters" 
        :key="filter.id"
        @click="activeFilter = filter.id"
        class="btn btn-sm"
        :class="activeFilter === filter.id ? 'btn-primary text-white' : 'btn-ghost bg-base-100 border border-base-300'"
      >
        {{ filter.label }}
      </button>
    </div>

    <!-- Users Table -->
    <div class="bg-base-100 rounded-xl border border-base-300 overflow-hidden shadow-sm">
      <div class="overflow-x-auto">
        <table class="table w-full">
          <thead>
            <tr class="bg-base-100 border-b border-base-200 text-base-content/60">
              <th class="py-4 font-semibold pl-6">Name</th>
              <th class="py-4 font-semibold">Department</th>
              <th class="py-4 font-semibold">Role</th>
              <th class="py-4 font-semibold pr-6 text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="users.length === 0">
              <td colspan="4" class="text-center py-12">
                <div class="flex flex-col items-center justify-center opacity-50">
                  <Archive :size="48" class="mb-2" />
                  <p>No archived users found.</p>
                </div>
              </td>
            </tr>
            <tr v-for="user in users" :key="user.id" class="hover:bg-slate-50/50 border-b border-base-100 last:border-0">
              <td class="py-4 pl-6">
                <div class="flex items-center gap-3">
                  <div class="opacity-70 avatar placeholder">
                    <div class="bg-neutral text-neutral-content rounded-full w-10">
                      <span class="text-xs">{{ user.name?.charAt(0) }}</span>
                    </div>
                  </div>
                  <div>
                    <div class="font-bold opacity-70">{{ user.name }}</div>
                    <div class="text-xs text-base-content/60">{{ user.email }}</div>
                  </div>
                </div>
              </td>
              <td class="py-4 text-base-content/80 opacity-70">{{ user.department }}</td>
              <td class="py-4">
                <span class="badge border-none py-3 px-4 font-medium opacity-70" :class="user.roleColor">
                  {{ user.role }}
                </span>
              </td>
              <td class="py-4 pr-6">
                <div class="flex items-center justify-end gap-2">
                  <button 
                    @click="restoreUser(user)"
                    class="btn btn-ghost btn-sm btn-square text-success bg-success/10 hover:bg-success/20"
                    title="Restore user"
                  >
                    <RotateCcw :size="16" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
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
