<script setup>
import { ref, computed, watch } from 'vue'
import { X, Calendar, Clock, AlertCircle } from 'lucide-vue-next'

const props = defineProps({
  isOpen: Boolean,
  initialSchedule: {
    type: Object,
    default: () => ({})
  }
})

const emit = defineEmits(['close', 'save'])

const scheduleData = ref({
  startDate: '',
  startTime: '',
  endDate: '',
  endTime: '',
  deadlineDate: '',
  deadlineTime: '',
  allDay: false
})

// Initialize with props if available
watch(() => props.initialSchedule, (newVal) => {
  if (newVal && Object.keys(newVal).length > 0) {
    scheduleData.value = { ...newVal }
  }
}, { immediate: true })

const clearSchedule = () => {
  scheduleData.value = {
    startDate: '',
    startTime: '',
    endDate: '',
    endTime: '',
    deadlineDate: '',
    deadlineTime: '',
    allDay: false
  }
}

const saveSchedule = () => {
  // Validate that at least start date and time are set OR deadline is set
  if ((!scheduleData.value.startDate || !scheduleData.value.startTime) && (!scheduleData.value.deadlineDate)) {
    alert('Please set a schedule or a deadline')
    return
  }

  // Auto-fill end date/time if not set
  if (!scheduleData.value.endDate) {
    scheduleData.value.endDate = scheduleData.value.startDate
  }
  if (!scheduleData.value.endTime) {
    scheduleData.value.endTime = scheduleData.value.startTime
  }

  emit('save', { ...scheduleData.value })
  emit('close')
}

const closeModal = () => {
  emit('close')
}
</script>

<template>
  <Teleport to="body">
    <div v-if="isOpen" class="modal modal-open z-99999 items-center justify-center">
    <div class="modal-box max-w-2xl w-full rounded-xl bg-base-100 shadow-2xl border border-base-300 p-0 overflow-hidden">
      <!-- Header -->
      <div class="bg-primary px-6 py-4 flex items-center justify-between text-primary-content">
        <h3 class="text-xl font-bold tracking-tight uppercase flex items-center gap-3">
          <Calendar :size="24" />
          Schedule Memo
        </h3>
        <button @click="closeModal" class="btn btn-ghost btn-sm btn-circle text-primary-content hover:bg-white/10">
          <X :size="20" />
        </button>
      </div>

      <!-- Body -->
      <div class="p-8 space-y-8">
        <!-- Start Section -->
        <div>
          <h4 class="text-sm font-black text-base-content/70 uppercase tracking-wider mb-4 flex items-center gap-2">
            <Clock :size="16" />
            Start
          </h4>
          <div class="grid grid-cols-2 gap-4">
            <!-- Start Date -->
            <div>
              <label class="label">
                <span class="label-text text-xs font-bold text-base-content/50 uppercase tracking-wider flex items-center gap-2">
                  <Calendar :size="14" />
                  Start Date
                </span>
              </label>
              <input 
                v-model="scheduleData.startDate"
                type="date" 
                class="input input-bordered w-full focus:border-primary focus:outline-none"
                placeholder="mm/dd/yyyy"
              />
            </div>

            <!-- Start Time -->
            <div>
              <label class="label">
                <span class="label-text text-xs font-bold text-base-content/50 uppercase tracking-wider flex items-center gap-2">
                  <Clock :size="14" />
                  Start Time
                </span>
              </label>
              <input 
                v-model="scheduleData.startTime"
                type="time" 
                class="input input-bordered w-full focus:border-primary focus:outline-none"
                placeholder="--:-- --"
              />
            </div>
          </div>
        </div>

        <!-- End Section -->
        <div>
          <h4 class="text-sm font-black text-base-content/70 uppercase tracking-wider mb-4 flex items-center gap-2">
            <Clock :size="16" />
            End
          </h4>
          <div class="grid grid-cols-2 gap-4">
            <!-- End Date -->
            <div>
              <label class="label">
                <span class="label-text text-xs font-bold text-base-content/50 uppercase tracking-wider flex items-center gap-2">
                  <Calendar :size="14" />
                  End Date
                </span>
              </label>
              <input 
                v-model="scheduleData.endDate"
                type="date" 
                class="input input-bordered w-full focus:border-primary focus:outline-none"
                placeholder="mm/dd/yyyy"
              />
            </div>

            <!-- End Time -->
            <div>
              <label class="label">
                <span class="label-text text-xs font-bold text-base-content/50 uppercase tracking-wider flex items-center gap-2">
                  <Clock :size="14" />
                  End Time
                </span>
              </label>
              <input 
                v-model="scheduleData.endTime"
                type="time" 
                class="input input-bordered w-full focus:border-primary focus:outline-none"
                placeholder="--:-- --"
              />
            </div>
          </div>
        </div>

        <!-- Deadline Section -->
        <div>
          <h4 class="text-sm font-black text-error uppercase tracking-wider mb-4 flex items-center gap-2">
            <AlertCircle :size="16" />
            Deadline (Action Required By)
          </h4>
          <div class="grid grid-cols-2 gap-4">
            <!-- Deadline Date -->
            <div>
              <label class="label">
                <span class="label-text text-xs font-bold text-base-content/50 uppercase tracking-wider flex items-center gap-2">
                  <Calendar :size="14" />
                  Deadline Date
                </span>
              </label>
              <input 
                v-model="scheduleData.deadlineDate"
                type="date" 
                class="input input-bordered w-full border-error/30 focus:border-error focus:outline-none"
                placeholder="mm/dd/yyyy"
              />
            </div>

            <!-- Deadline Time -->
            <div>
              <label class="label">
                <span class="label-text text-xs font-bold text-base-content/50 uppercase tracking-wider flex items-center gap-2">
                  <Clock :size="14" />
                  Deadline Time
                </span>
              </label>
              <input 
                v-model="scheduleData.deadlineTime"
                type="time" 
                class="input input-bordered w-full border-error/30 focus:border-error focus:outline-none"
                placeholder="--:-- --"
              />
            </div>
          </div>
        </div>

        <!-- All Day Event Checkbox -->
        <div class="form-control">
          <label class="label cursor-pointer justify-start gap-4">
            <input 
              v-model="scheduleData.allDay"
              type="checkbox" 
              class="checkbox checkbox-primary"
            />
            <span class="label-text font-bold">All Day Event</span>
          </label>
        </div>

        <!-- Info Message -->
        <div class="alert bg-info/10 border border-info/30 rounded-xl p-4">
          <div class="flex gap-3">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" class="stroke-info shrink-0 w-6 h-6">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
            </svg>
            <div class="text-sm leading-relaxed text-info">
              <span class="font-black">Schedule Send:</span> After admin approval, this memo will be automatically sent and archived 
              at the scheduled date and time. It will remain visible to recipients in their inbox.
            </div>
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="px-8 py-6 bg-base-200 border-t border-base-300 flex items-center justify-end gap-4">
        <button 
          @click="clearSchedule" 
          class="btn btn-ghost px-6 font-black text-[10px] uppercase tracking-[0.2em] text-base-content/50 hover:text-base-content transition-colors"
        >
          Clear
        </button>
        <button 
          @click="saveSchedule" 
          class="btn btn-primary px-8 text-white font-black text-[11px] uppercase tracking-[0.25em] shadow-lg shadow-primary/40 rounded-xl hover:scale-[1.02] active:scale-[0.98] transition-all"
        >
          Save
        </button>
      </div>
    </div>
    <div class="modal-backdrop bg-base-100/5 backdrop-blur-3xl transition-all duration-700" @click="closeModal"></div>
    </div>
  </Teleport>
</template>

<style scoped>
.modal-box {
  animation: modal-pop 0.3s cubic-bezier(0.19, 1, 0.22, 1);
}

@keyframes modal-pop {
  0% { opacity: 0; transform: scale(0.95) translateY(20px); }
  100% { opacity: 1; transform: scale(1) translateY(0); }
}
</style>
