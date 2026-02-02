<script setup>
import { ref, onMounted } from 'vue'
import { FileText, Cloud, RefreshCw } from 'lucide-vue-next'
import api from '@/services/api'

const stats = ref([
  {
    title: 'Received Memos',
    value: '0',
    subtitle: 'Total memos',
    icon: FileText,
    iconColor: 'text-blue-600',
    bgColor: 'bg-white', // Design shows minimal white card for this single stat? Or maybe simple colored icon
    isSimple: true // Flag to render simpler style if needed
  }
])

// Calendar Logic
const currentDate = ref(new Date())
const calendarDays = ref([])
const weekDays = ['S', 'M', 'T', 'W', 'T', 'F', 'S']
const currentMonth = currentDate.value.toLocaleString('default', { month: 'long', year: 'numeric' })
const selectedDay = ref(30) // Hardcoded for design match example, or real logic

const generateCalendar = () => {
    const year = currentDate.value.getFullYear()
    const month = currentDate.value.getMonth()
    const firstDay = new Date(year, month, 1).getDay()
    const daysInMonth = new Date(year, month + 1, 0).getDate()
    
    let days = []
    for (let i = 0; i < firstDay; i++) days.push({ day: '', isCurrent: false })
    for (let i = 1; i <= daysInMonth; i++) days.push({ day: i, isCurrent: true, isToday: i === new Date().getDate(), isSelected: i === 30 })
    while (days.length < 35) days.push({ day: '', isCurrent: false })
    
    calendarDays.value = days
}

const fetchDashboardData = async () => {
  try {
    const response = await api.get('/dashboard')
    const { user_stats } = response.data
    
    // Map backend data to UI
    stats.value[0].value = user_stats.received_memos || 0
    
  } catch (error) {
    console.error('Error fetching faculty dashboard:', error)
  }
}

onMounted(() => {
  generateCalendar()
  fetchDashboardData()
})
</script>

<template>
  <div class="dashboard-content">
    <!-- Welcome Header -->
    <div class="flex items-center justify-between mb-8">
      <div class="relative w-full overflow-hidden bg-[#0f172a] rounded-3xl p-8 lg:p-12 text-white">
        <div class="relative z-10 max-w-lg">
          <h1 class="text-4xl font-bold mb-2">Faculty Dashboard</h1>
          <p class="text-white/80 text-lg">Welcome back!</p>
        </div>
        
        <!-- Illustration Placeholder -->
        <div class="absolute right-0 top-0 bottom-0 w-1/3 hidden lg:flex items-center justify-center p-4">
             <img src="@/assets/images/images/Admin_Dashboard_welcome.png" class="h-full object-contain opacity-80" alt="Illustration" />
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      <!-- Left Column (Stats & Recent Memos) -->
      <div class="lg:col-span-2 space-y-8">
        
        <!-- Stats Row (Single Card for Faculty usually, maybe more later) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
           <div class="bg-base-100 p-6 rounded-2xl shadow-sm border border-base-300 flex items-center gap-4">
            <div class="w-12 h-12 rounded-xl bg-secondary/10 flex items-center justify-center shrink-0">
              <FileText class="w-6 h-6 text-secondary" />
            </div>
            <div>
              <p class="text-sm font-medium text-base-content/60">Received Memos</p>
              <h3 class="text-2xl font-bold text-base-content">{{ stats[0].value }}</h3>
              <p class="text-xs text-base-content/40 mt-0.5">Total memos</p>
            </div>
          </div>
        </div>

        <!-- Recent Memos Section -->
        <div class="bg-base-100 rounded-2xl p-6 border border-base-300 min-h-[400px]">
          <h2 class="text-xl font-bold text-base-content mb-6">Recent Memos</h2>
          
          <!-- Empty State -->
          <div class="flex flex-col items-center justify-center h-64 text-center">
            <div class="w-16 h-16 bg-base-200 rounded-full flex items-center justify-center mb-4">
               <FileText class="w-8 h-8 text-base-content/30" />
            </div>
            <p class="text-base-content font-medium mb-1">No memos received yet</p>
            <p class="text-base-content/60 text-sm">Memos sent to you will appear here</p>
          </div>
        </div>
      </div>

      <!-- Right Column (Calendar & System Status) -->
      <div class="space-y-8">
        <!-- Calendar -->
        <div class="bg-base-100 rounded-2xl p-6 border border-base-300">
          <div class="flex items-center justify-between mb-6">
            <h3 class="font-bold text-base-content">{{ currentMonth }}</h3>
            <div class="flex gap-2">
              <button class="p-1 hover:bg-base-200 rounded-full text-base-content/40 text-xs">‹</button>
              <button class="p-1 hover:bg-base-200 rounded-full text-base-content/40 text-xs">›</button>
            </div>
          </div>
          
          <div class="grid grid-cols-7 mb-2">
            <div v-for="day in weekDays" :key="day" class="text-center text-xs font-semibold text-base-content/40 py-2">
              {{ day }}
            </div>
          </div>
          <div class="grid grid-cols-7 gap-1">
             <div 
                v-for="(date, i) in calendarDays" 
                :key="i"
                class="aspect-square flex items-center justify-center text-sm rounded-lg"
                :class="[
                    date.isSelected ? 'bg-base-100 border-2 border-secondary text-secondary font-bold' : '',
                    !date.isSelected && date.isToday ? 'bg-secondary text-secondary-content font-bold' : 'text-base-content hover:bg-base-200',
                    !date.day ? 'pointer-events-none' : ''
                ]"
             >
                {{ date.day }}
             </div>
          </div>
        </div>

        <!-- System Status -->
        <div class="bg-base-100 rounded-2xl p-6 border border-base-300">
           <h3 class="font-bold text-base-content mb-4">System Status</h3>
           <div class="flex items-start gap-4">
             <div class="shrink-0">
               <Cloud class="w-10 h-10 text-primary" />
             </div>
             <div>
               <p class="font-bold text-base-content text-sm">All systems operational.</p>
               <div class="mt-1 space-y-1 text-xs text-base-content/50">
                 <p>Memo delivery: Normal</p>
                 <p>Calendar sync: Normal</p>
                 <p>Email notifications: Normal</p>
               </div>
               <p class="mt-3 text-xs text-base-content/40">If you notice any issues, please contact your department secretary.</p>
             </div>
           </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
@reference "../../style.css";
</style>
