<script setup>
import { ref, onMounted } from 'vue'
import { FileText, CheckCircle, Clock, Plus, Users, FileStack } from 'lucide-vue-next'
import api from '@/services/api'

const stats = ref([
  {
    title: 'Sent Memos',
    value: '0',
    subtitle: 'This month',
    icon: FileText,
    iconColor: 'text-primary',
    bgColor: 'bg-primary/10'
  },
  {
    title: 'Acknowledged',
    value: '0',
    subtitle: 'Completed',
    icon: CheckCircle,
    iconColor: 'text-primary-content',
    bgColor: 'bg-primary',
    isFilled: true
  },
  {
    title: 'Pending',
    value: '0',
    subtitle: 'Awaiting response',
    icon: Clock,
    iconColor: 'text-primary',
    bgColor: 'bg-primary/10'
  }
])

// Calendar Logic
const currentDate = ref(new Date())
const calendarDays = ref([])
const weekDays = ['S', 'M', 'T', 'W', 'T', 'F', 'S']
const currentMonth = currentDate.value.toLocaleString('default', { month: 'long', year: 'numeric' })

const generateCalendar = () => {
    const year = currentDate.value.getFullYear()
    const month = currentDate.value.getMonth()
    const firstDay = new Date(year, month, 1).getDay()
    const daysInMonth = new Date(year, month + 1, 0).getDate()
    
    // Simple calendar generation
    let days = []
    for (let i = 0; i < firstDay; i++) days.push({ day: '', isCurrent: false })
    for (let i = 1; i <= daysInMonth; i++) days.push({ day: i, isCurrent: true, isToday: i === new Date().getDate() })
    
    // Fill remaining
    while (days.length < 35) days.push({ day: '', isCurrent: false })
    
    calendarDays.value = days
}

const fetchDashboardData = async () => {
  try {
    const response = await api.get('/dashboard')
    const { user_stats, stats: globalStats } = response.data
    
    // Map backend data to UI
    stats.value[0].value = user_stats.sent_memos || 0
    // "Acknowledged" - mocking for now or deriving from completed memos logic if available
    stats.value[1].value = 0 
    stats.value[2].value = globalStats.pending_memos || 0 // Or user-specific pending
    
  } catch (error) {
    console.error('Error fetching secretary dashboard:', error)
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
          <h1 class="text-4xl font-bold mb-2">Secretary Dashboard</h1>
          <p class="text-white/80 text-lg">Welcome back, Department Secretary!</p>
        </div>
        
        <!-- Illustration Placeholder (Right side) -->
        <div class="absolute right-0 top-0 bottom-0 w-1/3 hidden lg:flex items-center justify-center p-4">
             <!-- Using a generic illustration placeholder style -->
             <img src="@/assets/images/images/Admin_Dashboard_welcome.png" class="h-full object-contain opacity-80" alt="Illustration" />
        </div>
      </div>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
      <!-- Left Column (Stats & Recent Memos) -->
      <div class="lg:col-span-2 space-y-8">
        
        <!-- Stats Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
          <div v-for="(stat, index) in stats" :key="index" class="bg-base-100 p-6 rounded-2xl shadow-sm border border-base-300 flex items-center gap-4 hover:shadow-md transition-shadow">
            <div 
                class="w-12 h-12 rounded-xl flex items-center justify-center shrink-0"
                :class="[stat.bgColor, stat.isFilled ? 'shadow-lg shadow-primary/20' : '']"
            >
              <component :is="stat.icon" class="w-6 h-6" :class="stat.iconColor" />
            </div>
            <div>
              <p class="text-sm font-medium text-base-content/60">{{ stat.title }}</p>
              <h3 class="text-2xl font-bold text-base-content">{{ stat.value }}</h3>
              <p class="text-xs text-base-content/40 mt-0.5">{{ stat.subtitle }}</p>
            </div>
          </div>
        </div>

        <!-- Recent Memos Section -->
        <div class="bg-base-100 rounded-2xl p-6 border border-base-300 min-h-[300px]">
          <h2 class="text-xl font-bold text-base-content mb-6">Recent Memos</h2>
          
          <!-- Empty State -->
          <div class="flex flex-col items-center justify-center h-64 text-center">
            <div class="w-16 h-16 bg-base-200 rounded-full flex items-center justify-center mb-4">
               <FileText class="w-8 h-8 text-base-content/30" />
            </div>
            <p class="text-base-content font-medium mb-1">No memos yet</p>
            <p class="text-base-content/60 text-sm">Your drafted and sent memos will appear here</p>
          </div>
        </div>
      </div>

      <!-- Right Column (Calendar & Quick Actions) -->
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
                    date.isToday ? 'bg-primary text-primary-content font-bold shadow-md shadow-primary/20' : 'text-base-content hover:bg-base-200',
                    !date.day ? 'pointer-events-none' : ''
                ]"
             >
                {{ date.day }}
             </div>
          </div>
        </div>

        <!-- Quick Actions -->
        <div class="bg-base-100 rounded-2xl p-6 border border-base-300">
           <h3 class="font-bold text-base-content mb-4">Quick Actions</h3>
           <div class="space-y-3">
             <button class="w-full btn btn-primary border-0 flex items-center gap-2 rounded-xl py-3 normal-case h-auto shadow-lg shadow-primary/10">
               <Plus class="w-5 h-5" />
               Distribute Memo
             </button>
             
             <button class="w-full btn btn-outline btn-primary flex items-center gap-2 rounded-xl py-3 normal-case h-auto">
               <FileStack class="w-5 h-5" />
               View All Memos
             </button>

             <button class="w-full btn btn-outline btn-primary flex items-center gap-2 rounded-xl py-3 normal-case h-auto">
               <Users class="w-5 h-5" />
               Department Members
             </button>
           </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
@reference "../../style.css";
/* Custom overrides for specific design tweaks if needed */
</style>
