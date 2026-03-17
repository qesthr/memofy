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
  
  // Use the color provided by the API
  let bgColor = event.color || '#3b82f6'
  let borderColor = '#3b82f6'
  
  // Highlighting: Border color should be a darker version of the priority color
  const priority = event.priority || 'medium'
  switch (priority) {
    case 'high':
      borderColor = '#D32F2F'
      break
    case 'medium':
      borderColor = '#F57C00'
      break
    case 'low':
      borderColor = '#388E3C'
      break
    default:
      borderColor = '#1d4ed8'
  }
  
  if (event.source === 'GOOGLE') {
    borderColor = '#1a73e8'
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

    <!-- Single scrollable container for header + time grid -->
    <div class="flex-1 overflow-y-scroll custom-scrollbar">

      <!-- Week Header (sticky) -->
      <div class="grid grid-cols-[64px_repeat(7,1fr)] border-b border-base-300 sticky top-0 z-20 bg-base-100">
        <div class="border-r border-base-300"></div>

        <div v-for="day in weekDays"
             :key="day.fullDate"
             class="py-3 flex flex-col items-center border-r border-base-300 last:border-r-0">

          <span class="text-[11px] font-bold text-base-content/40 uppercase tracking-widest">
            {{ day.name }}
          </span>

          <div class="w-10 h-10 flex items-center justify-center rounded-full mt-1 transition-all"
               :class="{ 
                 'bg-primary text-primary-content font-bold shadow-lg': day.isToday,
                 'border-2 border-primary text-primary font-bold': day.fullDate === formattedSelectedDate && !day.isToday
               }">
            <span class="text-xl">{{ day.date }}</span>
          </div>

        </div>
      </div>

      <!-- All Day Section (sticky, below header) -->
      <div class="grid grid-cols-[64px_repeat(7,1fr)] border-b border-base-300 sticky top-[74px] z-20 bg-base-100">

        <div class="flex items-center justify-center border-r border-base-300">
          <span class="text-[9px] font-bold text-base-content/40 uppercase">
            All Day
          </span>
        </div>

        <div v-for="day in weekDays"
             :key="day.fullDate"
             class="border-r border-base-300 last:border-r-0 py-1 px-1 flex flex-col gap-1"
             :class="{ 'bg-primary/5': day.fullDate === formattedSelectedDate }">

          <div v-for="event in allDayEventsForDay(day.fullDate)"
               :key="event.id"
               @click="openEventModal(event)"
               class="text-white text-[10px] font-bold px-2 py-1 rounded truncate shadow-sm cursor-pointer"
               :style="{ backgroundColor: getEventStyle(event).backgroundColor }">

            {{ event.title }}

          </div>
        </div>

      </div>

      <!-- Time Grid -->
      <div class="grid grid-cols-[64px_repeat(7,1fr)] relative min-h-[1440px]">

        <!-- Time Labels -->
        <div class="border-r border-base-300">

          <div v-for="slot in timeSlots"
               :key="`${slot.hour}-${slot.minute}`"
               class="h-[30px] flex items-center justify-end pr-2">

            <span v-if="slot.minute === 0"
                  class="text-[9px] text-base-content/40 font-medium">
              {{ slot.label }}
            </span>

          </div>

        </div>

        <!-- Day Columns -->
        <div v-for="day in weekDays"
             :key="day.fullDate"
             class="relative border-r border-base-300 last:border-r-0">

          <!-- Click Slots -->
          <div v-for="slot in timeSlots"
               :key="`${slot.hour}-${slot.minute}`"
               class="h-[30px] border-b border-base-300 hover:bg-base-200/50 cursor-pointer">
          </div>

          <!-- Events -->
          <div v-for="event in getDayEvents(day.fullDate)"
               :key="event.id"
               @click.stop="openEventModal(event)"
               class="absolute left-1 right-1 rounded p-1 shadow-sm text-white cursor-pointer"
               :style="getEventStyle(event)">

            <div class="text-[10px] font-bold leading-tight">
              {{ event.title }}
            </div>

            <div class="text-[9px] opacity-80">
              {{ new Date(event.start).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
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
