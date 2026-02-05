<script setup>
import { ref, onMounted, computed } from 'vue'
import { Download, ChevronDown, Link, Users, FileText, Calendar, Activity, TrendingUp } from 'lucide-vue-next'
import { Line, Doughnut, Bar } from 'vue-chartjs'
import {
  Chart as ChartJS,
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  BarElement,
  ArcElement,
  Title,
  Tooltip,
  Legend,
  Filler
} from 'chart.js'
import api from '../../services/api'

ChartJS.register(
  CategoryScale,
  LinearScale,
  PointElement,
  LineElement,
  BarElement,
  ArcElement,
  Title,
  Tooltip,
  Legend,
  Filler
)

const timePeriod = ref('Last 30 days')
const loading = ref(true)
const reportData = ref(null)

const periodDays = computed(() => {
  const periods = {
    'Last 7 days': 7,
    'Last 30 days': 30,
    'This Year': 365
  }
  return periods[timePeriod.value] || 30
})

const stats = computed(() => {
  if (!reportData.value) return []
  const overview = reportData.value.overview || {}
  const memos = reportData.value.memos || {}
  const users = reportData.value.users || {}

  return [
    { label: 'Total Users', value: overview.total_users || 0, icon: Users, color: 'text-blue-500', bg: 'bg-blue-50' },
    { label: 'Total Memos', value: overview.total_memos || 0, icon: FileText, color: 'text-orange-500', bg: 'bg-orange-50' },
    { label: 'Memos This Period', value: overview.memos_this_period || 0, icon: Calendar, color: 'text-blue-600', bg: 'bg-blue-50' },
    { label: 'Active Users', value: users.active_users || 0, icon: Activity, color: 'text-emerald-500', bg: 'bg-emerald-50' }
  ]
})

const activityChartData = computed(() => {
  if (!reportData.value?.userActivityTimeline) return null
  const timeline = reportData.value.userActivityTimeline
  return {
    labels: timeline.map(t => t.label),
    datasets: [{
      label: 'User Activities',
      data: timeline.map(t => t.count),
      borderColor: '#3b82f6',
      backgroundColor: 'rgba(59, 130, 246, 0.1)',
      fill: true,
      tension: 0.4,
      pointRadius: 2,
      pointHoverRadius: 6
    }]
  }
})

const memoStatusChartData = computed(() => {
  if (!reportData.value?.memoStatusDistribution) return null
  const dist = reportData.value.memoStatusDistribution
  return {
    labels: dist.labels.map(l => l.charAt(0).toUpperCase() + l.slice(1)),
    datasets: [{
      data: dist.data,
      backgroundColor: dist.colors ? Object.values(dist.colors) : ['#fbbf24', '#3b82f6', '#10b981', '#6b7280'],
      borderWidth: 0
    }]
  }
})

const dailyMemosChartData = computed(() => {
  if (!reportData.value?.dailyMemos) return null
  const daily = reportData.value.dailyMemos
  return {
    labels: daily.map(d => d.label),
    datasets: [{
      label: 'Memos Created',
      data: daily.map(d => d.count),
      backgroundColor: '#10b981',
      borderRadius: 4,
      barThickness: 8
    }]
  }
})

const chartOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      display: false
    },
    tooltip: {
      backgroundColor: '#1f2937',
      titleColor: '#f9fafb',
      bodyColor: '#d1d5db',
      cornerRadius: 8,
      padding: 12
    }
  },
  scales: {
    x: {
      grid: { display: false },
      ticks: { color: '#9ca3af', font: { size: 10 } }
    },
    y: {
      grid: { color: 'rgba(156, 163, 175, 0.1)' },
      ticks: { color: '#9ca3af', font: { size: 10 } },
      beginAtZero: true
    }
  }
}

const doughnutOptions = {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: {
      position: 'bottom',
      labels: {
        color: '#6b7280',
        padding: 15,
        usePointStyle: true,
        pointStyle: 'circle'
      }
    }
  },
  cutout: '65%'
}

const fetchReportData = async () => {
  loading.value = true
  try {
    const response = await api.get(`/reports?period=${periodDays.value}`)
    reportData.value = response.data
  } catch (error) {
    console.error('Error fetching report data:', error)
  } finally {
    loading.value = false
  }
}

const handlePeriodChange = () => {
  fetchReportData()
}

onMounted(() => {
  fetchReportData()
})
</script>

<template>
  <div class="view-container">
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="text-2xl font-bold mb-1">Reports & Analytics</h1>
        <p class="text-base-content/60 text-sm">Database statistics and user activity insights</p>
      </div>
      <div class="flex items-center gap-3">
        <select v-model="timePeriod" @change="handlePeriodChange" class="select select-bordered select-sm w-44">
          <option>Last 7 days</option>
          <option>Last 30 days</option>
          <option>This Year</option>
        </select>
        <button class="btn btn-primary text-white btn-sm gap-2">
          <Download :size="16" />
          Export Report
        </button>
      </div>
    </div>

    <div v-if="loading" class="flex items-center justify-center py-20">
      <span class="loading loading-spinner loading-lg text-primary"></span>
    </div>

    <template v-else>
      <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
        <div v-for="(stat, index) in stats" :key="index" class="bg-base-100 rounded-xl border border-base-200 p-6 flex items-center gap-4">
          <div class="w-12 h-12 rounded-lg flex items-center justify-center" :class="stat.bg">
            <component :is="stat.icon" :size="24" :class="stat.color" />
          </div>
          <div>
            <div class="text-2xl font-bold mb-0.5">{{ stat.value }}</div>
            <div class="text-xs text-base-content/60">{{ stat.label }}</div>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <div class="bg-base-100 rounded-xl border border-base-200 p-6">
          <div class="flex items-center justify-between mb-6">
            <h3 class="font-bold flex items-center gap-2">
              <TrendingUp :size="18" class="text-primary" />
              User Activity Over Time
            </h3>
          </div>
          <div class="h-64">
            <Line v-if="activityChartData" :data="activityChartData" :options="chartOptions" />
          </div>
        </div>

        <div class="bg-base-100 rounded-xl border border-base-200 p-6">
          <div class="flex items-center justify-between mb-6">
            <h3 class="font-bold flex items-center gap-2">
              <FileText :size="18" class="text-orange-500" />
              Memo Status Distribution
            </h3>
          </div>
          <div class="h-64">
            <Doughnut v-if="memoStatusChartData" :data="memoStatusChartData" :options="doughnutOptions" />
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <div class="bg-base-100 rounded-xl border border-base-200 p-6">
          <div class="flex items-center justify-between mb-6">
            <h3 class="font-bold flex items-center gap-2">
              <Calendar :size="18" class="text-emerald-500" />
              Daily Memos Created
            </h3>
          </div>
          <div class="h-64">
            <Bar v-if="dailyMemosChartData" :data="dailyMemosChartData" :options="chartOptions" />
          </div>
        </div>

        <div class="bg-base-100 rounded-xl border border-base-200 p-6">
          <h3 class="font-bold mb-4 flex items-center gap-2">
            <Users :size="18" class="text-blue-500" />
            Top Active Users
          </h3>
          <div class="space-y-3">
            <div v-for="(user, index) in reportData?.users?.top_active_users || []" :key="index"
                 class="flex items-center justify-between p-3 rounded-lg bg-base-200/50">
              <div class="flex items-center gap-3">
                <div class="avatar placeholder">
                  <div class="bg-primary text-primary-content rounded-full w-10">
                    <span class="text-xs">{{ user.name?.charAt(0) || 'U' }}</span>
                  </div>
                </div>
                <div>
                  <div class="font-medium text-sm">{{ user.name }}</div>
                  <div class="text-xs text-base-content/60">{{ user.email }}</div>
                </div>
              </div>
              <div class="text-right">
                <div class="font-bold text-primary">{{ user.activity_count }}</div>
                <div class="text-xs text-base-content/60">activities</div>
              </div>
            </div>
            <div v-if="!reportData?.users?.top_active_users?.length" class="text-center py-8 text-base-content/50">
              No activity data available
            </div>
          </div>
        </div>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <div class="bg-base-100 rounded-xl border border-base-200 p-6">
          <h3 class="font-bold mb-4 flex items-center gap-2">
            <Activity :size="18" class="text-purple-500" />
            Recent Activities
          </h3>
          <div class="space-y-3">
            <div v-for="(activity, index) in reportData?.activity?.recent || []" :key="index"
                 class="flex items-start gap-3 p-3 rounded-lg bg-base-200/50">
              <div class="avatar placeholder">
                <div class="bg-base-300 text-base-content rounded-full w-8">
                  <span class="text-xs">{{ activity.actor?.name?.charAt(0) || 'U' }}</span>
                </div>
              </div>
              <div class="flex-1 min-w-0">
                <div class="font-medium text-sm">{{ activity.actor?.name }}</div>
                <div class="text-xs text-base-content/60 truncate">{{ activity.description }}</div>
              </div>
              <div class="text-xs text-base-content/40 whitespace-nowrap">
                {{ new Date(activity.created_at).toLocaleDateString() }}
              </div>
            </div>
            <div v-if="!reportData?.activity?.recent?.length" class="text-center py-8 text-base-content/50">
              No recent activities
            </div>
          </div>
        </div>

        <div class="bg-base-100 rounded-xl border border-base-200 p-6">
          <h3 class="font-bold mb-4 flex items-center gap-2">
            <FileText :size="18" class="text-orange-500" />
            Memo Statistics
          </h3>
          <div class="space-y-4">
            <div class="flex items-center justify-between p-3 rounded-lg bg-base-200/50">
              <span class="text-sm">Total Memos</span>
              <span class="font-bold">{{ reportData?.memos?.total || 0 }}</span>
            </div>
            <div class="flex items-center justify-between p-3 rounded-lg bg-base-200/50">
              <span class="text-sm">Draft</span>
              <span class="font-bold text-yellow-500">{{ reportData?.memos?.by_status?.draft || 0 }}</span>
            </div>
            <div class="flex items-center justify-between p-3 rounded-lg bg-base-200/50">
              <span class="text-sm">Sent</span>
              <span class="font-bold text-blue-500">{{ reportData?.memos?.by_status?.sent || 0 }}</span>
            </div>
            <div class="flex items-center justify-between p-3 rounded-lg bg-base-200/50">
              <span class="text-sm">Read</span>
              <span class="font-bold text-green-500">{{ reportData?.memos?.by_status?.read || 0 }}</span>
            </div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
@reference "../../style.css";

.view-container {
  @apply p-0;
}
</style>
