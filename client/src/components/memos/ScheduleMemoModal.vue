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
    <div v-if="isOpen" class="modal modal-open z-99999 items-center justify-center p-4">
    <div class="modal-box max-w-lg w-full max-h-[85vh] rounded-2xl bg-base-100 shadow-2xl border border-base-300 p-0 overflow-hidden flex flex-col scale-95 md:scale-100 transition-transform">
      <!-- Header -->
      <div class="bg-primary px-5 py-3.5 flex items-center justify-between text-primary-content shrink-0">
        <h3 class="text-base font-black tracking-widest uppercase flex items-center gap-2">
          <Calendar :size="18" />
          Schedule Settings
        </h3>
        <button @click="closeModal" class="btn btn-ghost btn-xs btn-circle text-primary-content hover:bg-white/10">
          <X :size="16" />
        </button>
      </div>

      <!-- Body -->
      <div class="p-6 space-y-6 overflow-y-auto flex-1 custom-scrollbar">
        <!-- Start Section -->
        <div class="bg-base-200/30 p-4 rounded-xl border border-base-200/50">
          <h4 class="text-[10px] font-black text-primary uppercase tracking-[0.2em] mb-4 flex items-center gap-2">
            <Clock :size="14" />
            Start Schedule
          </h4>
          <div class="flex flex-col sm:flex-row sm:items-end gap-4">
            <!-- Start Date -->
            <div class="space-y-1">
              <label class="text-[9px] font-black text-base-content/40 uppercase tracking-widest px-1">Date</label>
              <input 
                v-model="scheduleData.startDate"
                type="date" 
                class="input input-bordered input-sm w-55 focus:border-primary focus:outline-none bg-base-100 h-10 rounded-lg text-xs font-bold"
              />
            </div>

            <!-- Start Time -->
            <div class="flex-1 sm:flex-initial space-y-1">
              <label class="text-[9px] font-black text-base-content/40 uppercase tracking-widest px-1">Time</label>
              <input 
                v-model="scheduleData.startTime"
                type="time" 
                class="input input-bordered input-sm w-full sm:w-32 focus:border-primary focus:outline-none bg-base-100 h-10 rounded-lg text-xs font-bold"
              />
            </div>
          </div>
        </div>

        <!-- End Section -->
        <div class="bg-base-200/30 p-4 rounded-xl border border-base-200/50">
          <h4 class="text-[10px] font-black text-secondary uppercase tracking-[0.2em] mb-4 flex items-center gap-2">
            <Clock :size="14" />
            End Schedule
          </h4>
          <div class="flex flex-col sm:flex-row sm:items-end gap-4">
            <!-- End Date -->
            <div class="space-y-1">
              <label class="text-[9px] font-black text-base-content/40 uppercase tracking-widest px-1">Date</label>
              <input 
                v-model="scheduleData.endDate"
                type="date" 
                class="input input-bordered input-sm w-55 focus:border-primary focus:outline-none bg-base-100 h-10 rounded-lg text-xs font-bold"
              />
            </div>

            <!-- End Time -->
            <div class="flex-1 sm:flex-initial space-y-1">
              <label class="text-[9px] font-black text-base-content/40 uppercase tracking-widest px-1">Time</label>
              <input 
                v-model="scheduleData.endTime"
                type="time" 
                class="input input-bordered input-sm w-full sm:w-32 focus:border-primary focus:outline-none bg-base-100 h-10 rounded-lg text-xs font-bold"
              />
            </div>
          </div>
        </div>

        <!-- Deadline Section -->
        <div class="bg-error/5 p-4 rounded-xl border border-error/10">
          <h4 class="text-[10px] font-black text-error uppercase tracking-[0.2em] mb-4 flex items-center gap-2">
            <AlertCircle :size="14" />
            Action Deadline
          </h4>
          <div class="flex flex-col sm:flex-row sm:items-end gap-4">
            <!-- Deadline Date -->
            <div class="space-y-1">
              <label class="text-[9px] font-black text-error/40 uppercase tracking-widest px-1">Date</label>
              <input 
                v-model="scheduleData.deadlineDate"
                type="date" 
                class="input input-bordered input-sm w-55 border-error/20 focus:border-error focus:outline-none bg-white/50 h-10 rounded-lg text-xs font-bold"
              />
            </div>

            <!-- Deadline Time -->
            <div class="flex-1 sm:flex-initial space-y-1">
              <label class="text-[9px] font-black text-error/40 uppercase tracking-widest px-1">Time</label>
              <input 
                v-model="scheduleData.deadlineTime"
                type="time" 
                class="input input-bordered input-sm w-full sm:w-32 border-error/20 focus:border-error focus:outline-none bg-white/50 h-10 rounded-lg text-xs font-bold"
              />
            </div>
          </div>
        </div>

        <!-- All Day Event Checkbox -->
        <div class="flex items-center px-2 py-1">
          <label class="label cursor-pointer justify-start gap-3 group">
            <input 
              v-model="scheduleData.allDay"
              type="checkbox" 
              class="checkbox checkbox-primary checkbox-sm rounded"
            />
            <span class="text-xs font-bold text-base-content/70 uppercase tracking-wider group-hover:text-primary transition-colors">All Day Event</span>
          </label>
        </div>

        <!-- Info Message (More Compact) -->
        <div class="flex gap-3 bg-info/5 border border-info/10 rounded-xl p-4">
          <AlertCircle :size="18" class="text-info shrink-0 mt-0.5" />
          <div class="text-[11px] leading-relaxed text-info/80 font-medium italic">
            <span class="font-black uppercase tracking-tighter not-italic">Note:</span> After approval, this memo will be automatically sent and archived 
            at the scheduled time. It will remain visible in recipient inboxes.
          </div>
        </div>
      </div>

      <!-- Footer -->
      <div class="px-6 py-4 bg-base-200/50 border-t border-base-300 flex items-center justify-end gap-3 shrink-0">
        <button 
          @click="clearSchedule" 
          class="btn btn-ghost btn-sm px-4 font-black text-[9px] uppercase tracking-widest text-base-content/40 hover:text-error hover:bg-error/5 transition-all"
        >
          Clear
        </button>
        <button 
          @click="saveSchedule" 
          class="btn btn-primary btn-sm px-6 text-white font-black text-[10px] uppercase tracking-widest shadow-lg shadow-primary/20 rounded-lg hover:scale-[1.02] active:scale-[0.98] transition-all h-9"
        >
          Save Schedule
        </button>
      </div>
    </div>
    <div class="modal-backdrop transition-all duration-500" @click="closeModal"></div>
    </div>
  </Teleport>
</template>

<style scoped>
.modal-box {
  animation: modal-pop 0.3s cubic-bezier(0.19, 1, 0.22, 1);
}

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

@keyframes modal-pop {
  0% { opacity: 0; transform: scale(0.95) translateY(20px); }
  100% { opacity: 1; transform: scale(1) translateY(0); }
}
</style>