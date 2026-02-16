<script setup>
import { ref } from 'vue'
import { Search, Bell, User, LogOut, Settings, Sun, Moon, Check, Camera } from 'lucide-vue-next'
import { useAuth } from '@/composables/useAuth'
import { useTheme } from '@/composables/useTheme'
import ProfilePhotoModal from '@/components/profile/ProfilePhotoModal.vue'
import AccountManagementModal from '@/components/profile/AccountManagementModal.vue'
import NotificationDropdown from '@/components/notifications/NotificationDropdown.vue'

const searchQuery = ref('')
const { user, logout } = useAuth()
const { theme, availableThemes, setTheme } = useTheme()

const showPhotoModal = ref(false)
const showAccountModal = ref(false)

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
        <NotificationDropdown />

        <!-- Theme Selector -->
        <div class="dropdown dropdown-end">
          <button tabindex="0" class="btn btn-ghost btn-circle" title="Toggle Theme">
            <Sun v-if="theme === 'dark'" :size="20" />
            <Moon v-else :size="20" />
          </button>
          <ul tabindex="0" class="dropdown-content z-2 menu p-2 shadow-2xl bg-base-100 border border-base-300 rounded-xl w-40 mt-4 max-h-[70vh] overflow-y-auto custom-scrollbar flex-nowrap">
            <li class="menu-title px-4 py-2 opacity-60 text-[10px] uppercase tracking-wider font-bold">Select Theme</li>
            <li v-for="t in ['light', 'dark']" :key="t">
              <button 
                @click="setTheme(t)"
                :class="{ 'active bg-primary text-primary-content': theme === t }"
                class="flex items-center justify-between py-2 px-4 group text-base-content"
              >
                <div class="flex items-center gap-3">
                   <Sun v-if="t === 'light'" :size="16" />
                   <Moon v-else :size="16" />
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
              <div class="w-8 h-8 rounded-full bg-primary text-primary-content flex items-center justify-center overflow-hidden">
                <img v-if="user?.profile_picture" :src="user.profile_picture" :alt="user.full_name" />
                <span v-else class="text-xs font-semibold">{{ getInitials(user?.full_name) }}</span>
              </div>
            </div>
            <div class="hidden sm:flex flex-col items-start ml-2">
              <span class="text-xs font-bold text-primary leading-none">Faculty</span>
              <span class="text-[10px] text-base-content/60 leading-tight">{{ user?.first_name }}</span>
            </div>
          </div>
          <ul tabindex="0" class="dropdown-content z-1 menu p-2 shadow-xl bg-base-100 border border-base-300 rounded-box w-52 mt-3">
            <li class="menu-title px-4 py-2 opacity-60">Account</li>
            <li><button @click="showPhotoModal = true"><Camera :size="16" /> Profile Photos</button></li>
            <li><button @click="showAccountModal = true"><Settings :size="16" /> My Account</button></li>
            <div class="divider my-0"></div>
            <li><button @click="logout" class="text-error"><LogOut :size="16" /> Logout</button></li>
          </ul>
        </div>
      </div>
    </div>

    <!-- Modals -->
    <ProfilePhotoModal 
      :is-open="showPhotoModal" 
      @close="showPhotoModal = false" 
    />
    <AccountManagementModal 
      :is-open="showAccountModal" 
      @close="showAccountModal = false" 
    />
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
  @apply focus:outline-none focus:border-primary focus:bg-base-100;
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
