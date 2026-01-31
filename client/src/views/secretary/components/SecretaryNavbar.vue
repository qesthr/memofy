<script setup>
import { ref } from 'vue'
import { Search, Bell, User, LogOut, Settings, Palette, Check } from 'lucide-vue-next'
import { useAuth } from '@/composables/useAuth'
import { useTheme } from '@/composables/useTheme'

const searchQuery = ref('')
const { user, logout } = useAuth()
const { theme, availableThemes, setTheme } = useTheme()

const getInitials = (name) => {
  if (!name) return '?'
  return name.split(' ').map(n => n[0]).join('').toUpperCase().substring(0, 2)
}
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
          placeholder="Search..." 
          class="search-input"
        />
      </div>

      <!-- Right Section -->
      <div class="navbar-right">
        <!-- Notifications -->
        <div class="dropdown dropdown-end">
          <button tabindex="0" class="btn btn-ghost btn-circle">
            <div class="indicator">
              <Bell :size="20" />
              <span class="badge badge-xs badge-info indicator-item"></span>
            </div>
          </button>
          <div tabindex="0" class="dropdown-content z-[1] mt-3 w-80 card card-compact bg-base-100 shadow-xl border border-base-300">
            <div class="card-body">
              <h3 class="font-bold text-lg">Notifications</h3>
              <div class="py-4 text-center text-sm text-base-content/60">
                No new notifications
              </div>
              <div class="card-actions">
                <button class="btn btn-info btn-block btn-sm">View all</button>
              </div>
            </div>
          </div>
        </div>

        <!-- Theme Selector -->
        <div class="dropdown dropdown-end">
          <button tabindex="0" class="btn btn-ghost btn-circle" title="Change Theme">
            <Palette :size="20" />
          </button>
          <ul tabindex="0" class="dropdown-content z-[2] menu p-2 shadow-2xl bg-base-100 border border-base-300 rounded-xl w-56 mt-4 max-h-[70vh] overflow-y-auto custom-scrollbar flex-nowrap">
            <li class="menu-title px-4 py-2 opacity-60 text-[10px] uppercase tracking-wider font-bold">Select Theme</li>
            <li v-for="t in availableThemes" :key="t">
              <button 
                @click="setTheme(t)"
                :class="{ 'active bg-primary text-primary-content': theme === t }"
                class="flex items-center justify-between py-2 px-4 group text-base-content"
                :data-theme="t"
              >
                <div class="flex items-center gap-3">
                   <div class="flex gap-0.5">
                      <div class="w-2 h-4 rounded-full bg-primary"></div>
                      <div class="w-2 h-4 rounded-full bg-secondary"></div>
                      <div class="w-2 h-4 rounded-full bg-accent"></div>
                      <div class="w-2 h-4 rounded-full bg-neutral"></div>
                   </div>
                   <span class="capitalize text-sm font-medium">{{ t }}</span>
                </div>
                <Check v-if="theme === t" :size="14" />
              </button>
            </li>
          </ul>
        </div>

        <!-- Profile Dropdown -->
        <div class="dropdown dropdown-end">
          <div tabindex="0" role="button" class="user-badge cursor-pointer">
            <div class="avatar online">
              <div class="w-8 h-8 rounded-full bg-info text-info-content flex items-center justify-center overflow-hidden">
                <img v-if="user?.profile_picture" :src="user.profile_picture" :alt="user.full_name" />
                <span v-else class="text-xs font-semibold">{{ getInitials(user?.full_name) }}</span>
              </div>
            </div>
            <div class="hidden sm:flex flex-col items-start ml-2">
              <span class="text-xs font-bold text-info leading-none">Secretary</span>
              <span class="text-[10px] text-base-content/60 leading-tight">{{ user?.first_name }}</span>
            </div>
          </div>
          <ul tabindex="0" class="dropdown-content z-[1] menu p-2 shadow-xl bg-base-100 border border-base-300 rounded-box w-52 mt-3">
            <li class="menu-title px-4 py-2 opacity-60">Account</li>
            <li><router-link to="/secretary/settings"><User :size="16" /> My Profile</router-link></li>
            <li><router-link to="/secretary/settings"><Settings :size="16" /> Settings</router-link></li>
            <div class="divider my-0"></div>
            <li><button @click="logout" class="text-error"><LogOut :size="16" /> Sign Out</button></li>
          </ul>
        </div>
      </div>
    </div>
  </header>
</template>

<style scoped>
@reference "../../../style.css";

.navbar {
  @apply fixed top-0 right-0 h-16 bg-base-100 border-b border-base-300 z-10;
  width: calc(100% - 16rem);
  margin-left: 16rem;
}

.navbar-content {
  @apply h-full w-full px-6 flex items-center justify-between;
}

.search-container {
  @apply relative w-full max-w-md;
}

.search-icon {
  @apply absolute left-3 top-1/2 -translate-y-1/2 text-base-content/40;
}

.search-input {
  @apply w-full pl-10 pr-4 py-2 rounded-lg;
  @apply bg-base-200 border border-transparent;
  @apply focus:outline-none focus:border-info focus:bg-base-100;
  @apply transition-all duration-200;
  @apply text-sm;
}

.search-input::placeholder {
  @apply text-base-content/40;
}

.navbar-right {
  @apply flex items-center gap-2;
}

.user-badge {
  @apply flex items-center px-2 py-1.5 rounded-lg hover:bg-base-200 transition-colors;
}
</style>
