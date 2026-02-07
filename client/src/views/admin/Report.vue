<script setup>
import { ref, onMounted, computed } from 'vue'
import { 
  Download, 
  ChevronDown, 
  FileText, 
  Users, 
  Calendar, 
  Activity, 
  TrendingUp,
  BarChart3,
  PieChart,
  RefreshCw,
  FileSpreadsheet,
  File,
  TrendingDown
} from 'lucide-vue-next'
import { Line, Doughnut, Bar } from 'vue-chartjs'
import api from '../../services/api'
import Swal from 'sweetalert2'
import { useAuth } from '../../composables/useAuth'

// Initialize auth for permissions
const { can } = useAuth()

// Google Analytics tracking via window.gtag
const trackEvent = (eventName, params = {}) => {
  if (typeof window !== 'undefined' && window.gtag) {
    window.gtag('event', eventName, params)
  }
  console.log('GA Event:', eventName, params)
}

const timePeriod = ref('Last 30 days')
const loading = ref(true)
const isExporting = ref(false)
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
    { 
      label: 'Total Users', 
      value: overview.total_users || 0, 
      icon: Users, 
      color: 'text-blue-500', 
      bg: 'bg-blue-50',
      trend: '+12%',
      trendUp: true
    },
    { 
      label: 'Total Memos', 
      value: overview.total_memos || 0, 
      icon: FileText, 
      color: 'text-orange-500', 
      bg: 'bg-orange-50',
      trend: '+8%',
      trendUp: true
    },
    { 
      label: 'This Period', 
      value: overview.memos_this_period || 0, 
      icon: Calendar, 
      color: 'text-emerald-500', 
      bg: 'bg-emerald-50',
      trend: '+15%',
      trendUp: true
    },
    { 
      label: 'Active Users', 
      value: users.active_users || 0, 
      icon: Activity, 
      color: 'text-purple-500', 
      bg: 'bg-purple-50',
      trend: '+5%',
      trendUp: true
    }
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
      pointHoverRadius: 6,
      pointBackgroundColor: '#3b82f6',
      pointBorderColor: '#fff',
      pointBorderWidth: 2
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
      backgroundColor: ['#fbbf24', '#3b82f6', '#10b981', '#6b7280'],
      borderWidth: 0,
      hoverOffset: 10
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
      borderRadius: 6,
      barThickness: 10
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
      padding: 12,
      titleFont: { size: 14, weight: 'bold' },
      bodyFont: { size: 13 }
    }
  },
  scales: {
    x: {
      grid: { display: false },
      ticks: { color: '#9ca3af', font: { size: 11 } }
    },
    y: {
      grid: { color: 'rgba(156, 163, 175, 0.1)' },
      ticks: { color: '#9ca3af', font: { size: 11 } },
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
        padding: 20,
        usePointStyle: true,
        pointStyle: 'circle',
        font: { size: 12 }
      }
    }
  },
  cutout: '65%'
}

const fetchReportData = async () => {
  loading.value = true
  try {
    trackEvent('report_view', {
      period: timePeriod.value,
      timestamp: new Date().toISOString()
    })
    
    const response = await api.get(`/reports?period=${periodDays.value}`)
    reportData.value = response.data
    
    trackEvent('report_data_loaded', {
      period: timePeriod.value,
      success: true
    })
  } catch (error) {
    console.error('Error fetching report data:', error)
    trackEvent('report_data_error', {
      period: timePeriod.value,
      error: error.message
    })
    
    Swal.fire({
      icon: 'error',
      title: 'Error Loading Report',
      text: 'Failed to load report data. Please try again.',
      confirmButtonColor: '#3b82f6'
    })
  } finally {
    loading.value = false
  }
}

const handlePeriodChange = () => {
  trackEvent('report_period_change', {
    from: timePeriod.value,
    timestamp: new Date().toISOString()
  })
  fetchReportData()
}

const exportPDF = async () => {
  isExporting.value = true
  
  trackEvent('report_export_pdf', {
    period: timePeriod.value,
    timestamp: new Date().toISOString()
  })
  
  try {
    const response = await api.get(`/reports/export/pdf?period=${periodDays.value}`, {
      responseType: 'blob'
    })
    
    const url = window.URL.createObjectURL(new Blob([response.data]))
    const link = document.createElement('a')
    link.href = url
    link.setAttribute('download', `memofy-report-${new Date().toISOString().split('T')[0]}.pdf`)
    document.body.appendChild(link)
    link.click()
    link.remove()
    
    Swal.fire({
      icon: 'success',
      title: 'PDF Exported!',
      text: 'Your report has been downloaded successfully.',
      timer: 2000,
      confirmButtonColor: '#3b82f6'
    })
    
    trackEvent('report_export_pdf_success', {
      period: timePeriod.value
    })
  } catch (error) {
    console.error('Export error:', error)
    trackEvent('report_export_pdf_error', {
      period: timePeriod.value,
      error: error.message
    })
    
    // Attempt to parse blob error message if available
    let errorMessage = 'Failed to export PDF. Please try again.'
    if (error.response?.data instanceof Blob) {
      const reader = new FileReader()
      reader.onload = () => {
        try {
          const json = JSON.parse(reader.result)
          errorMessage = json.message || errorMessage
          Swal.fire({
            icon: 'error',
            title: 'Export Failed',
            text: errorMessage,
            confirmButtonColor: '#3b82f6'
          })
        } catch (e) {
          // Not JSON, use default
          Swal.fire({
            icon: 'error',
            title: 'Export Failed',
            text: errorMessage,
            confirmButtonColor: '#3b82f6'
          })
        }
      }
      reader.readAsText(error.response.data)
    } else {
      Swal.fire({
        icon: 'error',
        title: 'Export Failed',
        text: error.response?.data?.message || errorMessage,
        confirmButtonColor: '#3b82f6'
      })
    }
  } finally {
    isExporting.value = false
  }
}

const exportExcel = async () => {
  isExporting.value = true
  
  trackEvent('report_export_excel', {
    period: timePeriod.value,
    timestamp: new Date().toISOString()
  })
  
  try {
    const response = await api.get(`/reports/export/excel?period=${periodDays.value}`)
    
    // For now, create CSV from JSON response
    const data = response.data.data
    const csvContent = convertToCSV(data)
    downloadCSV(csvContent, `memofy-report-${new Date().toISOString().split('T')[0]}.csv`)
    
    Swal.fire({
      icon: 'success',
      title: 'Excel/CSV Exported!',
      text: 'Your report has been exported successfully.',
      timer: 2000,
      confirmButtonColor: '#3b82f6'
    })
    
    trackEvent('report_export_excel_success', {
      period: timePeriod.value
    })
  } catch (error) {
    console.error('Export error:', error)
    trackEvent('report_export_excel_error', {
      period: timePeriod.value,
      error: error.message
    })
    
    Swal.fire({
      icon: 'error',
      title: 'Export Failed',
      text: 'Failed to export data. Please try again.',
      confirmButtonColor: '#3b82f6'
    })
  } finally {
    isExporting.value = false
  }
}

const convertToCSV = (data) => {
  const headers = ['Metric', 'Value']
  const rows = [
    ['Report Generated At', data.generated_at],
    ['Generated By', data.generated_by],
    ['Tracking Number', data.tracking_number],
    ['Period (Days)', data.period],
    [''],
    ['--- OVERVIEW ---'],
    ['Total Users', data.overview?.total_users || 0],
    ['Active Users', data.overview?.active_users || 0],
    ['Total Memos', data.overview?.total_memos || 0],
    ['Memos This Period', data.overview?.memos_this_period || 0],
    [''],
    ['--- MEMOS ---'],
    ['Total Memos', data.memos?.total || 0],
    ['Draft Memos', data.memos?.by_status?.draft || 0],
    ['Sent Memos', data.memos?.by_status?.sent || 0],
    ['Read Memos', data.memos?.by_status?.read || 0],
  ]
  
  return [headers, ...rows].map(row => row.join(',')).join('\n')
}

const downloadCSV = (content, filename) => {
  const blob = new Blob([content], { type: 'text/csv;charset=utf-8;' })
  const link = document.createElement('a')
  link.href = URL.createObjectURL(blob)
  link.download = filename
  link.click()
  URL.revokeObjectURL(link.href)
}

const refreshData = () => {
  trackEvent('report_refresh', {
    timestamp: new Date().toISOString()
  })
  fetchReportData()
}

onMounted(() => {
  fetchReportData()
})
</script>

<template>
  <div class="view-container">
    <!-- Header -->
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 mb-8">
      <div>
        <h1 class="text-2xl font-bold mb-1 flex items-center gap-3">
          <BarChart3 class="text-primary" :size="28" />
          Reports & Analytics
        </h1>
        <p class="text-base-content/60 text-sm">Comprehensive system insights and performance metrics</p>
      </div>
      
      <div class="flex flex-wrap items-center gap-3">
        <!-- Period Selector -->
        <div class="dropdown dropdown-end">
          <label tabindex="0" class="btn btn-outline gap-2 min-w-[160px]">
            <Calendar :size="18" class="text-primary" />
            {{ timePeriod }}
            <ChevronDown :size="16" />
          </label>
          <ul tabindex="0" class="dropdown-content z-50 menu p-2 shadow-lg bg-base-100 rounded-box w-52 border border-base-200">
            <li>
              <a @click="timePeriod = 'Last 7 days'; handlePeriodChange()" 
                 :class="{ 'active': timePeriod === 'Last 7 days' }">
                Last 7 days
              </a>
            </li>
            <li>
              <a @click="timePeriod = 'Last 30 days'; handlePeriodChange()"
                 :class="{ 'active': timePeriod === 'Last 30 days' }">
                Last 30 days
              </a>
            </li>
            <li>
              <a @click="timePeriod = 'This Year'; handlePeriodChange()"
                 :class="{ 'active': timePeriod === 'This Year' }">
                This Year
              </a>
            </li>
          </ul>
        </div>
        
        <!-- Refresh Button -->
        <button @click="refreshData" class="btn btn-ghost btn-square" :disabled="loading">
          <RefreshCw :size="18" :class="{ 'animate-spin': loading }" />
        </button>
        
        <!-- Export Dropdown -->
        <div v-if="can('reports.export')" class="dropdown dropdown-end">
          <div 
            tabindex="0" 
            role="button" 
            class="btn btn-primary gap-2 text-white" 
            :class="{ 'btn-disabled opacity-50': isExporting }"
          >
            <Download v-if="!isExporting" :size="18" />
            <span v-else class="loading loading-spinner loading-sm"></span>
            Export Report
          </div>
          <ul tabindex="0" class="dropdown-content z-50 menu p-2 shadow-lg bg-base-100 rounded-box w-52 border border-base-200">
            <li>
              <button 
                @click="exportPDF" 
                :disabled="isExporting" 
                class="flex items-center gap-2 w-full text-left p-3 hover:bg-base-200"
              >
                <File :size="18" class="text-red-500" />
                Export as PDF
              </button>
            </li>
            <li class="mt-1">
              <button 
                @click="exportExcel" 
                :disabled="isExporting" 
                class="flex items-center gap-2 w-full text-left p-3 hover:bg-base-200"
              >
                <FileSpreadsheet :size="18" class="text-green-500" />
                Export as Excel/CSV
              </button>
            </li>
          </ul>
        </div>
      </div>
    </div>

    <!-- Loading State -->
    <div v-if="loading" class="flex items-center justify-center py-24">
      <div class="text-center">
        <span class="loading loading-spinner loading-lg text-primary"></span>
        <p class="mt-4 text-base-content/60">Loading report data...</p>
      </div>
    </div>

    <div v-else>
      <!-- Stats Cards -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
        <div 
          v-for="(stat, index) in stats" 
          :key="index"
          class="group bg-base-100 rounded-2xl border border-base-200 p-6 hover:shadow-lg hover:border-primary/30 transition-all duration-300"
        >
          <div class="flex items-start justify-between mb-4">
            <div class="w-12 h-12 rounded-xl flex items-center justify-center transition-transform group-hover:scale-110" :class="stat.bg">
              <component :is="stat.icon" :size="24" :class="stat.color" />
            </div>
            <div 
              class="flex items-center gap-1 text-xs font-medium px-2 py-1 rounded-full"
              :class="stat.trendUp ? 'bg-emerald-50 text-emerald-600' : 'bg-red-50 text-red-600'"
            >
              <TrendingUp v-if="stat.trendUp" :size="12" />
              <TrendingDown v-else :size="12" />
              {{ stat.trend }}
            </div>
          </div>
          <div class="text-3xl font-bold mb-1">{{ stat.value.toLocaleString() }}</div>
          <div class="text-sm text-base-content/60">{{ stat.label }}</div>
        </div>
      </div>

      <!-- Charts Row 1 -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- User Activity Chart -->
        <div class="bg-base-100 rounded-2xl border border-base-200 p-6 hover:shadow-lg transition-shadow">
          <div class="flex items-center justify-between mb-6">
            <h3 class="font-bold text-lg flex items-center gap-2">
              <Activity class="text-primary" :size="20" />
              User Activity Over Time
            </h3>
            <span class="badge badge-primary badge-outline">{{ timePeriod }}</span>
          </div>
          <div class="h-72">
            <Line v-if="activityChartData" :data="activityChartData" :options="chartOptions" />
            <div v-else class="flex items-center justify-center h-full text-base-content/40">
              No activity data available
            </div>
          </div>
        </div>

        <!-- Memo Status Chart -->
        <div class="bg-base-100 rounded-2xl border border-base-200 p-6 hover:shadow-lg transition-shadow">
          <div class="flex items-center justify-between mb-6">
            <h3 class="font-bold text-lg flex items-center gap-2">
              <PieChart class="text-orange-500" :size="20" />
              Memo Status Distribution
            </h3>
            <span class="badge badge-outline">All Time</span>
          </div>
          <div class="h-72">
            <Doughnut v-if="memoStatusChartData" :data="memoStatusChartData" :options="doughnutOptions" />
            <div v-else class="flex items-center justify-center h-full text-base-content/40">
              No status data available
            </div>
          </div>
        </div>
      </div>

      <!-- Charts Row 2 -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">
        <!-- Daily Memos Chart -->
        <div class="bg-base-100 rounded-2xl border border-base-200 p-6 hover:shadow-lg transition-shadow">
          <div class="flex items-center justify-between mb-6">
            <h3 class="font-bold text-lg flex items-center gap-2">
              <BarChart3 class="text-emerald-500" :size="20" />
              Daily Memos Created
            </h3>
            <span class="badge badge-outline">{{ timePeriod }}</span>
          </div>
          <div class="h-72">
            <Bar v-if="dailyMemosChartData" :data="dailyMemosChartData" :options="chartOptions" />
            <div v-else class="flex items-center justify-center h-full text-base-content/40">
              No daily data available
            </div>
          </div>
        </div>

        <!-- Top Active Users -->
        <div class="bg-base-100 rounded-2xl border border-base-200 p-6 hover:shadow-lg transition-shadow">
          <h3 class="font-bold text-lg mb-6 flex items-center gap-2">
            <Users class="text-blue-500" :size="20" />
            Top Active Users
          </h3>
          <div class="space-y-4">
            <div 
              v-for="(user, index) in reportData?.users?.top_active_users || []" 
              :key="index"
              class="flex items-center gap-4 p-4 rounded-xl bg-base-50 hover:bg-base-100 transition-colors"
            >
              <div class="relative">
                <div class="avatar placeholder">
                  <div class="bg-gradient-to-br from-primary to-blue-600 text-white rounded-full w-12">
                    <span class="text-sm font-bold">{{ user.name?.charAt(0) || 'U' }}</span>
                  </div>
                </div>
                <div 
                  v-if="index < 3"
                  class="absolute -top-1 -right-1 w-5 h-5 rounded-full flex items-center justify-center text-xs font-bold"
                  :class="index === 0 ? 'bg-yellow-400 text-yellow-900' : index === 1 ? 'bg-gray-300 text-gray-700' : 'bg-amber-600 text-white'"
                >
                  {{ index + 1 }}
                </div>
              </div>
              <div class="flex-1 min-w-0">
                <div class="font-semibold truncate">{{ user.name }}</div>
                <div class="text-sm text-base-content/60 truncate">{{ user.email }}</div>
              </div>
              <div class="text-right">
                <div class="text-xl font-bold text-primary">{{ user.activity_count }}</div>
                <div class="text-xs text-base-content/50">activities</div>
              </div>
            </div>
            <div v-if="!reportData?.users?.top_active_users?.length" class="text-center py-12 text-base-content/50">
              <Users :size="48" class="mx-auto mb-3 opacity-30" />
              <p>No activity data available</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Bottom Row -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Recent Activities -->
        <div class="bg-base-100 rounded-2xl border border-base-200 p-6 hover:shadow-lg transition-shadow">
          <h3 class="font-bold text-lg mb-6 flex items-center gap-2">
            <Activity class="text-purple-500" :size="20" />
            Recent Activities
          </h3>
          <div class="space-y-3">
            <div 
              v-for="(activity, index) in reportData?.activity?.recent || []" 
              :key="index"
              class="flex items-start gap-4 p-4 rounded-xl bg-base-50 hover:bg-base-100 transition-colors"
            >
              <div class="avatar placeholder">
                <div class="bg-gradient-to-br from-purple-500 to-purple-700 text-white rounded-full w-10">
                  <span class="text-xs">{{ activity.actor?.name?.charAt(0) || 'U' }}</span>
                </div>
              </div>
              <div class="flex-1 min-w-0">
                <div class="font-medium">{{ activity.actor?.name }}</div>
                <div class="text-sm text-base-content/60 truncate">{{ activity.description }}</div>
              </div>
              <div class="text-xs text-base-content/40 whitespace-nowrap">
                {{ new Date(activity.created_at).toLocaleDateString() }}
              </div>
            </div>
            <div v-if="!reportData?.activity?.recent?.length" class="text-center py-12 text-base-content/50">
              <Activity :size="48" class="mx-auto mb-3 opacity-30" />
              <p>No recent activities</p>
            </div>
          </div>
        </div>

        <!-- Memo Statistics -->
        <div class="bg-base-100 rounded-2xl border border-base-200 p-6 hover:shadow-lg transition-shadow">
          <h3 class="font-bold text-lg mb-6 flex items-center gap-2">
            <FileText class="text-orange-500" :size="20" />
            Memo Statistics
          </h3>
          <div class="space-y-4">
            <div class="flex items-center justify-between p-4 rounded-xl bg-base-50 hover:bg-base-100 transition-colors">
              <div class="flex items-center gap-3">
                <div class="w-3 h-3 rounded-full bg-blue-500"></div>
                <span class="font-medium">Total Memos</span>
              </div>
              <span class="text-xl font-bold">{{ reportData?.memos?.total || 0 }}</span>
            </div>
            <div class="flex items-center justify-between p-4 rounded-xl bg-base-50 hover:bg-base-100 transition-colors">
              <div class="flex items-center gap-3">
                <div class="w-3 h-3 rounded-full bg-yellow-500"></div>
                <span class="font-medium">Draft</span>
              </div>
              <span class="text-xl font-bold text-yellow-500">{{ reportData?.memos?.by_status?.draft || 0 }}</span>
            </div>
            <div class="flex items-center justify-between p-4 rounded-xl bg-base-50 hover:bg-base-100 transition-colors">
              <div class="flex items-center gap-3">
                <div class="w-3 h-3 rounded-full bg-blue-500"></div>
                <span class="font-medium">Sent</span>
              </div>
              <span class="text-xl font-bold text-blue-500">{{ reportData?.memos?.by_status?.sent || 0 }}</span>
            </div>
            <div class="flex items-center justify-between p-4 rounded-xl bg-base-50 hover:bg-base-100 transition-colors">
              <div class="flex items-center gap-3">
                <div class="w-3 h-3 rounded-full bg-green-500"></div>
                <span class="font-medium">Read</span>
              </div>
              <span class="text-xl font-bold text-green-500">{{ reportData?.memos?.by_status?.read || 0 }}</span>
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

.dropdown a.active {
  @apply bg-primary text-white;
}
</style>
