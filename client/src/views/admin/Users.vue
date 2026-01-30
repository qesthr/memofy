<script setup>
import { ref, onMounted } from 'vue'
import { Plus, Search, Pencil, Archive, Filter, X } from 'lucide-vue-next'
import api from '../../services/api'
import Swal from 'sweetalert2'

const activeTab = ref('active')
const activeFilter = ref('all')
const users = ref([])
const showAddUserModal = ref(false)
const isLoading = ref(false)

// Form data
const formData = ref({
  name: '',
  email: '',
  department: '',
  role: ''
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

const tabs = [
  { id: 'active', label: 'Active Users' },
  { id: 'archived', label: 'Archived Users (0)' }
]

const filters = [
  { id: 'all', label: 'All', count: 0 },
  { id: 'admin', label: 'Admin', count: 0 },
  { id: 'secretary', label: 'Secretary', count: 0 },
  { id: 'faculty', label: 'Faculty', count: 0 }
]

const fetchUsers = async () => {
  try {
    const response = await api.get('/admin/users')
    users.value = response.data.data
  } catch (error) {
    console.error('Error fetching users:', error)
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
    role: ''
  }
}

const openModal = () => {
  resetForm()
  showAddUserModal.value = true
}

const closeModal = () => {
  showAddUserModal.value = false
  resetForm()
}

const sendInvitation = async () => {
  // Validate email domain
  if (!validateEmail(formData.value.email)) {
    Swal.fire({
      icon: 'error',
      title: 'Invalid Email',
      text: 'Only @buksu.edu.ph and @student.buksu.edu.ph email addresses are allowed.',
      confirmButtonColor: '#4285F4'
    })
    return
  }

  // Validate all fields
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
    const response = await api.post('/admin/invite-user', formData.value)

    // Success
    await Swal.fire({
      icon: 'success',
      title: 'Invitation Sent!',
      text: `An invitation has been sent to ${formData.value.email}`,
      confirmButtonColor: '#4285F4'
    })

    closeModal()
    fetchUsers() // Refresh user list

  } catch (error) {
    console.error('Error sending invitation:', error)
    const errorMessage = error.response?.data?.message || error.response?.data?.errors?.email?.[0] || 'Failed to send invitation'
    
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
  fetchUsers()
})
</script>

<template>
  <div class="view-container">
    <!-- Header -->
    <div class="flex items-center justify-between mb-8">
      <h1 class="text-2xl font-bold">Manage Users</h1>
      <button @click="openModal" class="btn btn-primary gap-2 text-white">
        <Plus :size="20" />
        Add user
      </button>
    </div>

    <!-- Tabs -->
    <div class="border-b border-base-300 mb-6">
      <div class="flex gap-8">
        <button 
          v-for="tab in tabs" 
          :key="tab.id"
          @click="activeTab = tab.id"
          class="pb-3 text-sm font-medium transition-colors relative"
          :class="activeTab === tab.id ? 'text-primary' : 'text-base-content/60 hover:text-base-content'"
        >
          {{ tab.label }}
          <div 
            v-if="activeTab === tab.id"
            class="absolute bottom-0 left-0 w-full h-0.5 bg-primary"
          ></div>
        </button>
      </div>
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
        <span class="ml-1 opacity-70">{{ filter.count }}</span>
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
            <tr v-for="user in users" :key="user.id" class="hover:bg-base-50/50 border-b border-base-100 last:border-0">
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
              <td class="py-4 pr-6">
                <div class="flex items-center justify-end gap-2">
                  <button class="btn btn-ghost btn-sm btn-square text-primary bg-blue-50 hover:bg-blue-100">
                    <Pencil :size="16" />
                  </button>
                  <button class="btn btn-ghost btn-sm btn-square text-orange-500 bg-orange-50 hover:bg-orange-100">
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
          <h3 class="font-bold text-xl">Add New User</h3>
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
        </div>

        <!-- Modal Actions -->
        <div class="modal-action mt-6">
          <button @click="closeModal" class="btn btn-ghost">Cancel</button>
          <button 
            @click="sendInvitation" 
            class="btn btn-primary text-white"
            :disabled="isLoading"
          >
            <span v-if="isLoading" class="loading loading-spinner loading-sm"></span>
            <span v-else>Send Invitation</span>
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
