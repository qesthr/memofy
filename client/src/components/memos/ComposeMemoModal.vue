<script setup>
import { ref, computed, onMounted, watch, onUnmounted } from 'vue'
import { X, Paperclip, Calendar, Eye, Send, Search, Trash2, FileText, Loader2, Check, Users } from 'lucide-vue-next'
import api from '@/services/api'
import ScheduleMemoModal from './ScheduleMemoModal.vue'
import { useAuth } from '@/composables/useAuth'
import Swal from 'sweetalert2'
import MemoPdfTemplate from './MemoPdfTemplate.vue'

const { user } = useAuth()

const props = defineProps({
  isOpen: Boolean,
  initialData: Object
})

const emit = defineEmits(['close', 'send'])

const formData = ref({
  to: '',
  subject: '',

  department: 'Department',
  departmentId: null,
  priority: 'Medium',
  content: '',
  recipientId: null, // Keep for backward compatibility/single selection
  recipientIds: [], // For multiple selection
  selectedRecipients: [], // For UI chips
  recipientType: 'individual', // individual or department
  attachments: [] // Array of uploaded files
})

const scheduleData = ref(null)
const showScheduleModal = ref(false)
const showAttachmentsExpanded = ref(false) // Collapsible attachments state

const users = ref([])
const showUserSuggestions = ref(false)
const isLoadingUsers = ref(false)
const recipientSearch = ref('')

// Department members
const departmentMembers = ref([])
const showMembersDropdown = ref(false)
const memberSearch = ref('')
const isLoadingMembers = ref(false)

const departments = ref([])

const showPreviewModal = ref(false)
const fileInput = ref(null)
const isUploading = ref(false)
const isSubmitting = ref(false)



const priorities = [
  { label: 'Low', color: 'bg-info' },
  { label: 'Medium', color: 'bg-warning' },
  { label: 'High', color: 'bg-error' }
]

// Computed
const canSubmit = computed(() => {
  return formData.value.subject && formData.value.subject.trim() !== '' &&
         formData.value.content && formData.value.content.trim() !== '' &&
         (formData.value.recipientIds.length > 0 || formData.value.departmentId) &&
         !isUploading.value && !isSubmitting.value
})

const attachmentCount = computed(() => formData.value.attachments.length)

// Filtered department members for dropdown
const filteredMembers = computed(() => {
  if (!memberSearch.value) return departmentMembers.value
  const search = memberSearch.value.toLowerCase()
  return departmentMembers.value.filter(m => 
    `${m.first_name} ${m.last_name}`.toLowerCase().includes(search) ||
    m.email.toLowerCase().includes(search)
  )
})



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

const fetchDepartmentMembers = async () => {
  try {
    isLoadingMembers.value = true
    const response = await api.get('/departments/members')
    departmentMembers.value = response.data.data || []
  } catch (error) {
    console.error('Error fetching department members:', error)
    departmentMembers.value = []
  } finally {
    isLoadingMembers.value = false
  }
}

const selectMember = (member) => {
  // Add member as recipient
  if (!formData.value.recipientIds.includes(member.id)) {
    formData.value.recipientIds.push(member.id)
    formData.value.selectedRecipients.push(member)
  }
  memberSearch.value = ''
  showMembersDropdown.value = false
}

const getRoleLabel = (role) => {
  if (typeof role === 'object' && role?.name) {
    return role.name.charAt(0).toUpperCase() + role.name.slice(1)
  }
  if (typeof role === 'string') {
    return role.charAt(0).toUpperCase() + role.slice(1)
  }
  return 'Member'
}



const filteredUsers = computed(() => {
  if (!recipientSearch.value) return []
  const search = recipientSearch.value.toLowerCase()
  return users.value.filter(u => 
    (`${u.first_name} ${u.last_name}`.toLowerCase().includes(search) ||
    u.email.toLowerCase().includes(search)) &&
    !formData.value.recipientIds.includes(u.id) // Exclude already selected
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
  recipientSearch.value = ''
  showUserSuggestions.value = false
}

const removeRecipient = (userId) => {
  formData.value.recipientIds = formData.value.recipientIds.filter(id => id !== userId)
  formData.value.selectedRecipients = formData.value.selectedRecipients.filter(r => r.id !== userId)
}

const selectDepartment = (dept) => {
  formData.value.department = dept.name
  formData.value.departmentId = dept.id
  // Clear individual recipients when department is selected
  formData.value.recipientIds = []
  formData.value.selectedRecipients = []
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

// Helper function to check if attachment is an image
const isImageAttachment = (attachment) => {
  const imageTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/svg+xml', 'image/webp']
  const imageExtensions = ['.jpg', '.jpeg', '.png', '.gif', '.svg', '.webp']
  
  // Check by type
  if (attachment.type && imageTypes.includes(attachment.type)) {
    return true
  }
  // Check by file_type (server response)
  if (attachment.file_type && imageTypes.includes(attachment.file_type)) {
    return true
  }
  // Check by extension
  if (attachment.name) {
    const ext = attachment.name.toLowerCase().substring(attachment.name.lastIndexOf('.'))
    if (imageExtensions.includes(ext)) return true
  }
  
  return false
}



const triggerFileInput = () => {
  if (fileInput.value) {
    fileInput.value.click()
  }
}

const handleSend = async () => {
  if (!canSubmit.value || isSubmitting.value) {
    if (!canSubmit.value && !isSubmitting.value) {
      Swal.fire({
        title: 'Incomplete Memo',
        text: 'Please fill in all required fields',
        icon: 'warning',
        confirmButtonText: 'OK'
      })
    }
    return
  }

  try {
    isSubmitting.value = true
    const priorityMap = {
      'Low': 'low',
      'Medium': 'medium',
      'High': 'high'
    }

    const payload = {
      recipient_ids: formData.value.recipientIds.length > 0 ? formData.value.recipientIds : [],
      department_id: formData.value.departmentId || null,
      subject: formData.value.subject,
      message: formData.value.content,
      priority: priorityMap[formData.value.priority] || 'normal',
      attachments: formData.value.attachments.map(a => ({
        name: a.name,
        path: a.path,
        size: a.size,
        type: a.type,
        url: a.url
      })),
      is_draft: false,

    }

    // Add schedule data if present
    if (scheduleData.value) {
      if (scheduleData.value.startDate && scheduleData.value.startTime) {
        payload.scheduled_send_at = `${scheduleData.value.startDate}T${scheduleData.value.startTime}`
        payload.schedule_end_at = `${scheduleData.value.endDate}T${scheduleData.value.endTime}`
        payload.all_day_event = scheduleData.value.allDay
      }
      
      if (scheduleData.value.deadlineDate) {
        payload.deadline_at = `${scheduleData.value.deadlineDate}${scheduleData.value.deadlineTime ? 'T' + scheduleData.value.deadlineTime : ''}`
      }
    }

    const rawRole = (user.value?.role && typeof user.value.role === 'object') ? user.value.role.name : user.value?.role
    const roleName = String(rawRole || '').toLowerCase()
    
    let endpoint = '/secretary/memos/submit-for-approval'
    let successTitle = 'Submitted for Approval!'
    let successText = 'Your memo has been submitted to Admin for approval before distribution.'
    
    if (roleName !== 'secretary') {
      endpoint = '/memos'
      successTitle = 'Memo Sent!'
      successText = 'Your memo has been sent successfully.'
    }

    const response = await api.post(endpoint, payload)
    
    emit('send', response.data)
    resetForm()
    closeModal()
    
    Swal.fire({
      title: successTitle,
      text: successText,
      icon: 'success',
      confirmButtonText: 'OK'
    })
  } catch (error) {
    console.error('Error sending memo:', error)
    Swal.fire('Error', error.response?.data?.message || 'Failed to submit memo', 'error')
  } finally {
    isSubmitting.value = false
  }
}



const resetForm = () => {
  formData.value = {
    to: '',
    subject: '',

    department: 'Department',
    departmentId: null,
    priority: 'Medium',
    content: '',
    recipientId: null,
    recipientIds: [],
    selectedRecipients: [],
    recipientType: 'individual',
    attachments: []
  }
  recipientSearch.value = ''
  scheduleData.value = null
}

const closeModal = async () => {

  emit('close')
}

const handleScheduleSave = (schedule) => {
  scheduleData.value = schedule
  showScheduleModal.value = false
}



onMounted(() => {
  fetchUsers()
  fetchDepartments()
  
  // Fetch department members for secretary
  const roleName = (user.value?.role && typeof user.value.role === 'object') ? user.value.role.name : user.value?.role
  if (roleName === 'secretary') {
    fetchDepartmentMembers()
  }

  // Auto-populate department for secretaries
  if (roleName === 'secretary' && user.value?.department_id) {
    formData.value.departmentId = user.value.department_id
    formData.value.department = user.value.department || 'My Department'
  }
})



watch(() => props.initialData, (newVal) => {
  if (newVal) {
    const data = { ...newVal }
    
    // Normalize recipient data
    if (data.recipient_ids && Array.isArray(data.recipient_ids)) {
      data.recipientIds = [...data.recipient_ids]
    } else if (data.recipient_id) {
      data.recipientIds = [data.recipient_id]
    } else {
      data.recipientIds = data.recipientIds || []
    }

    // Populate selectedRecipients from users list if possible
    if (data.recipientIds.length > 0 && users.value.length > 0) {
      data.selectedRecipients = users.value.filter(u => data.recipientIds.includes(u.id))
    }

    // Handle other fields from backend

    if (data.message) data.content = data.message
    
    formData.value = {
      ...data,
      priority: data.priority ? (data.priority.charAt(0).toUpperCase() + data.priority.slice(1).toLowerCase()) : 'Medium'
    }

    // Ensure attachments have unique IDs for Vue keys if they don't have them
    if (formData.value.attachments && Array.isArray(formData.value.attachments)) {
      formData.value.attachments = formData.value.attachments.map(a => ({
        ...a,
        id: a.id || (Date.now() + Math.random())
      }))
    }
  }
}, { immediate: true })

// Also watch users to populate selectedRecipients once users are loaded
watch(users, (newUsers) => {
  if (newUsers.length > 0 && formData.value.recipientIds.length > 0 && formData.value.selectedRecipients.length === 0) {
    formData.value.selectedRecipients = newUsers.filter(u => formData.value.recipientIds.includes(u.id))
  }
})

watch(() => recipientSearch.value, (newVal) => {
  if (newVal && filteredUsers.value.length > 0) {
    showUserSuggestions.value = true
  } else {
    showUserSuggestions.value = false
  }
})

// Refetch data when modal opens so new signatures/templates appear immediately
watch(() => props.isOpen, (val) => {
  if (val) {
    fetchUsers()
    fetchDepartments()
    
    // Fetch department members for secretary
    const roleName = (user.value?.role && typeof user.value.role === 'object') ? user.value.role.name : user.value?.role
    if (roleName === 'secretary') {
      fetchDepartmentMembers()
    }
  }
})
</script>

<template>
  <Teleport to="body">
    <div v-if="isOpen" class="modal modal-open z-99999 items-center justify-center">
    <div class="modal-box p-0 max-w-6xl w-[95vw] max-h-[95vh] h-[90vh] overflow-hidden rounded-xl bg-base-100 shadow-2xl border border-base-300 flex flex-col">
      <!-- Fixed Header -->
      <div class="bg-primary px-5 py-3 flex items-center justify-between text-primary-content shrink-0 z-10">
        <div class="flex items-center gap-2">
          <h3 class="text-lg font-bold tracking-tight uppercase">Compose Memo</h3>
        </div>
        <button @click="closeModal" class="btn btn-ghost btn-sm btn-circle text-primary-content hover:bg-white/10">
          <X :size="18" />
        </button>
      </div>

      <!-- Fixed Metadata Section (Compact) -->
      <div class="px-5 py-3 space-y-3 shrink-0 bg-base-100 border-b border-base-200">
        <!-- To Field (Compact Inline) -->
        <div class="flex items-start gap-2 group min-h-[32px]">
          <label class="w-12 text-[10px] font-black text-base-content/50 uppercase tracking-wider shrink-0 pt-2">TO</label>
          <div class="relative flex-1 flex flex-wrap items-center gap-1.5 pt-0.5">
            <!-- Selected Recipients Chips (Compact) -->
            <div v-for="recipient in formData.selectedRecipients" :key="recipient.id" class="badge badge-primary gap-1 py-1 px-2 h-auto min-h-[22px]">
              <span class="text-[9px] font-bold py-0.5">{{ recipient.first_name }} {{ recipient.last_name }}</span>
              <button @click="removeRecipient(recipient.id)" class="hover:text-white/80 transition-colors">
                <X :size="10" />
              </button>
            </div>

            <input 
              v-model="recipientSearch"
              @focus="showUserSuggestions = true"
              type="text" 
              placeholder="Add recipient..." 
              class="input input-ghost focus:bg-transparent border-transparent focus:border-transparent focus:outline-none p-0 h-8 text-sm flex-1 min-w-[120px] placeholder:text-base-content/20 font-medium"
            />
            <!-- Autocomplete Suggestion -->
            <div v-if="showUserSuggestions && filteredUsers.length > 0" class="absolute left-0 top-full mt-2 w-full max-w-md bg-base-100 shadow-2xl border border-base-300 rounded-xl z-[100] overflow-hidden">
              <ul class="menu p-1.5">
                <li v-for="user in filteredUsers" :key="user.id">
                  <button @click="selectUser(user)" class="flex items-center gap-3 py-2.5 px-3 hover:bg-base-200 rounded-lg transition-colors">
                    <div class="w-8 h-8 rounded-full bg-primary/10 text-primary flex items-center justify-center font-bold text-xs uppercase">
                      {{ user.first_name[0] }}{{ user.last_name[0] }}
                    </div>
                    <div class="flex flex-col items-start overflow-hidden">
                      <span class="font-bold text-xs truncate w-full">{{ user.first_name }} {{ user.last_name }}</span>
                      <span class="text-[9px] opacity-40 leading-none truncate w-full">{{ user.email }}</span>
                    </div>
                  </button>
                </li>
              </ul>
            </div>
          </div>
        </div>

        <div class="divider my-0 h-px bg-base-200 opacity-50"></div>

        <!-- Subject Field (Compact Inline) -->
        <div class="flex items-center gap-2 group min-h-[32px]">
          <label class="w-12 text-[10px] font-black text-base-content/50 uppercase tracking-wider shrink-0">SUBJECT</label>
          <input 
            v-model="formData.subject"
            type="text" 
            placeholder="Type your subject here..." 
            class="input input-ghost focus:bg-transparent border-transparent focus:border-transparent focus:outline-none p-0 h-8 text-sm w-full placeholder:text-base-content/20 font-bold"
          />
        </div>

        <div class="divider my-0 h-px bg-base-200 opacity-50"></div>

        <!-- Controls Row (Compact) -->
        <div class="flex flex-wrap items-center gap-2 py-1">


          <!-- Department Dropdown (Compact) -->
          <div class="dropdown">
            <div tabindex="0" role="button" class="btn btn-xs bg-base-200 border-none px-2 rounded-md font-bold text-[9px] uppercase tracking-wider hover:bg-base-300 text-base-content/70">
              {{ formData.department }}
              <span class="ml-1 opacity-40 text-[7px]">▼</span>
            </div>
            <ul tabindex="0" class="dropdown-content z-50 menu p-2 shadow-2xl bg-base-100 border border-base-300 rounded-lg w-48 mt-1">
              <li v-for="dept in filteredDepartments" :key="dept.id" @click="selectDepartment(dept)">
                <a class="font-bold flex justify-between text-xs">
                  {{ dept.name }}
                  <span class="text-[8px] opacity-40">{{ dept.code }}</span>
                </a>
              </li>
            </ul>
          </div>
          
          <!-- Priority Selector (Compact) -->
          <div class="dropdown">
            <div tabindex="0" role="button" class="btn btn-xs bg-base-200 border-none px-2 rounded-md font-bold text-[9px] uppercase tracking-wider hover:bg-base-300 text-base-content/70">
              <span class="w-1.5 h-1.5 rounded-full mr-1" :class="priorities.find(p => p.label === formData.priority)?.color || 'bg-base-300'"></span>
              {{ formData.priority }}
              <span class="ml-1 opacity-40 text-[7px]">▼</span>
            </div>
            <ul tabindex="0" class="dropdown-content z-50 menu p-2 shadow-2xl bg-base-100 border border-base-300 rounded-lg w-28 mt-1">
              <li v-for="p in priorities" :key="p.label" @click="formData.priority = p.label">
                <a class="font-bold flex items-center gap-2 text-xs">
                  <span class="w-1.5 h-1.5 rounded-full" :class="p.color"></span>
                  {{ p.label }}
                </a>
              </li>
            </ul>
          </div>
          
          <!-- Members Dropdown (For Secretary) -->
          <div v-if="departmentMembers.length > 0" class="dropdown">
            <div tabindex="0" role="button" class="btn btn-xs bg-primary/10 border-none px-2 rounded-md font-bold text-[9px] uppercase tracking-wider hover:bg-primary/20 text-primary">
              <Users :size="10" class="mr-1" />
              Members
              <span class="ml-1 bg-primary text-primary-content rounded-full px-1 text-[7px]">{{ departmentMembers.length }}</span>
            </div>
            <div tabindex="0" class="dropdown-content z-50 shadow-2xl bg-base-100 border border-base-300 rounded-lg w-72 mt-1 p-2">
              <!-- Search Input -->
              <div class="relative mb-2">
                <Search :size="12" class="absolute left-2 top-1/2 -translate-y-1/2 opacity-40" />
                <input 
                  v-model="memberSearch"
                  type="text" 
                  placeholder="Search members..." 
                  class="input input-sm input-bordered w-full pl-7 text-xs focus:outline-none focus:border-primary"
                />
              </div>
              
              <!-- Loading State -->
              <div v-if="isLoadingMembers" class="py-4 text-center">
                <span class="loading loading-spinner loading-sm"></span>
              </div>
              
              <!-- Members List -->
              <ul v-else-if="filteredMembers.length > 0" class="menu p-0 max-h-60 overflow-y-auto">
                <li v-for="member in filteredMembers" :key="member.id">
                  <button 
                    @click="selectMember(member)" 
                    class="flex items-center gap-2 py-2 px-2 hover:bg-base-200 rounded-lg transition-colors"
                    :class="{ 'opacity-50': formData.recipientIds.includes(member.id) }"
                  >
                    <div class="w-8 h-8 rounded-full bg-primary/10 text-primary flex items-center justify-center font-bold text-[10px] uppercase overflow-hidden">
                      <img v-if="member.profile_picture" :src="member.profile_picture" class="w-full h-full object-cover" />
                      <span v-else>{{ member.first_name?.[0] }}{{ member.last_name?.[0] }}</span>
                    </div>
                    <div class="flex-1 text-left">
                      <div class="font-bold text-xs">{{ member.first_name }} {{ member.last_name }}</div>
                      <div class="text-[9px] opacity-50">{{ getRoleLabel(member.role) }}</div>
                    </div>
                    <Check v-if="formData.recipientIds.includes(member.id)" :size="14" class="text-success" />
                  </button>
                </li>
              </ul>
              
              <!-- Empty State -->
              <div v-else class="py-4 text-center text-xs opacity-50">
                No members found
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Content Field (Scrollable) -->
      <div class="flex-1 overflow-y-auto px-10 py-6 custom-scrollbar bg-base-100">
        <textarea 
          v-model="formData.content"
          placeholder="Start typing your memo content here..." 
          class="textarea textarea-ghost focus:bg-transparent border-transparent focus:border-transparent focus:outline-none p-0 w-full min-h-full text-base resize-none placeholder:text-base-content/20 font-medium leading-relaxed"
        ></textarea>
      </div>

      <!-- Attachments Section (Collapsible/Compact) -->
      <div v-if="formData.attachments.length > 0" class="px-5 py-2 border-t border-base-200 bg-base-50/50">
        <div 
          @click="showAttachmentsExpanded = !showAttachmentsExpanded" 
          class="flex items-center gap-2 cursor-pointer hover:text-primary transition-colors"
        >
          <Paperclip :size="12" class="opacity-60" />
          <span class="text-[10px] font-bold uppercase opacity-60">Attachments ({{ attachmentCount }})</span>
          <span class="text-[10px] opacity-40">{{ showAttachmentsExpanded ? '▲' : '▼' }}</span>
        </div>
        <!-- Collapsible Content -->
        <div v-if="showAttachmentsExpanded" class="flex flex-wrap gap-2 mt-2">
          <template v-for="(attachment, index) in formData.attachments" :key="attachment.id">
            <div class="flex items-center gap-1.5 bg-base-200/80 px-2 py-1 rounded group">
              <FileText :size="12" class="opacity-60" />
              <a :href="attachment.url" target="_blank" class="text-[10px] font-medium max-w-[120px] truncate hover:text-primary transition-colors">
                {{ attachment.name }}
              </a>
              <span class="text-[8px] opacity-40">{{ formatFileSize(attachment.size) }}</span>
              <button @click.stop="removeAttachment(index)" class="text-error opacity-0 group-hover:opacity-100 transition-opacity">
                <X :size="10" />
              </button>
            </div>
          </template>
        </div>
      </div>

      <!-- Footer (Compact) -->
      <div class="px-5 py-3 bg-base-100 border-t border-base-200 flex items-center justify-between shrink-0 relative z-20">
        <div class="flex items-center gap-2">
          <input type="file" ref="fileInput" class="hidden" @change="handleFileUpload" multiple />
          <button 
            @click="triggerFileInput" 
            class="btn btn-ghost btn-sm btn-square rounded-lg hover:bg-base-200 relative" 
            :class="{ 'text-primary': formData.attachments.length > 0 }"
            title="Attach Files"
            :disabled="isUploading"
          >
            <Paperclip :size="16" :class="{ 'opacity-100': formData.attachments.length > 0, 'opacity-50': formData.attachments.length === 0 }" />
            <span v-if="isUploading" class="absolute -top-0.5 -right-0.5 loading loading-xs loading-primary"></span>
          </button>
          
          <button 
            @click="showScheduleModal = true" 
            class="btn btn-ghost btn-sm gap-1 px-2 font-bold text-[9px] uppercase tracking-wider rounded-lg transition-all"
            :class="{ 'text-primary': scheduleData }"
            title="Schedule"
          >
            <Calendar :size="14" />
            <span v-if="scheduleData" class="badge badge-primary badge-xs p-0.5">✓</span>
          </button>
          

        </div>

        <div class="flex items-center gap-2">
          <button @click="closeModal" class="btn btn-ghost btn-sm px-3 font-bold text-[9px] uppercase opacity-40 hover:opacity-100 transition-opacity">Cancel</button>
          <button @click="showPreviewModal = true" class="btn btn-ghost btn-sm border border-base-300 hover:border-primary/50 px-3 font-bold text-[9px] uppercase rounded-lg transition-all">Preview</button>
          <button 
            @click="handleSend" 
            class="btn btn-primary btn-sm px-6 text-white font-bold text-[10px] uppercase tracking-wider shadow-lg shadow-primary/30 rounded-lg hover:scale-[1.02] active:scale-[0.98] transition-all disabled:opacity-70"
            :disabled="!canSubmit || isSubmitting"
          >
            <Loader2 v-if="isSubmitting" :size="12" class="animate-spin mr-1.5" />
            {{ isSubmitting ? 'Composing...' : 'Compose' }}
          </button>
        </div>
      </div>
    </div>
    <div class="modal-backdrop bg-base-100/5 backdrop-blur-3xl transition-all duration-700" @click="closeModal"></div>
    </div>

    <!-- Schedule Modal -->
    <ScheduleMemoModal 
      :isOpen="showScheduleModal" 
      :initialSchedule="scheduleData || {}"
      @close="showScheduleModal = false"
      @save="handleScheduleSave"
    />

    <!-- Preview Modal - A4 Format -->
    <div v-if="showPreviewModal" class="modal modal-open z-[99999]">
      <div class="modal-box max-w-4xl w-[95vw] bg-gray-100 p-0 rounded-none shadow-2xl flex flex-col relative overflow-hidden">
        <!-- Close Button -->
        <button @click="showPreviewModal = false" class="absolute top-4 right-4 btn btn-ghost btn-circle btn-sm z-50 bg-white/80 hover:bg-white shadow-sm">
          <X :size="20" />
        </button>
        
        <!-- A4 Document Container -->
        <div class="flex-1 overflow-y-auto custom-scrollbar">
          <MemoPdfTemplate 
            :memo="formData" 
            :sender="user" 
            :isPreview="true" 
          />
        </div>

        <!-- Action Buttons -->
        <div class="shrink-0 px-6 py-4 bg-white border-t border-gray-200 flex justify-end items-center">
          <span class="text-xs text-base-content/40 mr-auto font-medium italic">Preview Mode - Exactly as the recipient will see it</span>
          <button @click="showPreviewModal = false" class="btn btn-primary btn-sm px-8 font-bold text-[10px] uppercase tracking-widest rounded-lg shadow-lg shadow-primary/20">Back to Edit</button>
        </div>
      </div>
      <div class="modal-backdrop bg-black/60 backdrop-blur-md" @click="showPreviewModal = false"></div>
    </div>
  </Teleport>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background-color: rgba(0, 0, 0, 0.08);
  border-radius: 10px;
}
[data-theme='dark'] .custom-scrollbar::-webkit-scrollbar-thumb {
  background-color: rgba(255, 255, 255, 0.08);
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
