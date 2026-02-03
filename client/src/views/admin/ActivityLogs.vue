<script setup>
import { ref, onMounted } from 'vue'
import { Download, Search, Users, Shield, Calendar as CalendarIcon, FileText } from 'lucide-vue-next'

const logs = ref([])

import api from '../../services/api'

const fetchLogs = async () => {
  try {
    const response = await api.get('/admin/activity-logs')
    logs.value = response.data.data
  } catch (error) {
    console.error('Error fetching logs:', error)
  }
}

onMounted(() => {
  fetchLogs()
})
</script>

<template>
  <div class="view-container">
    <!-- Header -->
    <div class="flex items-center justify-between mb-6">
      <h1 class="text-2xl font-bold">Activity Logs</h1>
      <button class="btn btn-primary text-white btn-sm gap-2">
        <Download :size="16" />
        Export CSV
      </button>
    </div>

    <!-- Filters -->
    <div class="bg-base-100 rounded-xl border border-base-200 p-6 mb-6">
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-4">
        <!-- Search -->
        <div class="form-control">
          <label class="label py-1"><span class="label-text text-xs font-semibold">Search</span></label>
          <input type="text" placeholder="Search activities..." class="input input-sm input-bordered w-full" />
        </div>
        
        <!-- Actor Role -->
        <div class="form-control">
          <label class="label py-1"><span class="label-text text-xs font-semibold">Actor Role</span></label>
          <select class="select select-sm select-bordered w-full font-normal">
            <option selected>All Roles</option>
          </select>
        </div>

        <!-- Action Type -->
        <div class="form-control">
          <label class="label py-1"><span class="label-text text-xs font-semibold">Action Type</span></label>
          <select class="select select-sm select-bordered w-full font-normal">
            <option selected>All Actions</option>
          </select>
        </div>

        <!-- Target Resource -->
        <div class="form-control">
          <label class="label py-1"><span class="label-text text-xs font-semibold">Target Resource</span></label>
          <select class="select select-sm select-bordered w-full font-normal">
            <option selected>All Resources</option>
          </select>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
        <!-- Start Date -->
        <div class="form-control">
          <label class="label py-1"><span class="label-text text-xs font-semibold">Start Date</span></label>
          <input type="date" class="input input-sm input-bordered w-full font-normal text-base-content/60" placeholder="mm/dd/yyyy" />
        </div>

        <!-- End Date -->
        <div class="form-control">
          <label class="label py-1"><span class="label-text text-xs font-semibold">End Date</span></label>
          <input type="date" class="input input-sm input-bordered w-full font-normal text-base-content/60" value="2026-01-30" />
        </div>

        <!-- Buttons -->
        <div class="flex gap-2">
          <button class="btn btn-primary btn-sm text-white px-6">Apply Filters</button>
          <button class="btn btn-ghost btn-sm bg-base-200">Clear</button>
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="bg-base-100 rounded-xl border border-base-200 overflow-hidden">
      <div class="overflow-x-auto">
        <table class="table w-full">
          <thead>
            <tr class="text-xs uppercase bg-base-100 border-b border-base-200 text-base-content/60">
              <th class="py-4 font-semibold pl-6">Timestamp</th>
              <th class="py-4 font-semibold">Actor</th>
              <th class="py-4 font-semibold">Action</th>
              <th class="py-4 font-semibold">Description</th>
              <th class="py-4 font-semibold pr-6 text-right">Target</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="log in logs" :key="log.id" class="hover:bg-slate-50/50 border-b border-base-100 last:border-0 text-sm">
              <td class="py-4 pl-6 text-base-content/60 font-mono text-xs">{{ log.timestamp }}</td>
              <td class="py-4">
                <div class="flex items-center gap-3">
                  <div class="avatar placeholder">
                    <div class="bg-primary/10 text-primary rounded-full w-8 h-8 flex items-center justify-center">
                      <Shield :size="14" v-if="log.role === 'Admin'" />
                      <Users :size="14" v-else />
                    </div>
                  </div>
                  <div>
                    <div class="font-semibold text-xs">{{ log.actor }}</div>
                    <div class="text-[10px] text-base-content/60">{{ log.email }}</div>
                  </div>
                </div>
              </td>
              <td class="py-4">
                <span class="badge badge-sm border-none bg-emerald-100 text-emerald-700 font-medium px-2 py-2.5 text-xs">
                  {{ log.action }}
                </span>
              </td>
              <td class="py-4 text-base-content/70">{{ log.description }}</td>
              <td class="py-4 pr-6 text-right text-base-content/40">{{ log.target }}</td>
            </tr>
          </tbody>
        </table>
      </div>
      
      <!-- Pagination -->
      <div class="p-4 border-t border-base-200 flex items-center justify-between">
        <span class="text-xs text-base-content/60">Page 1 of 1 (3 total)</span>
        <div class="join">
          <button class="join-item btn btn-xs btn-ghost" disabled>Previous</button>
          <button class="join-item btn btn-xs btn-ghost" disabled>Next</button>
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
