<script setup>
import { ref, onMounted } from 'vue'
import { Plus, Pencil, Shield, X, Check, Search } from 'lucide-vue-next'
import api from '../../services/api'
import Swal from 'sweetalert2'

const roles = ref([])
const permissions = ref({})
const isLoading = ref(false)
const showRoleModal = ref(false)
const isEditing = ref(false)
const selectedRole = ref(null)

const roleForm = ref({
  name: '',
  label: '',
  description: '',
  status: 'active',
  permission_ids: []
})

const fetchRoles = async () => {
  try {
    const response = await api.get('/roles')
    roles.value = response.data
  } catch (error) {
    console.error('Error fetching roles:', error)
    Swal.fire({
      icon: 'error',
      title: 'Failed to Load Roles',
      text: 'We could not retrieve the system roles. Please check your connection or try again later.',
      confirmButtonColor: '#3b82f6'
    })
  }
}

const fetchPermissions = async () => {
  try {
    const response = await api.get('/permissions')
    permissions.value = response.data
  } catch (error) {
    console.error('Error fetching permissions:', error)
    Swal.fire({
      icon: 'error',
      title: 'Failed to Load Permissions',
      text: 'System permissions could not be loaded. Some management features may be unavailable.',
      confirmButtonColor: '#3b82f6'
    })
  }
}

const openModal = (role = null) => {
  if (role) {
    isEditing.value = true
    selectedRole.value = role
    roleForm.value = {
      name: role.name,
      label: role.label,
      description: role.description,
      status: role.status || 'active',
      permission_ids: [...(role.permission_ids || [])]
    }
  } else {
    isEditing.value = false
    selectedRole.value = null
    roleForm.value = {
      name: '',
      label: '',
      description: '',
      status: 'active',
      permission_ids: []
    }
  }
  showRoleModal.value = true
}

const closeModal = () => {
  showRoleModal.value = false
}

const togglePermission = (id) => {
  const index = roleForm.value.permission_ids.indexOf(id)
  if (index === -1) {
    roleForm.value.permission_ids.push(id)
  } else {
    roleForm.value.permission_ids.splice(index, 1)
  }
}

const toggleCategory = (category, permissionsList) => {
  const allInCat = permissionsList.every(p => roleForm.value.permission_ids.includes(p.id))
  
  if (allInCat) {
    // Remove all
    const idsToRemove = permissionsList.map(p => p.id)
    roleForm.value.permission_ids = roleForm.value.permission_ids.filter(id => !idsToRemove.includes(id))
  } else {
    // Add missing
    permissionsList.forEach(p => {
      if (!roleForm.value.permission_ids.includes(p.id)) {
        roleForm.value.permission_ids.push(p.id)
      }
    })
  }
}

const isCategoryFull = (permissionsList) => {
    return permissionsList.length > 0 && permissionsList.every(p => roleForm.value.permission_ids.includes(p.id))
}

const saveRolePermissions = async () => {
  if (!selectedRole.value) return
  
  // Show loading state or confirm if you want, but user asked for SweetAlert success/error
  isLoading.value = true
  try {
    const roleId = selectedRole.value.id || selectedRole.value._id
    await api.put(`/roles/${roleId}/permissions`, {
      permission_ids: roleForm.value.permission_ids
    })
    
    await Swal.fire({
      icon: 'success',
      title: 'Permissions Saved!',
      text: `System permissions for ${roleForm.value.label} have been updated.`,
      timer: 2000,
      showConfirmButton: false,
      position: 'top-end',
      toast: true
    })
    
    await fetchRoles()
    closeModal()
  } catch (error) {
    console.error('Error saving permissions:', error)
    Swal.fire({
      icon: 'error',
      title: 'Action Failed',
      text: error.response?.data?.message || 'We could not save the permission changes. Please try again.',
      confirmButtonColor: '#3b82f6'
    })
  } finally {
    isLoading.value = false
  }
}

const formatCategoryName = (cat) => {
    return cat.charAt(0).toUpperCase() + cat.slice(1)
}

onMounted(() => {
  fetchRoles()
  fetchPermissions()
})
</script>

<template>
  <div class="view-container">
    <!-- Header -->
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="text-2xl font-bold text-base-content">Roles & Permissions</h1>
        <p class="text-base-content/60 mt-1">Manage user roles and their associated system permissions.</p>
      </div>
    </div>

    <!-- Roles Grid -->
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
      <div v-for="role in roles" :key="role.id" class="card bg-base-100 border border-base-300 hover:shadow-md transition-all group">
        <div class="card-body p-6">
          <div class="flex items-start justify-between mb-4">
            <div class="p-3 rounded-xl bg-primary/10 text-primary">
              <Shield :size="24" />
            </div>
            <div :class="['badge badge-sm font-semibold uppercase', role.status === 'active' ? 'badge-success text-white' : 'badge-ghost']">
              {{ role.status || 'active' }}
            </div>
          </div>
          
          <h3 class="text-lg font-bold">{{ role.label }}</h3>
          <p class="text-sm text-base-content/60 mb-6 line-clamp-2 h-10">{{ role.description }}</p>
          
          <div class="flex items-center justify-between mt-auto pt-4 border-t border-base-100">
            <span class="text-xs font-medium text-base-content/40">
              {{ role.permission_ids?.length || 0 }} Permissions Assigned
            </span>
            <button 
                @click="openModal(role)"
                class="btn btn-sm btn-ghost gap-2 text-primary hover:bg-primary/5"
            >
              <Pencil :size="14" />
              Manage Permissions
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Permissions Modal -->
    <div v-if="showRoleModal" class="modal modal-open">
      <div class="modal-box max-w-5xl bg-base-100 p-0 overflow-hidden rounded-2xl">
        <!-- Modal Header -->
        <div class="px-8 py-6 border-b border-base-200 flex items-center justify-between bg-slate-50/50">
          <div>
            <h3 class="font-bold text-xl flex items-center gap-2">
              <Shield class="text-primary" :size="24" />
              {{ isEditing ? 'Edit Role Permissions' : 'Role Details' }}
            </h3>
            <p class="text-sm text-base-content/60 mt-1">Configure what users with the <strong>{{ roleForm.label }}</strong> role can do.</p>
          </div>
          <button @click="closeModal" class="btn btn-sm btn-circle btn-ghost">
            <X :size="20" />
          </button>
        </div>

        <div class="max-h-[70vh] overflow-y-auto">
          <!-- Role Basic Info (Read Only for now) -->
          <div class="px-8 py-6 grid grid-cols-1 md:grid-cols-3 gap-6 bg-base-100">
            <div class="form-control">
                <label class="label py-1"><span class="label-text font-bold text-xs uppercase tracking-wider text-base-content/50">Role Name</span></label>
                <input v-model="roleForm.name" type="text" class="input input-sm bg-base-200/50 border-none cursor-not-allowed" readonly />
            </div>
            <div class="form-control col-span-2">
                <label class="label py-1"><span class="label-text font-bold text-xs uppercase tracking-wider text-base-content/50">Description</span></label>
                <input v-model="roleForm.description" type="text" class="input input-sm bg-base-200/50 border-none cursor-not-allowed" readonly />
            </div>
          </div>

          <!-- Permissions Grid -->
          <div class="px-8 pb-8">
            <div class="flex items-center justify-between mb-6 pb-2 border-b border-base-100">
                <h4 class="font-bold text-base">System Permissions</h4>
                <div class="flex items-center gap-4 text-xs">
                    <span class="flex items-center gap-1"><div class="w-3 h-3 rounded bg-primary"></div> Selected</span>
                    <span class="flex items-center gap-1"><div class="w-3 h-3 rounded border border-base-300"></div> Unselected</span>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
              <div v-for="(perms, category) in permissions" :key="category" class="permission-group">
                <div class="flex items-center justify-between mb-3 group/cat">
                  <h5 class="font-bold text-sm tracking-wide text-base-content/80 flex items-center gap-2">
                    <span class="w-1.5 h-1.5 rounded-full bg-primary/40"></span>
                    {{ formatCategoryName(category) }}
                  </h5>
                  <button 
                    @click="toggleCategory(category, perms)"
                    class="text-[10px] uppercase font-bold text-primary hover:underline"
                  >
                    {{ isCategoryFull(perms) ? 'Deselect All' : 'Select All' }}
                  </button>
                </div>
                
                <div class="space-y-2.5 bg-base-50/30 p-4 rounded-xl border border-base-100">
                  <label v-for="permission in perms" :key="permission.id" class="flex items-center gap-3 cursor-pointer group">
                    <div class="relative flex items-center">
                        <input 
                            type="checkbox" 
                            :checked="roleForm.permission_ids.includes(permission.id)"
                            @change="togglePermission(permission.id)"
                            class="checkbox checkbox-primary checkbox-xs rounded"
                        />
                    </div>
                    <span class="text-sm select-none group-hover:text-primary transition-colors">
                        {{ permission.name }}
                    </span>
                  </label>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Modal Action -->
        <div class="px-8 py-5 border-t border-base-200 bg-slate-50/50 flex justify-end gap-3">
          <button @click="closeModal" class="btn btn-ghost">Cancel</button>
          <button 
            @click="saveRolePermissions" 
            class="btn btn-primary text-white px-8"
            :disabled="isLoading || roleForm.name === 'admin'"
          >
            <span v-if="isLoading" class="loading loading-spinner loading-sm"></span>
            <span v-else>Save Changes</span>
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
  @apply p-8 bg-slate-50/50 min-h-full;
}

.modal-backdrop {
  @apply bg-black/60 backdrop-blur-sm;
}

.permission-group {
    @apply h-full;
}

/* Custom Checkbox sizing */
.checkbox-xs {
    --size: 1.1rem;
}

.line-clamp-2 {
  display: -webkit-box;
  -webkit-line-clamp: 2;
  -webkit-box-orient: vertical;
  overflow: hidden;
}
</style>
