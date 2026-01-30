<script setup>
import { ref } from 'vue'
import { Archive, RefreshCw, ChevronDown } from 'lucide-vue-next'

const priorityFilter = ref('All Priorities')
const sortFilter = ref('Newest')
</script>

<template>
  <div class="view-container">
    <!-- Header -->
    <div class="flex items-center justify-between mb-2">
      <div class="flex items-center gap-2 text-xl font-bold bg-transparent p-0">
        <Archive :size="20" class="text-primary" />
        <span>Archive</span>
      </div>
    </div>
    
    <!-- Refresh Button Row -->
    <div class="mb-4">
      <button class="btn btn-ghost btn-sm btn-circle text-base-content/60">
        <RefreshCw :size="18" />
      </button>
    </div>

    <!-- Filters Toolbar -->
    <div class="flex items-center justify-between mb-6 bg-base-100 p-1.5 rounded-lg border border-base-200">
      <select class="select select-sm select-ghost w-auto font-normal focus:bg-transparent">
        <option selected>All Priorities</option>
        <option>High</option>
        <option>Normal</option>
        <option>Low</option>
      </select>
      
      <select class="select select-sm select-ghost w-auto font-normal focus:bg-transparent text-right">
        <option selected>Newest</option>
        <option>Oldest</option>
      </select>
    </div>

    <!-- Empty State Box -->
    <div class="bg-base-100 rounded-xl border border-dashed border-base-300 p-12 min-h-[300px] flex flex-col items-center justify-center text-center">
      <div class="w-16 h-16 mb-4 text-base-content/20">
        <Archive :size="64" stroke-width="1.5" />
      </div>
      <h3 class="font-semibold text-base-content/70">No archived items</h3>
      <p class="text-sm text-base-content/40 mt-1">Archived memos, calendar events, and signatures will appear here</p>
    </div>
  </div>
</template>

<style scoped>
@reference "../../style.css";

.view-container {
  @apply p-0;
}
</style>
