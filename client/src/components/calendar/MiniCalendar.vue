<script setup>
import { ref, computed, watch } from 'vue'
import { useCalendar } from '@/composables/useCalendar'
import { useEvents } from '@/composables/useEvents'
import { ChevronLeft, ChevronRight } from 'lucide-vue-next'

const { 
  selectedDate, 
  setSelectedDate, 
  priorityFilters 
} = useCalendar()
const { events } = useEvents()

// Local state for the mini calendar's "viewed" month
const viewDate = ref(new Date(selectedDate.value.getFullYear(), selectedDate.value.getMonth(), 1))

// Bidirectional sync: Update viewDate when selectedDate changes (from main calendar)
watch(selectedDate, (newDate) => {
  if (newDate.getMonth() !== viewDate.value.getMonth() || newDate.getFullYear() !== viewDate.value.getFullYear()) {
    viewDate.value = new Date(newDate.getFullYear(), newDate.getMonth(), 1)
  }
})

const monthName = computed(() => {
  return viewDate.value.toLocaleString('default', { month: 'long', year: 'numeric' })
})

const days = computed(() => {
  const year = viewDate.value.getFullYear()
  const month = viewDate.value.getMonth()
  
  const firstDay = new Date(year, month, 1).getDay() // 0 = Sunday
  const lastDate = new Date(year, month + 1, 0).getDate()
  
  // Previous month days for padding
  const prevMonthLastDate = new Date(year, month, 0).getDate()
  const prevDays = []
  for (let i = firstDay - 1; i >= 0; i--) {
    prevDays.push({
      date: new Date(year, month - 1, prevMonthLastDate - i),
      current: false
    })
  }
  
  // Current month days
  const currentDays = []
  for (let i = 1; i <= lastDate; i++) {
    currentDays.push({
      date: new Date(year, month, i),
      current: true
    })
  }
  
  // Next month days for padding to reach 42 cells (6 rows)
  const nextDays = []
  const remainingCells = 42 - (prevDays.length + currentDays.length)
  for (let i = 1; i <= remainingCells; i++) {
    nextDays.push({
      date: new Date(year, month + 1, i),
      current: false
    })
  }
  
  return [...prevDays, ...currentDays, ...nextDays].map(day => {
    // Attach events to day
    const dayEvents = events.value.filter(e => {
        const d = new Date(e.start)
        return d.toDateString() === day.date.toDateString()
    })
    
    // Determine highest priority for highlighting
    let highestPriority = null
    if (dayEvents.length > 0) {
        if (dayEvents.some(e => e.priority === 'high')) highestPriority = 'high'
        else if (dayEvents.some(e => e.priority === 'medium')) highestPriority = 'medium'
        else highestPriority = 'low'
    }

    return { ...day, events: dayEvents, highestPriority }
  })
})

const isToday = (date) => {
  const today = new Date()
  return date.toDateString() === today.toDateString()
}

const isSelected = (date) => {
  return date.toDateString() === selectedDate.value.toDateString()
}

const selectDate = (date) => {
  setSelectedDate(date)
}

const getPriorityColor = (priority) => {
    switch(priority) {
        case 'high': return '#F44336'
        case 'medium': return '#FF9800'
        case 'low': return '#4CAF50'
        default: return '#3B82F6'
    }
}

const nextMonth = () => {
  viewDate.value = new Date(viewDate.value.getFullYear(), viewDate.value.getMonth() + 1, 1)
}

const prevMonth = () => {
  viewDate.value = new Date(viewDate.value.getFullYear(), viewDate.value.getMonth() - 1, 1)
}
</script>

<template>
  <div class="mini-calendar">
    <div class="flex items-center justify-between mb-4 px-1">
      <span class="text-sm font-medium">{{ monthName }}</span>
      <div class="flex gap-1">
        <button @click="prevMonth" class="btn btn-xs btn-ghost btn-circle">
          <ChevronLeft :size="14" />
        </button>
        <button @click="nextMonth" class="btn btn-xs btn-ghost btn-circle">
          <ChevronRight :size="14" />
        </button>
      </div>
    </div>

    <div class="grid grid-cols-7 text-center mb-1">
      <span v-for="d in ['S', 'M', 'T', 'W', 'T', 'F', 'S']" :key="d" 
            class="text-[10px] font-bold text-base-content/40 py-1 uppercase">
        {{ d }}
      </span>
    </div>

    <div class="grid grid-cols-7 text-center">
      <button 
        v-for="(day, idx) in days" 
        :key="idx"
        @click="selectDate(day.date)"
        class="text-xs h-8 flex items-center justify-center rounded-full hover:bg-base-200 transition-colors relative"
        :class="{
          'text-base-content/30': !day.current,
          'font-semibold text-primary': isToday(day.date) && !isSelected(day.date),
          'bg-primary text-primary-content font-bold hover:bg-primary/90': isSelected(day.date)
        }"
        :style="day.highestPriority && !isSelected(day.date) ? { 
            boxShadow: `inset 0 0 0 2px ${getPriorityColor(day.highestPriority)}`,
            backgroundColor: isToday(day.date) ? 'transparent' : `${getPriorityColor(day.highestPriority)}15`
        } : {}"
        :title="day.events.length > 0 ? `${day.events.length} events (Priority: ${day.highestPriority})` : ''"
      >
        {{ day.date.getDate() }}
        <!-- Small dot indicator -->
        <div v-if="day.events.length > 0 && !isSelected(day.date)" 
             class="absolute bottom-1 left-1/2 -translate-x-1/2 w-1 h-1 rounded-full"
             :style="{ backgroundColor: getPriorityColor(day.highestPriority) }">
        </div>
      </button>
    </div>
  </div>
</template>

<style scoped>
@reference "../../style.css";

.mini-calendar {
  @apply select-none;
}
</style>
