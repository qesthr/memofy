<script setup>
import { ref, computed } from 'vue'
import { useCalendar } from '@/composables/useCalendar'
import { ChevronLeft, ChevronRight } from 'lucide-vue-next'

const { selectedDate, setSelectedDate } = useCalendar()

// Local state for the mini calendar's "viewed" month, which can differ from selectedDate's month
const viewDate = ref(new Date(selectedDate.value.getFullYear(), selectedDate.value.getMonth(), 1))

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
  
  return [...prevDays, ...currentDays, ...nextDays]
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
      >
        {{ day.date.getDate() }}
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
