<script setup>
import { ref, onMounted, onUnmounted, watch, computed } from 'vue'
import { Plus, Pencil, Archive, X, Lock, Unlock, Clock, Users, User, Mail, Building2, Shield, RefreshCw, Copy, Eye, EyeOff } from 'lucide-vue-next'
import api from '../../services/api'
import Swal from 'sweetalert2'

const activeFilter = ref('all')
const users = ref([])
const currentUser = ref(null)
const showAddUserModal = ref(false)
const isEditing = ref(false)
const editingUserId = ref(null)
const isLoading = ref(false)
const isCheckingLock = ref(false)
const lockTooltipVisible = ref(false)
const hoveredUserId = ref(null)
const currentSessionExpiresAt = ref(null)

const lockSettings = ref({
  minutes: 1,
  seconds: 50
})

const allowedDomains = ref(['buksu.edu.ph', 'student.buksu.edu.ph']) // Default fallback

const userLocks = ref({})
let heartbeatInterval = null
let lockCheckInterval = null

const HEARTBEAT_INTERVAL = 30000
const LOCK_CHECK_INTERVAL = 1000

// Pagination state
const pagination = ref({
  current_page: 1,
  last_page: 1,
  per_page: 50,
  total: 0
})

// Smart pagination visible pages
const visiblePages = computed(() => {
  const current = pagination.value.current_page
  const last = pagination.value.last_page
  const delta = 2
  const pages = []
  
  if (last <= 7) {
    for (let i = 1; i <= last; i++) pages.push(i)
  } else {
    pages.push(1)
    const start = Math.max(2, current - delta)
    const end = Math.min(last - 1, current + delta)
    if (start > 2) pages.push('...')
    for (let i = start; i <= end; i++) pages.push(i)
    if (end < last - 1) pages.push('...')
    if (last > 1) pages.push(last)
  }
  return pages
})

const formData = ref({
  name: '',
  email: '',
  department: '',
  role: '',
  is_active: true,
  password: ''
})

const generatedPassword = ref('')
const showPassword = ref(false)

// Generate random password
const generatePassword = () => {
  const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789!@#$%^&*()_+-=[]{}|;:,.<>?'
  let password = ''
  for (let i = 0; i < 12; i++) {
    password += chars.charAt(Math.floor(Math.random() * chars.length))
  }
  generatedPassword.value = password
  formData.value.password = password
}

// Copy password to clipboard
const copyPassword = async () => {
  try {
    await navigator.clipboard.writeText(generatedPassword.value)
    await Swal.fire({
      icon: 'success',
      title: 'Copied!',
      text: 'Password copied to clipboard',
      timer: 1500,
      showConfirmButton: false
    })
  } catch (err) {
    console.error('Failed to copy:', err)
  }
}

// Initialize with generated password
onMounted(() => {
  generatePassword()
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
      status: 'active',
      page: pagination.value.current_page,
      per_page: pagination.value.per_page
    }
    if (activeFilter.value !== 'all') {
      params.role = activeFilter.value
    }

    const response = await api.get('/users', { params })
    const usersData = response.data.data || response.data
    
    users.value = usersData.map(user => ({
      ...user,
      name: user.name || `${user.first_name || ''} ${user.last_name || ''}`.trim(),
      roleColor: getRoleColor(user.role)
    }))
    
    // Update pagination from response
    if (response.data.current_page) {
      pagination.value = {
        current_page: response.data.current_page || 1,
        last_page: response.data.last_page || 1,
        per_page: response.data.per_page || 50,
        total: response.data.total || 0
      }
    }
  } catch (error) {
    console.error('Error fetching users:', error)
  }
}

const changePage = (page) => {
  if (page >= 1 && page <= pagination.value.last_page) {
    pagination.value.current_page = page
    fetchUsers()
  }
}

watch(activeFilter, () => {
  pagination.value.current_page = 1
  fetchUsers()
})

const fetchLockSettings = async () => {
  try {
    const response = await api.get('/locks/settings')
    lockSettings.value = response.data.lock_duration
  } catch (error) {
    console.error('Error fetching lock settings:', error)
    lockSettings.value = { minutes: 1, seconds: 50 }
  }
}

const fetchSystemSettings = async () => {
  try {
    const response = await api.get('/system-settings')
    if (response.data.allowed_email_domains && response.data.allowed_email_domains.length > 0) {
      allowedDomains.value = response.data.allowed_email_domains
    }
  } catch (error) {
    console.error('Error fetching system settings:', error)
  }
}

const checkAllLocks = async () => {
  try {
    const response = await api.get('/locks/all-locks')
    const locks = response.data.locks || []
    
    userLocks.value = {}
    locks.forEach(lock => {
      if (lock.resource_type === 'user') {
        userLocks.value[lock.resource_id] = {
          locked: true,
          locked_by: lock.locked_by,
          expires_at: new Date(lock.expires_at),
          seconds_remaining: lock.seconds_remaining
        }
      }
    })
  } catch (error) {
    console.error('Error checking locks:', error)
  }
}

const fetchCurrentUser = async () => {
  try {
    const response = await api.get('/current-user')
    currentUser.value = response.data
  } catch (error) {
    console.error('Error fetching current user:', error)
  }
}

watch(activeFilter, () => {
  fetchUsers()
})

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
  if (!email) return false
  const domain = email.split('@')[1]
  if (!domain) return false
  return allowedDomains.value.includes(domain)
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

const computeSecondsRemaining = (expiresAt) => {
  if (!expiresAt) return 0
  const expires = new Date(expiresAt)
  const now = new Date()
  const remaining = Math.floor((expires - now) / 1000)
  return remaining > 0 ? remaining : 0
}

const formatSecondsRemaining = (expiresAt) => {
  const seconds = computeSecondsRemaining(expiresAt)
  if (seconds <= 0) return '00:00'
  const mins = Math.floor(seconds / 60)
  const secs = seconds % 60
  return `${String(mins).padStart(2, '0')}:${String(secs).padStart(2, '0')}`
}

const formatLockDuration = () => {
  const mins = String(lockSettings.value.minutes || 0).padStart(2, '0')
  const secs = String(lockSettings.value.seconds || 0).padStart(2, '0')
  return `${mins}:${secs}`
}

const isUserLocked = (userId) => {
  const lock = userLocks.value[userId]
  if (!lock) return false
  const seconds = computeSecondsRemaining(lock.expires_at)
  return lock.locked && seconds > 0
}

const isLockedByMe = (userId) => {
  const lock = userLocks.value[userId]
  if (!lock) return false
  const seconds = computeSecondsRemaining(lock.expires_at)
  return lock.locked_by?.id === currentUser.value?.id && seconds > 0
}

const getLockInfo = (userId) => {
  return userLocks.value[userId]
}

const tryAcquireLock = async (userId) => {
  try {
    const response = await api.post('/locks/acquire', {
      resource_type: 'user',
      resource_id: userId
    })
    return response.data
  } catch (error) {
    console.error('Error acquiring lock:', error)
    return { success: false, message: 'Failed to acquire lock' }
  }
}

const releaseLock = async (userId) => {
  try {
    await api.post('/locks/release', {
      resource_type: 'user',
      resource_id: userId
    })
  } catch (error) {
    console.error('Error releasing lock:', error)
  }
}

const heartbeat = async () => {
  if (!editingUserId.value) return
  
  try {
    const response = await api.post('/locks/heartbeat', {
      resource_type: 'user',
      resource_id: editingUserId.value
    })
    
    if (response.data.success) {
      currentSessionExpiresAt.value = new Date(response.data.expires_at)
    }
  } catch (error) {
    console.error('Heartbeat failed:', error)
  }
}

const openModal = (user = null) => {
  if (user && user.id) {
    openEditModal(user)
  } else {
    openAddUserModal()
  }
}

const openAddUserModal = () => {
  isEditing.value = false
  editingUserId.value = null
  resetForm()
  showAddUserModal.value = true
}

const openEditModal = async (user) => {
  isCheckingLock.value = true
  
  try {
    const result = await tryAcquireLock(user.id)
    
    if (!result.success) {
      if (result.locked) {
        await Swal.fire({
          icon: 'warning',
          title: 'User Currently Being Edited',
          html: `
            <div class="text-left">
              <p class="mb-2">This user is currently being edited by another administrator:</p>
              <div class="bg-base-200 p-3 rounded-lg">
                <p><strong>Name:</strong> ${result.locked_by?.name || 'Unknown'}</p>
                <p><strong>Email:</strong> ${result.locked_by?.email || 'Unknown'}</p>
              </div>
              <p class="mt-3 text-sm text-base-content/60">
                Please wait until the lock expires or contact the other administrator.
              </p>
            </div>
          `,
          confirmButtonText: 'OK',
          confirmButtonColor: '#4285F4'
        })
      } else {
        await Swal.fire({
          icon: 'error',
          title: 'Error',
          text: result.message || 'Failed to acquire lock',
          confirmButtonColor: '#4285F4'
        })
      }
      isCheckingLock.value = false
      return
    }

    editingUserId.value = user.id
    isEditing.value = true
    formData.value = {
      name: user.name,
      email: user.email,
      department: user.department,
      role: user.role,
      is_active: user.is_active
    }
    currentSessionExpiresAt.value = new Date(result.expires_at)
    showAddUserModal.value = true
    
    heartbeatInterval = setInterval(heartbeat, HEARTBEAT_INTERVAL)
    
  } catch (error) {
    console.error('Error in openEditModal:', error)
    await Swal.fire({
      icon: 'error',
      title: 'Error',
      text: 'An unexpected error occurred',
      confirmButtonColor: '#4285F4'
    })
  } finally {
    isCheckingLock.value = false
  }
}

const closeModal = async () => {
  if (editingUserId.value) {
    await releaseLock(editingUserId.value)
    editingUserId.value = null
  }
  
  if (heartbeatInterval) {
    clearInterval(heartbeatInterval)
    heartbeatInterval = null
  }
  
  currentSessionExpiresAt.value = null
  showAddUserModal.value = false
  isEditing.value = false
  resetForm()
}

const checkLockExpiration = () => {
  if (!showAddUserModal.value || !currentSessionExpiresAt.value || !isEditing.value) return
  
  const seconds = computeSecondsRemaining(currentSessionExpiresAt.value)
  
  if (seconds <= 0) {
    closeModal()
    Swal.fire({
      icon: 'warning',
      title: 'Session Ended',
      text: 'Your edit session has ended due to inactivity. Please try again.',
      confirmButtonColor: '#4285F4',
      timer: 4000,
      timerProgressBar: true
    })
  }
}

const saveUser = async () => {
  if (!validateEmail(formData.value.email)) {
    Swal.fire({
      icon: 'error',
      icon: 'error',
      title: 'Invalid Email',
      text: `Only emails from the following domains are accepted: ${allowedDomains.value.map(d => '@' + d).join(', ')}`,
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
      await api.post('/users/invite', {
        ...formData.value,
        password: generatedPassword.value
      })

      await Swal.fire({
        icon: 'success',
        title: 'User Created!',
        text: `User account for ${formData.value.email} has been created and credentials have been sent via email.`,
        confirmButtonColor: '#4285F4'
      })
    }

    await closeModal()
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

const archiveUser = async (user) => {
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

const onMouseEnterEdit = (user) => {
  if (isUserLocked(user.id) && !isLockedByMe(user.id)) {
    hoveredUserId.value = user.id
    lockTooltipVisible.value = true
  }
}

const onMouseLeaveEdit = () => {
  hoveredUserId.value = null
  lockTooltipVisible.value = false
}

onMounted(() => {
  fetchCurrentUser()
  fetchUsers()
  fetchLockSettings()
  fetchSystemSettings()
  
  lockCheckInterval = setInterval(() => {
    checkAllLocks()
    checkLockExpiration()
  }, LOCK_CHECK_INTERVAL)
})

onUnmounted(() => {
  if (heartbeatInterval) {
    clearInterval(heartbeatInterval)
  }
  if (lockCheckInterval) {
    clearInterval(lockCheckInterval)
  }
})
</script>

<template>
  <div class="view-container">
    <div class="flex items-center justify-between mb-8">
      <h1 class="text-2xl font-bold">Manage Users</h1>
      <button @click="openModal()" class="btn btn-primary gap-2 text-white">
        <Plus :size="20" />
        Add user
      </button>
    </div>

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
              <td colspan="5" class="text-center py-8 text-base-content/60">
                No active users found.
              </td>
            </tr>
            <tr v-for="user in users" :key="user.id" class="hover:bg-slate-50/50 border-b border-base-100 last:border-0 relative">
              <td class="py-4 pl-6">
                <div class="flex items-center gap-3">
                  <div class="avatar">
                    <div class="w-8 h-8 rounded-full bg-primary/10 text-primary flex items-center justify-center font-bold text-xs uppercase shrink-0">
                      <img v-if="user.profile_picture" :src="user.profile_picture" :alt="user.name" class="w-full h-full object-cover" />
                      <span v-else class="text-xs">{{ user.name?.charAt(0) }}</span>
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
                  <div class="relative">
                    <button 
                      @click="openEditModal(user)"
                      class="btn btn-ghost btn-sm btn-square"
                      :class="[
                        isUserLocked(user.id) && !isLockedByMe(user.id)
                          ? 'opacity-50 cursor-not-allowed bg-gray-100'
                          : 'text-primary bg-blue-50 hover:bg-blue-100'
                      ]"
                      title="Edit user"
                      :disabled="isCheckingLock"
                      @mouseenter="onMouseEnterEdit(user)"
                      @mouseleave="onMouseLeaveEdit"
                    >
                      <Pencil v-if="!isCheckingLock" :size="16" />
                      <span v-else class="loading loading-spinner loading-sm"></span>
                    </button>
                    
                    <div 
                      v-if="isUserLocked(user.id) && !isLockedByMe(user.id) && lockTooltipVisible && hoveredUserId === user.id"
                      class="absolute z-50 bottom-full left-1/2 transform -translate-x-1/2 mb-2 w-64 bg-gray-900 text-white text-xs rounded-lg shadow-lg p-3"
                    >
                      <div class="flex items-start gap-2">
                        <Lock class="text-yellow-400 mt-0.5 shrink-0" :size="14" />
                        <div>
                          <p class="font-semibold mb-1">Currently Being Edited</p>
                          <p class="text-gray-300">By: {{ getLockInfo(user.id)?.locked_by?.name || 'Unknown' }}</p>
                          <p class="text-gray-300">Email: {{ getLockInfo(user.id)?.locked_by?.email || 'Unknown' }}</p>
                          <p class="text-yellow-400 mt-1">Expires: {{ formatSecondsRemaining(getLockInfo(user.id)?.expires_at) }}</p>
                        </div>
                      </div>
                      <div class="absolute top-full left-1/2 transform -translate-x-1/2 border-4 border-transparent border-t-gray-900"></div>
                    </div>
                  </div>
                  
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

    <!-- Pagination -->
    <div v-if="pagination.last_page > 1" class="mt-4 flex items-center justify-between">
      <span class="text-sm text-base-content/60">
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
          v-for="page in visiblePages" 
          :key="page"
          @click="page !== '...' && changePage(page)"
          :class="['join-item btn btn-xs', pagination.current_page === page ? 'btn-active' : 'btn-ghost', page === '...' ? 'disabled:cursor-default' : '']"
          :disabled="page === '...'"
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

    <div v-if="showAddUserModal" class="modal modal-open">
      <div class="modal-box max-w-lg p-0 overflow-hidden">
        <!-- Modal Header with decorative background -->
        <div class="bg-gradient-to-r from-primary via-primary/90 to-blue-600 p-6 text-primary-content relative overflow-hidden">
          <!-- Decorative circles -->
          <div class="absolute -top-10 -right-10 w-32 h-32 bg-white/10 rounded-full"></div>
          <div class="absolute -bottom-10 -left-10 w-24 h-24 bg-white/10 rounded-full"></div>
          
          <div class="relative flex items-center gap-4">
            <div class="w-14 h-14 bg-white/20 rounded-xl flex items-center justify-center backdrop-blur-sm">
              <User v-if="!isEditing" :size="28" />
              <Pencil v-else :size="28" />
            </div>
            <div>
              <h3 class="font-bold text-2xl">{{ isEditing ? 'Edit User' : 'Add New User' }}</h3>
              <p class="text-white/80 text-sm">{{ isEditing ? 'Update user information below' : 'Fill in the details to invite a new user' }}</p>
            </div>
          </div>
          <button @click="closeModal" class="absolute top-4 right-4 btn btn-ghost btn-sm btn-circle text-white hover:bg-white/20">
            <X :size="20" />
          </button>
        </div>

        <div v-if="isEditing" class="bg-warning/10 border-b border-warning/20 p-4">
          <div class="flex items-center gap-3 text-warning">
            <Clock :size="18" />
            <span class="font-medium">Edit Session Active</span>
          </div>
          <p class="text-sm text-base-content/70 mt-1 ml-7">
            Session expires in: <span class="font-mono font-bold">{{ formatSecondsRemaining(currentSessionExpiresAt) }}</span>
          </p>
        </div>

        <!-- Modal Body -->
        <div class="p-6 space-y-5">
          <!-- Name Field -->
          <div class="form-control">
            <label class="label py-1">
              <span class="label-text font-semibold flex items-center gap-2">
                <User :size="14" class="text-primary" />
                Full Name <span class="text-error">*</span>
              </span>
            </label>
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none transition-colors group-focus-within:text-primary">
                <User :size="16" class="text-base-content/40" />
              </div>
              <input 
                v-model="formData.name"
                type="text" 
                placeholder="e.g. John Doe" 
                class="input input-bordered w-full pl-11 bg-base-200 border-base-300 focus:bg-base-100 focus:border-primary transition-all rounded-xl"
              />
            </div>
          </div>

          <!-- Email Field -->
          <div class="form-control">
            <label class="label py-1">
              <span class="label-text font-semibold flex items-center gap-2">
                <Mail :size="14" class="text-primary" />
                Email Address <span class="text-error">*</span>
              </span>
            </label>
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none transition-colors group-focus-within:text-primary">
                <Mail :size="16" class="text-base-content/40" />
              </div>
              <input 
                v-model="formData.email"
                type="email" 
                placeholder="e.g. j.doe@buksu.edu.ph" 
                class="input input-bordered w-full pl-11 bg-base-200 border-base-300 focus:bg-base-100 focus:border-primary transition-all rounded-xl"
              />
            </div>
            <p class="text-[10px] text-base-content/50 mt-1 ml-1 italic">
              Only accepted domains: {{ allowedDomains.map(d => '@' + d).join(', ') }}
            </p>
          </div>

          <!-- Department & Role Row -->
          <div class="grid grid-cols-2 gap-4">
            <!-- Department -->
            <div class="form-control">
              <label class="label py-1">
                <span class="label-text font-semibold flex items-center gap-2">
                  <Building2 :size="14" class="text-primary" />
                  Department <span class="text-error">*</span>
                </span>
              </label>
              <select v-model="formData.department" class="select select-bordered w-full bg-base-200 border-base-300 focus:border-primary rounded-xl">
                <option value="" disabled>Select</option>
                <option v-for="dept in departments" :key="dept" :value="dept">
                  {{ dept }}
                </option>
              </select>
            </div>

            <!-- Role -->
            <div class="form-control">
              <label class="label py-1">
                <span class="label-text font-semibold flex items-center gap-2">
                  <Shield :size="14" class="text-primary" />
                  Role <span class="text-error">*</span>
                </span>
              </label>
              <select v-model="formData.role" class="select select-bordered w-full bg-base-200 border-base-300 focus:border-primary rounded-xl" :disabled="isEditing">
                <option value="" disabled>Select</option>
                <option v-for="role in roles" :key="role.value" :value="role.value">
                  {{ role.label }}
                </option>
              </select>
            </div>
          </div>

          <!-- Auto-Generated Password (Add Mode Only) -->
          <div v-if="!isEditing" class="form-control">
            <label class="label py-1">
              <span class="label-text font-semibold flex items-center gap-2">
                <Lock :size="14" class="text-primary" />
                Auto-Generated Password
              </span>
              <span class="label-text-alt text-primary font-medium">Will be sent to user via email</span>
            </label>
            <div class="relative">
              <input 
                v-model="generatedPassword"
                :type="showPassword ? 'text' : 'password'"
                readonly
                class="input input-bordered w-full pr-24 bg-base-200 border-base-300 focus:border-primary rounded-xl font-mono"
              />
              <div class="absolute inset-y-0 right-0 flex items-center gap-1 pr-2">
                <button 
                  @click="copyPassword"
                  class="btn btn-ghost btn-xs btn-square text-base-content/60 hover:text-primary"
                  title="Copy password"
                >
                  <Copy :size="14" />
                </button>
                <button 
                  @click="generatePassword"
                  class="btn btn-ghost btn-xs btn-square text-base-content/60 hover:text-primary"
                  title="Regenerate password"
                >
                  <RefreshCw :size="14" class="animate-spin-once" />
                </button>
                <button 
                  @click="showPassword = !showPassword"
                  class="btn btn-ghost btn-xs btn-square text-base-content/60 hover:text-primary"
                  title="Toggle visibility"
                >
                  <EyeOff v-if="showPassword" :size="14" />
                  <Eye v-else :size="14" />
                </button>
              </div>
            </div>
            <p class="text-[10px] text-base-content/50 mt-1 ml-1">
              A secure 12-character password will be automatically generated and sent to the user's email.
            </p>
          </div>

          <!-- Account Status (Edit Mode Only) -->
          <div v-if="isEditing" class="form-control">
            <label class="label py-1">
              <span class="label-text font-semibold flex items-center gap-2">
                <Shield :size="14" class="text-primary" />
                Account Status
              </span>
            </label>
            <div class="flex items-center justify-between bg-base-200 p-4 rounded-xl border border-base-300">
              <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-lg flex items-center justify-center" :class="formData.is_active ? 'bg-success/10 text-success' : 'bg-error/10 text-error'">
                  <Users v-if="formData.is_active" :size="20" />
                  <Lock v-else :size="20" />
                </div>
                <div>
                  <span class="font-semibold" :class="formData.is_active ? 'text-success' : 'text-error'">
                    {{ formData.is_active ? 'Active' : 'Inactive' }}
                  </span>
                  <p class="text-xs text-base-content/60">{{ formData.is_active ? 'User can log in' : 'User cannot log in' }}</p>
                </div>
              </div>
              <input 
                type="checkbox" 
                v-model="formData.is_active" 
                class="toggle toggle-lg toggle-success" 
              />
            </div>
          </div>

          <!-- Role Info Badge -->
          <div v-if="!isEditing" class="bg-primary/5 rounded-xl p-4 border border-primary/10">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-lg bg-primary/10 flex items-center justify-center">
                <Mail :size="18" class="text-primary" />
              </div>
              <div class="flex-1">
                <p class="font-semibold text-sm text-primary">Credentials will be sent via email</p>
                <p class="text-xs text-base-content/60">The user will receive login credentials and setup instructions at their email address.</p>
              </div>
            </div>
          </div>
        </div>

        <!-- Modal Footer -->
        <div class="bg-base-200/50 p-4 flex justify-end gap-3 border-t border-base-200">
          <button @click="closeModal" class="btn btn-ghost rounded-xl px-6">
            Cancel
          </button>
          <button 
            @click="saveUser" 
            class="btn btn-primary text-white rounded-xl px-6 shadow-lg shadow-primary/20"
            :disabled="isLoading"
          >
            <span v-if="isLoading" class="loading loading-spinner loading-sm"></span>
            <span v-else class="flex items-center gap-2">
              <Mail v-if="!isEditing" :size="16" />
              <span>{{ isEditing ? 'Save Changes' : 'Create User' }}</span>
            </span>
          </button>
        </div>
      </div>
      <div class="modal-backdrop bg-black/40 backdrop-blur-[2px]" @click="closeModal"></div>
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

.animate-spin-once {
  animation: spin-once 0.5s ease-out;
}

@keyframes spin-once {
  from {
    transform: rotate(0deg);
  }
  to {
    transform: rotate(360deg);
  }
}

.input:focus {
  @apply ring-4 ring-primary/10 border-primary outline-none;
}
</style>
