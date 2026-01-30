<script setup>
import { ref } from 'vue'
import { Search, Bell } from 'lucide-vue-next'

const searchQuery = ref('')
</script>

<template>
  <header class="navbar">
    <div class="navbar-content">
      <!-- Search Bar -->
      <div class="search-container">
        <Search :size="18" class="search-icon" />
        <input 
          v-model="searchQuery"
          type="text" 
          placeholder="Search dashboard..." 
          class="search-input"
        />
      </div>

      <!-- Right Section -->
      <div class="navbar-right">
        <!-- Notifications -->
        <button class="btn btn-ghost btn-circle">
          <div class="indicator">
            <Bell :size="20" />
          </div>
        </button>

        <!-- Admin Badge -->
        <div class="admin-badge">
          <div class="avatar">
            <div class="w-8 h-8 rounded-full bg-primary text-primary-content flex items-center justify-center">
              <span class="text-xs font-semibold">SA</span>
            </div>
          </div>
          <span class="admin-text">Admin</span>
        </div>
      </div>
    </div>
  </header>
</template>

<style scoped>
@reference "../style.css";

.navbar {
  @apply fixed top-0 right-0 h-16 bg-base-100 border-b border-base-300 z-10;
  width: calc(100% - 16rem);
  margin-left: 16rem;
}

.navbar-content {
  @apply h-full px-6 flex items-center justify-between;
}

.search-container {
  @apply relative flex-1 max-w-md;
}

.search-icon {
  @apply absolute left-3 top-1/2 -translate-y-1/2 text-base-content/40;
}

.search-input {
  @apply w-full pl-10 pr-4 py-2 rounded-lg;
  @apply bg-base-200 border border-transparent;
  @apply focus:outline-none focus:border-primary focus:bg-base-100;
  @apply transition-all duration-200;
  @apply text-sm;
}

.search-input::placeholder {
  @apply text-base-content/40;
}

.navbar-right {
  @apply flex items-center gap-3;
}

.admin-badge {
  @apply flex items-center gap-2 px-3 py-1.5 rounded-lg bg-base-200;
}

.admin-text {
  @apply text-sm font-medium text-primary;
}
</style>
