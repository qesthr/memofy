<script setup>
import { ref } from 'vue'
import { Download, ChevronDown, Link, Users, FileText, Calendar, Activity } from 'lucide-vue-next'

const timePeriod = ref('Last 30 days')
const activityFilter = ref('All Activities')

const stats = [
  { label: 'Total Users', value: '-', icon: Users, color: 'text-blue-500', bg: 'bg-blue-50' },
  { label: 'Total Memos', value: '-', icon: FileText, color: 'text-orange-500', bg: 'bg-orange-50' },
  { label: 'Calendar Events', value: '-', icon: Calendar, color: 'text-blue-600', bg: 'bg-blue-50' },
  { label: 'Active Users', value: '-', icon: Activity, color: 'text-emerald-500', bg: 'bg-emerald-50' },
]
</script>

<template>
  <div class="view-container">
    <!-- Header -->
    <div class="flex items-center justify-between mb-8">
      <div>
        <h1 class="text-2xl font-bold mb-1">Reports & Analytics</h1>
        <p class="text-base-content/60 text-sm">Database statistics and Google Analytics insights</p>
      </div>
      <button class="btn btn-primary text-white btn-sm gap-2">
        <Download :size="16" />
        Export Report
      </button>
    </div>

    <!-- Time Filter -->
    <div class="bg-base-100 rounded-xl border border-base-200 p-4 mb-6 flex items-center gap-4">
      <span class="font-semibold text-sm">Time Period:</span>
      <div class="relative">
        <select v-model="timePeriod" class="select select-bordered select-sm w-40">
          <option>Last 30 days</option>
          <option>Last 7 days</option>
          <option>This Year</option>
        </select>
      </div>
    </div>

    <!-- Google Analytics Connect -->
    <div class="bg-blue-50/50 rounded-xl border border-blue-100 p-6 mb-8">
      <button class="btn btn-primary btn-sm gap-2 text-white">
        <Link :size="16" />
        Connect Google Analytics
      </button>
    </div>

    <!-- Stats Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
      <div v-for="(stat, index) in stats" :key="index" class="bg-base-100 rounded-xl border border-base-200 p-6 flex items-center gap-4">
        <div class="w-12 h-12 rounded-lg flex items-center justify-center" :class="stat.bg">
          <component :is="stat.icon" :size="24" :class="stat.color" />
        </div>
        <div>
          <div class="text-xl font-bold mb-0.5">{{ stat.value }}</div>
          <div class="text-xs text-base-content/60">{{ stat.label }}</div>
          <div class="h-1 w-2 rounded-full bg-emerald-500 mt-1" v-if="stat.value === '-'"></div>
        </div>
      </div>
    </div>

    <!-- Charts Section -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
      <!-- User Activity Chart -->
      <div class="bg-base-100 rounded-xl border border-base-200 p-6 min-h-[300px]">
        <div class="flex items-center justify-between mb-6">
          <h3 class="font-bold">User Activity Over Time</h3>
          <select v-model="activityFilter" class="select select-ghost select-xs">
            <option>All Activities</option>
          </select>
        </div>
        <div class="h-48 flex items-center justify-center border-b border-base-100">
          <!-- Chart Placeholder -->
        </div>
      </div>

      <!-- Memo Stats Chart -->
      <div class="bg-base-100 rounded-xl border border-base-200 p-6 min-h-[300px]">
        <div class="flex items-center justify-between mb-6">
          <h3 class="font-bold">Memo Statistics</h3>
        </div>
        <div class="h-48 flex items-center justify-center border-b border-base-100">
           <!-- Chart Placeholder -->
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
