<script setup>
import { ref, onMounted, computed } from 'vue'
import { X, Save, Eraser, Undo2, Plus, Trash2, Mail, Users, FileText, Check } from 'lucide-vue-next'
import SignaturePad from 'signature_pad'
import api from '@/services/api'
import Swal from 'sweetalert2'
import { useAuth } from '@/composables/useAuth'

const { user, can } = useAuth()
const userRole = computed(() => (user.value?.role && typeof user.value.role === 'object') ? user.value.role.name : user.value?.role)

const props = defineProps({
  isOpen: Boolean
})

const emit = defineEmits(['close', 'apply'])

const activeTab = ref('templates') // templates, signatures, departments

const handleTabClick = (tab) => {
  if (tab === 'templates' && !can('template.manage')) {
    Swal.fire({
      title: 'Restricted',
      text: 'Restricted by Admin',
      icon: 'warning',
      confirmButtonColor: '#fb923c'
    })
    return
  }

  if (tab === 'signatures' && !can('signature.manage')) {
    Swal.fire({
      title: 'Restricted',
      text: 'Restricted by Admin',
      icon: 'warning',
      confirmButtonColor: '#fb923c'
    })
    return
  }
  
  if (tab === 'departments' && !can('department.manage')) {
    Swal.fire({
      title: 'Restricted',
      text: 'Restricted by Admin',
      icon: 'warning',
      confirmButtonColor: '#fb923c'
    })
    return
  }
  activeTab.value = tab
}
const templates = ref([])
const signatures = ref([])
const departments = ref([])
const isLoading = ref(false)

// Signature Pad State
const signaturePadRef = ref(null)
const signaturePadCanvas = ref(null)
const newSignatureName = ref('')
const signatureMode = ref('draw') // draw or upload
const uploadedSignature = ref(null)
const uploadedSignatureName = ref('')
const isFullscreen = ref(false)

// Template State
const newTemplate = ref({
  name: '',
  signature_id: null,
  department_id: null,
  priority: 'Medium',
  content: ''
})

// Department State
const newDepartment = ref({
  name: '',
  code: '',
  description: ''
})

const fetchInitialData = async () => {
  try {
    isLoading.value = true
    const [templatesRes, signaturesRes, departmentsRes] = await Promise.all([
      api.get('/memo-templates'),
      api.get('/signatures'),
      api.get('/departments')
    ])
    templates.value = templatesRes.data.data
    signatures.value = signaturesRes.data.data
    departments.value = departmentsRes.data.data
  } catch (error) {
    console.error('Error fetching customization data:', error)
  } finally {
    isLoading.value = false
  }
}

const initSignaturePad = () => {
  if (signaturePadCanvas.value) {
    signaturePadRef.value = new SignaturePad(signaturePadCanvas.value, {
      backgroundColor: 'rgb(255, 255, 255)',
      penColor: 'rgb(0, 0, 0)'
    })
    resizeCanvas()
  }
}

const resizeCanvas = (isForFullscreen = false) => {
  const canvas = isForFullscreen ? document.getElementById('fullscreenCanvas') : signaturePadCanvas.value
  if (!canvas) return
  const ratio = Math.max(window.devicePixelRatio || 1, 1)
  canvas.width = canvas.offsetWidth * ratio
  canvas.height = canvas.offsetHeight * ratio
  canvas.getContext('2d').scale(ratio, ratio)
  
  if (signaturePadRef.value && !isForFullscreen) {
     signaturePadRef.value.clear()
  }
}

const toggleFullscreen = () => {
  if (!isFullscreen.value) {
    // Before entering fullscreen, capture existing data if any
    const existingData = !signaturePadRef.value.isEmpty() ? signaturePadRef.value.toData() : null
    isFullscreen.value = true
    setTimeout(() => {
      const fsCanvas = document.getElementById('fullscreenCanvas')
      if (fsCanvas) {
        const fsPad = new SignaturePad(fsCanvas, {
          backgroundColor: 'rgb(255, 255, 255)',
          penColor: 'rgb(0, 0, 0)'
        })
        
        // Match dimensions
        const ratio = Math.max(window.devicePixelRatio || 1, 1)
        fsCanvas.width = fsCanvas.offsetWidth * ratio
        fsCanvas.height = fsCanvas.offsetHeight * ratio
        fsCanvas.getContext('2d').scale(ratio, ratio)
        
        if (existingData) {
          // Attempt to scale and redraw? SignaturePad.fromData is complex with scaling.
          // For now just restore.
          fsPad.fromData(existingData)
        }
        
        // We actually want to keep using the same ref if possible, but two canvases are tricky.
        // Let's swap the ref temporarily or redirect draw operations.
        // Strategy: Sync data back on close.
        window.tempFsPad = fsPad
      }
    }, 100)
  } else {
    // Closing fullscreen
    if (window.tempFsPad) {
      const fsData = window.tempFsPad.toData()
      isFullscreen.value = false
      setTimeout(() => {
        if (fsData) {
          signaturePadRef.value.fromData(fsData)
        }
      }, 100)
    }
  }
}

const clearSignature = () => {
  signaturePadRef.value.clear()
}

const undoSignature = () => {
  const data = signaturePadRef.value.toData()
  if (data) {
    data.pop()
    signaturePadRef.value.fromData(data)
  }
}

const saveSignature = async () => {
  if (signatureMode.value === 'draw') {
    if (signaturePadRef.value.isEmpty()) {
      Swal.fire('Warning', 'Please provide a signature first', 'warning')
      return
    }
  } else {
    if (!uploadedSignature.value) {
      Swal.fire('Warning', 'Please upload a signature image first', 'warning')
      return
    }
  }

  if (!newSignatureName.value) {
    Swal.fire('Warning', 'Please enter a name for this signatory', 'warning')
    return
  }

  try {
    const signatureData = signatureMode.value === 'draw' 
      ? signaturePadRef.value.toDataURL() 
      : uploadedSignature.value

    await api.post('/signatures', {
      name: newSignatureName.value,
      signature_data: signatureData,
      is_default: signatures.value.length === 0
    })
    
    newSignatureName.value = ''
    uploadedSignature.value = null
    if (signaturePadRef.value) signaturePadRef.value.clear()
    await fetchInitialData()
    Swal.fire('Success', 'Signature saved successfully', 'success')
  } catch (error) {
    console.error('Save Signature Error:', error)
    Swal.fire('Error', 'Failed to save signature. Please try again.', 'error')
  }
}

const handleSignatureUpload = (event) => {
  const file = event.target.files[0]
  if (!file) return

  const reader = new FileReader()
  reader.onload = (e) => {
    uploadedSignature.value = e.target.result
  }
  reader.readAsDataURL(file)
}

const deleteSignature = async (id) => {
  const result = await Swal.fire({
    title: 'Are you sure?',
    text: 'You will not be able to recover this signature!',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes, delete it!'
  })

  if (result.isConfirmed) {
    try {
      await api.delete(`/signatures/${id}`)
      await fetchInitialData()
      Swal.fire('Deleted!', 'Signature has been deleted.', 'success')
    } catch (error) {
      Swal.fire('Error', 'Failed to delete signature', 'error')
    }
  }
}

const saveTemplate = async () => {
  if (!newTemplate.value.name) {
    Swal.fire('Warning', 'Please enter a template name', 'warning')
    return
  }

  try {
    await api.post('/memo-templates', newTemplate.value)
    newTemplate.value = {
      name: '',
      signature_id: null,
      department_id: null,
      priority: 'Medium',
      content: ''
    }
    await fetchInitialData()
    Swal.fire('Success', 'Template saved successfully', 'success')
  } catch (error) {
    Swal.fire('Error', 'Failed to save template', 'error')
  }
}

const applyTemplate = (template) => {
  emit('apply', {
    to: '', // Let user fill recipient
    subject: '', // Optional: could add to template
    signature: signatures.value.find(s => s.id === template.signature_id)?.name || 'None',
    department: departments.value.find(d => d.id === template.department_id)?.name || 'Department',
    priority: template.priority || 'Medium',
    content: template.content || '',
    template_id: template.id
  })
}

const deleteTemplate = async (template) => {
  const result = await Swal.fire({
    title: 'Delete Template?',
    text: `Are you sure you want to delete "${template.name}"?`,
    icon: 'warning',
    showCancelButton: true,
  })

  if (result.isConfirmed) {
    try {
      await api.delete(`/memo-templates/${template.id}`)
      await fetchInitialData()
    } catch (error) {
      Swal.fire('Error', 'Failed to delete template', 'error')
    }
  }
}

const saveDepartment = async () => {
  if (!newDepartment.value.name) {
    Swal.fire('Warning', 'Please enter a department name', 'warning')
    return
  }

  try {
    await api.post('/departments', newDepartment.value)
    newDepartment.value = { name: '', code: '', description: '' }
    await fetchInitialData()
    Swal.fire('Success', 'Department added successfully', 'success')
  } catch (error) {
    Swal.fire('Error', error.response?.data?.message || 'Failed to add department', 'error')
  }
}

const deleteDepartment = async (id) => {
  const result = await Swal.fire({
    title: 'Delete Department?',
    text: 'This may affect existing users and memos. Proceed?',
    icon: 'warning',
    showCancelButton: true,
    confirmButtonText: 'Yes, delete it'
  })

  if (result.isConfirmed) {
    try {
      await api.delete(`/departments/${id}`)
      await fetchInitialData()
      Swal.fire('Deleted!', 'Department has been removed.', 'success')
    } catch (error) {
      Swal.fire('Error', 'Failed to delete department', 'error')
    }
  }
}

onMounted(() => {
  fetchInitialData()
  window.addEventListener('resize', resizeCanvas)
})

const closeModal = () => {
  emit('close')
}

// Watch for tab change to init signature pad
import { watch } from 'vue'
watch(activeTab, (newTab) => {
  if (newTab === 'signatures') {
    setTimeout(initSignaturePad, 100)
  }
})
</script>

<template>
  <div v-if="isOpen" class="modal modal-open items-center justify-center">
    <div class="modal-box p-0 max-w-5xl w-full h-[85vh] overflow-hidden rounded-2xl bg-base-100 shadow-2xl border border-base-300 flex flex-col">
      <!-- Header -->
      <div class="bg-primary/5 px-8 py-6 flex items-center justify-between border-b border-base-200 shrink-0">
        <div>
          <h3 class="text-2xl font-black tracking-tight text-primary uppercase">Customize Memo</h3>
          <p class="text-xs font-bold text-base-content/40 mt-1 uppercase tracking-widest">Templates, Signatures & Departments</p>
        </div>
        <button @click="closeModal" class="btn btn-ghost btn-sm btn-circle hover:bg-primary/10 text-primary">
          <X :size="20" />
        </button>
      </div>

      <div class="flex-1 flex overflow-hidden">
        <!-- Sidebar Navigation -->
        <div class="w-64 bg-base-200/30 border-r border-base-200 p-4 flex flex-col gap-2">
          <button 
            @click="handleTabClick('templates')"
            class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all font-black text-[10px] uppercase tracking-widest"
            :class="activeTab === 'templates' ? 'bg-primary text-white shadow-lg shadow-primary/20' : 'hover:bg-base-200 text-base-content/60'"
          >
            <FileText :size="18" />
            Templates
          </button>
          <button 
            @click="handleTabClick('signatures')"
            class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all font-black text-[10px] uppercase tracking-widest"
            :class="activeTab === 'signatures' ? 'bg-primary text-white shadow-lg shadow-primary/20' : 'hover:bg-base-200 text-base-content/60'"
          >
            <Plus :size="18" />
            Signatures
          </button>
          <button 
            @click="handleTabClick('departments')"
            class="flex items-center gap-3 px-4 py-3 rounded-xl transition-all font-black text-[10px] uppercase tracking-widest"
            :class="activeTab === 'departments' ? 'bg-primary text-white shadow-lg shadow-primary/20' : 'hover:bg-base-200 text-base-content/60'"
          >
            <Users :size="18" />
            Departments
          </button>
        </div>

        <!-- Main Content Area -->
        <div class="flex-1 overflow-y-auto p-8 custom-scrollbar">
          
          <!-- Templates Tab -->
          <div v-if="activeTab === 'templates'" class="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
              <!-- Create New Template -->
              <div class="card bg-base-100 border border-base-200 shadow-sm p-6 space-y-4">
                <h4 class="text-sm font-black uppercase tracking-widest text-primary flex items-center gap-2">
                  <Plus :size="16" /> Add New Template
                </h4>
                
                <div class="space-y-4">
                  <div class="form-control">
                    <label class="label"><span class="label-text font-bold text-xs uppercase opacity-60">Template Name</span></label>
                    <input v-model="newTemplate.name" type="text" class="input input-bordered rounded-xl bg-base-200/30 border-none focus:outline-primary/20" placeholder="e.g., Weekly Dept Update" />
                  </div>

                  <div class="grid grid-cols-2 gap-4">
                    <div class="form-control">
                      <label class="label"><span class="label-text font-bold text-xs uppercase opacity-60">Default Signature</span></label>
                      <select v-model="newTemplate.signature_id" class="select select-bordered rounded-xl bg-base-200/30 border-none">
                        <option :value="null">None</option>
                        <option v-for="sig in signatures" :key="sig.id" :value="sig.id">{{ sig.name }}</option>
                      </select>
                    </div>
                    <div class="form-control">
                      <label class="label"><span class="label-text font-bold text-xs uppercase opacity-60">Default Dept.</span></label>
                      <select v-model="newTemplate.department_id" class="select select-bordered rounded-xl bg-base-200/30 border-none">
                        <option :value="null">None</option>
                        <option v-for="dept in departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
                      </select>
                    </div>
                  </div>

                  <div class="form-control">
                    <label class="label"><span class="label-text font-bold text-xs uppercase opacity-60">Priority</span></label>
                    <select v-model="newTemplate.priority" class="select select-bordered rounded-xl bg-base-200/30 border-none">
                      <option>Low</option>
                      <option>Medium</option>
                      <option>High</option>
                    </select>
                  </div>

                  <div class="form-control">
                    <label class="label"><span class="label-text font-bold text-xs uppercase opacity-60">Default Content</span></label>
                    <textarea v-model="newTemplate.content" class="textarea textarea-bordered rounded-xl bg-base-200/30 border-none min-h-[150px]" placeholder="Enter default memo text..."></textarea>
                  </div>

                  <button @click="saveTemplate" class="btn btn-primary w-full text-white rounded-xl font-black uppercase tracking-widest text-xs">
                    Save Template
                  </button>
                </div>
              </div>

              <!-- Existing Templates -->
              <div class="space-y-4">
                <h4 class="text-sm font-black uppercase tracking-widest text-primary flex items-center gap-2">
                  <FileText :size="16" /> Saved Templates
                </h4>
                
                <div v-if="templates.length === 0" class="bg-base-200/50 rounded-2xl p-12 text-center border-2 border-dashed border-base-300">
                  <p class="text-sm font-bold opacity-30">No templates found</p>
                </div>

                <div v-for="template in templates" :key="template.id" class="group relative bg-base-100 border border-base-200 p-4 rounded-2xl hover:border-primary/50 transition-all hover:shadow-xl hover:shadow-primary/5">
                  <div class="flex justify-between items-start mb-2">
                    <div>
                      <h5 class="font-black text-sm text-base-content/80">{{ template.name }}</h5>
                      <p class="text-[10px] opacity-40 font-bold uppercase tracking-wider mt-0.5">
                        {{ template.priority }} Priority • {{ template.department?.name || 'No Dept' }}
                      </p>
                    </div>
                    <div class="flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                      <button @click="deleteTemplate(template)" class="btn btn-ghost btn-xs text-error"><Trash2 :size="14" /></button>
                    </div>
                  </div>
                  <p class="text-xs line-clamp-2 opacity-60 italic mb-4">"{{ template.content || 'No content defined...' }}"</p>
                  <button @click="applyTemplate(template)" class="btn btn-primary btn-sm btn-block text-white rounded-xl font-black uppercase tracking-tighter text-[10px]">
                    Use This Template
                  </button>
                </div>
              </div>
            </div>
          </div>

          <!-- Signatures Tab -->
          <div v-if="activeTab === 'signatures'" class="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500 pb-10">
            <!-- Creation Area (Full Width) -->
            <div class="card bg-base-100 border border-base-200 shadow-sm p-8 space-y-6">
              <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
                <div>
                  <h4 class="text-lg font-black uppercase tracking-tighter text-primary flex items-center gap-2">
                    <Plus :size="20" /> Add Signature
                  </h4>
                  <p class="text-[10px] font-bold opacity-40 uppercase tracking-widest mt-1">Ready-made or digital pad</p>
                </div>

                <!-- Mode Toggle -->
                <div class="tabs tabs-boxed bg-base-200 p-1 rounded-xl shrink-0">
                  <button 
                    @click="signatureMode = 'draw'" 
                    class="tab tab-sm font-black uppercase tracking-widest text-[9px] px-6 rounded-lg transition-all"
                    :class="signatureMode === 'draw' ? 'bg-primary text-white shadow-md' : 'text-base-content/40 hover:text-base-content'"
                  >
                    Draw Pad
                  </button>
                  <button 
                    @click="signatureMode = 'upload'" 
                    class="tab tab-sm font-black uppercase tracking-widest text-[9px] px-6 rounded-lg transition-all"
                    :class="signatureMode === 'upload' ? 'bg-primary text-white shadow-md' : 'text-base-content/40 hover:text-base-content'"
                  >
                    Upload Image
                  </button>
                </div>
              </div>
              
              <div class="grid grid-cols-1 lg:grid-cols-12 gap-10 items-start">
                <!-- Inputs & Canvas -->
                <div class="lg:col-span-8 space-y-4">
                  <div class="form-control max-w-sm">
                    <label class="label"><span class="label-text font-bold text-xs uppercase opacity-60">Signatory Full Name</span></label>
                    <input v-model="newSignatureName" type="text" class="input input-bordered rounded-xl bg-base-200/30 border-none focus:ring-2 ring-primary/20" placeholder="e.g., Dr. John Doe" />
                  </div>

                  <!-- Expanded Draw Mode -->
                  <div v-if="signatureMode === 'draw'" class="relative w-full aspect-[2.5/1] bg-white border-2 border-base-200 rounded-3xl overflow-hidden cursor-crosshair shadow-inner group/pad">
                    <canvas ref="signaturePadCanvas" class="w-full h-full"></canvas>
                    <div class="absolute top-6 right-6 flex gap-3 opacity-0 group-hover/pad:opacity-100 transition-opacity">
                      <button @click="toggleFullscreen" class="btn btn-circle btn-sm btn-ghost bg-base-100/90 shadow-xl border border-base-200 hover:bg-primary/10 hover:text-primary transition-all" title="Maximize">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-maximize-2"><polyline points="15 3 21 3 21 9"/><polyline points="9 21 3 21 3 15"/><line x1="21" y1="3" x2="14" y2="10"/><line x1="3" y1="21" x2="10" y2="14"/></svg>
                      </button>
                      <button @click="undoSignature" class="btn btn-circle btn-sm btn-ghost bg-base-100/90 shadow-xl border border-base-200 hover:bg-primary/10 hover:text-primary transition-all" title="Undo"><Undo2 :size="16" /></button>
                      <button @click="clearSignature" class="btn btn-circle btn-sm btn-ghost bg-base-100/90 shadow-xl border border-base-200 hover:bg-error/10 hover:text-error transition-all" title="Clear"><Eraser :size="16" /></button>
                    </div>
                    <!-- Watermark -->
                    <div class="absolute bottom-6 left-8 text-[12px] font-black uppercase tracking-[0.4em] opacity-10 pointer-events-none select-none">
                      BUKSU MEMOFY OFFICIAL VERIFIED
                    </div>
                  </div>

                  <!-- Upload Mode -->
                  <div v-else class="flex flex-col items-center justify-center w-full aspect-[2.5/1] bg-base-200/30 border-2 border-dashed border-base-300 rounded-3xl overflow-hidden group transition-all hover:bg-base-200/50 hover:border-primary/30">
                    <div v-if="!uploadedSignature" class="text-center p-10 cursor-pointer w-full h-full flex flex-col items-center justify-center" @click="$refs.signatureFile.click()">
                      <div class="w-20 h-20 rounded-full bg-primary/10 text-primary flex items-center justify-center mb-4 group-hover:scale-110 transition-transform">
                        <Plus :size="32" />
                      </div>
                      <p class="font-black text-xs uppercase tracking-widest text-base-content/60">Drop image here or click to browse</p>
                      <p class="text-[10px] font-bold opacity-30 mt-2 uppercase">PNG, JPG, or SVG (Max 2MB)</p>
                      <input type="file" ref="signatureFile" class="hidden" accept="image/*" @change="handleSignatureUpload" />
                    </div>
                    <div v-else class="relative w-full h-full p-8 flex items-center justify-center bg-white">
                      <img :src="uploadedSignature" class="max-h-full object-contain mix-blend-multiply" />
                      <button @click="uploadedSignature = null" class="absolute top-4 right-4 btn btn-circle btn-xs btn-error text-white shadow-lg">
                        <X :size="14" />
                      </button>
                    </div>
                  </div>
                </div>

                <!-- Info & CTA -->
                <div class="lg:col-span-4 h-full flex flex-col justify-between py-2 border-l border-base-200 pl-10">
                  <div class="space-y-4">
                    <div class="bg-primary/5 p-4 rounded-2xl">
                      <h5 class="font-black text-[10px] uppercase tracking-widest text-primary mb-2">Signature Guidelines</h5>
                      <ul class="text-[10px] font-bold opacity-60 space-y-2 list-disc pl-3">
                        <li>Use a high-contrast background</li>
                        <li>Ensure the name is clearly labeled</li>
                        <li>Signatures are stored securely</li>
                        <li>One default signature per user</li>
                      </ul>
                    </div>
                  </div>

                  <button @click="saveSignature" class="btn btn-primary btn-block text-white rounded-2xl font-black uppercase tracking-widest text-xs h-16 shadow-xl shadow-primary/20 hover:scale-[1.02] active:scale-[0.98] transition-all mt-6">
                    <Save :size="18" class="mr-2" /> Save Signature Asset
                  </button>
                </div>
              </div>
            </div>

            <!-- Saved Signatures Gallery -->
            <div class="space-y-6">
              <h4 class="text-sm font-black uppercase tracking-[0.2em] text-primary flex items-center gap-3 px-2">
                <span class="w-8 h-px bg-primary/20"></span>
                Your Saved Signatures
                <span class="badge badge-primary badge-sm bg-primary/10 text-primary border-none font-black">{{ signatures.length }}</span>
              </h4>
              
              <div v-if="signatures.length === 0" class="bg-base-100 border border-base-200 border-dashed rounded-3xl p-20 text-center">
                <p class="text-sm font-bold opacity-30 uppercase tracking-widest">No signature assets found in your vault</p>
              </div>

              <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-6">
                <div v-for="sig in signatures" :key="sig.id" class="group relative bg-white border border-base-200 p-6 rounded-3xl hover:border-primary/30 transition-all hover:shadow-2xl hover:shadow-primary/5">
                  <div class="flex justify-between items-center mb-6">
                    <div class="flex flex-col">
                      <span class="font-black text-[10px] uppercase tracking-widest text-base-content/80">{{ sig.name }}</span>
                      <span class="text-[9px] font-bold opacity-30 mt-0.5 uppercase">{{ new Date(sig.created_at).toLocaleDateString() }}</span>
                    </div>
                    <div class="flex items-center gap-2">
                      <span v-if="sig.is_default" class="bg-primary text-white text-[8px] font-black uppercase tracking-widest px-2 py-1 rounded-lg shadow-lg shadow-primary/20">Default</span>
                      <button @click="deleteSignature(sig.id)" class="btn btn-ghost btn-circle btn-xs text-error opacity-0 group-hover:opacity-100 transition-all bg-error/5 hover:bg-error hover:text-white">
                        <Trash2 :size="14" />
                      </button>
                    </div>
                  </div>
                  <div class="w-full aspect-[2.5/1] bg-slate-50/50 rounded-2xl flex items-center justify-center p-6 border border-base-100 group-hover:bg-white transition-colors">
                    <img :src="sig.signature_data" class="max-h-full object-contain mix-blend-multiply filter drop-shadow-sm" />
                  </div>
                </div>
              </div>
            </div>
          </div>

          <!-- Departments Tab -->
          <div v-if="activeTab === 'departments'" class="space-y-8 animate-in fade-in slide-in-from-bottom-4 duration-500">
            <div class="max-w-3xl mx-auto space-y-8">
              <div class="card bg-base-100 border border-base-200 p-8 rounded-3xl shadow-sm space-y-6">
                <h4 class="text-lg font-black uppercase tracking-tighter text-primary">Department Management</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div class="form-control">
                    <label class="label"><span class="label-text font-bold text-xs uppercase opacity-60">Dept Name</span></label>
                    <input v-model="newDepartment.name" type="text" class="input input-bordered rounded-xl bg-base-200/30 border-none" placeholder="e.g., Office of the President" />
                  </div>
                  <div class="form-control">
                    <label class="label"><span class="label-text font-bold text-xs uppercase opacity-60">Dept Code</span></label>
                    <input v-model="newDepartment.code" type="text" class="input input-bordered rounded-xl bg-base-200/30 border-none" placeholder="e.g., OP-01" />
                  </div>
                </div>
                <button @click="saveDepartment" class="btn btn-primary text-white rounded-xl font-black uppercase tracking-widest text-xs">
                  Add Department
                </button>
              </div>

              <div class="space-y-4">
                <h4 class="text-sm font-black uppercase tracking-widest text-primary px-2">Registered Departments</h4>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                  <div v-for="dept in departments" :key="dept.id" class="bg-base-100 border border-base-200 p-4 rounded-2xl flex items-center justify-between hover:bg-primary/5 transition-colors">
                    <div class="flex items-center gap-4">
                      <div class="w-10 h-10 rounded-xl bg-primary/10 text-primary flex items-center justify-center font-black">
                        {{ dept.code?.[0] || dept.name[0] }}
                      </div>
                      <div>
                        <p class="font-black text-xs uppercase">{{ dept.name }}</p>
                        <p class="text-[10px] opacity-40 font-bold">{{ dept.code || 'NO-CODE' }}</p>
                      </div>
                    </div>
                    <button @click="deleteDepartment(dept.id)" class="btn btn-ghost btn-sm text-error/40 hover:text-error"><Trash2 :size="14" /></button>
                  </div>
                </div>
              </div>
            </div>
          </div>

        </div>
      </div>

      <!-- Footer -->
      <div class="px-8 py-6 bg-base-100 border-t border-base-200 flex items-center justify-between shrink-0">
        <p class="text-[10px] font-bold text-base-content/30 uppercase tracking-[0.2em]">Templates synchronize across your team</p>
        <button @click="closeModal" class="btn btn-primary px-10 text-white font-black text-xs uppercase tracking-widest rounded-xl">
          Done
        </button>
      </div>
    </div>
    <div class="modal-backdrop bg-base-100/5 backdrop-blur-3xl transition-all duration-700" @click="closeModal"></div>

    <!-- Fullscreen Maximize Overlay -->
    <div v-if="isFullscreen" class="fixed inset-0 z-1000 bg-base-100 flex flex-col items-center justify-center p-10 animate-in fade-in zoom-in duration-300">
      <div class="w-full max-w-7xl h-full flex flex-col gap-8">
        <div class="flex items-center justify-between">
           <div>
              <h2 class="text-3xl font-black text-primary uppercase tracking-tighter">Signing Workspace</h2>
              <p class="text-xs font-bold opacity-40 uppercase tracking-[0.3em]">Maximum precision mode</p>
           </div>
           <button @click="toggleFullscreen" class="btn btn-circle btn-ghost bg-base-200 hover:bg-error/10 hover:text-error">
             <X :size="24" />
           </button>
        </div>
        
        <div class="flex-1 bg-white border-4 border-primary/20 rounded-[3rem] shadow-2xl relative overflow-hidden">
           <canvas id="fullscreenCanvas" class="w-full h-full cursor-crosshair"></canvas>
           <!-- Controls -->
           <div class="absolute bottom-10 right-10 flex gap-4">
              <button @click="() => window.tempFsPad.clear()" class="btn btn-square btn-lg bg-base-100 shadow-2xl border-2 border-base-200 hover:text-error">
                <Eraser :size="24" />
              </button>
              <button @click="() => { 
                const data = window.tempFsPad.toData(); 
                if (data.length > 0) { data.pop(); window.tempFsPad.fromData(data); } 
              }" class="btn btn-square btn-lg bg-base-100 shadow-2xl border-2 border-base-200">
                <Undo2 :size="24" />
              </button>
           </div>
           <!-- Watermark -->
           <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 text-[10vw] font-black uppercase tracking-[0.5em] opacity-[0.03] pointer-events-none select-none -rotate-12">
             VERIFIED
           </div>
        </div>
        
        <div class="flex justify-center shrink-0">
           <button @click="toggleFullscreen" class="btn btn-primary btn-lg px-20 text-white font-black uppercase tracking-widest rounded-2xl shadow-2xl shadow-primary/40">
             Apply & Close
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
  background-color: rgba(0, 0, 0, 0.05);
  border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background-color: transparent;
}

.modal-box {
  animation: modal-pop 0.5s cubic-bezier(0.19, 1, 0.22, 1);
}

@keyframes modal-pop {
  0% { opacity: 0; transform: scale(0.95) translateY(30px); }
  100% { opacity: 1; transform: scale(1) translateY(0); }
}

.animate-in {
  animation: animate-in 0.3s ease-out;
}

@keyframes animate-in {
  from { opacity: 0; transform: translateY(10px); }
  to { opacity: 1; transform: translateY(0); }
}

canvas {
  touch-action: none;
}
</style>
