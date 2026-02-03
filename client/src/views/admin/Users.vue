<script setup>
import { ref, onMounted, watch } from 'vue'
import { Plus, Pencil, Archive, X } from 'lucide-vue-next'
import api from '../../services/api'
import Swal from 'sweetalert2'

const activeFilter = ref('all')
const users = ref([])
const currentUser = ref(null)
const showAddUserModal = ref(false)
const isEditing = ref(false)
const editingUserId = ref(null)
const isLoading = ref(false)

// Form data
const formData = ref({
  name: '',
  email: '',
  department: '',
  role: '',
  is_active: true
})

const departments = [
  'Food Technology',
  'Automotive Technology',
  'Electronics Technology',
  'Information Technology/EMC'
]

const roles = [
  { value: 'admin', label: 'Admin' },
  { value: 'secretary', label: 'Secretary' },
  { value: 'faculty', label: 'Faculty' }
]

const filters = [
  { id: 'all', label: 'All' },
  { id: 'admin', label: 'Admin' },
  { id: 'secretary', label: 'Secretary' },
  { id: 'faculty', label: 'Faculty' }
]

const fetchUsers = async () => {
  try {
    const params = {
      status: 'active'
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

const fetchCurrentUser = async () => {
  try {
    const response = await api.get('/current-user')
    currentUser.value = response.data
  } catch (error) {
    console.error('Error fetching current user:', error)
  }
}

const archiveUser = async (user) => {
  // Prevent archiving self
  if (currentUser.value && user.id === currentUser.value.id) {
    Swal.fire({
      icon: 'error',
      title: 'Action Denied',
      text: 'You cannot perform this action on your own account.',
      confirmButtonColor: '#4285F4'
    })
    return
  }

  const isPending = user.display_status === 'pending'
  
  const result = await Swal.fire({
    icon: 'warning',
    title: isPending ? 'Cancel Invitation?' : 'Archive User?',
    text: isPending 
      ? `Are you sure you want to cancel the invitation for ${user.email}? This will delete their pending account.`
      : `Are you sure you want to archive ${user.name}? They will be disabled from logging in.`,
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3085d6',
    confirmButtonText: isPending ? 'Yes, cancel invitation' : 'Yes, archive user!'
  })

  if (!result.isConfirmed) return

  try {
    if (isPending) {
        await api.delete(`/users/${user.id}`)
    } else {
        await api.patch(`/users/${user.id}/toggle-active`)
    }
    
    Swal.fire({
      icon: 'success',
      title: isPending ? 'Invitation Cancelled!' : 'User Archived!',
      text: isPending 
        ? `The invitation for ${user.email} has been cancelled.`
        : `${user.name} has been moved to the archive.`,
      confirmButtonColor: '#4285F4',
      timer: 2000
    })
    
    fetchUsers()
  } catch (error) {
    console.error('Error updating user:', error)
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: error.response?.data?.message || 'Failed to update user',
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

const getStatusBadge = (status) => {
  switch (status) {
    case 'active':
      return 'badge-success text-white'
    case 'pending':
      return 'badge-warning text-white'
    case 'archived':
      return 'badge-error text-white'
    default:
      return 'badge-ghost'
  }
}

const validateEmail = (email) => {
  const regex = /^[\w\.\-]+@(student\.)?buksu\.edu\.ph$/
  return regex.test(email)
}

const resetForm = () => {
  formData.value = {
    name: '',
    email: '',
    department: '',
    role: '',
    is_active: true
  }
}

const openModal = (user = null) => {
  if (user) {
    isEditing.value = true
    editingUserId.value = user.id
    formData.value = {
      name: user.name,
      email: user.email,
      department: user.department,
      role: user.role,
      is_active: user.is_active
    }
  } else {
    isEditing.value = false
    editingUserId.value = null
    resetForm()
  }
  showAddUserModal.value = true
}

const closeModal = () => {
  showAddUserModal.value = false
  isEditing.value = false
  editingUserId.value = null
  resetForm()
}

const saveUser = async () => {
  if (!validateEmail(formData.value.email)) {
    Swal.fire({
      icon: 'error',
      title: 'Invalid Email',
      text: 'Only @buksu.edu.ph and @student.buksu.edu.ph email addresses are allowed.',
      confirmButtonColor: '#4285F4'
    })
    return
  }

  if (!formData.value.name || !formData.value.email || !formData.value.department || !formData.value.role) {
    Swal.fire({
      icon: 'warning',
      title: 'Incomplete Form',
      text: 'Please fill in all fields',
      confirmButtonColor: '#4285F4'
    })
    return
  }

  isLoading.value = true

  try {
    if (isEditing.value) {
      await api.put(`/users/${editingUserId.value}`, formData.value)
      
      await Swal.fire({
        icon: 'success',
        title: 'User Updated!',
        text: `${formData.value.name} has been updated successfully.`,
        confirmButtonColor: '#4285F4',
        timer: 2000
      })
    } else {
      await api.post('/users/invite', formData.value)

      await Swal.fire({
        icon: 'success',
        title: 'Invitation Sent!',
        text: `An invitation has been sent to ${formData.value.email}`,
        confirmButtonColor: '#4285F4'
      })
    }

    closeModal()
    fetchUsers()

  } catch (error) {
    console.error('Error saving user:', error)
    const errorMessage = error.response?.data?.message || 'Failed to save user'
    
    Swal.fire({
      icon: 'error',
      title: 'Error',
      text: errorMessage,
      confirmButtonColor: '#4285F4'
    })
  } finally {
    isLoading.value = false
  }
}

onMounted(() => {
  fetchCurrentUser()
  fetchUsers()
})
</script>

<template>
  <div class="view-container">
    <!-- Header -->
    <div class="flex items-center justify-between mb-8">
      <h1 class="text-2xl font-bold">Manage Users</h1>
      <button @click="openModal()" class="btn btn-primary gap-2 text-white">
        <Plus :size="20" />
        Add user
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
              <th class="py-4 font-semibold">Status</th>
              <th class="py-4 font-semibold pr-6 text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="users.length === 0">
              <td colspan="4" class="text-center py-8 text-base-content/60">
                No active users found.
              </td>
            </tr>
            <tr v-for="user in users" :key="user.id" class="hover:bg-slate-50/50 border-b border-base-100 last:border-0">
              <td class="py-4 pl-6">
                <div class="flex items-center gap-3">
                  <div class="avatar placeholder">
                    <div class="bg-primary text-primary-content rounded-full w-10">
                      <span class="text-xs">{{ user.name?.charAt(0) }}</span>
                    </div>
                  </div>
                  <div>
                    <div class="font-bold">{{ user.name }}</div>
                    <div class="text-xs text-base-content/60">{{ user.email }}</div>
                  </div>
                </div>
              </td>
              <td class="py-4 text-base-content/80">{{ user.department }}</td>
              <td class="py-4">
                <span class="badge border-none py-3 px-4 font-medium" :class="user.roleColor">
                  {{ user.role }}
                </span>
              </td>
              <td class="py-4">
                <span class="badge badge-sm font-semibold capitalize" :class="getStatusBadge(user.display_status)">
                  {{ user.display_status }}
                </span>
              </td>
              <td class="py-4 pr-6">
                <div class="flex items-center justify-end gap-2">
                  <button 
                    @click="openModal(user)"
                    class="btn btn-ghost btn-sm btn-square text-primary bg-blue-50 hover:bg-blue-100"
                    title="Edit user"
                  >
                    <Pencil :size="16" />
                  </button>
                  <button 
                    v-if="!(currentUser && user.id === currentUser.id)"
                    @click="archiveUser(user)"
                    class="btn btn-ghost btn-sm btn-square text-orange-500 bg-orange-50 hover:bg-orange-100"
                    title="Archive user"
                  >
                    <Archive :size="16" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Add User Modal -->
    <div v-if="showAddUserModal" class="modal modal-open">
      <div class="modal-box max-w-md">
        <!-- Modal Header -->
        <div class="flex items-center justify-between mb-6">
          <h3 class="font-bold text-xl">{{ isEditing ? 'Edit User' : 'Add New User' }}</h3>
          <button @click="closeModal" class="btn btn-sm btn-circle btn-ghost">
            <X :size="20" />
          </button>
        </div>

        <!-- Form -->
        <div class="space-y-4">
          <!-- Name -->
          <div class="form-control">
            <label class="label">
              <span class="label-text font-medium">Name <span class="text-error">*</span></span>
            </label>
            <input 
              v-model="formData.name"
              type="text" 
              placeholder="Enter full name" 
              class="input input-bordered w-full"
            />
          </div>

          <!-- Email -->
          <div class="form-control">
            <label class="label">
              <span class="label-text font-medium">Email <span class="text-error">*</span></span>
            </label>
            <input 
              v-model="formData.email"
              type="email" 
              placeholder="user@buksu.edu.ph" 
              class="input input-bordered w-full"
            />
            <label class="label">
              <span class="label-text-alt text-base-content/60">Only @buksu.edu.ph and @student.buksu.edu.ph allowed</span>
            </label>
          </div>

          <!-- Department -->
          <div class="form-control">
            <label class="label">
              <span class="label-text font-medium">Department <span class="text-error">*</span></span>
            </label>
            <select v-model="formData.department" class="select select-bordered w-full">
              <option value="" disabled>Select department</option>
              <option v-for="dept in departments" :key="dept" :value="dept">
                {{ dept }}
              </option>
            </select>
          </div>

          <!-- Role -->
          <div class="form-control">
            <label class="label">
              <span class="label-text font-medium">Role <span class="text-error">*</span></span>
            </label>
            <select v-model="formData.role" class="select select-bordered w-full">
              <option value="" disabled>Select role</option>
              <option v-for="role in roles" :key="role.value" :value="role.value">
                {{ role.label }}
              </option>
            </select>
          </div>

          <!-- Status (Only for Editing) -->
          <div v-if="isEditing" class="form-control">
            <label class="label">
              <span class="label-text font-medium">Account Status</span>
            </label>
            <div class="flex items-center gap-4 bg-slate-50/50 p-3 rounded-lg border border-base-200">
                <span class="text-sm" :class="formData.is_active ? 'text-success font-bold' : 'text-error font-bold'">
                    {{ formData.is_active ? 'Active' : 'Inactive' }}
                </span>
                <input 
                    type="checkbox" 
                    v-model="formData.is_active" 
                    class="toggle toggle-primary" 
                />
            </div>
          </div>
        </div>

        <!-- Modal Actions -->
        <div class="modal-action mt-6">
          <button @click="closeModal" class="btn btn-ghost">Cancel</button>
          <button 
            @click="saveUser" 
            class="btn btn-primary text-white"
            :disabled="isLoading"
          >
            <span v-if="isLoading" class="loading loading-spinner loading-sm"></span>
            <span v-else>{{ isEditing ? 'Save Changes' : 'Send Invitation' }}</span>
          </button>
        </div>
      </div>
      <div class="modal-backdrop" @click="closeModal"></div>
    </div>
  </div>
</template>

<style scoped>
@reference "../../style.css";

.view-container {
  @apply p-0;
}

.modal-backdrop {
  @apply bg-black/50;
}
</style>
