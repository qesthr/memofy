<script setup>
import { computed, onMounted } from 'vue'
import { useCalendar } from '@/composables/useCalendar'
import MiniCalendar from './MiniCalendar.vue'
import WeekView from './WeekView.vue'
import MonthView from './MonthView.vue'
import DayView from './DayView.vue'
import EventModal from './EventModal.vue'
import { ChevronLeft, ChevronRight, ChevronDown, Search, Settings, Calendar as CalendarIcon, Plus } from 'lucide-vue-next'
import GoogleCalendarConnect from '@/components/GoogleCalendarConnect.vue'

const { 
  selectedDate, 
  currentView, 
  setView, 
  next, 
  prev, 
  today,
  setSelectedDate,
  openEventModal,
  isGoogleConnected,
  checkGoogleStatus
} = useCalendar()

onMounted(() => {
  checkGoogleStatus()
})

const viewOptions = [
  { label: 'Day', value: 'DAY' },
  { label: 'Week', value: 'WEEK' },
  { label: 'Month', value: 'MONTH' },
  { label: 'Year', value: 'YEAR' }
]

const currentTitle = computed(() => {
  const options = { month: 'long', year: 'numeric' }
  if (currentView.value === 'DAY') {
    return selectedDate.value.toLocaleDateString('en-US', { ...options, day: 'numeric' })
  }
  return selectedDate.value.toLocaleDateString('en-US', options)
})
</script>

<template>
  <div class="flex flex-col h-full bg-base-100 overflow-hidden">
    <!-- Header -->
    <header class="flex items-center justify-between px-4 py-2 border-b border-black/20 dark:border-white/20">
      <div class="flex items-center gap-4">
        <div class="flex items-center gap-2 mr-4">
           <CalendarIcon class="text-primary" :size="24" />
           <span class="text-xl font-semibold hidden md:block">Calendar</span>
        </div>
        
        <button @click="today" class="btn btn-sm btn-outline px-4 rounded-md">Today</button>
        
        <div class="flex items-center gap-1 ml-2">
          <button @click="prev" class="btn btn-sm btn-ghost btn-circle">
            <ChevronLeft :size="20" />
          </button>
          <button @click="next" class="btn btn-sm btn-ghost btn-circle">
            <ChevronRight :size="20" />
          </button>
        </div>
        
        <h2 class="text-xl font-medium ml-4">{{ currentTitle }}</h2>
      </div>

      <div class="flex items-center gap-4">
        <div class="relative hidden lg:block">
          <Search class="absolute left-3 top-1/2 -translate-y-1/2 text-base-content/40" :size="18" />
          <input type="text" placeholder="Search" class="input input-sm input-bordered pl-10 w-64 bg-base-200/50 border-none rounded-md" />
        </div>

        <!-- View Selector -->
        <div class="dropdown dropdown-end">
          <label tabindex="0" class="btn btn-sm btn-outline gap-2 px-4 rounded-md min-w-[100px]">
            {{ viewOptions.find(o => o.value === currentView)?.label }}
            <ChevronDown :size="16" />
          </label>
          <ul tabindex="0" class="dropdown-content z-[100] menu p-2 shadow bg-base-100 rounded-box w-40 border border-base-200">
            <li v-for="opt in viewOptions" :key="opt.value">
              <a @click="setView(opt.value)" :class="{ 'active': currentView === opt.value }">
                {{ opt.label }}
              </a>
            </li>
          </ul>
        </div>
        
        <GoogleCalendarConnect :connected="isGoogleConnected" @update="checkGoogleStatus" />
      </div>
    </header>

    <!-- Main Body -->
    <div class="flex-1 flex overflow-hidden border-t border-black/20 dark:border-white/20">
      <!-- Sidebar -->
      <aside class="w-64 flex-shrink-0 border-r border-black/20 dark:border-white/20 p-4 flex flex-col gap-6 hidden md:flex overflow-y-auto custom-scrollbar">
        <button @click="openEventModal()" class="btn btn-primary text-white gap-2 w-full rounded-full shadow-md hover:shadow-lg transition-all mb-2">
          <Plus :size="20" />
          <span class="font-bold">Add Event</span>
        </button>

        <MiniCalendar />
        
        <!-- My Calendars (Legend) -->
        <div class="flex flex-col gap-4">
           <h3 class="text-xs font-bold text-base-content/50 uppercase tracking-wider">My Calendars</h3>
           <div class="flex flex-col gap-2">
              <label class="flex items-center gap-3 cursor-pointer">
                <input type="checkbox" checked class="checkbox checkbox-xs checkbox-primary" />
                <span class="text-sm">App Events</span>
              </label>
              <label class="flex items-center gap-3 cursor-pointer">
                <input type="checkbox" checked class="checkbox checkbox-xs border-[#4285F4] [--chkbg:#4285F4] [--chkfg:white]" />
                <span class="text-sm">Google Calendar</span>
                <span v-if="!isGoogleConnected" class="text-[10px] text-base-content/40 uppercase font-bold">(Offline)</span>
              </label>
           </div>
        </div>
      </aside>

      <!-- Grid Content -->
      <main class="flex-1 overflow-hidden relative bg-base-100">
        <DayView v-if="currentView === 'DAY'" />
        <WeekView v-else-if="currentView === 'WEEK'" />
        <MonthView v-else-if="currentView === 'MONTH'" />
        <div v-else class="flex items-center justify-center h-full text-base-content/40">
          Year View coming soon...
        </div>
      </main>
    </div>

    <!-- Event Modal -->
    <EventModal />
  </div>
</template>

<style scoped>
@reference "../../style.css";

.custom-scrollbar::-webkit-scrollbar {
  width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background-color: #cbd5e1;
  border-radius: 4px;
}

.btn-sm {
  @apply h-9 min-h-[36px];
}
</style>
