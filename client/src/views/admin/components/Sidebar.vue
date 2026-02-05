<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useLogout } from '@/composables/useLogout'
import { useAuth } from '@/composables/useAuth'
import {
  LayoutDashboard,
  Users,
  FileText,
  Archive,
  Calendar,
  FileBarChart,
  Activity,
  Settings,
  LogOut,
  Shield
} from 'lucide-vue-next'

const router = useRouter()
const route = useRoute()
const { logout } = useLogout()
const { can } = useAuth()

const userName = ref('--')
const userRole = ref('Administrator')
const userInitial = ref('A')

const menuItems = [
  { name: 'Dashboard', path: '/admin/dashboard', icon: LayoutDashboard },
  { name: 'Users', path: '/admin/users', icon: Users, permission: 'nav.users' },
  { name: 'Roles & Permissions', path: '/admin/roles', icon: Shield, permission: 'nav.roles' },
  { name: 'Memos', path: '/admin/memos', icon: FileText, permission: 'nav.memos' },
  { name: 'Archive', path: '/admin/archive', icon: Archive, permission: 'nav.archive' },
  { name: 'Calendar', path: '/admin/calendar', icon: Calendar, permission: 'nav.calendar' },
  { name: 'Report', path: '/admin/report', icon: FileBarChart, permission: 'nav.reports' },
  { name: 'Activity Logs', path: '/admin/activity-logs', icon: Activity, permission: 'nav.activity_logs' }
]

const filteredMenuItems = computed(() => {
  return menuItems.filter(item => !item.permission || can(item.permission))
})

const bottomItems = [
  { name: 'Settings', path: '/admin/settings', icon: Settings, permission: 'nav.settings' },
  { name: 'Logout', path: '/logout', icon: LogOut }
]

const filteredBottomItems = computed(() => {
  return bottomItems.filter(item => !item.permission || can(item.permission))
})

const handleLogout = () => {
  logout()
}

onMounted(() => {
  const user = JSON.parse(localStorage.getItem('user'))
  if (user) {
    userName.value = user.first_name + ' ' + user.last_name
    userRole.value = user.role.charAt(0).toUpperCase() + user.role.slice(1)
    userInitial.value = user.first_name.charAt(0)
  }
})
</script>

<template>
  <aside class="sidebar">
    <!-- Logo Section -->
    <div class="logo-section">
      <div class="flex items-center gap-3">
        <img src="@/assets/images/images/memofy-logo.png" alt="Memofy Logo" class="w-8 h-8" />
        <span class="text-xl font-bold text-primary">Memofy</span>
      </div>
    </div>

    <!-- Main Navigation -->
    <nav class="nav-section">
      <ul class="menu-list">
        <li v-for="item in filteredMenuItems" :key="item.path">
          <router-link
            :to="item.path"
            class="menu-item"
            :class="{ 'active': route.path === item.path }"
          >
            <component :is="item.icon" :size="20" :stroke-width="2" />
            <span>{{ item.name }}</span>
          </router-link>
        </li>
      </ul>
    </nav>

    <!-- Bottom Section -->
    <div class="bottom-section">
      <ul class="menu-list">
        <li v-for="item in filteredBottomItems" :key="item.path">
          <router-link
            v-if="item.name !== 'Logout'"
            :to="item.path"
            class="menu-item"
            :class="{ 'active': route.path === item.path }"
          >
            <component :is="item.icon" :size="20" :stroke-width="2" />
            <span>{{ item.name }}</span>
          </router-link>
          <button
            v-else
            @click="handleLogout"
            class="menu-item logout-btn"
          >
            <component :is="item.icon" :size="20" :stroke-width="2" />
            <span>{{ item.name }}</span>
          </button>
        </li>
      </ul>

      <!-- Admin Profile -->
      <div class="admin-profile">
        <div class="avatar">
          <div class="w-10 h-10 rounded-full bg-primary text-primary-content flex items-center justify-center">
            <span class="text-sm font-semibold">{{ userInitial }}</span>
          </div>
        </div>
        <div class="profile-info">
          <p class="font-semibold text-sm">{{ userName }}</p>
          <p class="text-xs text-base-content/60">{{ userRole }}</p>
        </div>
      </div>
    </div>
  </aside>
</template>

<style scoped>
@reference "../../../style.css";

.sidebar {
  @apply fixed left-0 top-0 h-screen w-64 bg-base-100 border-r border-base-300;
  @apply flex flex-col;
  overflow: hidden;
}

.logo-section {
  @apply px-6 py-5 border-b border-base-300;
  flex-shrink: 0;
}

.nav-section {
  @apply flex-1 py-4;
  overflow-y: auto;
  scrollbar-width: thin;
}

.nav-section::-webkit-scrollbar {
  width: 4px;
}

.nav-section::-webkit-scrollbar-track {
  background: transparent;
}

.nav-section::-webkit-scrollbar-thumb {
  background: hsl(var(--bc) / 0.2);
  border-radius: 2px;
}

.bottom-section {
  @apply border-t border-base-300 py-4;
  flex-shrink: 0;
}

.menu-list {
  @apply space-y-1 px-3;
}

.menu-item {
  @apply flex items-center gap-3 px-4 py-3 rounded-lg;
  @apply text-base-content/70 hover:bg-base-200 hover:text-base-content;
  @apply transition-all duration-200;
  @apply cursor-pointer w-full text-left;
  border: none;
  background: none;
  font-size: 0.9rem;
}

.menu-item.active {
  @apply bg-primary text-primary-content;
  @apply font-medium;
}

.menu-item:hover:not(.active) {
  @apply bg-base-200;
}

.logout-btn {
  @apply text-error/70 hover:bg-error/10 hover:text-error;
}

.admin-profile {
  @apply flex items-center gap-3 px-6 py-3 mt-2;
  @apply border-t border-base-300;
}

.profile-info {
  @apply flex-1 min-w-0;
}
</style>
