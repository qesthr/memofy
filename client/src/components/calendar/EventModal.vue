<script setup>
import { ref, watch, onMounted, computed } from 'vue'
import { useCalendar } from '@/composables/useCalendar'
import { useAuth } from '@/composables/useAuth'
import { useEvents } from '@/composables/useEvents'
import api from '@/services/api'
import Swal from 'sweetalert2'
import { X, Clock, MapPin, AlignLeft, Users, Trash2, CheckCircle, XCircle, Mail, Search } from 'lucide-vue-next'

const { showEventModal, activeEvent, closeEventModal, selectedDate } = useCalendar()
const { fetchEvents } = useEvents()
const { user: currentUser, can } = useAuth()

const isSaving = ref(false)
const users = ref([])
const form = ref({
  title: '',
  start: '',
  end: '',
  all_day: false,
  category: 'standard',
  priority: 'medium',
  description: '',
  invited_users: []
})

const userSearch = ref('')
const showUserSuggestions = ref(false)


const isEditMode = computed(() => !!activeEvent.value?.is_editable && !!activeEvent.value?.id)
const isViewMode = computed(() => !!activeEvent.value && !activeEvent.value.is_editable)
const isCreateFromGrid = computed(() => !!activeEvent.value && activeEvent.value.is_editable && !activeEvent.value.id)
const isMemoEvent = computed(() => activeEvent.value?.source === 'MEMO' && activeEvent.value?.memo_id)


const otherUsers = computed(() => {
    return users.value.filter(u => u.id !== currentUser.value?.id)
})

const filteredUsers = computed(() => {
  if (!userSearch.value) return otherUsers.value
  const search = userSearch.value.toLowerCase()
  return otherUsers.value.filter(u => 
    `${u.first_name} ${u.last_name}`.toLowerCase().includes(search) ||
    u.email.toLowerCase().includes(search)
  )
})

const handleUserSelect = (u) => {
    if (!form.value.invited_users.includes(u.id)) {
        form.value.invited_users.push(u.id)
    }
    userSearch.value = ''
    showUserSuggestions.value = false
}

const removeInvitedUser = (userId) => {
    form.value.invited_users = form.value.invited_users.filter(id => id !== userId)
}

onMounted(async () => {
    // Load users for invitation list
    try {
        const response = await api.get('/users')
        users.value = response.data.data || []
    } catch (err) {
        console.error('Failed to load users:', err)
    }
})

const toLocalISO = (date) => {
  const pad = (n) => String(n).padStart(2, '0')
  return `${date.getFullYear()}-${pad(date.getMonth()+1)}-${pad(date.getDate())}T${pad(date.getHours())}:${pad(date.getMinutes())}`
}

const safeDate = (dateVal, fallbackOffset = 0) => {
  const d = dateVal ? new Date(dateVal) : new Date(selectedDate.value)
  if (isNaN(d.getTime())) return toLocalISO(new Date())
  if (fallbackOffset) d.setHours(d.getHours() + fallbackOffset)
  return toLocalISO(d)
}

watch([showEventModal, activeEvent], () => {
  if (showEventModal.value) {
    if (activeEvent.value) {
      form.value = {
        title: activeEvent.value.title || '',
        start: safeDate(activeEvent.value.start),
        end: safeDate(activeEvent.value.end, activeEvent.value.end ? 0 : 1),
        all_day: activeEvent.value.all_day || false,
        category: activeEvent.value.category || 'standard',
        description: activeEvent.value.description || '',
        invited_users: activeEvent.value.participants?.map(p => p.user_id) || []
      }
    } else {
      // Create mode - prefill with selected date
      // Use local midnight of selected date if it's a generic Add Event click
      const start = new Date(selectedDate.value)
      // If it's today and early morning, prefill with next available hour
      // Otherwise keep the date of selectedDate
      const now = new Date()
      if (start.toDateString() === now.toDateString()) {
        start.setHours(now.getHours() + 1, 0, 0, 0)
      } else {
        start.setHours(9, 0, 0, 0)
      }
      
      const end = new Date(start)
      end.setHours(start.getHours() + 1)
      
      form.value = {
        title: '',
        start: toLocalISO(start),
        end: toLocalISO(end),
        all_day: false,
        category: 'standard',
        priority: 'medium', // Added default priority
        description: '',
        invited_users: []
      }
    }
  }
})

const saveEvent = async () => {
  const permissionNeeded = activeEvent.value?.id ? 'calendar.edit_event' : 'calendar.add_event'
  if (!can(permissionNeeded)) {
    Swal.fire({
      icon: 'error',
      title: 'Permission Denied',
      text: `You do not have permission to ${activeEvent.value?.id ? 'edit' : 'add'} events.`
    })
    return
  }

  isSaving.value = true
  try {
    if (activeEvent.value?.id) {
      await api.put(`/calendar/events/${activeEvent.value.id}`, form.value)
      
      // If this is a memo event, also update the memo's scheduled_send_at
      if (isMemoEvent.value) {
        try {
          await api.put(`/memos/${activeEvent.value.memo_id}`, {
            scheduled_send_at: form.value.start,
            schedule_end_at: form.value.end,
            all_day_event: form.value.all_day
          })
        } catch (err) {
          console.error('Failed to sync memo schedule:', err)
        }
      }
      
      Swal.fire('Updated!', 'Event has been updated.', 'success')
    } else {
      await api.post('/calendar/events', form.value)
      Swal.fire('Created!', 'Event has been created.', 'success')
    }
    fetchEvents()
    closeEventModal()
  } catch (err) {
    Swal.fire('Error', err.response?.data?.message || 'Failed to save event', 'error')
  } finally {
    isSaving.value = false
  }
}

const deleteEvent = async () => {
  if (!can('calendar.delete_event')) {
    Swal.fire({
      icon: 'error',
      title: 'Permission Denied',
      text: 'You do not have permission to delete events.'
    })
    return
  }

  const result = await Swal.fire({
    title: 'Delete Event?',
    text: "This action cannot be undone.",
    icon: 'warning',
    showCancelButton: true,
    confirmButtonColor: '#d33',
    confirmButtonText: 'Yes, delete'
  })

  if (result.isConfirmed) {
    try {
      await api.delete(`/calendar/events/${activeEvent.value.id}`)
      fetchEvents()
      closeEventModal()
      Swal.fire('Deleted!', 'Event has been deleted.', 'success')
    } catch (err) {
      Swal.fire('Error', 'Failed to delete event', 'error')
    }
  }
}

const respondToInvitation = async (status) => {
    try {
        await api.post(`/calendar/events/${activeEvent.value.id}/respond`, { status })
        fetchEvents()
        closeEventModal()
        Swal.fire('Responded!', `You have ${status} the invitation.`, 'success')
    } catch (err) {
        Swal.fire('Error', 'Failed to respond to invitation', 'error')
    }
}

const viewMemo = () => {
  if (activeEvent.value?.memo_id) {
    // Navigate to memo details - assuming a route exists like /memos/:id
    // If using vue-router
    window.location.href = `/memos/${activeEvent.value.memo_id}`
  }
}
</script>

<template>
  <div v-if="showEventModal" class="fixed inset-0 z-[1000] flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
    <div class="bg-base-100 rounded-xl shadow-2xl w-[60vh] h-[70vh] flex flex-col">
      <!-- Header -->
      <div class="flex items-center justify-between p-4 border-b border-base-200">
        <div class="flex items-center gap-3">
          <h3 class="text-lg font-bold">
              {{ activeEvent?.id ? (activeEvent.is_editable ? 'Edit Event' : 'Event Details') : 'Create Event' }}
          </h3>
          <span v-if="isMemoEvent" class="badge badge-success gap-2 text-white">
            <Mail :size="14" />
            Scheduled Memo
          </span>
        </div>
        <button @click="closeEventModal" class="btn btn-sm btn-ghost btn-circle">
          <X :size="20" />
        </button>
      </div>

      <!-- Body -->
      <div class="p-6 space-y-4 overflow-y-auto flex-1 custom-scrollbar">
        <!-- Title -->
        <div class="flex items-start gap-4">
           <div class="p-2 bg-primary/10 rounded-lg text-primary mt-1">
              <AlignLeft :size="20" />
           </div>
           <div class="flex-1">
              <input 
                v-model="form.title" 
                type="text" 
                placeholder="Add title" 
                class="input input-ghost text-2xl font-semibold w-full p-0 h-auto focus:bg-transparent focus:outline-none"
                :readonly="isViewMode"
              />
           </div>
        </div>

        <!-- Times -->
        <div class="flex items-start gap-4">
           <div class="p-2 bg-base-200 rounded-lg text-base-content/60">
              <Clock :size="20" />
           </div>
           <div class="flex-1 space-y-3">
              <div class="flex items-center gap-4">
                <div class="flex-1">
                  <label class="text-[10px] font-bold text-base-content/40 uppercase tracking-wider block mb-1">Start</label>
                  <input v-model="form.start" type="datetime-local" class="input input-sm input-bordered w-full rounded-md" :readonly="isViewMode" />
                </div>
                <div class="flex-1">
                  <label class="text-[10px] font-bold text-base-content/40 uppercase tracking-wider block mb-1">End</label>
                  <input v-model="form.end" type="datetime-local" class="input input-sm input-bordered w-full rounded-md" :readonly="isViewMode" />
                </div>
              </div>
              <label class="flex items-center gap-2 cursor-pointer">
                <input v-model="form.all_day" type="checkbox" class="checkbox checkbox-xs" :disabled="isViewMode" />
                <span class="text-sm">All Day</span>
              </label>
           </div>
        </div>

        <!-- Priority & Source -->
        <div v-if="!isViewMode" class="flex items-start gap-4">
           <div class="p-2 bg-base-200 rounded-lg text-base-content/60">
              <div class="w-5 h-5 rounded-full border-2 border-primary"></div>
           </div>
           <div class="flex-1">
              <label class="text-[10px] font-bold text-base-content/40 uppercase tracking-wider block mb-1">Priority</label>
              <select v-model="form.priority" class="select select-sm select-bordered w-full rounded-md">
                <option value="low" class="text-[#4CAF50]">Low</option>
                <option value="medium" class="text-[#FF9800]">Medium</option>
                <option value="high" class="text-[#F44336]">High</option>
              </select>
           </div>
        </div>

        <!-- Invitations -->
        <div v-if="!isViewMode" class="flex items-start gap-4">
           <div class="p-2 bg-base-200 rounded-lg text-base-content/60">
              <Users :size="20" />
           </div>
           <div class="flex-1">
              <label class="text-[10px] font-bold text-base-content/40 uppercase tracking-wider block mb-1">Invite Users</label>
              
              <!-- Invited Users Badges -->
              <div class="flex flex-wrap gap-2 mb-3">
                 <div v-for="userId in form.invited_users" :key="userId" 
                      class="flex items-center gap-2 bg-primary/10 text-primary py-1 pl-1 pr-2 rounded-full border border-primary/20">
                    <div class="w-5 h-5 rounded-full bg-primary text-white flex items-center justify-center text-[8px] font-bold overflow-hidden">
                      <img v-if="users.find(u => u.id === userId)?.profile_picture" 
                           :src="users.find(u => u.id === userId)?.profile_picture" 
                           class="w-full h-full object-cover" />
                      <span v-else>{{ users.find(u => u.id === userId)?.first_name?.[0] }}{{ users.find(u => u.id === userId)?.last_name?.[0] }}</span>
                    </div>
                    <span class="text-xs font-semibold">{{ users.find(u => u.id === userId)?.first_name }}</span>
                    <button @click="removeInvitedUser(userId)" class="hover:text-error transition-colors">
                      <X :size="12" />
                    </button>
                 </div>
              </div>

              <!-- Searchable Dropdown -->
              <div class="relative">
                <div class="relative">
                  <input 
                    v-model="userSearch"
                    @focus="showUserSuggestions = true"
                    type="text" 
                    placeholder="Search users to invite..." 
                    class="input input-sm input-bordered w-full rounded-md focus:outline-none focus:border-primary pr-8"
                  />
                  <div class="absolute right-2 top-1/2 -translate-y-1/2 opacity-30">
                    <Search :size="14" v-if="!userSearch" />
                    <X :size="14" v-else @click="userSearch = ''" class="cursor-pointer hover:opacity-100" />
                  </div>
                </div>

                <div v-if="showUserSuggestions && filteredUsers.length > 0" 
                     class="absolute top-full left-0 right-0 mt-1 bg-base-100 border border-base-300 rounded-lg shadow-xl z-1100 max-h-48 overflow-y-auto custom-scrollbar">
                  <div v-for="u in filteredUsers" :key="u.id" 
                       @click="handleUserSelect(u)"
                       class="flex items-center gap-3 p-2 hover:bg-base-200 cursor-pointer transition-colors"
                       :class="{ 'opacity-50 pointer-events-none bg-base-100': form.invited_users.includes(u.id) }">
                    <div class="w-8 h-8 rounded-full bg-primary/10 text-primary flex items-center justify-center text-[10px] font-bold overflow-hidden">
                      <img v-if="u.profile_picture" :src="u.profile_picture" class="w-full h-full object-cover" />
                      <span v-else>{{ u.first_name?.[0] }}{{ u.last_name?.[0] }}</span>
                    </div>
                    <div class="flex-1 overflow-hidden">
                      <div class="text-xs font-bold truncate">{{ u.first_name }} {{ u.last_name }}</div>
                      <div class="text-[10px] opacity-50 truncate uppercase tracking-tighter">{{ u.role }}</div>
                    </div>
                    <CheckCircle v-if="form.invited_users.includes(u.id)" :size="14" class="text-success" />
                  </div>
                </div>
                <!-- Backdrop for closing dropdown -->
                <div v-if="showUserSuggestions" @click="showUserSuggestions = false" class="fixed inset-0 z-[-1]"></div>
              </div>
           </div>
        </div>

        <!-- Description -->
        <div class="flex items-start gap-4">
           <div class="p-2 bg-base-200 rounded-lg text-base-content/60">
              <AlignLeft :size="20" />
           </div>
           <div class="flex-1">
              <label class="text-[10px] font-bold text-base-content/40 uppercase tracking-wider block mb-1">Description</label>
              <textarea v-model="form.description" class="textarea textarea-bordered w-full h-24 rounded-md focus:outline-none" placeholder="Add description" :readonly="isViewMode"></textarea>
           </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="p-4 border-t border-base-200 bg-base-100 flex items-center justify-between">
        <div>
           <button v-if="activeEvent?.id && activeEvent.is_editable" 
                   @click="deleteEvent" 
                   class="btn btn-sm btn-ghost text-error gap-2">
              <Trash2 :size="18" />
              Delete
           </button>
        </div>
        
        <div class="flex gap-2">
          <button @click="closeEventModal" class="btn btn-sm btn-ghost px-6">Cancel</button>
          
          <!-- View Memo Button for linked memos -->
          <button v-if="activeEvent?.memo_id" 
                  @click="viewMemo" 
                  class="btn btn-sm btn-outline btn-info gap-2 px-6">
            <Mail :size="18" />
            View Memo
          </button>

          <!-- View Specific Actions -->
          <template v-if="isViewMode">
              <template v-if="activeEvent?.invitation_status === 'accepted' || activeEvent?.invitation_status === 'declined'">
                  <button @click="deleteEvent" class="btn btn-sm btn-outline btn-error gap-2 px-6">
                    <Trash2 :size="18" /> Archive
                  </button>
              </template>
              <template v-else>
                  <button @click="respondToInvitation('declined')" class="btn btn-sm btn-outline btn-error gap-2">
                    <XCircle :size="18" /> Decline
                  </button>
                  <button @click="respondToInvitation('accepted')" class="btn btn-sm btn-primary gap-2 text-white">
                    <CheckCircle :size="18" /> Accept
                  </button>
              </template>
          </template>

          <button v-else @click="saveEvent" :disabled="isSaving || !form.title" class="btn btn-sm btn-primary px-8 text-white rounded-md">
            {{ isSaving ? 'Saving...' : (activeEvent?.id ? 'Update' : 'Save') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background-color: #cbd5e1;
  border-radius: 4px;
}
</style>
