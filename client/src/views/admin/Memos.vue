<script setup>
import { ref } from 'vue'
import { Plus, Search, ChevronDown, Calendar, X, Settings2 } from 'lucide-vue-next'
import ComposeMemoModal from '@/components/memos/ComposeMemoModal.vue'
import CustomizeMemoModal from '@/components/memos/CustomizeMemoModal.vue'
import Swal from 'sweetalert2'

const departmentFilter = ref('All Departments')
const priorityFilter = ref('All Priorities')
const sortFilter = ref('Newest')
const dateFilter = ref('mm/dd/yyyy')

const showComposeModal = ref(false)
const showCustomizeModal = ref(false)
const templateData = ref(null)

const handleTemplateApply = (data) => {
  templateData.value = data
  showCustomizeModal.value = false
  showComposeModal.value = true
}

const handleSendMemo = async (memoData) => {
  // Logic to send memo to API
  try {
    // Mocking API call for now
    console.log('Sending Memo:', memoData)
    
    await Swal.fire({
      title: 'Success!',
      text: 'Memo has been sent successfully.',
      icon: 'success',
      confirmButtonText: 'OK',
      customClass: {
        confirmButton: 'btn btn-primary'
      }
    })
    
    showComposeModal.value = false
  } catch (error) {
    Swal.fire('Error', 'Failed to send memo', 'error')
  }
}
</script>

<template>
  <div class="view-container">
    <!-- Toolbar -->
    <div class="flex flex-col md:flex-row items-center gap-4 mb-8 bg-base-100 p-2 rounded-xl border border-base-200 shadow-sm">
      <!-- Checkbox / Select All -->
      <div class="dropdown">
        <label tabindex="0" class="btn btn-sm btn-ghost btn-square">
          <input type="checkbox" class="checkbox checkbox-sm rounded-md" />
          <ChevronDown :size="14" class="ml-1" />
        </label>
        <!-- Dropdown content would go here -->
      </div>
      
      <!-- Filters -->
      <div class="flex-1 flex flex-wrap items-center gap-2 w-full">
        <select class="select select-sm select-bordered w-full md:w-auto bg-base-100">
          <option selected>All Departments</option>
          <option>Computer Science</option>
          <option>Information Technology</option>
        </select>
        
        <select class="select select-sm select-bordered w-full md:w-auto bg-base-100">
          <option selected>All Priorities</option>
          <option>High</option>
          <option>Normal</option>
          <option>Low</option>
        </select>
        
        <select class="select select-sm select-bordered w-full md:w-auto bg-base-100">
          <option selected>Newest</option>
          <option>Oldest</option>
        </select>
        
        <!-- Date Picker Placeholder -->
        <div class="relative w-full md:w-auto">
          <input 
            type="text" 
            placeholder="mm/dd/yyyy" 
            class="input input-sm input-bordered w-full pr-8 bg-base-100" 
          />
          <button class="absolute right-2 top-1/2 -translate-y-1/2 text-base-content/40 hover:text-base-content">
            <X :size="14" />
          </button>
        </div>
      </div>

      <!-- Action -->
      <div class="flex gap-2">
        <button @click="showCustomizeModal = true" class="btn btn-ghost btn-sm border border-base-300 px-4 hover:bg-base-200">
          <Settings2 :size="16" class="mr-2" /> Template
        </button>
        <button @click="showComposeModal = true; templateData = null" class="btn btn-primary btn-sm text-white px-6">
          <span class="mr-1">✎</span> Compose
        </button>
      </div>
    </div>

    <!-- Customize Memo Modal -->
    <CustomizeMemoModal
      :is-open="showCustomizeModal"
      @close="showCustomizeModal = false"
      @apply="handleTemplateApply"
    />

    <!-- Compose Memo Modal -->
    <ComposeMemoModal 
      :is-open="showComposeModal"
      :initial-data="templateData"
      @close="showComposeModal = false"
      @send="handleSendMemo"
    />

    <!-- Empty State -->
    <div class="flex flex-col items-center justify-center py-20">
      <p class="text-base-content/40 font-medium">No memos found</p>
    </div>
  </div>
</template>

<style scoped>
@reference "../../style.css";

.view-container {
  @apply p-0;
}
</style>
