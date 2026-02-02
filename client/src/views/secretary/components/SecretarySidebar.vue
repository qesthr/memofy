<script setup>
import { ref, onMounted } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import { useLogout } from '@/composables/useLogout'
import { 
  LayoutDashboard, 
  FileText, 
  Archive, 
  Calendar, 
  Settings,
  LogOut
} from 'lucide-vue-next'

const router = useRouter()
const route = useRoute()
const { logout } = useLogout()

const userName = ref('--')
const userRole = ref('Department Secretary')
const userInitial = ref('S')
const userEmail = ref('secretary@buksu.edu.ph')

const menuItems = [
  { name: 'Dashboard', path: '/secretary/dashboard', icon: LayoutDashboard },
  { name: 'Memos', path: '/secretary/memos', icon: FileText },
  { name: 'Archive', path: '/secretary/archive', icon: Archive },
  { name: 'Calendar', path: '/secretary/calendar', icon: Calendar }
]

const bottomItems = [
  { name: 'Settings', path: '/secretary/settings', icon: Settings },
  { name: 'Logout', path: '/logout', icon: LogOut }
]

const handleLogout = () => {
  logout()
}

onMounted(() => {
  const user = JSON.parse(localStorage.getItem('user'))
  if (user) {
    userName.value = user.first_name + ' ' + user.last_name
    // Force specific role display as requested by design
    userRole.value = 'Department Secretary' 
    userInitial.value = user.first_name.charAt(0)
    userEmail.value = user.email
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
        <li v-for="item in menuItems" :key="item.path">
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
        <li v-for="item in bottomItems" :key="item.path">
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

      <!-- Secretary Profile -->
      <div class="secretary-profile border-t border-base-300 pt-4">
        <div class="flex items-center gap-3">
            <div class="avatar">
              <div class="w-10 h-10 rounded-full bg-primary text-primary-content flex items-center justify-center">
                <span class="text-sm font-semibold">{{ userInitial }}</span>
              </div>
            </div>
            <div class="profile-info">
              <p class="font-bold text-sm text-base-content leading-tight">Secretary</p>
              <p class="text-xs text-base-content/60 truncate w-32" :title="userEmail">{{ userEmail }}</p>
            </div>
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
  z-index: 50;
}

.logo-section {
  @apply px-6 py-6 border-b border-base-300;
}

.nav-section {
  @apply flex-1 py-6;
}

.menu-list {
  @apply space-y-1 px-4;
}

.menu-item {
  @apply flex items-center gap-3 px-4 py-3 rounded-lg;
  @apply text-base-content/70 hover:bg-base-200 hover:text-base-content;
  @apply transition-all duration-200 font-medium;
  @apply cursor-pointer w-full text-left;
  border: none;
  background: none;
}

.menu-item.active {
  @apply bg-primary text-primary-content;
  @apply relative font-bold;
}

.menu-item.active::before {
  content: '';
  position: absolute;
  left: 0;
  top: 50%;
  transform: translateY(-50%);
  height: 20px;
  width: 4px;
  @apply bg-primary-content rounded-r-lg;
}

.logout-btn {
  @apply text-error/70 hover:text-error hover:bg-error/10;
}

.bottom-section {
  @apply py-4 border-t border-base-300;
}

.secretary-profile {
  @apply mt-6 px-6;
}

.profile-info {
  @apply flex-1 min-w-0;
}
</style>
