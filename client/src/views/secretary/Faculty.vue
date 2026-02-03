<script setup>
import { ref, onMounted, watch } from 'vue'
import { 
  Plus, 
  Search, 
  UserPlus, 
  X, 
  Loader2, 
  MoreVertical, 
  Archive, 
  Pencil,
  Building2,
  Mail,
  User 
} from 'lucide-vue-next'
import api from '@/services/api'
import Swal from 'sweetalert2'
import { useAuth } from '@/composables/useAuth'

const { can } = useAuth()

const faculty = ref([])
const currentUser = ref(null)
const isLoading = ref(false)
const showAddModal = ref(false)
const isSubmitting = ref(false)
const searchTerm = ref('')
const activeFilter = ref('active')

const filters = [
  { id: 'active', label: 'Active & Pending' },
  { id: 'archived', label: 'Archived' }
]

const facultyForm = ref({
  name: '',
  email: ''
})

const fetchFaculty = async () => {
  isLoading.value = true
  try {
    const response = await api.get('/users', {
      params: {
        role: 'faculty',
        search: searchTerm.value,
        status: activeFilter.value
      }
    })
    const data = response.data.data || response.data
    faculty.value = data.map(f => ({
      ...f,
      name: f.name || `${f.first_name || ''} ${f.last_name || ''}`.trim()
    }))
  } catch (error) {
    console.error('Error fetching faculty:', error)
  } finally {
    isLoading.value = false
  }
}

const fetchCurrentUser = async () => {
  try {
    const response = await api.get('/current-user')
    currentUser.value = response.data.user || response.data
  } catch (error) {
    console.error('Error fetching current user:', error)
  }
}

const inviteFaculty = async () => {
  if (!facultyForm.value.name || !facultyForm.value.email) {
    Swal.fire({
      icon: 'warning',
      title: 'Missing Fields',
      text: 'Please complete all required fields.'
    })
    return
  }

  isSubmitting.value = true
  try {
    await api.post('/users/invite', {
      name: facultyForm.value.name,
      email: facultyForm.value.email
    })

    Swal.fire({
      icon: 'success',
      title: 'Faculty Invited!',
      text: 'Faculty successfully invited and assigned to your department.',
      confirmButtonColor: '#4285F4',
      timer: 3000
    })

    closeModal()
    fetchFaculty()
  } catch (error) {
    const message = error.response?.data?.message || 'An unexpected error occurred.'
    Swal.fire({
      icon: 'error',
      title: 'Invitation Failed',
      text: message
    })
  } finally {
    isSubmitting.value = false
  }
}

const toggleStatus = async (user) => {
  const isPending = user.display_status === 'pending'
  
  const result = await Swal.fire({
    icon: 'warning',
    title: isPending ? 'Cancel Invitation?' : (user.is_active ? 'Archive Faculty?' : 'Activate Faculty?'),
    text: isPending 
      ? `Are you sure you want to cancel the invitation for ${user.email}?`
      : `Are you sure you want to ${user.is_active ? 'archive' : 'activate'} ${user.name}?`,
    showCancelButton: true,
    confirmButtonColor: isPending || user.is_active ? '#d33' : '#3085d6',
    cancelButtonColor: '#aaa',
    confirmButtonText: isPending ? 'Yes, cancel it' : `Yes, ${user.is_active ? 'archive' : 'activate'}`
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
      title: 'Updated!',
      text: 'Faculty status has been updated.',
      timer: 1500,
      showConfirmButton: false
    })
    fetchFaculty()
  } catch (error) {
    Swal.fire({
      icon: 'error',
      title: 'Action Failed',
      text: error.response?.data?.message || 'Could not update faculty status.'
    })
  }
}

const getStatusBadge = (status) => {
  switch (status) {
    case 'active': return 'badge-success text-white'
    case 'pending': return 'badge-warning text-white'
    case 'archived': return 'badge-error text-white'
    default: return 'badge-ghost'
  }
}

const closeModal = () => {
  showAddModal.value = false
  facultyForm.value = { name: '', email: '' }
}

watch(searchTerm, () => {
  fetchFaculty()
})

watch(activeFilter, () => {
  fetchFaculty()
})

onMounted(() => {
  fetchCurrentUser()
  fetchFaculty()
})
</script>

<template>
  <div class="space-y-6">
    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-base-100 p-6 rounded-2xl shadow-sm border border-base-200">
      <div>
        <h1 class="text-2xl font-bold text-base-content">Manage Faculty</h1>
        <p class="text-base-content/60 text-sm mt-1">
          View and manage faculty members for the <span class="font-semibold text-primary">{{ currentUser?.department || 'Department' }}</span>.
        </p>
      </div>
      <button 
        v-if="can('faculty.add')"
        @click="showAddModal = true"
        class="btn btn-primary bg-primary border-none text-white gap-2 px-6 shadow-lg shadow-blue-100"
      >
        <Plus :size="20" />
        Invite Faculty
      </button>
    </div>

    <!-- Filters & Search -->
    <div class="flex flex-col md:flex-row gap-4">
      <div class="flex gap-2">
        <button 
          v-for="filter in filters" 
          :key="filter.id"
          @click="activeFilter = filter.id"
          class="btn btn-sm rounded-xl px-4 transition-all"
          :class="activeFilter === filter.id ? 'btn-primary text-white shadow-md' : 'btn-ghost bg-base-100 border border-base-200 text-base-content/60'"
        >
          {{ filter.label }}
        </button>
      </div>
      <div class="relative flex-1">
        <Search class="absolute left-4 top-1/2 -translate-y-1/2 text-base-content/40" :size="20" />
        <input 
          v-model="searchTerm"
          type="text" 
          placeholder="Search by name or email..." 
          class="input input-bordered w-full pl-12 bg-base-100 border-base-300 focus:border-primary focus:ring-1 focus:ring-primary"
        />
      </div>
    </div>

    <!-- Faculty Table -->
    <div class="bg-base-100 rounded-2xl border border-base-200 overflow-hidden shadow-sm">
      <div class="overflow-x-auto">
        <table class="table w-full">
          <thead>
            <tr class="bg-base-200/50 text-base-content/60 uppercase text-xs tracking-wider border-b border-base-200">
              <th class="py-4 pl-6">Faculty Member</th>
              <th class="py-4">Role & Dept</th>
              <th class="py-4">Status</th>
              <th class="py-4 pr-6 text-right">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-base-100">
            <tr v-if="isLoading" class="border-none">
              <td colspan="4" class="py-12 text-center">
                <Loader2 class="w-8 h-8 animate-spin mx-auto text-primary opacity-50" />
                <p class="text-sm text-base-content/40 mt-2">Loading faculty list...</p>
              </td>
            </tr>
            <tr v-else-if="faculty.length === 0" class="border-none">
              <td colspan="4" class="py-12 text-center">
                <div class="bg-base-200 w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4">
                  <User :size="32" class="text-base-content/30" />
                </div>
                <p class="text-base-content font-medium">No faculty members found</p>
                <p class="text-sm text-base-content/40 mt-1">Try expanding your search or invite someone new.</p>
              </td>
            </tr>
            <tr v-for="user in faculty" :key="user.id" class="hover:bg-base-200/50 transition-colors">
              <td class="py-4 pl-6">
                <div class="flex items-center gap-4">
                  <div class="avatar placeholder">
                    <div class="bg-primary/10 text-primary font-bold rounded-xl w-12 h-12 shadow-inner border border-primary/5">
                      <span class="text-lg">{{ user.name?.charAt(0) }}</span>
                    </div>
                  </div>
                  <div>
                    <div class="font-bold text-base-content">{{ user.name }}</div>
                    <div class="text-xs text-base-content/50 flex items-center gap-1 mt-0.5">
                      <Mail :size="12" /> {{ user.email }}
                    </div>
                  </div>
                </div>
              </td>
              <td class="py-4">
                <div class="flex flex-col gap-1">
                  <span class="badge badge-sm border-none bg-primary/10 text-primary font-medium capitalize">{{ user.role }}</span>
                  <div class="text-[10px] text-base-content/40 flex items-center gap-1">
                    <Building2 :size="10" /> {{ user.department }}
                  </div>
                </div>
              </td>
              <td class="py-4">
                <span class="badge badge-sm font-semibold capitalize px-3 py-2.5" :class="getStatusBadge(user.display_status)">
                  {{ user.display_status }}
                </span>
              </td>
              <td class="py-4 pr-6">
                <div class="flex items-center justify-end gap-2">
                  <button 
                    v-if="user.is_active ? can('faculty.archive') : (user.display_status === 'pending' ? can('faculty.archive') : can('faculty.unarchive'))"
                    @click="toggleStatus(user)"
                    class="btn btn-ghost btn-sm btn-square rounded-lg transition-all"
                    :class="user.display_status === 'pending' || user.is_active ? 'hover:bg-red-50 text-red-400 hover:text-red-500' : 'hover:bg-blue-50 text-blue-400 hover:text-blue-500'"
                    :title="user.display_status === 'pending' ? 'Cancel Invitation' : (user.is_active ? 'Archive' : 'Activate')"
                  >
                    <Archive v-if="user.is_active" :size="18" />
                    <X v-else-if="user.display_status === 'pending'" :size="18" />
                    <Plus v-else :size="18" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Invite Modal -->
    <div v-if="showAddModal" class="modal modal-open bg-black/40 backdrop-blur-[2px] transition-all duration-300">
      <div class="modal-box bg-base-100 p-0 overflow-hidden rounded-3xl border-none shadow-2xl scale-100 max-w-lg">
        <!-- Modal Header -->
        <div class="bg-neutral p-8 text-neutral-content relative overflow-hidden">
          <div class="relative z-10">
            <h3 class="text-2xl font-bold flex items-center gap-3">
              <UserPlus class="text-primary" />
              Invite Faculty
            </h3>
            <p class="text-neutral-content/70 text-sm mt-2">
              The new faculty member will inherit the <span class="text-primary font-semibold">{{ currentUser?.department }}</span> department automatically.
            </p>
          </div>
          <div class="absolute right-0 top-0 bottom-0 w-32 bg-primary/10 skew-x-[-15deg] translate-x-12"></div>
        </div>

        <!-- Modal Body -->
        <div class="p-8 space-y-6">
          <div class="space-y-4">
            <!-- Name Input -->
            <div class="form-control">
              <label class="text-sm font-semibold text-base-content mb-1.5 ml-1">Full Name</label>
              <div class="relative group">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none transition-colors group-focus-within:text-primary">
                  <User :size="18" class="text-base-content/40" />
                </div>
                <input 
                  v-model="facultyForm.name"
                  type="text" 
                  placeholder="e.g. John Doe" 
                  class="input input-bordered w-full pl-11 bg-base-200 border-base-300 focus:bg-base-100 focus:border-primary transition-all rounded-xl"
                />
              </div>
            </div>

            <!-- Email Input -->
            <div class="form-control">
              <label class="text-sm font-semibold text-base-content mb-1.5 ml-1">Email Address</label>
              <div class="relative group">
                <div class="absolute inset-y-0 left-0 pl-4 flex items-center pointer-events-none transition-colors group-focus-within:text-primary">
                  <Mail :size="18" class="text-base-content/40" />
                </div>
                <input 
                  v-model="facultyForm.email"
                  type="email" 
                  placeholder="e.g. j.doe@buksu.edu.ph" 
                  class="input input-bordered w-full pl-11 bg-base-200 border-base-300 focus:bg-base-100 focus:border-primary transition-all rounded-xl"
                />
              </div>
              <p class="text-[10px] text-base-content/40 mt-1.5 ml-1 italic">
                Only BukSU domain emails (@buksu.edu.ph or @student.buksu.edu.ph) are accepted.
              </p>
            </div>

            <!-- Read-only Info -->
            <div class="grid grid-cols-2 gap-4 pt-2">
              <div class="bg-base-200 p-3 rounded-xl border border-base-200">
                <p class="text-[10px] font-bold text-base-content/40 uppercase tracking-wider mb-1">Assigned Role</p>
                <span class="text-sm font-bold text-base-content">Faculty</span>
              </div>
              <div class="bg-base-200 p-3 rounded-xl border border-base-200">
                <p class="text-[10px] font-bold text-base-content/40 uppercase tracking-wider mb-1">Department</p>
                <span class="text-sm font-bold text-base-content truncate block bg-primary/10 text-primary px-2 py-0.5 rounded">{{ currentUser?.department }}</span>
              </div>
            </div>
          </div>

          <!-- Actions -->
          <div class="flex gap-3 pt-2">
            <button 
              @click="closeModal" 
              class="btn btn-ghost flex-1 rounded-xl font-bold bg-base-300 hover:bg-base-200"
            >
              Cancel
            </button>
            <button 
              @click="inviteFaculty"
              class="btn btn-primary flex-2 rounded-xl border-none text-primary-content font-bold shadow-lg shadow-primary/20"
              :disabled="isSubmitting"
            >
              <span v-if="isSubmitting" class="loading loading-spinner"></span>
              <span v-else>Send Invitation</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
@reference "../../style.css";

.modal-box {
  animation: modal-pop 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}

@keyframes modal-pop {
  0% { opacity: 0; transform: scale(0.95) translateY(-10px); }
  100% { opacity: 1; transform: scale(1) translateY(0); }
}

.input:focus {
  @apply ring-4 ring-primary/10 border-primary outline-none;
}

.flex-2 {
  flex: 2;
}
</style>
