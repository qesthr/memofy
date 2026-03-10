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
  TrendingDown,
  Trophy,
  History,
  CheckCircle
} from 'lucide-vue-next'
import { Line, Doughnut, Bar } from 'vue-chartjs'
import api from '../../services/api'
import Swal from 'sweetalert2'
import { useAuth } from '../../composables/useAuth'

// Initialize auth for permissions
const { can } = useAuth()

// Format date helper
const formatDate = (date) => {
  if (!date) return ''
  const d = new Date(date)
  const now = new Date()
  const diff = now - d
  const minutes = Math.floor(diff / 60000)
  const hours = Math.floor(diff / 3600000)
  const days = Math.floor(diff / 86400000)
  
  if (minutes < 1) return 'Just now'
  if (minutes < 60) return `${minutes}m ago`
  if (hours < 24) return `${hours}h ago`
  if (days < 7) return `${days}d ago`
  return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric' })
}

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
      color: 'text-blue-600', 
      bg: 'bg-blue-50/50',
      gradient: 'from-blue-500/10 to-blue-600/5',
      trend: '+12%',
      trendUp: true
    },
    { 
      label: 'Total Memos', 
      value: overview.total_memos || 0, 
      icon: FileText, 
      color: 'text-amber-600', 
      bg: 'bg-amber-50/50',
      gradient: 'from-amber-500/10 to-amber-600/5',
      trend: '+8%',
      trendUp: true
    },
    { 
      label: 'This Period', 
      value: overview.memos_this_period || 0, 
      icon: Calendar, 
      color: 'text-emerald-600', 
      bg: 'bg-emerald-50/50',
      gradient: 'from-emerald-500/10 to-emerald-600/5',
      trend: '+15%',
      trendUp: true
    },
    { 
      label: 'Active Users', 
      value: users.active_users || 0, 
      icon: Activity, 
      color: 'text-violet-600', 
      bg: 'bg-violet-50/50',
      gradient: 'from-violet-500/10 to-violet-600/5',
      trend: '+5%',
      trendUp: true
    },
    { 
      label: 'Ack. Rate', 
      value: (overview.acknowledgment_rate || 0) + '%', 
      icon: CheckCircle, 
      color: 'text-indigo-600', 
      bg: 'bg-indigo-50/50',
      gradient: 'from-indigo-500/10 to-indigo-600/5',
      trend: 'Target: 100%',
      trendUp: true
    }
  ]
})

const activityChartData = computed(() => {
  if (!reportData.value?.userActivityTimeline || !Array.isArray(reportData.value.userActivityTimeline)) return null
  const timeline = reportData.value.userActivityTimeline
  if (timeline.length === 0) return null
  return {
    labels: timeline.map(t => t.label || ''),
    datasets: [{
      label: 'User Activities',
      data: timeline.map(t => t.count || 0),
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
  if (!dist.labels || !dist.data || dist.labels.length === 0) return null
  return {
    labels: dist.labels.map(l => (l || '').charAt(0).toUpperCase() + (l || '').slice(1)),
    datasets: [{
      data: dist.data,
      backgroundColor: ['#fbbf24', '#3b82f6', '#10b981', '#6b7280', '#f59e0b'],
      borderWidth: 0,
      hoverOffset: 10
    }]
  }
})

const dailyMemosChartData = computed(() => {
  if (!reportData.value?.dailyMemos || !Array.isArray(reportData.value.dailyMemos)) return null
  const daily = reportData.value.dailyMemos
  if (daily.length === 0) return null
  return {
    labels: daily.map(d => d.label || ''),
    datasets: [{
      label: 'Memos Created',
      data: daily.map(d => d.count || 0),
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
      backgroundColor: 'rgba(31, 41, 55, 0.95)',
      titleColor: '#f9fafb',
      bodyColor: '#d1d5db',
      cornerRadius: 12,
      padding: 12,
      titleFont: { size: 14, weight: '700' },
      bodyFont: { size: 13 },
      usePointStyle: true,
      borderColor: 'rgba(255, 255, 255, 0.1)',
      borderWidth: 1
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
    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-6 mb-10">
      <div>
        <h1 class="text-3xl font-extrabold tracking-tight mb-2 flex items-center gap-3">
          <div class="p-2 bg-primary/10 rounded-xl">
            <BarChart3 class="text-primary" :size="32" />
          </div>
          Reports & Analytics
        </h1>
        <p class="text-base-content/60 text-base ml-1">Track institutional performance with real-time data insights.</p>
      </div>
      
      <div class="flex flex-wrap items-center gap-4 bg-base-100/50 p-2 rounded-2xl border border-base-200 backdrop-blur-sm">
        <!-- Period Selector -->
        <div class="dropdown dropdown-end">
          <label tabindex="0" class="btn btn-ghost hover:bg-base-200 gap-2 min-w-[170px] border-none shadow-none">
            <Calendar :size="18" class="text-primary" />
            <span class="font-semibold">{{ timePeriod }}</span>
            <ChevronDown :size="16" class="opacity-50" />
          </label>
          <ul tabindex="0" class="dropdown-content mt-2 z-50 menu p-2 shadow-2xl bg-base-100 rounded-2xl w-56 border border-base-200">
            <li class="menu-title text-xs uppercase tracking-wider opacity-50 px-4 py-2">Select Period</li>
            <li>
              <a @click="timePeriod = 'Last 7 days'; handlePeriodChange()" 
                 :class="{ 'bg-primary text-white': timePeriod === 'Last 7 days' }" class="rounded-xl px-4 py-3">
                Last 7 days
              </a>
            </li>
            <li>
              <a @click="timePeriod = 'Last 30 days'; handlePeriodChange()"
                 :class="{ 'bg-primary text-white': timePeriod === 'Last 30 days' }" class="rounded-xl px-4 py-3">
                Last 30 days
              </a>
            </li>
            <li>
              <a @click="timePeriod = 'This Year'; handlePeriodChange()"
                 :class="{ 'bg-primary text-white': timePeriod === 'This Year' }" class="rounded-xl px-4 py-3">
                This Year
              </a>
            </li>
          </ul>
        </div>
        
        <div class="w-px h-8 bg-base-200 mx-1 hidden sm:block"></div>
        
        <!-- Refresh Button -->
        <button @click="refreshData" class="btn btn-ghost btn-circle hover:bg-base-200" :disabled="loading">
          <RefreshCw :size="18" :class="{ 'animate-spin': loading }" class="text-base-content/60" />
        </button>
        
        <!-- Export Button -->
        <div v-if="can('reports.export')">
          <button 
            @click="exportPDF" 
            class="btn btn-primary px-6 gap-2 text-white shadow-lg shadow-primary/20 hover:shadow-primary/40 transition-all duration-300" 
            :class="{ 'btn-disabled opacity-50': isExporting }"
            :disabled="isExporting"
          >
            <Download v-if="!isExporting" :size="18" />
            <span v-else class="loading loading-spinner loading-sm"></span>
            Export Report
          </button>
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
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 mb-10">
        <div 
          v-for="(stat, index) in stats" 
          :key="index"
          class="group bg-base-100 rounded-3xl border border-base-200 p-6 hover:shadow-2xl hover:shadow-base-content/5 hover:-translate-y-1 transition-all duration-500 ease-out relative overflow-hidden"
        >
          <!-- Subtle Gradient Overlay -->
          <div class="absolute inset-0 bg-gradient-to-br opacity-0 group-hover:opacity-100 transition-opacity duration-500" :class="stat.gradient"></div>
          
          <div class="relative z-10">
            <div class="flex items-start justify-between mb-6">
              <div class="w-14 h-14 rounded-2xl flex items-center justify-center transition-all duration-500 group-hover:scale-110 group-hover:rotate-3 shadow-sm border border-base-100" :class="stat.bg">
                <component :is="stat.icon" :size="28" :class="stat.color" />
              </div>
              <div 
                class="flex items-center gap-1.5 text-[11px] font-bold px-2.5 py-1 rounded-full shadow-sm"
                :class="stat.trendUp ? 'bg-emerald-500/10 text-emerald-600' : 'bg-rose-500/10 text-rose-600'"
              >
                <TrendingUp v-if="stat.trendUp" :size="12" />
                <TrendingDown v-else :size="12" />
                {{ stat.trend }}
              </div>
            </div>
            <div class="text-4xl font-black mb-1.5 tracking-tight text-base-content">{{ stat.value.toLocaleString() }}</div>
            <div class="text-sm font-semibold text-base-content/40 uppercase tracking-widest">{{ stat.label }}</div>
          </div>
        </div>
      </div>

      <!-- Charts Row 1 -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-8 mb-10">
        <!-- User Activity Chart -->
        <div class="bg-base-100 rounded-3xl border border-base-200 p-8 hover:shadow-xl transition-all duration-500">
          <div class="flex items-center justify-between mb-8">
            <div>
              <h3 class="font-bold text-xl flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-blue-500/10 flex items-center justify-center">
                  <Activity class="text-blue-600" :size="18" />
                </div>
                User Engagement
              </h3>
              <p class="text-xs text-base-content/40 mt-1 uppercase tracking-wider font-semibold">Activity trends over time</p>
            </div>
            <span class="badge badge-primary badge-outline font-bold px-4 py-3">{{ timePeriod }}</span>
          </div>
          <div class="h-80">
            <Line v-if="activityChartData" :data="activityChartData" :options="chartOptions" />
            <div v-else class="flex flex-col items-center justify-center h-full text-base-content/30 italic">
              <Activity :size="40" class="mb-2 opacity-20" />
              <p>Waiting for activity data...</p>
            </div>
          </div>
        </div>

        <!-- Memo Status Chart -->
        <div class="bg-base-100 rounded-3xl border border-base-200 p-8 hover:shadow-xl transition-all duration-500">
          <div class="flex items-center justify-between mb-8">
            <div>
              <h3 class="font-bold text-xl flex items-center gap-3">
                <div class="w-8 h-8 rounded-lg bg-amber-500/10 flex items-center justify-center">
                  <PieChart class="text-amber-600" :size="18" />
                </div>
                Memo Distribution
              </h3>
              <p class="text-xs text-base-content/40 mt-1 uppercase tracking-wider font-semibold">Breakdown by current status</p>
            </div>
            <span class="badge badge-ghost border-base-300 font-bold px-4 py-3">All Time</span>
          </div>
          <div class="h-80 relative">
            <Doughnut v-if="memoStatusChartData" :data="memoStatusChartData" :options="doughnutOptions" />
            <!-- Centered Stat for Doughnut -->
            <div v-if="memoStatusChartData" class="absolute inset-0 flex flex-col items-center justify-center pointer-events-none pb-10">
              <span class="text-3xl font-black text-base-content">{{ reportData?.memos?.total || 0 }}</span>
              <span class="text-[10px] font-bold text-base-content/40 uppercase tracking-widest">Total</span>
            </div>
            <div v-else class="flex flex-col items-center justify-center h-full text-base-content/30 italic">
              <PieChart :size="40" class="mb-2 opacity-20" />
              <p>No distribution data</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Charts Row 2 -->
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
        <!-- Performance Leaderboard -->
        <div class="bg-base-100 rounded-3xl border border-base-200 overflow-hidden hover:shadow-xl transition-all duration-500 flex flex-col">
          <div class="p-8 border-b border-base-200 bg-base-50/30">
            <h3 class="font-bold text-xl flex items-center gap-3">
              <div class="w-8 h-8 rounded-lg bg-emerald-500/10 flex items-center justify-center">
                <Trophy class="text-emerald-600" :size="18" />
              </div>
              Top Performers
            </h3>
            <p class="text-xs text-base-content/40 mt-1 uppercase tracking-wider font-semibold">Most active users this period</p>
          </div>
          <div class="p-0 grow">
            <div class="overflow-x-auto">
              <table class="table table-zebra-none w-full">
                <thead>
                  <tr class="bg-base-50/50">
                    <th class="text-[10px] uppercase tracking-widest font-black text-base-content/40 py-4 px-8">Rank</th>
                    <th class="text-[10px] uppercase tracking-widest font-black text-base-content/40 py-4">User</th>
                    <th class="text-[10px] uppercase tracking-widest font-black text-base-content/40 py-4 text-center">Activities</th>
                  </tr>
                </thead>
                <tbody>
                  <tr v-for="(user, index) in reportData?.users?.top_active_users || []" :key="index" class="hover:bg-primary/5 transition-colors group">
                    <td class="py-4 px-8">
                      <div class="w-8 h-8 rounded-full bg-base-200 flex items-center justify-center font-black text-sm text-base-content/60 group-hover:bg-primary group-hover:text-white transition-all duration-300">
                        {{ index + 1 }}
                      </div>
                    </td>
                    <td class="py-4">
                      <div class="flex items-center gap-3">
                        <div class="avatar placeholder">
                          <div class="bg-neutral text-neutral-content rounded-xl w-10">
                            <span class="text-xs font-bold">{{ (user.name || 'U').charAt(0) }}</span>
                          </div>
                        </div>
                        <div>
                          <div class="font-bold text-sm text-base-content">{{ user.name || 'Unknown User' }}</div>
                          <div class="text-[11px] text-base-content/40 font-medium">Faculty/Staff</div>
                        </div>
                      </div>
                    </td>
                    <td class="py-4 text-center">
                      <div class="inline-flex items-center bg-emerald-500/10 text-emerald-600 font-bold px-3 py-1 rounded-full text-xs">
                        {{ user.activity_count }}
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <!-- Recent Activity Feed -->
        <div class="bg-base-100 rounded-3xl border border-base-200 overflow-hidden hover:shadow-xl transition-all duration-500 flex flex-col">
          <div class="p-8 border-b border-base-200 bg-base-50/30">
            <h3 class="font-bold text-xl flex items-center gap-3">
              <div class="w-8 h-8 rounded-lg bg-violet-500/10 flex items-center justify-center">
                <History class="text-violet-600" :size="18" />
              </div>
              Live Audit Trail
            </h3>
            <p class="text-xs text-base-content/40 mt-1 uppercase tracking-wider font-semibold">Latest system-wide events</p>
          </div>
          <div class="p-8 space-y-6 grow overflow-y-auto max-h-[500px] custom-scrollbar">
            <div v-for="(log, index) in reportData?.activity?.recent_logs || []" :key="index" class="flex gap-4 group">
              <div class="relative">
                <div class="w-10 h-10 rounded-xl bg-base-100 border border-base-200 flex items-center justify-center shadow-sm group-hover:border-primary/50 transition-colors relative z-10">
                  <Activity :size="18" class="text-primary/60 group-hover:text-primary" />
                </div>
                <div v-if="index !== (reportData?.activity?.recent_logs?.length - 1)" class="absolute top-10 bottom-[-24px] left-1/2 -translate-x-1/2 w-px bg-base-200"></div>
              </div>
              <div class="grow pb-6">
                <div class="flex items-center justify-between mb-1">
                  <span class="text-sm font-bold text-base-content">{{ log.actor?.full_name || 'System' }}</span>
                  <span class="text-[10px] font-bold text-base-content/30 uppercase tracking-tighter">{{ formatDate(log.created_at) }}</span>
                </div>
                <p class="text-sm text-base-content/60 leading-relaxed">{{ log.description }}</p>
                <div v-if="log.action" class="mt-2 inline-block px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-base-200 text-base-content/60">
                  {{ log.action.replace(/_/g, ' ') }}
                </div>
              </div>
            </div>
            <div v-if="!(reportData?.activity?.recent_logs?.length)" class="flex flex-col items-center justify-center py-12 text-base-content/30 italic">
              <History :size="40" class="mx-auto mb-4 opacity-20" />
              <p>No recent activity detected</p>
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
  @apply p-6 min-h-screen;
}

.dropdown a.active {
  @apply bg-primary text-white;
}

.custom-scrollbar::-webkit-scrollbar {
  width: 6px;
}

.custom-scrollbar::-webkit-scrollbar-track {
  @apply bg-base-200 rounded-full;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
  @apply bg-base-300 rounded-full;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
  @apply bg-base-content/20;
}
</style>
