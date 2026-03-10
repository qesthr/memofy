<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter } from 'vue-router'
import { FileText, Hourglass, AlertCircle, Users, RefreshCw, CheckCircle, Clock, X } from 'lucide-vue-next'

// Statistics data - initialized with zeros/placeholders
const stats = ref([
  {
    title: 'Total Memos',
    value: '0',
    key: 'total_memos',
    icon: FileText,
    color: 'text-primary',
    bgColor: 'bg-primary/10'
  },
  {
    title: 'Acknowledgment Rate',
    value: '0%',
    key: 'acknowledgment_rate',
    icon: CheckCircle,
    color: 'text-violet-600',
    bgColor: 'bg-violet-100'
  },
  {
    title: 'Pending Approval',
    value: '0',
    key: 'pending_approval',
    icon: Hourglass,
    color: 'text-warning',
    bgColor: 'bg-warning/10'
  },
  {
    title: 'Upcoming Deadlines',
    value: '0',
    key: 'upcoming_deadlines',
    icon: AlertCircle,
    color: 'text-error',
    bgColor: 'bg-error/10'
  },
  {
    title: 'Total Users',
    value: '0',
    key: 'total_users',
    icon: Users,
    color: 'text-success',
    bgColor: 'bg-success/10'
  }
])

// Calendar data
const currentDate = new Date()
const currentMonth = currentDate.toLocaleString('default', { month: 'long', year: 'numeric' })
const daysInMonth = new Date(currentDate.getFullYear(), currentDate.getMonth() + 1, 0).getDate()
const firstDayOfMonth = new Date(currentDate.getFullYear(), currentDate.getMonth(), 1).getDay()

const calendarDays = ref([])
const events = ref([])

const recentMemos = ref([])
const selectedEvent = ref(null)
const isEventModalOpen = ref(false)

const openEventModal = (event) => {
  selectedEvent.value = event
  isEventModalOpen.value = true
}

const handleDayClick = (dateObj) => {
  if (!dateObj.isCurrentMonth) return
  const dayEvents = getDayEvents(dateObj.day, dateObj.isCurrentMonth)
  if (dayEvents.length > 0) {
    if (dayEvents.length === 1) {
      openEventModal(dayEvents[0])
    } else {
      // If multiple events, we could show a list, but for now just show the first one
      openEventModal(dayEvents[0])
    }
  }
}

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

const getDayEvents = (day, isCurrentMonth) => {
  if (!isCurrentMonth || !Array.isArray(events.value)) return []
  const dateStr = `${currentDate.getFullYear()}-${String(currentDate.getMonth() + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`
  return events.value.filter(event => event.start && event.start.startsWith(dateStr))
}

const getHighestPriorityColor = (day, isCurrentMonth) => {
  const dayEvents = getDayEvents(day, isCurrentMonth)
  if (dayEvents.length === 0) return null
  
  if (dayEvents.some(e => e.priority === 'high')) return '#F44336'
  if (dayEvents.some(e => e.priority === 'medium')) return '#FF9800'
  if (dayEvents.some(e => e.priority === 'low')) return '#4CAF50'
  
  return '#3B82F6' // Default Blue
}

generateCalendar()

const weekDays = ['S', 'M', 'T', 'W', 'T', 'F', 'S']

const pendingCount = ref(0)
const showPendingBanner = ref(false)
const bannerDismissed = ref(false)

const router = useRouter()

const goToPending = () => {
  router.push('/admin/memos?tab=pending')
}

// Fetch dashboard data
import api from '../../services/api'

const fetchDashboardData = async () => {
  try {
    const response = await api.get('/admin/dashboard-stats')
    const data = response.data
    
    // Update Stats
    stats.value.forEach(stat => {
      const val = data.stats[stat.key] || 0
      stat.value = stat.key === 'acknowledgment_rate' ? `${val}%` : val
      if (stat.key === 'pending_approval') {
        pendingCount.value = val
        if (val > 0 && !bannerDismissed.value) {
          showPendingBanner.value = true
        }
      }
    })

    recentMemos.value = data.recent_memos || []
    events.value = data.calendar_events || []

  } catch (error) {
    console.error('Error fetching dashboard data:', error)
  }
}

const dismissBanner = () => {
  showPendingBanner.value = false
  bannerDismissed.value = true
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
            src="../../assets/images/images/Admin_Dashboard_welcome.png" 
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
        :class="{ 'stat-card-clickable': stat.key === 'pending_approval' }"
        @click="stat.key === 'pending_approval' ? goToPending() : null"
        :title="stat.key === 'pending_approval' ? 'Click to review pending memos' : ''"
      >
        <div class="stat-icon" :class="stat.bgColor">
          <component :is="stat.icon" :size="24" :class="stat.color" />
        </div>
        <div class="stat-info">
          <p class="stat-title">{{ stat.title }}</p>
          <p class="stat-value">{{ stat.value }}</p>
        </div>
        <div v-if="stat.key === 'pending_approval' && pendingCount > 0" class="stat-card-arrow">
          <span class="text-warning text-xl">›</span>
        </div>
      </div>
    </div>

    <!-- Pending Approval Alert Banner -->
    <div v-if="showPendingBanner" class="pending-banner">
      <div class="pending-banner-content">
        <div class="pending-banner-icon">
          <Hourglass :size="20" class="text-warning animate-pulse" />
        </div>
        <div class="flex-1">
          <p class="font-semibold text-sm">{{ pendingCount }} memo{{ pendingCount !== 1 ? 's' : '' }} waiting for your approval</p>
          <p class="text-xs opacity-70">Secretary-submitted memos need your review before they can be sent to recipients.</p>
        </div>
        <button @click="goToPending" class="pending-banner-btn">Review Now</button>
        <button @click="dismissBanner" class="pending-banner-dismiss">
          <X :size="16" />
        </button>
      </div>
    </div>

    <!-- Main Content Grid -->
    <div class="content-grid">
      <!-- Recent Memos -->
      <div class="content-card recent-memos">
        <div class="flex items-center justify-between mb-4">
          <h2 class="card-title mb-0">Recent Memos</h2>
          <router-link to="/admin/memos" class="text-xs text-primary hover:underline">View All</router-link>
        </div>
        
        <div v-if="recentMemos.length > 0" class="memo-list">
          <div v-for="memo in recentMemos" :key="memo.id" class="memo-item p-3 border-b border-base-200 last:border-0 hover:bg-base-200/50 rounded-lg transition-colors cursor-pointer" @click="$router.push(`/admin/memos?id=${memo.id}`)">
            <div class="flex items-start gap-3">
              <div class="memo-avatar p-2 rounded-lg" :class="memo.priority === 'high' ? 'bg-error/10' : memo.priority === 'medium' ? 'bg-warning/10' : 'bg-success/10'">
                <FileText :size="18" :class="memo.priority === 'high' ? 'text-error' : memo.priority === 'medium' ? 'text-warning' : 'text-success'" />
              </div>
              <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between gap-2">
                  <h3 class="font-semibold text-sm truncate">{{ memo.subject }}</h3>
                  <span class="text-[10px] text-base-content/50 whitespace-nowrap">{{ new Date(memo.created_at).toLocaleDateString() }}</span>
                </div>
                <p class="text-xs text-base-content/60 truncate">{{ memo.message.replace(/<[^>]*>/g, '') }}</p>
                <div class="flex items-center gap-2 mt-1">
                  <span class="text-[10px] px-1.5 py-0.5 rounded-full border border-base-300 bg-base-200 uppercase font-medium">
                    {{ memo.priority }}
                  </span>
                  <span v-if="memo.sender" class="text-[10px] text-base-content/40">
                    From: {{ memo.sender.first_name }} {{ memo.sender.last_name }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div v-else class="empty-state">
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
              class="calendar-day relative"
              :class="{
                'other-month': !dateObj.isCurrentMonth,
                'today': dateObj.isToday,
                'has-events': getDayEvents(dateObj.day, dateObj.isCurrentMonth).length > 0
              }"
              @click="handleDayClick(dateObj)"
            >
              {{ dateObj.day }}
              <div v-if="getHighestPriorityColor(dateObj.day, dateObj.isCurrentMonth)" 
                   class="absolute bottom-1 left-1/2 -translate-x-1/2 w-1.5 h-1.5 rounded-full"
                   :style="{ backgroundColor: getHighestPriorityColor(dateObj.day, dateObj.isCurrentMonth) }">
              </div>
            </div>
          </div>
        </div>

        <!-- Event Detail Modal -->
        <div v-if="isEventModalOpen && selectedEvent" class="fixed inset-0 z-100 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm" @click.self="isEventModalOpen = false">
          <div class="bg-base-100 rounded-2xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all">
            <div class="p-6 border-b border-base-200 flex items-center justify-between" :style="{ borderTop: `4px solid ${selectedEvent.color}` }">
              <h3 class="text-lg font-bold">{{ selectedEvent.title }}</h3>
              <button @click="isEventModalOpen = false" class="btn btn-ghost btn-sm btn-circle text-base-content/50">✕</button>
            </div>
            <div class="p-6 space-y-4">
              <div class="flex items-start gap-4 text-sm">
                <div class="p-2 rounded-lg bg-base-200">
                  <Clock :size="18" class="text-primary" />
                </div>
                <div>
                  <p class="font-semibold">Time & Date</p>
                  <p class="text-base-content/60">
                    {{ new Date(selectedEvent.start).toLocaleString([], { dateStyle: 'medium', timeStyle: 'short' }) }}
                    <span v-if="selectedEvent.end && selectedEvent.start !== selectedEvent.end">
                      - {{ new Date(selectedEvent.end).toLocaleTimeString([], { timeStyle: 'short' }) }}
                    </span>
                  </p>
                </div>
              </div>

              <div v-if="selectedEvent.description" class="flex items-start gap-4 text-sm">
                <div class="p-2 rounded-lg bg-base-200">
                  <FileText :size="18" class="text-primary" />
                </div>
                <div>
                  <p class="font-semibold">Description</p>
                  <p class="text-base-content/60 line-clamp-4">{{ selectedEvent.description }}</p>
                </div>
              </div>

              <div class="flex items-center justify-between pt-4 border-t border-base-200">
                <span class="text-[10px] px-2 py-1 rounded-full bg-base-200 font-bold uppercase tracking-wider" :style="{ color: selectedEvent.color }">
                  {{ selectedEvent.priority }} Priority
                </span>
                <div class="flex gap-2">
                  <button v-if="selectedEvent.memo_id" @click="$router.push(`/admin/memos?id=${selectedEvent.memo_id}`); isEventModalOpen = false" class="btn btn-primary btn-sm">View Memo</button>
                  <button @click="isEventModalOpen = false" class="btn btn-ghost btn-sm">Close</button>
                </div>
              </div>
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

.stat-card-clickable {
  @apply cursor-pointer hover:border-warning/50 hover:shadow-warning/10;
  transition: all 0.2s ease;
}
.stat-card-clickable:hover {
  transform: translateY(-2px);
}

.stat-card-arrow {
  @apply flex items-center ml-auto opacity-60;
}

/* Pending Approval Banner */
.pending-banner {
  @apply rounded-xl border border-warning/30 bg-warning/10;
  padding: 12px 20px;
  animation: banner-slide-in 0.3s ease;
}

@keyframes banner-slide-in {
  0% { opacity: 0; transform: translateY(-8px); }
  100% { opacity: 1; transform: translateY(0); }
}

.pending-banner-content {
  @apply flex items-center gap-3;
}

.pending-banner-icon {
  @apply flex items-center justify-center w-9 h-9 rounded-lg bg-warning/20 shrink-0;
}

.pending-banner-btn {
  @apply btn btn-warning btn-sm shrink-0 font-semibold;
}

.pending-banner-dismiss {
  @apply btn btn-ghost btn-sm btn-circle shrink-0 text-warning;
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

.calendar-day.has-events {
  @apply font-semibold;
}

.memo-item {
  @apply transition-all duration-200;
}

.memo-item:hover {
  transform: translateX(4px);
}
</style>
