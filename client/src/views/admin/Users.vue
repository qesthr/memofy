<script setup>
import { ref, onMounted } from 'vue'
import { Plus, Search, Pencil, Archive, Filter } from 'lucide-vue-next'

const activeTab = ref('active')
const activeFilter = ref('all')

const users = ref([])

const tabs = [
  { id: 'active', label: 'Active Users' },
  { id: 'archived', label: 'Archived Users (0)' }
]

const filters = [
  { id: 'all', label: 'All', count: 0 },
  { id: 'admin', label: 'Admin', count: 0 },
  { id: 'secretary', label: 'Secretary', count: 0 },
  { id: 'faculty', label: 'Faculty', count: 0 }
]

import api from '../../services/api'

const fetchUsers = async () => {
  try {
    const response = await api.get('/admin/users')
    // Assuming API returns paginated response, getting data array
    users.value = response.data.data
  } catch (error) {
    console.error('Error fetching users:', error)
  }
}

onMounted(() => {
  fetchUsers()
})
</script>

<template>
  <div class="view-container">
    <!-- Header -->
    <div class="flex items-center justify-between mb-8">
      <h1 class="text-2xl font-bold">Manage Users</h1>
      <button class="btn btn-primary gap-2 text-white">
        <Plus :size="20" />
        Add user
      </button>
    </div>

    <!-- Tabs -->
    <div class="border-b border-base-300 mb-6">
      <div class="flex gap-8">
        <button 
          v-for="tab in tabs" 
          :key="tab.id"
          @click="activeTab = tab.id"
          class="pb-3 text-sm font-medium transition-colors relative"
          :class="activeTab === tab.id ? 'text-primary' : 'text-base-content/60 hover:text-base-content'"
        >
          {{ tab.label }}
          <div 
            v-if="activeTab === tab.id"
            class="absolute bottom-0 left-0 w-full h-0.5 bg-primary"
          ></div>
        </button>
      </div>
    </div>

    <!-- Filters -->
    <div class="flex gap-2 mb-6">
      <button 
        v-for="filter in filters" 
        :key="filter.id"
        @click="activeFilter = filter.id"
        class="btn btn-sm"
        :class="activeFilter === filter.id ? 'btn-primary text-white' : 'btn-ghost bg-base-100 border border-base-300'"
      >
        {{ filter.label }}
        <span class="ml-1 opacity-70">{{ filter.count }}</span>
      </button>
    </div>

    <!-- Users Table -->
    <div class="bg-base-100 rounded-xl border border-base-300 overflow-hidden shadow-sm">
      <div class="overflow-x-auto">
        <table class="table w-full">
          <thead>
            <tr class="bg-base-100 border-b border-base-200 text-base-content/60">
              <th class="py-4 font-semibold pl-6">Name</th>
              <th class="py-4 font-semibold">Department</th>
              <th class="py-4 font-semibold">Role</th>
              <th class="py-4 font-semibold pr-6 text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="user in users" :key="user.id" class="hover:bg-base-50/50 border-b border-base-100 last:border-0">
              <td class="py-4 pl-6">
                <div class="flex items-center gap-3">
                  <div class="avatar placeholder">
                    <div class="bg-primary text-primary-content rounded-full w-10">
                      <span class="text-xs">{{ user.name.charAt(0) }}</span>
                    </div>
                  </div>
                  <div>
                    <div class="font-bold">{{ user.name }}</div>
                    <div class="text-xs text-base-content/60">{{ user.email }}</div>
                  </div>
                </div>
              </td>
              <td class="py-4 text-base-content/80">{{ user.department }}</td>
              <td class="py-4">
                <span class="badge border-none py-3 px-4 font-medium" :class="user.roleColor">
                  {{ user.role }}
                </span>
              </td>
              <td class="py-4 pr-6">
                <div class="flex items-center justify-end gap-2">
                  <button class="btn btn-ghost btn-sm btn-square text-primary bg-blue-50 hover:bg-blue-100">
                    <Pencil :size="16" />
                  </button>
                  <button class="btn btn-ghost btn-sm btn-square text-orange-500 bg-orange-50 hover:bg-orange-100">
                    <Archive :size="16" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<style scoped>
@reference "../../style.css";

.view-container {
  @apply p-0; /* Content padding handled by layout */
}
</style>
