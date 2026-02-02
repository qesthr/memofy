<script setup>
import { computed } from 'vue'
import { useCalendar } from '@/composables/useCalendar'
import { useEvents } from '@/composables/useEvents'

const { selectedDate, openEventModal, formattedDate } = useCalendar()
const { events, isLoading } = useEvents()

const currentDay = computed(() => {
  return {
    name: selectedDate.value.toLocaleDateString('en-US', { weekday: 'long' }),
    date: selectedDate.value.getDate(),
    fullDate: formattedDate(selectedDate.value),
    isToday: selectedDate.value.toDateString() === new Date().toDateString()
  }
})

// 48 slots for 24 hours (30 mins each)
const timeSlots = computed(() => {
  const slots = []
  for (let i = 0; i < 24; i++) {
    slots.push({ hour: i, minute: 0, label: formatTime(i, 0) })
    slots.push({ hour: i, minute: 30, label: formatTime(i, 30) })
  }
  return slots
})

const formatTime = (hour, minute) => {
  const h = hour === 0 || hour === 12 ? 12 : hour % 12
  const ampm = hour >= 12 ? 'PM' : 'AM'
  const m = minute === 0 ? '00' : minute
  return `${h}:${m} ${ampm}`
}

const getEventStyle = (event) => {
  const start = new Date(event.start)
  const end = new Date(event.end)
  
  const startMinutes = start.getHours() * 60 + start.getMinutes()
  const duration = (end - start) / (1000 * 60)
  
  const top = (startMinutes / 60) * 60 
  const height = Math.max((duration / 60) * 60, 20) // min 20px
  
  return {
    top: `${top}px`,
    height: `${height}px`,
    backgroundColor: event.source === 'GOOGLE' ? '#4285F4' : '#3b82f6',
    borderLeft: `5px solid ${event.source === 'GOOGLE' ? '#1a73e8' : '#1d4ed8'}`
  }
}

const dayEvents = computed(() => {
  const fullDate = currentDay.value.fullDate
  return events.value.filter(e => {
    return formattedDate(e.start) === fullDate && !e.all_day
  })
})

const allDayEvents = computed(() => {
  const fullDate = currentDay.value.fullDate
  return events.value.filter(e => {
    return formattedDate(e.start) === fullDate && e.all_day
  })
})
</script>

<template>
  <div class="flex flex-col h-full bg-base-100 overflow-hidden">
    <!-- Day Header -->
    <div class="flex border-b border-black/20 dark:border-white/20">
      <div class="w-20 border-r border-black/20 dark:border-white/20"></div>
      <div class="flex-1 py-6 flex flex-col items-center">
        <span class="text-sm font-bold text-base-content/40 uppercase tracking-widest">{{ currentDay.name }}</span>
        <div class="w-16 h-16 flex items-center justify-center rounded-full mt-2"
             :class="{ 'bg-primary text-primary-content font-bold': currentDay.isToday }">
          <span class="text-4xl">{{ currentDay.date }}</span>
        </div>
      </div>
    </div>

    <!-- All Day Section -->
    <div v-if="allDayEvents.length > 0" class="flex border-b border-black/20 dark:border-white/20 bg-base-200/20">
      <div class="w-20 flex flex-col items-center justify-center border-r border-black/20 dark:border-white/20">
        <span class="text-[10px] font-bold text-base-content/40 uppercase">All Day</span>
      </div>
      <div class="flex-1 p-2 flex flex-wrap gap-2">
        <div v-for="event in allDayEvents" :key="event.id"
             @click="openEventModal(event)"
             class="bg-primary/20 text-primary text-xs font-medium px-3 py-1.5 rounded truncate border-l-4 border-primary shadow-sm min-w-[150px] cursor-pointer hover:bg-primary/30 transition-colors">
          {{ event.title }}
        </div>
      </div>
    </div>

    <!-- Scrollable Time Grid -->
    <div class="flex-1 overflow-y-auto relative custom-scrollbar p-0">
      <div class="flex relative min-h-[1440px]">
        
        <!-- Time Labels -->
        <div class="w-20 flex-shrink-0 border-r border-black/10 dark:border-white/10 bg-base-100 z-10">
          <div v-for="slot in timeSlots" :key="`${slot.hour}-${slot.minute}`" class="h-[30px] relative">
            <span v-if="slot.hour > 0 || slot.minute > 0" class="absolute -top-2.5 right-3 text-[10px] text-base-content/40 font-bold whitespace-nowrap uppercase">
              {{ slot.label }}
            </span>
          </div>
        </div>

        <!-- Grid and Events -->
        <div class="flex flex-1 relative bg-base-100">
          <!-- background grid lines (removed and moved to slots) -->
          <div class="absolute inset-0">
          </div>

          <!-- Events Area -->
          <div class="flex-1 relative">
             <!-- Clickable Slots for New Events (30 min slots) -->
             <div v-for="slot in timeSlots" :key="`${slot.hour}-${slot.minute}`" 
                  @click="openEventModal({ 
                    start: `${currentDay.fullDate}T${slot.hour.toString().padStart(2, '0')}:${slot.minute.toString().padStart(2, '0')}`,
                    end: (() => {
                      let h = slot.hour; let m = slot.minute + 30;
                      if (m >= 60) { h++; m = 0; }
                      return `${currentDay.fullDate}T${h.toString().padStart(2, '0')}:${m.toString().padStart(2, '0')}`;
                    })(),
                    is_editable: true 
                  })"
                  class="h-[30px] hover:bg-base-200/50 cursor-pointer transition-colors border-b border-black/10 dark:border-white/10 last:border-none">
             </div>

             <!-- Events Overlay -->
             <div v-for="event in dayEvents" :key="event.id"
                  @click.stop="openEventModal(event)"
                  class="absolute left-2 right-2 rounded-lg p-3 shadow-md overflow-hidden z-10 cursor-pointer hover:scale-[1.01] transition-all text-white border border-white/20"
                  :style="getEventStyle(event)">
                <div class="text-sm font-bold truncate">{{ event.title }}</div>
                <div class="text-xs opacity-90 mt-1">
                  {{ new Date(event.start).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }} -
                  {{ new Date(event.end).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
                </div>
                <div v-if="event.description" class="text-xs opacity-80 mt-2 line-clamp-2 italic">
                  {{ event.description }}
                </div>
             </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
  width: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background-color: #cbd5e1;
  border-radius: 5px;
  border: 2px solid transparent;
  background-clip: content-box;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background-color: #f8fafc;
}
</style>
