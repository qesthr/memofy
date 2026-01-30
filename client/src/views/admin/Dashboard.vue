<script setup>
import { ref, onMounted } from 'vue'
import { FileText, Hourglass, AlertCircle, Users, RefreshCw } from 'lucide-vue-next'

// Statistics data - initialized with zeros/placeholders
const stats = ref([
  {
    title: 'Total Memos',
    value: '0',
    icon: FileText,
    color: 'text-blue-500',
    bgColor: 'bg-blue-50'
  },
  {
    title: 'Pending',
    value: '0',
    icon: Hourglass,
    color: 'text-purple-500',
    bgColor: 'bg-purple-50'
  },
  {
    title: 'Overdue Memos',
    value: '0',
    icon: AlertCircle,
    color: 'text-orange-500',
    bgColor: 'bg-orange-50'
  },
  {
    title: 'Active Users',
    value: '0',
    icon: Users,
    color: 'text-green-500',
    bgColor: 'bg-green-50'
  }
])

// Calendar data
const currentDate = new Date()
const currentMonth = currentDate.toLocaleString('default', { month: 'long', year: 'numeric' })
const daysInMonth = new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 0).getDate()
const firstDayOfMonth = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1).getDay()

const calendarDays = ref([])
const generateCalendar = () => {
  const days = []
  const prevMonthDays = new Date(currentDate.getFullYear(), currentDate.getMonth(), 0).getDate()
  
  // Previous month days
  for (let i = firstDayOfMonth - 1; i >= 0; i--) {
    days.push({ day: prevMonthDays - i, isCurrentMonth: false })
  }
  
  // Current month days
  for (let i = 1; i <= daysInMonth; i++) {
    days.push({ day: i, isCurrentMonth: true, isToday: i === currentDate.getDate() })
  }
  
  // Next month days to fill the grid
  const remainingDays = 42 - days.length
  for (let i = 1; i <= remainingDays; i++) {
    days.push({ day: i, isCurrentMonth: false })
  }
  
  calendarDays.value = days
}

generateCalendar()

const weekDays = ['S', 'M', 'T', 'W', 'T', 'F', 'S']

// Fetch dashboard data
import api from '../../services/api'

const fetchDashboardData = async () => {
  try {
    const response = await api.get('/admin/dashboard-stats')
    // Stats array format strictly matches what backend returns
    stats.value = response.data
  } catch (error) {
    console.error('Error fetching dashboard data:', error)
  }
}

onMounted(() => {
  fetchDashboardData()
})
</script>

<template>
  <div class="dashboard">
    <!-- Welcome Banner -->
    <div class="welcome-banner">
      <div class="banner-content">
        <div class="banner-text">
          <h1 class="text-3xl font-bold text-white">Admin Dashboard</h1>
          <p class="text-white/80 mt-1">Welcome back!</p>
        </div>
        <div class="banner-image">
          <img 
            src="../assets/images/images/Admin_Dashboard_welcome.png" 
            alt="Welcome Illustration" 
            class="w-full h-full object-contain"
          />
        </div>
      </div>
    </div>

    <!-- Statistics Cards -->
    <div class="stats-grid">
      <div 
        v-for="(stat, index) in stats" 
        :key="index"
        class="stat-card"
      >
        <div class="stat-icon" :class="stat.bgColor">
          <component :is="stat.icon" :size="24" :class="stat.color" />
        </div>
        <div class="stat-info">
          <p class="stat-title">{{ stat.title }}</p>
          <p class="stat-value">{{ stat.value }}</p>
        </div>
      </div>
    </div>

    <!-- Main Content Grid -->
    <div class="content-grid">
      <!-- Recent Memos -->
      <div class="content-card recent-memos">
        <h2 class="card-title">Recent Memos</h2>
        <div class="empty-state">
          <FileText :size="48" class="text-base-content/20" />
          <p class="text-base-content/60 font-medium">No memos yet</p>
          <p class="text-base-content/40 text-sm">New submissions will appear here</p>
        </div>
      </div>

      <!-- Right Column -->
      <div class="right-column">
        <!-- Calendar Widget -->
        <div class="content-card calendar-widget">
          <div class="calendar-header">
            <h3 class="font-semibold">{{ currentMonth }}</h3>
            <div class="calendar-nav">
              <button class="btn btn-ghost btn-xs btn-circle">‹</button>
              <button class="btn btn-ghost btn-xs btn-circle">›</button>
            </div>
          </div>
          
          <div class="calendar-grid">
            <div 
              v-for="day in weekDays" 
              :key="'week-' + day"
              class="calendar-weekday"
            >
              {{ day }}
            </div>
            <div 
              v-for="(dateObj, index) in calendarDays" 
              :key="'day-' + index"
              class="calendar-day"
              :class="{
                'other-month': !dateObj.isCurrentMonth,
                'today': dateObj.isToday
              }"
            >
              {{ dateObj.day }}
            </div>
          </div>
        </div>

        <!-- System Status -->
        <div class="content-card system-status">
          <div class="status-header">
            <div class="flex items-center gap-2">
              <div class="status-indicator"></div>
              <div>
                <h3 class="font-semibold text-sm">System Status</h3>
                <p class="text-xs text-success">All systems operational</p>
              </div>
            </div>
            <button class="btn btn-ghost btn-xs btn-circle">
              <RefreshCw :size="14" />
            </button>
          </div>
          <p class="text-xs text-base-content/50 mt-2">Updated 1 second ago</p>
          <button class="btn btn-ghost btn-sm w-full mt-3 text-xs">
            Show Details
            <span class="ml-1">▼</span>
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
@reference "../../style.css";

.dashboard {
  @apply space-y-6;
}

/* Welcome Banner */
.welcome-banner {
  @apply rounded-2xl overflow-hidden;
  background: linear-gradient(135deg, #1e1b4b 0%, #312e81 100%);
  min-height: 200px;
}

.banner-content {
  @apply flex items-center justify-between p-8;
}

.banner-text h1 {
  @apply text-white;
}

.banner-image {
  @apply w-64 h-40;
}

/* Statistics Grid */
.stats-grid {
  @apply grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4;
}

.stat-card {
  @apply bg-base-100 rounded-xl p-5 flex items-center gap-4;
  @apply border border-base-300;
  @apply hover:shadow-md transition-shadow duration-200;
}

.stat-icon {
  @apply w-12 h-12 rounded-lg flex items-center justify-center;
}

.stat-info {
  @apply flex-1;
}

.stat-title {
  @apply text-sm text-base-content/60 mb-1;
}

.stat-value {
  @apply text-2xl font-bold text-base-content;
}

/* Content Grid */
.content-grid {
  @apply grid grid-cols-1 lg:grid-cols-3 gap-6;
}

.content-card {
  @apply bg-base-100 rounded-xl p-6 border border-base-300;
}

.card-title {
  @apply text-lg font-semibold mb-4;
}

.recent-memos {
  @apply lg:col-span-2;
}

.empty-state {
  @apply flex flex-col items-center justify-center py-12;
  @apply text-center;
}

.empty-state > * + * {
  @apply mt-2;
}

/* Right Column */
.right-column {
  @apply space-y-6;
}

/* Calendar Widget */
.calendar-widget {
  @apply p-5;
}

.calendar-header {
  @apply flex items-center justify-between mb-4;
}

.calendar-nav {
  @apply flex gap-1;
}

.calendar-grid {
  @apply grid grid-cols-7 gap-1;
}

.calendar-weekday {
  @apply text-center text-xs font-semibold text-base-content/60 py-2;
}

.calendar-day {
  @apply text-center text-sm py-2 rounded-lg;
  @apply hover:bg-base-200 cursor-pointer transition-colors;
}

.calendar-day.other-month {
  @apply text-base-content/30;
}

.calendar-day.today {
  @apply bg-primary text-primary-content font-semibold;
}

/* System Status */
.system-status {
  @apply p-5;
}

.status-header {
  @apply flex items-start justify-between;
}

.status-indicator {
  @apply w-10 h-10 rounded-full bg-success/20 flex items-center justify-center;
  position: relative;
}

.status-indicator::after {
  content: '✓';
  @apply text-success font-bold text-lg;
  position: absolute;
}
</style>
