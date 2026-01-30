<script setup>
import { ref, onMounted } from 'vue'
import { Plus, ChevronLeft, ChevronRight, Search } from 'lucide-vue-next'

const currentDate = ref('')
const currentFullDate = ref('')
const selectedDate = ref(new Date().getDate())

const updateDates = () => {
  const now = new Date()
  currentDate.value = now.toLocaleString('default', { month: 'long', year: 'numeric' })
  currentFullDate.value = now.toLocaleString('default', { weekday: 'long', month: 'long', day: 'numeric', year: 'numeric' })
}

import api from '../../services/api'
const events = ref([])

const fetchEvents = async () => {
  try {
    const response = await api.get('/admin/calendar-events')
    events.value = response.data
  } catch (error) {
    console.error('Error fetching events:', error)
  }
}

onMounted(() => {
  updateDates()
  fetchEvents()
})
</script>

<template>
  <div class="view-container h-full flex flex-col">
    <!-- Search Bar -->
    <div class="mb-6">
      <div class="relative max-w-2xl mx-auto">
        <Search :size="20" class="absolute left-4 top-1/2 -translate-y-1/2 text-base-content/40" />
        <input 
          type="text" 
          placeholder="Search events, titles, descriptions..." 
          class="input input-bordered w-full pl-12 rounded-full bg-base-100 shadow-sm"
        />
      </div>
    </div>

    <!-- Main Content -->
    <div class="flex gap-6 h-[calc(100vh-12rem)]">
      <!-- Left Sidebar -->
      <div class="w-80 flex-shrink-0 flex flex-col gap-6">
        <!-- Mini Calendar -->
        <div class="bg-base-100 rounded-xl border border-base-200 p-4 shadow-sm">
          <div class="flex items-center justify-between mb-4">
            <h3 class="font-bold text-sm">{{ currentDate }}</h3>
            <div class="flex gap-1">
              <button class="btn btn-xs btn-ghost btn-square"><ChevronLeft :size="14" /></button>
              <button class="btn btn-xs btn-ghost btn-square"><ChevronRight :size="14" /></button>
            </div>
          </div>
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
            <!-- Simplified calendar grid for demo -->
            <span v-for="i in 3" :key="'prev'+i" class="py-2 text-base-content/20">{{ 28 + i }}</span>
            <span v-for="i in 31" :key="'curr'+i" 
              class="py-2 rounded-lg cursor-pointer hover:bg-base-200"
              :class="{'bg-primary text-primary-content font-bold shadow-md': i === selectedDate, 'text-primary font-semibold': i === 1}"
            >
              {{ i }}
            </span>
            <span v-for="i in 7" :key="'next'+i" class="py-2 text-base-content/20">{{ i }}</span>
          </div>
        </div>

        <!-- My Calendars -->
        <div class="px-2">
          <h3 class="font-bold text-sm mb-3 text-base-content/80">My Calendars</h3>
          <div class="flex flex-col gap-2">
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" checked class="checkbox checkbox-xs checkbox-primary rounded-sm" />
              <span class="text-sm">My Events</span>
            </label>
            <label class="flex items-center gap-2 cursor-pointer">
              <input type="checkbox" class="checkbox checkbox-xs checkbox-primary rounded-sm" />
              <span class="text-sm">Department</span>
            </label>
          </div>
        </div>

        <!-- Categories -->
        <div class="px-2">
          <h3 class="font-bold text-sm mb-3 text-base-content/80">Categories</h3>
          <div class="flex flex-col gap-2">
            <div class="flex items-center gap-2">
              <div class="w-3 h-3 rounded-full bg-warning"></div>
              <span class="text-sm">Today</span>
            </div>
            <div class="flex items-center gap-2">
              <div class="w-3 h-3 rounded-full bg-error"></div>
              <span class="text-sm">Urgent</span>
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
            <div class="w-8 h-8 rounded bg-base-200 flex items-center justify-center">
              <!-- Google Calendar Icon Placeholder -->
              <span class="text-xl">📅</span>
            </div>
            <button class="btn btn-ghost btn-sm btn-square"><ChevronLeft :size="18" /></button>
            <h2 class="font-bold text-lg">{{ currentFullDate }}</h2>
            <button class="btn btn-ghost btn-sm btn-square"><ChevronRight :size="18" /></button>
          </div>
          
          <div class="join">
            <button class="join-item btn btn-sm bg-base-100">Today</button>
            <button class="join-item btn btn-sm bg-base-100">Week</button>
            <button class="join-item btn btn-sm bg-base-100">Month</button>
          </div>
        </div>

        <!-- Day View -->
        <div class="flex-1 overflow-y-auto relative p-4">
          <div class="absolute left-0 top-0 bottom-0 w-16 border-r border-base-200 bg-base-50/50"></div>
          <div class="ml-16">
            <!-- All Day -->
            <div class="h-12 border-b border-base-100 flex items-center px-4">
              <span class="text-xs font-semibold text-base-content/60 absolute left-2">All Day</span>
            </div>
            <!-- Hours -->
            <div v-for="hour in 18" :key="hour" class="h-20 border-b border-base-100 relative group">
              <span class="absolute -left-14 top-2 text-xs text-base-content/40 w-12 text-right">
                {{ hour + 4 }}:00 {{ hour + 4 >= 12 ? 'PM' : 'AM' }}
              </span>
              <!-- Grid lines -->
              <div class="absolute top-1/2 w-full border-t border-dashed border-base-100">
                <span class="absolute -left-14 -top-2 text-[10px] text-base-content/20 w-12 text-right opacity-0 group-hover:opacity-100">
                  {{ hour + 4 }}:30 {{ hour + 4 >= 12 ? 'PM' : 'AM' }}
                </span>
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
</style>
