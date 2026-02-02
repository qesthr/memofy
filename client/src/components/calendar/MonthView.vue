<script setup>
import { computed } from 'vue'
import { useCalendar } from '@/composables/useCalendar'
import { useEvents } from '@/composables/useEvents'

const { selectedDate, setSelectedDate, openEventModal, formattedDate } = useCalendar()
const { events } = useEvents()


const days = computed(() => {
  const year = selectedDate.value.getFullYear()
  const month = selectedDate.value.getMonth()
  
  const firstDay = new Date(year, month, 1).getDay()
  const lastDate = new Date(year, month + 1, 0).getDate()
  
  const prevMonthLastDate = new Date(year, month, 0).getDate()
  const prevDays = []
  for (let i = firstDay - 1; i >= 0; i--) {
    const d = new Date(year, month - 1, prevMonthLastDate - i)
    prevDays.push({
      date: d,
      currentMonth: false,
      fullDate: formattedDate(d)
    })
  }
  
  const currentDays = []
  for (let i = 1; i <= lastDate; i++) {
    const d = new Date(year, month, i)
    currentDays.push({
      date: d,
      currentMonth: true,
      isToday: d.toDateString() === new Date().toDateString(),
      fullDate: formattedDate(d)
    })
  }
  
  const nextDays = []
  const remaining = 42 - (prevDays.length + currentDays.length)
  for (let i = 1; i <= remaining; i++) {
    const d = new Date(year, month + 1, i)
    nextDays.push({
      date: d,
      currentMonth: false,
      fullDate: formattedDate(d)
    })
  }
  
  return [...prevDays, ...currentDays, ...nextDays]
})

const getDayEvents = (fullDate) => {
  return events.value.filter(e => {
    return formattedDate(e.start) === fullDate
  }).sort((a, b) => {
    if (a.all_day && !b.all_day) return -1
    return new Date(a.start) - new Date(b.start)
  })
}

const selectDate = (day) => {
  if (selectedDate.value.toDateString() === day.date.toDateString()) {
    openEventModal({ 
      start: `${day.fullDate}T09:00`, 
      end: `${day.fullDate}T09:30`,
      all_day: false, 
      is_editable: true 
    })
  } else {
    setSelectedDate(day.date)
  }
}
</script>

<template>
  <div class="flex flex-col h-full bg-base-100 border-l border-black/20 dark:border-white/20">
    <!-- Day headers -->
    <div class="grid grid-cols-7 border-b border-black/20 dark:border-white/20 bg-base-100 z-10">
      <div v-for="d in ['SUN', 'MON', 'TUE', 'WED', 'THU', 'FRI', 'SAT']" :key="d" 
           class="py-2 text-center text-[11px] font-bold text-base-content/40 tracking-widest uppercase">
        {{ d }}
      </div>
    </div>

    <!-- Month Grid -->
    <div class="flex-1 grid grid-cols-7 grid-rows-6 border-black/20 dark:border-white/20">
       <div v-for="(day, idx) in days" :key="idx" 
            @click="selectDate(day)"
            class="border-r border-b border-black/20 dark:border-white/20 min-h-0 flex flex-col p-1 hover:bg-base-200/20 transition-colors cursor-pointer group"
            :class="{ 'bg-black/10 dark:bg-white/10': !day.currentMonth }">
         
         <div class="flex justify-center mt-1">
            <div class="w-8 h-8 flex items-center justify-center rounded-full text-sm"
                 :class="{
                   'bg-primary text-primary-content font-bold': day.isToday,
                   'text-base-content/30': !day.currentMonth && !day.isToday,
                   'font-bold text-primary': day.date.toDateString() === selectedDate.toDateString() && !day.isToday
                 }">
              {{ day.date.getDate() }}
            </div>
         </div>

         <!-- Event Indicators (max 3-4 labels) -->
         <div class="flex flex-col gap-1 mt-2 overflow-hidden px-1">
            <div v-for="event in getDayEvents(day.fullDate).slice(0, 4)" :key="event.id"
                 @click.stop="openEventModal(event)"
                 class="px-1.5 py-0.5 rounded text-[10px] font-medium truncate cursor-pointer hover:brightness-110 transition-all"
                 :style="{ 
                   backgroundColor: event.source === 'GOOGLE' ? '#4285F4' : '#3b82f6',
                   color: 'white'
                 }">
              <span v-if="!event.all_day" class="opacity-80">{{ new Date(event.start).getHours() }}:{{ new Date(event.start).getMinutes().toString().padStart(2, '0') }}</span>
              {{ event.title }}
            </div>
            <div v-if="getDayEvents(day.fullDate).length > 4" class="text-[9px] text-center font-bold text-base-content/40">
               +{{ getDayEvents(day.fullDate).length - 4 }} more
            </div>
         </div>
       </div>
    </div>
  </div>
</template>

<style scoped>
.grid-rows-6 {
  grid-template-rows: repeat(6, 1fr);
}
</style>
