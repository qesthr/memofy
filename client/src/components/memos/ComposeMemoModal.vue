<script setup>
import { ref, computed, onMounted, watch, onUnmounted } from 'vue'
import { X, Paperclip, Calendar, Eye, Send, Search, Trash2, FileText, Loader2 } from 'lucide-vue-next'
import api from '@/services/api'
import ScheduleMemoModal from './ScheduleMemoModal.vue'
import { useAuth } from '@/composables/useAuth'
import Swal from 'sweetalert2'

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
  recipientId: null, // Keep for backward compatibility/single selection
  recipientIds: [], // For multiple selection
  selectedRecipients: [], // For UI chips
  recipientType: 'individual', // individual or department
  attachments: [], // Array of uploaded files
  draftId: null // For auto-save
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
const isSaving = ref(false)
const lastSaved = ref(null)

// Auto-save timer
let autoSaveTimer = null
const AUTO_SAVE_INTERVAL = 30000 // 30 seconds

const priorities = [
  { label: 'Low', color: 'bg-info' },
  { label: 'Medium', color: 'bg-warning' },
  { label: 'High', color: 'bg-error' }
]

// Computed
const canSubmit = computed(() => {
  return formData.value.subject.trim() !== '' &&
         formData.value.content.trim() !== '' &&
         (formData.value.recipientIds.length > 0 || formData.value.departmentId) &&
         !isUploading.value
})

const attachmentCount = computed(() => formData.value.attachments.length)

const fetchUsers = async () => {
  try {
    isLoadingUsers.value = true
    const params = {}
    
    // If secretary, filter by their department
    const roleName = (user.value?.role && typeof user.value.role === 'object') ? user.value.role.name : user.value?.role
    if (roleName === 'secretary' && user.value?.department_id) {
      params.department_id = user.value.department_id
    }

    const response = await api.get('/users', { params })
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

const filteredUsers = computed(() => {
  if (!formData.value.to) return []
  const search = formData.value.to.toLowerCase()
  return users.value.filter(u => 
    `${u.first_name} ${u.last_name}`.toLowerCase().includes(search) ||
    u.email.toLowerCase().includes(search)
  ).slice(0, 10) // Limit to 10 results
})

const filteredDepartments = computed(() => {
  const roleName = (user.value?.role && typeof user.value.role === 'object') ? user.value.role.name : user.value?.role
  if (roleName === 'admin') return departments.value
  
  return departments.value.filter(dept => dept.id === user.value?.department_id)
})

const selectUser = (selectedUser) => {
  if (!formData.value.recipientIds.includes(selectedUser.id)) {
    formData.value.recipientIds.push(selectedUser.id)
    formData.value.selectedRecipients.push(selectedUser)
  }
  formData.value.to = ''
  formData.value.recipientType = 'individual'
  showUserSuggestions.value = false
}

const removeRecipient = (userId) => {
  formData.value.recipientIds = formData.value.recipientIds.filter(id => id !== userId)
  formData.value.selectedRecipients = formData.value.selectedRecipients.filter(r => r.id !== userId)
}

const selectDepartment = (dept) => {
  formData.value.department = dept.name
  formData.value.departmentId = dept.id
  formData.value.to = `Department: ${dept.name}`
  formData.value.recipientType = 'department'
}

const handleFileUpload = async (event) => {
  const files = event.target.files
  if (!files || files.length === 0) return

  // Handle multiple files
  const filesArray = Array.from(files)
  
  for (const file of filesArray) {
    await uploadSingleFile(file)
  }
  
  // Reset input
  if (fileInput.value) {
    fileInput.value.value = ''
  }
}

const uploadSingleFile = async (file) => {
  // Validate file size (10MB max)
  if (file.size > 10 * 1024 * 1024) {
    Swal.fire({
      title: 'File Too Large',
      text: `${file.name} exceeds the 10MB limit`,
      icon: 'warning',
      confirmButtonText: 'OK'
    })
    return
  }

  // Validate file type
  const allowedTypes = [
    'application/pdf',
    'image/jpeg', 'image/png', 'image/gif', 'image/svg+xml',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'text/plain',
    'application/zip',
    'application/x-zip-compressed'
  ]
  
  if (!allowedTypes.includes(file.type)) {
    Swal.fire({
      title: 'Invalid File Type',
      text: `${file.name} has an unsupported file type`,
      icon: 'warning',
      confirmButtonText: 'OK'
    })
    return
  }

  try {
    isUploading.value = true
    const uploadData = new FormData()
    uploadData.append('file', file)
    uploadData.append('type', 'attachment')
    
    const response = await api.post('/upload', uploadData)
    
    formData.value.attachments.push({
      id: Date.now() + Math.random(),
      name: response.data.file_name,
      path: response.data.file_path,
      size: response.data.file_size,
      type: response.data.file_type,
      url: response.data.url
    })
    
    Swal.fire({
      title: 'Uploaded!',
      text: `${response.data.file_name} attached successfully`,
      icon: 'success',
      timer: 1500,
      showConfirmButton: false
    })
  } catch (error) {
    console.error('Upload failed:', error)
    Swal.fire('Error', `Failed to upload ${file.name}`, 'error')
  } finally {
    isUploading.value = false
  }
}

const removeAttachment = (index) => {
  formData.value.attachments.splice(index, 1)
}

const formatFileSize = (bytes) => {
  if (bytes < 1024) return bytes + ' B'
  if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB'
  return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
}

const triggerFileInput = () => {
  if (fileInput.value) {
    fileInput.value.click()
  }
}

const handleSend = async () => {
  if (!canSubmit.value) {
    Swal.fire({
      title: 'Incomplete Memo',
      text: 'Please fill in all required fields',
      icon: 'warning',
      confirmButtonText: 'OK'
    })
    return
  }

  try {
    const priorityMap = {
      'Low': 'low',
      'Medium': 'normal',
      'High': 'high'
    }

    const payload = {
      recipient_ids: formData.value.recipientType === 'individual' ? formData.value.recipientIds : [],
      department_id: formData.value.recipientType === 'department' ? formData.value.departmentId : null,
      subject: formData.value.subject,
      message: formData.value.content,
      priority: priorityMap[formData.value.priority] || 'normal',
      attachments: formData.value.attachments.map(a => ({
        name: a.name,
        path: a.path,
        size: a.size,
        type: a.type
      })),
      is_draft: false,
      signature_id: formData.value.signatureId
    }

    // Add schedule data if present
    if (scheduleData.value) {
      payload.scheduled_send_at = `${scheduleData.value.startDate}T${scheduleData.value.startTime}`
      payload.schedule_end_at = `${scheduleData.value.endDate}T${scheduleData.value.endTime}`
      payload.all_day_event = scheduleData.value.allDay
    }

    // Clear auto-save draft if exists
    if (formData.value.draftId) {
      try {
        await api.delete(`/secretary/memos/${formData.value.draftId}`)
      } catch (e) {
        console.error('Error clearing draft:', e)
      }
      formData.value.draftId = null
    }

    const response = await api.post('/secretary/memos/submit-for-approval', payload)
    
    emit('send', response.data)
    resetForm()
    closeModal()
    
    Swal.fire({
      title: 'Submitted for Approval!',
      text: 'Your memo has been submitted to Admin for approval before distribution.',
      icon: 'success',
      confirmButtonText: 'OK'
    })
  } catch (error) {
    console.error('Error sending memo:', error)
    Swal.fire('Error', error.response?.data?.message || 'Failed to submit memo for approval', 'error')
  }
}

const saveAsDraft = async () => {
  try {
    isSaving.value = true
    
    const priorityMap = {
      'Low': 'low',
      'Medium': 'normal',
      'High': 'high'
    }

    const payload = {
      recipient_ids: formData.value.recipientType === 'individual' ? formData.value.recipientIds : [],
      department_id: formData.value.recipientType === 'department' ? formData.value.departmentId : null,
      subject: formData.value.subject || 'Untitled Draft',
      message: formData.value.content || '',
      priority: priorityMap[formData.value.priority] || 'normal',
      attachments: formData.value.attachments.map(a => ({
        name: a.name,
        path: a.path,
        size: a.size,
        type: a.type
      })),
      signature_id: formData.value.signatureId
    }

    // Update existing draft or create new
    if (formData.value.draftId) {
      await api.put(`/secretary/memos/${formData.value.draftId}`, payload)
    } else {
      const response = await api.post('/secretary/memos/draft', payload)
      formData.value.draftId = response.data.data.id
    }

    lastSaved.value = new Date()
    
    Swal.fire({
      title: 'Draft Saved',
      text: 'Your memo has been saved as a draft',
      icon: 'success',
      timer: 1500,
      showConfirmButton: false
    })
  } catch (error) {
    console.error('Error saving draft:', error)
    Swal.fire('Error', 'Failed to save draft', 'error')
  } finally {
    isSaving.value = false
  }
}

const resetForm = () => {
  formData.value = {
    to: '',
    subject: '',
    signature: 'None',
    signatureId: null,
    department: 'Department',
    departmentId: null,
    priority: 'Medium',
    content: '',
    recipientId: null,
    recipientIds: [],
    selectedRecipients: [],
    recipientType: 'individual',
    attachments: [],
    draftId: null
  }
  scheduleData.value = null
  lastSaved.value = null
}

const closeModal = () => {
  // Auto-save before closing if there's content
  if (formData.value.subject || formData.value.content) {
    saveAsDraft()
  }
  emit('close')
}

const handleScheduleSave = (schedule) => {
  scheduleData.value = schedule
  showScheduleModal.value = false
}

// Auto-save functionality
const startAutoSave = () => {
  autoSaveTimer = setInterval(() => {
    if (formData.value.subject || formData.value.content) {
      saveAsDraft()
    }
  }, AUTO_SAVE_INTERVAL)
}

const stopAutoSave = () => {
  if (autoSaveTimer) {
    clearInterval(autoSaveTimer)
    autoSaveTimer = null
  }
}

onMounted(() => {
  fetchUsers()
  fetchDepartments()
  fetchSignatures()
  startAutoSave()

  // Auto-populate department for secretaries
  const roleName = (user.value?.role && typeof user.value.role === 'object') ? user.value.role.name : user.value?.role
  if (roleName === 'secretary' && user.value?.department_id) {
    formData.value.departmentId = user.value.department_id
    formData.value.department = user.value.department || 'My Department'
  }
})

onUnmounted(() => {
  stopAutoSave()
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
  if (newVal && filteredUsers.value.length > 0) {
    showUserSuggestions.value = true
  } else {
    showUserSuggestions.value = false
  }
})
</script>

<template>
  <Teleport to="body">
    <div v-if="isOpen" class="modal modal-open items-center justify-center">
    <div class="modal-box p-0 max-w-4xl w-full h-[85vh] overflow-hidden rounded-xl bg-base-100 shadow-2xl border border-base-300 flex flex-col">
      <!-- Fixed Header -->
      <div class="bg-primary px-6 py-4 flex items-center justify-between text-primary-content shrink-0 z-10">
        <div class="flex items-center gap-3">
          <h3 class="text-xl font-bold tracking-tight uppercase">Compose Memo</h3>
          <span v-if="formData.draftId" class="badge badge-sm badge-warning">Draft</span>
          <span v-if="lastSaved" class="text-xs opacity-70">Last saved: {{ lastSaved.toLocaleTimeString() }}</span>
        </div>
        <button @click="closeModal" class="btn btn-ghost btn-sm btn-circle text-primary-content hover:bg-white/10">
          <X :size="20" />
        </button>
      </div>

      <!-- Fixed Metadata Section (Not Scrollable) -->
      <div class="px-10 pt-8 pb-4 space-y-6 shrink-0 bg-base-100">
        <!-- To Field (Inline) -->
        <div class="flex items-center gap-4 group">
          <label class="w-20 text-xs font-black text-base-content/50 uppercase tracking-[0.2em] shrink-0">TO</label>
          <div class="relative flex-1 flex flex-wrap items-center gap-2">
            <!-- Selected Recipients Chips -->
            <div v-for="recipient in formData.selectedRecipients" :key="recipient.id" class="badge badge-primary gap-2 py-3 px-3">
              <span class="text-[10px] font-bold">{{ recipient.first_name }} {{ recipient.last_name }}</span>
              <button @click="removeRecipient(recipient.id)" class="hover:text-white/80 transition-colors">
                <X :size="10" />
              </button>
            </div>

            <input 
              v-model="formData.to"
              @focus="showUserSuggestions = true"
              type="text" 
              placeholder="Add recipient..." 
              class="input input-ghost focus:bg-transparent border-transparent focus:border-transparent focus:outline-none p-0 text-base flex-1 min-w-[150px] placeholder:text-base-content/20 font-medium"
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

      <!-- Attachments Section -->
      <div v-if="formData.attachments.length > 0" class="px-10 py-4 border-t border-base-200 bg-base-50">
        <div class="flex items-center gap-2 mb-2">
          <Paperclip :size="16" class="opacity-60" />
          <span class="text-xs font-bold uppercase opacity-60">Attachments ({{ attachmentCount }})</span>
        </div>
        <div class="flex flex-wrap gap-2">
          <div 
            v-for="(attachment, index) in formData.attachments" 
            :key="attachment.id"
            class="flex items-center gap-2 bg-base-200 px-3 py-2 rounded-lg"
          >
            <FileText :size="16" class="opacity-60" />
            <div class="flex flex-col">
              <span class="text-xs font-medium max-w-[150px] truncate">{{ attachment.name }}</span>
              <span class="text-[10px] opacity-40">{{ formatFileSize(attachment.size) }}</span>
            </div>
            <button @click="removeAttachment(index)" class="btn btn-ghost btn-xs btn-circle text-error">
              <X :size="14" />
            </button>
          </div>
        </div>
      </div>

      <!-- Footer (Fixed) -->
      <div class="px-10 py-6 bg-base-100 border-t border-base-200 flex items-center justify-between shrink-0">
        <div class="flex items-center gap-4">
          <input type="file" ref="fileInput" class="hidden" @change="handleFileUpload" multiple />
          <button 
            @click="triggerFileInput" 
            class="btn btn-ghost btn-md btn-square rounded-xl hover:bg-base-200 relative" 
            :class="{ 'text-primary bg-primary/10': formData.attachments.length > 0 }"
            title="Attach Files (Multiple allowed)"
            :disabled="isUploading"
          >
            <Paperclip :size="20" :class="{ 'opacity-100': formData.attachments.length > 0, 'opacity-60': formData.attachments.length === 0 }" />
            <span v-if="isUploading" class="absolute -top-1 -right-1 loading loading-sm loading-primary"></span>
          </button>
          
          <button 
            @click="showScheduleModal = true" 
            class="btn btn-ghost btn-md bg-base-100 border border-base-300 hover:border-primary/50 hover:bg-primary/5 gap-3 px-6 font-black text-[10px] uppercase tracking-widest rounded-xl transition-all"
            :class="{ 'bg-primary/10 border-primary': scheduleData }"
          >
            <Calendar :size="18" class="opacity-60" />
            Schedule
            <span v-if="scheduleData" class="badge badge-primary badge-xs">✓</span>
          </button>
          
          <button 
            @click="saveAsDraft"
            class="btn btn-ghost btn-md border border-base-300 hover:border-primary/50 hover:bg-primary/5 gap-3 px-6 font-black text-[10px] uppercase tracking-widest rounded-xl transition-all"
            :disabled="isSaving"
          >
            <Loader2 v-if="isSaving" :size="16" class="animate-spin" />
            <span v-else>Save Draft</span>
          </button>
        </div>

        <div class="flex items-center gap-4">
          <button @click="closeModal" class="btn btn-ghost btn-md px-6 font-black text-[10px] uppercase tracking-[0.2em] opacity-40 hover:opacity-100 transition-opacity">Cancel</button>
          <button @click="showPreviewModal = true" class="btn btn-ghost btn-md border border-base-300 hover:border-primary/50 hover:bg-primary/5 px-8 font-black text-[10px] uppercase tracking-[0.2em] rounded-xl transition-all">Preview</button>
          <button 
            @click="handleSend" 
            class="btn btn-primary btn-md px-12 text-white font-black text-[11px] uppercase tracking-[0.25em] shadow-2xl shadow-primary/40 rounded-xl hover:scale-[1.02] active:scale-[0.98] transition-all"
            :disabled="!canSubmit"
          >
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
          <div class="flex gap-4">
            <span class="w-16 font-black text-[10px] uppercase opacity-40">TO:</span> 
            <span class="font-bold underline uppercase flex flex-wrap gap-x-2">
              <template v-if="formData.selectedRecipients.length > 0">
                <span v-for="(r, i) in formData.selectedRecipients" :key="r.id">
                  {{ r.first_name }} {{ r.last_name }}{{ i < formData.selectedRecipients.length - 1 ? ',' : '' }}
                </span>
              </template>
              <template v-else-if="formData.recipientType === 'department'">
                {{ formData.department }}
              </template>
              <template v-else>
                RECIPIENT
              </template>
            </span>
          </div>
          <div class="flex gap-4"><span class="w-16 font-black text-[10px] uppercase opacity-40">FROM:</span> <span class="font-bold uppercase">ADMIN / {{ formData.department }}</span></div>
          <div class="flex gap-4"><span class="w-16 font-black text-[10px] uppercase opacity-40">SUBJECT:</span> <span class="font-bold uppercase">{{ formData.subject || '(NO SUBJECT)' }}</span></div>
        </div>

        <!-- Memo Content -->
        <div class="py-10 text-sm leading-relaxed min-h-[200px] whitespace-pre-wrap font-medium">
          {{ formData.content || 'No content provided...' }}
        </div>

        <!-- Attachments Preview -->
        <div v-if="formData.attachments.length > 0" class="border-t border-base-200 pt-4">
          <p class="text-[10px] font-bold uppercase opacity-40 mb-2">Attachments:</p>
          <div class="flex flex-wrap gap-2">
            <div v-for="attachment in formData.attachments" :key="attachment.id" class="flex items-center gap-2 bg-base-100 px-3 py-2 rounded">
              <Paperclip :size="12" class="opacity-60" />
              <span class="text-xs">{{ attachment.name }}</span>
            </div>
          </div>
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
  </Teleport>
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
