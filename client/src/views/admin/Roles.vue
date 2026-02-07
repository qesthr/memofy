<script setup>
import { ref, onMounted, computed } from 'vue'
import { 
  Plus, Pencil, Shield, X, Check, Search, Trash2, 
  Users, FileText, Calendar, BarChart3, Archive, 
  Settings, ShieldCheck, Eye, EyeOff, ChevronDown, ChevronRight
} from 'lucide-vue-next'
import api from '../../services/api'
import Swal from 'sweetalert2'

const roles = ref([])
const permissions = ref({})
const isLoading = ref(false)
const showRoleModal = ref(false)
const isEditing = ref(false)
const selectedRoleId = ref('')
const expandedCategories = ref({})
const editingPermissionIds = ref([])

const users = ref([])
const selectedUserId = ref('')

const isUserMode = computed(() => {
  const currentRole = roles.value.find(r => (r.id || r._id) === selectedRoleId.value)
  return currentRole && (currentRole.name === 'secretary' || currentRole.name === 'faculty')
})

const roleForm = ref({
  name: '',
  label: '',
  description: '',
  status: 'active',
  department: ''
})

const categoryIcons = {
  dashboard: Shield,
  faculty: Users,
  roles: ShieldCheck,
  activity: FileText,
  memo: FileText,
  calendar: Calendar,
  reports: BarChart3,
  archive: Archive,
  settings: Settings,
  navigation: Eye,
  admin: Shield
}

const categoryColors = {
  dashboard: 'bg-blue-100 text-blue-600',
  faculty: 'bg-purple-100 text-purple-600',
  roles: 'bg-indigo-100 text-indigo-600',
  activity: 'bg-yellow-100 text-yellow-600',
  memo: 'bg-orange-100 text-orange-600',
  calendar: 'bg-green-100 text-green-600',
  reports: 'bg-cyan-100 text-cyan-600',
  archive: 'bg-red-100 text-red-600',
  settings: 'bg-gray-100 text-gray-600',
  navigation: 'bg-pink-100 text-pink-600',
  admin: 'bg-red-100 text-red-600'
}

const defaultRoles = ['admin', 'secretary', 'faculty']

const fetchRoles = async () => {
  isLoading.value = true
  try {
    const response = await api.get('/roles')
    roles.value = response.data
    if (roles.value.length > 0 && !selectedRoleId.value) {
      selectRole(roles.value[0])
    }
  } catch (error) {
    console.error('Error fetching roles:', error)
    Swal.fire({
      icon: 'error',
      title: 'Failed to Load Roles',
      text: 'Could not retrieve system roles',
      confirmButtonColor: '#3b82f6'
    })
  } finally {
    isLoading.value = false
  }
}

const fetchPermissions = async (roleName = '') => {
  try {
    const response = await api.get(`/permissions?role=${roleName}`)
    permissions.value = response.data
    // Reset expanded states for new categories
    expandedCategories.value = {}
    Object.keys(permissions.value).forEach(cat => {
      expandedCategories.value[cat] = true
    })
  } catch (error) {
    console.error('Error fetching permissions:', error)
  }
}

const selectRole = (role) => {
  if (!role) return
  selectedRoleId.value = role.id || role._id
  selectedUserId.value = '' // Reset user selection
  users.value = []
  
  roleForm.value = {
    name: role.name,
    label: role.label || role.name,
    description: role.description || '',
    status: role.status || 'active',
    department: role.department || ''
  }
  editingPermissionIds.value = [...(role.permission_ids || [])]

  // Fetch filtered permissions for this role
  fetchPermissions(role.name)

  // If it's a role that supports user-specific overrides, fetch users
  if (role.name === 'secretary' || role.name === 'faculty') {
    fetchUsersByRole(role.name)
  }
}

const fetchUsersByRole = async (roleName) => {
  try {
    const response = await api.get(`/roles/${roleName}/users`)
    users.value = response.data
  } catch (error) {
    console.error('Error fetching users for role:', error)
  }
}

const selectUser = (userId) => {
  selectedUserId.value = userId
  const user = users.value.find(u => (u.id || u._id) === userId)
  if (user) {
    // If user has specific permissions, load them. 
    // Otherwise, fallback to the current role permissions which are already in editingPermissionIds
    if (user.permission_ids && user.permission_ids.length > 0) {
      editingPermissionIds.value = [...user.permission_ids]
    } else {
      // Fallback to current role permissions
      const currentRole = roles.value.find(r => (r.id || r._id) === selectedRoleId.value)
      editingPermissionIds.value = [...(currentRole?.permission_ids || [])]
    }
  }
}

const handleUserSwitch = (event) => {
  selectUser(event.target.value)
}

const handleRoleSwitch = (event) => {
  const roleId = event.target.value
  const role = roles.value.find(r => (r.id || r._id) === roleId)
  if (role) {
    selectRole(role)
  }
}

const openAddModal = () => {
  isEditing.value = false
  roleForm.value = {
    name: '',
    label: '',
    description: '',
    status: 'active',
    department: ''
  }
  showRoleModal.value = true
}

const closeModal = () => {
  showRoleModal.value = false
}

const togglePermission = (permissionId) => {
  const index = editingPermissionIds.value.indexOf(permissionId)
  if (index === -1) {
    editingPermissionIds.value.push(permissionId)
  } else {
    editingPermissionIds.value.splice(index, 1)
  }
}

const toggleCategoryPermissions = (category, permissionsList) => {
  const allSelected = permissionsList.every(p => editingPermissionIds.value.includes(p.id))
  
  if (allSelected) {
    editingPermissionIds.value = editingPermissionIds.value.filter(
      id => !permissionsList.some(p => p.id === id)
    )
  } else {
    permissionsList.forEach(p => {
      if (!editingPermissionIds.value.includes(p.id)) {
        editingPermissionIds.value.push(p.id)
      }
    })
  }
}

const isCategoryFullySelected = (permissionsList) => {
  return permissionsList.length > 0 && 
         permissionsList.every(p => editingPermissionIds.value.includes(p.id))
}

const toggleExpanded = (category) => {
  expandedCategories.value[category] = !expandedCategories.value[category]
}

const formatCategoryName = (cat) => {
  return cat.split('_').map(word => 
    word.charAt(0).toUpperCase() + word.slice(1)
  ).join(' ')
}

const isDefaultRole = (roleName) => {
  return defaultRoles.includes(roleName)
}

const createRole = async () => {
  if (!roleForm.value.label) {
    Swal.fire({
      icon: 'warning',
      title: 'Missing Information',
      text: 'Please provide a role label',
      confirmButtonColor: '#3b82f6'
    })
    return
  }

  isLoading.value = true
  try {
    const roleData = {
      ...roleForm.value,
      name: roleForm.value.name || roleForm.value.label.toLowerCase().replace(/\s+/g, '_'),
      permission_ids: []
    }
    const response = await api.post('/roles', roleData)
    await Swal.fire({
      icon: 'success',
      title: 'Role Created!',
      text: 'New role has been created successfully',
      timer: 2000,
      showConfirmButton: false
    })
    
    await fetchRoles()
    // Select the newly created role
    if (response.data.role) {
      selectRole(response.data.role)
    }
    closeModal()
  } catch (error) {
    console.error('Error creating role:', error)
    Swal.fire({
      icon: 'error',
      title: 'Action Failed',
      text: error.response?.data?.message || 'Could not create role',
      confirmButtonColor: '#3b82f6'
    })
  } finally {
    isLoading.value = false
  }
}

const updateCurrentRole = async () => {
  if (!selectedRoleId.value) return

  isLoading.value = true
  try {
    if (selectedUserId.value) {
      // Update User-specific permissions
      await api.put(`/users/${selectedUserId.value}/permissions`, {
        permission_ids: editingPermissionIds.value
      })

      // Update local user data in the users list
      const userIndex = users.value.findIndex(u => (u.id || u._id) === selectedUserId.value)
      if (userIndex !== -1) {
        users.value[userIndex].permission_ids = [...editingPermissionIds.value]
      }

      await Swal.fire({
        icon: 'success',
        title: 'User Permissions Updated!',
        text: 'Individual permissions saved successfully',
        timer: 2000,
        showConfirmButton: false
      })
    } else {
      // Update role details (standard behavior)
      await api.put(`/roles/${selectedRoleId.value}`, {
        ...roleForm.value,
        permission_ids: editingPermissionIds.value
      })

      await Swal.fire({
        icon: 'success',
        title: 'Role Updated!',
        text: 'Changes saved successfully',
        timer: 2000,
        showConfirmButton: false
      })
      
      await fetchRoles()
    }
  } catch (error) {
    console.error('Error updating permissions:', error)
    Swal.fire({
      icon: 'error',
      title: 'Action Failed',
      text: error.response?.data?.message || 'Could not save changes',
      confirmButtonColor: '#3b82f6'
    })
  } finally {
    isLoading.value = false
  }
}

const deleteCurrentRole = async () => {
  const currentRole = roles.value.find(r => (r.id || r._id) === selectedRoleId.value)
  if (!currentRole) return

  if (isDefaultRole(currentRole.name)) {
    Swal.fire({
      icon: 'warning',
      title: 'Cannot Delete',
      text: 'Default system roles cannot be deleted',
      confirmButtonColor: '#3b82f6'
    })
    return
  }

  const result = await Swal.fire({
    icon: 'warning',
    title: 'Delete Role?',
    text: `Are you sure you want to delete "${currentRole.label}"? This action cannot be undone.`,
    showCancelButton: true,
    confirmButtonColor: '#d33',
    cancelButtonColor: '#3b82f6',
    confirmButtonText: 'Yes, delete!'
  })

  if (!result.isConfirmed) return

  try {
    await api.delete(`/roles/${selectedRoleId.value}`)
    await Swal.fire({
      icon: 'success',
      title: 'Role Deleted!',
      text: 'Role has been deleted successfully',
      timer: 2000,
      showConfirmButton: false
    })
    selectedRoleId.value = ''
    await fetchRoles()
  } catch (error) {
    console.error('Error deleting role:', error)
    Swal.fire({
      icon: 'error',
      title: 'Action Failed',
      text: error.response?.data?.message || 'Could not delete role',
      confirmButtonColor: '#3b82f6'
    })
  }
}

onMounted(() => {
  fetchRoles()
  fetchPermissions()
})
</script>

<template>
  <div class="view-container h-[calc(100vh-140px)] flex flex-col overflow-hidden">
    <!-- Header -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-8">
      <div>
        <h1 class="text-2xl font-bold text-base-content">Roles & Permissions</h1>
        <p class="text-base-content/60 mt-1">Manage user roles and their associated system permissions.</p>
      </div>
      <button @click="openAddModal" class="btn btn-primary gap-2 text-white">
        <Plus :size="18" />
        New Role
      </button>
    </div>

    <!-- Main Editor Layout -->
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
      <!-- Left Sidebar: Role Selector & Basic Info -->
      <div class="lg:col-span-4 space-y-6 lg:sticky lg:top-4 h-fit">
        <div class="card bg-base-100 border border-base-200 shadow-sm">
          <div class="card-body p-6">
            <h3 class="text-sm font-bold uppercase tracking-wider text-base-content/40 mb-4">Select Role</h3>
            
            <div class="form-control mb-4">
              <label class="label">
                <span class="label-text-alt font-bold text-base-content/40 uppercase">Role</span>
              </label>
              <select 
                class="select select-bordered w-full"
                :value="selectedRoleId"
                @change="handleRoleSwitch"
              >
                <option disabled value="">Select a role to manage</option>
                <option 
                  v-for="role in roles" 
                  :key="role.id || role._id" 
                  :value="role.id || role._id"
                >
                  {{ role.label || role.name }}
                </option>
              </select>
            </div>

            <!-- Select User (Only for specific roles) -->
            <div v-if="isUserMode && users.length > 0" class="form-control mb-6">
              <label class="label pt-0">
                <span class="label-text-alt font-bold text-base-content/40 uppercase">Select Individual User</span>
              </label>
              <select 
                class="select select-bordered select-primary w-full"
                :value="selectedUserId"
                @change="handleUserSwitch"
              >
                <option value="">Apply to all {{ roleForm.label }}s</option>
                <option 
                  v-for="user in users" 
                  :key="user.id || user._id" 
                  :value="user.id || user._id"
                >
                  {{ user.first_name }} {{ user.last_name }} ({{ user.email }})
                </option>
              </select>
              <label class="label">
                <span class="label-text-alt text-primary font-medium italic">
                  {{ selectedUserId ? 'Customizing permissions for this user only' : 'Managing default permissions for this role' }}
                </span>
              </label>
            </div>

            <div v-if="selectedRoleId && !selectedUserId" class="space-y-4">
              <div class="form-control">
                <label class="label">
                  <span class="label-text font-semibold">Display Label</span>
                </label>
                <input 
                  v-model="roleForm.label" 
                  type="text" 
                  class="input input-bordered w-full"
                />
              </div>

              <div class="form-control">
                <label class="label">
                  <span class="label-text font-semibold">Description</span>
                </label>
                <textarea 
                  v-model="roleForm.description" 
                  class="textarea textarea-bordered w-full"
                  rows="3"
                ></textarea>
              </div>

              <div class="form-control">
                <label class="label">
                  <span class="label-text font-semibold">Status</span>
                </label>
                <select v-model="roleForm.status" class="select select-bordered w-full">
                  <option value="active">Active</option>
                  <option value="inactive">Inactive</option>
                </select>
              </div>

              <div class="pt-4 flex flex-col gap-2">
                <button 
                  @click="updateCurrentRole" 
                  class="btn btn-primary w-full text-white"
                  :disabled="isLoading"
                >
                  <Check v-if="!isLoading" :size="18" />
                  <span v-else class="loading loading-spinner loading-sm"></span>
                  {{ selectedUserId ? 'Save User Permissions' : 'Save Selection' }}
                </button>
                <button 
                  v-if="!isDefaultRole(roleForm.name)"
                  @click="deleteCurrentRole" 
                  class="btn btn-ghost text-error w-full gap-2"
                >
                  <Trash2 :size="18" />
                  Delete Role
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Right Content: Permissions Grid -->
      <div class="lg:col-span-8 overflow-y-auto custom-scrollbar pr-2" style="max-height: calc(100vh - 160px);">
        <div v-if="!selectedRoleId" class="flex flex-col items-center justify-center py-20 bg-base-100/50 rounded-2xl border-2 border-dashed border-base-200">
          <Shield :size="48" class="text-base-content/10 mb-4" />
          <p class="text-base-content/40 font-medium">Select a role from the dropdown to manage permissions</p>
        </div>

        <div v-else class="space-y-6">
          <div class="flex items-center justify-between px-2">
            <h2 class="text-lg font-bold flex items-center gap-2">
              <ShieldCheck class="text-primary" :size="20" />
              {{ selectedUserId ? 'Custom User Permissions' : 'System Permissions' }}
            </h2>
            <div class="flex items-center gap-2 text-xs font-semibold text-base-content/40">
              <span>{{ editingPermissionIds.length }} selected</span>
            </div>
          </div>

          <div class="grid grid-cols-1 gap-4">
            <div 
              v-for="(perms, category) in permissions" 
              :key="category" 
              class="card bg-base-100 border border-base-200 overflow-hidden"
            >
              <div class="card-body p-0">
                <button 
                  @click="toggleExpanded(category)"
                  class="w-full px-6 py-4 flex items-center justify-between hover:bg-base-50/50 transition-colors"
                >
                  <div class="flex items-center gap-3">
                    <div 
                      class="w-10 h-10 rounded-xl flex items-center justify-center shadow-sm"
                      :class="categoryColors[category] || 'bg-gray-100 text-gray-600'"
                    >
                      <component :is="categoryIcons[category] || Shield" :size="20" />
                    </div>
                    <div class="text-left">
                      <span class="font-bold text-base block">{{ formatCategoryName(category) }}</span>
                      <span class="text-xs font-medium text-base-content/40">
                        {{ perms.filter(p => editingPermissionIds.includes(p.id)).length }}/{{ perms.length }} Enabled
                      </span>
                    </div>
                  </div>
                  <ChevronDown 
                    :size="20" 
                    class="transition-transform duration-300"
                    :class="{ '-rotate-180': expandedCategories[category] }"
                  />
                </button>

                <div v-if="expandedCategories[category]" class="p-6 border-t border-base-100 bg-base-50/30">
                  <div class="flex items-center justify-end mb-4">
                    <button 
                      @click="toggleCategoryPermissions(category, perms)"
                      class="btn btn-xs btn-ghost text-primary text-[10px] font-bold tracking-wider uppercase border border-primary/20 hover:border-primary"
                    >
                      {{ isCategoryFullySelected(perms) ? 'Deselect Category' : 'Select All in Category' }}
                    </button>
                  </div>

                  <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                    <label 
                      v-for="permission in perms" 
                      :key="permission.id"
                      class="flex items-start gap-4 p-3 rounded-xl border border-transparent hover:border-base-300 hover:bg-base-100 cursor-pointer transition-all duration-200"
                      :class="{ 'bg-primary/5 border-primary/20': editingPermissionIds.includes(permission.id) }"
                    >
                      <div class="pt-1">
                        <input 
                          type="checkbox" 
                          :checked="editingPermissionIds.includes(permission.id)"
                          @change="togglePermission(permission.id)"
                          class="checkbox checkbox-primary checkbox-sm rounded-md"
                        />
                      </div>
                      <div class="flex-1">
                        <div class="text-sm font-bold antialiased leading-none mb-1">{{ permission.name }}</div>
                        <div class="text-[11px] text-base-content/50 leading-tight">{{ permission.description }}</div>
                      </div>
                    </label>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Create Role Modal (Repurposed for simple add) -->
    <div v-if="showRoleModal" class="modal modal-open">
      <div class="modal-box max-w-md rounded-2xl">
        <div class="flex items-center justify-between mb-6">
          <h3 class="font-bold text-xl flex items-center gap-2">
            <Shield class="text-primary" :size="22" />
            Create New Role
          </h3>
          <button @click="closeModal" class="btn btn-sm btn-circle btn-ghost">
            <X :size="18" />
          </button>
        </div>

        <div class="space-y-4">
          <div class="form-control">
            <label class="label">
              <span class="label-text font-bold">Display Label <span class="text-error">*</span></span>
            </label>
            <input 
              v-model="roleForm.label" 
              type="text" 
              placeholder="e.g., Department Head"
              class="input input-bordered w-full"
            />
          </div>

          <div class="form-control">
            <label class="label">
              <span class="label-text font-bold">Role Name (Key)</span>
            </label>
            <input 
              v-model="roleForm.name" 
              type="text" 
              placeholder="e.g., department_head (auto)"
              class="input input-bordered w-full"
            />
            <label class="label">
              <span class="label-text-alt text-base-content/40">Leave blank to auto-generate from label</span>
            </label>
          </div>

          <div class="form-control">
            <label class="label">
              <span class="label-text font-bold">Description</span>
            </label>
            <textarea 
              v-model="roleForm.description" 
              class="textarea textarea-bordered w-full"
              placeholder="Basic description..."
              rows="2"
            ></textarea>
          </div>
        </div>

        <div class="modal-action mt-8">
          <button @click="closeModal" class="btn btn-ghost rounded-xl">Cancel</button>
          <button 
            @click="createRole" 
            class="btn btn-primary text-white rounded-xl shadow-lg shadow-primary/20"
            :disabled="isLoading"
          >
            <span v-if="isLoading" class="loading loading-spinner loading-sm"></span>
            Create Role
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
  @apply bg-black/60 backdrop-blur-sm;
}

.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}

.checkbox-sm {
  --size: 1rem;
}

.custom-scrollbar::-webkit-scrollbar {
  width: 5px;
}

.custom-scrollbar::-webkit-scrollbar-track {
  background: transparent;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
  @apply bg-base-300 rounded-full;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  @apply bg-base-content/20;
}
</style>
