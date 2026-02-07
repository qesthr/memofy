<script setup>
import { computed } from 'vue'
import { useCalendar } from '@/composables/useCalendar'
import { useEvents } from '@/composables/useEvents'
import { useAuth } from '@/composables/useAuth'
import Swal from 'sweetalert2'

const { selectedDate, weekRange, openEventModal, formattedDate, formattedSelectedDate } = useCalendar()
const { events, isLoading } = useEvents()
const { can } = useAuth()

const weekDays = computed(() => {
  const curr = new Date(selectedDate.value)
  const first = curr.getDate() - curr.getDay()
  
  const days = []
  for (let i = 0; i < 7; i++) {
    const d = new Date(curr.setDate(first + i))
    days.push({
      name: d.toLocaleDateString('en-US', { weekday: 'short' }),
      date: d.getDate(),
      fullDate: formattedDate(d),
      isToday: d.toDateString() === new Date().toDateString()
    })
  }
  return days
})

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
  
  const top = (startMinutes / 60) * 60 // 1 hour = 60px
  const height = (duration / 60) * 60
  
  // Visual distinction for different sources and priorities
  let bgColor, borderColor
  if (event.source === 'GOOGLE') {
    bgColor = '#4285F4'
    borderColor = '#1a73e8'
  } else {
    const priority = event.priority || 'medium'
    switch (priority) {
      case 'high':
        bgColor = '#F44336'
        borderColor = '#D32F2F'
        break
      case 'medium':
        bgColor = '#FF9800'
        borderColor = '#F57C00'
        break
      case 'low':
        bgColor = '#4CAF50'
        borderColor = '#388E3C'
        break
      default:
        bgColor = '#3b82f6'
        borderColor = '#1d4ed8'
    }
  }
  
  return {
    top: `${top}px`,
    height: `${height}px`,
    backgroundColor: bgColor,
    borderLeft: `5px solid ${borderColor}`,
    boxShadow: '0 2px 4px rgba(0,0,0,0.1)'
  }
}

const getDayEvents = (fullDate) => {
  return events.value.filter(e => {
    return formattedDate(e.start) === fullDate && !e.all_day
  })
}

const allDayEventsForDay = (fullDate) => {
  return events.value.filter(e => {
    return formattedDate(e.start) === fullDate && e.all_day
  })
}
</script>

<template>
  <div class="flex flex-col h-full bg-base-100 overflow-hidden">
    <!-- Week Header -->
    <div class="flex border-b border-black/20 dark:border-white/20">
      <div class="w-16 border-r border-black/20 dark:border-white/20"></div>
      <div v-for="day in weekDays" :key="day.fullDate" 
           class="flex-1 py-3 flex flex-col items-center">
        <span class="text-[11px] font-bold text-base-content/40 uppercase tracking-widest">{{ day.name }}</span>
        <div class="w-10 h-10 flex items-center justify-center rounded-full mt-1 transition-all"
             :class="{ 
               'bg-primary text-primary-content font-bold shadow-lg': day.isToday,
               'border-2 border-primary text-primary font-bold': day.fullDate === formattedSelectedDate && !day.isToday
             }">
          <span class="text-xl">{{ day.date }}</span>
        </div>
      </div>
    </div>

    <!-- All Day Section -->
    <div class="flex border-b border-black/20 dark:border-white/20 min-h-[40px] bg-base-200/20">
      <div class="w-16 flex flex-col items-center justify-center border-r border-black/20 dark:border-white/20">
        <span class="text-[9px] font-bold text-base-content/40 uppercase">All Day</span>
      </div>
      <div v-for="day in weekDays" :key="day.fullDate" 
           class="flex-1 border-r border-black/20 dark:border-white/20 last:border-r-0 p-1 flex flex-col gap-1"
           :class="{ 'bg-primary/5': day.fullDate === formattedSelectedDate }">
        <div v-for="event in allDayEventsForDay(day.fullDate)" :key="event.id"
             @click="() => {
               if (can('calendar.edit_event')) {
                 openEventModal(event)
               } else {
                 Swal.fire({
                   icon: 'error',
                   title: 'Permission Denied',
                   text: 'You do not have permission to edit events.'
                 })
               }
             }"
             class="text-white text-[10px] font-bold px-2 py-1 rounded truncate shadow-sm cursor-pointer hover:brightness-110 transition-all"
             :style="{ backgroundColor: getEventStyle(event).backgroundColor, borderLeft: getEventStyle(event).borderLeft }">
          {{ event.title }}
        </div>
      </div>
    </div>

    <!-- Scrollable Time Grid -->
    <div class="flex-1 overflow-y-auto overflow-x-hidden relative custom-scrollbar">
      <div class="flex relative min-h-[1440px]"> <!-- 24 hours * 60px -->
        
        <!-- Time Labels -->
        <div class="w-16 flex-shrink-0 border-r border-black/20 dark:border-white/20">
          <div v-for="slot in timeSlots" :key="`${slot.hour}-${slot.minute}`" class="h-[30px] relative">
            <span v-if="slot.hour > 0 || slot.minute > 0" class="absolute -top-2 right-2 text-[9px] text-base-content/40 font-medium whitespace-nowrap">
              {{ slot.label }}
            </span>
          </div>
        </div>

        <!-- Grid and Events -->
        <div class="flex flex-1 relative">
          <!-- background grid lines (removed and moved to slots) -->
          <div class="absolute inset-0">
          </div>

          <!-- Days interaction area -->
          <div v-for="day in weekDays" :key="day.fullDate" 
               class="flex-1 border-r border-black/20 dark:border-white/20 last:border-r-0 relative"
               :class="{ 'bg-primary/[0.03]': day.fullDate === formattedSelectedDate }">
             <!-- Clickable Slots for New Events (30 min slots) -->
             <div v-for="slot in timeSlots" :key="`${slot.hour}-${slot.minute}`" 
                  @click="() => {
                    if (can('calendar.add_event')) {
                      openEventModal({ 
                        start: `${day.fullDate}T${slot.hour.toString().padStart(2, '0')}:${slot.minute.toString().padStart(2, '0')}`,
                        end: (() => {
                          let h = slot.hour; let m = slot.minute + 30;
                          if (m >= 60) { h++; m = 0; }
                          return `${day.fullDate}T${h.toString().padStart(2, '0')}:${m.toString().padStart(2, '0')}`;
                        })(),
                        is_editable: true 
                      })
                    } else {
                      Swal.fire({
                        icon: 'error',
                        title: 'Permission Denied',
                        text: 'You do not have permission to add events.'
                      })
                    }
                  }"
                  class="h-[30px] hover:bg-base-200/50 cursor-pointer transition-colors border-b border-black/5 dark:border-white/5 last:border-none">
             </div>

             <!-- Events for this day (Overlay) -->
             <div v-for="event in getDayEvents(day.fullDate)" :key="event.id"
                  @click.stop="() => {
                    if (can('calendar.edit_event')) {
                      openEventModal(event)
                    } else {
                      Swal.fire({
                        icon: 'error',
                        title: 'Permission Denied',
                        text: 'You do not have permission to edit events.'
                      })
                    }
                  }"
                  class="absolute left-1 right-1 rounded p-1 shadow-sm overflow-hidden z-10 cursor-pointer hover:shadow-md transition-all text-white"
                  :style="getEventStyle(event)">
                <div class="text-[10px] font-bold leading-tight">{{ event.title }}</div>
                <div class="text-[9px] opacity-80 leading-snug">
                  {{ new Date(event.start).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
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
  width: 8px;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background-color: #cbd5e1;
  border-radius: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
  background-color: transparent;
}
</style>
