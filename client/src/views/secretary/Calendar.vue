<script setup>
import { ref, onMounted, computed, watch } from 'vue'
import { Plus, ChevronLeft, ChevronRight, Search } from 'lucide-vue-next'
import api from '../../services/api'
import GoogleCalendarConnect from '../../components/GoogleCalendarConnect.vue'

const currentDate = ref('')
const currentFullDate = ref('')
// Use a real Date object for state
const currentViewDate = ref(new Date())
const selectedDate = ref(new Date().getDate())

const updateDates = () => {
  const now = currentViewDate.value
  currentDate.value = now.toLocaleString('default', { month: 'long', year: 'numeric' })
  currentFullDate.value = now.toLocaleString('default', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' })
  selectedDate.value = now.getDate()
}

// Navigation
const prevDay = () => {
  const d = new Date(currentViewDate.value)
  d.setDate(d.getDate() - 1)
  currentViewDate.value = d
  updateDates()
  fetchEvents()
}

const nextDay = () => {
  const d = new Date(currentViewDate.value)
  d.setDate(d.getDate() + 1)
  currentViewDate.value = d
  updateDates()
  fetchEvents()
}

// Google Calendar Logic
const isGoogleConnected = ref(false)
const checkGoogleStatus = async () => {
  try {
    const res = await api.get('/current-user')
    isGoogleConnected.value = !!res.data.user.google_calendar_token
  } catch (err) {
    console.error('Auth check failed', err)
  }
}

const events = ref([])
const googleEvents = ref([])

const fetchEvents = async () => {
  // 1. Fetch Local Events
  try {
    const response = await api.get('/calendar/local-events')
    events.value = response.data.map(e => ({...e, is_google: false}))
  } catch (error) {
    console.error('Error fetching events:', error)
  }

  // 2. Fetch Google Events (if connected)
  if (isGoogleConnected.value) {
    try {
      // Calculate start/end of view
      const start = new Date(currentViewDate.value)
      start.setHours(0,0,0,0)
      const end = new Date(currentViewDate.value)
      end.setHours(23,59,59,999)
      
      const response = await api.get('/calendar/events', {
        params: {
          start: start.toISOString(),
          end: end.toISOString()
        }
      })
      googleEvents.value = response.data || []
    } catch (error) {
      console.error('Error fetching google events:', error)
      if (error.response?.status === 401 || error.response?.status === 500) {
        isGoogleConnected.value = false // Assuming disconnected
      }
    }
  } else {
    googleEvents.value = []
  }
}

// Merge and Filter for Display
const dayEvents = computed(() => {
  const all = [...events.value, ...googleEvents.value]
  
  // Filter for current view date
  return all.filter(e => {
    const eDate = new Date(e.start)
    const vDate = currentViewDate.value
    return eDate.getDate() === vDate.getDate() &&
           eDate.getMonth() === vDate.getMonth() &&
           eDate.getFullYear() === vDate.getFullYear()
  })
})

const getEventStyle = (event) => {
  const start = new Date(event.start)
  const end = new Date(event.end)
  
  // Start Hour (relative to 4 AM)
  let startHour = start.getHours()
  let startMin = start.getMinutes()
  
  // If before 4 AM, clamp or hide? 
  // For simplicity, assume events are within view or clamp
  if (startHour < 4) { startHour = 4; startMin = 0; }
  
  const minutesFromStart = ((startHour - 4) * 60) + startMin
  const durationMinutes = (end - start) / (1000 * 60)
  
  // 1 hour = 80px (h-20) -> 1 min = 80/60
  const pxPerMin = 80 / 60
  
  const top = minutesFromStart * pxPerMin
  const height = Math.max(durationMinutes * pxPerMin, 20) // Min height
  
  return {
    top: `${top}px`,
    height: `${height}px`,
    backgroundColor: event.color || (event.is_google ? '#E8F0FE' : '#EBF5FF'),
    borderColor: event.is_google ? '#4285F4' : '#3B82F6',
    borderLeftWidth: '4px',
    color: event.is_google ? '#1967D2' : '#1E40AF'
  }
}

const onCalendarUpdate = () => {
  checkGoogleStatus().then(() => fetchEvents())
}

onMounted(() => {
  updateDates()
  checkGoogleStatus().then(() => fetchEvents())
})
</script>

<template>
  <div class="view-container h-full flex flex-col">
    <!-- Header with Search and Connect -->
    <div class="mb-6 flex justify-between items-center">
      <div class="relative max-w-xl w-full">
        <Search :size="20" class="absolute left-4 top-1/2 -translate-y-1/2 text-base-content/40" />
        <input 
          type="text" 
          placeholder="Search events..." 
          class="input input-bordered w-full pl-12 rounded-full bg-base-100 shadow-sm"
        />
      </div>
      
      <!-- Connect Button -->
      <GoogleCalendarConnect :connected="isGoogleConnected" @update="onCalendarUpdate" />
    </div>

    <!-- Main Content -->
    <div class="flex gap-6 h-[calc(100vh-12rem)]">
      <!-- Left Sidebar (Mini Calendar etc) -->
      <div class="w-80 flex-shrink-0 flex flex-col gap-6">
        <!-- Mini Calendar -->
        <div class="bg-base-100 rounded-xl border border-base-200 p-4 shadow-sm">
           <!-- (Kept simplified) -->
           <h3 class="font-bold text-sm mb-4">{{ currentDate }}</h3>
           
           <!-- Calendar Grid (Visual Only for now in sidebar) -->
           <div class="flex items-center justify-between mb-4">
            <div class="flex gap-1">
              <button class="btn btn-xs btn-ghost btn-square" @click="prevDay"><ChevronLeft :size="14" /></button>
              <button class="btn btn-xs btn-ghost btn-square" @click="nextDay"><ChevronRight :size="14" /></button>
            </div>
           </div>
           
           <!-- Date Grid Mockup -->
           <div class="grid grid-cols-7 gap-1 text-center text-xs mb-2">
            <span class="text-base-content/40 py-1">S</span>
            <span class="text-base-content/40 py-1">M</span>
            <span class="text-base-content/40 py-1">T</span>
            <span class="text-base-content/40 py-1">W</span>
            <span class="text-base-content/40 py-1">T</span>
            <span class="text-base-content/40 py-1">F</span>
            <span class="text-base-content/40 py-1">S</span>
           </div>
           
           <div class="grid grid-cols-7 gap-1 text-center text-sm">
             <span v-for="i in 30" :key="i" class="py-2 text-base-content/60">{{ i }}</span>
           </div>
        </div>

        <!-- My Calendars -->
        <div class="px-2">
          <h3 class="font-bold text-sm mb-3 text-base-content/80">My Calendars</h3>
          <!-- Updated Legend -->
          <div class="flex flex-col gap-2">
             <div class="flex items-center gap-2">
                <span class="w-3 h-3 rounded-full bg-blue-500"></span>
                <span class="text-sm">App Events</span>
             </div>
             <div v-if="isGoogleConnected" class="flex items-center gap-2">
                <span class="w-3 h-3 rounded-full bg-[#4285F4]"></span>
                <span class="text-sm">Google Calendar</span>
             </div>
          </div>
        </div>
      </div>

      <!-- Main Calendar View -->
      <div class="flex-1 bg-base-100 rounded-xl border border-base-200 shadow-sm flex flex-col">
        <!-- Header -->
        <div class="p-4 border-b border-base-200 flex items-center justify-between">
          <div class="flex items-center gap-4">
            <button class="btn btn-primary text-white btn-sm px-4">Add Event</button>
            <button class="btn btn-ghost btn-sm btn-square" @click="prevDay"><ChevronLeft :size="18" /></button>
            <h2 class="font-bold text-lg w-64 text-center">{{ currentFullDate }}</h2>
            <button class="btn btn-ghost btn-sm btn-square" @click="nextDay"><ChevronRight :size="18" /></button>
          </div>
          
          <div class="join">
            <button class="join-item btn btn-sm bg-base-100 active">Day</button>
            <button class="join-item btn btn-sm bg-base-100">Week</button>
            <button class="join-item btn btn-sm bg-base-100">Month</button>
          </div>
        </div>

        <!-- Day View Grid -->
        <div class="flex-1 overflow-y-auto relative p-4 custom-scrollbar">
          <div class="relative min-h-[1440px]"> <!-- 18 hours * 80px -->
             
             <!-- Sidebar Time Labels -->
             <div class="absolute left-0 top-0 bottom-0 w-16 border-r border-base-200 bg-base-50/50 z-20"></div>

             <!-- Render Hours (Grid Lines) -->
             <div class="ml-16 relative">
                 <!-- All Day -->
                <div class="h-12 border-b border-base-100 flex items-center px-4 relative">
                  <span class="text-xs font-semibold text-base-content/60 absolute -left-14">All Day</span>
                  <div class="flex gap-2">
                     <span v-for="ev in dayEvents.filter(e => e.allDay)" :key="ev.id"
                           class="badge badge-primary badge-outline text-xs"
                           :class="{'badge-info': ev.is_google}">
                        {{ ev.title }}
                     </span>
                  </div>
                </div>

                <!-- Time Slots -->
                <div v-for="hour in 18" :key="hour" class="h-20 border-b border-base-100 relative group box-border">
                  <span class="absolute -left-14 top-2 text-xs text-base-content/40 w-12 text-right">
                    {{ hour + 3 }}:00 {{ hour + 3 >= 12 ? 'PM' : 'AM' }}
                  </span>
                  <!-- Half hour line -->
                   <div class="absolute top-1/2 w-full border-t border-dashed border-base-100"></div>
                </div>

                <!-- EVENTS LAYER (Absolute Overlay) -->
                <div class="absolute top-12 left-0 right-0 bottom-0 pointer-events-none">
                   <div v-for="event in dayEvents.filter(e => !e.allDay)" 
                        :key="event.id"
                        class="absolute left-2 right-4 rounded px-2 py-1 text-xs font-medium cursor-pointer overflow-hidden hover:z-10 hover:shadow-lg transition-all pointer-events-auto shadow-sm"
                        :style="getEventStyle(event)">
                      <div class="font-bold truncate">{{ event.title }}</div>
                      <div class="opacity-80 truncate text-[10px]">
                        {{ new Date(event.start).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) }} - 
                        {{ new Date(event.end).toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) }}
                      </div>
                      <div v-if="event.description" class="hidden group-hover:block mt-1 text-[10px] opacity-90 line-clamp-2">
                         {{ event.description }}
                      </div>
                   </div>
                </div>

             </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
@reference "../../style.css";

.view-container {
  @apply p-0;
}
.custom-scrollbar::-webkit-scrollbar {
  width: 6px;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
  background-color: #cbd5e1;
  border-radius: 4px;
}
</style>
