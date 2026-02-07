<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { X, Paperclip, Calendar, Eye, Send, Search } from 'lucide-vue-next'
import api from '@/services/api'
import ScheduleMemoModal from './ScheduleMemoModal.vue'
import { useAuth } from '@/composables/useAuth'

const { user } = useAuth()

const props = defineProps({
  isOpen: Boolean,
  initialData: Object
})

const emit = defineEmits(['close', 'send'])

const formData = ref({
  to: '',
  subject: '',
  signature: 'None',
  signatureId: null,
  department: 'Department',
  departmentId: null,
  priority: 'Medium',
  content: '',
  recipientId: null,
  recipientType: 'individual', // individual or department
  attachmentPath: null,
  attachmentName: null
})

const scheduleData = ref(null)
const showScheduleModal = ref(false)

const users = ref([])
const showUserSuggestions = ref(false)
const isLoadingUsers = ref(false)
const departments = ref([])
const signatures = ref([])
const showPreviewModal = ref(false)
const fileInput = ref(null)
const isUploading = ref(false)

const priorities = [
  { label: 'Low', color: 'bg-info' },
  { label: 'Medium', color: 'bg-warning' },
  { label: 'High', color: 'bg-error' }
]

const fetchUsers = async () => {
  try {
    isLoadingUsers.value = true
    const response = await api.get('/users')
    users.value = response.data.data || response.data
  } catch (error) {
    console.error('Error fetching users:', error)
  } finally {
    isLoadingUsers.value = false
  }
}

const fetchDepartments = async () => {
  try {
    const response = await api.get('/departments')
    departments.value = response.data.data
  } catch (error) {
    console.error('Error fetching departments:', error)
  }
}
const fetchSignatures = async () => {
  try {
    const response = await api.get('/user-signatures')
    signatures.value = response.data.data
    
    // Check for default signature
    const defaultSig = signatures.value.find(s => s.is_default)
    if (defaultSig) {
      formData.value.signature = defaultSig.name
      formData.value.signatureId = defaultSig.id
    }
  } catch (error) {
    console.error('Error fetching signatures:', error)
  }
}

const filteredDepartments = computed(() => {
  const roleName = (user.value?.role && typeof user.value.role === 'object') ? user.value.role.name : user.value?.role
  if (roleName === 'admin') return departments.value
  
  return departments.value.filter(dept => dept.id === user.value?.department_id)
})

const selectUser = (user) => {
  formData.value.to = `${user.first_name} ${user.last_name} (${user.email})`
  formData.value.recipientId = user.id
  formData.value.recipientType = 'individual'
  showUserSuggestions.value = false
}

const selectDepartment = (dept) => {
  formData.value.department = dept.name
  formData.value.departmentId = dept.id
  formData.value.to = `Department: ${dept.name}`
  formData.value.recipientType = 'department'
}

const handleFileUpload = async (event) => {
  const file = event.target.files[0]
  if (!file) return

  try {
    isUploading.value = true
    const uploadData = new FormData()
    uploadData.append('file', file)
    
    const response = await api.post('/upload', uploadData)
    formData.value.attachmentPath = response.data.file_path
    formData.value.attachmentName = response.data.file_name
    Swal.fire({
      title: 'Uploaded!',
      text: 'File attached successfully',
      icon: 'success',
      timer: 1500,
      showConfirmButton: false
    })
  } catch (error) {
    console.error('Upload failed:', error)
    Swal.fire('Error', 'File upload failed', 'error')
  } finally {
    isUploading.value = false
  }
}

const handleSend = async () => {
  try {
    if (!formData.value.recipientId && !formData.value.departmentId) {
      alert('Please select a recipient or department')
      return
    }

    const priorityMap = {
      'Low': 'low',
      'Medium': 'normal',
      'High': 'high'
    }

    const payload = {
      recipient_id: formData.value.recipientType === 'individual' ? formData.value.recipientId : null,
      department_id: formData.value.recipientType === 'department' ? formData.value.departmentId : null,
      subject: formData.value.subject,
      message: formData.value.content,
      priority: priorityMap[formData.value.priority] || 'normal',
      attachments: [],
      is_draft: false,
      signature_id: formData.value.signatureId,
      attachment_path: formData.value.attachmentPath
    }

    // Add schedule data if present
    if (scheduleData.value) {
      payload.scheduled_send_at = `${scheduleData.value.startDate}T${scheduleData.value.startTime}`
      payload.schedule_end_at = `${scheduleData.value.endDate}T${scheduleData.value.endTime}`
      payload.all_day_event = scheduleData.value.allDay
    }

    const response = await api.post('/memos', payload)
    
    emit('send', response.data)
    resetForm()
    closeModal()
  } catch (error) {
    console.error('Error sending memo:', error)
    alert('Failed to send memo. Please try again.')
  }
}

const resetForm = () => {
  formData.value = {
    to: '',
    subject: '',
    signature: 'None',
    department: 'Department',
    priority: 'Medium',
    content: '',
    recipientId: null
  }
  scheduleData.value = null
}

const closeModal = () => {
  emit('close')
}

const handleScheduleSave = (schedule) => {
  scheduleData.value = schedule
  showScheduleModal.value = false
}

onMounted(() => {
  fetchUsers()
  fetchDepartments()
  fetchSignatures()
})

watch(() => props.initialData, (newVal) => {
  if (newVal) {
    formData.value = {
      ...formData.value,
      ...newVal
    }
  }
}, { immediate: true })

watch(() => formData.value.to, (newVal) => {
  if (newVal && filteredUsers.value.length > 0 && formData.value.recipientType === 'individual') {
    showUserSuggestions.value = true
  } else {
    showUserSuggestions.value = false
  }
})
</script>

<template>
  <div v-if="isOpen" class="modal modal-open items-center justify-center">
    <div class="modal-box p-0 max-w-4xl w-full h-[85vh] overflow-hidden rounded-xl bg-base-100 shadow-2xl border border-base-300 flex flex-col">
      <!-- Fixed Header -->
      <div class="bg-primary px-6 py-4 flex items-center justify-between text-primary-content shrink-0 z-10">
        <h3 class="text-xl font-bold tracking-tight uppercase">Compose Memo</h3>
        <button @click="closeModal" class="btn btn-ghost btn-sm btn-circle text-primary-content hover:bg-white/10">
          <X :size="20" />
        </button>
      </div>

      <!-- Fixed Metadata Section (Not Scrollable) -->
      <div class="px-10 pt-8 pb-4 space-y-6 shrink-0 bg-base-100">
        <!-- To Field (Inline) -->
        <div class="flex items-center gap-4 group">
          <label class="w-20 text-xs font-black text-base-content/50 uppercase tracking-[0.2em] shrink-0">TO</label>
          <div class="relative flex-1">
            <input 
              v-model="formData.to"
              @focus="showUserSuggestions = true"
              type="text" 
              placeholder="Recipient" 
              class="input input-ghost focus:bg-transparent border-transparent focus:border-transparent focus:outline-none p-0 text-base w-full placeholder:text-base-content/20 font-medium"
            />
            <!-- Autocomplete Suggestion -->
            <div v-if="showUserSuggestions && filteredUsers.length > 0" class="absolute left-0 top-full mt-2 w-full bg-base-100 shadow-2xl border border-base-300 rounded-xl z-[60] overflow-hidden">
              <ul class="menu p-1">
                <li v-for="user in filteredUsers" :key="user.id">
                  <button @click="selectUser(user)" class="flex items-center gap-3 py-3 px-4 hover:bg-base-200 rounded-lg transition-colors">
                    <div class="w-8 h-8 rounded-full bg-primary/10 text-primary flex items-center justify-center font-bold text-xs uppercase">
                      {{ user.first_name[0] }}{{ user.last_name[0] }}
                    </div>
                    <div class="flex flex-col items-start translate-y-[-1px]">
                      <span class="font-bold text-sm">{{ user.first_name }} {{ user.last_name }}</span>
                      <span class="text-[10px] opacity-40 leading-none">{{ user.email }}</span>
                    </div>
                  </button>
                </li>
              </ul>
            </div>
          </div>
        </div>

        <!-- Subject Field (Inline) -->
        <div class="flex items-center gap-4 group">
          <label class="w-20 text-xs font-black text-base-content/50 uppercase tracking-[0.2em] shrink-0">SUBJECT</label>
          <input 
            v-model="formData.subject"
            type="text" 
            placeholder="Subject" 
            class="input input-ghost focus:bg-transparent border-transparent focus:border-transparent focus:outline-none p-0 text-base w-full placeholder:text-base-content/20 font-medium"
          />
        </div>

        <!-- Controls Row -->
        <div class="flex flex-wrap items-center gap-6 py-2">
          <!-- Signature Dropdown -->
          <div class="dropdown">
            <div tabindex="0" role="button" class="btn btn-sm bg-base-200 border-none px-4 rounded-lg font-black text-[10px] uppercase tracking-wider hover:bg-base-300 text-base-content/70">
              Signature: {{ formData.signature }}
              <span class="ml-2 opacity-40 text-[8px]">▼</span>
            </div>
            <ul tabindex="0" class="dropdown-content z-50 menu p-2 shadow-2xl bg-base-100 border border-base-300 rounded-xl w-52 mt-2">
              <li @click="formData.signature = 'None'; formData.signatureId = null"><a class="font-bold">None</a></li>
              <li v-for="sig in signatures" :key="sig.id" @click="formData.signature = sig.name; formData.signatureId = sig.id">
                <a class="font-bold">{{ sig.name }}</a>
              </li>
            </ul>
          </div>

          <!-- Department Dropdown -->
          <div class="dropdown">
            <div tabindex="0" role="button" class="btn btn-sm bg-base-200 border-none px-4 rounded-lg font-black text-[10px] uppercase tracking-wider hover:bg-base-300 text-base-content/70">
              {{ formData.department }}
              <span class="ml-2 opacity-40 text-[8px]">▼</span>
            </div>
            <ul tabindex="0" class="dropdown-content z-50 menu p-2 shadow-2xl bg-base-100 border border-base-300 rounded-xl w-64 mt-2">
              <li v-for="dept in filteredDepartments" :key="dept.id" @click="selectDepartment(dept)">
                <a class="font-bold flex justify-between">
                  {{ dept.name }}
                  <span class="text-[8px] opacity-40">{{ dept.code }}</span>
                </a>
              </li>
            </ul>
          </div>
          
          <!-- Priority Selector -->
          <div class="dropdown">
            <div tabindex="0" role="button" class="btn btn-sm bg-base-200 border-none px-4 rounded-lg font-black text-[10px] uppercase tracking-wider hover:bg-base-300 text-base-content/70">
              <span class="w-2 h-2 rounded-full mr-2" :class="priorities.find(p => p.label === formData.priority).color"></span>
              Priority: {{ formData.priority }}
              <span class="ml-2 opacity-40 text-[8px]">▼</span>
            </div>
            <ul tabindex="0" class="dropdown-content z-50 menu p-2 shadow-2xl bg-base-100 border border-base-300 rounded-xl w-40 mt-2">
              <li v-for="p in priorities" :key="p.label" @click="formData.priority = p.label">
                <a class="font-bold flex items-center gap-3">
                  <span class="w-2 h-2 rounded-full" :class="p.color"></span>
                  {{ p.label }}
                </a>
              </li>
            </ul>
          </div>
        </div>
      </div>

      <!-- Scrollable Content Field -->
      <div class="flex-1 overflow-y-auto px-10 pb-8 custom-scrollbar bg-base-100">
        <textarea 
          v-model="formData.content"
          placeholder="Enter memo content (plain text)..." 
          class="textarea textarea-ghost focus:bg-transparent border-transparent focus:border-transparent focus:outline-none p-0 w-full min-h-full text-base resize-none placeholder:text-base-content/20 font-medium leading-relaxed"
        ></textarea>
      </div>

      <!-- Footer (Fixed) -->
      <div class="px-10 py-6 bg-base-100 border-t border-base-200 flex items-center justify-between shrink-0">
        <div class="flex items-center gap-4">
          <input type="file" ref="fileInput" class="hidden" @change="handleFileUpload" />
          <button @click="fileInput.click()" class="btn btn-ghost btn-md btn-square rounded-xl hover:bg-base-200" :class="{ 'text-primary bg-primary/10': formData.attachmentPath }" title="Attach Files (PDF, Image)">
            <Paperclip :size="20" :class="{ 'opacity-100': formData.attachmentPath, 'opacity-60': !formData.attachmentPath }" />
          </button>
          <div v-if="isUploading" class="loading loading-spinner loading-sm text-primary"></div>
          <span v-if="formData.attachmentName" class="text-[10px] font-bold opacity-40 max-w-[100px] truncate">{{ formData.attachmentName }}</span>
          
          <button 
            @click="showScheduleModal = true" 
            class="btn btn-ghost btn-md bg-base-100 border border-base-300 hover:border-primary/50 hover:bg-primary/5 gap-3 px-6 font-black text-[10px] uppercase tracking-widest rounded-xl transition-all"
            :class="{ 'bg-primary/10 border-primary': scheduleData }"
          >
            <Calendar :size="18" class="opacity-60" />
            Schedule
            <span v-if="scheduleData" class="badge badge-primary badge-xs">✓</span>
          </button>
        </div>

        <div class="flex items-center gap-4">
          <button @click="closeModal" class="btn btn-ghost btn-md px-6 font-black text-[10px] uppercase tracking-[0.2em] opacity-40 hover:opacity-100 transition-opacity">Cancel</button>
          <button @click="showPreviewModal = true" class="btn btn-ghost btn-md border border-base-300 hover:border-primary/50 hover:bg-primary/5 px-8 font-black text-[10px] uppercase tracking-[0.2em] rounded-xl transition-all">Preview</button>
          <button @click="handleSend" class="btn btn-primary btn-md px-12 text-white font-black text-[11px] uppercase tracking-[0.25em] shadow-2xl shadow-primary/40 rounded-xl hover:scale-[1.02] active:scale-[0.98] transition-all">
            Send
          </button>
        </div>
      </div>
    </div>
    <div class="modal-backdrop bg-base-100/5 backdrop-blur-3xl transition-all duration-700" @click="closeModal"></div>

    <!-- Schedule Modal -->
    <ScheduleMemoModal 
      :isOpen="showScheduleModal" 
      :initialSchedule="scheduleData || {}"
      @close="showScheduleModal = false"
      @save="handleScheduleSave"
    />

    <!-- Preview Modal -->
    <div v-if="showPreviewModal" class="modal modal-open z-[100]">
      <div class="modal-box max-w-2xl bg-white p-12 rounded-none shadow-2xl flex flex-col gap-8 relative">
        <button @click="showPreviewModal = false" class="absolute top-4 right-4 btn btn-ghost btn-circle btn-sm"><X :size="20" /></button>
        
        <!-- Memo Header -->
        <div class="border-b-4 border-primary pb-4 flex justify-between items-end">
          <h2 class="text-4xl font-black tracking-tighter text-primary italic">MEMO</h2>
          <div class="text-right">
            <p class="text-[10px] font-bold uppercase tracking-[0.3em] opacity-40">BukSU Memofy Official</p>
            <p class="text-xs font-black">{{ new Date().toLocaleDateString() }}</p>
          </div>
        </div>

        <!-- Memo Meta -->
        <div class="space-y-2">
          <div class="flex gap-4"><span class="w-16 font-black text-[10px] uppercase opacity-40">TO:</span> <span class="font-bold underline uppercase">{{ formData.to || 'RECIPIENT' }}</span></div>
          <div class="flex gap-4"><span class="w-16 font-black text-[10px] uppercase opacity-40">FROM:</span> <span class="font-bold uppercase">ADMIN / {{ formData.department }}</span></div>
          <div class="flex gap-4"><span class="w-16 font-black text-[10px] uppercase opacity-40">SUBJECT:</span> <span class="font-bold uppercase">{{ formData.subject || '(NO SUBJECT)' }}</span></div>
        </div>

        <!-- Memo Content -->
        <div class="py-10 text-sm leading-relaxed min-h-[200px] whitespace-pre-wrap font-medium">
          {{ formData.content || 'No content provided...' }}
        </div>

        <!-- Signature Space -->
        <div class="mt-auto pt-10 flex flex-col items-end">
          <div class="w-48 h-20 border-b border-black flex items-center justify-center p-2">
            <span class="text-[10px] opacity-20 font-black italic">SIGNATURE HERE</span>
          </div>
          <p class="text-[10px] font-black uppercase tracking-widest mt-2">{{ formData.signature }}</p>
          <p class="text-[8px] font-bold opacity-40">{{ formData.department }}</p>
        </div>

        <button @click="showPreviewModal = false" class="btn btn-primary btn-block text-white rounded-xl font-black uppercase tracking-widest text-xs mt-8">Back to Edit</button>
      </div>
      <div class="modal-backdrop bg-black/40 backdrop-blur-sm" @click="showPreviewModal = false"></div>
    </div>
  </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background-color: rgba(0, 0, 0, 0.1);
  border-radius: 10px;
}
[data-theme='dark'] .custom-scrollbar::-webkit-scrollbar-thumb {
  background-color: rgba(255, 255, 255, 0.1);
}
.custom-scrollbar::-webkit-scrollbar-track {
  background-color: transparent;
}

/* Ensure focus doesn't show outline but still interactive */
.input:focus, .select:focus, .textarea:focus {
  box-shadow: none !important;
}

.modal-box {
  animation: modal-pop 0.5s cubic-bezier(0.19, 1, 0.22, 1);
}

@keyframes modal-pop {
  0% { opacity: 0; transform: scale(0.95) translateY(30px); }
  100% { opacity: 1; transform: scale(1) translateY(0); }
}

/* Hide select arrow but keep clickable */
select {
  -webkit-appearance: none;
  -moz-appearance: none;
  appearance: none;
}
</style>
